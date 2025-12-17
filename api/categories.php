<?php
/**
 * API: Получение списка категорий
 * GET /api/categories.php
 * Возвращает все категории из БД
 */

require_once __DIR__ . '/config.php';

try {
    $pdo = getDBConnection();

    $stmt = $pdo->query("
        SELECT 
            id, 
            name, 
            image, 
            display_order AS displayOrder 
        FROM categories 
        ORDER BY display_order ASC
    ");

    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Ответ в формате, который ожидает api.js
    sendJSON([
        'success' => true,
        'data' => $categories,
        'categories' => $categories // Для обратной совместимости
    ]);

} catch (Exception $e) {
    error_log('Categories API error: ' . $e->getMessage());
    sendError('Не удалось загрузить категории', 500);
}
