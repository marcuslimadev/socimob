<?php

/** @var \Laravel\Lumen\Routing\Router $router */

// Domain Routes
$router->group(['prefix' => 'api/domain', 'middleware' => ['resolve-tenant']], function () use ($router) {
    
    // Rotas públicas
    $router->get('/', 'DomainController@current');
    $router->post('/validate', 'DomainController@validate');
    $router->post('/suggest', 'DomainController@suggest');

    // Rotas autenticadas
    $router->group(['middleware' => ['auth:api', 'validate-tenant-auth']], function () use ($router) {
        $router->put('/', 'DomainController@update');
        $router->get('/dns', 'DomainController@dnsInfo');
        $router->get('/dns-instructions', 'DomainController@dnsInstructions');
        $router->get('/alternatives', 'DomainController@alternatives');
    });
});
