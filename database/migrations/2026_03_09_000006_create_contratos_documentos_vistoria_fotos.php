<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contratos_documentos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('contrato_id');
            $table->unsignedBigInteger('cobranca_id')->nullable();
            // tipos: contrato, aditivo_reajuste, rescisao, extrato_repasse, recibo_pagamento, laudo_vistoria
            $table->string('tipo', 40);
            $table->string('nome', 255);
            $table->string('arquivo_path', 500)->nullable();
            // nao_enviado, enviado, parcial, assinado, cancelado
            $table->string('assinatura_status', 30)->default('nao_enviado');
            $table->string('d4sign_uuid', 100)->nullable();
            $table->string('d4sign_key', 100)->nullable();
            $table->timestamp('assinado_em')->nullable();
            $table->unsignedBigInteger('gerado_por_user_id')->nullable();
            $table->timestamp('gerado_em')->useCurrent();
            $table->timestamps();

            $table->foreign('contrato_id')
                  ->references('id')->on('contratos_locacao')
                  ->cascadeOnDelete();

            $table->foreign('cobranca_id')
                  ->references('id')->on('cobrancas_contrato')
                  ->nullOnDelete();

            $table->index(['tenant_id', 'contrato_id']);
        });

        // Vistoria fotos
        Schema::create('vistoria_fotos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->index();
            $table->unsignedBigInteger('vistoria_id');
            $table->string('comodo', 100)->nullable();
            $table->string('arquivo_path', 500);
            $table->string('url', 500);
            $table->string('mime_type', 50)->default('image/jpeg');
            $table->unsignedInteger('tamanho_bytes')->nullable();
            $table->text('legenda')->nullable();
            $table->boolean('destaque')->default(false);
            $table->unsignedSmallInteger('ordem')->default(0);
            $table->unsignedBigInteger('enviado_por_user_id')->nullable();
            $table->unsignedBigInteger('enviado_por_pessoa_id')->nullable();
            $table->timestamps();

            $table->foreign('vistoria_id')
                  ->references('id')->on('vistorias')
                  ->cascadeOnDelete();

            $table->index(['tenant_id', 'vistoria_id', 'comodo'], 'vistoria_fotos_tenant_vistoria_comodo_index');
        });

        // Enhancements to vistorias table
        Schema::table('vistorias', function (Blueprint $table) {
            $table->unsignedBigInteger('contrato_id')->nullable()->after('imovel_id');
            $table->json('comodos')->nullable()->after('observacoes');
            $table->string('assinatura_inquilino_status', 30)->default('pendente')->after('comodos');
            $table->string('assinatura_proprietario_status', 30)->default('pendente')->after('assinatura_inquilino_status');
            $table->timestamp('assinado_inquilino_em')->nullable()->after('assinatura_proprietario_status');
            $table->timestamp('assinado_proprietario_em')->nullable()->after('assinado_inquilino_em');

            $table->foreign('contrato_id')
                  ->references('id')->on('contratos_locacao')
                  ->nullOnDelete();

            $table->index(['tenant_id', 'contrato_id'], 'vistorias_tenant_contrato_index');
        });
    }

    public function down(): void
    {
        Schema::table('vistorias', function (Blueprint $table) {
            $table->dropForeign(['contrato_id']);
            $table->dropIndex('vistorias_tenant_contrato_index');
            $table->dropColumn([
                'contrato_id', 'comodos',
                'assinatura_inquilino_status', 'assinatura_proprietario_status',
                'assinado_inquilino_em', 'assinado_proprietario_em',
            ]);
        });

        Schema::dropIfExists('vistoria_fotos');
        Schema::dropIfExists('contratos_documentos');
    }
};
