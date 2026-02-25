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
     * Enviar imóvel para Imobi Brasil
     */
    public static function sendProperty(Property $property, Tenant $tenant): array
    {
        try {
            // Validar configuração do tenant
            if (!$tenant->imobi_brasil_enabled || !$tenant->imobi_brasil_api_key) {
                return [
                    'success' => false,
                    'error' => 'Integração Imobi Brasil não configurada para este tenant',
                ];
            }

            // Preparar dados do imóvel
            $payload = self::preparePropertyPayload($property);

            $baseUrl = $tenant->imobi_brasil_base_url ?? 'https://api.imobibrasil.com.br';
            $endpoint = $baseUrl . '/v1/properties';

            Log::info('Enviando imóvel para Imobi Brasil', [
                'property_id' => $property->id,
                'tenant_id' => $tenant->id,
                'endpoint' => $endpoint,
            ]);

            // Fazer requisição para Imobi Brasil
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $tenant->imobi_brasil_api_key,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->post($endpoint, $payload);

            // Validar resposta
            if (!$response->successful()) {
                $errorMessage = $response->json('message') ?? $response->json('error') ?? $response->body();
                
                Log::warning('Falha ao enviar imóvel para Imobi Brasil', [
                    'property_id' => $property->id,
                    'status' => $response->status(),
                    'error' => $errorMessage,
                ]);

                return [
                    'success' => false,
                    'error' => $errorMessage,
                    'status' => $response->status(),
                ];
            }

            $responseData = $response->json();
            $externalId = $responseData['id'] ?? $responseData['external_id'] ?? null;

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

            if (!$tenant->imobi_brasil_enabled || !$tenant->imobi_brasil_api_key) {
                return [
                    'success' => false,
                    'error' => 'Integração Imobi Brasil não configurada',
                ];
            }

            $payload = self::preparePropertyPayload($property);

            $baseUrl = $tenant->imobi_brasil_base_url ?? 'https://api.imobibrasil.com.br';
            $endpoint = $baseUrl . '/v1/properties/' . $property->imobi_brasil_external_id;

            Log::info('Atualizando imóvel no Imobi Brasil', [
                'property_id' => $property->id,
                'external_id' => $property->imobi_brasil_external_id,
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $tenant->imobi_brasil_api_key,
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])->put($endpoint, $payload);

            if (!$response->successful()) {
                $errorMessage = $response->json('message') ?? $response->body();
                
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
