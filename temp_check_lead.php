<?php
require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

try {
    $pdo = new PDO(
        'mysql:host=193.203.166.228;port=3306;dbname=u815655858_saas',
        'u815655858_saas',
        'MundoMelhor@10'
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Buscar mensagens SMS do lead 138
    $query = "SELECT m.*, c.telefone, c.canal 
              FROM mensagens m 
              JOIN conversas c ON m.conversa_id = c.id 
              WHERE c.lead_id = 138 
              AND m.direction = 'outgoing' 
              ORDER BY m.created_at DESC 
              LIMIT 5";
    
    $stmt = $pdo->query($query);
    $mensagens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "=== MENSAGENS SMS DO LEAD 138 ===\n\n";
    
    if (count($mensagens) > 0) {
        foreach ($mensagens as $msg) {
            echo "ID: {$msg['id']}\n";
            echo "Message SID: {$msg['message_sid']}\n";
            echo "Status: {$msg['status']}\n";
            echo "Canal: {$msg['canal']}\n";
            echo "Telefone: {$msg['telefone']}\n";
            echo "Enviado em: {$msg['created_at']}\n";
            echo "Conteúdo: " . substr($msg['content'], 0, 100) . "...\n";
            echo "---\n\n";
        }
    } else {
        echo "NENHUMA MENSAGEM SMS ENCONTRADA PARA O LEAD 138!\n";
    }
    
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage() . "\n";
}
