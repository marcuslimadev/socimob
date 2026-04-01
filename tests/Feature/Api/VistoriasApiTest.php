<?php

namespace Tests\Feature\Api;

use App\Models\ContratoLocacao;
use App\Models\Pessoa;
use App\Models\Property;
use App\Models\Vistoria;
use Tests\Feature\Support\BackendFeatureTestCase;

class VistoriasApiTest extends BackendFeatureTestCase
{
    public function test_it_creates_a_vistoria_from_contract_and_autofills_core_fields(): void
    {
        $tenant = $this->createTenant([
            'domain' => 'vistorias.local',
            'slug' => 'vistorias',
        ]);
        $user = $this->createUser($tenant);

        $locador = Pessoa::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Locador Teste',
            'tipo' => 'fisica',
            'cpf' => '11111111111',
        ]);
        $locatario = Pessoa::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Locatário Teste',
            'tipo' => 'fisica',
            'cpf' => '22222222222',
        ]);
        $responsavel = Pessoa::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Vistoriador Responsável',
            'tipo' => 'fisica',
            'cpf' => '33333333333',
        ]);
        $imovel = Property::query()->create([
            'tenant_id' => $tenant->id,
            'codigo' => 'IM-10',
            'titulo' => 'Apartamento Centro',
            'logradouro' => 'Rua Principal',
            'bairro' => 'Centro',
            'cidade' => 'São Paulo',
            'estado' => 'SP',
            'area_total' => 87.5,
        ]);
        $contrato = ContratoLocacao::query()->create([
            'tenant_id' => $tenant->id,
            'numero_contrato' => 'LOC-100',
            'imovel_id' => $imovel->id,
            'locador_pessoa_id' => $locador->id,
            'locatario_pessoa_id' => $locatario->id,
            'status' => 'ativo',
            'inicio' => '2026-04-01',
            'fim' => '2027-03-31',
        ]);

        $response = $this->postJson('/api/vistorias', [
            'status' => 'solicitada',
            'tipo' => 'entrada',
            'contrato_id' => $contrato->id,
            'responsavel_pessoa_id' => $responsavel->id,
        ], $this->adminHeaders($user, $tenant));

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('vistoria.contrato_id', $contrato->id)
            ->assertJsonPath('vistoria.imovel_id', $imovel->id)
            ->assertJsonPath('vistoria.cliente_nome', 'Locatário Teste')
            ->assertJsonPath('vistoria.participantes_ids.0', $locador->id)
            ->assertJsonPath('vistoria.participantes_ids.1', $locatario->id)
            ->assertJsonPath('vistoria.metragem', '87.50');

        $vistoria = Vistoria::query()->withoutGlobalScope('tenant')->firstOrFail();

        $this->assertSame($tenant->id, $vistoria->tenant_id);
        $this->assertSame($imovel->id, $vistoria->imovel_id);
        $this->assertStringStartsWith('VST-', (string) $vistoria->codigo);
    }

    public function test_it_filters_vistorias_by_tenant_and_status(): void
    {
        $tenantA = $this->createTenant([
            'domain' => 'vistorias-a.local',
            'slug' => 'vistorias-a',
        ]);
        $tenantB = $this->createTenant([
            'domain' => 'vistorias-b.local',
            'slug' => 'vistorias-b',
        ]);
        $userA = $this->createUser($tenantA, ['email' => 'vistorias-a@teste.local']);

        $vistoriaA = Vistoria::query()->create([
            'tenant_id' => $tenantA->id,
            'codigo' => 'VST-A1',
            'status' => 'designada',
            'tipo' => 'entrada',
        ]);
        Vistoria::query()->create([
            'tenant_id' => $tenantA->id,
            'codigo' => 'VST-A2',
            'status' => 'solicitada',
            'tipo' => 'saida',
        ]);
        Vistoria::query()->create([
            'tenant_id' => $tenantB->id,
            'codigo' => 'VST-B1',
            'status' => 'designada',
            'tipo' => 'entrada',
        ]);

        $response = $this->getJson('/api/vistorias?status=designada&per_page=50', $this->adminHeaders($userA, $tenantA));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $vistoriaA->id)
            ->assertJsonPath('data.0.codigo', 'VST-A1');
    }
}
