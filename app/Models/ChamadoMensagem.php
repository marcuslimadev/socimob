<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChamadoMensagem extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes;

    protected $table = 'chamado_mensagens';

    protected $fillable = [
        'tenant_id',
        'chamado_id',
        'autor_user_id',
        'autor_pessoa_id',
        'interna',
        'mensagem',
    ];

    protected $casts = [
        'interna' => 'boolean',
    ];

    public function chamado()
    {
        return $this->belongsTo(ChamadoOperacional::class, 'chamado_id');
    }
}
