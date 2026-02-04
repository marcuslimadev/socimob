<?php

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Bootstrap Lumen
$app = require_once __DIR__.'/bootstrap/app.php';

echo "=== ENVIANDO SMS PARA O LEAD 138 ===\n\n";

try {
    // Buscar o lead
    $lead = App\Models\Lead::find(138);
    
    if (!$lead) {
        echo "✗ Lead 138 não encontrado!\n";
        exit(1);
    }
    
    echo "✓ Lead encontrado:\n";
    echo "  Nome: {$lead->nome}\n";
    echo "  Telefone: {$lead->telefone}\n";
    echo "  Status: {$lead->status}\n\n";
    
    // Obter o serviço de automação
    $automationService = $app->make(App\Services\LeadAutomationService::class);
    
    echo "Iniciando atendimento IA (com envio de SMS)...\n";
    
    $resultado = $automationService->iniciarAtendimento($lead, true); // force = true
    
    if ($resultado['success']) {
        echo "\n✓ SUCESSO!\n";
        echo "  Conversa ID: {$resultado['conversa_id']}\n";
        echo "  SMS enviado com sucesso!\n\n";
        
        // Verificar se a mensagem foi registrada
        $conversa = App\Models\Conversa::find($resultado['conversa_id']);
        if ($conversa) {
            $mensagens = $conversa->mensagens()->where('direction', 'outgoing')->get();
            echo "Mensagens enviadas: " . $mensagens->count() . "\n";
            foreach ($mensagens as $msg) {
                echo "  - SID: {$msg->message_sid}, Status: {$msg->status}\n";
            }
        }
    } else {
        echo "\n✗ FALHA!\n";
        echo "  Erro: {$resultado['error']}\n";
        
        if (isset($resultado['conversa_id'])) {
            echo "  Conversa ID: {$resultado['conversa_id']}\n";
        }
    }
    
} catch (\Exception $e) {
    echo "\n✗ ERRO: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
