<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExtensionConsentLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'consent_version',
        'consent_text_hash',
        'accepted_at',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
