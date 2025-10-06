<?php
require 'config.php';

// Get search and category filter
$search = trim($_GET['q'] ?? '');
$cat_filter = isset($_GET['cat']) ? (int)$_GET['cat'] : 0;
$categories = $pdo->query("SELECT * FROM categories ORDER BY name")->fetchAll();

// Search functionality
$search_results = [];
if (!empty($search)) {
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name 
        FROM products p 
        JOIN categories c ON p.category_id = c.id 
        WHERE LOWER(p.name) LIKE ? OR LOWER(p.description) LIKE ?
        ORDER BY p.created_at DESC
    ");
    $term = '%' . strtolower($search) . '%';
    $stmt->execute([$term, $term]);
    $search_results = $stmt->fetchAll();
}

// Category filtering
$category_products = [];
$current_category = null;
if ($cat_filter > 0) {
    // Get category name
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$cat_filter]);
    $current_category = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get all products from this category
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name 
        FROM products p 
        JOIN categories c ON p.category_id = c.id 
        WHERE p.category_id = ?
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$cat_filter]);
    $category_products = $stmt->fetchAll();
}

// Get latest products from each category (for default view)
$latest_products = [];
foreach ($categories as $category) {
    $stmt = $pdo->prepare("
        SELECT * FROM products 
        WHERE category_id = ? 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute([$category['id']]);
    $products = $stmt->fetchAll();
    if ($products) {
        $latest_products[$category['name']] = $products;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php 
        if (!empty($search)) {
            echo "Search: " . h($search) . " - ZEEMKAHLEEM LUXURY";
        } elseif ($cat_filter > 0 && $current_category) {
            echo h($current_category['name']) . " - ZEEMKAHLEEM LUXURY";
        } else {
            echo "ZEEMKAHLEEM LUXURY - Premium Fashion";
        }
        ?>
    </title>
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
            font-family: 'Playfair Display', 'Inter', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            overflow-x: hidden;
        }

        /* Luxury Loading Animation with Theme Support */
        .loading-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--bg);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            animation: fadeOut 1s ease-in-out 3s forwards;
        }

        .luxury-loader {
            position: relative;
            width: 200px;
            height: 200px;
        }

        .diamond {
            position: absolute;
            width: 80px;
            height: 80px;
            background: linear-gradient(45deg, var(--primary), var(--accent));
            transform: rotate(45deg);
            animation: diamondPulse 2s ease-in-out infinite;
            box-shadow: 0 0 30px var(--primary);
            border-radius: 15px;
        }

        .diamond::before {
            content: '';
            position: absolute;
            top: 10px;
            left: 10px;
            right: 10px;
            bottom: 10px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            transform: rotate(45deg);
            border-radius: 8px;
        }

        .sparkle {
            position: absolute;
            width: 8px;
            height: 8px;
            background: var(--text);
            border-radius: 50%;
            animation: sparkle 2s ease-in-out infinite;
            box-shadow: 0 0 10px currentColor;
        }

        .sparkle:nth-child(1) { top: 20px; left: 20px; animation-delay: 0s; }
        .sparkle:nth-child(2) { top: 20px; right: 20px; animation-delay: 0.5s; }
        .sparkle:nth-child(3) { bottom: 20px; left: 20px; animation-delay: 1s; }
        .sparkle:nth-child(4) { bottom: 20px; right: 20px; animation-delay: 1.5s; }

        .brand-name {
            margin-top: 2rem;
            font-size: 3rem;
            font-weight: bold;
            background: linear-gradient(45deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: textGlow 2s ease-in-out infinite alternate;
        }

        .tagline {
            margin-top: 1rem;
            color: var(--primary);
            font-style: italic;
            letter-spacing: 3px;
            animation: fadeIn 2s ease-in-out;
        }

        @keyframes diamondPulse {
            0%, 100% { 
                transform: rotate(45deg) scale(1);
                box-shadow: 0 0 30px var(--primary);
            }
            50% { 
                transform: rotate(45deg) scale(1.1);
                box-shadow: 0 0 50px var(--accent);
            }
        }

        @keyframes sparkle {
            0%, 100% { opacity: 0; transform: scale(0); }
            50% { opacity: 1; transform: scale(1); }
        }

        @keyframes textGlow {
            from { filter: drop-shadow(0 0 10px rgba(212, 175, 55, 0.5)); }
            to { filter: drop-shadow(0 0 20px rgba(230, 200, 117, 0.8)); }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @keyframes fadeOut {
            to { opacity: 0; visibility: hidden; }
        }

        /* Enhanced Top Bar with Neomorphism */
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

        .menu-toggle {
            display: flex;
            flex-direction: column;
            cursor: pointer;
            gap: 4px;
            z-index: 1001;
            padding: 10px;
            border-radius: 12px;
            background: var(--card);
            box-shadow: var(--shadow);
        }

        .menu-toggle span {
            width: 25px;
            height: 3px;
            background: var(--text);
            transition: all 0.3s ease;
            border-radius: 2px;
        }

        .menu-toggle.active span:nth-child(1) {
            transform: rotate(45deg) translate(6px, 6px);
        }

        .menu-toggle.active span:nth-child(2) {
            opacity: 0;
        }

        .menu-toggle.active span:nth-child(3) {
            transform: rotate(-45deg) translate(6px, -6px);
        }

        .top-bar-actions {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        /* Luxury Glassmorphism Cart Icon with Neomorphism */
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

        /* Neomorphism Theme Toggle */
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

        /* Luxury Sliding Navigation Drawer */
        .nav-drawer {
            position: fixed;
            top: 0;
            left: -100%;
            width: 50%;
            height: 100vh;
            background: var(--card);
            backdrop-filter: blur(20px);
            z-index: 999;
            transition: left 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            padding: 100px 2rem 2rem;
            display: flex;
            flex-direction: column;
            box-shadow: 20px 0 50px rgba(0, 0, 0, 0.3);
            border-right: 1px solid rgba(255, 255, 255, 0.1);
        }

        .nav-drawer.active {
            left: 0;
        }

        .nav-header {
            margin-bottom: 3rem;
            text-align: center;
        }

        .nav-brand {
            font-size: 1.8rem;
            font-weight: bold;
            background: linear-gradient(45deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 0.5rem;
        }

        .nav-tagline {
            color: var(--text);
            opacity: 0.7;
            font-size: 0.9rem;
        }

        .nav-search {
            margin-bottom: 2rem;
        }

        .nav-search input {
            width: 100%;
            padding: 1rem 1.5rem;
            border: none;
            border-radius: 15px;
            background: var(--bg);
            color: var(--text);
            font-family: 'Inter', sans-serif;
            box-shadow: var(--inner-shadow);
            font-size: 1rem;
        }

        .nav-search input:focus {
            outline: none;
            box-shadow: 5px 5px 15px rgba(0, 0, 0, 0.1), -5px -5px 15px rgba(255, 255, 255, 0.1);
        }

        .nav-links {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 1rem;
            flex: 1;
        }

        .nav-links a {
            color: var(--text);
            text-decoration: none;
            font-size: 1.1rem;
            padding: 1rem 1.5rem;
            border-radius: 12px;
            transition: all 0.3s ease;
            font-family: 'Inter', sans-serif;
            display: block;
            background: var(--card);
            box-shadow: var(--shadow);
        }

        .nav-links a:hover {
            background: var(--bg);
            transform: translateX(10px);
            box-shadow: 5px 5px 15px rgba(0, 0, 0, 0.1), -5px -5px 15px rgba(255, 255, 255, 0.05);
        }

        .nav-footer {
            margin-top: auto;
            padding-top: 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .nav-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
            z-index: 998;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .nav-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        /* Hero Section with Neomorphism */
        .hero {
            height: 100vh;
            background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), url('https://images.unsplash.com/photo-1441986300917-64674bd600d8?ixlib=rb-4.0.3&auto=format&fit=crop&w=1950&q=80');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            margin-top: 80px;
            position: relative;
            overflow: hidden;
        }

        .hero-content {
            background: var(--card);
            backdrop-filter: blur(20px);
            padding: 4rem;
            border-radius: 30px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
            z-index: 1;
            animation: fadeInUp 1s ease-out;
            box-shadow: var(--shadow);
        }

        .hero h1 {
            font-size: 4.5rem;
            margin-bottom: 1.5rem;
            background: linear-gradient(45deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: float 3s ease-in-out infinite;
        }

        .hero p {
            font-size: 1.3rem;
            margin-bottom: 2rem;
            opacity: 0.9;
            animation: fadeIn 2s ease-in-out 0.5s both;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Product Grid with Enhanced Responsiveness */
        .container {
            max-width: 1200px;
            margin: 4rem auto;
            padding: 0 2rem;
        }

        .section-title {
            font-size: 3rem;
            text-align: center;
            margin-bottom: 4rem;
            position: relative;
            font-weight: 600;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -15px;
            left: 50%;
            transform: translateX(-50%);
            width: 150px;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--accent));
            border-radius: 2px;
        }

        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 2.5rem;
            margin-bottom: 6rem;
        }

        /* Neomorphism Product Cards */
        .product-card {
            background: var(--card);
            border-radius: 25px;
            padding: 2rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            position: relative;
            overflow: hidden;
            animation: fadeInUp 0.6s ease-out both;
            box-shadow: var(--shadow);
        }

        .product-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: left 0.6s ease;
        }

        .product-card:hover::before {
            left: 100%;
        }

        .product-card:hover {
            transform: translateY(-15px) scale(1.02);
            box-shadow: 15px 15px 30px rgba(0, 0, 0, 0.2), -15px -15px 30px rgba(255, 255, 255, 0.1);
        }

        .product-image {
            width: 100%;
            height: 250px;
            object-fit: cover;
            border-radius: 15px;
            margin-bottom: 1.5rem;
            transition: transform 0.4s ease;
            box-shadow: var(--inner-shadow);
        }

        .product-card:hover .product-image {
            transform: scale(1.05);
        }

        .product-info h3 {
            margin-bottom: 0.8rem;
            font-size: 1.4rem;
            font-weight: 600;
        }

        .product-price {
            color: var(--primary);
            font-weight: bold;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        /* Neomorphism Buttons */
        .add-to-cart {
            width: 100%;
            padding: 1rem;
            background: var(--card);
            color: var(--text);
            border: none;
            border-radius: 15px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 1rem;
            font-size: 1.1rem;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .add-to-cart::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s ease;
        }

        .add-to-cart:hover::before {
            left: 100%;
        }

        .add-to-cart:hover {
            transform: translateY(-2px);
            box-shadow: 5px 5px 15px rgba(0, 0, 0, 0.2), -5px -5px 15px rgba(255, 255, 255, 0.1);
        }

        /* Luxury Footer with Neomorphism */
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

        /* Enhanced Product Modal with Neomorphism */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 2000;
            backdrop-filter: blur(10px);
            animation: modalFadeIn 0.3s ease;
        }

        @keyframes modalFadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: var(--card);
            padding: 3rem;
            border-radius: 30px;
            max-width: 95%;
            max-height: 95%;
            overflow-y: auto;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: var(--shadow);
            animation: modalSlideIn 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translate(-50%, -40%);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%);
            }
        }

        .close-modal {
            position: absolute;
            top: 1.5rem;
            right: 1.5rem;
            background: var(--card);
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: var(--text);
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 1;
            box-shadow: var(--shadow);
        }

        .close-modal:hover {
            transform: rotate(90deg);
            box-shadow: 5px 5px 15px rgba(0, 0, 0, 0.2), -5px -5px 15px rgba(255, 255, 255, 0.1);
        }

        .product-gallery {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 3rem;
            align-items: start;
        }

        .main-image-container {
            position: relative;
        }

        .main-image {
            width: 100%;
            max-height: 500px;
            object-fit: cover;
            border-radius: 20px;
            box-shadow: var(--inner-shadow);
            transition: transform 0.3s ease;
        }

        .image-thumbnails {
            display: flex;
            gap: 0.8rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
            justify-content: center;
        }

        .thumbnail {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 12px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: all 0.3s ease;
            box-shadow: var(--shadow);
        }

        .thumbnail:hover,
        .thumbnail.active {
            border-color: var(--primary);
            transform: scale(1.1);
        }

        .product-details {
            padding: 1rem;
        }

        .product-details h2 {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            color: var(--primary);
            font-weight: 600;
        }

        .product-category {
            color: var(--accent);
            font-style: italic;
            margin-bottom: 1.5rem;
            font-size: 1.2rem;
        }

        .product-price-large {
            font-size: 3rem;
            color: var(--primary);
            font-weight: bold;
            margin: 1.5rem 0;
        }

        .product-description {
            line-height: 1.8;
            margin-bottom: 2.5rem;
            font-size: 1.1rem;
        }

        .action-buttons {
            display: flex;
            gap: 1.5rem;
            margin-top: 2.5rem;
        }

        .btn-primary {
            padding: 1.2rem 2.5rem;
            background: var(--card);
            color: var(--text);
            border: none;
            border-radius: 20px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1.1rem;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.5s ease;
        }

        .btn-primary:hover::before {
            left: 100%;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 5px 5px 15px rgba(0, 0, 0, 0.2), -5px -5px 15px rgba(255, 255, 255, 0.1);
        }

        .btn-secondary {
            padding: 1.2rem 2.5rem;
            background: transparent;
            color: var(--text);
            border: 2px solid var(--primary);
            border-radius: 20px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1.1rem;
            box-shadow: var(--shadow);
        }

        .btn-secondary:hover {
            background: var(--primary);
            color: var(--secondary);
            transform: translateY(-3px);
        }

        /* Enhanced Responsive Design */
        @media (max-width: 1200px) {
            .hero h1 {
                font-size: 3.5rem;
            }
            
            .section-title {
                font-size: 2.5rem;
            }
            
            .nav-drawer {
                width: 50%;
            }
        }

        @media (max-width: 992px) {
            .product-gallery {
                grid-template-columns: 1fr;
                gap: 2rem;
            }
            
            .nav-drawer {
                width: 60%;
            }
        }

        @media (max-width: 768px) {
            .top-bar {
                padding: 0 1rem;
            }
            
            .hero h1 {
                font-size: 2.8rem;
            }
            
            .hero-content {
                padding: 2.5rem;
                margin: 1rem;
            }
            
            .products-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1.5rem;
            }
            
            .section-title {
                font-size: 2.2rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .social-links {
                gap: 1.5rem;
            }
            
            .social-links a {
                width: 50px;
                height: 50px;
                font-size: 1.3rem;
            }
            
            .brand-name {
                font-size: 2.5rem;
            }
            
            .nav-drawer {
                width: 75%;
            }
        }

        @media (max-width: 576px) {
            .top-bar {
                height: 70px;
            }
            
            .hero {
                margin-top: 70px;
            }
            
            .hero h1 {
                font-size: 2.2rem;
            }
            
            .hero p {
                font-size: 1.1rem;
            }
            
            .container {
                padding: 0 1rem;
            }
            
            .products-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }
            
            .product-card {
                padding: 1.5rem;
            }
            
            .modal-content {
                padding: 2rem 1.5rem;
            }
            
            .product-details h2 {
                font-size: 2rem;
            }
            
            .product-price-large {
                font-size: 2.5rem;
            }
            
            .luxury-footer {
                padding: 3rem 1rem 2rem;
            }
            
            .brand-name {
                font-size: 2rem;
            }
            
            .luxury-loader {
                width: 150px;
                height: 150px;
            }
            
            .diamond {
                width: 60px;
                height: 60px;
            }
        }

        @media (max-width: 400px) {
            .cart-icon,
            .theme-toggle {
                width: 45px;
                height: 45px;
            }
            
            .nav-drawer {
                width: 85%;
            }
            
            .hero h1 {
                font-size: 2rem;
            }
            
            .products-grid {
                grid-template-columns: 1fr;
            }
        }

        /* Stagger animations for product cards */
        .products-grid .product-card:nth-child(1) { animation-delay: 0.1s; }
        .products-grid .product-card:nth-child(2) { animation-delay: 0.2s; }
        .products-grid .product-card:nth-child(3) { animation-delay: 0.3s; }
        .products-grid .product-card:nth-child(4) { animation-delay: 0.4s; }
        .products-grid .product-card:nth-child(5) { animation-delay: 0.5s; }
        .products-grid .product-card:nth-child(6) { animation-delay: 0.6s; }

        /* Additional Luxury Animations */
        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }

        .floating {
            animation: float 3s ease-in-out infinite;
        }
    

        /* Category Breadcrumb */
        .breadcrumb {
            max-width: 1200px;
            margin: 100px auto 0;
            padding: 0 2rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .breadcrumb a {
            color: var(--text);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .breadcrumb a:hover {
            color: var(--primary);
        }

        .breadcrumb-separator {
            color: var(--accent);
        }

        .current-category {
            color: var(--primary);
            font-weight: 600;
        }

        /* Category Info Header */
        .category-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .category-title {
            font-size: 3rem;
            font-weight: 700;
            background: linear-gradient(45deg, var(--primary), var(--accent));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 1rem;
        }

        .category-count {
            font-size: 1.2rem;
            opacity: 0.8;
        }

        /* Active Category Highlight */
        .nav-links a.active,
        .mobile-nav-links a.active {
            color: var(--primary);
            background: rgba(212, 175, 55, 0.1);
        }

        .nav-links a.active::after {
            width: 100%;
        }

        /* No Products Message */
        .no-products {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text);
            opacity: 0.7;
        }

        .no-products i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .no-products h3 {
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }

        /* ... (Rest of your existing CSS remains exactly the same) ... */
    </style>
</head>
<body data-theme="dark">
    <!-- Luxury Loading Screen -->
    <div class="loading-screen">
        <div class="luxury-loader">
            <div class="diamond"></div>
            <div class="sparkle"></div>
            <div class="sparkle"></div>
            <div class="sparkle"></div>
            <div class="sparkle"></div>
        </div>
        <div class="brand-name">ZEEMKAHLEEM</div>
        <div class="tagline">LUXURY FASHION</div>
    </div>

    <!-- Top Bar -->
    <div class="top-bar">
        <div class="top-bar-content">
            <div class="brand">
                <a href="index.php" style="text-decoration: none; display: flex; align-items: center; gap: 1rem;">
                    <div class="logo">ZK</div>
                    <span style="font-weight: 600; color: var(--text);">ZEEMKAHLEEM LUXURY</span>
                </a>
            </div>

            <!-- Hamburger Menu -->
            <div class="menu-toggle" id="menuToggle">
                <span></span>
                <span></span>
                <span></span>
            </div>

            <div class="top-bar-actions">
                <a href="cart.php" class="cart-icon">
                    <i class="fas fa-shopping-bag"></i>
                    <span class="cart-count" id="cartCount">0</span>
                </a>
                <button class="theme-toggle" onclick="toggleTheme()">
                    <i class="fas fa-moon"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Luxury Navigation Drawer -->
    <div class="nav-drawer" id="navDrawer">
        <div class="nav-header">
            <div class="nav-brand">ZEEMKAHLEEM LUXURY</div>
            <div class="nav-tagline">Premium Fashion Collection</div>
        </div>

        <div class="nav-search">
            <form method="GET" action="">
                <input type="text" name="q" placeholder="Search luxury items..." value="<?= h($search) ?>">
            </form>
        </div>

        <ul class="nav-links">
            <li>
                <a href="index.php" class="<?= (!$cat_filter && empty($search)) ? 'active' : '' ?>">
                    <i class="fas fa-home"></i> Home
                </a>
            </li>
            <?php foreach ($categories as $category): ?>
                <li>
                    <a href="?cat=<?= $category['id'] ?>" 
                       class="<?= ($cat_filter == $category['id']) ? 'active' : '' ?>">
                        <i class="fas fa-tag"></i> <?= h($category['name']) ?>
                    </a>
                </li>
            <?php endforeach; ?>
            <!-- <li><a href="admin.php"><i class="fas fa-cog"></i> Admin Panel</a></li> -->
        </ul>

        <div class="nav-footer">
            <div style="text-align: center; color: var(--text); opacity: 0.7; font-size: 0.9rem;">
                © <?= date('Y') ?> ZEEMKAHLEEM LUXURY
            </div>
        </div>
    </div>

    <!-- Navigation Overlay -->
    <div class="nav-overlay" id="navOverlay" onclick="closeNavDrawer()"></div>

    <!-- Breadcrumb Navigation -->
    <?php if ($cat_filter > 0 && $current_category): ?>
        <div class="breadcrumb">
            <a href="index.php">Home</a>
            <span class="breadcrumb-separator">/</span>
            <span class="current-category"><?= h($current_category['name']) ?></span>
        </div>
    <?php endif; ?>

    <!-- Hero Section (Only show on homepage) -->
    <?php if (empty($search) && $cat_filter == 0): ?>
        <section class="hero" id="home">
            <div class="hero-content">
                <h1 class="floating">ELEVATE YOUR STYLE</h1>
                <p>Discover the finest luxury fashion collection curated for the sophisticated</p>
                <button class="btn-primary" onclick="scrollToProducts()">
                    EXPLORE COLLECTION
                </button>
            </div>
        </section>
    <?php endif; ?>

    <!-- Main Content -->
    <div class="container">
        <?php if (!empty($search)): ?>
            <!-- Search Results -->
            <div class="page-header">
                <h1 class="page-title">Search Results</h1>
                <p class="page-subtitle">Found <?= count($search_results) ?> results for "<?= h($search) ?>"</p>
            </div>

            <div class="products-grid">
                <?php if (empty($search_results)): ?>
                    <div class="no-products" style="grid-column: 1 / -1;">
                        <i class="fas fa-search"></i>
                        <h3>No products found</h3>
                        <p>Try adjusting your search terms or browse our categories</p>
                        <a href="index.php" class="continue-shopping" style="margin-top: 2rem; display: inline-block;">
                            <i class="fas fa-arrow-left"></i> Back to Home
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($search_results as $product): 
                        $images = explode(',', $product['images']);
                    ?>
                        <div class="product-card" onclick="openProductModal(<?= $product['id'] ?>)">
                            <img src="uploads/<?= h($images[0]) ?>" alt="<?= h($product['name']) ?>" class="product-image">
                            <div class="product-info">
                                <h3><?= h($product['name']) ?></h3>
                                <p style="color: var(--accent); margin-bottom: 0.5rem;"><?= h($product['category_name']) ?></p>
                                <div class="product-price">₦<?= number_format($product['price'], 2) ?></div>
                                <button class="add-to-cart" onclick="event.stopPropagation(); addToCart(<?= $product['id'] ?>)">
                                    <i class="fas fa-shopping-bag"></i> Add to Cart
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        <?php elseif ($cat_filter > 0 && $current_category): ?>
            <!-- Category Products -->
            <div class="category-header">
                <h1 class="category-title"><?= h($current_category['name']) ?></h1>
                <p class="category-count"><?= count($category_products) ?> luxury item<?= count($category_products) !== 1 ? 's' : '' ?> available</p>
            </div>

            <div class="products-grid">
                <?php if (empty($category_products)): ?>
                    <div class="no-products" style="grid-column: 1 / -1;">
                        <i class="fas fa-tag"></i>
                        <h3>No products in this category yet</h3>
                        <p>Check back soon for new arrivals in <?= h($current_category['name']) ?></p>
                        <a href="index.php" class="continue-shopping" style="margin-top: 2rem; display: inline-block;">
                            <i class="fas fa-arrow-left"></i> Back to Home
                        </a>
                    </div>
                <?php else: ?>
                    <?php foreach ($category_products as $product): 
                        $images = explode(',', $product['images']);
                    ?>
                        <div class="product-card" onclick="openProductModal(<?= $product['id'] ?>)">
                            <img src="uploads/<?= h($images[0]) ?>" alt="<?= h($product['name']) ?>" class="product-image">
                            <div class="product-info">
                                <h3><?= h($product['name']) ?></h3>
                                <div class="product-price">₦<?= number_format($product['price'], 2) ?></div>
                                <button class="add-to-cart" onclick="event.stopPropagation(); addToCart(<?= $product['id'] ?>)">
                                    <i class="fas fa-shopping-bag"></i> Add to Cart
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <!-- Default Homepage View -->
            <?php foreach ($latest_products as $category_name => $products): ?>
                <h2 class="section-title">Latest in <?= h($category_name) ?></h2>
                <div class="products-grid">
                    <?php foreach ($products as $product): 
                        $images = explode(',', $product['images']);
                    ?>
                        <div class="product-card" onclick="openProductModal(<?= $product['id'] ?>)">
                            <img src="uploads/<?= h($images[0]) ?>" alt="<?= h($product['name']) ?>" class="product-image">
                            <div class="product-info">
                                <h3><?= h($product['name']) ?></h3>
                                <div class="product-price">₦<?= number_format($product['price'], 2) ?></div>
                                <button class="add-to-cart" onclick="event.stopPropagation(); addToCart(<?= $product['id'] ?>)">
                                    <i class="fas fa-shopping-bag"></i> Add to Cart
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
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

    <!-- Enhanced Product Modal -->
    <div class="modal" id="productModal">
        <div class="modal-content">
            <button class="close-modal" onclick="closeModal()">&times;</button>
            <div id="modalContent"></div>
        </div>
    </div>

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

        // Navigation Drawer
        const menuToggle = document.getElementById('menuToggle');
        const navDrawer = document.getElementById('navDrawer');
        const navOverlay = document.getElementById('navOverlay');

        function openNavDrawer() {
            navDrawer.classList.add('active');
            navOverlay.classList.add('active');
            menuToggle.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeNavDrawer() {
            navDrawer.classList.remove('active');
            navOverlay.classList.remove('active');
            menuToggle.classList.remove('active');
            document.body.style.overflow = '';
        }

        menuToggle.addEventListener('click', openNavDrawer);
        navOverlay.addEventListener('click', closeNavDrawer);

        // Cart Management
        let cart = JSON.parse(localStorage.getItem('cart')) || {};

        function updateCartCount() {
            const count = Object.values(cart).reduce((sum, qty) => sum + qty, 0);
            document.getElementById('cartCount').textContent = count;
        }

        function addToCart(productId) {
            // Update localStorage
            let cart = JSON.parse(localStorage.getItem('cart')) || {};
            cart[productId] = (cart[productId] || 0) + 1;
            localStorage.setItem('cart', JSON.stringify(cart));
            
            // Update server session via AJAX
            fetch('cart.php?action=add_to_cart', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'product_id=' + productId + '&qty=1'
            })
            .then(r => r.json())
            .then(d => {
                if (d.ok) {
                    document.getElementById('cartCount').textContent = d.count;
                    
                    // Animation feedback
                    const btn = event.target;
                    const originalText = btn.innerHTML;
                    btn.innerHTML = '<i class="fas fa-check"></i> Added!';
                    btn.style.background = 'linear-gradient(45deg, #28a745, #20c997)';
                    
                    // Add cart icon animation
                    const cartIcon = document.querySelector('.cart-icon');
                    cartIcon.style.transform = 'scale(1.1)';
                    
                    setTimeout(() => {
                        btn.innerHTML = originalText;
                        btn.style.background = '';
                        cartIcon.style.transform = 'scale(1)';
                    }, 2000);
                }
            })
            .catch(error => console.error('Error adding to cart:', error));
        }

        // Enhanced Product Modal
        async function openProductModal(productId) {
            try {
                const response = await fetch(`product.php?id=${productId}`);
                const product = await response.json();
                
                const modalContent = document.getElementById('modalContent');
                const images = product.images || [];
                
                let modalHTML = `
                    <div class="product-gallery">
                        <div class="main-image-container">
                            <img src="uploads/${images[0]}" alt="${product.name}" class="main-image" id="mainModalImage">
                            ${images.length > 1 ? `
                                <div class="image-thumbnails">
                                    ${images.map((img, index) => `
                                        <img src="uploads/${img}" 
                                             alt="${product.name} - View ${index + 1}"
                                             class="thumbnail ${index === 0 ? 'active' : ''}"
                                             onclick="changeMainImage('uploads/${img}', this)">
                                    `).join('')}
                                </div>
                            ` : ''}
                        </div>
                        <div class="product-details">
                            <h2>${product.name}</h2>
                            <div class="product-category">${product.category_name}</div>
                            <div class="product-price-large">₦${parseFloat(product.price).toFixed(2)}</div>
                            <p class="product-description">${product.description || 'Experience luxury and sophistication with this premium product. Crafted with attention to detail and designed for the discerning customer.'}</p>
                            <div class="action-buttons">
                                <button class="btn-primary" onclick="addToCartFromModal(${product.id})">
                                    <i class="fas fa-shopping-bag"></i> Add to Cart
                                </button>
                                <button class="btn-secondary" onclick="closeModal()">
                                    Continue Shopping
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                
                modalContent.innerHTML = modalHTML;
                document.getElementById('productModal').style.display = 'block';
                document.body.style.overflow = 'hidden';
                
                // Add keyboard navigation
                document.addEventListener('keydown', handleModalKeyboard);
            } catch (error) {
                console.error('Error loading product:', error);
            }
        }

        function addToCartFromModal(productId) {
            cart[productId] = (cart[productId] || 0) + 1;
            localStorage.setItem('cart', JSON.stringify(cart));
            updateCartCount();
            
            // Animation feedback
            const btn = document.querySelector('.btn-primary');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-check"></i> Added to Cart!';
            btn.style.background = 'linear-gradient(45deg, #28a745, #20c997)';
            
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.style.background = '';
            }, 2000);
        }

        function changeMainImage(imageSrc, thumbnail) {
            // Update main image
            const mainImage = document.getElementById('mainModalImage');
            mainImage.style.opacity = '0';
            
            setTimeout(() => {
                mainImage.src = imageSrc;
                mainImage.style.opacity = '1';
            }, 200);
            
            // Update active thumbnail
            document.querySelectorAll('.thumbnail').forEach(thumb => {
                thumb.classList.remove('active');
            });
            thumbnail.classList.add('active');
        }

        function closeModal() {
            document.getElementById('productModal').style.display = 'none';
            document.body.style.overflow = '';
            document.removeEventListener('keydown', handleModalKeyboard);
        }

        function handleModalKeyboard(event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        }

        function scrollToProducts() {
            document.querySelector('.container').scrollIntoView({ 
                behavior: 'smooth' 
            });
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('productModal');
            if (event.target === modal) {
                closeModal();
            }
        }

        // Initialize
        updateCartCount();

        // Remove loading screen after animation
        setTimeout(() => {
            document.querySelector('.loading-screen').style.display = 'none';
        }, 3000);

        // Add intersection observer for animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe all product cards
        document.querySelectorAll('.product-card').forEach(card => {
            card.style.opacity = '0';
            card.style.transform = 'translateY(30px)';
            card.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(card);
        });

        // Sync cart with server
        function syncCartWithServer() {
            const localCart = JSON.parse(localStorage.getItem('cart')) || {};
            const sessionCart = <?= json_encode($_SESSION['cart'] ?? []) ?>;

            if (Object.keys(localCart).length > 0 && Object.keys(sessionCart).length === 0) {
                fetch('sync_cart.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ cart: localCart })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('Cart synced with server');
                        updateCartCount();
                    }
                })
                .catch(error => console.error('Error syncing cart:', error));
            }
            else if (Object.keys(sessionCart).length > 0 && Object.keys(localCart).length === 0) {
                localStorage.setItem('cart', JSON.stringify(sessionCart));
                updateCartCount();
            }
            else if (Object.keys(sessionCart).length > 0) {
                localStorage.setItem('cart', JSON.stringify(sessionCart));
                updateCartCount();
            }
        }

        // Call sync when page loads
        document.addEventListener('DOMContentLoaded', function() {
            syncCartWithServer();
            updateCartCount();
        });
    </script>
</body>
</html>