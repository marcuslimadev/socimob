<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vistorias', function (Blueprint $table) {
            $table->json('imovel_livre')->nullable()->after('imovel_id');
        });
    }

    public function down(): void
    {
        Schema::table('vistorias', function (Blueprint $table) {
            $table->dropColumn('imovel_livre');
        });
    }
};
