<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LeadPropertyMatch extends Model
{
    protected $table = 'lead_property_matches';
    
    public $timestamps = false;
    
    protected $fillable = [
        'lead_id',
        'property_id',
        'conversa_id',
        'match_score',
        'pdf_sent',
        'pdf_sent_at',
        'pdf_path',
        'visualizado',
        'interesse',
        'feedback'
    ];
    
    protected $casts = [
        'match_score' => 'decimal:2',
        'pdf_sent' => 'boolean',
        'visualizado' => 'boolean',
        'pdf_sent_at' => 'datetime',
        'created_at' => 'datetime'
    ];
    
    // Relacionamentos
    public function lead()
    {
        return $this->belongsTo(Lead::class, 'lead_id');
    }
    
    public function property()
    {
        return $this->belongsTo(Property::class, 'property_id');
    }
    
    public function conversa()
    {
        return $this->belongsTo(Conversa::class, 'conversa_id');
    }
}
