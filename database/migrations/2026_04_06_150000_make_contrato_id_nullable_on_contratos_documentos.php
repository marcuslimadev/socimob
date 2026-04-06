<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE contratos_documentos MODIFY contrato_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        Schema::table('contratos_documentos', function (Blueprint $table) {
            $table->dropForeign(['contrato_id']);
        });

        DB::table('contratos_documentos')
            ->whereNull('contrato_id')
            ->whereNotNull('contrato_compra_venda_id')
            ->delete();

        DB::statement('ALTER TABLE contratos_documentos MODIFY contrato_id BIGINT UNSIGNED NOT NULL');

        Schema::table('contratos_documentos', function (Blueprint $table) {
            $table->foreign('contrato_id')
                ->references('id')->on('contratos_locacao')
                ->cascadeOnDelete();
        });
    }
};