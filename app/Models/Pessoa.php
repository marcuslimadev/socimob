<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Pessoa extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $table = 'pessoas';

    protected $fillable = [
        'tenant_id',
        'nome',
        'pais',
        'telefone',
        'celular',
        'email',
        'tipo',
        'cpf',
        'rg',
        'orgao_expedidor',
        'data_expedicao',
        'cnh',
        'data_nascimento',
        'cnpj',
        'razao_social',
        'inscricao_estadual',
        'inscricao_municipal',
        'cep',
        'estado',
        'cidade',
        'bairro',
        'endereco',
        'numero',
        'complemento',
        'contatos',
        'observacoes',
        'ativo',
        // Campos CRM
        'papeis',
        'status',
        'origem',
        'classificacao',
        'interesses',
        'preferencias',
        'renda_mensal',
        'profissao',
        'empresa',
        'documentos',
        'fotos',
        'corretor_responsavel_id',
        'indicado_por_id',
        'score',
        'ultimo_atendimento',
        'ultimo_contato',
        'total_atendimentos',
        'total_imoveis_visitados',
        'tags',
        'facebook',
        'instagram',
        'linkedin',
        'whatsapp',
    ];

    protected $casts = [
        'contatos' => 'array',
        'data_expedicao' => 'date',
        'data_nascimento' => 'date',
        'ativo' => 'boolean',
        'papeis' => 'array',
        'interesses' => 'array',
        'preferencias' => 'array',
        'documentos' => 'array',
        'fotos' => 'array',
        'tags' => 'array',
        'renda_mensal' => 'decimal:2',
        'ultimo_atendimento' => 'datetime',
        'ultimo_contato' => 'datetime',
        'score' => 'integer',
        'total_atendimentos' => 'integer',
        'total_imoveis_visitados' => 'integer',
    ];

    /**
     * Corretor responsável
     */
    public function corretorResponsavel()
    {
        return $this->belongsTo(User::class, 'corretor_responsavel_id');
    }

    /**
     * Pessoa que indicou
     */
    public function indicadoPor()
    {
        return $this->belongsTo(Pessoa::class, 'indicado_por_id');
    }

    /**
     * Pessoas que esta pessoa indicou
     */
    public function indicacoes()
    {
        return $this->hasMany(Pessoa::class, 'indicado_por_id');
    }

    /**
     * Interações/Timeline
     */
    public function interacoes()
    {
        return $this->hasMany(PessoaInteracao::class)->orderBy('data_interacao', 'desc');
    }

    /**
     * Documentos anexados
     */
    public function documentosAnexados()
    {
        return $this->hasMany(PessoaDocumento::class);
    }

    /**
     * Relacionamentos com outras pessoas
     */
    public function relacionamentos()
    {
        return $this->hasMany(PessoaRelacionamento::class, 'pessoa_origem_id');
    }

    /**
     * Verifica se pessoa tem determinado papel
     */
    public function temPapel($papel)
    {
        return in_array($papel, $this->papeis ?? []);
    }

    /**
     * Adiciona papel à pessoa
     */
    public function adicionarPapel($papel)
    {
        $papeis = $this->papeis ?? [];
        if (!in_array($papel, $papeis)) {
            $papeis[] = $papel;
            $this->papeis = $papeis;
            $this->save();
        }
    }

    /**
     * Remove papel da pessoa
     */
    public function removerPapel($papel)
    {
        $papeis = $this->papeis ?? [];
        $papeis = array_filter($papeis, fn($p) => $p !== $papel);
        $this->papeis = array_values($papeis);
        $this->save();
    }

    /**
     * Registra interação
     */
    public function registrarInteracao($tipo, $descricao, $assunto = null, $resultado = null, $userId = null)
    {
        $interacao = $this->interacoes()->create([
            'tenant_id' => $this->tenant_id,
            'user_id' => $userId,
            'tipo' => $tipo,
            'assunto' => $assunto,
            'descricao' => $descricao,
            'resultado' => $resultado,
            'data_interacao' => now(),
        ]);

        // Atualizar contadores
        $this->increment('total_atendimentos');
        $this->update(['ultimo_atendimento' => now()]);

        return $interacao;
    }

    /**
     * Atualizar score baseado em atividades
     */
    public function atualizarScore()
    {
        $score = 0;
        
        // Score baseado em atendimentos
        $score += $this->total_atendimentos * 5;
        
        // Score baseado em imóveis visitados
        $score += $this->total_imoveis_visitados * 10;
        
        // Score baseado em recência do último contato
        if ($this->ultimo_contato) {
            $diasSemContato = now()->diffInDays($this->ultimo_contato);
            if ($diasSemContato < 7) $score += 20;
            elseif ($diasSemContato < 30) $score += 10;
        }
        
        // Score baseado em classificação
        $classificacaoScores = ['A' => 50, 'B' => 30, 'C' => 15, 'D' => 5];
        if (isset($classificacaoScores[$this->classificacao])) {
            $score += $classificacaoScores[$this->classificacao];
        }
        
        $this->score = $score;
        $this->save();
        
        return $score;
    }
}
