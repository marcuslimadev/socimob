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
        // Adicionar campo api_url_externa à tabela tenants
        if (!Schema::hasColumn('tenants', 'api_url_externa')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->string('api_url_externa', 500)->nullable()->after('api_token');
            });
        }

        // Adicionar campos Twilio à tabela tenant_configs
        if (!Schema::hasColumn('tenant_configs', 'twilio_account_sid')) {
            Schema::table('tenant_configs', function (Blueprint $table) {
                $table->string('twilio_account_sid', 255)->nullable()->after('api_key_neca');
                $table->text('twilio_auth_token')->nullable()->after('twilio_account_sid');
                $table->string('twilio_whatsapp_from', 50)->nullable()->after('twilio_auth_token');
            });
        }

        // Adicionar campo api_key_openai se não existir
        if (!Schema::hasColumn('tenant_configs', 'api_key_openai')) {
            Schema::table('tenant_configs', function (Blueprint $table) {
                $table->text('api_key_openai')->nullable()->after('api_key_neca');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('tenants', 'api_url_externa')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropColumn('api_url_externa');
            });
        }

        if (Schema::hasColumn('tenant_configs', 'twilio_account_sid')) {
            Schema::table('tenant_configs', function (Blueprint $table) {
                $table->dropColumn([
                    'twilio_account_sid',
                    'twilio_auth_token',
                    'twilio_whatsapp_from',
                ]);
            });
        }

        if (Schema::hasColumn('tenant_configs', 'api_key_openai')) {
            Schema::table('tenant_configs', function (Blueprint $table) {
                $table->dropColumn('api_key_openai');
            });
        }
    }
};
