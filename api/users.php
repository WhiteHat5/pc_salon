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
        // GET запрос - получение пользователя по telegram_id
        $telegramId = $_GET['telegram_id'] ?? null;
        
        if (!$telegramId) {
            sendError('telegram_id обязателен', 400);
        }

        $stmt = $pdo->prepare("SELECT id, telegram_id, first_name, last_name, username, phone, full_name, address, created_at, updated_at FROM users WHERE telegram_id = ?");
        $stmt->execute([$telegramId]);
        $user = $stmt->fetch();

        if (!$user) {
            sendJSON(['success' => true, 'user' => null]);
        } else {
            sendJSON(['success' => true, 'user' => $user]);
        }
    }
    elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // POST запрос - создание или обновление пользователя
        $data = getPostData();

        if (!$data) {
            sendError('Некорректный JSON', 400);
        }

        // Поддерживаем как telegram_id, так и phone для идентификации
        $telegramId   = $data['telegram_id'] ?? null;
        $phone        = $data['phone'] ?? null;
        
        if (!$telegramId && !$phone) {
            sendError('telegram_id или phone обязателен', 400);
        }

        $firstName    = $data['first_name'] ?? null;
        $lastName     = $data['last_name'] ?? null;
        $username     = $data['username'] ?? null;
        $fullName     = $data['full_name'] ?? null;
        $address      = $data['address'] ?? null;

        // Проверяем, существует ли пользователь
        if ($telegramId) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE telegram_id = ?");
            $stmt->execute([$telegramId]);
            $exists = $stmt->fetch();
            $identifier = 'telegram_id';
            $identifierValue = $telegramId;
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
            $stmt->execute([$phone]);
            $exists = $stmt->fetch();
            $identifier = 'phone';
            $identifierValue = $phone;
        }

        if ($exists) {
            // Обновляем существующего пользователя
            $updateFields = [];
            $updateValues = [];
            
            if ($telegramId) {
                $updateFields[] = "telegram_id = ?";
                $updateValues[] = $telegramId;
            }
            if ($firstName !== null) {
                $updateFields[] = "first_name = COALESCE(?, first_name)";
                $updateValues[] = $firstName;
            }
            if ($lastName !== null) {
                $updateFields[] = "last_name = COALESCE(?, last_name)";
                $updateValues[] = $lastName;
            }
            if ($username !== null) {
                $updateFields[] = "username = COALESCE(?, username)";
                $updateValues[] = $username;
            }
            if ($phone !== null) {
                $updateFields[] = "phone = COALESCE(?, phone)";
                $updateValues[] = $phone;
            }
            if ($fullName !== null) {
                $updateFields[] = "full_name = COALESCE(?, full_name)";
                $updateValues[] = $fullName;
            }
            if ($address !== null) {
                $updateFields[] = "address = COALESCE(?, address)";
                $updateValues[] = $address;
            }
            
            $updateValues[] = $exists['id'];
            
            $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($updateValues);
            
            $userId = $exists['id'];
        } else {
            // Создаём нового пользователя
            $sql = "INSERT INTO users (telegram_id, first_name, last_name, username, phone, full_name, address)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$telegramId, $firstName, $lastName, $username, $phone, $fullName, $address]);
            $userId = $pdo->lastInsertId();
        }

        // Возвращаем обновлённого пользователя
        $stmt = $pdo->prepare("SELECT id, telegram_id, first_name, last_name, username, phone, full_name, address, created_at, updated_at FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        sendJSON(['success' => true, 'user' => $user, 'message' => 'Пользователь сохранён']);
    }
    else {
        sendError('Метод не поддерживается', 405);
    }

} catch (Exception $e) {
    error_log('Users API error: ' . $e->getMessage());
    sendError('Ошибка сервера: ' . $e->getMessage(), 500);
}