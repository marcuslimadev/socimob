<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class VistoriaContestacaoMidia extends Model
{
    protected $table = 'vistoria_contestacao_midias';

    protected $fillable = ['contestacao_id', 'contestacao_item_id', 'tipo', 'path', 'mime_type', 'tamanho_bytes', 'legenda'];

    protected $casts = ['tamanho_bytes' => 'integer'];

    protected $appends = ['url'];

    public function getUrlAttribute(): ?string
    {
        return $this->path ? Storage::disk('public')->url($this->path) : null;
    }
}
