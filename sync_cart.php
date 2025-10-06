<?php
require 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get input data
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        // Check if we have form data instead of JSON
        if (empty($data) && isset($_POST['cart'])) {
            $data = json_decode($_POST['cart'], true);
        }
        
        $cart = $data['cart'] ?? [];
        
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
        
    } catch (Exception $e) {
        error_log("Cart sync error: " . $e->getMessage());
        echo json_encode([
            'success' => false, 
            'message' => 'Error syncing cart: ' . $e->getMessage()
        ]);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Invalid request method']);
?>
