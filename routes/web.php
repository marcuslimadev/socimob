<?php

/** @var \Laravel\Lumen\Routing\Router $router */
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
*/

// Health check - movido para /api/health para não conflitar com index.html
$router->get('/api/health', function () use ($router) {
    return response()->json([
        'app' => 'SOCIMOB',
        'version' => $router->app->version(),
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

$router->post('/github/webhook', 'GitHubWebhookController@handle');

// Webhook Chaves na Mão - Receber leads
$router->get('/webhook/chaves-na-mao', 'ChavesNaMaoWebhookController@methodNotAllowed');
$router->post('/webhook/chaves-na-mao', 'ChavesNaMaoWebhookController@receive');

// Short link para WhatsApp (resolve tenant pelo domínio)
$router->group(['middleware' => 'resolve-tenant'], function () use ($router) {
    $router->get('/w/{code}', 'ShortLinkController@redirectWhatsApp');
});

// Auth API routes
$router->group(['prefix' => 'api', 'middleware' => 'resolve-tenant'], function () use ($router) {
    // Short link para WhatsApp (resolve tenant pelo domínio)
    $router->get('/w/{code}', 'ShortLinkController@redirectWhatsApp');

    // ⚡ Rate limiting: 5 tentativas por minuto em login
    $router->post('/auth/login', ['middleware' => 'throttle:5,1', 'uses' => 'AuthController@login']);
    $router->post('/auth/google', ['middleware' => 'throttle:5,1', 'uses' => 'AuthController@googleLogin']);
    $router->post('/auth/logout', 'AuthController@logout');
    $router->get('/auth/me', ['middleware' => 'simple-auth', 'uses' => 'AuthController@me']);
    
    // 🔒 Sincronização de imóveis - PROTEGIDO POR TENANT
    // Só funciona se acessado pelo domínio correto (ex: exclusivalarimoveis.com)
    $router->get('/properties/sync', 'PropertyController@sync');

    // Proxy de mídia do Twilio (usado pelo chat) - relativo a /api
    $router->get('/conversas/media/proxy', 'ConversasController@proxyMedia');
    
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
            \Log::error('Erro ao carregar config do tenant', [
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
        $router->get('/config', 'Portal\PortalController@getConfig');

        // Listagem de im¢veis (p£blico)
        $router->get('/imoveis', 'Portal\PortalController@getImoveis');

        // Detalhes de im¢vel (p£blico)
        $router->get('/imoveis/{id}', 'Portal\PortalController@getImovel');

        // Chat bot - criar lead (público, sem auth)
        $router->post('/chat-lead', ['middleware' => 'throttle:10,1', 'uses' => 'Portal\PortalController@createChatLead']);

        // Avaliação de imóvel - solicitar (público, sem auth)
        $router->post('/avaliacao', ['middleware' => 'throttle:10,1', 'uses' => 'Portal\PortalController@createEvaluationRequest']);

        // Login do portal
        $router->post('/auth/login', 'Portal\ClientAuthController@login');
        $router->post('/auth/register', 'Portal\ClientAuthController@register');
        
        // Rotas autenticadas do portal
        $router->group(['middleware' => 'simple-auth'], function () use ($router) {
            $router->get('/auth/me', 'Portal\ClientAuthController@me');
            $router->post('/interesse', 'Portal\PortalController@registrarInteresse');
            $router->get('/likes', 'Portal\LikesController@list');
            $router->post('/likes/{propertyId}', 'Portal\LikesController@like');
            $router->post('/chat/start', 'Portal\ChatController@start');
            $router->get('/chat/{id}', 'Portal\ChatController@show');
            $router->get('/chat/{id}/mensagens', 'Portal\ChatController@mensagens');
            $router->post('/chat/{id}/mensagens', 'Portal\ChatController@send');
        });
    });

    // Analytics collect (public, consent required on client)
    $router->post('/analytics/collect', 'AnalyticsController@collect');
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
    $router->get('/', 'PublicPropertyController@index');
    $router->get('/{codigo}', 'PublicPropertyController@show');
});

// Formatação de texto com IA
$router->post('/api/format-text', 'TextFormatterController@formatText');

// ===========================
// WEBHOOK (SEM AUTENTICAÇÃO)
// ===========================
$router->group(['prefix' => 'webhook'], function () use ($router) {
    // GET para validação do webhook (Twilio)
    $router->get('/whatsapp', 'WebhookController@validateWebhook');
    $router->get('/whatsapp/status', 'WebhookController@validateStatusWebhook');
    // POST para receber mensagens
    $router->post('/whatsapp', 'WebhookController@receive');
    $router->post('/whatsapp/status', 'WebhookController@status');
});

// ===========================
// DEPLOY WEBHOOK (SEM AUTENTICAÇÃO, MAS COM SECRET TOKEN)
// ===========================
$router->group(['prefix' => 'api/deploy'], function () use ($router) {
    $router->get('/', 'DeployController@deploy');   // GET também funciona
    $router->post('/', 'DeployController@deploy');
    $router->get('/info', 'DeployController@info');
});

// Proxy público para mídias do Twilio (usado no chat via <img src>)
$router->get('/conversas/media/proxy', 'ConversasController@proxyMedia');

// ===========================
// Autenticação (sem middleware)
// ===========================
$router->group(['prefix' => 'api/auth'], function () use ($router) {
    $router->post('/login', 'AuthController@login');
    $router->post('/forgot-password', 'PasswordResetController@sendResetLink');
    $router->post('/reset-password', 'PasswordResetController@reset');
});

// ===========================
// Rotas protegidas (AUTENTICADAS COM TENANT ISOLATION)
// ===========================
// CRITICAL: resolve-tenant MUST come before simple-auth to enforce domain-based tenant isolation
$router->group(['prefix' => 'api', 'middleware' => ['resolve-tenant', 'simple-auth']], function () use ($router) {

    // Auth
    $router->get('/auth/me', 'AuthController@me');
    $router->post('/auth/logout', 'AuthController@logout');

    // Importação de imóveis
    $router->group(['prefix' => 'importacoes/imoveis'], function () use ($router) {
        $router->get('/overview', 'ImportacaoImoveisController@overview');
        $router->get('/historico', 'ImportacaoImoveisController@historico');
        $router->get('/fila', 'ImportacaoImoveisController@fila');
        $router->get('/logs', 'ImportacaoImoveisController@logs');
        $router->post('/', 'ImportacaoImoveisController@agendarImportacao');
        $router->post('/detalhes', 'ImportacaoImoveisController@agendarDetalhes');
        $router->post('/sincronizar', 'ImportacaoImoveisController@sincronizarImovel');
    });

    // Dashboard
    $router->get('/dashboard/stats', 'DashboardController@stats');
    $router->get('/dashboard/chart/atendimentos', 'DashboardController@chartAtendimentos');
    $router->get('/dashboard/atividades', 'DashboardController@atividades');
    $router->get('/dashboard/timeline', 'DashboardController@timeline');

    // Vistorias
    $router->get('/vistorias', 'VistoriasController@index');
    $router->get('/vistorias/export', 'VistoriasController@export');
    $router->get('/vistorias/solicitacoes', 'VistoriaSolicitacoesController@index');
    $router->post('/vistorias/solicitacoes', 'VistoriaSolicitacoesController@store');
    $router->put('/vistorias/solicitacoes/{id}/status', 'VistoriaSolicitacoesController@updateStatus');
    $router->get('/vistorias/contestacoes', 'VistoriaContestacoesController@index');
    $router->post('/vistorias/contestacoes', 'VistoriaContestacoesController@store');
    $router->get('/vistorias/contestacoes/{id}', 'VistoriaContestacoesController@show');
    $router->put('/vistorias/contestacoes/{id}/status', 'VistoriaContestacoesController@updateStatus');
    $router->delete('/vistorias/contestacoes/{id}', 'VistoriaContestacoesController@destroy');
    $router->get('/vistorias/{id}', 'VistoriasController@show');

    // Pessoas
    $router->get('/pessoas', 'PessoasController@index');
    $router->post('/pessoas', 'PessoasController@store');
    $router->get('/pessoas/{id}', 'PessoasController@show');
    $router->put('/pessoas/{id}', 'PessoasController@update');
    $router->delete('/pessoas/{id}', 'PessoasController@destroy');
    
    // Pessoas - Interações/Timeline
    $router->get('/pessoas/{id}/interacoes', 'PessoasController@getInteracoes');
    $router->post('/pessoas/{id}/interacoes', 'PessoasController@addInteracao');
    
    // Pessoas - Documentos
    $router->get('/pessoas/{id}/documentos', 'PessoasController@getDocumentos');
    $router->post('/pessoas/{id}/documentos', 'PessoasController@uploadDocumento');
    $router->get('/pessoas/{id}/documentos/export', 'PessoasController@exportDocumentos');
    $router->post('/pessoas/{id}/documentos/export', 'PessoasController@exportDocumentosSelecionados');
    $router->delete('/pessoas/documentos/{documentoId}', 'PessoasController@deleteDocumento');
    $router->post('/pessoas/documentos/{documentoId}/verificar', 'PessoasController@verificarDocumento');
    
    // Pessoas - Relacionamentos
    $router->get('/pessoas/{id}/relacionamentos', 'PessoasController@getRelacionamentos');
    $router->post('/pessoas/{id}/relacionamentos', 'PessoasController@addRelacionamento');
    $router->delete('/pessoas/relacionamentos/{relacionamentoId}', 'PessoasController@deleteRelacionamento');
    
    // Pessoas - Ações
    $router->post('/pessoas/{id}/papeis', 'PessoasController@gerenciarPapeis');
    $router->post('/pessoas/{id}/score', 'PessoasController@atualizarScore');

    // Notificações
    $router->get('/notifications', 'NotificationController@index');
    $router->get('/notifications/unread-count', 'NotificationController@unreadCount');
    $router->get('/notifications/summary', 'NotificationController@summary');
    $router->post('/notifications/mark-all-as-read', 'NotificationController@markAllAsRead');
    $router->get('/notifications/{id}', 'NotificationController@show');
    $router->post('/notifications/{id}/read', 'NotificationController@markAsRead');
    $router->post('/notifications/{id}/unread', 'NotificationController@markAsUnread');
    $router->delete('/notifications/{id}', 'NotificationController@destroy');

    // Assinaturas Eletrônicas
    $router->get('/assinaturas/documentos', 'AssinaturasController@index');
    $router->post('/assinaturas/documentos', 'AssinaturasController@store');
    $router->get('/assinaturas/documentos/{id}', 'AssinaturasController@show');
    $router->put('/assinaturas/documentos/{id}/status', 'AssinaturasController@updateStatus');
    $router->delete('/assinaturas/documentos/{id}', 'AssinaturasController@destroy');

    // Imóveis - CRUD
    $router->get('/imoveis/export', 'PropertyController@export');
    $router->get('/imoveis', 'PropertyController@index');
    $router->post('/imoveis', 'PropertyController@store');
    $router->put('/imoveis/{id}', 'PropertyController@update');
    $router->delete('/imoveis/{id}', 'PropertyController@destroy');
    $router->post('/imoveis/ai/gerar-descricao', 'PropertyController@generateDescriptions');

    // Properties - Generate AI Description for Ads
    $router->post('/properties/{id}/generate-ad-description', 'PropertyController@generateAdDescription');

    // Leads
    $router->get('/leads', 'LeadsController@index');
    $router->post('/leads', 'LeadsController@store');
    $router->get('/leads/stats', 'LeadsController@stats');
    $router->get('/leads/{id}', 'LeadsController@show');
    $router->put('/leads/{id}', 'LeadsController@update');
    $router->patch('/leads/{id}/state', 'LeadsController@updateState');
    $router->patch('/leads/{id}/status', 'LeadsController@updateStatus');
    $router->post('/leads/{id}/claim', 'LeadsController@claim');
    $router->post('/leads/{id}/release', 'LeadsController@release');
    $router->get('/leads/{id}/documents', 'LeadDocumentsController@index');
    $router->post('/leads/{id}/documents', 'LeadDocumentsController@store');
    $router->delete('/leads/{id}/documents/{documentId}', 'LeadDocumentsController@destroy');
    $router->get('/leads/{id}/documents/export', 'LeadDocumentsController@export');
    $router->post('/leads/{id}/documents/export', 'LeadDocumentsController@exportSelected');
    $router->delete('/leads/{id}', 'LeadsController@destroy');
    $router->delete('/leads', 'LeadsController@bulkDestroy');
    $router->post('/leads/{id}/diagnostico', 'LeadsController@diagnostico');

    // Conversas e Chat
    $router->group(['prefix' => 'admin'], function () use ($router) {
        $router->get('/conversas', 'Admin\ConversasController@index');
        $router->get('/conversas/fila/estatisticas', 'Admin\ConversasController@estatisticasFila');
        $router->post('/conversas/fila/pegar-proxima', 'Admin\ConversasController@pegarProxima');
        $router->post('/conversas/{id}/devolver-fila', 'Admin\ConversasController@devolverParaFila');
        $router->post('/conversas/{id}/atribuir', 'Admin\ConversasController@atribuirCorretor');
        $router->get('/conversas/tempo-real', 'ConversasController@tempoReal');
        $router->get('/conversas/por-telefone/{telefone}', 'ConversasController@porTelefone');
        $router->get('/conversas/{id}', 'Admin\ConversasController@show');
        $router->get('/conversas/{id}/mensagens', 'Admin\ConversasController@mensagens');
        $router->post('/conversas/{id}/mensagens', 'Admin\ConversasController@enviarMensagem');
        // Proxy de mídia (Twilio) – também exposto sem auth em /api/conversas/media/proxy
        $router->get('/conversas/media/proxy', 'Admin\ConversasController@proxyMedia');
        $router->get('/mensagens/{id}/media', 'Admin\MensagemMediaController@show');
        $router->get('/corretores', 'Admin\CommissionController@listarCorretores');

        // Leads - SMS
        $router->post('/leads/{id}/sms', 'Admin\LeadsController@sendSms');
        
        // Tenant Settings
        $router->get('/settings', 'Admin\TenantSettingsController@index');
        $router->put('/settings', 'Admin\TenantSettingsController@update');
    });
    
    // Imóveis - Detalhes completos
    $router->get('/imoveis/detalhes/{codigo}', 'PropertyController@detalhesCompletos');
    $router->get('/imoveis/{id}', 'PropertyController@show');

    // CRM unificado
    $router->get('/crm/clientes', 'CRMController@index');
    $router->patch('/crm/clientes/{id}/status', 'CRMController@updateStatus');

    // Configurações do CRM / IA
    $router->get('/settings', 'SettingsController@index');
    $router->put('/settings', 'SettingsController@update');
});
