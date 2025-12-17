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
        
        // Валидация telegram_id (должен быть числом)
        if (!is_numeric($telegramId) || (int)$telegramId <= 0) {
            sendError('Некорректный telegram_id', 400);
        }

        $stmt = $pdo->prepare("SELECT id, telegram_id, first_name, last_name, username, phone, full_name, address, created_at, updated_at FROM users WHERE telegram_id = ?");
        $stmt->execute([(int)$telegramId]);
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
        $telegramId   = isset($data['telegram_id']) && $data['telegram_id'] !== '' && $data['telegram_id'] !== null ? (int)$data['telegram_id'] : null;
        $phone        = isset($data['phone']) ? trim($data['phone']) : null;
        $phone        = $phone === '' ? null : $phone;
        
        if (!$telegramId && !$phone) {
            sendError('telegram_id или phone обязателен', 400);
        }
        
        // Валидация telegram_id
        if ($telegramId !== null && $telegramId <= 0) {
            sendError('Некорректный telegram_id', 400);
        }
        
        // Валидация телефона (базовая проверка формата)
        if ($phone !== null && !preg_match('/^[\d\s\-\+\(\)]+$/', $phone)) {
            sendError('Некорректный формат телефона', 400);
        }

        $firstName    = isset($data['first_name']) ? trim($data['first_name']) : null;
        $firstName    = $firstName === '' ? null : $firstName;
        $lastName     = isset($data['last_name']) ? trim($data['last_name']) : null;
        $lastName     = $lastName === '' ? null : $lastName;
        $username     = isset($data['username']) ? trim($data['username']) : null;
        $username     = $username === '' ? null : $username;
        $fullName     = isset($data['full_name']) ? trim($data['full_name']) : null;
        $fullName     = $fullName === '' ? null : $fullName;
        $address      = isset($data['address']) ? trim($data['address']) : null;
        $address      = $address === '' ? null : $address;
        
        // Валидация длины полей
        if ($firstName !== null && mb_strlen($firstName) > 100) {
            sendError('first_name не может быть длиннее 100 символов', 400);
        }
        if ($lastName !== null && mb_strlen($lastName) > 100) {
            sendError('last_name не может быть длиннее 100 символов', 400);
        }
        if ($username !== null && mb_strlen($username) > 100) {
            sendError('username не может быть длиннее 100 символов', 400);
        }
        if ($fullName !== null && mb_strlen($fullName) > 200) {
            sendError('full_name не может быть длиннее 200 символов', 400);
        }
        if ($address !== null && mb_strlen($address) > 500) {
            sendError('address не может быть длиннее 500 символов', 400);
        }
        if ($phone !== null && mb_strlen($phone) > 20) {
            sendError('phone не может быть длиннее 20 символов', 400);
        }

        // Проверяем, существует ли пользователь
        if ($telegramId) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE telegram_id = ?");
            $stmt->execute([$telegramId]);
            $exists = $stmt->fetch();
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
            $stmt->execute([$phone]);
            $exists = $stmt->fetch();
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