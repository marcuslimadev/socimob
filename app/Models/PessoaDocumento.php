<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PessoaDocumento extends Model
{
    use SoftDeletes, BelongsToTenant;

    protected $table = 'pessoa_documentos';

    protected $fillable = [
        'tenant_id',
        'pessoa_id',
        'tipo',
        'nome',
        'arquivo',
        'tamanho',
        'mime_type',
        'observacoes',
        'uploaded_by',
        'data_validade',
        'verificado',
        'verificado_em',
        'verificado_por',
    ];

    protected $casts = [
        'tamanho' => 'integer',
        'data_validade' => 'datetime',
        'verificado' => 'boolean',
        'verificado_em' => 'datetime',
    ];

    public function pessoa()
    {
        return $this->belongsTo(Pessoa::class);
    }

    public function uploadedBy()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function verificadoPor()
    {
        return $this->belongsTo(User::class, 'verificado_por');
    }

    /**
     * Retorna URL completa do arquivo
     */
    public function getUrlAttribute()
    {
        return url('storage/' . $this->arquivo);
    }

    /**
     * Formata tamanho do arquivo
     */
    public function getTamanhoFormatadoAttribute()
    {
        if (!$this->tamanho) return '-';
        
        $bytes = $this->tamanho;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }
}
