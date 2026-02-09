<?php
/**
 * Script de migração: Criar registros em Pessoas para todos os Leads existentes
 * Uso: php migrate_leads_to_pessoas.php
 */

require_once __DIR__ . '/vendor/autoload.php';

use App\Models\Lead;
use App\Models\Pessoa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Bootstrap Lumen
$app = require_once __DIR__ . '/bootstrap/app.php';

echo "╔═══════════════════════════════════════════════════════════════════╗\n";
echo "║   📊 MIGRAÇÃO: Leads → Pessoas                                    ║\n";
echo "╚═══════════════════════════════════════════════════════════════════╝\n\n";

try {
    // Contar leads
    $totalLeads = Lead::count();
    echo "📋 Total de leads encontrados: $totalLeads\n\n";

    if ($totalLeads === 0) {
        echo "⚠️  Nenhum lead encontrado para migrar.\n";
        exit(0);
    }

    $created = 0;
    $updated = 0;
    $skipped = 0;
    $errors = 0;

    echo "═══════════════════════════════════════════════════════════════════\n";
    echo "🔄 Iniciando migração...\n\n";

    Lead::chunk(100, function ($leads) use (&$created, &$updated, &$skipped, &$errors) {
        foreach ($leads as $lead) {
            try {
                // Buscar pessoa existente pelo telefone/whatsapp
                $telefone = $lead->whatsapp ?: $lead->telefone;
                
                if (empty($telefone) && empty($lead->email) && empty($lead->cpf)) {
                    echo "⏭️  Lead #{$lead->id} ({$lead->nome}) - sem telefone, email ou CPF, pulando...\n";
                    $skipped++;
                    continue;
                }

                $pessoa = null;

                // Buscar por telefone/whatsapp
                if (!empty($telefone)) {
                    $pessoa = Pessoa::withoutGlobalScope('tenant')
                        ->where('tenant_id', $lead->tenant_id)
                        ->where(function($q) use ($telefone) {
                            $q->where('telefone', $telefone)
                              ->orWhere('celular', $telefone)
                              ->orWhere('whatsapp', $telefone);
                        })
                        ->first();
                }

                // Se não encontrou por telefone, buscar por email
                if (!$pessoa && !empty($lead->email)) {
                    $pessoa = Pessoa::withoutGlobalScope('tenant')
                        ->where('tenant_id', $lead->tenant_id)
                        ->where('email', $lead->email)
                        ->first();
                }

                // Se não encontrou por email, buscar por CPF
                if (!$pessoa && !empty($lead->cpf)) {
                    $pessoa = Pessoa::withoutGlobalScope('tenant')
                        ->where('tenant_id', $lead->tenant_id)
                        ->where('cpf', $lead->cpf)
                        ->first();
                }

                $dadosPessoa = [
                    'tenant_id' => $lead->tenant_id,
                    'nome' => $lead->nome,
                    'email' => $lead->email,
                    'telefone' => $lead->telefone,
                    'celular' => $lead->whatsapp ?: $lead->telefone,
                    'whatsapp' => $lead->whatsapp,
                    'cpf' => $lead->cpf,
                    'tipo' => 'fisica',
                    'pais' => 'Brasil',
                    'ativo' => true,
                    // Campos CRM do lead
                    'papeis' => ['cliente', 'lead'],
                    'status' => $lead->status,
                    'origem' => $lead->isFromIntegration() ? 'Chaves na Mão' : 'Manual',
                    'corretor_responsavel_id' => $lead->corretor_id,
                    'renda_mensal' => $lead->renda_mensal,
                    'profissao' => $lead->profissao,
                    'observacoes' => $lead->observacoes,
                    'ultimo_contato' => $lead->ultima_interacao,
                    'primeiro_contato' => $lead->primeira_interacao,
                ];

                if ($pessoa) {
                    // Atualizar pessoa existente
                    $pessoa->update($dadosPessoa);
                    echo "✅ Lead #{$lead->id} → Pessoa #$pessoa->id atualizada ({$lead->nome})\n";
                    $updated++;
                    
                    // Atualizar lead com pessoa_id
                    $lead->update(['pessoa_id' => $pessoa->id]);
                } else {
                    // Criar nova pessoa
                    $pessoa = Pessoa::create($dadosPessoa);
                    echo "🆕 Lead #{$lead->id} → Pessoa #$pessoa->id criada ({$lead->nome})\n";
                    $created++;
                    
                    // Atualizar lead com pessoa_id
                    $lead->update(['pessoa_id' => $pessoa->id]);
                }

            } catch (\Exception $e) {
                echo "❌ Erro ao processar Lead #{$lead->id}: {$e->getMessage()}\n";
                Log::error('Erro na migração Lead → Pessoa', [
                    'lead_id' => $lead->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $errors++;
            }
        }
    });

    echo "\n═══════════════════════════════════════════════════════════════════\n";
    echo "✅ MIGRAÇÃO CONCLUÍDA!\n\n";
    echo "📊 Estatísticas:\n";
    echo "   🆕 Pessoas criadas:    $created\n";
    echo "   🔄 Pessoas atualizadas: $updated\n";
    echo "   ⏭️  Leads pulados:      $skipped\n";
    echo "   ❌ Erros:              $errors\n";
    echo "   📋 Total processado:   " . ($created + $updated + $skipped + $errors) . "/$totalLeads\n";
    echo "═══════════════════════════════════════════════════════════════════\n";

} catch (\Exception $e) {
    echo "\n❌ ERRO FATAL: {$e->getMessage()}\n";
    echo "Stack trace:\n{$e->getTraceAsString()}\n";
    exit(1);
}
