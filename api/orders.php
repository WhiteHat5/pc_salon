<?php
/**
 * API: Заказы — создание
 * POST /api/orders.php
 */

require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError('Только POST запросы', 405);
}

$data = getPostData();
if (!$data) {
    sendError('Некорректный JSON', 400);
}

try {
    $pdo = getDBConnection();
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
        'order' => [
            'id' => (int)$orderId,
            'order_number' => $orderNumber
        ],
        'message' => 'Заказ успешно сохранён'
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Orders API error: ' . $e->getMessage());

    sendJSON([
        'success' => false,
        'error' => 'Ошибка сохранения заказа: ' . $e->getMessage()
    ], 500);
}