<?php

/** @var \Laravel\Lumen\Routing\Router $router */

// Admin Routes (Tenant Admin)
// CRITICAL: resolve-tenant MUST come before simple-auth to enforce domain-based tenant isolation
$router->group(['prefix' => 'api/admin', 'middleware' => ['resolve-tenant', 'simple-auth']], function () use ($router) {
    
    // Settings
    $router->get('/settings', 'App\Http\Controllers\Admin\TenantSettingsController@index');
    $router->put('/settings/tenant', 'App\Http\Controllers\Admin\TenantSettingsController@updateTenant');
    $router->put('/settings/theme', 'App\Http\Controllers\Admin\TenantSettingsController@updateTheme');
    $router->put('/settings/domain', 'App\Http\Controllers\Admin\TenantSettingsController@updateDomain');
    $router->post('/settings/assets', 'App\Http\Controllers\Admin\TenantSettingsController@uploadAssets');
    
    // Tenant Config - Salvar configurações da empresa (nome, logo, etc)
    $router->post('/tenant/config', 'App\Http\Controllers\Admin\TenantSettingsController@updateConfig');

    // Email Settings
    $router->get('/settings/email', 'App\Http\Controllers\Admin\TenantSettingsController@getEmailSettings');
    $router->put('/settings/email', 'App\Http\Controllers\Admin\TenantSettingsController@updateEmailSettings');

    // Notification Settings
    $router->get('/settings/notifications', 'App\Http\Controllers\Admin\TenantSettingsController@getNotificationSettings');
    $router->put('/settings/notifications', 'App\Http\Controllers\Admin\TenantSettingsController@updateNotificationSettings');

    // Analytics
    $router->get('/analytics/overview', 'App\Http\Controllers\Admin\AnalyticsController@overview');

    // AI Prompt Settings
    $router->get('/settings/ai-prompt', 'App\Http\Controllers\Admin\TenantSettingsController@getAiPrompt');
    $router->post('/settings/ai-prompt', 'App\Http\Controllers\Admin\TenantSettingsController@saveAiPrompt');
    $router->delete('/settings/ai-prompt', 'App\Http\Controllers\Admin\TenantSettingsController@deleteAiPrompt');

    // Atendimento Automático Settings
    $router->get('/settings/atendimento-automatico', 'App\Http\Controllers\Admin\TenantSettingsController@getAtendimentoAutomatico');
    $router->post('/settings/atendimento-automatico', 'App\Http\Controllers\Admin\TenantSettingsController@setAtendimentoAutomatico');

    // Financeiro - Comissões
    $router->get('/financeiro/notas-servico', 'App\Http\Controllers\Admin\\FinanceiroController@index');
    $router->post('/financeiro/notas-servico', 'App\Http\Controllers\Admin\FinanceiroController@emitirNfseComissao');
    $router->post('/financeiro/comissoes/nfse', 'App\Http\Controllers\Admin\FinanceiroController@emitirNfseComissao'); // legado
    $router->get('/financeiro/contratos', 'App\Http\Controllers\Admin\ContratosLocacaoController@index');
    $router->post('/financeiro/contratos', 'App\Http\Controllers\Admin\ContratosLocacaoController@store');
    $router->get('/financeiro/contratos/{id}', 'App\Http\Controllers\Admin\ContratosLocacaoController@show');
    $router->put('/financeiro/contratos/{id}', 'App\Http\Controllers\Admin\ContratosLocacaoController@update');
    $router->get('/financeiro/cobrancas-contrato', 'App\Http\Controllers\Admin\CobrancasContratoController@index');
    $router->post('/financeiro/contratos/{id}/gerar-cobranca', 'App\Http\Controllers\Admin\CobrancasContratoController@gerar');
    $router->patch('/financeiro/cobrancas-contrato/{id}/status', 'App\Http\Controllers\Admin\CobrancasContratoController@updateStatus');
    $router->get('/financeiro/lancamentos', 'App\Http\Controllers\Admin\LancamentosFinanceirosController@index');
    $router->post('/financeiro/lancamentos', 'App\Http\Controllers\Admin\LancamentosFinanceirosController@store');
    $router->post('/financeiro/lancamentos/{id}/baixas', 'App\Http\Controllers\Admin\LancamentosFinanceirosController@registrarBaixa');

    // Operação - Chamados
    $router->get('/operacao/chamados', 'App\Http\Controllers\Admin\ChamadosOperacionaisController@index');
    $router->patch('/operacao/chamados/{id}', 'App\Http\Controllers\Admin\ChamadosOperacionaisController@update');
    $router->post('/operacao/chamados/{id}/mensagens', 'App\Http\Controllers\Admin\ChamadosOperacionaisController@adicionarMensagem');
    $router->post('/operacao/chamados/{id}/anexos', 'App\Http\Controllers\Admin\ChamadosOperacionaisController@adicionarAnexo');

    $router->get('/comissoes', 'App\Http\Controllers\Admin\CommissionController@index');
    $router->post('/comissoes', 'App\Http\Controllers\Admin\CommissionController@store');
    $router->get('/comissoes/{id}', 'App\Http\Controllers\Admin\CommissionController@show');
    $router->get('/comissoes/{id}/status', 'App\Http\Controllers\Admin\CommissionController@verificarStatus');
    $router->get('/corretores', 'App\Http\Controllers\Admin\CommissionController@listarCorretores');

    // Leads - Automação IA
    $router->post('/leads/{id}/iniciar-atendimento', 'App\Http\Controllers\Admin\LeadsController@iniciarAtendimento');
    $router->post('/leads/iniciar-atendimento-lote', 'App\Http\Controllers\Admin\LeadsController@iniciarAtendimentoLote');

    // Importação de Imóveis
    $router->get('/imoveis', 'App\Http\Controllers\Admin\ImportacaoController@listar');
    $router->post('/imoveis/importar', 'App\Http\Controllers\Admin\ImportacaoController@importar');
    $router->get('/imoveis/importar/{jobId}', 'App\Http\Controllers\Admin\ImportacaoController@status');
    $router->post('/importacao/teste-api', 'App\Http\Controllers\Admin\ImportacaoController@testarAPI');

    // System Logs
    $router->get('/system-logs', 'App\Http\Controllers\Admin\SystemLogsController@index');
    $router->delete('/system-logs/clear', 'App\Http\Controllers\Admin\SystemLogsController@clear');

    // Visitas
    $router->get('/visitas', 'App\Http\Controllers\Admin\\VisitasController@index');
    $router->patch('/visitas/{id}', 'App\Http\Controllers\Admin\\VisitasController@update');

    // Usuários/Equipe
    $router->get('/users', function () use ($router) {
        $user = app('request')->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        $users = app('db')->table('users')
            ->where('tenant_id', $user->tenant_id)
            ->select('id', 'name', 'email', 'role', 'is_active as ativo', 'created_at')
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json(['data' => $users]);
    });
    
    $router->post('/users', function () use ($router) {
        $user = app('request')->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        $data = app('request')->all();
        
        // Validação básica
        if (!isset($data['name']) || !isset($data['email']) || !isset($data['password'])) {
            return response()->json(['message' => 'Campos obrigatórios faltando'], 400);
        }
        
        // Verifica se email já existe no tenant
        $exists = app('db')->table('users')
            ->where('tenant_id', $user->tenant_id)
            ->where('email', $data['email'])
            ->exists();
            
        if ($exists) {
            return response()->json(['message' => 'Email já cadastrado'], 400);
        }
        
        // Criar usuário
        $userId = app('db')->table('users')->insertGetId([
            'tenant_id' => $user->tenant_id,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => password_hash($data['password'], PASSWORD_BCRYPT),
            'role' => $data['role'] ?? 'corretor',
            'is_active' => $data['ativo'] ?? true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        return response()->json(['message' => 'Usuário criado com sucesso', 'id' => $userId], 201);
    });
    
    $router->put('/users/{id}', function ($id) use ($router) {
        $user = app('request')->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        $data = app('request')->all();
        
        $update = [
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'] ?? 'user',
            'is_active' => $data['ativo'] ?? true,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Atualizar senha apenas se fornecida
        if (!empty($data['password'])) {
            $update['password'] = password_hash($data['password'], PASSWORD_BCRYPT);
        }
        
        app('db')->table('users')
            ->where('id', $id)
            ->where('tenant_id', $user->tenant_id)
            ->update($update);
            
        return response()->json(['message' => 'Usuário atualizado com sucesso']);
    });
    
    $router->delete('/users/{id}', function ($id) use ($router) {
        $user = app('request')->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        app('db')->table('users')
            ->where('id', $id)
            ->where('tenant_id', $user->tenant_id)
            ->delete();
            
        return response()->json(['message' => 'Usuário excluído com sucesso']);
    });

    // Portal chat
    $router->post('/portal-chat/{id}/take', 'App\Http\Controllers\Admin\\PortalChatController@take');
    $router->post('/portal-chat/{id}/release', 'App\Http\Controllers\Admin\\PortalChatController@release');

    // Clientes
    $router->get('/clientes', 'App\Http\Controllers\Admin\\ClientesController@index');
    $router->post('/clientes/sync', 'App\Http\Controllers\Admin\\ClientesController@sync');

    // Chaves na Mão - Integração de Leads
    $router->get('/chaves-na-mao/status', 'App\Http\Controllers\ChavesNaMaoController@status');
    $router->post('/chaves-na-mao/test', 'App\Http\Controllers\ChavesNaMaoController@test');
    $router->post('/chaves-na-mao/retry', 'App\Http\Controllers\ChavesNaMaoController@retry');
    $router->post('/chaves-na-mao/resend', 'App\Http\Controllers\ChavesNaMaoController@resend');

    // Conversas a partir de leads
    $router->post('/leads/conversas/sync', 'App\Http\Controllers\Admin\\LeadConversaController@syncFromLeads');
    $router->post('/leads/{id}/start-ai', 'App\Http\Controllers\Admin\\LeadConversaController@startAi');

    // ── Ads Automation (Marketing / Anúncios) ────────────────────────────────
    $router->get('/ads/status',                         'App\Http\Controllers\Ads\AdsConnectionController@status');
    $router->post('/ads/{provider}/connect/start',      'App\Http\Controllers\Ads\AdsConnectionController@startConnect');
    $router->delete('/ads/{provider}/connect',          'App\Http\Controllers\Ads\AdsConnectionController@disconnect');
    $router->post('/ads/settings',                      'App\Http\Controllers\Ads\AdsConnectionController@saveSettings');
    $router->post('/ads/olx/connect/credentials',       'App\Http\Controllers\Ads\AdsConnectionController@connectCredentials');
    $router->post('/ads/{provider}/accounts',           'App\Http\Controllers\Ads\AdsConnectionController@saveAccount');
    $router->get('/ads/analytics',                      'App\Http\Controllers\Ads\AdsAnalyticsController@index');
    $router->get('/ads/logs',                           'App\Http\Controllers\Ads\AdsListingController@logs');

    // Property Ads (Propaganda de Imóveis)
    $router->get('/property-ads', function () use ($router) {
        $user = app('request')->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Retorna lista vazia por enquanto - funcionalidade em desenvolvimento
        return response()->json([
            'success' => true,
            'data' => [],
            'message' => 'Funcionalidade em desenvolvimento'
        ]);
    });

    $router->delete('/property-ads/{id}', function ($id) use ($router) {
        $user = app('request')->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return response()->json([
            'success' => true,
            'message' => 'Anúncio excluído com sucesso'
        ]);
    });
});
