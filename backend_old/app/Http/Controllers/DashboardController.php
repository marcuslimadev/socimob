<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Conversa;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Controller do Dashboard
 */
class DashboardController extends Controller
{
    /**
     * Estatísticas gerais
     * GET /api/dashboard/stats
     */
    public function stats()
    {
        try {
            $db = app('db');
            $stats = [
                'leads' => [
                    'total' => $db->table('leads')->count(),
                    'novos' => $db->table('leads')->where('status', 'novo')->count(),
                    'em_atendimento' => $db->table('leads')->where('status', 'em_atendimento')->count(),
                    'qualificados' => $db->table('leads')->where('status', 'qualificado')->count(),
                    'fechados_mes' => $db->table('leads')
                        ->where('status', 'fechado')
                        ->whereRaw('EXTRACT(MONTH FROM updated_at) = ?', [date('m')])
                        ->count()
                ],
                'conversas' => [
                    'ativas' => $db->table('conversas')->where('status', 'ativa')->count(),
                    'hoje' => $db->table('conversas')->whereDate('iniciada_em', date('Y-m-d'))->count(),
                    'aguardando' => $db->table('conversas')->where('status', 'aguardando_corretor')->count()
                ],
                'corretores' => [
                    'total' => $db->table('users')->where('tipo', 'corretor')->where('ativo', true)->count(),
                    'online' => 0
                ]
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
     * Gráfico de atendimentos (últimos 7 dias)
     * GET /api/dashboard/chart/atendimentos
     */
    public function chartAtendimentos()
    {
        $db = app('db');
        $dataInicio = date('Y-m-d', strtotime('-7 days'));
        $dados = $db->table('conversas')
            ->select($db->raw('DATE(iniciada_em) as data'), $db->raw('COUNT(*) as total'))
            ->where('iniciada_em', '>=', $dataInicio)
            ->groupBy('data')
            ->orderBy('data')
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => $dados
        ]);
    }
    
    /**
     * Atividades recentes
     * GET /api/dashboard/atividades
     */
    public function atividades()
    {
        try {
            $db = app('db');
            $conversas = $db->table('conversas')
                ->leftJoin('leads', 'conversas.lead_id', '=', 'leads.id')
                ->select(
                    'conversas.id as conversa_id',
                    'conversas.lead_id',
                    'conversas.telefone',
                    'conversas.iniciada_em',
                    'leads.nome as lead_nome'
                )
                ->orderBy('conversas.iniciada_em', 'desc')
                ->limit(10)
                ->get()
                ->map(function($conv) {
                    return [
                        'tipo' => 'nova_conversa',
                        'descricao' => 'Nova conversa iniciada com ' . ($conv->lead_nome ?? $conv->telefone),
                        'timestamp' => $conv->iniciada_em,
                        'data' => [
                            'conversa_id' => $conv->conversa_id,
                            'lead_id' => $conv->lead_id
                        ]
                    ];
                });
            
            return response()->json([
                'success' => true,
                'data' => $conversas
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
