# 🚀 SOCIMOB - Stack Moderna Integrada

## Arquitetura Full-Stack

### Backend: Lumen PHP (porta 8000)
- **Framework**: Lumen 10 (micro-framework Laravel)
- **Database**: MySQL via XAMPP
- **Multi-tenancy**: Trait BelongsToTenant + middleware ResolveTenant
- **APIs REST**: 
  - `/api/super-admin/*` - Gestão de tenants
  - `/api/admin/*` - Admin/Corretores
  - `/api/portal/*` - Portal público
  - `/api/leads/*` - Gestão de leads
  - `/api/imoveis/*` - Gestão de imóveis
  - `/api/conversas/*` - Chat/WhatsApp

### Frontend: React + TypeScript (porta 3000)
- **Framework**: React 19 + TypeScript
- **Build Tool**: Vite 7
- **UI Library**: Radix UI + shadcn/ui
- **Styling**: TailwindCSS 4
- **Routing**: Wouter (client-side)
- **State**: React Context API
- **HTTP Client**: Axios
- **Package Manager**: pnpm

### Proxy Configuration
O Vite faz proxy das chamadas `/api` e `/storage` para o backend PHP na porta 8000, permitindo desenvolvimento sem CORS issues.

## Scripts Disponíveis

### Desenvolvimento
```bash
# Backend apenas (porta 8000)
php -S 127.0.0.1:8000 -t public router.php

# Frontend apenas (porta 3000)
pnpm dev

# Full-stack (ambos em janelas separadas)
# Backend: php -S 127.0.0.1:8000 -t public router.php
# Frontend: pnpm dev
```

### Build Produção
```bash
# Frontend
pnpm build   # Gera dist/public

# Backend
# Já está pronto (PHP não precisa build)
```

## Estrutura do Projeto

```
socimobatual/
├── app/                    # Backend Lumen
│   ├── Http/
│   │   ├── Controllers/
│   │   └── Middleware/
│   ├── Models/
│   ├── Services/
│   └── Observers/
├── client/                 # Frontend React
│   ├── src/
│   │   ├── components/    # UI components
│   │   ├── pages/         # Páginas/Views
│   │   ├── contexts/      # Context providers
│   │   ├── lib/           # Utilitários (api.ts)
│   │   ├── App.tsx
│   │   └── main.tsx
│   ├── public/
│   └── index.html
├── public/                 # Assets públicos PHP
│   ├── app/               # Admin HTML antigo
│   └── portal/            # Portal HTML antigo (Ethereal)
├── routes/                 # Rotas backend
│   ├── web.php
│   ├── admin.php
│   └── portal.php
├── bootstrap/
├── database/
├── storage/
├── vite.config.ts         # Config Vite com proxy
├── package.json
├── pnpm-lock.yaml
├── .env                   # Vars de ambiente (backend + frontend)
└── router.php             # Router PHP dev server
```

## Variáveis de Ambiente

### Backend (.env)
```dotenv
APP_NAME=SOCIMOB
DB_CONNECTION=mysql
DB_DATABASE=exclusiva
EXCLUSIVA_OPENAI_API_KEY=
EXCLUSIVA_TWILIO_ACCOUNT_SID=
```

### Frontend (prefixo VITE_ no .env)
```dotenv
VITE_API_BASE_URL=http://localhost:8000/api
VITE_BACKEND_URL=http://localhost:8000
VITE_APP_NAME=SOCIMOB
```

## Integração API

O frontend React consome as APIs Lumen via axios configurado em `client/src/lib/api.ts`:

```typescript
import api from '@/lib/api';

// Exemplo de uso
const response = await api.get('/leads');
const leads = response.data;
```

**Autenticação:**
- Token armazenado em `localStorage.getItem('token')`
- Interceptor adiciona header `Authorization: Bearer {token}`
- Redirecionamento automático para `/login` em 401

## Páginas Disponíveis

1. **Dashboard** (`/`) - Métricas e visão geral
2. **Leads** (`/leads`) - Kanban board de leads
3. **Imóveis** (`/properties`) - Gestão de imóveis
4. **Chat** (`/chat`) - Conversas WhatsApp
5. **Notificações** (`/notifications`)
6. **Portal** (`/portal`) - Portal do cliente
7. **Configurações** (`/settings`)
8. **Login** (`/login`)

## Acesso

- **Frontend React**: http://localhost:3000
- **Backend API**: http://localhost:8000/api
- **Admin Antigo**: http://localhost:8000/app/
- **Portal Antigo**: http://localhost:8000/portal/

## Próximos Passos

1. ✅ Frontend React integrado
2. ✅ Proxy Vite configurado
3. ✅ API client (axios) criado
4. ⏳ Conectar páginas React às APIs existentes
5. ⏳ Implementar autenticação no frontend
6. ⏳ Migrar funcionalidades do admin HTML para React
7. ⏳ Desativar frontends antigos quando pronto
