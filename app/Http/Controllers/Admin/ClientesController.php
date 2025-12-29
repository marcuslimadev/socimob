<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\User;
use App\Services\LeadCustomerService;
use Illuminate\Http\Request;

class ClientesController extends Controller
{
    private LeadCustomerService $leadCustomerService;

    public function __construct(LeadCustomerService $leadCustomerService)
    {
        $this->leadCustomerService = $leadCustomerService;
    }

    /**
     * GET /api/admin/clientes
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $clientes = app('db')->table('users')
            ->leftJoin('leads', 'leads.user_id', '=', 'users.id')
            ->where('users.tenant_id', $user->tenant_id)
            ->whereIn('users.role', ['client', 'cliente'])
            ->select(
                'users.id',
                'users.name',
                'users.email',
                'users.role',
                'users.is_active',
                'users.created_at',
                'leads.id as lead_id',
                'leads.tenant_id as lead_tenant_id',
                'leads.nome as lead_nome',
                'leads.email as lead_email',
                'leads.telefone as lead_telefone',
                'leads.whatsapp as lead_whatsapp',
                'leads.status as lead_status',
                'leads.observacoes as lead_observacoes',
                'leads.user_id as lead_user_id',
                'leads.corretor_id as lead_corretor_id',
                'leads.created_at as lead_created_at',
                'leads.updated_at as lead_updated_at',
                'leads.budget_min as lead_budget_min',
                'leads.budget_max as lead_budget_max',
                'leads.localizacao as lead_localizacao',
                'leads.quartos as lead_quartos',
                'leads.suites as lead_suites',
                'leads.garagem as lead_garagem',
                'leads.cpf as lead_cpf',
                'leads.renda_mensal as lead_renda_mensal',
                'leads.estado_civil as lead_estado_civil',
                'leads.composicao_familiar as lead_composicao_familiar',
                'leads.profissao as lead_profissao',
                'leads.fonte_renda as lead_fonte_renda',
                'leads.financiamento_status as lead_financiamento_status',
                'leads.prazo_compra as lead_prazo_compra',
                'leads.objetivo_compra as lead_objetivo_compra',
                'leads.preferencia_tipo_imovel as lead_preferencia_tipo_imovel',
                'leads.preferencia_bairro as lead_preferencia_bairro',
                'leads.preferencia_lazer as lead_preferencia_lazer',
                'leads.preferencia_seguranca as lead_preferencia_seguranca',
                'leads.observacoes_cliente as lead_observacoes_cliente',
                'leads.caracteristicas_desejadas as lead_caracteristicas_desejadas',
                'leads.state as lead_state',
                'leads.primeira_interacao as lead_primeira_interacao',
                'leads.ultima_interacao as lead_ultima_interacao',
                'leads.diagnostico_ia as lead_diagnostico_ia',
                'leads.diagnostico_status as lead_diagnostico_status',
                'leads.diagnostico_gerado_em as lead_diagnostico_gerado_em',
                'leads.chaves_na_mao_status as lead_chaves_na_mao_status',
                'leads.chaves_na_mao_sent_at as lead_chaves_na_mao_sent_at',
                'leads.chaves_na_mao_response as lead_chaves_na_mao_response',
                'leads.chaves_na_mao_error as lead_chaves_na_mao_error',
                'leads.chaves_na_mao_retries as lead_chaves_na_mao_retries',
                'leads.whatsapp_name as lead_whatsapp_name'
            )
            ->orderBy('users.created_at', 'desc')
            ->get();

        return response()->json(['data' => $clientes]);
    }

    /**
     * POST /api/admin/clientes/sync
     */
    public function sync(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $source = $request->input('source', 'all');

        $query = Lead::where('tenant_id', $user->tenant_id)
            ->whereNull('user_id');

        if ($source === 'chaves_na_mao') {
            $query->where('observacoes', 'like', '%Chaves na%');
        }

        $leads = $query->get();

        $created = 0;
        $linked = 0;
        $skipped = 0;

        foreach ($leads as $lead) {
            $userResult = $this->leadCustomerService->ensureClientForLead($lead);
            if (!$userResult) {
                $skipped++;
                continue;
            }

            if ($userResult->wasRecentlyCreated) {
                $created++;
            } else {
                $linked++;
            }
        }

        return response()->json([
            'success' => true,
            'processed' => $leads->count(),
            'created' => $created,
            'linked' => $linked,
            'skipped' => $skipped
        ]);
    }
}
