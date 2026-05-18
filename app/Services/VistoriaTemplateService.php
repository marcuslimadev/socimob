<?php

namespace App\Services;

use App\Models\Vistoria;
use App\Models\VistoriaTemplate;

class VistoriaTemplateService
{
    public function aplicar(Vistoria $vistoria, VistoriaTemplate $template): void
    {
        foreach (($template->conteudo_json['ambientes'] ?? []) as $ambienteIndex => $ambienteData) {
            $ambiente = $vistoria->ambientes()->create([
                'nome' => $ambienteData['nome'] ?? 'Ambiente',
                'ordem' => $ambienteData['ordem'] ?? $ambienteIndex,
                'estado_geral' => $ambienteData['estado_geral'] ?? null,
                'pintura_estado' => $ambienteData['pintura_estado'] ?? null,
                'limpeza_estado' => $ambienteData['limpeza_estado'] ?? null,
                'observacoes' => $ambienteData['observacoes'] ?? null,
            ]);

            foreach (($ambienteData['itens'] ?? []) as $itemIndex => $itemData) {
                $ambiente->itens()->create([
                    'nome' => $itemData['nome'] ?? 'Item',
                    'descricao' => $itemData['descricao'] ?? null,
                    'estado' => $itemData['estado'] ?? 'nao_aplicavel',
                    'ordem' => $itemData['ordem'] ?? $itemIndex,
                ]);
            }
        }
    }
}
