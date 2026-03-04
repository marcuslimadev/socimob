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
    $router->post('/auth/register', 'App\Http\Controllers\Portal\\ClientAuthController@register');
    $router->post('/auth/login', 'App\Http\Controllers\Portal\\ClientAuthController@login');
    $router->get('/auth/me', ['middleware' => ['simple-auth', 'validate-tenant-auth'], 'uses' => 'App\Http\Controllers\Portal\\ClientAuthController@me']);
    
    // Configurações do tenant (público)
    $router->get('/config', 'App\Http\Controllers\Portal\PortalController@getConfig');
    
    // Listar imóveis disponíveis (público)
    $router->get('/imoveis', 'App\Http\Controllers\Portal\PortalController@getImoveis');
    
    // Detalhes de um imóvel (público)
    $router->get('/imoveis/{id}', 'App\Http\Controllers\Portal\PortalController@getImovel');
    
    // Registrar interesse/lead (público)
    $router->post('/interesse', 'App\Http\Controllers\Portal\PortalController@registrarInteresse');

    // Capturar lead via simulação de financiamento (público)
    $router->post('/simulacao-lead', 'Portal\PortalController@registrarSimulacaoLead');

    // Agendar visita (público)
    $router->post('/visitas', 'App\Http\Controllers\Portal\\VisitasController@agendar');

    // Perfil (autenticado)
    $router->get('/profile', ['middleware' => ['simple-auth', 'validate-tenant-auth'], 'uses' => 'App\Http\Controllers\Portal\\ProfileController@show']);
    $router->put('/profile', ['middleware' => ['simple-auth', 'validate-tenant-auth'], 'uses' => 'App\Http\Controllers\Portal\\ProfileController@update']);

    // Likes (autenticado)
    $router->get('/likes', ['middleware' => ['simple-auth', 'validate-tenant-auth'], 'uses' => 'App\Http\Controllers\Portal\\LikesController@list']);
    $router->post('/likes/{propertyId}', ['middleware' => ['simple-auth', 'validate-tenant-auth'], 'uses' => 'App\Http\Controllers\Portal\\LikesController@like']);

    // Chat (autenticado)
    $router->post('/chat/start', ['middleware' => ['simple-auth', 'validate-tenant-auth'], 'uses' => 'App\Http\Controllers\Portal\\ChatController@start']);
    $router->get('/chat/{id}', ['middleware' => ['simple-auth', 'validate-tenant-auth'], 'uses' => 'App\Http\Controllers\Portal\\ChatController@show']);
    $router->get('/chat/{id}/mensagens', ['middleware' => ['simple-auth', 'validate-tenant-auth'], 'uses' => 'App\Http\Controllers\Portal\\ChatController@mensagens']);
    $router->post('/chat/{id}/mensagens', ['middleware' => ['simple-auth', 'validate-tenant-auth'], 'uses' => 'App\Http\Controllers\Portal\\ChatController@send']);

    // Solicitar colocação de imóvel à venda (autenticado)
    $router->post('/imoveis/solicitar', ['middleware' => ['simple-auth', 'validate-tenant-auth'], 'uses' => 'App\Http\Controllers\Portal\PortalController@solicitarVenda']);

    // Portal da Pessoa - Contratos / Cobranças / Fiscal
    $router->get('/meus-imoveis', ['middleware' => ['simple-auth', 'validate-tenant-auth'], 'uses' => 'App\Http\Controllers\Portal\\PessoaFinanceiroController@meusImoveis']);
    $router->get('/financeiro/cobrancas', ['middleware' => ['simple-auth', 'validate-tenant-auth'], 'uses' => 'App\Http\Controllers\Portal\\PessoaFinanceiroController@minhasCobrancas']);
    $router->get('/financeiro/notas-fiscais', ['middleware' => ['simple-auth', 'validate-tenant-auth'], 'uses' => 'App\Http\Controllers\Portal\\PessoaFinanceiroController@minhasNotasFiscais']);

    // Operação - Chamados do cliente
    $router->get('/chamados', ['middleware' => ['simple-auth', 'validate-tenant-auth'], 'uses' => 'App\Http\Controllers\Portal\\ChamadosController@index']);
    $router->post('/chamados', ['middleware' => ['simple-auth', 'validate-tenant-auth'], 'uses' => 'App\Http\Controllers\Portal\\ChamadosController@store']);
    $router->get('/chamados/{id}/mensagens', ['middleware' => ['simple-auth', 'validate-tenant-auth'], 'uses' => 'App\Http\Controllers\Portal\\ChamadosController@mensagens']);
    $router->post('/chamados/{id}/mensagens', ['middleware' => ['simple-auth', 'validate-tenant-auth'], 'uses' => 'App\Http\Controllers\Portal\\ChamadosController@adicionarMensagem']);
});
