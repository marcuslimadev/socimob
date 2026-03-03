<?php

/** @var \Laravel\Lumen\Routing\Router $router */

// Subscription Routes
$router->group(['prefix' => 'api/subscriptions', 'middleware' => ['resolve-tenant']], function () use ($router) {
    
    // Rotas públicas (webhook)
    $router->post('/webhook', 'App\Http\Controllers\SubscriptionController@webhook');

    // Rotas autenticadas
    $router->group(['middleware' => ['simple-auth', 'validate-tenant-auth']], function () use ($router) {
        $router->get('/current', 'App\Http\Controllers\SubscriptionController@current');
        $router->post('/', 'App\Http\Controllers\SubscriptionController@store');
        $router->post('/cancel', 'App\Http\Controllers\SubscriptionController@cancel');
        $router->put('/card', 'App\Http\Controllers\SubscriptionController@updateCard');
    });
});
