<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;

class PropertyDocument extends Model
{
    use BelongsToTenant;

    protected $table = 'property_documents';

    protected $fillable = [
        'tenant_id',
        'property_id',
        'tipo',
        'nome',
        'arquivo_path',
        'mime_type',
        'tamanho_bytes',
        'uploaded_by_user_id',
    ];

    protected $casts = [
        'tamanho_bytes' => 'integer',
    ];

    protected $appends = ['url_documento'];

    public function getUrlDocumentoAttribute(): ?string
    {
        if (!$this->arquivo_path) {
            return null;
        }

        try {
            if (config('filesystems.default') === 's3') {
                return Storage::temporaryUrl($this->arquivo_path, now()->addHours(2));
            }

            /** @var FilesystemAdapter $disk */
            $disk = Storage::disk('public');

            return $disk->url($this->arquivo_path);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }
}