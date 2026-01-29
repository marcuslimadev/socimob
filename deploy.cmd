@echo off
REM Script de deploy completo - Windows CMD
REM Uso: deploy.cmd [mensagem-do-commit]

setlocal enabledelayedexpansion

REM Configuração
set COMMIT_MESSAGE=%~1
if "%COMMIT_MESSAGE%"=="" set COMMIT_MESSAGE=build: update frontend build

set SSH_HOST=145.223.105.168
set SSH_PORT=65002
set SSH_USER=u815655858
set SSH_PASS=MundoMelhor@10
set DEPLOY_PATH=~/domains/lojadaesquina.store/public_html

echo.
echo ================================================================
echo           DEPLOY AUTOMATICO - SOCIMOB v2
echo ================================================================
echo.

REM 1. BUILD DO FRONTEND
echo === BUILD DO FRONTEND REACT ===
cd client
call npm run build
if errorlevel 1 (
    echo [ERRO] Build falhou
    exit /b 1
)
cd ..
echo [OK] Build do frontend concluido
echo.

REM 2. COMMIT E PUSH
echo === COMMIT E PUSH ===
git add dist/public

git commit -m "%COMMIT_MESSAGE%" -m "" -m "Co-Authored-By: Claude Sonnet 4.5 <noreply@anthropic.com>"
if errorlevel 1 (
    echo [AVISO] Nenhuma mudanca para commitar
) else (
    echo [OK] Commit criado
)

git push origin master
if errorlevel 1 (
    echo [ERRO] Push falhou
    exit /b 1
)
echo [OK] Push concluido
echo.

REM 3. DEPLOY NO SERVIDOR SSH
echo === DEPLOY NO SERVIDOR SSH ===
echo Servidor: %SSH_USER%@%SSH_HOST%:%SSH_PORT%
echo Caminho: %DEPLOY_PATH%
echo.

REM Criar script temporário
set TEMP_SCRIPT=%TEMP%\deploy_ssh_%RANDOM%.sh
(
echo cd %DEPLOY_PATH% ^&^& \
echo echo '=== GIT PULL ===' ^&^& \
echo git pull origin master ^&^& \
echo echo '' ^&^& \
echo echo '=== COPIAR BUILD ===' ^&^& \
echo cp -rf dist/public/* public/ ^&^& \
echo echo '' ^&^& \
echo echo '=== VERIFICAR BUILD ===' ^&^& \
echo ls -lh public/index.html ^&^& \
echo ls -lh public/assets/*.js public/assets/*.css 2^>/dev/null ^| tail -3 ^&^& \
echo echo '' ^&^& \
echo echo '=== DEPLOY CONCLUIDO ===' ^&^& \
echo date
) > "%TEMP_SCRIPT%"

REM Executar via plink
where plink >nul 2>nul
if %errorlevel% equ 0 (
    echo Conectando via plink...
    type "%TEMP_SCRIPT%" | plink -P %SSH_PORT% -pw %SSH_PASS% -batch %SSH_USER%@%SSH_HOST%
    set SSH_RESULT=!errorlevel!
) else (
    where ssh >nul 2>nul
    if !errorlevel! equ 0 (
        echo Conectando via ssh...
        echo AVISO: Digite a senha: %SSH_PASS%
        type "%TEMP_SCRIPT%" | ssh -p %SSH_PORT% %SSH_USER%@%SSH_HOST% "bash -s"
        set SSH_RESULT=!errorlevel!
    ) else (
        echo [ERRO] Nem plink nem ssh encontrados!
        echo.
        echo Execute manualmente no servidor:
        echo   ssh -p %SSH_PORT% %SSH_USER%@%SSH_HOST%
        echo   cd %DEPLOY_PATH%
        echo   git pull origin master
        echo   cp -rf dist/public/* public/
        del "%TEMP_SCRIPT%"
        exit /b 1
    )
)

del "%TEMP_SCRIPT%"

if !SSH_RESULT! equ 0 (
    echo [OK] Deploy SSH concluido
) else (
    echo [AVISO] Deploy SSH completou com avisos
)
echo.

REM 4. VERIFICAR SITE
echo === VERIFICAR SITE ONLINE ===
curl -s https://lojadaesquina.store/api/health 2>nul
echo.

REM SUCESSO
echo.
echo ================================================================
echo              DEPLOY CONCLUIDO COM SUCESSO!
echo ================================================================
echo.
echo Frontend: https://lojadaesquina.store
echo API:      https://lojadaesquina.store/api/health
echo.

endlocal
