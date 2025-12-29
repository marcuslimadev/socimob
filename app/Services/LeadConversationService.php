<?php

namespace App\Services;

use App\Models\Conversa;
use App\Models\Lead;
use App\Models\Mensagem;
use Carbon\Carbon;

class LeadConversationService
{
    public function ensureConversaForLead(Lead $lead, array $options = []): ?Conversa
    {
        $existing = $lead->conversas()->orderBy('id', 'desc')->first();
        if ($existing) {
            return $existing;
        }

        $telefone = $this->normalizeTelefone($lead->telefone ?? $lead->whatsapp);
        if (!$telefone) {
            return null;
        }

        $iniciadaEm = $lead->created_at ? Carbon::parse($lead->created_at) : Carbon::now();
        $ultimaAtividade = $lead->updated_at ? Carbon::parse($lead->updated_at) : $iniciadaEm;

        $conversa = Conversa::create([
            'tenant_id' => $lead->tenant_id,
            'lead_id' => $lead->id,
            'corretor_id' => $lead->corretor_id,
            'telefone' => $telefone,
            'status' => 'ativa',
            'canal' => $options['canal'] ?? 'chaves_na_mao',
            'iniciada_em' => $iniciadaEm,
            'ultima_atividade' => $ultimaAtividade,
        ]);

        $message = $options['message'] ?? $this->extractMensagemFromObservacoes($lead->observacoes);
        if ($message) {
            Mensagem::create([
                'tenant_id' => $lead->tenant_id,
                'conversa_id' => $conversa->id,
                'direction' => 'incoming',
                'message_type' => 'text',
                'content' => $message,
                'status' => 'received',
                'sent_at' => $iniciadaEm,
            ]);

            $conversa->update(['ultima_atividade' => $iniciadaEm]);
        }

        return $conversa;
    }

    private function normalizeTelefone(?string $telefone): ?string
    {
        if (!$telefone) {
            return null;
        }

        return trim($telefone);
    }

    private function extractMensagemFromObservacoes(?string $observacoes): ?string
    {
        if (!$observacoes) {
            return null;
        }

        $texto = str_replace(["\r\n", "\r"], "\n", $observacoes);
        $linhas = explode("\n", $texto);

        $capturando = false;
        $buffer = [];
        foreach ($linhas as $linha) {
            $linhaTrim = trim($linha);
            if (!$capturando && stripos($linhaTrim, 'Mensagem:') !== false) {
                $capturando = true;
                $buffer[] = trim(preg_replace('/^.*?Mensagem:\s*/i', '', $linhaTrim));
                continue;
            }

            if ($capturando) {
                if ($linhaTrim === '') {
                    break;
                }

                if ($this->isObservacaoMarker($linhaTrim)) {
                    break;
                }

                $buffer[] = $linhaTrim;
            }
        }

        $mensagem = trim(implode("\n", $buffer));
        return $mensagem !== '' ? $mensagem : null;
    }

    private function isObservacaoMarker(string $linha): bool
    {
        $markers = ['Origem:', 'Imov', 'Veic', 'Anunc', 'Referenc'];
        foreach ($markers as $marker) {
            if (stripos($linha, $marker) !== false) {
                return true;
            }
        }

        return false;
    }
}
