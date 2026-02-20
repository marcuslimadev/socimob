<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('cobrancas_contrato', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('contrato_id');
            $table->string('competencia', 7);
            $table->date('vencimento');
            $table->date('data_emissao')->nullable();
            $table->string('status')->default('pendente');
            $table->decimal('valor_base', 15, 2)->default(0);
            $table->decimal('desconto', 15, 2)->default(0);
            $table->decimal('multa', 15, 2)->default(0);
            $table->decimal('juros', 15, 2)->default(0);
            $table->decimal('valor_total', 15, 2)->default(0);
            $table->decimal('valor_pago', 15, 2)->default(0);
            $table->date('data_pagamento')->nullable();
            $table->string('forma_pagamento')->nullable();
            $table->string('nosso_numero')->nullable();
            $table->string('linha_digitavel')->nullable();
            $table->json('itens')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'contrato_id', 'competencia'], 'uq_cobranca_contrato_competencia');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'vencimento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cobrancas_contrato');
    }
};
