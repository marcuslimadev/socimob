<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repasses_proprietario', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('contrato_id');
            $table->unsignedBigInteger('cobranca_id')->nullable();
            $table->string('competencia', 7); // YYYY-MM
            $table->decimal('valor_aluguel_recebido', 15, 2)->default(0);
            $table->decimal('valor_taxa_administracao', 15, 2)->default(0);
            $table->decimal('valor_deducoes', 15, 2)->default(0);
            $table->decimal('valor_repasse', 15, 2)->default(0);
            $table->string('status', 20)->default('pendente'); // pendente, pago
            $table->date('data_pagamento')->nullable();
            $table->string('forma_pagamento', 50)->nullable();
            $table->text('observacoes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('contrato_id')
                  ->references('id')->on('contratos_locacao')
                  ->cascadeOnDelete();

            $table->foreign('cobranca_id')
                  ->references('id')->on('cobrancas_contrato')
                  ->nullOnDelete();

            $table->unique(['tenant_id', 'contrato_id', 'competencia'], 'repasses_tenant_contrato_competencia_unique');
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repasses_proprietario');
    }
};
