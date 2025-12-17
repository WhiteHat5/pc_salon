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
            if (!is_numeric($userId) || (int)$userId <= 0) {
                sendError('Некорректный user_id', 400);
            }
            $where[] = "r.user_id = ?";
            $params[] = (int)$userId;
        }
        if ($productId !== null) {
            if (!is_numeric($productId) || (int)$productId <= 0) {
                sendError('Некорректный product_id', 400);
            }
            $where[] = "r.product_id = ?";
            $params[] = (int)$productId;
        }
        if ($orderId !== null) {
            if (!is_numeric($orderId) || (int)$orderId <= 0) {
                sendError('Некорректный order_id', 400);
            }
            $where[] = "r.order_id = ?";
            $params[] = (int)$orderId;
        }
        if ($published !== null) {
            $where[] = "r.is_published = ?";
            $params[] = ($published === 'true' || $published === '1' || $published === 1) ? 1 : 0;
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

        $userId = isset($data['user_id']) ? (int)$data['user_id'] : null;
        $orderId = isset($data['order_id']) && $data['order_id'] !== '' && $data['order_id'] !== null ? (int)$data['order_id'] : null;
        $productId = isset($data['product_id']) && $data['product_id'] !== '' && $data['product_id'] !== null ? (int)$data['product_id'] : null;
        $rating = isset($data['rating']) ? (int)$data['rating'] : null;
        $title = isset($data['title']) ? trim($data['title']) : null;
        $title = $title === '' ? null : $title;
        $comment = isset($data['comment']) ? trim($data['comment']) : null;
        $comment = $comment === '' ? null : $comment;

        // Валидация обязательных полей
        if (!$userId || $userId <= 0) {
            sendError('user_id обязателен и должен быть положительным числом', 400);
        }
        
        if ($rating === null) {
            sendError('rating обязателен', 400);
        }

        if ($rating < 1 || $rating > 5) {
            sendError('Рейтинг должен быть от 1 до 5', 400);
        }
        
        // Проверяем существование пользователя
        $checkStmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $checkStmt->execute([$userId]);
        if (!$checkStmt->fetch()) {
            sendError('Пользователь не найден', 404);
        }
        
        // Если указан order_id, проверяем существование заказа
        if ($orderId !== null) {
            $checkStmt = $pdo->prepare("SELECT id FROM orders WHERE id = ?");
            $checkStmt->execute([$orderId]);
            if (!$checkStmt->fetch()) {
                sendError('Заказ не найден', 404);
            }
        }
        
        // Если указан product_id, проверяем существование товара
        if ($productId !== null) {
            $checkStmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
            $checkStmt->execute([$productId]);
            if (!$checkStmt->fetch()) {
                sendError('Товар не найден', 404);
            }
        }
        
        // Валидация длины заголовка
        if ($title !== null && mb_strlen($title) > 200) {
            sendError('Заголовок не может быть длиннее 200 символов', 400);
        }
        
        // Валидация длины комментария
        if ($comment !== null && mb_strlen($comment) > 2000) {
            sendError('Комментарий не может быть длиннее 2000 символов', 400);
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


