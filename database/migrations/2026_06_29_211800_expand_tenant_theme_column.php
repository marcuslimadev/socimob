<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tenants') || !Schema::hasColumn('tenants', 'theme')) {
            return;
        }

        DB::statement("ALTER TABLE tenants MODIFY theme VARCHAR(80) NOT NULL DEFAULT 'classico-premium'");
    }

    public function down(): void
    {
        if (!Schema::hasTable('tenants') || !Schema::hasColumn('tenants', 'theme')) {
            return;
        }

        DB::statement("ALTER TABLE tenants MODIFY theme ENUM('classico', 'bauhaus') NOT NULL DEFAULT 'classico'");
    }
};
