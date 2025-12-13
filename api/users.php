<?php
/**
 * API для работы с пользователями
 */

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();

switch ($method) {
    case 'GET':
        // Получить пользователя
        $telegramId = $_GET['telegram_id'] ?? null;
        $userId = $_GET['id'] ?? null;
        
        if (!$telegramId && !$userId) {
            sendError('telegram_id or id parameter required', 400);
        }
        
        try {
            if ($telegramId) {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE telegram_id = ?");
                $stmt->execute([$telegramId]);
            } else {
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$userId]);
            }
            
            $user = $stmt->fetch();
            
            if (!$user) {
                sendError('User not found', 404);
            }
            
            sendJSON(['success' => true, 'data' => $user]);
        } catch (PDOException $e) {
            sendError('Failed to fetch user: ' . $e->getMessage(), 500);
        }
        break;
        
    case 'POST':
        // Создать или обновить пользователя
        $data = getPostData();
        
        // Проверяем наличие хотя бы одного идентификатора (telegram_id или phone)
        if (!isset($data['telegram_id']) && !isset($data['phone'])) {
            sendError('telegram_id or phone is required', 400);
        }
        
        try {
            $existingUser = null;
            $identifierField = null;
            $identifierValue = null;
            
            // Ищем существующего пользователя по telegram_id или phone
            if (isset($data['telegram_id'])) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE telegram_id = ?");
                $stmt->execute([$data['telegram_id']]);
                $existingUser = $stmt->fetch();
                $identifierField = 'telegram_id';
                $identifierValue = $data['telegram_id'];
            }
            
            // Если не нашли по telegram_id, ищем по phone
            if (!$existingUser && isset($data['phone'])) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE phone = ?");
                $stmt->execute([$data['phone']]);
                $existingUser = $stmt->fetch();
                $identifierField = 'phone';
                $identifierValue = $data['phone'];
            }
            
            if ($existingUser) {
                // Обновляем существующего пользователя
                $updateFields = [];
                $updateValues = [];
                
                if (isset($data['telegram_id'])) {
                    $updateFields[] = "telegram_id = ?";
                    $updateValues[] = $data['telegram_id'];
                }
                if (isset($data['first_name'])) {
                    $updateFields[] = "first_name = COALESCE(?, first_name)";
                    $updateValues[] = $data['first_name'];
                }
                if (isset($data['last_name'])) {
                    $updateFields[] = "last_name = COALESCE(?, last_name)";
                    $updateValues[] = $data['last_name'];
                }
                if (isset($data['full_name']) && !isset($data['first_name']) && !isset($data['last_name'])) {
                    // Если передан full_name, разбиваем на first_name и last_name
                    $nameParts = explode(' ', $data['full_name'], 2);
                    $updateFields[] = "first_name = COALESCE(?, first_name)";
                    $updateValues[] = $nameParts[0];
                    if (isset($nameParts[1])) {
                        $updateFields[] = "last_name = COALESCE(?, last_name)";
                        $updateValues[] = $nameParts[1];
                    }
                }
                if (isset($data['username'])) {
                    $updateFields[] = "username = COALESCE(?, username)";
                    $updateValues[] = $data['username'];
                }
                if (isset($data['phone'])) {
                    $updateFields[] = "phone = COALESCE(?, phone)";
                    $updateValues[] = $data['phone'];
                }
                if (isset($data['address'])) {
                    $updateFields[] = "address = COALESCE(?, address)";
                    $updateValues[] = $data['address'];
                }
                
                $updateFields[] = "last_activity = CURRENT_TIMESTAMP";
                
                // Добавляем WHERE условие
                $updateValues[] = $existingUser['id'];
                
                $updateStmt = $pdo->prepare("
                    UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?
                ");
                
                $updateStmt->execute($updateValues);
                $userId = $existingUser['id'];
            } else {
                // Создаем нового пользователя
                // Обрабатываем full_name
                $firstName = $data['first_name'] ?? null;
                $lastName = $data['last_name'] ?? null;
                if (!$firstName && isset($data['full_name'])) {
                    $nameParts = explode(' ', $data['full_name'], 2);
                    $firstName = $nameParts[0];
                    $lastName = isset($nameParts[1]) ? $nameParts[1] : null;
                }
                
                $insertStmt = $pdo->prepare("
                    INSERT INTO users (telegram_id, first_name, last_name, username, phone, address, last_activity)
                    VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                ");
                
                $insertStmt->execute([
                    $data['telegram_id'] ?? null,
                    $firstName,
                    $lastName,
                    $data['username'] ?? null,
                    $data['phone'] ?? null,
                    $data['address'] ?? null
                ]);
                
                $userId = $pdo->lastInsertId();
                
                // Создаем запись статистики
                $statsStmt = $pdo->prepare("INSERT INTO user_statistics (user_id, registration_date) VALUES (?, CURRENT_TIMESTAMP)");
                $statsStmt->execute([$userId]);
            }
            
            // Получаем обновленного пользователя
            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            sendJSON(['success' => true, 'data' => $user], 201);
            
        } catch (PDOException $e) {
            sendError('Failed to create/update user: ' . $e->getMessage(), 500);
        }
        break;
        
    default:
        sendError('Method not allowed', 405);
        break;
}

