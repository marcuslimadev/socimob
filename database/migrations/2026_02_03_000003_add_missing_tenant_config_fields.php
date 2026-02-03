<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_configs', function (Blueprint $table) {
            // Template de boas-vindas do Twilio
            if (!Schema::hasColumn('tenant_configs', 'twilio_template_welcome_sid')) {
                $table->string('twilio_template_welcome_sid')->nullable()
                    ->comment('SID do template de boas-vindas do Twilio');
            }
            
            // Alias para api_key_openai (se não existir, criar)
            if (!Schema::hasColumn('tenant_configs', 'api_key_openai') && 
                !Schema::hasColumn('tenant_configs', 'openai_api_key')) {
                $table->text('openai_api_key')->nullable()
                    ->comment('Chave de API da OpenAI');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenant_configs', function (Blueprint $table) {
            if (Schema::hasColumn('tenant_configs', 'twilio_template_welcome_sid')) {
                $table->dropColumn('twilio_template_welcome_sid');
            }
            if (Schema::hasColumn('tenant_configs', 'openai_api_key')) {
                $table->dropColumn('openai_api_key');
            }
        });
    }
};
