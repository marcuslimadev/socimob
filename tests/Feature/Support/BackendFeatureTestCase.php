<?php

namespace Tests\Feature\Support;

use App\Models\Tenant;
use App\Models\User;
use App\Support\SimpleAuthToken;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

abstract class BackendFeatureTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        app()->forgetInstance('tenant');

        $this->rebuildBackendSchema();
    }

    protected function createTenant(array $overrides = []): Tenant
    {
        return Tenant::query()->create(array_merge([
            'name' => 'Tenant Teste',
            'slug' => 'tenant-teste',
            'domain' => 'tenant-teste.local',
            'is_active' => true,
        ], $overrides));
    }

    protected function createUser(Tenant $tenant, array $overrides = []): User
    {
        return User::query()->create(array_merge([
            'tenant_id' => $tenant->id,
            'name' => 'Admin Teste',
            'email' => 'admin+' . $tenant->id . '@teste.local',
            'password' => bcrypt('secret'),
            'role' => 'admin',
            'is_active' => true,
        ], $overrides));
    }

    protected function tenantHeaders(Tenant $tenant): array
    {
        return [
            'Accept' => 'application/json',
            'X-Tenant-Domain' => $tenant->domain,
        ];
    }

    protected function adminHeaders(User $user, ?Tenant $tenant = null): array
    {
        $tenant ??= $user->tenant;

        return array_merge($this->tenantHeaders($tenant), [
            'Authorization' => 'Bearer ' . SimpleAuthToken::issue($user->id),
        ]);
    }

    private function rebuildBackendSchema(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ([
            'contratos_documentos',
            'contratos_compra_venda',
            'vistoria_comentarios',
            'vistoria_fotos',
            'vistoria_solicitacoes',
            'vistorias',
            'contratos_locacao',
            'imo_properties',
            'pessoas',
            'users',
            'tenants',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('domain')->nullable();
            $table->string('slug')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('pessoa_id')->nullable();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->boolean('must_change_password')->default(false);
            $table->string('role')->default('admin');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('pessoas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('nome');
            $table->string('pais')->nullable();
            $table->string('nacionalidade')->nullable();
            $table->string('estado_civil')->nullable();
            $table->string('regime_bens')->nullable();
            $table->string('conjuge_nome')->nullable();
            $table->string('conjuge_cpf')->nullable();
            $table->string('conjuge_rg')->nullable();
            $table->string('conjuge_profissao')->nullable();
            $table->string('conjuge_nacionalidade')->nullable();
            $table->string('conjuge_orgao_expedidor')->nullable();
            $table->date('conjuge_data_expedicao')->nullable();
            $table->string('telefone')->nullable();
            $table->string('celular')->nullable();
            $table->string('email')->nullable();
            $table->string('tipo')->nullable();
            $table->string('cpf')->nullable();
            $table->string('rg')->nullable();
            $table->string('orgao_expedidor')->nullable();
            $table->date('data_expedicao')->nullable();
            $table->string('cnh')->nullable();
            $table->date('data_nascimento')->nullable();
            $table->string('cnpj')->nullable();
            $table->string('razao_social')->nullable();
            $table->string('inscricao_estadual')->nullable();
            $table->string('inscricao_municipal')->nullable();
            $table->string('cep')->nullable();
            $table->string('estado')->nullable();
            $table->string('cidade')->nullable();
            $table->string('bairro')->nullable();
            $table->string('endereco')->nullable();
            $table->string('numero')->nullable();
            $table->string('complemento')->nullable();
            $table->json('contatos')->nullable();
            $table->text('observacoes')->nullable();
            $table->boolean('ativo')->nullable();
            $table->json('papeis')->nullable();
            $table->string('status')->nullable();
            $table->string('origem')->nullable();
            $table->string('classificacao')->nullable();
            $table->json('interesses')->nullable();
            $table->json('preferencias')->nullable();
            $table->decimal('renda_mensal', 12, 2)->nullable();
            $table->string('profissao')->nullable();
            $table->string('empresa')->nullable();
            $table->json('documentos')->nullable();
            $table->json('fotos')->nullable();
            $table->unsignedBigInteger('corretor_responsavel_id')->nullable();
            $table->unsignedBigInteger('indicado_por_id')->nullable();
            $table->integer('score')->nullable();
            $table->dateTime('primeiro_contato')->nullable();
            $table->dateTime('ultimo_atendimento')->nullable();
            $table->dateTime('ultimo_contato')->nullable();
            $table->integer('total_atendimentos')->nullable();
            $table->integer('total_imoveis_visitados')->nullable();
            $table->json('tags')->nullable();
            $table->string('facebook')->nullable();
            $table->string('instagram')->nullable();
            $table->string('linkedin')->nullable();
            $table->string('whatsapp')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('imo_properties', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('codigo')->nullable();
            $table->string('codigo_imovel')->nullable();
            $table->string('referencia_imovel')->nullable();
            $table->string('titulo')->nullable();
            $table->text('descricao')->nullable();
            $table->string('descricao_resumida')->nullable();
            $table->string('finalidade_imovel')->nullable();
            $table->string('tipo_imovel')->nullable();
            $table->decimal('valor_venda', 14, 2)->nullable();
            $table->decimal('valor_aluguel', 14, 2)->nullable();
            $table->string('logradouro')->nullable();
            $table->string('cidade')->nullable();
            $table->string('estado')->nullable();
            $table->string('bairro')->nullable();
            $table->string('numero')->nullable();
            $table->string('complemento')->nullable();
            $table->string('cep')->nullable();
            $table->decimal('area_total', 12, 2)->nullable();
            $table->decimal('area_privativa', 12, 2)->nullable();
            $table->integer('dormitorios')->nullable();
            $table->integer('banheiros')->nullable();
            $table->integer('garagem')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('contratos_locacao', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('numero_contrato')->nullable();
            $table->unsignedBigInteger('imovel_id')->nullable();
            $table->unsignedBigInteger('locador_pessoa_id')->nullable();
            $table->json('co_locadores_ids')->nullable();
            $table->unsignedBigInteger('locatario_pessoa_id')->nullable();
            $table->string('status')->nullable();
            $table->date('inicio')->nullable();
            $table->date('fim')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('vistorias', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('contrato_id')->nullable();
            $table->unsignedBigInteger('responsavel_pessoa_id')->nullable();
            $table->string('codigo')->nullable();
            $table->string('status')->nullable();
            $table->string('cliente_nome')->nullable();
            $table->unsignedBigInteger('imovel_id')->nullable();
            $table->json('imovel_livre')->nullable();
            $table->string('tipo')->nullable();
            $table->json('vistoriadores')->nullable();
            $table->json('pessoas')->nullable();
            $table->json('participantes_ids')->nullable();
            $table->decimal('metragem', 12, 2)->nullable();
            $table->boolean('mobiliado')->nullable();
            $table->dateTime('data_vistoria')->nullable();
            $table->text('observacoes')->nullable();
            $table->json('comodos')->nullable();
            $table->string('assinatura_inquilino_status')->nullable();
            $table->string('assinatura_proprietario_status')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('vistoria_solicitacoes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('codigo')->nullable();
            $table->string('status')->nullable();
            $table->string('cliente_nome')->nullable();
            $table->string('tipo')->nullable();
            $table->unsignedBigInteger('imovel_id')->nullable();
            $table->unsignedBigInteger('vistoria_id')->nullable();
            $table->text('observacoes')->nullable();
            $table->json('pessoas')->nullable();
            $table->json('historico')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('vistoria_fotos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('vistoria_id');
            $table->string('comodo')->nullable();
            $table->text('descricao')->nullable();
            $table->string('arquivo_path')->nullable();
            $table->string('url')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('tamanho_bytes')->nullable();
            $table->string('legenda')->nullable();
            $table->boolean('destaque')->default(false);
            $table->integer('ordem')->default(0);
            $table->unsignedBigInteger('enviado_por_user_id')->nullable();
            $table->unsignedBigInteger('enviado_por_pessoa_id')->nullable();
            $table->string('enviado_por_tipo')->nullable();
            $table->timestamps();
        });

        Schema::create('vistoria_comentarios', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('vistoria_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('pessoa_id')->nullable();
            $table->string('autor_nome')->nullable();
            $table->text('comentario');
            $table->timestamps();
        });

        Schema::create('contratos_compra_venda', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->string('numero_contrato')->nullable();
            $table->unsignedBigInteger('imovel_id')->nullable();
            $table->unsignedBigInteger('vendedor_pessoa_id');
            $table->unsignedBigInteger('comprador_pessoa_id');
            $table->json('co_vendedores_ids')->nullable();
            $table->json('co_compradores_ids')->nullable();
            $table->string('status')->nullable();
            $table->date('data_contrato')->nullable();
            $table->date('data_escritura_prevista')->nullable();
            $table->date('data_entrega_chaves')->nullable();
            $table->integer('prazo_documentacao_dias')->nullable();
            $table->integer('prazo_escritura_dias')->nullable();
            $table->integer('prazo_registro_dias')->nullable();
            $table->decimal('valor_total', 14, 2)->nullable();
            $table->decimal('valor_sinal', 14, 2)->nullable();
            $table->decimal('valor_parcela_final', 14, 2)->nullable();
            $table->decimal('multa_percentual', 8, 2)->nullable();
            $table->decimal('multa_moratoria_percentual', 8, 2)->nullable();
            $table->decimal('juros_percentual_mes', 8, 2)->nullable();
            $table->decimal('corretagem_valor', 14, 2)->nullable();
            $table->string('corretagem_responsavel')->nullable();
            $table->string('intermediadora_nome')->nullable();
            $table->string('intermediadora_documento')->nullable();
            $table->string('intermediadora_fantasia')->nullable();
            $table->text('objeto_descricao')->nullable();
            $table->string('matricula_numero')->nullable();
            $table->string('cartorio_nome')->nullable();
            $table->string('inscricao_cadastral')->nullable();
            $table->json('parcelas_pagamento')->nullable();
            $table->json('clausulas')->nullable();
            $table->text('observacoes')->nullable();
            $table->json('metadata')->nullable();
            $table->string('testemunha_um_nome')->nullable();
            $table->string('testemunha_um_documento')->nullable();
            $table->string('testemunha_um_email')->nullable();
            $table->string('testemunha_dois_nome')->nullable();
            $table->string('testemunha_dois_documento')->nullable();
            $table->string('testemunha_dois_email')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('contratos_documentos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->unsignedBigInteger('contrato_id')->nullable();
            $table->unsignedBigInteger('contrato_compra_venda_id')->nullable();
            $table->unsignedBigInteger('cobranca_id')->nullable();
            $table->string('tipo')->nullable();
            $table->string('categoria')->nullable();
            $table->integer('versao')->nullable();
            $table->unsignedBigInteger('referencia_documento_id')->nullable();
            $table->string('nome')->nullable();
            $table->string('arquivo_path')->nullable();
            $table->string('assinatura_status')->nullable();
            $table->string('d4sign_uuid')->nullable();
            $table->string('d4sign_key')->nullable();
            $table->dateTime('assinado_em')->nullable();
            $table->unsignedBigInteger('gerado_por_user_id')->nullable();
            $table->dateTime('gerado_em')->nullable();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
    }
}
