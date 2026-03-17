<?php
namespace App\Services;

use App\Models\ContratoDocumento;
use App\Models\ContratoLocacao;
use App\Models\ContratoTemplate;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Generates and manages PDF documents for rental contracts.
 */
class ContratoDocumentoService
{
    /**
     * Map of document types to Blade template paths.
     */
    private const TEMPLATES = [
        'contrato' => 'pdfs.contratos.contrato',
        'aditivo' => 'pdfs.contratos.aditivo',
        'rescisao' => 'pdfs.contratos.rescisao',
        'renovacao' => 'pdfs.contratos.renovacao',
        'recibo' => 'pdfs.contratos.recibo',
    ];

    public function gerarPdf(ContratoLocacao $contrato, string $tipo, ?string $template = null): ContratoDocumento
    {
        $viewName = $template ?? (self::TEMPLATES[$tipo] ?? self::TEMPLATES['contrato']);

        // Garante que todas as relações estão carregadas (incluindo aninhadas)
        $contrato->loadMissing([
            'locador',
            'locatario',
            'imovel',
            'fiadores.pessoa',
        ]);

        // Carrega co-locadores caso existam
        if (!empty($contrato->co_locadores_ids)) {
            $contrato->load('locador'); // garante principal
        }

        // Carrega personalização do tenant para este tipo de documento
        $tenantTemplate = ContratoTemplate::where('tipo', $tipo)
            ->where('tenant_id', $contrato->tenant_id)
            ->first();

        $tenant = Tenant::find($contrato->tenant_id);
        $logoPath = $this->resolvePdfImageData($tenant?->logo_url);
        $watermarkPath = $this->resolvePdfImageData($tenant?->watermark_url) ?? $logoPath;

        $pdf = Pdf::loadView($viewName, [
            'contrato'       => $contrato,
            'locador'        => $contrato->locador,
            'locatario'      => $contrato->locatario,
            'imovel'         => $contrato->imovel,
            'fiadores'       => $contrato->fiadores,
            'geradoEm'       => now(),
            'tenantTemplate' => $tenantTemplate,
            'tenant'         => $tenant,
            'tenantLogoSrc'  => $logoPath,
            'tenantWatermarkSrc' => $watermarkPath,
        ]);

        $pdf->setPaper('A4', 'portrait');

        $filename = sprintf(
            'contratos/%d/%s-%s-%s.pdf',
            $contrato->tenant_id,
            $contrato->id,
            $tipo,
            now()->format('YmdHis'),
        );

        Storage::disk('public')->put($filename, $pdf->output());

        return ContratoDocumento::create([
            'tenant_id' => $contrato->tenant_id,
            'contrato_id' => $contrato->id,
            'tipo' => $tipo,
            'nome' => $this->nomeAmigavel($tipo, $contrato),
            'arquivo_path' => $filename,
            'assinatura_status' => 'nao_enviado',
        ]);
    }

    public function excluir(ContratoDocumento $documento): void
    {
        Storage::disk('public')->delete($documento->arquivo_path);
        $documento->delete();
    }

    private function nomeAmigavel(string $tipo, ContratoLocacao $contrato): string
    {
        $nomes = [
            'contrato' => 'Contrato de Locação',
            'aditivo' => 'Aditivo Contratual',
            'rescisao' => 'Termo de Rescisão',
            'renovacao' => 'Termo de Renovação',
            'recibo' => 'Recibo de Locação',
        ];

        $nome = $nomes[$tipo] ?? Str::title($tipo);
        $numero = $contrato->numero_contrato ?? $contrato->id;

        return "{$nome} - Contrato #{$numero}";
    }

    private function resolvePdfImageData(?string $asset): ?string
    {
        if (!$asset) {
            return null;
        }

        $asset = trim($asset);
        if ($asset === '' || !preg_match('/\.(png|jpe?g|webp|svg)$/i', $asset)) {
            return null;
        }

        $content = null;
        $mimeType = null;

        if (filter_var($asset, FILTER_VALIDATE_URL)) {
            $content = @file_get_contents($asset);
            if ($content !== false) {
                $mimeType = $this->guessMimeType($asset, $content);
            }
        } else {
            $relativePath = ltrim($asset, '/');
            $candidates = [
                public_path($relativePath),
                storage_path('app/public/' . $relativePath),
            ];

            foreach ($candidates as $candidate) {
                if (is_file($candidate)) {
                    $content = @file_get_contents($candidate);
                    if ($content !== false) {
                        $mimeType = $this->guessMimeType($candidate, $content);
                        break;
                    }
                }
            }
        }

        if (!$content || !$mimeType) {
            return null;
        }

        return sprintf('data:%s;base64,%s', $mimeType, base64_encode($content));
    }

    private function guessMimeType(string $path, string $content): ?string
    {
        $extension = strtolower(pathinfo(parse_url($path, PHP_URL_PATH) ?? $path, PATHINFO_EXTENSION));

        return match ($extension) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => null,
        };
    }
}
