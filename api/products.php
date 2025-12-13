<?php
/**
 * API для работы с товарами
 */

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();

switch ($method) {
    case 'GET':
        // Получить товары
        $categoryId = $_GET['category_id'] ?? null;
        $productId = $_GET['id'] ?? null;
        
        try {
            if ($productId) {
                // Получить один товар по ID
                $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND is_active = 1");
                $stmt->execute([$productId]);
                $product = $stmt->fetch();
                
                if (!$product) {
                    sendError('Product not found', 404);
                }
                
                sendJSON(['success' => true, 'data' => $product]);
            } elseif ($categoryId) {
                // Получить товары по категории
                $stmt = $pdo->prepare("SELECT * FROM products WHERE category_id = ? AND is_active = 1 ORDER BY id ASC");
                $stmt->execute([$categoryId]);
                $products = $stmt->fetchAll();
                sendJSON(['success' => true, 'data' => $products]);
            } else {
                // Получить все товары
                $stmt = $pdo->query("SELECT * FROM products WHERE is_active = 1 ORDER BY category_id, id ASC");
                $products = $stmt->fetchAll();
                sendJSON(['success' => true, 'data' => $products]);
            }
        } catch (PDOException $e) {
            sendError('Failed to fetch products: ' . $e->getMessage(), 500);
        }
        break;
        
    default:
        sendError('Method not allowed', 405);
        break;
}

