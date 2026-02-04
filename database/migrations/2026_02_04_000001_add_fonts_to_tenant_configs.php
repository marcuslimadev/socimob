<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_configs', function (Blueprint $table) {
            if (!Schema::hasColumn('tenant_configs', 'font_primary')) {
                $table->string('font_primary', 200)->nullable()->after('accent_color');
            }
            if (!Schema::hasColumn('tenant_configs', 'font_secondary')) {
                $table->string('font_secondary', 200)->nullable()->after('font_primary');
            }
            if (!Schema::hasColumn('tenant_configs', 'font_url')) {
                $table->string('font_url', 500)->nullable()->after('font_secondary');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenant_configs', function (Blueprint $table) {
            if (Schema::hasColumn('tenant_configs', 'font_url')) {
                $table->dropColumn('font_url');
            }
            if (Schema::hasColumn('tenant_configs', 'font_secondary')) {
                $table->dropColumn('font_secondary');
            }
            if (Schema::hasColumn('tenant_configs', 'font_primary')) {
                $table->dropColumn('font_primary');
            }
        });
    }
};
