<?php
/**
 * Скрипт для импорта товаров в базу данных
 * Запускается один раз для заполнения базы начальными данными
 */

require_once 'config.php';

$pdo = getDBConnection();

// Данные товаров (из вашего index.html)
$products = [
    // 4K Gaming
    ['category_id' => '4k', 'name' => 'AURUM 4K Pro', 'price' => 349990, 'image' => 'photo/pc4_1.jpg', 'cpu' => 'i9-13900K', 'gpu' => 'RTX4090'],
    ['category_id' => '4k', 'name' => 'AURUM 4K Elite', 'price' => 299990, 'image' => 'photo/pc4_2.jpg', 'cpu' => 'i7-13700K', 'gpu' => 'RTX4080'],
    ['category_id' => '4k', 'name' => 'AURUM 4K Standard', 'price' => 249990, 'image' => 'photo/pc4_3.jpg', 'cpu' => 'R9 7900X', 'gpu' => 'RTX4070 TI'],
    ['category_id' => '4k', 'name' => 'AURUM 4K Basic', 'price' => 199990, 'image' => 'photo/pc4_4.jpg', 'cpu' => 'R7 7700X', 'gpu' => 'RTX4070'],
    
    // 2K Gaming
    ['category_id' => '2k', 'name' => 'AURUM 2K Ultra', 'price' => 239990, 'image' => 'photo/pc2_1.jpg', 'cpu' => 'i7-13700KF', 'gpu' => 'RTX4080 SUPER'],
    ['category_id' => '2k', 'name' => 'AURUM 2K Pro', 'price' => 199990, 'image' => 'photo/pc2_2.jpg', 'cpu' => 'R7 7800X3D', 'gpu' => 'RTX4070 TI SUPER'],
    ['category_id' => '2k', 'name' => 'AURUM 2K Core', 'price' => 169990, 'image' => 'photo/pc2_3.jpg', 'cpu' => 'i5-13600KF', 'gpu' => 'RTX4070 SUPER'],
    ['category_id' => '2k', 'name' => 'AURUM 2K Starter', 'price' => 149990, 'image' => 'photo/pc2_4.jpg', 'cpu' => 'R5 7600', 'gpu' => 'RTX4060 Ti'],
    
    // Full HD Gaming
    ['category_id' => 'fullhd', 'name' => 'AURUM FHD Ultra', 'price' => 139990, 'image' => 'photo/pc_fh1.jpg', 'cpu' => 'i5-13400F', 'gpu' => 'RTX4060 Ti'],
    ['category_id' => 'fullhd', 'name' => 'AURUM FHD Pro', 'price' => 119990, 'image' => 'photo/pc_fh2.jpg', 'cpu' => 'R5 7600', 'gpu' => 'RTX4060'],
    ['category_id' => 'fullhd', 'name' => 'AURUM FHD Core', 'price' => 99990, 'image' => 'photo/pc_fh3.jpg', 'cpu' => 'i5-12400F', 'gpu' => 'RTX3060 Ti'],
    ['category_id' => 'fullhd', 'name' => 'AURUM FHD Starter', 'price' => 89990, 'image' => 'photo/pc_fh4.jpg', 'cpu' => 'R5 5600', 'gpu' => 'RTX3050'],
];

try {
    $pdo->beginTransaction();
    
    $stmt = $pdo->prepare("
        INSERT INTO products (category_id, name, price, image, cpu, gpu, is_active) 
        VALUES (?, ?, ?, ?, ?, ?, 1)
    ");
    
    $imported = 0;
    foreach ($products as $product) {
        $stmt->execute([
            $product['category_id'],
            $product['name'],
            $product['price'],
            $product['image'],
            $product['cpu'],
            $product['gpu']
        ]);
        $imported++;
    }
    
    $pdo->commit();
    
    echo "Successfully imported {$imported} products!\n";
    
} catch (PDOException $e) {
    $pdo->rollBack();
    echo "Error importing products: " . $e->getMessage() . "\n";
}

