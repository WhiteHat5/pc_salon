-- ============================================
-- Минимальная схема БД для мини-приложения AURUM (PC Salon)
-- Только необходимые таблицы для текущего функционала
-- ============================================

CREATE DATABASE IF NOT EXISTS pc_salon CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pc_salon;

-- ============================================
-- 1. ПОЛЬЗОВАТЕЛИ
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    telegram_id BIGINT UNIQUE COMMENT 'Уникальный ID пользователя в Telegram',
    first_name VARCHAR(100) COMMENT 'Имя',
    last_name VARCHAR(100) COMMENT 'Фамилия',
    username VARCHAR(100) COMMENT 'Username в Telegram',
    phone VARCHAR(20) COMMENT 'Номер телефона',
    full_name VARCHAR(255) COMMENT 'ФИО (для заказов без Telegram)',
    address TEXT COMMENT 'Адрес доставки',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_telegram_id (telegram_id),
    INDEX idx_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2. КАТЕГОРИИ
-- ============================================
CREATE TABLE IF NOT EXISTS categories (
    id VARCHAR(50) PRIMARY KEY COMMENT 'ID категории (4k, 2k, fullhd)',
    name VARCHAR(100) NOT NULL COMMENT 'Название категории',
    image VARCHAR(255) COMMENT 'Путь к изображению категории',
    display_order INT DEFAULT 0 COMMENT 'Порядок отображения',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. ТОВАРЫ (готовые сборки)
-- ============================================
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id VARCHAR(50) NOT NULL COMMENT 'ID категории',
    name VARCHAR(255) NOT NULL COMMENT 'Название товара',
    price DECIMAL(10, 2) NOT NULL COMMENT 'Базовая цена',
    image VARCHAR(255) COMMENT 'Путь к изображению',
    cpu VARCHAR(100) COMMENT 'Процессор',
    gpu VARCHAR(100) COMMENT 'Видеокарта',
    description TEXT COMMENT 'Полное описание товара',
    is_active BOOLEAN DEFAULT TRUE COMMENT 'Активен ли товар',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    INDEX idx_category (category_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. ЗАКАЗЫ
-- ============================================
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL COMMENT 'ID пользователя (NULL для гостей)',
    order_number VARCHAR(50) UNIQUE NOT NULL COMMENT 'Номер заказа (например, AURUM-2025-0001)',
    delivery_type ENUM('delivery', 'pickup') NOT NULL COMMENT 'Тип доставки',
    full_name VARCHAR(255) NOT NULL COMMENT 'ФИО',
    phone VARCHAR(20) NOT NULL COMMENT 'Телефон',
    address TEXT COMMENT 'Адрес (обязателен для delivery)',
    comment TEXT COMMENT 'Комментарий к заказу',
    total_amount DECIMAL(10, 2) NOT NULL COMMENT 'Общая сумма',
    payment_method ENUM('card', 'cash') DEFAULT 'cash' COMMENT 'Способ оплаты',
    payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
    order_status ENUM('new', 'processing', 'confirmed', 'shipped', 'delivered', 'cancelled') DEFAULT 'new',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_order_number (order_number),
    INDEX idx_status (order_status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 5. ТОВАРЫ В ЗАКАЗЕ
-- ============================================
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NULL COMMENT 'ID базового товара (если обычная сборка)',
    item_type ENUM('product', 'config', 'pc') NOT NULL DEFAULT 'product' COMMENT 'Тип: обычный, конфигурированный или категория-PC',
    item_name VARCHAR(255) NOT NULL COMMENT 'Название на момент заказа',
    item_specs TEXT COMMENT 'Краткие характеристики (CPU | GPU и т.д.)',
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10, 2) NOT NULL,
    total_price DECIMAL(10, 2) NOT NULL,
    config_data JSON NULL COMMENT 'Полная конфигурация для кастомных сборок (cpu, gpu, ssd, warranty)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    INDEX idx_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 6. ОТЗЫВЫ (оставил — есть раздел в приложении)
-- ============================================
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL COMMENT 'ID пользователя',
    order_id INT NULL COMMENT 'ID заказа, по которому оставлен отзыв',
    product_id INT NULL COMMENT 'ID товара',
    rating TINYINT UNSIGNED NOT NULL CHECK (rating BETWEEN 1 AND 5),
    title VARCHAR(255),
    comment TEXT,
    is_published BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    INDEX idx_published (is_published),
    INDEX idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- БАЗОВЫЕ ДАННЫЕ: КАТЕГОРИИ
-- ============================================
INSERT INTO categories (id, name, image, display_order) VALUES
('4k', '4K Gaming', 'photo/4K.png', 1),
('2k', '2K Gaming', 'photo/2K.png', 2),
('fullhd', 'Full HD Gaming', 'photo/FullHD.png', 3)
ON DUPLICATE KEY UPDATE 
    name = VALUES(name), 
    image = VALUES(image), 
    display_order = VALUES(display_order);