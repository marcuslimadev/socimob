<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('tenant_configs', 'whatsapp_number')) {
            Schema::table('tenant_configs', function (Blueprint $table) {
                $table->string('whatsapp_number')->nullable()->comment('Número de WhatsApp do tenant para contato direto (ex: +5531999887766)');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tenant_configs', 'whatsapp_number')) {
            Schema::table('tenant_configs', function (Blueprint $table) {
                $table->dropColumn('whatsapp_number');
            });
        }
    }
};
