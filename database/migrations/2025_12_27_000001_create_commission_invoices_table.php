<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('commission_invoices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('corretor_id');
            $table->unsignedBigInteger('lead_id')->nullable();
            $table->unsignedBigInteger('property_id')->nullable();
            $table->decimal('valor_total', 15, 2);
            $table->decimal('aliquota_iss', 5, 2)->default(0);
            $table->decimal('valor_iss', 15, 2)->default(0);
            $table->string('descricao_servico')->default('Comissão de vendas');
            $table->date('competencia')->nullable();
            $table->string('status')->default('pending');
            $table->string('nfse_numero')->nullable();
            $table->string('nfse_codigo_verificacao')->nullable();
            $table->string('nfse_rps')->nullable();
            $table->string('nfse_pdf_url')->nullable();
            $table->string('nfse_xml_url')->nullable();
            $table->string('integracao_id')->nullable();
            $table->json('tomador_dados')->nullable();
            $table->json('financeiro_metadata')->nullable();
            $table->json('retorno_integracao')->nullable();
            $table->string('financeiro_status')->default('pendente');
            $table->date('financeiro_vencimento')->nullable();
            $table->date('financeiro_liquidacao')->nullable();
            $table->text('erro_integracao')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('corretor_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('lead_id')->references('id')->on('leads')->onDelete('set null');
            $table->foreign('property_id')->references('id')->on('properties')->onDelete('set null');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'corretor_id']);
            $table->index(['tenant_id', 'financeiro_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commission_invoices');
    }
};
