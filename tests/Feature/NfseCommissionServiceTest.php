<?php

namespace Tests\Feature;

use App\Models\CommissionInvoice;
use App\Models\Tenant;
use App\Services\NfseCommissionService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NfseCommissionServiceTest extends TestCase
{
    public function test_emitir_nfse_na_nfeio_com_headers_e_payload_esperados(): void
    {
        Http::fake([
            'https://api.nfe.io/v1/companies/company-test/serviceinvoices' => Http::response([
                'id' => 'nfse-123',
                'number' => '1001',
                'checkCode' => 'ABC123',
                'rpsNumber' => '9001',
                'pdfUrl' => 'https://cdn.nfe.io/nota.pdf',
                'xmlUrl' => 'https://cdn.nfe.io/nota.xml',
                'status' => 'Issued',
            ], 201),
        ]);

        $tenant = new Tenant([
            'name' => 'Imobiliária Teste',
            'metadata' => [
                'nfeio_base_url' => 'https://api.nfe.io',
                'nfeio_api_key' => 'apikey-test',
                'nfeio_company_id' => 'company-test',
                'nfeio_service_code_corretagem' => '01.01',
                'nfse_national_service_code' => '100501004',
            ],
        ]);

        $invoice = new CommissionInvoice([
            'id' => 10,
            'tenant_id' => 1,
            'corretor_id' => 99,
            'lead_id' => 22,
            'property_id' => 33,
            'valor_total' => 1500.75,
            'aliquota_iss' => 5,
            'valor_iss' => 75.03,
            'descricao_servico' => 'Corretagem de venda de imóvel',
            'financeiro_status' => 'pendente',
        ]);
        $invoice->setRelation('tenant', $tenant);

        $service = new NfseCommissionService();

        $result = $service->emitir($invoice, [
            'nome' => 'Cliente Teste',
            'documento' => '123.456.789-01',
            'email' => 'cliente@teste.com',
            'endereco' => [
                'logradouro' => 'Rua A',
                'numero' => '100',
                'bairro' => 'Centro',
                'cidade' => 'São Paulo',
                'uf' => 'SP',
                'cep' => '01001-000',
            ],
        ], [
            'forma_pagamento' => 'pix',
            'vencimento' => '2026-02-28',
        ]);

        Http::assertSentCount(1);

        $recorded = Http::recorded();
        $request = $recorded[0][0];

        $this->assertSame('https://api.nfe.io/v1/companies/company-test/serviceinvoices', $request->url());
        $this->assertTrue($request->hasHeader('X-NFE-APIKEY', 'apikey-test'));
        $this->assertTrue($request->hasHeader('Authorization', 'apikey-test'));
        $this->assertTrue($request->hasHeader('Idempotency-Key'));
        $this->assertSame('01.01', data_get($request->data(), 'cityServiceCode'));
        $this->assertSame('100501004', data_get($request->data(), 'nationalTaxCode'));
        $this->assertSame('01.01', data_get($request->data(), 'serviceCode.city'));
        $this->assertSame('01.01', data_get($request->data(), 'serviceCode.municipal'));
        $this->assertSame('100501004', data_get($request->data(), 'serviceCode.national'));
        $this->assertSame('12345678901', data_get($request->data(), 'borrower.federalTaxNumber'));
        $this->assertSame(1500.75, data_get($request->data(), 'servicesAmount'));

        $this->assertSame('1001', $result['nfse_numero']);
        $this->assertSame('ABC123', $result['codigo_verificacao']);
        $this->assertSame('nfse-123', $result['integracao_id']);
        $this->assertSame('Issued', $result['financeiro_status']);
    }

    public function test_emitir_nfse_falha_sem_credenciais(): void
    {
        $tenant = new Tenant([
            'name' => 'Imobiliária Sem Credenciais',
            'metadata' => [],
        ]);

        $invoice = new CommissionInvoice([
            'id' => 11,
            'tenant_id' => 1,
            'corretor_id' => 1,
            'valor_total' => 100,
            'descricao_servico' => 'Teste sem credenciais',
        ]);
        $invoice->setRelation('tenant', $tenant);

        $service = new NfseCommissionService();

        $this->expectException(\RuntimeException::class);
        $service->emitir($invoice, [
            'nome' => 'Cliente',
            'documento' => '11122233344',
        ]);
    }
}
