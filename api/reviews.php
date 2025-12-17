<?php
/**
 * API: Отзывы
 * GET  /api/reviews.php?user_id=123 — получить отзывы пользователя
 * GET  /api/reviews.php?product_id=456 — получить отзывы по товару
 * GET  /api/reviews.php?published=true — получить опубликованные отзывы
 * POST /api/reviews.php — создать отзыв
 */

require_once __DIR__ . '/config.php';

try {
    $pdo = getDBConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $userId = $_GET['user_id'] ?? null;
        $productId = $_GET['product_id'] ?? null;
        $orderId = $_GET['order_id'] ?? null;
        $published = $_GET['published'] ?? null;

        $where = [];
        $params = [];

        if ($userId !== null) {
            $where[] = "r.user_id = ?";
            $params[] = (int)$userId;
        }
        if ($productId !== null) {
            $where[] = "r.product_id = ?";
            $params[] = (int)$productId;
        }
        if ($orderId !== null) {
            $where[] = "r.order_id = ?";
            $params[] = (int)$orderId;
        }
        if ($published !== null) {
            $where[] = "r.is_published = ?";
            $params[] = $published === 'true' ? 1 : 0;
        }

        $sql = "
            SELECT 
                r.id,
                r.user_id,
                r.order_id,
                r.product_id,
                r.rating,
                r.title,
                r.comment,
                r.is_published,
                r.created_at,
                r.updated_at,
                u.first_name,
                u.last_name,
                u.username,
                p.name AS product_name
            FROM reviews r
            LEFT JOIN users u ON r.user_id = u.id
            LEFT JOIN products p ON r.product_id = p.id
        ";

        if (!empty($where)) {
            $sql .= " WHERE " . implode(" AND ", $where);
        }

        $sql .= " ORDER BY r.created_at DESC";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $reviews = $stmt->fetchAll();

        sendJSON([
            'success' => true,
            'data' => $reviews,
            'reviews' => $reviews // Для обратной совместимости
        ]);

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = getPostData();

        if (!$data) {
            sendError('Некорректный JSON', 400);
        }

        $userId = $data['user_id'] ?? null;
        $orderId = $data['order_id'] ?? null;
        $productId = $data['product_id'] ?? null;
        $rating = $data['rating'] ?? null;
        $title = $data['title'] ?? null;
        $comment = $data['comment'] ?? null;

        if (!$userId || !$rating) {
            sendError('user_id и rating обязательны', 400);
        }

        if ($rating < 1 || $rating > 5) {
            sendError('Рейтинг должен быть от 1 до 5', 400);
        }

        $stmt = $pdo->prepare("
            INSERT INTO reviews (user_id, order_id, product_id, rating, title, comment, is_published)
            VALUES (?, ?, ?, ?, ?, ?, 0)
        ");

        $stmt->execute([
            (int)$userId,
            $orderId ? (int)$orderId : null,
            $productId ? (int)$productId : null,
            (int)$rating,
            $title,
            $comment
        ]);

        $reviewId = $pdo->lastInsertId();

        // Получаем созданный отзыв
        $stmt = $pdo->prepare("
            SELECT 
                r.*,
                u.first_name,
                u.last_name,
                p.name AS product_name
            FROM reviews r
            LEFT JOIN users u ON r.user_id = u.id
            LEFT JOIN products p ON r.product_id = p.id
            WHERE r.id = ?
        ");
        $stmt->execute([$reviewId]);
        $review = $stmt->fetch();

        sendJSON([
            'success' => true,
            'data' => $review,
            'message' => 'Отзыв успешно создан'
        ], 201);

    } else {
        sendError('Метод не поддерживается', 405);
    }

} catch (Exception $e) {
    error_log('Reviews API error: ' . $e->getMessage());
    sendError('Ошибка сервера: ' . $e->getMessage(), 500);
}


