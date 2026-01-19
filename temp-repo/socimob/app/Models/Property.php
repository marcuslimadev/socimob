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
        'valor_venda',        // Mudado de 'preco'
        'logradouro',         // Mudado de 'endereco'
        'cidade',
        'estado',
        'bairro',            // Adicionado
        'area_total',
        'dormitorios',        // Mudado de 'quartos'
        'banheiros',
        'garagem',            // Mudado de 'vagas'
        'imagens',            // Mudado de 'fotos'
        'last_sync',
    ];

    protected $casts = [
        'active' => 'boolean',
        'exibir_imovel' => 'boolean',
        'valor_venda' => 'float',
        'area_total' => 'float',
        'dormitorios' => 'integer',
        'banheiros' => 'integer',
        'garagem' => 'integer',
        'imagens' => 'array',      // Mudado de 'fotos'
        'last_sync' => 'datetime',
    ];

    public function matches()
    {
        return $this->hasMany(LeadPropertyMatch::class, 'property_id');
    }
}
