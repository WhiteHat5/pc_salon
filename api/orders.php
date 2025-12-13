<?php
/**
 * API для работы с заказами
 */

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();

switch ($method) {
    case 'POST':
        // Создать заказ
        $data = getPostData();
        
        if (!isset($data['items']) || !isset($data['delivery_type'])) {
            sendError('Missing required fields: items, delivery_type', 400);
        }
        
        if (!isset($data['phone']) && !isset($data['user_id'])) {
            sendError('phone or user_id is required', 400);
        }
        
        try {
            $pdo->beginTransaction();
            
            // Если user_id не передан, создаем или находим пользователя по телефону
            $userId = $data['user_id'] ?? null;
            if (!$userId && isset($data['phone'])) {
                // Ищем пользователя по телефону
                $userStmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
                $userStmt->execute([$data['phone']]);
                $existingUser = $userStmt->fetch();
                
                if ($existingUser) {
                    $userId = $existingUser['id'];
                } else {
                    // Создаем нового пользователя
                    $nameParts = isset($data['full_name']) ? explode(' ', $data['full_name'], 2) : [null, null];
                    $insertUserStmt = $pdo->prepare("
                        INSERT INTO users (first_name, last_name, phone, address, last_activity)
                        VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
                    ");
                    $insertUserStmt->execute([
                        $nameParts[0] ?? null,
                        isset($nameParts[1]) ? $nameParts[1] : null,
                        $data['phone'],
                        $data['address'] ?? null
                    ]);
                    $userId = $pdo->lastInsertId();
                    
                    // Создаем запись статистики
                    $statsStmt = $pdo->prepare("INSERT INTO user_statistics (user_id, registration_date) VALUES (?, CURRENT_TIMESTAMP)");
                    $statsStmt->execute([$userId]);
                }
            }
            
            // Генерируем номер заказа
            $orderNumber = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            
            // Создаем заказ
            $stmt = $pdo->prepare("
                INSERT INTO orders (
                    user_id, order_number, delivery_type, full_name, phone, 
                    address, comment, total_amount, payment_method, payment_status, order_status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $userId,
                $orderNumber,
                $data['delivery_type'],
                $data['full_name'] ?? '',
                $data['phone'] ?? '',
                $data['address'] ?? null,
                $data['comment'] ?? null,
                $data['total_amount'] ?? 0,
                $data['payment_method'] ?? 'cash',
                'pending',
                'new'
            ]);
            
            $orderId = $pdo->lastInsertId();
            
            // Добавляем товары в заказ
            $itemsStmt = $pdo->prepare("
                INSERT INTO order_items (
                    order_id, product_id, config_id, item_type, item_name, 
                    item_specs, quantity, unit_price, total_price, config_data
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            foreach ($data['items'] as $item) {
                $configData = isset($item['config_data']) ? json_encode($item['config_data']) : null;
                
                $itemsStmt->execute([
                    $orderId,
                    $item['product_id'] ?? null,
                    $item['config_id'] ?? null,
                    $item['item_type'] ?? 'product',
                    $item['item_name'] ?? '',
                    $item['item_specs'] ?? null,
                    $item['quantity'] ?? 1,
                    $item['unit_price'] ?? 0,
                    $item['total_price'] ?? 0,
                    $configData
                ]);
            }
            
            $pdo->commit();
            
            sendJSON([
                'success' => true, 
                'message' => 'Order created successfully',
                'data' => ['order_id' => $orderId, 'order_number' => $orderNumber]
            ], 201);
            
        } catch (PDOException $e) {
            $pdo->rollBack();
            sendError('Failed to create order: ' . $e->getMessage(), 500);
        }
        break;
        
    case 'GET':
        // Получить заказы
        $userId = $_GET['user_id'] ?? null;
        $orderId = $_GET['id'] ?? null;
        
        try {
            if ($orderId) {
                // Получить один заказ с товарами
                $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
                $stmt->execute([$orderId]);
                $order = $stmt->fetch();
                
                if (!$order) {
                    sendError('Order not found', 404);
                }
                
                // Получаем товары заказа
                $itemsStmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ?");
                $itemsStmt->execute([$orderId]);
                $order['items'] = $itemsStmt->fetchAll();
                
                sendJSON(['success' => true, 'data' => $order]);
            } elseif ($userId) {
                // Получить заказы пользователя
                $stmt = $pdo->prepare("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC");
                $stmt->execute([$userId]);
                $orders = $stmt->fetchAll();
                sendJSON(['success' => true, 'data' => $orders]);
            } else {
                sendError('user_id or id parameter required', 400);
            }
        } catch (PDOException $e) {
            sendError('Failed to fetch orders: ' . $e->getMessage(), 500);
        }
        break;
        
    default:
        sendError('Method not allowed', 405);
        break;
}

