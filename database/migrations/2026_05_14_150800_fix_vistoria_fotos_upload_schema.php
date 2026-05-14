<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vistoria_fotos', function (Blueprint $table) {
            if (!Schema::hasColumn('vistoria_fotos', 'descricao')) {
                $table->text('descricao')->nullable()->after('comodo');
            }

            if (!Schema::hasColumn('vistoria_fotos', 'enviado_por_tipo')) {
                $table->string('enviado_por_tipo', 30)->nullable()->after('enviado_por_pessoa_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vistoria_fotos', function (Blueprint $table) {
            if (Schema::hasColumn('vistoria_fotos', 'enviado_por_tipo')) {
                $table->dropColumn('enviado_por_tipo');
            }

            if (Schema::hasColumn('vistoria_fotos', 'descricao')) {
                $table->dropColumn('descricao');
            }
        });
    }
};
