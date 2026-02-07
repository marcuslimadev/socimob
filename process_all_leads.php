<?php

/**
 * Script de Processamento em Lote de Leads
 * 
 * Funcionalidades:
 * 1. Criar perfis completos para todos os leads
 * 2. Enviar SMS para leads que ainda não receberam
 * 
 * Uso: php process_all_leads.php [--dry-run] [--only-sms] [--only-profiles]
 */

require __DIR__ . '/bootstrap/app.php';

use App\Models\Lead;
use App\Models\Conversa;
use App\Models\Pessoa;
use App\Services\TwilioService;
use App\Services\SmsShortLinkService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

// Parse arguments
$dryRun = in_array('--dry-run', $argv);
$onlySms = in_array('--only-sms', $argv);
$onlyProfiles = in_array('--only-profiles', $argv);

echo "\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "   PROCESSAMENTO EM LOTE DE LEADS - SOCIMOB\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "\n";

if ($dryRun) {
    echo "🔍 MODO DRY-RUN ATIVADO - Nenhuma alteração será feita\n\n";
}

$startTime = microtime(true);

// ============================================================================
// 1. CRIAR PERFIS COMPLETOS PARA LEADS SEM CONVERSA
// ============================================================================

if (!$onlySms) {
    echo "📋 ETAPA 1: Criando perfis completos para leads\n";
    echo "───────────────────────────────────────────────────────────\n";

    $leadsWithoutConversa = Lead::whereDoesntHave('conversas')
        ->orderBy('created_at', 'desc')
        ->get();

    echo "Leads sem conversa: " . $leadsWithoutConversa->count() . "\n\n";

    $profilesCreated = 0;

    foreach ($leadsWithoutConversa as $lead) {
        echo "  → Lead #{$lead->id}: {$lead->nome} ({$lead->telefone})\n";
        
        if (!$dryRun) {
            try {
                // Verificar se já existe pessoa com este telefone
                $pessoaExistente = Pessoa::where('tenant_id', $lead->tenant_id)
                    ->where(function($query) use ($lead) {
                        $query->where('telefone', $lead->telefone)
                              ->orWhere('celular', $lead->telefone);
                    })
                    ->first();

                if (!$pessoaExistente) {
                    // Criar pessoa a partir do lead
                    $pessoa = Pessoa::create([
                        'tenant_id' => $lead->tenant_id,
                        'nome' => $lead->nome,
                        'telefone' => $lead->telefone,
                        'celular' => $lead->telefone,
                        'email' => $lead->email,
                        'tipo' => 'fisica',
                        'cpf' => $lead->cpf,
                        'renda_mensal' => $lead->renda_mensal,
                        'profissao' => $lead->profissao,
                        'papeis' => json_encode(['lead', 'interessado']),
                        'status' => 'ativo',
                        'origem' => $lead->origem ?? 'sistema',
                        'classificacao' => 'lead',
                        'interesses' => $lead->objetivo_compra,
                        'preferencias' => json_encode([
                            'tipo_imovel' => $lead->preferencia_tipo_imovel,
                            'bairro' => $lead->preferencia_bairro,
                            'quartos' => $lead->quartos,
                            'suites' => $lead->suites,
                            'garagem' => $lead->garagem,
                        ]),
                        'corretor_responsavel_id' => $lead->corretor_id,
                        'observacoes' => $lead->observacoes,
                        'ativo' => true,
                    ]);
                    
                    echo "    ✓ Pessoa criada (ID: {$pessoa->id})\n";
                } else {
                    echo "    ℹ Pessoa já existe (ID: {$pessoaExistente->id})\n";
                }

                // Criar conversa para o lead
                $conversa = Conversa::create([
                    'tenant_id' => $lead->tenant_id,
                    'lead_id' => $lead->id,
                    'telefone' => $lead->telefone,
                    'whatsapp_name' => $lead->nome,
                    'status' => 'ativa',
                    'stage' => 'qualificacao',
                    'canal' => 'manual',
                    'iniciada_em' => $lead->created_at ?? new \DateTime(),
                ]);

                echo "    ✓ Conversa criada (ID: {$conversa->id})\n";
                $profilesCreated++;
            } catch (\Exception $e) {
                echo "    ✗ Erro: " . $e->getMessage() . "\n";
                Log::error("Erro ao criar conversa para lead {$lead->id}", [
                    'error' => $e->getMessage(),
                    'lead_id' => $lead->id,
                ]);
            }
        } else {
            echo "    [DRY-RUN] Pessoa e conversa seriam criadas\n";
            $profilesCreated++;
        }
    }

    echo "\n";
    echo "✅ Perfis criados: {$profilesCreated}\n";
    echo "\n";
}

// ============================================================================
// 2. ENVIAR SMS PARA LEADS PENDENTES
// ============================================================================

if (!$onlyProfiles) {
    echo "📱 ETAPA 2: Enviando SMS para leads pendentes\n";
    echo "───────────────────────────────────────────────────────────\n";

    // Buscar leads que ainda não receberam SMS
    $leadsPendingSms = Lead::where(function($query) {
            $query->where('sms_enviado', false)
                  ->orWhereNull('sms_enviado');
        })
        ->whereNotNull('telefone')
        ->where('telefone', '!=', '')
        ->orderBy('created_at', 'desc')
        ->get();

    echo "Leads pendentes de SMS: " . $leadsPendingSms->count() . "\n\n";

    if ($leadsPendingSms->isEmpty()) {
        echo "✓ Nenhum lead pendente de SMS\n\n";
    } else {
        $twilioService = app(TwilioService::class);
        $shortLinkService = app(SmsShortLinkService::class);
        
        $smsSent = 0;
        $smsErrors = 0;
        $maxPerRun = 100; // Limite de SMS por execução (para evitar exceder quota)

        foreach ($leadsPendingSms as $index => $lead) {
            if ($index >= $maxPerRun) {
                echo "\n⚠️  Limite de {$maxPerRun} SMS atingido. Execute novamente para continuar.\n";
                break;
            }

            echo "  → Lead #{$lead->id}: {$lead->nome} ({$lead->telefone})\n";
            
            if (!$dryRun) {
                try {
                    // Criar short link
                    $shortLink = $shortLinkService->createForLead($lead);
                    
                    // Montar mensagem
                    $tenantName = $lead->tenant?->nome ?? 'SOCIMOB';
                    $shortCode = $shortLink->code;
                    $websiteUrl = config('app.url', 'https://lojadaesquina.store');
                    $whatsappUrl = "{$websiteUrl}/api/w/{$shortCode}";
                    
                    $message = "Olá! Sou a assistente virtual da {$tenantName}. ";
                    $message .= "Vi que você tem interesse em imóveis. ";
                    $message .= "Vamos conversar pelo WhatsApp? ";
                    $message .= "Clique aqui: {$whatsappUrl}";
                    
                    // Enviar SMS
                    $result = $twilioService->sendSMS($lead->telefone, $message);
                    
                    if ($result['success'] ?? false) {
                        // Marcar como enviado
                        $lead->update([
                            'sms_enviado' => true,
                            'sms_enviado_em' => new \DateTime(),
                        ]);
                        
                        echo "    ✓ SMS enviado (Code: {$shortCode})\n";
                        $smsSent++;
                        
                        // Pequeno delay para não sobrecarregar API
                        usleep(500000); // 0.5 segundo
                    } else {
                        echo "    ✗ Erro ao enviar: " . ($result['error'] ?? 'Desconhecido') . "\n";
                        $smsErrors++;
                    }
                } catch (\Exception $e) {
                    echo "    ✗ Exceção: " . $e->getMessage() . "\n";
                    $smsErrors++;
                    Log::error("Erro ao enviar SMS para lead {$lead->id}", [
                        'error' => $e->getMessage(),
                        'lead_id' => $lead->id,
                        'telefone' => $lead->telefone,
                    ]);
                }
            } else {
                echo "    [DRY-RUN] SMS seria enviado\n";
                $smsSent++;
            }
        }

        echo "\n";
        echo "✅ SMS enviados: {$smsSent}\n";
        if ($smsErrors > 0) {
            echo "❌ Erros: {$smsErrors}\n";
        }
        echo "\n";
    }
}

// ============================================================================
// RESUMO
// ============================================================================

$duration = round(microtime(true) - $startTime, 2);

echo "═══════════════════════════════════════════════════════════\n";
echo "   PROCESSAMENTO CONCLUÍDO\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "\n";

if (!$onlySms) {
    echo "Perfis criados: {$profilesCreated}\n";
}

if (!$onlyProfiles) {
    echo "SMS enviados: {$smsSent}\n";
    if (isset($smsErrors) && $smsErrors > 0) {
        echo "SMS com erro: {$smsErrors}\n";
    }
}

echo "Tempo total: {$duration}s\n";
echo "\n";

if ($dryRun) {
    echo "⚠️  MODO DRY-RUN - Nenhuma alteração foi feita no banco de dados\n";
    echo "Execute sem --dry-run para aplicar as mudanças\n";
    echo "\n";
}

echo "Opções disponíveis:\n";
echo "  --dry-run        : Simula execução sem fazer alterações\n";
echo "  --only-sms       : Apenas envia SMS, não cria perfis\n";
echo "  --only-profiles  : Apenas cria perfis, não envia SMS\n";
echo "\n";
