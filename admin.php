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
    
    // SIMPLIFIED ADD PRODUCT - REMOVED COMPLEX VALIDATION
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_product') {
        $name = trim($_POST['name'] ?? '');
        $category_id = (int)($_POST['category_id'] ?? 0);
        $price = (float)($_POST['price'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        
        if (!empty($name) && $category_id > 0 && $price > 0) {
            try {
                $slug = simple_slugify($name);
                $images = [];
                
                // SIMPLIFIED: Handle main image upload
                if (!empty($_FILES['main_image']['name']) && $_FILES['main_image']['error'] === UPLOAD_ERR_OK) {
                    $main_file = $_FILES['main_image'];
                    $file_name = $main_file['name'];
                    $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                    $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    
                    if (in_array($file_ext, $allowed_ext)) {
                        // Check file size directly
                        if ($main_file['size'] <= MAX_FILE_SIZE) {
                            $new_filename = uniqid('main_') . '.' . $file_ext;
                            $upload_path = 'uploads/' . $new_filename;
                            
                            if (move_uploaded_file($main_file['tmp_name'], $upload_path)) {
                                $images[] = $new_filename;
                            } else {
                                $error = "Failed to upload main image. Check directory permissions.";
                            }
                        } else {
                            $error = "Main image is too large. Maximum size is 30MB.";
                        }
                    } else {
                        $error = "Invalid file type for main image. Allowed: jpg, jpeg, png, gif, webp";
                    }
                } else {
                    $error = "Main image is required";
                }
                
                // SIMPLIFIED: Handle additional images
                if (empty($error) && !empty($_FILES['additional_images']['name'][0])) {
                    foreach ($_FILES['additional_images']['tmp_name'] as $key => $tmp_name) {
                        if ($_FILES['additional_images']['error'][$key] === UPLOAD_ERR_OK) {
                            $file_name = $_FILES['additional_images']['name'][$key];
                            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                            $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                            
                            if (in_array($file_ext, $allowed_ext)) {
                                if ($_FILES['additional_images']['size'][$key] <= MAX_FILE_SIZE) {
                                    $new_filename = uniqid('add_') . '.' . $file_ext;
                                    $upload_path = 'uploads/' . $new_filename;
                                    
                                    if (move_uploaded_file($tmp_name, $upload_path)) {
                                        $images[] = $new_filename;
                                    }
                                }
                                // Silently skip files that are too large for additional images
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
            } catch (PDOException $e) {
                $error = "Error adding product: " . $e->getMessage();
                error_log("Product add error: " . $e->getMessage());
            }
        } else {
            $error = "Please fill all required fields with valid data";
        }
    }
    
    // Other admin functions (edit category, delete category, etc.) remain the same...
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
        }

        .admin-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border-radius: 15px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .admin-card h2 {
            color: var(--primary);
            margin-bottom: 1.5rem;
            font-size: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
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
        }

        .form-file:hover {
            border-color: var(--primary);
            background: rgba(255, 255, 255, 0.1);
        }

        .file-hint {
            font-size: 0.875rem;
            color: var(--accent);
            margin-top: 0.5rem;
            font-style: italic;
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
        }

        .preview-image.main {
            border-color: var(--accent);
            box-shadow: 0 0 10px rgba(212, 175, 55, 0.5);
        }

        .remove-image {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            cursor: pointer;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        input, select, textarea {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            background: rgba(255, 255, 255, 0.1);
            color: var(--text);
            font-size: 1rem;
        }

        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--primary);
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-primary {
            background: var(--primary);
            color: var(--secondary);
        }

        .btn-primary:hover {
            background: var(--accent);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(212, 175, 55, 0.3);
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        .btn-warning {
            background: #ffc107;
            color: var(--secondary);
        }

        .btn-warning:hover {
            background: #e0a800;
            transform: translateY(-2px);
        }

        .btn-info {
            background: #17a2b8;
            color: white;
        }

        .btn-info:hover {
            background: #138496;
            transform: translateY(-2px);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }

        .message.success {
            background: rgba(40, 167, 69, 0.2);
            border: 1px solid #28a745;
            color: #28a745;
        }

        .message.error {
            background: rgba(220, 53, 69, 0.2);
            border: 1px solid #dc3545;
            color: #dc3545;
        }

        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, var(--secondary), #16213e);
        }

        .login-form {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            padding: 3rem;
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            width: 100%;
            max-width: 400px;
        }

        .login-form h2 {
            text-align: center;
            color: var(--primary);
            margin-bottom: 2rem;
            font-size: 2rem;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }

        .product-item {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 1rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
        }

        .product-image {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 1rem;
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
            border-radius: 10px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: var(--primary);
        }

        /* Category Table */
        .category-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
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

        .category-table tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
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
        }

        .modal-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .modal-header h3 {
            color: var(--primary);
            margin: 0;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text);
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .admin-container {
                padding: 10px;
            }
            
            .admin-card {
                padding: 1rem;
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
                <div style="margin-top: 1rem; text-align: center; color: #999;">
                    Default: admin / admin123
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Admin Dashboard -->
        <div class="admin-container">
            <div class="admin-header">
                <h1>ZEEMKAHLEEM LUXURY - Admin Panel</h1>
                <div>
                    Welcome, <?= h($_SESSION['admin_username']) ?>! 
                    <a href="?logout" class="btn btn-danger" style="margin-left: 1rem;">Logout</a>
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
                    <div>Categories</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?= count($products) ?></div>
                    <div>Products</div>
                </div>
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
                    <p>No categories found. Add your first category above.</p>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; margin-top: 1rem;">
                            <thead>
                                <tr>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 1px solid rgba(255, 255, 255, 0.1); background: rgba(255, 255, 255, 0.1); color: var(--primary);">Category Name</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 1px solid rgba(255, 255, 255, 0.1); background: rgba(255, 255, 255, 0.1); color: var(--primary);">Products</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 1px solid rgba(255, 255, 255, 0.1); background: rgba(255, 255, 255, 0.1); color: var(--primary);">Created</th>
                                    <th style="padding: 1rem; text-align: left; border-bottom: 1px solid rgba(255, 255, 255, 0.1); background: rgba(255, 255, 255, 0.1); color: var(--primary);">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $category): ?>
                                    <tr>
                                        <td style="padding: 1rem; text-align: left; border-bottom: 1px solid rgba(255, 255, 255, 0.1);"><?= h($category['name']) ?></td>
                                        <td style="padding: 1rem; text-align: left; border-bottom: 1px solid rgba(255, 255, 255, 0.1);"><?= $category_counts[$category['id']] ?? 0 ?> products</td>
                                        <td style="padding: 1rem; text-align: left; border-bottom: 1px solid rgba(255, 255, 255, 0.1);"><?= date('M j, Y', strtotime($category['created_at'])) ?></td>
                                        <td style="padding: 1rem; text-align: left; border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                                            <div style="display: flex; gap: 0.5rem;">
                                                <button class="btn btn-warning btn-sm" 
                                                        onclick="alert('Edit functionality to be implemented')">
                                                    Edit
                                                </button>
                                                <?php if (($category_counts[$category['id']] ?? 0) === 0): ?>
                                                    <a href="?action=delete_category&id=<?= $category['id'] ?>" 
                                                       class="btn btn-danger btn-sm"
                                                       onclick="return confirm('Are you sure you want to delete this category?')">
                                                        Delete
                                                    </a>
                                                <?php else: ?>
                                                    <button class="btn btn-danger btn-sm" disabled title="Cannot delete category with products">
                                                        Delete
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Add Product -->
            <div class="admin-card">
                <h2>Add Product</h2>
                <form method="POST" enctype="multipart/form-data" id="productForm">
                    <input type="hidden" name="action" value="add_product">
                    
                    <div class="form-group">
                        <label for="product_name">Product Name</label>
                        <input type="text" id="product_name" name="name" required>
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
                        <input type="number" id="product_price" name="price" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="product_description">Description</label>
                        <textarea id="product_description" name="description" rows="4"></textarea>
                    </div>

                    <!-- Main Product Image -->
                    <div class="form-group">
                        <label class="form-label">Main Product Image <span style="color: var(--accent)">*</span></label>
                        <input type="file" name="main_image" class="form-file" accept="image/*" required onchange="previewMainImage(this)">
                        <div class="file-hint">Maximum file size: 30MB. Allowed formats: JPG, JPEG, PNG, GIF, WEBP</div>
                        <div class="image-preview" id="mainImagePreview"></div>
                    </div>
                    
                    <!-- Additional Product Images -->
                    <div class="form-group">
                        <label class="form-label">Additional Product Images</label>
                        <input type="file" name="additional_images[]" class="form-file" accept="image/*" multiple onchange="previewAdditionalImages(this)">
                        <div class="file-hint">Maximum file size: 30MB per image. Allowed formats: JPG, JPEG, PNG, GIF, WEBP</div>
                        <div class="image-preview" id="additionalImagesPreview"></div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Add Product</button>
                </form>
            </div>

            <!-- Products List -->
            <div class="admin-card">
                <h2>Products (<?= count($products) ?>)</h2>
                <div class="products-grid">
                    <?php foreach ($products as $product): 
                        $images = explode(',', $product['images']);
                    ?>
                        <div class="product-item">
                            <?php if (!empty($images[0])): ?>
                                <img src="uploads/<?= h($images[0]) ?>" alt="<?= h($product['name']) ?>" class="product-image">
                            <?php else: ?>
                                <div class="product-image" style="background: #ccc; display: flex; align-items: center; justify-content: center;">
                                    No Image
                                </div>
                            <?php endif; ?>
                            <h3><?= h($product['name']) ?></h3>
                            <p><strong>Category:</strong> <?= h($product['category_name']) ?></p>
                            <p><strong>Price:</strong> ₦<?= number_format($product['price'], 2) ?></p>
                            <p><strong>Images:</strong> <?= count($images) ?></p>
                            <p><strong>Added:</strong> <?= date('M j, Y', strtotime($product['created_at'])) ?></p>
                            <div class="product-actions">
                                <button class="btn btn-info btn-sm" onclick="alert('Edit functionality to be implemented')">Edit</button>
                                <a href="?action=delete_product&id=<?= $product['id'] ?>" 
                                   class="btn btn-danger btn-sm"
                                   onclick="return confirm('Are you sure you want to delete this product? This action cannot be undone.')">
                                    Delete
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <script>
            // Product Image Preview Functions
            function previewMainImage(input) {
                const preview = document.getElementById('mainImagePreview');
                preview.innerHTML = '';
                
                if (input.files && input.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.className = 'preview-image main';
                        preview.appendChild(img);
                    }
                    reader.readAsDataURL(input.files[0]);
                }
            }

            function previewAdditionalImages(input) {
                const preview = document.getElementById('additionalImagesPreview');
                preview.innerHTML = '';
                
                if (input.files) {
                    for (let i = 0; i < input.files.length; i++) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const img = document.createElement('img');
                            img.src = e.target.result;
                            img.className = 'preview-image';
                            preview.appendChild(img);
                        }
                        reader.readAsDataURL(input.files[i]);
                    }
                }
            }

            // File size validation before upload
            document.getElementById('productForm').addEventListener('submit', function(e) {
                const mainImage = this.querySelector('input[name="main_image"]');
                const additionalImages = this.querySelectorAll('input[name="additional_images[]"]');
                
                // Check main image
                if (mainImage.files[0]) {
                    if (mainImage.files[0].size > <?= MAX_FILE_SIZE ?>) {
                        e.preventDefault();
                        alert('Main image is too large. Maximum size is 30MB.');
                        return false;
                    }
                }
                
                // Check additional images
                if (additionalImages[0].files) {
                    for (let file of additionalImages[0].files) {
                        if (file.size > <?= MAX_FILE_SIZE ?>) {
                            e.preventDefault();
                            alert(`File "${file.name}" is too large. Maximum size is 30MB per image.`);
                            return false;
                        }
                    }
                }
            });
        </script>
    <?php endif; ?>
</body>
</html>
