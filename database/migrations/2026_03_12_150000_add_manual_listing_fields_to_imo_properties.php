<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imo_properties', function (Blueprint $table) {
            if (!Schema::hasColumn('imo_properties', 'valor_aluguel')) {
                $table->decimal('valor_aluguel', 15, 2)->nullable()->after('valor_venda');
            }

            if (!Schema::hasColumn('imo_properties', 'classificacoes')) {
                $table->longText('classificacoes')->nullable()->after('caracteristicas');
            }
        });
    }

    public function down(): void
    {
        Schema::table('imo_properties', function (Blueprint $table) {
            if (Schema::hasColumn('imo_properties', 'classificacoes')) {
                $table->dropColumn('classificacoes');
            }

            if (Schema::hasColumn('imo_properties', 'valor_aluguel')) {
                $table->dropColumn('valor_aluguel');
            }
        });
    }
};