<?php
/**
 * Админ-панель для управления товарами
 * Простая защита паролем (в продакшене используйте более надежную аутентификацию)
 */
session_start();

// Простая проверка пароля (в продакшене используйте хеширование и БД)
$ADMIN_PASSWORD = 'admin123'; // ИЗМЕНИТЕ ПАРОЛЬ!

if (isset($_POST['password'])) {
    if ($_POST['password'] === $ADMIN_PASSWORD) {
        $_SESSION['admin_authenticated'] = true;
    } else {
        $error = 'Неверный пароль';
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: admin.php');
    exit;
}

if (!isset($_SESSION['admin_authenticated']) || !$_SESSION['admin_authenticated']) {
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Вход в админ-панель - AURUM</title>
        <script src="https://cdn.tailwindcss.com"></script>
    </head>
    <body class="bg-gradient-to-br from-slate-900 via-blue-950 to-slate-900 text-white min-h-screen flex items-center justify-center">
        <div class="bg-slate-800/70 rounded-xl p-8 border border-amber-800 max-w-md w-full">
            <h1 class="text-2xl font-bold text-amber-400 mb-6 text-center">Вход в админ-панель</h1>
            <?php if (isset($error)): ?>
                <div class="bg-red-600/20 border border-red-600 rounded-lg p-3 mb-4 text-red-400 text-sm">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-amber-300 text-sm font-medium mb-2">Пароль</label>
                    <input type="password" name="password" required class="w-full p-3 rounded-lg bg-slate-700 border border-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 text-white" autofocus>
                </div>
                <button type="submit" class="w-full bg-gradient-to-r from-amber-600 to-yellow-600 hover:from-amber-700 hover:to-yellow-700 text-slate-900 py-3 rounded-xl font-bold transition">
                    Войти
                </button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Админ-панель - AURUM PC Salon</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; }
        #edit-modal {
            backdrop-filter: blur(4px);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-900 via-blue-950 to-slate-900 text-white min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-6xl">
        <!-- Заголовок -->
        <div class="mb-8 flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-amber-400 mb-2">
                    <i class="fas fa-cog mr-2"></i>Админ-панель AURUM
                </h1>
                <p class="text-slate-400">Управление товарами и категориями</p>
            </div>
            <div class="flex gap-3">
                <a href="admin-orders.php" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg transition">
                    <i class="fas fa-shopping-cart mr-2"></i>Заказы
                </a>
                <a href="?logout=1" class="bg-red-600 hover:bg-red-700 text-white py-2 px-4 rounded-lg transition">
                    <i class="fas fa-sign-out-alt mr-2"></i>Выход
                </a>
            </div>
        </div>

        <!-- Форма добавления товара -->
        <div class="bg-slate-800/70 rounded-xl p-6 mb-6 border border-amber-800">
            <h2 class="text-xl font-bold text-amber-300 mb-4">
                <i class="fas fa-plus-circle mr-2"></i>Добавить новый товар
            </h2>
            <form id="add-product-form" class="space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-amber-300 text-sm font-medium mb-2">Категория <span class="text-red-400">*</span></label>
                        <select id="product-category" required class="w-full p-3 rounded-lg bg-slate-700 border border-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 text-white">
                            <option value="">Выберите категорию</option>
                            <option value="4k">4K Gaming</option>
                            <option value="2k">2K Gaming</option>
                            <option value="fullhd">Full HD Gaming</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-amber-300 text-sm font-medium mb-2">Название товара <span class="text-red-400">*</span></label>
                        <input type="text" id="product-name" required class="w-full p-3 rounded-lg bg-slate-700 border border-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 text-white" placeholder="AURUM 4K Pro">
                    </div>
                    <div>
                        <label class="block text-amber-300 text-sm font-medium mb-2">Цена (₽) <span class="text-red-400">*</span></label>
                        <input type="number" id="product-price" required min="0" step="0.01" class="w-full p-3 rounded-lg bg-slate-700 border border-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 text-white" placeholder="349990">
                    </div>
                    <div>
                        <label class="block text-amber-300 text-sm font-medium mb-2">Изображение (путь) <span class="text-red-400">*</span></label>
                        <input type="text" id="product-image" required class="w-full p-3 rounded-lg bg-slate-700 border border-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 text-white" placeholder="photo/pc4_1.jpg">
                    </div>
                    <div>
                        <label class="block text-amber-300 text-sm font-medium mb-2">Процессор</label>
                        <input type="text" id="product-cpu" class="w-full p-3 rounded-lg bg-slate-700 border border-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 text-white" placeholder="i9-13900K">
                    </div>
                    <div>
                        <label class="block text-amber-300 text-sm font-medium mb-2">Видеокарта</label>
                        <input type="text" id="product-gpu" class="w-full p-3 rounded-lg bg-slate-700 border border-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 text-white" placeholder="RTX4090">
                    </div>
                </div>
                <div>
                    <label class="block text-amber-300 text-sm font-medium mb-2">Описание</label>
                    <textarea id="product-description" rows="5" class="w-full p-3 rounded-lg bg-slate-700 border border-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 text-white resize-none" placeholder="Полное описание товара..."></textarea>
                </div>
                <div class="flex items-center gap-3">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" id="product-active" checked class="w-4 h-4 text-amber-600 rounded">
                        <span class="text-amber-300">Товар активен</span>
                    </label>
                </div>
                <button type="submit" class="w-full md:w-auto bg-gradient-to-r from-amber-600 to-yellow-600 hover:from-amber-700 hover:to-yellow-700 text-slate-900 py-3 px-8 rounded-xl font-bold transition transform hover:scale-105">
                    <i class="fas fa-save mr-2"></i>Добавить товар
                </button>
            </form>
        </div>

        <!-- Список товаров -->
        <div class="bg-slate-800/70 rounded-xl p-6 border border-amber-800">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold text-amber-300">
                    <i class="fas fa-list mr-2"></i>Список товаров
                </h2>
                <button id="refresh-products" class="bg-slate-700 hover:bg-slate-600 text-amber-300 py-2 px-4 rounded-lg transition">
                    <i class="fas fa-sync-alt mr-2"></i>Обновить
                </button>
            </div>
            <div id="products-list" class="space-y-3">
                <div class="text-center text-amber-300 py-8">Загрузка товаров...</div>
            </div>
        </div>

        <!-- Модальное окно редактирования -->
        <div id="edit-modal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center">
            <div class="bg-slate-800 rounded-xl p-6 border border-amber-800 max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-amber-300">
                        <i class="fas fa-edit mr-2"></i>Редактировать товар
                    </h2>
                    <button type="button" onclick="window.closeEditModal()" class="text-slate-400 hover:text-white">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
                <form id="edit-product-form" class="space-y-4">
                    <input type="hidden" id="edit-product-id">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-amber-300 text-sm font-medium mb-2">Категория <span class="text-red-400">*</span></label>
                            <select id="edit-product-category" required class="w-full p-3 rounded-lg bg-slate-700 border border-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 text-white">
                                <option value="">Выберите категорию</option>
                                <option value="4k">4K Gaming</option>
                                <option value="2k">2K Gaming</option>
                                <option value="fullhd">Full HD Gaming</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-amber-300 text-sm font-medium mb-2">Название товара <span class="text-red-400">*</span></label>
                            <input type="text" id="edit-product-name" required class="w-full p-3 rounded-lg bg-slate-700 border border-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 text-white">
                        </div>
                        <div>
                            <label class="block text-amber-300 text-sm font-medium mb-2">Цена (₽) <span class="text-red-400">*</span></label>
                            <input type="number" id="edit-product-price" required min="0" step="0.01" class="w-full p-3 rounded-lg bg-slate-700 border border-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 text-white">
                        </div>
                        <div>
                            <label class="block text-amber-300 text-sm font-medium mb-2">Изображение (путь) <span class="text-red-400">*</span></label>
                            <input type="text" id="edit-product-image" required class="w-full p-3 rounded-lg bg-slate-700 border border-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 text-white">
                        </div>
                        <div>
                            <label class="block text-amber-300 text-sm font-medium mb-2">Процессор</label>
                            <input type="text" id="edit-product-cpu" class="w-full p-3 rounded-lg bg-slate-700 border border-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 text-white">
                        </div>
                        <div>
                            <label class="block text-amber-300 text-sm font-medium mb-2">Видеокарта</label>
                            <input type="text" id="edit-product-gpu" class="w-full p-3 rounded-lg bg-slate-700 border border-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 text-white">
                        </div>
                    </div>
                    <div>
                        <label class="block text-amber-300 text-sm font-medium mb-2">Описание</label>
                        <textarea id="edit-product-description" rows="5" class="w-full p-3 rounded-lg bg-slate-700 border border-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 text-white resize-none"></textarea>
                    </div>
                    <div class="flex items-center gap-3">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" id="edit-product-active" class="w-4 h-4 text-amber-600 rounded">
                            <span class="text-amber-300">Товар активен</span>
                        </label>
                    </div>
                    <div class="flex gap-3">
                        <button type="submit" class="flex-1 bg-gradient-to-r from-amber-600 to-yellow-600 hover:from-amber-700 hover:to-yellow-700 text-slate-900 py-3 px-8 rounded-xl font-bold transition">
                            <i class="fas fa-save mr-2"></i>Сохранить изменения
                        </button>
                        <button type="button" onclick="window.closeEditModal()" class="bg-slate-700 hover:bg-slate-600 text-white py-3 px-8 rounded-xl font-bold transition">
                            Отмена
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Сообщения -->
        <div id="message" class="fixed top-4 right-4 z-50 hidden">
            <div class="bg-slate-800 rounded-lg p-4 border border-amber-700 shadow-xl">
                <p id="message-text"></p>
            </div>
        </div>
    </div>

    <script>
        // Определяем путь к API
        const API_BASE_URL = window.location.pathname.includes('/pc_salon/') 
            ? '/pc_salon/api' 
            : './api';

        // Глобальные переменные
        let currentProducts = [];

        // Показать сообщение
        function showMessage(text, isError = false) {
            const msgEl = document.getElementById('message');
            const textEl = document.getElementById('message-text');
            textEl.textContent = text;
            textEl.className = isError ? 'text-red-400' : 'text-green-400';
            msgEl.classList.remove('hidden');
            setTimeout(() => msgEl.classList.add('hidden'), 3000);
        }

        // Загрузка товаров
        async function loadProducts() {
            try {
                const response = await fetch(`${API_BASE_URL}/products.php?admin=1`);
                
                // Проверяем, что ответ действительно JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    console.error('Не JSON ответ:', text.substring(0, 200));
                    throw new Error('Сервер вернул не JSON ответ. Проверьте консоль браузера для деталей.');
                }
                
                const result = await response.json();
                
                if (!result.success) {
                    throw new Error(result.error || 'Ошибка загрузки товаров');
                }
                
                const products = result.products || result.data || [];
                renderProducts(products);
            } catch (error) {
                console.error('Ошибка загрузки:', error);
                showMessage('Ошибка загрузки товаров: ' + error.message, true);
                document.getElementById('products-list').innerHTML = '<div class="text-center text-red-400 py-8">Ошибка загрузки: ' + error.message + '</div>';
            }
        }

        // Отображение товаров
        function renderProducts(products) {
            currentProducts = products; // Сохраняем для редактирования
            const container = document.getElementById('products-list');
            if (products.length === 0) {
                container.innerHTML = '<div class="text-center text-amber-300 py-8">Товары не найдены</div>';
                return;
            }

            container.innerHTML = products.map(product => `
                <div class="bg-slate-700/50 rounded-lg p-4 border border-amber-800 flex items-center gap-4">
                    <img src="${product.image || 'photo/4K.png'}" alt="${product.name}" class="w-20 h-20 object-cover rounded-lg bg-slate-600" onerror="this.src='photo/4K.png'">
                    <div class="flex-1">
                        <h3 class="font-bold text-amber-300">${product.name}</h3>
                        <p class="text-sm text-slate-400">${product.cpu || ''} ${product.gpu || ''}</p>
                        <p class="text-amber-400 font-bold mt-1">${parseFloat(product.price).toLocaleString()} ₽</p>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <button type="button" onclick="window.toggleActive(${product.id}, ${product.is_active ? 0 : 1})" class="px-3 py-1.5 rounded-full text-xs font-medium transition ${product.is_active ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700'} cursor-pointer text-white border border-white/20 hover:border-white/50 min-w-[80px]">
                            ${product.is_active ? '✓ Активен' : '✗ Неактивен'}
                        </button>
                        <button type="button" onclick="window.editProduct(${product.id})" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-3 rounded-lg transition shadow hover:shadow-lg min-w-[40px] h-[40px] flex items-center justify-center" title="Редактировать товар">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button type="button" onclick="window.deleteProduct(${product.id})" class="bg-red-600 hover:bg-red-700 text-white py-2 px-3 rounded-lg transition shadow hover:shadow-lg min-w-[40px] h-[40px] flex items-center justify-center" title="Удалить товар">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `).join('');
        }

        // Добавление товара
        document.getElementById('add-product-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const productData = {
                category_id: document.getElementById('product-category').value,
                name: document.getElementById('product-name').value,
                price: parseFloat(document.getElementById('product-price').value),
                image: document.getElementById('product-image').value,
                cpu: document.getElementById('product-cpu').value || null,
                gpu: document.getElementById('product-gpu').value || null,
                description: document.getElementById('product-description').value || null,
                is_active: document.getElementById('product-active').checked ? 1 : 0
            };

            try {
                const response = await fetch(`${API_BASE_URL}/products.php`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(productData)
                });

                // Проверяем тип ответа
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    console.error('Не JSON ответ при добавлении:', text.substring(0, 500));
                    throw new Error('Сервер вернул не JSON ответ. Проверьте консоль для деталей.');
                }

                const result = await response.json();
                
                if (result.success) {
                    showMessage('Товар успешно добавлен!');
                    document.getElementById('add-product-form').reset();
                    loadProducts();
                } else {
                    showMessage('Ошибка: ' + (result.error || 'Неизвестная ошибка'), true);
                }
            } catch (error) {
                console.error('Ошибка добавления товара:', error);
                showMessage('Ошибка: ' + error.message, true);
            }
        });

        // Переключение активности товара (глобальная функция)
        window.toggleActive = async function(id, newStatus) {
            try {
                console.log('Переключение активности товара:', id, 'новый статус:', newStatus);
                // Используем POST с action=update для надежности
                const response = await fetch(`${API_BASE_URL}/products.php?id=${id}&action=update`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ is_active: newStatus })
                });
                
                console.log('Ответ сервера:', response.status, response.statusText);

                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    console.error('Не JSON ответ при переключении активности:', text.substring(0, 500));
                    throw new Error('Сервер вернул не JSON ответ: ' + text.substring(0, 100));
                }

                const result = await response.json();
                
                if (result.success) {
                    showMessage(newStatus ? 'Товар активирован!' : 'Товар деактивирован!');
                    loadProducts();
                } else {
                    showMessage('Ошибка: ' + (result.error || 'Неизвестная ошибка'), true);
                }
            } catch (error) {
                console.error('Ошибка переключения активности:', error);
                showMessage('Ошибка: ' + error.message, true);
            }
        };

        // Редактирование товара (глобальная функция)
        window.editProduct = async function(id) {
            const product = currentProducts.find(p => p.id == id);
            if (!product) {
                showMessage('Товар не найден', true);
                return;
            }

            // Заполняем форму данными товара
            document.getElementById('edit-product-id').value = product.id;
            document.getElementById('edit-product-category').value = product.category_id || '';
            document.getElementById('edit-product-name').value = product.name || '';
            document.getElementById('edit-product-price').value = product.price || '';
            document.getElementById('edit-product-image').value = product.image || '';
            document.getElementById('edit-product-cpu').value = product.cpu || '';
            document.getElementById('edit-product-gpu').value = product.gpu || '';
            document.getElementById('edit-product-description').value = product.description || '';
            document.getElementById('edit-product-active').checked = product.is_active == 1;

            // Показываем модальное окно
            document.getElementById('edit-modal').classList.remove('hidden');
            document.getElementById('edit-modal').classList.add('flex');
        };

        window.closeEditModal = function() {
            document.getElementById('edit-modal').classList.add('hidden');
            document.getElementById('edit-modal').classList.remove('flex');
            document.getElementById('edit-product-form').reset();
        };

        // Обработчик формы редактирования
        document.getElementById('edit-product-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const productId = document.getElementById('edit-product-id').value;
            const productData = {
                category_id: document.getElementById('edit-product-category').value,
                name: document.getElementById('edit-product-name').value,
                price: parseFloat(document.getElementById('edit-product-price').value),
                image: document.getElementById('edit-product-image').value,
                cpu: document.getElementById('edit-product-cpu').value || null,
                gpu: document.getElementById('edit-product-gpu').value || null,
                description: document.getElementById('edit-product-description').value || null,
                is_active: document.getElementById('edit-product-active').checked ? 1 : 0
            };

            try {
                console.log('Редактирование товара:', productId, productData);
                // Используем POST с action=update для надежности
                const response = await fetch(`${API_BASE_URL}/products.php?id=${productId}&action=update`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(productData)
                });
                
                console.log('Ответ сервера при редактировании:', response.status, response.statusText);

                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    console.error('Не JSON ответ при редактировании:', text.substring(0, 500));
                    throw new Error('Сервер вернул не JSON ответ: ' + text.substring(0, 100));
                }

                const result = await response.json();
                
                if (result.success) {
                    showMessage('Товар успешно обновлен!');
                    window.closeEditModal();
                    loadProducts();
                } else {
                    showMessage('Ошибка: ' + (result.error || 'Неизвестная ошибка'), true);
                }
            } catch (error) {
                console.error('Ошибка редактирования товара:', error);
                showMessage('Ошибка: ' + error.message, true);
            }
        });

        // Удаление товара (глобальная функция)
        window.deleteProduct = async function(id) {
            if (!confirm('Вы уверены, что хотите УДАЛИТЬ этот товар из базы данных? Это действие нельзя отменить!')) return;

            try {
                const response = await fetch(`${API_BASE_URL}/products.php?id=${id}`, {
                    method: 'DELETE'
                });

                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    console.error('Не JSON ответ при удалении:', text.substring(0, 500));
                    throw new Error('Сервер вернул не JSON ответ');
                }

                const result = await response.json();
                
                if (result.success) {
                    showMessage('Товар удален из базы данных!');
                    loadProducts();
                } else {
                    showMessage('Ошибка: ' + (result.error || 'Неизвестная ошибка'), true);
                }
            } catch (error) {
                console.error('Ошибка удаления товара:', error);
                showMessage('Ошибка: ' + error.message, true);
            }
        };

        // Обновление списка
        document.getElementById('refresh-products').addEventListener('click', loadProducts);

        // Убеждаемся, что функции доступны глобально
        console.log('Админ-панель инициализирована. Функции:', {
            toggleActive: typeof window.toggleActive,
            editProduct: typeof window.editProduct,
            deleteProduct: typeof window.deleteProduct,
            closeEditModal: typeof window.closeEditModal
        });

        // Загрузка при старте
        loadProducts();
    </script>
</body>
</html>
