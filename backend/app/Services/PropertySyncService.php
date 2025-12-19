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
            throw new \Exception('EXCLUSIVA_API_TOKEN não configurado no .env');
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
                        $existing = Property::where('codigo_imovel', $codigo)->first();
                        
                        $data = $this->mapPropertyData($imovel);
                        
                        // Contar imagens para logging
                        $numImagens = 0;
                        if (isset($data['imagens'])) {
                            $imagensArray = json_decode($data['imagens'], true);
                            $numImagens = is_array($imagensArray) ? count($imagensArray) : 0;
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
        
        // Preparar dados de imagens - formato completo
        $imagensData = [];
        if (!empty($imovel['imagens']) && is_array($imovel['imagens'])) {
            foreach ($imovel['imagens'] as $img) {
                if (isset($img['url'])) {
                    $imagensData[] = [
                        'url' => $img['url'],
                        'destaque' => isset($img['destaque']) ? (bool)$img['destaque'] : false
                    ];
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
        
        return [
            'codigo_imovel' => $imovel['codigoImovel'],
            'referencia_imovel' => $imovel['referenciaImovel'] ?? null,
            'finalidade_imovel' => $imovel['finalidadeImovel'] ?? null,
            'tipo_imovel' => $imovel['descricaoTipoImovel'] ?? null,
            'descricao' => $this->formatDescriptionWithAI($imovel['descricaoImovel'] ?? null),
            'dormitorios' => intval($imovel['dormitorios'] ?? 0),
            'suites' => intval($imovel['suites'] ?? 0),
            'banheiros' => intval($imovel['banheiros'] ?? 0),
            'garagem' => intval($imovel['garagem'] ?? 0),
            'valor_venda' => $imovel['valorEsperado'] ?? 0,
            'valor_iptu' => $imovel['valorIPTU'] ?? 0,
            'valor_condominio' => $imovel['valorCondominio'] ?? 0,
            'cidade' => $imovel['endereco']['cidade'] ?? null,
            'estado' => $imovel['endereco']['estado'] ?? null,
            'bairro' => $imovel['endereco']['bairro'] ?? null,
            'logradouro' => $imovel['endereco']['logradouro'] ?? null,
            'numero' => $imovel['endereco']['numero'] ?? null,
            'complemento' => $imovel['endereco']['complemento'] ?? null,
            'cep' => $imovel['endereco']['cep'] ?? null,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'area_privativa' => $areaPrivativa,
            'area_total' => $areaTotal,
            'area_terreno' => $areaTerreno,
            'imagem_destaque' => $imagemDestaque,
            'imagens' => json_encode($imagensData),
            'caracteristicas' => json_encode($caracteristicas),
            'em_condominio' => isset($imovel['emCondominio']) ? ($imovel['emCondominio'] ? 1 : 0) : 0,
            'exclusividade' => isset($imovel['exclusividade']) ? ($imovel['exclusividade'] ? 1 : 0) : 0,
            'exibir_imovel' => 1, // Se está na API, deve ser exibido
            'active' => 1, // Se está na API, está ativo
            'api_data' => json_encode($imovel),
            'api_created_at' => $this->parseApiDateTime($imovel['dataInsercaoImovel'] ?? null),
            'api_updated_at' => $this->parseApiDateTime($imovel['ultimaAtualizacaoImovel'] ?? null)
        ];
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
}
