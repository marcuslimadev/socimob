<?php
namespace App\Services;

use App\Models\CobrancaContrato;
use App\Models\ContratoLocacao;
use App\Models\RepasseProprietario;

/**
 * Handles the calculation and creation of owner transfer (repasse) records
 * when a rental charge is marked as paid.
 */
class RepasseService
{
    /**
     * Generate or update a repasse record when a cobrança is paid.
     */
    public function gerarOuAtualizar(ContratoLocacao $contrato, CobrancaContrato $cobranca): RepasseProprietario
    {
        $competencia = $cobranca->competencia;

        $valorAluguelRecebido = (float) ($cobranca->valor_pago ?? $cobranca->valor_total ?? $cobranca->valor_base);

        $percentualComissao = (float) ($contrato->comissao_administracao_percentual ?? 0);
        $valorTaxa = round($valorAluguelRecebido * $percentualComissao / 100, 2);
        $valorRepasse = round($valorAluguelRecebido - $valorTaxa, 2);

        $repasse = RepasseProprietario::firstOrNew([
            'tenant_id' => $contrato->tenant_id,
            'contrato_id' => $contrato->id,
            'competencia' => $competencia,
        ]);

        $repasse->fill([
            'cobranca_id' => $cobranca->id,
            'valor_aluguel_recebido' => $valorAluguelRecebido,
            'valor_taxa_administracao' => $valorTaxa,
            'valor_deducoes' => 0,
            'valor_repasse' => $valorRepasse,
            'status' => $repasse->exists && $repasse->status === 'pago' ? 'pago' : 'pendente',
        ]);

        $repasse->save();

        return $repasse;
    }

    /**
     * Add a deduction (e.g., repair, tax) to an existing repasse and recalculate the net value.
     */
    public function adicionarDeducao(RepasseProprietario $repasse, float $valorDeducao): RepasseProprietario
    {
        $novaDeducao = (float) $repasse->valor_deducoes + $valorDeducao;
        $novoRepasse = (float) $repasse->valor_aluguel_recebido - (float) $repasse->valor_taxa_administracao - $novaDeducao;

        $repasse->update([
            'valor_deducoes' => $novaDeducao,
            'valor_repasse' => max(0, $novoRepasse),
        ]);

        return $repasse->fresh();
    }
}
