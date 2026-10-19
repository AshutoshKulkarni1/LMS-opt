<?php
/**
 * PHP built-in development server router.
 *
 * Usage: php -S localhost:8000 router.php
 *
 * This router:
 * 1. Serves static assets (css/js/images/fonts) directly from the project root.
 * 2. Routes PHP requests to src/php/public/.
 * 3. Rewrites /assets/* to the real assets/ directory.
 */

// Serve static assets directly
header('Connection: close');
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Strip /test-platform prefix if present (for XAMPP URL portability)
if (str_starts_with($requestUri, '/test-platform')) {
    $requestUri = substr($requestUri, strlen('/test-platform'));
}

// ─── Map /assets/ to real filesystem path ───────────────────
if (strpos($requestUri, '/assets/') === 0) {
    $filePath = __DIR__ . $requestUri;
    if (file_exists($filePath) && !is_dir($filePath)) {
        // Set MIME type based on extension
        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        $mimeTypes = [
            'css'  => 'text/css',
            'js'   => 'application/javascript',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif'  => 'image/gif',
            'svg'  => 'image/svg+xml',
            'webp' => 'image/webp',
            'woff' => 'font/woff',
            'woff2'=> 'font/woff2',
            'ttf'  => 'font/ttf',
            'ico'  => 'image/x-icon',
            'json' => 'application/json',
        ];
        if (isset($mimeTypes[$ext])) {
            header('Content-Type: ' . $mimeTypes[$ext]);
        }
        header('Cache-Control: public, max-age=31536000, immutable');

        $isHead = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD';

        // Gzip compression for eligible static text responses (css, js, svg, json, xml, txt)
        $isEligible = in_array(strtolower($ext), ['css', 'js', 'svg', 'json', 'xml', 'txt', 'html'], true);
        if ($isEligible) {
            header('Vary: Accept-Encoding');

            $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
            $supportsGzip = false;
            if (preg_match('/(?:^|,)\s*gzip\s*(?:;\s*q\s*=\s*([0-9.]+))?/i', $acceptEncoding, $m)) {
                $supportsGzip = !isset($m[1]) || (float)$m[1] > 0;
            } elseif (preg_match('/(?:^|,)\s*\*\s*(?:;\s*q\s*=\s*([0-9.]+))?/i', $acceptEncoding, $m)) {
                $supportsGzip = !isset($m[1]) || (float)$m[1] > 0;
            }

            $canGzip = $supportsGzip && function_exists('gzencode') && !ini_get('zlib.output_compression');
            $content = file_get_contents($filePath);

            if ($canGzip) {
                $compressed = gzencode($content, 6);
                header('Content-Encoding: gzip');
                header('Content-Length: ' . strlen($compressed));
                if (!$isHead) {
                    echo $compressed;
                }
                return true;
            }

            header('Content-Length: ' . strlen($content));
            if (!$isHead) {
                echo $content;
            }
            return true;
        }

        // For non-compressible assets (images, fonts, etc.)
        header('Content-Length: ' . filesize($filePath));
        if (!$isHead) {
            readfile($filePath);
        }
        return true;
    }
    // If asset not found, return 404
    http_response_code(404);
    echo "Asset not found: $requestUri";
    return true;
}

// ─── Serve well-known root static files (favicon, robots.txt) ──
if (preg_match('#^/(favicon\.ico|robots\.txt)$#', $requestUri, $m)) {
    $filePath = __DIR__ . '/' . $m[1];
    if (file_exists($filePath)) {
        header('Content-Type: ' . ($m[1] === 'robots.txt' ? 'text/plain' : 'image/x-icon'));
        header('Cache-Control: public, max-age=86400');
        readfile($filePath);
        return true;
    }
}

// ─── Helper to serve dynamic PHP scripts with optional Gzip compression ─
function servePhpScript(string $file): bool {
    $acceptEncoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
    $supportsGzip = false;
    if (preg_match('/(?:^|,)\s*gzip\s*(?:;\s*q\s*=\s*([0-9.]+))?/i', $acceptEncoding, $m)) {
        $supportsGzip = !isset($m[1]) || (float)$m[1] > 0;
    } elseif (preg_match('/(?:^|,)\s*\*\s*(?:;\s*q\s*=\s*([0-9.]+))?/i', $acceptEncoding, $m)) {
        $supportsGzip = !isset($m[1]) || (float)$m[1] > 0;
    }
    if ($supportsGzip && function_exists('ob_gzhandler') && !ini_get('zlib.output_compression')) {
        ob_start('ob_gzhandler');
    }
    require $file;
    return true;
}

// ─── Map /api/ and /src/php/api/ to src/php/api/ ────────────
if (strpos($requestUri, '/api/') === 0 || strpos($requestUri, '/src/php/api/') === 0) {
    $apiDir = __DIR__ . '/src/php';
    $filePath = $apiDir . $requestUri;
    // Remove /src/php prefix if present (legacy XAMPP paths)
    if (strpos($requestUri, '/src/php/api/') === 0) {
        $filePath = $apiDir . substr($requestUri, strlen('/src/php'));
    }
    if (file_exists($filePath) && !is_dir($filePath) && pathinfo($filePath, PATHINFO_EXTENSION) === 'php') {
        return servePhpScript($filePath);
    }
    http_response_code(404);
    echo "API endpoint not found: $requestUri";
    return true;
}

// ─── Strip /src/php/public prefix (legacy XAMPP paths) ────
if (strpos($requestUri, '/src/php/public/') === 0 || $requestUri === '/src/php/public') {
    $requestUri = substr($requestUri, strlen('/src/php/public')) ?: '/';
}

// ─── Clean URL: /admin/colleges/{id} → college_dashboard.php?id={id} ──
// (SRS requirement: Dynamic College Dashboard at /admin/colleges/{college_id})
$publicDir = __DIR__ . '/src/php/public';
if (preg_match('#^/admin/colleges/(\d+)$#', $requestUri, $m)) {
    $filePath = $publicDir . '/admin/college_dashboard.php';
    if (file_exists($filePath)) {
        $_GET['id'] = (int)$m[1];
        $_REQUEST['id'] = $_GET['id'];
        return servePhpScript($filePath);
    }
}

// ─── Map / to src/php/public/ ──────────────────────────────
$filePath = $publicDir . $requestUri;

// If requesting a directory, try index.php
if (is_dir($filePath)) {
    $indexPath = rtrim($filePath, '/') . '/index.php';
    if (file_exists($indexPath)) {
        return servePhpScript($indexPath);
    }
    // Directory listing not allowed
    http_response_code(404);
    echo "404 Not Found: $requestUri";
    return true;
}

// Serve the PHP file or static file from public/
if (file_exists($filePath) && !is_dir($filePath)) {
    if (pathinfo($filePath, PATHINFO_EXTENSION) === 'php') {
        return servePhpScript($filePath);
    }
    // Serve non-PHP static files within public/
    return false; // Let PHP built-in server handle it
}

// 404 fallback
http_response_code(404);
echo "404 Not Found: $requestUri";
return true;
