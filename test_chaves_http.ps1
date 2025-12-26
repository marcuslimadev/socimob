$url = "https://lojadaesquina.store/api/admin/chaves-na-mao/test"
$token = "Bearer " + [Convert]::ToBase64String([Text.Encoding]::UTF8.GetBytes("admin@exclusiva.com:password"))

Write-Host "🧪 Testando integração Chaves na Mão via HTTP..." -ForegroundColor Cyan

try {
    $response = Invoke-RestMethod -Uri $url -Method POST -Headers @{
        "Authorization" = $token
        "Content-Type" = "application/json"
    } -TimeoutSec 60
    
    Write-Host "✅ Resposta recebida:" -ForegroundColor Green
    $response | ConvertTo-Json -Depth 10
} catch {
    Write-Host "❌ Erro:" -ForegroundColor Red
    Write-Host $_.Exception.Message
    if ($_.Exception.Response) {
        $reader = New-Object System.IO.StreamReader($_.Exception.Response.GetResponseStream())
        $responseBody = $reader.ReadToEnd()
        Write-Host $responseBody
    }
}
