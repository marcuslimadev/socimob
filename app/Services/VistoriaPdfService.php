<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\Vistoria;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VistoriaPdfService
{
    public function gerar(Vistoria $vistoria, ?Request $request = null): Vistoria
    {
        $vistoria->loadMissing(app(VistoriaService::class)->relacoesDetalhe());

        $tenant = Tenant::withoutGlobalScopes()->find($vistoria->tenant_id);
        $midiasUrl = url('/vistorias/publico/' . $vistoria->link_publico_midias_token . '/midias');
        $contestacaoUrl = url('/vistorias/publico/' . $vistoria->link_contestacao_token . '/contestacao');
        $tenantLogo = $this->logoPath($tenant);

        $pdf = Pdf::loadView('pdfs.vistorias.termo', [
            'vistoria' => $vistoria,
            'tenant' => $tenant,
            'tenantLogo' => $tenantLogo,
            'midiasUrl' => $midiasUrl,
            'contestacaoUrl' => $contestacaoUrl,
            'midiasQrUrl' => $this->qrUrl($midiasUrl),
            'contestacaoQrUrl' => $this->qrUrl($contestacaoUrl),
            'pdfImageSrc' => fn (?string $path, ?string $mime = null, ?string $fallbackUrl = null) => $this->pdfImageSrc($path, $mime, $fallbackUrl),
            'geradoEm' => now(),
        ])->setPaper('A4', 'portrait')
            ->setOption([
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
                'chroot' => public_path(),
            ]);

        $conteudo = $pdf->output();
        $path = "tenants/{$vistoria->tenant_id}/vistorias/{$vistoria->id}/pdf/termo-vistoria-" . now()->format('YmdHis') . '.pdf';
        Storage::disk('public')->put($path, $conteudo);

        $vistoria->update([
            'pdf_path' => $path,
            'hash_pdf' => hash('sha256', $conteudo),
        ]);

        app(VistoriaService::class)->registrarHistorico($vistoria, 'pdf_gerado', 'PDF do termo de vistoria gerado.', $request);

        return $vistoria->fresh(app(VistoriaService::class)->relacoesDetalhe());
    }

    public function qrUrl(string $url): string
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' . rawurlencode($url);
    }

    private function logoPath(?Tenant $tenant): ?string
    {
        $logo = trim((string) ($tenant?->logo_url ?? ''));
        if ($logo === '') {
            return null;
        }

        if (Str::startsWith($logo, ['http://', 'https://'])) {
            return $logo;
        }

        $relative = ltrim(Str::replaceFirst('/storage/', '', $logo), '/');
        if (Storage::disk('public')->exists($relative)) {
            return public_path('storage/' . $relative);
        }

        $publicRelative = ltrim($logo, '/');
        return file_exists(public_path($publicRelative)) ? public_path($publicRelative) : null;
    }

    private function pdfImageSrc(?string $path, ?string $mime = null, ?string $fallbackUrl = null): ?string
    {
        $path = trim((string) $path);

        if ($path !== '') {
            try {
                if (Storage::disk('public')->exists($path)) {
                    $content = Storage::disk('public')->get($path);
                    if ($content !== '') {
                        $mime = $mime && Str::startsWith($mime, 'image/') ? $mime : 'image/jpeg';
                        return 'data:' . $mime . ';base64,' . base64_encode($content);
                    }
                }
            } catch (\Throwable) {
                // Fallback abaixo tenta URL pública quando o storage local não estiver disponível.
            }
        }

        if ($fallbackUrl) {
            return $fallbackUrl;
        }

        return $path !== '' ? Storage::disk('public')->url($path) : null;
    }
}
