<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Adiciona coluna `telefone_normalizado` (apenas dígitos) à tabela conversas.
 *
 * Objetivo: eliminar full table scan causado pelo LIKE '%sufixo' em porTelefone().
 * O índice em telefone_normalizado permite buscas por sufixo via REVERSE + LIKE
 * e buscas exatas por qualquer formato normalizado.
 */
class AddTelefoneNormalizadoToConversas extends Migration
{
    public function up(): void
    {
        Schema::table('conversas', function (Blueprint $table) {
            if (!Schema::hasColumn('conversas', 'telefone_normalizado')) {
                $table->string('telefone_normalizado', 30)->nullable()->after('telefone');
                $table->index('telefone_normalizado', 'idx_conversas_telefone_norm');
            }
        });

        // Preencher retroativamente: remover tudo que não for dígito
        DB::statement("
            UPDATE conversas
            SET telefone_normalizado = REGEXP_REPLACE(COALESCE(telefone, ''), '[^0-9]', '')
            WHERE telefone_normalizado IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('conversas', function (Blueprint $table) {
            $table->dropIndex('idx_conversas_telefone_norm');
            $table->dropColumn('telefone_normalizado');
        });
    }
}
