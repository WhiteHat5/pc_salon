-- ============================================
-- База данных для мини-приложения PC Salon
-- ============================================

-- Создание базы данных
CREATE DATABASE IF NOT EXISTS pc_salon CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pc_salon;

-- ============================================
-- 1. ТАБЛИЦА ПОЛЬЗОВАТЕЛЕЙ
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    telegram_id BIGINT UNIQUE NOT NULL COMMENT 'Уникальный ID пользователя в Telegram',
    first_name VARCHAR(100) COMMENT 'Имя',
    last_name VARCHAR(100) COMMENT 'Фамилия',
    username VARCHAR(100) COMMENT 'Username в Telegram',
    phone VARCHAR(20) COMMENT 'Номер телефона',
    address TEXT COMMENT 'Адрес доставки',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата регистрации',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Дата последнего обновления',
    last_activity TIMESTAMP NULL COMMENT 'Последняя активность',
    INDEX idx_telegram_id (telegram_id),
    INDEX idx_phone (phone)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 2. ТАБЛИЦА КАТЕГОРИЙ ТОВАРОВ
-- ============================================
CREATE TABLE IF NOT EXISTS categories (
    id VARCHAR(50) PRIMARY KEY COMMENT 'ID категории (4k, 2k, fullhd)',
    name VARCHAR(100) NOT NULL COMMENT 'Название категории',
    image VARCHAR(255) COMMENT 'Путь к изображению категории',
    display_order INT DEFAULT 0 COMMENT 'Порядок отображения',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. ТАБЛИЦА ТОВАРОВ
-- ============================================
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id VARCHAR(50) NOT NULL COMMENT 'ID категории',
    name VARCHAR(255) NOT NULL COMMENT 'Название товара',
    price DECIMAL(10, 2) NOT NULL COMMENT 'Базовая цена',
    image VARCHAR(255) COMMENT 'Путь к изображению',
    cpu VARCHAR(100) COMMENT 'Процессор',
    gpu VARCHAR(100) COMMENT 'Видеокарта',
    description TEXT COMMENT 'Описание товара',
    is_active BOOLEAN DEFAULT TRUE COMMENT 'Активен ли товар',
    stock_quantity INT DEFAULT 0 COMMENT 'Количество на складе',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
    INDEX idx_category (category_id),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. ТАБЛИЦА КОНФИГУРАЦИЙ ТОВАРОВ
-- ============================================
CREATE TABLE IF NOT EXISTS product_configs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL COMMENT 'ID базового товара',
    base_price DECIMAL(10, 2) NOT NULL COMMENT 'Базовая цена',
    base_cpu VARCHAR(100) COMMENT 'Базовый процессор',
    base_gpu VARCHAR(100) COMMENT 'Базовая видеокарта',
    image VARCHAR(255) COMMENT 'Изображение',
    description TEXT COMMENT 'Описание конфигурации',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    INDEX idx_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 5. ТАБЛИЦА ОПЦИЙ КОНФИГУРАЦИЙ (CPU, GPU, SSD, Warranty)
-- ============================================
CREATE TABLE IF NOT EXISTS config_options (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_id INT NOT NULL COMMENT 'ID конфигурации',
    option_type ENUM('cpu', 'gpu', 'ssd', 'warranty') NOT NULL COMMENT 'Тип опции',
    name VARCHAR(255) NOT NULL COMMENT 'Название опции',
    price_modifier DECIMAL(10, 2) DEFAULT 0 COMMENT 'Изменение цены (+/-)',
    is_selected BOOLEAN DEFAULT FALSE COMMENT 'Выбрана ли по умолчанию',
    display_order INT DEFAULT 0 COMMENT 'Порядок отображения',
    FOREIGN KEY (config_id) REFERENCES product_configs(id) ON DELETE CASCADE,
    INDEX idx_config_type (config_id, option_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 6. ТАБЛИЦА ЗАКАЗОВ
-- ============================================
CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL COMMENT 'ID пользователя',
    order_number VARCHAR(50) UNIQUE NOT NULL COMMENT 'Номер заказа',
    delivery_type ENUM('delivery', 'pickup') NOT NULL COMMENT 'Тип доставки',
    full_name VARCHAR(255) NOT NULL COMMENT 'ФИО заказчика',
    phone VARCHAR(20) NOT NULL COMMENT 'Телефон',
    address TEXT COMMENT 'Адрес доставки (для доставки)',
    comment TEXT COMMENT 'Комментарий к заказу',
    total_amount DECIMAL(10, 2) NOT NULL COMMENT 'Общая сумма заказа',
    payment_method ENUM('card', 'cash') DEFAULT 'cash' COMMENT 'Способ оплаты',
    payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending' COMMENT 'Статус оплаты',
    order_status ENUM('new', 'processing', 'confirmed', 'shipped', 'delivered', 'cancelled') DEFAULT 'new' COMMENT 'Статус заказа',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата создания заказа',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_order_number (order_number),
    INDEX idx_status (order_status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 7. ТАБЛИЦА ТОВАРОВ В ЗАКАЗЕ
-- ============================================
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL COMMENT 'ID заказа',
    product_id INT NULL COMMENT 'ID товара (если обычный товар)',
    config_id INT NULL COMMENT 'ID конфигурации (если конфигурированный товар)',
    item_type ENUM('product', 'config', 'pc') NOT NULL COMMENT 'Тип товара',
    item_name VARCHAR(255) NOT NULL COMMENT 'Название товара',
    item_specs TEXT COMMENT 'Характеристики товара',
    quantity INT NOT NULL DEFAULT 1 COMMENT 'Количество',
    unit_price DECIMAL(10, 2) NOT NULL COMMENT 'Цена за единицу',
    total_price DECIMAL(10, 2) NOT NULL COMMENT 'Общая цена',
    config_data JSON COMMENT 'Данные конфигурации (JSON)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    FOREIGN KEY (config_id) REFERENCES product_configs(id) ON DELETE SET NULL,
    INDEX idx_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 8. ТАБЛИЦА ОТЗЫВОВ
-- ============================================
CREATE TABLE IF NOT EXISTS reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL COMMENT 'ID пользователя',
    product_id INT NULL COMMENT 'ID товара (если отзыв на товар)',
    order_id INT NULL COMMENT 'ID заказа (связь с заказом)',
    rating TINYINT UNSIGNED NOT NULL COMMENT 'Оценка от 1 до 5',
    title VARCHAR(255) COMMENT 'Заголовок отзыва',
    comment TEXT COMMENT 'Текст отзыва',
    is_published BOOLEAN DEFAULT FALSE COMMENT 'Опубликован ли отзыв',
    is_moderated BOOLEAN DEFAULT FALSE COMMENT 'Проверен ли модератором',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_product (product_id),
    INDEX idx_published (is_published)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 9. ТАБЛИЦА СТАТИСТИКИ ПОЛЬЗОВАТЕЛЕЙ
-- ============================================
CREATE TABLE IF NOT EXISTS user_statistics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL COMMENT 'ID пользователя',
    total_orders INT DEFAULT 0 COMMENT 'Всего заказов',
    total_spent DECIMAL(10, 2) DEFAULT 0 COMMENT 'Всего потрачено',
    favorite_category VARCHAR(50) COMMENT 'Любимая категория',
    last_order_date TIMESTAMP NULL COMMENT 'Дата последнего заказа',
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Дата регистрации',
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (favorite_category) REFERENCES categories(id) ON DELETE SET NULL,
    UNIQUE KEY unique_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 10. ТАБЛИЦА ОНЛАЙН ПОЛЬЗОВАТЕЛЕЙ
-- ============================================
CREATE TABLE IF NOT EXISTS online_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL COMMENT 'ID пользователя (может быть NULL для анонимных)',
    telegram_id BIGINT NULL COMMENT 'Telegram ID (для быстрого поиска)',
    session_id VARCHAR(255) NOT NULL COMMENT 'ID сессии',
    ip_address VARCHAR(45) COMMENT 'IP адрес',
    user_agent TEXT COMMENT 'User Agent браузера',
    current_page VARCHAR(255) COMMENT 'Текущая страница',
    last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Последняя активность',
    INDEX idx_user (user_id),
    INDEX idx_telegram (telegram_id),
    INDEX idx_session (session_id),
    INDEX idx_activity (last_activity)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 11. ТАБЛИЦА КОРЗИН ПОЛЬЗОВАТЕЛЕЙ (для сохранения между сессиями)
-- ============================================
CREATE TABLE IF NOT EXISTS user_carts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL COMMENT 'ID пользователя (NULL для гостей)',
    session_id VARCHAR(255) COMMENT 'ID сессии для гостей',
    product_id INT NULL COMMENT 'ID товара',
    config_id INT NULL COMMENT 'ID конфигурации',
    item_type ENUM('product', 'config', 'pc') NOT NULL COMMENT 'Тип товара',
    quantity INT NOT NULL DEFAULT 1 COMMENT 'Количество',
    config_data JSON COMMENT 'Данные конфигурации (JSON)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (config_id) REFERENCES product_configs(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_session (session_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 12. ТАБЛИЦА ИЗБРАННОГО
-- ============================================
CREATE TABLE IF NOT EXISTS user_favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL COMMENT 'ID пользователя',
    product_id INT NULL COMMENT 'ID товара',
    config_id INT NULL COMMENT 'ID конфигурации',
    item_type ENUM('product', 'config', 'pc') NOT NULL COMMENT 'Тип товара',
    config_data JSON COMMENT 'Данные конфигурации (JSON)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (config_id) REFERENCES product_configs(id) ON DELETE CASCADE,
    UNIQUE KEY unique_favorite (user_id, product_id, config_id, item_type),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 13. ТАБЛИЦА ИСТОРИИ ДЕЙСТВИЙ ПОЛЬЗОВАТЕЛЕЙ (для аналитики)
-- ============================================
CREATE TABLE IF NOT EXISTS user_actions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL COMMENT 'ID пользователя',
    action_type ENUM('view_product', 'add_to_cart', 'remove_from_cart', 'add_to_favorites', 'remove_from_favorites', 'view_category', 'search', 'checkout') NOT NULL COMMENT 'Тип действия',
    item_id INT NULL COMMENT 'ID товара/категории',
    item_type VARCHAR(50) COMMENT 'Тип элемента',
    metadata JSON COMMENT 'Дополнительные данные (JSON)',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_action (action_type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ВСТАВКА БАЗОВЫХ ДАННЫХ
-- ============================================

-- Вставка категорий
INSERT INTO categories (id, name, image, display_order) VALUES
('4k', '4K Gaming', '4K.png', 1),
('2k', '2K Gaming', '2K.png', 2),
('fullhd', 'Full HD Gaming', 'FullHD.png', 3)
ON DUPLICATE KEY UPDATE name=VALUES(name), image=VALUES(image);

-- ============================================
-- ПРЕДЛОЖЕНИЯ ПО ДОПОЛНИТЕЛЬНЫМ ТАБЛИЦАМ
-- ============================================

/*
ДОПОЛНИТЕЛЬНЫЕ ТАБЛИЦЫ, КОТОРЫЕ МОЖНО ДОБАВИТЬ:

1. ПРОМОКОДЫ/СКИДКИ:
   - promo_codes (id, code, discount_type, discount_value, valid_from, valid_to, usage_limit)

2. УВЕДОМЛЕНИЯ:
   - notifications (id, user_id, type, title, message, is_read, created_at)

3. СООБЩЕНИЯ/ЧАТ С ПОДДЕРЖКОЙ:
   - support_messages (id, user_id, order_id, message, is_from_user, created_at)

4. ЛОГИ ОШИБОК:
   - error_logs (id, user_id, error_type, error_message, stack_trace, created_at)

5. НАСТРОЙКИ СИСТЕМЫ:
   - settings (key, value, description)

6. АДМИНИСТРАТОРЫ:
   - admins (id, username, password_hash, role, created_at)
*/

