<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class NewClientInterestMail extends Mailable
{
    use Queueable, SerializesModels;

    public $clientName;
    public $clientEmail;
    public $clientPhone;
    public $propertyTitle;
    public $propertyCode;
    public $matchScore;
    public $leadUrl;
    public $tenantName;

    /**
     * Create a new message instance.
     */
    public function __construct(array $data)
    {
        $this->clientName = $data['client_name'] ?? 'Cliente';
        $this->clientEmail = $data['client_email'] ?? '';
        $this->clientPhone = $data['client_phone'] ?? '';
        $this->propertyTitle = $data['property_title'] ?? 'Imóvel';
        $this->propertyCode = $data['property_code'] ?? '';
        $this->matchScore = $data['match_score'] ?? 0;
        $this->leadUrl = $data['lead_url'] ?? '#';
        $this->tenantName = $data['tenant_name'] ?? 'SOCIMOB';
    }

    /**
     * Build the message.
     */
    public function build()
    {
        return $this->subject("Novo Interesse em Imóvel - {$this->clientName}")
                    ->view('emails.new_client_interest');
    }
}
