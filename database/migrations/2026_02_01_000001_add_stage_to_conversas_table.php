<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('conversas')) {
            return;
        }

        Schema::table('conversas', function (Blueprint $table) {
            if (!Schema::hasColumn('conversas', 'stage')) {
                $table->string('stage')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('conversas')) {
            return;
        }

        Schema::table('conversas', function (Blueprint $table) {
            if (Schema::hasColumn('conversas', 'stage')) {
                $table->dropColumn('stage');
            }
        });
    }
};
