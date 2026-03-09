<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;

use App\Models\ContratoFiador;
use App\Models\ContratoLocacao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContratoFiadoresController extends Controller
{
    public function index(int $contratoId)
    {
        $contrato = ContratoLocacao::find($contratoId);
        if (!$contrato) {
            return response()->json(['success' => false, 'message' => 'Contrato não encontrado'], 404);
        }

        $fiadores = ContratoFiador::with('pessoa:id,nome,email,telefone,whatsapp,cpf_cnpj')
            ->where('contrato_id', $contratoId)
            ->get();

        return response()->json(['success' => true, 'items' => $fiadores]);
    }

    public function store(Request $request, int $contratoId)
    {
        $contrato = ContratoLocacao::find($contratoId);
        if (!$contrato) {
            return response()->json(['success' => false, 'message' => 'Contrato não encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'pessoa_id' => 'required|integer',
            'tipo_vinculo' => 'nullable|string|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        // Prevent duplicate fiador for same contract
        $exists = ContratoFiador::where('contrato_id', $contratoId)
            ->where('pessoa_id', $validator->validated()['pessoa_id'])
            ->exists();

        if ($exists) {
            return response()->json(['success' => false, 'message' => 'Fiador já vinculado a este contrato'], 409);
        }

        $item = ContratoFiador::create([
            'contrato_id' => $contratoId,
            'pessoa_id' => $validator->validated()['pessoa_id'],
            'tipo_vinculo' => $validator->validated()['tipo_vinculo'] ?? 'fiador',
        ]);

        return response()->json([
            'success' => true,
            'item' => $item->load('pessoa:id,nome,email,telefone,cpf_cnpj'),
        ], 201);
    }

    public function destroy(int $contratoId, int $fiadorId)
    {
        $item = ContratoFiador::where('contrato_id', $contratoId)->find($fiadorId);
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Fiador não encontrado'], 404);
        }

        $item->delete();

        return response()->json(['success' => true]);
    }
}
