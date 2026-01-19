<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\TwilioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MensagemMediaController extends Controller
{
    private TwilioService $twilio;

    public function __construct(TwilioService $twilio)
    {
        $this->twilio = $twilio;
    }

    public function show(Request $request, $id)
    {
        $user = $request->user();
        if (!$user) {
            return response('Unauthorized', 401);
        }

        $tenantId = $request->attributes->get('tenant_id');

        $msg = DB::table('mensagens')
            ->join('conversas', 'mensagens.conversa_id', '=', 'conversas.id')
            ->select(
                'mensagens.id',
                'mensagens.tenant_id as mensagem_tenant_id',
                'mensagens.message_type',
                'mensagens.media_url',
                'conversas.tenant_id as conversa_tenant_id',
                'conversas.corretor_id as conversa_corretor_id'
            )
            ->where('mensagens.id', (int) $id)
            ->first();

        if (!$msg) {
            return response('Not found', 404);
        }

        $effectiveTenantId = $msg->mensagem_tenant_id ?: $msg->conversa_tenant_id;
        if ($tenantId && $effectiveTenantId && (int) $tenantId !== (int) $effectiveTenantId) {
            return response('Not found', 404);
        }

        // Corretor só pode baixar mídia das próprias conversas (se já atribuída)
        if (($user->role ?? null) === 'corretor' && $msg->conversa_corretor_id && (int) $msg->conversa_corretor_id !== (int) $user->id) {
            return response('Forbidden', 403);
        }

        if (empty($msg->media_url)) {
            return response('Not found', 404);
        }

        $cacheDir = storage_path('app/media');
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }

        $extension = $this->guessExtension($msg->media_url);
        $cachePath = $cacheDir . '/mensagem_' . (int) $msg->id . ($extension ? ('.' . $extension) : '');

        if (!file_exists($cachePath) || filesize($cachePath) === 0) {
            $download = $this->twilio->downloadMedia($msg->media_url);
            if (empty($download['success'])) {
                return response('Failed to fetch media', 502);
            }

            file_put_contents($cachePath, $download['data']);
        }

        $contentType = $this->guessContentType($extension, $msg->message_type);
        $bytes = file_get_contents($cachePath);

        return response($bytes, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    private function guessExtension(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (!$path) {
            return null;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!$ext) {
            return null;
        }

        // Normalizar extensões comuns
        if ($ext === 'oga') return 'ogg';
        if ($ext === 'm4a') return 'm4a';
        if ($ext === 'mp4') return 'mp4';

        return $ext;
    }

    private function guessContentType(?string $extension, ?string $messageType): string
    {
        $ext = strtolower((string) $extension);

        if ($ext === 'mp3') return 'audio/mpeg';
        if ($ext === 'wav') return 'audio/wav';
        if ($ext === 'm4a') return 'audio/mp4';
        if ($ext === 'mp4') return 'audio/mp4';
        if ($ext === 'ogg' || $ext === 'oga') return 'audio/ogg';

        if (($messageType ?? '') === 'audio') {
            return 'audio/ogg';
        }

        return 'application/octet-stream';
    }
}
