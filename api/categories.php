<?php
/**
 * API для работы с категориями
 */

require_once 'config.php';

$method = $_SERVER['REQUEST_METHOD'];
$pdo = getDBConnection();

switch ($method) {
    case 'GET':
        // Получить список всех категорий
        try {
            $stmt = $pdo->query("SELECT * FROM categories ORDER BY display_order ASC");
            $categories = $stmt->fetchAll();
            sendJSON(['success' => true, 'data' => $categories]);
        } catch (PDOException $e) {
            sendError('Failed to fetch categories: ' . $e->getMessage(), 500);
        }
        break;
        
    default:
        sendError('Method not allowed', 405);
        break;
}

