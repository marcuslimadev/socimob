<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vistoria extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $table = 'vistorias';

    protected $fillable = [
        'tenant_id',
        'contrato_id',
        'responsavel_pessoa_id',
        'codigo',
        'status',
        'cliente_nome',
        'imovel_id',
        'imovel_livre',
        'tipo',
        'vistoriadores',
        'pessoas',
        'participantes_ids',
        'metragem',
        'mobiliado',
        'data_vistoria',
        'observacoes',
        'comodos',
        'assinatura_inquilino_status',
        'assinatura_proprietario_status',
        'data_agendada',
        'data_inicio',
        'data_fim',
        'vistoriador_id',
        'observacoes_gerais',
        'introducao_texto',
        'criterios_avaliacao_json',
        'criterios_pintura_json',
        'criterios_limpeza_json',
        'prazo_contestacao_dias',
        'data_limite_contestacao',
        'link_publico_midias_token',
        'link_contestacao_token',
        'pdf_path',
        'hash_pdf',
        'criado_por',
        'atualizado_por',
    ];

    protected $casts = [
        'vistoriadores' => 'array',
        'pessoas' => 'array',
        'participantes_ids' => 'array',
        'imovel_livre' => 'array',
        'comodos' => 'array',
        'metragem' => 'decimal:2',
        'mobiliado' => 'boolean',
        'data_vistoria' => 'datetime',
        'data_agendada' => 'datetime',
        'data_inicio' => 'datetime',
        'data_fim' => 'datetime',
        'criterios_avaliacao_json' => 'array',
        'criterios_pintura_json' => 'array',
        'criterios_limpeza_json' => 'array',
        'data_limite_contestacao' => 'datetime',
    ];

    public function fotos()
    {
        return $this->hasMany(VistoriaFoto::class, 'vistoria_id')->orderBy('comodo')->orderBy('ordem');
    }

    public function comentarios()
    {
        return $this->hasMany(VistoriaComentario::class, 'vistoria_id')->orderByDesc('created_at');
    }

    public function partes()
    {
        return $this->hasMany(VistoriaParte::class, 'vistoria_id')->orderBy('ordem_assinatura')->orderBy('id');
    }

    public function ambientes()
    {
        return $this->hasMany(VistoriaAmbiente::class, 'vistoria_id')->orderBy('ordem')->orderBy('id');
    }

    public function itens()
    {
        return $this->hasManyThrough(VistoriaItem::class, VistoriaAmbiente::class, 'vistoria_id', 'vistoria_ambiente_id');
    }

    public function inconformidades()
    {
        return $this->hasMany(VistoriaInconformidade::class, 'vistoria_id')->orderByDesc('id');
    }

    public function midias()
    {
        return $this->hasMany(VistoriaMidia::class, 'vistoria_id')->orderBy('ambiente_id')->orderBy('ordem')->orderBy('id');
    }

    public function chaves()
    {
        return $this->hasMany(VistoriaChave::class, 'vistoria_id')->orderBy('tipo');
    }

    public function medidores()
    {
        return $this->hasMany(VistoriaMedidor::class, 'vistoria_id')->orderBy('tipo')->orderBy('id');
    }

    public function contestacoes()
    {
        return $this->hasMany(VistoriaContestacao::class, 'vistoria_id')->orderByDesc('created_at');
    }

    public function historicos()
    {
        return $this->hasMany(VistoriaHistorico::class, 'vistoria_id')->orderByDesc('created_at');
    }

    public function contrato()
    {
        return $this->belongsTo(ContratoLocacao::class, 'contrato_id');
    }

    public function imovel()
    {
        return $this->belongsTo(Property::class, 'imovel_id');
    }

    public function responsavel()
    {
        return $this->belongsTo(Pessoa::class, 'responsavel_pessoa_id');
    }
}
