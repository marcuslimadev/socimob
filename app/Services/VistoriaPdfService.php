<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\Vistoria;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class VistoriaPdfService
{
    public function gerar(Vistoria $vistoria, ?Request $request = null): Vistoria
    {
        $vistoria->loadMissing(app(VistoriaService::class)->relacoesDetalhe());

        $tenant = Tenant::withoutGlobalScopes()->find($vistoria->tenant_id);
        $midiasUrl = url('/vistorias/publico/' . $vistoria->link_publico_midias_token . '/midias');
        $contestacaoUrl = url('/vistorias/publico/' . $vistoria->link_contestacao_token . '/contestacao');

        $pdf = Pdf::loadView('pdfs.vistorias.termo', [
            'vistoria' => $vistoria,
            'tenant' => $tenant,
            'midiasUrl' => $midiasUrl,
            'contestacaoUrl' => $contestacaoUrl,
            'midiasQrUrl' => $this->qrUrl($midiasUrl),
            'contestacaoQrUrl' => $this->qrUrl($contestacaoUrl),
            'geradoEm' => now(),
        ])->setPaper('A4', 'portrait')
            ->setOption(['isRemoteEnabled' => true]);

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
}
