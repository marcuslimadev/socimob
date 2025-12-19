<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Conversa extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'lead_id',
        'corretor_id',
        'telefone',
        'status',
        'canal',
        'mensagens',
        'user_id',
        'iniciada_em',
        'finalizada_em',
        'ultima_atividade',
    ];

    protected $casts = [
        'iniciada_em' => 'datetime',
        'finalizada_em' => 'datetime',
        'ultima_atividade' => 'datetime',
    ];

    public function lead()
    {
        return $this->belongsTo(Lead::class);
    }

    public function corretor()
    {
        return $this->belongsTo(User::class, 'corretor_id');
    }

    public function mensagens()
    {
        return $this->hasMany(Mensagem::class);
    }

    public function documents()
    {
        return $this->hasMany(LeadDocument::class);
    }

    public function matches()
    {
        return $this->hasMany(LeadPropertyMatch::class);
    }
}
