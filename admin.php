<?php
require 'config.php';

$error = '';
$message = '';

// Handle logout
if (isset($_GET['logout'])) {
    unset($_SESSION['admin_id']);
    unset($_SESSION['admin_username']);
    session_destroy();
    header('Location: admin.php');
    exit;
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if (!empty($username) && !empty($password)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
            $stmt->execute([$username]);
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($admin && password_verify($password, $admin['password'])) {
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_username'] = $admin['username'];
                header('Location: admin.php');
                exit;
            } else {
                $error = "Invalid username or password";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    } else {
        $error = "Please enter both username and password";
    }
}

// Check if admin is logged in
$is_logged_in = isset($_SESSION['admin_id']);

// Handle admin actions (only if logged in)
if ($is_logged_in) {
    // Add Category
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_category') {
        $name = trim($_POST['name'] ?? '');
        
        if (!empty($name)) {
            try {
                $slug = simple_slugify($name);
                // Check if category already exists
                $checkStmt = $pdo->prepare("SELECT id FROM categories WHERE name = ? OR slug = ?");
                $checkStmt->execute([$name, $slug]);
                if ($checkStmt->fetch()) {
                    $error = "Category already exists!";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)");
                    $stmt->execute([$name, $slug]);
                    $message = "Category '$name' added successfully!";
                }
            } catch (PDOException $e) {
                $error = "Error adding category: " . $e->getMessage();
                error_log("Category add error: " . $e->getMessage());
            }
        } else {
            $error = "Category name cannot be empty";
        }
    }
    
    // Edit Category
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_category') {
        $category_id = (int)($_POST['category_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        
        if (!empty($name) && $category_id > 0) {
            try {
                $slug = simple_slugify($name);
                // Check if category already exists (excluding current category)
                $checkStmt = $pdo->prepare("SELECT id FROM categories WHERE (name = ? OR slug = ?) AND id != ?");
                $checkStmt->execute([$name, $slug, $category_id]);
                if ($checkStmt->fetch()) {
                    $error = "Category name already exists!";
                } else {
                    $stmt = $pdo->prepare("UPDATE categories SET name = ?, slug = ? WHERE id = ?");
                    $stmt->execute([$name, $slug, $category_id]);
                    $message = "Category updated successfully!";
                }
            } catch (PDOException $e) {
                $error = "Error updating category: " . $e->getMessage();
            }
        } else {
            $error = "Category name cannot be empty";
        }
    }
    
    // Delete Category
    if (isset($_GET['action']) && $_GET['action'] === 'delete_category' && isset($_GET['id'])) {
        $category_id = (int)$_GET['id'];
        
        try {
            // Check if category has products
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
            $checkStmt->execute([$category_id]);
            $product_count = $checkStmt->fetchColumn();
            
            if ($product_count > 0) {
                $error = "Cannot delete category with products! Please delete or move the products first.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
                $stmt->execute([$category_id]);
                $message = "Category deleted successfully!";
            }
        } catch (PDOException $e) {
            $error = "Error deleting category: " . $e->getMessage();
        }
    }
    
    // Add Product
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_product') {
        $name = trim($_POST['name'] ?? '');
        $category_id = (int)($_POST['category_id'] ?? 0);
        $price = (float)($_POST['price'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        
        if (!empty($name) && $category_id > 0 && $price > 0) {
            try {
                // Check total upload size first
                $total_upload_size = getTotalUploadSize($_FILES);
                if ($total_upload_size > MAX_TOTAL_UPLOAD_SIZE) {
                    $error = "Total upload size exceeds maximum allowed limit of " . (MAX_TOTAL_UPLOAD_SIZE / 1024 / 1024) . "MB";
                } else {
                    $slug = simple_slugify($name);
                    $images = [];
                    
                    // Handle main image upload
                    if (!empty($_FILES['main_image']['name'])) {
                        $main_file = $_FILES['main_image'];
                        $file_errors = validateFile($main_file, true);
                        
                        if (!empty($file_errors)) {
                            $error = implode(', ', $file_errors);
                        } else {
                            $file_name = $main_file['name'];
                            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                            $new_filename = uniqid('main_') . '.' . $file_ext;
                            $upload_path = 'uploads/' . $new_filename;
                            
                            if (safeMoveUploadedFile($main_file['tmp_name'], $upload_path)) {
                                $images[] = $new_filename;
                            } else {
                                $error = "Failed to upload main image. Check directory permissions.";
                            }
                        }
                    } else {
                        $error = "Main product image is required";
                    }
                    
                    // Handle additional images upload only if no error so far
                    if (empty($error) && !empty($_FILES['additional_images']['name'][0])) {
                        foreach ($_FILES['additional_images']['tmp_name'] as $key => $tmp_name) {
                            if ($_FILES['additional_images']['error'][$key] === UPLOAD_ERR_OK) {
                                $additional_file = [
                                    'name' => $_FILES['additional_images']['name'][$key],
                                    'type' => $_FILES['additional_images']['type'][$key],
                                    'tmp_name' => $tmp_name,
                                    'error' => $_FILES['additional_images']['error'][$key],
                                    'size' => $_FILES['additional_images']['size'][$key]
                                ];
                                
                                $file_errors = validateFile($additional_file);
                                if (empty($file_errors)) {
                                    $file_name = $additional_file['name'];
                                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                                    $new_filename = uniqid('add_') . '.' . $file_ext;
                                    $upload_path = 'uploads/' . $new_filename;
                                    
                                    if (safeMoveUploadedFile($tmp_name, $upload_path)) {
                                        $images[] = $new_filename;
                                    }
                                } else {
                                    // Log the error but continue with other files
                                    error_log("Additional image upload error: " . implode(', ', $file_errors));
                                }
                            }
                        }
                    }
                    
                    if (empty($images) && empty($error)) {
                        $error = "Please upload at least the main product image";
                    }
                    
                    if (empty($error)) {
                        $images_str = implode(',', $images);
                        $stmt = $pdo->prepare("INSERT INTO products (category_id, name, slug, description, price, images) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$category_id, $name, $slug, $description, $price, $images_str]);
                        $message = "Product '$name' added successfully!";
                    }
                }
            } catch (PDOException $e) {
                $error = "Error adding product: " . $e->getMessage();
                error_log("Product add error: " . $e->getMessage());
                
                // Clean up uploaded files if database operation failed
                if (!empty($images)) {
                    foreach ($images as $image) {
                        if (file_exists('uploads/' . $image)) {
                            @unlink('uploads/' . $image);
                        }
                    }
                }
            }
        } else {
            $error = "Please fill all required fields with valid data";
        }
    }
    
    // Edit Product
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_product') {
        $product_id = (int)($_POST['product_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $category_id = (int)($_POST['category_id'] ?? 0);
        $price = (float)($_POST['price'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $existing_images = $_POST['existing_images'] ?? [];
        
        if (!empty($name) && $category_id > 0 && $price > 0 && $product_id > 0) {
            try {
                // Check total upload size first
                $total_upload_size = getTotalUploadSize($_FILES);
                if ($total_upload_size > MAX_TOTAL_UPLOAD_SIZE) {
                    $error = "Total upload size exceeds maximum allowed limit of " . (MAX_TOTAL_UPLOAD_SIZE / 1024 / 1024) . "MB";
                } else {
                    $slug = simple_slugify($name);
                    $images = is_array($existing_images) ? $existing_images : [];
                    $new_images = [];
                    
                    // Handle new main image upload
                    if (!empty($_FILES['main_image']['name'])) {
                        $main_file = $_FILES['main_image'];
                        $file_errors = validateFile($main_file);
                        
                        if (!empty($file_errors)) {
                            $error = implode(', ', $file_errors);
                        } else {
                            $file_name = $main_file['name'];
                            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                            $new_filename = uniqid('main_') . '.' . $file_ext;
                            $upload_path = 'uploads/' . $new_filename;
                            
                            if (safeMoveUploadedFile($main_file['tmp_name'], $upload_path)) {
                                $new_images[] = $new_filename;
                                // Replace the first image (main image)
                                if (!empty($images)) {
                                    // Delete old main image
                                    $old_main_image = $images[0];
                                    if (file_exists('uploads/' . $old_main_image)) {
                                        @unlink('uploads/' . $old_main_image);
                                    }
                                    $images[0] = $new_filename;
                                } else {
                                    $images[] = $new_filename;
                                }
                            } else {
                                $error = "Failed to upload main image";
                            }
                        }
                    }
                    
                    // Handle new additional images upload
                    if (empty($error) && !empty($_FILES['additional_images']['name'][0])) {
                        foreach ($_FILES['additional_images']['tmp_name'] as $key => $tmp_name) {
                            if ($_FILES['additional_images']['error'][$key] === UPLOAD_ERR_OK) {
                                $additional_file = [
                                    'name' => $_FILES['additional_images']['name'][$key],
                                    'type' => $_FILES['additional_images']['type'][$key],
                                    'tmp_name' => $tmp_name,
                                    'error' => $_FILES['additional_images']['error'][$key],
                                    'size' => $_FILES['additional_images']['size'][$key]
                                ];
                                
                                $file_errors = validateFile($additional_file);
                                if (empty($file_errors)) {
                                    $file_name = $additional_file['name'];
                                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                                    $new_filename = uniqid('add_') . '.' . $file_ext;
                                    $upload_path = 'uploads/' . $new_filename;
                                    
                                    if (safeMoveUploadedFile($tmp_name, $upload_path)) {
                                        $new_images[] = $new_filename;
                                        $images[] = $new_filename;
                                    }
                                }
                            }
                        }
                    }
                    
                    if (empty($images)) {
                        $error = "Product must have at least one image";
                    } else if (empty($error)) {
                        $images_str = implode(',', $images);
                        $stmt = $pdo->prepare("UPDATE products SET category_id = ?, name = ?, slug = ?, description = ?, price = ?, images = ? WHERE id = ?");
                        $stmt->execute([$category_id, $name, $slug, $description, $price, $images_str, $product_id]);
                        $message = "Product '$name' updated successfully!";
                    }
                    
                    // Clean up new images if there was an error
                    if (!empty($error) && !empty($new_images)) {
                        foreach ($new_images as $image) {
                            if (file_exists('uploads/' . $image)) {
                                @unlink('uploads/' . $image);
                            }
                        }
                    }
                }
            } catch (PDOException $e) {
                $error = "Error updating product: " . $e->getMessage();
                error_log("Product update error: " . $e->getMessage());
            }
        } else {
            $error = "Please fill all required fields with valid data";
        }
    }
    
    // Delete Product
    if (isset($_GET['action']) && $_GET['action'] === 'delete_product' && isset($_GET['id'])) {
        $product_id = (int)$_GET['id'];
        
        try {
            // Get product images to delete files
            $stmt = $pdo->prepare("SELECT images FROM products WHERE id = ?");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
            
            if ($product) {
                $images = explode(',', $product['images']);
                // Delete image files
                foreach ($images as $image) {
                    if (file_exists('uploads/' . $image)) {
                        @unlink('uploads/' . $image);
                    }
                }
                
                // Delete product from database
                $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
                $stmt->execute([$product_id]);
                $message = "Product deleted successfully!";
            }
        } catch (PDOException $e) {
            $error = "Error deleting product: " . $e->getMessage();
        }
    }
    
    // Create New Admin
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_admin') {
        $new_username = trim($_POST['new_username'] ?? '');
        $new_password = trim($_POST['new_password'] ?? '');
        
        if (!empty($new_username) && !empty($new_password)) {
            try {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
                $stmt->execute([$new_username, $hashed_password]);
                $message = "New admin '$new_username' created successfully!";
            } catch (PDOException $e) {
                $error = "Error creating admin: " . $e->getMessage();
            }
        } else {
            $error = "Please enter both username and password for new admin";
        }
    }
}

// Get categories and products for display
if ($is_logged_in) {
    try {
        $categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
        $products = $pdo->query("
            SELECT p.*, c.name as category_name 
            FROM products p 
            JOIN categories c ON p.category_id = c.id 
            ORDER BY p.created_at DESC
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        // Get product counts per category
        $category_counts = [];
        foreach ($categories as $category) {
            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
            $countStmt->execute([$category['id']]);
            $category_counts[$category['id']] = $countStmt->fetchColumn();
        }
    } catch (PDOException $e) {
        $error = "Error fetching data: " . $e->getMessage();
        $categories = [];
        $products = [];
        $category_counts = [];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - ZEEMKAHLEEM LUXURY</title>
    <style>
        :root {
            --primary: #d4af37;
            --secondary: #1a1a2e;
            --accent: #e6c875;
            --text-light: #f8f9fa;
            --text-dark: #212529;
            --bg-light: #ffffff;
            --bg-dark: #0f0f1a;
            --glass-light: rgba(255, 255, 255, 0.1);
            --glass-dark: rgba(0, 0, 0, 0.2);
            --success: #28a745;
            --danger: #dc3545;
            --warning: #ffc107;
            --info: #17a2b8;
        }

        [data-theme="light"] {
            --bg: var(--bg-light);
            --text: var(--text-dark);
            --glass: var(--glass-light);
        }

        [data-theme="dark"] {
            --bg: var(--bg-dark);
            --text: var(--text-light);
            --glass: var(--glass-dark);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            padding: 20px;
            line-height: 1.6;
        }

        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--primary);
            flex-wrap: wrap;
            gap: 1rem;
        }

        .admin-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .admin-card h2 {
            color: var(--primary);
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .admin-card h2::before {
            content: '✦';
            font-size: 1.2em;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--accent);
        }

        .form-file {
            width: 100%;
            padding: 0.75rem;
            border: 2px dashed rgba(255, 255, 255, 0.3);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.05);
            color: var(--text);
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-file:hover {
            border-color: var(--primary);
            background: rgba(255, 255, 255, 0.1);
        }

        .form-file:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.3);
        }

        .file-hint {
            font-size: 0.875rem;
            color: var(--accent);
            margin-top: 0.5rem;
            font-style: italic;
            opacity: 0.8;
        }

        .file-size-warning {
            font-size: 0.875rem;
            color: var(--warning);
            margin-top: 0.5rem;
            padding: 0.5rem;
            background: rgba(255, 193, 7, 0.1);
            border-radius: 4px;
            border-left: 3px solid var(--warning);
        }

        .file-size-success {
            font-size: 0.875rem;
            color: var(--success);
            margin-top: 0.5rem;
            padding: 0.5rem;
            background: rgba(40, 167, 69, 0.1);
            border-radius: 4px;
            border-left: 3px solid var(--success);
        }

        .image-preview {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }

        .preview-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 8px;
            border: 2px solid var(--primary);
            position: relative;
            transition: transform 0.3s ease;
        }

        .preview-image:hover {
            transform: scale(1.05);
        }

        .preview-image.main {
            border-color: var(--accent);
            box-shadow: 0 0 15px rgba(212, 175, 55, 0.5);
        }

        .remove-image {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--danger);
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .remove-image:hover {
            background: #c82333;
            transform: scale(1.1);
        }

        input, select, textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
            color: var(--text);
            font-size: 1rem;
            font-family: inherit;
            transition: all 0.3s ease;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.3);
            background: rgba(255, 255, 255, 0.15);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-family: inherit;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn:hover::before {
            left: 100%;
        }

        .btn-primary {
            background: var(--primary);
            color: var(--secondary);
        }

        .btn-primary:hover {
            background: var(--accent);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(212, 175, 55, 0.3);
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.3);
        }

        .btn-warning {
            background: var(--warning);
            color: var(--secondary);
        }

        .btn-warning:hover {
            background: #e0a800;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 193, 7, 0.3);
        }

        .btn-info {
            background: var(--info);
            color: white;
        }

        .btn-info:hover {
            background: #138496;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(23, 162, 184, 0.3);
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.3);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none !important;
        }

        .btn:disabled:hover {
            transform: none !important;
            box-shadow: none !important;
        }

        .message {
            padding: 1rem 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            border-left: 4px solid;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message.success {
            background: rgba(40, 167, 69, 0.15);
            border-color: var(--success);
            color: #28a745;
        }

        .message.error {
            background: rgba(220, 53, 69, 0.15);
            border-color: var(--danger);
            color: #dc3545;
        }

        .message.warning {
            background: rgba(255, 193, 7, 0.15);
            border-color: var(--warning);
            color: #ffc107;
        }

        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, var(--secondary), #16213e);
            padding: 20px;
        }

        .login-form {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            padding: 3rem;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            width: 100%;
            max-width: 400px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .login-form h2 {
            text-align: center;
            color: var(--primary);
            margin-bottom: 2rem;
            font-size: 2rem;
            text-shadow: 0 2px 10px rgba(212, 175, 55, 0.3);
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }

        .product-item {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
            transition: all 0.3s ease;
        }

        .product-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            border-color: rgba(212, 175, 55, 0.3);
        }

        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 1rem;
            transition: transform 0.3s ease;
        }

        .product-item:hover .product-image {
            transform: scale(1.05);
        }

        .product-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            padding: 1.5rem;
            border-radius: 12px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            border-color: var(--primary);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary);
            text-shadow: 0 2px 10px rgba(212, 175, 55, 0.3);
        }

        .stat-label {
            font-size: 0.9rem;
            opacity: 0.8;
            margin-top: 0.5rem;
        }

        /* Category Table */
        .category-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            overflow: hidden;
        }

        .category-table th,
        .category-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .category-table th {
            background: rgba(255, 255, 255, 0.1);
            font-weight: 600;
            color: var(--primary);
        }

        .category-table tr:last-child td {
            border-bottom: none;
        }

        .category-table tr:hover {
            background: rgba(255, 255, 255, 0.08);
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            backdrop-filter: blur(5px);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: var(--bg);
            padding: 2rem;
            border-radius: 15px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translate(-50%, -40%);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%);
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .modal-header h3 {
            color: var(--primary);
            margin: 0;
            font-size: 1.5rem;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text);
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .close-modal:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--danger);
        }

        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .upload-progress {
            width: 100%;
            height: 6px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 3px;
            margin-top: 0.5rem;
            overflow: hidden;
            display: none;
        }

        .upload-progress-bar {
            height: 100%;
            background: var(--primary);
            width: 0%;
            transition: width 0.3s ease;
        }

        @media (max-width: 768px) {
            .admin-container {
                padding: 10px;
            }
            
            .admin-header {
                flex-direction: column;
                text-align: center;
            }
            
            .admin-card {
                padding: 1.5rem;
            }
            
            .products-grid {
                grid-template-columns: 1fr;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .category-table {
                display: block;
                overflow-x: auto;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .product-actions {
                flex-direction: column;
            }
            
            .modal-content {
                padding: 1.5rem;
                width: 95%;
            }
            
            .login-form {
                padding: 2rem;
            }
        }

        @media (max-width: 480px) {
            body {
                padding: 10px;
            }
            
            .admin-card {
                padding: 1rem;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .action-buttons {
                width: 100%;
            }
        }

        /* Theme Toggle */
        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            z-index: 1001;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }

        .theme-toggle:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: scale(1.1);
        }

        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: var(--accent);
            opacity: 0.7;
        }

        .empty-state::before {
            content: '📁';
            font-size: 3rem;
            display: block;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .upload-stats {
            background: rgba(255, 255, 255, 0.05);
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1rem;
            border-left: 4px solid var(--primary);
        }

        .upload-stats h4 {
            color: var(--primary);
            margin-bottom: 0.5rem;
        }

        .upload-stats p {
            margin: 0.25rem 0;
            font-size: 0.9rem;
            opacity: 0.8;
        }
    </style>
</head>
<body data-theme="dark">
    <?php if (!$is_logged_in): ?>
        <!-- Login Form -->
        <div class="login-container">
            <div class="login-form">
                <h2>Admin Login</h2>
                <?php if ($error): ?>
                    <div class="message error"><?= h($error) ?></div>
                <?php endif; ?>
                <form method="POST">
                    <input type="hidden" name="action" value="login">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" required placeholder="Enter username">
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" required placeholder="Enter password">
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">Login</button>
                </form>
                <div style="margin-top: 1rem; text-align: center; color: #999; font-size: 0.9rem;">
                    Default credentials: admin / admin123
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Theme Toggle -->
        <div class="theme-toggle" onclick="toggleTheme()">
            <span id="theme-icon">🌙</span>
        </div>

        <!-- Admin Dashboard -->
        <div class="admin-container">
            <div class="admin-header">
                <h1>ZEEMKAHLEEM LUXURY - Admin Panel</h1>
                <div style="display: flex; align-items: center; gap: 1rem;">
                    <span>Welcome, <strong><?= h($_SESSION['admin_username']) ?></strong>!</span>
                    <a href="?logout" class="btn btn-danger">Logout</a>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="message success"><?= h($message) ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="message error"><?= h($error) ?></div>
            <?php endif; ?>

            <!-- Stats Overview -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= count($categories) ?></div>
                    <div class="stat-label">Categories</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= count($products) ?></div>
                    <div class="stat-label">Products</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= array_sum($category_counts) ?></div>
                    <div class="stat-label">Total Items</div>
                </div>
            </div>

            <!-- Upload Stats -->
            <div class="admin-card">
                <h2>Upload Configuration</h2>
                <div class="upload-stats">
                    <h4>Current Upload Limits</h4>
                    <p>✅ Maximum file size: <strong>100MB</strong> per image</p>
                    <p>✅ Total upload limit: <strong>500MB</strong> per request</p>
                    <p>✅ Maximum files: <strong>20</strong> images per upload</p>
                    <p>✅ Supported formats: JPG, JPEG, PNG, GIF, WEBP, BMP, SVG</p>
                    <p>✅ Execution time: <strong>10 minutes</strong> for large uploads</p>
                </div>
            </div>

            <!-- Create New Admin -->
            <div class="admin-card">
                <h2>Create New Admin</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="create_admin">
                    <div class="form-group">
                        <label for="new_username">Username</label>
                        <input type="text" id="new_username" name="new_username" required placeholder="Enter new admin username">
                    </div>
                    <div class="form-group">
                        <label for="new_password">Password</label>
                        <input type="password" id="new_password" name="new_password" required placeholder="Enter new admin password">
                    </div>
                    <button type="submit" class="btn btn-primary">Create Admin</button>
                </form>
            </div>

            <!-- Add Category -->
            <div class="admin-card">
                <h2>Add Category</h2>
                <form method="POST">
                    <input type="hidden" name="action" value="add_category">
                    <div class="form-group">
                        <label for="category_name">Category Name</label>
                        <input type="text" id="category_name" name="name" required placeholder="Enter category name">
                    </div>
                    <button type="submit" class="btn btn-primary">Add Category</button>
                </form>
            </div>

            <!-- Manage Categories -->
            <div class="admin-card">
                <h2>Manage Categories (<?= count($categories) ?>)</h2>
                <?php if (empty($categories)): ?>
                    <div class="empty-state">
                        No categories found. Add your first category above.
                    </div>
                <?php else: ?>
                    <table class="category-table">
                        <thead>
                            <tr>
                                <th>Category Name</th>
                                <th>Products</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td><?= h($category['name']) ?></td>
                                    <td><?= $category_counts[$category['id']] ?? 0 ?> products</td>
                                    <td><?= date('M j, Y', strtotime($category['created_at'])) ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn btn-warning btn-sm" 
                                                    onclick="openEditCategoryModal(<?= $category['id'] ?>, '<?= h($category['name']) ?>')">
                                                ✏️ Edit
                                            </button>
                                            <?php if (($category_counts[$category['id']] ?? 0) === 0): ?>
                                                <a href="?action=delete_category&id=<?= $category['id'] ?>" 
                                                   class="btn btn-danger btn-sm"
                                                   onclick="return confirm('Are you sure you want to delete this category?')">
                                                    🗑️ Delete
                                                </a>
                                            <?php else: ?>
                                                <button class="btn btn-danger btn-sm" disabled title="Cannot delete category with products">
                                                    🗑️ Delete
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>

            <!-- Add Product -->
            <div class="admin-card">
                <h2>Add Product</h2>
                <form method="POST" enctype="multipart/form-data" id="productForm" onsubmit="return validateProductForm()">
                    <input type="hidden" name="action" value="add_product">
                    
                    <div class="form-group">
                        <label for="product_name">Product Name</label>
                        <input type="text" id="product_name" name="name" required placeholder="Enter product name">
                    </div>
                    
                    <div class="form-group">
                        <label for="product_category">Category</label>
                        <select id="product_category" name="category_id" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= h($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="product_price">Price (₦)</label>
                        <input type="number" id="product_price" name="price" step="0.01" min="0" required placeholder="0.00">
                    </div>
                    
                    <div class="form-group">
                        <label for="product_description">Description</label>
                        <textarea id="product_description" name="description" rows="4" placeholder="Enter product description (optional)"></textarea>
                    </div>

                    <!-- Upload Progress -->
                    <div class="upload-progress" id="uploadProgress">
                        <div class="upload-progress-bar" id="uploadProgressBar"></div>
                    </div>

                    <!-- Main Product Image -->
                    <div class="form-group">
                        <label class="form-label">Main Product Image <span style="color: var(--accent)">*</span></label>
                        <input type="file" name="main_image" class="form-file" accept="image/*" required onchange="previewMainImage(this)">
                        <div class="file-hint">This will be the primary image displayed for the product</div>
                        <div class="file-size-success">✅ Maximum file size: 100MB per image (Supports: jpg, jpeg, png, gif, webp, bmp, svg)</div>
                        <div class="image-preview" id="mainImagePreview"></div>
                    </div>
                    
                    <!-- Additional Product Images -->
                    <div class="form-group">
                        <label class="form-label">Additional Product Images</label>
                        <input type="file" name="additional_images[]" class="form-file" accept="image/*" multiple onchange="previewAdditionalImages(this)">
                        <div class="file-hint">You can select multiple images to show different angles of the product</div>
                        <div class="file-size-success">✅ Maximum file size: 100MB per image. Total upload limit: 500MB</div>
                        <div class="image-preview" id="additionalImagesPreview"></div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <span id="submitText">Add Product</span>
                        <span id="submitLoading" class="loading" style="display: none;"></span>
                    </button>
                </form>
            </div>

            <!-- Products List -->
            <div class="admin-card">
                <h2>Products (<?= count($products) ?>)</h2>
                <?php if (empty($products)): ?>
                    <div class="empty-state">
                        No products found. Add your first product above.
                    </div>
                <?php else: ?>
                    <div class="products-grid">
                        <?php foreach ($products as $product): 
                            $images = explode(',', $product['images']);
                        ?>
                            <div class="product-item">
                                <img src="uploads/<?= h($images[0]) ?>" alt="<?= h($product['name']) ?>" class="product-image" loading="lazy">
                                <h3><?= h($product['name']) ?></h3>
                                <p><strong>Category:</strong> <?= h($product['category_name']) ?></p>
                                <p><strong>Price:</strong> ₦<?= number_format($product['price'], 2) ?></p>
                                <p><strong>Images:</strong> <?= count($images) ?></p>
                                <p><strong>Added:</strong> <?= date('M j, Y', strtotime($product['created_at'])) ?></p>
                                <div class="product-actions">
                                    <button class="btn btn-info btn-sm" onclick="openEditProductModal(<?= $product['id'] ?>)">
                                        ✏️ Edit
                                    </button>
                                    <a href="?action=delete_product&id=<?= $product['id'] ?>" 
                                       class="btn btn-danger btn-sm"
                                       onclick="return confirm('Are you sure you want to delete \'<?= h($product['name']) ?>\'? This action cannot be undone.')">
                                        🗑️ Delete
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Edit Category Modal -->
        <div class="modal" id="editCategoryModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Edit Category</h3>
                    <button class="close-modal" onclick="closeEditCategoryModal()">&times;</button>
                </div>
                <form method="POST" id="editCategoryForm">
                    <input type="hidden" name="action" value="edit_category">
                    <input type="hidden" name="category_id" id="edit_category_id">
                    <div class="form-group">
                        <label for="edit_category_name">Category Name</label>
                        <input type="text" id="edit_category_name" name="name" required>
                    </div>
                    <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                        <button type="submit" class="btn btn-primary">Update Category</button>
                        <button type="button" class="btn btn-warning" onclick="closeEditCategoryModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Edit Product Modal -->
        <div class="modal" id="editProductModal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Edit Product</h3>
                    <button class="close-modal" onclick="closeEditProductModal()">&times;</button>
                </div>
                <form method="POST" enctype="multipart/form-data" id="editProductForm" onsubmit="return validateEditProductForm()">
                    <input type="hidden" name="action" value="edit_product">
                    <input type="hidden" name="product_id" id="edit_product_id">
                    
                    <div class="form-group">
                        <label for="edit_product_name">Product Name</label>
                        <input type="text" id="edit_product_name" name="name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_product_category">Category</label>
                        <select id="edit_product_category" name="category_id" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['id'] ?>"><?= h($cat['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_product_price">Price (₦)</label>
                        <input type="number" id="edit_product_price" name="price" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_product_description">Description</label>
                        <textarea id="edit_product_description" name="description" rows="4"></textarea>
                    </div>

                    <!-- Current Images -->
                    <div class="form-group">
                        <label class="form-label">Current Images</label>
                        <div class="image-preview" id="editCurrentImagesPreview"></div>
                        <div class="file-hint">Click the X button to remove an image (at least one image must remain)</div>
                    </div>

                    <!-- New Main Product Image -->
                    <div class="form-group">
                        <label class="form-label">Change Main Product Image</label>
                        <input type="file" name="main_image" class="form-file" accept="image/*" onchange="previewEditMainImage(this)">
                        <div class="file-hint">Upload a new image to replace the current main image</div>
                        <div class="file-size-success">✅ Maximum file size: 100MB per image</div>
                        <div class="image-preview" id="editMainImagePreview"></div>
                    </div>
                    
                    <!-- New Additional Product Images -->
                    <div class="form-group">
                        <label class="form-label">Add More Product Images</label>
                        <input type="file" name="additional_images[]" class="form-file" accept="image/*" multiple onchange="previewEditAdditionalImages(this)">
                        <div class="file-hint">You can select multiple images to add to the product</div>
                        <div class="file-size-success">✅ Maximum file size: 100MB per image. Total upload limit: 500MB</div>
                        <div class="image-preview" id="editAdditionalImagesPreview"></div>
                    </div>
                    
                    <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                        <button type="submit" class="btn btn-primary" id="editSubmitBtn">
                            <span id="editSubmitText">Update Product</span>
                            <span id="editSubmitLoading" class="loading" style="display: none;"></span>
                        </button>
                        <button type="button" class="btn btn-warning" onclick="closeEditProductModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>

        <script>
            // Theme functionality
            function toggleTheme() {
                const body = document.body;
                const themeIcon = document.getElementById('theme-icon');
                const currentTheme = body.getAttribute('data-theme');
                const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                
                body.setAttribute('data-theme', newTheme);
                themeIcon.textContent = newTheme === 'dark' ? '🌙' : '☀️';
                
                // Save preference to localStorage
                localStorage.setItem('admin-theme', newTheme);
            }

            // Load saved theme
            document.addEventListener('DOMContentLoaded', function() {
                const savedTheme = localStorage.getItem('admin-theme') || 'dark';
                document.body.setAttribute('data-theme', savedTheme);
                document.getElementById('theme-icon').textContent = savedTheme === 'dark' ? '🌙' : '☀️';
            });

            // Category Modal Functions
            function openEditCategoryModal(categoryId, categoryName) {
                document.getElementById('edit_category_id').value = categoryId;
                document.getElementById('edit_category_name').value = categoryName;
                document.getElementById('editCategoryModal').style.display = 'block';
            }

            function closeEditCategoryModal() {
                document.getElementById('editCategoryModal').style.display = 'none';
            }

            // File size validation - MASSIVE LIMITS
            function validateFileSize(input, maxSizeMB) {
                if (input.files && input.files[0]) {
                    const fileSize = input.files[0].size / 1024 / 1024; // in MB
                    if (fileSize > maxSizeMB) {
                        alert(`File size must be less than ${maxSizeMB}MB`);
                        input.value = '';
                        return false;
                    }
                }
                return true;
            }

            // Add file size validation to file inputs
            document.addEventListener('DOMContentLoaded', function() {
                const mainImageInput = document.querySelector('input[name="main_image"]');
                const additionalImagesInput = document.querySelector('input[name="additional_images[]"]');
                
                if (mainImageInput) {
                    mainImageInput.addEventListener('change', function() {
                        validateFileSize(this, 100); // 100MB limit
                    });
                }
                
                if (additionalImagesInput) {
                    additionalImagesInput.addEventListener('change', function() {
                        for (let i = 0; i < this.files.length; i++) {
                            const fileSize = this.files[i].size / 1024 / 1024;
                            if (fileSize > 100) {
                                alert(`File "${this.files[i].name}" exceeds 100MB limit`);
                                this.value = '';
                                break;
                            }
                        }
                    });
                }
            });

            // Product Image Preview Functions
            function previewMainImage(input) {
                const preview = document.getElementById('mainImagePreview');
                preview.innerHTML = '';
                
                if (input.files && input.files[0]) {
                    // Validate file size - 100MB limit
                    if (input.files[0].size > 100 * 1024 * 1024) {
                        alert(`File size must be less than 100MB`);
                        input.value = '';
                        return;
                    }

                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const container = document.createElement('div');
                        container.style.position = 'relative';
                        container.style.display = 'inline-block';
                        
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'preview-image main';
                        img.title = 'Main Product Image';
                        
                        container.appendChild(img);
                        preview.appendChild(container);
                    }
                    reader.readAsDataURL(input.files[0]);
                }
            }

            function previewAdditionalImages(input) {
                const preview = document.getElementById('additionalImagesPreview');
                preview.innerHTML = '';
                
                if (input.files) {
                    for (let i = 0; i < input.files.length; i++) {
                        // Validate file size - 100MB limit
                        if (input.files[i].size > 100 * 1024 * 1024) {
                            alert(`File "${input.files[i].name}" exceeds 100MB limit`);
                            input.value = '';
                            preview.innerHTML = '';
                            return;
                        }

                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const container = document.createElement('div');
                            container.style.position = 'relative';
                            container.style.display = 'inline-block';
                            
                            const img = document.createElement('img');
                            img.src = e.target.result;
                            img.className = 'preview-image';
                            img.title = `Additional Image ${preview.children.length + 1}`;
                            
                            container.appendChild(img);
                            preview.appendChild(container);
                        }
                        reader.readAsDataURL(input.files[i]);
                    }
                }
            }

            // Form Validation
            function validateProductForm() {
                const form = document.getElementById('productForm');
                const mainImage = form.querySelector('input[name="main_image"]');
                const submitBtn = document.getElementById('submitBtn');
                const submitText = document.getElementById('submitText');
                const submitLoading = document.getElementById('submitLoading');
                
                // Basic validation
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return false;
                }
                
                // Check if main image is selected
                if (!mainImage.files || !mainImage.files[0]) {
                    alert('Please select a main product image');
                    mainImage.focus();
                    return false;
                }
                
                // Show loading state
                submitText.style.display = 'none';
                submitLoading.style.display = 'inline-block';
                submitBtn.disabled = true;
                
                return true;
            }

            function validateEditProductForm() {
                const form = document.getElementById('editProductForm');
                const submitBtn = document.getElementById('editSubmitBtn');
                const submitText = document.getElementById('editSubmitText');
                const submitLoading = document.getElementById('editSubmitLoading');
                
                if (!form.checkValidity()) {
                    form.reportValidity();
                    return false;
                }
                
                // Show loading state
                submitText.style.display = 'none';
                submitLoading.style.display = 'inline-block';
                submitBtn.disabled = true;
                
                return true;
            }

            // Edit Product Modal Functions - FIXED VERSION
            let currentProductImages = [];

            async function openEditProductModal(productId) {
                try {
                    showLoadingState(true);
                    const response = await fetch(`get_product.php?id=${productId}`);
                    if (!response.ok) {
                        throw new Error('Failed to fetch product data');
                    }
                    const product = await response.json();
                    
                    // Populate form fields
                    document.getElementById('edit_product_id').value = product.id;
                    document.getElementById('edit_product_name').value = product.name;
                    document.getElementById('edit_product_category').value = product.category_id;
                    document.getElementById('edit_product_price').value = product.price;
                    document.getElementById('edit_product_description').value = product.description || '';
                    
                    // Handle images - FIXED: No need to split, it's already an array
                    currentProductImages = product.images || [];
                    displayCurrentImages(currentProductImages);
                    
                    // Show modal
                    document.getElementById('editProductModal').style.display = 'block';
                } catch (error) {
                    console.error('Error fetching product:', error);
                    alert('Error loading product data: ' + error.message);
                } finally {
                    showLoadingState(false);
                }
            }

            function showLoadingState(show) {
                if (show) {
                    document.body.style.cursor = 'wait';
                } else {
                    document.body.style.cursor = 'default';
                }
            }

            function displayCurrentImages(images) {
                const preview = document.getElementById('editCurrentImagesPreview');
                preview.innerHTML = '';
                
                if (images.length === 0) {
                    preview.innerHTML = '<div class="empty-state" style="padding: 1rem;">No images</div>';
                    return;
                }
                
                images.forEach((image, index) => {
                    const container = document.createElement('div');
                    container.style.position = 'relative';
                    container.style.display = 'inline-block';
                    
                    const img = document.createElement('img');
                    img.src = `uploads/${image}`;
                    img.className = `preview-image ${index === 0 ? 'main' : ''}`;
                    img.style.cursor = 'pointer';
                    img.title = index === 0 ? 'Main Image' : `Image ${index + 1}`;
                    
                    const removeBtn = document.createElement('button');
                    removeBtn.innerHTML = '×';
                    removeBtn.className = 'remove-image';
                    removeBtn.onclick = function(e) {
                        e.preventDefault();
                        removeImageFromProduct(image);
                    };
                    removeBtn.title = 'Remove this image';
                    
                    container.appendChild(img);
                    container.appendChild(removeBtn);
                    preview.appendChild(container);
                });
            }

            function removeImageFromProduct(imageFilename) {
                if (currentProductImages.length <= 1) {
                    alert('Product must have at least one image');
                    return;
                }
                
                if (confirm('Are you sure you want to remove this image?')) {
                    currentProductImages = currentProductImages.filter(img => img !== imageFilename);
                    displayCurrentImages(currentProductImages);
                }
            }

            function previewEditMainImage(input) {
                const preview = document.getElementById('editMainImagePreview');
                preview.innerHTML = '';
                
                if (input.files && input.files[0]) {
                    // Validate file size - 100MB limit
                    if (input.files[0].size > 100 * 1024 * 1024) {
                        alert(`File size must be less than 100MB`);
                        input.value = '';
                        return;
                    }

                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const container = document.createElement('div');
                        container.style.position = 'relative';
                        container.style.display = 'inline-block';
                        
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'preview-image main';
                        img.title = 'New Main Image';
                        
                        container.appendChild(img);
                        preview.appendChild(container);
                    }
                    reader.readAsDataURL(input.files[0]);
                }
            }

            function previewEditAdditionalImages(input) {
                const preview = document.getElementById('editAdditionalImagesPreview');
                preview.innerHTML = '';
                
                if (input.files) {
                    for (let i = 0; i < input.files.length; i++) {
                        // Validate file size - 100MB limit
                        if (input.files[i].size > 100 * 1024 * 1024) {
                            alert(`File "${input.files[i].name}" exceeds 100MB limit`);
                            input.value = '';
                            preview.innerHTML = '';
                            return;
                        }

                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const container = document.createElement('div');
                            container.style.position = 'relative';
                            container.style.display = 'inline-block';
                            
                            const img = document.createElement('img');
                            img.src = e.target.result;
                            img.className = 'preview-image';
                            img.title = `New Additional Image ${i + 1}`;
                            
                            container.appendChild(img);
                            preview.appendChild(container);
                        }
                        reader.readAsDataURL(input.files[i]);
                    }
                }
            }

            function closeEditProductModal() {
                document.getElementById('editProductModal').style.display = 'none';
                currentProductImages = [];
                
                // Reset loading states
                const editSubmitText = document.getElementById('editSubmitText');
                const editSubmitLoading = document.getElementById('editSubmitLoading');
                const editSubmitBtn = document.getElementById('editSubmitBtn');
                
                editSubmitText.style.display = 'inline';
                editSubmitLoading.style.display = 'none';
                editSubmitBtn.disabled = false;
            }

            // Handle edit product form submission
            document.getElementById('editProductForm').addEventListener('submit', function(e) {
                // Add current images as hidden inputs
                currentProductImages.forEach(image => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'existing_images[]';
                    input.value = image;
                    this.appendChild(input);
                });
            });

            // Close modals when clicking outside
            window.onclick = function(event) {
                const categoryModal = document.getElementById('editCategoryModal');
                const productModal = document.getElementById('editProductModal');
                
                if (event.target === categoryModal) {
                    closeEditCategoryModal();
                }
                if (event.target === productModal) {
                    closeEditProductModal();
                }
            }

            // Handle form resets
            document.getElementById('productForm').addEventListener('reset', function() {
                document.getElementById('mainImagePreview').innerHTML = '';
                document.getElementById('additionalImagesPreview').innerHTML = '';
                
                const submitBtn = document.getElementById('submitBtn');
                const submitText = document.getElementById('submitText');
                const submitLoading = document.getElementById('submitLoading');
                
                submitText.style.display = 'inline';
                submitLoading.style.display = 'none';
                submitBtn.disabled = false;
            });

            // Keyboard shortcuts
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeEditCategoryModal();
                    closeEditProductModal();
                }
            });

            // Simulate upload progress (for demonstration)
            function simulateUploadProgress() {
                const progressBar = document.getElementById('uploadProgressBar');
                const progressContainer = document.getElementById('uploadProgress');
                let width = 0;
                
                progressContainer.style.display = 'block';
                
                const interval = setInterval(() => {
                    if (width >= 100) {
                        clearInterval(interval);
                        setTimeout(() => {
                            progressContainer.style.display = 'none';
                        }, 500);
                    } else {
                        width++;
                        progressBar.style.width = width + '%';
                    }
                }, 20);
            }

            // Add progress simulation to form submission
            document.getElementById('productForm').addEventListener('submit', function() {
                const files = this.querySelector('input[type="file"]').files;
                if (files.length > 0) {
                    simulateUploadProgress();
                }
            });

            // Auto-calculate total file size
            function calculateTotalSize(input) {
                let totalSize = 0;
                if (input.files) {
                    for (let i = 0; i < input.files.length; i++) {
                        totalSize += input.files[i].size;
                    }
                }
                return totalSize;
            }

            // Add file size display
            document.addEventListener('DOMContentLoaded', function() {
                const fileInputs = document.querySelectorAll('input[type="file"]');
                fileInputs.forEach(input => {
                    input.addEventListener('change', function() {
                        const totalSize = calculateTotalSize(this);
                        const sizeInMB = (totalSize / 1024 / 1024).toFixed(2);
                        
                        // Find or create size display
                        let sizeDisplay = this.parentNode.querySelector('.file-size-display');
                        if (!sizeDisplay) {
                            sizeDisplay = document.createElement('div');
                            sizeDisplay.className = 'file-size-display file-hint';
                            this.parentNode.appendChild(sizeDisplay);
                        }
                        
                        if (totalSize > 0) {
                            sizeDisplay.textContent = `Total selected: ${sizeInMB} MB`;
                            sizeDisplay.style.color = sizeInMB > 500 ? 'var(--danger)' : 'var(--success)';
                        } else {
                            sizeDisplay.textContent = '';
                        }
                    });
                });
            });
        </script>
    <?php endif; ?>
</body>
</html>
