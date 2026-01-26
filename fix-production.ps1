$server = "145.223.105.168"
$port = "65002"
$user = "u815655858"
$password = "MundoMelhor@10"
$remotePath = "/home/u815655858/domains/lojadaesquina.store/public_html"

Write-Host "Iniciando correcao do servidor..." -ForegroundColor Green

# 1. Verificar estrutura
Write-Host "Verificando estrutura..." -ForegroundColor Cyan
$cmd1 = "ls -la $remotePath"
echo $cmd1 | plink -batch -P $port -pw $password $user@$server

# 2. Verificar se index.html existe
Write-Host "Verificando build React..." -ForegroundColor Cyan
$cmd2 = "ls -lh $remotePath/index.html 2>&1"
echo $cmd2 | plink -batch -P $port -pw $password $user@$server

# 3. Verificar assets
Write-Host "Verificando assets..." -ForegroundColor Cyan
$cmd3 = "ls -lh $remotePath/assets/ 2>&1 | head -5"
echo $cmd3 | plink -batch -P $port -pw $password $user@$server

# 4. Ajustar permissões
Write-Host "Ajustando permissoes..." -ForegroundColor Cyan
$cmd4 = "chmod 644 $remotePath/index.html 2>&1"
echo $cmd4 | plink -batch -P $port -pw $password $user@$server

# 5. Limpar cache
Write-Host "Limpando cache..." -ForegroundColor Cyan
$cmd5 = "cd $remotePath && php artisan cache:clear 2>&1"
echo $cmd5 | plink -batch -P $port -pw $password $user@$server

Write-Host "Concluido!" -ForegroundColor Green
Write-Host "Teste: https://exclusivalarimoveis.com/" -ForegroundColor Yellow
