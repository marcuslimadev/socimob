# Teste do Sistema de Fila - API

Write-Host "🧪 TESTE DO SISTEMA DE FILA" -ForegroundColor Cyan
Write-Host ("=" * 50)
Write-Host ""

# Base URL
$baseUrl = "http://127.0.0.1:8000"

# Token de admin
$adminToken = "eyJ1c2VyX2lkIjoxLCJ0aW1lc3RhbXAiOjE3MzYzNDE5ODB9"

# Headers
$headers = @{
    "Authorization" = "Bearer $adminToken"
    "Content-Type" = "application/json"
}

# 1. Listar conversas
Write-Host "1️⃣ Listando conversas..." -ForegroundColor Yellow
try {
    $conversas = Invoke-RestMethod -Uri "$baseUrl/api/admin/conversas" -Headers $headers -Method GET
    Write-Host "✅ Total de conversas: $($conversas.data.Count)" -ForegroundColor Green
    
    # Mostrar primeiras 5
    $conversas.data | Select-Object -First 5 | ForEach-Object {
        $status = if ($_.corretor_id) { "Atribuída" } else { "FILA" }
        Write-Host "  - Conversa #$($_.id) ($($_.lead_nome)): $status"
    }
} catch {
    Write-Host "❌ Erro ao listar: $_" -ForegroundColor Red
}

Write-Host ""

# 2. Estatísticas da fila
Write-Host "2️⃣ Buscando estatísticas..." -ForegroundColor Yellow
try {
    $stats = Invoke-RestMethod -Uri "$baseUrl/api/admin/conversas/fila/estatisticas" -Headers $headers -Method GET
    Write-Host "✅ Estatísticas:" -ForegroundColor Green
    Write-Host "  Em fila: $($stats.data.em_fila)"
    Write-Host "  Atribuídas: $($stats.data.atribuidas)"
    Write-Host "  Total ativas: $($stats.data.total_ativas)"
    
    if ($stats.data.por_corretor) {
        Write-Host "  Por corretor:"
        $stats.data.por_corretor | ForEach-Object {
            Write-Host "    - $($_.name): $($_.total)"
        }
    }
} catch {
    Write-Host "❌ Erro ao buscar stats: $_" -ForegroundColor Red
}

Write-Host ""

# 3. Tentar pegar próxima da fila
Write-Host "3️⃣ Tentando pegar próxima da fila..." -ForegroundColor Yellow
try {
    $proxima = Invoke-RestMethod -Uri "$baseUrl/api/admin/conversas/fila/pegar-proxima" -Headers $headers -Method POST
    Write-Host "✅ Conversa atribuída:" -ForegroundColor Green
    Write-Host "  ID: $($proxima.data.id)"
    Write-Host "  Lead: $($proxima.data.lead_nome)"
    Write-Host "  Telefone: $($proxima.data.lead_telefone)"
} catch {
    if ($_.Exception.Response.StatusCode -eq 404) {
        Write-Host "⚠️  Fila vazia - nenhuma conversa disponível" -ForegroundColor Yellow
    } else {
        Write-Host "❌ Erro: $_" -ForegroundColor Red
    }
}

Write-Host ""

# 4. Estatísticas finais
Write-Host "4️⃣ Estatísticas após teste..." -ForegroundColor Yellow
try {
    $finalStats = Invoke-RestMethod -Uri "$baseUrl/api/admin/conversas/fila/estatisticas" -Headers $headers -Method GET
    Write-Host "✅ Estatísticas finais:" -ForegroundColor Green
    Write-Host "  Em fila: $($finalStats.data.em_fila)"
    Write-Host "  Atribuídas: $($finalStats.data.atribuidas)"
    Write-Host "  Total ativas: $($finalStats.data.total_ativas)"
} catch {
    Write-Host "❌ Erro: $_" -ForegroundColor Red
}

Write-Host ""
Write-Host ("=" * 50)
Write-Host "✅ Teste completo!" -ForegroundColor Green
