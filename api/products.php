<?php
/**
 * API: Товары (готовые сборки ПК)
 * 
 * GET /api/products.php                  → все товары
 * GET /api/products.php?category=4k      → товары только из категории 4k (или 2k, fullhd)
 * GET /api/products.php?id=101           → один товар по ID
 * GET /api/products.php?admin=1          → все товары (включая неактивные, для админки)
 * POST /api/products.php                  → создать новый товар
 * PUT /api/products.php?id=101           → обновить товар (можно обновить отдельные поля)
 * DELETE /api/products.php?id=101        → удалить товар из БД
 */

require_once __DIR__ . '/config.php';

try {
    $pdo = getDBConnection();

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Параметры запроса
        $category = $_GET['category'] ?? null;
        $productId = $_GET['id'] ?? null;

        if ($productId !== null) {
            // Получение одного товара по ID
            $stmt = $pdo->prepare("
                SELECT 
                    id,
                    name,
                    price,
                    image,
                    cpu,
                    gpu,
                    description
                FROM products 
                WHERE id = ? AND is_active = 1
            ");
            $stmt->execute([(int)$productId]);
            $product = $stmt->fetch();

            if (!$product) {
                sendError('Товар не найден', 404);
            }

            sendJSON([
                'success' => true,
                'data' => $product,
                'product' => $product // Для обратной совместимости
            ]);
        } elseif ($category !== null) {
            // Товары по категории
            $categoryId = is_numeric($category) ? (int)$category : null;
            
            if ($categoryId === null) {
                sendError('Некорректный ID категории', 400);
            }
            
            // Проверяем существование категории
            $checkStmt = $pdo->prepare("SELECT id FROM categories WHERE id = ?");
            $checkStmt->execute([$categoryId]);
            if (!$checkStmt->fetch()) {
                sendError('Категория не найдена', 404);
            }
            
            $stmt = $pdo->prepare("
                SELECT 
                    id,
                    name,
                    price,
                    image,
                    cpu,
                    gpu,
                    description
                FROM products 
                WHERE category_id = ? AND is_active = 1
                ORDER BY id ASC
            ");
            $stmt->execute([$categoryId]);
            $products = $stmt->fetchAll();

            sendJSON([
                'success' => true,
                'data' => $products,
                'products' => $products // Для обратной совместимости
            ]);
        } else {
            // Все товары (для поиска или админки)
            // Проверяем, запрашивает ли админ-панель
            $showAll = (isset($_GET['admin']) && $_GET['admin'] === '1') || 
                       (isset($_GET['admin_mode']) && $_GET['admin_mode'] === 'true');
            
            $sql = "
                SELECT 
                    id,
                    name,
                    price,
                    image,
                    cpu,
                    gpu,
                    category_id,
                    description,
                    is_active
                FROM products 
            ";
            
            if (!$showAll) {
                $sql .= " WHERE is_active = 1";
            }
            
            $sql .= " ORDER BY category_id, id";
            
            $stmt = $pdo->query($sql);
            $products = $stmt->fetchAll();

            sendJSON([
                'success' => true,
                'data' => $products,
                'products' => $products // Для обратной совместимости
            ]);
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT' || 
              ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['_method']) && $_GET['_method'] === 'PUT') ||
              ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'update')) {
        // Обновление товара (для админ-панели)
        $productId = $_GET['id'] ?? null;
        if (!$productId) {
            sendError('Необходим параметр id', 400);
        }

        $data = getPostData();
        if (!$data) {
            sendError('Некорректный JSON', 400);
        }

        // Проверяем существование товара
        $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
        $stmt->execute([(int)$productId]);
        if (!$stmt->fetch()) {
            sendError('Товар не найден', 404);
        }

        // Формируем запрос на обновление только переданных полей
        $updateFields = [];
        $updateValues = [];

        if (isset($data['category_id'])) {
            $categoryId = (int)$data['category_id'];
            if ($categoryId <= 0) {
                sendError('category_id должен быть положительным числом', 400);
            }
            // Проверяем существование категории
            $checkStmt = $pdo->prepare("SELECT id FROM categories WHERE id = ?");
            $checkStmt->execute([$categoryId]);
            if (!$checkStmt->fetch()) {
                sendError('Категория не найдена', 404);
            }
            $updateFields[] = "category_id = ?";
            $updateValues[] = $categoryId;
        }
        if (isset($data['name'])) {
            $name = trim($data['name']);
            if ($name === '') {
                sendError('name не может быть пустым', 400);
            }
            $updateFields[] = "name = ?";
            $updateValues[] = $name;
        }
        if (isset($data['price'])) {
            $price = (float)$data['price'];
            if ($price < 0) {
                sendError('price не может быть отрицательным', 400);
            }
            $updateFields[] = "price = ?";
            $updateValues[] = $price;
        }
        if (isset($data['image'])) {
            $updateFields[] = "image = ?";
            $updateValues[] = $data['image'];
        }
        if (isset($data['cpu'])) {
            $updateFields[] = "cpu = ?";
            $updateValues[] = $data['cpu'];
        }
        if (isset($data['gpu'])) {
            $updateFields[] = "gpu = ?";
            $updateValues[] = $data['gpu'];
        }
        if (isset($data['description'])) {
            $updateFields[] = "description = ?";
            $updateValues[] = $data['description'];
        }
        if (isset($data['is_active'])) {
            $updateFields[] = "is_active = ?";
            $updateValues[] = (int)$data['is_active'];
        }

        if (empty($updateFields)) {
            sendError('Нет полей для обновления', 400);
        }

        $updateValues[] = (int)$productId;
        $sql = "UPDATE products SET " . implode(", ", $updateFields) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($updateValues);

        // Получаем обновленный товар
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([(int)$productId]);
        $product = $stmt->fetch();

        sendJSON([
            'success' => true,
            'data' => $product,
            'message' => 'Товар успешно обновлен'
        ]);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Создание нового товара (для админ-панели)
        $data = getPostData();
        if (!$data) {
            sendError('Некорректный JSON', 400);
        }

        $categoryId = isset($data['category_id']) ? (int)$data['category_id'] : null;
        $name = isset($data['name']) ? trim($data['name']) : null;
        $price = isset($data['price']) ? $data['price'] : null;

        if (!$categoryId || $categoryId <= 0) {
            sendError('Обязательное поле category_id должно быть положительным числом', 400);
        }
        
        if (!$name || $name === '') {
            sendError('Обязательное поле name не может быть пустым', 400);
        }
        
        if ($price === null || !is_numeric($price) || $price < 0) {
            sendError('Обязательное поле price должно быть неотрицательным числом', 400);
        }

        // Проверяем существование категории
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE id = ?");
        $stmt->execute([$categoryId]);
        if (!$stmt->fetch()) {
            sendError('Категория не найдена', 404);
        }

        // Валидация и очистка данных
        $image = isset($data['image']) ? trim($data['image']) : null;
        $image = $image === '' ? null : $image;
        $cpu = isset($data['cpu']) ? trim($data['cpu']) : null;
        $cpu = $cpu === '' ? null : $cpu;
        $gpu = isset($data['gpu']) ? trim($data['gpu']) : null;
        $gpu = $gpu === '' ? null : $gpu;
        $description = isset($data['description']) ? trim($data['description']) : null;
        $description = $description === '' ? null : $description;
        $isActive = isset($data['is_active']) ? ((int)$data['is_active'] > 0 ? 1 : 0) : 1;

        $stmt = $pdo->prepare("
            INSERT INTO products (category_id, name, price, image, cpu, gpu, description, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $categoryId,
            $name,
            (float)$price,
            $image,
            $cpu,
            $gpu,
            $description,
            $isActive
        ]);

        $productId = $pdo->lastInsertId();

        // Получаем созданный товар
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
        $stmt->execute([$productId]);
        $product = $stmt->fetch();

        sendJSON([
            'success' => true,
            'data' => $product,
            'message' => 'Товар успешно создан'
        ], 201);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        // Реальное удаление товара из БД (для админ-панели)
        $productId = $_GET['id'] ?? null;
        if (!$productId) {
            sendError('Необходим параметр id', 400);
        }

        // Проверяем существование товара
        $stmt = $pdo->prepare("SELECT id FROM products WHERE id = ?");
        $stmt->execute([(int)$productId]);
        if (!$stmt->fetch()) {
            sendError('Товар не найден', 404);
        }

        // Удаляем товар из БД
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        $stmt->execute([(int)$productId]);

        if ($stmt->rowCount() > 0) {
            sendJSON([
                'success' => true,
                'message' => 'Товар успешно удален из базы данных'
            ]);
        } else {
            sendError('Товар не найден', 404);
        }
    } else {
        sendError('Метод не поддерживается', 405);
    }

} catch (Exception $e) {
    error_log('Products API error: ' . $e->getMessage());
    sendError('Не удалось выполнить операцию: ' . $e->getMessage(), 500);
}
