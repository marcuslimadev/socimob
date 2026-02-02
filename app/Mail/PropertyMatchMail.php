<?php

namespace App\Mail;

use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PropertyMatchMail extends Mailable
{
    use Queueable, SerializesModels;

    public $notification;
    public $property;
    public $intention;

    /**
     * Create a new message instance.
     */
    public function __construct(Notification $notification)
    {
        $this->notification = $notification;
        $this->property = $notification->property;
        $this->intention = $notification->intention;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $tenant = $this->intention?->tenant;
        $fromEmail = $tenant?->contact_email ?? env('MAIL_FROM_ADDRESS', 'noreply@socimob.com');
        $fromName = $tenant?->name ?? env('MAIL_FROM_NAME', 'SOCIMOB');

        return $this->from($fromEmail, $fromName)
                    ->subject($this->notification->title ?? 'Encontramos um imóvel para você!')
                    ->view('emails.property_match');
    }
}
