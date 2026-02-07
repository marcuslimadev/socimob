<?php
/**
 * Script para baixar imagens do Twilio e salvar no storage local
 * Execute: php download_twilio_media.php
 */

require __DIR__ . '/bootstrap/app.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;

echo "=== DOWNLOAD DE MÍDIAS DO TWILIO ===\n\n";

// Busca mensagens com media_url do Twilio que ainda não foram baixadas
$messages = DB::table('mensagens')
    ->whereNotNull('media_url')
    ->where('media_url', 'like', 'https://api.twilio.com/%')
    ->get();

echo "Encontradas {$messages->count()} mensagens com mídia do Twilio\n\n";

$accountSid = env('EXCLUSIVA_TWILIO_ACCOUNT_SID') ?: env('TWILIO_ACCOUNT_SID');
$authToken = env('EXCLUSIVA_TWILIO_AUTH_TOKEN') ?: env('TWILIO_AUTH_TOKEN');

if (!$accountSid || !$authToken) {
    echo "❌ ERRO: Credenciais do Twilio não configuradas no .env\n";
    echo "   Certifique-se de ter EXCLUSIVA_TWILIO_ACCOUNT_SID e EXCLUSIVA_TWILIO_AUTH_TOKEN\n";
    echo "   ou TWILIO_ACCOUNT_SID e TWILIO_AUTH_TOKEN\n";
    exit(1);
}

$downloaded = 0;
$errors = 0;

foreach ($messages as $message) {
    echo "Processando mensagem #{$message->id}...\n";
    echo "  URL original: {$message->media_url}\n";
    
    try {
        // Faz download autenticado da mídia
        $response = Http::withBasicAuth($accountSid, $authToken)
            ->timeout(30)
            ->get($message->media_url);
        
        if (!$response->successful()) {
            echo "  ❌ Erro HTTP {$response->status()}\n\n";
            $errors++;
            continue;
        }
        
        // Determina extensão pelo Content-Type
        $contentType = $response->header('Content-Type');
        $extension = match($contentType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'audio/mpeg' => 'mp3',
            'audio/ogg' => 'ogg',
            default => 'bin'
        };
        
        // Gera nome do arquivo
        $filename = "twilio_media_{$message->id}_" . time() . ".{$extension}";
        $path = "messages/media/{$filename}";
        
        // Salva no storage
        Storage::disk('public')->put($path, $response->body());
        
        // Atualiza o registro no banco
        DB::table('mensagens')
            ->where('id', $message->id)
            ->update([
                'media_url' => "/storage/{$path}",
                'updated_at' => now()
            ]);
        
        echo "  ✅ Salvo em: /storage/{$path}\n\n";
        $downloaded++;
        
    } catch (\Exception $e) {
        echo "  ❌ Erro: {$e->getMessage()}\n\n";
        $errors++;
    }
}

echo "\n=== RESUMO ===\n";
echo "✅ Baixadas: {$downloaded}\n";
echo "❌ Erros: {$errors}\n";
echo "Total processado: {$messages->count()}\n";
