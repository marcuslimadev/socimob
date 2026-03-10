<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contrato_templates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            // tipos: contrato, aditivo, rescisao, renovacao, recibo
            $table->string('tipo', 40);
            // Personalização visual/textual
            $table->string('titulo', 255)->nullable();
            $table->text('intro_texto')->nullable();          // parágrafo de abertura
            $table->json('clausulas_padrao')->nullable();     // array de strings — cláusulas padrão do tenant
            $table->text('rodape_texto')->nullable();         // texto do rodapé
            $table->boolean('incluir_logo')->default(true);
            $table->boolean('ativo')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'tipo']);
            $table->foreign('tenant_id')
                  ->references('id')->on('tenants')
                  ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contrato_templates');
    }
};
