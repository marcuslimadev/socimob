<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExtensionSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'extension_id',
        'browser',
        'last_seen_at',
        'status',
    ];

    protected $casts = [
        'last_seen_at' => 'datetime',
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
