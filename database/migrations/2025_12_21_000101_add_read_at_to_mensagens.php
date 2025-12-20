<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('mensagens', 'read_at')) {
            Schema::table('mensagens', function (Blueprint $table) {
                $table->dateTime('read_at')->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('mensagens', 'read_at')) {
            Schema::table('mensagens', function (Blueprint $table) {
                $table->dropColumn('read_at');
            });
        }
    }
};
