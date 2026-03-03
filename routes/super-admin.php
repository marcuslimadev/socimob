<?php

/** @var \Laravel\Lumen\Routing\Router $router */

// Super Admin Routes
$router->group(['prefix' => 'api/super-admin', 'middleware' => ['simple-auth']], function () use ($router) {
    
    // Dashboard
    $router->get('/dashboard', 'App\Http\Controllers\SuperAdmin\DashboardController@index');
    $router->get('/dashboard/growth', 'App\Http\Controllers\SuperAdmin\DashboardController@growth');
    $router->get('/dashboard/revenue', 'App\Http\Controllers\SuperAdmin\DashboardController@revenue');
    $router->get('/dashboard/plans', 'App\Http\Controllers\SuperAdmin\DashboardController@plans');

    // Tenants (CRUD)
    $router->get('/tenants', 'App\Http\Controllers\SuperAdmin\TenantController@index');
    $router->post('/tenants', 'App\Http\Controllers\SuperAdmin\TenantController@store');
    $router->get('/tenants/{id}', 'App\Http\Controllers\SuperAdmin\TenantController@show');
    $router->put('/tenants/{id}', 'App\Http\Controllers\SuperAdmin\TenantController@update');
    $router->delete('/tenants/{id}', 'App\Http\Controllers\SuperAdmin\TenantController@destroy');

    // Users (CRUD)
    $router->get('/users', 'App\Http\Controllers\SuperAdmin\UserController@index');
    $router->post('/users', 'App\Http\Controllers\SuperAdmin\UserController@store');
    $router->get('/users/{id}', 'App\Http\Controllers\SuperAdmin\UserController@show');
    $router->put('/users/{id}', 'App\Http\Controllers\SuperAdmin\UserController@update');
    $router->delete('/users/{id}', 'App\Http\Controllers\SuperAdmin\UserController@destroy');
    $router->post('/users/{id}/reset-password', 'App\Http\Controllers\SuperAdmin\UserController@resetPassword');

    // Tenant Actions
    $router->post('/tenants/{id}/activate', 'App\Http\Controllers\SuperAdmin\TenantController@activate');
    $router->post('/tenants/{id}/deactivate', 'App\Http\Controllers\SuperAdmin\TenantController@deactivate');
    $router->post('/tenants/{id}/generate-api-token', 'App\Http\Controllers\SuperAdmin\TenantController@generateApiToken');
    $router->get('/tenants/{id}/stats', 'App\Http\Controllers\SuperAdmin\TenantController@stats');
    $router->get('/tenants/{id}/users', 'App\Http\Controllers\SuperAdmin\TenantController@users');
    $router->post('/tenants/{id}/suspend-subscription', 'App\Http\Controllers\SuperAdmin\TenantController@suspendSubscription');
    $router->post('/tenants/{id}/activate-subscription', 'App\Http\Controllers\SuperAdmin\TenantController@activateSubscription');
    $router->get('/tenant-associations', 'App\Http\Controllers\SuperAdmin\TenantController@associationsIndex');
    $router->put('/tenant-associations/{id}', 'App\Http\Controllers\SuperAdmin\TenantController@associationsUpdate');

    // Property Sharing Visualization (apenas para superadmin ver compartilhamentos)
    $router->get('/property-sharing', 'App\Http\Controllers\SuperAdmin\PropertySharingController@index');
    $router->get('/property-sharing/{propertyId}/details', 'App\Http\Controllers\SuperAdmin\PropertySharingController@getPropertySharing');

    // Plans
    $router->get('/settings/plans', 'App\Http\Controllers\SuperAdmin\SettingsController@getPlans');
    $router->put('/settings/plans/{planId}', 'App\Http\Controllers\SuperAdmin\SettingsController@updatePlan');

    // Integrations
    $router->get('/settings/integrations', 'App\Http\Controllers\SuperAdmin\SettingsController@getIntegrations');
    $router->put('/settings/integrations/{service}', 'App\Http\Controllers\SuperAdmin\SettingsController@updateIntegration');

    // Settings
    $router->get('/settings', 'App\Http\Controllers\SuperAdmin\SettingsController@index');
    $router->get('/settings/{key}', 'App\Http\Controllers\SuperAdmin\SettingsController@show');
    $router->put('/settings/{key}', 'App\Http\Controllers\SuperAdmin\SettingsController@update');

    // Analytics
    $router->get('/analytics/overview', 'App\Http\Controllers\SuperAdmin\AnalyticsController@overview');
});
