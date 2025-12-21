# Plano de testes ponta a ponta (backend e frontend)

Plano executável pelo agente para validar fluxos críticos do sistema imobiliário em ambiente local ou CI, desde o clone do repositório até a verificação de UX nos navegadores.

## 1) Preparação do ambiente
1. Clonar o repositório
   ```bash
   git clone <URL_DO_REPO> socimob && cd socimob
   ```
2. Configurar `.env` do backend
   ```bash
   cd backend
   cp .env.example .env
   php -r "copy('.env.example', '.env');"  # alternativa
   # Ajustar DB_* e APP_URL para apontar para o serviço local
   ```
3. Subir dependências do backend
   ```bash
   composer install
   php artisan key:generate || true  # Lumen ignora, manter para compat
   php artisan migrate --seed
   ```
4. Subir backend
   ```bash
   php -S 0.0.0.0:8000 -t public
   ```
5. Configurar frontend
   ```bash
   cd ../frontend
   cp .env.example .env.local
   npm install
   npx playwright install
   npm run dev -- --host 0.0.0.0 --port 3000
   ```

## 2) Testes automatizados
1. Backend (API)
   ```bash
   cd backend
   php artisan test --env=testing
   ```
2. Frontend (unitário/integração + e2e)
   ```bash
   cd frontend
   npm test               # Jest/RTL
   npm run test:e2e       # Playwright (requer servidor em 3000)
   ```

## 3) Testes manuais guiados (fluxos essenciais)
### A. Autenticação e onboarding
1. Registrar e logar usuário gestor via e-mail/senha.
2. Login com Google (OAuth) pelo app web; confirmar criação de conta/tenant.
3. Sair e relogar para validar persistência de sessão.

### B. Isolamento multi-tenant
1. Criar dois tenants distintos (gestores diferentes).
2. Criar leads em cada tenant e confirmar que listagens e buscas não cruzam dados.
3. Garantir que URLs diretas de leads/conversas retornem 404 quando o `tenant_id` divergir.

### C. Gestão de leads
1. Importar imóveis e leads (CSV ou integração existente) e validar campos obrigatórios.
2. Visualizar leads no painel, aplicar filtros e paginação.
3. Atribuir lead a corretor (“pegar atendimento”) e verificar que outros corretores ficam bloqueados.
4. Registrar mensagens/conversas; confirmar histórico por lead e por corretor.
5. Arquivar lead, restaurar lead e consultar histórico de mudanças.

### D. Página de vendas e templates
1. Acessar painel do gestor e escolher um template de página de vendas.
2. Visualizar prévia do template e publicar.
3. Abrir página pública e confirmar renderização dos imóveis do tenant correto.

### E. Portal do corretor
1. Corretor faz login e lista seus leads.
2. Tenta capturar lead já atribuído e recebe bloqueio.
3. Registra atividade (nota/documento/upload) e valida visibilidade ao gestor.

### F. Portal do cliente
1. Cliente acessa login público e registra via Google.
2. Visualiza imóveis recomendados (matches) e envia mensagens; gestor/corretor visualizam no painel.

## 4) Observabilidade e logs
1. Monitorar logs do backend durante os fluxos
   ```bash
   cd backend
   tail -f storage/logs/lumen.log
   ```
2. Coletar console do navegador e network para erros de SPA.

## 5) Critérios de aceitação
- Todos os testes automatizados passam (backend e frontend).
- Fluxos manuais acima executados sem erros visíveis ou dados cruzados entre tenants.
- Login com Google funcional em frontend e backend (usar credenciais de teste).
- Templates de página de vendas podem ser escolhidos, visualizados e publicados.
- Leads podem ser atribuídos, arquivados, restaurados e pesquisados com histórico completo.
