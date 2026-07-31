<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imo_properties', function (Blueprint $table) {
            if (!Schema::hasColumn('imo_properties', 'ano_construcao')) {
                $table->unsignedSmallInteger('ano_construcao')->nullable()->after('area_terreno');
            }

            if (!Schema::hasColumn('imo_properties', 'renda_sugerida_compra')) {
                $table->decimal('renda_sugerida_compra', 15, 2)->nullable()->after('valor_iptu');
            }

            if (!Schema::hasColumn('imo_properties', 'vantagens')) {
                $table->longText('vantagens')->nullable()->after('classificacoes');
            }

            if (!Schema::hasColumn('imo_properties', 'lazer')) {
                $table->longText('lazer')->nullable()->after('vantagens');
            }

            if (!Schema::hasColumn('imo_properties', 'proximidades')) {
                $table->longText('proximidades')->nullable()->after('lazer');
            }
        });
    }

    public function down(): void
    {
        Schema::table('imo_properties', function (Blueprint $table) {
            $columns = array_values(array_filter([
                Schema::hasColumn('imo_properties', 'ano_construcao') ? 'ano_construcao' : null,
                Schema::hasColumn('imo_properties', 'renda_sugerida_compra') ? 'renda_sugerida_compra' : null,
                Schema::hasColumn('imo_properties', 'vantagens') ? 'vantagens' : null,
                Schema::hasColumn('imo_properties', 'lazer') ? 'lazer' : null,
                Schema::hasColumn('imo_properties', 'proximidades') ? 'proximidades' : null,
            ]));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
