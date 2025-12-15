<?php
/**
 * Конфигурация подключения к базе данных и вспомогательные функции
 * Используется во всех API-файлах (users.php, products.php, orders.php и т.д.)
 */

declare(strict_types=1);

// Отключаем вывод ошибок PHP (чтобы не ломать JSON ответы)
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Включаем буферизацию вывода для перехвата любых неожиданных выводов
if (!ob_get_level()) {
    ob_start();
}

// === Настройки базы данных ===
define('DB_HOST', 'localhost');
define('DB_NAME', 'pc_salon');
define('DB_USER', 'root');
define('DB_PASS', '');               // Если у тебя есть пароль — укажи его здесь!
define('DB_CHARSET', 'utf8mb4');

// === Подключение к БД (singleton) ===
function getDBConnection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            // Очищаем буфер перед отправкой ошибки
            while (ob_get_level()) {
                ob_end_clean();
            }
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Не удалось подключиться к базе данных'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
    }

    return $pdo;
}

// === Отправка JSON-ответа ===
function sendJSON(array $data, int $statusCode = 200): void
{
    // Очищаем буфер вывода от любых неожиданных выводов (включая ошибки PHP)
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Убеждаемся, что заголовки еще не отправлены
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
    }
    
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// === Отправка ошибки ===
function sendError(string $message, int $statusCode = 400): void
{
    sendJSON(['success' => false, 'error' => $message], $statusCode);
}

// === Получение данных из тела POST-запроса ===
function getPostData(): ?array
{
    $input = file_get_contents('php://input');
    if (empty($input)) {
        return null;
    }
    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendError('Некорректный JSON в теле запроса', 400);
    }
    return $data;
}

// === CORS заголовки (важно для работы из Telegram WebApp через ngrok) ===
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Обработка preflight-запросов (OPTIONS)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}
