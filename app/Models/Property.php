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
        'latitude',
        'longitude',
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
        'latitude' => 'float',
        'longitude' => 'float',
        'last_sync' => 'datetime',
    ];

    // Acessores para compatibilidade com o frontend
    protected $appends = [
        'type',
        'transaction_type',
        'price',
        'address',
        'neighborhood',
        'city',
        'state',
        'bedrooms',
        'bathrooms',
        'parking_spaces',
        'images',
        'photos',
        'area'
    ];

    public function getTypeAttribute()
    {
        return $this->tipo_imovel;
    }

    public function getTransactionTypeAttribute()
    {
        return $this->finalidade_imovel;
    }

    public function getPriceAttribute()
    {
        return $this->valor_venda;
    }

    public function getAddressAttribute()
    {
        return $this->logradouro;
    }

    public function getNeighborhoodAttribute()
    {
        return $this->bairro;
    }

    public function getCityAttribute()
    {
        return $this->cidade;
    }

    public function getStateAttribute()
    {
        return $this->estado;
    }

    public function getBedroomsAttribute()
    {
        return $this->dormitorios;
    }

    public function getBathroomsAttribute()
    {
        return $this->banheiros;
    }

    public function getParkingSpacesAttribute()
    {
        return $this->garagem;
    }

    public function getImagesAttribute()
    {
        return $this->imagens;
    }

    public function getPhotosAttribute()
    {
        return $this->imagens;
    }

    public function getAreaAttribute()
    {
        return $this->area_total;
    }

    public function matches()
    {
        return $this->hasMany(LeadPropertyMatch::class, 'property_id');
    }

    public function fotos()
    {
        return $this->hasMany(ImovelImagem::class, 'codigo', 'codigo');
    }
}
