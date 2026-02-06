#!/bin/bash

# Deploy Script - Sistema de Perfil de Leads
# Commit: a940ac4
# Features: LeadProfile component, upload/download documents, ZIP export

echo "=== DEPLOY SISTEMA DE PERFIL DE LEADS ==="
echo "Conectando ao servidor..."

ssh -p 65002 u815655858@145.223.105.168 << 'ENDSSH'
  cd ~/domains/lojadaesquina.store/public_html
  
  echo "1. Fazendo git pull..."
  git pull origin master
  
  echo "2. Instalando dependências..."
  pnpm install
  
  echo "3. Buildando frontend..."
  pnpm build
  
  echo "4. Limpando caches do Laravel..."
  php artisan cache:clear
  php artisan config:clear
  php artisan route:clear
  
  echo "5. Verificando saúde da aplicação..."
  curl -s https://lojadaesquina.store/api/health | grep -q "ok" && echo "✓ API está online" || echo "✗ API com problemas"
  
  echo "=== DEPLOY CONCLUÍDO ==="
  echo "Acesse: https://lojadaesquina.store/leads/[ID] para testar o novo perfil"
  
ENDSSH

echo ""
echo "Deploy finalizado!"
echo ""
echo "NOVO SISTEMA DISPONÍVEL:"
echo "- Perfil completo de leads em /leads/:id"
echo "- Upload de documentos (PDF, DOC, imagens)"
echo "- Download individual ou ZIP de todos documentos"
echo "- Tabs: Informações, Documentos, Intenções, Atividades"
echo "- Click no LeadCard abre o perfil automaticamente"
