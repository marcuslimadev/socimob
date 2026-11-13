<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Miniaturas sob demanda das fotos de imóveis enviadas pelo CRM.
 *
 * GET /api/img-thumb?u=<url ou caminho da foto>&w=<largura>
 * Gera uma vez (GD), guarda em storage/app/thumbs e serve com cache longo do navegador.
 * Se a foto não for local ou não puder ser reduzida, responde 404 e o front usa o original.
 */
class ImageThumbnailController extends Controller
{
    private const ALLOWED_WIDTHS = [160, 320, 640];
    private const QUALITY = 78;

    /** Pastas públicas (relativas à raiz web) de onde as fotos podem sair. */
    private const SOURCE_PREFIXES = ['uploads/properties/', 'storage/uploads/properties/'];

    public function show(Request $request)
    {
        $source = (string) $request->query('u', '');
        $width = $this->normalizeWidth((int) $request->query('w', 320));

        $original = $this->resolveLocalFile($source);
        if (!$original) {
            return $this->fallback();
        }

        $thumbPath = $this->thumbPath($original, $width);

        if (!is_file($thumbPath) && !$this->generate($original, $thumbPath, $width)) {
            return $this->fallback();
        }

        return response()->file($thumbPath, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    private function normalizeWidth(int $width): int
    {
        foreach (self::ALLOWED_WIDTHS as $allowed) {
            if ($width <= $allowed) {
                return $allowed;
            }
        }

        return end(self::ALLOWED_WIDTHS);
    }

    /** Converte a URL/caminho recebido em arquivo local dentro das pastas permitidas. */
    private function resolveLocalFile(string $source): ?string
    {
        $path = parse_url($source, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            return null;
        }

        $relative = ltrim(rawurldecode($path), '/');
        if (str_contains($relative, '..')) {
            return null;
        }

        $extension = strtolower(pathinfo($relative, PATHINFO_EXTENSION));
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return null;
        }

        foreach (self::SOURCE_PREFIXES as $prefix) {
            if (!str_starts_with($relative, $prefix)) {
                continue;
            }

            $candidates = [
                public_path($relative),
                storage_path('app/public/' . substr($relative, strlen('storage/'))),
            ];

            foreach ($candidates as $candidate) {
                $real = realpath($candidate);
                if ($real && is_file($real)) {
                    return $real;
                }
            }
        }

        return null;
    }

    private function thumbPath(string $original, int $width): string
    {
        $dir = storage_path('app/thumbs');
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        return $dir . '/' . md5($original . '|' . filemtime($original)) . "_{$width}.jpg";
    }

    private function generate(string $original, string $target, int $width): bool
    {
        if (!extension_loaded('gd')) {
            return false;
        }

        try {
            $info = @getimagesize($original);
            if (!$info) {
                return false;
            }

            [$srcW, $srcH, $type] = $info;
            $image = match ($type) {
                IMAGETYPE_JPEG => @imagecreatefromjpeg($original),
                IMAGETYPE_PNG => @imagecreatefrompng($original),
                IMAGETYPE_WEBP => @imagecreatefromwebp($original),
                default => false,
            };
            if (!$image) {
                return false;
            }

            $newW = min($width, $srcW);
            $newH = (int) round($srcH * ($newW / $srcW));

            $canvas = imagecreatetruecolor($newW, $newH);
            imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
            imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);

            // Grava em arquivo temporário e renomeia: evita servir miniatura pela metade.
            $tmp = $target . '.' . uniqid('', true) . '.tmp';
            $saved = imagejpeg($canvas, $tmp, self::QUALITY);
            imagedestroy($image);
            imagedestroy($canvas);

            return $saved && @rename($tmp, $target);
        } catch (\Throwable $e) {
            Log::warning('[ImageThumbnail] Falha ao gerar miniatura', [
                'file' => $original,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /** 404 simples: o front cai para a foto original no onError (sem redirecionar para URL externa). */
    private function fallback()
    {
        return response('', 404);
    }
}
