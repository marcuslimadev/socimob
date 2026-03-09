<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos_locacao', function (Blueprint $table) {
            $table->date('rescindido_em')->nullable()->after('fim');
            $table->text('motivo_rescisao')->nullable()->after('rescindido_em');
            $table->decimal('multa_rescisao_calculada', 15, 2)->nullable()->after('motivo_rescisao');
            $table->date('renovado_ate')->nullable()->after('multa_rescisao_calculada');
            $table->index(['tenant_id', 'rescindido_em'], 'contratos_locacao_tenant_rescindido_index');
        });
    }

    public function down(): void
    {
        Schema::table('contratos_locacao', function (Blueprint $table) {
            $table->dropIndex('contratos_locacao_tenant_rescindido_index');
            $table->dropColumn(['rescindido_em', 'motivo_rescisao', 'multa_rescisao_calculada', 'renovado_ate']);
        });
    }
};
