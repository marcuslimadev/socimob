$server = "u815655858@145.223.105.168"
$port = "65002"
$password = "MundoMelhor@10"
$path = "~/domains/lojadaesquina.store/public_html"

Write-Host "=== INICIANDO QUEUE WORKER ===" -ForegroundColor Cyan

# Comando para iniciar worker em background
$command = "cd $path && nohup php artisan queue:work database --sleep=3 --tries=3 > storage/logs/queue.log 2>&1 & echo 'Worker started'"

plink -ssh -P $port -pw $password $server $command

Write-Host "✅ Worker iniciado!" -ForegroundColor Green
