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

// ─── Cloud & Container Database Environment Variable Detection ───
// Supports Railway, Render, Fly.io, Heroku, Docker Compose, and custom .env

// 1. Connection URLs (DATABASE_URL, MYSQL_URL, MARIADB_URL)
$rawDbUrl = env('DATABASE_URL', env('MYSQL_URL', env('MARIADB_URL')));
$urlHost = null; $urlPort = null; $urlName = null; $urlUser = null; $urlPass = null;
if (!empty($rawDbUrl)) {
    $parsed = parse_url($rawDbUrl);
    if (!empty($parsed['host'])) $urlHost = $parsed['host'];
    if (!empty($parsed['port'])) $urlPort = (string)$parsed['port'];
    if (!empty($parsed['user'])) $urlUser = urldecode($parsed['user']);
    if (isset($parsed['pass']))  $urlPass = urldecode($parsed['pass']);
    if (!empty($parsed['path'])) $urlName = ltrim(urldecode($parsed['path']), '/');
}

// 2. Individual environment variables (checks cloud provider keys then LMS keys)
$dbHost = env('DB_HOST', $urlHost ?? env('MYSQLHOST', env('MYSQL_HOST', env('MARIADB_HOST', '127.0.0.1'))));
$dbPort = env('DB_PORT', $urlPort ?? env('MYSQLPORT', env('MYSQL_PORT', env('MARIADB_PORT', '3306'))));
$dbName = env('DB_NAME', $urlName ?? env('MYSQLDATABASE', env('MYSQL_DATABASE', env('MARIADB_DATABASE', 'test_platform'))));
$dbUser = env('DB_USER', $urlUser ?? env('MYSQLUSER', env('MYSQL_USER', env('MARIADB_USER', 'root'))));
$dbPass = env('DB_PASS', $urlPass ?? env('DB_PASSWORD', env('MYSQLPASSWORD', env('MYSQL_PASSWORD', env('MARIADB_PASSWORD', '')))));

define('DB_HOST', $dbHost);
define('DB_PORT', $dbPort);
define('DB_NAME', $dbName);
define('DB_USER', $dbUser);
define('DB_PASS', $dbPass);

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
 * Automatically initialize database schema if connected to an empty cloud database.
 * Does NOT alter or touch an existing database.
 */
function initSchemaIfEmpty(PDO $pdo): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    try {
        $check = $pdo->query("SHOW TABLES LIKE 'admins'")->fetch();
        if ($check) {
            return; // Schema already exists — preserve completely
        }

        $sqlFile = __DIR__ . '/../../../docker/mariadb/init.sql';
        if (!file_exists($sqlFile)) {
            error_log('Schema init: init.sql not found at ' . $sqlFile);
            return;
        }

        $sql = file_get_contents($sqlFile);
        // Strip CREATE DATABASE and USE statements — Railway DB already exists
        $sql = preg_replace('/CREATE\s+DATABASE[^;]+;/i', '', $sql);
        $sql = preg_replace('/USE\s+[^;]+;/i', '', $sql);

        // PDO::exec() silently stops after the first statement when given
        // multi-statement SQL. Split on semicolons and run each individually.
        $executed = 0;
        foreach (explode(';', $sql) as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || preg_match('/^[\s\-\#]*$/', $stmt)) {
                continue; // skip blank / comment-only chunks
            }
            try {
                $pdo->exec($stmt);
                $executed++;
            } catch (Throwable $stmtErr) {
                // Log but continue — ALTER TABLE migrations may already be applied
                error_log('Schema init stmt error: ' . $stmtErr->getMessage() . ' | SQL: ' . substr($stmt, 0, 120));
            }
        }
        error_log("Schema initialized from init.sql ({$executed} statements executed)");
    } catch (Throwable $t) {
        error_log('Schema initialization failed: ' . $t->getMessage());
    }
}

/**
 * Automatically load demo seed data if AUTO_SEED=true is set in environment
 * and database has 0 colleges.
 */
function initSeedIfRequested(PDO $pdo): void {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    $autoSeed = getenv('AUTO_SEED');
    if ($autoSeed !== 'true' && $autoSeed !== '1') {
        return;
    }

    try {
        $count = (int)$pdo->query("SELECT COUNT(*) FROM colleges")->fetchColumn();
        if ($count === 0) {
            $seederFile = __DIR__ . '/../../../sql/seed_test_data.php';
            if (file_exists($seederFile)) {
                require_once $seederFile;
                if (function_exists('runDatabaseSeeder')) {
                    runDatabaseSeeder($pdo, false);
                    error_log('Auto-seed: Successfully seeded empty database on startup.');
                }
            }
        }
    } catch (Throwable $t) {
        error_log('Auto-seed failed: ' . $t->getMessage());
    }
}

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
                PDO::ATTR_TIMEOUT            => 5,
            ]);
            initSchemaIfEmpty($pdo);
            initSeedIfRequested($pdo);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            if (ini_get('display_errors') === '1') {
                throw $e;
            }
            throw new PDOException('Unable to establish a database connection: ' . $e->getMessage(), (int)$e->getCode(), $e);
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
        if (!headers_sent()) {
            http_response_code(500);
        }
        if (ini_get('display_errors') === '1') {
            echo '<pre>' . htmlspecialchars((string)$e) . '</pre>';
        } else {
            echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Service Unavailable</title><style>body{font-family:system-ui,-apple-system,sans-serif;background:#0f172a;color:#f8fafc;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}.c{background:#1e293b;padding:32px;border-radius:12px;border:1px solid #334155;text-align:center;max-width:440px;box-shadow:0 10px 25px rgba(0,0,0,0.5);}h2{margin-top:0;font-size:20px;}p{color:#94a3b8;font-size:14px;line-height:1.5;}</style></head><body><div class="c"><h2>Service Temporarily Unavailable</h2><p>Unable to establish a database connection. Please try again later or contact the administrator.</p></div></body></html>';
        }
        exit;
    });
}
