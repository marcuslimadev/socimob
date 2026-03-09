<?php
namespace App\Mail;

use App\Models\ContratoLocacao;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContratoExpiraMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ContratoLocacao $contrato,
        public readonly int $diasRestantes,
    ) {
    }

    public function build(): static
    {
        $numero = $this->contrato->numero_contrato ?? $this->contrato->id;

        return $this->subject("Aviso de Expiração de Contrato #{$numero}")
                    ->view('emails.locacao.contrato_expira');
    }
}
