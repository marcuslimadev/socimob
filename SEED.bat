@echo off
echo 🌱 Executando Seeders da Exclusiva...
echo ======================================

REM Verificar se estamos no diretório correto
if not exist "composer.json" (
    echo ❌ Execute este script na raiz do projeto!
    pause
    exit /b 1
)

REM Verificar se vendor existe
if not exist "vendor" (
    echo 📦 Instalando dependências...
    composer install
)

REM Executar seeders
echo 🚀 Populando banco de dados...
php database\seeders\DatabaseSeeder.php

echo.
echo ✅ Seeders executados!
echo.
echo 🎯 Para iniciar o servidor:
echo    START.bat
echo    ou: php -S 127.0.0.1:8000 -t public

pause