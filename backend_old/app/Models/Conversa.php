<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Conversa extends Model
{
    protected $table = 'conversas';
    
    // Desabilitar timestamps automáticos (created_at/updated_at)
    public $timestamps = false;
    
    protected $fillable = [
        'telefone',
        'lead_id',
        'corretor_id',
        'status',
        'stage',
        'whatsapp_name',
        'profile_pic',
        'contexto_conversa',
        'ultima_mensagem',
        'iniciada_em',
        'ultima_atividade',
        'finalizada_em'
    ];
    
    protected $casts = [
        'iniciada_em' => 'datetime',
        'ultima_atividade' => 'datetime',
        'finalizada_em' => 'datetime'
    ];
    
    // Relacionamentos
    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }
    
    public function corretor()
    {
        return $this->belongsTo(User::class, 'corretor_id');
    }
    
    public function mensagens()
    {
        return $this->hasMany(Mensagem::class, 'conversa_id');
    }
}
