<?php

namespace App\Services;

use App\Models\Vistoria;
use App\Models\VistoriaContestacao;
use App\Models\VistoriaContestacaoMidia;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VistoriaContestacaoService
{
    public function resolverPorToken(string $token): Vistoria
    {
        $vistoria = Vistoria::withoutTenant()
            ->where('link_contestacao_token', $token)
            ->with(app(VistoriaService::class)->relacoesDetalhe())
            ->first();

        if (!$vistoria || $vistoria->status === 'cancelada') {
            abort(404, 'Link de contestação inválido.');
        }

        return $vistoria;
    }

    public function receber(Vistoria $vistoria, array $data, Request $request): VistoriaContestacao
    {
        if ($vistoria->data_limite_contestacao && now()->greaterThan($vistoria->data_limite_contestacao)) {
            abort(422, 'Prazo de contestação expirado.');
        }

        $contestacao = VistoriaContestacao::create([
            'tenant_id' => $vistoria->tenant_id,
            'vistoria_id' => $vistoria->id,
            'parte_id' => $data['parte_id'] ?? null,
            'codigo' => 'CON-' . now()->format('Ymd-His'),
            'status' => 'enviada',
            'tipo' => 'divergencia',
            'descricao' => $data['texto'] ?? null,
            'texto' => $data['texto'] ?? null,
            'nome' => $data['nome'],
            'documento' => $data['documento'] ?? null,
            'email' => $data['email'] ?? null,
            'telefone' => $data['telefone'] ?? null,
            'cliente_nome' => $data['nome'],
            'data_envio' => now(),
            'data_contestacao' => now(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'historico' => [[
                'evento' => 'contestacao_recebida',
                'data' => now()->toIso8601String(),
            ]],
        ]);

        foreach (($data['itens'] ?? []) as $item) {
            if (!empty($item['descricao'])) {
                $contestacao->itens()->create([
                    'ambiente_id' => $item['ambiente_id'] ?? null,
                    'item_id' => $item['item_id'] ?? null,
                    'inconformidade_id' => $item['inconformidade_id'] ?? null,
                    'descricao' => $item['descricao'],
                ]);
            }
        }

        foreach ($request->file('midias', []) as $file) {
            if ($file instanceof UploadedFile) {
                $this->salvarMidia($vistoria, $contestacao, $file);
            }
        }

        $vistoria->update(['status' => 'contestada']);
        app(VistoriaService::class)->registrarHistorico($vistoria, 'contestacao_recebida', 'Contestação recebida via link público.', $request);

        return $contestacao->fresh(['itens', 'midias']);
    }

    public function responder(VistoriaContestacao $contestacao, array $data, Request $request): VistoriaContestacao
    {
        $contestacao->update([
            'status' => $data['status'] ?? 'em_analise',
            'resposta_admin' => $data['resposta_admin'] ?? $data['resolucao'] ?? null,
            'resolucao' => $data['resposta_admin'] ?? $data['resolucao'] ?? null,
            'data_resposta' => now(),
            'data_resolucao' => now(),
            'respondido_por' => $request->user()?->id,
            'user_id' => $request->user()?->id,
        ]);

        if ($contestacao->vistoria) {
            app(VistoriaService::class)->registrarHistorico($contestacao->vistoria, 'contestacao_respondida', 'Contestação respondida pela administração.', $request);
        }

        return $contestacao->fresh(['itens', 'midias']);
    }

    private function salvarMidia(Vistoria $vistoria, VistoriaContestacao $contestacao, UploadedFile $file): void
    {
        $mime = $file->getMimeType() ?: $file->getClientMimeType();
        if (!preg_match('#^(image|video)/#', (string) $mime)) {
            abort(422, 'Anexo de contestação deve ser foto ou vídeo.');
        }

        $tipo = str_starts_with((string) $mime, 'video/') ? 'video' : 'foto';
        $ext = $file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin';
        $path = Storage::disk('public')->putFileAs(
            "tenants/{$vistoria->tenant_id}/vistorias/{$vistoria->id}/contestacoes/{$contestacao->id}",
            $file,
            Str::uuid() . '.' . preg_replace('/[^a-z0-9]+/i', '', strtolower($ext))
        );

        VistoriaContestacaoMidia::create([
            'contestacao_id' => $contestacao->id,
            'tipo' => $tipo,
            'path' => $path,
            'mime_type' => $mime,
            'tamanho_bytes' => $file->getSize(),
            'legenda' => null,
        ]);
    }
}
