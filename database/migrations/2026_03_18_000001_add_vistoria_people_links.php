<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vistorias', function (Blueprint $table) {
            $table->unsignedBigInteger('responsavel_pessoa_id')->nullable()->after('contrato_id');
            $table->json('participantes_ids')->nullable()->after('pessoas');

            $table->foreign('responsavel_pessoa_id')
                ->references('id')->on('pessoas')
                ->nullOnDelete();

            $table->index(['tenant_id', 'responsavel_pessoa_id'], 'vistorias_tenant_responsavel_index');
        });
    }

    public function down(): void
    {
        Schema::table('vistorias', function (Blueprint $table) {
            $table->dropForeign(['responsavel_pessoa_id']);
            $table->dropIndex('vistorias_tenant_responsavel_index');
            $table->dropColumn(['responsavel_pessoa_id', 'participantes_ids']);
        });
    }
};
