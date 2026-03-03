<?php
namespace App\Http\Controllers\Portal;
use App\Http\Controllers\Controller;


use App\Models\ChamadoMensagem;
use App\Models\ChamadoOperacional;
use App\Models\Pessoa;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ChamadosController extends Controller
{
    public function index(Request $request)
    {
        $pessoa = $this->resolverPessoaAutenticada($request);
        if (!$pessoa) {
            return response()->json(['success' => false, 'message' => 'Pessoa não vinculada ao usuário'], 404);
        }

        $items = ChamadoOperacional::with(['mensagens', 'anexos'])
            ->where('aberto_por_pessoa_id', $pessoa->id)
            ->orderByDesc('id')
            ->get();

        return response()->json(['success' => true, 'items' => $items]);
    }

    public function store(Request $request)
    {
        $pessoa = $this->resolverPessoaAutenticada($request);
        if (!$pessoa) {
            return response()->json(['success' => false, 'message' => 'Pessoa não vinculada ao usuário'], 404);
        }

        $validator = Validator::make($request->all(), [
            'contrato_id' => 'nullable|integer',
            'cobranca_id' => 'nullable|integer',
            'assunto' => 'required|string|max:150',
            'categoria' => 'nullable|string|max:80',
            'prioridade' => 'nullable|in:baixa,media,alta,urgente',
            'descricao' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $protocolo = 'CH-' . now()->format('Ymd') . '-' . str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);

        $item = ChamadoOperacional::create(array_merge($validator->validated(), [
            'aberto_por_pessoa_id' => $pessoa->id,
            'status' => 'aberto',
            'protocolo' => $protocolo,
        ]));

        ChamadoMensagem::create([
            'chamado_id' => $item->id,
            'autor_pessoa_id' => $pessoa->id,
            'interna' => false,
            'mensagem' => $request->input('descricao'),
        ]);

        return response()->json(['success' => true, 'item' => $item->fresh('mensagens')], 201);
    }

    public function mensagens(Request $request, int $id)
    {
        $pessoa = $this->resolverPessoaAutenticada($request);
        if (!$pessoa) {
            return response()->json(['success' => false, 'message' => 'Pessoa não vinculada ao usuário'], 404);
        }

        $item = ChamadoOperacional::where('aberto_por_pessoa_id', $pessoa->id)
            ->find($id);

        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Chamado não encontrado'], 404);
        }

        return response()->json([
            'success' => true,
            'items' => $item->mensagens()->orderBy('id')->get(),
        ]);
    }

    public function adicionarMensagem(Request $request, int $id)
    {
        $pessoa = $this->resolverPessoaAutenticada($request);
        if (!$pessoa) {
            return response()->json(['success' => false, 'message' => 'Pessoa não vinculada ao usuário'], 404);
        }

        $item = ChamadoOperacional::where('aberto_por_pessoa_id', $pessoa->id)
            ->find($id);

        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Chamado não encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'mensagem' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'errors' => $validator->errors()], 422);
        }

        $mensagem = ChamadoMensagem::create([
            'chamado_id' => $item->id,
            'autor_pessoa_id' => $pessoa->id,
            'interna' => false,
            'mensagem' => $request->input('mensagem'),
        ]);

        return response()->json(['success' => true, 'item' => $mensagem], 201);
    }

    private function resolverPessoaAutenticada(Request $request): ?Pessoa
    {
        $user = $request->user();
        if (!$user instanceof User) {
            return null;
        }

        return Pessoa::where('tenant_id', $user->tenant_id)
            ->where(function ($q) use ($user) {
                $q->where('email', $user->email);
                if (!empty($user->phone)) {
                    $q->orWhere('telefone', $user->phone)
                        ->orWhere('whatsapp', $user->phone)
                        ->orWhere('celular', $user->phone);
                }
            })
            ->first();
    }
}
