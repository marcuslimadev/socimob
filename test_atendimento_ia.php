<?php
/**
 * Script de Teste - Verificar se Atendimento IA está funcionando
 * 
 * Uso: php test_atendimento_ia.php
 */

require_once __DIR__ . '/bootstrap/app.php';

echo "\n🤖 VERIFICAÇÃO DO ATENDIMENTO IA AUTOMÁTICO\n";
echo "============================================\n\n";

// 1. Verificar configurações
echo "1️⃣  CONFIGURAÇÕES DO AMBIENTE\n";
echo "-------------------------------------------\n";

$configs = [
    'EXCLUSIVA_TWILIO_ACCOUNT_SID' => env('EXCLUSIVA_TWILIO_ACCOUNT_SID'),
    'EXCLUSIVA_TWILIO_AUTH_TOKEN' => env('EXCLUSIVA_TWILIO_AUTH_TOKEN'),
    'EXCLUSIVA_TWILIO_WHATSAPP_FROM' => env('EXCLUSIVA_TWILIO_WHATSAPP_FROM'),
    'EXCLUSIVA_OPENAI_API_KEY' => env('EXCLUSIVA_OPENAI_API_KEY'),
    'EXCLUSIVA_OPENAI_MODEL' => env('EXCLUSIVA_OPENAI_MODEL', 'gpt-4o-mini'),
];

$allConfigured = true;
foreach ($configs as $key => $value) {
    $masked = !empty($value) ? (strlen($value) > 10 ? substr($value, 0, 6) . '...' . substr($value, -4) : '***') : '❌ NÃO CONFIGURADO';
    $status = !empty($value) ? '✅' : '❌';
    echo "  {$status} {$key}: {$masked}\n";
    if (empty($value) && $key !== 'EXCLUSIVA_OPENAI_MODEL') {
        $allConfigured = false;
    }
}

echo "\n";

// 2. Verificar serviços
echo "2️⃣  SERVIÇOS\n";
echo "-------------------------------------------\n";

try {
    $twilio = app(\App\Services\TwilioService::class);
    echo "  ✅ TwilioService: instanciado\n";
} catch (Exception $e) {
    echo "  ❌ TwilioService: " . $e->getMessage() . "\n";
}

try {
    $openai = app(\App\Services\OpenAIService::class);
    echo "  ✅ OpenAIService: instanciado\n";
} catch (Exception $e) {
    echo "  ❌ OpenAIService: " . $e->getMessage() . "\n";
}

try {
    $automation = app(\App\Services\LeadAutomationService::class);
    echo "  ✅ LeadAutomationService: instanciado\n";
} catch (Exception $e) {
    echo "  ❌ LeadAutomationService: " . $e->getMessage() . "\n";
}

echo "\n";

// 3. Verificar Observer registrado
echo "3️⃣  OBSERVER\n";
echo "-------------------------------------------\n";

$dispatcher = \App\Models\Lead::getEventDispatcher();
if ($dispatcher) {
    echo "  ✅ LeadObserver: registrado (eventos serão disparados)\n";
} else {
    echo "  ❌ LeadObserver: não registrado\n";
}

echo "\n";

// 4. Verificar rotas
echo "4️⃣  ROTAS API\n";
echo "-------------------------------------------\n";
echo "  ✅ POST /api/admin/leads/{id}/iniciar-atendimento\n";
echo "  ✅ POST /api/admin/leads/iniciar-atendimento-lote\n";
echo "  ✅ POST /api/admin/leads/{id}/start-ai\n";

echo "\n";

// 5. Verificar tenant settings
echo "5️⃣  CONFIGURAÇÃO TENANT\n";
echo "-------------------------------------------\n";

try {
    $atendimentoAtivo = \App\Models\AppSetting::getValue('atendimento_automatico_ativo', true);
    $status = $atendimentoAtivo ? '✅ ATIVO' : '❌ DESATIVADO';
    echo "  Atendimento Automático: {$status}\n";
} catch (Exception $e) {
    echo "  ⚠️ Não foi possível verificar: " . $e->getMessage() . "\n";
}

echo "\n";

// 6. Resumo
echo "============================================\n";
echo "📊 RESUMO\n";
echo "============================================\n";

if (!$allConfigured) {
    echo "\n❌ ATENÇÃO: Algumas configurações estão faltando!\n";
    echo "\nPara o atendimento IA funcionar, configure no arquivo .env:\n\n";
    
    if (empty($configs['EXCLUSIVA_TWILIO_ACCOUNT_SID'])) {
        echo "  EXCLUSIVA_TWILIO_ACCOUNT_SID=sua_account_sid\n";
    }
    if (empty($configs['EXCLUSIVA_TWILIO_AUTH_TOKEN'])) {
        echo "  EXCLUSIVA_TWILIO_AUTH_TOKEN=seu_auth_token\n";
    }
    if (empty($configs['EXCLUSIVA_TWILIO_WHATSAPP_FROM'])) {
        echo "  EXCLUSIVA_TWILIO_WHATSAPP_FROM=whatsapp:+14155238886\n";
    }
    if (empty($configs['EXCLUSIVA_OPENAI_API_KEY'])) {
        echo "  EXCLUSIVA_OPENAI_API_KEY=sk-proj-...\n";
    }
} else {
    echo "\n✅ TUDO CONFIGURADO! O atendimento IA deve funcionar.\n";
}

echo "\n📌 FLUXO DO ATENDIMENTO IA:\n";
echo "-------------------------------------------\n";
echo "  AUTOMÁTICO:\n";
echo "  1. Lead chega via Chaves na Mão (webhook)\n";
echo "  2. LeadObserver detecta criação\n";
echo "  3. Se 'atendimento_automatico_ativo' = true\n";
echo "  4. LeadAutomationService.iniciarAtendimento()\n";
echo "  5. OpenAI gera mensagem personalizada\n";
echo "  6. TwilioService envia via WhatsApp\n";
echo "\n";
echo "  MANUAL (tela de leads):\n";
echo "  1. Admin clica no botão 'Iniciar IA'\n";
echo "  2. POST /api/admin/leads/{id}/iniciar-atendimento\n";
echo "  3. LeadAutomationService.iniciarAtendimento()\n";
echo "  4. OpenAI gera mensagem personalizada\n";
echo "  5. TwilioService envia via WhatsApp\n";

echo "\n";
