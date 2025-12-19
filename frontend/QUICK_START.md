# 🚀 Quick Start Guide - Frontend SOCIMOB

## 1️⃣ Instalação

```bash
# Entrar no diretório do frontend
cd c:/Projetos/saas/frontend

# Instalar dependências
npm install

# (Opcional) Se houver problemas com cache
rm -rf node_modules package-lock.json
npm install
```

---

## 2️⃣ Configuração

### Variáveis de Ambiente (`.env`)

```env
# API Backend URL
VITE_API_URL=http://localhost:8000

# Ou para produção
VITE_API_URL=https://sua-api.com
```

### Verificar Porta do Vite

Padrão: `http://localhost:5173`

---

## 3️⃣ Iniciar Servidor

### Modo Desenvolvimento

```bash
npm run dev
```

Saída esperada:
```
  VITE v7.2.2  ready in 1234 ms

  ➜  Local:   http://localhost:5173/
  ➜  press h to show help
```

### Modo Build

```bash
npm run build
```

Gera pasta `dist/` com arquivos otimizados.

### Modo Preview

```bash
npm run preview
```

Simula produção localmente.

---

## 4️⃣ Testar Funcionalidades

### Login de Teste

1. Acesse: `http://localhost:5173/login`
2. Use credenciais criadas nos testes backend:

```
Email: super@test.com
Senha: password

OU

Email: admin@empresa.com
Senha: password
```

### Validar Redirecionamento

- **Super Admin** → `/super-admin`
- **Admin** → `/dashboard`
- **User** → `/dashboard`

---

## 5️⃣ Usar Composables

### Exemplo: Listar Propriedades

```vue
<script setup>
import { onMounted } from 'vue'
import { useProperties } from '@/composables/useProperties'

const { properties, fetchProperties, loading } = useProperties()

onMounted(async () => {
  await fetchProperties()
})
</script>

<template>
  <div>
    <p v-if="loading">Carregando...</p>
    <ul v-else>
      <li v-for="prop in properties" :key="prop.id">
        {{ prop.titulo }} - R$ {{ prop.valor }}
      </li>
    </ul>
  </div>
</template>
```

### Exemplo: Proteger Componente

```vue
<template>
  <role-guard roles="admin">
    <ImportacaoImoveis />
  </role-guard>
</template>
```

---

## 6️⃣ Adicionar Nova Rota

### 1. Criar Componente Vue

`src/views/MeuComponente.vue`:
```vue
<template>
  <div>Meu Componente</div>
</template>

<script setup lang="ts">
// Sua lógica aqui
</script>
```

### 2. Adicionar Rota

`src/router/index.ts`:
```typescript
{
  path: '/meu-componente',
  name: 'MeuComponente',
  component: () => import('@/views/MeuComponente.vue'),
  meta: { 
    requiresAuth: true,
    roles: ['admin', 'super_admin']  // Opcional
  }
}
```

### 3. Adicionar Link

`src/components/Navbar.vue`:
```vue
<router-link to="/meu-componente">
  Meu Componente
</router-link>
```

---

## 7️⃣ Adicionar Nova Permissão

### 1. Atualizar Constantes

`src/composables/useUsers.ts`:
```typescript
export const ROLES = {
  SUPER_ADMIN: 'super_admin',
  ADMIN: 'admin',
  USER: 'user',
  CLIENT: 'client',
  NOVO_ROLE: 'novo_role'  // Novo
}

export const ROLE_PERMISSIONS = {
  novo_role: ['view_data', 'edit_data']
}
```

### 2. Usar em RoleGuard

```vue
<role-guard :roles="['novo_role']">
  <Conteúdo />
</role-guard>
```

---

## 8️⃣ API Endpoints Disponíveis

### Autenticação
```
POST   /api/auth/login
GET    /api/auth/me
POST   /api/auth/logout
```

### Tenants (Super Admin)
```
GET    /api/super-admin/tenants
POST   /api/super-admin/tenants
PUT    /api/super-admin/tenants/{id}
DELETE /api/super-admin/tenants/{id}
```

### Usuários
```
GET    /api/users
POST   /api/users
PUT    /api/users/{id}
DELETE /api/users/{id}
```

### Propriedades
```
GET    /api/properties
POST   /api/properties
PUT    /api/properties/{id}
DELETE /api/properties/{id}
POST   /api/properties/import
```

---

## 9️⃣ Depuração

### Ver State de um Composable

```javascript
// No console do navegador
import { useAuth } from '@/composables/useAuth'
const { user, token, isAdmin } = useAuth()

console.log('User:', user.value)
console.log('Token:', token.value)
console.log('Is Admin:', isAdmin.value)
```

### Ver LocalStorage

```javascript
// No console
localStorage.getItem('token')
localStorage.getItem('user')
localStorage.getItem('tenant_id')
```

### Limpar Dados

```javascript
// Logout forçado
localStorage.clear()
location.reload()
```

---

## 🔟 Troubleshooting

### Erro: "401 Unauthorized"

**Problema:** Token expirou ou é inválido

**Solução:**
```javascript
// Limpar e fazer logout
localStorage.clear()
location.href = '/login'
```

### Erro: "403 Forbidden"

**Problema:** Usuário não tem permissão para essa ação

**Solução:** Verificar role do usuário
```javascript
console.log(user.value.role)  // Qual é o role atual?
```

### Erro: "CORS"

**Problema:** Backend não está acessível

**Solução:**
1. Verificar se backend está rodando
2. Verificar URL do backend em `.env`
3. Backend deve permitir CORS

### Erro: "404 Not Found"

**Problema:** Rota não existe no backend

**Solução:**
1. Verificar URL da API
2. Verificar se endpoint existe no backend
3. Verificar método HTTP (GET, POST, etc)

### Propriedades não aparecem

**Problema:** `tenant_id` não configurado corretamente

**Solução:**
```javascript
// Verificar tenant_id
console.log(localStorage.getItem('tenant_id'))

// Se vazio, fazer logout e login novamente
```

---

## 🎯 Exemplos de Uso Rápido

### Criar Nova Propriedade

```typescript
import { useProperties } from '@/composables/useProperties'

const { createProperty } = useProperties()

await createProperty({
  titulo: 'Casa 3 quartos',
  endereco: 'Rua A, 123',
  cidade: 'São Paulo',
  tipo: 'casa',
  valor: 500000
})
```

### Mudar Role do Usuário

```typescript
import { useUsers } from '@/composables/useUsers'

const { changeUserRole } = useUsers()

await changeUserRole(userId, 'admin')
```

### Filtrar Propriedades

```typescript
import { useProperties } from '@/composables/useProperties'

const { filters, filteredProperties } = useProperties()

filters.value.cidade = 'São Paulo'
filters.value.minValue = 300000
filters.value.maxValue = 600000

console.log(filteredProperties.value)
```

### Verificar Permissão

```typescript
import { useAuth } from '@/composables/useAuth'

const { hasPermission } = useAuth()

if (hasPermission(['admin', 'super_admin'])) {
  console.log('Pode fazer algo')
} else {
  console.log('Acesso negado')
}
```

---

## 📚 Documentação Completa

- `APRIMORAMENTOS_FRONTEND.md` - Features e composables
- `ARCHITECTURE_DIAGRAM.md` - Diagramas de arquitetura
- `IMPLEMENTATION_CHECKLIST.md` - Checklist completo
- `EXEMPLOS_COMPOSABLES.ts` - 10 exemplos práticos

---

## 🎓 Próximos Passos

1. **Entender a Arquitetura**
   - Ler `ARCHITECTURE_DIAGRAM.md`
   - Entender fluxo de autenticação

2. **Testar Funcionalidades**
   - Fazer login como super_admin
   - Criar tenant
   - Criar usuário
   - Importar propriedades

3. **Desenvolver Features**
   - Copiar estrutura de um composable existente
   - Criar novo composable para sua feature
   - Adicionar rotas e componentes

4. **Escrever Testes**
   - Criar testes E2E
   - Testar fluxos críticos

---

## 📞 Suporte

### Encontrar Problema?

1. Verificar console do navegador (F12)
2. Verificar Network tab
3. Verificar localStorage
4. Consultar exemplos em `EXEMPLOS_COMPOSABLES.ts`

### Mais Informações?

- Ler documentação em `.md` files
- Consultar testes do backend: `/backend/tests/Feature/`
- Checar tipos TypeScript em composables

---

**Status:** ✅ Pronto para desenvolvimento  
**Compatibilidade:** Node 16+, npm 8+  
**Navegadores:** Chrome, Firefox, Safari, Edge (modernos)
