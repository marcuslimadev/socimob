<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lancamentos_financeiros', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('contrato_id')->nullable();
            $table->unsignedBigInteger('cobranca_id')->nullable();
            $table->unsignedBigInteger('pessoa_id')->nullable();
            $table->string('tipo');
            $table->string('categoria')->nullable();
            $table->string('descricao')->nullable();
            $table->date('competencia')->nullable();
            $table->date('vencimento')->nullable();
            $table->decimal('valor', 15, 2);
            $table->decimal('valor_em_aberto', 15, 2);
            $table->string('status')->default('aberto');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'tipo', 'status']);
            $table->index(['tenant_id', 'contrato_id']);
            $table->index(['tenant_id', 'cobranca_id']);
            $table->index(['tenant_id', 'pessoa_id']);
        });

        Schema::create('baixas_financeiras', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('lancamento_id');
            $table->date('data_baixa');
            $table->decimal('valor_baixa', 15, 2);
            $table->string('meio_pagamento')->nullable();
            $table->string('referencia')->nullable();
            $table->string('status_conciliacao')->default('pendente');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'lancamento_id']);
            $table->index(['tenant_id', 'data_baixa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('baixas_financeiras');
        Schema::dropIfExists('lancamentos_financeiros');
    }
};
