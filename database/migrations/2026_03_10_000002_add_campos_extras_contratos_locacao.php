<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contratos_locacao', function (Blueprint $table) {
            // Data de assinatura do contrato
            $table->date('data_assinatura')->nullable()->after('fim');

            // Destinação do imóvel
            $table->string('destinacao_imovel', 50)->nullable()->default('residencial')->after('data_assinatura');

            // Garantidora (para seguro fiança / Garantti)
            $table->string('garantidora_nome', 255)->nullable()->after('tipo_garantia');
            $table->string('garantidora_cnpj', 18)->nullable()->after('garantidora_nome');
            $table->string('garantidora_endereco', 500)->nullable()->after('garantidora_cnpj');

            // Múltiplos locadores (co-proprietários)
            // Armazena array de pessoa_ids adicionais (além do locador_pessoa_id principal)
            $table->json('co_locadores_ids')->nullable()->after('locador_pessoa_id');

            // Valor da administração em reais (além do percentual)
            $table->decimal('valor_administracao', 15, 2)->nullable()->after('comissao_administracao_percentual');
        });
    }

    public function down(): void
    {
        Schema::table('contratos_locacao', function (Blueprint $table) {
            $table->dropColumn([
                'data_assinatura',
                'destinacao_imovel',
                'garantidora_nome',
                'garantidora_cnpj',
                'garantidora_endereco',
                'co_locadores_ids',
                'valor_administracao',
            ]);
        });
    }
};
