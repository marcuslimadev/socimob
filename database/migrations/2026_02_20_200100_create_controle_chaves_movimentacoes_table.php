<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('controle_chaves_movimentacoes')) {
            Schema::create('controle_chaves_movimentacoes', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('tenant_id')->index();
                $table->unsignedBigInteger('property_id')->index();
                $table->string('tipo', 20); // retirada | devolucao
                $table->string('responsavel', 150);
                $table->string('destino', 255)->nullable();
                $table->text('observacoes')->nullable();
                $table->timestamp('movimentado_em')->useCurrent();
                $table->unsignedBigInteger('user_id')->nullable()->index();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('controle_chaves_movimentacoes');
    }
};

