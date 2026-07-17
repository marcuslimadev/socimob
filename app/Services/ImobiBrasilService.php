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
    private static function reclaimExternalIdIfStale(Property $property, string|int $externalId): void
    {
        $externalId = trim((string) $externalId);
        if ($externalId === '') {
            return;
        }

        $conflictingProperty = Property::withTrashed()
            ->where('tenant_id', $property->tenant_id)
            ->where('imobi_brasil_external_id', $externalId)
            ->where('id', '!=', $property->id)
            ->first();

        if (!$conflictingProperty) {
            return;
        }

        $sameCode = collect([
            $property->codigo,
            $property->codigo_imovel,
            $property->referencia_imovel,
        ])->filter()->contains(function ($value) use ($conflictingProperty) {
            return in_array($value, array_filter([
                $conflictingProperty->codigo,
                $conflictingProperty->codigo_imovel,
                $conflictingProperty->referencia_imovel,
            ]), true);
        });

        if ($conflictingProperty->trashed() && $sameCode) {
            Log::warning('Reivindicando external_id Imobi Brasil preso em imóvel na lixeira', [
                'external_id' => $externalId,
                'current_property_id' => $property->id,
                'conflicting_property_id' => $conflictingProperty->id,
                'codigo' => $property->codigo,
            ]);

            $conflictingProperty->forceFill([
                'imobi_brasil_sent' => false,
                'imobi_brasil_sent_at' => null,
                'imobi_brasil_external_id' => null,
                'imobi_brasil_error' => 'External ID reassociado ao imóvel ativo #' . $property->id,
            ])->save();

            return;
        }

        throw new \RuntimeException(sprintf(
            'Código Imobi Brasil %s já está vinculado ao imóvel #%d (%s).',
            $externalId,
            $conflictingProperty->id,
            $conflictingProperty->codigo ?: $conflictingProperty->codigo_imovel ?: 'sem-codigo'
        ));
    }

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
            // Se já tem external_id (independente do flag sent), usar atualização
            if ($property->imobi_brasil_external_id) {
                Log::info('Imóvel já tem external_id, usando atualização em vez de inserção', [
                    'property_id' => $property->id,
                    'external_id' => $property->imobi_brasil_external_id,
                ]);
                // Garantir que sent=true no DB
                if (!$property->imobi_brasil_sent) {
                    $property->update(['imobi_brasil_sent' => true]);
                    $property->refresh();
                }
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
                self::reclaimExternalIdIfStale($property, $existingId);
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
                    self::reclaimExternalIdIfStale($property, $externalId);

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
                // Atualizar timestamp de última sincronização + garantir sent=true
                $property->update([
                    'imobi_brasil_sent' => true,
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

            // Se o código do imóvel não existe mais no Imobi Brasil (foi deletado),
            // limpar os campos e reinserir automaticamente
            if (
                str_contains(mb_strtolower($errorMsg), 'c') &&
                (
                    str_contains(mb_strtolower($errorMsg), 'código de imóvel válido') ||
                    str_contains(mb_strtolower($errorMsg), 'codigo de imovel valido') ||
                    str_contains($errorMsg, 'código de imóvel válido') ||
                    str_contains($errorMsg, 'Informe um código de imóvel válido')
                )
            ) {
                Log::warning('Código de imóvel inválido no Imobi Brasil - reinserindo como novo', [
                    'property_id' => $property->id,
                    'old_external_id' => $externalId,
                ]);

                $property->update([
                    'imobi_brasil_sent' => false,
                    'imobi_brasil_external_id' => null,
                    'imobi_brasil_error' => null,
                    'imobi_brasil_sent_at' => null,
                ]);
                $property->refresh();

                return self::sendProperty($property, $tenant);
            }

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
            $cached = (int) \Illuminate\Support\Facades\Cache::get($cacheKey, 0);
            if ($cached > 0) return $cached;
        }

        $client = new \GuzzleHttp\Client([
            'verify'      => false,
            'timeout'     => 15,
            'http_errors' => false,
        ]);

        // Estratégia 1: usar filtro ?cidade= (retorna resultados diretos, 1 request)
        try {
            $response = $client->get($baseUrl . '/cidade/lista', [
                'headers' => ['token' => $apiKey, 'Accept' => 'application/json'],
                'query'   => ['cidade' => trim($nomeCidade)],
            ]);
            $json   = json_decode($response->getBody()->getContents(), true);
            $cities = $json['resultSet']['data'] ?? $json['data'] ?? [];
            foreach ($cities as $c) {
                if (
                    strtolower($c['nomeCidade'] ?? '') === $targetNome &&
                    strtoupper($c['siglaEstado'] ?? '') === $targetEstado
                ) {
                    $codigo = (int) $c['codigoCidade'];
                    \Illuminate\Support\Facades\Cache::put($cacheKey, $codigo, Carbon::now()->addDays(30));
                    Log::info('codigoCidade encontrado via filtro', ['cidade' => $nomeCidade, 'estado' => $siglaEstado, 'codigo' => $codigo]);
                    return $codigo;
                }
            }
        } catch (\Exception $e) {
            Log::warning('Erro no filtro cidade', ['error' => $e->getMessage()]);
        }

        // Estratégia 2: paginação com estimativa alphabética (fallback)
        $firstLetter = strtoupper(substr($targetNome, 0, 1));
        $letterIndex = max(0, ord($firstLetter) - ord('A'));
        $totalPages  = 280;
        $startPage   = max(1, (int) round($totalPages * ($letterIndex / 26)));
        $searchPages = array_merge(range($startPage, $totalPages), range(1, $startPage - 1));

        foreach ($searchPages as $page) {
            try {
                $response = $client->get($baseUrl . '/cidade/lista', [
                    'headers' => ['token' => $apiKey, 'Accept' => 'application/json'],
                    'query'   => ['page' => $page],
                ]);
                $json   = json_decode($response->getBody()->getContents(), true);
                $cities = $json['resultSet']['data'] ?? $json['data'] ?? [];
                if (empty($cities)) break;

                foreach ($cities as $c) {
                    if (
                        strtolower($c['nomeCidade'] ?? '') === $targetNome &&
                        strtoupper($c['siglaEstado'] ?? '') === $targetEstado
                    ) {
                        $codigo = (int) $c['codigoCidade'];
                        \Illuminate\Support\Facades\Cache::put($cacheKey, $codigo, Carbon::now()->addDays(30));
                        Log::info('codigoCidade encontrado via paginação', ['cidade' => $nomeCidade, 'estado' => $siglaEstado, 'codigo' => $codigo, 'page' => $page]);
                        return $codigo;
                    }
                }

                $firstOnPage = strtolower($cities[0]['nomeCidade'] ?? '');
                if ($page >= $startPage && $firstOnPage > $targetNome) break;

            } catch (\Exception $e) {
                Log::warning('Erro ao buscar codigoCidade paginado', ['page' => $page, 'error' => $e->getMessage()]);
                break;
            }
        }

        \Illuminate\Support\Facades\Cache::put($cacheKey, 0, Carbon::now()->addDay());
        Log::info('codigoCidade não encontrado', ['cidade' => $nomeCidade, 'estado' => $siglaEstado]);
        return 0;
    }

    /**
     * Formata área com vírgula como separador decimal (padrão da API).
     * Ex: 61.77 → "61,77" | 39.00 → "39,00" | null → ""
     */
    private static function formatArea($value): string
    {
        if ($value === null || $value === '') return '';
        return number_format((float) $value, 2, ',', '');
    }

    /**
     * Remove emojis e símbolos não suportados pela API.
     * Mantém apenas caracteres latinos (incluindo acentos), dígitos,
     * pontuação comum e quebras de linha.
     */
    private static function stripEmojis(string $text): string
    {
        if (empty($text)) return '';

        // Garante que a string está em UTF-8 válido
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');

        // Mantém apenas:
        //   \x{0009}           = TAB
        //   \x{000A}\x{000D}   = newline / CR
        //   \x{0020}-\x{007E}  = ASCII imprimível (letras, dígitos, pontuação)
        //   \x{00A0}-\x{024F}  = Latin-1 Supplement + Latin Extended A/B (acentos: ã à é ê ç etc.)
        // Tudo fora desse range (emojis, símbolos, CJK etc.) é descartado
        $text = preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{007E}\x{00A0}-\x{024F}]/u', '', $text);

        // Limpa espaços múltiplos que sobram após remoção
        $text = preg_replace('/ {2,}/', ' ', $text);

        return trim($text);
    }

    /**
     * Converte qualquer texto ou HTML para texto plano limpo.
     * Remove todas as tags HTML, emojis, ícones, aspas tipográficas,
     * travessões e qualquer caractere que possa causar problemas no Imobi Brasil.
     */
    private static function cleanToPlainText(string $text): string
    {
        if (empty($text)) return '';

        // Garante UTF-8 válido
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');

        // Converte tags de quebra de linha HTML em newlines antes de remover as tags
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text);
        $text = preg_replace('/<\/p>/i', "\n", $text);
        $text = preg_replace('/<\/li>/i', "\n", $text);
        $text = preg_replace('/<\/h[1-6]>/i', "\n", $text);

        // Remove todas as tags HTML
        $text = strip_tags($text);

        // Decodifica entidades HTML (&amp; → &, &nbsp; → espaço, etc.)
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Substitui aspas tipográficas por aspas normais
        $text = str_replace(["\u{2018}", "\u{2019}", "\u{201A}", "\u{201B}"], "'", $text);
        $text = str_replace(["\u{201C}", "\u{201D}", "\u{201E}", "\u{201F}"], '"', $text);

        // Substitui travessões e traços especiais por hífen
        $text = str_replace(["\u{2013}", "\u{2014}", "\u{2015}", "\u{2212}"], '-', $text);

        // Remove caracteres de controle invisíveis (zero-width, BOM, etc.)
        $text = preg_replace('/[\x{200B}-\x{200F}\x{FEFF}\x{00AD}]/u', '', $text);

        // Normaliza quebras de linha
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Remove emojis, símbolos e caracteres fora do range Latin (mantém acentos)
        $text = preg_replace('/[^\x{000A}\x{000D}\x{0020}-\x{007E}\x{00C0}-\x{024F}]/u', '', $text);

        // Substitui espaços não-quebráveis e tabulações por espaço normal
        $text = preg_replace('/[\x{00A0}\x{0009}]/u', ' ', $text);

        // Limpa espaços múltiplos na mesma linha
        $text = preg_replace('/[ ]{2,}/', ' ', $text);

        // Limpa linhas em branco excessivas (máximo 2 quebras seguidas)
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    /**
     * Converte texto plano (com quebras de linha e *negrito*) para HTML formatado.
     * Se o conteúdo já contiver tags HTML, apenas sanitiza emojis e retorna.
     * Imobi Brasil renderiza HTML na descricaoImovel e campos similares.
     */
    private static function formatDescriptionAsHtml(string $text): string
    {
        if (empty($text)) return '';

        // Se já é HTML, apenas sanitiza emojis/símbolos inválidos e retorna
        if (strip_tags($text) !== $text) {
            return self::stripEmojis($text);
        }

        // Garante UTF-8 válido
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');

        // Remove emojis/símbolos inválidos (mantém Latin + acentos + pontuação + newlines)
        $text = preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{007E}\x{00A0}-\x{024F}]/u', '', $text);

        // Normalizar quebras de linha
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // Converter marcação *negrito* → <strong>negrito</strong>
        $text = preg_replace('/\*([^*\n]+)\*/', '<strong>$1</strong>', $text);

        // Dividir por parágrafos (2+ quebras de linha seguidas)
        $paragraphs = preg_split('/\n{2,}/', $text);

        $html = '';
        foreach ($paragraphs as $para) {
            $para = trim($para);
            if ($para === '') continue;
            // Quebras simples dentro do parágrafo viram <br>
            $para = nl2br($para);
            // Limpa espaços múltiplos
            $para = preg_replace('/ {2,}/', ' ', $para);
            $html .= '<p>' . $para . '</p>' . "\n";
        }

        return trim($html);
    }

    public static function preparePropertyPayload(Property $property, string $apiKey = '', string $baseUrl = ''): array
    {
        $purposeValue = strtolower((string) ($property->finalidade_imovel ?? 'venda'));
        $preferRentValue = str_contains($purposeValue, 'alug') || str_contains($purposeValue, 'loca') || str_contains($purposeValue, 'temporad');
        $listingValue = $preferRentValue
            ? ((float) ($property->valor_aluguel ?? 0) > 0 ? $property->valor_aluguel : $property->valor_venda)
            : ((float) ($property->valor_venda ?? 0) > 0 ? $property->valor_venda : $property->valor_aluguel);

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
            'cobertura' => 1,
            'flat' => 6,
            'barracao' => 9,
        ];
        
        $codigoTipoImovel = $tipoImovelMapping[strtolower($property->tipo_imovel ?? 'apartamento')] ?? 1;

        // Mapear finalidade - API aceita strings: 'venda', 'locacao', 'temporada'
        $finalidadeMapping = [
            'venda'     => 'venda',
            'locacao'   => 'locacao',
            'aluguel'   => 'locacao',
            'temporada' => 'temporada',
        ];
        $finalidadeStr = strtolower($property->finalidade_imovel ?? 'venda');
        $finalidade = $finalidadeMapping[$finalidadeStr] ?? 'venda';

        // Estrutura exata conforme a API espera
        return [
            'codigoProprietario' => 0,
            'codigoCorretor' => 0,
            'codigoUsuarioAdicional' => 0,
            'finalidade' => $finalidade,
            'codigoTipoImovel' => $codigoTipoImovel,
            'referencia' => $property->codigo ?? ('PROP-' . $property->id),
            'cep' => '',
            'bairro' => $property->bairro ?? '',
            'logradouro' => '',
            'codigoCidade' => ($apiKey && $baseUrl)
                ? self::findCodigoCidade(
                    $property->cidade ?? '',
                    $property->estado ?? '',
                    $apiKey,
                    $baseUrl
                  )
                : 0,
            'numero' => '',
            'pontoReferencia' => '',
            'complemento' => '',
            'mapa' => $property->latitude && $property->longitude ? 'sim' : 'nao',
            'zona' => $property->zona ?? '',
            'regiao' => $property->regiao ?? '',
            'exibirEnderecoSite' => 'nao',
            'exibirEnderecoSitePersonalizado' => [
                [
                    'cep' => false,
                    'logradouro' => false,
                    'numero' => false,
                    'complemento' => false,
                    'zona' => true,
                    'regiao' => true,
                    'pontoReferencia' => false,
                    'nomeCondominio' => true,
                ]
            ],
            'exibirEnderecoPortalPersonalizado' => [
                [
                    'cep' => false,
                    'logradouro' => false,
                    'numero' => false,
                    'complemento' => false,
                    'zona' => true,
                    'regiao' => true,
                    'pontoReferencia' => false,
                    'nomeCondominio' => true,
                ]
            ],
            // Áreas: API usa vírgula como decimal (ex: "61,77"), não ponto
            'areaPrivativa' => self::formatArea($property->area_privativa),
            'tipoAreaPrivativa' => 0,
            'areaTotal' => self::formatArea($property->area_total),
            'tipoAreaTotal' => 0,
            'areaTerreno' => self::formatArea($property->area_terreno),
            'tipoAreaTerreno' => 0,
            'areaConstruida' => self::formatArea($property->area_construida),
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
            'descricaoImovel' => self::cleanToPlainText($property->descricao ?? $property->titulo ?? ''),
            'observacaoImovel' => self::cleanToPlainText($property->observacao ?? ''),
            'pontosFortesImovel' => self::cleanToPlainText($property->pontos_fortes ?? ''),
            'outrasInformacoesImovel' => self::cleanToPlainText($property->outras_informacoes ?? ''),
            'destaqueInicial' => ($property->destaque ?? false) ? 'sim' : 'nao',
            'destaquesSuperDestaqueInicial' => ($property->super_destaque ?? false) ? 'sim' : 'nao',
            'valorImovel' => (int) ($listingValue ?? 0),
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
            'terrenoFrente' => self::formatArea($property->terreno_frente),
            'tipoTerrenoFrente' => 0,
            'terrenoFundo' => self::formatArea($property->terreno_fundo),
            'tipoTerrenoFundo' => 0,
            'terrenoEsquerda' => self::formatArea($property->terreno_esquerda),
            'tipoTerrenoEsquerda' => 0,
            'terrenoDireita' => self::formatArea($property->terreno_direita),
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
            // Portais: objeto plano (não array), somente ChaveNaMao = true
            'portaisDivulgarConvencional' => [
                'VivaReal'           => false,
                'ImovelWeb'          => false,
                '123i'               => false,
                'ZapImoveis'         => false,
                'MercadoLivre'       => false,
                'OLX'                => false,
                'ChaveNaMao'         => true,
                'Moving'             => false,
                'SPImovel'           => false,
                'CasaMineira'        => false,
                '018Imoveis'         => false,
                'DreamCasa'          => false,
                'Imoveis014'         => false,
                '016Imoveis '        => false,
                'OlhoMagico'         => false,
                'OruloExclusividades'=> false,
                'Lopes'              => false,
                'FacebookAds'        => false,
                'Buskaza'            => false,
            ],
            'portaisDivulgarDestaque'      => [],
            'portaisDivulgarSuperDestaque' => [],
            'portaisDivulgarSuperDestaque2' => [
                'VivaReal'           => false,
                'ImovelWeb'          => false,
                '123i'               => false,
                'ZapImoveis'         => false,
                'MercadoLivre'       => false,
                'OLX'                => false,
                'ChaveNaMao'         => false,
                'Moving'             => false,
                'SPImovel'           => false,
                'CasaMineira'        => false,
                '018Imoveis'         => false,
                'DreamCasa'          => false,
                'Imoveis014'         => false,
                '016Imoveis '        => false,
                'OlhoMagico'         => false,
                'OruloExclusividades'=> false,
                'Lopes'              => false,
                'FacebookAds'        => false,
                'Buskaza'            => false,
            ],
        ];
    }

    /**
     * Enviar imagens da propriedade para Imobi Brasil
     * Endpoint: POST /imovel/{codigoImovel}/imagem/inserir
     */
    public static function getPropertyImageUrls(Property $property): array
    {
        $imageUrls = [];
        $seenUrls = [];
        $videoExtensions = ['mp4', 'avi', 'mov', 'wmv', 'webm', 'mkv', 'flv', 'ogv', 'm4v', '3gp', 'ts'];
        $docExtensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'zip', 'rar'];

        $addImageUrl = static function ($rawUrl) use (&$imageUrls, &$seenUrls, $videoExtensions, $docExtensions): void {
            if (is_array($rawUrl)) {
                $rawUrl = $rawUrl['url'] ?? $rawUrl['imagem'] ?? $rawUrl['image_url'] ?? $rawUrl['path'] ?? null;
            }

            if (!is_string($rawUrl)) {
                return;
            }

            $url = trim($rawUrl);
            if ($url === '' || str_starts_with($url, 'blob:') || str_starts_with($url, 'data:')) {
                return;
            }

            $path = parse_url($url, PHP_URL_PATH) ?: $url;
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if ($ext !== '' && (in_array($ext, $videoExtensions, true) || in_array($ext, $docExtensions, true))) {
                return;
            }

            $key = strtolower($url);
            if (isset($seenUrls[$key])) {
                return;
            }

            $seenUrls[$key] = true;
            $imageUrls[] = $url;
        };

        $addImageUrl($property->imagem_destaque);

        $rawImagens = $property->imagens;
        if (is_array($rawImagens)) {
            foreach ($rawImagens as $url) {
                $addImageUrl($url);
            }
        }

        try {
            if ($property->relationLoaded('fotos')) {
                $storedImages = $property->getRelation('fotos')
                    ->sortByDesc('destaque')
                    ->values();
            } else {
                $query = \App\Models\ImovelImagem::where('codigo', $property->codigo)
                    ->orderBy('destaque', 'desc');

                if (\Illuminate\Support\Facades\Schema::hasColumn('imoveis_imagens', 'id')) {
                    $query->orderBy('id');
                }

                $storedImages = $query->get();
            }

            foreach ($storedImages as $img) {
                $addImageUrl($img->url);
            }
        } catch (\Exception $dbEx) {
            Log::warning('Tabela imoveis_imagens indisponível, usando imagens do imóvel', [
                'property_id' => $property->id,
                'error' => $dbEx->getMessage(),
            ]);
        }

        return $imageUrls;
    }

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

            $codigoImovel = $property->imobi_brasil_external_id;

            // Excluir TODAS imagens existentes no Imobi Brasil antes de reenviar (evita duplicatas)
            // Itera em loop pois a API pode paginar os resultados
            $totalRemovidos = 0;
            $maxIteracoes   = 20; // segurança contra loop infinito
            for ($i = 0; $i < $maxIteracoes; $i++) {
                $existingImages = static::listPropertyImages((int) $codigoImovel, $tenant);
                if (empty($existingImages['result_set'])) break;
                foreach ($existingImages['result_set'] as $img) {
                    // A API retorna o campo como "codigoImagem" (não "codigo" nem "id")
                    $codigoImagem = $img['codigoImagem'] ?? $img['codigo'] ?? $img['id'] ?? null;
                    if ($codigoImagem) {
                        static::deletePropertyImage((int) $codigoImovel, (int) $codigoImagem, $tenant);
                        $totalRemovidos++;
                    }
                }
            }
            if ($totalRemovidos > 0) {
                Log::info('Imagens anteriores removidas do Imobi Brasil antes do reenvio', [
                    'property_id'   => $property->id,
                    'codigo_imovel' => $codigoImovel,
                    'removed'       => $totalRemovidos,
                ]);
            }

            // Montar lista consolidada de imagens. Fotos recém-salvas ficam no JSON
            // do imóvel, enquanto importações antigas podem estar em imoveis_imagens.
            $imageUrls = static::getPropertyImageUrls($property);

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

            // Pré-calcular a URL base da aplicação para detecção de imagens locais
            $appUrl = rtrim(config('app.url', env('APP_URL', '')), '/');

            // Enviar cada imagem
            foreach ($imagens as $index => $imagemUrl) {
                try {
                    $endpoint = $baseUrl . '/imovel/' . $codigoImovel . '/imagem/inserir';

                    // Tentar ler imagem localmente (evita round-trip HTTP em servidores compartilhados
                    // onde o DNS do próprio domínio pode não resolver internamente)
                    $imgContent = null;
                    if ($appUrl && strpos($imagemUrl, $appUrl) === 0) {
                        $urlPath   = ltrim(parse_url($imagemUrl, PHP_URL_PATH), '/');
                        $localPath = public_path($urlPath);
                        if (file_exists($localPath) && is_readable($localPath)) {
                            $imgContent = @file_get_contents($localPath);
                            if ($imgContent === false) {
                                $imgContent = null;
                            }
                        }
                    }

                    // Fallback: baixar via HTTP se não encontrou localmente
                    if ($imgContent === null) {
                        $imgResponse = $client->get($imagemUrl, [
                            'verify'      => false,
                            'timeout'     => 30,
                            'http_errors' => false,
                        ]);

                        if ($imgResponse->getStatusCode() !== 200) {
                            $erros[] = "Imagem $index: não foi possível baixar ($imagemUrl)";
                            Log::warning('Não foi possível baixar imagem para Imobi Brasil', [
                                'property_id' => $property->id,
                                'image_index' => $index,
                                'image_url'   => $imagemUrl,
                                'http'        => $imgResponse->getStatusCode(),
                            ]);
                            continue;
                        }

                        $imgContent = $imgResponse->getBody()->getContents();
                    }

                    if (empty($imgContent)) {
                        $erros[] = "Imagem $index: conteúdo vazio ($imagemUrl)";
                        Log::warning('Conteúdo de imagem vazio para Imobi Brasil', [
                            'property_id' => $property->id,
                            'image_index' => $index,
                            'image_url'   => $imagemUrl,
                        ]);
                        continue;
                    }

                    $ext = strtolower(pathinfo(parse_url($imagemUrl, PHP_URL_PATH), PATHINFO_EXTENSION)) ?: 'jpg';

                    // Mapa de MIME types reais para download/leitura correta
                    $mimeMap = [
                        'jpg'  => 'image/jpeg',
                        'jpeg' => 'image/jpeg',
                        'jfif' => 'image/jpeg',
                        'pjpeg'=> 'image/jpeg',
                        'pjp'  => 'image/jpeg',
                        'png'  => 'image/png',
                        'gif'  => 'image/gif',
                        'webp' => 'image/webp',
                        'avif' => 'image/avif',
                        'heic' => 'image/heic',
                        'heif' => 'image/heif',
                        'tiff' => 'image/tiff',
                        'tif'  => 'image/tiff',
                        'bmp'  => 'image/bmp',
                        'svg'  => 'image/svg+xml',
                    ];
                    $mime = $mimeMap[$ext] ?? 'image/jpeg';

                    // Extensões aceitas pela API Imobi Brasil: jpg, jpeg, png, gif
                    // Todos os outros formatos são normalizados para jpg no nome do arquivo
                    $extAceitas = ['jpg', 'jpeg', 'png', 'gif'];
                    $extNormalizada = in_array($ext, $extAceitas) ? $ext : 'jpg';

                    // Enviar como binário multipart; primeira imagem = destaque
                    $multipart = [
                        [
                            'name'     => 'codigoImovel',
                            'contents' => (string)$codigoImovel,
                        ],
                        [
                            'name'     => 'imagem',
                            'contents' => $imgContent,
                            'filename' => 'imagem_' . $index . '.' . $extNormalizada,
                            'headers'  => ['Content-Type' => $mime],
                        ],
                    ];
                    if ($index === 0) {
                        $multipart[] = ['name' => 'destaque', 'contents' => 'sim'];
                        $multipart[] = ['name' => 'principal', 'contents' => 'sim'];
                    }
                    $response = $client->post($endpoint, [
                        'headers'    => ['token' => $apiKey],
                        'multipart'  => $multipart,
                    ]);

                    $statusCode = $response->getStatusCode();
                    $body = $response->getBody()->getContents();
                    $responseData = json_decode($body, true) ?: [];

                    if (($statusCode === 200 || $statusCode === 201) && ($responseData['status'] ?? false)) {
                        $sucessos++;

                        Log::info('Imagem enviada com sucesso para Imobi Brasil', [
                            'property_id' => $property->id,
                            'image_index' => $index,
                            'image_url' => $imagemUrl,
                            'response' => $responseData,
                        ]);
                    } else {
                        $erro = $responseData['message'] ?? "HTTP $statusCode";
                        $erros[] = "Imagem $index: $erro";

                        Log::warning('Erro ao enviar imagem para Imobi Brasil', [
                            'property_id' => $property->id,
                            'image_index' => $index,
                            'image_url' => $imagemUrl,
                            'status_code' => $statusCode,
                            'error' => $erro,
                            'response_body' => substr($body, 0, 500),
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

            // Atualizar timestamp de sincronização de imagens apenas se ao menos uma foi enviada
            // (evita bloquear reenvio futuro quando todas falharam)
            if ($sucessos > 0) {
                $property->update([
                    'imobi_brasil_images_sent_at' => \Carbon\Carbon::now(),
                ]);
            }

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

    // =========================================================================
    // IMÓVEIS - Métodos adicionais
    // =========================================================================

    /**
     * Excluir um imóvel no Imobi Brasil
     * POST /imovel/excluir/{codigoImovel}
     */
    public static function deleteProperty(int $codigoImovel, Tenant $tenant): array
    {
        try {
            $apiKey  = static::getApiKey($tenant);
            $baseUrl = rtrim(static::getBaseUrl($tenant), '/');

            if (!$apiKey) {
                return ['success' => false, 'error' => 'Integração Imobi Brasil não configurada'];
            }

            $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 30, 'http_errors' => false]);

            $response = $client->post($baseUrl . '/imovel/excluir/' . $codigoImovel, [
                'headers' => ['token' => $apiKey, 'Accept' => 'application/json'],
            ]);

            $data = json_decode($response->getBody()->getContents(), true) ?: [];

            return [
                'success'      => !empty($data['status']),
                'api_response' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao excluir imóvel no Imobi Brasil', ['codigo' => $codigoImovel, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Listar imóveis no Imobi Brasil
     * GET /imovel/lista
     */
    public static function listProperties(Tenant $tenant, array $params = []): array
    {
        try {
            $apiKey  = static::getApiKey($tenant);
            $baseUrl = rtrim(static::getBaseUrl($tenant), '/');

            if (!$apiKey) {
                return ['success' => false, 'error' => 'Integração Imobi Brasil não configurada'];
            }

            $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 30, 'http_errors' => false]);

            $response = $client->get($baseUrl . '/imovel/lista', [
                'headers' => ['token' => $apiKey, 'Accept' => 'application/json'],
                'query'   => $params,
            ]);

            $data = json_decode($response->getBody()->getContents(), true) ?: [];

            $rawResultSet = $data['resultSet'] ?? $data['result_set'] ?? [];
            if (!is_array($rawResultSet)) {
                $rawResultSet = [];
            }

            $normalizedResultSet = $rawResultSet;
            if (!isset($normalizedResultSet['data']) || !is_array($normalizedResultSet['data'])) {
                if (array_is_list($rawResultSet)) {
                    $normalizedResultSet = ['data' => $rawResultSet];
                } elseif (isset($rawResultSet['imoveis']) && is_array($rawResultSet['imoveis'])) {
                    $normalizedResultSet['data'] = $rawResultSet['imoveis'];
                } else {
                    $normalizedResultSet['data'] = [];
                }
            }

            if (!isset($normalizedResultSet['total_pages'])) {
                $normalizedResultSet['total_pages'] = $rawResultSet['total_pages']
                    ?? $rawResultSet['totalPages']
                    ?? ($rawResultSet['pagination']['total_pages'] ?? 1);
            }

            if (!isset($normalizedResultSet['total_items'])) {
                $normalizedResultSet['total_items'] = $rawResultSet['total_items']
                    ?? $rawResultSet['total']
                    ?? count($normalizedResultSet['data']);
            }

            if (!isset($normalizedResultSet['page'])) {
                $normalizedResultSet['page'] = $rawResultSet['page']
                    ?? $rawResultSet['pagina']
                    ?? ($rawResultSet['pagination']['current_page'] ?? ($params['page'] ?? $params['pagina'] ?? 1));
            }

            if (!isset($normalizedResultSet['per_page'])) {
                $normalizedResultSet['per_page'] = $rawResultSet['per_page']
                    ?? $rawResultSet['limite']
                    ?? $rawResultSet['limit']
                    ?? ($params['limit'] ?? $params['limite'] ?? count($normalizedResultSet['data']));
            }

            return [
                'success'    => !empty($data['status']),
                'result_set' => $normalizedResultSet,
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao listar imóveis no Imobi Brasil', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Dados de um imóvel no Imobi Brasil
     * GET /imovel/dados/{codigoImovel}
     */
    public static function getPropertyData(int $codigoImovel, Tenant $tenant): array
    {
        try {
            $apiKey  = static::getApiKey($tenant);
            $baseUrl = rtrim(static::getBaseUrl($tenant), '/');

            if (!$apiKey) {
                return ['success' => false, 'error' => 'Integração Imobi Brasil não configurada'];
            }

            $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 30, 'http_errors' => false]);

            $response = $client->get($baseUrl . '/imovel/dados/' . $codigoImovel, [
                'headers' => ['token' => $apiKey, 'Accept' => 'application/json'],
            ]);

            $data = json_decode($response->getBody()->getContents(), true) ?: [];

            $rawResultSet = $data['resultSet'] ?? $data['result_set'] ?? [];
            if (is_array($rawResultSet) && isset($rawResultSet['data'])) {
                $dataField = $rawResultSet['data'];
                if (is_array($dataField) && !array_is_list($dataField)) {
                    $rawResultSet = $dataField;
                } elseif (is_array($dataField) && array_is_list($dataField)) {
                    $rawResultSet = $dataField[0] ?? [];
                }
            }

            if (!is_array($rawResultSet)) {
                $rawResultSet = [];
            }

            return [
                'success'    => !empty($data['status']),
                'result_set' => $rawResultSet,
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao buscar dados do imóvel no Imobi Brasil', ['codigo' => $codigoImovel, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Listar tipos de imóveis no Imobi Brasil
     * GET /imovel/tipo/lista
     */
    public static function listPropertyTypes(Tenant $tenant, array $params = []): array
    {
        try {
            $apiKey  = static::getApiKey($tenant);
            $baseUrl = rtrim(static::getBaseUrl($tenant), '/');

            if (!$apiKey) {
                return ['success' => false, 'error' => 'Integração Imobi Brasil não configurada'];
            }

            $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 30, 'http_errors' => false]);

            $response = $client->get($baseUrl . '/imovel/tipo/lista', [
                'headers' => ['token' => $apiKey, 'Accept' => 'application/json'],
                'query'   => $params,
            ]);

            $data = json_decode($response->getBody()->getContents(), true) ?: [];

            return [
                'success'    => !empty($data['status']),
                'result_set' => $data['resultSet'] ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao listar tipos de imóveis no Imobi Brasil', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // =========================================================================
    // PESSOAS
    // =========================================================================

    /**
     * Inserir pessoa no Imobi Brasil
     * POST /pessoa/inserir
     */
    public static function insertPessoa(array $payload, Tenant $tenant): array
    {
        try {
            $apiKey  = static::getApiKey($tenant);
            $baseUrl = rtrim(static::getBaseUrl($tenant), '/');

            if (!$apiKey) {
                return ['success' => false, 'error' => 'Integração Imobi Brasil não configurada'];
            }

            $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 30, 'http_errors' => false]);

            $response = $client->post($baseUrl . '/pessoa/inserir', [
                'headers' => ['token' => $apiKey, 'Content-Type' => 'application/json', 'Accept' => 'application/json'],
                'json'    => $payload,
            ]);

            $data = json_decode($response->getBody()->getContents(), true) ?: [];

            Log::info('Resposta inserção pessoa Imobi Brasil', ['status_code' => $response->getStatusCode(), 'response' => $data]);

            return [
                'success'    => !empty($data['status']),
                'result_set' => $data['resultSet'] ?? null,
                'api_response' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao inserir pessoa no Imobi Brasil', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Alterar pessoa no Imobi Brasil
     * POST /pessoa/alterar/{codigoPessoa}
     */
    public static function updatePessoa(int $codigoPessoa, array $payload, Tenant $tenant): array
    {
        try {
            $apiKey  = static::getApiKey($tenant);
            $baseUrl = rtrim(static::getBaseUrl($tenant), '/');

            if (!$apiKey) {
                return ['success' => false, 'error' => 'Integração Imobi Brasil não configurada'];
            }

            $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 30, 'http_errors' => false]);

            $response = $client->post($baseUrl . '/pessoa/alterar/' . $codigoPessoa, [
                'headers' => [
                    'token'        => $apiKey,
                    'codigoPessoa' => $codigoPessoa,
                    'Content-Type' => 'application/json',
                    'Accept'       => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = json_decode($response->getBody()->getContents(), true) ?: [];

            return [
                'success'      => !empty($data['status']),
                'api_response' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao alterar pessoa no Imobi Brasil', ['codigo' => $codigoPessoa, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Listar pessoas no Imobi Brasil
     * GET /pessoa/lista
     */
    public static function listPessoas(Tenant $tenant, array $params = []): array
    {
        try {
            $apiKey  = static::getApiKey($tenant);
            $baseUrl = rtrim(static::getBaseUrl($tenant), '/');

            if (!$apiKey) {
                return ['success' => false, 'error' => 'Integração Imobi Brasil não configurada'];
            }

            $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 30, 'http_errors' => false]);

            $response = $client->get($baseUrl . '/pessoa/lista', [
                'headers' => ['token' => $apiKey, 'Accept' => 'application/json'],
                'query'   => array_filter($params, fn($v) => $v !== null),
            ]);

            $data = json_decode($response->getBody()->getContents(), true) ?: [];

            return [
                'success'    => !empty($data['status']),
                'result_set' => $data['resultSet'] ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao listar pessoas no Imobi Brasil', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Dados de uma pessoa no Imobi Brasil
     * GET /pessoa/dados/{codigoPessoa}
     */
    public static function getPessoa(int $codigoPessoa, Tenant $tenant): array
    {
        try {
            $apiKey  = static::getApiKey($tenant);
            $baseUrl = rtrim(static::getBaseUrl($tenant), '/');

            if (!$apiKey) {
                return ['success' => false, 'error' => 'Integração Imobi Brasil não configurada'];
            }

            $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 30, 'http_errors' => false]);

            $response = $client->get($baseUrl . '/pessoa/dados/' . $codigoPessoa, [
                'headers' => ['token' => $apiKey, 'codigoPessoa' => $codigoPessoa, 'Accept' => 'application/json'],
            ]);

            $data = json_decode($response->getBody()->getContents(), true) ?: [];

            return [
                'success'    => !empty($data['status']),
                'result_set' => $data['resultSet'] ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao buscar pessoa no Imobi Brasil', ['codigo' => $codigoPessoa, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Excluir pessoa no Imobi Brasil
     * POST /pessoa/excluir/{codigoPessoa}
     */
    public static function deletePessoa(int $codigoPessoa, Tenant $tenant): array
    {
        try {
            $apiKey  = static::getApiKey($tenant);
            $baseUrl = rtrim(static::getBaseUrl($tenant), '/');

            if (!$apiKey) {
                return ['success' => false, 'error' => 'Integração Imobi Brasil não configurada'];
            }

            $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 30, 'http_errors' => false]);

            $response = $client->post($baseUrl . '/pessoa/excluir/' . $codigoPessoa, [
                'headers' => ['token' => $apiKey, 'codigoPessoa' => $codigoPessoa, 'Accept' => 'application/json'],
            ]);

            $data = json_decode($response->getBody()->getContents(), true) ?: [];

            return [
                'success'      => !empty($data['status']),
                'api_response' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao excluir pessoa no Imobi Brasil', ['codigo' => $codigoPessoa, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // =========================================================================
    // MENSAGENS
    // =========================================================================

    /**
     * Inserir mensagem no Imobi Brasil (lead/contato)
     * POST /mensagem/inserir
     * Tipos: PP, L, I, W, C, VL, EE
     */
    public static function insertMensagem(array $payload, Tenant $tenant): array
    {
        try {
            $apiKey  = static::getApiKey($tenant);
            $baseUrl = rtrim(static::getBaseUrl($tenant), '/');

            if (!$apiKey) {
                return ['success' => false, 'error' => 'Integração Imobi Brasil não configurada'];
            }

            $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 30, 'http_errors' => false]);

            $response = $client->post($baseUrl . '/mensagem/inserir', [
                'headers' => ['token' => $apiKey, 'Content-Type' => 'application/json', 'Accept' => 'application/json'],
                'json'    => $payload,
            ]);

            $statusCode = $response->getStatusCode();
            $data       = json_decode($response->getBody()->getContents(), true) ?: [];

            Log::info('Mensagem enviada para Imobi Brasil', ['status_code' => $statusCode, 'response' => $data]);

            return [
                'success'      => !empty($data['status']),
                'result_set'   => $data['resultSet'] ?? null,
                'api_response' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao inserir mensagem no Imobi Brasil', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Excluir mensagem no Imobi Brasil
     * POST /mensagem/excluir/{codigoMensagem}
     */
    public static function deleteMensagem(int $codigoMensagem, Tenant $tenant): array
    {
        try {
            $apiKey  = static::getApiKey($tenant);
            $baseUrl = rtrim(static::getBaseUrl($tenant), '/');

            if (!$apiKey) {
                return ['success' => false, 'error' => 'Integração Imobi Brasil não configurada'];
            }

            $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 30, 'http_errors' => false]);

            $response = $client->post($baseUrl . '/mensagem/excluir/' . $codigoMensagem, [
                'headers' => ['token' => $apiKey, 'codigoMensagem' => $codigoMensagem, 'Accept' => 'application/json'],
            ]);

            $data = json_decode($response->getBody()->getContents(), true) ?: [];

            return [
                'success'      => !empty($data['status']),
                'api_response' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao excluir mensagem no Imobi Brasil', ['codigo' => $codigoMensagem, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Dados de uma mensagem no Imobi Brasil
     * GET /mensagem/dados/{codigoMensagem}
     */
    public static function getMensagem(int $codigoMensagem, Tenant $tenant): array
    {
        try {
            $apiKey  = static::getApiKey($tenant);
            $baseUrl = rtrim(static::getBaseUrl($tenant), '/');

            if (!$apiKey) {
                return ['success' => false, 'error' => 'Integração Imobi Brasil não configurada'];
            }

            $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 30, 'http_errors' => false]);

            $response = $client->get($baseUrl . '/mensagem/dados/' . $codigoMensagem, [
                'headers' => ['token' => $apiKey, 'codigoMensagem' => $codigoMensagem, 'Accept' => 'application/json'],
            ]);

            $data = json_decode($response->getBody()->getContents(), true) ?: [];

            return [
                'success'    => !empty($data['status']),
                'result_set' => $data['resultSet'] ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao buscar mensagem no Imobi Brasil', ['codigo' => $codigoMensagem, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Listar mensagens no Imobi Brasil
     * GET /mensagem/lista
     */
    public static function listMensagens(Tenant $tenant, array $params = []): array
    {
        try {
            $apiKey  = static::getApiKey($tenant);
            $baseUrl = rtrim(static::getBaseUrl($tenant), '/');

            if (!$apiKey) {
                return ['success' => false, 'error' => 'Integração Imobi Brasil não configurada'];
            }

            $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 30, 'http_errors' => false]);

            $headers = ['token' => $apiKey, 'Accept' => 'application/json'];
            if (isset($params['lido']))       $headers['lido']       = $params['lido'];
            if (isset($params['dataInicio'])) $headers['dataInicio'] = $params['dataInicio'];
            if (isset($params['dataFim']))    $headers['dataFim']    = $params['dataFim'];
            if (isset($params['tipo']))       $headers['tipo']       = $params['tipo'];

            $query = array_filter([
                'page'     => $params['page']     ?? null,
                'per_page' => $params['per_page'] ?? null,
            ], fn($v) => $v !== null);

            $response = $client->get($baseUrl . '/mensagem/lista', [
                'headers' => $headers,
                'query'   => $query,
            ]);

            $data = json_decode($response->getBody()->getContents(), true) ?: [];

            return [
                'success'    => !empty($data['status']),
                'result_set' => $data['resultSet'] ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao listar mensagens no Imobi Brasil', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Marcar mensagem como lida no Imobi Brasil
     * POST /mensagem/lido/{codigoMensagem}
     */
    public static function markMensagemAsRead(int $codigoMensagem, Tenant $tenant): array
    {
        try {
            $apiKey  = static::getApiKey($tenant);
            $baseUrl = rtrim(static::getBaseUrl($tenant), '/');

            if (!$apiKey) {
                return ['success' => false, 'error' => 'Integração Imobi Brasil não configurada'];
            }

            $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 30, 'http_errors' => false]);

            $response = $client->post($baseUrl . '/mensagem/lido/' . $codigoMensagem, [
                'headers' => ['token' => $apiKey, 'codigoMensagem' => $codigoMensagem, 'Accept' => 'application/json'],
            ]);

            $data = json_decode($response->getBody()->getContents(), true) ?: [];

            return [
                'success'      => !empty($data['status']),
                'api_response' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao marcar mensagem como lida no Imobi Brasil', ['codigo' => $codigoMensagem, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // =========================================================================
    // NEGÓCIOS
    // =========================================================================

    /**
     * Inserir negócio no Imobi Brasil
     * POST /negocio/inserir
     */
    public static function insertNegocio(array $payload, Tenant $tenant): array
    {
        try {
            $apiKey  = static::getApiKey($tenant);
            $baseUrl = rtrim(static::getBaseUrl($tenant), '/');

            if (!$apiKey) {
                return ['success' => false, 'error' => 'Integração Imobi Brasil não configurada'];
            }

            $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 30, 'http_errors' => false]);

            $response = $client->post($baseUrl . '/negocio/inserir', [
                'headers' => ['token' => $apiKey, 'Content-Type' => 'application/json', 'Accept' => 'application/json'],
                'json'    => $payload,
            ]);

            $data = json_decode($response->getBody()->getContents(), true) ?: [];

            return [
                'success'      => !empty($data['status']),
                'result_set'   => $data['resultSet'] ?? null,
                'api_response' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao inserir negócio no Imobi Brasil', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Alterar negócio no Imobi Brasil
     * POST /negocio/alterar/{codigoNegocio}
     */
    public static function updateNegocio(int $codigoNegocio, array $payload, Tenant $tenant): array
    {
        try {
            $apiKey  = static::getApiKey($tenant);
            $baseUrl = rtrim(static::getBaseUrl($tenant), '/');

            if (!$apiKey) {
                return ['success' => false, 'error' => 'Integração Imobi Brasil não configurada'];
            }

            $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 30, 'http_errors' => false]);

            $response = $client->post($baseUrl . '/negocio/alterar/' . $codigoNegocio, [
                'headers' => [
                    'token'         => $apiKey,
                    'codigoNegocio' => $codigoNegocio,
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json',
                ],
                'json' => $payload,
            ]);

            $data = json_decode($response->getBody()->getContents(), true) ?: [];

            return [
                'success'      => !empty($data['status']),
                'api_response' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao alterar negócio no Imobi Brasil', ['codigo' => $codigoNegocio, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Excluir negócio no Imobi Brasil
     * POST /negocio/excluir/{codigoNegocio}
     */
    public static function deleteNegocio(int $codigoNegocio, Tenant $tenant): array
    {
        try {
            $apiKey  = static::getApiKey($tenant);
            $baseUrl = rtrim(static::getBaseUrl($tenant), '/');

            if (!$apiKey) {
                return ['success' => false, 'error' => 'Integração Imobi Brasil não configurada'];
            }

            $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 30, 'http_errors' => false]);

            $response = $client->post($baseUrl . '/negocio/excluir/' . $codigoNegocio, [
                'headers' => ['token' => $apiKey, 'codigoNegocio' => $codigoNegocio, 'Accept' => 'application/json'],
            ]);

            $data = json_decode($response->getBody()->getContents(), true) ?: [];

            return [
                'success'      => !empty($data['status']),
                'api_response' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao excluir negócio no Imobi Brasil', ['codigo' => $codigoNegocio, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Listar negócios no Imobi Brasil
     * GET /negocio/lista
     */
    public static function listNegocios(Tenant $tenant, array $params = []): array
    {
        try {
            $apiKey  = static::getApiKey($tenant);
            $baseUrl = rtrim(static::getBaseUrl($tenant), '/');

            if (!$apiKey) {
                return ['success' => false, 'error' => 'Integração Imobi Brasil não configurada'];
            }

            $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 30, 'http_errors' => false]);

            $response = $client->get($baseUrl . '/negocio/lista', [
                'headers' => ['token' => $apiKey, 'Accept' => 'application/json'],
                'query'   => array_filter($params, fn($v) => $v !== null),
            ]);

            $data = json_decode($response->getBody()->getContents(), true) ?: [];

            return [
                'success'    => !empty($data['status']),
                'result_set' => $data['resultSet'] ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao listar negócios no Imobi Brasil', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Dados de um negócio no Imobi Brasil
     * GET /negocio/dados/{codigoNegocio}
     */
    public static function getNegocio(int $codigoNegocio, Tenant $tenant): array
    {
        try {
            $apiKey  = static::getApiKey($tenant);
            $baseUrl = rtrim(static::getBaseUrl($tenant), '/');

            if (!$apiKey) {
                return ['success' => false, 'error' => 'Integração Imobi Brasil não configurada'];
            }

            $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 30, 'http_errors' => false]);

            $response = $client->get($baseUrl . '/negocio/dados/' . $codigoNegocio, [
                'headers' => ['token' => $apiKey, 'Accept' => 'application/json'],
            ]);

            $data = json_decode($response->getBody()->getContents(), true) ?: [];

            return [
                'success'    => !empty($data['status']),
                'result_set' => $data['resultSet'] ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao buscar negócio no Imobi Brasil', ['codigo' => $codigoNegocio, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Listar etapas de negócios no Imobi Brasil
     * GET /negocio/lista/etapas
     */
    public static function listEtapasNegocios(Tenant $tenant): array
    {
        try {
            $apiKey  = static::getApiKey($tenant);
            $baseUrl = rtrim(static::getBaseUrl($tenant), '/');

            if (!$apiKey) {
                return ['success' => false, 'error' => 'Integração Imobi Brasil não configurada'];
            }

            $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 30, 'http_errors' => false]);

            $response = $client->get($baseUrl . '/negocio/lista/etapas', [
                'headers' => ['token' => $apiKey, 'Accept' => 'application/json'],
            ]);

            $data = json_decode($response->getBody()->getContents(), true) ?: [];

            return [
                'success'    => !empty($data['status']),
                'result_set' => $data['resultSet'] ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao listar etapas de negócios no Imobi Brasil', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // =========================================================================
    // CORRETORES
    // =========================================================================

    /**
     * Dados de um corretor no Imobi Brasil
     * GET /corretor/dados/{codigoCorretor}
     */
    public static function getCorretor(int $codigoCorretor, Tenant $tenant): array
    {
        try {
            $apiKey  = static::getApiKey($tenant);
            $baseUrl = rtrim(static::getBaseUrl($tenant), '/');

            if (!$apiKey) {
                return ['success' => false, 'error' => 'Integração Imobi Brasil não configurada'];
            }

            $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 30, 'http_errors' => false]);

            $response = $client->get($baseUrl . '/corretor/dados/' . $codigoCorretor, [
                'headers' => ['token' => $apiKey, 'Accept' => 'application/json'],
            ]);

            $data = json_decode($response->getBody()->getContents(), true) ?: [];

            return [
                'success'    => !empty($data['status']),
                'result_set' => $data['resultSet'] ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao buscar corretor no Imobi Brasil', ['codigo' => $codigoCorretor, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Listar corretores no Imobi Brasil
     * GET /corretor/lista
     */
    public static function listCorretores(Tenant $tenant, array $params = []): array
    {
        try {
            $apiKey  = static::getApiKey($tenant);
            $baseUrl = rtrim(static::getBaseUrl($tenant), '/');

            if (!$apiKey) {
                return ['success' => false, 'error' => 'Integração Imobi Brasil não configurada'];
            }

            $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 30, 'http_errors' => false]);

            $response = $client->get($baseUrl . '/corretor/lista', [
                'headers' => ['token' => $apiKey, 'Accept' => 'application/json'],
                'query'   => array_filter($params, fn($v) => $v !== null),
            ]);

            $data = json_decode($response->getBody()->getContents(), true) ?: [];

            return [
                'success'    => !empty($data['status']),
                'result_set' => $data['resultSet'] ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao listar corretores no Imobi Brasil', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // =========================================================================
    // CLIENTES
    // =========================================================================

    /**
     * Dados de um cliente no Imobi Brasil
     * GET /cliente/dados/{codigoCliente}
     */
    public static function getCliente(int $codigoCliente, Tenant $tenant): array
    {
        try {
            $apiKey  = static::getApiKey($tenant);
            $baseUrl = rtrim(static::getBaseUrl($tenant), '/');

            if (!$apiKey) {
                return ['success' => false, 'error' => 'Integração Imobi Brasil não configurada'];
            }

            $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 30, 'http_errors' => false]);

            $response = $client->get($baseUrl . '/cliente/dados/' . $codigoCliente, [
                'headers' => ['token' => $apiKey, 'Accept' => 'application/json'],
            ]);

            $data = json_decode($response->getBody()->getContents(), true) ?: [];

            return [
                'success'    => !empty($data['status']),
                'result_set' => $data['resultSet'] ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao buscar cliente no Imobi Brasil', ['codigo' => $codigoCliente, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Listar clientes no Imobi Brasil
     * GET /cliente/lista
     */
    public static function listClientes(Tenant $tenant, array $params = []): array
    {
        try {
            $apiKey  = static::getApiKey($tenant);
            $baseUrl = rtrim(static::getBaseUrl($tenant), '/');

            if (!$apiKey) {
                return ['success' => false, 'error' => 'Integração Imobi Brasil não configurada'];
            }

            $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 30, 'http_errors' => false]);

            $response = $client->get($baseUrl . '/cliente/lista', [
                'headers' => ['token' => $apiKey, 'Accept' => 'application/json'],
                'query'   => array_filter($params, fn($v) => $v !== null),
            ]);

            $data = json_decode($response->getBody()->getContents(), true) ?: [];

            return [
                'success'    => !empty($data['status']),
                'result_set' => $data['resultSet'] ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao listar clientes no Imobi Brasil', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // =========================================================================
    // CIDADES
    // =========================================================================

    /**
     * Listar cidades no Imobi Brasil
     * GET /cidade/lista
     */
    public static function listCidades(Tenant $tenant, array $params = []): array
    {
        try {
            $apiKey  = static::getApiKey($tenant);
            $baseUrl = rtrim(static::getBaseUrl($tenant), '/');

            if (!$apiKey) {
                return ['success' => false, 'error' => 'Integração Imobi Brasil não configurada'];
            }

            $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 30, 'http_errors' => false]);

            $response = $client->get($baseUrl . '/cidade/lista', [
                'headers' => ['token' => $apiKey, 'Accept' => 'application/json'],
                'query'   => array_filter($params, fn($v) => $v !== null),
            ]);

            $data = json_decode($response->getBody()->getContents(), true) ?: [];

            return [
                'success'    => !empty($data['status']),
                'result_set' => $data['resultSet'] ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao listar cidades no Imobi Brasil', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // =========================================================================
    // CONTA
    // =========================================================================

    /**
     * Status da conta no Imobi Brasil
     * GET /account/status
     */
    public static function getAccountStatus(Tenant $tenant): array
    {
        try {
            $apiKey  = static::getApiKey($tenant);
            $baseUrl = rtrim(static::getBaseUrl($tenant), '/');

            if (!$apiKey) {
                return ['success' => false, 'error' => 'Integração Imobi Brasil não configurada'];
            }

            $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 15, 'http_errors' => false]);

            $response = $client->get($baseUrl . '/account/status', [
                'headers' => ['token' => $apiKey, 'Accept' => 'application/json'],
            ]);

            $data = json_decode($response->getBody()->getContents(), true) ?: [];

            return [
                'success'      => !empty($data['status']),
                'result_set'   => $data['resultSet'] ?? [],
                'status_conta' => $data['resultSet']['statusConta'] ?? false,
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao verificar status da conta Imobi Brasil', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // =========================================================================
    // CORRETORES - Imóveis do corretor
    // =========================================================================

    /**
     * Lista imóveis de um corretor no Imobi Brasil
     * GET /corretor/imoveis/{codigoCorretor}
     */
    public static function listImoveisCorretor(int $codigoCorretor, Tenant $tenant): array
    {
        try {
            $apiKey  = static::getApiKey($tenant);
            $baseUrl = rtrim(static::getBaseUrl($tenant), '/');

            if (!$apiKey) {
                return ['success' => false, 'error' => 'Integração Imobi Brasil não configurada'];
            }

            $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 30, 'http_errors' => false]);

            $response = $client->get($baseUrl . '/corretor/imoveis/' . $codigoCorretor, [
                'headers' => ['token' => $apiKey, 'Accept' => 'application/json'],
            ]);

            $data = json_decode($response->getBody()->getContents(), true) ?: [];

            return [
                'success'    => !empty($data['status']),
                'result_set' => $data['resultSet'] ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao listar imóveis do corretor no Imobi Brasil', ['codigo' => $codigoCorretor, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // =========================================================================
    // CARACTERÍSTICAS DE IMÓVEIS
    // =========================================================================

    /**
     * Inserir característica no Imobi Brasil
     * POST /imovel/caracteristica/inserir
     */
    public static function insertCaracteristica(string $nomeCaracteristica, string $nomeGrupo, Tenant $tenant): array
    {
        try {
            $apiKey  = static::getApiKey($tenant);
            $baseUrl = rtrim(static::getBaseUrl($tenant), '/');

            if (!$apiKey) {
                return ['success' => false, 'error' => 'Integração Imobi Brasil não configurada'];
            }

            $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 30, 'http_errors' => false]);

            $response = $client->post($baseUrl . '/imovel/caracteristica/inserir', [
                'headers' => ['token' => $apiKey, 'Content-Type' => 'application/json', 'Accept' => 'application/json'],
                'json'    => ['nomeCaracteristica' => $nomeCaracteristica, 'nomeGrupo' => $nomeGrupo],
            ]);

            $data = json_decode($response->getBody()->getContents(), true) ?: [];

            return [
                'success'      => !empty($data['status']),
                'result_set'   => $data['resultSet'] ?? null,
                'api_response' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao inserir característica no Imobi Brasil', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Listar características no Imobi Brasil
     * GET /imovel/caracteristica/lista
     */
    public static function listCaracteristicas(Tenant $tenant, array $params = []): array
    {
        try {
            $apiKey  = static::getApiKey($tenant);
            $baseUrl = rtrim(static::getBaseUrl($tenant), '/');

            if (!$apiKey) {
                return ['success' => false, 'error' => 'Integração Imobi Brasil não configurada'];
            }

            $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 30, 'http_errors' => false]);

            $response = $client->get($baseUrl . '/imovel/caracteristica/lista', [
                'headers' => ['token' => $apiKey, 'Accept' => 'application/json'],
                'query'   => array_filter($params, fn($v) => $v !== null),
            ]);

            $data = json_decode($response->getBody()->getContents(), true) ?: [];

            return [
                'success'    => !empty($data['status']),
                'result_set' => $data['resultSet'] ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao listar características no Imobi Brasil', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Adicionar característica a um imóvel no Imobi Brasil
     * POST /imovel/{codigoImovel}/caracteristica/inserir/{codigoCaracteristica}
     */
    public static function addCaracteristicaToProperty(int $codigoImovel, int $codigoCaracteristica, Tenant $tenant): array
    {
        try {
            $apiKey  = static::getApiKey($tenant);
            $baseUrl = rtrim(static::getBaseUrl($tenant), '/');

            if (!$apiKey) {
                return ['success' => false, 'error' => 'Integração Imobi Brasil não configurada'];
            }

            $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 30, 'http_errors' => false]);

            $response = $client->post($baseUrl . '/imovel/' . $codigoImovel . '/caracteristica/inserir/' . $codigoCaracteristica, [
                'headers' => [
                    'token'               => $apiKey,
                    'codigoImovel'        => $codigoImovel,
                    'codigoCaracteristica' => $codigoCaracteristica,
                    'Content-Type'        => 'application/json',
                    'Accept'              => 'application/json',
                ],
                'json' => ['codigoImovel' => $codigoImovel, 'codigoCaracteristica' => $codigoCaracteristica],
            ]);

            $data = json_decode($response->getBody()->getContents(), true) ?: [];

            return [
                'success'      => !empty($data['status']),
                'api_response' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao adicionar característica ao imóvel no Imobi Brasil', [
                'codigoImovel'        => $codigoImovel,
                'codigoCaracteristica' => $codigoCaracteristica,
                'error'               => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Remover característica de um imóvel no Imobi Brasil
     * POST /imovel/{codigoImovel}/caracteristica/excluir/{codigoCaracteristica}
     */
    public static function removeCaracteristicaFromProperty(int $codigoImovel, int $codigoCaracteristica, Tenant $tenant): array
    {
        try {
            $apiKey  = static::getApiKey($tenant);
            $baseUrl = rtrim(static::getBaseUrl($tenant), '/');

            if (!$apiKey) {
                return ['success' => false, 'error' => 'Integração Imobi Brasil não configurada'];
            }

            $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 30, 'http_errors' => false]);

            $response = $client->post($baseUrl . '/imovel/' . $codigoImovel . '/caracteristica/excluir/' . $codigoCaracteristica, [
                'headers' => [
                    'token'               => $apiKey,
                    'codigoImovel'        => $codigoImovel,
                    'codigoCaracteristica' => $codigoCaracteristica,
                    'Accept'              => 'application/json',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true) ?: [];

            return [
                'success'      => !empty($data['status']),
                'api_response' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao remover característica do imóvel no Imobi Brasil', [
                'codigoImovel'        => $codigoImovel,
                'codigoCaracteristica' => $codigoCaracteristica,
                'error'               => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Excluir uma característica no Imobi Brasil
     * POST /imovel/caracteristica/excluir/{codigoCaracteristica}
     */
    public static function deleteCaracteristica(int $codigoCaracteristica, Tenant $tenant): array
    {
        try {
            $apiKey  = static::getApiKey($tenant);
            $baseUrl = rtrim(static::getBaseUrl($tenant), '/');

            if (!$apiKey) {
                return ['success' => false, 'error' => 'Integração Imobi Brasil não configurada'];
            }

            $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 30, 'http_errors' => false]);

            $response = $client->post($baseUrl . '/imovel/caracteristica/excluir/' . $codigoCaracteristica, [
                'headers' => ['token' => $apiKey, 'codigoCaracteristica' => $codigoCaracteristica, 'Accept' => 'application/json'],
            ]);

            $data = json_decode($response->getBody()->getContents(), true) ?: [];

            return [
                'success'      => !empty($data['status']),
                'api_response' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao excluir característica no Imobi Brasil', ['codigo' => $codigoCaracteristica, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // =========================================================================
    // IMAGENS DE IMÓVEIS
    // =========================================================================

    /**
     * Excluir imagem de um imóvel no Imobi Brasil
     * POST /imovel/{codigoImovel}/imagem/excluir/{codigoImagem}
     */
    public static function deletePropertyImage(int $codigoImovel, int $codigoImagem, Tenant $tenant): array
    {
        try {
            $apiKey  = static::getApiKey($tenant);
            $baseUrl = rtrim(static::getBaseUrl($tenant), '/');

            if (!$apiKey) {
                return ['success' => false, 'error' => 'Integração Imobi Brasil não configurada'];
            }

            $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 30, 'http_errors' => false]);

            $response = $client->post($baseUrl . '/imovel/' . $codigoImovel . '/imagem/excluir/' . $codigoImagem, [
                'headers' => [
                    'token'        => $apiKey,
                    'codigoImovel' => $codigoImovel,
                    'codigoImagem' => $codigoImagem,
                    'Accept'       => 'application/json',
                ],
            ]);

            $data = json_decode($response->getBody()->getContents(), true) ?: [];

            return [
                'success'      => !empty($data['status']),
                'api_response' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao excluir imagem do imóvel no Imobi Brasil', [
                'codigoImovel' => $codigoImovel,
                'codigoImagem' => $codigoImagem,
                'error'        => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Listar imagens de um imóvel no Imobi Brasil
     * GET /imovel/{codigoImovel}/imagem/lista
     */
    public static function listPropertyImages(int $codigoImovel, Tenant $tenant, array $params = []): array
    {
        try {
            $apiKey  = static::getApiKey($tenant);
            $baseUrl = rtrim(static::getBaseUrl($tenant), '/');

            if (!$apiKey) {
                return ['success' => false, 'error' => 'Integração Imobi Brasil não configurada'];
            }

            $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 30, 'http_errors' => false]);

            $response = $client->get($baseUrl . '/imovel/' . $codigoImovel . '/imagem/lista', [
                'headers' => ['token' => $apiKey, 'Accept' => 'application/json'],
                'query'   => array_filter($params, fn($v) => $v !== null),
            ]);

            $data = json_decode($response->getBody()->getContents(), true) ?: [];

            // A API pode retornar resultSet como array direto ou como {data: [...], total: N}
            $rawResultSet = $data['resultSet'] ?? [];
            $images = is_array($rawResultSet)
                ? (isset($rawResultSet['data']) ? $rawResultSet['data'] : array_values($rawResultSet))
                : [];

            Log::debug('Imobi Brasil - listPropertyImages', [
                'codigoImovel' => $codigoImovel,
                'status'       => $data['status'] ?? null,
                'count'        => count($images),
                'raw_keys'     => is_array($rawResultSet) ? array_keys($rawResultSet) : null,
            ]);

            return [
                'success'    => !empty($data['status']),
                'result_set' => $images,
                'total'      => $rawResultSet['total'] ?? count($images),
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao listar imagens do imóvel no Imobi Brasil', ['codigoImovel' => $codigoImovel, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // =========================================================================
    // PESSOAS - Imagem
    // =========================================================================

    /**
     * Excluir imagem de uma pessoa no Imobi Brasil
     * POST /pessoa/excluir/imagem/{codigoPessoa}
     */
    public static function deletePessoaImage(int $codigoPessoa, Tenant $tenant): array
    {
        try {
            $apiKey  = static::getApiKey($tenant);
            $baseUrl = rtrim(static::getBaseUrl($tenant), '/');

            if (!$apiKey) {
                return ['success' => false, 'error' => 'Integração Imobi Brasil não configurada'];
            }

            $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 30, 'http_errors' => false]);

            $response = $client->post($baseUrl . '/pessoa/excluir/imagem/' . $codigoPessoa, [
                'headers' => ['token' => $apiKey, 'codigoPessoa' => $codigoPessoa, 'Accept' => 'application/json'],
            ]);

            $data = json_decode($response->getBody()->getContents(), true) ?: [];

            return [
                'success'      => !empty($data['status']),
                'api_response' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao excluir imagem da pessoa no Imobi Brasil', ['codigo' => $codigoPessoa, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // =========================================================================
    // USUÁRIO ADICIONAL
    // =========================================================================

    /**
     * Dados de um usuário adicional no Imobi Brasil
     * GET /usuario-adicional/dados/{codigoUsuario}
     */
    public static function getUsuarioAdicional(int $codigoUsuario, Tenant $tenant): array
    {
        try {
            $apiKey  = static::getApiKey($tenant);
            $baseUrl = rtrim(static::getBaseUrl($tenant), '/');

            if (!$apiKey) {
                return ['success' => false, 'error' => 'Integração Imobi Brasil não configurada'];
            }

            $client = new \GuzzleHttp\Client(['verify' => false, 'timeout' => 30, 'http_errors' => false]);

            $response = $client->get($baseUrl . '/usuario-adicional/dados/' . $codigoUsuario, [
                'headers' => ['token' => $apiKey, 'codigoUsuario' => $codigoUsuario, 'Accept' => 'application/json'],
            ]);

            $data = json_decode($response->getBody()->getContents(), true) ?: [];

            return [
                'success'    => !empty($data['status']),
                'result_set' => $data['resultSet'] ?? [],
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao buscar usuário adicional no Imobi Brasil', ['codigo' => $codigoUsuario, 'error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
