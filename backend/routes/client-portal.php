<?php

/** @var \Laravel\Lumen\Routing\Router $router */

// Client Portal Routes
$router->group(['prefix' => 'api', 'middleware' => ['resolve-tenant']], function () use ($router) {
    
    // Rotas públicas (sem autenticação)
    $router->post('/intentions', 'ClientIntentionController@store');

    // Rotas autenticadas
    $router->group(['middleware' => ['auth:api', 'validate-tenant-auth']], function () use ($router) {
        
        // Intenções
        $router->get('/intentions', 'ClientIntentionController@index');
        $router->get('/intentions/{id}', 'ClientIntentionController@show');
        $router->put('/intentions/{id}', 'ClientIntentionController@update');
        $router->delete('/intentions/{id}', 'ClientIntentionController@destroy');
        $router->post('/intentions/{id}/pause', 'ClientIntentionController@pause');
        $router->post('/intentions/{id}/resume', 'ClientIntentionController@resume');
        $router->get('/intentions/{id}/matches', 'ClientIntentionController@matches');
        $router->get('/intentions/{id}/notifications', 'ClientIntentionController@notifications');

        // Notificações
        $router->get('/notifications', 'NotificationController@index');
        $router->get('/notifications/{id}', 'NotificationController@show');
        $router->post('/notifications/{id}/read', 'NotificationController@markAsRead');
        $router->post('/notifications/{id}/unread', 'NotificationController@markAsUnread');
        $router->post('/notifications/mark-all-as-read', 'NotificationController@markAllAsRead');
        $router->delete('/notifications/{id}', 'NotificationController@destroy');
        $router->get('/notifications/unread/count', 'NotificationController@unreadCount');
        $router->get('/notifications/summary', 'NotificationController@summary');
    });
});
