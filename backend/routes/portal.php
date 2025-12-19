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
    
    // Configurações do tenant (público)
    $router->get('/config', 'Portal\PortalController@getConfig');
    
    // Listar imóveis disponíveis (público)
    $router->get('/imoveis', 'Portal\PortalController@getImoveis');
    
    // Detalhes de um imóvel (público)
    $router->get('/imoveis/{id}', 'Portal\PortalController@getImovel');
    
    // Registrar interesse/lead (público)
    $router->post('/interesse', 'Portal\PortalController@registrarInteresse');
});
