<?php

/** @var \Laravel\Lumen\Routing\Router $router */

// Super Admin Routes
$router->group(['prefix' => 'api/super-admin', 'middleware' => ['simple-auth']], function () use ($router) {
    
    // Dashboard
    $router->get('/dashboard', 'SuperAdmin\DashboardController@index');
    $router->get('/dashboard/growth', 'SuperAdmin\DashboardController@growth');
    $router->get('/dashboard/revenue', 'SuperAdmin\DashboardController@revenue');
    $router->get('/dashboard/plans', 'SuperAdmin\DashboardController@plans');

    // Tenants (CRUD)
    $router->get('/tenants', 'SuperAdmin\TenantController@index');
    $router->post('/tenants', 'SuperAdmin\TenantController@store');
    $router->get('/tenants/{id}', 'SuperAdmin\TenantController@show');
    $router->put('/tenants/{id}', 'SuperAdmin\TenantController@update');
    $router->delete('/tenants/{id}', 'SuperAdmin\TenantController@destroy');

    // Users (CRUD)
    $router->get('/users', 'SuperAdmin\UserController@index');
    $router->post('/users', 'SuperAdmin\UserController@store');
    $router->get('/users/{id}', 'SuperAdmin\UserController@show');
    $router->put('/users/{id}', 'SuperAdmin\UserController@update');
    $router->delete('/users/{id}', 'SuperAdmin\UserController@destroy');

    // Tenant Actions
    $router->post('/tenants/{id}/activate', 'SuperAdmin\TenantController@activate');
    $router->post('/tenants/{id}/deactivate', 'SuperAdmin\TenantController@deactivate');
    $router->post('/tenants/{id}/generate-api-token', 'SuperAdmin\TenantController@generateApiToken');
    $router->get('/tenants/{id}/stats', 'SuperAdmin\TenantController@stats');
    $router->get('/tenants/{id}/users', 'SuperAdmin\TenantController@users');
    $router->post('/tenants/{id}/suspend-subscription', 'SuperAdmin\TenantController@suspendSubscription');
    $router->post('/tenants/{id}/activate-subscription', 'SuperAdmin\TenantController@activateSubscription');

    // Plans
    $router->get('/settings/plans', 'SuperAdmin\SettingsController@getPlans');
    $router->put('/settings/plans/{planId}', 'SuperAdmin\SettingsController@updatePlan');

    // Integrations
    $router->get('/settings/integrations', 'SuperAdmin\SettingsController@getIntegrations');
    $router->put('/settings/integrations/{service}', 'SuperAdmin\SettingsController@updateIntegration');

    // Settings
    $router->get('/settings', 'SuperAdmin\SettingsController@index');
    $router->get('/settings/{key}', 'SuperAdmin\SettingsController@show');
    $router->put('/settings/{key}', 'SuperAdmin\SettingsController@update');
});
