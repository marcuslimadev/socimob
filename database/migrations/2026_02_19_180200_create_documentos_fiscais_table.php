<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('documentos_fiscais', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('cobranca_id')->nullable();
            $table->unsignedBigInteger('locador_pessoa_id')->nullable();
            $table->unsignedBigInteger('locatario_pessoa_id')->nullable();
            $table->string('tipo')->default('nfse');
            $table->string('status')->default('rascunho');
            $table->string('numero')->nullable();
            $table->string('serie')->nullable();
            $table->string('codigo_verificacao')->nullable();
            $table->dateTime('emitida_em')->nullable();
            $table->decimal('valor_servico', 15, 2)->default(0);
            $table->decimal('valor_impostos', 15, 2)->default(0);
            $table->string('service_item_code')->nullable();
            $table->string('city_service_code')->nullable();
            $table->text('url_pdf')->nullable();
            $table->text('url_xml')->nullable();
            $table->json('payload')->nullable();
            $table->json('retorno')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'tipo']);
            $table->index(['tenant_id', 'cobranca_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documentos_fiscais');
    }
};
