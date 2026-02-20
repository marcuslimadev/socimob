<?php

namespace App\Services;

use App\Models\CobrancaContrato;
use App\Models\ContratoLocacao;
use Carbon\Carbon;

class ContratoCobrancaService
{
    public function gerarOuObterCobranca(ContratoLocacao $contrato, string $competencia): CobrancaContrato
    {
        $cobranca = CobrancaContrato::where('tenant_id', $contrato->tenant_id)
            ->where('contrato_id', $contrato->id)
            ->where('competencia', $competencia)
            ->first();

        if ($cobranca) {
            return $cobranca;
        }

        $base =
            (float) $contrato->valor_aluguel +
            (float) $contrato->valor_condominio +
            (float) $contrato->valor_iptu +
            (float) $contrato->valor_taxa +
            (float) $contrato->valor_seguro;

        $referencia = Carbon::createFromFormat('Y-m', $competencia)->startOfMonth();
        $vencimento = $referencia->copy()->day(min((int) $contrato->dia_vencimento, (int) $referencia->daysInMonth));

        $itens = [
            ['codigo' => 'ALUGUEL', 'descricao' => 'Aluguel', 'valor' => (float) $contrato->valor_aluguel],
            ['codigo' => 'CONDOMINIO', 'descricao' => 'Condomínio', 'valor' => (float) $contrato->valor_condominio],
            ['codigo' => 'IPTU', 'descricao' => 'IPTU', 'valor' => (float) $contrato->valor_iptu],
            ['codigo' => 'TAXA', 'descricao' => 'Taxas', 'valor' => (float) $contrato->valor_taxa],
            ['codigo' => 'SEGURO', 'descricao' => 'Seguro', 'valor' => (float) $contrato->valor_seguro],
        ];

        return CobrancaContrato::create([
            'tenant_id' => $contrato->tenant_id,
            'contrato_id' => $contrato->id,
            'competencia' => $competencia,
            'vencimento' => $vencimento->toDateString(),
            'data_emissao' => now()->toDateString(),
            'status' => 'pendente',
            'valor_base' => $base,
            'desconto' => 0,
            'multa' => 0,
            'juros' => 0,
            'valor_total' => $base,
            'valor_pago' => 0,
            'itens' => $itens,
        ]);
    }
}
