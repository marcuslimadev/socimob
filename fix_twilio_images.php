<?php
/**
 * Baixar imagens do Twilio - VERSÃO PRODUÇÃO
 * Execute no servidor: php fix_twilio_images.php
 */

require __DIR__ . '/bootstrap/app.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

echo "=== CORRIGIR IMAGENS DO TWILIO ===\n\n";

// Busca mensagens com URL do Twilio
$messages = DB::table('mensagens')
    ->whereNotNull('media_url')
    ->where('media_url', 'like', 'https://api.twilio.com/%')
    ->get();

echo "Encontradas {$messages->count()} mensagens\n\n";

$accountSid = env('EXCLUSIVA_TWILIO_ACCOUNT_SID') ?: env('TWILIO_ACCOUNT_SID');
$authToken = env('EXCLUSIVA_TWILIO_AUTH_TOKEN') ?: env('TWILIO_AUTH_TOKEN');

if (!$accountSid || !$authToken) {
    echo "❌ Credenciais não configuradas!\n";
    exit(1);
}

$success = 0;
$errors = 0;

foreach ($messages as $msg) {
    echo "Mensagem #{$msg->id}: ";
    
    // Encontrar lead_id pela conversa
    $conversa = DB::table('conversas')->where('id', $msg->conversa_id)->first();
    if (!$conversa || !$conversa->lead_id) {
        echo "❌ Sem lead_id\n";
        $errors++;
        continue;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $msg->media_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, "$accountSid:$authToken");
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$data) {
        echo "❌ HTTP $httpCode\n";
        $errors++;
        continue;
    }
    
    // Determinar extensão
    $ext = 'jpg';
    if (strpos($contentType, 'png') !== false) $ext = 'png';
    if (strpos($contentType, 'gif') !== false) $ext = 'gif';
    if (strpos($contentType, 'webp') !== false) $ext = 'webp';
    
    // Criar diretório
    $dir = __DIR__ . "/storage/app/public/leads/{$conversa->lead_id}/media";
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    // Salvar arquivo
    $filename = "lead_{$conversa->lead_id}_msg_{$msg->id}_" . time() . ".$ext";
    $fullPath = $dir . '/' . $filename;
    file_put_contents($fullPath, $data);
    
    // Atualizar banco
    $localPath = "/storage/leads/{$conversa->lead_id}/media/$filename";
    DB::table('mensagens')
        ->where('id', $msg->id)
        ->update(['media_url' => $localPath]);
    
    echo "✅ $filename (" . number_format(strlen($data)/1024, 1) . " KB)\n";
    $success++;
}

echo "\n=== RESULTADO ===\n";
echo "✅ Sucesso: $success\n";
echo "❌ Erros: $errors\n";
