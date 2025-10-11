<?php
// Increase file upload limits at the very beginning
ini_set('upload_max_filesize', '100M');
ini_set('post_max_size', '100M');
ini_set('max_file_uploads', '20');
ini_set('max_execution_time', 300);
ini_set('max_input_time', 300);
ini_set('memory_limit', '256M');
ini_set('display_errors', 0); // Hide errors from users
ini_set('log_errors', 1);

session_start();

// Database configuration
define('DB_HOST', 'db.pxxl.pro');
define('DB_PORT', '10233');
define('DB_NAME', 'db_e4a8923c');
define('DB_USER', 'user_e38b806e');
define('DB_PASS', 'e8334ec01a6d8bd8557ef57e5abfff50');
define('BUSINESS_WHATSAPP', '2349160935693');

// File upload configuration
define('MAX_FILE_SIZE', 100 * 1024 * 1024); // 100MB in bytes
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('MAX_TOTAL_UPLOAD_SIZE', 500 * 1024 * 1024); // 500MB total

// Session configuration for cart
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Color scheme for luxury brand
define('PRIMARY_COLOR', '#d4af37');
define('SECONDARY_COLOR', '#1a1a2e');
define('ACCENT_COLOR', '#e6c875');
define('TEXT_LIGHT', '#f8f9fa');
define('TEXT_DARK', '#212529');

// Error reporting for debugging (off in production)
error_reporting(E_ALL);

try {
    // Include port number in DSN
    $pdo = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database Connection Failed: " . $e->getMessage());
    die("Database Connection Failed. Please check your configuration.");
}

// Create tables with better error handling
$tables = [
    "CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    "CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(100) UNIQUE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    
    "CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_id INT NOT NULL,
        name VARCHAR(255) NOT NULL,
        slug VARCHAR(255) NOT NULL,
        description TEXT,
        price DECIMAL(10,2) NOT NULL,
        images TEXT NOT NULL,
        featured BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
];

foreach ($tables as $table) {
    try {
        $pdo->exec($table);
    } catch (PDOException $e) {
        error_log("Table creation error: " . $e->getMessage());
        // Continue execution even if table creation fails
    }
}

// Create uploads directory if not exists
if (!file_exists('uploads')) {
    if (!mkdir('uploads', 0755, true)) {
        error_log("Failed to create uploads directory");
    }
}

// Create index.html in uploads to prevent directory listing
if (file_exists('uploads') && !file_exists('uploads/index.html')) {
    file_put_contents('uploads/index.html', '<!-- Directory listing prevented -->');
}

// Helper functions
function slugify($text) {
    // Replace non-letter or digits by -
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    
    // Check if iconv is available, if not use a fallback
    if (function_exists('iconv')) {
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    } else {
        // Fallback: remove non-ASCII characters
        $text = preg_replace('/[^\x00-\x7F]/', '', $text);
    }
    
    // Remove unwanted characters
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    
    if (empty($text)) {
        return 'n-a';
    }
    
    return $text;
}

// Alternative slugify function without iconv dependency
function simple_slugify($text) {
    // Convert to lowercase
    $text = strtolower($text);
    
    // Replace non-alphanumeric characters with hyphens
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    
    // Trim hyphens from both ends
    $text = trim($text, '-');
    
    // Remove consecutive hyphens
    $text = preg_replace('/-+/', '-', $text);
    
    if (empty($text)) {
        return 'n-a';
    }
    
    return $text;
}

function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// File upload helper functions
function getUploadError($error_code) {
    $errors = [
        UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
        UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
        UPLOAD_ERR_PARTIAL => 'The uploaded file was only partially uploaded',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload',
    ];
    return $errors[$error_code] ?? 'Unknown upload error';
}

function validateFile($file, $is_main = false) {
    $errors = [];
    
    // Check if file was uploaded
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        if ($is_main) {
            $errors[] = 'Main image is required';
        }
        return $errors;
    }
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = getUploadError($file['error']);
        return $errors;
    }
    
    // Check file size
    if ($file['size'] > MAX_FILE_SIZE) {
        $errors[] = 'File size must be less than ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB';
    }
    
    // Check file type
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_ext, ALLOWED_EXTENSIONS)) {
        $errors[] = 'Invalid file type. Allowed: ' . implode(', ', ALLOWED_EXTENSIONS);
    }
    
    // Check if file is actually an image
    $image_info = @getimagesize($file['tmp_name']);
    if (!$image_info) {
        $errors[] = 'Uploaded file is not a valid image';
    }
    
    return $errors;
}

function safeMoveUploadedFile($tmp_path, $destination) {
    // Additional security check
    if (!is_uploaded_file($tmp_path)) {
        return false;
    }
    
    // Create directory if it doesn't exist
    $dir = dirname($destination);
    if (!file_exists($dir)) {
        mkdir($dir, 0755, true);
    }
    
    return move_uploaded_file($tmp_path, $destination);
}

function getTotalUploadSize($files) {
    $total_size = 0;
    foreach ($files as $file) {
        if (is_array($file['tmp_name'])) {
            // Multiple files
            foreach ($file['tmp_name'] as $key => $tmp_name) {
                if ($file['error'][$key] === UPLOAD_ERR_OK) {
                    $total_size += $file['size'][$key];
                }
            }
        } else {
            // Single file
            if ($file['error'] === UPLOAD_ERR_OK) {
                $total_size += $file['size'];
            }
        }
    }
    return $total_size;
}

// Ensure default admin exists
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM admins");
    if ($stmt->fetchColumn() == 0) {
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO admins (username, password) VALUES (?, ?)");
        $stmt->execute(['admin', $password]);
        error_log("Default admin created: admin / admin123");
    }
} catch (PDOException $e) {
    error_log("Admin check failed: " . $e->getMessage());
}

// Check if we're approaching upload limits
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES)) {
    $total_size = getTotalUploadSize($_FILES);
    if ($total_size > MAX_TOTAL_UPLOAD_SIZE) {
        error_log("Upload size exceeded: " . $total_size . " bytes");
        // This will be handled in the individual scripts
    }
}
?>
