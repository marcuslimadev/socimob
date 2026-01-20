# Script de Deploy via Plink
# Tenta várias abordagens para conexão SSH

$host_ip = "145.223.105.168"
$port = "65002"
$user = "u738323131"
$password = "MundoMelhor@10"

Write-Host "=== Tentativa 1: Plink com -pw ===" -ForegroundColor Yellow
$result1 = plink -P $port -l $user $host_ip -pw $password "echo 'OK'" 2>&1
Write-Host $result1

Write-Host "`n=== Tentativa 2: Plink sem -batch ===" -ForegroundColor Yellow
$result2 = echo "y" | plink -P $port $user@$host_ip -pw $password "echo 'OK'" 2>&1
Write-Host $result2

Write-Host "`n=== Tentativa 3: Verificar se plink está correto ===" -ForegroundColor Yellow
plink -V

Write-Host "`n=== Tentativa 4: Usar pscp para testar autenticação ===" -ForegroundColor Yellow
$testFile = "DEPLOY_SUMMARY.md"
pscp -P $port -pw $password $testFile ${user}@${host_ip}:/tmp/test.txt 2>&1 | Select-Object -First 5

Write-Host "`n=== RECOMENDAÇÃO ===" -ForegroundColor Cyan
Write-Host "A senha pode estar incorreta ou expirada."
Write-Host "Tente uma das seguintes opções:"
Write-Host "1. Resetar senha SSH no painel Hostinger"
Write-Host "2. Usar FileZilla (GUI) para fazer upload manual"
Write-Host "3. Usar terminal web no painel Hostinger"
Write-Host "`nArquivos prontos para deploy em: $(Get-Location)"
