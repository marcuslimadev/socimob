<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChamadoAnexo;
use App\Models\ChamadoMensagem;
use App\Models\ChamadoOperacional;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ChamadosOperacionaisController extends Controller
{
    public function index(Request $request)
    {
        $query = ChamadoOperacional::query()
            ->with(['mensagens', 'anexos'])
            ->orderByDesc('id');

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        return response()->json([
            'success' => true,
            'items' => $query->limit(300)->get(),
        ]);
    }

    public function update(Request $request, int $id)
    {
        $item = ChamadoOperacional::find($id);
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Chamado não encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'nullable|string|max:30',
            'prioridade' => 'nullable|string|max:20',
            'categoria' => 'nullable|string|max:80',
            'responsavel_user_id' => 'nullable|integer',
            'metadata' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $item->update($validator->validated());

        if ($item->status === 'resolvido' && !$item->resolvido_em) {
            $item->resolvido_em = now();
            $item->save();
        }

        return response()->json(['success' => true, 'item' => $item->fresh()]);
    }

    public function adicionarMensagem(Request $request, int $id)
    {
        $item = ChamadoOperacional::find($id);
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Chamado não encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'mensagem' => 'required|string',
            'interna' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $mensagem = ChamadoMensagem::create([
            'chamado_id' => $item->id,
            'autor_user_id' => $request->user()?->id,
            'interna' => (bool) $request->input('interna', false),
            'mensagem' => $request->input('mensagem'),
        ]);

        if (!$item->primeira_resposta_em) {
            $item->primeira_resposta_em = now();
            $item->save();
        }

        return response()->json(['success' => true, 'item' => $mensagem], 201);
    }

    public function adicionarAnexo(Request $request, int $id)
    {
        $item = ChamadoOperacional::find($id);
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Chamado não encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'mensagem_id' => 'nullable|integer',
            'nome_arquivo' => 'required|string|max:255',
            'mime_type' => 'nullable|string|max:100',
            'tamanho_bytes' => 'nullable|integer|min:0',
            'caminho_arquivo' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $anexo = ChamadoAnexo::create(array_merge($validator->validated(), [
            'chamado_id' => $item->id,
        ]));

        return response()->json(['success' => true, 'item' => $anexo], 201);
    }
}
