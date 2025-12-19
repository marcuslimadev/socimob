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

            $table->decimal('budget_min', 12, 2)->nullable();
            $table->decimal('budget_max', 12, 2)->nullable();
            $table->string('localizacao')->nullable();
            $table->integer('quartos')->nullable();
            $table->integer('suites')->nullable();
            $table->integer('garagem')->nullable();
            $table->string('cpf', 11)->nullable();
            $table->decimal('renda_mensal', 12, 2)->nullable();
            $table->string('estado_civil')->nullable();
            $table->string('composicao_familiar')->nullable();
            $table->string('profissao')->nullable();
            $table->string('fonte_renda')->nullable();
            $table->string('financiamento_status')->nullable();
            $table->string('prazo_compra')->nullable();
            $table->string('objetivo_compra')->nullable();
            $table->string('preferencia_tipo_imovel')->nullable();
            $table->string('preferencia_bairro')->nullable();
            $table->text('preferencia_lazer')->nullable();
            $table->text('preferencia_seguranca')->nullable();
            $table->text('observacoes_cliente')->nullable();
            $table->text('caracteristicas_desejadas')->nullable();
            $table->string('state', 2)->nullable();
            $table->dateTime('primeira_interacao')->nullable();
            $table->dateTime('ultima_interacao')->nullable();
            $table->longText('diagnostico_ia')->nullable();
            $table->string('diagnostico_status')->nullable();
            $table->dateTime('diagnostico_gerado_em')->nullable();
            $table->string('whatsapp_name')->nullable();

            $table->index('corretor_id');
            $table->unique(['tenant_id', 'cpf']);
        });

        // Garantir que o status não fique preso ao ENUM antigo
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

            $table->index('corretor_id');
            $table->index('ultima_atividade');
        });

        Schema::table('imo_properties', function (Blueprint $table) {
            if (!Schema::hasColumn('imo_properties', 'tenant_id')) {
                $table->unsignedBigInteger('tenant_id')->nullable()->after('id');
            }
            $table->string('codigo_imovel')->nullable()->index();
            $table->string('referencia_imovel')->nullable();
            $table->string('finalidade_imovel')->nullable();
            $table->string('tipo_imovel')->nullable();
            $table->integer('dormitorios')->default(0);
            $table->integer('suites')->default(0);
            $table->integer('banheiros')->default(0);
            $table->integer('garagem')->default(0);
            $table->decimal('valor_venda', 15, 2)->default(0);
            $table->decimal('valor_iptu', 15, 2)->default(0);
            $table->decimal('valor_condominio', 15, 2)->default(0);
            $table->string('cidade')->nullable();
            $table->string('estado')->nullable();
            $table->string('bairro')->nullable();
            $table->string('logradouro')->nullable();
            $table->string('numero')->nullable();
            $table->string('complemento')->nullable();
            $table->string('cep')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('area_privativa', 12, 2)->nullable();
            $table->decimal('area_total', 12, 2)->nullable();
            $table->decimal('area_terreno', 12, 2)->nullable();
            $table->string('imagem_destaque')->nullable();
            $table->longText('imagens')->nullable();
            $table->longText('caracteristicas')->nullable();
            $table->boolean('em_condominio')->default(false);
            $table->boolean('exclusividade')->default(false);
            $table->json('api_data')->nullable();
            $table->dateTime('api_created_at')->nullable();
            $table->dateTime('api_updated_at')->nullable();
        });
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
            $table->dropUnique('leads_tenant_id_cpf_unique');
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
