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

        // Пытаемся распарсить JSON независимо от заголовка Content-Type,
        // так как некоторые сервера могут вернуть JSON с другим типом
        let data;
        try {
            data = await response.json();
        } catch (jsonError) {
            // Если парсинг JSON не удался, читаем ответ как текст для отладки
            const text = await response.text();
            console.error('Не удалось распарсить JSON. Ответ сервера:', text.substring(0, 200));
            throw new Error('Сервер вернул некорректный JSON или другой формат ответа.');
        }

        if (!response.ok) {
            // Сервер вернул ошибку (например, 400, 404, 500)
            throw new Error(data.error || `HTTP ${response.status}: ${response.statusText}`);
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
        return result.order || result.data;
    },

    async getByUser(userId) {
        const result = await apiRequest(`orders.php?user_id=${userId}`);
        return result.orders || result.data || [];
    },

    async getByTelegramId(telegramId) {
        try {
            const result = await apiRequest(`orders.php?telegram_id=${telegramId}`);
            console.log('OrdersAPI.getByTelegramId result:', result);
            // API возвращает { success: true, data: [...], orders: [...] }
            const orders = result.orders || result.data || [];
            console.log('OrdersAPI.getByTelegramId returning:', orders);
            
            // Убеждаемся, что возвращаем массив
            if (!Array.isArray(orders)) {
                console.warn('OrdersAPI.getByTelegramId: result is not an array:', orders);
                return [];
            }
            return orders;
        } catch (error) {
            console.error('OrdersAPI.getByTelegramId error:', error);
            // Если ошибка 404 или пользователь не найден, возвращаем пустой массив
            if (error.message && (error.message.includes('404') || error.message.includes('не найден'))) {
                return [];
            }
            // Для других ошибок пробрасываем дальше
            throw error;
        }
    },

    async getById(orderId) {
        const result = await apiRequest(`orders.php?id=${orderId}`);
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
        const result = await apiRequest(`reviews.php?user_id=${userId}`);
        return result.reviews || result.data || [];
    },

    async getByOrder(orderId) {
        const result = await apiRequest(`reviews.php?order_id=${orderId}`);
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