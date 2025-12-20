<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Atividade extends Model
{
    use HasFactory, BelongsToTenant;

    protected $table = 'atividades';

    protected $fillable = [
        'tenant_id',
        'lead_id',
        'tipo',
        'descricao',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }
}
