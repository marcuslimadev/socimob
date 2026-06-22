<?php

namespace Tests\Feature;

use App\Models\Property;
use Tests\Feature\Support\BackendFeatureTestCase;

class PropertyNumericDefaultsTest extends BackendFeatureTestCase
{
    public function test_property_never_persists_a_null_sale_price(): void
    {
        $tenant = $this->createTenant();
        $property = Property::query()->create([
            'tenant_id' => $tenant->id,
            'tipo_imovel' => 'apartamento',
            'finalidade_imovel' => 'aluguel',
            'valor_venda' => null,
            'valor_aluguel' => 1800,
        ]);

        $this->assertSame(0.0, $property->fresh()->valor_venda);
    }

    public function test_update_converts_an_explicitly_empty_sale_price_to_zero(): void
    {
        $tenant = $this->createTenant();
        $admin = $this->createUser($tenant);
        $property = Property::query()->create([
            'tenant_id' => $tenant->id,
            'codigo' => 'TEST-001',
            'codigo_imovel' => 'TEST-001',
            'tipo_imovel' => 'casa',
            'finalidade_imovel' => 'aluguel',
            'valor_venda' => 150000,
            'valor_aluguel' => 2500,
        ]);

        $response = $this
            ->withHeaders($this->adminHeaders($admin, $tenant))
            ->putJson("/api/imoveis/{$property->id}", [
                'valor_venda' => null,
            ]);

        $response->assertOk();
        $response->assertJsonPath('data.valor_venda', 0);
        $this->assertSame(0.0, $property->fresh()->valor_venda);
        $this->assertSame(2500.0, $property->fresh()->valor_aluguel);
    }
}
