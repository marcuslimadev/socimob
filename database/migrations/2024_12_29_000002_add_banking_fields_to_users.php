<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Dados bancários para recebimento de comissões
            $table->string('pix_key')->nullable()->comment('Chave PIX do corretor');
            $table->enum('pix_type', ['cpf', 'cnpj', 'email', 'telefone', 'aleatoria'])->nullable()->comment('Tipo da chave PIX');
            $table->string('banco')->nullable()->comment('Nome do banco');
            $table->string('agencia')->nullable()->comment('Agência bancária');
            $table->string('conta')->nullable()->comment('Número da conta');
            $table->enum('tipo_conta', ['corrente', 'poupanca'])->nullable()->default('corrente');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'pix_key',
                'pix_type',
                'banco',
                'agencia',
                'conta',
                'tipo_conta'
            ]);
        });
    }
};
