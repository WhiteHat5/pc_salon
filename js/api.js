/**
 * API модуль для работы с backend
 */

const API_BASE_URL = window.location.origin + '/pc_salon/api';

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
        const response = await fetch(url, config);
        const data = await response.json();
        
        if (!response.ok) {
            throw new Error(data.error || `HTTP error! status: ${response.status}`);
        }
        
        return data;
    } catch (error) {
        console.error('API request failed:', error);
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

