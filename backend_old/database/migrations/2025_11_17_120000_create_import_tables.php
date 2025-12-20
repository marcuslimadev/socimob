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
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('import_logs')) {
            Schema::dropIfExists('import_logs');
        }

        if (Schema::hasTable('import_jobs')) {
            Schema::dropIfExists('import_jobs');
        }
    }
};
