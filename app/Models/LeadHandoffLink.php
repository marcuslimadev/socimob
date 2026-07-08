<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Link curto (dominio.com/hl/{code}) que redireciona para wa.me/{client_phone}
 * com a saudação já preenchida, usado nas notificações de handoff de leads.
 */
class LeadHandoffLink extends Model
{
    protected $fillable = [
        'tenant_id',
        'lead_id',
        'code',
        'client_phone',
        'message',
        'recipient_name',
        'used_at',
    ];

    protected $casts = [
        'used_at' => 'datetime',
    ];
}
