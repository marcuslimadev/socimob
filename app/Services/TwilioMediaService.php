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
        $this->accountSid = env('TWILIO_ACCOUNT_SID');
        $this->authToken = env('TWILIO_AUTH_TOKEN');
    }

    /**
     * Baixa mídia do Twilio e salva localmente
     * 
     * @param string $mediaUrl URL da mídia no Twilio
     * @param int $leadId ID do lead
     * @param int $mensagemId ID da mensagem
     * @return string|null Caminho local da mídia salva
     */
    public function downloadAndSaveMedia(string $mediaUrl, int $leadId, int $mensagemId): ?string
    {
        try {
            Log::info("Baixando mídia do Twilio", [
                'media_url' => $mediaUrl,
                'lead_id' => $leadId,
                'mensagem_id' => $mensagemId
            ]);

            // Faz download autenticado da mídia
            $response = Http::withBasicAuth($this->accountSid, $this->authToken)
                ->timeout(30)
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
        return match($contentType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'audio/mpeg' => 'mp3',
            'audio/ogg' => 'ogg',
            'audio/wav' => 'wav',
            'video/mp4' => 'mp4',
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
