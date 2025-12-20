<?php

// Script de servidor embutido para desenvolvimento
// Garante que o .env seja carregado corretamente

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
);

// Se o arquivo existe e não é o index.php, serve o arquivo estático
if ($uri !== '/' && file_exists(__DIR__.'/public'.$uri)) {
    return false;
}

// Caso contrário, redireciona para o index.php
require_once __DIR__.'/public/index.php';
