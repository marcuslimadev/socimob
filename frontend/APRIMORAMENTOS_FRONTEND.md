# 🚀 Frontend Enhancements - SOCIMOB SaaS

## 📋 Resumo das Melhorias

Aprimoramentos do frontend baseados nos testes implementados no backend, com foco em:
- ✅ **Autenticação robusta** com Bearer tokens
- ✅ **RBAC (Role-Based Access Control)** com 4 níveis
- ✅ **Isolamento de dados por Tenant**
- ✅ **Componentes de importação de imóveis**
- ✅ **Gerenciamento de empresas (tenants)**
- ✅ **Gerenciamento de usuários com acesso baseado em role**

---

## 🏗️ Arquitetura Implementada

### 1️⃣ Composables (Lógica de Negócio)

#### `useAuth.ts` ⭐
Gerencia autenticação, tokens Bearer e permissões do usuário.

```typescript
// Recursos
- login(email, senha) - Autenticação
- logout() - Desconexão
- checkAuth() - Verificar token válido
- hasPermission(roles) - Validar role
- canAccessTenant(tenantId) - Acesso multi-tenant
- Getters: isSuperAdmin, isAdmin, isUser, isClient, isActive
```

#### `useTenant.ts` 🏢
Gerencia tenants (empresas) do sistema.

```typescript
// Recursos
- fetchTenants() - Listar todos (super admin)
- createTenant(data) - Criar novo tenant
- updateTenant(id, data) - Editar tenant
- deleteTenant(id) - Deletar tenant
- toggleTenantStatus(id, active) - Ativar/desativar
```

#### `useUsers.ts` 👥
Gerencia usuários com RBAC.

```typescript
// Recursos
- fetchUsers(filters) - Listar usuários
- createUser(data) - Criar usuário
- updateUser(id, data) - Editar usuário
- changeUserRole(id, role) - Alterar role
- Dados: ROLES, ROLE_LABELS, ROLE_PERMISSIONS
```

#### `useProperties.ts` 🏠
Gerencia propriedades/imóveis do tenant.

```typescript
// Recursos
- fetchProperties(filters) - Listar propriedades
- createProperty(data) - Criar propriedade
- updateProperty(id, data) - Editar propriedade
- deleteProperty(id) - Deletar propriedade
- Computados: filteredProperties, statistics
```

#### `usePropertyImport.ts` 📥
Gerencia importação de imóveis via CSV.

```typescript
// Recursos
- importProperties(file) - Upload e importação
- validateFile(file) - Validação de arquivo
- parseCSV(content) - Parse do CSV
- downloadTemplate() - Download template
- Progresso: importProgress, importedCount, failedCount
```

---

### 2️⃣ Componentes

#### `RoleGuard.vue` 🔐
Proteção de componentes baseada em role.

```vue
<role-guard roles="admin">
  <YourComponent />
</role-guard>

<!-- Múltiplos roles -->
<role-guard :roles="['super_admin', 'admin']">
  <Content />
</role-guard>
```

#### `PropertyImportEnhanced.vue` 📥
Interface melhorada para importação de imóveis.

Features:
- ✅ Drag and drop de arquivos
- ✅ Validação de CSV em tempo real
- ✅ Download de template
- ✅ Barra de progresso
- ✅ Visualização de erros
- ✅ Isolamento por tenant

#### `TenantsEnhanced.vue` 🏢
Interface para gerenciamento de tenants (super admin).

Features:
- ✅ Listar, criar, editar e deletar tenants
- ✅ Ativar/desativar tenants
- ✅ Busca e filtros
- ✅ Modal para formulário
- ✅ Status visual (ativo/inativo)

---

## 🔐 Controle de Acesso (RBAC)

### Roles Disponíveis

| Role | Permissões | Acesso |
|------|-----------|--------|
| **super_admin** | Gerenciar tudo (tenants, users, subscriptions) | Todas as rotas `/super-admin/*` |
| **admin** | Gerenciar users, properties, leads do tenant | Dashboard, Importação, Imoveis |
| **user** | Gerenciar leads, ver propriedades | Dashboard, Leads, Imoveis |
| **client** | Ver propriedades e seus dados | Dashboard, Imoveis (read-only) |

### Permissões por Role

```typescript
ROLE_PERMISSIONS = {
  super_admin: ['manage_tenants', 'manage_users', 'manage_subscriptions', 'view_all_data'],
  admin: ['manage_users_in_tenant', 'manage_properties', 'manage_leads', 'view_tenant_data'],
  user: ['manage_own_leads', 'view_properties', 'manage_own_data'],
  client: ['view_properties', 'view_own_data']
}
```

---

## 🛣️ Rotas Implementadas

```
/login                          - Login (sem autenticação)
/                              - Dashboard (todas as roles)
/leads                         - Leads (autenticado)
/imoveis                       - Imóveis (autenticado)
/conversas                     - Conversas (autenticado)
/importacao                    - Importação (admin)
/importacao-enhanced           - Importação Melhorada (admin)
/super-admin                   - Painel Super Admin (super_admin)
/super-admin/tenants           - Gerenciar Tenants (super_admin)
/super-admin/tenants-enhanced  - Gerenciar Tenants Melhorado (super_admin)
/super-admin/users             - Gerenciar Users (super_admin)
/super-admin/subscriptions     - Gerenciar Subscriptions (super_admin)
```

---

## 🔗 Isolamento Multi-Tenant

Todos os composables respeitam o isolamento por `tenant_id`:

```typescript
// Automaticamente adiciona tenant_id ao contexto do usuário
const tenantId = localStorage.getItem('tenant_id')

// Requisições já filtradas por tenant
GET /api/properties       // Retorna só do tenant do user
GET /api/users            // Retorna só do tenant do user
POST /api/properties      // Cria com tenant_id automático
```

---

## 🔄 Fluxo de Autenticação

```
1. Login → POST /api/auth/login
2. Recebe token Bearer base64("{userId}|{timestamp}|{secret}")
3. Salva token e user no localStorage
4. Todas as requisições incluem header: Authorization: Bearer {token}
5. Router valida roles e permissions antes de renderizar
6. Logout remove token e redireciona para /login
```

---

## 📝 Exemplo de Uso

### Usar composable de propriedades

```vue
<script setup lang="ts">
import { useProperties } from '@/composables/useProperties'

const { properties, loading, fetchProperties, statistics } = useProperties()

onMounted(async () => {
  await fetchProperties()
})
</script>

<template>
  <div v-if="loading">Loading...</div>
  <div v-else>
    <p>Total: {{ statistics.total }}</p>
    <ul>
      <li v-for="prop in properties" :key="prop.id">
        {{ prop.titulo }} - R$ {{ prop.valor }}
      </li>
    </ul>
  </div>
</template>
```

### Proteger rota com role

```vue
<role-guard roles="admin">
  <ImportacaoImoveis />
</role-guard>

<!-- Ou múltiplos roles -->
<role-guard :roles="['super_admin', 'admin']">
  <SpecialContent />
</role-guard>
```

---

## 🚀 Melhorias Técnicas

### API Client (`services/api.js`)
- ✅ Interceptor para adicionar Bearer token automaticamente
- ✅ Tratamento de erros 401 (logout automático)
- ✅ Suporte para upload de arquivos

### Router Guards (`router/index.ts`)
- ✅ Validação de autenticação
- ✅ Verificação de roles
- ✅ Redirecionamento automático
- ✅ Proteção de rotas por role

### Estado Global (Pinia Stores)
- ✅ `auth.js` - Estado de autenticação
- ✅ `dashboard.js` - Dashboard data
- ✅ `leads.js` - Leads data
- ✅ Sincronização com localStorage

---

## 📊 Checklist de Funcionalidades

- ✅ Autenticação com Bearer Token
- ✅ RBAC com 4 níveis (super_admin, admin, user, client)
- ✅ RoleGuard para proteção de componentes
- ✅ Isolamento de dados por Tenant
- ✅ Importação de imóveis via CSV
- ✅ Gerenciamento de Tenants
- ✅ Gerenciamento de Usuários
- ✅ Gerenciamento de Propriedades
- ✅ Validação de permissões em rotas
- ✅ Componentes melhorados e responsivos

---

## 🔄 Sincronização com Backend

Todos os composables utilizam a API definida em `services/api.js`:

```javascript
const API_URL = import.meta.env.VITE_API_URL || 'https://exclusiva-backend.onrender.com'

const api = axios.create({
  baseURL: API_URL,
  headers: { 'Content-Type': 'application/json' }
})
```

Configure em `.env`:
```
VITE_API_URL=http://localhost:8000
```

---

## 📦 Estrutura de Pastas

```
src/
├── composables/           # Lógica reutilizável
│   ├── useAuth.ts        # Autenticação
│   ├── useTenant.ts      # Tenants
│   ├── useUsers.ts       # Usuários
│   ├── useProperties.ts  # Propriedades
│   └── usePropertyImport.ts  # Importação
├── components/
│   ├── RoleGuard.vue     # Proteção por role
│   ├── Navbar.vue
│   └── ...
├── views/
│   ├── PropertyImportEnhanced.vue  # Import melhorado
│   ├── TenantsEnhanced.vue         # Tenants melhorado
│   └── ...
├── stores/               # Pinia stores
├── router/               # Vue Router
├── services/             # API client
└── assets/
```

---

## 🎯 Próximos Passos

1. **Teste E2E** - Criar testes end-to-end com Cypress/Playwright
2. **SSO** - Integrar autenticação social (Google, Microsoft)
3. **Auditoria** - Adicionar logging de ações por usuário
4. **Performance** - Implementar cache de dados
5. **Notificações** - Sistema de notificações em tempo real (WebSocket)

---

**Última atualização:** 18 de Dezembro de 2025  
**Compatível com:** Backend SOCIMOB v1.0
