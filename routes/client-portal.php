<?php

/** @var \Laravel\Lumen\Routing\Router $router */

// Client Portal Routes
$router->group(['prefix' => 'api', 'middleware' => ['resolve-tenant']], function () use ($router) {
    
    // Rotas públicas (sem autenticação)
    $router->post('/intentions', 'App\Http\Controllers\ClientIntentionController@store');

    // Rotas autenticadas
    $router->group(['middleware' => ['simple-auth', 'validate-tenant-auth']], function () use ($router) {
        
        // Intenções
        $router->get('/intentions', 'App\Http\Controllers\ClientIntentionController@index');
        $router->get('/intentions/{id}', 'App\Http\Controllers\ClientIntentionController@show');
        $router->put('/intentions/{id}', 'App\Http\Controllers\ClientIntentionController@update');
        $router->delete('/intentions/{id}', 'App\Http\Controllers\ClientIntentionController@destroy');
        $router->post('/intentions/{id}/pause', 'App\Http\Controllers\ClientIntentionController@pause');
        $router->post('/intentions/{id}/resume', 'App\Http\Controllers\ClientIntentionController@resume');
        $router->get('/intentions/{id}/matches', 'App\Http\Controllers\ClientIntentionController@matches');
        $router->get('/intentions/{id}/notifications', 'App\Http\Controllers\ClientIntentionController@notifications');

        // Notificações
        $router->get('/notifications', 'App\Http\Controllers\NotificationController@index');
        $router->get('/notifications/unread/count', 'App\Http\Controllers\NotificationController@unreadCount');
        $router->get('/notifications/summary', 'App\Http\Controllers\NotificationController@summary');
        $router->post('/notifications/mark-all-as-read', 'App\Http\Controllers\NotificationController@markAllAsRead');
        $router->get('/notifications/{id}', 'App\Http\Controllers\NotificationController@show');
        $router->post('/notifications/{id}/read', 'App\Http\Controllers\NotificationController@markAsRead');
        $router->post('/notifications/{id}/unread', 'App\Http\Controllers\NotificationController@markAsUnread');
        $router->delete('/notifications/{id}', 'App\Http\Controllers\NotificationController@destroy');
    });
});
