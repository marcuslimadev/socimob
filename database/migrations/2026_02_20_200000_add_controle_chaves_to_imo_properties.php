<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imo_properties', function (Blueprint $table) {
            if (!Schema::hasColumn('imo_properties', 'local_chaves')) {
                $table->string('local_chaves', 255)->nullable()->after('descricao_resumida');
            }
            if (!Schema::hasColumn('imo_properties', 'status_chaves')) {
                $table->string('status_chaves', 30)->default('disponivel')->after('local_chaves');
            }
        });
    }

    public function down(): void
    {
        Schema::table('imo_properties', function (Blueprint $table) {
            if (Schema::hasColumn('imo_properties', 'status_chaves')) {
                $table->dropColumn('status_chaves');
            }
            if (Schema::hasColumn('imo_properties', 'local_chaves')) {
                $table->dropColumn('local_chaves');
            }
        });
    }
};

