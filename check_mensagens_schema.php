<?php
$pdo = new PDO('mysql:host=srv1005.hstgr.io;dbname=u815655858_saas', 'u815655858_saas', 'MundoMelhor@10', [PDO::ATTR_TIMEOUT => 30, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$stmt = $pdo->query('DESCRIBE mensagens');
echo "Colunas da tabela 'mensagens':\n\n";
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo "- {$col['Field']} ({$col['Type']}) " . ($col['Null'] === 'YES' ? 'NULL' : 'NOT NULL') . "\n";
}
