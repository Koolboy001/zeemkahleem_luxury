<?php
require 'config.php';

if (isset($_GET['id'])) {
    $productId = (int)$_GET['id'];
    
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, c.name as category_name 
            FROM products p 
            JOIN categories c ON p.category_id = c.id 
            WHERE p.id = ?
        ");
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            $product['images'] = explode(',', $product['images']);
            header('Content-Type: application/json');
            echo json_encode($product);
            exit;
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
        exit;
    }
}

http_response_code(404);
echo json_encode(['error' => 'Product not found']);
?>