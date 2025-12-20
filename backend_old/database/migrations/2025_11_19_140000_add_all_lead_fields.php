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
            // DADOS CADASTRAIS
            if (!Schema::hasColumn('leads', 'cpf')) {
                $table->string('cpf', 14)->nullable()->after('telefone');
            }
            if (!Schema::hasColumn('leads', 'email')) {
                $table->string('email', 150)->nullable()->after('cpf');
            }
            
            // DADOS FINANCEIROS
            if (!Schema::hasColumn('leads', 'renda_mensal')) {
                $table->decimal('renda_mensal', 15, 2)->nullable()->after('email');
            }
            if (!Schema::hasColumn('leads', 'budget_min')) {
                $table->decimal('budget_min', 15, 2)->nullable()->after('renda_mensal');
            }
            if (!Schema::hasColumn('leads', 'budget_max')) {
                $table->decimal('budget_max', 15, 2)->nullable()->after('budget_min');
            }
            
            // DADOS PESSOAIS
            if (!Schema::hasColumn('leads', 'estado_civil')) {
                $table->string('estado_civil', 50)->nullable()->after('budget_max');
            }
            if (!Schema::hasColumn('leads', 'composicao_familiar')) {
                $table->string('composicao_familiar', 150)->nullable()->after('estado_civil');
            }
            if (!Schema::hasColumn('leads', 'profissao')) {
                $table->string('profissao', 150)->nullable()->after('composicao_familiar');
            }
            if (!Schema::hasColumn('leads', 'fonte_renda')) {
                $table->string('fonte_renda', 100)->nullable()->after('profissao');
            }
            
            // DADOS DE FINANCIAMENTO
            if (!Schema::hasColumn('leads', 'financiamento_status')) {
                $table->string('financiamento_status', 100)->nullable()->after('fonte_renda');
            }
            if (!Schema::hasColumn('leads', 'prazo_compra')) {
                $table->string('prazo_compra', 100)->nullable()->after('financiamento_status');
            }
            if (!Schema::hasColumn('leads', 'objetivo_compra')) {
                $table->string('objetivo_compra', 100)->nullable()->after('prazo_compra');
            }
            
            // PREFERÊNCIAS DE IMÓVEL
            if (!Schema::hasColumn('leads', 'localizacao')) {
                $table->string('localizacao', 200)->nullable()->after('objetivo_compra');
            }
            if (!Schema::hasColumn('leads', 'city')) {
                $table->string('city', 100)->nullable()->after('localizacao');
            }
            if (!Schema::hasColumn('leads', 'state')) {
                $table->string('state', 2)->nullable()->after('city');
            }
            if (!Schema::hasColumn('leads', 'country')) {
                $table->string('country', 50)->nullable()->after('state');
            }
            if (!Schema::hasColumn('leads', 'latitude')) {
                $table->decimal('latitude', 10, 8)->nullable()->after('country');
            }
            if (!Schema::hasColumn('leads', 'longitude')) {
                $table->decimal('longitude', 11, 8)->nullable()->after('latitude');
            }
            if (!Schema::hasColumn('leads', 'quartos')) {
                $table->integer('quartos')->nullable()->after('longitude');
            }
            if (!Schema::hasColumn('leads', 'suites')) {
                $table->integer('suites')->nullable()->after('quartos');
            }
            if (!Schema::hasColumn('leads', 'garagem')) {
                $table->integer('garagem')->nullable()->after('suites');
            }
            if (!Schema::hasColumn('leads', 'preferencia_tipo_imovel')) {
                $table->string('preferencia_tipo_imovel', 100)->nullable()->after('garagem');
            }
            if (!Schema::hasColumn('leads', 'preferencia_bairro')) {
                $table->string('preferencia_bairro', 200)->nullable()->after('preferencia_tipo_imovel');
            }
            if (!Schema::hasColumn('leads', 'preferencia_lazer')) {
                $table->text('preferencia_lazer')->nullable()->after('preferencia_bairro');
            }
            if (!Schema::hasColumn('leads', 'preferencia_seguranca')) {
                $table->text('preferencia_seguranca')->nullable()->after('preferencia_lazer');
            }
            if (!Schema::hasColumn('leads', 'caracteristicas_desejadas')) {
                $table->text('caracteristicas_desejadas')->nullable()->after('preferencia_seguranca');
            }
            
            // OBSERVAÇÕES E DIAGNÓSTICO
            if (!Schema::hasColumn('leads', 'observacoes_cliente')) {
                $table->text('observacoes_cliente')->nullable()->after('caracteristicas_desejadas');
            }
            if (!Schema::hasColumn('leads', 'diagnostico_ia')) {
                $table->text('diagnostico_ia')->nullable()->after('observacoes_cliente');
            }
            if (!Schema::hasColumn('leads', 'diagnostico_status')) {
                $table->string('diagnostico_status', 20)->default('pendente')->after('diagnostico_ia');
            }
            if (!Schema::hasColumn('leads', 'diagnostico_gerado_em')) {
                $table->timestamp('diagnostico_gerado_em')->nullable()->after('diagnostico_status');
            }
            
            // TIMESTAMPS DE INTERAÇÃO
            if (!Schema::hasColumn('leads', 'primeira_interacao')) {
                $table->timestamp('primeira_interacao')->nullable()->after('diagnostico_gerado_em');
            }
            if (!Schema::hasColumn('leads', 'ultima_interacao')) {
                $table->timestamp('ultima_interacao')->nullable()->after('primeira_interacao');
            }
            
            // GESTÃO E ORIGEM
            if (!Schema::hasColumn('leads', 'corretor_id')) {
                $table->unsignedBigInteger('corretor_id')->nullable()->after('ultima_interacao');
            }
            if (!Schema::hasColumn('leads', 'status')) {
                $table->string('status', 50)->default('novo')->after('corretor_id');
            }
            if (!Schema::hasColumn('leads', 'origem')) {
                $table->string('origem', 50)->nullable()->after('status');
            }
            if (!Schema::hasColumn('leads', 'score')) {
                $table->integer('score')->default(0)->after('origem');
            }
            if (!Schema::hasColumn('leads', 'whatsapp_name')) {
                $table->string('whatsapp_name', 150)->nullable()->after('score');
            }
            if (!Schema::hasColumn('leads', 'profile_pic_url')) {
                $table->string('profile_pic_url', 500)->nullable()->after('whatsapp_name');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $columns = [
                'cpf', 'email', 'renda_mensal', 'budget_min', 'budget_max',
                'estado_civil', 'composicao_familiar', 'profissao', 'fonte_renda',
                'financiamento_status', 'prazo_compra', 'objetivo_compra',
                'localizacao', 'city', 'state', 'country', 'latitude', 'longitude',
                'quartos', 'suites', 'garagem', 'preferencia_tipo_imovel', 'preferencia_bairro',
                'preferencia_lazer', 'preferencia_seguranca', 'caracteristicas_desejadas',
                'observacoes_cliente', 'diagnostico_ia', 'diagnostico_status', 'diagnostico_gerado_em',
                'primeira_interacao', 'ultima_interacao', 'corretor_id', 'status', 'origem', 'score',
                'whatsapp_name', 'profile_pic_url'
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('leads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
