<?php

namespace Tests\Feature\Admin;

use App\Models\ContratoCompraVenda;
use App\Models\Pessoa;
use App\Models\Property;
use Tests\Feature\Support\BackendFeatureTestCase;

class ContratosCompraVendaApiTest extends BackendFeatureTestCase
{
    public function test_it_creates_a_sale_contract_and_updates_people_roles(): void
    {
        $tenant = $this->createTenant([
            'domain' => 'compra-venda.local',
            'slug' => 'compra-venda',
        ]);
        $user = $this->createUser($tenant);

        $vendedor = Pessoa::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Vendedor Principal',
            'tipo' => 'fisica',
            'cpf' => '11111111111',
            'papeis' => ['cliente'],
        ]);
        $comprador = Pessoa::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Comprador Principal',
            'tipo' => 'fisica',
            'cpf' => '22222222222',
            'papeis' => [],
        ]);
        $coVendedor = Pessoa::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Co Vendedor',
            'tipo' => 'fisica',
            'cpf' => '33333333333',
            'papeis' => [],
        ]);
        $coComprador = Pessoa::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Co Comprador',
            'tipo' => 'fisica',
            'cpf' => '44444444444',
            'papeis' => [],
        ]);
        $imovel = Property::query()->create([
            'tenant_id' => $tenant->id,
            'codigo' => 'VEN-1',
            'titulo' => 'Casa de Teste',
            'logradouro' => 'Rua das Flores',
            'numero' => '100',
            'bairro' => 'Centro',
            'cidade' => 'Curitiba',
            'estado' => 'PR',
            'cep' => '80000-000',
            'area_total' => 120,
            'area_privativa' => 110,
            'garagem' => 2,
        ]);

        $response = $this->postJson('/api/admin/financeiro/compra-venda', [
            'vendedor_pessoa_id' => $vendedor->id,
            'comprador_pessoa_id' => $comprador->id,
            'co_vendedores_ids' => [$coVendedor->id],
            'co_compradores_ids' => [$coComprador->id],
            'imovel_id' => $imovel->id,
            'status' => 'rascunho',
            'data_contrato' => '2026-04-01',
            'valor_total' => 350000.50,
            'parcelas_pagamento' => [
                ['descricao' => 'Sinal', 'valor' => 50000, 'texto' => 'Pagamento no ato da assinatura'],
            ],
        ], $this->adminHeaders($user, $tenant));

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('item.numero_contrato', 'CV-000001')
            ->assertJsonPath('item.imovel.id', $imovel->id)
            ->assertJsonPath('item.vendedor.id', $vendedor->id)
            ->assertJsonPath('item.comprador.id', $comprador->id);

        $contrato = ContratoCompraVenda::query()->withoutGlobalScope('tenant')->firstOrFail();

        $this->assertSame($tenant->id, $contrato->tenant_id);
        $this->assertSame('CV-000001', $contrato->numero_contrato);
        $this->assertSame([$coVendedor->id], $contrato->co_vendedores_ids);
        $this->assertSame([$coComprador->id], $contrato->co_compradores_ids);

        $this->assertContains('vendedor', $vendedor->fresh()->papeis);
        $this->assertContains('comprador', $comprador->fresh()->papeis);
        $this->assertContains('vendedor', $coVendedor->fresh()->papeis);
        $this->assertContains('comprador', $coComprador->fresh()->papeis);
    }

    public function test_it_blocks_admin_access_when_token_tenant_does_not_match_resolved_tenant(): void
    {
        $tenantA = $this->createTenant([
            'domain' => 'tenant-admin-a.local',
            'slug' => 'tenant-admin-a',
        ]);
        $tenantB = $this->createTenant([
            'domain' => 'tenant-admin-b.local',
            'slug' => 'tenant-admin-b',
        ]);
        $userA = $this->createUser($tenantA, ['email' => 'admin-a@teste.local']);

        $response = $this->getJson('/api/admin/financeiro/compra-venda', $this->adminHeaders($userA, $tenantB));

        $response
            ->assertStatus(403)
            ->assertJsonPath('error', 'Forbidden');
    }
}
