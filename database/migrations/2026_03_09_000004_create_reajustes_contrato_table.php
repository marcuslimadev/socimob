<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reajustes_contrato', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('contrato_id');
            $table->string('competencia', 7); // YYYY-MM em que foi aplicado
            $table->string('indice', 20);     // IGPM, IPCA, INPC, fixo
            $table->decimal('percentual_aplicado', 8, 4);
            $table->decimal('valor_anterior', 15, 2);
            $table->decimal('valor_novo', 15, 2);
            $table->text('observacoes')->nullable();
            $table->timestamp('aplicado_em')->useCurrent();
            $table->unsignedBigInteger('aplicado_por_user_id')->nullable();
            $table->timestamps();

            $table->foreign('contrato_id')
                  ->references('id')->on('contratos_locacao')
                  ->cascadeOnDelete();

            $table->index(['tenant_id', 'contrato_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reajustes_contrato');
    }
};
