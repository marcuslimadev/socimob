<?php

/** @var \Laravel\Lumen\Routing\Router $router */

// Domain Routes
$router->group(['prefix' => 'api/domain', 'middleware' => ['resolve-tenant']], function () use ($router) {
    
    // Rotas públicas
    $router->get('/', 'App\Http\Controllers\DomainController@current');
    $router->post('/validate', 'App\Http\Controllers\DomainController@validate');
    $router->post('/suggest', 'App\Http\Controllers\DomainController@suggest');

    // Rotas autenticadas
    $router->group(['middleware' => ['simple-auth', 'validate-tenant-auth']], function () use ($router) {
        $router->put('/', 'App\Http\Controllers\DomainController@update');
        $router->get('/dns', 'App\Http\Controllers\DomainController@dnsInfo');
        $router->get('/dns-instructions', 'App\Http\Controllers\DomainController@dnsInstructions');
        $router->get('/alternatives', 'App\Http\Controllers\DomainController@alternatives');
    });
});
