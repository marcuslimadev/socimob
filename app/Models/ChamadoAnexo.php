<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ChamadoAnexo extends Model
{
    use HasFactory, BelongsToTenant, SoftDeletes;

    protected $table = 'chamado_anexos';

    protected $fillable = [
        'tenant_id',
        'chamado_id',
        'mensagem_id',
        'nome_arquivo',
        'mime_type',
        'tamanho_bytes',
        'caminho_arquivo',
    ];

    public function chamado()
    {
        return $this->belongsTo(ChamadoOperacional::class, 'chamado_id');
    }

    public function mensagem()
    {
        return $this->belongsTo(ChamadoMensagem::class, 'mensagem_id');
    }
}
