<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVistoriaContestacoesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vistoria_contestacoes', function (Blueprint $table) {
            $table->id();
            $table->integer('tenant_id');
            $table->unsignedBigInteger('vistoria_id')->nullable();
            $table->string('codigo')->nullable();
            $table->string('status')->default('apontada'); // apontada, em_analise, finalizada
            $table->string('tipo')->nullable(); // dano, divergencia, outro
            $table->text('descricao')->nullable();
            $table->string('cliente_nome')->nullable();
            $table->string('imovel_referencia')->nullable();
            $table->json('fotos')->nullable(); // Array de URLs de fotos
            $table->json('documentos')->nullable(); // Array de URLs de documentos
            $table->text('resolucao')->nullable(); // Descrição da resolução
            $table->timestamp('data_contestacao')->nullable();
            $table->timestamp('data_resolucao')->nullable();
            $table->integer('user_id')->nullable(); // Usuário responsável
            $table->json('historico')->nullable(); // Array de eventos
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index('vistoria_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('vistoria_contestacoes');
    }
}
