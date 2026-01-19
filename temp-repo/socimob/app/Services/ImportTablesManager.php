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
        } else {
            self::ensureImportJobsColumns();
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

    private static function ensureImportJobsColumns(): void
    {
        Schema::table('import_jobs', function (Blueprint $table) {
            if (!Schema::hasColumn('import_jobs', 'tipo')) {
                $table->string('tipo', 50)->nullable()->after('id');
            }
            if (!Schema::hasColumn('import_jobs', 'status')) {
                $table->string('status', 30)->default('agendado')->after('tipo');
            }
            if (!Schema::hasColumn('import_jobs', 'origem')) {
                $table->string('origem', 50)->nullable()->after('status');
            }
            if (!Schema::hasColumn('import_jobs', 'responsavel')) {
                $table->string('responsavel', 120)->nullable()->after('origem');
            }
            if (!Schema::hasColumn('import_jobs', 'parametros')) {
                $table->json('parametros')->nullable()->after('responsavel');
            }
            if (!Schema::hasColumn('import_jobs', 'total_itens')) {
                $table->unsignedInteger('total_itens')->default(0)->after('parametros');
            }
            if (!Schema::hasColumn('import_jobs', 'processados')) {
                $table->unsignedInteger('processados')->default(0)->after('total_itens');
            }
            if (!Schema::hasColumn('import_jobs', 'erros')) {
                $table->unsignedInteger('erros')->default(0)->after('processados');
            }
            if (!Schema::hasColumn('import_jobs', 'tempo_execucao')) {
                $table->unsignedSmallInteger('tempo_execucao')->nullable()->after('erros');
            }
            if (!Schema::hasColumn('import_jobs', 'inicio_previsto')) {
                $table->timestamp('inicio_previsto')->nullable()->after('tempo_execucao');
            }
            if (!Schema::hasColumn('import_jobs', 'iniciado_em')) {
                $table->timestamp('iniciado_em')->nullable()->after('inicio_previsto');
            }
            if (!Schema::hasColumn('import_jobs', 'finalizado_em')) {
                $table->timestamp('finalizado_em')->nullable()->after('iniciado_em');
            }
            if (!Schema::hasColumn('import_jobs', 'created_at')) {
                $table->timestamp('created_at')->nullable()->after('finalizado_em');
            }
            if (!Schema::hasColumn('import_jobs', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
        });

        Log::info('ImportTablesManager: tabela import_jobs atualizada com colunas faltantes.');
    }
}
