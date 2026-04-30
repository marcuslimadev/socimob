<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('app_settings')) {
            return;
        }

        // Força OpenAI como padrão global e por tenant.
        DB::table('app_settings')
            ->where('key', 'ai_atendimento_provider')
            ->update(['value' => 'openai']);
    }

    public function down(): void
    {
        // Sem reversão automática segura para configuração operacional.
    }
};
