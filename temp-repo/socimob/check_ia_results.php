<?php
require __DIR__ . '/bootstrap/app.php';

try {
    $db = app('db')->connection();
    
    echo "═══════════════════════════════════════════════════════════════\n";
    echo " CONVERSA 48 - DETALHES\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";
    
    $conversa = $db->selectOne("SELECT * FROM conversas WHERE id = 48");
    
    if ($conversa) {
        foreach ((array)$conversa as $key => $value) {
            echo str_pad($key, 20) . ": " . ($value ?? 'NULL') . "\n";
        }
    } else {
        echo "Conversa não encontrada.\n";
    }
    
    echo "\n═══════════════════════════════════════════════════════════════\n";
    echo " MENSAGENS DA CONVERSA 48\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";
    
    $mensagens = $db->select("SELECT * FROM mensagens WHERE conversa_id = 48");
    
    foreach ($mensagens as $msg) {
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "ID: {$msg->id}\n";
        echo "Direction: {$msg->direction}\n";
        echo "Message Type: {$msg->message_type}\n";
        echo "Status: {$msg->status}\n";
        echo "Content: " . substr($msg->content, 0, 150) . "...\n";
        echo "Sent At: {$msg->sent_at}\n";
        echo "Created: {$msg->created_at}\n";
    }
    
    echo "\n═══════════════════════════════════════════════════════════════\n";
    echo " LEAD 56 - STATUS ATUAL\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";
    
    $lead = $db->selectOne("SELECT id, nome, telefone, status, ultima_interacao, created_at, updated_at FROM leads WHERE id = 56");
    
    if ($lead) {
        foreach ((array)$lead as $key => $value) {
            echo str_pad($key, 20) . ": " . ($value ?? 'NULL') . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}
