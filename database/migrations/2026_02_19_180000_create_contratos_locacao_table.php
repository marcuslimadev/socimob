<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('contratos_locacao', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('imovel_id')->nullable();
            $table->unsignedBigInteger('locador_pessoa_id');
            $table->unsignedBigInteger('locatario_pessoa_id');
            $table->string('status')->default('rascunho');
            $table->date('inicio')->nullable();
            $table->date('fim')->nullable();
            $table->unsignedTinyInteger('dia_vencimento')->default(10);
            $table->decimal('valor_aluguel', 15, 2)->default(0);
            $table->decimal('valor_condominio', 15, 2)->default(0);
            $table->decimal('valor_iptu', 15, 2)->default(0);
            $table->decimal('valor_taxa', 15, 2)->default(0);
            $table->decimal('valor_seguro', 15, 2)->default(0);
            $table->string('indice_reajuste')->nullable();
            $table->string('periodicidade_reajuste')->nullable();
            $table->date('proximo_reajuste')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'locador_pessoa_id']);
            $table->index(['tenant_id', 'locatario_pessoa_id']);
            $table->index(['tenant_id', 'imovel_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contratos_locacao');
    }
};
