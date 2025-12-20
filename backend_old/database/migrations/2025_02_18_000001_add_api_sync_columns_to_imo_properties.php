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
        Schema::table('imo_properties', function (Blueprint $table) {
            if (!Schema::hasColumn('imo_properties', 'api_created_at')) {
                $table->timestamp('api_created_at')->nullable();
            }

            if (!Schema::hasColumn('imo_properties', 'api_updated_at')) {
                $table->timestamp('api_updated_at')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('imo_properties', function (Blueprint $table) {
            if (Schema::hasColumn('imo_properties', 'api_created_at')) {
                $table->dropColumn('api_created_at');
            }

            if (Schema::hasColumn('imo_properties', 'api_updated_at')) {
                $table->dropColumn('api_updated_at');
            }
        });
    }
};
