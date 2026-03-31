<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos_documentos', function (Blueprint $table) {
            $table->unsignedBigInteger('contrato_compra_venda_id')
                ->nullable()
                ->after('contrato_id');

            $table->foreign('contrato_compra_venda_id', 'contratos_documentos_compra_venda_foreign')
                ->references('id')->on('contratos_compra_venda')
                ->cascadeOnDelete();

            $table->index(
                ['tenant_id', 'contrato_compra_venda_id', 'tipo', 'categoria', 'versao'],
                'contratos_documentos_compra_venda_fluxo_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('contratos_documentos', function (Blueprint $table) {
            $table->dropIndex('contratos_documentos_compra_venda_fluxo_index');
            $table->dropForeign('contratos_documentos_compra_venda_foreign');
            $table->dropColumn('contrato_compra_venda_id');
        });
    }
};
