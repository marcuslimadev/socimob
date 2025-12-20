<?php

/*
|--------------------------------------------------------------------------
| Portal Routes (Público)
|--------------------------------------------------------------------------
|
| Rotas para o portal público de vendas
| Middleware: resolve-tenant (identifica tenant por domínio)
|
*/

$router->group(['prefix' => 'api/portal', 'middleware' => 'resolve-tenant'], function () use ($router) {
    // Auth do portal cliente
    $router->post('/auth/register', 'Portal\\ClientAuthController@register');
    $router->post('/auth/login', 'Portal\\ClientAuthController@login');
    $router->get('/auth/me', ['middleware' => ['auth:api', 'validate-tenant-auth'], 'uses' => 'Portal\\ClientAuthController@me']);
    
    // Configurações do tenant (público)
    $router->get('/config', 'Portal\PortalController@getConfig');
    
    // Listar imóveis disponíveis (público)
    $router->get('/imoveis', 'Portal\PortalController@getImoveis');
    
    // Detalhes de um imóvel (público)
    $router->get('/imoveis/{id}', 'Portal\PortalController@getImovel');
    
    // Registrar interesse/lead (público)
    $router->post('/interesse', 'Portal\PortalController@registrarInteresse');

    // Agendar visita (público)
    $router->post('/visitas', 'Portal\\VisitasController@agendar');

    // Perfil (autenticado)
    $router->get('/profile', ['middleware' => ['auth:api', 'validate-tenant-auth'], 'uses' => 'Portal\\ProfileController@show']);
    $router->put('/profile', ['middleware' => ['auth:api', 'validate-tenant-auth'], 'uses' => 'Portal\\ProfileController@update']);

    // Likes (autenticado)
    $router->get('/likes', ['middleware' => ['auth:api', 'validate-tenant-auth'], 'uses' => 'Portal\\LikesController@list']);
    $router->post('/likes/{propertyId}', ['middleware' => ['auth:api', 'validate-tenant-auth'], 'uses' => 'Portal\\LikesController@like']);

    // Chat (autenticado)
    $router->post('/chat/start', ['middleware' => ['auth:api', 'validate-tenant-auth'], 'uses' => 'Portal\\ChatController@start']);
    $router->get('/chat/{id}', ['middleware' => ['auth:api', 'validate-tenant-auth'], 'uses' => 'Portal\\ChatController@show']);
    $router->get('/chat/{id}/mensagens', ['middleware' => ['auth:api', 'validate-tenant-auth'], 'uses' => 'Portal\\ChatController@mensagens']);
    $router->post('/chat/{id}/mensagens', ['middleware' => ['auth:api', 'validate-tenant-auth'], 'uses' => 'Portal\\ChatController@send']);
});
