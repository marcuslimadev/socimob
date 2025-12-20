<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePropertiesTable extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('properties')) {
            Schema::create('properties', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('tenant_id');
                $table->string('external_id')->nullable()->comment('ID da API externa');
                $table->string('titulo');
                $table->enum('tipo', ['casa', 'apartamento', 'terreno', 'comercial'])->default('casa');
                $table->enum('finalidade', ['venda', 'aluguel'])->default('venda');
                $table->decimal('preco', 12, 2)->default(0);
                $table->decimal('area', 10, 2)->nullable();
                $table->integer('quartos')->default(0);
                $table->integer('banheiros')->default(0);
                $table->integer('vagas')->default(0);
                $table->string('endereco');
                $table->string('bairro')->nullable();
                $table->string('cidade')->nullable();
                $table->string('estado', 2)->nullable();
                $table->string('cep', 10)->nullable();
                $table->text('descricao')->nullable();
                $table->json('fotos')->nullable();
                $table->enum('status', ['disponivel', 'reservado', 'vendido', 'alugado'])->default('disponivel');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                $table->index(['tenant_id', 'status', 'is_active']);
                $table->unique(['tenant_id', 'external_id']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('properties');
    }
}
