Write-Host "=== VERIFICAR E ATUALIZAR .ENV NO SERVIDOR ===" -ForegroundColor Cyan
Write-Host ""

$server = "u815655858@145.223.105.168"
$port = "65002"
$path = "~/domains/lojadaesquina.store/public_html"

# Comando SSH para verificar se a variável já existe
$checkCommand = "grep 'WEBHOOK_TENANT_ID' $path/.env || echo 'NAO_ENCONTRADO'"

Write-Host "Verificando se WEBHOOK_TENANT_ID existe no .env..." -ForegroundColor Yellow

$result = plink -ssh -P $port $server "cd $path && $checkCommand" 2>&1

if ($result -match "NAO_ENCONTRADO" -or $result -match "linha vazia") {
    Write-Host "Variavel NAO encontrada. Adicionando..." -ForegroundColor Red
    
    # Adicionar a variável
    $addCommand = "echo '' >> $path/.env && echo 'WEBHOOK_TENANT_ID=1' >> $path/.env"
    plink -ssh -P $port $server "cd $path && $addCommand"
    
    Write-Host "WEBHOOK_TENANT_ID=1 adicionado ao .env" -ForegroundColor Green
    
    # Verificar novamente
    Write-Host ""
    Write-Host "Verificando novamente..." -ForegroundColor Yellow
    plink -ssh -P $port $server "cd $path && tail -3 .env"
    
} else {
    Write-Host "Variavel JA existe:" -ForegroundColor Green
    Write-Host $result
}

Write-Host ""
Write-Host "=== VERIFICACAO CONCLUIDA ===" -ForegroundColor Cyan
