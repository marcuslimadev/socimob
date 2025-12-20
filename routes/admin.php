<?php

/** @var \Laravel\Lumen\Routing\Router $router */

// Admin Routes (Tenant Admin)
$router->group(['prefix' => 'api/admin', 'middleware' => ['simple-auth']], function () use ($router) {
    
    // Settings
    $router->get('/settings', 'Admin\TenantSettingsController@index');
    $router->put('/settings/tenant', 'Admin\TenantSettingsController@updateTenant');
    $router->put('/settings/theme', 'Admin\TenantSettingsController@updateTheme');
    $router->put('/settings/domain', 'Admin\TenantSettingsController@updateDomain');
    $router->put('/settings/api-keys', 'Admin\TenantSettingsController@updateApiKeys');
    $router->post('/settings/assets', 'Admin\TenantSettingsController@uploadAssets');

    // Email Settings
    $router->get('/settings/email', 'Admin\TenantSettingsController@getEmailSettings');
    $router->put('/settings/email', 'Admin\TenantSettingsController@updateEmailSettings');

    // Notification Settings
    $router->get('/settings/notifications', 'Admin\TenantSettingsController@getNotificationSettings');
    $router->put('/settings/notifications', 'Admin\TenantSettingsController@updateNotificationSettings');

    // Importação de Imóveis
    $router->get('/imoveis', 'Admin\ImportacaoController@listar');
    $router->post('/imoveis/importar', 'Admin\ImportacaoController@importar');
    $router->get('/imoveis/importar/{jobId}', 'Admin\ImportacaoController@status');
    $router->post('/importacao/teste-api', 'Admin\ImportacaoController@testarAPI');

    // Visitas
    $router->get('/visitas', 'Admin\\VisitasController@index');
    $router->patch('/visitas/{id}', 'Admin\\VisitasController@update');

    // Portal chat
    $router->post('/portal-chat/{id}/take', 'Admin\\PortalChatController@take');
    $router->post('/portal-chat/{id}/release', 'Admin\\PortalChatController@release');
});
