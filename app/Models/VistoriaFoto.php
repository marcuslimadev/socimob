<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

class VistoriaFoto extends Model
{
    use BelongsToTenant;

    protected $table = 'vistoria_fotos';

    protected $fillable = [
        'tenant_id',
        'vistoria_id',
        'comodo',
        'arquivo_path',
        'url',
        'mime_type',
        'tamanho_bytes',
        'legenda',
        'destaque',
        'ordem',
        'enviado_por_user_id',
        'enviado_por_pessoa_id',
    ];

    protected $casts = [
        'destaque' => 'boolean',
        'ordem' => 'integer',
        'tamanho_bytes' => 'integer',
    ];

    protected $appends = ['url_signed'];

    public function getUrlSignedAttribute(): string
    {
        try {
            if (config('filesystems.default') === 's3') {
                return Storage::temporaryUrl($this->arquivo_path, now()->addHours(2));
            }

            /** @var FilesystemAdapter $disk */
            $disk = Storage::disk('public');
            return $disk->url($this->arquivo_path);
        } catch (\Exception $e) {
            return $this->url ?? '';
        }
    }

    public function vistoria()
    {
        return $this->belongsTo(Vistoria::class, 'vistoria_id');
    }
}
