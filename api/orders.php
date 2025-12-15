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
        $orderId = $_GET['id'] ?? null;

        if ($orderId !== null) {
            // Получение одного заказа с товарами
            $stmt = $pdo->prepare("
                SELECT * FROM orders WHERE id = ?
            ");
            $stmt->execute([(int)$orderId]);
            $order = $stmt->fetch();

            if (!$order) {
                sendError('Заказ не найден', 404);
            }

            // Получаем товары заказа
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

        } elseif ($userId !== null) {
            // Получение заказов пользователя
            $stmt = $pdo->prepare("
                SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC
            ");
            $stmt->execute([(int)$userId]);
            $orders = $stmt->fetchAll();

            sendJSON([
                'success' => true,
                'data' => $orders,
                'orders' => $orders // Для обратной совместимости
            ]);

        } else {
            sendError('Необходим параметр user_id или id', 400);
        }

    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = getPostData();
        if (!$data) {
            sendError('Некорректный JSON', 400);
        }

        // Логируем входящие данные для отладки (всегда, не только в debug режиме)
        error_log('Order data received: ' . json_encode([
            'delivery_type' => $data['delivery_type'] ?? 'not set',
            'full_name' => $data['full_name'] ?? 'not set',
            'phone' => $data['phone'] ?? 'not set',
            'address' => isset($data['address']) ? (empty($data['address']) ? 'empty' : 'set') : 'not set',
            'items_count' => isset($data['items']) && is_array($data['items']) ? count($data['items']) : 0,
            'total_amount' => $data['total_amount'] ?? 0
        ], JSON_UNESCAPED_UNICODE));
        
        if (isset($_GET['debug'])) {
            error_log('Full order data (debug): ' . json_encode($data, JSON_UNESCAPED_UNICODE));
        }

        $pdo->beginTransaction();

        // Генерация номера заказа (с защитой от дубликатов)
        // Используем максимальный существующий номер + 1, а не COUNT
        $year = date('Y');
        $stmt = $pdo->prepare("SELECT order_number FROM orders WHERE order_number LIKE ? ORDER BY order_number DESC LIMIT 1");
        $stmt->execute(["AURUM-{$year}-%"]);
        $lastOrder = $stmt->fetch();
        
        $orderNumber = null;
        if ($lastOrder) {
            // Извлекаем номер из последнего заказа (например, AURUM-2025-0019 -> 19)
            $parts = explode('-', $lastOrder['order_number']);
            $lastNumber = (int)end($parts);
            $nextNumber = $lastNumber + 1;
        } else {
            // Если заказов за этот год нет, начинаем с 1
            $nextNumber = 1;
        }
        
        // Генерируем номер и проверяем уникальность
        $maxAttempts = 100; // Увеличиваем количество попыток
        for ($attempt = 0; $attempt < $maxAttempts; $attempt++) {
            $orderNumber = 'AURUM-' . $year . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
            
            // Проверяем, не существует ли уже такой номер
            $stmt = $pdo->prepare("SELECT id FROM orders WHERE order_number = ?");
            $stmt->execute([$orderNumber]);
            if (!$stmt->fetch()) {
                break; // Номер уникален
            }
            // Если номер существует, пробуем следующий
            $nextNumber++;
        }
        
        if (!$orderNumber) {
            throw new Exception('Не удалось сгенерировать уникальный номер заказа после ' . $maxAttempts . ' попыток');
        }

        // Основные данные заказа
        $userId        = isset($data['user_id']) && $data['user_id'] !== null && $data['user_id'] !== '' ? (int)$data['user_id'] : null;
        
        // Проверяем существование пользователя, если user_id указан
        if ($userId !== null) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            if (!$stmt->fetch()) {
                // Пользователь не существует, но это не критично - можем создать заказ без user_id
                error_log("Warning: User ID {$userId} not found, creating order without user_id");
                $userId = null;
            }
        }
        $deliveryType  = $data['delivery_type'] ?? 'pickup';
        $fullName      = trim($data['full_name'] ?? '');
        $phone         = trim($data['phone'] ?? '');
        $totalAmount   = (float)($data['total_amount'] ?? 0);
        $paymentMethod = $data['payment_method'] ?? 'cash';
        $items         = $data['items'] ?? [];
        
        // Обработка адреса - важно правильно обработать для delivery
        $address = null;
        if (isset($data['address'])) {
            $address = trim($data['address']);
            // Если после trim() пустая строка, делаем null
            if ($address === '') {
                $address = null;
            }
        }
        
        // Комментарий опционален
        $comment = null;
        if (isset($data['comment']) && trim($data['comment']) !== '') {
            $comment = trim($data['comment']);
        }

        // Валидация данных заказа
        if (empty($items) || !is_array($items)) {
            throw new Exception('Список товаров пуст или некорректен');
        }
        if (empty($fullName)) {
            throw new Exception('Не указано имя');
        }
        if (empty($phone)) {
            throw new Exception('Не указан телефон');
        }
        if ($totalAmount <= 0) {
            throw new Exception('Сумма заказа должна быть больше нуля');
        }
        if (!in_array($deliveryType, ['delivery', 'pickup'])) {
            throw new Exception('Некорректный тип доставки: ' . $deliveryType);
        }
        
        // Для доставки адрес обязателен и не может быть пустым
        if ($deliveryType === 'delivery') {
            if ($address === null || $address === '' || trim($address) === '') {
                error_log('Order validation failed: delivery type requires address. Address value: ' . var_export($address, true));
                throw new Exception('Для доставки необходимо указать адрес');
            }
        }
        
        // Логирование для отладки (в режиме разработки)
        if (isset($_GET['debug'])) {
            error_log('Order data before insert: ' . json_encode([
                'user_id' => $userId,
                'delivery_type' => $deliveryType,
                'full_name' => $fullName,
                'phone' => $phone,
                'address' => $address,
                'total_amount' => $totalAmount,
                'items_count' => count($items)
            ], JSON_UNESCAPED_UNICODE));
        }

        // Создаём заказ
        // Важно: для delivery адрес должен быть не NULL
        try {
            $stmt = $pdo->prepare("
                INSERT INTO orders 
                (user_id, order_number, delivery_type, full_name, phone, address, comment, total_amount, payment_method)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $orderNumber, $deliveryType, $fullName, $phone, $address, $comment, $totalAmount, $paymentMethod]);
            $orderId = $pdo->lastInsertId();
            
            if (!$orderId) {
                throw new Exception('Не удалось создать заказ. orderId не получен.');
            }
        } catch (PDOException $e) {
            error_log('Order insert error: ' . $e->getMessage());
            error_log('Order data: ' . json_encode([
                'user_id' => $userId,
                'delivery_type' => $deliveryType,
                'address' => $address,
                'full_name' => $fullName
            ], JSON_UNESCAPED_UNICODE));
            throw new Exception('Ошибка при создании заказа в БД: ' . $e->getMessage());
        }

        // Подготовка вставки товаров
        $stmtItem = $pdo->prepare("
            INSERT INTO order_items 
            (order_id, product_id, item_type, item_name, item_specs, quantity, unit_price, total_price, config_data)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($items as $index => $item) {
            try {
                $productId = isset($item['product_id']) && $item['product_id'] !== null && $item['product_id'] !== '' 
                    ? (int)$item['product_id'] 
                    : null;
                $itemType  = $item['item_type'] ?? 'product';

                // Для типов 'config' и 'pc' product_id может быть NULL — это нормально
                if ($itemType !== 'product' && $productId !== null) {
                    $productId = null; // Принудительно обнуляем для кастомных и PC
                }
                
                // Если product_id указан, проверяем существование товара
                if ($productId !== null && $itemType === 'product') {
                    $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ? AND is_active = 1");
                    $stmt->execute([$productId]);
                    if (!$stmt->fetch()) {
                        // Товар не найден или неактивен, но продолжаем (может быть удален после добавления в корзину)
                        error_log("Warning: Product ID {$productId} not found or inactive, but continuing with order");
                    }
                }

                $itemName = $item['item_name'] ?? 'Без названия';
                $itemSpecs = !empty($item['item_specs']) ? $item['item_specs'] : null;
                $quantity = isset($item['quantity']) ? max(1, (int)$item['quantity']) : 1;
                $unitPrice = (float)($item['unit_price'] ?? 0);
                $totalPrice = (float)($item['total_price'] ?? 0);
                
                // Если total_price не указан, вычисляем
                if ($totalPrice <= 0) {
                    $totalPrice = $unitPrice * $quantity;
                }

                $configData = null;
                if (isset($item['config_data']) && $item['config_data'] !== null) {
                    if (is_array($item['config_data'])) {
                        $configData = json_encode($item['config_data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    } else {
                        $configData = $item['config_data']; // Уже JSON строка
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
            } catch (Exception $e) {
                throw new Exception("Ошибка при добавлении товара #" . ($index + 1) . ": " . $e->getMessage());
            }
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

} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        try {
            $pdo->rollBack();
        } catch (Exception $rollbackE) {
            error_log('Rollback error: ' . $rollbackE->getMessage());
        }
    }
    
    $errorMessage = $e->getMessage();
    $errorCode = $e->getCode();
    $errorInfo = $e->errorInfo ?? [];
    
    // Логируем детальную информацию об ошибке БД
    error_log('Orders API PDO error: ' . $errorMessage);
    error_log('Orders API PDO error code: ' . $errorCode);
    error_log('Orders API PDO error info: ' . json_encode($errorInfo, JSON_UNESCAPED_UNICODE));
    
    // Определяем понятное сообщение об ошибке
    $userMessage = 'Ошибка сохранения заказа в базе данных';
    if (strpos($errorMessage, 'Duplicate entry') !== false || strpos($errorMessage, '1062') !== false) {
        $userMessage = 'Заказ с таким номером уже существует. Попробуйте еще раз.';
    } elseif (strpos($errorMessage, 'Foreign key constraint') !== false || strpos($errorMessage, '1452') !== false) {
        $userMessage = 'Ошибка связи данных. Проверьте корректность товаров в заказе.';
    } elseif (strpos($errorMessage, 'Cannot add or update a child row') !== false) {
        $userMessage = 'Ошибка связи данных. Возможно, товар был удален.';
    } elseif (strpos($errorMessage, 'SQLSTATE') !== false) {
        $userMessage = 'Ошибка базы данных. Проверьте логи сервера.';
    }
    
    $errorResponse = [
        'success' => false,
        'error' => $userMessage
    ];
    
    if (isset($_GET['debug'])) {
        $errorResponse['debug'] = [
            'message' => $errorMessage,
            'code' => $errorCode,
            'info' => $errorInfo
        ];
    }
    
    sendJSON($errorResponse, 500);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        try {
            $pdo->rollBack();
        } catch (Exception $rollbackE) {
            error_log('Rollback error: ' . $rollbackE->getMessage());
        }
    }
    
    $errorMessage = $e->getMessage();
    $errorTrace = $e->getTraceAsString();
    
    error_log('Orders API error: ' . $errorMessage);
    error_log('Orders API error trace: ' . $errorTrace);
    
    // В режиме отладки возвращаем более подробную информацию
    $errorResponse = [
        'success' => false,
        'error' => 'Ошибка сохранения заказа: ' . $errorMessage
    ];
    
    if (isset($_GET['debug'])) {
        $errorResponse['debug'] = [
            'message' => $errorMessage,
            'trace' => $errorTrace
        ];
    }
    
    sendJSON($errorResponse, 500);
}
