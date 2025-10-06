<?php
require 'config.php';

// Initialize variables
$error = '';
$message = '';
$cart_items = [];
$total = 0;

// Handle cart actions
$action = $_REQUEST['action'] ?? '';

// Debug: Check session cart
error_log("Session cart: " . print_r($_SESSION['cart'], true));

if ($action === 'add_to_cart') {
    $id = (int)($_POST['product_id'] ?? 0);
    $qty = max(1, (int)($_POST['qty'] ?? 1));
    
    if (!$id) {
        echo json_encode(['ok' => false, 'err' => 'no id']);
        exit;
    }
    
    // Initialize cart if not exists
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    // Add to cart
    if (isset($_SESSION['cart'][$id])) {
        $_SESSION['cart'][$id] += $qty;
    } else {
        $_SESSION['cart'][$id] = $qty;
    }
    
    echo json_encode([
        'ok' => true, 
        'count' => array_sum($_SESSION['cart']),
        'cart' => $_SESSION['cart']
    ]);
    exit;
}

if ($action === 'remove_from_cart') {
    $id = (int)($_POST['product_id'] ?? 0);
    
    if (isset($_SESSION['cart'][$id])) {
        unset($_SESSION['cart'][$id]);
    }
    
    echo json_encode([
        'ok' => true, 
        'count' => isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0,
        'cart' => $_SESSION['cart'] ?? []
    ]);
    exit;
}

if ($action === 'update_qty') {
    $id = (int)($_POST['product_id'] ?? 0);
    $qty = max(0, (int)($_POST['qty'] ?? 0));
    
    if ($qty <= 0) {
        unset($_SESSION['cart'][$id]);
    } else {
        $_SESSION['cart'][$id] = $qty;
    }
    
    echo json_encode([
        'ok' => true, 
        'count' => isset($_SESSION['cart']) ? array_sum($_SESSION['cart']) : 0,
        'cart' => $_SESSION['cart'] ?? []
    ]);
    exit;
}

if ($action === 'clear_cart') {
    $_SESSION['cart'] = [];
    echo json_encode(['ok' => true, 'count' => 0, 'cart' => []]);
    exit;
}

// Function to get product details
function get_product($pdo, $id) {
    $stmt = $pdo->prepare('SELECT p.*, c.name AS category_name FROM products p JOIN categories c ON p.category_id = c.id WHERE p.id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle product JSON request for modal
if (isset($_GET['product_json'])) {
    $id = (int)$_GET['product_json'];
    $p = get_product($pdo, $id);
    
    if (!$p) {
        echo json_encode([]);
        exit;
    }
    
    $imgs = $p['images'] ? explode(',', $p['images']) : [];
    echo json_encode([
        'id' => $p['id'],
        'name' => $p['name'],
        'price' => $p['price'],
        'description' => $p['description'],
        'category_name' => $p['category_name'],
        'images' => $imgs
    ]);
    exit;
}

// Handle checkout
if (isset($_POST['checkout'])) {
    $cart = $_SESSION['cart'] ?? [];
    
    if (empty($cart)) {
        $error = "Your cart is empty! Please add some items before checking out.";
    } else {
        $lines = [];
        $total = 0;
        
        foreach ($cart as $pid => $qty) {
            $p = get_product($pdo, $pid);
            if (!$p) continue;
            
            $subtotal = $p['price'] * $qty;
            $total += $subtotal;
            $lines[] = "{$p['name']} ({$p['category_name']}) x{$qty} - ₦" . number_format($subtotal, 2);
        }
        
        $customer_name = trim($_POST['customer_name'] ?? '');
        $customer_phone = trim($_POST['customer_phone'] ?? '');
        $customer_address = trim($_POST['customer_address'] ?? '');
        
        if (empty($customer_name) || empty($customer_phone) || empty($customer_address)) {
            $error = "Please fill in all customer information fields.";
        } else {
            $msg = "🛍️ *ZEEMKAHLEEM LUXURY - NEW ORDER*\n\n";
            $msg .= "*Order Details:*\n" . implode("\n", $lines) . "\n\n";
            $msg .= "*Total: ₦" . number_format($total, 2) . "*\n\n";
            $msg .= "*Customer Information:*\n";
            $msg .= "Name: $customer_name\n";
            $msg .= "Phone: $customer_phone\n";
            $msg .= "Address: $customer_address\n\n";
            $msg .= "Thank you for your order! 🎉";
            
            $phone = preg_replace('/[^0-9]/', '', BUSINESS_WHATSAPP);
            $url = 'https://wa.me/' . $phone . '?text=' . urlencode($msg);
            
            // Clear cart after successful checkout
            $_SESSION['cart'] = [];
            
            // Redirect to WhatsApp
            header('Location: ' . $url);
            exit;
        }
    }
}

// Get cart items for display
$cart = $_SESSION['cart'] ?? [];
foreach ($cart as $product_id => $quantity) {
    $product = get_product($pdo, $product_id);
    if ($product) {
        $subtotal = $product['price'] * $quantity;
        $total += $subtotal;
        $cart_items[] = [
            'product' => $product,
            'quantity' => $quantity,
            'subtotal' => $subtotal
        ];
    }
}

// If no items in cart but we have localStorage, try to sync
if (empty($cart_items)) {
    echo "<script>
        // Try to sync cart from localStorage on page load
        document.addEventListener('DOMContentLoaded', function() {
            const localCart = JSON.parse(localStorage.getItem('cart')) || {};
            if (Object.keys(localCart).length > 0) {
                // Convert cart to FormData for better compatibility
                const formData = new FormData();
                formData.append('cart', JSON.stringify(localCart));
                
                fetch('sync_cart.php', {
                    method: 'POST',
                    body: formData
                }).then(r => {
                    if (!r.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return r.json();
                }).then(data => {
                    if (data.success) {
                        console.log('Cart synced successfully');
                        location.reload();
                    } else {
                        console.error('Cart sync failed:', data.message);
                    }
                }).catch(error => {
                    console.error('Error syncing cart:', error);
                });
            }
        });
    </script>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - ZEEMKAHLEEM LUXURY</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #d4af37;
            --secondary: #1a1a2e;
            --accent: #e6c875;
            --text-light: #f8f9fa;
            --text-dark: #212529;
            --bg-light: #f8f9fa;
            --bg-dark: #0f0f1a;
            --card-light: #ffffff;
            --card-dark: #1a1a2e;
            --shadow-light: 10px 10px 20px #d9d9d9, -10px -10px 20px #ffffff;
            --shadow-dark: 10px 10px 20px #0a0a12, -10px -10px 20px #141422;
            --inner-shadow-light: inset 5px 5px 10px #d9d9d9, inset -5px -5px 10px #ffffff;
            --inner-shadow-dark: inset 5px 5px 10px #0a0a12, inset -5px -5px 10px #141422;
        }

        [data-theme="light"] {
            --bg: var(--bg-light);
            --text: var(--text-dark);
            --card: var(--card-light);
            --shadow: var(--shadow-light);
            --inner-shadow: var(--inner-shadow-light);
        }

        [data-theme="dark"] {
            --bg: var(--bg-dark);
            --text: var(--text-light);
            --card: var(--card-dark);
            --shadow: var(--shadow-dark);
            --inner-shadow: var(--inner-shadow-dark);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            transition: background-color 0.3s, color 0.3s, box-shadow 0.3s;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Top Bar */
        .top-bar {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 80px;
            background: var(--card);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 2rem;
            z-index: 1000;
            box-shadow: var(--shadow);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .top-bar-content {
            display: flex;
            align-items: center;
            justify-content: space-between;
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 1rem;
            z-index: 1001;
        }

        .logo {
            font-size: 2rem;
            font-weight: bold;
            background: linear-gradient(45deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .top-bar-actions {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .cart-icon {
            position: relative;
            cursor: pointer;
            background: var(--card);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            text-decoration: none;
            box-shadow: var(--shadow);
        }

        .cart-icon:hover {
            transform: translateY(-2px);
            box-shadow: 5px 5px 15px rgba(0, 0, 0, 0.2), -5px -5px 15px rgba(255, 255, 255, 0.1);
        }

        .cart-icon i {
            font-size: 1.3rem;
            color: var(--text);
        }

        .cart-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background: linear-gradient(45deg, var(--primary), var(--accent));
            color: var(--secondary);
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
            font-weight: bold;
            border: 2px solid var(--card);
            box-shadow: 0 2px 8px rgba(212, 175, 55, 0.4);
        }

        .theme-toggle {
            background: var(--card);
            border-radius: 50%;
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: var(--shadow);
        }

        .theme-toggle:hover {
            transform: translateY(-2px);
            box-shadow: 5px 5px 15px rgba(0, 0, 0, 0.2), -5px -5px 15px rgba(255, 255, 255, 0.1);
        }

        .theme-toggle i {
            font-size: 1.2rem;
            color: var(--text);
        }

        /* Main Container */
        .container {
            max-width: 1200px;
            margin: 100px auto 4rem;
            padding: 0 2rem;
        }

        .page-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .page-title {
            font-size: 3rem;
            font-weight: 700;
            background: linear-gradient(45deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
        }

        .page-subtitle {
            font-size: 1.2rem;
            opacity: 0.8;
        }

        /* Cart Content */
        .cart-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 3rem;
            align-items: start;
        }

        @media (max-width: 768px) {
            .cart-content {
                grid-template-columns: 1fr;
            }
        }

        /* Cart Items */
        .cart-items {
            background: var(--card);
            border-radius: 25px;
            padding: 2rem;
            box-shadow: var(--shadow);
        }

        .cart-section-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 2rem;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .cart-section-title i {
            font-size: 1.3rem;
        }

        .empty-cart {
            text-align: center;
            padding: 3rem;
            color: var(--text);
            opacity: 0.7;
        }

        .empty-cart i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-cart h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        .cart-item {
            display: grid;
            grid-template-columns: 100px 1fr auto;
            gap: 1.5rem;
            padding: 1.5rem;
            border-radius: 15px;
            background: var(--bg);
            margin-bottom: 1.5rem;
            box-shadow: var(--inner-shadow);
            transition: all 0.3s ease;
        }

        .cart-item:hover {
            transform: translateY(-2px);
            box-shadow: 5px 5px 15px rgba(0, 0, 0, 0.1), -5px -5px 15px rgba(255, 255, 255, 0.05);
        }

        .item-image {
            width: 100px;
            height: 100px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .item-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .item-details {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .item-name {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .item-category {
            font-size: 0.9rem;
            color: var(--accent);
            margin-bottom: 0.5rem;
        }

        .item-price {
            font-size: 1.3rem;
            font-weight: bold;
            color: var(--primary);
        }

        .item-controls {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 1rem;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            background: var(--card);
            padding: 0.5rem;
            border-radius: 10px;
            box-shadow: var(--inner-shadow);
        }

        .quantity-btn {
            width: 35px;
            height: 35px;
            border: none;
            background: var(--bg);
            color: var(--text);
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
        }

        .quantity-btn:hover {
            background: var(--primary);
            color: var(--secondary);
        }

        .quantity-input {
            width: 50px;
            text-align: center;
            border: none;
            background: transparent;
            color: var(--text);
            font-weight: bold;
            font-size: 1rem;
        }

        .quantity-input:focus {
            outline: none;
        }

        .item-total {
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--primary);
        }

        .remove-btn {
            background: linear-gradient(45deg, #ff6b6b, #ee5a52);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: var(--shadow);
        }

        .remove-btn:hover {
            transform: translateY(-2px);
            box-shadow: 5px 5px 15px rgba(255, 107, 107, 0.3);
        }

        /* Order Summary */
        .order-summary {
            background: var(--card);
            border-radius: 25px;
            padding: 2rem;
            box-shadow: var(--shadow);
            position: sticky;
            top: 100px;
        }

        .summary-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 2rem;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .summary-title i {
            font-size: 1.3rem;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .summary-row:last-child {
            border-bottom: none;
        }

        .summary-label {
            font-size: 1rem;
            opacity: 0.8;
        }

        .summary-value {
            font-size: 1.1rem;
            font-weight: 600;
        }

        .summary-total {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary);
        }

        .checkout-form {
            margin-top: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--text);
        }

        .form-input {
            width: 100%;
            padding: 1rem;
            border: none;
            border-radius: 12px;
            background: var(--bg);
            color: var(--text);
            font-family: 'Inter', sans-serif;
            box-shadow: var(--inner-shadow);
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            box-shadow: 5px 5px 15px rgba(0, 0, 0, 0.1), -5px -5px 15px rgba(255, 255, 255, 0.1);
        }

        .checkout-btn {
            width: 100%;
            padding: 1.2rem;
            background: linear-gradient(45deg, var(--primary), var(--accent));
            color: var(--secondary);
            border: none;
            border-radius: 15px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }

        .checkout-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s ease;
        }

        .checkout-btn:hover::before {
            left: 100%;
        }

        .checkout-btn:hover {
            transform: translateY(-3px);
            box-shadow: 5px 5px 20px rgba(212, 175, 55, 0.4);
        }

        .continue-shopping {
            display: inline-block;
            width: 100%;
            padding: 1rem;
            text-align: center;
            background: var(--card);
            color: var(--text);
            text-decoration: none;
            border-radius: 12px;
            margin-top: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: var(--shadow);
        }

        .continue-shopping:hover {
            transform: translateY(-2px);
            background: var(--bg);
        }

        /* Messages */
        .message {
            padding: 1rem 1.5rem;
            border-radius: 12px;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }

        .message.error {
            background: linear-gradient(45deg, #ff6b6b, #ee5a52);
            color: white;
        }

        .message.success {
            background: linear-gradient(45deg, #51cf66, #40c057);
            color: white;
        }

        /* Luxury Footer */
        .luxury-footer {
            background: var(--card);
            padding: 4rem 2rem 2rem;
            margin-top: 6rem;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .footer-content {
            max-width: 1200px;
            margin: 0 auto;
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .social-links {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-bottom: 2.5rem;
        }

        .social-links a {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 60px;
            height: 60px;
            background: var(--card);
            border-radius: 50%;
            color: var(--text);
            text-decoration: none;
            font-size: 1.5rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .social-links a::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(45deg, var(--primary), var(--accent));
            opacity: 0;
            transition: opacity 0.3s ease;
            border-radius: 50%;
        }

        .social-links a:hover::before {
            opacity: 1;
        }

        .social-links a:hover {
            transform: translateY(-5px) scale(1.1);
            box-shadow: 5px 5px 15px rgba(0, 0, 0, 0.2), -5px -5px 15px rgba(255, 255, 255, 0.1);
        }

        .social-links a i {
            position: relative;
            z-index: 1;
        }

        .footer-text {
            color: var(--text);
            margin-bottom: 1rem;
            font-size: 1.1rem;
        }

        .footer-tagline {
            font-size: 0.9rem;
            opacity: 0.7;
            margin-top: 1rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .top-bar {
                padding: 0 1rem;
                height: 70px;
            }

            .container {
                margin: 90px auto 2rem;
                padding: 0 1rem;
            }

            .page-title {
                font-size: 2.2rem;
            }

            .cart-item {
                grid-template-columns: 80px 1fr;
                gap: 1rem;
            }

            .item-controls {
                grid-column: 1 / -1;
                flex-direction: row;
                justify-content: space-between;
                align-items: center;
                margin-top: 1rem;
            }

            .cart-items, .order-summary {
                padding: 1.5rem;
            }

            .social-links {
                gap: 1.5rem;
            }

            .social-links a {
                width: 50px;
                height: 50px;
                font-size: 1.3rem;
            }
        }

        @media (max-width: 480px) {
            .page-title {
                font-size: 1.8rem;
            }

            .cart-item {
                grid-template-columns: 1fr;
                text-align: center;
            }

            .item-image {
                margin: 0 auto;
            }

            .item-controls {
                flex-direction: column;
                gap: 1rem;
            }

            .quantity-controls {
                justify-content: center;
            }
        }
    </style>
</head>
<body data-theme="dark">
    <!-- Top Bar -->
    <div class="top-bar">
        <div class="top-bar-content">
            <div class="brand">
                <a href="index.php" style="text-decoration: none; display: flex; align-items: center; gap: 1rem;">
                    <div class="logo">ZK</div>
                    <span style="font-weight: 600; color: var(--text);">ZEEMKAHLEEM LUXURY</span>
                </a>
            </div>

            <div class="top-bar-actions">
                <a href="cart.php" class="cart-icon">
                    <i class="fas fa-shopping-bag"></i>
                    <span class="cart-count" id="cartCount"><?= array_sum($_SESSION['cart'] ?? []) ?></span>
                </a>
                <button class="theme-toggle" onclick="toggleTheme()">
                    <i class="fas fa-moon"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="container">
        <div class="page-header">
            <h1 class="page-title">Shopping Cart</h1>
            <p class="page-subtitle">Review your luxury selections</p>
        </div>

        <?php if ($error): ?>
            <div class="message error">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="cart-content">
            <!-- Cart Items -->
            <div class="cart-items">
                <h2 class="cart-section-title">
                    <i class="fas fa-shopping-bag"></i>
                    Your Items (<?= count($cart_items) ?>)
                </h2>

                <?php if (empty($cart_items)): ?>
                    <div class="empty-cart">
                        <i class="fas fa-shopping-cart"></i>
                        <h3>Your cart is empty</h3>
                        <p>Discover our luxury collection and add some items to your cart</p>
                        <a href="index.php" class="continue-shopping" style="margin-top: 2rem;">
                            <i class="fas fa-arrow-left"></i> Start Shopping
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($cart_items as $item): 
                        $product = $item['product'];
                        $images = explode(',', $product['images']);
                        $firstImage = $images[0] ?? 'https://via.placeholder.com/100';
                    ?>
                        <div class="cart-item" id="cart-item-<?= $product['id'] ?>">
                            <div class="item-image">
                                <img src="uploads/<?= $firstImage ?>" alt="<?= htmlspecialchars($product['name']) ?>">
                            </div>
                            <div class="item-details">
                                <div>
                                    <div class="item-name"><?= htmlspecialchars($product['name']) ?></div>
                                    <div class="item-category"><?= htmlspecialchars($product['category_name']) ?></div>
                                    <div class="item-price">₦<?= number_format($product['price'], 2) ?></div>
                                </div>
                            </div>
                            <div class="item-controls">
                                <div class="quantity-controls">
                                    <button class="quantity-btn" onclick="updateQuantity(<?= $product['id'] ?>, -1)">-</button>
                                    <input type="number" class="quantity-input" id="qty-<?= $product['id'] ?>" 
                                           value="<?= $item['quantity'] ?>" min="1" 
                                           onchange="updateQuantityInput(<?= $product['id'] ?>, this.value)">
                                    <button class="quantity-btn" onclick="updateQuantity(<?= $product['id'] ?>, 1)">+</button>
                                </div>
                                <div class="item-total">₦<?= number_format($item['subtotal'], 2) ?></div>
                                <button class="remove-btn" onclick="removeFromCart(<?= $product['id'] ?>)">
                                    <i class="fas fa-trash"></i> Remove
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Order Summary -->
            <?php if (!empty($cart_items)): ?>
                <div class="order-summary">
                    <h2 class="summary-title">
                        <i class="fas fa-receipt"></i>
                        Order Summary
                    </h2>

                    <div class="summary-row">
                        <span class="summary-label">Subtotal</span>
                        <span class="summary-value">₦<?= number_format($total, 2) ?></span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Shipping</span>
                        <span class="summary-value">Free</span>
                    </div>
                    <div class="summary-row">
                        <span class="summary-label">Tax</span>
                        <span class="summary-value">Included</span>
                    </div>
                    <div class="summary-row" style="border-top: 2px solid var(--primary); padding-top: 1.5rem; margin-top: 0.5rem;">
                        <span class="summary-label summary-total">Total</span>
                        <span class="summary-value summary-total">₦<?= number_format($total, 2) ?></span>
                    </div>

                    <form method="POST" class="checkout-form">
                        <input type="hidden" name="checkout" value="1">
                        
                        <div class="form-group">
                            <label class="form-label" for="customer_name">
                                <i class="fas fa-user"></i> Full Name
                            </label>
                            <input type="text" id="customer_name" name="customer_name" class="form-input" 
                                   placeholder="Enter your full name" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="customer_phone">
                                <i class="fas fa-phone"></i> Phone Number
                            </label>
                            <input type="tel" id="customer_phone" name="customer_phone" class="form-input" 
                                   placeholder="Enter your phone number" required>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="customer_address">
                                <i class="fas fa-map-marker-alt"></i> Delivery Address
                            </label>
                            <textarea id="customer_address" name="customer_address" class="form-input" 
                                      placeholder="Enter your complete delivery address" 
                                      rows="3" required></textarea>
                        </div>

                        <button type="submit" class="checkout-btn">
                            <i class="fab fa-whatsapp"></i> Checkout via WhatsApp
                        </button>
                    </form>

                    <a href="index.php" class="continue-shopping">
                        <i class="fas fa-arrow-left"></i> Continue Shopping
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Luxury Footer -->
    <footer class="luxury-footer">
        <div class="footer-content">
            <div class="social-links">
                <a href="https://www.instagram.com/zeemkhaleem_closet0/" target="_blank">
                    <i class="fab fa-instagram"></i>
                </a>
                <a href="#">
                    <i class="fab fa-facebook-f"></i>
                </a>
                <a href="https://www.tiktok.com/@zeemkhaleem_closets" target="_blank">
                    <i class="fab fa-tiktok"></i>
                </a>
                <a href="https://wa.me/2349160935693" target="_blank">
                    <i class="fab fa-whatsapp"></i>
                </a>
            </div>
            <div class="footer-text">© <?= date('Y') ?> ZEEMKAHLEEM LUXURY — Premium Fashion</div>
            <div class="footer-tagline">Transform your style with our exclusive luxury collection</div>
        </div>
    </footer>

    <script>
        // Theme Management
        function toggleTheme() {
            const currentTheme = document.body.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            document.body.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            
            const themeIcon = document.querySelector('.theme-toggle i');
            themeIcon.className = newTheme === 'light' ? 'fas fa-sun' : 'fas fa-moon';
        }

        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || 'dark';
        document.body.setAttribute('data-theme', savedTheme);
        const themeIcon = document.querySelector('.theme-toggle i');
        themeIcon.className = savedTheme === 'light' ? 'fas fa-sun' : 'fas fa-moon';

        // Cart Management Functions
        async function updateQuantity(productId, change) {
            const input = document.getElementById('qty-' + productId);
            let newQty = parseInt(input.value) + change;
            
            if (newQty < 1) newQty = 1;
            
            await updateCartQuantity(productId, newQty);
        }

        async function updateQuantityInput(productId, newQty) {
            if (newQty < 1) newQty = 1;
            await updateCartQuantity(productId, parseInt(newQty));
        }

        async function updateCartQuantity(productId, quantity) {
            try {
                const response = await fetch('cart.php?action=update_qty', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'product_id=' + productId + '&qty=' + quantity
                });
                
                const data = await response.json();
                
                if (data.ok) {
                    // Update localStorage
                    let localCart = JSON.parse(localStorage.getItem('cart')) || {};
                    if (quantity <= 0) {
                        delete localCart[productId];
                    } else {
                        localCart[productId] = quantity;
                    }
                    localStorage.setItem('cart', JSON.stringify(localCart));
                    
                    // Update cart count
                    document.getElementById('cartCount').textContent = data.count;
                    
                    // Reload the page to reflect changes
                    location.reload();
                }
            } catch (error) {
                console.error('Error updating quantity:', error);
            }
        }

        async function removeFromCart(productId) {
            if (confirm('Are you sure you want to remove this item from your cart?')) {
                try {
                    const response = await fetch('cart.php?action=remove_from_cart', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'product_id=' + productId
                    });
                    
                    const data = await response.json();
                    
                    if (data.ok) {
                        // Update localStorage
                        let localCart = JSON.parse(localStorage.getItem('cart')) || {};
                        delete localCart[productId];
                        localStorage.setItem('cart', JSON.stringify(localCart));
                        
                        // Update cart count
                        document.getElementById('cartCount').textContent = data.count;
                        
                        // Remove item from display
                        const itemElement = document.getElementById('cart-item-' + productId);
                        if (itemElement) {
                            itemElement.style.opacity = '0';
                            itemElement.style.transform = 'translateX(-100px)';
                            setTimeout(() => {
                                location.reload();
                            }, 300);
                        } else {
                            location.reload();
                        }
                    }
                } catch (error) {
                    console.error('Error removing item:', error);
                }
            }
        }

        // Sync cart on page load
        document.addEventListener('DOMContentLoaded', function() {
            const localCart = JSON.parse(localStorage.getItem('cart')) || {};
            const serverCartCount = <?= count($cart_items) ?>;
            
            // If localStorage has items but server doesn't, sync to server
            if (Object.keys(localCart).length > 0 && serverCartCount === 0) {
                fetch('sync_cart.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({cart: localCart})
                }).then(r => r.json()).then(data => {
                    if (data.success) {
                        console.log('Cart synced to server');
                        location.reload();
                    }
                });
            }
            
            // Update cart count
            const cartCount = Object.keys(localCart).length > 0 ? 
                Object.values(localCart).reduce((a, b) => a + b, 0) : 
                <?= array_sum($_SESSION['cart'] ?? []) ?>;
            document.getElementById('cartCount').textContent = cartCount;
        });

        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const checkoutForm = document.querySelector('.checkout-form');
            if (checkoutForm) {
                checkoutForm.addEventListener('submit', function(e) {
                    const name = document.getElementById('customer_name').value.trim();
                    const phone = document.getElementById('customer_phone').value.trim();
                    const address = document.getElementById('customer_address').value.trim();
                    
                    if (!name || !phone || !address) {
                        e.preventDefault();
                        alert('Please fill in all required fields before checkout.');
                    }
                });
            }
        });
    </script>
</body>
</html>
