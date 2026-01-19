<?php

/** @var \Laravel\Lumen\Routing\Router $router */

// Subscription Routes
$router->group(['prefix' => 'api/subscriptions', 'middleware' => ['resolve-tenant']], function () use ($router) {
    
    // Rotas públicas (webhook)
    $router->post('/webhook', 'SubscriptionController@webhook');

    // Rotas autenticadas
    $router->group(['middleware' => ['simple-auth', 'validate-tenant-auth']], function () use ($router) {
        $router->get('/current', 'SubscriptionController@current');
        $router->post('/', 'SubscriptionController@store');
        $router->post('/cancel', 'SubscriptionController@cancel');
        $router->put('/card', 'SubscriptionController@updateCard');
    });
});
