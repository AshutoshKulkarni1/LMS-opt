<?php
/**
 * Fluent 2 Test Platform - Database Configuration
 * 
 * Switch between local and production by changing DB_ENV.
 */

require_once __DIR__ . '/env.php';

define('DB_ENV', env('DB_ENV', 'local')); // 'local' or 'production'

// Set PHP timezone to match MySQL (Asia/Kolkata = UTC+5:30).
// Change to your local timezone if different.
date_default_timezone_set('Asia/Kolkata');

// Configure production vs local error handling and logging
$isProduction = (env('APP_ENV', DB_ENV) === 'production');
if ($isProduction) {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
} else {
    // In local development, respect APP_DEBUG or default to safe logging
    $debug = env('APP_DEBUG', 'false') === 'true';
    ini_set('display_errors', $debug ? '1' : '0');
    ini_set('display_startup_errors', $debug ? '1' : '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL);
}

// Database credentials — ALL values come from .env / platform environment variables
define('DB_HOST', env('DB_HOST', '127.0.0.1'));
define('DB_PORT', env('DB_PORT', '3306'));
define('DB_NAME', env('DB_NAME', 'test_platform'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));

define('PYTHON_API_URL', env('PYTHON_API_URL', 'http://127.0.0.1:5000'));

/*
 * BASE_URL & ASSETS_URL: URL prefixes for redirects, links, and static assets.
 * Respects .env / environment variables first.
 * For clean web roots (Docker / production virtual hosts / cli-server), defaults to '' and '/assets'.
 * Auto-detects legacy XAMPP subdirectory only if /test-platform is in REQUEST_URI.
 */
$isSubdirXampp = (php_sapi_name() !== 'cli-server' && php_sapi_name() !== 'cli' && isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/test-platform') === 0);
$defaultBaseUrl = $isSubdirXampp ? '/test-platform/src/php/public' : '';
$defaultAssetsUrl = $isSubdirXampp ? '/test-platform/assets' : '/assets';

define('BASE_URL', env('BASE_URL', $defaultBaseUrl));
define('ASSETS_URL', env('ASSETS_URL', $defaultAssetsUrl));

/**
 * Get PDO database connection.
 * All queries MUST use this with prepared statements.
 */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            // Server-side logging without credentials
            error_log('Database connection failed: ' . $e->getMessage());
            if (ini_get('display_errors') === '1') {
                throw $e;
            }
            http_response_code(500);
            die('<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Service Unavailable</title><style>body{font-family:system-ui,-apple-system,sans-serif;background:#0f172a;color:#f8fafc;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}.c{background:#1e293b;padding:32px;border-radius:12px;border:1px solid #334155;text-align:center;max-width:440px;box-shadow:0 10px 25px rgba(0,0,0,0.5);}h2{margin-top:0;font-size:20px;}p{color:#94a3b8;font-size:14px;line-height:1.5;}</style></head><body><div class="c"><h2>Service Temporarily Unavailable</h2><p>Unable to establish a database connection. Please try again later or contact the administrator.</p></div></body></html>');
        }
    }
    return $pdo;
}

/**
 * Global error/exception handlers → notification bell.
 * Registered once per request; they only run on uncaught failures and
 * never suppress the default PHP handling (return false = continue).
 * notifyAdmin() lives in helpers.php — the function_exists guard covers
 * include order differences across entry points (APIs, pages).
 */
if (!function_exists('__registerNotificationHandlers')) {
    function __registerNotificationHandlers(): bool { return true; }
    set_error_handler(function (int $errno, string $errstr, string $errfile = '', int $errline = 0): bool {
        if (!(error_reporting() & $errno)) return false; // @-suppressed or non-fatal noise
        $interesting = [E_ERROR, E_WARNING, E_USER_ERROR, E_USER_WARNING, E_RECOVERABLE_ERROR];
        if (!in_array($errno, $interesting, true)) return false;
        if (function_exists('notifyAdmin')) {
            $safeMsg = function_exists('sanitizeSensitiveString') ? sanitizeSensitiveString($errstr) : $errstr;
            notifyAdmin('system', 'PHP Error [' . $errno . ']', $safeMsg . ' — ' . basename($errfile) . ':' . $errline);
        }
        return false; // keep default handler behavior
    });
    set_exception_handler(function (Throwable $e): void {
        if (function_exists('notifyAdmin')) {
            $safeMsg = function_exists('sanitizeSensitiveString') ? sanitizeSensitiveString($e->getMessage()) : $e->getMessage();
            notifyAdmin('system', 'Uncaught Exception', $safeMsg . ' — ' . basename($e->getFile()) . ':' . $e->getLine());
        }
    });
}
