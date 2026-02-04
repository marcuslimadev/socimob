$server = "u815655858@145.223.105.168"
$port = "65002"
$password = "MundoMelhor@10"
$path = "~/domains/lojadaesquina.store/public_html"

Write-Host "=== INICIANDO QUEUE WORKER COM PHP 8.3 ===" -ForegroundColor Cyan

# Comando para iniciar worker com PHP 8.3
$command = "cd $path && nohup /opt/alt/php83/usr/bin/php artisan queue:work database --sleep=3 --tries=3 > storage/logs/queue.log 2>&1 & echo 'Worker PHP 8.3 started'"

plink -ssh -P $port -pw $password $server $command

Write-Host "✅ Worker PHP 8.3 iniciado!" -ForegroundColor Green
