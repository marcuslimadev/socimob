<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class VistoriaMidia extends Model
{
    protected $table = 'vistoria_midias';

    protected $fillable = [
        'vistoria_id', 'ambiente_id', 'item_id', 'inconformidade_id', 'chave_id', 'medidor_id', 'tipo',
        'path_original', 'path_thumb', 'mime_type', 'tamanho_bytes',
        'duracao_segundos', 'legenda', 'ordem', 'metadata_json',
    ];

    protected $casts = [
        'metadata_json' => 'array',
        'tamanho_bytes' => 'integer',
        'duracao_segundos' => 'integer',
        'ordem' => 'integer',
    ];

    protected $appends = ['url', 'thumb_url'];

    public function getUrlAttribute(): ?string
    {
        return $this->path_original ? Storage::disk('public')->url($this->path_original) : null;
    }

    public function getThumbUrlAttribute(): ?string
    {
        return $this->path_thumb ? Storage::disk('public')->url($this->path_thumb) : null;
    }
}
