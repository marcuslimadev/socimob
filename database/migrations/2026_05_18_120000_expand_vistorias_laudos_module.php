<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vistorias', function (Blueprint $table) {
            $this->addColumnIfMissing($table, 'data_agendada', fn () => $table->timestamp('data_agendada')->nullable()->after('data_vistoria'));
            $this->addColumnIfMissing($table, 'data_inicio', fn () => $table->timestamp('data_inicio')->nullable()->after('data_agendada'));
            $this->addColumnIfMissing($table, 'data_fim', fn () => $table->timestamp('data_fim')->nullable()->after('data_inicio'));
            $this->addColumnIfMissing($table, 'vistoriador_id', fn () => $table->unsignedBigInteger('vistoriador_id')->nullable()->after('responsavel_pessoa_id'));
            $this->addColumnIfMissing($table, 'observacoes_gerais', fn () => $table->text('observacoes_gerais')->nullable()->after('observacoes'));
            $this->addColumnIfMissing($table, 'introducao_texto', fn () => $table->text('introducao_texto')->nullable()->after('observacoes_gerais'));
            $this->addColumnIfMissing($table, 'criterios_avaliacao_json', fn () => $table->json('criterios_avaliacao_json')->nullable()->after('introducao_texto'));
            $this->addColumnIfMissing($table, 'criterios_pintura_json', fn () => $table->json('criterios_pintura_json')->nullable()->after('criterios_avaliacao_json'));
            $this->addColumnIfMissing($table, 'criterios_limpeza_json', fn () => $table->json('criterios_limpeza_json')->nullable()->after('criterios_pintura_json'));
            $this->addColumnIfMissing($table, 'prazo_contestacao_dias', fn () => $table->unsignedSmallInteger('prazo_contestacao_dias')->default(5)->after('criterios_limpeza_json'));
            $this->addColumnIfMissing($table, 'data_limite_contestacao', fn () => $table->timestamp('data_limite_contestacao')->nullable()->after('prazo_contestacao_dias'));
            $this->addColumnIfMissing($table, 'link_publico_midias_token', fn () => $table->string('link_publico_midias_token', 96)->nullable()->after('data_limite_contestacao'));
            $this->addColumnIfMissing($table, 'link_contestacao_token', fn () => $table->string('link_contestacao_token', 96)->nullable()->after('link_publico_midias_token'));
            $this->addColumnIfMissing($table, 'pdf_path', fn () => $table->string('pdf_path')->nullable()->after('link_contestacao_token'));
            $this->addColumnIfMissing($table, 'hash_pdf', fn () => $table->string('hash_pdf', 128)->nullable()->after('pdf_path'));
            $this->addColumnIfMissing($table, 'criado_por', fn () => $table->unsignedBigInteger('criado_por')->nullable()->after('hash_pdf'));
            $this->addColumnIfMissing($table, 'atualizado_por', fn () => $table->unsignedBigInteger('atualizado_por')->nullable()->after('criado_por'));
        });

        Schema::table('vistorias', function (Blueprint $table) {
            $this->addIndexIfMissing('vistorias', ['tenant_id', 'status'], 'vistorias_tenant_status_laudo_idx', $table);
            $this->addIndexIfMissing('vistorias', ['tenant_id', 'tipo'], 'vistorias_tenant_tipo_laudo_idx', $table);
            $this->addIndexIfMissing('vistorias', ['tenant_id', 'link_publico_midias_token'], 'vistorias_midias_token_idx', $table);
            $this->addIndexIfMissing('vistorias', ['tenant_id', 'link_contestacao_token'], 'vistorias_contestacao_token_idx', $table);
        });

        $this->createPartes();
        $this->createAmbientes();
        $this->createItens();
        $this->createInconformidades();
        $this->createMidias();
        $this->createChaves();
        $this->expandContestacoes();
        $this->createContestacaoItens();
        $this->createContestacaoMidias();
        $this->createHistoricos();
        $this->createTemplates();
    }

    public function down(): void
    {
        Schema::dropIfExists('vistoria_templates');
        Schema::dropIfExists('vistoria_historicos');
        Schema::dropIfExists('vistoria_contestacao_midias');
        Schema::dropIfExists('vistoria_contestacao_itens');
        Schema::dropIfExists('vistoria_chaves');
        Schema::dropIfExists('vistoria_midias');
        Schema::dropIfExists('vistoria_inconformidades');
        Schema::dropIfExists('vistoria_itens');
        Schema::dropIfExists('vistoria_ambientes');
        Schema::dropIfExists('vistoria_partes');
    }

    private function createPartes(): void
    {
        if (Schema::hasTable('vistoria_partes')) {
            return;
        }

        Schema::create('vistoria_partes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vistoria_id');
            $table->unsignedBigInteger('pessoa_id')->nullable();
            $table->string('nome');
            $table->string('documento', 80)->nullable();
            $table->string('email')->nullable();
            $table->string('telefone', 80)->nullable();
            $table->string('funcao', 40)->default('outro');
            $table->unsignedSmallInteger('ordem_assinatura')->default(0);
            $table->boolean('assinou')->default(false);
            $table->timestamp('data_assinatura')->nullable();
            $table->string('assinatura_path')->nullable();
            $table->string('ip_assinatura', 80)->nullable();
            $table->text('user_agent_assinatura')->nullable();
            $table->timestamps();

            $table->index('vistoria_id');
            $table->index(['vistoria_id', 'funcao']);
        });
    }

    private function createAmbientes(): void
    {
        if (Schema::hasTable('vistoria_ambientes')) {
            return;
        }

        Schema::create('vistoria_ambientes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vistoria_id');
            $table->string('nome');
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->string('estado_geral', 40)->nullable();
            $table->string('pintura_estado', 40)->nullable();
            $table->string('limpeza_estado', 40)->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index('vistoria_id');
        });
    }

    private function createItens(): void
    {
        if (Schema::hasTable('vistoria_itens')) {
            return;
        }

        Schema::create('vistoria_itens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vistoria_ambiente_id');
            $table->string('nome');
            $table->text('descricao')->nullable();
            $table->string('estado', 40)->default('nao_aplicavel');
            $table->boolean('possui_inconformidade')->default(false);
            $table->text('observacoes')->nullable();
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->timestamps();

            $table->index('vistoria_ambiente_id');
        });
    }

    private function createInconformidades(): void
    {
        if (Schema::hasTable('vistoria_inconformidades')) {
            return;
        }

        Schema::create('vistoria_inconformidades', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vistoria_id');
            $table->unsignedBigInteger('ambiente_id');
            $table->unsignedBigInteger('item_id')->nullable();
            $table->text('descricao');
            $table->string('severidade', 20)->default('media');
            $table->string('responsabilidade_sugerida')->nullable();
            $table->string('status', 30)->default('registrada');
            $table->timestamps();

            $table->index('vistoria_id');
            $table->index('ambiente_id');
            $table->index('item_id');
            $table->index(['vistoria_id', 'status']);
        });
    }

    private function createMidias(): void
    {
        if (Schema::hasTable('vistoria_midias')) {
            return;
        }

        Schema::create('vistoria_midias', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vistoria_id');
            $table->unsignedBigInteger('ambiente_id')->nullable();
            $table->unsignedBigInteger('item_id')->nullable();
            $table->unsignedBigInteger('inconformidade_id')->nullable();
            $table->string('tipo', 30);
            $table->string('path_original');
            $table->string('path_thumb')->nullable();
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('tamanho_bytes')->default(0);
            $table->unsignedInteger('duracao_segundos')->nullable();
            $table->string('legenda')->nullable();
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->index('vistoria_id');
            $table->index('ambiente_id');
            $table->index('item_id');
            $table->index('inconformidade_id');
        });
    }

    private function createChaves(): void
    {
        if (Schema::hasTable('vistoria_chaves')) {
            return;
        }

        Schema::create('vistoria_chaves', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vistoria_id');
            $table->string('tipo', 80);
            $table->unsignedSmallInteger('quantidade')->default(1);
            $table->string('estado', 40)->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();

            $table->index('vistoria_id');
        });
    }

    private function expandContestacoes(): void
    {
        Schema::table('vistoria_contestacoes', function (Blueprint $table) {
            $this->addColumnIfMissing($table, 'parte_id', fn () => $table->unsignedBigInteger('parte_id')->nullable()->after('vistoria_id'));
            $this->addColumnIfMissing($table, 'nome', fn () => $table->string('nome')->nullable()->after('parte_id'));
            $this->addColumnIfMissing($table, 'documento', fn () => $table->string('documento', 80)->nullable()->after('nome'));
            $this->addColumnIfMissing($table, 'email', fn () => $table->string('email')->nullable()->after('documento'));
            $this->addColumnIfMissing($table, 'telefone', fn () => $table->string('telefone', 80)->nullable()->after('email'));
            $this->addColumnIfMissing($table, 'texto', fn () => $table->text('texto')->nullable()->after('telefone'));
            $this->addColumnIfMissing($table, 'data_envio', fn () => $table->timestamp('data_envio')->nullable()->after('texto'));
            $this->addColumnIfMissing($table, 'ip', fn () => $table->string('ip', 80)->nullable()->after('data_envio'));
            $this->addColumnIfMissing($table, 'user_agent', fn () => $table->text('user_agent')->nullable()->after('ip'));
            $this->addColumnIfMissing($table, 'resposta_admin', fn () => $table->text('resposta_admin')->nullable()->after('user_agent'));
            $this->addColumnIfMissing($table, 'data_resposta', fn () => $table->timestamp('data_resposta')->nullable()->after('resposta_admin'));
            $this->addColumnIfMissing($table, 'respondido_por', fn () => $table->unsignedBigInteger('respondido_por')->nullable()->after('data_resposta'));
            $this->addColumnIfMissing($table, 'deleted_at', fn () => $table->softDeletes()->after('updated_at'));
        });
    }

    private function createContestacaoItens(): void
    {
        if (Schema::hasTable('vistoria_contestacao_itens')) {
            return;
        }

        Schema::create('vistoria_contestacao_itens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contestacao_id');
            $table->unsignedBigInteger('ambiente_id')->nullable();
            $table->unsignedBigInteger('item_id')->nullable();
            $table->unsignedBigInteger('inconformidade_id')->nullable();
            $table->text('descricao');
            $table->timestamps();

            $table->index('contestacao_id');
        });
    }

    private function createContestacaoMidias(): void
    {
        if (Schema::hasTable('vistoria_contestacao_midias')) {
            return;
        }

        Schema::create('vistoria_contestacao_midias', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contestacao_id');
            $table->unsignedBigInteger('contestacao_item_id')->nullable();
            $table->string('tipo', 30);
            $table->string('path');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('tamanho_bytes')->default(0);
            $table->string('legenda')->nullable();
            $table->timestamps();

            $table->index('contestacao_id');
        });
    }

    private function createHistoricos(): void
    {
        if (Schema::hasTable('vistoria_historicos')) {
            return;
        }

        Schema::create('vistoria_historicos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('vistoria_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('acao', 80);
            $table->text('descricao')->nullable();
            $table->json('dados_antes_json')->nullable();
            $table->json('dados_depois_json')->nullable();
            $table->string('ip', 80)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('vistoria_id');
            $table->index(['vistoria_id', 'acao']);
        });
    }

    private function createTemplates(): void
    {
        if (Schema::hasTable('vistoria_templates')) {
            return;
        }

        Schema::create('vistoria_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('nome');
            $table->string('tipo_vistoria', 40)->nullable();
            $table->json('conteudo_json');
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->index(['tenant_id', 'ativo']);
            $table->index(['tenant_id', 'tipo_vistoria']);
        });
    }

    private function addColumnIfMissing(Blueprint $table, string $column, callable $definition): void
    {
        if (!Schema::hasColumn($table->getTable(), $column)) {
            $definition();
        }
    }

    private function addIndexIfMissing(string $tableName, array $columns, string $indexName, Blueprint $table): void
    {
        if (!Schema::hasColumn($tableName, $columns[0])) {
            return;
        }

        $table->index($columns, $indexName);
    }
};
