<?php
/**
 * API: Заказы
 * POST /api/orders.php — создание заказа
 * GET  /api/orders.php?user_id=123 — получить заказы пользователя
 * GET  /api/orders.php?id=456 — получить заказ по ID
 */

require_once __DIR__ . '/config.php';

try {
    $pdo = getDBConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $userId = $_GET['user_id'] ?? null;
        $telegramId = $_GET['telegram_id'] ?? null;
        $orderId = $_GET['id'] ?? null;

        if ($orderId !== null) {
            // Получение одного заказа с товарами
            $stmt = $pdo->prepare("
                SELECT o.*, 
                       GROUP_CONCAT(
                           JSON_OBJECT(
                               'id', oi.id,
                               'product_id', oi.product_id,
                               'item_type', oi.item_type,
                               'item_name', oi.item_name,
                               'item_specs', oi.item_specs,
                               'quantity', oi.quantity,
                               'unit_price', oi.unit_price,
                               'total_price', oi.total_price,
                               'config_data', oi.config_data
                           ) SEPARATOR '|||'
                       ) as items_json
                FROM orders o
                LEFT JOIN order_items oi ON o.id = oi.order_id
                WHERE o.id = ?
                GROUP BY o.id
            ");
            $stmt->execute([(int)$orderId]);
            $order = $stmt->fetch();

            if (!$order) {
                sendError('Заказ не найден', 404);
            }

            // Получаем товары заказа отдельным запросом
            $stmt = $pdo->prepare("
                SELECT * FROM order_items WHERE order_id = ?
            ");
            $stmt->execute([(int)$orderId]);
            $order['items'] = $stmt->fetchAll();

            sendJSON([
                'success' => true,
                'data' => $order,
                'order' => $order // Для обратной совместимости
            ]);

        } elseif ($telegramId !== null) {
            // Получение заказов пользователя по telegram_id
            $stmt = $pdo->prepare("
                SELECT o.* FROM orders o
                INNER JOIN users u ON o.user_id = u.id
                WHERE u.telegram_id = ?
                ORDER BY o.created_at DESC
            ");
            $stmt->execute([(int)$telegramId]);
            $orders = $stmt->fetchAll();

            // Для каждого заказа получаем товары
            foreach ($orders as &$order) {
                $stmt = $pdo->prepare("
                    SELECT * FROM order_items WHERE order_id = ?
                ");
                $stmt->execute([$order['id']]);
                $order['items'] = $stmt->fetchAll();
            }
            unset($order);

            sendJSON([
                'success' => true,
                'data' => $orders,
                'orders' => $orders // Для обратной совместимости
            ]);

        } elseif ($userId !== null) {
            // Получение заказов пользователя по user_id
            $stmt = $pdo->prepare("
                SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC
            ");
            $stmt->execute([(int)$userId]);
            $orders = $stmt->fetchAll();

            // Для каждого заказа получаем товары
            foreach ($orders as &$order) {
                $stmt = $pdo->prepare("
                    SELECT * FROM order_items WHERE order_id = ?
                ");
                $stmt->execute([$order['id']]);
                $order['items'] = $stmt->fetchAll();
            }
            unset($order);

            sendJSON([
                'success' => true,
                'data' => $orders,
                'orders' => $orders // Для обратной совместимости
            ]);

        } else {
            sendError('Необходим параметр user_id, telegram_id или id', 400);
        }

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = getPostData();
        if (!$data) {
            sendError('Некорректный JSON', 400);
        }

        $pdo->beginTransaction();

        // Генерация номера заказа
        $count = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn() + 1;
        $orderNumber = 'AURUM-' . date('Y') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

        // Основные данные заказа
        $userId        = $data['user_id'] ?? null;
        $deliveryType  = $data['delivery_type'] ?? 'pickup';
        $fullName      = $data['full_name'] ?? '';
        $phone         = $data['phone'] ?? '';
        $address       = $data['address'] ?? null;
        $comment       = $data['comment'] ?? null;
        $totalAmount   = (float)($data['total_amount'] ?? 0);
        $paymentMethod = $data['payment_method'] ?? 'cash';
        $items         = $data['items'] ?? [];

        if (empty($items) || empty($fullName) || empty($phone) || $totalAmount <= 0) {
            throw new Exception('Обязательные поля не заполнены');
        }

        // Создаём заказ
        $stmt = $pdo->prepare("
            INSERT INTO orders 
            (user_id, order_number, delivery_type, full_name, phone, address, comment, total_amount, payment_method)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $orderNumber, $deliveryType, $fullName, $phone, $address, $comment, $totalAmount, $paymentMethod]);
        $orderId = $pdo->lastInsertId();

        // Подготовка вставки товаров
        $stmtItem = $pdo->prepare("
            INSERT INTO order_items 
            (order_id, product_id, item_type, item_name, item_specs, quantity, unit_price, total_price, config_data)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($items as $item) {
            $productId = !empty($item['product_id']) ? (int)$item['product_id'] : null;
            $itemType  = $item['item_type'] ?? 'product';

            // Для типов 'config' и 'pc' product_id может быть NULL — это нормально
            if ($itemType !== 'product' && $productId !== null) {
                $productId = null; // Принудительно обнуляем для кастомных и PC
            }

            $stmtItem->execute([
                $orderId,
                $productId, // Может быть NULL
                $itemType,
                $item['item_name'] ?? 'Без названия',
                $item['item_specs'] ?? null,
                $item['quantity'] ?? 1,
                (float)($item['unit_price'] ?? 0),
                (float)($item['total_price'] ?? 0),
                isset($item['config_data']) ? json_encode($item['config_data'], JSON_UNESCAPED_UNICODE) : null
            ]);
        }

        $pdo->commit();

        sendJSON([
            'success' => true,
            'data' => [
                'id' => (int)$orderId,
                'order_number' => $orderNumber
            ],
            'order' => [
                'id' => (int)$orderId,
                'order_number' => $orderNumber
            ],
            'message' => 'Заказ успешно сохранён'
        ]);

    } else {
        sendError('Метод не поддерживается', 405);
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Orders API error: ' . $e->getMessage());

    sendJSON([
        'success' => false,
        'error' => 'Ошибка сохранения заказа: ' . $e->getMessage()
    ], 500);
}
