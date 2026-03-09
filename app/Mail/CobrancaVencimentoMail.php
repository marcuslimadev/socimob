<?php
namespace App\Mail;

use App\Models\CobrancaContrato;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CobrancaVencimentoMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly CobrancaContrato $cobranca)
    {
    }

    public function build(): static
    {
        $numero = $this->cobranca->contrato?->numero_contrato ?? $this->cobranca->contrato_id;

        return $this->subject("Lembrete de Vencimento - Contrato #{$numero}")
                    ->view('emails.locacao.cobranca_vencimento');
    }
}
