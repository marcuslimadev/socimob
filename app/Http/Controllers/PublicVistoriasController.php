<?php

namespace App\Http\Controllers;

use App\Models\Vistoria;
use App\Services\VistoriaContestacaoService;
use App\Services\VistoriaPdfService;
use App\Services\VistoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PublicVistoriasController extends Controller
{
    public function linkInvalido()
    {
        return response()->view('vistorias.publico.link-invalido', [], 200);
    }

    public function midias(string $token)
    {
        $vistoria = $this->vistoriaPorMidiasToken($token);

        return view('vistorias.publico.midias', [
            'vistoria' => $vistoria,
            'midiasUrl' => url('/api/vistorias/publico/' . $vistoria->link_publico_midias_token . '/midias'),
            'contestacaoUrl' => url('/api/vistorias/publico/' . $vistoria->link_contestacao_token . '/contestacao'),
        ]);
    }

    public function contestacao(string $token)
    {
        $vistoria = app(VistoriaContestacaoService::class)->resolverPorToken($token);

        return view('vistorias.publico.contestacao', [
            'vistoria' => $vistoria,
            'prazoExpirado' => $vistoria->data_limite_contestacao && now()->greaterThan($vistoria->data_limite_contestacao),
        ]);
    }

    public function enviarContestacao(Request $request, string $token)
    {
        $vistoria = app(VistoriaContestacaoService::class)->resolverPorToken($token);
        $data = $request->validate([
            'nome' => 'required|string|max:255',
            'documento' => 'nullable|string|max:80',
            'email' => 'nullable|email|max:255',
            'telefone' => 'nullable|string|max:80',
            'texto' => 'required|string|max:5000',
            'ambiente_id' => 'nullable|integer',
            'item_id' => 'nullable|integer',
            'inconformidade_id' => 'nullable|integer',
            'midias.*' => 'nullable|file|max:102400',
        ]);
        $data['itens'] = [[
            'ambiente_id' => $data['ambiente_id'] ?? null,
            'item_id' => $data['item_id'] ?? null,
            'inconformidade_id' => $data['inconformidade_id'] ?? null,
            'descricao' => $data['texto'],
        ]];

        app(VistoriaContestacaoService::class)->receber($vistoria, $data, $request);

        return redirect()
            ->to('/api/vistorias/publico/' . $token . '/contestacao')
            ->with('success', 'Contestação enviada com sucesso.');
    }

    public function pdf(Request $request, string $token)
    {
        $vistoria = $this->vistoriaPorMidiasToken($token);
        if (!$vistoria->pdf_path) {
            $vistoria = app(VistoriaPdfService::class)->gerar($vistoria, $request);
        }

        if (!$vistoria->pdf_path || !Storage::disk('public')->exists($vistoria->pdf_path)) {
            abort(404, 'PDF não encontrado.');
        }

        return Storage::disk('public')->download($vistoria->pdf_path, ($vistoria->codigo ?: 'vistoria') . '.pdf');
    }

    private function vistoriaPorMidiasToken(string $token): Vistoria
    {
        $vistoria = Vistoria::withoutTenant()
            ->where('link_publico_midias_token', $token)
            ->with(app(VistoriaService::class)->relacoesDetalhe())
            ->first();

        if (!$vistoria || $vistoria->status === 'cancelada') {
            abort(404, 'Link inválido.');
        }

        return $vistoria;
    }
}
