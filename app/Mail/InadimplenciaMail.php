<?php
namespace App\Mail;

use App\Models\CobrancaContrato;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InadimplenciaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly CobrancaContrato $cobranca,
        public readonly int $diasAtraso,
    ) {
    }

    public function build(): static
    {
        $numero = $this->cobranca->contrato?->numero_contrato ?? $this->cobranca->contrato_id;

        return $this->subject("Aviso de Inadimplência - Contrato #{$numero}")
                    ->view('emails.locacao.inadimplencia');
    }
}
