# Criar Lead Marcus e Iniciar Atendimento IA

$baseUrl = "http://127.0.0.1:8000"

Write-Host "═══════════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host " CRIANDO LEAD MARCUS VIA API" -ForegroundColor Cyan
Write-Host "═══════════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host ""

# 1. Login
Write-Host "📋 ETAPA 1: Fazendo login..." -ForegroundColor Yellow

$loginData = @{
    email = "admin@exclusiva.com"
    password = "password"
} | ConvertTo-Json

try {
    $loginResponse = Invoke-WebRequest -Uri "$baseUrl/api/auth/login" `
        -Method POST `
        -Body $loginData `
        -ContentType "application/json" `
        -UseBasicParsing

    $loginResult = $loginResponse.Content | ConvertFrom-Json
    $token = $loginResult.token
    
    Write-Host "✅ Login OK! Token obtido" -ForegroundColor Green
    Write-Host ""
} catch {
    Write-Host "❌ Erro no login: $_" -ForegroundColor Red
    exit 1
}

# 2. Criar Lead
Write-Host "📋 ETAPA 2: Criando lead Marcus..." -ForegroundColor Yellow

$leadData = @{
    nome = "Marcus"
    telefone = "+5592992287144"
    whatsapp = "+5592992287144"
    email = "marcus@teste.com"
    status = "novo"
    observacoes = "Lead criado para teste de atendimento IA"
    quartos = 2
} | ConvertTo-Json

try {
    $createResponse = Invoke-WebRequest -Uri "$baseUrl/api/admin/leads" `
        -Method POST `
        -Body $leadData `
        -ContentType "application/json" `
        -Headers @{"Authorization" = "Bearer $token"} `
        -UseBasicParsing

    $createResult = $createResponse.Content | ConvertFrom-Json
    
    # Tenta diferentes estruturas de resposta
    if ($createResult.data.id) {
        $leadId = $createResult.data.id
    } elseif ($createResult.id) {
        $leadId = $createResult.id
    } else {
        Write-Host "❌ Lead ID não encontrado na resposta" -ForegroundColor Red
        Write-Host $createResponse.Content
        exit 1
    }
    
    Write-Host "✅ Lead criado! ID: $leadId" -ForegroundColor Green
    Write-Host ""
} catch {
    Write-Host "❌ Erro ao criar lead: $_" -ForegroundColor Red
    exit 1
}

# 3. Iniciar Atendimento IA
Write-Host "═══════════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host " INICIANDO ATENDIMENTO IA" -ForegroundColor Cyan
Write-Host "═══════════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host ""

try {
    $iniciarResponse = Invoke-WebRequest -Uri "$baseUrl/api/admin/leads/$leadId/iniciar-atendimento" `
        -Method POST `
        -ContentType "application/json" `
        -Headers @{"Authorization" = "Bearer $token"} `
        -UseBasicParsing

    $iniciarResult = $iniciarResponse.Content | ConvertFrom-Json
    
    Write-Host "HTTP Code: $($iniciarResponse.StatusCode)" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "═══════════════════════════════════════════════════════════════" -ForegroundColor Cyan
    Write-Host " RESULTADO" -ForegroundColor Cyan
    Write-Host "═══════════════════════════════════════════════════════════════" -ForegroundColor Cyan
    Write-Host ""
    Write-Host ($iniciarResult | ConvertTo-Json -Depth 5)
    Write-Host ""
    
    if ($iniciarResult.success) {
        Write-Host "SUCESSO! Atendimento IA iniciado para Marcus!" -ForegroundColor Green
        Write-Host ""
        Write-Host "Mensagem enviada para: +5592992287144" -ForegroundColor Cyan
        
        if ($iniciarResult.data.mensagem) {
            Write-Host ""
            Write-Host "Mensagem:" -ForegroundColor Yellow
            Write-Host "-----------------------------------------------------------" -ForegroundColor Gray
            Write-Host $iniciarResult.data.mensagem -ForegroundColor White
            Write-Host "-----------------------------------------------------------" -ForegroundColor Gray
        }
    } else {
        Write-Host "❌ ERRO ao iniciar atendimento" -ForegroundColor Red
    }
    
} catch {
    Write-Host "❌ Erro ao iniciar atendimento: $_" -ForegroundColor Red
    Write-Host $_.Exception.Response
    exit 1
}
