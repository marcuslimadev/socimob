<?php

namespace Tests\Feature;

use App\Models\Property;
use Tests\Feature\Support\BackendFeatureTestCase;

class PropertyCommercialDetailsTest extends BackendFeatureTestCase
{
    public function test_update_persists_grouped_commercial_details_for_the_tenant_property(): void
    {
        $tenant = $this->createTenant();
        $admin = $this->createUser($tenant);
        $property = Property::query()->create([
            'tenant_id' => $tenant->id,
            'codigo' => 'DET-001',
            'codigo_imovel' => 'DET-001',
            'tipo_imovel' => 'casa',
            'finalidade_imovel' => 'venda',
            'valor_venda' => 750000,
        ]);

        $response = $this
            ->withHeaders($this->adminHeaders($admin, $tenant))
            ->putJson("/api/imoveis/{$property->id}", [
                'ano_construcao' => 2022,
                'renda_sugerida_compra' => 18000,
                'vantagens' => json_encode(['energia_fotovoltaica', 'painel_solar']),
                'lazer' => json_encode(['piscina_aquecida', 'academia']),
                'proximidades' => json_encode(['supermercado', 'hospital']),
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.ano_construcao', 2022);
        $response->assertJsonPath('data.renda_sugerida_compra', 18000);
        $response->assertJsonPath('data.vantagens.0', 'energia_fotovoltaica');
        $response->assertJsonPath('data.lazer.1', 'academia');
        $response->assertJsonPath('data.proximidades.1', 'hospital');

        $saved = $property->fresh();
        $this->assertSame(['energia_fotovoltaica', 'painel_solar'], $saved->vantagens);
        $this->assertSame(['piscina_aquecida', 'academia'], $saved->lazer);
        $this->assertSame(['supermercado', 'hospital'], $saved->proximidades);
    }

    public function test_update_rejects_an_invalid_construction_year(): void
    {
        $tenant = $this->createTenant();
        $admin = $this->createUser($tenant);
        $property = Property::query()->create([
            'tenant_id' => $tenant->id,
            'codigo' => 'DET-002',
            'codigo_imovel' => 'DET-002',
            'tipo_imovel' => 'apartamento',
            'finalidade_imovel' => 'venda',
            'valor_venda' => 450000,
        ]);

        $this
            ->withHeaders($this->adminHeaders($admin, $tenant))
            ->putJson("/api/imoveis/{$property->id}", ['ano_construcao' => 999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ano_construcao');
    }
}
