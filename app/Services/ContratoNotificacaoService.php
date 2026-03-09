<?php
namespace App\Services;

use App\Jobs\SendWhatsAppMessageJob;
use App\Mail\CobrancaVencimentoMail;
use App\Mail\ContratoExpiraMail;
use App\Mail\InadimplenciaMail;
use App\Models\CobrancaContrato;
use App\Models\ContratoLocacao;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends payment reminders, overdue notices and contract-expiry alerts
 * via WhatsApp and e-mail.
 */
class ContratoNotificacaoService
{
    /**
     * Send a payment reminder for a charge that will soon expire.
     */
    public function enviarLembretePagamento(CobrancaContrato $cobranca): void
    {
        $locatario = $cobranca->contrato?->locatario;
        if (!$locatario) {
            return;
        }

        $vencimento = $cobranca->data_vencimento
            ? \Carbon\Carbon::parse($cobranca->data_vencimento)->format('d/m/Y')
            : 'em breve';

        $valorFormatado = 'R$ ' . number_format((float) $cobranca->valor_total, 2, ',', '.');

        // WhatsApp
        if ($locatario->whatsapp || $locatario->telefone) {
            $telefone = $locatario->whatsapp ?: $locatario->telefone;
            $mensagem = "Olá {$locatario->nome}, lembramos que seu aluguel de {$valorFormatado} vence em {$vencimento}. Em caso de dúvidas, entre em contato conosco.";

            dispatch(new SendWhatsAppMessageJob($telefone, $mensagem));
        }

        // E-mail
        if ($locatario->email) {
            try {
                Mail::to($locatario->email)->send(new CobrancaVencimentoMail($cobranca));
            } catch (\Throwable $e) {
                Log::error('ContratoNotificacaoService: falha ao enviar e-mail de vencimento', [
                    'cobranca_id' => $cobranca->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Send an overdue payment notice.
     */
    public function enviarAvisoInadimplencia(CobrancaContrato $cobranca): void
    {
        $locatario = $cobranca->contrato?->locatario;
        if (!$locatario) {
            return;
        }

        $diasAtraso = $cobranca->data_vencimento
            ? (int) \Carbon\Carbon::today()->diffInDays($cobranca->data_vencimento)
            : 0;

        $valorFormatado = 'R$ ' . number_format((float) $cobranca->valor_total, 2, ',', '.');

        if ($locatario->whatsapp || $locatario->telefone) {
            $telefone = $locatario->whatsapp ?: $locatario->telefone;
            $mensagem = "Olá {$locatario->nome}, identificamos que seu aluguel de {$valorFormatado} está em atraso há {$diasAtraso} dia(s). Entre em contato para regularizar.";

            dispatch(new SendWhatsAppMessageJob($telefone, $mensagem));
        }

        if ($locatario->email) {
            try {
                Mail::to($locatario->email)->send(new InadimplenciaMail($cobranca, $diasAtraso));
            } catch (\Throwable $e) {
                Log::error('ContratoNotificacaoService: falha ao enviar e-mail de inadimplência', [
                    'cobranca_id' => $cobranca->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * Notify both locador and locatario that the contract is about to expire.
     */
    public function enviarAvisoExpiracaoContrato(ContratoLocacao $contrato, int $diasRestantes): void
    {
        foreach ([$contrato->locador, $contrato->locatario] as $pessoa) {
            if (!$pessoa) {
                continue;
            }

            $fimFormatado = $contrato->fim?->format('d/m/Y') ?? 'data indefinida';
            $mensagem = "Informamos que o contrato #{$contrato->numero_contrato} expira em {$diasRestantes} dia(s) ({$fimFormatado}). Entre em contato para renovação.";

            if ($pessoa->whatsapp || $pessoa->telefone) {
                $telefone = $pessoa->whatsapp ?: $pessoa->telefone;
                dispatch(new SendWhatsAppMessageJob($telefone, $mensagem));
            }

            if ($pessoa->email) {
                try {
                    Mail::to($pessoa->email)->send(new ContratoExpiraMail($contrato, $diasRestantes));
                } catch (\Throwable $e) {
                    Log::error('ContratoNotificacaoService: falha ao enviar e-mail de expiração', [
                        'contrato_id' => $contrato->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Send a payment confirmation notification to the locatario.
     */
    public function enviarConfirmacaoPagamento(CobrancaContrato $cobranca): void
    {
        $locatario = $cobranca->contrato?->locatario;
        if (!$locatario) {
            return;
        }

        $valorFormatado = 'R$ ' . number_format((float) ($cobranca->valor_pago ?? $cobranca->valor_total), 2, ',', '.');
        $competencia = $cobranca->competencia ?? '';

        if ($locatario->whatsapp || $locatario->telefone) {
            $telefone = $locatario->whatsapp ?: $locatario->telefone;
            $mensagem = "Olá {$locatario->nome}, confirmamos o recebimento do seu pagamento de {$valorFormatado} referente à competência {$competencia}. Obrigado!";

            dispatch(new SendWhatsAppMessageJob($telefone, $mensagem));
        }
    }
}
