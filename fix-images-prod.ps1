$serverUser = "u815655858"
$serverHost = "145.223.105.168"
$serverPort = "65002"
$serverPass = "MundoMelhor@10"
$serverPath = "~/domains/lojadaesquina.store/public_html"

Write-Host "=== CORRIGINDO IMAGENS DO TWILIO NO SERVIDOR ===" -ForegroundColor Cyan
Write-Host ""

$commands = @"
cd $serverPath && \
echo '=== REMOVENDO ARQUIVOS CONFLITANTES ===' && \
rm -rf public/storage && \
echo '=== ATUALIZANDO CÓDIGO ===' && \
git reset --hard HEAD && \
git pull origin master && \
echo '' && \
echo '=== BAIXANDO IMAGENS DO TWILIO ===' && \
/opt/alt/php83/usr/bin/php fix_twilio_images.php && \
echo '' && \
echo '✅ PRONTO! Recarregue o chat.'
"@

Write-Host "Conectando ao servidor..." -ForegroundColor Gray
echo "exit" | plink -P $serverPort -pw $serverPass -batch $serverUser@$serverHost $commands

if ($LASTEXITCODE -eq 0) {
    Write-Host ""
    Write-Host "✅ IMAGENS CORRIGIDAS!" -ForegroundColor Green
    Write-Host "Acesse: https://lojadaesquina.store/chat" -ForegroundColor Cyan
} else {
    Write-Host ""
    Write-Host "⚠️ Concluído com código: $LASTEXITCODE" -ForegroundColor Yellow
}
