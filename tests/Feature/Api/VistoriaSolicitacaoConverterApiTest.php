<?php

namespace Tests\Feature\Api;

use App\Models\Property;
use App\Models\Vistoria;
use App\Models\VistoriaSolicitacao;
use Tests\Feature\Support\BackendFeatureTestCase;

class VistoriaSolicitacaoConverterApiTest extends BackendFeatureTestCase
{
    public function test_it_converts_solicitacao_to_vistoria_with_imovel(): void
    {
        $tenant = $this->createTenant([
            'domain' => 'sol-converter.local',
            'slug' => 'sol-converter',
        ]);
        $user = $this->createUser($tenant);

        $imovel = Property::query()->create([
            'tenant_id' => $tenant->id,
            'codigo' => 'IM-SOL',
            'titulo' => 'Apartamento teste',
            'area_total' => 70,
            'logradouro' => 'Rua A',
            'cidade' => 'SP City',
            'estado' => 'SP',
        ]);

        $solicitacao = VistoriaSolicitacao::query()->create([
            'tenant_id' => $tenant->id,
            'codigo' => 'SOL-10',
            'status' => 'solicitada',
            'cliente_nome' => 'João Porto',
            'tipo' => 'entrada',
            'imovel_id' => $imovel->id,
            'observacoes' => 'Verificar infiltrações',
            'historico' => [],
        ]);

        $response = $this->postJson(
            '/api/vistorias/solicitacoes/' . $solicitacao->id . '/converter',
            [],
            $this->adminHeaders($user, $tenant)
        );

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('already_converted', false)
            ->assertJsonPath('vistoria.imovel_id', $imovel->id)
            ->assertJsonPath('vistoria.status', 'designada')
            ->assertJsonPath('vistoria.tipo', 'entrada');

        $solicitacao->refresh();

        $this->assertNotNull($solicitacao->vistoria_id);
        $this->assertSame('designada', $solicitacao->status);

        $vistoria = Vistoria::query()->withoutGlobalScope('tenant')->findOrFail((int) $solicitacao->vistoria_id);
        $this->assertStringContainsString('Origem: solicitação', (string) $vistoria->observacoes);
    }

    public function test_it_converts_solicitacao_without_imovel_as_imovel_livre(): void
    {
        $tenant = $this->createTenant([
            'domain' => 'sol-livre.local',
            'slug' => 'sol-livre',
        ]);
        $user = $this->createUser($tenant);

        $solicitacao = VistoriaSolicitacao::query()->create([
            'tenant_id' => $tenant->id,
            'status' => 'solicitada',
            'cliente_nome' => 'Maria',
            'tipo' => 'avaliacao de imovel',
            'imovel_id' => null,
            'observacoes' => 'Cliente enviou fotos pelo portal',
            'historico' => [],
        ]);

        $response = $this->postJson(
            '/api/vistorias/solicitacoes/' . $solicitacao->id . '/converter',
            [],
            $this->adminHeaders($user, $tenant)
        );

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('vistoria.imovel_id', null)
            ->assertJsonPath('vistoria.tipo', 'periodica');

        $sid = $response->json('vistoria_id');
        $vistoria = Vistoria::query()->withoutGlobalScope('tenant')->findOrFail($sid);
        $this->assertNotNull($vistoria->imovel_livre);
        $this->assertSame('Maria', $vistoria->cliente_nome);
    }

    public function test_second_conversion_returns_existing(): void
    {
        $tenant = $this->createTenant([
            'domain' => 'sol-twice.local',
            'slug' => 'sol-twice',
        ]);
        $user = $this->createUser($tenant);

        $solicitacao = VistoriaSolicitacao::query()->create([
            'tenant_id' => $tenant->id,
            'status' => 'solicitada',
            'cliente_nome' => 'Duplo',
            'tipo' => 'periodica',
            'historico' => [],
        ]);

        $this->postJson(
            '/api/vistorias/solicitacoes/' . $solicitacao->id . '/converter',
            [],
            $this->adminHeaders($user, $tenant)
        )->assertCreated();

        $second = $this->postJson(
            '/api/vistorias/solicitacoes/' . $solicitacao->id . '/converter',
            [],
            $this->adminHeaders($user, $tenant)
        );

        $second->assertOk()
            ->assertJsonPath('already_converted', true);

        $this->assertSame(1, Vistoria::query()->withoutGlobalScope('tenant')->count());
    }
}
