<?php

namespace App\Http\Controllers\Portal;

use App\Models\ContratoLocacao;
use App\Models\Pessoa;
use App\Models\RepasseProprietario;
use App\Models\CobrancaContrato;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Portal do Proprietário — acesso de leitura aos dados do proprietário
 */
class PortalProprietarioController
{
    /**
     * Resolver Pessoa do proprietário autenticado.
     * Busca a Pessoa via email do User autenticado, garantindo acesso multi-tenant seguro.
     */
    private function resolverProprietario(Request $request): ?Pessoa
    {
        $user = $request->user();

        if (!$user instanceof User) {
            return null;
        }

        return Pessoa::where('tenant_id', $user->tenant_id)
            ->whereRaw('LOWER(email) = ?', [strtolower($user->email)])
            ->first();
    }

    /**
     * GET /api/portal/proprietario/dashboard
     */
    public function dashboard(Request $request)
    {
        $proprietario = $this->resolverProprietario($request);

        if (!$proprietario) {
            return response()->json(['success' => false, 'message' => 'Proprietário não encontrado'], 404);
        }

        $tenantId = $proprietario->tenant_id;

        $contratos = ContratoLocacao::where('tenant_id', $tenantId)
            ->where('locador_pessoa_id', $proprietario->id)
            ->whereIn('status', ['ativo', 'ativo_vencido'])
            ->with(['imovel:id,titulo,codigo,endereco', 'locatario:id,nome'])
            ->get();

        $contratoIds = $contratos->pluck('id');

        $totalReceber = RepasseProprietario::whereIn('contrato_id', $contratoIds)
            ->where('tenant_id', $tenantId)
            ->where('status', '!=', 'pago')
            ->sum('valor_repasse');

        $repassesRecentes = RepasseProprietario::whereIn('contrato_id', $contratoIds)
            ->where('tenant_id', $tenantId)
            ->with(['contrato.imovel:id,titulo,codigo'])
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'proprietario' => [
                'id'   => $proprietario->id,
                'nome' => $proprietario->nome,
            ],
            'resumo' => [
                'total_contratos' => $contratos->count(),
                'total_a_receber' => (float) $totalReceber,
            ],
            'contratos_ativos'  => $contratos,
            'repasses_recentes' => $repassesRecentes,
        ]);
    }

    /**
     * GET /api/portal/proprietario/contratos
     */
    public function contratos(Request $request)
    {
        $proprietario = $this->resolverProprietario($request);

        if (!$proprietario) {
            return response()->json(['success' => false, 'message' => 'Proprietário não encontrado'], 404);
        }

        $contratos = ContratoLocacao::where('tenant_id', $proprietario->tenant_id)
            ->where('locador_pessoa_id', $proprietario->id)
            ->with([
                'imovel:id,titulo,codigo,endereco',
                'locatario:id,nome,email,telefone',
            ])
            ->orderByDesc('id')
            ->get();

        return response()->json(['success' => true, 'items' => $contratos]);
    }

    /**
     * GET /api/portal/proprietario/contratos/{id}
     */
    public function contrato(Request $request, int $id)
    {
        $proprietario = $this->resolverProprietario($request);

        if (!$proprietario) {
            return response()->json(['success' => false, 'message' => 'Proprietário não encontrado'], 404);
        }

        $contrato = ContratoLocacao::where('tenant_id', $proprietario->tenant_id)
            ->where('locador_pessoa_id', $proprietario->id)
            ->where('id', $id)
            ->with([
                'imovel:id,titulo,codigo,endereco',
                'locatario:id,nome,email,telefone',
                'reajustes',
                'repasses',
            ])
            ->first();

        if (!$contrato) {
            return response()->json(['success' => false, 'message' => 'Contrato não encontrado'], 404);
        }

        return response()->json(['success' => true, 'item' => $contrato]);
    }

    /**
     * GET /api/portal/proprietario/repasses
     */
    public function repasses(Request $request)
    {
        $proprietario = $this->resolverProprietario($request);

        if (!$proprietario) {
            return response()->json(['success' => false, 'message' => 'Proprietário não encontrado'], 404);
        }

        $contratoIds = ContratoLocacao::where('tenant_id', $proprietario->tenant_id)
            ->where('locador_pessoa_id', $proprietario->id)
            ->pluck('id');

        $repasses = RepasseProprietario::whereIn('contrato_id', $contratoIds)
            ->where('tenant_id', $proprietario->tenant_id)
            ->with(['contrato.imovel:id,titulo,codigo'])
            ->orderByDesc('competencia')
            ->get();

        return response()->json(['success' => true, 'items' => $repasses]);
    }

    /**
     * GET /api/portal/proprietario/cobrancas
     * Cobranças dos imóveis do proprietário (para acompanhamento de inadimplência)
     */
    public function cobrancas(Request $request)
    {
        $proprietario = $this->resolverProprietario($request);

        if (!$proprietario) {
            return response()->json(['success' => false, 'message' => 'Proprietário não encontrado'], 404);
        }

        $contratoIds = ContratoLocacao::where('tenant_id', $proprietario->tenant_id)
            ->where('locador_pessoa_id', $proprietario->id)
            ->pluck('id');

        $cobrancas = CobrancaContrato::whereIn('contrato_id', $contratoIds)
            ->with(['contrato.imovel:id,titulo,codigo', 'contrato.locatario:id,nome'])
            ->orderByDesc('vencimento')
            ->limit(100)
            ->get();

        return response()->json(['success' => true, 'items' => $cobrancas]);
    }
}
