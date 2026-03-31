<?php

namespace App\Services;

use App\Models\ContratoCompraVenda;
use App\Models\ContratoDocumento;
use App\Models\ContratoTemplate;
use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ContratoCompraVendaDocumentoService
{
    private const TEMPLATE = 'pdfs.contratos.compra_venda';

    public function gerarPdf(ContratoCompraVenda $contrato, string $tipo, ?string $template = null): ContratoDocumento
    {
        $viewName = $template ?? self::TEMPLATE;
        $proximaVersao = ((int) ContratoDocumento::where('contrato_compra_venda_id', $contrato->id)
            ->where('tipo', $tipo)
            ->whereIn('categoria', ['original', 'revisado'])
            ->max('versao')) + 1;
        $categoria = $proximaVersao === 1 ? 'original' : 'revisado';

        $contrato->loadMissing([
            'vendedor',
            'comprador',
            'imovel',
        ]);

        $tenantTemplate = ContratoTemplate::where('tipo', 'compra_venda')
            ->where('tenant_id', $contrato->tenant_id)
            ->first();

        $tenant = Tenant::find($contrato->tenant_id);
        $logoPath = $this->resolvePdfImageData($tenant?->logo_url);
        $watermarkPath = $this->resolvePdfImageData($tenant?->watermark_url) ?? $logoPath;

        $pdf = Pdf::loadView($viewName, [
            'contrato' => $contrato,
            'vendedor' => $contrato->vendedor,
            'comprador' => $contrato->comprador,
            'imovel' => $contrato->imovel,
            'geradoEm' => now(),
            'tenantTemplate' => $tenantTemplate,
            'tenant' => $tenant,
            'tenantLogoSrc' => $logoPath,
            'tenantWatermarkSrc' => $watermarkPath,
        ]);

        $pdf->setPaper('A4', 'portrait');

        $filename = sprintf(
            'contratos-compra-venda/%d/%s-%s-%s.pdf',
            $contrato->tenant_id,
            $contrato->id,
            $tipo,
            now()->format('YmdHis'),
        );

        Storage::disk('public')->put($filename, $pdf->output());

        return ContratoDocumento::create([
            'tenant_id' => $contrato->tenant_id,
            'contrato_compra_venda_id' => $contrato->id,
            'tipo' => $tipo,
            'categoria' => $categoria,
            'versao' => $proximaVersao,
            'nome' => $this->nomeAmigavel($tipo, $contrato, $categoria, $proximaVersao),
            'arquivo_path' => $filename,
            'assinatura_status' => 'nao_enviado',
        ]);
    }

    public function registrarDocumentoAssinado(ContratoDocumento $documentoBase, UploadedFile $arquivo, ?int $userId = null): ContratoDocumento
    {
        $documentoBase->loadMissing('contratoCompraVenda');
        $versao = max(1, (int) ($documentoBase->versao ?? 1));

        $filename = sprintf(
            'contratos-compra-venda/%d/%s-%s-v%d-assinado-%s.pdf',
            $documentoBase->tenant_id,
            $documentoBase->contrato_compra_venda_id,
            $documentoBase->tipo,
            $versao,
            now()->format('YmdHis'),
        );

        Storage::disk('public')->putFileAs(
            dirname($filename),
            $arquivo,
            basename($filename),
        );

        $documentoAssinado = ContratoDocumento::where('referencia_documento_id', $documentoBase->id)
            ->where('categoria', 'assinado')
            ->first();

        if ($documentoAssinado?->arquivo_path) {
            Storage::disk('public')->delete($documentoAssinado->arquivo_path);
        }

        $payload = [
            'tenant_id' => $documentoBase->tenant_id,
            'contrato_compra_venda_id' => $documentoBase->contrato_compra_venda_id,
            'tipo' => $documentoBase->tipo,
            'categoria' => 'assinado',
            'versao' => $versao,
            'referencia_documento_id' => $documentoBase->id,
            'nome' => $this->nomeAmigavel($documentoBase->tipo, $documentoBase->contratoCompraVenda, 'assinado', $versao),
            'arquivo_path' => $filename,
            'assinatura_status' => 'assinado',
            'assinado_em' => now(),
            'gerado_por_user_id' => $userId,
            'gerado_em' => now(),
            'd4sign_uuid' => null,
            'd4sign_key' => null,
        ];

        if ($documentoAssinado) {
            $documentoAssinado->update($payload);
            $documentoAssinado = $documentoAssinado->fresh();
        } else {
            $documentoAssinado = ContratoDocumento::create($payload);
        }

        $documentoBase->update([
            'assinatura_status' => 'assinado',
            'assinado_em' => $documentoAssinado->assinado_em,
        ]);

        return $documentoAssinado;
    }

    public function sincronizarStatusDocumentoBase(ContratoDocumento $documento): void
    {
        if ($documento->categoria !== 'assinado' || !$documento->referencia_documento_id) {
            return;
        }

        $documentoBase = ContratoDocumento::find($documento->referencia_documento_id);
        if (!$documentoBase) {
            return;
        }

        $temAssinado = ContratoDocumento::where('referencia_documento_id', $documentoBase->id)
            ->where('categoria', 'assinado')
            ->exists();

        if ($temAssinado) {
            return;
        }

        $documentoBase->update([
            'assinatura_status' => $documentoBase->d4sign_uuid ? 'aguardando' : 'nao_enviado',
            'assinado_em' => null,
        ]);
    }

    public function excluir(ContratoDocumento $documento): void
    {
        $documento->loadMissing('versoesAssinadas');

        foreach ($documento->versoesAssinadas as $versaoAssinada) {
            if ($versaoAssinada->arquivo_path) {
                Storage::disk('public')->delete($versaoAssinada->arquivo_path);
            }

            $versaoAssinada->delete();
        }

        if ($documento->arquivo_path) {
            Storage::disk('public')->delete($documento->arquivo_path);
        }

        $documento->delete();

        $this->sincronizarStatusDocumentoBase($documento);
    }

    private function nomeAmigavel(string $tipo, ?ContratoCompraVenda $contrato, string $categoria = 'original', int $versao = 1): string
    {
        $nome = $tipo === 'compra_venda'
            ? 'Contrato de Compra e Venda'
            : Str::title(str_replace('_', ' ', $tipo));
        $numero = $contrato?->numero_contrato ?? $contrato?->id ?? 'novo';
        $categoriaLabel = match ($categoria) {
            'assinado' => 'Assinado',
            'revisado' => 'Revisado',
            default => 'Original',
        };

        return "{$nome} - {$categoriaLabel} V{$versao} - Contrato #{$numero}";
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
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            default => function_exists('finfo_buffer')
                ? finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), $content)
                : null,
        };
    }
}
