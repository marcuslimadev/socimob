@echo off
echo.
echo ========================================
echo   EXCLUSIVA - Servidor para Ngrok
echo   Escutando em TODAS as interfaces
echo ========================================
echo.

cd /d "%~dp0"

:START
echo [%date% %time%] Iniciando servidor PHP em 0.0.0.0:8000...
php -S 0.0.0.0:8000 -t public

echo.
echo [%date% %time%] Servidor parou. Reiniciando em 3 segundos...
timeout /t 3 /nobreak > nul
goto START
