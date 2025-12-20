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
            if (!Schema::hasColumn('leads', 'cpf')) {
                $table->string('cpf', 14)->nullable()->unique()->after('email');
            }

            $numericColumns = [
                'renda_mensal' => ['type' => 'decimal', 'precision' => 15, 'scale' => 2, 'position' => 'after', 'reference' => 'budget_max'],
            ];

            foreach ($numericColumns as $column => $config) {
                if (!Schema::hasColumn('leads', $column)) {
                    $table->decimal($column, $config['precision'], $config['scale'])->nullable()->after($config['reference']);
                }
            }

            $stringColumns = [
                'estado_civil' => 'budget_max',
                'composicao_familiar' => 'estado_civil',
                'profissao' => 'composicao_familiar',
                'fonte_renda' => 'profissao',
                'financiamento_status' => 'fonte_renda',
                'prazo_compra' => 'financiamento_status',
                'objetivo_compra' => 'prazo_compra',
                'preferencia_tipo_imovel' => 'objetivo_compra',
                'preferencia_bairro' => 'preferencia_tipo_imovel',
            ];

            foreach ($stringColumns as $column => $after) {
                if (!Schema::hasColumn('leads', $column)) {
                    $table->string($column, 150)->nullable()->after($after);
                }
            }

            if (!Schema::hasColumn('leads', 'preferencia_lazer')) {
                $table->text('preferencia_lazer')->nullable()->after('preferencia_bairro');
            }

            if (!Schema::hasColumn('leads', 'preferencia_seguranca')) {
                $table->text('preferencia_seguranca')->nullable()->after('preferencia_lazer');
            }

            if (!Schema::hasColumn('leads', 'observacoes_cliente')) {
                $table->text('observacoes_cliente')->nullable()->after('preferencia_seguranca');
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
        });

        if (!Schema::hasTable('lead_documents')) {
            Schema::create('lead_documents', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('lead_id');
                $table->unsignedBigInteger('conversa_id')->nullable();
                $table->unsignedBigInteger('mensagem_id')->nullable();
                $table->string('nome')->nullable();
                $table->string('tipo', 50)->nullable();
                $table->string('mime_type', 80)->nullable();
                $table->string('status', 20)->default('pendente');
                $table->string('arquivo_url', 500)->nullable();
                $table->text('observacoes')->nullable();
                $table->timestamps();

                $table->foreign('lead_id')->references('id')->on('leads')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $columns = [
                'cpf',
                'renda_mensal',
                'estado_civil',
                'composicao_familiar',
                'profissao',
                'fonte_renda',
                'financiamento_status',
                'prazo_compra',
                'objetivo_compra',
                'preferencia_tipo_imovel',
                'preferencia_bairro',
                'preferencia_lazer',
                'preferencia_seguranca',
                'observacoes_cliente',
                'diagnostico_ia',
                'diagnostico_status',
                'diagnostico_gerado_em',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('leads', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        if (Schema::hasTable('lead_documents')) {
            Schema::dropIfExists('lead_documents');
        }
    }
};
