<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;

use App\Models\ContratoLocacao;
use App\Models\ReajusteContrato;
use App\Services\ReajusteService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReajusteContratoController extends Controller
{
    public function __construct(private readonly ReajusteService $reajusteService)
    {
    }

    public function index(int $contratoId)
    {
        $contrato = ContratoLocacao::find($contratoId);
        if (!$contrato) {
            return response()->json(['success' => false, 'message' => 'Contrato não encontrado'], 404);
        }

        $items = ReajusteContrato::where('contrato_id', $contratoId)
            ->orderByDesc('aplicado_em')
            ->get();

        return response()->json(['success' => true, 'items' => $items]);
    }

    public function preview(Request $request, int $contratoId)
    {
        $contrato = ContratoLocacao::find($contratoId);
        if (!$contrato) {
            return response()->json(['success' => false, 'message' => 'Contrato não encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'indice' => 'nullable|string|max:20',
            'percentual_manual' => 'nullable|numeric|min:-100|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $preview = $this->reajusteService->calcularPreview(
            $contrato,
            $validator->validated()['indice'] ?? null,
            $validator->validated()['percentual_manual'] ?? null,
        );

        return response()->json(['success' => true, 'preview' => $preview]);
    }

    public function aplicar(Request $request, int $contratoId)
    {
        $contrato = ContratoLocacao::find($contratoId);
        if (!$contrato) {
            return response()->json(['success' => false, 'message' => 'Contrato não encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'indice' => 'nullable|string|max:20',
            'percentual_aplicado' => 'required|numeric|min:-100|max:100',
            'competencia' => 'required|date_format:Y-m',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $reajuste = $this->reajusteService->aplicar(
            $contrato,
            $validator->validated(),
        );

        return response()->json(['success' => true, 'item' => $reajuste, 'contrato' => $contrato->fresh()], 201);
    }
}
