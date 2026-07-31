<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Property extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes;

    protected $table = 'imo_properties';

    protected $fillable = [
        'tenant_id',
        'codigo',
        'codigo_imovel',
        'referencia_imovel',
        'titulo',
        'descricao',
        'descricao_resumida',
        'local_chaves',
        'status_chaves',
        'visibilidade_endereco',
        'active',
        'exibir_imovel',
        'destaque',
        'external_id',
        'finalidade_imovel',
        'tipo_imovel',
        'valor_venda',        // Mudado de 'preco'
        'valor_aluguel',
        'logradouro',         // Mudado de 'endereco'
        'cidade',
        'estado',
        'bairro',            // Adicionado
        'numero',
        'complemento',
        'cep',
        'area_total',
        'area_privativa',
        'area_terreno',
        'ano_construcao',
        'dormitorios',        // Mudado de 'quartos'
        'suites',
        'banheiros',
        'garagem',            // Mudado de 'vagas'
        'imagens',            // Mudado de 'fotos'
        'imagem_destaque',
        'caracteristicas',
        'classificacoes',
        'vantagens',
        'lazer',
        'proximidades',
        'valor_condominio',
        'valor_iptu',
        'renda_sugerida_compra',
        'em_condominio',
        'nome_condominio',
        'exclusividade',
        'latitude',
        'longitude',
        'last_sync',
        // Campos Imobi Brasil
        'imobi_brasil_sent',
        'imobi_brasil_sent_at',
        'imobi_brasil_external_id',
        'imobi_brasil_error',
        'imobi_brasil_images_sent_at',
        // Quem inseriu (interno, não exibir ao portal)
        'inserido_por_user_id',
        'inserido_por_nome',
        // Captador do imóvel (interno)
        'captador_user_id',
        'captador_nome',
        'construtora_pessoa_id',
        // Dados do proprietário (interno, não exibir ao portal)
        'proprietario_nome',
        'proprietario_telefone',
        'proprietario_email',
        'proprietario_observacoes',
        'trash_source',
        'trash_reason',
        'trashed_by_user_id',
        'trash_metadata',
    ];

    protected $casts = [
        'active' => 'boolean',
        'exibir_imovel' => 'boolean',
        'destaque' => 'boolean',
        'valor_venda' => 'float',
        'valor_aluguel' => 'float',
        'valor_condominio' => 'float',
        'valor_iptu' => 'float',
        'renda_sugerida_compra' => 'float',
        'area_total' => 'float',
        'area_privativa' => 'float',
        'area_terreno' => 'float',
        'ano_construcao' => 'integer',
        'dormitorios' => 'integer',
        'suites' => 'integer',
        'banheiros' => 'integer',
        'garagem' => 'integer',
        'em_condominio' => 'boolean',
        'exclusividade' => 'boolean',
        'imagens' => 'array',      // Mudado de 'fotos'
        'vantagens' => 'array',
        'lazer' => 'array',
        'proximidades' => 'array',
        'latitude' => 'float',
        'longitude' => 'float',
        'construtora_pessoa_id' => 'integer',
        'last_sync' => 'datetime',
        'deleted_at' => 'datetime',
        'trash_metadata' => 'array',
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

    public function setValorVendaAttribute($value): void
    {
        $this->attributes['valor_venda'] = ($value === null || $value === '') ? 0 : $value;
    }

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

    public function portalTenants()
    {
        return $this->hasMany(PropertyPortalTenant::class, 'property_id');
    }

    public function documentos()
    {
        return $this->hasMany(PropertyDocument::class, 'property_id');
    }

    public function construtora()
    {
        return $this->belongsTo(Pessoa::class, 'construtora_pessoa_id');
    }

    public function scopePortalInventory($query)
    {
        return $query->where(function ($builder) {
            $builder->whereNotNull('external_id')
                ->orWhereNotNull('imobi_brasil_external_id')
                ->orWhereNotNull('codigo')
                ->orWhereNotNull('codigo_imovel');
        });
    }

    public function scopePubliclyVisible($query)
    {
        return $query->where('active', true)->where('exibir_imovel', true);
    }
}
