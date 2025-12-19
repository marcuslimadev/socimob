#!/bin/bash
# Script para testar o frontend com o backend

# Cores para output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${BLUE}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   SOCIMOB SaaS - Frontend Testing Script                  ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""

# Verificar Node.js
echo -e "${YELLOW}[1/5]${NC} Verificando Node.js..."
if command -v node &> /dev/null; then
    echo -e "${GREEN}✓${NC} Node.js $(node --version) instalado"
else
    echo -e "${RED}✗${NC} Node.js não encontrado"
    exit 1
fi

# Instalar dependências
echo ""
echo -e "${YELLOW}[2/5]${NC} Instalando dependências..."
cd "$(dirname "$0")"
if npm install; then
    echo -e "${GREEN}✓${NC} Dependências instaladas"
else
    echo -e "${RED}✗${NC} Erro ao instalar dependências"
    exit 1
fi

# Validar estrutura de pastas
echo ""
echo -e "${YELLOW}[3/5]${NC} Validando estrutura de pastas..."
required_dirs=("src/composables" "src/components" "src/views" "src/router" "src/services")
for dir in "${required_dirs[@]}"; do
    if [ -d "$dir" ]; then
        echo -e "${GREEN}✓${NC} $dir"
    else
        echo -e "${RED}✗${NC} $dir não encontrado"
    fi
done

# Verificar variáveis de ambiente
echo ""
echo -e "${YELLOW}[4/5]${NC} Verificando configuração..."
if [ -f ".env" ]; then
    API_URL=$(grep "VITE_API_URL" .env)
    echo -e "${GREEN}✓${NC} .env encontrado: $API_URL"
else
    echo -e "${YELLOW}!${NC} .env não encontrado. Criando com defaults..."
    cat > .env << EOF
VITE_API_URL=http://localhost:8000
EOF
    echo -e "${GREEN}✓${NC} .env criado"
fi

# Build de desenvolvimento
echo ""
echo -e "${YELLOW}[5/5]${NC} Iniciando servidor de desenvolvimento..."
echo ""
echo -e "${GREEN}╔════════════════════════════════════════════════════════════╗${NC}"
echo -e "${GREEN}║   Frontend está rodando!                                  ║${NC}"
echo -e "${GREEN}║   URL: http://localhost:5173                              ║${NC}"
echo -e "${GREEN}║                                                            ║${NC}"
echo -e "${GREEN}║   Composables disponíveis:                                ║${NC}"
echo -e "${GREEN}║   • useAuth() - Autenticação e RBAC                       ║${NC}"
echo -e "${GREEN}║   • useTenant() - Gerenciar tenants                       ║${NC}"
echo -e "${GREEN}║   • useUsers() - Gerenciar usuários                       ║${NC}"
echo -e "${GREEN}║   • useProperties() - Gerenciar imóveis                   ║${NC}"
echo -e "${GREEN}║   • usePropertyImport() - Importar via CSV                ║${NC}"
echo -e "${GREEN}║                                                            ║${NC}"
echo -e "${GREEN}║   Press Ctrl+C para parar                                 ║${NC}"
echo -e "${GREEN}╚════════════════════════════════════════════════════════════╝${NC}"
echo ""

npm run dev
