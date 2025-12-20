#!/bin/bash

# ============================================================================
# DIAGNÓSTICO E CORREÇÃO 403 - Exclusiva SaaS
# ============================================================================
# Script para resolver erro 403 Forbidden na Hostinger

echo "🔍 Diagnóstico do Erro 403 Forbidden"
echo "===================================="

# ============================================================================
# 1. VERIFICAR ESTRUTURA DE DIRETÓRIOS
# ============================================================================
echo ""
echo "📁 Verificando estrutura..."

if [ -f "public/index.php" ]; then
    echo "✅ public/index.php encontrado"
else
    echo "❌ public/index.php NÃO encontrado"
    echo "💡 O servidor precisa apontar para a pasta /public"
fi

if [ -f ".htaccess" ]; then
    echo "✅ .htaccess na raiz encontrado"
else
    echo "⚠️  .htaccess na raiz não encontrado"
fi

if [ -f "public/.htaccess" ]; then
    echo "✅ public/.htaccess encontrado"
else
    echo "❌ public/.htaccess NÃO encontrado - CRIANDO..."
    cat > public/.htaccess << 'EOF'
<IfModule mod_rewrite.c>
    <IfModule mod_negotiation.c>
        Options -MultiViews
    </IfModule>

    RewriteEngine On

    # Handle Angular and Vue history mode
    RewriteCond %{REQUEST_FILENAME} -d [OR]
    RewriteCond %{REQUEST_FILENAME} -f
    RewriteRule ^ ^$1 [N]

    RewriteRule ^ index.php [L]
</IfModule>
EOF
    echo "✅ public/.htaccess criado"
fi

# ============================================================================
# 2. VERIFICAR E CORRIGIR PERMISSÕES
# ============================================================================
echo ""
echo "🔐 Corrigindo permissões..."

# Permissões de diretórios (755)
find . -type d -exec chmod 755 {} \; 2>/dev/null
echo "✅ Permissões de diretórios: 755"

# Permissões de arquivos (644)
find . -type f -exec chmod 644 {} \; 2>/dev/null
echo "✅ Permissões de arquivos: 644"

# Permissões especiais
chmod -R 775 storage bootstrap/cache 2>/dev/null
chmod 644 public/index.php 2>/dev/null
chmod +x *.sh scripts/*.sh 2>/dev/null

echo "✅ Permissões especiais configuradas"

# ============================================================================
# 3. VERIFICAR CONFIGURAÇÃO DO SERVIDOR
# ============================================================================
echo ""
echo "🌐 Verificando configuração..."

# Criar .htaccess na raiz se não existir
if [ ! -f ".htaccess" ]; then
    cat > .htaccess << 'EOF'
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
EOF
    echo "✅ .htaccess na raiz criado (redireciona para /public)"
fi

# Verificar se há index.html conflitante
if [ -f "index.html" ]; then
    echo "⚠️  index.html encontrado na raiz - pode estar conflitando"
    echo "💡 Considere renomear para index.html.bak"
fi

if [ -f "public/index.html" ]; then
    echo "⚠️  public/index.html encontrado - pode estar conflitando"
    echo "💡 Removendo index.html para dar prioridade ao index.php"
    mv public/index.html public/index.html.bak 2>/dev/null
fi

# ============================================================================
# 4. TESTAR ACESSO
# ============================================================================
echo ""
echo "🧪 Testando arquivos..."

if [ -r "public/index.php" ]; then
    echo "✅ public/index.php é legível"
    
    # Verificar se o PHP está funcionando
    if php public/index.php > /dev/null 2>&1; then
        echo "✅ public/index.php executa sem erro"
    else
        echo "❌ public/index.php tem erro de sintaxe"
        echo "🔧 Verificando erro:"
        php -l public/index.php
    fi
else
    echo "❌ public/index.php não é legível"
fi

# ============================================================================
# 5. INFORMAÇÕES PARA O PAINEL HOSTINGER
# ============================================================================
echo ""
echo "📋 CONFIGURAÇÃO DO PAINEL HOSTINGER:"
echo "===================================="
echo ""
echo "🎯 DOCUMENT ROOT deve apontar para:"
echo "   $(pwd)/public"
echo ""
echo "📁 Ou mover arquivos para:"
echo "   mv public/* ./"
echo "   mv public/.htaccess ./"
echo ""
echo "🌐 URLs para testar:"
echo "   https://lojadaesquina.store/"
echo "   https://lojadaesquina.store/app/"
echo ""

# ============================================================================
# 6. CRIAR ESTRUTURA ALTERNATIVA (SE NECESSÁRIO)
# ============================================================================
echo ""
echo "🔄 OPÇÃO ALTERNATIVA (se Document Root não puder ser alterado):"
echo ""
read -p "Quer mover arquivos do /public para raiz? (s/n): " -n 1 -r
echo
if [[ $REPLY =~ ^[Ss]$ ]]; then
    echo "📦 Movendo arquivos..."
    
    # Backup
    cp -r public public_backup
    
    # Mover arquivos
    cp -r public/* ./
    cp public/.htaccess ./ 2>/dev/null || true
    
    # Ajustar paths no index.php
    sed -i 's|../bootstrap/app.php|bootstrap/app.php|g' index.php 2>/dev/null
    
    echo "✅ Arquivos movidos para raiz"
    echo "⚠️  Backup em public_backup/"
fi

echo ""
echo "✅ DIAGNÓSTICO CONCLUÍDO!"
echo ""
echo "📞 Se ainda der 403:"
echo "1. Verifique no painel Hostinger se Document Root aponta para /public"
echo "2. Ou execute este script e aceite mover arquivos para raiz"
echo "3. Verifique logs do servidor no painel Hostinger"