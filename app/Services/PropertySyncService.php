<?php

namespace App\Services;

use App\Models\Property;
use Illuminate\Support\Facades\Log;

/**
 * Serviço de sincronização de imóveis
 * Busca dados da API da Exclusiva Lar e atualiza o banco de dados
 */
class PropertySyncService
{
    private $apiToken;
    private $baseUrl = 'https://www.exclusivalarimoveis.com.br/api/v1/app/imovel';
    private $geocodeCache = [];
    private $lastGeocodeCall = 0;
    
    public function __construct()
    {
        $this->apiToken = env('EXCLUSIVA_API_TOKEN');
        
        if (!$this->apiToken) {
            // throw new \Exception('EXCLUSIVA_API_TOKEN não configurado no .env');
        }
    }
    
    /**
     * Sincronizar todos os imóveis
     */
    public function syncAll()
    {
        $startTime = microtime(true);
        
        Log::info('🏠 Iniciando sincronização de imóveis...');
        
        try {
            $stats = [
                'found' => 0,
                'new' => 0,
                'updated' => 0,
                'errors' => 0
            ];

            $errorDetails = [];
            
            $page = 1;
            $totalPages = 1;
            
            $perPage = 50;

            // Loop por todas as páginas
            do {
                Log::info("📄 Buscando página {$page}...");
                
                // Montar query string para paginação
                $queryString = http_build_query([
                    'pagina' => $page,
                    'limite' => $perPage
                ]);
                
                // Buscar lista de imóveis (com paginação) - tentando GET primeiro
                try {
                    $lista = $this->callApi("/lista?{$queryString}");
                } catch (\Exception $e) {
                    // Se falhar, tentar POST
                    Log::info("GET /lista falhou, tentando POST...");
                    $lista = $this->callApiPost("/lista", [
                        'pagina' => $page,
                        'limite' => $perPage
                    ]);
                }
                
                if (!isset($lista['resultSet']['data'])) {
                    throw new \Exception('Resposta da API inválida: estrutura esperada não encontrada');
                }
                
                $resultSet = $lista['resultSet'];
                $imoveis = $resultSet['data'];
                $totalPages = $resultSet['total_pages'] ?? 1;
                $totalItems = $resultSet['total_items'] ?? 0;
                
                Log::info("📊 Página {$page}/{$totalPages} - " . count($imoveis) . " imóveis", [
                    'total_items' => $totalItems,
                    'per_page' => $resultSet['per_page'] ?? 50
                ]);
                
                $stats['found'] += count($imoveis);
                
                foreach ($imoveis as $item) {
                    $codigo = $item['codigoImovel'] ?? null;
                    
                    if (!$codigo) {
                        $stats['errors']++;
                        continue;
                    }
                    
                    try {
                        // Buscar dados completos do imóvel (GET ainda funciona)
                        $response = $this->callApi("/dados/{$codigo}");
                        
                        if (!isset($response['resultSet'])) {
                            throw new \Exception("Dados não encontrados para imóvel {$codigo}");
                        }
                        
                        $imovel = $response['resultSet'];
                        
                        // Verificar se já existe
                        $existing = Property::where('codigo', $codigo)->first();
                        
                        $data = $this->mapPropertyData($imovel);
                        
                        // Contar imagens para logging
                        $numImagens = 0;
                        if (isset($data['imagens']) && is_array($data['imagens'])) {
                            $numImagens = count($data['imagens']);
                        }
                        
                        if ($existing) {
                            $existing->update($data);
                            $stats['updated']++;
                            Log::debug("✏️ Imóvel {$codigo} atualizado ({$numImagens} imagens)");
                        } else {
                            Property::create($data);
                            $stats['new']++;
                            Log::debug("➕ Imóvel {$codigo} criado ({$numImagens} imagens)");
                        }
                        
                    } catch (\Exception $e) {
                        $stats['errors']++;
                        $errorMessage = $e->getMessage();
                        $errorDetails[] = [
                            'codigo' => $codigo,
                            'message' => $errorMessage,
                            'file' => $e->getFile(),
                            'line' => $e->getLine()
                        ];
                        Log::error("❌ Erro ao processar imóvel {$codigo}", [
                            'error' => $errorMessage
                        ]);
                    }
                }
                
                $page++;
                
            } while ($page <= $totalPages);
            
            $elapsed = round((microtime(true) - $startTime) * 1000, 2);
            
            // Contar quantos imóveis têm imagens
            $comImagens = Property::whereNotNull('imagens')
                ->where('imagens', '!=', '[]')
                ->where('imagens', '!=', '')
                ->count();
            
            $stats['with_images'] = $comImagens;
            
            Log::info('✅ Sincronização concluída', [
                'stats' => $stats,
                'time_ms' => $elapsed
            ]);
            
            return [
                'success' => true,
                'stats' => $stats,
                'time_ms' => $elapsed,
                'errors_detail' => $errorDetails
            ];
            
        } catch (\Exception $e) {
            Log::error('❌ Erro na sincronização de imóveis', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Mapear dados do imóvel da API para o formato do banco
     */
    private function mapPropertyData($imovel)
    {
        // Converter áreas - nova estrutura da API
        $areaPrivativa = isset($imovel['area']['privativa']['valor']) ? 
            $this->parseArea($imovel['area']['privativa']['valor']) : null;
        $areaTotal = isset($imovel['area']['total']['valor']) ? 
            $this->parseArea($imovel['area']['total']['valor']) : null;
        $areaTerreno = isset($imovel['area']['terreno']['valor']) ? 
            $this->parseArea($imovel['area']['terreno']['valor']) : null;
        
        // Características - agora pode ser array vazio
        $caracteristicas = [];
        if (!empty($imovel['caracteristicas']) && is_array($imovel['caracteristicas'])) {
            foreach ($imovel['caracteristicas'] as $carac) {
                if (is_string($carac)) {
                    $caracteristicas[] = $carac;
                } elseif (isset($carac['nomeCaracteristica'])) {
                    $caracteristicas[] = $carac['nomeCaracteristica'];
                }
            }
        }
        
        // Imagem destaque
        $imagemDestaque = $this->getImagemDestaque($imovel['imagens'] ?? []);
        
        // Preparar dados de imagens - apenas URLs (strings)
        $imagensData = [];
        if (!empty($imovel['imagens']) && is_array($imovel['imagens'])) {
            foreach ($imovel['imagens'] as $img) {
                if (isset($img['url'])) {
                    $imagensData[] = $img['url'];
                }
            }
        }

        $latitude = $imovel['endereco']['latitude'] ?? null;
        $longitude = $imovel['endereco']['longitude'] ?? null;

        if (empty($latitude) || empty($longitude)) {
            [$latitude, $longitude] = $this->resolveCoordinates($imovel);
        }

        $latitude = ($latitude === null || $latitude === '') ? null : (float) $latitude;
        $longitude = ($longitude === null || $longitude === '') ? null : (float) $longitude;
        
        // Montar título baseado no tipo e localização
        $tipo = $imovel['descricaoTipoImovel'] ?? 'Imóvel';
        $bairro = $imovel['endereco']['bairro'] ?? '';
        $cidade = $imovel['endereco']['cidade'] ?? '';
        $titulo = "$tipo no $bairro - $cidade";
        
        // Montar endereço completo
        $endereco = $imovel['endereco'] ?? [];
        $enderecoCompleto = implode(', ', array_filter([
            $endereco['logradouro'] ?? '',
            $endereco['numero'] ?? '',
            $endereco['complemento'] ?? '',
            $endereco['bairro'] ?? '',
            $endereco['cidade'] ?? '',
            $endereco['estado'] ?? ''
        ])) ?: 'Endereço não informado';
        
        $data = [
            'codigo' => $imovel['codigoImovel'],
            'external_id' => strval($imovel['codigoImovel']),
            'titulo' => $titulo,
            'finalidade_imovel' => $this->mapearFinalidade($imovel['finalidadeImovel'] ?? 'Venda'),
            'tipo_imovel' => $this->mapearTipo($imovel['descricaoTipoImovel'] ?? 'Casa'),
            'descricao' => $this->formatDescriptionWithAI($imovel['descricaoImovel'] ?? null),
            'dormitorios' => intval($imovel['dormitorios'] ?? 0),
            'banheiros' => intval($imovel['banheiros'] ?? 0),
            'garagem' => intval($imovel['garagem'] ?? 0),
            'valor_venda' => floatval($imovel['valorEsperado'] ?? 0),
            'logradouro' => $enderecoCompleto,
            'cidade' => $imovel['endereco']['cidade'] ?? null,
            'estado' => $imovel['endereco']['estado'] ?? null,
            'bairro' => $imovel['endereco']['bairro'] ?? null,
            'area_total' => $areaTotal,
            'imagens' => $imagensData, // Array será convertido automaticamente pelo cast
            'latitude' => $latitude,
            'longitude' => $longitude,
            'exibir_imovel' => true,
            'active' => true,
            'last_sync' => date('Y-m-d H:i:s')
        ];
        
        // Garantir que o tenant_id seja incluído explicitamente (segurança adicional)
        if (app()->bound('tenant')) {
            $data['tenant_id'] = app('tenant')->id;
        }
        
        return $data;
    }
    
    /**
     * Converter área de string para float
     */
    private function parseArea($valor)
    {
        if (!$valor) return null;
        return (float) str_replace(',', '.', $valor);
    }

    private function parseApiDateTime($value)
    {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        $normalized = str_replace('/', '-', trim((string) $value));
        $timestamp = strtotime($normalized);

        return $timestamp ? date('Y-m-d H:i:s', $timestamp) : null;
    }
    
    /**
     * Mapear tipo de imóvel para formato padronizado
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

    /**
     * Mapear finalidade do imóvel
     */
    private function mapearFinalidade($finalidade)
    {
        $finalidade = strtolower($finalidade);
        
        if (strpos($finalidade, 'vend') !== false) return 'venda';
        if (strpos($finalidade, 'alug') !== false) return 'aluguel';
        
        return 'venda';
    }
    
    /**
     * Obter imagem destaque
     */
    private function getImagemDestaque($imagens)
    {
        if (empty($imagens)) return null;
        
        // Buscar imagem marcada como destaque
        foreach ($imagens as $img) {
            if (isset($img['destaque']) && $img['destaque']) {
                return $img['url'];
            }
        }
        
        // Se não tiver destaque, pega a primeira
        return $imagens[0]['url'] ?? null;
    }

    private function resolveCoordinates(array $imovel)
    {
        $endereco = $imovel['endereco'] ?? [];
        $logradouro = trim($endereco['logradouro'] ?? '');
        $numero = trim($endereco['numero'] ?? '');
        $bairro = trim($endereco['bairro'] ?? '');
        $cidade = trim($endereco['cidade'] ?? '');
        $estado = strtoupper(trim($endereco['estado'] ?? ''));
        $cep = preg_replace('/\D/', '', $endereco['cep'] ?? '');

        if (empty($cidade)) {
            $cidade = 'Belo Horizonte';
            $estado = $estado ?: 'MG';
        }

        $cacheKey = md5(json_encode([$logradouro, $numero, $bairro, $cidade, $estado, $cep]));
        if (isset($this->geocodeCache[$cacheKey])) {
            return $this->geocodeCache[$cacheKey];
        }

        if (empty($bairro) && empty($logradouro) && empty($cidade)) {
            return $this->geocodeCache[$cacheKey] = [null, null];
        }

        if ($cep) {
            $coords = $this->geocodeViaCep($cep);
            if ($this->validCoordinates($coords[0], $coords[1])) {
                return $this->geocodeCache[$cacheKey] = $coords;
            }
        }

        $queries = [];
        if ($logradouro && $numero) {
            $queries[] = "{$logradouro}, {$numero}, {$bairro}, {$cidade}, {$estado}, Brasil";
        }
        if ($logradouro) {
            $queries[] = "{$logradouro}, {$bairro}, {$cidade}, {$estado}, Brasil";
        }
        if ($bairro) {
            $queries[] = "{$bairro}, {$cidade}, {$estado}, Brasil";
        }
        $queries[] = "{$cidade}, {$estado}, Brasil";

        foreach ($queries as $query) {
            $coords = $this->searchNominatim($query);
            if ($this->validCoordinates($coords[0], $coords[1])) {
                return $this->geocodeCache[$cacheKey] = $coords;
            }
        }

        if ($estado) {
            $coords = $this->getStateCoordinates($estado);
            if ($this->validCoordinates($coords[0], $coords[1])) {
                return $this->geocodeCache[$cacheKey] = $coords;
            }
        }

        return $this->geocodeCache[$cacheKey] = [null, null];
    }

    private function geocode($endereco)
    {
        if (empty(trim($endereco))) {
            return [null, null];
        }
        return $this->searchNominatim($endereco . ', Brasil');
    }

    private function geocodeViaCep($cep)
    {
        $cep = preg_replace('/\D/', '', $cep);
        if (strlen($cep) !== 8) {
            return [null, null];
        }

        $url = "https://viacep.com.br/ws/{$cep}/json/";
        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'method' => 'GET',
                'header' => "User-Agent: PropertySync/1.0\r\n"
            ]
        ]);

        $resp = @file_get_contents($url, false, $context);
        if ($resp === false) {
            return [null, null];
        }

        $data = json_decode($resp, true);
        if (empty($data) || !empty($data['erro'])) {
            return [null, null];
        }

        $parts = array_filter([
            $data['logradouro'] ?? null,
            $data['bairro'] ?? null,
            ($data['localidade'] ?? '') . ' - ' . ($data['uf'] ?? ''),
            'Brasil'
        ]);

        if (empty($parts)) {
            return [null, null];
        }

        $query = implode(', ', $parts);
        return $this->searchNominatim($query);
    }

    private function searchNominatim($query)
    {
        if ($this->lastGeocodeCall > 0) {
            $elapsed = microtime(true) - $this->lastGeocodeCall;
            if ($elapsed < 1.1) {
                usleep((int)((1.1 - $elapsed) * 1000000));
            }
        }

        $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
            'q' => $query,
            'format' => 'json',
            'limit' => 1,
            'addressdetails' => 1,
            'countrycodes' => 'br'
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'User-Agent: PropertySync/1.0 (contato@exclusivalarimoveis.com.br)'
            ]
        ]);

        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $this->lastGeocodeCall = microtime(true);

        if ($httpCode !== 200 || $resp === false) {
            return [null, null];
        }

        $data = json_decode($resp, true);
        if (is_array($data) && count($data) > 0) {
            $lat = isset($data[0]['lat']) ? (float) $data[0]['lat'] : null;
            $lng = isset($data[0]['lon']) ? (float) $data[0]['lon'] : null;
            return [$lat, $lng];
        }

        return [null, null];
    }

    private function validCoordinates($lat, $lng)
    {
        if ($lat === null || $lng === null) {
            return false;
        }

        return $lat >= -33.75 && $lat <= 5.27 && $lng >= -73.99 && $lng <= -28.84;
    }

    private function getStateCoordinates($estado)
    {
        $coords = [
            'AC' => [-9.0238, -70.8120],
            'AL' => [-9.5713, -36.7820],
            'AP' => [1.4061, -51.6022],
            'AM' => [-3.4168, -65.8561],
            'BA' => [-12.5797, -41.7007],
            'CE' => [-5.4984, -39.3206],
            'DF' => [-15.7998, -47.8645],
            'ES' => [-19.1834, -40.3089],
            'GO' => [-15.8270, -49.8362],
            'MA' => [-4.9609, -45.2744],
            'MT' => [-12.6819, -56.9211],
            'MS' => [-20.7722, -54.7852],
            'MG' => [-19.9167, -43.9345],
            'PA' => [-3.7970, -52.4751],
            'PB' => [-7.2399, -36.7819],
            'PR' => [-24.8940, -51.5555],
            'PE' => [-8.8137, -36.9541],
            'PI' => [-6.6000, -42.2800],
            'RJ' => [-22.9068, -43.1729],
            'RN' => [-5.4026, -36.9541],
            'RS' => [-30.0346, -51.2177],
            'RO' => [-10.9472, -62.8278],
            'RR' => [1.3227, -60.6522],
            'SC' => [-27.2423, -50.2189],
            'SP' => [-23.5505, -46.6333],
            'SE' => [-10.5741, -37.3857],
            'TO' => [-10.1753, -48.2982],
        ];

        $estado = strtoupper($estado);
        return $coords[$estado] ?? [null, null];
    }
    
    /**
     * Fazer chamada à API da Exclusiva Lar
     */
    private function callApi($endpoint)
    {
        $url = $this->baseUrl . $endpoint;
        
        Log::debug("API Call URL: {$url}");
        Log::debug("Token usado: " . substr($this->apiToken, 0, 10) . '...');
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'token: ' . $this->apiToken,
                'User-Agent: ExclusivaLar-CRM/1.0'
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 20
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        Log::debug("API Response: HTTP {$httpCode}", [
            'response_length' => strlen($response),
            'has_error' => !empty($error),
            'response_preview' => substr($response, 0, 200)
        ]);
        
        if ($httpCode !== 200) {
            throw new \Exception("API retornou HTTP {$httpCode}: {$response}");
        }
        
        if ($error) {
            throw new \Exception("Erro cURL: {$error}");
        }
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Resposta JSON inválida: ' . json_last_error_msg());
        }
        
        return $data;
    }
    
    /**
     * Fazer chamada POST à API da Exclusiva Lar
     */
    private function callApiPost($endpoint, $postData = [])
    {
        $url = $this->baseUrl . $endpoint;
        
        Log::debug("API POST URL: {$url}");
        Log::debug("POST Data: " . json_encode($postData));
        
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($postData),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'token: ' . $this->apiToken,
                'User-Agent: ExclusivaLar-CRM/1.0'
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 20
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        Log::debug("API POST Response: HTTP {$httpCode}", [
            'response_length' => strlen($response),
            'has_error' => !empty($error),
            'response_preview' => substr($response, 0, 200)
        ]);
        
        if ($httpCode !== 200) {
            throw new \Exception("API retornou HTTP {$httpCode}: {$response}");
        }
        
        if ($error) {
            throw new \Exception("Erro cURL: {$error}");
        }
        
        $data = json_decode($response, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Resposta JSON inválida: ' . json_last_error_msg());
        }
        
        return $data;
    }

    /**
     * Formatar descrição de imóvel com OpenAI
     */
    private function formatDescriptionWithAI($descricao)
    {
        // Se não há descrição, retornar null
        if (empty($descricao)) {
            return null;
        }

        // Verificar se a OpenAI está configurada
        $apiKey = env('OPENAI_API_KEY');
        if (!$apiKey) {
            Log::warning('OpenAI API key não configurada, usando descrição original');
            // Return description with HTML entities properly encoded
            return htmlspecialchars_decode($descricao, ENT_QUOTES | ENT_HTML5);
        }

        try {
            $prompt = "Você é um especialista em marketing imobiliário. Formate esta descrição de imóvel de forma profissional, atrativa e organizada em HTML. Use tags HTML apropriadas como <p>, <strong>, <ul>, <li> para estruturar o conteúdo. Mantenha todas as informações importantes, mas torne-a mais vendável e bem estruturada. Use emojis apropriados. Texto original:\n\n" . $descricao;

            $data = [
                'model' => 'gpt-4o-mini',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Você é um especialista em marketing imobiliário. Sua função é transformar descrições de imóveis em textos atraentes, bem formatados e profissionais usando HTML. Use tags como <p>, <strong>, <ul>, <li>, <br> para formatação.'
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt
                    ]
                ],
                'max_tokens' => 1000,
                'temperature' => 0.7
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($error) {
                Log::warning('OpenAI curl error: ' . $error);
                return htmlspecialchars_decode($descricao, ENT_QUOTES | ENT_HTML5);
            }

            if ($httpCode !== 200) {
                Log::warning('OpenAI HTTP error: ' . $httpCode . ' - ' . $response);
                return htmlspecialchars_decode($descricao, ENT_QUOTES | ENT_HTML5);
            }

            $result = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::warning('OpenAI JSON parse error: ' . json_last_error_msg());
                return htmlspecialchars_decode($descricao, ENT_QUOTES | ENT_HTML5);
            }

            if (!isset($result['choices'][0]['message']['content'])) {
                Log::warning('OpenAI response format error', ['response' => $result]);
                return htmlspecialchars_decode($descricao, ENT_QUOTES | ENT_HTML5);
            }

            $textoFormatado = trim($result['choices'][0]['message']['content']);
            
            // Remove markdown code blocks if present (```html ... ```)
            $textoFormatado = preg_replace('/```html\s*(.*?)\s*```/s', '$1', $textoFormatado);
            $textoFormatado = preg_replace('/```\s*(.*?)\s*```/s', '$1', $textoFormatado);
            
            Log::info('✨ Descrição formatada com sucesso via OpenAI');
            
            return $textoFormatado;

        } catch (\Exception $e) {
            Log::error('Erro ao formatar descrição com OpenAI', [
                'error' => $e->getMessage(),
                'descricao_original' => substr($descricao, 0, 100) . '...'
            ]);
            
            // Em caso de erro, retornar descrição original com HTML decodificado
            return htmlspecialchars_decode($descricao, ENT_QUOTES | ENT_HTML5);
        }
    }

    /**
     * Validar se imóvel tem dados mínimos necessários
     *
     * @param array $propertyData
     * @return array ['valid' => bool, 'missing' => array]
     */
    private function validateMinimalData(array $propertyData): array
    {
        $requiredFields = [
            'codigo' => 'Código do imóvel',
            'titulo' => 'Título',
            'finalidade_imovel' => 'Finalidade',
            'tipo_imovel' => 'Tipo',
        ];

        $missing = [];

        foreach ($requiredFields as $field => $label) {
            if (empty($propertyData[$field])) {
                $missing[] = $label;
            }
        }

        // Validar se tem pelo menos um valor (venda ou aluguel)
        if (empty($propertyData['valor_venda']) && empty($propertyData['valor_aluguel'])) {
            $missing[] = 'Valor (venda ou aluguel)';
        }

        return [
            'valid' => empty($missing),
            'missing' => $missing
        ];
    }

    /**
     * Processar e validar imagens do imóvel
     *
     * @param array $imagens
     * @return array Imagens processadas e validadas
     */
    private function processPropertyImages(array $imagens): array
    {
        $processed = [];

        foreach ($imagens as $img) {
            $url = $img['url'] ?? null;

            if (!$url) {
                continue;
            }

            // Validar se URL é acessível (simplificado - não faz request real para não sobrecarregar)
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                Log::warning('URL de imagem inválida', ['url' => $url]);
                continue;
            }

            // Validar extensão da imagem
            $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
            $validExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array($extension, $validExtensions)) {
                Log::warning('Extensão de imagem não suportada', ['url' => $url, 'extension' => $extension]);
                continue;
            }

            $processed[] = [
                'url' => $url,
                'titulo' => $img['titulo'] ?? '',
                'destaque' => $img['destaque'] ?? false,
                'ordem' => $img['ordem'] ?? 999,
            ];
        }

        // Ordenar por destaque e depois por ordem
        usort($processed, function($a, $b) {
            if ($a['destaque'] != $b['destaque']) {
                return $b['destaque'] - $a['destaque']; // Destaque primeiro
            }
            return $a['ordem'] - $b['ordem'];
        });

        return $processed;
    }

    /**
     * Encontrar e remover imóveis duplicados
     *
     * @param int|null $tenantId
     * @return array Estatísticas de deduplicação
     */
    public function deduplicateProperties(?int $tenantId = null): array
    {
        Log::info('🔄 Iniciando deduplicação de imóveis', ['tenant_id' => $tenantId]);

        $query = Property::query();

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $properties = $query->get();
        $duplicates = [];
        $processed = [];

        foreach ($properties as $property) {
            if (in_array($property->id, $processed)) {
                continue;
            }

            // Buscar duplicatas por código
            $sameCodigo = $properties->where('codigo', $property->codigo)
                ->where('id', '!=', $property->id)
                ->pluck('id')
                ->toArray();

            if (!empty($sameCodigo)) {
                $duplicates[] = [
                    'master_id' => $property->id,
                    'master_codigo' => $property->codigo,
                    'duplicate_ids' => $sameCodigo,
                    'reason' => 'same_codigo'
                ];

                $processed[] = $property->id;
                $processed = array_merge($processed, $sameCodigo);
            }
        }

        // Remover duplicatas (manter o mais recente)
        $removed = 0;
        foreach ($duplicates as $duplicate) {
            foreach ($duplicate['duplicate_ids'] as $dupId) {
                try {
                    $dup = Property::find($dupId);
                    if ($dup) {
                        // Transferir matches antes de deletar
                        \App\Models\LeadPropertyMatch::where('property_id', $dupId)
                            ->update(['property_id' => $duplicate['master_id']]);

                        $dup->delete();
                        $removed++;

                        Log::info('Duplicata removida', [
                            'duplicate_id' => $dupId,
                            'master_id' => $duplicate['master_id'],
                            'codigo' => $duplicate['master_codigo']
                        ]);
                    }
                } catch (\Exception $e) {
                    Log::error('Erro ao remover duplicata', [
                        'duplicate_id' => $dupId,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        Log::info('✅ Deduplicação concluída', [
            'total_duplicates' => count($duplicates),
            'removed' => $removed
        ]);

        return [
            'success' => true,
            'total_duplicates' => count($duplicates),
            'removed' => $removed,
            'details' => $duplicates
        ];
    }

    /**
     * Sincronizar um imóvel específico com retry
     *
     * @param string $codigo
     * @param int $maxRetries
     * @return array|null
     */
    public function syncSingleProperty(string $codigo, int $maxRetries = 3): ?array
    {
        $attempt = 0;

        while ($attempt < $maxRetries) {
            try {
                $attempt++;

                Log::info("Sincronizando imóvel {$codigo} (tentativa {$attempt}/{$maxRetries})");

                $response = $this->callApi("/dados/{$codigo}");

                if (!isset($response['resultSet'])) {
                    throw new \Exception("Dados não encontrados para imóvel {$codigo}");
                }

                $imovel = $response['resultSet'];

                // Validar dados mínimos
                $mappedData = $this->mapPropertyData($imovel);
                $validation = $this->validateMinimalData($mappedData);

                if (!$validation['valid']) {
                    Log::warning('Imóvel com dados incompletos', [
                        'codigo' => $codigo,
                        'missing' => $validation['missing']
                    ]);

                    // Continuar mesmo com dados incompletos, mas registrar
                }

                // Verificar se já existe
                $existing = Property::where('codigo', $codigo)->first();

                if ($existing) {
                    $existing->update($mappedData);
                    Log::info("✏️ Imóvel {$codigo} atualizado");

                    return [
                        'success' => true,
                        'action' => 'updated',
                        'property_id' => $existing->id,
                        'codigo' => $codigo
                    ];
                } else {
                    $property = Property::create($mappedData);
                    Log::info("➕ Imóvel {$codigo} criado");

                    return [
                        'success' => true,
                        'action' => 'created',
                        'property_id' => $property->id,
                        'codigo' => $codigo
                    ];
                }

            } catch (\Exception $e) {
                Log::warning("Tentativa {$attempt} falhou para imóvel {$codigo}", [
                    'error' => $e->getMessage()
                ]);

                if ($attempt >= $maxRetries) {
                    Log::error("❌ Falha definitiva ao sincronizar imóvel {$codigo}", [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);

                    return null;
                }

                // Aguardar antes de retry
                sleep($attempt);
            }
        }

        return null;
    }

    /**
     * Validar e corrigir coordenadas geográficas
     *
     * @param int|null $tenantId
     * @return array
     */
    public function validateAndFixCoordinates(?int $tenantId = null): array
    {
        Log::info('🗺️ Validando coordenadas geográficas');

        $query = Property::query();

        if ($tenantId) {
            $query->where('tenant_id', $tenantId);
        }

        $properties = $query->whereNotNull('logradouro')
            ->whereNotNull('cidade')
            ->get();

        $stats = [
            'total' => $properties->count(),
            'valid' => 0,
            'invalid' => 0,
            'fixed' => 0,
            'failed' => 0
        ];

        foreach ($properties as $property) {
            $lat = $property->latitude;
            $lng = $property->longitude;

            if ($this->validCoordinates($lat, $lng)) {
                $stats['valid']++;
                continue;
            }

            $stats['invalid']++;

            // Tentar obter coordenadas
            try {
                $endereco = trim(implode(', ', array_filter([
                    $property->logradouro,
                    $property->bairro,
                    $property->cidade,
                    $property->estado
                ])));

                [$newLat, $newLng] = $this->geocode($endereco);

                if ($this->validCoordinates($newLat, $newLng)) {
                    $property->update([
                        'latitude' => $newLat,
                        'longitude' => $newLng
                    ]);

                    $stats['fixed']++;
                    Log::debug("Coordenadas corrigidas para {$property->codigo}");
                } else {
                    $stats['failed']++;
                }

            } catch (\Exception $e) {
                $stats['failed']++;
                Log::error("Erro ao corrigir coordenadas", [
                    'property_id' => $property->id,
                    'error' => $e->getMessage()
                ]);
            }

            // Respeitar rate limit
            usleep(500000); // 0.5 segundos
        }

        Log::info('✅ Validação de coordenadas concluída', $stats);

        return [
            'success' => true,
            'stats' => $stats
        ];
    }
}
