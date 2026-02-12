<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PropertyEvaluation extends Model
{
    protected $table = 'property_evaluations';

    protected $fillable = [
        'tenant_id',
        'nome',
        'telefone',
        'email',
        'tipo_imovel',
        'endereco',
        'bairro',
        'cidade',
        'observacoes',
        'status',
        'lead_id',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }
}
