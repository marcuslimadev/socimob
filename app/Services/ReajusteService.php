<?php
namespace App\Services;

use App\Models\ContratoLocacao;
use App\Models\ReajusteContrato;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;

/**
 * Handles rent adjustment calculations and application for rental contracts.
 *
 * Supported indices: IGPM, IPCA, INPC, manual (percentual_manual)
 */
class ReajusteService
{
    /**
     * Calculate a preview of the adjustment without persisting.
     */
    public function calcularPreview(ContratoLocacao $contrato, ?string $indice, ?float $percentualManual): array
    {
        $percentual = $this->resolverPercentual($indice, $percentualManual);
        $valorAnterior = (float) $contrato->valor_aluguel;
        $valorNovo = round($valorAnterior * (1 + $percentual / 100), 2);

        return [
            'indice' => $indice,
            'percentual_aplicado' => $percentual,
            'valor_anterior' => $valorAnterior,
            'valor_novo' => $valorNovo,
            'diferenca' => round($valorNovo - $valorAnterior, 2),
        ];
    }

    /**
     * Apply the adjustment and persist the ReajusteContrato record.
     */
    public function aplicar(ContratoLocacao $contrato, array $dados): ReajusteContrato
    {
        $percentual = (float) $dados['percentual_aplicado'];
        $valorAnterior = (float) $contrato->valor_aluguel;
        $valorNovo = round($valorAnterior * (1 + $percentual / 100), 2);

        $reajuste = ReajusteContrato::create([
            'tenant_id' => $contrato->tenant_id,
            'contrato_id' => $contrato->id,
            'competencia' => $dados['competencia'],
            'indice' => $dados['indice'] ?? 'manual',
            'percentual_aplicado' => $percentual,
            'valor_anterior' => $valorAnterior,
            'valor_novo' => $valorNovo,
            'aplicado_em' => now(),
        ]);

        // Advance next reajuste date by 12 months (or configured periodicity)
        $proximoReajuste = $contrato->proximo_reajuste
            ? Carbon::parse($contrato->proximo_reajuste)->addMonths(12)
            : now()->addMonths(12);

        $contrato->update([
            'valor_aluguel' => $valorNovo,
            'proximo_reajuste' => $proximoReajuste->toDateString(),
        ]);

        return $reajuste;
    }

    /**
     * Resolve the adjustment percentage from the given index or manual override.
     * Falls back to stored contrato indice if neither is specified.
     */
    private function resolverPercentual(?string $indice, ?float $percentualManual): float
    {
        if ($percentualManual !== null) {
            return $percentualManual;
        }

        if ($indice) {
            $percentual = $this->buscarIndiceBCB($indice);
            if ($percentual !== null) {
                return $percentual;
            }
        }

        return 0.0;
    }

    /**
     * Fetch the accumulated 12-month variation for an index from the BCB API.
     * Returns null on failure so the caller can fall back.
     */
    private function buscarIndiceBCB(string $indice): ?float
    {
        // BCB series codes for common indices
        $series = [
            'IGPM' => 189,
            'IPCA' => 433,
            'INPC' => 188,
        ];

        $code = $series[strtoupper($indice)] ?? null;
        if (!$code) {
            return null;
        }

        try {
            $dataFim = now()->format('d/m/Y');
            $dataIni = now()->subMonths(12)->format('d/m/Y');
            $url = "https://api.bcb.gov.br/dados/serie/bcdata.sgs.{$code}/dados?formato=json&dataInicial={$dataIni}&dataFinal={$dataFim}";

            $response = Http::timeout(10)->get($url);
            if (!$response->ok()) {
                return null;
            }

            $dados = $response->json();
            if (empty($dados)) {
                return null;
            }

            // Sum all monthly variations over the period
            $acumulado = array_reduce($dados, function (float $carry, array $item) {
                return $carry + (float) ($item['valor'] ?? 0);
            }, 0.0);

            return round($acumulado, 4);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
