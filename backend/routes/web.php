<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
*/

// Health check
$router->get('/', function () use ($router) {
    return response()->json([
        'app' => 'Exclusiva Lar CRM',
        'version' => $router->app->version(),
        'status' => 'online'
    ]);
});

// Auth API routes
$router->group(['prefix' => 'api'], function () use ($router) {
    $router->post('/auth/login', 'AuthController@login');
    $router->post('/auth/logout', 'AuthController@logout');
    $router->get('/auth/me', ['middleware' => 'auth', 'uses' => 'AuthController@me']);
    
    // Dashboard routes
    $router->get('/dashboard/stats', 'DashboardController@stats');
    $router->get('/dashboard/atividades', 'DashboardController@atividades');
});

// Landing Page Pública
$router->get('/imoveis', function () {
    $path = base_path('public/imoveis.html');
    if (file_exists($path)) {
        return response(file_get_contents($path))
            ->header('Content-Type', 'text/html');
    }
    return response('Landing page não encontrada. Path: ' . $path, 404);
});

// Database test
$router->get('/db-test', function () {
    try {
        // Debug: mostrar configuração
        $config = config('database.connections.pgsql');
        
        $pdo = app('db')->connection()->getPdo();
        $result = app('db')->select('SELECT COUNT(*) as count FROM users');
        return response()->json([
            'database' => 'connected',
            'users_count' => $result[0]->count,
            'debug_host' => $config['host'] ?? 'N/A',
            'debug_database' => $config['database'] ?? 'N/A',
            'has_database_url' => !empty(env('DATABASE_URL'))
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'database' => 'error',
            'message' => $e->getMessage()
        ], 500);
    }
});

// Super Admin API routes
require __DIR__ . '/super-admin.php';

// Teste de leads (sem autenticação)
$router->get('/test-leads', function () {
    try {
        $leads = app('db')->select('SELECT id, nome, telefone, email, origem, created_at FROM leads ORDER BY created_at DESC LIMIT 5');
        return response()->json([
            'total' => count($leads),
            'leads' => $leads
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage()
        ], 500);
    }
});

// Teste de stats (sem autenticação)
$router->get('/test-stats', function () {
    try {
        $stats = [
            'leads_total' => app('db')->table('leads')->count(),
            'leads_novos' => app('db')->table('leads')->where('status', 'novo')->count(),
            'conversas_ativas' => app('db')->table('conversas')->where('status', 'ativa')->count(),
            'corretores_ativos' => app('db')->table('users')->where('tipo', 'corretor')->where('ativo', true)->count()
        ];
        return response()->json($stats);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage()
        ], 500);
    }
});

// Stats completo para o dashboard (sem auth temporariamente)
$router->get('/api-public/dashboard/stats', function () {
    try {
        $db = app('db');
        $stats = [
            'leads' => [
                'total' => $db->table('leads')->count(),
                'novos' => $db->table('leads')->where('status', 'novo')->count(),
                'em_atendimento' => $db->table('leads')->where('status', 'em_atendimento')->count(),
                'qualificados' => $db->table('leads')->where('status', 'qualificado')->count(),
                'fechados_mes' => $db->table('leads')->where('status', 'fechado')->whereRaw('EXTRACT(MONTH FROM updated_at) = ?', [date('m')])->count()
            ],
            'conversas' => [
                'ativas' => $db->table('conversas')->where('status', 'ativa')->count(),
                'hoje' => $db->table('conversas')->whereDate('iniciada_em', date('Y-m-d'))->count(),
                'aguardando' => $db->table('conversas')->where('status', 'aguardando_corretor')->count()
            ],
            'corretores' => [
                'total' => $db->table('users')->where('tipo', 'corretor')->where('ativo', true)->count(),
                'online' => 0
            ]
        ];
        return response()->json(['success' => true, 'data' => $stats]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// Atividades recentes (sem auth temporariamente)
$router->get('/api-public/dashboard/atividades', function () {
    try {
        $atividades = app('db')->table('conversas')
            ->leftJoin('leads', 'conversas.lead_id', '=', 'leads.id')
            ->select('conversas.id as conversa_id', 'conversas.lead_id', 'conversas.telefone', 'conversas.iniciada_em', 'leads.nome as lead_nome')
            ->orderBy('conversas.iniciada_em', 'desc')
            ->limit(10)
            ->get()
            ->map(function($conv) {
                return [
                    'tipo' => 'nova_conversa',
                    'descricao' => 'Nova conversa iniciada com ' . ($conv->lead_nome ?? $conv->telefone),
                    'timestamp' => $conv->iniciada_em,
                    'data' => ['conversa_id' => $conv->conversa_id, 'lead_id' => $conv->lead_id]
                ];
            });
        return response()->json(['success' => true, 'data' => $atividades]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// Leads públicos temporários
$router->get('/api-public/leads', function () {
    try {
        $leads = app('db')->table('leads')
            ->select('id', 'nome', 'telefone', 'email', 'origem', 'status', 'created_at', 'updated_at')
            ->orderBy('created_at', 'desc')
            ->get();
        return response()->json(['success' => true, 'data' => $leads]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// Conversas públicas temporárias
$router->get('/api-public/conversas', function () {
    try {
        $conversas = app('db')->table('conversas')
            ->leftJoin('leads', 'conversas.lead_id', '=', 'leads.id')
            ->select('conversas.*', 'leads.nome as lead_nome', 'leads.email as lead_email')
            ->orderBy('conversas.iniciada_em', 'desc')
            ->get();
        return response()->json(['success' => true, 'data' => $conversas]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// Usuários de teste (TEMPORÁRIO - REMOVER DEPOIS)
$router->get('/api-public/test-users', function () {
    try {
        $users = app('db')->table('users')
            ->select('id', 'nome', 'email', 'tipo', 'ativo', 'created_at')
            ->get();
        return response()->json(['success' => true, 'data' => $users]);
    } catch (\Exception $e) {
        return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
    }
});

// Debug conversa específica (TEMPORÁRIO)
$router->get('/debug/conversa/{id}', function ($id) {
    try {
        $db = app('db');
        
        $conversa = $db->table('conversas')->where('id', $id)->first();
        if (!$conversa) {
            return response()->json(['error' => 'Conversa não encontrada', 'id' => $id], 404);
        }
        
        $mensagens = $db->table('mensagens')->where('conversa_id', $id)->get();
        
        return response()->json([
            'conversa' => $conversa,
            'total_mensagens' => count($mensagens),
            'mensagens_sample' => $mensagens->take(3)
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});

// Debug sync único imóvel (TEMPORÁRIO)
$router->get('/debug/property-sync/{codigo}', function ($codigo) {
    try {
        $syncService = app(App\Services\PropertySyncService::class);
        
        // Testar busca de dados
        $reflection = new ReflectionClass($syncService);
        $method = $reflection->getMethod('callApi');
        $method->setAccessible(true);
        
        $response = $method->invoke($syncService, "/dados/{$codigo}");
        $imovel = $response['resultSet'] ?? null;
        
        if (!$imovel) {
            return response()->json(['error' => 'Imóvel não encontrado na API'], 404);
        }
        
        // Testar mapeamento
        $mapMethod = $reflection->getMethod('mapPropertyData');
        $mapMethod->setAccessible(true);
        
        $mapped = $mapMethod->invoke($syncService, $imovel);
        
        return response()->json([
            'codigo' => $codigo,
            'api_data' => $imovel,
            'mapped_data' => $mapped
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => array_slice($e->getTrace(), 0, 5)
        ], 500);
    }
});

// Debug lista da API (TEMPORÁRIO)
$router->get('/debug/property-list/{page?}', function ($page = 1) {
    try {
        $syncService = app(App\Services\PropertySyncService::class);
        
        $reflection = new ReflectionClass($syncService);
        $method = $reflection->getMethod('callApi');
        $method->setAccessible(true);
        
        $response = $method->invoke($syncService, "/lista?page={$page}");
        $resultSet = $response['resultSet'] ?? [];
        
        return response()->json([
            'page' => $page,
            'total_pages' => $resultSet['total_pages'] ?? 0,
            'total_items' => $resultSet['total_items'] ?? 0,
            'per_page' => $resultSet['per_page'] ?? 0,
            'sample_codes' => collect($resultSet['data'] ?? [])->pluck('codigoImovel')->take(10)
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ], 500);
    }
});

// Debug total de imóveis da API (TEMPORÁRIO)
$router->get('/debug/api-total', function () {
    try {
        define('API_TOKEN', '$2y$10$Lcn1ct.wEfBonZldcjuVQ.pD5p8gBRNrPlHjVwruaG5HAui2XCG9O');
        define('API_BASE', 'https://www.exclusivalarimoveis.com.br/api/v1/app/imovel');
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => API_BASE . '/lista?status=ativo&page=1&per_page=20',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . API_TOKEN]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if (!$response) {
            return response()->json(['error' => 'Falha ao conectar API'], 500);
        }
        
        $data = json_decode($response, true);
        $resultSet = $data['resultSet'] ?? [];
        
        return response()->json([
            'http_code' => $httpCode,
            'status' => $data['status'] ?? false,
            'total_items' => $resultSet['total_items'] ?? 0,
            'total_pages' => $resultSet['total_pages'] ?? 0,
            'per_page' => $resultSet['per_page'] ?? 0,
            'sample_codes' => array_column($resultSet['data'] ?? [], 'codigoImovel')
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage()
        ], 500);
    }
});

// Debug insert único imóvel (TEMPORÁRIO)
$router->get('/debug/insert-property/{codigo}', function ($codigo) {
    try {
        $syncService = app(App\Services\PropertySyncService::class);
        
        // Buscar dados da API
        $reflection = new ReflectionClass($syncService);
        $method = $reflection->getMethod('callApi');
        $method->setAccessible(true);
        
        $response = $method->invoke($syncService, "/dados/{$codigo}");
        
        if (!isset($response['resultSet'])) {
            return response()->json(['error' => 'Imóvel não encontrado na API'], 404);
        }
        
        $imovel = $response['resultSet'];
        
        // Mapear dados
        $mapMethod = $reflection->getMethod('mapPropertyData');
        $mapMethod->setAccessible(true);
        $mapped = $mapMethod->invoke($syncService, $imovel);
        
        // Verificar se já existe
        $existing = \App\Models\Property::where('codigo_imovel', $codigo)->first();
        
        if ($existing) {
            $result = $existing->update($mapped);
            return response()->json([
                'action' => 'updated',
                'success' => $result,
                'property_id' => $existing->id,
                'data' => $mapped
            ]);
        } else {
            try {
                $property = \App\Models\Property::create($mapped);
                return response()->json([
                    'action' => 'created',
                    'success' => true,
                    'property_id' => $property->id,
                    'data' => $mapped
                ]);
            } catch (\Exception $createError) {
                return response()->json([
                    'action' => 'create_failed',
                    'error' => $createError->getMessage(),
                    'code' => $createError->getCode(),
                    'data' => $mapped
                ], 500);
            }
        }
        
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => array_slice($e->getTrace(), 0, 3)
        ], 500);
    }
});

// Debug configurações (TEMPORÁRIO)
$router->get('/debug/config', function () {
        return response()->json([
            'app_env' => env('APP_ENV'),
            'app_debug' => env('APP_DEBUG'),
            'has_exclusiva_token' => !empty(env('EXCLUSIVA_API_TOKEN')),
            'token_preview' => env('EXCLUSIVA_API_TOKEN') ? substr(env('EXCLUSIVA_API_TOKEN'), 0, 10) . '...' : 'NOT SET',
            'has_openai_key' => !empty(env('OPENAI_API_KEY')),
            'has_twilio_sid' => !empty(env('TWILIO_ACCOUNT_SID')),
            'has_database_url' => !empty(env('DATABASE_URL'))
        ]);
    });

    // SQL direto (TEMPORÁRIO)
    $router->get('/debug/sql/{sql}', function ($sql) {
        try {
            $db = app('db');
            $decodedSql = urldecode($sql);
            
            if (strpos(strtoupper($decodedSql), 'SELECT') === 0) {
                $result = $db->select($decodedSql);
                return response()->json([
                    'success' => true,
                    'sql' => $decodedSql,
                    'result' => $result
                ]);
            } else {
                $result = $db->statement($decodedSql);
                return response()->json([
                    'success' => true,
                    'sql' => $decodedSql,
                    'affected' => $result
                ]);
            }
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'sql' => $decodedSql ?? $sql
            ], 500);
        }
    });

// Contar imóveis (TEMPORÁRIO)
$router->get('/debug/count-properties', function () {
    try {
        $db = app('db');
        $total = $db->table('imo_properties')->count();
        $ativos = $db->table('imo_properties')->where('active', 1)->where('exibir_imovel', 1)->count();
        
        return response()->json([
            'total' => $total,
            'ativos' => $ativos,
            'success' => true
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'success' => false
        ], 500);
    }
});

// Converter descrições para HTML (TEMPORÁRIO)
$router->get('/debug/convert-descriptions-html', function () {
    try {
        set_time_limit(300);
        
        // Função de formatação
        $formatHtml = function($text) {
            if (empty($text)) return null;
            
            // Remove tags <p> e </p> se existirem
            $text = preg_replace('/<\/?p>/', '', $text);
            
            // Decodifica HTML entities (&amp; -> &, &lt; -> <, &gt; -> >, &ndash; -> –)
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            
            $text = trim($text);
            $text = str_replace(["\r\n", "\r", "\n"], "|||BR|||", $text);
            
            $html = '';
            $lines = explode("|||BR|||", $text);
            $inList = false;
            
            foreach ($lines as $line) {
                $line = trim($line);
                
                if (empty($line)) {
                    if ($inList) {
                        $html .= '</ul>';
                        $inList = false;
                    }
                    $html .= '<br>';
                    continue;
                }
                
                // Títulos com emojis
                if (preg_match('/^[🏡✨🌟💰🚗📄🎯🔑⭐🎉]/', $line)) {
                    if ($inList) {
                        $html .= '</ul>';
                        $inList = false;
                    }
                    $html .= '<h3>' . htmlspecialchars($line) . '</h3>';
                }
                // Itens de lista
                elseif (preg_match('/^[\-\*•]/', $line) || preg_match('/^\*\*/', $line)) {
                    if (!$inList) {
                        $html .= '<ul>';
                        $inList = true;
                    }
                    $item = preg_replace('/^[\-\*•]\s*/', '', $line);
                    // Converte **texto:** para <strong>texto:</strong> ANTES de escapar
                    $item = preg_replace('/\*\*([^:]+):\*\*/', '<strong>$1:</strong>', $item);
                    // Escapa o resto mas preserva as tags <strong>
                    $item = str_replace(['<strong>', '</strong>'], ['|||STRONG|||', '|||/STRONG|||'], $item);
                    $item = htmlspecialchars($item, ENT_NOQUOTES);
                    $item = str_replace(['|||STRONG|||', '|||/STRONG|||'], ['<strong>', '</strong>'], $item);
                    $html .= '<li>' . $item . '</li>';
                }
                // Linha normal
                else {
                    if ($inList) {
                        $html .= '</ul>';
                        $inList = false;
                    }
                    // Converte **texto** para <strong>texto</strong> ANTES de escapar
                    $line = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $line);
                    $line = str_replace(['<strong>', '</strong>'], ['|||STRONG|||', '|||/STRONG|||'], $line);
                    $line = htmlspecialchars($line, ENT_NOQUOTES);
                    $line = str_replace(['|||STRONG|||', '|||/STRONG|||'], ['<strong>', '</strong>'], $line);
                    $html .= '<p>' . $line . '</p>';
                }
            }
            
            if ($inList) {
                $html .= '</ul>';
            }
            
            return $html;
        };
        
        $db = app('db');
        $properties = $db->table('imo_properties')
            ->whereNotNull('descricao')
            ->where('descricao', '!=', '')
            ->select('id', 'codigo_imovel', 'descricao')
            ->get();
        
        $updated = 0;
        $skipped = 0;
        $samples = [];
        
        foreach ($properties as $prop) {
            // Sempre reconverter para aplicar nova lógica de decodificação
            $htmlDesc = $formatHtml($prop->descricao);
            
            if ($htmlDesc) {
                $db->table('imo_properties')
                    ->where('id', $prop->id)
                    ->update(['descricao' => $htmlDesc]);
                
                $updated++;
                
                // Guardar amostra dos primeiros 3
                if (count($samples) < 3) {
                    $samples[] = [
                        'codigo' => $prop->codigo_imovel,
                        'original' => substr($prop->descricao, 0, 200),
                        'html' => substr($htmlDesc, 0, 300)
                    ];
                }
            }
        }
        
        return response()->json([
            'success' => true,
            'total' => count($properties),
            'updated' => $updated,
            'skipped' => $skipped,
            'samples' => $samples
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
});// Run migrations (TEMPORÁRIO)
$router->get('/debug/migrate', function () {
    try {
        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        $output = \Illuminate\Support\Facades\Artisan::output();
        
        return response()->json([
            'success' => true,
            'message' => 'Migrations executadas com sucesso',
            'output' => $output
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ], 500);
    }
});

// Adicionar campos manualmente (TEMPORÁRIO)
$router->get('/debug/add-columns', function () {
    try {
        $db = app('db');
        $results = [];
        
        // Listar todas as colunas necessárias
        $columns = [
            'valor_iptu' => 'ALTER TABLE imo_properties ADD COLUMN valor_iptu DECIMAL(10,2) DEFAULT 0',
            'valor_condominio' => 'ALTER TABLE imo_properties ADD COLUMN valor_condominio DECIMAL(10,2) DEFAULT 0',
            'logradouro' => 'ALTER TABLE imo_properties ADD COLUMN logradouro VARCHAR(255)',
            'numero' => 'ALTER TABLE imo_properties ADD COLUMN numero VARCHAR(50)',
            'complemento' => 'ALTER TABLE imo_properties ADD COLUMN complemento VARCHAR(255)',
            'cep' => 'ALTER TABLE imo_properties ADD COLUMN cep VARCHAR(20)',
            'area_terreno' => 'ALTER TABLE imo_properties ADD COLUMN area_terreno DECIMAL(10,2)',
            'caracteristicas' => 'ALTER TABLE imo_properties ADD COLUMN caracteristicas JSON',
            'imagens' => 'ALTER TABLE imo_properties ADD COLUMN imagens JSON',
            'exclusividade' => 'ALTER TABLE imo_properties ADD COLUMN exclusividade BOOLEAN DEFAULT FALSE',
            'api_data' => 'ALTER TABLE imo_properties ADD COLUMN api_data JSON'
        ];
        
        foreach ($columns as $columnName => $sql) {
            try {
                // Verificar se coluna já existe
                $exists = $db->select("SELECT column_name FROM information_schema.columns WHERE table_name='imo_properties' AND column_name=?", [$columnName]);
                
                if (empty($exists)) {
                    $db->statement($sql);
                    $results[$columnName] = 'ADDED';
                } else {
                    $results[$columnName] = 'EXISTS';
                }
            } catch (\Exception $e) {
                $results[$columnName] = 'ERROR: ' . $e->getMessage();
            }
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Campos processados',
            'results' => $results
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ], 500);
    }
});

// ===========================
// ROTAS PÚBLICAS (SEM AUTENTICAÇÃO)
// ===========================
$router->group(['prefix' => 'api/properties'], function () use ($router) {
    // Rotas específicas ANTES das dinâmicas
    $router->get('/sync', 'PropertyController@sync');
    
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
    $router->post('/whatsapp', 'WebhookController@receive');
    $router->post('/whatsapp/status', 'WebhookController@status');
});

// ===========================
// Autenticação (sem middleware)
// ===========================
$router->group(['prefix' => 'api/auth'], function () use ($router) {
    $router->post('/login', 'AuthController@login');
});

// ===========================
// Rotas protegidas (TEMPORARIAMENTE SEM AUTH - PARA DEBUG)
// ===========================
$router->group(['prefix' => 'api'], function () use ($router) {

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
    
    // Leads
    $router->get('/leads', 'LeadsController@index');
    $router->get('/leads/stats', 'LeadsController@stats');
    $router->get('/leads/{id}', 'LeadsController@show');
    $router->put('/leads/{id}', 'LeadsController@update');
    $router->patch('/leads/{id}/state', 'LeadsController@updateState');
    $router->patch('/leads/{id}/status', 'LeadsController@updateStatus');
    $router->delete('/leads/{id}', 'LeadsController@destroy');
    $router->delete('/leads', 'LeadsController@bulkDestroy');
    $router->post('/leads/{id}/diagnostico', 'LeadsController@diagnostico');

    // Conversas
    $router->get('/conversas', 'ConversasController@index');
    $router->get('/conversas/tempo-real', 'ConversasController@tempoReal');
    $router->get('/conversas/por-telefone/{telefone}', 'ConversasController@porTelefone');
    $router->get('/conversas/{id}', 'ConversasController@show');
    $router->post('/conversas/{id}/mensagens', 'ConversasController@sendMessage');
    
    // Imóveis - Debug completo
    $router->get('/imoveis/detalhes/{codigo}', 'PropertyController@detalhesCompletos');
    
    // Debug: ver contexto que IA recebe
    $router->get('/debug/ia-context', function () {
        $properties = app('db')->table('imo_properties')
            ->where('active', 1)
            ->where('exibir_imovel', 1)
            ->whereNotNull('imagens')
            ->select('codigo_imovel', 'tipo_imovel', 'bairro', 'cidade', 'valor_venda', 'dormitorios', 'suites', 'imagens')
            ->limit(3)
            ->get()
            ->toArray();
        
        $context = "=== IMÓVEIS PARA IA ===\n";
        foreach ($properties as $prop) {
            $imagens = $prop->imagens ?? null;
            
            // Laravel casting
            if (is_string($imagens)) {
                $imagens = json_decode($imagens, true);
            }
            
            $imageUrls = [];
            if (is_array($imagens)) {
                foreach ($imagens as $img) {
                    if (is_string($img)) {
                        $imageUrls[] = $img;
                    } elseif (is_array($img) && isset($img['url'])) {
                        $imageUrls[] = $img['url'];
                    }
                }
            }
            
            $context .= sprintf(
                "- Código: %s | %s | %s\n",
                $prop->codigo_imovel,
                $prop->tipo_imovel,
                $prop->bairro
            );
            
            if (!empty($imageUrls)) {
                $context .= "  📸 Fotos:\n  " . implode("\n  ", array_slice($imageUrls, 0, 3)) . "\n";
            } else {
                $context .= "  ⚠️ SEM FOTOS\n";
            }
        }
        
        return response()->json([
            'success' => true,
            'raw_properties' => $properties,
            'formatted_context' => $context,
            'teste' => [
                'tipo_imagens' => gettype($properties[0]->imagens ?? null),
                'eh_string' => is_string($properties[0]->imagens ?? null),
                'eh_array' => is_array($properties[0]->imagens ?? null)
            ]
        ]);
    });

    // Configurações do CRM / IA
    $router->get('/settings', 'SettingsController@index');
    $router->put('/settings', 'SettingsController@update');
});

// Trigger sync remoto (debug)
$router->get('/debug/trigger-sync', function () {
    try {
        set_time_limit(600); // 10 minutos
        ini_set('memory_limit', '512M');
        
        $syncWorker = base_path('sync_worker.php');
        
        if (!file_exists($syncWorker)) {
            return response()->json([
                'success' => false,
                'error' => 'sync_worker.php not found'
            ], 404);
        }
        
        // Executar sync worker
        ob_start();
        include $syncWorker;
        $output = ob_get_clean();
        
        return response()->json([
            'success' => true,
            'message' => 'Sync completed',
            'output' => $output
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

// Forçar FASE 2 (atualizar detalhes de todos os imóveis)
$router->get('/debug/force-fase2', function () use ($router) {
    try {
        set_time_limit(600);
        ini_set('memory_limit', '512M');
        
        $db = app('db');
        
        // Parâmetros
        $limit = $router->app->request->query('limit', 50); // Processar 50 por vez
        $offset = $router->app->request->query('offset', 0);
        
        // Buscar lote de imóveis
        $imoveis = $db->table('imo_properties')
            ->select('id', 'codigo_imovel', 'referencia_imovel')
            ->orderBy('id')
            ->skip($offset)
            ->take($limit)
            ->get();
        
        $total = count($imoveis);
        $updated = 0;
        $errors = [];
        $skipped = [];
        
        define('API_TOKEN', '$2y$10$Lcn1ct.wEfBonZldcjuVQ.pD5p8gBRNrPlHjVwruaG5HAui2XCG9O');
        define('API_BASE', 'https://www.exclusivalarimoveis.com.br/api/v1/app/imovel');
        
        // Função de formatação (mesma do sync_worker)
        $formatHtml = function($text) {
            if (empty($text)) return null;
            $text = preg_replace('/<\/?p>/', '', $text);
            $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $text = trim($text);
            $text = str_replace(["\r\n", "\r", "\n"], "|||BR|||", $text);
            $html = '';
            $lines = explode("|||BR|||", $text);
            $inList = false;
            
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) {
                    if ($inList) { $html .= '</ul>'; $inList = false; }
                    $html .= '<br>';
                    continue;
                }
                if (preg_match('/^[🏡✨🌟💰🚗📄🎯🔑⭐🎉]/', $line)) {
                    if ($inList) { $html .= '</ul>'; $inList = false; }
                    $html .= '<h3>' . htmlspecialchars($line) . '</h3>';
                } elseif (preg_match('/^[\-\*•]/', $line) || preg_match('/^\*\*/', $line)) {
                    if (!$inList) { $html .= '<ul>'; $inList = true; }
                    $item = preg_replace('/^[\-\*•]\s*/', '', $line);
                    $item = preg_replace('/\*\*([^:]+):\*\*/', '<strong>$1:</strong>', $item);
                    $item = str_replace(['<strong>', '</strong>'], ['|||STRONG|||', '|||/STRONG|||'], $item);
                    $item = htmlspecialchars($item, ENT_NOQUOTES);
                    $item = str_replace(['|||STRONG|||', '|||/STRONG|||'], ['<strong>', '</strong>'], $item);
                    $html .= '<li>' . $item . '</li>';
                } else {
                    if ($inList) { $html .= '</ul>'; $inList = false; }
                    $line = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $line);
                    $line = str_replace(['<strong>', '</strong>'], ['|||STRONG|||', '|||/STRONG|||'], $line);
                    $line = htmlspecialchars($line, ENT_NOQUOTES);
                    $line = str_replace(['|||STRONG|||', '|||/STRONG|||'], ['<strong>', '</strong>'], $line);
                    $html .= '<p>' . $line . '</p>';
                }
            }
            if ($inList) $html .= '</ul>';
            return $html;
        };
        
        foreach ($imoveis as $imovel) {
            try {
                // Buscar detalhes da API
                $url = API_BASE . '/dados/' . $imovel->codigo_imovel;
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Accept: application/json',
                    'token: ' . API_TOKEN,
                    'User-Agent: Force-Fase2/1.0'
                ]);
                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $curlError = curl_error($ch);
                curl_close($ch);
                
                if ($response === false || !empty($curlError)) {
                    $errors[] = [
                        'codigo' => $imovel->codigo_imovel,
                        'error' => 'CURL: ' . $curlError
                    ];
                    continue;
                }
                
                if ($httpCode !== 200) {
                    $skipped[] = [
                        'codigo' => $imovel->codigo_imovel,
                        'reason' => 'HTTP ' . $httpCode
                    ];
                    continue;
                }
                
                $data = json_decode($response, true);
                if (!isset($data['resultSet'])) {
                    $skipped[] = [
                        'codigo' => $imovel->codigo_imovel,
                        'reason' => 'No resultSet data'
                    ];
                    continue;
                }
                
                $d = $data['resultSet'];
                
                // Atualizar apenas descrição
                $db->table('imo_properties')
                    ->where('id', $imovel->id)
                    ->update([
                        'descricao' => $formatHtml($d['descricaoImovel'] ?? null),
                        'updated_at' => date('Y-m-d H:i:s')
                    ]);
                
                $updated++;
                
                // Rate limiting
                usleep(1100000); // 1.1 segundos
                
            } catch (\Exception $e) {
                $errors[] = [
                    'codigo' => $imovel->codigo_imovel,
                    'error' => $e->getMessage()
                ];
            }
        }
        
        $totalDb = $db->table('imo_properties')->count();
        $hasMore = ($offset + $limit) < $totalDb;
        
        return response()->json([
            'success' => true,
            'offset' => $offset,
            'limit' => $limit,
            'processed' => count($imoveis),
            'updated' => $updated,
            'skipped' => count($skipped),
            'skipped_samples' => array_slice($skipped, 0, 3),
            'errors' => count($errors),
            'error_samples' => array_slice($errors, 0, 3),
            'total_db' => $totalDb,
            'has_more' => $hasMore,
            'next_offset' => $hasMore ? ($offset + $limit) : null
        ]);
        
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});

