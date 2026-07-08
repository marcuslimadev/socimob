<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int|null $tenant_id
 * @property string|null $nome
 * @property string|null $email
 * @property string|null $telefone
 * @property string|null $whatsapp
 * @property string|null $whatsapp_name
 * @property string|null $status
 * @property string|null $classificacao
 * @property string|null $observacoes
 * @property \Carbon\Carbon|null $atendimento_notificado_em
 * @property string|null $atendimento_notificado_para
 * @property int|null $user_id
 * @property int|null $pessoa_id
 * @property int|null $corretor_id
 * @property float|null $budget_min
 * @property float|null $budget_max
 * @property string|null $localizacao
 * @property int|null $quartos
 * @property int|null $suites
 * @property int|null $garagem
 * @property string|null $cpf
 * @property string|null $state
 * @property \Carbon\Carbon|null $criado_at
 * @property \Carbon\Carbon|null $updated_at
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
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
        'classificacao',
        'observacoes',
        'user_id',
        'pessoa_id',
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
        'chaves_na_mao_status',
        'chaves_na_mao_sent_at',
        'chaves_na_mao_response',
        'chaves_na_mao_error',
        'chaves_na_mao_retries',
        'sms_enviado',
        'sms_enviado_em',
        'atendimento_notificado_em',
        'atendimento_notificado_para',
    ];

    protected $casts = [
        'budget_min' => 'float',
        'budget_max' => 'float',
        'primeira_interacao' => 'datetime',
        'ultima_interacao' => 'datetime',
        'diagnostico_gerado_em' => 'datetime',
        'chaves_na_mao_sent_at' => 'datetime',
        'sms_enviado' => 'boolean',
        'sms_enviado_em' => 'datetime',
        'atendimento_notificado_em' => 'datetime',
    ];

    public function corretor()
    {
        return $this->belongsTo(User::class, 'corretor_id');
    }

    public function pessoa()
    {
        return $this->belongsTo(Pessoa::class, 'pessoa_id');
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

    public function isFromIntegration(): bool
    {
        $observacoes = mb_strtolower($this->observacoes ?? '');

        if ($observacoes === '') {
            return false;
        }

        $hasOrigem = str_contains($observacoes, 'origem:');
        $hasChaves = str_contains($observacoes, 'chaves na mão') || str_contains($observacoes, 'chaves na mao');
        $hasIntegracao = str_contains($observacoes, 'integração') || str_contains($observacoes, 'integracao');

        return ($hasOrigem && ($hasChaves || $hasIntegracao)) || $hasChaves;
    }
}
