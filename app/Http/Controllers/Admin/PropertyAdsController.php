<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PropertyAdsController extends Controller
{
    public function proxyImage(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $url = trim((string) $request->query('url', ''));
        if ($url === '') {
            return response()->json(['error' => 'URL da imagem é obrigatória'], 422);
        }

        if (!$this->isAllowedRemoteUrl($url)) {
            return response()->json(['error' => 'URL da imagem inválida'], 422);
        }

        try {
            $response = Http::withOptions([
                'verify' => env('VERIFY_SSL_CERTIFICATES', true),
            ])
                ->timeout(12)
                ->accept('image/*')
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 Socimob Image Proxy',
                ])
                ->get($url);
        } catch (\Throwable $e) {
            Log::warning('Property image proxy failed', [
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            return $this->fallbackImageResponse();
        }

        if (!$response->successful()) {
            Log::warning('Property image proxy returned unsuccessful status', [
                'url' => $url,
                'status' => $response->status(),
            ]);

            return $this->fallbackImageResponse();
        }

        $contentType = (string) $response->header('Content-Type', 'application/octet-stream');
        if (stripos($contentType, 'image/') !== 0) {
            Log::warning('Property image proxy returned non-image response', [
                'url' => $url,
                'content_type' => $contentType,
            ]);

            return $this->fallbackImageResponse();
        }

        return response($response->body(), 200)
            ->header('Content-Type', $contentType)
            ->header('Cache-Control', 'private, max-age=3600');
    }

    private function fallbackImageResponse()
    {
        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="320" height="240" viewBox="0 0 320 240">
  <rect width="320" height="240" fill="#e5e7eb"/>
  <path d="M96 154l42-50 34 40 18-22 34 32H96z" fill="#94a3b8"/>
  <circle cx="216" cy="82" r="18" fill="#cbd5e1"/>
</svg>
SVG;

        return response($svg, 200)
            ->header('Content-Type', 'image/svg+xml')
            ->header('Cache-Control', 'private, max-age=3600');
    }

    private function isAllowedRemoteUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }

        if ($host === 'localhost' || str_ends_with($host, '.local')) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
            && filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return false;
        }

        return true;
    }
}
