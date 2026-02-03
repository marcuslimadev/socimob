<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class EnhancePessoasCrm extends Migration
{
    /**
     * Run the migrations - Melhorias para CRM completo
     */
    public function up()
    {
        Schema::table('pessoas', function (Blueprint $table) {
            // Papéis/Perfis da pessoa
            $table->json('papeis')->nullable()->after('tipo'); // ['cliente', 'proprietario', 'corretor', 'prestador_servico', 'inquilino']
            
            // Status e origem
            $table->string('status')->default('ativo')->after('ativo'); // ativo, inativo, lead, cliente, ex-cliente
            $table->string('origem')->nullable()->after('status'); // site, indicacao, telefone, whatsapp, facebook, instagram
            $table->string('classificacao')->nullable()->after('origem'); // A, B, C, D (potencial)
            
            // Interesses e preferências
            $table->json('interesses')->nullable()->after('classificacao'); // ['comprar', 'alugar', 'vender', 'investir']
            $table->json('preferencias')->nullable()->after('interesses'); // {tipo_imovel, bairros, faixa_preco, etc}
            
            // Dados de negócio
            $table->decimal('renda_mensal', 15, 2)->nullable()->after('preferencias');
            $table->string('profissao')->nullable()->after('renda_mensal');
            $table->string('empresa')->nullable()->after('profissao');
            
            // Documentos e arquivos
            $table->json('documentos')->nullable()->after('empresa'); // [{tipo, nome, url, data_upload}]
            $table->json('fotos')->nullable()->after('documentos'); // URLs de fotos
            
            // Relacionamentos
            $table->unsignedBigInteger('corretor_responsavel_id')->nullable()->after('fotos');
            $table->unsignedBigInteger('indicado_por_id')->nullable()->after('corretor_responsavel_id');
            
            // Métricas e score
            $table->integer('score')->default(0)->after('indicado_por_id'); // Score de engajamento
            $table->timestamp('ultimo_atendimento')->nullable()->after('score');
            $table->timestamp('ultimo_contato')->nullable()->after('ultimo_atendimento');
            $table->integer('total_atendimentos')->default(0)->after('ultimo_contato');
            $table->integer('total_imoveis_visitados')->default(0)->after('total_atendimentos');
            
            // Tags personalizadas
            $table->json('tags')->nullable()->after('total_imoveis_visitados'); // ['vip', 'urgente', 'investidor']
            
            // Redes sociais
            $table->string('facebook')->nullable()->after('tags');
            $table->string('instagram')->nullable()->after('facebook');
            $table->string('linkedin')->nullable()->after('instagram');
            $table->string('whatsapp')->nullable()->after('linkedin');
            
            // Índices para performance
            $table->index('status');
            $table->index('classificacao');
            $table->index('corretor_responsavel_id');
            $table->index('score');
        });
        
        // Criar tabela de interações/timeline
        Schema::create('pessoa_interacoes', function (Blueprint $table) {
            $table->id();
            $table->integer('tenant_id');
            $table->unsignedBigInteger('pessoa_id');
            $table->unsignedBigInteger('user_id')->nullable(); // Quem fez a interação
            
            $table->string('tipo'); // atendimento, visita, ligacao, email, whatsapp, reuniao, proposta
            $table->string('assunto')->nullable();
            $table->text('descricao')->nullable();
            $table->json('metadata')->nullable(); // Dados adicionais específicos do tipo
            
            $table->string('resultado')->nullable(); // positivo, negativo, neutro, pendente
            $table->timestamp('data_interacao');
            $table->timestamp('proxima_acao')->nullable();
            
            $table->timestamps();
            
            $table->index(['pessoa_id', 'data_interacao']);
            $table->index(['tenant_id', 'tipo']);
        });
        
        // Criar tabela de documentos
        Schema::create('pessoa_documentos', function (Blueprint $table) {
            $table->id();
            $table->integer('tenant_id');
            $table->unsignedBigInteger('pessoa_id');
            
            $table->string('tipo'); // rg, cpf, comprovante_renda, contrato, procuracao, etc
            $table->string('nome');
            $table->string('arquivo'); // Caminho do arquivo
            $table->integer('tamanho')->nullable(); // Bytes
            $table->string('mime_type')->nullable();
            $table->text('observacoes')->nullable();
            
            $table->unsignedBigInteger('uploaded_by')->nullable(); // user_id
            $table->timestamp('data_validade')->nullable();
            $table->boolean('verificado')->default(false);
            $table->timestamp('verificado_em')->nullable();
            $table->unsignedBigInteger('verificado_por')->nullable();
            
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['pessoa_id', 'tipo']);
            $table->index('tenant_id');
        });
        
        // Criar tabela de relacionamentos entre pessoas
        Schema::create('pessoa_relacionamentos', function (Blueprint $table) {
            $table->id();
            $table->integer('tenant_id');
            $table->unsignedBigInteger('pessoa_origem_id');
            $table->unsignedBigInteger('pessoa_destino_id');
            
            $table->string('tipo'); // conjuge, socio, fiador, procurador, dependente, referencia
            $table->text('observacoes')->nullable();
            $table->boolean('ativo')->default(true);
            
            $table->timestamps();
            
            $table->index(['pessoa_origem_id', 'tipo']);
            $table->index('tenant_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::table('pessoas', function (Blueprint $table) {
            $table->dropColumn([
                'papeis', 'status', 'origem', 'classificacao',
                'interesses', 'preferencias', 'renda_mensal', 'profissao', 'empresa',
                'documentos', 'fotos', 'corretor_responsavel_id', 'indicado_por_id',
                'score', 'ultimo_atendimento', 'ultimo_contato', 
                'total_atendimentos', 'total_imoveis_visitados',
                'tags', 'facebook', 'instagram', 'linkedin', 'whatsapp'
            ]);
        });
        
        Schema::dropIfExists('pessoa_interacoes');
        Schema::dropIfExists('pessoa_documentos');
        Schema::dropIfExists('pessoa_relacionamentos');
    }
}
