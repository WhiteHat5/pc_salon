/**
 * API модуль для работы с backend
 */

// Базовый URL API
let API_BASE_URL;

if (window.API_URL) {
  // Явно указан в index.html (рекомендуемый способ для продакшена)
  API_BASE_URL = window.API_URL.trim().replace(/\/+$/, ''); // Убираем слеши в конце
} else if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
  // Локальная разработка — папка api в корне проекта
  API_BASE_URL = window.location.origin + '/api';
} else {
  // Продакшен — обязательно укажи window.API_URL в index.html!
  console.error('API_URL не определён! Укажите window.API_URL в index.html');
  API_BASE_URL = '/api'; // fallback, но на GitHub Pages не сработает без своего сервера
}

// Утилита для запросов
async function apiRequest(endpoint, options = {}) {
    // Убеждаемся, что endpoint не начинается со слеша
    const cleanEndpoint = endpoint.startsWith('/') ? endpoint.slice(1) : endpoint;
    const url = `${API_BASE_URL}/${cleanEndpoint}`;
    const config = {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            ...options.headers
        },
        ...options
    };

    if (config.body && typeof config.body === 'object') {
        config.body = JSON.stringify(config.body);
    }

    try {
        const response = await fetch(url, config);

        // Проверяем Content-Type перед парсингом JSON
        const contentType = response.headers.get('content-type');
        const isJson = contentType && contentType.includes('application/json');
        
        let data;
        if (isJson) {
            // Важно: твой config.php всегда возвращает JSON, даже при ошибке
            data = await response.json();
        } else {
            // Если не JSON, пытаемся прочитать как текст для диагностики
            const text = await response.text();
            throw new Error(`Сервер вернул не JSON ответ. Статус: ${response.status}. Ответ: ${text.substring(0, 200)}`);
        }

        if (!response.ok) {
            // Сервер вернул ошибку (например, 400, 404, 500)
            throw new Error(data.error || `HTTP ${response.status}`);
        }

        // Успешный ответ — возвращаем data (в твоём бэкенде это обычно { data: ..., success: true })
        return data;
    } catch (error) {
        // Если это уже наша ошибка, пробрасываем дальше
        if (error.message && (error.message.includes('Сервер вернул не JSON') || error.message.includes('HTTP'))) {
            throw error;
        }
        
        if (error.name === 'TypeError' && error.message.includes('Failed to fetch')) {
            throw new Error('Нет соединения с сервером. Проверьте интернет или URL API.');
        }
        
        // Обработка ошибок парсинга JSON
        if (error.name === 'SyntaxError' || error instanceof SyntaxError) {
            throw new Error('Сервер вернул некорректный JSON ответ');
        }
        
        throw error; // Пробрасываем дальше для обработки в UI
    }
}

// ========================
// API ОБЪЕКТЫ
// ========================

const CategoriesAPI = {
    async getAll() {
        const result = await apiRequest('categories.php');
        return result.categories || result.data || []; // Гибкость под разные форматы ответа
    }
};

const ProductsAPI = {
    async getAll() {
        const result = await apiRequest('products.php');
        return result.products || result.data || [];
    },

    async getByCategory(categoryId) {
        // Экранируем categoryId для безопасности
        const encodedCategoryId = encodeURIComponent(categoryId);
        const result = await apiRequest(`products.php?category=${encodedCategoryId}`); // Используем ?category= как в бэкенде
        return result.products || result.data || [];
    },

    async getById(productId) {
        // Экранируем productId для безопасности
        const encodedProductId = encodeURIComponent(productId);
        const result = await apiRequest(`products.php?id=${encodedProductId}`);
        return result.product || result.data || null;
    }
};

const UsersAPI = {
    async createOrUpdate(userData) {
        const result = await apiRequest('users.php', {
            method: 'POST',
            body: userData
        });
        return result.user || result.data;
    },

    async getByTelegramId(telegramId) {
        try {
            // Экранируем telegramId для безопасности
            const encodedTelegramId = encodeURIComponent(telegramId);
            const result = await apiRequest(`users.php?telegram_id=${encodedTelegramId}`);
            return result.user || result.data || null;
        } catch (error) {
            if (error.message.includes('404') || error.message.includes('Not Found') || error.message.includes('не найден')) {
                return null;
            }
            throw error;
        }
    }
};

const OrdersAPI = {
    async create(orderData) {
        const result = await apiRequest('orders.php', {
            method: 'POST',
            body: orderData
        });
        return result.order || result.data;
    },

    async getByUser(userId) {
        // Экранируем userId для безопасности
        const encodedUserId = encodeURIComponent(userId);
        const result = await apiRequest(`orders.php?user_id=${encodedUserId}`);
        return result.orders || result.data || [];
    },

    async getByTelegramId(telegramId) {
        // Экранируем telegramId для безопасности
        const encodedTelegramId = encodeURIComponent(telegramId);
        const result = await apiRequest(`orders.php?telegram_id=${encodedTelegramId}`);
        console.log('OrdersAPI.getByTelegramId result:', result);
        // API возвращает { success: true, data: [...], orders: [...] }
        const orders = result.orders || result.data || [];
        console.log('OrdersAPI.getByTelegramId returning:', orders);
        return Array.isArray(orders) ? orders : [];
    },

    async getById(orderId) {
        // Экранируем orderId для безопасности
        const encodedOrderId = encodeURIComponent(orderId);
        const result = await apiRequest(`orders.php?id=${encodedOrderId}`);
        return result.order || result.data || null;
    }
};

const ReviewsAPI = {
    async create(reviewData) {
        const result = await apiRequest('reviews.php', {
            method: 'POST',
            body: reviewData
        });
        return result.data || result.review;
    },

    async getByUser(userId) {
        // Экранируем userId для безопасности
        const encodedUserId = encodeURIComponent(userId);
        const result = await apiRequest(`reviews.php?user_id=${encodedUserId}`);
        return result.reviews || result.data || [];
    },

    async getByOrder(orderId) {
        // Экранируем orderId для безопасности
        const encodedOrderId = encodeURIComponent(orderId);
        const result = await apiRequest(`reviews.php?order_id=${encodedOrderId}`);
        return result.reviews || result.data || [];
    },

    async getPublished() {
        const result = await apiRequest('reviews.php?published=true');
        return result.reviews || result.data || [];
    }
};

// ========================
// ЭКСПОРТ
// ========================
window.API = {
    categories: CategoriesAPI,
    products: ProductsAPI,
    users: UsersAPI,
    orders: OrdersAPI,
    reviews: ReviewsAPI,
    baseUrl: API_BASE_URL // Для отладки
};

console.log('API инициализирован. Базовый URL:', API_BASE_URL);