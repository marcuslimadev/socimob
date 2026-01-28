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
        Schema::create('vistorias', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('codigo')->nullable();
            $table->string('status')->default('solicitada');
            $table->string('cliente_nome')->nullable();
            $table->unsignedBigInteger('imovel_id')->nullable();
            $table->string('tipo')->nullable();
            $table->json('vistoriadores')->nullable();
            $table->json('pessoas')->nullable();
            $table->decimal('metragem', 10, 2)->nullable();
            $table->boolean('mobiliado')->default(false);
            $table->timestamp('data_vistoria')->nullable();
            $table->text('observacoes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('codigo');
            $table->index('status');
            $table->index('imovel_id');
            $table->index('tipo');
            $table->index('data_vistoria');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('vistorias');
    }
};
