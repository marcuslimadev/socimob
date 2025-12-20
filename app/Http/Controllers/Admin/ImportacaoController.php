<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\Tenant;
use App\Services\ImportTablesManager;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ImportacaoController extends Controller
{
    /**
     * Listar im¢veis do tenant
     * GET /api/admin/imoveis
     */
    public function listar(Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->tenant_id) {
            return response()->json(['error' => 'User has no tenant'], 400);
        }

        $tenant = Tenant::find($user->tenant_id);
        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $imoveis = Property::where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $imoveis,
        ]);
    }

    /**
     * Status do job de importa‡Æo
     * GET /api/admin/imoveis/importar/{jobId}
     */
    public function status($jobId, Request $request)
    {
        $user = $request->user();

        if (!$user || !$user->tenant_id) {
            return response()->json(['error' => 'User has no tenant'], 400);
        }

        ImportTablesManager::ensureImportTablesExist();

        $job = DB::table('import_jobs')->where('id', $jobId)->first();
        if (!$job) {
            return response()->json(['error' => 'Job not found'], 404);
        }

        $params = json_decode($job->parametros ?? '[]', true) ?: [];
        if (!empty($params['tenant_id']) && (int) $params['tenant_id'] !== (int) $user->tenant_id) {
            return response()->json(['error' => 'Forbidden'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'job_id' => $job->id,
                'status' => $job->status,
                'total' => (int) ($job->total_itens ?? 0),
                'processados' => (int) ($job->processados ?? 0),
                'erros' => (int) ($job->erros ?? 0),
                'resultado' => $params['resultado'] ?? null,
                'erro' => $params['erro'] ?? null,
                'iniciado_em' => $job->iniciado_em,
                'finalizado_em' => $job->finalizado_em,
            ],
        ]);
    }

    /**
     * Testar API externa
     * POST /admin/importacao/teste-api
     */
    public function testarAPI(Request $request)
    {
        $url = $request->input('url');
        $token = $request->input('token');
        
        if (!$url || !$token) {
            return response()->json([
                'success' => false,
                'error' => 'URL e token são obrigatórios'
            ]);
        }
        
        try {
            $client = new \GuzzleHttp\Client([
                'verify' => false,
                'timeout' => 10,
                'http_errors' => false
            ]);
            
            $endpoint = $this->normalizarBaseUrl($url) . '/lista';
            $token = $this->normalizarToken($token);
            
            $response = $client->get($endpoint, [
                'headers' => [
                    'token' => $token,
                    'Content-Type' => 'application/json'
                ],
                'query' => [
                    'page' => 1,
                    'per_page' => 3
                ]
            ]);
            
            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();
            
            return response()->json([
                'success' => true,
                'status' => $statusCode,
                'response' => json_decode($body, true),
                'raw_response' => $body,
                'url_testada' => $endpoint,
                'token_usado' => substr($token, 0, 10) . '...'
            ]);
            
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Importar imóveis da API externa
     * POST /api/admin/imoveis/importar
     */
    public function importar(Request $request)
    {
        $user = $request->user();
        
        if (!$user || !$user->tenant_id) {
            return response()->json(['error' => 'User has no tenant'], 400);
        }

        $tenant = Tenant::find($user->tenant_id);

        if (!$tenant) {
            return response()->json(['error' => 'Tenant not found'], 404);
        }

        $this->validate($request, [
            'fonte' => 'required|string',
        ]);

        $fonte = $request->input('fonte');
        
        // Se for API customizada, usar URL fornecida
        if ($fonte === 'custom') {
            $apiUrl = $request->input('api_url');
            $apiKey = $request->input('api_key');
        } else {
            // Usar configuracoes salvas do tenant
            $apiUrl = $tenant->api_url_externa;
            $apiKey = $tenant->api_token_externa;
        }

        // Se ainda não tiver, usar defaults da Exclusiva
        if (!$apiUrl) {
            $apiUrl = 'https://www.exclusivalarimoveis.com.br/';
        }
        if (!$apiKey) {
            $apiKey = 'SUA_API_KEY_AQUI'; // Será configurado em Configurações
        }

        $apiUrl = $this->normalizarBaseUrl($apiUrl);
        $apiKey = $this->normalizarToken($apiKey);

        if (!$apiUrl || !$apiKey) {
            return response()->json([
                'success' => false,
                'error' => 'API não configurada',
                'message' => 'Configure a URL e Token da API em: Configurações > Integrações > API Externa'
            ], 400);
        }

        ImportTablesManager::ensureImportTablesExist();

        $agora = Carbon::now();
        $jobId = DB::table('import_jobs')->insertGetId([
            'tipo' => 'api_exclusiva',
            'status' => 'agendado',
            'origem' => $fonte,
            'responsavel' => $user->name ?? $user->email ?? 'Sistema',
            'parametros' => json_encode([
                'tenant_id' => $tenant->id,
                'fonte' => $fonte,
                'api_url' => $apiUrl,
            ]),
            'inicio_previsto' => $agora,
            'created_at' => $agora,
            'updated_at' => $agora,
        ]);

        $this->registrarLog('Importacao agendada para processamento.', $jobId);

        app()->terminating(function () use ($jobId, $tenant, $apiUrl, $apiKey, $fonte) {
            $this->processarJobImportacao($jobId, $tenant->id, $apiUrl, $apiKey, $fonte);
        });

        return response()->json([
            'success' => true,
            'message' => 'Importacao agendada.',
            'data' => [
                'job_id' => $jobId,
                'status' => 'agendado',
            ],
        ]);
    }

    /**
     * Buscar imóveis da API externa
     */
    private function buscarImoveisExternos($apiUrl, $apiKey, $fonte)
    {
        // API Exclusiva Lar Imóveis
        if (strpos($apiUrl, 'exclusivalarimoveis.com.br') !== false || $fonte === 'exclusiva') {
            return $this->buscarExclusiva($apiUrl, $apiKey);
        }

        // Outras APIs podem ser adicionadas aqui
        return $this->buscarGenerico($apiUrl, $apiKey);
    }

    /**
     * Buscar da API Exclusiva (formato específico)
     */
    private function buscarExclusiva($baseUrl, $token)
    {
        $baseUrl = $this->normalizarBaseUrl($baseUrl);
        $token = $this->normalizarToken($token);
        $imoveis = [];
        $pagina = 1;
        $limite = 20;

        try {
            // Criar cliente Guzzle customizado
            $client = new \GuzzleHttp\Client([
                'verify' => false, // Desabilitar verificação SSL se necessário
                'timeout' => 30,
                'http_errors' => false
            ]);

            do {
                // Montar URL completa
                $url = $baseUrl . '/lista';
                
                Log::info("Buscando página $pagina", ['url' => $url]);
                
                // Fazer requisição com Guzzle
                $response = $client->get($url, [
                    'headers' => [
                        'token' => $token,
                        'Content-Type' => 'application/json'
                    ],
                    'query' => [
                        'page' => $pagina,
                        'per_page' => $limite
                    ]
                ]);

                $statusCode = $response->getStatusCode();
                $body = $response->getBody()->getContents();

                if ($statusCode !== 200) {
                    Log::warning("Erro na página $pagina", [
                        'status' => $statusCode,
                        'body' => $body
                    ]);
                    
                    // Verificar se é erro de token inválido
                    $errorData = json_decode($body, true);
                    if ($statusCode === 401 && isset($errorData['message']) && 
                        strpos($errorData['message'], 'Token inválido') !== false) {
                        throw new \Exception('Token da API Exclusiva é inválido. Entre em contato com a Exclusiva Lar Imóveis para obter um token válido para integração.');
                    }
                    
                    break;
                }

                $data = json_decode($body, true);

                if (!$data) {
                    Log::error("Falha ao decodificar JSON da página $pagina");
                    break;
                }

                Log::info("Resposta recebida", [
                    'status' => $data['status'] ?? null,
                    'has_resultSet' => isset($data['resultSet']),
                    'data_count' => isset($data['resultSet']['data']) ? count($data['resultSet']['data']) : 0
                ]);
                
                // Estrutura real: resultSet.data[]
                if (!isset($data['status']) || !$data['status']) {
                    $mensagem = $data['message'] ?? 'API retornou status false';
                    Log::warning("API retornou status false na página $pagina", ['response' => $data]);
                    throw new \Exception($mensagem);
                }

                $resultSet = $data['resultSet'] ?? [];
				$listaImoveis = $resultSet['data'] ?? [];
				$totalPages = $resultSet['total_pages'] ?? 1;

                if (empty($listaImoveis)) {
                    Log::info("Nenhum imóvel encontrado na página $pagina");
                    break;
                }

                Log::info("Processando " . count($listaImoveis) . " imóveis da página $pagina");

                foreach ($listaImoveis as $imovelLista) {
                    // Apenas imóveis ativos
                    if (!$imovelLista['statusImovel']) {
                        continue;
                    }

                    // Buscar detalhes completos do imóvel
                    $detalhes = $this->buscarDetalhesExclusiva($baseUrl, $token, $imovelLista['codigoImovel']);
                    if ($detalhes) {
                        $imoveis[] = $this->mapearImovelExclusiva($detalhes);
                    }
                    
                    // Delay para não sobrecarregar a API
                    usleep(100000); // 0.1 segundo
                }

                $pagina++;
                
                // Limitar a 3 páginas (60 imóveis) por importação para não travar
                if ($pagina > 3 || $pagina > $totalPages) {
                    Log::info("Limite de páginas atingido (3 páginas)");
                    break;
                }

            } while (!empty($listaImoveis));

        } catch (\Exception $e) {
            Log::error('Erro ao buscar da API Exclusiva: ' . $e->getMessage());
            throw $e;
        }

        return $imoveis;
    }

    /**
     * Buscar detalhes de um imóvel específico da Exclusiva
     */
    private function buscarDetalhesExclusiva($baseUrl, $token, $imovelId)
    {
        try {
            $baseUrl = $this->normalizarBaseUrl($baseUrl);
            $token = $this->normalizarToken($token);
            $client = new \GuzzleHttp\Client([
                'verify' => false,
                'timeout' => 30,
                'http_errors' => false
            ]);

            $url = $baseUrl . '/dados/' . $imovelId;
            
            $response = $client->get($url, [
                'headers' => [
                    'token' => $token,
                    'Content-Type' => 'application/json'
                ]
            ]);

            if ($response->getStatusCode() === 200) {
                $body = $response->getBody()->getContents();
                $data = json_decode($body, true);
                
                // Estrutura real: resultSet (objeto direto, não array)
                if (isset($data['status']) && $data['status'] && isset($data['resultSet'])) {
                    return $data['resultSet'];
                }
            }
        } catch (\Exception $e) {
            Log::warning("Erro ao buscar detalhes do imóvel $imovelId: " . $e->getMessage());
        }

        return null;
    }

    private function normalizarBaseUrl($baseUrl)
    {
        $baseUrl = trim((string) $baseUrl);
        $baseUrl = trim($baseUrl, " \t\n\r\0\x0B'\"");
        $baseUrl = strtok($baseUrl, '?') ?: $baseUrl;
        $baseUrl = rtrim($baseUrl, '/');

        // Se veio com /lista ou /dados, remover para manter base consistente
        $baseUrl = preg_replace('#/api/v1/app/imovel/(lista|dados)(/.*)?$#', '/api/v1/app/imovel', $baseUrl);

        if (preg_match('#/api/v1/app/imovel$#', $baseUrl)) {
            return $baseUrl;
        }

        return $baseUrl . '/api/v1/app/imovel';
    }

    private function normalizarToken($token)
    {
        $token = trim((string) $token);
        return trim($token, " \t\n\r\0\x0B'\"");
    }

    /**
     * Mapear imóvel da Exclusiva para formato interno
     */
    private function mapearImovelExclusiva($imovel)
    {
        // Montar título baseado no tipo e localização
        $tipo = $imovel['descricaoTipoImovel'] ?? 'Imóvel';
        $bairro = $imovel['endereco']['bairro'] ?? '';
        $cidade = $imovel['endereco']['cidade'] ?? '';
        $titulo = "$tipo no $bairro - $cidade";
        
        // Extrair URLs das imagens
        $fotos = [];
        if (isset($imovel['imagens']) && is_array($imovel['imagens'])) {
            $fotos = array_map(function($img) {
                return $img['url'];
            }, $imovel['imagens']);
        }

        // Área: priorizar área total, depois construída
        $area = floatval($imovel['area']['total']['valor'] ?? $imovel['area']['construida']['valor'] ?? 0);
        
        return [
            'external_id' => strval($imovel['codigoImovel'] ?? ''),
            'codigo' => strval($imovel['codigoImovel'] ?? ''),
            'titulo' => $titulo,
            'tipo_imovel' => $this->mapearTipo($imovel['descricaoTipoImovel'] ?? 'Casa'),
            'finalidade_imovel' => $this->mapearFinalidade($imovel['finalidadeImovel'] ?? 'Venda'),
            'preco' => floatval($imovel['valorEsperado'] ?? 0),
            'area_total' => $area,
            'quartos' => intval($imovel['dormitorios'] ?? 0),
            'banheiros' => intval($imovel['banheiros'] ?? 0),
            'vagas' => intval($imovel['garagem'] ?? 0),
            'endereco' => $this->montarEnderecoExclusiva($imovel['endereco'] ?? []),
            'cidade' => $imovel['endereco']['cidade'] ?? '',
            'estado' => $imovel['endereco']['estado'] ?? '',
            'descricao' => $imovel['descricaoImovel'] ?? '',
            'fotos' => $fotos, // Remover json_encode, será feito automaticamente pelo cast
            'active' => $imovel['exibirImovel'] ?? true,
            'exibir_imovel' => $imovel['exibirImovel'] ?? true,
            'url_ficha' => '', // Pode ser adicionado se disponível na API
            'last_sync' => date('Y-m-d H:i:s')
        ];
    }

    /**
     * Montar endereço completo da Exclusiva
     */
    private function montarEnderecoExclusiva($endereco)
    {
        if (empty($endereco)) {
            return 'Endereço não informado';
        }

        $partes = array_filter([
            $endereco['logradouro'] ?? '',
            $endereco['numero'] ?? '',
            $endereco['complemento'] ?? '',
            $endereco['bairro'] ?? '',
            $endereco['cidade'] ?? '',
            $endereco['estado'] ?? ''
        ]);

        return implode(', ', $partes) ?: 'Endereço não informado';
    }

    /**
     * Buscar de API genérica
     */
    private function buscarGenerico($apiUrl, $apiKey)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json'
            ])->get($apiUrl);

            if ($response->successful()) {
                $data = $response->json();
                return $data['data'] ?? $data['imoveis'] ?? $data;
            }
        } catch (\Exception $e) {
            Log::error('Erro ao buscar da API genérica: ' . $e->getMessage());
        }

        return [];
    }

    /**
     * Processar e salvar imóveis importados
     */
    private function processarImportacao($imoveis, $tenantId, $jobId = null)
    {
        $importados = 0;
        $duplicados = 0;
        $erros = 0;
        $logged = 0;
        $logLimit = 5;

        foreach ($imoveis as $imovelData) {
            try {
                if (empty($imovelData['external_id'])) {
                    $erros++;
                    if ($jobId && $logged < $logLimit) {
                        $this->registrarLog('Imovel sem external_id, ignorado.', $jobId, 'erro', $imovelData['codigo'] ?? null);
                        $logged++;
                    }
                    continue;
                }

                // Adicionar tenant_id aos dados
                $imovelData['tenant_id'] = $tenantId;
                
                // Filtrar apenas campos permitidos no fillable do Property
                $camposPermitidos = [
                    'tenant_id', 'codigo', 'titulo', 'descricao', 'active', 'exibir_imovel',
                    'external_id', 'finalidade_imovel', 'tipo_imovel', 'preco', 'endereco',
                    'cidade', 'estado', 'area_total', 'quartos', 'banheiros', 'vagas',
                    'fotos', 'url_ficha', 'last_sync'
                ];
                
                $dadosFiltrados = array_intersect_key($imovelData, array_flip($camposPermitidos));
                
                // Verificar duplicata por external_id
                $existente = Property::where('tenant_id', $tenantId)
                    ->where('external_id', $dadosFiltrados['external_id'])
                    ->first();

                if ($existente) {
                    // Atualizar imóvel existente
                    $existente->update($dadosFiltrados);
                    $duplicados++;
                    if ($jobId && $logged < $logLimit) {
                        $this->registrarLog('Imovel atualizado (duplicado).', $jobId, 'info', $dadosFiltrados['external_id'], [
                            'property_id' => $existente->id,
                            'titulo' => $dadosFiltrados['titulo'] ?? null,
                        ]);
                        $logged++;
                    }
                    continue;
                }

                // Criar novo imóvel
                Property::create($dadosFiltrados);

                $importados++;
                if ($jobId && $logged < $logLimit) {
                    $this->registrarLog('Imovel criado.', $jobId, 'info', $dadosFiltrados['external_id'], [
                        'titulo' => $dadosFiltrados['titulo'] ?? null,
                    ]);
                    $logged++;
                }

            } catch (\Exception $e) {
                Log::error('Erro ao salvar imóvel: ' . $e->getMessage());
                $erros++;
                if ($jobId && $logged < $logLimit) {
                    $this->registrarLog('Erro ao salvar imovel: ' . $e->getMessage(), $jobId, 'erro', $imovelData['external_id'] ?? null);
                    $logged++;
                }
            }
        }

        return [
            'importados' => $importados,
            'duplicados' => $duplicados,
            'erros' => $erros
        ];
    }

    private function processarJobImportacao(int $jobId, int $tenantId, string $apiUrl, string $apiKey, string $fonte): void
    {
        $inicio = Carbon::now();
        $this->atualizarJob($jobId, [
            'status' => 'processando',
            'iniciado_em' => $inicio,
        ]);

        try {
            $imoveis = $this->buscarImoveisExternos($apiUrl, $apiKey, $fonte);
            $total = count($imoveis);

            $this->atualizarJob($jobId, [
                'total_itens' => $total,
            ]);

            $resultado = [
                'importados' => 0,
                'duplicados' => 0,
                'erros' => 0,
            ];

            if ($total > 0) {
                $resultado = $this->processarImportacao($imoveis, $tenantId, $jobId);
            } else {
                $this->registrarLog('Nenhum imovel encontrado na API externa.', $jobId, 'info');
            }

            $params = [
                'tenant_id' => $tenantId,
                'fonte' => $fonte,
                'api_url' => $apiUrl,
                'resultado' => $resultado,
            ];

            $this->atualizarJob($jobId, [
                'status' => 'concluido',
                'finalizado_em' => Carbon::now(),
                'tempo_execucao' => $inicio->diffInSeconds(Carbon::now()),
                'processados' => $resultado['importados'] + $resultado['duplicados'],
                'erros' => $resultado['erros'],
                'parametros' => json_encode($params),
            ]);

            $this->registrarLog('Importacao concluida.', $jobId, 'info', null, $resultado);
        } catch (\Throwable $e) {
            $params = [
                'tenant_id' => $tenantId,
                'fonte' => $fonte,
                'api_url' => $apiUrl,
                'erro' => $e->getMessage(),
            ];

            $this->atualizarJob($jobId, [
                'status' => 'falhou',
                'finalizado_em' => Carbon::now(),
                'tempo_execucao' => $inicio->diffInSeconds(Carbon::now()),
                'erros' => 1,
                'parametros' => json_encode($params),
            ]);

            $this->registrarLog('Falha na importacao: ' . $e->getMessage(), $jobId, 'erro');
            Log::error('Erro na importacao (job)', ['job_id' => $jobId, 'error' => $e->getMessage()]);
        }
    }

    private function atualizarJob(int $jobId, array $dados): void
    {
        if (!Schema::hasTable('import_jobs')) {
            return;
        }

        $dados['updated_at'] = Carbon::now();
        DB::table('import_jobs')->where('id', $jobId)->update($dados);
    }

    private function registrarLog(string $mensagem, ?int $jobId = null, string $nivel = 'info', ?string $codigo = null, array $detalhes = []): void
    {
        if (!Schema::hasTable('import_logs')) {
            Log::warning('Registro de log ignorado: ' . $mensagem);
            return;
        }

        DB::table('import_logs')->insert([
            'job_id' => $jobId,
            'nivel' => $nivel,
            'codigo_imovel' => $codigo,
            'mensagem' => $mensagem,
            'detalhes' => $detalhes ? json_encode($detalhes) : null,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);
    }

    /**
     * Helpers de mapeamento
     */
    private function mapearTipo($tipo)
    {
        $tipo = strtolower($tipo);
        
        // Casa e variações
        if (strpos($tipo, 'casa') !== false) return 'casa';
        
        // Apartamento e variações
        if (strpos($tipo, 'apartamento') !== false || 
            strpos($tipo, 'apto') !== false ||
            strpos($tipo, 'flat') !== false ||
            strpos($tipo, 'studio') !== false ||
            strpos($tipo, 'kitnet') !== false ||
            strpos($tipo, 'cobertura') !== false) {
            return 'apartamento';
        }
        
        // Terreno e variações
        if (strpos($tipo, 'terreno') !== false ||
            strpos($tipo, 'lote') !== false ||
            strpos($tipo, 'área') !== false) {
            return 'terreno';
        }
        
        // Comercial e variações
        if (strpos($tipo, 'comercial') !== false ||
            strpos($tipo, 'sala') !== false ||
            strpos($tipo, 'loja') !== false ||
            strpos($tipo, 'galpão') !== false ||
            strpos($tipo, 'galpao') !== false ||
            strpos($tipo, 'ponto comercial') !== false ||
            strpos($tipo, 'prédio') !== false ||
            strpos($tipo, 'predio') !== false) {
            return 'comercial';
        }
        
        // Padrão
        return 'casa';
    }

    private function mapearFinalidade($finalidade)
    {
        $finalidade = strtolower($finalidade);
        
        if (strpos($finalidade, 'vend') !== false) return 'venda';
        if (strpos($finalidade, 'alug') !== false) return 'aluguel';
        
        return 'venda';
    }
}
