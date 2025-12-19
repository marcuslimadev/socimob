<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Property;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ImportacaoController extends Controller
{
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
            
            $endpoint = rtrim($url, '/') . '/api/v1/app/imovel/lista';
            
            $response = $client->get($endpoint, [
                'headers' => [
                    'token' => $token,
                    'Content-Type' => 'application/json'
                ],
                'query' => [
                    'pagina' => 1,
                    'limite' => 3
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
            'api_url' => 'nullable|string',
            'api_key' => 'nullable|string',
        ]);

        $fonte = $request->input('fonte');
        
        try {
            // Se for API customizada, usar URL fornecida
            if ($fonte === 'custom') {
                $apiUrl = $request->input('api_url');
                $apiKey = $request->input('api_key');
            } else {
                // Usar configurações salvas do tenant
                $apiUrl = $tenant->api_url_externa;
                $apiKey = $tenant->api_token_externa;
            }

            if (!$apiUrl || !$apiKey) {
                return response()->json([
                    'error' => 'API não configurada. Configure em Configurações → Integrações'
                ], 400);
            }

            // Chamar API externa
            $imoveis = $this->buscarImoveisExternos($apiUrl, $apiKey, $fonte);

            if (empty($imoveis)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Nenhum imóvel encontrado na API externa'
                ], 200);
            }

            // Importar imóveis
            $resultado = $this->processarImportacao($imoveis, $tenant->id);

            return response()->json([
                'success' => true,
                'message' => 'Importação concluída com sucesso!',
                'importados' => $resultado['importados'],
                'duplicados' => $resultado['duplicados'],
                'erros' => $resultado['erros'],
                'total' => count($imoveis)
            ]);

        } catch (\Exception $e) {
            Log::error('Erro na importação: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'error' => 'Erro ao importar: ' . $e->getMessage()
            ], 500);
        }
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
                $url = rtrim($baseUrl, '/') . '/api/v1/app/imovel/lista';
                
                Log::info("Buscando página $pagina", ['url' => $url]);
                
                // Fazer requisição com Guzzle
                $response = $client->get($url, [
                    'headers' => [
                        'token' => $token,
                        'Content-Type' => 'application/json'
                    ],
                    'query' => [
                        'pagina' => $pagina,
                        'limite' => $limite
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
                    Log::warning("API retornou status false na página $pagina", ['response' => $data]);
                    break;
                }

                $listaImoveis = $data['resultSet']['data'] ?? [];

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
                if ($pagina > 3) {
                    Log::info("Limite de páginas atingido (3 páginas)");
                    break;
                }

            } while (!empty($listaImoveis));

        } catch (\Exception $e) {
            Log::error('Erro ao buscar da API Exclusiva: ' . $e->getMessage());
        }

        return $imoveis;
    }

    /**
     * Buscar detalhes de um imóvel específico da Exclusiva
     */
    private function buscarDetalhesExclusiva($baseUrl, $token, $imovelId)
    {
        try {
            $client = new \GuzzleHttp\Client([
                'verify' => false,
                'timeout' => 30,
                'http_errors' => false
            ]);

            $url = rtrim($baseUrl, '/') . '/api/v1/app/imovel/dados/' . $imovelId;
            
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
            'last_sync' => now()
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
    private function processarImportacao($imoveis, $tenantId)
    {
        $importados = 0;
        $duplicados = 0;
        $erros = 0;

        foreach ($imoveis as $imovelData) {
            try {
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
                    continue;
                }

                // Criar novo imóvel
                Property::create($dadosFiltrados);

                $importados++;

            } catch (\Exception $e) {
                Log::error('Erro ao salvar imóvel: ' . $e->getMessage());
                $erros++;
            }
        }

        return [
            'importados' => $importados,
            'duplicados' => $duplicados,
            'erros' => $erros
        ];
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
