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
            return rtrim($url, '/');
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
        return 'https://exclusivalarimoveis.com.br';
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
     * Usando padrão Guzzle da importação
     */
    public static function sendProperty(Property $property, Tenant $tenant): array
    {
        try {
            // Validar configuração do tenant
            $apiKey = static::getApiKey($tenant);
            if (!$apiKey) {
                return [
                    'success' => false,
                    'error' => 'Integração Imobi Brasil não configurada para este tenant',
                ];
            }

            // Preparar dados do imóvel
            $payload = self::preparePropertyPayload($property);

            $baseUrl = static::getBaseUrl($tenant);
            $baseUrl = rtrim($baseUrl, '/');
            
            // Endpoint para inserção: POST /imovel/inserir
            $endpoint = $baseUrl . '/imovel/inserir';

            Log::info('Enviando imóvel para Imobi Brasil', [
                'property_id' => $property->id,
                'tenant_id' => $tenant->id,
                'endpoint' => $endpoint,
            ]);

            // Fazer requisição com Guzzle (padrão igual à importação)
            $client = new \GuzzleHttp\Client([
                'verify' => env('VERIFY_SSL_CERTIFICATES', true),
                'timeout' => 30,
                'http_errors' => false
            ]);

            $response = $client->post($endpoint, [
                'headers' => [
                    'token' => $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            // Validar resposta
            if ($statusCode !== 200 && $statusCode !== 201) {
                $errorMessage = 'API retornou HTTP ' . $statusCode;
                
                try {
                    $jsonError = json_decode($body, true);
                    $errorMessage = $jsonError['message'] ?? $jsonError['error'] ?? $body;
                } catch (\Exception $e) {
                    $errorMessage = substr($body, 0, 200);
                }
                
                Log::warning('Falha ao enviar imóvel para Imobi Brasil', [
                    'property_id' => $property->id,
                    'status' => $statusCode,
                    'error' => $errorMessage,
                ]);

                return [
                    'success' => false,
                    'error' => $errorMessage,
                    'status' => $statusCode,
                ];
            }

            // Decodificar resposta
            $responseData = json_decode($body, true) ?? [];
            // A API retorna o código do imóvel
            $externalId = $responseData['codigo'] ?? $responseData['codigoImovel'] ?? $responseData['id'] ?? null;

            // Atualizar imóvel com informações de envio
            $property->update([
                'imobi_brasil_sent' => true,
                'imobi_brasil_sent_at' => Carbon::now(),
                'imobi_brasil_external_id' => $externalId,
                'imobi_brasil_error' => null,
            ]);

            Log::info('Imóvel enviado com sucesso para Imobi Brasil', [
                'property_id' => $property->id,
                'external_id' => $externalId,
            ]);

            return [
                'success' => true,
                'message' => 'Imóvel enviado com sucesso',
                'external_id' => $externalId,
                'response' => $responseData,
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao enviar imóvel para Imobi Brasil', [
                'property_id' => $property->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Registrar erro no imóvel
            $property->update([
                'imobi_brasil_error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Atualizar imóvel no Imobi Brasil
     * Usando padrão Guzzle da importação
     */
    public static function updateProperty(Property $property, Tenant $tenant): array
    {
        try {
            if (!$property->imobi_brasil_external_id) {
                return [
                    'success' => false,
                    'error' => 'Imóvel não foi enviado para Imobi Brasil ainda',
                ];
            }

            $apiKey = static::getApiKey($tenant);
            if (!$apiKey) {
                return [
                    'success' => false,
                    'error' => 'Integração Imobi Brasil não configurada',
                ];
            }

            $payload = self::preparePropertyPayload($property);

            $baseUrl = static::getBaseUrl($tenant);
            $baseUrl = rtrim($baseUrl, '/');
            // Endpoint para alteração: POST /imovel/alterar/{codigoImovel}
            $endpoint = $baseUrl . '/imovel/alterar/' . urlencode($property->imobi_brasil_external_id);

            Log::info('Atualizando imóvel no Imobi Brasil', [
                'property_id' => $property->id,
                'external_id' => $property->imobi_brasil_external_id,
            ]);

            // Fazer requisição com Guzzle (padrão igual à importação)
            $client = new \GuzzleHttp\Client([
                'verify' => env('VERIFY_SSL_CERTIFICATES', true),
                'timeout' => 30,
                'http_errors' => false
            ]);

            $response = $client->post($endpoint, [
                'headers' => [
                    'token' => $apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $statusCode = $response->getStatusCode();
            $body = $response->getBody()->getContents();

            if ($statusCode !== 200 && $statusCode !== 201) {
                $errorMessage = 'API retornou HTTP ' . $statusCode;
                
                try {
                    $jsonError = json_decode($body, true);
                    $errorMessage = $jsonError['message'] ?? $jsonError['error'] ?? $body;
                } catch (\Exception $e) {
                    $errorMessage = substr($body, 0, 200);
                }
                
                Log::warning('Falha ao atualizar imóvel no Imobi Brasil', [
                    'property_id' => $property->id,
                    'error' => $errorMessage,
                ]);

                return [
                    'success' => false,
                    'error' => $errorMessage,
                ];
            }

            $property->update([
                'imobi_brasil_sent_at' => Carbon::now(),
                'imobi_brasil_error' => null,
            ]);

            Log::info('Imóvel atualizado com sucesso no Imobi Brasil', [
                'property_id' => $property->id,
            ]);

            return [
                'success' => true,
                'message' => 'Imóvel atualizado com sucesso',
            ];
        } catch (\Exception $e) {
            Log::error('Erro ao atualizar imóvel no Imobi Brasil', [
                'property_id' => $property->id,
                'error' => $e->getMessage(),
            ]);

            $property->update([
                'imobi_brasil_error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Preparar payload do imóvel para envio
     */
    public static function preparePropertyPayload(Property $property): array
    {
        $imagens = [];
        if ($property->imagens && is_array($property->imagens)) {
            $imagens = array_map(fn($img) => [
                'url' => $img,
                'destaque' => $img === $property->imagem_destaque,
            ], $property->imagens);
        }

        $endereco = trim(implode(', ', array_filter([
            $property->logradouro,
            $property->numero,
            $property->complemento,
            $property->bairro,
            $property->cidade . '/' . $property->estado,
            $property->cep,
        ])));

        return [
            'titulo' => $property->titulo,
            'descricao' => $property->descricao,
            'descricao_resumida' => $property->descricao_resumida,
            'tipo_imovel' => $property->tipo_imovel,
            'finalidade_imovel' => $property->finalidade_imovel,
            'valor_venda' => (float) $property->valor_venda,
            'valor_condominio' => (float) ($property->valor_condominio ?? 0),
            'valor_iptu' => (float) ($property->valor_iptu ?? 0),
            'endereco' => $endereco,
            'logradouro' => $property->logradouro,
            'numero' => $property->numero,
            'complemento' => $property->complemento,
            'bairro' => $property->bairro,
            'cidade' => $property->cidade,
            'estado' => $property->estado,
            'cep' => $property->cep,
            'latitude' => $property->latitude,
            'longitude' => $property->longitude,
            'dormitorios' => (int) ($property->dormitorios ?? 0),
            'suites' => (int) ($property->suites ?? 0),
            'banheiros' => (int) ($property->banheiros ?? 0),
            'garagem' => (int) ($property->garagem ?? 0),
            'area_total' => (float) ($property->area_total ?? 0),
            'area_privativa' => (float) ($property->area_privativa ?? 0),
            'area_terreno' => (float) ($property->area_terreno ?? 0),
            'em_condominio' => (bool) $property->em_condominio,
            'nome_condominio' => $property->nome_condominio,
            'visibilidade_endereco' => $property->visibilidade_endereco,
            'active' => (bool) $property->active,
            'imagens' => $imagens,
        ];
    }
}
