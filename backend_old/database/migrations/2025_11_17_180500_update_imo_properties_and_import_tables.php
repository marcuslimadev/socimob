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
        // Garantir tables de importação
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

                $table->foreign('job_id')->references('id')->on('import_jobs')->onDelete('cascade');
            });
        }

        // Adicionar colunas faltantes em imo_properties
        if (Schema::hasTable('imo_properties')) {
            if (!Schema::hasColumn('imo_properties', 'api_created_at')) {
                Schema::table('imo_properties', function (Blueprint $table) {
                    $table->timestamp('api_created_at')->nullable()->after('active');
                });
            }

            if (!Schema::hasColumn('imo_properties', 'api_updated_at')) {
                Schema::table('imo_properties', function (Blueprint $table) {
                    $table->timestamp('api_updated_at')->nullable()->after('api_created_at');
                });
            }

            $cols = [
                'imagens' => function (Blueprint $table) { $table->json('imagens')->nullable(); },
                'caracteristicas' => function (Blueprint $table) { $table->json('caracteristicas')->nullable(); },
                'api_data' => function (Blueprint $table) { $table->json('api_data')->nullable(); },
                'imagem_destaque' => function (Blueprint $table) { $table->string('imagem_destaque')->nullable(); },
                'valor_iptu' => function (Blueprint $table) { $table->decimal('valor_iptu', 12,2)->nullable()->default(0); },
                'valor_condominio' => function (Blueprint $table) { $table->decimal('valor_condominio', 12,2)->nullable()->default(0); },
                'logradouro' => function (Blueprint $table) { $table->string('logradouro')->nullable(); },
                'numero' => function (Blueprint $table) { $table->string('numero')->nullable(); },
                'complemento' => function (Blueprint $table) { $table->string('complemento')->nullable(); },
                'cep' => function (Blueprint $table) { $table->string('cep')->nullable(); },
                'area_terreno' => function (Blueprint $table) { $table->decimal('area_terreno', 10,2)->nullable(); },
                'exclusividade' => function (Blueprint $table) { $table->boolean('exclusividade')->default(false); },
            ];

            foreach ($cols as $col => $closure) {
                if (!Schema::hasColumn('imo_properties', $col)) {
                    Schema::table('imo_properties', $closure);
                }
            }
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

        if (Schema::hasTable('imo_properties')) {
            $columns = [
                'api_created_at','api_updated_at','imagens','caracteristicas','api_data','imagem_destaque','valor_iptu','valor_condominio','logradouro','numero','complemento','cep','area_terreno','exclusividade'
            ];
            Schema::table('imo_properties', function (Blueprint $table) use ($columns) {
                foreach ($columns as $col) {
                    if (Schema::hasColumn('imo_properties', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
