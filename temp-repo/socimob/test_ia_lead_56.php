<?php

/**
 * Teste de inicialização de atendimento IA para o lead 56
 */

use App\Models\Lead;
use App\Services\LeadAutomationService;
use App\Services\WhatsAppService;
use App\Services\TwilioService;
use App\Services\OpenAIService;

// Bootstrap da aplicação
$app = require_once __DIR__.'/bootstrap/app.php';
$app->boot();

echo "╔═══════════════════════════════════════════════════════════════╗\n";
echo "║         🤖 TESTE ATENDIMENTO IA - LEAD 56                     ║\n";
echo "╚═══════════════════════════════════════════════════════════════╝\n\n";

try {
    // 1. Buscar lead
    echo "📋 ETAPA 1: Buscando lead 56...\n";
    $lead = Lead::find(56);
    
    if (!$lead) {
        die("❌ Lead 56 não encontrado!\n");
    }
    
    echo "✅ Lead encontrado:\n";
    echo "   - ID: {$lead->id}\n";
    echo "   - Nome: {$lead->nome}\n";
    echo "   - Telefone: {$lead->telefone}\n";
    echo "   - Tenant ID: {$lead->tenant_id}\n";
    echo "   - Status: {$lead->status}\n\n";
    
    // 2. Instanciar services
    echo "📋 ETAPA 2: Instanciando services...\n";
    
    $twilioService = new TwilioService();
    $openAIService = new OpenAIService();
    $stageDetectionService = new \App\Services\StageDetectionService();
    $leadCustomerService = new \App\Services\LeadCustomerService();
    
    $whatsappService = new WhatsAppService(
        $twilioService,
        $openAIService,
        $stageDetectionService,
        $leadCustomerService
    );
    
    $leadAutomationService = new LeadAutomationService(
        $whatsappService,
        $twilioService,
        $openAIService
    );
    
    echo "✅ Services instanciados\n\n";
    
    // 3. Verificar configurações
    echo "📋 ETAPA 3: Verificando configurações...\n";
    echo "   - Twilio Account SID: " . (env('EXCLUSIVA_TWILIO_ACCOUNT_SID') ? '✅ Configurado' : '❌ Não configurado') . "\n";
    echo "   - Twilio Auth Token: " . (env('EXCLUSIVA_TWILIO_AUTH_TOKEN') ? '✅ Configurado' : '❌ Não configurado') . "\n";
    echo "   - Twilio WhatsApp From: " . (env('EXCLUSIVA_TWILIO_WHATSAPP_FROM') ?: '❌ Não configurado') . "\n";
    echo "   - OpenAI API Key: " . (env('EXCLUSIVA_OPENAI_API_KEY') ? '✅ Configurado' : '❌ Não configurado') . "\n";
    echo "   - OpenAI Model: " . (env('EXCLUSIVA_OPENAI_MODEL') ?: 'gpt-4o-mini') . "\n";
    echo "   - AI Assistant Name: " . (env('EXCLUSIVA_AI_ASSISTANT_NAME') ?: 'Teresa') . "\n\n";
    
    // 4. Iniciar atendimento (force = true)
    echo "📋 ETAPA 4: Iniciando atendimento IA (force=true)...\n\n";
    
    $resultado = $leadAutomationService->iniciarAtendimento($lead, true);
    
    echo "\n═══════════════════════════════════════════════════════════════\n";
    echo "📊 RESULTADO:\n";
    echo "═══════════════════════════════════════════════════════════════\n";
    
    if ($resultado['success']) {
        echo "✅ SUCESSO!\n\n";
        echo "   - Lead ID: " . $resultado['lead_id'] . "\n";
        echo "   - Conversa ID: " . ($resultado['conversa_id'] ?? 'N/A') . "\n";
        echo "   - Mensagem enviada:\n";
        echo "     " . str_replace("\n", "\n     ", $resultado['mensagem'] ?? 'N/A') . "\n";
    } else {
        echo "❌ ERRO!\n\n";
        echo "   - Lead ID: " . $resultado['lead_id'] . "\n";
        echo "   - Erro: " . $resultado['error'] . "\n";
    }
    
    echo "\n═══════════════════════════════════════════════════════════════\n";
    
} catch (\Exception $e) {
    echo "\n❌ EXCEÇÃO CAPTURADA:\n";
    echo "   - Erro: " . $e->getMessage() . "\n";
    echo "   - Arquivo: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "   - Stack Trace:\n";
    
    $trace = explode("\n", $e->getTraceAsString());
    foreach (array_slice($trace, 0, 10) as $line) {
        echo "     " . $line . "\n";
    }
}
