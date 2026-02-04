<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "=== VERIFICAÇÃO SMS LEAD 138 ===\n\n";
echo "Conectando ao banco de dados...\n";

try {
    $pdo = new PDO(
        'mysql:host=193.203.166.228;port=3306;dbname=u815655858_saas;charset=utf8mb4',
        'u815655858_saas',
        'MundoMelhor@10',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 10
        ]
    );
    
    echo "✓ Conectado com sucesso!\n\n";
    
    // Verificar se o lead 138 existe
    echo "Verificando lead 138...\n";
    $stmt = $pdo->prepare("SELECT id, nome, telefone, status, created_at FROM leads WHERE id = ?");
    $stmt->execute([138]);
    $lead = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$lead) {
        echo "✗ Lead 138 não encontrado!\n";
        exit(1);
    }
    
    echo "✓ Lead encontrado:\n";
    echo "  - Nome: {$lead['nome']}\n";
    echo "  - Telefone: {$lead['telefone']}\n";
    echo "  - Status: {$lead['status']}\n";
    echo "  - Criado em: {$lead['created_at']}\n\n";
    
    // Buscar conversas do lead
    echo "Buscando conversas...\n";
    $stmt = $pdo->prepare("SELECT * FROM conversas WHERE lead_id = ?");
    $stmt->execute([138]);
    $conversas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($conversas)) {
        echo "✗ Nenhuma conversa encontrada para o lead 138!\n";
        exit(0);
    }
    
    echo "✓ Encontradas " . count($conversas) . " conversa(s)\n\n";
    
    // Para cada conversa, buscar mensagens enviadas
    $totalMensagens = 0;
    foreach ($conversas as $conversa) {
        echo "--- Conversa ID: {$conversa['id']} ---\n";
        echo "Canal: {$conversa['canal']}\n";
        echo "Telefone: {$conversa['telefone']}\n\n";
        
        $stmt = $pdo->prepare("
            SELECT id, message_sid, status, direction, content, sent_at, created_at 
            FROM mensagens 
            WHERE conversa_id = ? AND direction = 'outgoing'
            ORDER BY created_at DESC
        ");
        $stmt->execute([$conversa['id']]);
        $mensagens = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($mensagens)) {
            echo "Nenhuma mensagem enviada (outgoing) nesta conversa.\n\n";
            continue;
        }
        
        echo "Mensagens enviadas (" . count($mensagens) . "):\n";
        foreach ($mensagens as $msg) {
            $totalMensagens++;
            echo "\n  SMS #{$msg['id']}:\n";
            echo "  - Message SID: {$msg['message_sid']}\n";
            echo "  - Status: {$msg['status']}\n";
            echo "  - Enviado em: {$msg['sent_at']}\n";
            echo "  - Criado em: {$msg['created_at']}\n";
            echo "  - Conteúdo (primeiros 100 chars): " . substr($msg['content'], 0, 100) . "...\n";
        }
        echo "\n";
    }
    
    echo "\n=== RESUMO ===\n";
    echo "Total de mensagens SMS enviadas para o lead 138: $totalMensagens\n";
    
    if ($totalMensagens > 0) {
        echo "\n✓ SMS FOI ENVIADO PARA O LEAD 138!\n";
    } else {
        echo "\n✗ NENHUM SMS FOI ENVIADO PARA O LEAD 138!\n";
    }
    
} catch (PDOException $e) {
    echo "\n✗ Erro de conexão/banco: " . $e->getMessage() . "\n";
    echo "Código: " . $e->getCode() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "\n✗ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
