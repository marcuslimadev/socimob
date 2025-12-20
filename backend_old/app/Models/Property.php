<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Property extends Model
{
    protected $table = 'imo_properties';
    
    protected $fillable = [
        'codigo_imovel',
        'referencia_imovel',
        'finalidade_imovel',
        'tipo_imovel',
        'descricao',
        'dormitorios',
        'suites',
        'banheiros',
        'garagem',
        'valor_venda',
        'valor_aluguel',
        'valor_iptu',
        'valor_condominio',
        'cidade',
        'estado',
        'bairro',
        'logradouro',
        'numero',
        'complemento',
        'cep',
        'area_privativa',
        'area_total',
        'area_terreno',
        'imagem_destaque',
        'imagens',
        'caracteristicas',
        'latitude',
        'longitude',
        'em_condominio',
        'exclusividade',
        'exibir_imovel',
        'active',
        'api_data',
        'api_created_at',
        'api_updated_at'
    ];
    
    protected $casts = [
        'dormitorios' => 'integer',
        'suites' => 'integer',
        'banheiros' => 'integer',
        'garagem' => 'integer',
        'valor_venda' => 'decimal:2',
        'valor_aluguel' => 'decimal:2',
        'valor_iptu' => 'decimal:2',
        'valor_condominio' => 'decimal:2',
        'area_privativa' => 'decimal:2',
        'area_total' => 'decimal:2',
        'area_terreno' => 'decimal:2',
        'imagens' => 'array',
        'caracteristicas' => 'array',
        'api_data' => 'array',
        'em_condominio' => 'boolean',
        'exclusividade' => 'boolean',
        'exibir_imovel' => 'boolean',
        'active' => 'boolean',
        'api_created_at' => 'datetime',
        'api_updated_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];
    
    // Accessor para normalizar imagens
    public function getImagensAttribute($value)
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (!is_array($decoded)) {
                return [];
            }
            $value = $decoded;
        }
        
        if (!is_array($value)) {
            return [];
        }
        
        // Normalizar: se são strings, converter para objetos {url, destaque}
        return array_map(function($img) {
            if (is_string($img)) {
                return ['url' => $img, 'destaque' => false];
            }
            if (is_array($img) && !isset($img['url'])) {
                return ['url' => '', 'destaque' => false];
            }
            return $img;
        }, $value);
    }
    
    // Relacionamento
    public function leadMatches()
    {
        return $this->hasMany(LeadPropertyMatch::class, 'property_id');
    }
}
