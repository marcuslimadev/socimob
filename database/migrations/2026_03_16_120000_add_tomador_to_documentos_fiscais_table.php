<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('documentos_fiscais', function (Blueprint $table) {
            $table->unsignedBigInteger('tomador_pessoa_id')->nullable()->after('locatario_pessoa_id');
            $table->unsignedBigInteger('property_id')->nullable()->after('tomador_pessoa_id');
            $table->string('contexto_emissao', 40)->nullable()->after('property_id');

            $table->index(['tenant_id', 'tomador_pessoa_id'], 'documentos_fiscais_tenant_tomador_idx');
            $table->index(['tenant_id', 'contexto_emissao'], 'documentos_fiscais_tenant_contexto_idx');
        });
    }

    public function down(): void
    {
        Schema::table('documentos_fiscais', function (Blueprint $table) {
            $table->dropIndex('documentos_fiscais_tenant_tomador_idx');
            $table->dropIndex('documentos_fiscais_tenant_contexto_idx');
            $table->dropColumn(['tomador_pessoa_id', 'property_id', 'contexto_emissao']);
        });
    }
};