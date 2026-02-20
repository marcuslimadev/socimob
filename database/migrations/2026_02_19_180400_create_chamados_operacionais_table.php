<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chamados_operacionais', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('contrato_id')->nullable();
            $table->unsignedBigInteger('cobranca_id')->nullable();
            $table->unsignedBigInteger('aberto_por_pessoa_id')->nullable();
            $table->unsignedBigInteger('responsavel_user_id')->nullable();
            $table->string('protocolo')->nullable();
            $table->string('assunto');
            $table->string('categoria')->nullable();
            $table->string('prioridade')->default('media');
            $table->string('status')->default('aberto');
            $table->text('descricao')->nullable();
            $table->dateTime('primeira_resposta_em')->nullable();
            $table->dateTime('resolvido_em')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'aberto_por_pessoa_id']);
            $table->index(['tenant_id', 'contrato_id']);
            $table->index(['tenant_id', 'cobranca_id']);
            $table->unique(['tenant_id', 'protocolo'], 'uq_chamado_protocolo_tenant');
        });

        Schema::create('chamado_mensagens', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('chamado_id');
            $table->unsignedBigInteger('autor_user_id')->nullable();
            $table->unsignedBigInteger('autor_pessoa_id')->nullable();
            $table->boolean('interna')->default(false);
            $table->text('mensagem');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'chamado_id']);
        });

        Schema::create('chamado_anexos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('chamado_id');
            $table->unsignedBigInteger('mensagem_id')->nullable();
            $table->string('nome_arquivo');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('tamanho_bytes')->nullable();
            $table->text('caminho_arquivo');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'chamado_id']);
            $table->index(['tenant_id', 'mensagem_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chamado_anexos');
        Schema::dropIfExists('chamado_mensagens');
        Schema::dropIfExists('chamados_operacionais');
    }
};
