#!/bin/bash
cd ~/domains/lojadaesquina.store/public_html
echo "=== DIRETORIO ATUAL ==="
pwd
echo ""
echo "=== GIT STATUS ==="
git status
echo ""
echo "=== GIT PULL ==="
git pull origin master
echo ""

# Verificar se pnpm está instalado
if ! command -v pnpm &> /dev/null; then
    echo "=== INSTALAR PNPM ==="
    npm install -g pnpm 2>/dev/null || echo "AVISO: Não foi possível instalar pnpm globalmente"
fi

# Build do frontend React
if [ -f "package.json" ]; then
    echo "=== INSTALAR DEPENDENCIAS NODE ==="
    pnpm install --frozen-lockfile 2>/dev/null || npm install 2>/dev/null || echo "AVISO: Falha ao instalar dependências Node"
    echo ""
    echo "=== BUILD FRONTEND REACT ==="
    pnpm build 2>/dev/null || npm run build 2>/dev/null || echo "AVISO: Falha no build do frontend"
    echo ""
    
    # Copiar build do React (dist/public) para public/
    if [ -d "dist/public" ]; then
        echo "=== COPIAR BUILD REACT PARA PUBLIC ==="
        # Preservar public/index.php (backend Lumen) e public/app/ (admin HTML antigo se existir)
        cp -r dist/public/* public/ 2>/dev/null || echo "AVISO: Falha ao copiar build"
        echo "Build React copiado para public/"
        ls -lh public/index.html 2>/dev/null && echo "✓ Frontend React instalado" || echo "✗ Frontend não encontrado"
        echo ""
    else
        echo "AVISO: Pasta dist/public não encontrada após build"
        echo ""
    fi
fi

echo "=== LOCALIZAR COMPOSER ==="
which composer || command -v composer || echo "Composer nao encontrado no PATH"
echo ""
echo "=== RECARREGAR AUTOLOAD ==="
php composer.phar dump-autoload --optimize 2>/dev/null || composer dump-autoload --optimize 2>/dev/null || echo "Composer nao disponivel"
echo ""
echo "=== AJUSTAR PERMISSOES ==="
chmod -R 755 storage bootstrap/cache public 2>/dev/null || echo "Permissoes ajustadas conforme possivel"
# Garantir permissões de escrita para storage
chmod -R 775 storage 2>/dev/null || echo "Storage já com permissões corretas"
echo ""
echo "=== DEPLOY CONCLUIDO ==="
date
