<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Laravel compatibility: define $router for legacy Lumen-style route definitions
$router = app('router');
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
*/

// Health check - movido para /api/health para não conflitar com index.html
$router->get('/api/health', function () use ($router) {
    return response()->json([
        'app' => 'SOCIMOB',
        'version' => app()->version(),
        'status' => 'online'
    ]);
});

// ===========================
// FRONTEND ANTIGO - ARQUIVADO
// O frontend React (novo) é servido pelo servidor Express em server/index.ts
// As rotas abaixo foram desativadas pois agora usamos o frontend React
// ===========================

// // Home - portal público do tenant
// $router->get('/', function () {
//     $path = base_path('public/portal/index.html');
//     if (file_exists($path)) {
//         return response(file_get_contents($path))
//             ->header('Content-Type', 'text/html');
//     }
//     return response('Portal do cliente não encontrado', 404);
// });

// // Login do CRM (admin/corretor)
// $router->get('/login', function () {
//     $path = base_path('public/app/index.html');
//     if (file_exists($path)) {
//         return response(file_get_contents($path))
//             ->header('Content-Type', 'text/html');
//     }
//     return response('Login não encontrado', 404);
// });

$router->post('/github/webhook',  'App\Http\Controllers\GitHubWebhookController@handle');

// Webhook Chaves na Mão - Receber leads
$router->get('/webhook/chaves-na-mao', 'App\Http\Controllers\ChavesNaMaoWebhookController@methodNotAllowed');
$router->post('/webhook/chaves-na-mao', 'App\Http\Controllers\ChavesNaMaoWebhookController@receive');

// Short link para WhatsApp (resolve tenant pelo domínio)
$router->group(['middleware' => 'resolve-tenant'], function () use ($router) {
    $router->get('/w/{code}', 'App\Http\Controllers\ShortLinkController@redirectWhatsApp');
});

// Auth API routes
$router->group(['prefix' => 'api', 'middleware' => 'resolve-tenant'], function () use ($router) {
    // Short link para WhatsApp (resolve tenant pelo domínio)
    $router->get('/w/{code}', 'App\Http\Controllers\ShortLinkController@redirectWhatsApp');

    // ⚡ Rate limiting: 5 tentativas por minuto em login
    $router->post('/auth/login', ['middleware' => 'throttle:5,1', 'uses' => 'App\Http\Controllers\AuthController@login']);
    $router->post('/auth/google', ['middleware' => 'throttle:5,1', 'uses' => 'App\Http\Controllers\AuthController@googleLogin']);
    $router->post('/auth/logout', 'App\Http\Controllers\AuthController@logout');
    $router->get('/auth/me', ['middleware' => 'simple-auth', 'uses' => 'App\Http\Controllers\AuthController@me']);
    
    // 🔒 Sincronização de imóveis - PROTEGIDO POR TENANT
    // Só funciona se acessado pelo domínio correto (ex: exclusivalarimoveis.com)
    $router->get('/properties/sync', 'App\Http\Controllers\PropertyController@sync');

    // Proxy de mídia do Twilio (usado pelo chat) - relativo a /api
    $router->get('/conversas/media/proxy', 'App\Http\Controllers\ConversasController@proxyMedia');
    
    // Configuração do tenant (público) - para homepage dinâmica
    $router->get('/tenant/config', function () {
        try {
            // Resolver tenant pelo domínio
            $tenant = app('tenant');
            
            if (!$tenant) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tenant não identificado'
                ], 404);
            }
            
            if ($tenant) {
                return response()->json([
                    'success' => true,
                    'tenant' => [
                        'name' => $tenant->name,
                        'slogan' => $tenant->description ?? 'Encontre o Imóvel dos Seus Sonhos',
                        'logo_url' => $tenant->logo_url ?? '/assets/logo.png',
                        'primary_color' => $tenant->primary_color ?? '#1e293b',
                        'secondary_color' => $tenant->secondary_color ?? '#3b82f6',
                        'contact_phone' => $tenant->contact_phone,
                        'contact_email' => $tenant->contact_email,
                        'domain' => $tenant->domain,
                        'hero_title' => "Gestão imobiliária fluida<br><span class=\"glow-hero-highlight\">{$tenant->name}</span>",
                        'hero_description' => $tenant->description ?? 'A experiência Glow entrega login único, atendimento inteligente e painéis que respeitam seu papel (cliente, corretor ou administrador) sem precisar trocar de tela.'
                    ]
                ]);
            }
            
            // Se não encontrar tenant, retornar padrão SOCIMOB
            return response()->json([
                'success' => true,
                'tenant' => [
                    'name' => 'SOCIMOB',
                    'slogan' => 'Gestão Imobiliária',
                    'logo_url' => '/assets/logo.png',
                    'primary_color' => '#1e293b',
                    'secondary_color' => '#3b82f6'
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao carregar config do tenant', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar configurações'
            ], 500);
        }
    });
    
    // Portal do Cliente - rotas p£blicas (sem autentica‡Æo)
    $router->group(['prefix' => 'portal'], function () use ($router) {
        // Configura‡Æo do tenant (p£blico)
        $router->get('/config', 'App\Http\Controllers\Portal\PortalController@getConfig');

        // Listagem de im¢veis (p£blico)
        $router->get('/imoveis', 'App\Http\Controllers\Portal\PortalController@getImoveis');

        // Detalhes de im¢vel (p£blico)
        $router->get('/imoveis/{id}', 'App\Http\Controllers\Portal\PortalController@getImovel');

        // Chat bot - criar lead (público, sem auth)
        $router->post('/chat-lead', ['middleware' => 'throttle:10,1', 'uses' => 'App\Http\Controllers\Portal\PortalController@createChatLead']);

        // Avaliação de imóvel - solicitar (público, sem auth)
        $router->post('/avaliacao', ['middleware' => 'throttle:10,1', 'uses' => 'App\Http\Controllers\Portal\PortalController@createEvaluationRequest']);

        // Login do portal
        $router->post('/auth/login', 'App\Http\Controllers\Portal\ClientAuthController@login');
        $router->post('/auth/register', 'App\Http\Controllers\Portal\ClientAuthController@register');
        
        // Rotas autenticadas do portal
        $router->group(['middleware' => 'simple-auth'], function () use ($router) {
            $router->get('/auth/me', 'App\Http\Controllers\Portal\ClientAuthController@me');
            $router->post('/interesse', 'App\Http\Controllers\Portal\PortalController@registrarInteresse');
            $router->get('/likes', 'App\Http\Controllers\Portal\LikesController@list');
            $router->post('/likes/{propertyId}', 'App\Http\Controllers\Portal\LikesController@like');
            $router->post('/chat/start', 'App\Http\Controllers\Portal\ChatController@start');
            $router->get('/chat/{id}', 'App\Http\Controllers\Portal\ChatController@show');
            $router->get('/chat/{id}/mensagens', 'App\Http\Controllers\Portal\ChatController@mensagens');
            $router->post('/chat/{id}/mensagens', 'App\Http\Controllers\Portal\ChatController@send');
        });
    });

    // Analytics collect (public, consent required on client)
    $router->post('/analytics/collect', 'App\Http\Controllers\AnalyticsController@collect');
});

// Landing Page Pública - DESATIVADA (agora usa frontend React)
// $router->get('/imoveis', function () {
//     $path = base_path('public/imoveis.html');
//     if (file_exists($path)) {
//         return response(file_get_contents($path))
//             ->header('Content-Type', 'text/html');
//     }
//     return response('Landing page não encontrada. Path: ' . $path, 404);
// });

// Super Admin API routes
require __DIR__ . '/super-admin.php';

// ===========================
// ROTAS PÚBLICAS (SEM AUTENTICAÇÃO)
// ===========================
$router->group(['prefix' => 'api/properties'], function () use ($router) {
    
    // Sync worker em duas fases
    $router->get('/sync-worker', function () {
        set_time_limit(300); // 5 minutos
        
        $output = [];
        $exitCode = 0;
        
        $workerPath = base_path('sync_worker.php');
        
        if (!file_exists($workerPath)) {
            return response()->json([
                'success' => false,
                'message' => 'sync_worker.php não encontrado',
                'path' => $workerPath
            ], 404);
        }
        
        exec("php {$workerPath} 2>&1", $output, $exitCode);
        
        return response()->json([
            'success' => $exitCode === 0,
            'exit_code' => $exitCode,
            'output' => implode("\n", $output),
            'timestamp' => date('c')
        ]);
    });
    
    // Rotas dinâmicas
    $router->get('/', 'App\Http\Controllers\PublicPropertyController@index');
    $router->get('/{codigo}', 'App\Http\Controllers\PublicPropertyController@show');
});

// Formatação de texto com IA
$router->post('/api/format-text', 'App\Http\Controllers\TextFormatterController@formatText');

// ===========================
// ADS AUTOMATION WEBHOOKS (SEM AUTENTICAÇÃO — verificação por assinatura HMAC)
// ===========================
$router->group(['prefix' => 'api/ads/webhooks', 'middleware' => 'resolve-tenant'], function () use ($router) {
    // GET: handshake de verificação (Meta: hub.mode=subscribe)
    $router->get('/{provider}/receive', 'App\Http\Controllers\Ads\AdsWebhookController@verify');
    // POST: receber eventos (Meta: leadgen, Google: futuro)
    $router->post('/{provider}/receive', 'App\Http\Controllers\Ads\AdsWebhookController@receive');
});

// ===========================
// OLX OAUTH CALLBACK (SEM AUTENTICAÇÃO — verificado pelo state anti-CSRF)
// URI registrada no painel OLX Pro: https://app.socimob.com/api/oauth/olx/callback
// ===========================
$router->get('/api/oauth/{provider}/callback', ['middleware' => 'resolve-tenant', 'uses' => 'App\Http\Controllers\Ads\AdsConnectionController@oauthCallback']);

// ===========================
// WEBHOOK (SEM AUTENTICAÇÃO)
// ===========================
$router->group(['prefix' => 'webhook'], function () use ($router) {
    // GET para validação do webhook (Twilio)
    $router->get('/whatsapp', 'App\Http\Controllers\WebhookController@validateWebhook');
    $router->get('/whatsapp/status', 'App\Http\Controllers\WebhookController@validateStatusWebhook');
    // POST para receber mensagens
    $router->post('/whatsapp', 'App\Http\Controllers\WebhookController@receive');
    $router->post('/whatsapp/status', 'App\Http\Controllers\WebhookController@status');
});

// ===========================
// DEPLOY WEBHOOK (SEM AUTENTICAÇÃO, MAS COM SECRET TOKEN)
// ===========================
$router->group(['prefix' => 'api/deploy'], function () use ($router) {
    $router->get('/', 'App\Http\Controllers\DeployController@deploy');   // GET também funciona
    $router->post('/', 'App\Http\Controllers\DeployController@deploy');
    $router->get('/info', 'App\Http\Controllers\DeployController@info');
});

// Proxy público para mídias do Twilio (usado no chat via <img src>)
$router->get('/conversas/media/proxy', 'App\Http\Controllers\ConversasController@proxyMedia');

// ===========================
// Autenticação (sem middleware)
// ===========================
$router->group(['prefix' => 'api/auth'], function () use ($router) {
    $router->post('/login', 'App\Http\Controllers\AuthController@login');
    $router->post('/forgot-password', 'App\Http\Controllers\PasswordResetController@sendResetLink');
    $router->post('/reset-password', 'App\Http\Controllers\PasswordResetController@reset');
});

// ===========================
// Rotas protegidas (AUTENTICADAS COM TENANT ISOLATION)
// ===========================
// CRITICAL: resolve-tenant MUST come before simple-auth to enforce domain-based tenant isolation
$router->group(['prefix' => 'api', 'middleware' => ['resolve-tenant', 'simple-auth']], function () use ($router) {

    // Auth
    $router->get('/auth/me', 'App\Http\Controllers\AuthController@me');
    $router->post('/auth/logout', 'App\Http\Controllers\AuthController@logout');

    // Importação de imóveis
    $router->group(['prefix' => 'importacoes/imoveis'], function () use ($router) {
        $router->get('/overview', 'App\Http\Controllers\ImportacaoImoveisController@overview');
        $router->get('/historico', 'App\Http\Controllers\ImportacaoImoveisController@historico');
        $router->get('/fila', 'App\Http\Controllers\ImportacaoImoveisController@fila');
        $router->get('/logs', 'App\Http\Controllers\ImportacaoImoveisController@logs');
        $router->post('/', 'App\Http\Controllers\ImportacaoImoveisController@agendarImportacao');
        $router->post('/detalhes', 'App\Http\Controllers\ImportacaoImoveisController@agendarDetalhes');
        $router->post('/sincronizar', 'App\Http\Controllers\ImportacaoImoveisController@sincronizarImovel');
    });

    // Dashboard
    $router->get('/dashboard/stats', 'App\Http\Controllers\DashboardController@stats');
    $router->get('/dashboard/chart/atendimentos', 'App\Http\Controllers\DashboardController@chartAtendimentos');
    $router->get('/dashboard/atividades', 'App\Http\Controllers\DashboardController@atividades');
    $router->get('/dashboard/timeline', 'App\Http\Controllers\DashboardController@timeline');

    // Vistorias
    $router->get('/vistorias', 'App\Http\Controllers\VistoriasController@index');
    $router->get('/vistorias/export', 'App\Http\Controllers\VistoriasController@export');
    $router->get('/vistorias/solicitacoes', 'App\Http\Controllers\VistoriaSolicitacoesController@index');
    $router->post('/vistorias/solicitacoes', 'App\Http\Controllers\VistoriaSolicitacoesController@store');
    $router->put('/vistorias/solicitacoes/{id}/status', 'App\Http\Controllers\VistoriaSolicitacoesController@updateStatus');
    $router->get('/vistorias/contestacoes', 'App\Http\Controllers\VistoriaContestacoesController@index');
    $router->post('/vistorias/contestacoes', 'App\Http\Controllers\VistoriaContestacoesController@store');
    $router->get('/vistorias/contestacoes/{id}', 'App\Http\Controllers\VistoriaContestacoesController@show');
    $router->put('/vistorias/contestacoes/{id}/status', 'App\Http\Controllers\VistoriaContestacoesController@updateStatus');
    $router->delete('/vistorias/contestacoes/{id}', 'App\Http\Controllers\VistoriaContestacoesController@destroy');
    $router->get('/vistorias/{id}', 'App\Http\Controllers\VistoriasController@show');

    // Pessoas
    $router->get('/pessoas', 'App\Http\Controllers\PessoasController@index');
    $router->post('/pessoas', 'App\Http\Controllers\PessoasController@store');
    $router->get('/pessoas/{id}', 'App\Http\Controllers\PessoasController@show');
    $router->put('/pessoas/{id}', 'App\Http\Controllers\PessoasController@update');
    $router->delete('/pessoas/{id}', 'App\Http\Controllers\PessoasController@destroy');
    
    // Pessoas - Interações/Timeline
    $router->get('/pessoas/{id}/interacoes', 'App\Http\Controllers\PessoasController@getInteracoes');
    $router->post('/pessoas/{id}/interacoes', 'App\Http\Controllers\PessoasController@addInteracao');
    
    // Pessoas - Documentos
    $router->get('/pessoas/{id}/documentos', 'App\Http\Controllers\PessoasController@getDocumentos');
    $router->post('/pessoas/{id}/documentos', 'App\Http\Controllers\PessoasController@uploadDocumento');
    $router->get('/pessoas/{id}/documentos/export', 'App\Http\Controllers\PessoasController@exportDocumentos');
    $router->post('/pessoas/{id}/documentos/export', 'App\Http\Controllers\PessoasController@exportDocumentosSelecionados');
    $router->delete('/pessoas/documentos/{documentoId}', 'App\Http\Controllers\PessoasController@deleteDocumento');
    $router->post('/pessoas/documentos/{documentoId}/verificar', 'App\Http\Controllers\PessoasController@verificarDocumento');
    
    // Pessoas - Relacionamentos
    $router->get('/pessoas/{id}/relacionamentos', 'App\Http\Controllers\PessoasController@getRelacionamentos');
    $router->post('/pessoas/{id}/relacionamentos', 'App\Http\Controllers\PessoasController@addRelacionamento');
    $router->delete('/pessoas/relacionamentos/{relacionamentoId}', 'App\Http\Controllers\PessoasController@deleteRelacionamento');
    
    // Pessoas - Ações
    $router->post('/pessoas/{id}/papeis', 'App\Http\Controllers\PessoasController@gerenciarPapeis');
    $router->post('/pessoas/{id}/score', 'App\Http\Controllers\PessoasController@atualizarScore');
    $router->post('/pessoas/{id}/sync-imobi-brasil', 'App\Http\Controllers\PessoasController@syncImobiBrasil');
    $router->delete('/pessoas/{id}/sync-imobi-brasil', 'App\Http\Controllers\PessoasController@unsyncImobiBrasil');

    // Notificações
    $router->get('/notifications', 'App\Http\Controllers\NotificationController@index');
    $router->get('/notifications/unread-count', 'App\Http\Controllers\NotificationController@unreadCount');
    $router->get('/notifications/summary', 'App\Http\Controllers\NotificationController@summary');
    $router->post('/notifications/mark-all-as-read', 'App\Http\Controllers\NotificationController@markAllAsRead');
    $router->get('/notifications/{id}', 'App\Http\Controllers\NotificationController@show');
    $router->post('/notifications/{id}/read', 'App\Http\Controllers\NotificationController@markAsRead');
    $router->post('/notifications/{id}/unread', 'App\Http\Controllers\NotificationController@markAsUnread');
    $router->delete('/notifications/{id}', 'App\Http\Controllers\NotificationController@destroy');

    // Assinaturas Eletrônicas
    $router->get('/assinaturas/documentos', 'App\Http\Controllers\AssinaturasController@index');
    $router->post('/assinaturas/documentos', 'App\Http\Controllers\AssinaturasController@store');
    $router->get('/assinaturas/documentos/{id}', 'App\Http\Controllers\AssinaturasController@show');
    $router->put('/assinaturas/documentos/{id}/status', 'App\Http\Controllers\AssinaturasController@updateStatus');
    $router->delete('/assinaturas/documentos/{id}', 'App\Http\Controllers\AssinaturasController@destroy');

    // Imóveis - CRUD
    $router->get('/imoveis/export', 'App\Http\Controllers\PropertyController@export');
    $router->get('/imoveis', 'App\Http\Controllers\PropertyController@index');
    $router->post('/imoveis/{id}/restore', 'App\Http\Controllers\PropertyController@restore');
    $router->delete('/imoveis/{id}/force', 'App\Http\Controllers\PropertyController@forceDestroy');
    $router->get('/imoveis/portal-opcoes', 'App\Http\Controllers\PropertyController@portalOptions');
    $router->post('/imoveis', 'App\Http\Controllers\PropertyController@store');
    $router->put('/imoveis/{id}', 'App\Http\Controllers\PropertyController@update');
    $router->delete('/imoveis/{id}', 'App\Http\Controllers\PropertyController@destroy');
    $router->post('/imoveis/ai/gerar-descricao', 'App\Http\Controllers\PropertyController@generateDescriptions');
    
    // Integração Imobi Brasil - Imóveis locais
    $router->post('/imoveis/{id}/enviar-imobi-brasil', 'App\Http\Controllers\PropertyController@enviarImobiBrasil');
    $router->put('/imoveis/{id}/atualizar-imobi-brasil', 'App\Http\Controllers\PropertyController@atualizarImobiBrasil');
    $router->get('/imoveis/{id}/status-imobi-brasil', 'App\Http\Controllers\PropertyController@statusImobiBrasil');
    $router->post('/imoveis/{id}/enviar-imagens-imobi-brasil', 'App\Http\Controllers\PropertyController@enviarImagensImobiBrasil');
    $router->get('/imoveis/{id}/listar-imagens-imobi-brasil', 'App\Http\Controllers\PropertyController@listarImagensImobiBrasil');

    // ImobiBrasil - Proxy API (panel de gestão)
    $router->group(['prefix' => 'imobi-brasil'], function () use ($router) {
        // Conta
        $router->get('/account/status', 'App\Http\Controllers\ImobiBrasilController@accountStatus');

        // Imóveis
        $router->get('/imoveis', 'App\Http\Controllers\ImobiBrasilController@listarImoveis');
        $router->get('/imoveis/tipos', 'App\Http\Controllers\ImobiBrasilController@listarTiposImovel');
        $router->get('/imoveis/{codigoImovel}', 'App\Http\Controllers\ImobiBrasilController@dadosImovel');
        $router->delete('/imoveis/{codigoImovel}', 'App\Http\Controllers\ImobiBrasilController@excluirImovel');
        $router->get('/imoveis/{codigoImovel}/imagens', 'App\Http\Controllers\ImobiBrasilController@listarImagensImovel');
        $router->delete('/imoveis/{codigoImovel}/imagens/{codigoImagem}', 'App\Http\Controllers\ImobiBrasilController@excluirImagemImovel');

        // Características
        $router->get('/caracteristicas', 'App\Http\Controllers\ImobiBrasilController@listarCaracteristicas');
        $router->post('/caracteristicas', 'App\Http\Controllers\ImobiBrasilController@inserirCaracteristica');
        $router->delete('/caracteristicas/{codigoCaracteristica}', 'App\Http\Controllers\ImobiBrasilController@excluirCaracteristica');
        $router->post('/imoveis/{codigoImovel}/caracteristicas/{codigoCaracteristica}', 'App\Http\Controllers\ImobiBrasilController@adicionarCaracteristicaImovel');
        $router->delete('/imoveis/{codigoImovel}/caracteristicas/{codigoCaracteristica}', 'App\Http\Controllers\ImobiBrasilController@removerCaracteristicaImovel');

        // Pessoas
        $router->get('/pessoas', 'App\Http\Controllers\ImobiBrasilController@listarPessoas');
        $router->post('/pessoas', 'App\Http\Controllers\ImobiBrasilController@inserirPessoa');
        $router->get('/pessoas/{codigoPessoa}', 'App\Http\Controllers\ImobiBrasilController@dadosPessoa');
        $router->post('/pessoas/{codigoPessoa}', 'App\Http\Controllers\ImobiBrasilController@alterarPessoa');
        $router->delete('/pessoas/{codigoPessoa}', 'App\Http\Controllers\ImobiBrasilController@excluirPessoa');
        $router->delete('/pessoas/{codigoPessoa}/imagem', 'App\Http\Controllers\ImobiBrasilController@excluirImagemPessoa');

        // Mensagens
        $router->get('/mensagens', 'App\Http\Controllers\ImobiBrasilController@listarMensagens');
        $router->post('/mensagens', 'App\Http\Controllers\ImobiBrasilController@inserirMensagem');
        $router->get('/mensagens/{codigoMensagem}', 'App\Http\Controllers\ImobiBrasilController@dadosMensagem');
        $router->delete('/mensagens/{codigoMensagem}', 'App\Http\Controllers\ImobiBrasilController@excluirMensagem');
        $router->post('/mensagens/{codigoMensagem}/lido', 'App\Http\Controllers\ImobiBrasilController@marcarMensagemLida');

        // Negócios
        $router->get('/negocios', 'App\Http\Controllers\ImobiBrasilController@listarNegocios');
        $router->post('/negocios', 'App\Http\Controllers\ImobiBrasilController@inserirNegocio');
        $router->get('/negocios/etapas', 'App\Http\Controllers\ImobiBrasilController@listarEtapasNegocios');
        $router->get('/negocios/{codigoNegocio}', 'App\Http\Controllers\ImobiBrasilController@dadosNegocio');
        $router->post('/negocios/{codigoNegocio}', 'App\Http\Controllers\ImobiBrasilController@alterarNegocio');
        $router->delete('/negocios/{codigoNegocio}', 'App\Http\Controllers\ImobiBrasilController@excluirNegocio');

        // Corretores
        $router->get('/corretores', 'App\Http\Controllers\ImobiBrasilController@listarCorretores');
        $router->get('/corretores/{codigoCorretor}', 'App\Http\Controllers\ImobiBrasilController@dadosCorretor');
        $router->get('/corretores/{codigoCorretor}/imoveis', 'App\Http\Controllers\ImobiBrasilController@imoveisCorretor');

        // Clientes
        $router->get('/clientes', 'App\Http\Controllers\ImobiBrasilController@listarClientes');
        $router->get('/clientes/{codigoCliente}', 'App\Http\Controllers\ImobiBrasilController@dadosCliente');

        // Cidades
        $router->get('/cidades', 'App\Http\Controllers\ImobiBrasilController@listarCidades');

        // Usuários adicionais
        $router->get('/usuarios-adicionais/{codigoUsuario}', 'App\Http\Controllers\ImobiBrasilController@dadosUsuarioAdicional');
    });
    
    $router->get('/chaves', 'App\Http\Controllers\PropertyController@keysIndex');
    $router->get('/chaves/movimentacoes', 'App\Http\Controllers\PropertyController@keysMovements');
    $router->post('/chaves/movimentacoes', 'App\Http\Controllers\PropertyController@keysMove');

    // Properties - Generate AI Description for Ads
    $router->post('/properties/{id}/generate-ad-description', 'App\Http\Controllers\PropertyController@generateAdDescription');

    // Leads
    $router->get('/leads', 'App\Http\Controllers\LeadsController@index');
    $router->post('/leads', 'App\Http\Controllers\LeadsController@store');
    $router->get('/leads/stats', 'App\Http\Controllers\LeadsController@stats');
    $router->get('/leads/{id}', 'App\Http\Controllers\LeadsController@show');
    $router->put('/leads/{id}', 'App\Http\Controllers\LeadsController@update');
    $router->patch('/leads/{id}/state', 'App\Http\Controllers\LeadsController@updateState');
    $router->patch('/leads/{id}/status', 'App\Http\Controllers\LeadsController@updateStatus');
    $router->post('/leads/{id}/claim', 'App\Http\Controllers\LeadsController@claim');
    $router->post('/leads/{id}/release', 'App\Http\Controllers\LeadsController@release');
    $router->get('/leads/{id}/documents', 'App\Http\Controllers\LeadDocumentsController@index');
    $router->post('/leads/{id}/documents', 'App\Http\Controllers\LeadDocumentsController@store');
    $router->delete('/leads/{id}/documents/{documentId}', 'App\Http\Controllers\LeadDocumentsController@destroy');
    $router->get('/leads/{id}/documents/export', 'App\Http\Controllers\LeadDocumentsController@export');
    $router->post('/leads/{id}/documents/export', 'App\Http\Controllers\LeadDocumentsController@exportSelected');
    $router->delete('/leads/{id}', 'App\Http\Controllers\LeadsController@destroy');
    $router->delete('/leads', 'App\Http\Controllers\LeadsController@bulkDestroy');
    $router->post('/leads/{id}/diagnostico', 'App\Http\Controllers\LeadsController@diagnostico');

    // Conversas e Chat
    $router->group(['prefix' => 'admin'], function () use ($router) {
        $router->get('/conversas', 'App\Http\Controllers\Admin\ConversasController@index');
        $router->get('/conversas/fila/estatisticas', 'App\Http\Controllers\Admin\ConversasController@estatisticasFila');
        $router->post('/conversas/fila/pegar-proxima', 'App\Http\Controllers\Admin\ConversasController@pegarProxima');
        $router->post('/conversas/{id}/devolver-fila', 'App\Http\Controllers\Admin\ConversasController@devolverParaFila');
        $router->post('/conversas/{id}/atribuir', 'App\Http\Controllers\Admin\ConversasController@atribuirCorretor');
        $router->get('/conversas/tempo-real', 'App\Http\Controllers\ConversasController@tempoReal');
        $router->get('/conversas/por-telefone/{telefone}', 'App\Http\Controllers\ConversasController@porTelefone');
        $router->get('/conversas/{id}', 'App\Http\Controllers\Admin\ConversasController@show');
        $router->get('/conversas/{id}/mensagens', 'App\Http\Controllers\Admin\ConversasController@mensagens');
        $router->post('/conversas/{id}/mensagens', 'App\Http\Controllers\Admin\ConversasController@enviarMensagem');
        // Proxy de mídia (Twilio) – também exposto sem auth em /api/conversas/media/proxy
        $router->get('/conversas/media/proxy', 'App\Http\Controllers\Admin\ConversasController@proxyMedia');
        $router->get('/mensagens/{id}/media', 'App\Http\Controllers\Admin\MensagemMediaController@show');
        $router->get('/corretores', 'App\Http\Controllers\Admin\CommissionController@listarCorretores');

        // Leads - SMS
        $router->post('/leads/{id}/sms', 'App\Http\Controllers\Admin\LeadsController@sendSms');
        
        // Tenant Settings
        $router->get('/settings', 'App\Http\Controllers\Admin\TenantSettingsController@index');
        $router->put('/settings', 'App\Http\Controllers\Admin\TenantSettingsController@update');
    });
    
    // Imóveis - Detalhes completos
    $router->get('/imoveis/detalhes/{codigo}', 'App\Http\Controllers\PropertyController@detalhesCompletos');
    $router->get('/imoveis/{id}', 'App\Http\Controllers\PropertyController@show');

    // CRM unificado
    $router->get('/crm/clientes', 'App\Http\Controllers\CRMController@index');
    $router->patch('/crm/clientes/{id}/status', 'App\Http\Controllers\CRMController@updateStatus');

    // Configurações do CRM / IA
    $router->get('/settings', 'App\Http\Controllers\SettingsController@index');
    $router->put('/settings', 'App\Http\Controllers\SettingsController@update');

    // ===========================
    // ADS AUTOMATION MODULE
    // ===========================

    // Status geral e configurações
    $router->get('/ads/status', 'App\Http\Controllers\Ads\AdsConnectionController@status');
    $router->post('/ads/settings', 'App\Http\Controllers\Ads\AdsConnectionController@saveSettings');
    $router->get('/ads/logs', 'App\Http\Controllers\Ads\AdsListingController@logs');

    // Leads captados pelos providers
    $router->get('/ads/leads', 'App\Http\Controllers\Ads\AdsLeadsController@index');
    $router->get('/ads/leads/stats', 'App\Http\Controllers\Ads\AdsLeadsController@stats');
    $router->post('/ads/leads/olx/sync', 'App\Http\Controllers\Ads\AdsLeadsController@syncOlx'); // pull OLX leads

    // Analytics dashboard
    $router->get('/ads/analytics', 'App\Http\Controllers\Ads\AdsAnalyticsController@index');

    // Conexão OAuth por provider
    $router->post('/ads/{provider}/connect/start', 'App\Http\Controllers\Ads\AdsConnectionController@startConnect');
    $router->get('/ads/{provider}/connect/callback', 'App\Http\Controllers\Ads\AdsConnectionController@oauthCallback');
    $router->delete('/ads/{provider}/connect', 'App\Http\Controllers\Ads\AdsConnectionController@disconnect');
    $router->post('/ads/{provider}/accounts', 'App\Http\Controllers\Ads\AdsConnectionController@saveAccount');

    // OLX: conexão por credenciais (sem OAuth popup)
    $router->post('/ads/olx/connect/credentials', 'App\Http\Controllers\Ads\AdsConnectionController@connectCredentials');

    // Publicação de imóveis
    $router->post('/listings/{id}/ads/publish', 'App\Http\Controllers\Ads\AdsListingController@publish');
    $router->post('/listings/{id}/ads/unpublish', 'App\Http\Controllers\Ads\AdsListingController@unpublish');
    $router->get('/listings/{id}/ads/status', 'App\Http\Controllers\Ads\AdsListingController@listingAdsStatus');
});

// Additional route files (previously loaded via bootstrap/app.php then() callback)
require __DIR__ . '/admin.php';
require __DIR__ . '/client-portal.php';
require __DIR__ . '/subscriptions.php';
require __DIR__ . '/themes.php';
require __DIR__ . '/domains.php';
require __DIR__ . '/portal.php';
