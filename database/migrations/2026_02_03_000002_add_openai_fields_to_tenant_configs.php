<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_configs', function (Blueprint $table) {
            // OpenAI
            if (!Schema::hasColumn('tenant_configs', 'api_key_openai')) {
                $table->text('api_key_openai')->nullable()->comment('Chave API da OpenAI');
            }
            if (!Schema::hasColumn('tenant_configs', 'openai_model')) {
                $table->string('openai_model')->default('gpt-4o-mini')->nullable()->comment('Modelo OpenAI (gpt-4, gpt-3.5-turbo, etc)');
            }
            if (!Schema::hasColumn('tenant_configs', 'ai_assistant_name')) {
                $table->string('ai_assistant_name')->default('Assistente')->nullable()->comment('Nome do assistente IA');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenant_configs', function (Blueprint $table) {
            $table->dropColumn(['api_key_openai', 'openai_model', 'ai_assistant_name']);
        });
    }
};
