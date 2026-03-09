<?php
namespace App\Services;

use App\Models\ContratoDocumento;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Integration with D4Sign digital signature platform.
 *
 * Configure via environment variables:
 *   D4SIGN_API_KEY, D4SIGN_CRYPTO_KEY, D4SIGN_BASE_URL, D4SIGN_SAFE_UUID
 */
class D4SignService
{
    private string $baseUrl;
    private string $apiKey;
    private string $cryptoKey;
    private string $safeUuid;

    public function __construct()
    {
        $this->baseUrl = rtrim(env('D4SIGN_BASE_URL', 'https://secure.d4sign.com.br/api/v1'), '/');
        $this->apiKey = env('D4SIGN_API_KEY', '');
        $this->cryptoKey = env('D4SIGN_CRYPTO_KEY', '');
        $this->safeUuid = env('D4SIGN_SAFE_UUID', '');
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey) && !empty($this->cryptoKey) && !empty($this->safeUuid);
    }

    /**
     * Upload a document PDF to D4Sign and add signatories.
     *
     * @param ContratoDocumento $documento
     * @param array $signatarios  [['email'=>'...','nome'=>'...','papel'=>'?'],...]
     * @return array ['success'=>bool, 'uuid'=>string, 'key'=>string, 'message'=>string]
     */
    public function enviarDocumento(ContratoDocumento $documento, array $signatarios): array
    {
        if (!$this->isConfigured()) {
            Log::warning('D4Sign não configurado: variáveis de ambiente ausentes');
            return ['success' => false, 'message' => 'D4Sign não configurado'];
        }

        try {
            // 1. Upload the PDF file
            $pdfContent = Storage::disk('public')->get($documento->arquivo_path);
            if (!$pdfContent) {
                return ['success' => false, 'message' => 'Arquivo PDF não encontrado'];
            }

            $uploadResponse = Http::withHeaders([
                'tokenAPI' => $this->apiKey,
                'cryptKey' => $this->cryptoKey,
            ])->attach('file', $pdfContent, basename($documento->arquivo_path))
              ->post("{$this->baseUrl}/documents/{$this->safeUuid}/upload");

            if (!$uploadResponse->ok()) {
                Log::error('D4Sign upload failed', ['status' => $uploadResponse->status(), 'body' => $uploadResponse->body()]);
                return ['success' => false, 'message' => 'Falha no upload para D4Sign'];
            }

            $docUuid = $uploadResponse->json('uuid') ?? $uploadResponse->json('id');
            $docKey  = $uploadResponse->json('key') ?? null;

            if (!$docUuid) {
                return ['success' => false, 'message' => 'UUID do documento não retornado pelo D4Sign'];
            }

            // 2. Add each signatory
            foreach ($signatarios as $sig) {
                $addResponse = Http::withHeaders([
                    'tokenAPI' => $this->apiKey,
                    'cryptKey' => $this->cryptoKey,
                ])->post("{$this->baseUrl}/documents/{$docUuid}/createlist", [
                    'email' => $sig['email'],
                    'act' => '1', // 1=assinar, 2=aprovar, 3=reconhecer
                    'foreign' => '0',
                    'certificadoicpbr' => '0',
                    'assinatura_presencial' => '0',
                    'docauth' => '0',
                ]);

                if (!$addResponse->ok()) {
                    Log::warning('D4Sign addSignatory failed', ['email' => $sig['email'], 'body' => $addResponse->body()]);
                }
            }

            // 3. Send to signatories
            Http::withHeaders([
                'tokenAPI' => $this->apiKey,
                'cryptKey' => $this->cryptoKey,
            ])->post("{$this->baseUrl}/documents/{$docUuid}/sendtosigner");

            return ['success' => true, 'uuid' => $docUuid, 'key' => $docKey];
        } catch (\Throwable $e) {
            Log::error('D4SignService error', ['message' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Check the current signing status of a document.
     */
    public function consultarStatus(string $docUuid): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $response = Http::withHeaders([
                'tokenAPI' => $this->apiKey,
                'cryptKey' => $this->cryptoKey,
            ])->get("{$this->baseUrl}/documents/{$docUuid}");

            if ($response->ok()) {
                return $response->json('statusId') ?? $response->json('status');
            }
        } catch (\Throwable $e) {
            Log::error('D4SignService consultarStatus error', ['message' => $e->getMessage()]);
        }

        return null;
    }
}
