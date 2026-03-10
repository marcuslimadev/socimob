<?php

namespace App\Models;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class ContratoTemplate extends Model
{
    use BelongsToTenant;

    protected $table = 'contrato_templates';

    protected $fillable = [
        'tenant_id',
        'tipo',
        'titulo',
        'intro_texto',
        'clausulas_padrao',
        'rodape_texto',
        'incluir_logo',
        'ativo',
    ];

    protected $casts = [
        'clausulas_padrao' => 'array',
        'incluir_logo'     => 'boolean',
        'ativo'            => 'boolean',
    ];

    /** Retorna os títulos padrão por tipo de documento */
    public static function tituloPadrao(string $tipo): string
    {
        return match ($tipo) {
            'contrato'  => 'Contrato de Locação Residencial',
            'aditivo'   => 'Aditivo Contratual',
            'rescisao'  => 'Termo de Rescisão Contratual',
            'renovacao' => 'Termo de Renovação de Contrato',
            'recibo'    => 'Recibo de Locação',
            default     => 'Documento',
        };
    }
}
