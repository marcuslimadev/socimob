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
            if (!Schema::hasColumn('tenant_configs', 'api_key_openai')) {
                $table->text('api_key_openai')->nullable()->after('api_key_neca');
            }
        });
        
        // Também adicionar na tabela tenants se não existir
        Schema::table('tenants', function (Blueprint $table) {
            if (!Schema::hasColumn('tenants', 'api_key_openai')) {
                $table->text('api_key_openai')->nullable()->after('api_key_neca');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenant_configs', function (Blueprint $table) {
            if (Schema::hasColumn('tenant_configs', 'api_key_openai')) {
                $table->dropColumn('api_key_openai');
            }
        });
        
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'api_key_openai')) {
                $table->dropColumn('api_key_openai');
            }
        });
    }
};

