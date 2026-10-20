<?php
/**
 * PHP built-in development server router with Clean URL support.
 *
 * Usage: php -S localhost:8000 router.php
 *
 * Features:
 * 1. Clean URLs: routes /login, /signup, /verify-otp, /student/*, /admin/* to their respective PHP files.
 * 2. Canonical 301 Redirection: redirects legacy .php URLs to clean URLs for GET/HEAD requests.
 * 3. POST Safety: NEVER redirects POST requests; preserves form submissions and request bodies.
 * 4. Query String Preservation: retains query strings on clean redirects and dispatching.
 * 5. Static Assets: directly serves /assets/*, favicon.ico, and robots.txt with caching and gzip.
 * 6. Security: blocks path traversal, null bytes, and sensitive files (.env, config/, storage/).
 */

if (php_sapi_name() === 'cli-server') {
    header('Connection: close');
}
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$queryString = $_SERVER['QUERY_STRING'] ?? '';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

// ─── 1. Security Check: Path Traversal & Null Bytes ──────────
if (strpos($requestUri, '..') !== false || strpos($requestUri, "\0") !== false) {
    http_response_code(400);
    echo "400 Bad Request";
    return true;
}

// Strip /test-platform prefix if present (for XAMPP URL portability)
if (str_starts_with($requestUri, '/test-platform')) {
    $requestUri = substr($requestUri, strlen('/test-platform')) ?: '/';
}

// ─── 2. Block direct web access to sensitive internal files ─
if (preg_match('#^/(?:\.env|\.git|composer\.(?:json|lock)|package(?:-lock)?\.json|src/php/(?:config|includes|storage))#i', $requestUri)) {
    http_response_code(403);
    echo "403 Forbidden";
    return true;
}

// ─── 3. Map /assets/ to real filesystem path ────────────────
if (strpos($requestUri, '/assets/') === 0) {
    $filePath = __DIR__ . $requestUri;
    if (file_exists($filePath) && !is_dir($filePath)) {
        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        $mimeTypes = [
            'css'   => 'text/css',
            'js'    => 'application/javascript',
            'png'   => 'image/png',
            'jpg'   => 'image/jpeg',
            'jpeg'  => 'image/jpeg',
            'gif'   => 'image/gif',
            'svg'   => 'image/svg+xml',
            'webp'  => 'image/webp',
            'woff'  => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf'   => 'font/ttf',
            'ico'   => 'image/x-icon',
            'json'  => 'application/json',
        ];
        if (isset($mimeTypes[$ext])) {
            header('Content-Type: ' . $mimeTypes[$ext]);
        }
        if (in_array(strtolower($ext), ['css', 'js'], true)) {
            header('Cache-Control: no-cache, must-revalidate');
        } else {
            header('Cache-Control: public, max-age=31536000, immutable');
        }

        $isHead = ($method === 'HEAD');

        // Gzip compression for eligible text assets
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

        header('Content-Length: ' . filesize($filePath));
        if (!$isHead) {
            readfile($filePath);
        }
        return true;
    }
    http_response_code(404);
    echo "Asset not found: $requestUri";
    return true;
}

// ─── 4. Serve well-known root static files (favicon, robots.txt) ──
if (preg_match('#^/(favicon\.ico|robots\.txt)$#', $requestUri, $m)) {
    $filePath = __DIR__ . '/' . $m[1];
    if (file_exists($filePath)) {
        header('Content-Type: ' . ($m[1] === 'robots.txt' ? 'text/plain' : 'image/x-icon'));
        header('Cache-Control: public, max-age=86400');
        readfile($filePath);
        return true;
    }
}

// ─── 5. Helper to serve dynamic PHP scripts with Gzip ────────
if (!function_exists('servePhpScript')) {
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
}

// ─── 6. Map /api/ and /src/php/api/ to src/php/api/ ──────────
if (strpos($requestUri, '/api/') === 0 || strpos($requestUri, '/src/php/api/') === 0) {
    $endpoint = strpos($requestUri, '/src/php/api/') === 0
        ? substr($requestUri, strlen('/src/php/api/'))
        : substr($requestUri, strlen('/api/'));

    $apiDir = __DIR__ . '/src/php/api';
    $filePath = $apiDir . '/' . ltrim($endpoint, '/');
    if (!str_ends_with($filePath, '.php')) {
        $filePath .= '.php';
    }
    $realApi = realpath($filePath);
    $realApiDir = realpath($apiDir);
    if ($realApi && $realApiDir && str_starts_with($realApi, $realApiDir) && is_file($realApi)) {
        return servePhpScript($realApi);
    }
    http_response_code(404);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'API endpoint not found', 'uri' => $requestUri]);
    return true;
}

// Strip /src/php/public prefix (legacy XAMPP paths)
if (strpos($requestUri, '/src/php/public/') === 0 || $requestUri === '/src/php/public') {
    $requestUri = substr($requestUri, strlen('/src/php/public')) ?: '/';
}

$publicDir = __DIR__ . '/src/php/public';
$realPublicDir = realpath($publicDir);

// ─── 7. Legacy .php URLs: Redirect GET to Clean URL, preserve POST ─
if (str_ends_with($requestUri, '.php')) {
    $candidate = $publicDir . $requestUri;
    $realCandidate = realpath($candidate);

    if ($realCandidate && $realPublicDir && str_starts_with($realCandidate, $realPublicDir) && is_file($realCandidate)) {
        // GET or HEAD: 301 Permanent Redirect to canonical clean URL
        if ($method === 'GET' || $method === 'HEAD') {
            $cleanUri = substr($requestUri, 0, -4);
            if ($cleanUri === '/index') {
                $cleanUri = '/';
            }
            $target = $cleanUri . (!empty($queryString) ? '?' . $queryString : '');
            header('Location: ' . $target, true, 301);
            exit;
        }
        // POST or other methods: Serve directly without redirect to preserve data
        return servePhpScript($realCandidate);
    }
}

// ─── 8. Clean Dynamic Route: /admin/colleges/{id} ────────────
if (preg_match('#^/admin/colleges/(\d+)$#', $requestUri, $m)) {
    $filePath = $publicDir . '/admin/college_dashboard.php';
    if (file_exists($filePath)) {
        $_GET['id'] = (int)$m[1];
        $_REQUEST['id'] = $_GET['id'];
        return servePhpScript($filePath);
    }
}

// ─── 9. Clean URL Dispatching for public routes ──────────────
$trimmedUri = rtrim($requestUri, '/') ?: '/';

// Root route / or /index
if ($trimmedUri === '/' || $trimmedUri === '/index') {
    return servePhpScript($publicDir . '/index.php');
}

// Clean URL match: /student/dashboard -> /student/dashboard.php, /login -> /login.php
$cleanCandidate = $publicDir . $trimmedUri . '.php';
$realClean = realpath($cleanCandidate);
if ($realClean && $realPublicDir && str_starts_with($realClean, $realPublicDir) && is_file($realClean)) {
    return servePhpScript($realClean);
}

// Directory match: /student -> /student/dashboard or /admin -> /admin/dashboard
$directPath = $publicDir . $trimmedUri;
if (is_dir($directPath) && $directPath !== $publicDir) {
    $indexPath = $directPath . '/index.php';
    $dashPath = $directPath . '/dashboard.php';
    if (file_exists($indexPath)) {
        return servePhpScript($indexPath);
    } elseif (file_exists($dashPath)) {
        header('Location: ' . $trimmedUri . '/dashboard', true, 302);
        exit;
    }
    http_response_code(404);
    echo "404 Not Found";
    return true;
}

// Direct file access in public/ (e.g. static files within public/)
if (file_exists($directPath) && is_file($directPath)) {
    $realDirect = realpath($directPath);
    if ($realDirect && $realPublicDir && str_starts_with($realDirect, $realPublicDir)) {
        if (pathinfo($realDirect, PATHINFO_EXTENSION) === 'php') {
            return servePhpScript($realDirect);
        }
        if (php_sapi_name() === 'cli-server') {
            return false; // Let PHP built-in server handle non-PHP static files
        }
        readfile($realDirect);
        exit;
    }
}

// ─── 10. 404 Fallback ────────────────────────────────────────
http_response_code(404);
echo "404 Not Found: " . htmlspecialchars($requestUri, ENT_QUOTES, 'UTF-8');
return true;
