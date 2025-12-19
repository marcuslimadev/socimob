@echo off
echo ========================================
echo   EXCLUSIVA - Plataforma SaaS Imobiliaria
echo   Servidor Unico PHP
echo ========================================
echo.
echo Iniciando servidor PHP na porta 8000...
echo.
echo Acesse o sistema em:
echo   Homepage:        http://127.0.0.1:8000
echo   Admin:           http://127.0.0.1:8000/app/
echo   Portal Cliente:  http://127.0.0.1:8000/portal/
echo   API:             http://127.0.0.1:8000/api/
echo.
echo Para parar o servidor, feche esta janela ou pressione Ctrl+C
echo.
echo ========================================
echo.

cd /d "%~dp0"
php -S 127.0.0.1:8000 -t public router.php

pause
