<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            if (!Schema::hasColumn('leads', 'corretor_id')) {
                $table->unsignedBigInteger('corretor_id')->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('leads', 'budget_min')) {
                $table->decimal('budget_min', 12, 2)->nullable();
            }
            if (!Schema::hasColumn('leads', 'budget_max')) {
                $table->decimal('budget_max', 12, 2)->nullable();
            }
            if (!Schema::hasColumn('leads', 'localizacao')) {
                $table->string('localizacao')->nullable();
            }
            if (!Schema::hasColumn('leads', 'quartos')) {
                $table->integer('quartos')->nullable();
            }
            if (!Schema::hasColumn('leads', 'suites')) {
                $table->integer('suites')->nullable();
            }
            if (!Schema::hasColumn('leads', 'garagem')) {
                $table->integer('garagem')->nullable();
            }
            if (!Schema::hasColumn('leads', 'cpf')) {
                $table->string('cpf', 11)->nullable();
            }
            if (!Schema::hasColumn('leads', 'renda_mensal')) {
                $table->decimal('renda_mensal', 12, 2)->nullable();
            }
            if (!Schema::hasColumn('leads', 'estado_civil')) {
                $table->string('estado_civil')->nullable();
            }
            if (!Schema::hasColumn('leads', 'composicao_familiar')) {
                $table->string('composicao_familiar')->nullable();
            }
            if (!Schema::hasColumn('leads', 'profissao')) {
                $table->string('profissao')->nullable();
            }
            if (!Schema::hasColumn('leads', 'fonte_renda')) {
                $table->string('fonte_renda')->nullable();
            }
            if (!Schema::hasColumn('leads', 'financiamento_status')) {
                $table->string('financiamento_status')->nullable();
            }
            if (!Schema::hasColumn('leads', 'prazo_compra')) {
                $table->string('prazo_compra')->nullable();
            }
            if (!Schema::hasColumn('leads', 'objetivo_compra')) {
                $table->string('objetivo_compra')->nullable();
            }
            if (!Schema::hasColumn('leads', 'preferencia_tipo_imovel')) {
                $table->string('preferencia_tipo_imovel')->nullable();
            }
            if (!Schema::hasColumn('leads', 'preferencia_bairro')) {
                $table->string('preferencia_bairro')->nullable();
            }
            if (!Schema::hasColumn('leads', 'preferencia_lazer')) {
                $table->text('preferencia_lazer')->nullable();
            }
            if (!Schema::hasColumn('leads', 'preferencia_seguranca')) {
                $table->text('preferencia_seguranca')->nullable();
            }
            if (!Schema::hasColumn('leads', 'observacoes_cliente')) {
                $table->text('observacoes_cliente')->nullable();
            }
            if (!Schema::hasColumn('leads', 'caracteristicas_desejadas')) {
                $table->text('caracteristicas_desejadas')->nullable();
            }
            if (!Schema::hasColumn('leads', 'state')) {
                $table->string('state', 2)->nullable();
            }
            if (!Schema::hasColumn('leads', 'primeira_interacao')) {
                $table->dateTime('primeira_interacao')->nullable();
            }
            if (!Schema::hasColumn('leads', 'ultima_interacao')) {
                $table->dateTime('ultima_interacao')->nullable();
            }
            if (!Schema::hasColumn('leads', 'diagnostico_ia')) {
                $table->longText('diagnostico_ia')->nullable();
            }
            if (!Schema::hasColumn('leads', 'diagnostico_status')) {
                $table->string('diagnostico_status')->nullable();
            }
            if (!Schema::hasColumn('leads', 'diagnostico_gerado_em')) {
                $table->dateTime('diagnostico_gerado_em')->nullable();
            }
            if (!Schema::hasColumn('leads', 'whatsapp_name')) {
                $table->string('whatsapp_name')->nullable();
            }
        });

        if (Schema::hasColumn('leads', 'corretor_id')) {
            $hasIndex = DB::select("SHOW INDEX FROM leads WHERE Key_name = 'leads_corretor_id_index'");
            if (empty($hasIndex)) {
                Schema::table('leads', function (Blueprint $table) {
                    $table->index('corretor_id');
                });
            }
        }
        if (Schema::hasColumn('leads', 'cpf')) {
            $hasUnique = DB::select("SHOW INDEX FROM leads WHERE Key_name = 'leads_tenant_id_cpf_unique'");
            if (empty($hasUnique)) {
                Schema::table('leads', function (Blueprint $table) {
                    $table->unique(['tenant_id', 'cpf']);
                });
            }
        }

        if (Schema::hasColumn('leads', 'status')) {
            DB::statement("ALTER TABLE leads MODIFY COLUMN status VARCHAR(50) DEFAULT 'novo'");
        }

        Schema::table('conversas', function (Blueprint $table) {
            if (!Schema::hasColumn('conversas', 'corretor_id')) {
                $table->unsignedBigInteger('corretor_id')->nullable()->after('lead_id');
            }
            if (!Schema::hasColumn('conversas', 'ultima_atividade')) {
                $table->dateTime('ultima_atividade')->nullable()->after('status');
            }
        });

        if (Schema::hasColumn('conversas', 'corretor_id')) {
            $hasIndex = DB::select("SHOW INDEX FROM conversas WHERE Key_name = 'conversas_corretor_id_index'");
            if (empty($hasIndex)) {
                Schema::table('conversas', function (Blueprint $table) {
                    $table->index('corretor_id');
                });
            }
        }
        if (Schema::hasColumn('conversas', 'ultima_atividade')) {
            $hasIndex = DB::select("SHOW INDEX FROM conversas WHERE Key_name = 'conversas_ultima_atividade_index'");
            if (empty($hasIndex)) {
                Schema::table('conversas', function (Blueprint $table) {
                    $table->index('ultima_atividade');
                });
            }
        }

        Schema::table('imo_properties', function (Blueprint $table) {
            if (!Schema::hasColumn('imo_properties', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            }
            if (!Schema::hasColumn('imo_properties', 'codigo_imovel')) {
                $table->string('codigo_imovel')->nullable();
            }
            if (!Schema::hasColumn('imo_properties', 'referencia_imovel')) {
                $table->string('referencia_imovel')->nullable();
            }
            if (!Schema::hasColumn('imo_properties', 'finalidade_imovel')) {
                $table->string('finalidade_imovel')->nullable();
            }
            if (!Schema::hasColumn('imo_properties', 'tipo_imovel')) {
                $table->string('tipo_imovel')->nullable();
            }
            if (!Schema::hasColumn('imo_properties', 'dormitorios')) {
                $table->integer('dormitorios')->default(0);
            }
            if (!Schema::hasColumn('imo_properties', 'suites')) {
                $table->integer('suites')->default(0);
            }
            if (!Schema::hasColumn('imo_properties', 'banheiros')) {
                $table->integer('banheiros')->default(0);
            }
            if (!Schema::hasColumn('imo_properties', 'garagem')) {
                $table->integer('garagem')->default(0);
            }
            if (!Schema::hasColumn('imo_properties', 'valor_venda')) {
                $table->decimal('valor_venda', 15, 2)->default(0);
            }
            if (!Schema::hasColumn('imo_properties', 'valor_iptu')) {
                $table->decimal('valor_iptu', 15, 2)->default(0);
            }
            if (!Schema::hasColumn('imo_properties', 'valor_condominio')) {
                $table->decimal('valor_condominio', 15, 2)->default(0);
            }
            if (!Schema::hasColumn('imo_properties', 'cidade')) {
                $table->string('cidade')->nullable();
            }
            if (!Schema::hasColumn('imo_properties', 'estado')) {
                $table->string('estado')->nullable();
            }
            if (!Schema::hasColumn('imo_properties', 'bairro')) {
                $table->string('bairro')->nullable();
            }
            if (!Schema::hasColumn('imo_properties', 'logradouro')) {
                $table->string('logradouro')->nullable();
            }
            if (!Schema::hasColumn('imo_properties', 'numero')) {
                $table->string('numero')->nullable();
            }
            if (!Schema::hasColumn('imo_properties', 'complemento')) {
                $table->string('complemento')->nullable();
            }
            if (!Schema::hasColumn('imo_properties', 'cep')) {
                $table->string('cep')->nullable();
            }
            if (!Schema::hasColumn('imo_properties', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable();
            }
            if (!Schema::hasColumn('imo_properties', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable();
            }
            if (!Schema::hasColumn('imo_properties', 'area_privativa')) {
                $table->decimal('area_privativa', 12, 2)->nullable();
            }
            if (!Schema::hasColumn('imo_properties', 'area_total')) {
                $table->decimal('area_total', 12, 2)->nullable();
            }
            if (!Schema::hasColumn('imo_properties', 'area_terreno')) {
                $table->decimal('area_terreno', 12, 2)->nullable();
            }
            if (!Schema::hasColumn('imo_properties', 'imagem_destaque')) {
                $table->string('imagem_destaque')->nullable();
            }
            if (!Schema::hasColumn('imo_properties', 'imagens')) {
                $table->longText('imagens')->nullable();
            }
            if (!Schema::hasColumn('imo_properties', 'caracteristicas')) {
                $table->longText('caracteristicas')->nullable();
            }
            if (!Schema::hasColumn('imo_properties', 'em_condominio')) {
                $table->boolean('em_condominio')->default(false);
            }
            if (!Schema::hasColumn('imo_properties', 'exclusividade')) {
                $table->boolean('exclusividade')->default(false);
            }
            if (!Schema::hasColumn('imo_properties', 'api_data')) {
                $table->json('api_data')->nullable();
            }
            if (!Schema::hasColumn('imo_properties', 'api_created_at')) {
                $table->dateTime('api_created_at')->nullable();
            }
            if (!Schema::hasColumn('imo_properties', 'api_updated_at')) {
                $table->dateTime('api_updated_at')->nullable();
            }
        });

        if (Schema::hasColumn('imo_properties', 'codigo_imovel')) {
            $hasIndex = DB::select("SHOW INDEX FROM imo_properties WHERE Key_name = 'imo_properties_codigo_imovel_index'");
            if (empty($hasIndex)) {
                Schema::table('imo_properties', function (Blueprint $table) {
                    $table->index('codigo_imovel');
                });
            }
        }
    }

    public function down(): void
    {
        Schema::table('conversas', function (Blueprint $table) {
            if (Schema::hasColumn('conversas', 'corretor_id')) {
                $table->dropColumn(['corretor_id']);
            }
            if (Schema::hasColumn('conversas', 'ultima_atividade')) {
                $table->dropColumn(['ultima_atividade']);
            }
        });

        Schema::table('leads', function (Blueprint $table) {
            if (Schema::hasColumn('leads', 'corretor_id')) {
                $table->dropIndex(['corretor_id']);
                $table->dropColumn(['corretor_id']);
            }
            if (Schema::hasColumn('leads', 'cpf')) {
                $table->dropUnique('leads_tenant_id_cpf_unique');
            }
            $table->dropColumn([
                'budget_min', 'budget_max', 'localizacao', 'quartos', 'suites', 'garagem', 'cpf', 'renda_mensal',
                'estado_civil', 'composicao_familiar', 'profissao', 'fonte_renda', 'financiamento_status', 'prazo_compra',
                'objetivo_compra', 'preferencia_tipo_imovel', 'preferencia_bairro', 'preferencia_lazer', 'preferencia_seguranca',
                'observacoes_cliente', 'caracteristicas_desejadas', 'state', 'primeira_interacao', 'ultima_interacao',
                'diagnostico_ia', 'diagnostico_status', 'diagnostico_gerado_em', 'whatsapp_name',
            ]);
        });

        Schema::table('imo_properties', function (Blueprint $table) {
            $table->dropColumn([
                'tenant_id', 'codigo_imovel', 'referencia_imovel', 'finalidade_imovel', 'tipo_imovel', 'dormitorios',
                'suites', 'banheiros', 'garagem', 'valor_venda', 'valor_iptu', 'valor_condominio', 'cidade', 'estado',
                'bairro', 'logradouro', 'numero', 'complemento', 'cep', 'latitude', 'longitude', 'area_privativa',
                'area_total', 'area_terreno', 'imagem_destaque', 'imagens', 'caracteristicas', 'em_condominio',
                'exclusividade', 'api_data', 'api_created_at', 'api_updated_at',
            ]);
        });

        if (Schema::hasColumn('leads', 'status')) {
            DB::statement("ALTER TABLE leads MODIFY COLUMN status ENUM('novo','em_atendimento','qualificado','fechado','descartado') DEFAULT 'novo'");
        }
    }
};
