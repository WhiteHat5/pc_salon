<?php
/**
 * Админ-панель для управления заказами
 * Использует ту же систему аутентификации, что и admin.php
 */
session_start();

// Простая проверка пароля (должен совпадать с admin.php)
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
    header('Location: admin-orders.php');
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
    <title>Управление заказами - AURUM PC Salon</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { font-family: system-ui, -apple-system, sans-serif; }
        #order-modal {
            backdrop-filter: blur(4px);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-900 via-blue-950 to-slate-900 text-white min-h-screen">
    <div class="container mx-auto px-4 py-8 max-w-7xl">
        <!-- Заголовок -->
        <div class="mb-8 flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-amber-400 mb-2">
                    <i class="fas fa-shopping-cart mr-2"></i>Управление заказами
                </h1>
                <p class="text-slate-400">Просмотр и управление заказами клиентов</p>
            </div>
            <div class="flex gap-3">
                <a href="admin.php" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg transition">
                    <i class="fas fa-box mr-2"></i>Товары
                </a>
                <a href="?logout=1" class="bg-red-600 hover:bg-red-700 text-white py-2 px-4 rounded-lg transition">
                    <i class="fas fa-sign-out-alt mr-2"></i>Выход
                </a>
            </div>
        </div>

        <!-- Фильтры -->
        <div class="bg-slate-800/70 rounded-xl p-4 mb-6 border border-amber-800">
            <div class="flex flex-wrap gap-4 items-center">
                <label class="text-amber-300 text-sm font-medium">Фильтр по статусу:</label>
                <select id="status-filter" class="p-2 rounded-lg bg-slate-700 border border-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 text-white">
                    <option value="">Все заказы</option>
                    <option value="new">Новые</option>
                    <option value="processing">В обработке</option>
                    <option value="confirmed">Подтверждены</option>
                    <option value="shipped">Отправлены</option>
                    <option value="delivered">Доставлены</option>
                    <option value="cancelled">Отменены</option>
                </select>
                <button id="refresh-orders" class="bg-slate-700 hover:bg-slate-600 text-amber-300 py-2 px-4 rounded-lg transition">
                    <i class="fas fa-sync-alt mr-2"></i>Обновить
                </button>
            </div>
        </div>

        <!-- Список заказов -->
        <div class="bg-slate-800/70 rounded-xl p-6 border border-amber-800">
            <div id="orders-list" class="space-y-4">
                <div class="text-center text-amber-300 py-8">Загрузка заказов...</div>
            </div>
        </div>

        <!-- Модальное окно просмотра/редактирования заказа -->
        <div id="order-modal" class="fixed inset-0 bg-black/50 z-50 hidden flex items-center justify-center">
            <div class="bg-slate-800 rounded-xl p-6 border border-amber-800 max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold text-amber-300">
                        <i class="fas fa-eye mr-2"></i>Детали заказа
                    </h2>
                    <button type="button" onclick="window.closeOrderModal()" class="text-slate-400 hover:text-white">
                        <i class="fas fa-times text-2xl"></i>
                    </button>
                </div>
                <div id="order-details"></div>
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
        let currentOrders = [];
        let currentOrder = null;

        // Показать сообщение
        function showMessage(text, isError = false) {
            const msgEl = document.getElementById('message');
            const textEl = document.getElementById('message-text');
            textEl.textContent = text;
            textEl.className = isError ? 'text-red-400' : 'text-green-400';
            msgEl.classList.remove('hidden');
            setTimeout(() => msgEl.classList.add('hidden'), 3000);
        }

        // Загрузка заказов
        async function loadOrders(statusFilter = '') {
            try {
                let url = `${API_BASE_URL}/orders.php?admin=1`;
                if (statusFilter) {
                    url += `&status=${encodeURIComponent(statusFilter)}`;
                }
                
                const response = await fetch(url);
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    console.error('Не JSON ответ:', text.substring(0, 200));
                    throw new Error('Сервер вернул не JSON ответ');
                }
                
                const result = await response.json();
                
                if (!result.success) {
                    throw new Error(result.error || 'Ошибка загрузки заказов');
                }
                
                const orders = result.orders || result.data || [];
                currentOrders = orders;
                renderOrders(orders);
            } catch (error) {
                console.error('Ошибка загрузки:', error);
                showMessage('Ошибка загрузки заказов: ' + error.message, true);
                document.getElementById('orders-list').innerHTML = '<div class="text-center text-red-400 py-8">Ошибка загрузки: ' + error.message + '</div>';
            }
        }

        // Отображение заказов
        function renderOrders(orders) {
            const container = document.getElementById('orders-list');
            if (orders.length === 0) {
                container.innerHTML = '<div class="text-center text-amber-300 py-8">Заказы не найдены</div>';
                return;
            }

            const statusMap = {
                'new': { text: 'Новый', color: 'bg-blue-600', textColor: 'text-blue-100' },
                'processing': { text: 'В обработке', color: 'bg-yellow-600', textColor: 'text-yellow-100' },
                'confirmed': { text: 'Подтвержден', color: 'bg-green-600', textColor: 'text-green-100' },
                'shipped': { text: 'Отправлен', color: 'bg-purple-600', textColor: 'text-purple-100' },
                'delivered': { text: 'Доставлен', color: 'bg-emerald-600', textColor: 'text-emerald-100' },
                'cancelled': { text: 'Отменен', color: 'bg-red-600', textColor: 'text-red-100' }
            };

            const paymentStatusMap = {
                'pending': { text: 'Ожидает оплаты', color: 'text-yellow-400' },
                'paid': { text: 'Оплачен', color: 'text-green-400' },
                'failed': { text: 'Ошибка оплаты', color: 'text-red-400' }
            };

            container.innerHTML = orders.map(order => {
                const status = statusMap[order.order_status] || { text: order.order_status || 'Новый', color: 'bg-gray-600', textColor: 'text-gray-100' };
                const paymentStatus = paymentStatusMap[order.payment_status] || { text: order.payment_status || 'pending', color: 'text-gray-400' };
                
                const orderDate = order.created_at ? new Date(order.created_at).toLocaleDateString('ru-RU', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric',
                    hour: '2-digit',
                    minute: '2-digit'
                }) : '';
                
                const deliveryTypeText = order.delivery_type === 'delivery' ? 'Доставка' : 'Самовывоз';
                const itemsCount = order.items ? order.items.length : 0;

                return `
                    <div class="bg-slate-700/50 rounded-lg p-4 border border-amber-800">
                        <div class="flex flex-wrap justify-between items-start gap-4">
                            <div class="flex-1 min-w-[200px]">
                                <div class="flex items-center gap-3 mb-2">
                                    <span class="font-bold text-amber-300 text-lg">${order.order_number || 'Заказ #' + order.id}</span>
                                    <span class="px-2 py-1 rounded text-xs font-medium ${status.color} ${status.textColor}">${status.text}</span>
                                </div>
                                <div class="text-sm text-slate-300 space-y-1">
                                    <div><i class="fas fa-user mr-2"></i>${order.full_name || 'Не указано'}</div>
                                    <div><i class="fas fa-phone mr-2"></i>${order.phone || 'Не указано'}</div>
                                    <div><i class="fas fa-calendar mr-2"></i>${orderDate}</div>
                                    <div><i class="fas fa-truck mr-2"></i>${deliveryTypeText}</div>
                                    ${order.address ? `<div><i class="fas fa-map-marker-alt mr-2"></i>${order.address}</div>` : ''}
                                </div>
                            </div>
                            <div class="text-right min-w-[150px]">
                                <div class="text-2xl font-bold text-amber-400 mb-2">${parseFloat(order.total_amount || 0).toLocaleString('ru-RU')} ₽</div>
                                <div class="text-sm ${paymentStatus.color} mb-2">${paymentStatus.text}</div>
                                <div class="text-sm text-slate-400">Товаров: ${itemsCount}</div>
                            </div>
                            <div class="flex gap-2">
                                <button type="button" onclick="window.viewOrder(${order.id})" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-lg transition" title="Просмотреть">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button type="button" onclick="window.updateOrderStatus(${order.id}, 'processing')" class="bg-yellow-600 hover:bg-yellow-700 text-white py-2 px-3 rounded-lg transition text-sm" title="В обработку">
                                    <i class="fas fa-cog"></i>
                                </button>
                                <button type="button" onclick="window.updateOrderStatus(${order.id}, 'confirmed')" class="bg-green-600 hover:bg-green-700 text-white py-2 px-3 rounded-lg transition text-sm" title="Подтвердить">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button type="button" onclick="window.updateOrderStatus(${order.id}, 'cancelled')" class="bg-red-600 hover:bg-red-700 text-white py-2 px-3 rounded-lg transition text-sm" title="Отменить">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Просмотр заказа
        window.viewOrder = async function(id) {
            try {
                const response = await fetch(`${API_BASE_URL}/orders.php?id=${id}`);
                const result = await response.json();
                
                if (!result.success) {
                    throw new Error(result.error || 'Ошибка загрузки заказа');
                }
                
                const order = result.order || result.data;
                currentOrder = order;
                renderOrderDetails(order);
                
                document.getElementById('order-modal').classList.remove('hidden');
                document.getElementById('order-modal').classList.add('flex');
            } catch (error) {
                console.error('Ошибка загрузки заказа:', error);
                showMessage('Ошибка: ' + error.message, true);
            }
        };

        // Отображение деталей заказа
        function renderOrderDetails(order) {
            const container = document.getElementById('order-details');
            
            const statusMap = {
                'new': { text: 'Новый', color: 'bg-blue-600' },
                'processing': { text: 'В обработке', color: 'bg-yellow-600' },
                'confirmed': { text: 'Подтвержден', color: 'bg-green-600' },
                'shipped': { text: 'Отправлен', color: 'bg-purple-600' },
                'delivered': { text: 'Доставлен', color: 'bg-emerald-600' },
                'cancelled': { text: 'Отменен', color: 'bg-red-600' }
            };

            const status = statusMap[order.order_status] || { text: order.order_status || 'Новый', color: 'bg-gray-600' };
            const orderDate = order.created_at ? new Date(order.created_at).toLocaleDateString('ru-RU', {
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            }) : '';

            const itemsHtml = order.items && order.items.length > 0 ? order.items.map(item => {
                let specs = '';
                if (item.item_specs) {
                    specs = `<div class="text-sm text-slate-400 mt-1">${item.item_specs}</div>`;
                }
                if (item.config_data) {
                    try {
                        const config = typeof item.config_data === 'string' ? JSON.parse(item.config_data) : item.config_data;
                        const configParts = [];
                        if (config.cpu) configParts.push(`CPU: ${config.cpu}`);
                        if (config.gpu) configParts.push(`GPU: ${config.gpu}`);
                        if (config.ssd) configParts.push(`SSD: ${config.ssd}`);
                        if (config.warranty) configParts.push(`Гарантия: ${config.warranty}`);
                        if (configParts.length > 0) {
                            specs += `<div class="text-sm text-slate-400 mt-1">${configParts.join(' | ')}</div>`;
                        }
                    } catch (e) {
                        console.error('Error parsing config_data:', e);
                    }
                }
                return `
                    <div class="border-b border-slate-700 pb-3 mb-3">
                        <div class="flex justify-between items-start">
                            <div class="flex-1">
                                <div class="font-medium text-amber-300">${item.item_name || 'Без названия'}</div>
                                ${specs}
                                <div class="text-sm text-slate-400 mt-1">Количество: ${item.quantity || 1}</div>
                            </div>
                            <div class="text-right ml-4">
                                <div class="text-amber-400 font-bold">${parseFloat(item.total_price || 0).toLocaleString('ru-RU')} ₽</div>
                                <div class="text-sm text-slate-400">${parseFloat(item.unit_price || 0).toLocaleString('ru-RU')} ₽ за шт.</div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('') : '<div class="text-slate-400">Товары не найдены</div>';

            container.innerHTML = `
                <div class="space-y-4">
                    <!-- Основная информация -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-amber-300 text-sm font-medium mb-2">Номер заказа</label>
                            <div class="p-3 rounded-lg bg-slate-700 text-white">${order.order_number || 'Заказ #' + order.id}</div>
                        </div>
                        <div>
                            <label class="block text-amber-300 text-sm font-medium mb-2">Статус</label>
                            <div class="flex items-center gap-2">
                                <span class="px-3 py-2 rounded text-sm font-medium ${status.color} text-white">${status.text}</span>
                                <select id="order-status-select" class="flex-1 p-2 rounded-lg bg-slate-700 border border-amber-700 text-white">
                                    <option value="new" ${order.order_status === 'new' ? 'selected' : ''}>Новый</option>
                                    <option value="processing" ${order.order_status === 'processing' ? 'selected' : ''}>В обработке</option>
                                    <option value="confirmed" ${order.order_status === 'confirmed' ? 'selected' : ''}>Подтвержден</option>
                                    <option value="shipped" ${order.order_status === 'shipped' ? 'selected' : ''}>Отправлен</option>
                                    <option value="delivered" ${order.order_status === 'delivered' ? 'selected' : ''}>Доставлен</option>
                                    <option value="cancelled" ${order.order_status === 'cancelled' ? 'selected' : ''}>Отменен</option>
                                </select>
                                <button onclick="window.saveOrderStatus(${order.id})" class="bg-amber-600 hover:bg-amber-700 text-white py-2 px-4 rounded-lg transition">
                                    <i class="fas fa-save"></i>
                                </button>
                            </div>
                        </div>
                        <div>
                            <label class="block text-amber-300 text-sm font-medium mb-2">ФИО</label>
                            <div class="p-3 rounded-lg bg-slate-700 text-white">${order.full_name || 'Не указано'}</div>
                        </div>
                        <div>
                            <label class="block text-amber-300 text-sm font-medium mb-2">Телефон</label>
                            <div class="p-3 rounded-lg bg-slate-700 text-white">${order.phone || 'Не указано'}</div>
                        </div>
                        <div>
                            <label class="block text-amber-300 text-sm font-medium mb-2">Тип доставки</label>
                            <div class="p-3 rounded-lg bg-slate-700 text-white">${order.delivery_type === 'delivery' ? 'Доставка' : 'Самовывоз'}</div>
                        </div>
                        ${order.address ? `
                        <div>
                            <label class="block text-amber-300 text-sm font-medium mb-2">Адрес доставки</label>
                            <div class="p-3 rounded-lg bg-slate-700 text-white">${order.address}</div>
                        </div>
                        ` : ''}
                        <div>
                            <label class="block text-amber-300 text-sm font-medium mb-2">Способ оплаты</label>
                            <div class="p-3 rounded-lg bg-slate-700 text-white">${order.payment_method === 'card' ? 'Карта' : 'Наличные'}</div>
                        </div>
                        <div>
                            <label class="block text-amber-300 text-sm font-medium mb-2">Статус оплаты</label>
                            <div class="p-3 rounded-lg bg-slate-700 text-white">${order.payment_status === 'paid' ? 'Оплачен' : (order.payment_status === 'failed' ? 'Ошибка оплаты' : 'Ожидает оплаты')}</div>
                        </div>
                        <div>
                            <label class="block text-amber-300 text-sm font-medium mb-2">Дата создания</label>
                            <div class="p-3 rounded-lg bg-slate-700 text-white">${orderDate}</div>
                        </div>
                        <div>
                            <label class="block text-amber-300 text-sm font-medium mb-2">Общая сумма</label>
                            <div class="p-3 rounded-lg bg-slate-700 text-amber-400 font-bold text-xl">${parseFloat(order.total_amount || 0).toLocaleString('ru-RU')} ₽</div>
                        </div>
                    </div>
                    ${order.comment ? `
                    <div>
                        <label class="block text-amber-300 text-sm font-medium mb-2">Комментарий</label>
                        <div class="p-3 rounded-lg bg-slate-700 text-white">${order.comment}</div>
                    </div>
                    ` : ''}
                    <!-- Товары -->
                    <div>
                        <label class="block text-amber-300 text-sm font-medium mb-2">Товары в заказе</label>
                        <div class="p-4 rounded-lg bg-slate-700/50">
                            ${itemsHtml}
                        </div>
                    </div>
                </div>
            `;
        };

        // Закрыть модальное окно
        window.closeOrderModal = function() {
            document.getElementById('order-modal').classList.add('hidden');
            document.getElementById('order-modal').classList.remove('flex');
        };

        // Обновление статуса заказа
        window.updateOrderStatus = async function(id, status) {
            if (!confirm(`Изменить статус заказа на "${status === 'processing' ? 'В обработке' : status === 'confirmed' ? 'Подтвержден' : 'Отменен'}"?`)) {
                return;
            }

            try {
                const response = await fetch(`${API_BASE_URL}/orders.php?id=${id}&action=update`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ order_status: status })
                });

                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    console.error('Не JSON ответ:', text.substring(0, 500));
                    throw new Error('Сервер вернул не JSON ответ');
                }

                const result = await response.json();
                
                if (result.success) {
                    showMessage('Статус заказа обновлен!');
                    loadOrders(document.getElementById('status-filter').value);
                    if (currentOrder && currentOrder.id == id) {
                        currentOrder.order_status = status;
                        renderOrderDetails(currentOrder);
                    }
                } else {
                    showMessage('Ошибка: ' + (result.error || 'Неизвестная ошибка'), true);
                }
            } catch (error) {
                console.error('Ошибка обновления статуса:', error);
                showMessage('Ошибка: ' + error.message, true);
            }
        };

        // Сохранение статуса из модального окна
        window.saveOrderStatus = async function(id) {
            const status = document.getElementById('order-status-select').value;
            await window.updateOrderStatus(id, status);
        };

        // Фильтр по статусу
        document.getElementById('status-filter').addEventListener('change', (e) => {
            loadOrders(e.target.value);
        });

        // Обновление списка
        document.getElementById('refresh-orders').addEventListener('click', () => {
            loadOrders(document.getElementById('status-filter').value);
        });

        // Закрытие по клику на фон
        document.getElementById('order-modal').addEventListener('click', (e) => {
            if (e.target === document.getElementById('order-modal')) {
                window.closeOrderModal();
            }
        });

        // Загрузка при старте
        loadOrders();
    </script>
</body>
</html>


