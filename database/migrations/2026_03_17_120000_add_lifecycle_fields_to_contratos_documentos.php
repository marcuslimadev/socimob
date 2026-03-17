<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos_documentos', function (Blueprint $table) {
            $table->string('categoria', 30)->default('original')->after('tipo');
            $table->unsignedInteger('versao')->default(1)->after('categoria');
            $table->unsignedBigInteger('referencia_documento_id')->nullable()->after('versao');

            $table->foreign('referencia_documento_id')
                ->references('id')->on('contratos_documentos')
                ->cascadeOnDelete();

            $table->index(['contrato_id', 'tipo', 'categoria', 'versao'], 'contratos_documentos_fluxo_index');
        });

        DB::table('contratos_documentos')
            ->whereNull('categoria')
            ->update([
                'categoria' => 'original',
                'versao' => 1,
            ]);
    }

    public function down(): void
    {
        Schema::table('contratos_documentos', function (Blueprint $table) {
            $table->dropIndex('contratos_documentos_fluxo_index');
            $table->dropForeign(['referencia_documento_id']);
            $table->dropColumn(['categoria', 'versao', 'referencia_documento_id']);
        });
    }
};