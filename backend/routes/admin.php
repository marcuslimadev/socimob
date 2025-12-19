<?php

/** @var \Laravel\Lumen\Routing\Router $router */

// Admin Routes (Tenant Admin)
$router->group(['prefix' => 'api/admin', 'middleware' => ['auth:api', 'validate-tenant-auth']], function () use ($router) {
    
    // Settings
    $router->get('/settings', 'Admin\TenantSettingsController@index');
    $router->put('/settings/tenant', 'Admin\TenantSettingsController@updateTenant');
    $router->put('/settings/theme', 'Admin\TenantSettingsController@updateTheme');
    $router->put('/settings/domain', 'Admin\TenantSettingsController@updateDomain');
    $router->put('/settings/api-keys', 'Admin\TenantSettingsController@updateApiKeys');

    // Email Settings
    $router->get('/settings/email', 'Admin\TenantSettingsController@getEmailSettings');
    $router->put('/settings/email', 'Admin\TenantSettingsController@updateEmailSettings');

    // Notification Settings
    $router->get('/settings/notifications', 'Admin\TenantSettingsController@getNotificationSettings');
    $router->put('/settings/notifications', 'Admin\TenantSettingsController@updateNotificationSettings');
});
