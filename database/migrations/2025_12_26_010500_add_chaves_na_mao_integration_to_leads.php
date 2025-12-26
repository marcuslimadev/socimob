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
            // Campos de controle de integração com Chaves na Mão
            $table->enum('chaves_na_mao_status', ['pending', 'sent', 'error'])->nullable()->after('diagnostico_gerado_em');
            $table->timestamp('chaves_na_mao_sent_at')->nullable()->after('chaves_na_mao_status');
            $table->text('chaves_na_mao_response')->nullable()->after('chaves_na_mao_sent_at');
            $table->text('chaves_na_mao_error')->nullable()->after('chaves_na_mao_response');
            $table->unsignedTinyInteger('chaves_na_mao_retries')->default(0)->after('chaves_na_mao_error');
            
            $table->index('chaves_na_mao_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['chaves_na_mao_status']);
            $table->dropColumn([
                'chaves_na_mao_status',
                'chaves_na_mao_sent_at',
                'chaves_na_mao_response',
                'chaves_na_mao_error',
                'chaves_na_mao_retries'
            ]);
        });
    }
};
