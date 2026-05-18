<?php

namespace App\Services;

use App\Models\Vistoria;
use App\Models\VistoriaParte;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VistoriaAssinaturaService
{
    public function assinar(Vistoria $vistoria, VistoriaParte $parte, string $assinaturaBase64, Request $request): VistoriaParte
    {
        if ($parte->vistoria_id !== $vistoria->id) {
            abort(404, 'Parte não encontrada para esta vistoria.');
        }

        if (!preg_match('/^data:image\/png;base64,/', $assinaturaBase64)) {
            abort(422, 'Assinatura deve ser enviada como PNG em base64.');
        }

        $binario = base64_decode(substr($assinaturaBase64, strpos($assinaturaBase64, ',') + 1), true);
        if (!$binario) {
            abort(422, 'Assinatura inválida.');
        }

        $path = "tenants/{$vistoria->tenant_id}/vistorias/{$vistoria->id}/assinaturas/" . Str::uuid() . '.png';
        Storage::disk('public')->put($path, $binario);

        $parte->update([
            'assinou' => true,
            'data_assinatura' => now(),
            'assinatura_path' => $path,
            'ip_assinatura' => $request->ip(),
            'user_agent_assinatura' => $request->userAgent(),
        ]);

        app(VistoriaService::class)->registrarHistorico($vistoria, 'assinatura_realizada', "Assinatura registrada para {$parte->nome}.", $request);

        return $parte->fresh();
    }
}
