<?php

require_once __DIR__.'/vendor/autoload.php';

// Configurar o Lumen
$app = require_once __DIR__.'/bootstrap/app.php';

echo "=== LISTANDO TODAS AS ROTAS DISPONÍVEIS ===\n\n";

// Pegar o router do Lumen
$router = $app->router;

try {
    // Usar reflexão para acessar as rotas registradas
    $reflection = new ReflectionClass($router);
    $routesProperty = $reflection->getProperty('routes');
    $routesProperty->setAccessible(true);
    $routes = $routesProperty->getValue($router);

    echo "Rotas encontradas:\n";
    
    foreach (['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'] as $method) {
        if (isset($routes[$method])) {
            echo "\n=== $method ===\n";
            foreach ($routes[$method] as $route => $handler) {
                echo "  $method $route\n";
            }
        }
    }

} catch (Exception $e) {
    echo "Erro ao listar rotas: " . $e->getMessage() . "\n\n";
    
    // Método alternativo - testar rotas específicas
    echo "Testando rotas específicas:\n\n";
    
    $testRoutes = [
        'GET /',
        'GET /api/health',
        'GET /api/admin/settings',
        'POST /api/admin/imoveis/importar',
        'POST /api/auth/login',
        'GET /app/',
        'GET /app/dashboard.html',
        'GET /app/imoveis.html'
    ];
    
    foreach ($testRoutes as $testRoute) {
        [$method, $path] = explode(' ', $testRoute, 2);
        
        // Simular uma request
        $request = \Illuminate\Http\Request::create($path, $method);
        
        try {
            $response = $app->dispatch($request);
            echo "✅ $testRoute - Status: " . $response->getStatusCode() . "\n";
        } catch (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e) {
            echo "❌ $testRoute - 404 Not Found\n";
        } catch (\Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException $e) {
            echo "⚠️  $testRoute - Method Not Allowed\n";
        } catch (Exception $e) {
            echo "🔥 $testRoute - Error: " . get_class($e) . "\n";
        }
    }
}

echo "\n=== VERIFICANDO ARQUIVOS ESTÁTICOS ===\n\n";

$staticFiles = [
    '/app/dashboard.html',
    '/app/imoveis.html',
    '/app/login.html',
    '/app/index.html'
];

foreach ($staticFiles as $file) {
    $fullPath = __DIR__ . '/public' . $file;
    if (file_exists($fullPath)) {
        echo "✅ $file - Existe\n";
    } else {
        echo "❌ $file - Não existe\n";
    }
}

echo "\n=== MIDDLEWARE REGISTRADOS ===\n\n";

try {
    $middlewareReflection = new ReflectionClass($app);
    $middlewareProperty = $middlewareReflection->getProperty('middleware');
    $middlewareProperty->setAccessible(true);
    $middleware = $middlewareProperty->getValue($app);
    
    echo "Global Middleware:\n";
    foreach ($middleware as $mid) {
        echo "  - $mid\n";
    }
    
    $routeMiddlewareProperty = $middlewareReflection->getProperty('routeMiddleware');
    $routeMiddlewareProperty->setAccessible(true);
    $routeMiddleware = $routeMiddlewareProperty->getValue($app);
    
    echo "\nRoute Middleware:\n";
    foreach ($routeMiddleware as $name => $class) {
        echo "  - $name => $class\n";
    }
    
} catch (Exception $e) {
    echo "Erro ao listar middleware: " . $e->getMessage() . "\n";
}