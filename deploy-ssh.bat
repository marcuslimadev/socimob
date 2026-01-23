@echo off
echo ========================================
echo   DEPLOY SSH - LOJADAESQUINA.STORE
echo ========================================
echo.
echo Credenciais:
echo   Host: 145.223.105.168
echo   Port: 65002
echo   User: u815655858
echo   Pass: MundoMelhor@10
echo   Path: ~/domains/lojadaesquina.store/public_html
echo.
echo ========================================
echo.
echo Conectando ao servidor...
echo.

REM Usar o script bash que criamos
type deploy-server.sh | ssh -p 65002 u815655858@145.223.105.168 "bash -s"

echo.
echo ========================================
echo   DEPLOY CONCLUIDO!
echo ========================================
echo.
echo Acesse: https://lojadaesquina.store
echo API: https://lojadaesquina.store/api/health
echo.
pause
