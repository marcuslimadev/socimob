<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class EmailService
{
    /**
     * Enviar e-mail de nova solicitação de vistoria
     */
    public function enviarNovaSolicitacaoVistoria($solicitacao, $destinatarios = [])
    {
        try {
            $assunto = "Nova Solicitação de Vistoria #{$solicitacao->id}";
            $mensagem = "
                <h2>Nova Solicitação de Vistoria</h2>
                <p><strong>Código:</strong> " . ($solicitacao->codigo ?? $solicitacao->id) . "</p>
                <p><strong>Cliente:</strong> {$solicitacao->cliente_nome}</p>
                <p><strong>Tipo:</strong> {$solicitacao->tipo}</p>
                <p><strong>Status:</strong> {$solicitacao->status}</p>
                <p><strong>Data:</strong> " . date('d/m/Y H:i', strtotime($solicitacao->created_at)) . "</p>
            ";

            return $this->enviarEmail($destinatarios, $assunto, $mensagem);
        } catch (\Exception $e) {
            Log::error('Erro ao enviar email de nova solicitação de vistoria', [
                'error' => $e->getMessage(),
                'solicitacao_id' => $solicitacao->id
            ]);
            return false;
        }
    }

    /**
     * Enviar e-mail de alteração de status de vistoria
     */
    public function enviarStatusVistoriaAlterado($solicitacao, $statusAntigo, $statusNovo, $destinatarios = [])
    {
        try {
            $assunto = "Vistoria #{$solicitacao->id} - Status Alterado";
            $mensagem = "
                <h2>Status da Vistoria Alterado</h2>
                <p><strong>Código:</strong> " . ($solicitacao->codigo ?? $solicitacao->id) . "</p>
                <p><strong>Cliente:</strong> {$solicitacao->cliente_nome}</p>
                <p><strong>Status Anterior:</strong> {$statusAntigo}</p>
                <p><strong>Novo Status:</strong> {$statusNovo}</p>
                <p><strong>Data da Alteração:</strong> " . date('d/m/Y H:i') . "</p>
            ";

            return $this->enviarEmail($destinatarios, $assunto, $mensagem);
        } catch (\Exception $e) {
            Log::error('Erro ao enviar email de status alterado', [
                'error' => $e->getMessage(),
                'solicitacao_id' => $solicitacao->id
            ]);
            return false;
        }
    }

    /**
     * Enviar e-mail de documento enviado para assinatura
     */
    public function enviarDocumentoParaAssinatura($documento, $assinantes = [])
    {
        try {
            foreach ($assinantes as $assinante) {
                if (empty($assinante['email'])) continue;

                $assunto = "Documento para Assinatura: {$documento->titulo}";
                $mensagem = "
                    <h2>Documento Aguardando Assinatura</h2>
                    <p>Olá, <strong>" . ($assinante['nome'] ?? 'Assinante') . "</strong></p>
                    <p>Você possui um documento aguardando assinatura:</p>
                    <p><strong>Título:</strong> {$documento->titulo}</p>
                    <p><strong>Tipo:</strong> " . ($documento->tipo ?? 'Não especificado') . "</p>
                    <p><strong>Descrição:</strong> " . ($documento->descricao ?? 'Não especificado') . "</p>
                    <br>
                    <p>Por favor, acesse o sistema para realizar a assinatura.</p>
                ";

                $this->enviarEmail([$assinante['email']], $assunto, $mensagem);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Erro ao enviar email de documento para assinatura', [
                'error' => $e->getMessage(),
                'documento_id' => $documento->id
            ]);
            return false;
        }
    }

    /**
     * Enviar e-mail de assinatura concluída
     */
    public function enviarAssinaturaConcluida($documento, $destinatarios = [])
    {
        try {
            $assunto = "Assinatura Concluída: {$documento->titulo}";
            $mensagem = "
                <h2>Assinatura de Documento Concluída</h2>
                <p><strong>Título:</strong> {$documento->titulo}</p>
                <p><strong>Tipo:</strong> " . ($documento->tipo ?? 'Não especificado') . "</p>
                <p><strong>Data de Conclusão:</strong> " . date('d/m/Y H:i', strtotime($documento->data_conclusao)) . "</p>
                <br>
                <p>Todas as assinaturas foram coletadas com sucesso.</p>
            ";

            return $this->enviarEmail($destinatarios, $assunto, $mensagem);
        } catch (\Exception $e) {
            Log::error('Erro ao enviar email de assinatura concluída', [
                'error' => $e->getMessage(),
                'documento_id' => $documento->id
            ]);
            return false;
        }
    }

    /**
     * Método genérico para enviar e-mail
     */
    private function enviarEmail($destinatarios, $assunto, $mensagem)
    {
        if (empty($destinatarios)) {
            Log::warning('Tentativa de enviar email sem destinatários', ['assunto' => $assunto]);
            return false;
        }

        // Se não tiver configuração de email, apenas loga
        if (empty(env('MAIL_HOST'))) {
            Log::info('Email não enviado (SMTP não configurado)', [
                'destinatarios' => $destinatarios,
                'assunto' => $assunto
            ]);
            return true; // Retorna true para não bloquear o fluxo
        }

        try {
            Mail::send([], [], function ($message) use ($destinatarios, $assunto, $mensagem) {
                $message->to($destinatarios)
                    ->subject($assunto)
                    ->html($mensagem);
            });

            Log::info('Email enviado com sucesso', [
                'destinatarios' => $destinatarios,
                'assunto' => $assunto
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Erro ao enviar email', [
                'error' => $e->getMessage(),
                'destinatarios' => $destinatarios,
                'assunto' => $assunto
            ]);
            return false;
        }
    }
}
