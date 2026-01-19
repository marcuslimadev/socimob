<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Users Table Structure</h1>";

try {
    require_once __DIR__ . '/../bootstrap/app.php';
    
    $columns = app('db')->select("SHOW COLUMNS FROM users");
    
    echo "<h2>Colunas da tabela users:</h2>";
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
    
} catch (Exception $e) {
    echo "<h2 style='color: red'>ERRO:</h2>";
    echo "<pre style='color: red'>";
    echo $e->getMessage();
    echo "</pre>";
}
