<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Applies a semi-transparent watermark image over property photos using GD.
 */
class WatermarkService
{
    /**
     * Apply the tenant watermark to a local image file in-place.
     *
     * @param  string  $imagePath      Absolute path to the property image file.
     * @param  string  $watermarkUrl   Public URL or absolute path of the watermark PNG.
     * @param  float   $opacity        Watermark opacity 0.0 – 1.0 (default 0.35).
     * @param  float   $scale          Watermark width relative to image width (default 0.25 = 25%).
     * @param  string  $position       Corner position: 'bottom-right' | 'bottom-left' | 'bottom-center' | 'center'.
     * @return bool
     */
    public static function apply(
        string $imagePath,
        string $watermarkUrl,
        float  $opacity   = 0.35,
        float  $scale     = 0.25,
        string $position  = 'bottom-right'
    ): bool {
        try {
            if (!extension_loaded('gd')) {
                Log::warning('WatermarkService: extensão GD não disponível');
                return false;
            }

            // ── Load source image ──────────────────────────────────────────
            $ext = strtolower(pathinfo($imagePath, PATHINFO_EXTENSION));
            $src = match ($ext) {
                'jpg', 'jpeg', 'jfif' => @imagecreatefromjpeg($imagePath),
                'png'                 => @imagecreatefrompng($imagePath),
                'webp'                => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($imagePath) : false,
                default               => false,
            };

            if (!$src) {
                Log::warning('WatermarkService: não foi possível carregar a imagem fonte', ['path' => $imagePath]);
                return false;
            }

            $srcW = imagesx($src);
            $srcH = imagesy($src);

            // ── Load watermark ─────────────────────────────────────────────
            $wmPath = self::resolveLocalPath($watermarkUrl);
            if (!$wmPath || !file_exists($wmPath)) {
                // Try downloading via HTTP
                $wmContent = @file_get_contents($watermarkUrl);
                if (!$wmContent) {
                    imagedestroy($src);
                    Log::warning('WatermarkService: não foi possível carregar a marca d\'água', ['url' => $watermarkUrl]);
                    return false;
                }
                $wmPath = tempnam(sys_get_temp_dir(), 'wm_') . '.png';
                file_put_contents($wmPath, $wmContent);
                $tmpWm = true;
            } else {
                $tmpWm = false;
            }

            $wmExt = strtolower(pathinfo($wmPath, PATHINFO_EXTENSION));
            $wm = match ($wmExt) {
                'png'  => @imagecreatefrompng($wmPath),
                'jpg', 'jpeg' => @imagecreatefromjpeg($wmPath),
                'webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($wmPath) : false,
                default => @imagecreatefrompng($wmPath),
            };

            if ($tmpWm && file_exists($wmPath)) {
                @unlink($wmPath);
            }

            if (!$wm) {
                imagedestroy($src);
                Log::warning('WatermarkService: imagem da marca d\'água inválida', ['url' => $watermarkUrl]);
                return false;
            }

            // ── Scale watermark ────────────────────────────────────────────
            $wmOrigW = imagesx($wm);
            $wmOrigH = imagesy($wm);

            $wmW = (int) round($srcW * $scale);
            $wmH = (int) round($wmOrigH * ($wmW / $wmOrigW));

            // Ensure watermark is not taller than 40% of source
            if ($wmH > $srcH * 0.4) {
                $wmH = (int) round($srcH * 0.4);
                $wmW = (int) round($wmOrigW * ($wmH / $wmOrigH));
            }

            $wmResized = imagecreatetruecolor($wmW, $wmH);
            imagealphablending($wmResized, false);
            imagesavealpha($wmResized, true);
            $transparent = imagecolorallocatealpha($wmResized, 0, 0, 0, 127);
            imagefill($wmResized, 0, 0, $transparent);
            imagecopyresampled($wmResized, $wm, 0, 0, 0, 0, $wmW, $wmH, $wmOrigW, $wmOrigH);
            imagedestroy($wm);

            // ── Apply opacity by blending with the source pixels ───────────
            $margin = (int) round($srcW * 0.02); // 2% margin from edges

            [$destX, $destY] = self::calcPosition($position, $srcW, $srcH, $wmW, $wmH, $margin);

            // Merge watermark onto a copy with desired opacity
            imagecopymerge($src, $wmResized, $destX, $destY, 0, 0, $wmW, $wmH, (int) round($opacity * 100));
            imagedestroy($wmResized);

            // ── Save back to original path ─────────────────────────────────
            $result = match ($ext) {
                'jpg', 'jpeg', 'jfif' => imagejpeg($src, $imagePath, 90),
                'png'                  => imagepng($src, $imagePath, 6),
                'webp'                 => function_exists('imagewebp') ? imagewebp($src, $imagePath, 85) : false,
                default                => imagejpeg($src, $imagePath, 90),
            };

            imagedestroy($src);

            return (bool) $result;
        } catch (\Throwable $e) {
            Log::error('WatermarkService: erro inesperado', ['error' => $e->getMessage(), 'path' => $imagePath]);
            return false;
        }
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private static function resolveLocalPath(string $url): ?string
    {
        // Already an absolute filesystem path
        if (file_exists($url)) {
            return $url;
        }

        // URL starting with /  → try public_path
        if (str_starts_with($url, '/')) {
            $local = public_path(ltrim($url, '/'));
            return file_exists($local) ? $local : null;
        }

        // Full HTTP URL → try to derive local path from app URL
        $appUrl = rtrim(config('app.url', ''), '/');
        if ($appUrl && str_starts_with($url, $appUrl)) {
            $relativePart = ltrim(substr($url, strlen($appUrl)), '/');
            $local = public_path($relativePart);
            return file_exists($local) ? $local : null;
        }

        return null;
    }

    private static function calcPosition(
        string $position,
        int $srcW, int $srcH,
        int $wmW,  int $wmH,
        int $margin
    ): array {
        return match ($position) {
            'bottom-left'   => [$margin,                   $srcH - $wmH - $margin],
            'bottom-center' => [(int)(($srcW - $wmW) / 2), $srcH - $wmH - $margin],
            'center'        => [(int)(($srcW - $wmW) / 2), (int)(($srcH - $wmH) / 2)],
            default         => [$srcW - $wmW - $margin,    $srcH - $wmH - $margin], // bottom-right
        };
    }
}
