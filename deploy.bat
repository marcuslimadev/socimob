@echo off
REM ============================================================================
REM DEPLOY SIMPLES - Hostinger/Manual (Windows)
REM ============================================================================
REM Script para deploy direto no servidor sem GitHub Actions

echo 🚀 Deploy Manual - Exclusiva SaaS
echo =================================

REM Verificar se estamos no diretório correto
if not exist "composer.json" (
    echo ❌ Execute este script na raiz do projeto!
    pause
    exit /b 1
)

REM ============================================================================
REM 1. DEPENDÊNCIAS
REM ============================================================================
echo.
echo 📦 Instalando/atualizando dependências...
composer install --no-dev --optimize-autoloader

REM ============================================================================
REM 2. MIGRAÇÕES  
REM ============================================================================
echo.
echo 🗃️  Executando migrações...
php artisan migrate --force

REM ============================================================================
REM 3. SEEDERS (apenas primeiro deploy)
REM ============================================================================
echo.
if exist ".first-deploy-done" (
    echo ℹ️  Deploy subsequente - seeders não executados
    echo    Dados existentes preservados
) else (
    echo 🌱 PRIMEIRO DEPLOY - Executando seeders...
    php database\seeders\DatabaseSeeder.php
    echo %date% %time%: Primeiro deploy concluído > .first-deploy-done
    echo ✅ Dados iniciais criados!
)

REM ============================================================================
REM 4. CACHE E PERMISSÕES
REM ============================================================================
echo.
echo ⚙️  Configurações finais...
php artisan config:cache 2>nul || echo    Config cache não disponível

REM ============================================================================
REM 5. RESULTADO
REM ============================================================================
echo.
echo ✅ DEPLOY CONCLUÍDO!
echo ===================
echo.
echo 🎯 Sistema pronto para uso:
echo   📧 Admin: contato@exclusiva.com.br / Teste@123
echo   📧 Super: admin@exclusiva.com / password
echo.
echo 🌐 Próximo passo:
echo   Acesse: https://seu-dominio.com/app/
echo.

pause