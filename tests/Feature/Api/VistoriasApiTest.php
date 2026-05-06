<?php

namespace Tests\Feature\Api;

use App\Models\ContratoLocacao;
use App\Models\Pessoa;
use App\Models\Property;
use App\Models\User;
use App\Models\Vistoria;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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

    public function test_it_creates_vistoria_with_imovel_livre_without_cadastro_property(): void
    {
        $tenant = $this->createTenant([
            'domain' => 'vistorias-livre.local',
            'slug' => 'vistorias-livre',
        ]);
        $user = $this->createUser($tenant);

        $response = $this->postJson('/api/vistorias', [
            'status' => 'solicitada',
            'tipo' => 'entrada',
            'cliente_nome' => 'Cliente avulso',
            'imovel_livre' => [
                'titulo' => 'Loja esquina centro',
                'logradouro' => 'Av. Brasil, 100',
                'bairro' => 'Centro',
                'cidade' => 'Curitiba',
                'estado' => 'PR',
                'tipo_imovel' => 'Loja',
                'referencia' => 'Próximo ao mercado municipal',
            ],
        ], $this->adminHeaders($user, $tenant));

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('vistoria.imovel_id', null)
            ->assertJsonPath('vistoria.contrato_id', null)
            ->assertJsonPath('vistoria.imovel.label', 'Loja esquina centro')
            ->assertJsonPath('vistoria.imovel_livre.cidade', 'Curitiba');

        $vistoria = Vistoria::query()->withoutGlobalScope('tenant')->firstOrFail();
        $this->assertSame($tenant->id, $vistoria->tenant_id);
        $this->assertNull($vistoria->imovel_id);
        $this->assertIsArray($vistoria->imovel_livre);
        $this->assertSame('Curitiba', $vistoria->imovel_livre['cidade']);
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

    public function test_somente_minhas_filters_by_user_pessoa_id(): void
    {
        $tenant = $this->createTenant([
            'domain' => 'vistorias-minhas.local',
            'slug' => 'vistorias-minhas',
        ]);
        $resp = Pessoa::query()->create([
            'tenant_id' => $tenant->id,
            'nome' => 'Maria Corretora',
            'tipo' => 'fisica',
            'cpf' => '44444444444',
        ]);
        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'pessoa_id' => $resp->id,
            'name' => 'Maria Login',
            'email' => 'maria@teste.local',
            'password' => bcrypt('secret'),
            'role' => 'corretor',
            'is_active' => true,
        ]);

        $mine = Vistoria::query()->create([
            'tenant_id' => $tenant->id,
            'codigo' => 'VST-M1',
            'status' => 'designada',
            'tipo' => 'entrada',
            'responsavel_pessoa_id' => $resp->id,
        ]);
        Vistoria::query()->create([
            'tenant_id' => $tenant->id,
            'codigo' => 'VST-M2',
            'status' => 'solicitada',
            'tipo' => 'entrada',
            'responsavel_pessoa_id' => null,
        ]);

        $response = $this->getJson('/api/vistorias?somente_minhas=1&per_page=50', $this->adminHeaders($user, $tenant));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id);
    }

    public function test_corretor_cannot_destroy_vistoria(): void
    {
        $tenant = $this->createTenant([
            'domain' => 'vistorias-del.local',
            'slug' => 'vistorias-del',
        ]);
        $corretor = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Corretor Del',
            'email' => 'corretor-del@teste.local',
            'password' => bcrypt('secret'),
            'role' => 'corretor',
            'is_active' => true,
        ]);
        $vistoria = Vistoria::query()->create([
            'tenant_id' => $tenant->id,
            'codigo' => 'VST-D1',
            'status' => 'solicitada',
            'tipo' => 'entrada',
        ]);

        $response = $this->deleteJson("/api/vistorias/{$vistoria->id}", [], $this->adminHeaders($corretor, $tenant));

        $response->assertStatus(403);

        $this->assertSame(1, Vistoria::query()->withoutGlobalScope('tenant')->where('id', $vistoria->id)->count());
    }

    public function test_it_uploads_vistoria_foto_under_tenant_scoped_route(): void
    {
        Storage::fake('public');

        $tenant = $this->createTenant([
            'domain' => 'vistorias-foto.local',
            'slug' => 'vistorias-foto',
        ]);
        $user = $this->createUser($tenant);

        $vistoria = Vistoria::query()->create([
            'tenant_id' => $tenant->id,
            'codigo' => 'VST-F1',
            'status' => 'designada',
            'tipo' => 'entrada',
        ]);

        $file = UploadedFile::fake()->image('sala.jpg', 400, 300);

        $response = $this->withHeaders($this->adminHeaders($user, $tenant))
            ->post('/api/vistorias/' . $vistoria->id . '/fotos', [
                'foto' => $file,
                'comodo' => 'Sala',
                'descricao' => 'Teste',
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('vistoria_fotos', [
            'tenant_id' => $tenant->id,
            'vistoria_id' => $vistoria->id,
            'comodo' => 'Sala',
        ]);
    }
}
