<?php

/** @var \Laravel\Lumen\Routing\Router $router */

// Theme Routes
$router->group(['prefix' => 'api/theme', 'middleware' => ['resolve-tenant']], function () use ($router) {
    
    // Rotas públicas
    $router->get('/', 'ThemeController@current');
    $router->get('/css', 'ThemeController@css');
    $router->get('/available', 'ThemeController@available');
    $router->get('/preview/{themeName}', 'ThemeController@preview');

    // Rotas autenticadas
    $router->group(['middleware' => ['auth:api', 'validate-tenant-auth']], function () use ($router) {
        $router->put('/', 'ThemeController@update');
        $router->post('/reset', 'ThemeController@reset');
    });
});
