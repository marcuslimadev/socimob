<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Adicionar campos de integração Imobi Brasil na tabela tenants
        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'imobi_brasil_enabled')) {
                $table->boolean('imobi_brasil_enabled')->default(false)->after('api_token_externa');
            }
            if (!Schema::hasColumn('tenants', 'imobi_brasil_api_key')) {
                $table->text('imobi_brasil_api_key')->nullable()->after('imobi_brasil_enabled');
            }
            if (!Schema::hasColumn('tenants', 'imobi_brasil_base_url')) {
                $table->string('imobi_brasil_base_url')->nullable()->default('https://api.imobibrasil.com.br')->after('imobi_brasil_api_key');
            }
        });

        // Adicionar campos de rastreamento na tabela imo_properties
        Schema::table('imo_properties', function (Blueprint $table) {
            if (!Schema::hasColumn('imo_properties', 'imobi_brasil_sent')) {
                $table->boolean('imobi_brasil_sent')->default(false)->after('exclusividade');
            }
            if (!Schema::hasColumn('imo_properties', 'imobi_brasil_sent_at')) {
                $table->timestamp('imobi_brasil_sent_at')->nullable()->after('imobi_brasil_sent');
            }
            if (!Schema::hasColumn('imo_properties', 'imobi_brasil_external_id')) {
                $table->string('imobi_brasil_external_id')->nullable()->unique()->after('imobi_brasil_sent_at');
            }
            if (!Schema::hasColumn('imo_properties', 'imobi_brasil_error')) {
                $table->text('imobi_brasil_error')->nullable()->after('imobi_brasil_external_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('imo_properties', function (Blueprint $table) {
            if (Schema::hasColumn('imo_properties', 'imobi_brasil_error')) {
                $table->dropColumn('imobi_brasil_error');
            }
            if (Schema::hasColumn('imo_properties', 'imobi_brasil_external_id')) {
                $table->dropUnique(['imobi_brasil_external_id']);
                $table->dropColumn('imobi_brasil_external_id');
            }
            if (Schema::hasColumn('imo_properties', 'imobi_brasil_sent_at')) {
                $table->dropColumn('imobi_brasil_sent_at');
            }
            if (Schema::hasColumn('imo_properties', 'imobi_brasil_sent')) {
                $table->dropColumn('imobi_brasil_sent');
            }
        });

        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'imobi_brasil_base_url')) {
                $table->dropColumn('imobi_brasil_base_url');
            }
            if (Schema::hasColumn('tenants', 'imobi_brasil_api_key')) {
                $table->dropColumn('imobi_brasil_api_key');
            }
            if (Schema::hasColumn('tenants', 'imobi_brasil_enabled')) {
                $table->dropColumn('imobi_brasil_enabled');
            }
        });
    }
};
