<?php

namespace App\Services;

use App\Models\CommissionInvoice;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class NfseCommissionService
{
    private const NFSE_FILE_REDIRECT_STATUSES = [301, 302, 303, 307, 308];

    public function emitir(CommissionInvoice $invoice, array $tomador, array $financeiro = []): array
    {
        $tenant = $invoice->tenant ?? Tenant::find($invoice->tenant_id);
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

        $payload = $this->montarPayload($invoice, $tomador, $financeiro, $tenant);
        $endpoint = rtrim($baseUrl, '/') . '/v1/companies/' . $companyId . '/serviceinvoices';
        $idempotencyKey = (string) Str::uuid();

        Log::info('Enviando solicitação de NFSe para NFe.io', [
            'invoice_id' => $invoice->id,
            'endpoint' => $endpoint,
            'tenant_id' => $invoice->tenant_id,
            'idempotency_key' => $idempotencyKey,
        ]);

        $response = Http::withHeaders([
            'X-NFE-APIKEY' => $apiKey,
            'Authorization' => $apiKey,
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Idempotency-Key' => $idempotencyKey,
        ])->timeout(30)
            ->post($endpoint, $payload);

        $body = $response->json() ?? [];

        if ($response->failed()) {
            Log::error('Falha ao emitir NFSe na NFe.io', [
                'invoice_id' => $invoice->id,
                'status' => $response->status(),
                'body' => $body ?: $response->body(),
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
            'nfse_numero' => data_get($body, 'number') ?? data_get($body, 'nfse.numero'),
            'codigo_verificacao' => data_get($body, 'checkCode') ?? data_get($body, 'nfse.codigo_verificacao'),
            'nfse_rps' => data_get($body, 'rpsNumber') ?? data_get($body, 'nfse.rps'),
            'pdf_url' => $pdfUrl,
            'xml_url' => $xmlUrl,
            'integracao_id' => $integracaoId,
            'raw_response' => array_merge($body, [
                'submitted_payload' => $payload,
                'service_code_used' => $payload['cityServiceCode'] ?? null,
            ]),
            'payload' => $payload,
            'financeiro_status' => $status,
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

            Log::warning('NFe.io não retornou URL assinada para arquivo da NFSe de comissão', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'tipo_arquivo' => $tipoArquivo,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Falha ao resolver arquivo da NFSe de comissão na NFe.io', [
                'endpoint' => $endpoint,
                'tipo_arquivo' => $tipoArquivo,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    private function montarPayload(CommissionInvoice $invoice, array $tomador, array $financeiro, ?Tenant $tenant): array
    {
        $documento = $this->somenteDigitos($tomador['documento'] ?? null);
        $cityServiceCode = $this->resolverCityServiceCode($invoice, $tenant);
        $nationalTaxCode = $this->resolverNationalTaxCode($invoice, $tenant);

        $payload = [
            'cityServiceCode' => $cityServiceCode,
            'nationalTaxCode' => $nationalTaxCode,
            'description' => $invoice->descricao_servico,
            'servicesAmount' => (float) $invoice->valor_total,
            'borrower' => [
                'federalTaxNumber' => $documento,
                'name' => $tomador['nome'] ?? null,
                'email' => $tomador['email'] ?? null,
            ],
            'additionalInformation' => $this->montarInformacoesAdicionais($invoice, $financeiro),
        ];

        $enderecoTomador = $this->normalizarEndereco($tomador['endereco'] ?? null);
        if (!empty($enderecoTomador)) {
            $payload['borrower']['address'] = $enderecoTomador;
        }

        return $this->limparPayload($payload);
    }

    private function resolverCityServiceCode(CommissionInvoice $invoice, ?Tenant $tenant): string
    {
        $tipoNota = strtolower((string) data_get($invoice->financeiro_metadata, 'tipo_nota', 'corretagem'));

        $keys = $tipoNota === 'aluguel'
            ? ['nfeio_service_code_aluguel', 'nfeio_service_code_locatario', 'nfeio_service_code']
            : ['nfeio_service_code_corretagem', 'nfeio_service_code_proprietario', 'nfeio_service_code'];

        foreach ($keys as $key) {
            $value = $tenant?->getIntegrationValue($key);
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        if ($tipoNota === 'aluguel') {
            return env('NFE_IO_SERVICE_CODE_ALUGUEL', env('NFE_IO_SERVICE_CODE_LOCATARIO', env('NFE_IO_SERVICE_CODE', '01.01')));
        }

        return env('NFE_IO_SERVICE_CODE_CORRETAGEM', env('NFE_IO_SERVICE_CODE_PROPRIETARIO', env('NFE_IO_SERVICE_CODE', '004')));
    }

    private function resolverNationalTaxCode(CommissionInvoice $invoice, ?Tenant $tenant): ?string
    {
        $tipoNota = strtolower((string) data_get($invoice->financeiro_metadata, 'tipo_nota', 'corretagem'));

        $keys = $tipoNota === 'aluguel'
            ? ['nfse_national_service_code_aluguel', 'nfse_national_service_code_locatario', 'nfse_national_service_code']
            : ['nfse_national_service_code_corretagem', 'nfse_national_service_code_proprietario', 'nfse_national_service_code'];

        foreach ($keys as $key) {
            $normalized = $this->normalizarNationalTaxCode($tenant?->getIntegrationValue($key));
            if ($normalized) {
                return $normalized;
            }
        }

        $metadataKey = $tipoNota === 'aluguel'
            ? 'nfse_national_service_code_locatario'
            : 'nfse_national_service_code';

        $normalizedMetadata = $this->normalizarNationalTaxCode(data_get($tenant?->metadata, $metadataKey));
        if ($normalizedMetadata) {
            return $normalizedMetadata;
        }

        if ($tipoNota !== 'aluguel') {
            return '100501';
        }

        return $this->normalizarNationalTaxCode(
            env('NFE_IO_NATIONAL_TAX_CODE_ALUGUEL', env('NFE_IO_NATIONAL_TAX_CODE_LOCATARIO', env('NFE_IO_NATIONAL_TAX_CODE')))
        );
    }

    private function normalizarNationalTaxCode(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $value) ?: '';

        return strlen($digits) === 6 ? $digits : null;
    }

    private function montarInformacoesAdicionais(CommissionInvoice $invoice, array $financeiro): string
    {
        $partes = [
            'Invoice #' . $invoice->id,
            'Corretor #' . $invoice->corretor_id,
        ];

        if ($invoice->lead_id) {
            $partes[] = 'Lead #' . $invoice->lead_id;
        }

        if ($invoice->property_id) {
            $partes[] = 'Imóvel #' . $invoice->property_id;
        }

        if (!empty($financeiro['forma_pagamento'])) {
            $partes[] = 'Pagamento: ' . $financeiro['forma_pagamento'];
        }

        if (!empty($financeiro['vencimento'])) {
            $partes[] = 'Vencimento: ' . $financeiro['vencimento'];
        }

        return implode(' | ', $partes);
    }

    private function normalizarEndereco($endereco): ?array
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

    private function somenteDigitos(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        return preg_replace('/\D+/', '', $value);
    }

    private function limparPayload(array $payload): array
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
}
