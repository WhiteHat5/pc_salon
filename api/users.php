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
        
        if (!isset($data['telegram_id'])) {
            sendError('telegram_id is required', 400);
        }
        
        try {
            // Проверяем, существует ли пользователь
            $stmt = $pdo->prepare("SELECT id FROM users WHERE telegram_id = ?");
            $stmt->execute([$data['telegram_id']]);
            $existingUser = $stmt->fetch();
            
            if ($existingUser) {
                // Обновляем существующего пользователя
                $updateStmt = $pdo->prepare("
                    UPDATE users SET 
                        first_name = COALESCE(?, first_name),
                        last_name = COALESCE(?, last_name),
                        username = COALESCE(?, username),
                        phone = COALESCE(?, phone),
                        address = COALESCE(?, address),
                        last_activity = CURRENT_TIMESTAMP
                    WHERE telegram_id = ?
                ");
                
                $updateStmt->execute([
                    $data['first_name'] ?? null,
                    $data['last_name'] ?? null,
                    $data['username'] ?? null,
                    $data['phone'] ?? null,
                    $data['address'] ?? null,
                    $data['telegram_id']
                ]);
                
                $userId = $existingUser['id'];
            } else {
                // Создаем нового пользователя
                $insertStmt = $pdo->prepare("
                    INSERT INTO users (telegram_id, first_name, last_name, username, phone, address, last_activity)
                    VALUES (?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                ");
                
                $insertStmt->execute([
                    $data['telegram_id'],
                    $data['first_name'] ?? null,
                    $data['last_name'] ?? null,
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

