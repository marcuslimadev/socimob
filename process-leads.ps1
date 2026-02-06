# Script de Processamento em Lote de Leads
# Cria perfis e envia SMS para leads pendentes

param(
    [switch]$DryRun,
    [switch]$OnlySms,
    [switch]$OnlyProfiles,
    [switch]$Production
)

Write-Host "═══════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host "   PROCESSAMENTO EM LOTE DE LEADS - SOCIMOB" -ForegroundColor Cyan
Write-Host "═══════════════════════════════════════════════════════════" -ForegroundColor Cyan
Write-Host ""

if ($Production) {
    Write-Host "🌐 MODO PRODUÇÃO - Executando no servidor remoto" -ForegroundColor Yellow
    Write-Host ""
    
    # Construir comando
    $remoteCmd = "cd ~/domains/lojadaesquina.store/public_html && php process_all_leads.php"
    
    if ($DryRun) {
        $remoteCmd += " --dry-run"
    }
    if ($OnlySms) {
        $remoteCmd += " --only-sms"
    }
    if ($OnlyProfiles) {
        $remoteCmd += " --only-profiles"
    }
    
    Write-Host "Executando: $remoteCmd" -ForegroundColor Gray
    Write-Host ""
    
    # Executar no servidor
    plink -batch -ssh u815655858@145.223.105.168 -P 65002 -pw MundoMelhor@10 $remoteCmd
    
} else {
    Write-Host "💻 MODO LOCAL - Executando localmente" -ForegroundColor Yellow
    Write-Host ""
    
    # Construir comando local
    $localCmd = "php process_all_leads.php"
    
    if ($DryRun) {
        $localCmd += " --dry-run"
    }
    if ($OnlySms) {
        $localCmd += " --only-sms"
    }
    if ($OnlyProfiles) {
        $localCmd += " --only-profiles"
    }
    
    Write-Host "Executando: $localCmd" -ForegroundColor Gray
    Write-Host ""
    
    # Executar localmente
    Invoke-Expression $localCmd
}

Write-Host ""
Write-Host "✅ Execução concluída!" -ForegroundColor Green
Write-Host ""
Write-Host "Exemplos de uso:" -ForegroundColor Cyan
Write-Host "  .\process-leads.ps1 -DryRun                  # Simular localmente" -ForegroundColor Gray
Write-Host "  .\process-leads.ps1                          # Executar localmente" -ForegroundColor Gray
Write-Host "  .\process-leads.ps1 -Production -DryRun      # Simular em produção" -ForegroundColor Gray
Write-Host "  .\process-leads.ps1 -Production              # Executar em produção" -ForegroundColor Gray
Write-Host "  .\process-leads.ps1 -OnlySms -Production     # Apenas SMS em produção" -ForegroundColor Gray
Write-Host "  .\process-leads.ps1 -OnlyProfiles            # Apenas criar perfis" -ForegroundColor Gray
Write-Host ""
