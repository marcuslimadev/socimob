<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vistoria_solicitacoes', function (Blueprint $table) {
            $table->unsignedBigInteger('vistoria_id')->nullable()->after('imovel_id');
            $table->foreign('vistoria_id')
                ->references('id')
                ->on('vistorias')
                ->nullOnDelete();
            $table->index('vistoria_id');
        });
    }

    public function down(): void
    {
        Schema::table('vistoria_solicitacoes', function (Blueprint $table) {
            $table->dropForeign(['vistoria_id']);
            $table->dropColumn('vistoria_id');
        });
    }
};
