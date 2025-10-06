<?php
require 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $cart = $input['cart'] ?? [];
    
    // Validate cart data
    $validatedCart = [];
    foreach ($cart as $productId => $quantity) {
        $productId = (int)$productId;
        $quantity = max(1, (int)$quantity);
        if ($productId > 0) {
            $validatedCart[$productId] = $quantity;
        }
    }
    
    // Update session cart
    $_SESSION['cart'] = $validatedCart;
    
    echo json_encode([
        'success' => true,
        'count' => array_sum($validatedCart),
        'message' => 'Cart synced successfully'
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request']);
?>