<?php

namespace App\Services;

use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class NfseService
{
    private const NFSE_FILE_REDIRECT_STATUSES = [301, 302, 303, 307, 308];

    private function getTenantNfeConfig(int $tenantId): array
    {
        $tenant = Tenant::find($tenantId);

        return [
            'base_url' => $tenant?->getIntegrationValue(
                'nfeio_base_url',
                env('NFE_IO_BASE_URL', env('NFSE_API_URL', 'https://api.nfe.io'))
            ),
            'api_key' => $tenant?->getIntegrationValue(
                'nfeio_api_key',
                env('NFE_IO_API_KEY', env('NFSE_API_TOKEN'))
            ),
            'company_id' => $tenant?->getIntegrationValue(
                'nfeio_company_id',
                env('NFE_IO_COMPANY_ID')
            ),
        ];
    }

    public function emitir(int $tenantId, array $payload, array $context = []): array
    {
        [
            'base_url' => $baseUrl,
            'api_key' => $apiKey,
            'company_id' => $companyId,
        ] = $this->getTenantNfeConfig($tenantId);

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
            'payload_debug' => $this->resumirPayloadFiscal($payload),
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
                'payload_debug' => $this->resumirPayloadFiscal($payload),
                'payload' => $payload,
            ]);

            $message = data_get($body, 'error.message')
                ?? data_get($body, 'message')
                ?? 'Erro ao emitir NFSe na NFe.io';

            throw new RuntimeException($message);
        }

        $integracaoId = data_get($body, 'id') ?? data_get($body, 'nfse.id') ?? Str::uuid()->toString();
        $status = data_get($body, 'status') ?? data_get($body, 'financeiro.status');
        ['pdf_url' => $pdfUrl, 'xml_url' => $xmlUrl] = $this->resolverDocumentoUrls(
            $baseUrl,
            $apiKey,
            $companyId,
            $integracaoId,
            $status,
            $body
        );

        return [
            'numero' => data_get($body, 'number') ?? data_get($body, 'nfse.numero'),
            'codigo_verificacao' => data_get($body, 'checkCode') ?? data_get($body, 'nfse.codigo_verificacao'),
            'rps' => data_get($body, 'rpsNumber') ?? data_get($body, 'nfse.rps'),
            'pdf_url' => $pdfUrl,
            'xml_url' => $xmlUrl,
            'integracao_id' => $integracaoId,
            'raw_response' => $body,
            'payload' => $payload,
            'status' => $status,
        ];
    }

    public function consultar(int $tenantId, string $integracaoId, array $context = []): array
    {
        [
            'base_url' => $baseUrl,
            'api_key' => $apiKey,
            'company_id' => $companyId,
        ] = $this->getTenantNfeConfig($tenantId);

        if (!$baseUrl || !$apiKey || !$companyId) {
            throw new RuntimeException('Credenciais da NFe.io não configuradas (NFE_IO_BASE_URL, NFE_IO_API_KEY, NFE_IO_COMPANY_ID)');
        }

        $endpoint = rtrim($baseUrl, '/') . '/v1/companies/' . $companyId . '/serviceinvoices/' . $integracaoId;

        Log::info('Consultando NFSe na NFe.io', [
            'tenant_id' => $tenantId,
            'endpoint' => $endpoint,
            'integracao_id' => $integracaoId,
            'context' => $context,
        ]);

        $response = Http::withHeaders([
            'X-NFE-APIKEY' => $apiKey,
            'Authorization' => $apiKey,
            'Accept' => 'application/json',
        ])->timeout(30)->get($endpoint);

        $body = $response->json() ?? [];

        if ($response->failed()) {
            Log::error('Falha ao consultar NFSe na NFe.io', [
                'tenant_id' => $tenantId,
                'status' => $response->status(),
                'body' => $body ?: $response->body(),
                'context' => $context,
            ]);

            $message = data_get($body, 'error.message')
                ?? data_get($body, 'message')
                ?? 'Erro ao consultar NFSe na NFe.io';

            throw new RuntimeException($message);
        }

        $resolvedIntegracaoId = data_get($body, 'id') ?? $integracaoId;
        $status = data_get($body, 'status') ?? data_get($body, 'financeiro.status');
        ['pdf_url' => $pdfUrl, 'xml_url' => $xmlUrl] = $this->resolverDocumentoUrls(
            $baseUrl,
            $apiKey,
            $companyId,
            $resolvedIntegracaoId,
            $status,
            $body
        );

        return [
            'numero' => data_get($body, 'number') ?? data_get($body, 'nfse.numero'),
            'codigo_verificacao' => data_get($body, 'checkCode') ?? data_get($body, 'nfse.codigo_verificacao'),
            'rps' => data_get($body, 'rpsNumber') ?? data_get($body, 'nfse.rps'),
            'pdf_url' => $pdfUrl,
            'xml_url' => $xmlUrl,
            'integracao_id' => $resolvedIntegracaoId,
            'raw_response' => $body,
            'status' => $status,
        ];
    }

    private function resolverDocumentoUrls(
        string $baseUrl,
        string $apiKey,
        string $companyId,
        ?string $integracaoId,
        mixed $status,
        array $body
    ): array {
        $pdfUrl = data_get($body, 'pdfUrl')
            ?? data_get($body, 'links.pdf')
            ?? data_get($body, 'pdf.url')
            ?? data_get($body, 'pdf.downloadUrl');
        $xmlUrl = data_get($body, 'xmlUrl')
            ?? data_get($body, 'links.xml')
            ?? data_get($body, 'xml.url')
            ?? data_get($body, 'xml.downloadUrl');

        $statusNormalizado = strtolower(trim((string) $status));
        $podeResolverArquivos = $integracaoId
            && in_array($statusNormalizado, ['issued', 'authorized'], true);

        if (!$podeResolverArquivos) {
            return [
                'pdf_url' => $pdfUrl,
                'xml_url' => $xmlUrl,
            ];
        }

        return [
            'pdf_url' => $pdfUrl ?: $this->resolverArquivoNfse($baseUrl, $apiKey, $companyId, $integracaoId, 'pdf'),
            'xml_url' => $xmlUrl ?: $this->resolverArquivoNfse($baseUrl, $apiKey, $companyId, $integracaoId, 'xml'),
        ];
    }

    private function resolverArquivoNfse(
        string $baseUrl,
        string $apiKey,
        string $companyId,
        string $integracaoId,
        string $tipoArquivo
    ): ?string {
        $endpoint = rtrim($baseUrl, '/') . '/v1/companies/' . $companyId . '/serviceinvoices/' . $integracaoId . '/' . $tipoArquivo;

        try {
            $response = Http::withHeaders([
                'X-NFE-APIKEY' => $apiKey,
                'Authorization' => $apiKey,
                'Accept' => '*/*',
            ])->withOptions([
                'allow_redirects' => false,
            ])->timeout(30)->get($endpoint);

            if (in_array($response->status(), self::NFSE_FILE_REDIRECT_STATUSES, true)) {
                return $response->header('Location');
            }

            if ($response->successful()) {
                return $endpoint;
            }

            Log::warning('NFe.io não retornou URL assinada para arquivo da NFSe', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'tipo_arquivo' => $tipoArquivo,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Falha ao resolver arquivo da NFSe na NFe.io', [
                'endpoint' => $endpoint,
                'tipo_arquivo' => $tipoArquivo,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
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

    private function resumirPayloadFiscal(array $payload): array
    {
        return [
            'cityServiceCode' => data_get($payload, 'cityServiceCode'),
            'nationalTaxCode' => data_get($payload, 'nationalTaxCode'),
            'serviceCode' => data_get($payload, 'serviceCode'),
            'servicesAmount' => data_get($payload, 'servicesAmount'),
        ];
    }
}