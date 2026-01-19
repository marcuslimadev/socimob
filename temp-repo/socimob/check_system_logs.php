<?php
require __DIR__ . '/bootstrap/app.php';

try {
    $db = app('db')->connection();
    
    echo "═══════════════════════════════════════════════════════════════\n";
    echo " ÚLTIMOS 10 LOGS DO SISTEMA\n";
    echo "═══════════════════════════════════════════════════════════════\n\n";
    
    $logs = $db->select("SELECT * FROM system_logs ORDER BY id DESC LIMIT 10");
    
    foreach ($logs as $log) {
        echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        echo "ID: {$log->id}\n";
        echo "Level: {$log->level}\n";
        echo "Category: {$log->category}\n";
        echo "Action: {$log->action}\n";
        echo "Message: {$log->message}\n";
        if ($log->context) {
            echo "Context: " . substr($log->context, 0, 200) . "...\n";
        }
        echo "Created: {$log->created_at}\n";
    }
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
}
