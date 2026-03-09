<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contrato_fiadores', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('contrato_id');
            $table->unsignedBigInteger('pessoa_id');
            $table->string('tipo_vinculo', 30)->default('fiador'); // fiador, fiador_solidario, avalista
            $table->timestamps();

            $table->foreign('contrato_id')
                  ->references('id')->on('contratos_locacao')
                  ->cascadeOnDelete();

            $table->foreign('pessoa_id')
                  ->references('id')->on('pessoas')
                  ->restrictOnDelete();

            $table->unique(['contrato_id', 'pessoa_id'], 'contrato_fiadores_unique');
            $table->index(['tenant_id', 'contrato_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contrato_fiadores');
    }
};
