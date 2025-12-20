<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'imo_properties';

    protected $fillable = [
        'tenant_id',
        'codigo',
        'titulo',
        'descricao',
        'active',
        'exibir_imovel',
        'external_id',
        'finalidade_imovel',
        'tipo_imovel',
        'preco',
        'endereco',
        'cidade',
        'estado',
        'area_total',
        'quartos',
        'banheiros',
        'vagas',
        'fotos',
        'url_ficha',
        'last_sync',
    ];

    protected $casts = [
        'active' => 'boolean',
        'exibir_imovel' => 'boolean',
        'preco' => 'float',
        'area_total' => 'float',
        'quartos' => 'integer',
        'banheiros' => 'integer',
        'vagas' => 'integer',
        'fotos' => 'array',
        'last_sync' => 'datetime',
    ];

    public function matches()
    {
        return $this->hasMany(LeadPropertyMatch::class, 'property_id');
    }
}
