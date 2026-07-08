<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->timestamp('atendimento_notificado_em')->nullable()->after('chaves_na_mao_retries');
            $table->string('atendimento_notificado_para')->nullable()->after('atendimento_notificado_em');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['atendimento_notificado_em', 'atendimento_notificado_para']);
        });
    }
};
