# ========================================
# SOCIMOB - Gerenciador de Servidor PHP
# ========================================

param(
    [Parameter(Mandatory=$false)]
    [ValidateSet("start", "stop", "restart", "status", "test")]
    [string]$Action = "start"
)

$PhpPath = "C:\xampp\php\php.exe"
$ProjectRoot = "C:\Projetos\socimobatual"
$PublicDir = Join-Path $ProjectRoot "public"
$RouterPath = Join-Path $ProjectRoot "router.php"
$Port = 8000

function Write-ColorOutput {
    param([string]$Message, [ConsoleColor]$Color = [ConsoleColor]::White)
    Write-Host $Message -ForegroundColor $Color
}

function Stop-PhpServer {
    Write-ColorOutput "`n==== Parando Servidor PHP ====" "Cyan"
    $processes = Get-Process -Name php -ErrorAction SilentlyContinue
    if ($processes) {
        $processes | Stop-Process -Force
        Write-ColorOutput "✓ Processos PHP encerrados" "Green"
    } else {
        Write-ColorOutput "! Nenhum processo PHP rodando" "Yellow"
    }
}

function Start-PhpServer {
    Write-ColorOutput "`n==== Iniciando Servidor PHP ====" "Cyan"
    Write-ColorOutput "PHP: $PhpPath" "Gray"
    Write-ColorOutput "Porta: $Port" "Gray"
    Write-ColorOutput "" ""
    Write-ColorOutput "Acesse:" "Yellow"
    Write-ColorOutput "  Homepage:       http://127.0.0.1:$Port/" "Green"
    Write-ColorOutput "  Admin:          http://127.0.0.1:$Port/app/" "Green"  
    Write-ColorOutput "  Portal Cliente: http://127.0.0.1:$Port/portal/" "Green"
    Write-ColorOutput "  API Health:     http://127.0.0.1:$Port/api/health" "Green"
    Write-ColorOutput "" ""
    Write-ColorOutput "Pressione Ctrl+C para parar o servidor`n" "Yellow"
    
    Set-Location $ProjectRoot
    & $PhpPath -S "127.0.0.1:$Port" -t $PublicDir $RouterPath
}

function Test-Server {
    Write-ColorOutput "`n==== Testando Servidor ====" "Cyan"
    
    # Teste 1: Conectividade
    Write-ColorOutput "`n1. Testando conectividade..." "Yellow"
    try {
        $netstat = netstat -an | Select-String ":$Port"
        if ($netstat) {
            Write-ColorOutput "   ✓ Servidor escutando na porta $Port" "Green"
        } else {
            Write-ColorOutput "   X Servidor NÃO está rodando na porta $Port" "Red"
            return
        }
    } catch {
        Write-ColorOutput "   X Erro ao verificar porta: $_" "Red"
        return
    }
    
    # Teste 2: API Health
    Write-ColorOutput "`n2. Testando /api/health..." "Yellow"
    try {
        $response = Invoke-RestMethod -Uri "http://127.0.0.1:$Port/api/health" -Method GET -TimeoutSec 5
        Write-ColorOutput "   ✓ API Health OK" "Green"
        Write-ColorOutput "   App: $($response.app)" "Cyan"
        Write-ColorOutput "   Status: $($response.status)" "Cyan"
    } catch {
        Write-ColorOutput "   X API Health FALHOU: $_" "Red"
    }
    
    # Teste 3: Tenant Config
    Write-ColorOutput "`n3. Testando /api/tenant/config..." "Yellow"
    try {
        $response = Invoke-RestMethod -Uri "http://127.0.0.1:$Port/api/tenant/config" -Method GET -TimeoutSec 5
        if ($response.success) {
            Write-ColorOutput "   ✓ Tenant Config OK" "Green"
            Write-ColorOutput "   Tenant: $($response.tenant.name)" "Cyan"
        }
    } catch {
        Write-ColorOutput "   X Tenant Config FALHOU: $_" "Red"
    }
    
    # Teste 4: Login Page
    Write-ColorOutput "`n4. Testando /app/login.html..." "Yellow"
    try {
        $response = Invoke-WebRequest -Uri "http://127.0.0.1:$Port/app/login.html" -Method GET -TimeoutSec 5
        if ($response.StatusCode -eq 200) {
            Write-ColorOutput "   ✓ Login Page OK (HTTP $($response.StatusCode))" "Green"
        }
    } catch {
        Write-ColorOutput "   X Login Page FALHOU: $_" "Red"
    }
    
    Write-ColorOutput "`n==== Testes Concluídos ====`n" "Cyan"
}

function Get-ServerStatus {
    Write-ColorOutput "`n==== Status do Servidor ====" "Cyan"
    
    # Verificar processo PHP
    $processes = Get-Process -Name php -ErrorAction SilentlyContinue
    if ($processes) {
        Write-ColorOutput "✓ Processo PHP rodando:" "Green"
        $processes | Select-Object ProcessName, Id, StartTime, @{Name='Memory(MB)';Expression={[math]::Round($_.WorkingSet64/1MB,2)}} | Format-Table -AutoSize
    } else {
        Write-ColorOutput "X Nenhum processo PHP rodando" "Red"
    }
    
    # Verificar porta
    $netstat = netstat -an | Select-String ":$Port"
    if ($netstat) {
        Write-ColorOutput "✓ Porta $Port em uso" "Green"
    } else {
        Write-ColorOutput "X Porta $Port disponível (servidor não está rodando)" "Yellow"
    }
}

# Executar ação
switch ($Action) {
    "start" {
        Stop-PhpServer
        Start-Sleep -Seconds 1
        Start-PhpServer
    }
    "stop" {
        Stop-PhpServer
    }
    "restart" {
        Stop-PhpServer
        Start-Sleep -Seconds 1
        Start-PhpServer
    }
    "status" {
        Get-ServerStatus
    }
    "test" {
        Test-Server
    }
}
