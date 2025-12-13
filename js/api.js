/**
 * API модуль для работы с backend
 */

// Определяем базовый URL для API
let API_BASE_URL;

// Можно указать публичный URL API через window.API_URL или переменную окружения
if (window.API_URL) {
  // Явно указанный URL (например, https://your-api-domain.com/api)
  API_BASE_URL = window.API_URL.endsWith('/api') ? window.API_URL : window.API_URL + '/api';
} else if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
  // Локальная разработка
  API_BASE_URL = window.location.origin + '/pc_salon/api';
} else {
  // GitHub Pages или другой хостинг - нужен публичный URL API
  // По умолчанию пробуем относительный путь (не сработает на GitHub Pages без PHP)
  const path = window.location.pathname;
  const basePath = path.substring(0, path.lastIndexOf('/'));
  API_BASE_URL = basePath + '/api';
  
  // Для GitHub Pages нужно указать window.API_URL в index.html
  console.warn('API_BASE_URL использует относительный путь. Для GitHub Pages укажите window.API_URL с публичным URL API.');
}

// Утилита для выполнения запросов
async function apiRequest(endpoint, options = {}) {
    const url = `${API_BASE_URL}/${endpoint}`;
    const config = {
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
        // Проверяем, что мы не на file:// протоколе
        if (window.location.protocol === 'file:') {
            throw new Error('API недоступен на file:// протоколе');
        }
        
        const response = await fetch(url, config);
        
        // Проверяем, что ответ валидный JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            throw new Error('Invalid response format');
        }
        
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.error || `HTTP error! status: ${response.status}`);
        }
        
        return data;
    } catch (error) {
        // Не логируем ошибки в консоль, чтобы не засорять её
        // Просто пробрасываем ошибку дальше для обработки в вызывающем коде
        throw error;
    }
}

// API для категорий
const CategoriesAPI = {
    async getAll() {
        const result = await apiRequest('categories.php');
        return result.data || [];
    }
};

// API для товаров
const ProductsAPI = {
    async getAll() {
        const result = await apiRequest('products.php');
        return result.data || [];
    },
    
    async getByCategory(categoryId) {
        const result = await apiRequest(`products.php?category_id=${categoryId}`);
        return result.data || [];
    },
    
    async getById(productId) {
        const result = await apiRequest(`products.php?id=${productId}`);
        return result.data;
    }
};

// API для пользователей
const UsersAPI = {
    async getByTelegramId(telegramId) {
        try {
            const result = await apiRequest(`users.php?telegram_id=${telegramId}`);
            return result.data;
        } catch (error) {
            if (error.message.includes('404')) {
                return null; // Пользователь не найден
            }
            throw error;
        }
    },
    
    async createOrUpdate(userData) {
        const result = await apiRequest('users.php', {
            method: 'POST',
            body: userData
        });
        return result.data;
    },
    
    async updateActivity(telegramId) {
        // Обновляем активность при создании/обновлении пользователя
        const user = await this.getByTelegramId(telegramId);
        if (user) {
            await this.createOrUpdate({ telegram_id: telegramId });
        }
    }
};

// API для заказов
const OrdersAPI = {
    async create(orderData) {
        const result = await apiRequest('orders.php', {
            method: 'POST',
            body: orderData
        });
        return result.data;
    },
    
    async getByUserId(userId) {
        const result = await apiRequest(`orders.php?user_id=${userId}`);
        return result.data || [];
    },
    
    async getById(orderId) {
        const result = await apiRequest(`orders.php?id=${orderId}`);
        return result.data;
    }
};

// Экспорт API
window.API = {
    categories: CategoriesAPI,
    products: ProductsAPI,
    users: UsersAPI,
    orders: OrdersAPI
};

