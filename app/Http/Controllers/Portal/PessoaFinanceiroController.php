<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\CobrancaContrato;
use App\Models\ContratoLocacao;
use App\Models\DocumentoFiscal;
use App\Models\Pessoa;
use App\Models\User;
use Illuminate\Http\Request;

class PessoaFinanceiroController extends Controller
{
    public function meusImoveis(Request $request)
    {
        $pessoa = $this->resolverPessoaAutenticada($request);
        if (!$pessoa) {
            return response()->json(['success' => false, 'message' => 'Pessoa não vinculada ao usuário'], 404);
        }

        $items = ContratoLocacao::with(['imovel:id,titulo,codigo,cidade,bairro', 'locador:id,nome', 'locatario:id,nome'])
            ->where(function ($q) use ($pessoa) {
                $q->where('locador_pessoa_id', $pessoa->id)
                    ->orWhere('locatario_pessoa_id', $pessoa->id);
            })
            ->orderByDesc('id')
            ->get();

        return response()->json(['success' => true, 'items' => $items]);
    }

    public function minhasCobrancas(Request $request)
    {
        $pessoa = $this->resolverPessoaAutenticada($request);
        if (!$pessoa) {
            return response()->json(['success' => false, 'message' => 'Pessoa não vinculada ao usuário'], 404);
        }

        $items = CobrancaContrato::with(['contrato.locador:id,nome', 'contrato.locatario:id,nome', 'documentoFiscal'])
            ->whereHas('contrato', function ($q) use ($pessoa) {
                $q->where('locador_pessoa_id', $pessoa->id)
                    ->orWhere('locatario_pessoa_id', $pessoa->id);
            })
            ->orderByDesc('vencimento')
            ->get();

        return response()->json(['success' => true, 'items' => $items]);
    }

    public function minhasNotasFiscais(Request $request)
    {
        $pessoa = $this->resolverPessoaAutenticada($request);
        if (!$pessoa) {
            return response()->json(['success' => false, 'message' => 'Pessoa não vinculada ao usuário'], 404);
        }

        $items = DocumentoFiscal::with('cobranca.contrato')
            ->where(function ($q) use ($pessoa) {
                $q->where('locador_pessoa_id', $pessoa->id)
                    ->orWhere('locatario_pessoa_id', $pessoa->id);
            })
            ->orderByDesc('id')
            ->get();

        return response()->json(['success' => true, 'items' => $items]);
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
