<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Atividade extends Model
{
    protected $table = 'atividades';
    
    public $timestamps = false;
    
    protected $fillable = [
        'user_id',
        'lead_id',
        'tipo',
        'descricao',
        'dados',
        'ip_address'
    ];
    
    protected $casts = [
        'dados' => 'array',
        'created_at' => 'datetime'
    ];
    
    // Relacionamentos
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
    
    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }
}
