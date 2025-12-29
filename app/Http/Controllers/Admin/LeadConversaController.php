<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Services\LeadConversationService;
use Illuminate\Http\Request;

class LeadConversaController extends Controller
{
    private LeadConversationService $leadConversationService;

    public function __construct(LeadConversationService $leadConversationService)
    {
        $this->leadConversationService = $leadConversationService;
    }

    /**
     * POST /api/admin/leads/conversas/sync
     */
    public function syncFromLeads(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $source = $request->input('source', 'chaves_na_mao');

        $query = Lead::query()
            ->where('tenant_id', $user->tenant_id)
            ->whereDoesntHave('conversas');

        if ($source === 'chaves_na_mao') {
            $query->where('observacoes', 'like', '%Chaves na%');
        }

        $leads = $query->get();

        $created = 0;
        $skipped = 0;

        foreach ($leads as $lead) {
            $conversa = $this->leadConversationService->ensureConversaForLead($lead, [
                'canal' => $source
            ]);
            if ($conversa) {
                $created++;
            } else {
                $skipped++;
            }
        }

        return response()->json([
            'success' => true,
            'processed' => $leads->count(),
            'created' => $created,
            'skipped' => $skipped
        ]);
    }
}
