<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('vistoria_solicitacoes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('codigo')->nullable();
            $table->string('status')->default('solicitada');
            $table->string('cliente_nome');
            $table->string('tipo');
            $table->unsignedBigInteger('imovel_id')->nullable();
            $table->text('observacoes')->nullable();
            $table->json('pessoas')->nullable();
            $table->json('historico')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('status');
            $table->index('codigo');
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
        Schema::dropIfExists('vistoria_solicitacoes');
    }
};
