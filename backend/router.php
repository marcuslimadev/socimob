<?php
/**
 * Router script for PHP built-in server
 * Handles static files and directs API requests to index.php
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$publicPath = __DIR__ . '/public';

// Log de debug para diagnóstico 404
error_log("[ROUTER DEBUG] " . date('Y-m-d H:i:s') . " - " . $_SERVER['REQUEST_METHOD'] . " " . $uri);

// Se for a raiz e não tiver query string, serve index.html
if ($uri === '/' && empty($_SERVER['QUERY_STRING'])) {
    $indexFile = $publicPath . '/index.html';
    if (file_exists($indexFile)) {
        header('Content-Type: text/html; charset=UTF-8');
        readfile($indexFile);
        return true;
    }
}

// Se for um arquivo estático, serve diretamente
if ($uri !== '/' && file_exists($publicPath . $uri)) {
    // Detecta o tipo MIME
    $ext = pathinfo($uri, PATHINFO_EXTENSION);
    $mimeTypes = [
        'html' => 'text/html',
        'css' => 'text/css',
        'js' => 'application/javascript',
        'json' => 'application/json',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
    ];
    
    if (isset($mimeTypes[$ext])) {
        header('Content-Type: ' . $mimeTypes[$ext]);
    }
    
    readfile($publicPath . $uri);
    return true;
}

// Caso contrário, deixa o Lumen processar (API routes)
require_once $publicPath . '/index.php';
