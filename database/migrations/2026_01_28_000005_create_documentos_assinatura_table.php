<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDocumentosAssinaturaTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('documentos_assinatura', function (Blueprint $table) {
            $table->id();
            $table->integer('tenant_id');
            $table->string('titulo');
            $table->string('tipo')->nullable(); // contrato, procuracao, termo, outro
            $table->string('status')->default('pendente'); // pendente, assinado, cancelado, expirado
            $table->text('descricao')->nullable();
            $table->string('arquivo_url')->nullable(); // URL do PDF
            $table->string('documento_id_externo')->nullable(); // ID no serviço de assinatura (Clicksign, Docusign)
            $table->json('assinantes')->nullable(); // Array de assinantes [{nome, email, cpf, status, data_assinatura}]
            $table->integer('lead_id')->nullable();
            $table->integer('imovel_id')->nullable();
            $table->timestamp('data_envio')->nullable();
            $table->timestamp('data_conclusao')->nullable();
            $table->timestamp('data_expiracao')->nullable();
            $table->json('historico')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['tenant_id', 'status']);
            $table->index('lead_id');
            $table->index('imovel_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('documentos_assinatura');
    }
}
