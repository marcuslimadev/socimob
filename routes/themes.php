<?php

/** @var \Laravel\Lumen\Routing\Router $router */

// Theme Routes
$router->group(['prefix' => 'api/theme', 'middleware' => ['resolve-tenant']], function () use ($router) {
    
    // Rotas públicas
    $router->get('/', 'App\Http\Controllers\ThemeController@current');
    $router->get('/css', 'App\Http\Controllers\ThemeController@css');
    $router->get('/available', 'App\Http\Controllers\ThemeController@available');
    $router->get('/preview/{themeName}', 'App\Http\Controllers\ThemeController@preview');

    // Rotas autenticadas
    $router->group(['middleware' => ['simple-auth', 'validate-tenant-auth']], function () use ($router) {
        $router->put('/', 'App\Http\Controllers\ThemeController@update');
        $router->post('/reset', 'App\Http\Controllers\ThemeController@reset');
    });
});
