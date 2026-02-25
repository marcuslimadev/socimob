<?php

namespace App\Services;

use App\Models\Property;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ImobiBrasilService
{
    /**
     * Obter a URL base da API do Imobi Brasil
     * Usa a mesma URL que a importação de imóveis (api_url_externa)
     */
    public static function getBaseUrl(Tenant $tenant): string
    {
        // 1. Tentar usar api_url_externa (mesma que importação de imóveis usa)
        $url = $tenant->getIntegrationValue('api_url_externa');
        if ($url) {
            $url = rtrim($url, '/');
            // Adicionar /api/v1/app se ainda não tiver
            if (strpos($url, '/api/v1/app') === false && strpos($url, '/app') === false) {
                $url = $url . '/api/v1/app';
            }
            return $url;
        }

        // 2. Tentar URL configurada específica para Imobi Brasil
        if ($tenant->imobi_brasil_base_url && $tenant->imobi_brasil_base_url !== 'https://api.imobibrasil.com.br') {
            return rtrim($tenant->imobi_brasil_base_url, '/');
        }

        // 3. Tentar variável de ambiente específica do tenant
        $tenantName = strtoupper(str_replace(['-', ' ', '_'], '_', $tenant->name ?? ''));
        if ($tenantName && env($tenantName . '_IMOBI_BRASIL_BASE_URL')) {
            return rtrim(env($tenantName . '_IMOBI_BRASIL_BASE_URL'), '/');
        }

        // 4. Tentar variável de ambiente genérica
        if (env('IMOBI_BRASIL_BASE_URL')) {
            return rtrim(env('IMOBI_BRASIL_BASE_URL'), '/');
        }

        // 5. URL padrão
        return 'https://exclusivalarimoveis.com.br/api/v1/app';
    }

    /**
     * Obter a chave API do Imobi Brasil
     * Usa a mesma chave que a importação de imóveis (api_token_externa)
     */
    public static function getApiKey(Tenant $tenant): ?string
    {
        // 1. Tentar usar api_token_externa (mesma que importação de imóveis usa)
        $token = $tenant->getIntegrationValue('api_token_externa');
        if ($token) {
            return $token;
        }

        // 2. Tentar chave configurada no banco de dados do tenant
        if ($tenant->imobi_brasil_api_key) {
            return $tenant->imobi_brasil_api_key;
        }

        // 3. Tentar EXCLUSIVA_API_TOKEN (padrão para tenant padrão)
        if (env('EXCLUSIVA_API_TOKEN')) {
            return env('EXCLUSIVA_API_TOKEN');
        }

        // 4. Tentar variável de ambiente específica do tenant baseada no NOME
        $tenantName = strtoupper(str_replace(['-', ' ', '_'], '_', $tenant->name ?? ''));
        if ($tenantName && env($tenantName . '_API_TOKEN')) {
            return env($tenantName . '_API_TOKEN');
        }

        // 5. Tentar variável específica para Imobi Brasil
        if ($tenantName && env($tenantName . '_IMOBI_BRASIL_API_KEY')) {
            return env($tenantName . '_IMOBI_BRASIL_API_KEY');
        }

        // 6. Tentar variável genérica
        if (env('IMOBI_BRASIL_API_KEY')) {
            return env('IMOBI_BRASIL_API_KEY');
        }

        return null;
    }

    /**
     * Encontrar o codigoImovel de uma propriedade recém-criada pela referência
     * Usa a API /imovel/lista para buscar
     */
    private static function findPropertyCodeByReference(Property $property, Tenant $tenant, string $apiKey, string $baseUrl): ?int
    {
        try {
            $client = new \GuzzleHttp\Client([
                'verify' => false,
                'timeout' => 30,
                'http_errors' => false,
            ]);

            // Buscar primeira página com limite alto
            $response = $client->get($baseUrl . '/imovel/lista', [
                'headers' => [
                    'token' => $apiKey,
                    'Accept' => 'application/json',
                ],
                'query' => [
                    'page' => 1,
                    'limit' => 100,
                ],
            ]);

            $body = $response->getBody()->getContents();
            $data = json_decode($body, true);

            if (empty($data['status']) || empty($data['resultSet']['data'])) {
                Log::warning('Não conseguiu listar imóveis para encontrar código', [
                    'property_id' => $property->id,
                ]);
                return null;
            }

            $referencia = $property->codigo ?? ('PROP-' . $property->id);
            
            // Procurar por imóvel com essa referência, ordenando por data mais recente
            $matches = [];
            foreach ($data['resultSet']['data'] as $imovel) {
                if (($imovel['referenciaImovel'] ?? null) === $referencia) {
                    $matches[] = $imovel;
                }
            }

            if (empty($matches)) {
                Log::warning('Imóvel recém-criado não encontrado na lista', [
                    'property_id' => $property->id,
                    'referencia' => $referencia,
                ]);
                return null;
            }

            // Usar o imóvel mais recente (primeiro da lista está em ordem descrescente por data)
            $imovelMaisRecente = $matches[0];
            $codigoImovel = $imovelMaisRecente['codigoImovel'] ?? null;

            if ($codigoImovel) {
                Log::info('Código do imóvel recuperado', [
                    'property_id' => $property->id,
                    'codigo_imovel' => $codigoImovel,
                    'data_insercao' => $imovelMaisRecente['dataInsercaoImovel'] ?? null,
                ]);
            }

            return $codigoImovel;
        } catch (\Exception $e) {
            Log::error('Erro ao buscar código do imóvel', [
                'property_id' => $property->id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Verificar se a integração está habilitada para o tenant
     */
    public static function isEnabled(Tenant $tenant): bool
    {
        // Se está marcada como habilitada no banco, validar se tem chave
        if ($tenant->imobi_brasil_enabled) {
            return static::getApiKey($tenant) !== null;
        }

        // Caso contrário, verificar se há chave configurada via ambiente
        return static::getApiKey($tenant) !== null;
    }

    /**
     * Enviar imóvel para Imobi Brasil
     * Se já foi enviado, atualiza o existente em vez de duplicar
     */
    public static function sendProperty(Property $property, Tenant $tenant): array
    {
        try {
            // Se já foi enviado antes, usar atualização
            if ($property->imobi_brasil_sent && $property->imobi_brasil_external_id) {
                Log::info('Imóvel já foi enviado, usando atualização em vez de inserção', [
                    'property_id' => $property->id,
                    'external_id' => $property->imobi_brasil_external_id,
                ]);
                return self::updateProperty($property, $tenant);
            }

            // Validar configuração do tenant
            $apiKey = static::getApiKey($tenant);
            if (!$apiKey) {
                return [
                    'success' => false,
                    'error' => 'Integração Imobi Brasil não configurada para este tenant',
                ];
            }

            $baseUrl = rtrim(static::getBaseUrl($tenant), '/');

            // Verificar se já existe por referência na API (evita duplicatas caso
            // imobi_brasil_sent tenha sido zerado acidentalmente)
            $existingId = self::findPropertyCodeByReference($property, $tenant, $apiKey, $baseUrl);
            if ($existingId) {
                Log::info('Imóvel já existe no Imobi Brasil pela referência, usando atualização', [
                    'property_id' => $property->id,
                    'external_id' => $existingId,
                ]);
                $property->update([
                    'imobi_brasil_sent' => true,
                    'imobi_brasil_external_id' => $existingId,
                    'imobi_brasil_error' => null,
                ]);
                $property->refresh();
                return self::updateProperty($property, $tenant);
            }

            // Preparar dados do imóvel
            $payload = self::preparePropertyPayload($property, $apiKey, $baseUrl);

            Log::info('Enviando novo imóvel para Imobi Brasil', [
                'property_id' => $property->id,
                'tenant_id' => $tenant->id,
                'base_url' => $baseUrl,
                'endpoint' => $baseUrl . '/imovel/inserir',
            ]);

            // Chamar API real do Imobi Brasil
            $client = new \GuzzleHttp\Client([
                'verify' => false,
                'timeout' => 30,
                'http_errors' => false,
            ]);

            $response = $client->post($baseUrl . '/imovel/inserir', [
                'headers' => [
                    'token' => $apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            Log::info('Resposta da API Imobi Brasil (inserção)', [
                'property_id' => $property->id,
                'status_code' => $statusCode,
                'body_preview' => substr($body, 0, 500),
            ]);

            // Tentar parsear resposta JSON
            $data = json_decode($body, true) ?: [];

            // Se for sucesso (API retorna status=true)
            if (!empty($data['status'])) {
                // API retornou sucesso, mas precisa buscar o codigoImovel
                // Pois a resposta do POST não retorna o código
                $externalId = self::findPropertyCodeByReference($property, $tenant, $apiKey, $baseUrl);

                if ($externalId) {
                    // Atualizar imóvel com informações do servidor
                    $property->update([
                        'imobi_brasil_sent' => true,
                        'imobi_brasil_sent_at' => \Carbon\Carbon::now(),
                        'imobi_brasil_external_id' => $externalId,
                        'imobi_brasil_error' => null,
                    ]);

                    Log::info('Imóvel enviado com sucesso para Imobi Brasil', [
                        'property_id' => $property->id,
                        'external_id' => $externalId,
                    ]);

                    return [
                        'success' => true,
                        'message' => 'Imóvel enviado com sucesso para Imobi Brasil',
                        'external_id' => $externalId,
                        'api_response' => $data,
                    ];
                } else {
                    // Se não conseguiu encontrar o código, registrar erro
                    return [
                        'success' => false,
                        'error' => 'Imóvel criado mas código não foi recuperado da API',
                    ];
                }
            }

            // Se não foi sucesso, registrar erro
            $errorMsg = $data['message'] ?? ('API retornou HTTP ' . $statusCode . ': ' . substr($body, 0, 200));
            
            $property->update([
                'imobi_brasil_error' => substr($errorMsg, 0, 500),
            ]);

            return [
                'success' => false,
                'error' => $errorMsg,
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao enviar imóvel para Imobi Brasil', [
                'property_id' => $property->id,
                'error' => $e->getMessage(),
            ]);

            $property->update([
                'imobi_brasil_error' => substr($e->getMessage(), 0, 500),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Atualizar imóvel no Imobi Brasil
     * Chamado quando imóvel já foi enviado e estamos atualizando dados
     */
    public static function updateProperty(Property $property, Tenant $tenant): array
    {
        try {
            // Validar se já foi enviado
            if (!$property->imobi_brasil_sent || !$property->imobi_brasil_external_id) {
                return [
                    'success' => false,
                    'error' => 'Imóvel não foi enviado para Imobi Brasil ainda. Use sendProperty primeiro.',
                ];
            }

            $apiKey = static::getApiKey($tenant);
            if (!$apiKey) {
                return [
                    'success' => false,
                    'error' => 'Integração Imobi Brasil não configurada',
                ];
            }

            // Preparar payload atualizado com codigoTipoImovel obrigatório
            $baseUrl = rtrim(static::getBaseUrl($tenant), '/');
            $payload = self::preparePropertyPayload($property, $apiKey, $baseUrl);
            
            // IMPORTANTE: API requer codigoTipoImovel para update
            // Usar tipo CASA (1) como padrão se não houver informação
            if (empty($payload['codigoTipoImovel'])) {
                $payload['codigoTipoImovel'] = 1; // Casa como padrão
            }

            // Endpoint para alteração: POST /imovel/alterar/{codigoImovel}
            // NOTA: API usa POST (não PUT) para alterações
            $externalId = $property->imobi_brasil_external_id;
            $endpoint = $baseUrl . '/imovel/alterar/' . urlencode($externalId);

            Log::info('Atualizando imóvel no Imobi Brasil', [
                'property_id' => $property->id,
                'external_id' => $externalId,
                'endpoint' => $endpoint,
                'tipo_imovel' => $payload['codigoTipoImovel'] ?? 'default',
            ]);

            // Fazer requisição com Guzzle
            $client = new \GuzzleHttp\Client([
                'verify' => false,
                'timeout' => 30,
                'http_errors' => false,
            ]);

            // IMPORTANTE: Usar POST (não PUT) para update
            $response = $client->post($endpoint, [
                'headers' => [
                    'token' => $apiKey,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            Log::info('Resposta da API Imobi Brasil (atualização)', [
                'property_id' => $property->id,
                'status_code' => $statusCode,
                'body_preview' => substr($body, 0, 500),
            ]);

            // Parsear resposta
            $data = json_decode($body, true) ?: [];

            // Verificar se foi sucesso
            if (!empty($data['status']) || $statusCode === 200 || $statusCode === 201) {
                // Atualizar timestamp de última sincronização
                $property->update([
                    'imobi_brasil_sent_at' => \Carbon\Carbon::now(),
                    'imobi_brasil_error' => null,
                ]);

                Log::info('Imóvel atualizado com sucesso no Imobi Brasil', [
                    'property_id' => $property->id,
                    'external_id' => $externalId,
                ]);

                return [
                    'success' => true,
                    'message' => 'Imóvel atualizado com sucesso no Imobi Brasil',
                    'external_id' => $externalId,
                    'api_response' => $data,
                ];
            }

            // Se não foi sucesso
            $errorMsg = $data['message'] ?? ('API retornou HTTP ' . $statusCode);
            
            $property->update([
                'imobi_brasil_error' => substr($errorMsg, 0, 500),
            ]);

            Log::warning('Erro ao atualizar imóvel no Imobi Brasil', [
                'property_id' => $property->id,
                'external_id' => $externalId,
                'error' => $errorMsg,
            ]);

            return [
                'success' => false,
                'error' => $errorMsg,
            ];
        } catch (\Exception $e) {
            Log::error('Exceção ao atualizar imóvel no Imobi Brasil', [
                'property_id' => $property->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $property->update([
                'imobi_brasil_error' => substr($e->getMessage(), 0, 500),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Preparar payload do imóvel para envio
     * Conforme estrutura esperada pela API Imobi Brasil
     */
    /**
     * Busca o codigoCidade na API do Imobi Brasil dado nome cidade + sigla estado.
     * Usa cache para evitar múltiplas requisições à API.
     * A busca começa na página estimada para o primeiro caractere do nome (A~pag 1, Z~pag 280),
     * depois percorre para frente até o final e, se necessário, do início até a página estimada.
     */
    public static function findCodigoCidade(string $nomeCidade, string $siglaEstado, string $apiKey, string $baseUrl): int
    {
        if (empty(trim($nomeCidade)) || empty(trim($siglaEstado))) {
            return 0;
        }

        $targetNome   = strtolower(trim($nomeCidade));
        $targetEstado = strtoupper(trim($siglaEstado));
        $cacheKey     = 'imobi_cidade_' . md5($targetNome . '_' . $targetEstado);

        if (\Illuminate\Support\Facades\Cache::has($cacheKey)) {
            return (int) \Illuminate\Support\Facades\Cache::get($cacheKey, 0);
        }

        // Estimativa de página inicial baseada na primeira letra (A=pág 1, Z≈pág 280)
        $firstLetter = strtoupper(substr($targetNome, 0, 1));
        $letterIndex = max(0, ord($firstLetter) - ord('A')); // 0-25
        $totalPages  = 280;
        $startPage   = max(1, (int) round($totalPages * ($letterIndex / 26)));

        // Ordem de busca: do estimado até o final, depois do início até estimado-1
        $searchPages = array_merge(
            range($startPage, $totalPages),
            range(1, $startPage - 1)
        );

        $client = new \GuzzleHttp\Client([
            'verify'      => false,
            'timeout'     => 15,
            'http_errors' => false,
        ]);

        $inFirstHalf = true; // true quando estamos na fatia [$startPage..$totalPages]

        foreach ($searchPages as $page) {
            // Detectar quando passamos do wrap
            if ($inFirstHalf && $page < $startPage) {
                $inFirstHalf = false;
            }

            try {
                $response = $client->get($baseUrl . '/cidade/lista', [
                    'headers' => ['token' => $apiKey, 'Accept' => 'application/json'],
                    'query'   => ['page' => $page],
                ]);

                $json   = json_decode($response->getBody()->getContents(), true);
                $cities = $json['data'] ?? (is_array($json) ? $json : []);

                if (empty($cities)) {
                    break; // sem mais páginas
                }

                // Varrer a página em busca da cidade
                foreach ($cities as $c) {
                    if (
                        strtolower($c['nomeCidade'] ?? '') === $targetNome &&
                        strtoupper($c['siglaEstado'] ?? '') === $targetEstado
                    ) {
                        $codigo = (int) $c['codigoCidade'];
                        \Illuminate\Support\Facades\Cache::put($cacheKey, $codigo, Carbon::now()->addDays(30));
                        Log::info('codigoCidade encontrado', [
                            'cidade'  => $nomeCidade,
                            'estado'  => $siglaEstado,
                            'codigo'  => $codigo,
                            'page'    => $page,
                        ]);
                        return $codigo;
                    }
                }

                // Otimização alfabética: evita percorrer páginas desnecessárias
                $firstOnPage = strtolower($cities[0]['nomeCidade'] ?? '');
                $lastOnPage  = strtolower(end($cities)['nomeCidade'] ?? '');

                if ($inFirstHalf) {
                    // Estamos indo para frente a partir de $startPage
                    // Se a primeira cidade da página já é alfabeticamente maior que o alvo,
                    // o alvo não existe nesta direção — encerra
                    if ($firstOnPage > $targetNome) {
                        break;
                    }
                } else {
                    // Estamos no trecho 1..$startPage-1 (busca de fallback)
                    // Pular páginas onde o último item ainda é menor que o alvo
                    if ($lastOnPage < $targetNome) {
                        continue;
                    }
                    // Se o primeiro item já passou do alvo, parar (não vai aparecer)
                    if ($firstOnPage > $targetNome) {
                        break;
                    }
                }

            } catch (\Exception $e) {
                Log::warning('Erro ao buscar codigoCidade', [
                    'cidade' => $nomeCidade,
                    'estado' => $siglaEstado,
                    'page'   => $page,
                    'error'  => $e->getMessage(),
                ]);
                break;
            }
        }

        // Não encontrado — cachear miss por 1 dia
        \Illuminate\Support\Facades\Cache::put($cacheKey, 0, Carbon::now()->addDay());
        Log::info('codigoCidade não encontrado', ['cidade' => $nomeCidade, 'estado' => $siglaEstado]);
        return 0;
    }

    public static function preparePropertyPayload(Property $property, string $apiKey = '', string $baseUrl = ''): array
    {
        // Mapear tipo de imóvel para código
        $tipoImovelMapping = [
            'apartamento' => 1,
            'casa' => 2,
            'sala' => 3,
            'loja' => 4,
            'terreno' => 5,
            'kitnet' => 6,
            'chacara' => 7,
            'sitio' => 8,
            'galpao' => 9,
            'conjunto' => 10,
        ];
        
        $codigoTipoImovel = $tipoImovelMapping[strtolower($property->tipo_imovel ?? 'apartamento')] ?? 1;

        // Mapear finalidade para código inteiro conforme especificação da API
        // 0 = Venda, 1 = Locação/Aluguel, 2 = Temporada
        $finalidadeMapping = [
            'venda'     => 0,
            'locacao'   => 1,
            'aluguel'   => 1,
            'temporada' => 2,
        ];
        $finalidadeStr = strtolower($property->finalidade_imovel ?? 'venda');
        $finalidade = $finalidadeMapping[$finalidadeStr] ?? 0;

        // Estrutura exata conforme a API espera
        return [
            'codigoProprietario' => 0,
            'codigoCorretor' => 0,
            'codigoUsuarioAdicional' => 0,
            'finalidade' => $finalidade,
            'codigoTipoImovel' => $codigoTipoImovel,
            'referencia' => $property->codigo ?? ('PROP-' . $property->id),
            'cep' => $property->cep ?? '',
            'bairro' => $property->bairro ?? '',
            'logradouro' => $property->logradouro ?? '',
            'codigoCidade' => ($apiKey && $baseUrl)
                ? self::findCodigoCidade(
                    $property->cidade ?? '',
                    $property->estado ?? '',
                    $apiKey,
                    $baseUrl
                  )
                : 0,
            'numero' => $property->numero ?? '',
            'pontoReferencia' => '',
            'complemento' => $property->complemento ?? '',
            'mapa' => $property->latitude && $property->longitude ? 'sim' : 'nao',
            'zona' => $property->zona ?? '',
            'regiao' => $property->regiao ?? '',
            'exibirEnderecoSite' => 'sim',
            'exibirEnderecoSitePersonalizado' => [
                [
                    'cep' => true,
                    'logradouro' => true,
                    'numero' => true,
                    'complemento' => true,
                    'zona' => true,
                    'regiao' => true,
                    'pontoReferencia' => true,
                    'nomeCondominio' => true,
                ]
            ],
            'exibirEnderecoPortalPersonalizado' => [
                [
                    'cep' => true,
                    'logradouro' => true,
                    'numero' => true,
                    'complemento' => true,
                    'zona' => true,
                    'regiao' => true,
                    'pontoReferencia' => true,
                    'nomeCondominio' => true,
                ]
            ],
            'areaPrivativa' => (string) ($property->area_privativa ?? ''),
            'tipoAreaPrivativa' => 0,
            'areaTotal' => (string) ($property->area_total ?? ''),
            'tipoAreaTotal' => 0,
            'areaTerreno' => (string) ($property->area_terreno ?? ''),
            'tipoAreaTerreno' => 0,
            'areaConstruida' => (string) ($property->area_construida ?? ''),
            'tipoareaConstruida' => 0,
            'dormitorios' => (int) ($property->dormitorios ?? 0),
            'suites' => (int) ($property->suites ?? 0),
            'banheiros' => (int) ($property->banheiros ?? 0),
            'salas' => (int) ($property->salas ?? 0),
            'garagem' => (int) ($property->garagem ?? 0),
            'acomodacoes' => (int) ($property->acomodacoes ?? 0),
            'pavimento' => $property->pavimento ?? '',
            'nomeCondominio' => $property->nome_condominio ?? '',
            'anoConstrucao' => $property->ano_construcao ?? '',
            'mobiliado' => ($property->mobiliado ?? false) ? 'sim' : 'nao',
            'descricaoImovel' => $property->titulo ?? '',
            'observacaoImovel' => $property->observacao ?? '',
            'pontosFortesImovel' => $property->pontos_fortes ?? '',
            'outrasInformacoesImovel' => $property->outras_informacoes ?? '',
            'destaqueInicial' => ($property->destaque ?? false) ? 'sim' : 'nao',
            'destaquesSuperDestaqueInicial' => ($property->super_destaque ?? false) ? 'sim' : 'nao',
            'valorImovel' => (int) ($property->valor_venda ?? 0),
            'valorIPTU' => (int) ($property->valor_iptu ?? 0),
            'valorCondominio' => (int) ($property->valor_condominio ?? 0),
            'valorObservacao' => $property->valor_observacao ?? '',
            'valorTaxas' => (int) ($property->valor_taxas ?? 0),
            'exibirImovel' => $property->active ? 'sim' : 'nao',
            'video' => $property->video ?? '',
            'tarjaImagem' => $property->tarja_imagem ?? '',
            'unidade' => $property->unidade ?? '',
            'torreUnica' => $property->torre_unica ?? '',
            'tratarEmpreendimento' => ($property->tratar_empreendimento ?? false) ? 'sim' : 'nao',
            'numeroAndar' => (int) ($property->numero_andar ?? 0),
            'numeroTorre' => (int) ($property->numero_torre ?? 0),
            'nomeEmpreendimento' => $property->nome_empreendimento ?? '',
            'descricaoEmpreendimento' => $property->descricao_empreendimento ?? '',
            'estagioEmpreendimento' => (int) ($property->estagio_empreendimento ?? 0),
            'inicioPrevisaoEmpreendimento' => $property->inicio_previsao_empreendimento ?? '',
            'entregaPrevisaoEmpreendimento' => $property->entrega_previsao_empreendimento ?? '',
            'disponibilizarExportacao' => 'sim',
            'exibirCorretor' => 'sim',
            'terrenoFrente' => $property->terreno_frente ?? '',
            'tipoTerrenoFrente' => 0,
            'terrenoFundo' => $property->terreno_fundo ?? '',
            'tipoTerrenoFundo' => 0,
            'terrenoEsquerda' => $property->terreno_esquerda ?? '',
            'tipoTerrenoEsquerda' => 0,
            'terrenoDireita' => $property->terreno_direita ?? '',
            'tipoTerrenoDireita' => 0,
            'emCondominio' => $property->em_condominio ? 'sim' : 'nao',
            'exclusividade' => ($property->exclusividade ?? false) ? 'sim' : 'nao',
            'autorizacao' => ($property->autorizacao ?? false) ? 'sim' : 'nao',
            'averbada' => ($property->averbada ?? false) ? 'sim' : 'nao',
            'escriturada' => ($property->escriturada ?? false) ? 'sim' : 'nao',
            'aceitaFinanciamento' => ($property->aceita_financiamento ?? false) ? 'sim' : 'nao',
            'comPlaca' => ($property->com_placa ?? false) ? 'sim' : 'nao',
            'permuta' => ($property->permuta ?? false) ? 'sim' : 'nao',
            'disponibilidade' => (int) ($property->disponibilidade ?? 0),
            'localChave' => $property->local_chave ?? '',
            'origemCaptacao' => $property->origem_captacao ?? '',
            'tourVirtual' => $property->tour_virtual ?? '',
            'revisaoData' => \Carbon\Carbon::now()->format('Y-m-d'),
            'scriptsPersonalizados' => $property->scripts_personalizados ?? '',
            'seoURL' => $property->seo_url ?? '',
            'seoTitulo' => $property->seo_titulo ?? '',
            'seoDescricao' => $property->seo_descricao ?? '',
            'dispararPeriodico' => 'sim',
            'portaisDivulgarConvencional' => [
                [
                    'VivaReal' => true,
                    'ImovelWeb' => true,
                    '123i' => true,
                    'ZapImoveis' => true,
                    'OLX' => true,
                    'ChaveNaMao' => true,
                    'Moving' => true,
                    'SPImovel' => true,
                    'CasaMineira' => true,
                    '018Imoveis' => true,
                    'DreamCasa' => true,
                    'Imoveis014' => true,
                    '016Imoveis' => true,
                    'OlhoMagico' => true,
                    'OruloExclusividades' => true,
                    'Lopes' => true,
                    'FacebookAds' => true,
                    'Buskaza' => true,
                ]
            ],
            'portaisDivulgarDestaque' => [
                [
                    'VivaReal' => true,
                    'ImovelWeb' => true,
                    '123i' => true,
                    'ZapImoveis' => true,
                    'OLX' => true,
                    'ChaveNaMao' => true,
                    'Moving' => true,
                    'SPImovel' => true,
                    'CasaMineira' => true,
                    '018Imoveis' => true,
                    'DreamCasa' => true,
                    'Imoveis014' => true,
                    '016Imoveis' => true,
                    'OlhoMagico' => true,
                    'OruloExclusividades' => true,
                    'Lopes' => true,
                    'FacebookAds' => true,
                    'Buskaza' => true,
                ]
            ],
            'portaisDivulgarSuperDestaque' => [
                [
                    'VivaReal' => true,
                    'ImovelWeb' => true,
                    '123i' => true,
                    'ZapImoveis' => true,
                    'OLX' => true,
                    'ChaveNaMao' => true,
                    'Moving' => true,
                    'SPImovel' => true,
                    'CasaMineira' => true,
                    '018Imoveis' => true,
                    'DreamCasa' => true,
                    'Imoveis014' => true,
                    '016Imoveis' => true,
                    'OlhoMagico' => true,
                    'OruloExclusividades' => true,
                    'Lopes' => true,
                    'FacebookAds' => true,
                    'Buskaza' => true,
                ]
            ],
            'portaisDivulgarSuperDestaque2' => [
                [
                    'VivaReal' => [
                        [
                            'tipo' => 'PREMIERE_1',
                        ]
                    ]
                ]
            ],
        ];
    }

    /**
     * Enviar imagens da propriedade para Imobi Brasil
     * Endpoint: POST /imovel/{codigoImovel}/imagem/inserir
     */
    public static function sendPropertyImages(Property $property, Tenant $tenant): array
    {
        try {
            // Validar se propriedade foi enviada
            if (!$property->imobi_brasil_sent || !$property->imobi_brasil_external_id) {
                return [
                    'success' => false,
                    'error' => 'Propriedade não foi enviada para Imobi Brasil ainda',
                    'images_sent' => 0,
                ];
            }

            $apiKey = static::getApiKey($tenant);
            if (!$apiKey) {
                return [
                    'success' => false,
                    'error' => 'Integração Imobi Brasil não configurada',
                    'images_sent' => 0,
                ];
            }

            // Buscar imagens locais - primeiro do imoveis_imagens, depois fallback para JSON
            $imagensDb = \App\Models\ImovelImagem::where('codigo', $property->codigo)
                ->orderBy('destaque', 'desc')
                ->get();

            // Montar lista de URLs de imagem (apenas imagens, sem vídeos)
            $imageUrls = [];
            $videoExtensions = ['mp4', 'avi', 'mov', 'wmv', 'webm', 'mkv'];

            if ($imagensDb->isNotEmpty()) {
                // Usar tabela imoveis_imagens
                foreach ($imagensDb as $img) {
                    $ext = strtolower(pathinfo(parse_url($img->url, PHP_URL_PATH), PATHINFO_EXTENSION));
                    if (!in_array($ext, $videoExtensions)) {
                        $imageUrls[] = $img->url;
                    }
                }
            } else {
                // Fallback: usar JSON imagens do model
                $rawImagens = $property->imagens;
                if (is_array($rawImagens)) {
                    foreach ($rawImagens as $url) {
                        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
                        if (!in_array($ext, $videoExtensions)) {
                            $imageUrls[] = $url;
                        }
                    }
                }
            }

            if (empty($imageUrls)) {
                return [
                    'success' => true,
                    'message' => 'Nenhuma imagem para enviar',
                    'images_sent' => 0,
                    'images_total' => 0,
                    'errors' => [],
                ];
            }

            // Usar $imageUrls como fonte de dados
            $imagens = collect($imageUrls);

            $baseUrl = static::getBaseUrl($tenant);
            $baseUrl = rtrim($baseUrl, '/');
            $codigoImovel = $property->imobi_brasil_external_id;

            Log::info('Iniciando sincronização de imagens para Imobi Brasil', [
                'property_id' => $property->id,
                'codigo_imovel' => $codigoImovel,
                'total_images' => $imagens->count(),
            ]);

            $client = new \GuzzleHttp\Client([
                'verify' => false,
                'timeout' => 60,
                'http_errors' => false,
            ]);

            $sucessos = 0;
            $erros = [];

            // Enviar cada imagem
            foreach ($imagens as $index => $imagemUrl) {
                try {
                    $endpoint = $baseUrl . '/imovel/' . $codigoImovel . '/imagem/inserir';

                    // Enviar imagem como URL string
                    $response = $client->post($endpoint, [
                        'headers' => [
                            'token' => $apiKey,
                        ],
                        'multipart' => [
                            [
                                'name' => 'codigoImovel',
                                'contents' => (string)$codigoImovel,
                            ],
                            [
                                'name' => 'imagem',
                                'contents' => $imagemUrl,
                            ],
                        ],
                    ]);

                    $statusCode = $response->getStatusCode();
                    $body = $response->getBody()->getContents();
                    $responseData = json_decode($body, true) ?: [];

                    if ($statusCode === 200 && ($responseData['status'] ?? false)) {
                        $sucessos++;

                        Log::info('Imagem enviada com sucesso para Imobi Brasil', [
                            'property_id' => $property->id,
                            'image_index' => $index,
                            'image_url' => $imagemUrl,
                        ]);
                    } else {
                        $erro = $responseData['message'] ?? "HTTP $statusCode";
                        $erros[] = "Imagem $index: $erro";

                        Log::warning('Erro ao enviar imagem para Imobi Brasil', [
                            'property_id' => $property->id,
                            'image_index' => $index,
                            'image_url' => $imagemUrl,
                            'status' => $statusCode,
                            'error' => $erro,
                        ]);
                    }
                } catch (\Exception $e) {
                    $erros[] = "Imagem $index: {$e->getMessage()}";

                    Log::error('Exceção ao enviar imagem para Imobi Brasil', [
                        'property_id' => $property->id,
                        'image_index' => $index,
                        'image_url' => $imagemUrl,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Atualizar timestamp de sincronização de imagens
            $property->update([
                'imobi_brasil_images_sent_at' => \Carbon\Carbon::now(),
            ]);

            Log::info('Sincronização de imagens concluída', [
                'property_id' => $property->id,
                'images_sent' => $sucessos,
                'total_images' => $imagens->count(),
                'errors' => count($erros),
            ]);

            return [
                'success' => $sucessos > 0,
                'message' => "$sucessos de {$imagens->count()} imagens enviadas com sucesso",
                'images_sent' => $sucessos,
                'images_total' => $imagens->count(),
                'errors' => $erros,
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao sincronizar imagens com Imobi Brasil', [
                'property_id' => $property->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'images_sent' => 0,
            ];
        }
    }
}
