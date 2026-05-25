<?php

namespace App\Services;

use App\Models\Vistoria;
use App\Models\VistoriaMidia;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VistoriaMidiaService
{
    private const MIME_TIPOS = [
        'image/jpeg' => 'foto',
        'image/png' => 'foto',
        'image/webp' => 'foto',
        'image/heic' => 'foto',
        'image/heif' => 'foto',
        'video/mp4' => 'video',
        'video/quicktime' => 'video',
        'video/webm' => 'video',
        'audio/mpeg' => 'audio',
        'audio/mp4' => 'audio',
        'audio/ogg' => 'audio',
        'application/pdf' => 'documento',
    ];

    public function upload(Vistoria $vistoria, UploadedFile $file, array $data, ?Request $request = null): VistoriaMidia
    {
        $mime = $file->getMimeType() ?: $file->getClientMimeType();
        if (!isset(self::MIME_TIPOS[$mime])) {
            abort(422, 'Tipo de arquivo não permitido para vistoria.');
        }

        $maxKb = (int) config('vistoria.upload_max_kb', env('VISTORIA_UPLOAD_MAX_KB', 102400));
        if (($file->getSize() / 1024) > $maxKb) {
            abort(422, 'Arquivo acima do limite permitido.');
        }

        $tipo = self::MIME_TIPOS[$mime];
        $ext = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin');
        $basePath = "tenants/{$vistoria->tenant_id}/vistorias/{$vistoria->id}/midias";
        $filename = Str::uuid() . '.' . preg_replace('/[^a-z0-9]+/i', '', $ext);
        $path = Storage::disk('public')->putFileAs($basePath, $file, $filename);

        $midia = VistoriaMidia::create([
            'vistoria_id' => $vistoria->id,
            'ambiente_id' => $data['ambiente_id'] ?? null,
            'item_id' => $data['item_id'] ?? null,
            'inconformidade_id' => $data['inconformidade_id'] ?? null,
            'chave_id' => $data['chave_id'] ?? null,
            'medidor_id' => $data['medidor_id'] ?? null,
            'tipo' => $tipo,
            'path_original' => $path,
            'path_thumb' => null,
            'mime_type' => $mime,
            'tamanho_bytes' => $file->getSize(),
            'legenda' => $data['legenda'] ?? $data['descricao'] ?? null,
            'ordem' => (int) ($data['ordem'] ?? 0),
            'metadata_json' => [
                'original_name' => Str::limit($file->getClientOriginalName(), 160, ''),
                'ip' => $request?->ip(),
            ],
        ]);

        app(VistoriaService::class)->registrarHistorico($vistoria, 'midia_adicionada', 'Mídia adicionada à vistoria.', $request);

        return $midia;
    }

    public function excluir(Vistoria $vistoria, VistoriaMidia $midia, ?Request $request = null): void
    {
        Storage::disk('public')->delete(array_filter([$midia->path_original, $midia->path_thumb]));
        $midia->delete();

        app(VistoriaService::class)->registrarHistorico($vistoria, 'midia_excluida', 'Mídia removida da vistoria.', $request);
    }
}
