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
        Schema::table('leads', function (Blueprint $table) {
            $table->boolean('sms_enviado')->default(false)->after('ultima_interacao');
            $table->timestamp('sms_enviado_em')->nullable()->after('sms_enviado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn(['sms_enviado', 'sms_enviado_em']);
        });
    }
};
