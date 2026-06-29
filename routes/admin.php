<?php

/** @var \Laravel\Lumen\Routing\Router $router */

// Admin Routes (Tenant Admin)
// CRITICAL: resolve-tenant MUST come before simple-auth to enforce domain-based tenant isolation
$router->group(['prefix' => 'api/admin', 'middleware' => ['resolve-tenant', 'simple-auth']], function () use ($router) {
    
    // Settings
    $router->get('/settings', 'App\Http\Controllers\Admin\TenantSettingsController@index');
    $router->put('/settings', 'App\Http\Controllers\Admin\TenantSettingsController@update');
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

    // Links importantes do CRM
    $router->get('/important-links', 'App\Http\Controllers\Admin\ImportantLinksController@index');
    $router->post('/important-links', 'App\Http\Controllers\Admin\ImportantLinksController@store');
    $router->put('/important-links/{id}', 'App\Http\Controllers\Admin\ImportantLinksController@update');
    $router->delete('/important-links/{id}', 'App\Http\Controllers\Admin\ImportantLinksController@destroy');

    // AI Prompt Settings
    $router->get('/settings/ai-prompt', 'App\Http\Controllers\Admin\TenantSettingsController@getAiPrompt');
    $router->post('/settings/ai-prompt', 'App\Http\Controllers\Admin\TenantSettingsController@saveAiPrompt');
    $router->delete('/settings/ai-prompt', 'App\Http\Controllers\Admin\TenantSettingsController@deleteAiPrompt');
    $router->get('/settings/ai-provider', 'App\Http\Controllers\Admin\TenantSettingsController@getAiProvider');
    $router->post('/settings/ai-provider', 'App\Http\Controllers\Admin\TenantSettingsController@setAiProvider');

    // Atendimento Automático Settings
    $router->get('/settings/atendimento-automatico', 'App\Http\Controllers\Admin\TenantSettingsController@getAtendimentoAutomatico');
    $router->post('/settings/atendimento-automatico', 'App\Http\Controllers\Admin\TenantSettingsController@setAtendimentoAutomatico');

    // Financeiro - Comissões
    $router->get('/financeiro/notas-servico', 'App\Http\Controllers\Admin\\FinanceiroController@index');
    $router->get('/financeiro/notas-servico/{registroTipo}/{id}', 'App\Http\Controllers\Admin\FinanceiroController@showNotaServico');
    $router->get('/financeiro/notas-servico/{registroTipo}/{id}/danfse', 'App\Http\Controllers\Admin\FinanceiroController@downloadDanfse');
    $router->post('/financeiro/notas-servico', 'App\Http\Controllers\Admin\FinanceiroController@emitirNfseComissao');
    $router->post('/financeiro/notas-servico/{id}/sincronizar', 'App\Http\Controllers\Admin\FinanceiroController@sincronizarDocumentoFiscal');
    $router->delete('/financeiro/notas-servico/{registroTipo}/{id}', 'App\Http\Controllers\Admin\FinanceiroController@destroyNotaServico');
    $router->post('/financeiro/comissoes/nfse', 'App\Http\Controllers\Admin\FinanceiroController@emitirNfseComissao'); // legado
    $router->get('/financeiro/contratos', 'App\Http\Controllers\Admin\ContratosLocacaoController@index');
    $router->post('/financeiro/contratos', 'App\Http\Controllers\Admin\ContratosLocacaoController@store');
    $router->get('/financeiro/contratos/{id}', 'App\Http\Controllers\Admin\ContratosLocacaoController@show');
    $router->put('/financeiro/contratos/{id}', 'App\Http\Controllers\Admin\ContratosLocacaoController@update');
    $router->get('/financeiro/compra-venda', 'App\Http\Controllers\Admin\ContratosCompraVendaController@index');
    $router->post('/financeiro/compra-venda', 'App\Http\Controllers\Admin\ContratosCompraVendaController@store');
    $router->get('/financeiro/compra-venda/{id}', 'App\Http\Controllers\Admin\ContratosCompraVendaController@show');
    $router->put('/financeiro/compra-venda/{id}', 'App\Http\Controllers\Admin\ContratosCompraVendaController@update');
    $router->get('/financeiro/contratos/{contratoId}/vistorias',        'App\Http\Controllers\Admin\VistoriasContratoController@index');
    $router->post('/financeiro/contratos/{contratoId}/vistorias',       'App\Http\Controllers\Admin\VistoriasContratoController@store');
    $router->patch('/financeiro/contratos/{contratoId}/vistorias/{id}', 'App\Http\Controllers\Admin\VistoriasContratoController@update');
    $router->delete('/financeiro/contratos/{contratoId}/vistorias/{id}','App\Http\Controllers\Admin\VistoriasContratoController@destroy');
    $router->get('/financeiro/cobrancas-contrato', 'App\Http\Controllers\Admin\CobrancasContratoController@index');
    $router->post('/financeiro/contratos/{id}/gerar-cobranca', 'App\Http\Controllers\Admin\CobrancasContratoController@gerar');
    $router->patch('/financeiro/cobrancas-contrato/{id}/status', 'App\Http\Controllers\Admin\CobrancasContratoController@updateStatus');
    $router->get('/financeiro/lancamentos', 'App\Http\Controllers\Admin\LancamentosFinanceirosController@index');
    $router->post('/financeiro/lancamentos', 'App\Http\Controllers\Admin\LancamentosFinanceirosController@store');
    $router->put('/financeiro/lancamentos/{id}', 'App\Http\Controllers\Admin\LancamentosFinanceirosController@update');
    $router->delete('/financeiro/lancamentos/{id}', 'App\Http\Controllers\Admin\LancamentosFinanceirosController@destroy');
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
    $router->post('/leads/reprocessar-pendentes', 'App\Http\Controllers\Admin\LeadsController@reprocessarPendentes');

    // Importação de Imóveis
    $router->get('/imoveis', 'App\Http\Controllers\Admin\ImportacaoController@listar');
    $router->post('/imoveis/importar', 'App\Http\Controllers\Admin\ImportacaoController@importar');
    $router->get('/imoveis/importar/{jobId}', 'App\Http\Controllers\Admin\ImportacaoController@status');
    $router->post('/importacao/teste-api', 'App\Http\Controllers\Admin\ImportacaoController@testarAPI');
    $router->get('/imoveis/sincronizacoes', 'App\Http\Controllers\Admin\PropertySyncController@index');
    $router->post('/imoveis/sincronizacoes/executar', 'App\Http\Controllers\Admin\PropertySyncController@runManual');

    // System Logs
    $router->get('/system-logs', 'App\Http\Controllers\Admin\SystemLogsController@index');
    $router->delete('/system-logs/clear', 'App\Http\Controllers\Admin\SystemLogsController@clear');

    // Visitas
    $router->get('/visitas', 'App\Http\Controllers\Admin\\VisitasController@index');
    $router->post('/visitas', 'App\Http\Controllers\Admin\\VisitasController@store');
    $router->patch('/visitas/{id}', 'App\Http\Controllers\Admin\\VisitasController@update');

    // Usuários/Equipe
    $router->get('/users', function () use ($router) {
        $user = app('request')->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
        
        $users = app('db')->table('users')
            ->leftJoin('pessoas', 'users.pessoa_id', '=', 'pessoas.id')
            ->where('users.tenant_id', $user->tenant_id)
            ->select(
                'users.id',
                'users.name',
                'users.email',
                'users.role',
                'users.is_active as ativo',
                'users.pessoa_id',
                'pessoas.nome as pessoa_nome',
                'users.created_at'
            )
            ->orderBy('users.created_at', 'desc')
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
        
        $pessoaId = null;
        if (!empty($data['pessoa_id'])) {
            $pessoaId = (int) $data['pessoa_id'];
            $ok = app('db')->table('pessoas')
                ->where('tenant_id', $user->tenant_id)
                ->where('id', $pessoaId)
                ->exists();
            if (!$ok) {
                return response()->json(['message' => 'Pessoa inválida para este tenant'], 400);
            }
        }

        // Criar usuário
        $userId = app('db')->table('users')->insertGetId([
            'tenant_id' => $user->tenant_id,
            'pessoa_id' => $pessoaId,
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
        
        $pessoaId = null;
        if (array_key_exists('pessoa_id', $data)) {
            if ($data['pessoa_id'] === '' || $data['pessoa_id'] === null) {
                $pessoaId = null;
            } else {
                $pessoaId = (int) $data['pessoa_id'];
                $ok = app('db')->table('pessoas')
                    ->where('tenant_id', $user->tenant_id)
                    ->where('id', $pessoaId)
                    ->exists();
                if (!$ok) {
                    return response()->json(['message' => 'Pessoa inválida para este tenant'], 400);
                }
            }
        }

        $update = [
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'] ?? 'user',
            'is_active' => $data['ativo'] ?? true,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        if (array_key_exists('pessoa_id', $data)) {
            $update['pessoa_id'] = $pessoaId;
        }
        
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
    $router->post('/ads/listings/{id}/configure',       'App\Http\Controllers\Ads\AdsListingController@configure');

    // Property Ads (Propaganda de Imóveis)
    $router->get('/property-ads/proxy-image', 'App\Http\Controllers\Admin\PropertyAdsController@proxyImage');
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

    // ── Gestão de Locação ────────────────────────────────────────────────────

    // Contratos - extended actions
    $router->delete('/financeiro/contratos/{id}',            'App\Http\Controllers\Admin\ContratosLocacaoController@destroy');
    $router->post('/financeiro/contratos/{id}/encerrar',     'App\Http\Controllers\Admin\ContratosLocacaoController@encerrar');
    $router->post('/financeiro/contratos/{id}/renovar',      'App\Http\Controllers\Admin\ContratosLocacaoController@renovar');
    $router->delete('/financeiro/compra-venda/{id}',         'App\Http\Controllers\Admin\ContratosCompraVendaController@destroy');

    // Cobrancas - extended actions
    $router->get('/financeiro/cobrancas-contrato/{id}',          'App\Http\Controllers\Admin\CobrancasContratoController@show');
    $router->put('/financeiro/cobrancas-contrato/{id}',          'App\Http\Controllers\Admin\CobrancasContratoController@update');
    $router->get('/financeiro/cobrancas-contrato/inadimplencia', 'App\Http\Controllers\Admin\CobrancasContratoController@inadimplencia');

    // Fiadores do contrato
    $router->get('/financeiro/contratos/{contratoId}/fiadores',          'App\Http\Controllers\Admin\ContratoFiadoresController@index');
    $router->post('/financeiro/contratos/{contratoId}/fiadores',         'App\Http\Controllers\Admin\ContratoFiadoresController@store');
    $router->delete('/financeiro/contratos/{contratoId}/fiadores/{id}',  'App\Http\Controllers\Admin\ContratoFiadoresController@destroy');

    // Reajustes do contrato
    $router->get('/financeiro/contratos/{contratoId}/reajustes',         'App\Http\Controllers\Admin\ReajusteContratoController@index');
    $router->get('/financeiro/contratos/{contratoId}/reajustes/preview', 'App\Http\Controllers\Admin\ReajusteContratoController@preview');
    $router->post('/financeiro/contratos/{contratoId}/reajustes',        'App\Http\Controllers\Admin\ReajusteContratoController@aplicar');

    // Repasses ao proprietário
    $router->get('/financeiro/repasses',                             'App\Http\Controllers\Admin\RepasseProprietarioController@index');
    $router->get('/financeiro/repasses/{id}',                        'App\Http\Controllers\Admin\RepasseProprietarioController@show');
    $router->post('/financeiro/repasses/{id}/pagar',                 'App\Http\Controllers\Admin\RepasseProprietarioController@pagar');
    $router->get('/financeiro/contratos/{contratoId}/repasses/extrato', 'App\Http\Controllers\Admin\RepasseProprietarioController@extrato');

    // Documentos do contrato (PDF + Assinatura Digital)
    $router->get('/financeiro/contratos/{contratoId}/documentos',                             'App\Http\Controllers\Admin\ContratoDocumentosController@index');
    $router->post('/financeiro/contratos/{contratoId}/documentos/gerar-pdf',                  'App\Http\Controllers\Admin\ContratoDocumentosController@gerarPdf');
    $router->post('/financeiro/contratos/{contratoId}/documentos/{id}/enviar-assinatura',     'App\Http\Controllers\Admin\ContratoDocumentosController@enviarParaAssinatura');
    $router->post('/financeiro/contratos/{contratoId}/documentos/{id}/upload-assinado',       'App\Http\Controllers\Admin\ContratoDocumentosController@uploadAssinado');
    $router->delete('/financeiro/contratos/{contratoId}/documentos/{id}',                     'App\Http\Controllers\Admin\ContratoDocumentosController@destroy');
    $router->get('/financeiro/compra-venda/{contratoId}/documentos',                          'App\Http\Controllers\Admin\ContratoCompraVendaDocumentosController@index');
    $router->post('/financeiro/compra-venda/{contratoId}/documentos/gerar-pdf',               'App\Http\Controllers\Admin\ContratoCompraVendaDocumentosController@gerarPdf');
    $router->post('/financeiro/compra-venda/{contratoId}/documentos/{id}/enviar-assinatura',  'App\Http\Controllers\Admin\ContratoCompraVendaDocumentosController@enviarParaAssinatura');
    $router->post('/financeiro/compra-venda/{contratoId}/documentos/{id}/upload-assinado',    'App\Http\Controllers\Admin\ContratoCompraVendaDocumentosController@uploadAssinado');
    $router->delete('/financeiro/compra-venda/{contratoId}/documentos/{id}',                  'App\Http\Controllers\Admin\ContratoCompraVendaDocumentosController@destroy');

    // Templates de contrato (personalização por tenant)
    $router->get('/financeiro/contrato-templates',              'App\Http\Controllers\Admin\ContratoTemplatesController@index');
    $router->get('/financeiro/contrato-templates/{tipo}',       'App\Http\Controllers\Admin\ContratoTemplatesController@show');
    $router->put('/financeiro/contrato-templates/{tipo}',       'App\Http\Controllers\Admin\ContratoTemplatesController@upsert');
    $router->delete('/financeiro/contrato-templates/{tipo}',    'App\Http\Controllers\Admin\ContratoTemplatesController@destroy');

    // Fotos de vistoria
    $router->get('/vistorias/{vistoriaId}/fotos',          'App\Http\Controllers\Admin\VistoriaFotoController@index');
    $router->post('/vistorias/{vistoriaId}/fotos',         'App\Http\Controllers\Admin\VistoriaFotoController@store');
    $router->patch('/vistorias/{vistoriaId}/fotos/{id}',   'App\Http\Controllers\Admin\VistoriaFotoController@update');
    $router->delete('/vistorias/{vistoriaId}/fotos/{id}',  'App\Http\Controllers\Admin\VistoriaFotoController@destroy');

    // Pessoas (inquilinos e proprietários)
    $router->get('/pessoas',         'App\Http\Controllers\PessoasController@index');
    $router->post('/pessoas',        'App\Http\Controllers\PessoasController@store');
    $router->get('/pessoas/{id}',    'App\Http\Controllers\PessoasController@show');
    $router->put('/pessoas/{id}',    'App\Http\Controllers\PessoasController@update');
    $router->delete('/pessoas/{id}', 'App\Http\Controllers\PessoasController@destroy');
});
