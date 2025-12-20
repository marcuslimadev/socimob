<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    protected $table = 'leads';
    
    protected $fillable = [
        'telefone',
        'nome',
        'email',
        'cpf',
        'whatsapp_name',
        'profile_pic_url',
        'budget_min',
        'budget_max',
        'renda_mensal',
        'localizacao',
        'city',
        'state',
        'country',
        'latitude',
        'longitude',
        'quartos',
        'suites',
        'garagem',
        'caracteristicas_desejadas',
        'corretor_id',
        'status',
        'origem',
        'score',
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
        'primeira_interacao',
        'ultima_interacao'
    ];

    protected $casts = [
        'budget_min' => 'decimal:2',
        'budget_max' => 'decimal:2',
        'renda_mensal' => 'decimal:2',
        'quartos' => 'integer',
        'suites' => 'integer',
        'garagem' => 'integer',
        'score' => 'integer',
        'primeira_interacao' => 'datetime',
        'ultima_interacao' => 'datetime',
        'diagnostico_gerado_em' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    protected $appends = [
        'cadastro_percentual',
        'campos_pendentes',
    ];

    private const CADASTRO_FIELDS = [
        'nome' => 'Nome',
        'telefone' => 'Telefone',
        'email' => 'Email',
        'cpf' => 'CPF',
        'budget_min' => 'Orçamento mínimo',
        'budget_max' => 'Orçamento máximo',
        'renda_mensal' => 'Renda mensal',
        'estado_civil' => 'Estado civil',
        'composicao_familiar' => 'Composição familiar',
        'profissao' => 'Profissão',
        'fonte_renda' => 'Fonte de renda',
        'localizacao' => 'Localização desejada',
        'quartos' => 'Qtd. de quartos',
        'objetivo_compra' => 'Objetivo da compra',
        'preferencia_tipo_imovel' => 'Tipo de imóvel',
        'preferencia_bairro' => 'Bairro preferido',
    ];
    
    // Relacionamentos
    public function corretor()
    {
        return $this->belongsTo(User::class, 'corretor_id');
    }
    
    public function conversas()
    {
        return $this->hasMany(Conversa::class, 'lead_id');
    }
    
    public function propertyMatches()
    {
        return $this->hasMany(LeadPropertyMatch::class, 'lead_id');
    }
    
    public function atividades()
    {
        return $this->hasMany(Atividade::class, 'lead_id');
    }

    public function documents()
    {
        return $this->hasMany(LeadDocument::class, 'lead_id')->orderByDesc('created_at');
    }

    public function getCadastroPercentualAttribute(): int
    {
        $campos = array_keys(self::CADASTRO_FIELDS);
        $total = count($campos);

        if ($total === 0) {
            return 0;
        }

        $preenchidos = 0;

        foreach ($campos as $campo) {
            $valor = $this->{$campo} ?? null;
            if (!empty($valor)) {
                $preenchidos++;
            }
        }

        return (int) round(($preenchidos / $total) * 100);
    }

    public function getCamposPendentesAttribute(): array
    {
        $pendentes = [];

        foreach (self::CADASTRO_FIELDS as $campo => $label) {
            if (empty($this->{$campo})) {
                $pendentes[] = $label;
            }
        }

        return $pendentes;
    }
}
