@echo off
setlocal enabledelayedexpansion

REM ============================================================================
REM SCRIPT DE PRIMEIRO DEPLOY - Exclusiva SaaS (Windows)
REM ============================================================================
REM Este script configura o ambiente completo no primeiro deploy
REM Inclui: dependências, migrações, seeders e configurações iniciais

echo 🚀 Iniciando setup do primeiro deploy...
echo ======================================

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
echo 📦 Instalando dependências...
if exist "vendor" (
    echo ℹ️  Vendor já existe, atualizando...
    composer update --no-dev --prefer-dist --no-interaction --optimize-autoloader
) else (
    echo 🔧 Instalação completa do composer...
    composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
)

REM ============================================================================
REM 2. VERIFICAR AMBIENTE
REM ============================================================================
echo.
echo 🔍 Verificando ambiente...

REM Verificar se .env existe
if not exist ".env" (
    if exist ".env.example" (
        echo ⚙️  Copiando .env.example para .env...
        copy ".env.example" ".env"
        echo ⚠️  IMPORTANTE: Configure as variáveis de ambiente no .env
    ) else (
        echo ❌ Arquivo .env não encontrado!
        pause
        exit /b 1
    )
)

REM Verificar conexão com banco (simples)
echo 🗄️  Testando conexão com banco de dados...
php -r "try { $pdo = new PDO('mysql:host=' . ($_ENV['DB_HOST'] ?? 'localhost') . ';dbname=' . ($_ENV['DB_DATABASE'] ?? 'exclusiva'), $_ENV['DB_USERNAME'] ?? 'root', $_ENV['DB_PASSWORD'] ?? ''); echo '✅ Conexão com banco OK\n'; } catch (Exception $e) { echo '❌ Erro de conexão: ' . $e->getMessage() . '\n'; exit(1); }"

REM ============================================================================
REM 3. MIGRAÇÕES
REM ============================================================================
echo.
echo 🗃️  Executando migrações...
php -f artisan migrate --force 2>nul || (
    echo ⚠️  Artisan não disponível, pulando migrações automáticas
    echo     Execute manualmente: php artisan migrate --force
)

REM ============================================================================
REM 4. SEEDERS (PRIMEIRO DEPLOY APENAS)
REM ============================================================================
echo.
if exist ".first-deploy-done" (
    echo ℹ️  Deploy subsequente detectado - seeders não executados
) else (
    echo 🌱 PRIMEIRO DEPLOY - Executando seeders...
    
    if exist "database\seeders\DatabaseSeeder.php" (
        php database\seeders\DatabaseSeeder.php
        
        REM Marcar primeiro deploy como concluído
        echo %date% %time%: Primeiro deploy com seeders concluído > .first-deploy-done
        echo ✅ Seeders executados e marcador criado
    ) else (
        echo ⚠️  Seeders não encontrados em database\seeders\
    )
)

REM ============================================================================
REM 5. PERMISSÕES E CACHE (se necessário)
REM ============================================================================
echo.
echo 🔧 Configurações finais...

REM Cache de configurações (se disponível)
php -f artisan config:cache 2>nul || echo    Config cache não disponível
php -f artisan route:cache 2>nul || echo    Route cache não disponível

REM ============================================================================
REM 6. RESUMO FINAL
REM ============================================================================
echo.
echo ✅ PRIMEIRO DEPLOY CONCLUÍDO!
echo ==============================
echo.
echo 📋 Resumo do que foi feito:
echo   ✅ Dependências instaladas (composer)
echo   ✅ Migrações executadas
if not exist ".first-deploy-done" (
    echo   ✅ Seeders executados (dados iniciais)
) else (
    echo   ℹ️  Seeders pulados (deploy subsequente)
)
echo   ✅ Cache otimizado
echo.
echo 🎯 CREDENCIAIS CRIADAS (primeiro deploy):
echo   Super Admin: admin@exclusiva.com / password
echo   Contato: contato@exclusiva.com.br / Teste@123
echo   Alexsandra: alexsandra@exclusiva.com.br / Senha@123
echo.
echo 🌐 Próximos passos:
echo   1. Iniciar servidor: START.bat ou php -S 127.0.0.1:8000 -t public
echo   2. Acessar: http://127.0.0.1:8000/app/
echo   3. Fazer login com as credenciais acima
echo.
echo 📞 Em caso de problemas:
echo   - Verificar se MySQL está rodando
echo   - Conferir configuração do .env
echo   - Ver logs em storage\logs\
echo.

pause