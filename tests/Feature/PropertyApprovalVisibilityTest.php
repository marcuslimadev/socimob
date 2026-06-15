<?php

namespace Tests\Feature;

use App\Models\Property;
use App\Models\Tenant;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\Feature\Support\BackendFeatureTestCase;

class PropertyApprovalVisibilityTest extends BackendFeatureTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (!Schema::hasColumn('imo_properties', 'active')) {
            Schema::table('imo_properties', function (Blueprint $table) {
                $table->boolean('active')->default(true);
            });
        }

        if (!Schema::hasColumn('imo_properties', 'exibir_imovel')) {
            Schema::table('imo_properties', function (Blueprint $table) {
                $table->boolean('exibir_imovel')->default(true);
            });
        }

        if (!Schema::hasTable('tenant_configs')) {
            Schema::create('tenant_configs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id');
                $table->boolean('require_approval_for_properties')->default(false);
                $table->timestamps();
            });
        }
    }

    public function test_broker_created_property_stays_hidden_even_when_request_asks_to_publish(): void
    {
        $tenant = $this->createTenant();
        $broker = $this->createUser($tenant, [
            'name' => 'Corretora Nova',
            'email' => 'corretora+' . $tenant->id . '@teste.local',
            'role' => 'corretor',
        ]);

        $response = $this
            ->withHeaders($this->adminHeaders($broker, $tenant))
            ->postJson('/api/imoveis', $this->propertyPayload(['exibir_imovel' => true]));

        $response->assertCreated();
        $response->assertJsonPath('data.exibir_imovel', false);

        $property = Property::query()->where('tenant_id', $tenant->id)->firstOrFail();
        $this->assertFalse((bool) $property->exibir_imovel);
    }

    public function test_admin_can_publish_property_when_creating(): void
    {
        $tenant = $this->createTenant();
        $admin = $this->createUser($tenant, ['role' => 'admin']);

        $response = $this
            ->withHeaders($this->adminHeaders($admin, $tenant))
            ->postJson('/api/imoveis', $this->propertyPayload(['exibir_imovel' => true]));

        $response->assertCreated();
        $response->assertJsonPath('data.exibir_imovel', true);
    }

    public function test_admin_created_property_stays_hidden_when_tenant_requires_approval(): void
    {
        $tenant = $this->createTenant();
        $tenant->config()->create(['require_approval_for_properties' => true]);
        $admin = $this->createUser($tenant, ['role' => 'admin']);

        $response = $this
            ->withHeaders($this->adminHeaders($admin, $tenant))
            ->postJson('/api/imoveis', $this->propertyPayload(['exibir_imovel' => true]));

        $response->assertCreated();
        $response->assertJsonPath('data.exibir_imovel', false);
    }

    private function propertyPayload(array $overrides = []): array
    {
        return array_merge([
            'tipo_imovel' => 'casa',
            'finalidade_imovel' => 'venda',
            'valor_venda' => 350000,
            'cep' => '01001-000',
            'estado' => 'SP',
            'cidade' => 'Sao Paulo',
            'bairro' => 'Centro',
            'logradouro' => 'Rua Teste',
            'numero' => '123',
            'dormitorios' => 2,
            'suites' => 0,
            'banheiros' => 1,
            'garagem' => 1,
            'area_total' => 80,
            'active' => true,
        ], $overrides);
    }
}
