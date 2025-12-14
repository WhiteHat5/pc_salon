<?php
/**
 * API: Товары (готовые сборки ПК)
 * 
 * GET /api/products.php                  → все товары
 * GET /api/products.php?category=4k      → товары только из категории 4k (или 2k, fullhd)
 * GET /api/products.php?id=101           → один товар по ID (для будущего использования)
 */

require_once __DIR__ . '/config.php';

try {
    $pdo = getDBConnection();

    // Параметры запроса
    $category = $_GET['category'] ?? null;
    $productId = $_GET['id'] ?? null;

    if ($productId !== null) {
        // Получение одного товара по ID
        $stmt = $pdo->prepare("
            SELECT 
                id,
                name,
                price,
                image,
                cpu,
                gpu,
                description
            FROM products 
            WHERE id = ? AND is_active = 1
        ");
        $stmt->execute([(int)$productId]);
        $product = $stmt->fetch();

        if (!$product) {
            sendError('Товар не найден', 404);
        }

        sendJSON([
            'success' => true,
            'product' => $product
        ]);
    } elseif ($category !== null) {
        // Товары по категории
        $stmt = $pdo->prepare("
            SELECT 
                id,
                name,
                price,
                image,
                cpu,
                gpu,
                description
            FROM products 
            WHERE category_id = ? AND is_active = 1
            ORDER BY id ASC
        ");
        $stmt->execute([$category]);
        $products = $stmt->fetchAll();

        sendJSON([
            'success' => true,
            'products' => $products
        ]);
    } else {
        // Все товары (для поиска или админки)
        $stmt = $pdo->query("
            SELECT 
                id,
                name,
                price,
                image,
                cpu,
                gpu,
                category_id,
                description
            FROM products 
            WHERE is_active = 1
            ORDER BY category_id, id
        ");
        $products = $stmt->fetchAll();

        sendJSON([
            'success' => true,
            'products' => $products
        ]);
    }

} catch (Exception $e) {
    error_log('Products API error: ' . $e->getMessage());
    sendError('Не удалось загрузить товары', 500);
}