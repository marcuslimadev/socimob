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
        'codigo_imovel',
        'referencia_imovel',
        'finalidade_imovel',
        'tipo_imovel',
        'dormitorios',
        'suites',
        'banheiros',
        'garagem',
        'valor_venda',
        'valor_iptu',
        'valor_condominio',
        'cidade',
        'estado',
        'bairro',
        'logradouro',
        'numero',
        'complemento',
        'cep',
        'latitude',
        'longitude',
        'area_privativa',
        'area_total',
        'area_terreno',
        'imagem_destaque',
        'imagens',
        'caracteristicas',
        'em_condominio',
        'exclusividade',
        'api_data',
        'api_created_at',
        'api_updated_at',
        'last_sync',
    ];

    protected $casts = [
        'active' => 'boolean',
        'exibir_imovel' => 'boolean',
        'em_condominio' => 'boolean',
        'exclusividade' => 'boolean',
        'valor_venda' => 'float',
        'valor_iptu' => 'float',
        'valor_condominio' => 'float',
        'latitude' => 'float',
        'longitude' => 'float',
        'area_privativa' => 'float',
        'area_total' => 'float',
        'area_terreno' => 'float',
        'api_data' => 'array',
        'api_created_at' => 'datetime',
        'api_updated_at' => 'datetime',
        'last_sync' => 'datetime',
    ];

    public function matches()
    {
        return $this->hasMany(LeadPropertyMatch::class, 'property_id');
    }
}
