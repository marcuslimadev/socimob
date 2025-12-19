<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'nome',
        'email',
        'telefone',
        'whatsapp',
        'whatsapp_name',
        'status',
        'observacoes',
        'user_id',
        'corretor_id',
        'budget_min',
        'budget_max',
        'localizacao',
        'quartos',
        'suites',
        'garagem',
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
        'caracteristicas_desejadas',
        'state',
        'primeira_interacao',
        'ultima_interacao',
        'diagnostico_ia',
        'diagnostico_status',
        'diagnostico_gerado_em',
    ];

    protected $casts = [
        'budget_min' => 'float',
        'budget_max' => 'float',
        'primeira_interacao' => 'datetime',
        'ultima_interacao' => 'datetime',
        'diagnostico_gerado_em' => 'datetime',
    ];

    public function corretor()
    {
        return $this->belongsTo(User::class, 'corretor_id');
    }

    public function conversas()
    {
        return $this->hasMany(Conversa::class);
    }

    public function documents()
    {
        return $this->hasMany(LeadDocument::class);
    }

    public function propertyMatches()
    {
        return $this->hasMany(LeadPropertyMatch::class);
    }
}
