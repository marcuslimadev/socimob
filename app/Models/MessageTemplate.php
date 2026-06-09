<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MessageTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'name',
        'content',
        'category',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
