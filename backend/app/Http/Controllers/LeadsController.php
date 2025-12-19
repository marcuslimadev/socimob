<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use App\Models\Atividade;
use App\Models\Conversa;
use App\Models\Lead;
use App\Models\LeadDocument;
use App\Models\LeadPropertyMatch;
use App\Models\Mensagem;
use App\Services\OpenAIService;

/**
 * Controller de Leads
 */
class LeadsController extends Controller
{
    /**
     * Listar leads
     * GET /api/leads
     */
    public function index(Request $request)
    {
        try {
            $db = app('db');
            $query = $db->table('leads');
            
            // Filtros
            if ($request->status) {
                $query->where('status', $request->status);
            }
            
            if ($request->corretor_id) {
                $query->where('corretor_id', $request->corretor_id);
            }
            
            if ($request->search) {
                $query->where(function($q) use ($request) {
                    $q->where('nome', 'like', '%' . $request->search . '%')
                      ->orWhere('telefone', 'like', '%' . $request->search . '%');
                });
            }
            
            // Ordenação
            $query->orderBy('updated_at', 'desc');
            
            // Get all leads (simplificado - sem paginação por enquanto)
            $leads = $query->get();
            
            // Formatar datas para ISO8601 (compatível com JavaScript)
            $leads = collect($leads)->map(function($lead) {
                if (isset($lead->created_at)) {
                    $lead->created_at = date('c', strtotime($lead->created_at));
                }
                if (isset($lead->updated_at)) {
                    $lead->updated_at = date('c', strtotime($lead->updated_at));
                }
                if (isset($lead->primeira_interacao)) {
                    $lead->primeira_interacao = date('c', strtotime($lead->primeira_interacao));
                }
                if (isset($lead->ultima_interacao)) {
                    $lead->ultima_interacao = date('c', strtotime($lead->ultima_interacao));
                }
                return $lead;
            });
            
            return response()->json([
                'success' => true,
                'data' => $leads
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Detalhes do lead
     * GET /api/leads/{id}
     */
    public function show($id)
    {
        try {
            $lead = Lead::with(['corretor', 'conversas.mensagens'])
                ->findOrFail($id);
            
            // Tentar carregar relacionamentos opcionais
            try {
                $lead->load('propertyMatches.property');
            } catch (\Exception $e) {
                Log::warning('Erro ao carregar propertyMatches', ['error' => $e->getMessage()]);
            }
            
            try {
                $lead->load('documents');
            } catch (\Exception $e) {
                Log::warning('Erro ao carregar documents', ['error' => $e->getMessage()]);
            }
            
            return response()->json([
                'success' => true,
                'data' => $lead
            ]);
        } catch (\Exception $e) {
            Log::error('Erro ao buscar lead', [
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Erro ao buscar lead: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Atualizar lead
     * PUT /api/leads/{id}
     */
    public function update(Request $request, $id)
    {
        $lead = Lead::findOrFail($id);

        if ($request->has('cpf')) {
            $request->merge([
                'cpf' => $request->filled('cpf')
                    ? preg_replace('/\D/', '', $request->input('cpf'))
                    : null,
            ]);
        }

        $data = $this->validate($request, [
            'nome' => 'sometimes|string|max:191',
            'email' => 'nullable|email|max:191',
            'status' => 'sometimes|in:novo,em_atendimento,qualificado,convertido,proposta,fechado,perdido',
            'corretor_id' => 'nullable|integer',
            'budget_min' => 'nullable|numeric|min:0',
            'budget_max' => 'nullable|numeric|min:0',
            'localizacao' => 'nullable|string|max:255',
            'quartos' => 'nullable|integer|min:0',
            'suites' => 'nullable|integer|min:0',
            'garagem' => 'nullable|integer|min:0',
            'cpf' => ['nullable', 'regex:/^\d{11}$/', Rule::unique('leads')->ignore($lead->id)],
            'renda_mensal' => 'nullable|numeric|min:0',
            'estado_civil' => 'nullable|string|max:100',
            'composicao_familiar' => 'nullable|string|max:150',
            'profissao' => 'nullable|string|max:150',
            'fonte_renda' => 'nullable|string|max:150',
            'financiamento_status' => 'nullable|string|max:100',
            'prazo_compra' => 'nullable|string|max:100',
            'objetivo_compra' => 'nullable|string|max:150',
            'preferencia_tipo_imovel' => 'nullable|string|max:150',
            'preferencia_bairro' => 'nullable|string|max:150',
            'preferencia_lazer' => 'nullable|string',
            'preferencia_seguranca' => 'nullable|string',
            'observacoes_cliente' => 'nullable|string',
            'caracteristicas_desejadas' => 'nullable|string',
        ]);

        $lead->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Lead atualizado com sucesso',
            'data' => $lead
        ]);
    }
    
    /**
     * Estatísticas de leads
     * GET /api/leads/stats
     */
    public function stats()
    {
        try {
            $db = app('db');
            $stats = [
                'total' => $db->table('leads')->count(),
                'novos' => $db->table('leads')->where('status', 'novo')->count(),
                'em_atendimento' => $db->table('leads')->where('status', 'em_atendimento')->count(),
                'qualificados' => $db->table('leads')->where('status', 'qualificado')->count(),
                'fechados' => $db->table('leads')->where('status', 'fechado')->count(),
                'hoje' => $db->table('leads')->whereDate('created_at', date('Y-m-d'))->count()
            ];
            
            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualizar estado do lead (para Kanban drag-and-drop)
     * PATCH /api/leads/{id}/state
     */
    public function updateState(Request $request, $id)
    {
        try {
            $lead = Lead::findOrFail($id);
            
            $this->validate($request, [
                'state' => 'required|string|max:2'
            ]);
            
            $lead->state = $request->state;
            $lead->save();
            
            return response()->json([
                'success' => true,
                'message' => 'Estado atualizado com sucesso',
                'data' => $lead
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Atualizar status do funil do lead (para Kanban drag-and-drop)
     * PATCH /api/leads/{id}/status
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $lead = Lead::findOrFail($id);

            $this->validate($request, [
                'status' => 'required|in:novo,em_atendimento,qualificado,proposta,fechado,perdido'
            ]);

            $lead->status = $request->status;
            $lead->updated_at = now();
            $lead->save();

            return response()->json([
                'success' => true,
                'message' => 'Status do funil atualizado com sucesso',
                'data' => $lead
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remover lead e todos os dados relacionados
     * DELETE /api/leads/{id}
     */
    public function destroy($id)
    {
        $lead = Lead::findOrFail($id);

        DB::beginTransaction();

        try {
            $stats = $this->deleteLeads([$lead->id]);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Lead excluído com sucesso',
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remover múltiplos leads e seus relacionamentos
     * DELETE /api/leads (payload: ids[])
     */
    public function bulkDestroy(Request $request)
    {
        $data = $this->validate($request, [
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|distinct'
        ]);

        $leadIds = Lead::whereIn('id', $data['ids'])->pluck('id')->all();

        if (empty($leadIds)) {
            return response()->json([
                'success' => false,
                'error' => 'Nenhum lead encontrado para exclusão'
            ], 404);
        }

        DB::beginTransaction();

        try {
            $stats = $this->deleteLeads($leadIds);
            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Leads excluídos com sucesso',
                'data' => array_merge($stats, ['ids' => $leadIds])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Gerar diagnóstico inteligente do lead sob demanda
     */
    public function diagnostico(Request $request, $id)
    {
        $lead = Lead::with(['conversas.mensagens' => function ($query) {
                $query->orderBy('sent_at');
            }, 'propertyMatches.property', 'documents'])
            ->findOrFail($id);

        $regenerate = $request->boolean('regenerate', false);

        if ($lead->diagnostico_ia && !$regenerate) {
            return response()->json([
                'success' => true,
                'data' => $lead,
            ]);
        }

        $historico = $this->formatConversationHistory($lead);
        $properties = $lead->propertyMatches->map(function ($match) {
            $property = $match->property;

            if (!$property) {
                return null;
            }

            return [
                'codigo' => $property->codigo_imovel ?? null,
                'tipo' => $property->tipo_imovel ?? null,
                'bairro' => $property->bairro ?? null,
                'cidade' => $property->cidade ?? null,
                'valor' => $property->valor_venda,
            ];
        })
            ->filter(fn ($item) => $item && $item['codigo'])
            ->values()
            ->toArray();

        /** @var OpenAIService $openai */
        $openai = app(OpenAIService::class);
        $result = $openai->generateLeadDiagnostic($lead->toArray(), $historico, $properties);

        if (!($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Falha ao gerar diagnóstico',
            ], 422);
        }

        $lead->diagnostico_ia = $result['content'];
        $lead->diagnostico_status = 'concluido';
        $lead->diagnostico_gerado_em = now();
        $lead->save();

        return response()->json([
            'success' => true,
            'data' => $lead->fresh(['documents', 'conversas.mensagens', 'propertyMatches.property'])
        ]);
    }

    private function formatConversationHistory(Lead $lead): string
    {
        $history = '';

        foreach ($lead->conversas as $conversa) {
            foreach ($conversa->mensagens as $mensagem) {
                $direction = $mensagem->direction === 'incoming' ? 'Cliente' : 'Atendente';
                $texto = $mensagem->transcription ?: $mensagem->content;
                if (!$texto) {
                    continue;
                }
                $history .= $direction . ': ' . trim($texto) . "\n";
            }
        }

        return $history;
    }

    private function deleteLeads(array $leadIds): array
    {
        if (empty($leadIds)) {
            return [
                'leads' => 0,
                'conversas' => 0,
                'mensagens' => 0,
                'documentos' => 0,
                'atividades' => 0,
                'matches' => 0,
            ];
        }

        $conversaIds = Conversa::whereIn('lead_id', $leadIds)->pluck('id')->all();

        $mensagensDeletadas = !empty($conversaIds)
            ? Mensagem::whereIn('conversa_id', $conversaIds)->delete()
            : 0;

        $documentosDeletados = LeadDocument::whereIn('lead_id', $leadIds)->delete();
        $atividadesDeletadas = Atividade::whereIn('lead_id', $leadIds)->delete();
        $matchesDeletados = LeadPropertyMatch::whereIn('lead_id', $leadIds)->delete();

        $conversasDeletadas = !empty($conversaIds)
            ? Conversa::whereIn('id', $conversaIds)->delete()
            : 0;

        $leadsDeletados = Lead::whereIn('id', $leadIds)->delete();

        return [
            'leads' => $leadsDeletados,
            'conversas' => $conversasDeletadas,
            'mensagens' => $mensagensDeletadas,
            'documentos' => $documentosDeletados,
            'atividades' => $atividadesDeletadas,
            'matches' => $matchesDeletados,
        ];
    }
}
