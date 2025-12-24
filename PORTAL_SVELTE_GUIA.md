# Portal Svelte - Início Rápido

## 📦 Setup Inicial (Primeira vez)

```powershell
# 1. Entrar na pasta do projeto Svelte
cd portal-svelte

# 2. Instalar dependências
npm install
```

## 🚀 Desenvolvimento

### Opção 1: Comando único (Recomendado)
```powershell
# Na raiz do projeto
.\start-svelte-dev.ps1
```

Isso vai:
1. Iniciar o backend Lumen (porta 8000)
2. Iniciar o Vite dev server (porta 5173)
3. Abrir o navegador automaticamente

### Opção 2: Manual
```powershell
# Terminal 1: Backend
cd backend
php -S 127.0.0.1:8000 -t public

# Terminal 2: Frontend Svelte
cd portal-svelte
npm run dev
```

## 🌐 Acessar

### Durante Desenvolvimento
- **Portal Svelte (dev)**: http://localhost:5173
- **Backend API**: http://127.0.0.1:8000/api
- **Portal jQuery (antigo)**: http://127.0.0.1:8000/portal/

### Após Build
- **Portal Svelte (prod)**: http://127.0.0.1:8000/portal/svelte/

## 🔨 Build e Deploy

### Build para produção
```powershell
cd portal-svelte
npm run build
```

Arquivos gerados em: `public/portal/svelte/`

### Deploy automático
```powershell
cd portal-svelte
npm run deploy
```

Faz build e copia para `public/portal/svelte/` automaticamente

### Testar build local
```powershell
cd portal-svelte
npm run preview
```

## 📝 Comparação: jQuery vs Svelte

### Portal Atual (jQuery)
- 📁 `public/portal/index.html` - ~1760 linhas
- ❌ HTML monolítico
- ❌ Estado manual com variáveis globais
- ❌ Sem componentização
- ✅ Zero build (CDN)
- ✅ Deploy = FTP

### Portal Novo (Svelte)
- 📁 `portal-svelte/src/` - Componentizado
- ✅ Componentes reutilizáveis
- ✅ Estado reativo automático
- ✅ HMR instantâneo
- ✅ TypeScript ready
- ✅ Bundle ~5kb
- ⚠️ Precisa de build

## 🎯 Features Equivalentes

| Feature | jQuery | Svelte | Status |
|---------|--------|--------|--------|
| Autenticação | ✅ | ✅ | Pronto |
| Lista de imóveis | ✅ | ✅ | Pronto |
| Filtros (search, tipo, finalidade) | ✅ | ✅ | Pronto |
| Cards responsivos | ✅ | ✅ | Pronto |
| Modal de detalhes | ✅ | ✅ | Pronto |
| Carousel de fotos | ✅ | ✅ | Pronto |
| Botão de interesse | ✅ | ✅ | Pronto |
| Chat | ✅ | 🔄 | Próximo |
| Favoritos | ✅ | 🔄 | Próximo |
| Perfil | ✅ | 🔄 | Próximo |

## 🔧 Estrutura de Componentes

```
App.svelte (Raiz)
├── Navbar.svelte (Navegação)
├── PropertyFilters.svelte (Busca e filtros)
├── PropertyGrid.svelte (Container de cards)
│   └── PropertyCard.svelte × N (Card individual)
└── PropertyModal.svelte (Detalhes + Carousel)
```

## 📊 Estado (Stores)

### auth.js
- `user`: Dados do usuário
- `token`: Token de autenticação
- `isAuthenticated`: Boolean

### properties.js
- `properties`: Array de imóveis
- `loading`: Estado de carregamento
- `filters`: Filtros ativos
- `filteredProperties`: Computed (auto-filtra)

## 🎨 Vantagens do Svelte

1. **Reatividade Mágica**
   ```svelte
   <script>
     let search = '';
     $: filteredItems = items.filter(i => i.name.includes(search));
   </script>
   ```

2. **Componentes Pequenos**
   ```svelte
   <!-- PropertyCard.svelte -->
   <script>
     export let property;
   </script>
   
   <div>{property.title}</div>
   ```

3. **Transições Built-in**
   ```svelte
   <div transition:fly={{ y: 50 }}>
     Modal
   </div>
   ```

4. **Stores Simples**
   ```js
   import { writable } from 'svelte/store';
   export const count = writable(0);
   ```

## 🚦 Próximos Passos

1. ✅ Rodar `npm install`
2. ✅ Testar em dev: `npm run dev`
3. 🔄 Comparar com portal jQuery
4. 🔄 Adicionar features faltantes (chat, favoritos)
5. 🔄 Migrar para TypeScript (opcional)
6. 🔄 Adicionar testes (Vitest)

## 📚 Recursos

- [Documentação Svelte](https://svelte.dev/docs)
- [Svelte Tutorial](https://svelte.dev/tutorial)
- [Vite Guide](https://vitejs.dev/guide/)
- [Axios Docs](https://axios-http.com/docs/)

---

**Dica**: Use o VS Code com extensão "Svelte for VS Code" para melhor DX!
