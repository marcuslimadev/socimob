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
    $router->post('/simulacao-lead', 'App\Http\Controllers\Portal\PortalController@registrarSimulacaoLead');

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

    // Lookup de CPF para pré-preenchimento do formulário (autenticado)
    $router->get('/lookup-cpf', ['middleware' => ['simple-auth', 'validate-tenant-auth'], 'uses' => 'App\Http\Controllers\Portal\PortalController@lookupCpf']);

    // Solicitar colocação de imóvel à venda (publico)
    $router->post('/imoveis/solicitar', 'App\Http\Controllers\Portal\PortalController@solicitarVenda');

    // Portal da Pessoa - Contratos / Cobranças / Fiscal
    $router->get('/meus-imoveis', ['middleware' => ['simple-auth', 'validate-tenant-auth'], 'uses' => 'App\Http\Controllers\Portal\\PessoaFinanceiroController@meusImoveis']);
    $router->get('/financeiro/cobrancas', ['middleware' => ['simple-auth', 'validate-tenant-auth'], 'uses' => 'App\Http\Controllers\Portal\\PessoaFinanceiroController@minhasCobrancas']);
    $router->get('/financeiro/notas-fiscais', ['middleware' => ['simple-auth', 'validate-tenant-auth'], 'uses' => 'App\Http\Controllers\Portal\\PessoaFinanceiroController@minhasNotasFiscais']);

    // Operação - Chamados do cliente
    $router->get('/chamados', ['middleware' => ['simple-auth', 'validate-tenant-auth'], 'uses' => 'App\Http\Controllers\Portal\\ChamadosController@index']);
    $router->post('/chamados', ['middleware' => ['simple-auth', 'validate-tenant-auth'], 'uses' => 'App\Http\Controllers\Portal\\ChamadosController@store']);
    $router->get('/chamados/{id}/mensagens', ['middleware' => ['simple-auth', 'validate-tenant-auth'], 'uses' => 'App\Http\Controllers\Portal\\ChamadosController@mensagens']);
    $router->post('/chamados/{id}/mensagens', ['middleware' => ['simple-auth', 'validate-tenant-auth'], 'uses' => 'App\Http\Controllers\Portal\\ChamadosController@adicionarMensagem']);

    // ── Portal do Proprietário ───────────────────────────────────────────────
    // Uses shared simple-auth + validate-tenant-auth; owner is identified by
    // their linked Pessoa record (locador_pessoa_id on ContratoLocacao).
    $router->post('/proprietario/auth/login', 'App\Http\Controllers\Portal\ProprietarioAuthController@login');

    $router->get('/proprietario/dashboard',      ['middleware' => ['simple-auth', 'validate-tenant-auth'], 'uses' => 'App\Http\Controllers\Portal\PortalProprietarioController@dashboard']);
    $router->get('/proprietario/contratos',      ['middleware' => ['simple-auth', 'validate-tenant-auth'], 'uses' => 'App\Http\Controllers\Portal\PortalProprietarioController@contratos']);
    $router->get('/proprietario/contratos/{id}', ['middleware' => ['simple-auth', 'validate-tenant-auth'], 'uses' => 'App\Http\Controllers\Portal\PortalProprietarioController@contrato']);
    $router->get('/proprietario/repasses',       ['middleware' => ['simple-auth', 'validate-tenant-auth'], 'uses' => 'App\Http\Controllers\Portal\PortalProprietarioController@repasses']);
    $router->get('/proprietario/cobrancas',      ['middleware' => ['simple-auth', 'validate-tenant-auth'], 'uses' => 'App\Http\Controllers\Portal\PortalProprietarioController@cobrancas']);
});
