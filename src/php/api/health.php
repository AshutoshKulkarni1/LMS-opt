<?php
header('Content-Type: application/json');

$extensions = [
    'pdo' => extension_loaded('pdo'),
    'pdo_mysql' => extension_loaded('pdo_mysql'),
    'mbstring' => extension_loaded('mbstring'),
    'curl' => extension_loaded('curl'),
];

$dbStatus = 'untested';
$dbError = null;
$serverVersion = null;

try {
    require_once __DIR__ . '/../config/db.php';
    $pdo = getDB();
    $serverVersion = $pdo->getAttribute(PDO::ATTR_SERVER_VERSION);
    $dbStatus = 'connected';
} catch (Throwable $e) {
    $dbStatus = 'failed';
    $dbError = $e->getMessage();
}

echo json_encode([
    'status' => $dbStatus === 'connected' ? 'ok' : 'degraded',
    'php_version' => PHP_VERSION,
    'php_sapi' => php_sapi_name(),
    'extensions' => $extensions,
    'db_status' => $dbStatus,
    'db_server_version' => $serverVersion,
    'db_error' => $dbError,
    'db_host' => defined('DB_HOST') ? DB_HOST : null,
    'db_port' => defined('DB_PORT') ? DB_PORT : null,
    'db_name' => defined('DB_NAME') ? DB_NAME : null,
    'db_user' => defined('DB_USER') ? DB_USER : null,
], JSON_PRETTY_PRINT);
