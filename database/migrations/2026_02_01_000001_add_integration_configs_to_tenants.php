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
        Schema::table('tenants', function (Blueprint $table) {
            // Twilio Integration
            if (!Schema::hasColumn('tenants', 'twilio_account_sid')) {
                $table->string('twilio_account_sid', 255)->nullable()->after('api_token');
            }
            if (!Schema::hasColumn('tenants', 'twilio_auth_token')) {
                $table->string('twilio_auth_token', 255)->nullable()->after('twilio_account_sid');
            }
            if (!Schema::hasColumn('tenants', 'twilio_whatsapp_from')) {
                $table->string('twilio_whatsapp_from', 50)->nullable()->after('twilio_auth_token');
            }
            if (!Schema::hasColumn('tenants', 'twilio_template_welcome_sid')) {
                $table->string('twilio_template_welcome_sid', 255)->nullable()->after('twilio_whatsapp_from');
            }
            
            // OpenAI Integration
            if (!Schema::hasColumn('tenants', 'openai_api_key')) {
                $table->text('openai_api_key')->nullable()->after('twilio_template_welcome_sid');
            }
            if (!Schema::hasColumn('tenants', 'openai_model')) {
                $table->string('openai_model', 50)->default('gpt-4o-mini')->nullable()->after('openai_api_key');
            }
            if (!Schema::hasColumn('tenants', 'ai_assistant_name')) {
                $table->string('ai_assistant_name', 100)->default('Teresa')->nullable()->after('openai_model');
            }
            
            // Email Integration
            if (!Schema::hasColumn('tenants', 'mail_driver')) {
                $table->string('mail_driver', 20)->default('smtp')->nullable()->after('ai_assistant_name');
            }
            if (!Schema::hasColumn('tenants', 'mail_host')) {
                $table->string('mail_host', 255)->nullable()->after('mail_driver');
            }
            if (!Schema::hasColumn('tenants', 'mail_port')) {
                $table->integer('mail_port')->default(587)->nullable()->after('mail_host');
            }
            if (!Schema::hasColumn('tenants', 'mail_username')) {
                $table->string('mail_username', 255)->nullable()->after('mail_port');
            }
            if (!Schema::hasColumn('tenants', 'mail_password')) {
                $table->text('mail_password')->nullable()->after('mail_username');
            }
            if (!Schema::hasColumn('tenants', 'mail_encryption')) {
                $table->string('mail_encryption', 10)->default('tls')->nullable()->after('mail_password');
            }
            if (!Schema::hasColumn('tenants', 'mail_from_address')) {
                $table->string('mail_from_address', 255)->nullable()->after('mail_encryption');
            }
            if (!Schema::hasColumn('tenants', 'mail_from_name')) {
                $table->string('mail_from_name', 255)->nullable()->after('mail_from_address');
            }
            
            // Additional Settings
            if (!Schema::hasColumn('tenants', 'razao_social')) {
                $table->string('razao_social', 255)->nullable()->after('description');
            }
            if (!Schema::hasColumn('tenants', 'cnpj')) {
                $table->string('cnpj', 20)->nullable()->after('razao_social');
            }
            if (!Schema::hasColumn('tenants', 'endereco')) {
                $table->string('endereco', 500)->nullable()->after('cnpj');
            }
            if (!Schema::hasColumn('tenants', 'slogan')) {
                $table->string('slogan', 500)->nullable()->after('logo_url');
            }
            if (!Schema::hasColumn('tenants', 'favicon_url')) {
                $table->string('favicon_url', 500)->nullable()->after('slogan');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $columns = [
                'twilio_account_sid',
                'twilio_auth_token',
                'twilio_whatsapp_from',
                'twilio_template_welcome_sid',
                'openai_api_key',
                'openai_model',
                'ai_assistant_name',
                'mail_driver',
                'mail_host',
                'mail_port',
                'mail_username',
                'mail_password',
                'mail_encryption',
                'mail_from_address',
                'mail_from_name',
                'razao_social',
                'cnpj',
                'endereco',
                'slogan',
                'favicon_url',
            ];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('tenants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
