<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenant_configs', function (Blueprint $table) {
            if (!Schema::hasColumn('tenant_configs', 'portal_finalidades')) {
                $table->json('portal_finalidades')->nullable()->after('favicon_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenant_configs', function (Blueprint $table) {
            if (Schema::hasColumn('tenant_configs', 'portal_finalidades')) {
                $table->dropColumn('portal_finalidades');
            }
        });
    }
};
