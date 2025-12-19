@echo off
echo ========================================
echo   DIAGNÓSTICO RÁPIDO - SERVIDOR PHP
echo ========================================
echo.

echo 1. Parando processos PHP existentes...
taskkill /F /IM php.exe 2>nul
timeout /t 2 /nobreak >nul

echo 2. Verificando porta 8000...
netstat -an | findstr :8000

echo.
echo 3. Navegando para pasta do projeto...
cd /d "C:\Projetos\saas\backend"

echo 4. Verificando arquivos essenciais...
if exist "public\index.php" (
    echo ✅ public\index.php - OK
) else (
    echo ❌ public\index.php - FALTANDO
)

if exist "router.php" (
    echo ✅ router.php - OK
) else (
    echo ❌ router.php - FALTANDO
)

if exist "public\app\imoveis.html" (
    echo ✅ public\app\imoveis.html - OK
) else (
    echo ❌ public\app\imoveis.html - FALTANDO
)

echo.
echo 5. Iniciando servidor PHP...
echo URL de teste: http://127.0.0.1:8000/debug-routes.html
echo.
start "" "http://127.0.0.1:8000/debug-routes.html"
php -S 127.0.0.1:8000 -t public router.php

pause