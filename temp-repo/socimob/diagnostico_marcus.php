<?php
require __DIR__ . '/bootstrap/app.php';

try {
    $db = app('db')->connection();
    
    echo "═══════════════════════════════════════════════════════════════\n";
    echo " DIAGNÓSTICO - MENSAGEM PARA MARCUS\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";
    
    // 1. Verificar conversa
    $conversa = $db->selectOne("SELECT * FROM conversas WHERE id = 49");
    
    if ($conversa) {
        echo "✅ CONVERSA 49 encontrada\n";
        echo "   Lead ID: {$conversa->lead_id}\n";
        echo "   Telefone: {$conversa->telefone}\n";
        echo "   Status: {$conversa->status}\n\n";
    }
    
    // 2. Verificar mensagens
    echo "═══════════════════════════════════════════════════════════════\n";
    echo " MENSAGENS DA CONVERSA 49\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";
    
    $mensagens = $db->select("SELECT * FROM mensagens WHERE conversa_id = 49 ORDER BY id DESC");
    
    if (count($mensagens) > 0) {
        foreach ($mensagens as $msg) {
            echo "Mensagem ID: {$msg->id}\n";
            echo "  Direction: {$msg->direction}\n";
            echo "  Status: {$msg->status}\n";
            echo "  Message SID: {$msg->message_sid}\n";
            echo "  Content: " . substr($msg->content, 0, 100) . "...\n";
            echo "  Sent at: {$msg->sent_at}\n";
            echo "  Created: {$msg->created_at}\n\n";
        }
    } else {
        echo "❌ Nenhuma mensagem encontrada!\n\n";
    }
    
    // 3. Verificar system logs
    echo "═══════════════════════════════════════════════════════════════\n";
    echo " ÚLTIMOS LOGS RELACIONADOS AO LEAD 57\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";
    
    $logs = $db->select("
        SELECT * FROM system_logs 
        WHERE context LIKE '%\"lead_id\":57%' 
           OR context LIKE '%conversa_id\":49%'
        ORDER BY id DESC 
        LIMIT 10
    ");
    
    foreach ($logs as $log) {
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "Level: {$log->level}\n";
        echo "Category: {$log->category}\n";
        echo "Action: {$log->action}\n";
        echo "Message: {$log->message}\n";
        if ($log->context) {
            $context = json_decode($log->context, true);
            echo "Context:\n";
            print_r($context);
        }
        echo "Created: {$log->created_at}\n";
    }
    
    // 4. Verificar credenciais Twilio
    echo "\n═══════════════════════════════════════════════════════════════\n";
    echo " CONFIGURAÇÃO TWILIO\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";
    
    $accountSid = env('EXCLUSIVA_TWILIO_ACCOUNT_SID');
    $authToken = env('EXCLUSIVA_TWILIO_AUTH_TOKEN');
    $from = env('EXCLUSIVA_TWILIO_WHATSAPP_FROM');
    
    echo "Account SID: " . ($accountSid ? substr($accountSid, 0, 10) . "..." : "❌ NÃO CONFIGURADO") . "\n";
    echo "Auth Token: " . ($authToken ? substr($authToken, 0, 10) . "..." : "❌ NÃO CONFIGURADO") . "\n";
    echo "WhatsApp From: " . ($from ?: "❌ NÃO CONFIGURADO") . "\n";
    
    // 5. Testar envio direto via Twilio
    if ($accountSid && $authToken && $from) {
        echo "\n═══════════════════════════════════════════════════════════════\n";
        echo " TESTANDO ENVIO DIRETO VIA TWILIO\n";
        echo "═══════════════════════════════════════════════════════════════\n\n";
        
        $twilioService = new \App\Services\TwilioService();
        
        try {
            $resultado = $twilioService->sendMessage(
                'whatsapp:+5592992287144',
                '🔔 TESTE: Esta é uma mensagem de teste do sistema SOCIMOB/Exclusiva'
            );
            
            echo "✅ Mensagem de teste enviada!\n";
            echo "Message SID: {$resultado['sid']}\n";
            echo "Status: {$resultado['status']}\n";
            echo "To: {$resultado['to']}\n";
            
        } catch (Exception $e) {
            echo "❌ ERRO ao enviar mensagem de teste:\n";
            echo $e->getMessage() . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ ERRO: " . $e->getMessage() . "\n";
}
