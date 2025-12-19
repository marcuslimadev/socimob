#!/bin/bash
# Script de inicialização rápida do projeto SOCIMOB

set -e

GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${BLUE}"
echo "╔════════════════════════════════════════════════════════════╗"
echo "║          SOCIMOB SaaS - Frontend & Backend Setup           ║"
echo "║              Complete Development Environment              ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo -e "${NC}"

# Cores e paths
BACKEND_PATH="./backend"
FRONTEND_PATH="./frontend"
DOCKER_PATH="./docker"

# Função para executar comando com feedback
run_step() {
    local step=$1
    local command=$2
    local description=$3
    
    echo -e "${YELLOW}[$step]${NC} ${description}..."
    if eval "$command"; then
        echo -e "${GREEN}✓${NC} ${description} concluído"
    else
        echo -e "${RED}✗${NC} Erro ao ${description}"
        exit 1
    fi
    echo ""
}

# 1. Backend
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}BACKEND SETUP${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo ""

if [ -d "$BACKEND_PATH" ]; then
    cd "$BACKEND_PATH"
    
    run_step "1" "composer install" "Instalar dependências PHP"
    run_step "2" "cp .env.example .env" "Copiar arquivo .env"
    run_step "3" "php artisan key:generate" "Gerar chave de aplicação"
    run_step "4" "php artisan migrate:fresh" "Executar migrações"
    run_step "5" "php vendor/bin/phpunit" "Executar testes"
    
    cd ..
else
    echo -e "${RED}Backend path not found: $BACKEND_PATH${NC}"
    exit 1
fi

# 2. Frontend
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}FRONTEND SETUP${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo ""

if [ -d "$FRONTEND_PATH" ]; then
    cd "$FRONTEND_PATH"
    
    run_step "6" "npm install" "Instalar dependências Node.js"
    run_step "7" "cp .env.example .env || echo 'VITE_API_URL=http://localhost:8000' > .env" "Configurar variáveis"
    run_step "8" "npm run build" "Build de produção"
    
    cd ..
else
    echo -e "${RED}Frontend path not found: $FRONTEND_PATH${NC}"
    exit 1
fi

# 3. Docker (opcional)
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo -e "${BLUE}DOCKER SETUP (Opcional)${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo ""

if [ -f "$DOCKER_PATH/docker-compose.yml" ]; then
    echo -e "${YELLOW}[9]${NC} Iniciar containers Docker..."
    cd "$DOCKER_PATH"
    
    if docker-compose up -d; then
        echo -e "${GREEN}✓${NC} Docker iniciado"
    else
        echo -e "${YELLOW}!${NC} Docker não disponível (continue manualmente)"
    fi
    
    cd ../..
else
    echo -e "${YELLOW}!${NC} Docker não disponível"
fi

echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}✓ SETUP CONCLUÍDO!${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo ""

echo -e "${YELLOW}PRÓXIMOS PASSOS:${NC}"
echo ""
echo -e "${GREEN}1. Iniciar Backend:${NC}"
echo "   cd backend"
echo "   php artisan serve"
echo "   # Acesso: http://localhost:8000"
echo ""
echo -e "${GREEN}2. Iniciar Frontend:${NC}"
echo "   cd frontend"
echo "   npm run dev"
echo "   # Acesso: http://localhost:5173"
echo ""
echo -e "${GREEN}3. Testar Login:${NC}"
echo "   Email: super@test.com"
echo "   Senha: password"
echo ""
echo -e "${YELLOW}RECURSOS:${NC}"
echo "   • Backend Testes: /backend/tests/Feature/"
echo "   • Frontend Docs: /frontend/QUICK_START.md"
echo "   • API Docs: /backend/RELATORIO_TESTES.md"
echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
