<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contratos_compra_venda', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->string('numero_contrato', 80)->nullable();
            $table->unsignedBigInteger('imovel_id')->nullable();
            $table->unsignedBigInteger('vendedor_pessoa_id');
            $table->unsignedBigInteger('comprador_pessoa_id');
            $table->json('co_vendedores_ids')->nullable();
            $table->json('co_compradores_ids')->nullable();
            $table->string('status', 50)->default('rascunho');
            $table->date('data_contrato')->nullable();
            $table->date('data_escritura_prevista')->nullable();
            $table->date('data_entrega_chaves')->nullable();
            $table->unsignedSmallInteger('prazo_documentacao_dias')->nullable();
            $table->unsignedSmallInteger('prazo_escritura_dias')->nullable();
            $table->unsignedSmallInteger('prazo_registro_dias')->nullable();
            $table->decimal('valor_total', 15, 2)->nullable();
            $table->decimal('valor_sinal', 15, 2)->nullable();
            $table->decimal('valor_parcela_final', 15, 2)->nullable();
            $table->decimal('multa_percentual', 5, 2)->nullable();
            $table->decimal('multa_moratoria_percentual', 5, 2)->nullable();
            $table->decimal('juros_percentual_mes', 5, 2)->nullable();
            $table->decimal('corretagem_valor', 15, 2)->nullable();
            $table->string('corretagem_responsavel', 120)->nullable();
            $table->string('intermediadora_nome', 255)->nullable();
            $table->string('intermediadora_documento', 40)->nullable();
            $table->string('intermediadora_fantasia', 255)->nullable();
            $table->text('objeto_descricao')->nullable();
            $table->string('matricula_numero', 120)->nullable();
            $table->string('cartorio_nome', 255)->nullable();
            $table->string('inscricao_cadastral', 120)->nullable();
            $table->json('parcelas_pagamento')->nullable();
            $table->json('clausulas')->nullable();
            $table->text('observacoes')->nullable();
            $table->json('metadata')->nullable();
            $table->string('testemunha_um_nome', 255)->nullable();
            $table->string('testemunha_um_documento', 40)->nullable();
            $table->string('testemunha_um_email', 255)->nullable();
            $table->string('testemunha_dois_nome', 255)->nullable();
            $table->string('testemunha_dois_documento', 40)->nullable();
            $table->string('testemunha_dois_email', 255)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('imovel_id')
                ->references('id')->on('imo_properties')
                ->nullOnDelete();

            $table->foreign('vendedor_pessoa_id')
                ->references('id')->on('pessoas');

            $table->foreign('comprador_pessoa_id')
                ->references('id')->on('pessoas');

            $table->unique(['tenant_id', 'numero_contrato'], 'contratos_compra_venda_tenant_numero_unique');
            $table->index(['tenant_id', 'status'], 'contratos_compra_venda_tenant_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contratos_compra_venda');
    }
};
