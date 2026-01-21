<?php
// Router for PHP built-in server

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$publicPath = __DIR__ . '/public';

// Serve static files directly
if ($uri !== '/' && is_file($publicPath . $uri)) {
    return false; // Let PHP's built-in server serve the file
}

// Serve index.html for root
if ($uri === '/' || $uri === '/index.html') {
    $indexFile = $publicPath . '/index.html';
    if (file_exists($indexFile)) {
        header('Content-Type: text/html; charset=UTF-8');
        readfile($indexFile);
        return true;
    }
}

// Serve index.html for directories
if (is_dir($publicPath . $uri)) {
    $indexFile = rtrim($publicPath . $uri, '/') . '/index.html';
    if (file_exists($indexFile)) {
        header('Content-Type: text/html; charset=UTF-8');
        readfile($indexFile);
        return true;
    }
}

// For all other requests (including API), pass to Lumen
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = $publicPath . '/index.php';
$_SERVER['DOCUMENT_ROOT'] = $publicPath;
chdir($publicPath);
require_once $publicPath . '/index.php';
