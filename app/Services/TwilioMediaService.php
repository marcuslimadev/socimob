<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class TwilioMediaService
{
    protected $accountSid;
    protected $authToken;

    public function __construct()
    {
        // Tenta EXCLUSIVA_ primeiro (ambiente de produção), depois fallback para TWILIO_
        $this->accountSid = env('EXCLUSIVA_TWILIO_ACCOUNT_SID') ?: env('TWILIO_ACCOUNT_SID');
        $this->authToken = env('EXCLUSIVA_TWILIO_AUTH_TOKEN') ?: env('TWILIO_AUTH_TOKEN');
        
        if (!$this->accountSid || !$this->authToken) {
            Log::error('❌ Credenciais do Twilio não configuradas! Verifique EXCLUSIVA_TWILIO_ACCOUNT_SID e EXCLUSIVA_TWILIO_AUTH_TOKEN no .env');
        }
    }

    /**
     * Baixa mídia do Twilio e salva localmente
     * 
     * @param string $mediaUrl URL da mídia no Twilio
     * @param int $leadId ID do lead
     * @param int $mensagemId ID da mensagem
     * @return string|null Caminho local da mídia salva
     */
    public function downloadAndSaveMedia(string $mediaUrl, int $leadId, int $mensagemId, ?string $accountSidOverride = null, ?string $authTokenOverride = null): ?string
    {
        try {
            $sid = $accountSidOverride ?: $this->accountSid;
            $token = $authTokenOverride ?: $this->authToken;

            Log::info("Baixando mídia do Twilio", [
                'media_url' => $mediaUrl,
                'lead_id' => $leadId,
                'mensagem_id' => $mensagemId
            ]);

            // Faz download autenticado da mídia (com follow redirects)
            $response = Http::withBasicAuth($sid, $token)
                ->timeout(30)
                ->withOptions([
                    'allow_redirects' => ['max' => 5] // Seguir até 5 redirects
                ])
                ->get($mediaUrl);

            if (!$response->successful()) {
                Log::error("Erro ao baixar mídia do Twilio", [
                    'status' => $response->status(),
                    'media_url' => $mediaUrl
                ]);
                return null;
            }

            // Determina extensão pelo Content-Type
            $contentType = $response->header('Content-Type');
            $extension = $this->getExtensionFromContentType($contentType);

            // Gera nome do arquivo
            $filename = "lead_{$leadId}_msg_{$mensagemId}_" . time() . ".{$extension}";
            $path = "leads/{$leadId}/media/{$filename}";

            // Salva no storage público
            Storage::disk('public')->put($path, $response->body());

            $localPath = "/storage/{$path}";

            Log::info("Mídia salva com sucesso", [
                'local_path' => $localPath,
                'size' => strlen($response->body())
            ]);

            // Atualiza a mensagem com o caminho local
            DB::table('mensagens')
                ->where('id', $mensagemId)
                ->update([
                    'media_url' => $localPath,
                    'updated_at' => now()
                ]);

            // Salva no perfil do lead se for imagem
            if ($this->isImage($contentType)) {
                $this->saveToLeadProfile($leadId, $localPath);
            }

            return $localPath;

        } catch (\Exception $e) {
            Log::error("Exceção ao processar mídia do Twilio", [
                'error' => $e->getMessage(),
                'media_url' => $mediaUrl
            ]);
            return null;
        }
    }

    /**
     * Salva imagem no perfil do lead
     */
    protected function saveToLeadProfile(int $leadId, string $imagePath): void
    {
        try {
            // Busca o lead
            $lead = DB::table('leads')->where('id', $leadId)->first();
            
            if (!$lead) {
                Log::warning("Lead não encontrado para salvar imagem", ['lead_id' => $leadId]);
                return;
            }

            // Cria registro na tabela de anexos do lead (se existir)
            // Ou adiciona ao campo de imagens do perfil
            DB::table('lead_anexos')->insert([
                'lead_id' => $leadId,
                'tipo' => 'imagem',
                'caminho' => $imagePath,
                'origem' => 'whatsapp',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            Log::info("Imagem salva no perfil do lead", [
                'lead_id' => $leadId,
                'image_path' => $imagePath
            ]);

        } catch (\Exception $e) {
            // Se a tabela não existe, tenta criar
            if (strpos($e->getMessage(), 'lead_anexos') !== false) {
                $this->createLeadAnexosTable();
                // Tenta novamente
                DB::table('lead_anexos')->insert([
                    'lead_id' => $leadId,
                    'tipo' => 'imagem',
                    'caminho' => $imagePath,
                    'origem' => 'whatsapp',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            } else {
                Log::error("Erro ao salvar imagem no perfil do lead", [
                    'error' => $e->getMessage(),
                    'lead_id' => $leadId
                ]);
            }
        }
    }

    /**
     * Cria tabela de anexos do lead se não existir
     */
    protected function createLeadAnexosTable(): void
    {
        DB::statement("
            CREATE TABLE IF NOT EXISTS `lead_anexos` (
                `id` bigint unsigned NOT NULL AUTO_INCREMENT,
                `lead_id` bigint unsigned NOT NULL,
                `tipo` varchar(50) NOT NULL,
                `caminho` varchar(500) NOT NULL,
                `origem` varchar(50) DEFAULT 'upload',
                `descricao` text,
                `created_at` timestamp NULL DEFAULT NULL,
                `updated_at` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `lead_anexos_lead_id_foreign` (`lead_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }

    /**
     * Retorna extensão baseada no Content-Type
     */
    protected function getExtensionFromContentType(?string $contentType): string
    {
        if (!$contentType) return 'bin';

        // Limpar parâmetros (ex: "audio/ogg; codecs=opus" → "audio/ogg")
        $clean = strtolower(trim(explode(';', $contentType)[0]));

        return match($clean) {
            // Imagens
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/heic' => 'heic',
            'image/svg+xml' => 'svg',
            // Áudio
            'audio/mpeg', 'audio/mp3' => 'mp3',
            'audio/ogg', 'audio/opus' => 'ogg',
            'audio/wav', 'audio/x-wav' => 'wav',
            'audio/mp4', 'audio/m4a', 'audio/x-m4a' => 'm4a',
            'audio/amr' => 'amr',
            'audio/aac' => 'aac',
            // Vídeo
            'video/mp4' => 'mp4',
            'video/3gpp' => '3gp',
            'video/quicktime' => 'mov',
            'video/webm' => 'webm',
            'video/x-msvideo' => 'avi',
            // Documentos
            'application/pdf' => 'pdf',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'text/plain' => 'txt',
            'text/csv' => 'csv',
            default => 'bin'
        };
    }

    /**
     * Verifica se é imagem
     */
    protected function isImage(?string $contentType): bool
    {
        return str_starts_with($contentType ?? '', 'image/');
    }
}
