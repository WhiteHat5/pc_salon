<?php
/**
 * API: Пользователи
 * POST /api/users.php — создание или обновление пользователя по Telegram данным
 * GET  /api/users.php?telegram_id=123456789 — получение пользователя
 */

require_once __DIR__ . '/config.php';

try {
    $pdo = getDBConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $telegramId = $_GET['telegram_id'] ?? null;

        if (!$telegramId) {
            sendError('Параметр telegram_id обязателен', 400);
        }

        $stmt = $pdo->prepare("SELECT id, telegram_id, first_name, last_name, username, phone, full_name, address FROM users WHERE telegram_id = ?");
        $stmt->execute([$telegramId]);
        $user = $stmt->fetch();

        if (!$user) {
            sendJSON(['success' => true, 'user' => null]);
        } else {
            sendJSON(['success' => true, 'user' => $user]);
        }
    }

    elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $data = getPostData();

        if (!$data || !isset($data['telegram_id'])) {
            sendError('Некорректные данные', 400);
        }

        $telegramId   = $data['telegram_id'];
        $firstName    = $data['first_name'] ?? null;
        $lastName     = $data['last_name'] ?? null;
        $username     = $data['username'] ?? null;
        $phone        = $data['phone'] ?? null;
        $fullName     = $data['full_name'] ?? null;
        $address      = $data['address'] ?? null;

        // Проверяем, существует ли пользователь
        $stmt = $pdo->prepare("SELECT id FROM users WHERE telegram_id = ?");
        $stmt->execute([$telegramId]);
        $exists = $stmt->fetch();

        if ($exists) {
            // Обновляем
            $sql = "UPDATE users SET 
                    first_name = ?, last_name = ?, username = ?, 
                    phone = ?, full_name = ?, address = ?
                    WHERE telegram_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$firstName, $lastName, $username, $phone, $fullName, $address, $telegramId]);
        } else {
            // Создаём нового
            $sql = "INSERT INTO users (telegram_id, first_name, last_name, username, phone, full_name, address)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$telegramId, $firstName, $lastName, $username, $phone, $fullName, $address]);
        }

        sendJSON(['success' => true, 'message' => 'Пользователь сохранён']);
    }

    else {
        sendError('Метод не поддерживается', 405);
    }

} catch (Exception $e) {
    error_log('Users API error: ' . $e->getMessage());
    sendError('Ошибка сервера', 500);
}