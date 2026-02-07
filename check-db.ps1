$serverUser = "u815655858"
$serverHost = "145.223.105.168"
$serverPort = "65002"
$serverPass = "MundoMelhor@10"

$phpCode = @'
<?php
require __DIR__ . '/bootstrap/app.php';
$msg = DB::table('mensagens')->where('id', 417)->first();
if ($msg) {
    echo "ID: " . $msg->id . "\n";
    echo "Media URL: " . $msg->media_url . "\n";
    echo "Type: " . $msg->message_type . "\n";
} else {
    echo "Nao encontrada\n";
}
'@

$commands = "cd ~/domains/lojadaesquina.store/public_html && echo '$phpCode' > /tmp/check.php && /opt/alt/php83/usr/bin/php /tmp/check.php && rm /tmp/check.php"

echo "exit" | plink -P $serverPort -pw $serverPass -batch $serverUser@$serverHost $commands
