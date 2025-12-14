<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/config.php';

<?php
/**
 * API: Заказы
 * POST /api/orders.php — создание нового заказа
 * Тело запроса: JSON с данными заказа и массивом items
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
    $orderNumber = 'AURUM-' . date('Y') . '-' . str_pad($pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn() + 1, 4, '0', STR_PAD_LEFT);

    // Данные заказа
    $userId        = $data['user_id'] ?? null; // может быть null для гостей
    $deliveryType  = $data['delivery_type'] ?? 'pickup';
    $fullName      = $data['full_name'] ?? '';
    $phone         = $data['phone'] ?? '';
    $address       = $data['address'] ?? null;
    $comment       = $data['comment'] ?? null;
    $totalAmount   = $data['total_amount'] ?? 0;
    $paymentMethod = $data['payment_method'] ?? 'cash';
    $items         = $data['items'] ?? [];

    if (empty($items) || empty($fullName) || empty($phone) || $totalAmount <= 0) {
        throw new Exception('Некорректные данные заказа');
    }

    // Сохраняем заказ
    $stmt = $pdo->prepare("
        INSERT INTO orders 
        (user_id, order_number, delivery_type, full_name, phone, address, comment, total_amount, payment_method)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$userId, $orderNumber, $deliveryType, $fullName, $phone, $address, $comment, $totalAmount, $paymentMethod]);
    $orderId = $pdo->lastInsertId();

    // Сохраняем товары в заказе
    $stmtItem = $pdo->prepare("
        INSERT INTO order_items 
        (order_id, product_id, item_type, item_name, item_specs, quantity, unit_price, total_price, config_data)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($items as $item) {
        $stmtItem->execute([
            $orderId,
            $item['product_id'] ?? null,
            $item['item_type'] ?? 'product',
            $item['item_name'] ?? 'Неизвестный товар',
            $item['item_specs'] ?? null,
            $item['quantity'] ?? 1,
            $item['unit_price'] ?? 0,
            $item['total_price'] ?? 0,
            isset($item['config_data']) ? json_encode($item['config_data'], JSON_UNESCAPED_UNICODE) : null
        ]);
    }

    $pdo->commit();

    sendJSON([
        'success' => true,
        'order' => [
            'id' => $orderId,
            'order_number' => $orderNumber
        ],
        'message' => 'Заказ успешно сохранён'
    ]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Orders API error: ' . $e->getMessage());
    sendError('Не удалось сохранить заказ: ' . $e->getMessage(), 500);
}