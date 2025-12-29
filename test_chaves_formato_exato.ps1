Write-Host "=== TESTE FORMATO EXATO CHAVES NA MAO ===" -ForegroundColor Green
Write-Host ""

$headers = @{
    "Content-Type" = "application/json"
    "User-Agent" = "chavesnamao-leads-api"
    "Authorization" = "Basic Y29udGF0b0BleGNsdXNpdmFsYXJpbW92ZWlzLmNvbS5icjpkODI1YzU0MmUyNmRmMjdjOWZlNjk2YzM5MWVlNTkwZg=="
}

# Lead como JSON string (formato deles)
$leadJson = '{"id":"12425758","name":"Vagner Chaves na Mao","email":"teste@chavesnamao.com.br","phone":"(99) 99999-9999","message":"teste integracao leads","createdAt":"2025-12-26 08:09:29","sendAt":"2025-12-26 08:09:29","segment":"VEHICLE","proposeTypeName":"Formulario WhatsApp","ad":{"id":null,"title":"","reference":"113CA","brand":"","model":"","trim":"","color":"","fuel":"","manufacturedYear":"","modelYear":"","mileage":""},"client":{"name":"","tradeName":"","document":""}}'

# Body com "lead" como string JSON
$body = @{
    lead = $leadJson
} | ConvertTo-Json

Write-Host "Enviando payload com lead como string JSON..." -ForegroundColor Yellow
Write-Host ""

try {
    $response = Invoke-RestMethod -Uri "https://exclusivalarimoveis.com/webhook/chaves-na-mao" -Method POST -Headers $headers -Body $body
    Write-Host "SUCESSO!" -ForegroundColor Green
    Write-Host ""
    $response | ConvertTo-Json
} catch {
    Write-Host "ERRO!" -ForegroundColor Red
    Write-Host "Status: $($_.Exception.Response.StatusCode.value__)"
    Write-Host "Mensagem: $($_.Exception.Message)"
    if ($_.ErrorDetails) {
        Write-Host "Detalhes:"
        Write-Host $_.ErrorDetails.Message
    }
}
