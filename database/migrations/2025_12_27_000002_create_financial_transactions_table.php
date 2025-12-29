<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_transactions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('commission_invoice_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('tipo')->default('receber');
            $table->string('status')->default('pendente');
            $table->date('vencimento')->nullable();
            $table->date('data_pagamento')->nullable();
            $table->decimal('valor', 15, 2);
            $table->string('forma_pagamento')->nullable();
            $table->string('referencia_externa')->nullable();
            $table->string('descricao');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('commission_invoice_id')->references('id')->on('commission_invoices')->onDelete('set null');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'tipo']);
            $table->index(['tenant_id', 'vencimento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_transactions');
    }
};
