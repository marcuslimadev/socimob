<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class NfseService
{
    public function emitir(int $tenantId, array $payload, array $context = []): array
    {
        $tenant = Tenant::find($tenantId);
        $baseUrl = $tenant?->getIntegrationValue(
            'nfeio_base_url',
            env('NFE_IO_BASE_URL', env('NFSE_API_URL', 'https://api.nfe.io'))
        );
        $apiKey = $tenant?->getIntegrationValue(
            'nfeio_api_key',
            env('NFE_IO_API_KEY', env('NFSE_API_TOKEN'))
        );
        $companyId = $tenant?->getIntegrationValue(
            'nfeio_company_id',
            env('NFE_IO_COMPANY_ID')
        );

        if (!$baseUrl || !$apiKey || !$companyId) {
            throw new RuntimeException('Credenciais da NFe.io não configuradas (NFE_IO_BASE_URL, NFE_IO_API_KEY, NFE_IO_COMPANY_ID)');
        }

        $endpoint = rtrim($baseUrl, '/') . '/v1/companies/' . $companyId . '/serviceinvoices';
        $idempotencyKey = (string) Str::uuid();

        Log::info('Enviando solicitação de NFSe para NFe.io', [
            'tenant_id' => $tenantId,
            'endpoint' => $endpoint,
            'idempotency_key' => $idempotencyKey,
            'context' => $context,
        ]);

        $response = Http::withHeaders([
            'X-NFE-APIKEY' => $apiKey,
            'Authorization' => $apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Idempotency-Key' => $idempotencyKey,
        ])->timeout(30)->post($endpoint, $payload);

        $body = $response->json() ?? [];

        if ($response->failed()) {
            Log::error('Falha ao emitir NFSe na NFe.io', [
                'tenant_id' => $tenantId,
                'status' => $response->status(),
                'body' => $body ?: $response->body(),
                'context' => $context,
            ]);

            $message = data_get($body, 'error.message')
                ?? data_get($body, 'message')
                ?? 'Erro ao emitir NFSe na NFe.io';

            throw new RuntimeException($message);
        }

        return [
            'numero' => data_get($body, 'number') ?? data_get($body, 'nfse.numero'),
            'codigo_verificacao' => data_get($body, 'checkCode') ?? data_get($body, 'nfse.codigo_verificacao'),
            'rps' => data_get($body, 'rpsNumber') ?? data_get($body, 'nfse.rps'),
            'pdf_url' => data_get($body, 'pdfUrl') ?? data_get($body, 'links.pdf'),
            'xml_url' => data_get($body, 'xmlUrl') ?? data_get($body, 'links.xml'),
            'integracao_id' => data_get($body, 'id') ?? data_get($body, 'nfse.id') ?? Str::uuid()->toString(),
            'raw_response' => $body,
            'payload' => $payload,
            'status' => data_get($body, 'status') ?? data_get($body, 'financeiro.status'),
        ];
    }

    public function limparPayload(array $payload): array
    {
        return collect($payload)
            ->map(function ($value) {
                if (is_array($value)) {
                    $filtered = $this->limparPayload($value);
                    return empty($filtered) ? null : $filtered;
                }

                return $value;
            })
            ->filter(function ($value) {
                if (is_array($value)) {
                    return count($value) > 0;
                }

                return !is_null($value);
            })
            ->all();
    }

    public function normalizarEndereco($endereco): ?array
    {
        if (!is_array($endereco)) {
            return null;
        }

        return $this->limparPayload([
            'country' => 'BRA',
            'postalCode' => $this->somenteDigitos($endereco['cep'] ?? null),
            'street' => $endereco['logradouro'] ?? $endereco['street'] ?? null,
            'number' => $endereco['numero'] ?? $endereco['number'] ?? 'S/N',
            'additionalInformation' => $endereco['complemento'] ?? $endereco['additionalInformation'] ?? null,
            'district' => $endereco['bairro'] ?? $endereco['district'] ?? null,
            'city' => [
                'code' => $endereco['codigoMunicipio'] ?? $endereco['city_code'] ?? null,
                'name' => $endereco['cidade'] ?? $endereco['city'] ?? null,
            ],
            'state' => $endereco['uf'] ?? $endereco['state'] ?? null,
        ]);
    }

    public function somenteDigitos(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        return preg_replace('/\D+/', '', $value);
    }
}