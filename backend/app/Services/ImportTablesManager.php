<?php

namespace App\Services;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

class ImportTablesManager
{
    /**
     * Garante que as tabelas de importação existam. Se não existirem, cria com a mesma estrutura da migration.
     */
    public static function ensureImportTablesExist(): void
    {
        if (!Schema::hasTable('import_jobs')) {
            Schema::create('import_jobs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('tipo', 50);
                $table->string('status', 30)->default('agendado');
                $table->string('origem', 50)->nullable();
                $table->string('responsavel', 120)->default('Sistema');
                $table->json('parametros')->nullable();
                $table->unsignedInteger('total_itens')->default(0);
                $table->unsignedInteger('processados')->default(0);
                $table->unsignedInteger('erros')->default(0);
                $table->unsignedSmallInteger('tempo_execucao')->nullable();
                $table->timestamp('inicio_previsto')->nullable();
                $table->timestamp('iniciado_em')->nullable();
                $table->timestamp('finalizado_em')->nullable();
                $table->timestamps();
            });

            Log::info('ImportTablesManager: tabela import_jobs criada automaticamente.');
        }

        if (!Schema::hasTable('import_logs')) {
            Schema::create('import_logs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('job_id')->nullable();
                $table->string('nivel', 20)->default('info');
                $table->string('codigo_imovel', 100)->nullable();
                $table->text('mensagem');
                $table->json('detalhes')->nullable();
                $table->timestamps();

                $table->foreign('job_id')
                    ->references('id')
                    ->on('import_jobs')
                    ->onDelete('cascade');
            });

            Log::info('ImportTablesManager: tabela import_logs criada automaticamente.');
        }
    }
}
