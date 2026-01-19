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
        Schema::table('tenant_configs', function (Blueprint $table) {
            // Adicionar campos do Twilio se não existirem
            if (!Schema::hasColumn('tenant_configs', 'twilio_account_sid')) {
                $table->text('twilio_account_sid')->nullable();
            }
            if (!Schema::hasColumn('tenant_configs', 'twilio_auth_token')) {
                $table->text('twilio_auth_token')->nullable();
            }
            if (!Schema::hasColumn('tenant_configs', 'twilio_whatsapp_from')) {
                $table->string('twilio_whatsapp_from', 50)->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_configs', function (Blueprint $table) {
            if (Schema::hasColumn('tenant_configs', 'twilio_account_sid')) {
                $table->dropColumn('twilio_account_sid');
            }
            if (Schema::hasColumn('tenant_configs', 'twilio_auth_token')) {
                $table->dropColumn('twilio_auth_token');
            }
            if (Schema::hasColumn('tenant_configs', 'twilio_whatsapp_from')) {
                $table->dropColumn('twilio_whatsapp_from');
            }
        });
    }
};
