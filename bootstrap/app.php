<?php

require_once __DIR__ . '/../vendor/autoload.php';

(function () {
    if (!function_exists('now')) {
        function now($tz = null)
        {
            return \Carbon\Carbon::now($tz);
        }
    }
})();

namespace App\Http\Controllers {
    if (!function_exists(__NAMESPACE__ . '\\now')) {
        function now($tz = null)
        {
            return \Carbon\Carbon::now($tz);
        }
    }
}

namespace App\Http\Controllers\Admin {
    if (!function_exists(__NAMESPACE__ . '\\now')) {
        function now($tz = null)
        {
            return \Carbon\Carbon::now($tz);
        }
    }
}

namespace App\Http\Controllers\SuperAdmin {
    if (!function_exists(__NAMESPACE__ . '\\now')) {
        function now($tz = null)
        {
            return \Carbon\Carbon::now($tz);
        }
    }
}

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
| Register Service Providers
|--------------------------------------------------------------------------
*/

// $app->register(App\Providers\AppServiceProvider::class);
// $app->register(App\Providers\AuthServiceProvider::class);
// $app->register(App\Providers\EventServiceProvider::class);
$app->register(App\Providers\ObserverServiceProvider::class); // ✅ ACTIVE

// 🔥 CRITICAL: Boot providers NOW to register Observers before routes load
$app->boot();

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
$app->configure('mail');
$app->configure('twilio');

/*
|--------------------------------------------------------------------------
| Register Middleware
|--------------------------------------------------------------------------
*/

$app->middleware([
    App\Http\Middleware\CorsMiddleware::class,
]);

$app->routeMiddleware([
    'auth' => App\Http\Middleware\Authenticate::class,
    'simple-auth' => App\Http\Middleware\SimpleTokenAuth::class,
    'auth:api' => App\Http\Middleware\SimpleTokenAuth::class,
    'validate-tenant-auth' => App\Http\Middleware\ValidateTenantAuth::class,
    'resolve-tenant' => App\Http\Middleware\ResolveTenant::class,
    'throttle' => Illuminate\Routing\Middleware\ThrottleRequests::class, // ⚡ Rate limiting
]);

/*
|--------------------------------------------------------------------------
| Register Service Providers
|--------------------------------------------------------------------------
*/

$app->register(Illuminate\Mail\MailServiceProvider::class);
$app->register(Illuminate\Bus\BusServiceProvider::class); // ✅ Queue support
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
