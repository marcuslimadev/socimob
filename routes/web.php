<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Laravel compatibility: define $router for legacy Lumen-style route definitions
$router = app('router');
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
*/

if (!function_exists('socimobDeployInfo')) {
    function socimobDeployInfo(): array
    {
        $fallbackVersion = app()->version();
        $fallbackDeployedAt = now()->format('d/m/Y H:i');
        $fallbackSummary = 'Sem resumo de deploy disponível.';

        $versionFile = storage_path('app/deploy-version.json');
        if (!File::exists($versionFile)) {
            return [
                'app' => 'SOCIMOB',
                'version' => $fallbackVersion,
                'deployed_at' => $fallbackDeployedAt,
                'deploy_summary' => $fallbackSummary,
            ];
        }

        try {
            $raw = File::get($versionFile);
            $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

            return [
                'app' => $data['app'] ?? 'SOCIMOB',
                'version' => $data['version'] ?? $fallbackVersion,
                'deployed_at' => $data['deployed_at'] ?? $fallbackDeployedAt,
                'deploy_summary' => $data['deploy_summary'] ?? $fallbackSummary,
                'deployed_by' => $data['deployed_by'] ?? null,
            ];
        } catch (\Throwable $e) {
            return [
                'app' => 'SOCIMOB',
                'version' => $fallbackVersion,
                'deployed_at' => $fallbackDeployedAt,
                'deploy_summary' => $fallbackSummary,
            ];
        }
    }
}

// Health check - movido para /api/health para não conflitar com index.html
$router->get('/api/health', function () use ($router) {
    $deploy = socimobDeployInfo();

    $schedulerPath = storage_path('framework/scheduler-health.json');
    $schedulerLastRunAt = null;
    $schedulerHealthy = false;
    $schedulerStatus = 'unknown';
    if (File::exists($schedulerPath)) {
        try {
            $raw = File::get($schedulerPath);
            $sched = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            $schedulerLastRunAt = $sched['last_run_at'] ?? null;
            if ($schedulerLastRunAt) {
                $last = \Carbon\Carbon::parse($schedulerLastRunAt);
                $schedulerHealthy = $last->isAfter(now()->subMinutes(5));
                $schedulerStatus = $schedulerHealthy ? 'ok' : 'stale';
            }
        } catch (\Throwable $e) {
            $schedulerStatus = 'invalid';
        }
    } else {
        $schedulerStatus = 'never';
    }

    return response()->json([
        'app' => $deploy['app'],
        'version' => $deploy['version'],
        'deployed_at' => $deploy['deployed_at'],
        'status' => 'online',
        'scheduler' => [
            'last_run_at' => $schedulerLastRunAt,
            'healthy' => $schedulerHealthy,
            'status' => $schedulerStatus,
        ],
    ]);
});

// Endpoint de versão com resumo do último deploy
$router->get('/versao', function () use ($router) {
    $deploy = socimobDeployInfo();
    return response()->json([
        'app' => $deploy['app'],
        'version' => $deploy['version'],
        'deployed_at' => $deploy['deployed_at'],
        'deploy_summary' => $deploy['deploy_summary'],
        'deployed_by' => $deploy['deployed_by'] ?? null,
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

// Webhook Chaves na Mão - o controller valida Basic Auth e resolve o tenant.
$router->get('/webhook/chaves-na-mao', 'App\Http\Controllers\ChavesNaMaoWebhookController@methodNotAllowed');
$router->post('/webhook/chaves-na-mao', 'App\Http\Controllers\ChavesNaMaoWebhookController@receive');

// Short link para WhatsApp (resolve tenant pelo domínio)
$router->group(['middleware' => 'resolve-tenant'], function () use ($router) {
    $router->get('/', 'App\Http\Controllers\Portal\SiteSeoController@home');
    $router->get('/sitemap.xml', 'App\Http\Controllers\Portal\SiteSeoController@sitemap');
    $router->get('/integracoes/chaves-na-mao/imoveis.xml', 'App\Http\Controllers\ChavesNaMaoXmlController@feed');
    $router->get('/integracoes/chaves-na-mao/imagens/{property}/{version}/{position}.jpg', 'App\Http\Controllers\ChavesNaMaoXmlController@image');
    $router->get('/w/{code}', 'App\Http\Controllers\ShortLinkController@redirectWhatsApp');
    $router->get('/hl/{code}', 'App\Http\Controllers\ShortLinkController@redirectHandoff');
    // Página do imóvel com metatags Open Graph dinâmicas (prévia rica ao compartilhar no WhatsApp)
    $router->get('/portal/imovel/{id}', 'App\Http\Controllers\Portal\PropertyShareController@show');
});

// Auth API routes
$router->group(['prefix' => 'api', 'middleware' => 'resolve-tenant'], function () use ($router) {
    // Short link para WhatsApp (resolve tenant pelo domínio)
    $router->get('/w/{code}', 'App\Http\Controllers\ShortLinkController@redirectWhatsApp');

    // ⚡ Rate limiting: 5 tentativas por minuto em login
    $router->post('/auth/login', ['middleware' => 'throttle:5,1', 'uses' => 'App\Http\Controllers\AuthController@login']);
    $router->post('/auth/logout', 'App\Http\Controllers\AuthController@logout');
    $router->get('/auth/me', ['middleware' => 'simple-auth', 'uses' => 'App\Http\Controllers\AuthController@me']);
    
    // 🔒 Sincronização de imóveis - PROTEGIDO POR TENANT
    // Só funciona se acessado pelo domínio correto (ex: exclusivalarimoveis.com)
    $router->get('/properties/sync', 'App\Http\Controllers\PropertyController@sync');

    // Proxy de mídia do Twilio (usado pelo chat) - relativo a /api
    $router->get('/conversas/media/proxy', ['middleware' => 'throttle:30,1', 'uses' => 'App\Http\Controllers\ConversasController@proxyMedia']);
    
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

$router->group(['prefix' => 'api/whatsapp/webhook/meta'], function () use ($router) {
    $router->get('/', 'App\Http\Controllers\WhatsApp\MetaWebhookController@verify');
    $router->post('/', 'App\Http\Controllers\WhatsApp\MetaWebhookController@receive');
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
$router->group(['prefix' => 'api/deploy', 'middleware' => ['throttle:5,1']], function () use ($router) {
    $router->post('/', 'App\Http\Controllers\DeployController@deploy');
    $router->get('/info', 'App\Http\Controllers\DeployController@info');
});

// Proxy público para mídias do Twilio (usado no chat via <img src>)
$router->get('/conversas/media/proxy', ['middleware' => 'throttle:30,1', 'uses' => 'App\Http\Controllers\ConversasController@proxyMedia']);

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
$router->get('/vistorias/publico/midias', 'App\Http\Controllers\PublicVistoriasController@linkInvalido');
$router->get('/vistorias/publico//midias', 'App\Http\Controllers\PublicVistoriasController@linkInvalido');
$router->get('/vistorias/publico/contestacao', 'App\Http\Controllers\PublicVistoriasController@linkInvalido');
$router->get('/vistorias/publico//contestacao', 'App\Http\Controllers\PublicVistoriasController@linkInvalido');
$router->get('/vistorias/publico/pdf', 'App\Http\Controllers\PublicVistoriasController@linkInvalido');
$router->get('/vistorias/publico//pdf', 'App\Http\Controllers\PublicVistoriasController@linkInvalido');
$router->get('/vistorias/publico/{token}/midias', 'App\Http\Controllers\PublicVistoriasController@midias');
$router->get('/vistorias/publico/{token}/contestacao', 'App\Http\Controllers\PublicVistoriasController@contestacao');
$router->post('/vistorias/publico/{token}/contestacao', 'App\Http\Controllers\PublicVistoriasController@enviarContestacao');
$router->get('/vistorias/publico/{token}/pdf', 'App\Http\Controllers\PublicVistoriasController@pdf');
$router->get('/api/vistorias/publico/midias', 'App\Http\Controllers\PublicVistoriasController@linkInvalido');
$router->get('/api/vistorias/publico//midias', 'App\Http\Controllers\PublicVistoriasController@linkInvalido');
$router->get('/api/vistorias/publico/contestacao', 'App\Http\Controllers\PublicVistoriasController@linkInvalido');
$router->get('/api/vistorias/publico//contestacao', 'App\Http\Controllers\PublicVistoriasController@linkInvalido');
$router->get('/api/vistorias/publico/pdf', 'App\Http\Controllers\PublicVistoriasController@linkInvalido');
$router->get('/api/vistorias/publico//pdf', 'App\Http\Controllers\PublicVistoriasController@linkInvalido');
$router->get('/api/vistorias/publico/{token}/midias', 'App\Http\Controllers\PublicVistoriasController@midias');
$router->get('/api/vistorias/publico/{token}/contestacao', 'App\Http\Controllers\PublicVistoriasController@contestacao');
$router->post('/api/vistorias/publico/{token}/contestacao', 'App\Http\Controllers\PublicVistoriasController@enviarContestacao');
$router->get('/api/vistorias/publico/{token}/pdf', 'App\Http\Controllers\PublicVistoriasController@pdf');

$router->group(['prefix' => 'api', 'middleware' => ['resolve-tenant', 'simple-auth']], function () use ($router) {

    // Auth
    $router->get('/auth/me', 'App\Http\Controllers\AuthController@me');
    $router->post('/auth/logout', 'App\Http\Controllers\AuthController@logout');
    $router->post('/auth/change-password-first-access', 'App\Http\Controllers\AuthController@changePasswordFirstAccess');

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
    $router->get('/dashboard/chart/atendimentos-por-corretor', 'App\Http\Controllers\DashboardController@chartAtendimentosPorCorretor');
    $router->get('/dashboard/chart/captacoes-por-corretor', 'App\Http\Controllers\DashboardController@chartCaptacoesPorCorretor');
    $router->get('/dashboard/chart/acessos-portal-por-dia', 'App\Http\Controllers\DashboardController@chartAcessosPortalPorDia');
    $router->get('/dashboard/atividades', 'App\Http\Controllers\DashboardController@atividades');
    $router->get('/dashboard/timeline', 'App\Http\Controllers\DashboardController@timeline');

    // Vistorias
    $router->get('/vistorias/meta', 'App\Http\Controllers\VistoriasController@meta');
    $router->post('/vistorias/participantes', 'App\Http\Controllers\VistoriasController@storeParticipante');
    $router->get('/vistorias', 'App\Http\Controllers\VistoriasController@index');
    $router->post('/vistorias', 'App\Http\Controllers\VistoriasController@store');
    $router->get('/vistorias/export', 'App\Http\Controllers\VistoriasController@export');
    $router->post('/vistorias/{id}/iniciar', 'App\Http\Controllers\VistoriasController@iniciar');
    $router->post('/vistorias/{id}/finalizar', 'App\Http\Controllers\VistoriasController@finalizar');
    $router->post('/vistorias/{id}/cancelar', 'App\Http\Controllers\VistoriasController@cancelar');
    $router->post('/vistorias/{id}/gerar-pdf', 'App\Http\Controllers\VistoriasController@gerarPdf');
    $router->get('/vistorias/{id}/download-pdf', 'App\Http\Controllers\VistoriasController@downloadPdf');
    $router->put('/vistorias/{id}', 'App\Http\Controllers\VistoriasController@update');
    $router->delete('/vistorias/{id}', 'App\Http\Controllers\VistoriasController@destroy');
    $router->get('/vistorias/solicitacoes', 'App\Http\Controllers\VistoriaSolicitacoesController@index');
    $router->post('/vistorias/solicitacoes', 'App\Http\Controllers\VistoriaSolicitacoesController@store');
    $router->post('/vistorias/solicitacoes/{solicitacaoId}/converter', 'App\Http\Controllers\VistoriasController@converterFromSolicitacao');
    $router->put('/vistorias/solicitacoes/{id}/status', 'App\Http\Controllers\VistoriaSolicitacoesController@updateStatus');
    $router->get('/vistorias/contestacoes', 'App\Http\Controllers\VistoriaContestacoesController@index');
    $router->post('/vistorias/contestacoes', 'App\Http\Controllers\VistoriaContestacoesController@store');
    $router->get('/vistorias/contestacoes/{id}', 'App\Http\Controllers\VistoriaContestacoesController@show');
    $router->put('/vistorias/contestacoes/{id}/status', 'App\Http\Controllers\VistoriaContestacoesController@updateStatus');
    $router->delete('/vistorias/contestacoes/{id}', 'App\Http\Controllers\VistoriaContestacoesController@destroy');

    $router->get('/vistorias/{id}/ambientes', 'App\Http\Controllers\VistoriaOperacionalController@ambientes');
    $router->post('/vistorias/{id}/ambientes', 'App\Http\Controllers\VistoriaOperacionalController@storeAmbiente');
    $router->put('/vistorias/{id}/ambientes/{ambienteId}', 'App\Http\Controllers\VistoriaOperacionalController@updateAmbiente');
    $router->delete('/vistorias/{id}/ambientes/{ambienteId}', 'App\Http\Controllers\VistoriaOperacionalController@destroyAmbiente');
    $router->post('/vistorias/{id}/ambientes/{ambienteId}/itens', 'App\Http\Controllers\VistoriaOperacionalController@storeItem');
    $router->put('/vistorias/{id}/itens/{itemId}', 'App\Http\Controllers\VistoriaOperacionalController@updateItem');
    $router->delete('/vistorias/{id}/itens/{itemId}', 'App\Http\Controllers\VistoriaOperacionalController@destroyItem');
    $router->post('/vistorias/{id}/inconformidades', 'App\Http\Controllers\VistoriaOperacionalController@storeInconformidade');
    $router->put('/vistorias/{id}/inconformidades/{inconformidadeId}', 'App\Http\Controllers\VistoriaOperacionalController@updateInconformidade');
    $router->delete('/vistorias/{id}/inconformidades/{inconformidadeId}', 'App\Http\Controllers\VistoriaOperacionalController@destroyInconformidade');
    $router->post('/vistorias/{id}/midias', 'App\Http\Controllers\VistoriaOperacionalController@storeMidia');
    $router->delete('/vistorias/{id}/midias/{midiaId}', 'App\Http\Controllers\VistoriaOperacionalController@destroyMidia');
    $router->post('/vistorias/{id}/chaves', 'App\Http\Controllers\VistoriaOperacionalController@storeChave');
    $router->put('/vistorias/{id}/chaves/{chaveId}', 'App\Http\Controllers\VistoriaOperacionalController@updateChave');
    $router->delete('/vistorias/{id}/chaves/{chaveId}', 'App\Http\Controllers\VistoriaOperacionalController@destroyChave');
    $router->post('/vistorias/{id}/medidores', 'App\Http\Controllers\VistoriaOperacionalController@storeMedidor');
    $router->put('/vistorias/{id}/medidores/{medidorId}', 'App\Http\Controllers\VistoriaOperacionalController@updateMedidor');
    $router->delete('/vistorias/{id}/medidores/{medidorId}', 'App\Http\Controllers\VistoriaOperacionalController@destroyMedidor');
    $router->post('/vistorias/{id}/assinaturas', 'App\Http\Controllers\VistoriaOperacionalController@assinar');
    $router->get('/vistorias/{id}/contestacoes', 'App\Http\Controllers\VistoriaOperacionalController@contestacoes');
    $router->post('/vistorias/{id}/contestacoes/{contestacaoId}/responder', 'App\Http\Controllers\VistoriaOperacionalController@responderContestacao');

    // Fotos da vistoria (mesmo auth/tenant das demais rotas de vistoria — uso em campo e app)
    $router->get('/vistorias/{vistoriaId}/fotos', 'App\Http\Controllers\VistoriaFotosApiController@index');
    $router->post('/vistorias/{vistoriaId}/fotos', 'App\Http\Controllers\VistoriaFotosApiController@store');
    $router->patch('/vistorias/{vistoriaId}/fotos/{fotoId}', 'App\Http\Controllers\VistoriaFotosApiController@update');
    $router->delete('/vistorias/{vistoriaId}/fotos/{fotoId}', 'App\Http\Controllers\VistoriaFotosApiController@destroy');
    $router->get('/vistorias/{vistoriaId}/comentarios', 'App\Http\Controllers\VistoriaComentariosApiController@index');
    $router->post('/vistorias/{vistoriaId}/comentarios', 'App\Http\Controllers\VistoriaComentariosApiController@store');
    $router->put('/vistorias/{vistoriaId}/comentarios/{comentarioId}', 'App\Http\Controllers\VistoriaComentariosApiController@update');
    $router->delete('/vistorias/{vistoriaId}/comentarios/{comentarioId}', 'App\Http\Controllers\VistoriaComentariosApiController@destroy');

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
    $router->get('/imoveis/captadores', 'App\Http\Controllers\PropertyController@captadores');
    $router->post('/imoveis', 'App\Http\Controllers\PropertyController@store');
    $router->get('/imoveis/{id}/documentos', 'App\Http\Controllers\PropertyController@listDocuments');
    $router->post('/imoveis/{id}/documentos', 'App\Http\Controllers\PropertyController@uploadDocument');
    $router->delete('/imoveis/{id}/documentos/{documentoId}', 'App\Http\Controllers\PropertyController@deleteDocument');
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
        $router->get('/conversas/disparar-atendimentos/dias', 'App\Http\Controllers\Admin\ConversasController@diasElegiveisDisparo');
        $router->post('/conversas/disparar-atendimentos', 'App\Http\Controllers\Admin\ConversasController@dispararAtendimentos');
        $router->post('/conversas/fila/pegar-proxima', 'App\Http\Controllers\Admin\ConversasController@pegarProxima');
        $router->post('/conversas/{id}/devolver-fila', 'App\Http\Controllers\Admin\ConversasController@devolverParaFila');
        $router->post('/conversas/{id}/atribuir', 'App\Http\Controllers\Admin\ConversasController@atribuirCorretor');
        $router->delete('/conversas/{id}', 'App\Http\Controllers\ConversasController@destroy');
        $router->get('/conversas/tempo-real', 'App\Http\Controllers\ConversasController@tempoReal');
        $router->get('/conversas/por-telefone/{telefone}', 'App\Http\Controllers\ConversasController@porTelefone');
        $router->post('/conversas/{id}/repescagem/sugerir', 'App\Http\Controllers\Admin\ConversasController@sugerirRepescagemConversa');
        $router->post('/conversas/{id}/repescagem/enviar', 'App\Http\Controllers\Admin\ConversasController@enviarRepescagemConversa');
        $router->get('/conversas/{id}', 'App\Http\Controllers\Admin\ConversasController@show');
        $router->get('/conversas/{id}/mensagens', 'App\Http\Controllers\Admin\ConversasController@mensagens');
        $router->post('/conversas/{id}/mensagens', 'App\Http\Controllers\Admin\ConversasController@enviarMensagem');
        $router->post('/conversas/{id}/mensagens/media', 'App\Http\Controllers\Admin\ConversasController@enviarMidia');
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
    $router->post('/crm/clientes/{id}/assume', 'App\Http\Controllers\CRMController@assume');
    $router->post('/crm/clientes/{id}/assign', 'App\Http\Controllers\CRMController@assign');

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

    // WhatsApp Cloud API
    $router->post('/whatsapp/messages/text', 'App\Http\Controllers\WhatsApp\WhatsAppMessageController@sendText');
    $router->post('/whatsapp/messages/template', 'App\Http\Controllers\WhatsApp\WhatsAppMessageController@sendTemplate');
    $router->post('/whatsapp/messages/media', 'App\Http\Controllers\WhatsApp\WhatsAppMessageController@sendMedia');
    $router->get('/whatsapp/messages/{id}', 'App\Http\Controllers\WhatsApp\WhatsAppMessageController@show');
    $router->get('/whatsapp/conversations/{id}', 'App\Http\Controllers\WhatsApp\WhatsAppConversationController@show');
    $router->get('/whatsapp/templates', 'App\Http\Controllers\WhatsApp\WhatsAppTemplateController@index');
    $router->post('/whatsapp/templates/sync', 'App\Http\Controllers\WhatsApp\WhatsAppTemplateController@sync');
    $router->post('/whatsapp/tenants/{tenantId}/connect', 'App\Http\Controllers\WhatsApp\WhatsAppTenantConnectionController@connect');
});

// Additional route files (previously loaded via bootstrap/app.php then() callback)
require __DIR__ . '/admin.php';
require __DIR__ . '/client-portal.php';
require __DIR__ . '/subscriptions.php';
require __DIR__ . '/themes.php';
require __DIR__ . '/domains.php';
require __DIR__ . '/portal.php';
