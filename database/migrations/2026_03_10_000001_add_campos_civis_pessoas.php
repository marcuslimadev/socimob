<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pessoas', function (Blueprint $table) {
            $table->string('nacionalidade', 100)->nullable()->after('pais');
            $table->enum('estado_civil', [
                'solteiro',
                'casado',
                'uniao_estavel',
                'divorciado',
                'viuvo',
                'separado',
            ])->nullable()->after('nacionalidade');
            $table->string('regime_bens', 80)->nullable()->after('estado_civil');

            // Cônjuge / companheiro(a)
            $table->string('conjuge_nome', 255)->nullable()->after('regime_bens');
            $table->string('conjuge_cpf', 14)->nullable()->after('conjuge_nome');
            $table->string('conjuge_rg', 20)->nullable()->after('conjuge_cpf');
            $table->string('conjuge_profissao', 100)->nullable()->after('conjuge_rg');
            $table->string('conjuge_nacionalidade', 100)->nullable()->after('conjuge_profissao');
            $table->string('conjuge_orgao_expedidor', 50)->nullable()->after('conjuge_nacionalidade');
            $table->date('conjuge_data_expedicao')->nullable()->after('conjuge_orgao_expedidor');
        });
    }

    public function down(): void
    {
        Schema::table('pessoas', function (Blueprint $table) {
            $table->dropColumn([
                'nacionalidade',
                'estado_civil',
                'regime_bens',
                'conjuge_nome',
                'conjuge_cpf',
                'conjuge_rg',
                'conjuge_profissao',
                'conjuge_nacionalidade',
                'conjuge_orgao_expedidor',
                'conjuge_data_expedicao',
            ]);
        });
    }
};
