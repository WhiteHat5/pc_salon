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
    const url = `${API_BASE_URL}/${endpoint}`;
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

        // Важно: твой config.php всегда возвращает JSON, даже при ошибке
        const data = await response.json();

        // Проверяем, есть ли ошибка в ответе (даже если HTTP статус 200)
        if (data.success === false && data.error) {
            throw new Error(data.error);
        }

        if (!response.ok) {
            // Сервер вернул ошибку (например, 400, 404, 500)
            throw new Error(data.error || data.message || `HTTP ${response.status}`);
        }

        // Успешный ответ — возвращаем data (в твоём бэкенде это обычно { data: ..., success: true })
        return data;
    } catch (error) {
        if (error.name === 'TypeError' && error.message.includes('Failed to fetch')) {
            throw new Error('Нет соединения с сервером. Проверьте интернет или URL API.');
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
        const result = await apiRequest(`products.php?category=${categoryId}`); // Используем ?category= как в бэкенде
        return result.products || result.data || [];
    },

    async getById(productId) {
        const result = await apiRequest(`products.php?id=${productId}`);
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
            const result = await apiRequest(`users.php?telegram_id=${telegramId}`);
            return result.user || result.data || null;
        } catch (error) {
            if (error.message.includes('404') || error.message.includes('Not Found')) {
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
        
        // Проверяем успешность операции
        if (!result.success && result.error) {
            throw new Error(result.error);
        }
        
        return result.order || result.data || result;
    },

    async getByUser(userId) {
        const result = await apiRequest(`orders.php?user_id=${userId}`);
        return result.orders || result.data || [];
    },

    async getById(orderId) {
        const result = await apiRequest(`orders.php?id=${orderId}`);
        return result.order || result.data || null;
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
    baseUrl: API_BASE_URL // Для отладки
};

console.log('API инициализирован. Базовый URL:', API_BASE_URL);