<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Adicionar campos de API externa na tabela tenants se não existirem
        if (!Schema::hasColumn('tenants', 'api_url_externa')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->string('api_url_externa')->nullable();
            });
        }
        
        if (!Schema::hasColumn('tenants', 'api_token_externa')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->text('api_token_externa')->nullable();
            });
        }

        // Adicionar campos Twilio na tabela tenant_configs se não existirem
        if (!Schema::hasColumn('tenant_configs', 'twilio_account_sid')) {
            Schema::table('tenant_configs', function (Blueprint $table) {
                $table->string('twilio_account_sid')->nullable();
            });
        }
        
        if (!Schema::hasColumn('tenant_configs', 'twilio_auth_token')) {
            Schema::table('tenant_configs', function (Blueprint $table) {
                $table->string('twilio_auth_token')->nullable();
            });
        }
        
        if (!Schema::hasColumn('tenant_configs', 'twilio_whatsapp_from')) {
            Schema::table('tenant_configs', function (Blueprint $table) {
                $table->string('twilio_whatsapp_from')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tenants', 'api_url_externa')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropColumn('api_url_externa');
            });
        }
        
        if (Schema::hasColumn('tenants', 'api_token_externa')) {
            Schema::table('tenants', function (Blueprint $table) {
                $table->dropColumn('api_token_externa');
            });
        }

        if (Schema::hasColumn('tenant_configs', 'twilio_account_sid')) {
            Schema::table('tenant_configs', function (Blueprint $table) {
                $table->dropColumn(['twilio_account_sid', 'twilio_auth_token', 'twilio_whatsapp_from']);
            });
        }
    }
};
