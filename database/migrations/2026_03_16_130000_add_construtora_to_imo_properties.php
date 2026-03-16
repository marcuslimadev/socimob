<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('imo_properties', function (Blueprint $table) {
            $table->unsignedBigInteger('construtora_pessoa_id')->nullable()->after('captador_nome');
            $table->index(['tenant_id', 'construtora_pessoa_id'], 'imo_properties_tenant_construtora_idx');
        });
    }

    public function down(): void
    {
        Schema::table('imo_properties', function (Blueprint $table) {
            $table->dropIndex('imo_properties_tenant_construtora_idx');
            $table->dropColumn('construtora_pessoa_id');
        });
    }
};