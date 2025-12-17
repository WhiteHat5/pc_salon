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
            // Получение одного заказа
            $stmt = $pdo->prepare("
                SELECT * FROM orders WHERE id = ?
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
            // Сначала находим пользователя
            $stmt = $pdo->prepare("SELECT id, phone FROM users WHERE telegram_id = ?");
            $stmt->execute([(int)$telegramId]);
            $user = $stmt->fetch();
            
            $orders = [];
            if ($user) {
                // Находим заказы по user_id
                $stmt = $pdo->prepare("
                    SELECT * FROM orders 
                    WHERE user_id = ? 
                    ORDER BY created_at DESC
                ");
                $stmt->execute([$user['id']]);
                $ordersByUserId = $stmt->fetchAll();
                
                // Также находим заказы по телефону (на случай, если заказ был создан без user_id)
                if ($user['phone']) {
                    $stmt = $pdo->prepare("
                        SELECT * FROM orders 
                        WHERE user_id IS NULL AND phone = ? 
                        ORDER BY created_at DESC
                    ");
                    $stmt->execute([$user['phone']]);
                    $ordersByPhone = $stmt->fetchAll();
                    
                    // Объединяем результаты, убирая дубликаты
                    $orderIds = [];
                    foreach ($ordersByUserId as $order) {
                        $orders[] = $order;
                        $orderIds[] = $order['id'];
                    }
                    foreach ($ordersByPhone as $order) {
                        if (!in_array($order['id'], $orderIds)) {
                            $orders[] = $order;
                        }
                    }
                } else {
                    $orders = $ordersByUserId;
                }
            } else {
                // Если пользователь не найден, возвращаем пустой массив
                $orders = [];
            }

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
        $userId        = isset($data['user_id']) && $data['user_id'] !== '' ? (int)$data['user_id'] : null;
        $deliveryType  = $data['delivery_type'] ?? 'pickup';
        $fullName      = trim($data['full_name'] ?? '');
        $phone         = trim($data['phone'] ?? '');
        $address       = isset($data['address']) ? trim($data['address']) : null;
        $address       = $address === '' ? null : $address;
        $comment       = isset($data['comment']) ? trim($data['comment']) : null;
        $comment       = $comment === '' ? null : $comment;
        $totalAmount   = isset($data['total_amount']) ? (float)$data['total_amount'] : 0;
        $paymentMethod = $data['payment_method'] ?? 'cash';
        $items         = $data['items'] ?? [];

        // Валидация обязательных полей
        if (empty($items) || !is_array($items)) {
            throw new Exception('Список товаров пуст или некорректен');
        }
        
        if (empty($fullName)) {
            throw new Exception('Имя обязательно для заполнения');
        }
        
        if (empty($phone)) {
            throw new Exception('Телефон обязателен для заполнения');
        }
        
        // Валидация формата телефона (базовая)
        if (!preg_match('/^[\d\s\-\+\(\)]+$/', $phone)) {
            throw new Exception('Некорректный формат телефона');
        }
        
        if ($totalAmount <= 0) {
            throw new Exception('Сумма заказа должна быть больше нуля');
        }

        // Для доставки адрес обязателен
        if ($deliveryType === 'delivery' && (empty($address) || trim($address) === '')) {
            throw new Exception('Адрес доставки обязателен для заказа с доставкой');
        }
        
        // Валидация типа доставки
        if (!in_array($deliveryType, ['pickup', 'delivery'])) {
            throw new Exception('Некорректный тип доставки');
        }
        
        // Валидация способа оплаты
        if (!in_array($paymentMethod, ['cash', 'card', 'online'])) {
            $paymentMethod = 'cash'; // Значение по умолчанию
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
            if (!is_array($item)) {
                throw new Exception('Элемент заказа должен быть массивом');
            }
            
            $productId = isset($item['product_id']) && $item['product_id'] !== '' && $item['product_id'] !== null ? (int)$item['product_id'] : null;
            $itemType  = $item['item_type'] ?? 'product';
            $itemName  = trim($item['item_name'] ?? 'Без названия');
            $quantity  = isset($item['quantity']) ? max(1, (int)$item['quantity']) : 1;
            $unitPrice = isset($item['unit_price']) ? (float)$item['unit_price'] : 0;
            $totalPrice = isset($item['total_price']) ? (float)$item['total_price'] : ($unitPrice * $quantity);
            $itemSpecs = isset($item['item_specs']) ? trim($item['item_specs']) : null;
            $itemSpecs = $itemSpecs === '' ? null : $itemSpecs;
            
            // Валидация типа товара
            if (!in_array($itemType, ['product', 'config', 'pc'])) {
                $itemType = 'product';
            }

            // Для типов 'config' и 'pc' product_id должен быть NULL
            if ($itemType !== 'product') {
                $productId = null;
            }

            // Проверяем существование товара в БД, если product_id указан
            if ($productId !== null && $itemType === 'product') {
                $checkStmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND is_active = 1");
                $checkStmt->execute([$productId]);
                $productExists = $checkStmt->fetch();
                
                // Если товар не существует в БД, устанавливаем product_id в NULL
                if (!$productExists) {
                    error_log("Product ID {$productId} not found in products table or inactive, setting to NULL");
                    $productId = null;
                }
            }
            
            // Валидация названия
            if (empty($itemName)) {
                $itemName = 'Товар без названия';
            }
            
            // Валидация цен
            if ($unitPrice < 0) {
                $unitPrice = 0;
            }
            if ($totalPrice < 0) {
                $totalPrice = $unitPrice * $quantity;
            }

            // Подготовка config_data для JSON
            $configData = null;
            if (isset($item['config_data']) && !empty($item['config_data'])) {
                if (is_array($item['config_data'])) {
                    $configData = json_encode($item['config_data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                } else {
                    // Если уже строка, пытаемся декодировать и перекодировать для валидации
                    $decoded = json_decode($item['config_data'], true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $configData = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    }
                }
            }

            $stmtItem->execute([
                $orderId,
                $productId, // Может быть NULL
                $itemType,
                $itemName,
                $itemSpecs,
                $quantity,
                $unitPrice,
                $totalPrice,
                $configData
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
