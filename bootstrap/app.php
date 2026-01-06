<?php

require_once __DIR__ . '/../vendor/autoload.php';

(new Laravel\Lumen\Bootstrap\LoadEnvironmentVariables(
    dirname(__DIR__)
))->bootstrap();

date_default_timezone_set(env('APP_TIMEZONE', 'UTC'));

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
*/

$app = new Laravel\Lumen\Application(
    dirname(__DIR__)
);

$app->withFacades();
$app->withEloquent();

/*
|--------------------------------------------------------------------------
| Register Model Observers
|--------------------------------------------------------------------------
*/

// Observer para integração automática com Chaves na Mão
App\Models\Lead::observe(App\Observers\LeadObserver::class);

/*
|--------------------------------------------------------------------------
| Register Container Bindings
|--------------------------------------------------------------------------
*/

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

/*
|--------------------------------------------------------------------------
| Register Config Files
|--------------------------------------------------------------------------
*/

$app->configure('app');
$app->configure('database');
$app->configure('cache');
$app->configure('session');
$app->configure('queue');

/*
|--------------------------------------------------------------------------
| Register Middleware
|--------------------------------------------------------------------------
*/

$app->middleware([
    App\Http\Middleware\CorsMiddleware::class,
    App\Http\Middleware\ResolveTenant::class, // Resolver tenant em todas as requisições
]);

$app->routeMiddleware([
    'auth' => App\Http\Middleware\Authenticate::class,
    'simple-auth' => App\Http\Middleware\SimpleTokenAuth::class,
    'auth:api' => App\Http\Middleware\SimpleTokenAuth::class,
    'validate-tenant-auth' => App\Http\Middleware\ValidateTenantAuth::class,
    'resolve-tenant' => App\Http\Middleware\ResolveTenant::class,
]);

/*
|--------------------------------------------------------------------------
| Register Service Providers
|--------------------------------------------------------------------------
*/

// $app->register(App\Providers\AppServiceProvider::class);
// $app->register(App\Providers\AuthServiceProvider::class);
// $app->register(App\Providers\EventServiceProvider::class);

/*
|--------------------------------------------------------------------------
| Load The Application Routes
|--------------------------------------------------------------------------
*/

$app->router->group([
    'namespace' => 'App\Http\Controllers',
], function ($router) {
    require __DIR__ . '/../routes/web.php';
    require __DIR__ . '/../routes/admin.php';
    require __DIR__ . '/../routes/super-admin.php';
    require __DIR__ . '/../routes/client-portal.php';
    require __DIR__ . '/../routes/subscriptions.php';
    require __DIR__ . '/../routes/themes.php';
    require __DIR__ . '/../routes/domains.php';
    require __DIR__ . '/../routes/portal.php';
});

return $app;
