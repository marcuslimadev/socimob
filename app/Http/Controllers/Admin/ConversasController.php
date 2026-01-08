<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Lumen\Routing\Controller as BaseController;

class ConversasController extends BaseController
{
    /**
     * Listar todas as conversas do corretor/admin
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $tenantId = $request->attributes->get('tenant_id');
            
            $query = DB::table('conversas')
                ->leftJoin('leads', 'conversas.lead_id', '=', 'leads.id')
                ->select(
                    'conversas.*',
                    'leads.nome as lead_nome',
                    'leads.telefone as lead_telefone',
                    'leads.email as lead_email'
                )
                ->where('conversas.tenant_id', $tenantId);
            
            // Se for corretor, filtrar apenas suas conversas
            if ($user->role === 'corretor') {
                $query->where('conversas.corretor_id', $user->id);
            }
            
            // Ordenar por última atividade
            $conversas = $query->orderBy('conversas.ultima_atividade', 'desc')
                ->orderBy('conversas.created_at', 'desc')
                ->get();
            
            // Adicionar última mensagem e contador de não lidas
            foreach ($conversas as &$conversa) {
                // Última mensagem
                $ultimaMensagem = DB::table('mensagens')
                    ->where('conversa_id', $conversa->id)
                    ->orderBy('created_at', 'desc')
                    ->first();
                
                $conversa->ultima_mensagem = $ultimaMensagem 
                    ? substr($ultimaMensagem->content, 0, 100) 
                    : null;
                
                // Contar não lidas (incoming que não têm read_at)
                $conversa->mensagens_nao_lidas = DB::table('mensagens')
                    ->where('conversa_id', $conversa->id)
                    ->where('direction', 'incoming')
                    ->whereNull('read_at')
                    ->count();
            }
            
            return response()->json([
                'success' => true,
                'data' => $conversas
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar conversas',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Listar mensagens de uma conversa
     */
    public function mensagens(Request $request, $conversaId)
    {
        try {
            $tenantId = $request->attributes->get('tenant_id');
            
            // Verificar se conversa existe e pertence ao tenant
            $conversa = DB::table('conversas')
                ->where('id', $conversaId)
                ->where('tenant_id', $tenantId)
                ->first();
            
            if (!$conversa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversa não encontrada'
                ], 404);
            }
            
            // Buscar mensagens
            $mensagens = DB::table('mensagens')
                ->where('conversa_id', $conversaId)
                ->orderBy('created_at', 'asc')
                ->get();
            
            // Marcar mensagens incoming como lidas
            DB::table('mensagens')
                ->where('conversa_id', $conversaId)
                ->where('direction', 'incoming')
                ->whereNull('read_at')
                ->update(['read_at' => now()]);
            
            return response()->json([
                'success' => true,
                'data' => $mensagens
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar mensagens',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Enviar mensagem
     */
    public function enviarMensagem(Request $request, $conversaId)
    {
        try {
            $this->validate($request, [
                'content' => 'required|string'
            ]);
            
            $user = $request->user();
            $tenantId = $request->attributes->get('tenant_id');
            
            // Verificar conversa
            $conversa = DB::table('conversas')
                ->where('id', $conversaId)
                ->where('tenant_id', $tenantId)
                ->first();
            
            if (!$conversa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversa não encontrada'
                ], 404);
            }
            
            // Criar mensagem
            $mensagemId = DB::table('mensagens')->insertGetId([
                'tenant_id' => $tenantId,
                'conversa_id' => $conversaId,
                'direction' => 'outgoing',
                'message_type' => 'text',
                'content' => $request->content,
                'status' => 'queued',
                'created_at' => now(),
                'updated_at' => now()
            ]);
            
            // Enviar via Twilio
            try {
                $twilioService = app(\App\Services\TwilioService::class);
                $resultado = $twilioService->sendMessage(
                    $conversa->telefone,
                    $request->content
                );
                
                // Atualizar com message_sid e status
                DB::table('mensagens')
                    ->where('id', $mensagemId)
                    ->update([
                        'message_sid' => $resultado['sid'] ?? null,
                        'status' => 'sent',
                        'sent_at' => now(),
                        'updated_at' => now()
                    ]);
                
            } catch (\Exception $e) {
                // Marcar como erro mas não falhar o request
                DB::table('mensagens')
                    ->where('id', $mensagemId)
                    ->update([
                        'status' => 'failed',
                        'updated_at' => now()
                    ]);
                
                \Illuminate\Support\Facades\Log::error('Erro ao enviar via Twilio', [
                    'mensagem_id' => $mensagemId,
                    'erro' => $e->getMessage()
                ]);
            }
            
            // Atualizar última atividade da conversa
            DB::table('conversas')
                ->where('id', $conversaId)
                ->update([
                    'ultima_atividade' => now(),
                    'updated_at' => now()
                ]);
            
            $mensagem = DB::table('mensagens')->find($mensagemId);
            
            return response()->json([
                'success' => true,
                'data' => $mensagem
            ]);
            
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Dados inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao enviar mensagem',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Detalhes de uma conversa
     */
    public function show(Request $request, $conversaId)
    {
        try {
            $tenantId = $request->attributes->get('tenant_id');
            
            $conversa = DB::table('conversas')
                ->leftJoin('leads', 'conversas.lead_id', '=', 'leads.id')
                ->leftJoin('users as corretor', 'conversas.corretor_id', '=', 'corretor.id')
                ->select(
                    'conversas.*',
                    'leads.nome as lead_nome',
                    'leads.telefone as lead_telefone',
                    'leads.email as lead_email',
                    'leads.quartos',
                    'leads.localizacao',
                    'leads.budget_min',
                    'leads.budget_max',
                    'corretor.name as corretor_nome'
                )
                ->where('conversas.id', $conversaId)
                ->where('conversas.tenant_id', $tenantId)
                ->first();
            
            if (!$conversa) {
                return response()->json([
                    'success' => false,
                    'message' => 'Conversa não encontrada'
                ], 404);
            }
            
            return response()->json([
                'success' => true,
                'data' => $conversa
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Erro ao carregar conversa',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
