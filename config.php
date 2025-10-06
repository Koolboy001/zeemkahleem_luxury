<?php
session_start();

// Database configuration (update with your actual DB details)
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '60845');
define('DB_NAME', 'zeemkahleem_luxury'); // replace with your actual DB name on pxxl.app
define('DB_USER', 'your_hosted_db_user'); // replace this
define('DB_PASS', 'your_hosted_db_password'); // replace this
define('BUSINESS_WHATSAPP', '2349160935693');

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

try {
    // Include port number in DSN
    $pdo = new PDO("mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

// Create tables
$tables = [
    "CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(100) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
    "CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(100) UNIQUE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    
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
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
    )"
];

foreach ($tables as $table) {
    try {
        $pdo->exec($table);
    } catch (PDOException $e) {
        // Skip if table already exists
    }
}

// Create uploads directory if not exists
if (!file_exists('uploads')) {
    mkdir('uploads', 0777, true);
}

// Helper functions
function slugify($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);
    return $text ?: 'n-a';
}

function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

// Ensure default admin exists
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM admins");
    if ($stmt->fetchColumn() == 0) {
        $password = password_hash('admin123', PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO admins (username, password) VALUES (?, ?)")->execute(['admin', $password]);
        error_log("Default admin created: admin / admin123");
    }
} catch (PDOException $e) {
    error_log("Admin check failed: " . $e->getMessage());
}
?>
