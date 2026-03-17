<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

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

        $response = Http::withOptions([
            'verify' => env('VERIFY_SSL_CERTIFICATES', true),
        ])
            ->timeout(20)
            ->accept('image/*')
            ->get($url);

        if (!$response->successful()) {
            return response()->json(['error' => 'Não foi possível carregar a imagem remota'], 502);
        }

        $contentType = (string) $response->header('Content-Type', 'application/octet-stream');
        if (stripos($contentType, 'image/') !== 0) {
            return response()->json(['error' => 'A URL informada não retornou uma imagem'], 422);
        }

        return response($response->body(), 200)
            ->header('Content-Type', $contentType)
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