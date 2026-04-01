<?php

namespace Tests\Feature\Api;

use App\Models\Pessoa;
use Tests\Feature\Support\BackendFeatureTestCase;

class PessoasApiTest extends BackendFeatureTestCase
{
    public function test_it_creates_a_physical_person_with_normalized_fields_and_tenant_context(): void
    {
        $tenant = $this->createTenant([
            'domain' => 'pessoas-criacao.local',
            'slug' => 'pessoas-criacao',
        ]);
        $user = $this->createUser($tenant);

        $response = $this->postJson('/api/pessoas', [
            'nome' => '  Maria da Silva  ',
            'tipo' => 'fisica',
            'cpf' => '123.456.789-01',
            'email' => 'MARIA@EXEMPLO.COM ',
            'telefone' => '  (11) 99999-0000  ',
            'papeis' => ['inquilino'],
        ], $this->adminHeaders($user, $tenant));

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.tenant_id', $tenant->id)
            ->assertJsonPath('data.cpf', '12345678901')
            ->assertJsonPath('data.email', 'maria@exemplo.com')
            ->assertJsonPath('data.nome', 'Maria da Silva')
            ->assertJsonPath('data.pais', 'Brasil');

        $pessoa = Pessoa::query()->withoutGlobalScope('tenant')->firstOrFail();

        $this->assertSame($tenant->id, $pessoa->tenant_id);
        $this->assertSame(['inquilino'], $pessoa->papeis);
    }

    public function test_it_requires_cnpj_and_razao_social_for_legal_entities(): void
    {
        $tenant = $this->createTenant([
            'domain' => 'pessoas-juridicas.local',
            'slug' => 'pessoas-juridicas',
        ]);
        $user = $this->createUser($tenant);

        $response = $this->postJson('/api/pessoas', [
            'nome' => 'Empresa sem documento',
            'tipo' => 'juridica',
        ], $this->adminHeaders($user, $tenant));

        $response
            ->assertStatus(422)
            ->assertJsonPath('messages.cnpj.0', 'CNPJ é obrigatório para pessoa jurídica.')
            ->assertJsonPath('messages.razao_social.0', 'Razão social é obrigatória para pessoa jurídica.');
    }

    public function test_it_lists_only_people_from_the_resolved_tenant_and_applies_search(): void
    {
        $tenantA = $this->createTenant([
            'domain' => 'tenant-a.local',
            'slug' => 'tenant-a',
        ]);
        $tenantB = $this->createTenant([
            'domain' => 'tenant-b.local',
            'slug' => 'tenant-b',
        ]);
        $userA = $this->createUser($tenantA, ['email' => 'tenant-a@teste.local']);

        Pessoa::query()->create([
            'tenant_id' => $tenantA->id,
            'nome' => 'João Comprador',
            'tipo' => 'fisica',
            'cpf' => '11111111111',
            'ativo' => true,
        ]);
        Pessoa::query()->create([
            'tenant_id' => $tenantA->id,
            'nome' => 'Maria Locatária',
            'tipo' => 'fisica',
            'cpf' => '22222222222',
            'ativo' => true,
        ]);
        Pessoa::query()->create([
            'tenant_id' => $tenantB->id,
            'nome' => 'João Outro Tenant',
            'tipo' => 'fisica',
            'cpf' => '33333333333',
            'ativo' => true,
        ]);

        $response = $this->getJson('/api/pessoas?search=João&per_page=50', $this->adminHeaders($userA, $tenantA));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.nome', 'João Comprador');
    }
}
