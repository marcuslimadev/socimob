<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos_locacao', function (Blueprint $table) {
            $table->string('numero_contrato', 30)->nullable()->after('id');
            $table->string('tipo_garantia', 30)->default('none')->after('valor_seguro');
            $table->decimal('valor_garantia', 15, 2)->nullable()->after('tipo_garantia');
            $table->decimal('comissao_administracao_percentual', 5, 2)->default(10.00)->after('valor_garantia');
            $table->text('observacoes')->nullable()->after('comissao_administracao_percentual');
            $table->json('clausulas')->nullable()->after('observacoes');
        });

        // Preencher numero_contrato para registros existentes
        DB::statement("
            UPDATE contratos_locacao
            SET numero_contrato = CONCAT('LOCACAO-', YEAR(created_at), '-', LPAD(id, 4, '0'))
            WHERE numero_contrato IS NULL
        ");

        // Adicionar unique após o preenchimento
        Schema::table('contratos_locacao', function (Blueprint $table) {
            $table->unique(['tenant_id', 'numero_contrato'], 'contratos_locacao_tenant_numero_unique');
        });
    }

    public function down(): void
    {
        Schema::table('contratos_locacao', function (Blueprint $table) {
            $table->dropUnique('contratos_locacao_tenant_numero_unique');
            $table->dropColumn([
                'numero_contrato',
                'tipo_garantia',
                'valor_garantia',
                'comissao_administracao_percentual',
                'observacoes',
                'clausulas',
            ]);
        });
    }
};
