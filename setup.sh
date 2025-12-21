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
echo "║             SOCIMOB SaaS - Ambiente Completo               ║"
echo "║      Backend com frontend estático em HTML/jQuery          ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo -e "${NC}"

# Detectar onde o backend está localizado (raiz ou pasta backend)
if [ -d "./backend" ] && [ -f "./backend/composer.json" ]; then
    BACKEND_PATH="./backend"
elif [ -f "./composer.json" ]; then
    BACKEND_PATH="."
else
    echo -e "${RED}Nenhum backend detectado neste diretório.${NC}"
    echo "Certifique-se de estar no repositório correto."
    exit 1
fi

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

echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo -e "${GREEN}✓ SETUP CONCLUÍDO!${NC}"
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
echo ""

echo -e "${YELLOW}PRÓXIMOS PASSOS:${NC}"
echo ""
echo -e "${GREEN}1. Iniciar Backend:${NC}"
if [ "$BACKEND_PATH" != "." ]; then
    echo "   cd $BACKEND_PATH"
fi
echo "   php artisan serve"
echo "   # Acesso: http://localhost:8000"
echo ""
echo -e "${GREEN}2. Testar Login:${NC}"
echo "   Email: super@test.com"
echo "   Senha: password"
echo ""
echo -e "${YELLOW}RECURSOS:${NC}"
echo "   • Backend Testes: ${BACKEND_PATH}/tests/Feature/"
echo "   • API Docs: ${BACKEND_PATH}/RELATORIO_TESTES.md"
echo ""
echo -e "${BLUE}═══════════════════════════════════════════════════════════${NC}"
