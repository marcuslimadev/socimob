Write-Host "=== INICIANDO QUEUE WORKER NO SERVIDOR ===" -ForegroundColor Cyan
Write-Host ""

$server = "u815655858@145.223.105.168"
$port = "65002"
$path = "~/domains/lojadaesquina.store/public_html"

Write-Host "Iniciando worker em background..." -ForegroundColor Yellow

# Comando para iniciar o worker em background
$command = @"
cd $path && nohup php artisan queue:work database --sleep=3 --tries=3 --daemon > storage/logs/queue.log 2>&1 &
echo "Queue worker iniciado com PID: \$!"
"@

plink -ssh -P $port $server $command

Write-Host ""
Write-Host "✅ Worker iniciado!" -ForegroundColor Green
Write-Host ""
Write-Host "Para verificar se está rodando:" -ForegroundColor Yellow
Write-Host "  ps aux | grep 'queue:work'" -ForegroundColor Gray
Write-Host ""
Write-Host "Para ver logs:" -ForegroundColor Yellow
Write-Host "  tail -f storage/logs/queue.log" -ForegroundColor Gray
