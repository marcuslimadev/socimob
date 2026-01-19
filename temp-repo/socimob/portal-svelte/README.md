# Portal Svelte - SOCIMOB

Portal de imóveis moderno construído com Svelte + Vite

## 🚀 Desenvolvimento

### Instalar dependências
```bash
npm install
```

### Rodar em desenvolvimento (com HMR)
```bash
npm run dev
```

Acesse: http://localhost:5173

A API do backend roda em http://127.0.0.1:8000 (proxy configurado)

### Build para produção
```bash
npm run build
```

Gera arquivos otimizados em `../public/portal/svelte/`

### Deploy rápido
```bash
npm run deploy
```

Faz build e copia automaticamente para `public/portal/svelte/`

## 📁 Estrutura

```
portal-svelte/
├── src/
│   ├── components/
│   │   ├── Navbar.svelte          # Barra de navegação
│   │   ├── PropertyFilters.svelte # Filtros de busca
│   │   ├── PropertyGrid.svelte    # Grid de imóveis
│   │   ├── PropertyCard.svelte    # Card individual
│   │   └── PropertyModal.svelte   # Modal de detalhes
│   ├── stores/
│   │   ├── auth.js                # Estado de autenticação
│   │   └── properties.js          # Estado de imóveis + filtros
│   ├── App.svelte                 # Componente principal
│   ├── main.js                    # Entry point
│   └── app.css                    # Estilos globais
├── index.html
├── vite.config.js
└── package.json
```

## 🎯 Features

### ✅ Implementado
- [x] Autenticação (usa token do localStorage)
- [x] Listagem de imóveis com filtros reativos
- [x] Busca em tempo real (search, tipo, finalidade)
- [x] Cards responsivos com hover effects
- [x] Modal de detalhes com carousel de fotos
- [x] Estado global com Svelte stores
- [x] Integração com API `/api/portal/imoveis`
- [x] Design moderno (Glow theme)
- [x] Build otimizado para produção

### 🔄 Próximas Features
- [ ] Sistema de favoritos
- [ ] Chat com a imobiliária
- [ ] Agendamento de visitas
- [ ] Comparador de imóveis
- [ ] Mapa interativo (Leaflet)
- [ ] Galeria de fotos em tela cheia
- [ ] Compartilhar imóvel
- [ ] Perfil do usuário

## 🌐 URLs

### Desenvolvimento
- **Frontend**: http://localhost:5173
- **Backend API**: http://127.0.0.1:8000/api

### Produção
- **Portal Svelte**: http://127.0.0.1:8000/portal/svelte/
- **Portal jQuery**: http://127.0.0.1:8000/portal/
- **Admin**: http://127.0.0.1:8000/app/

## 📦 Vantagens do Svelte

1. **Reatividade automática** - `$:` magic
2. **Componentes pequenos** - fácil manutenção
3. **Bundle minúsculo** - ~5kb runtime
4. **Transições built-in** - animações suaves
5. **TypeScript pronto** - basta renomear .js → .ts
6. **HMR instantâneo** - Vite é extremamente rápido

## 🔧 Configuração

### Proxy para API
O Vite está configurado para fazer proxy de `/api` para o backend Lumen:

```js
// vite.config.js
server: {
  proxy: {
    '/api': 'http://127.0.0.1:8000'
  }
}
```

### Base Path
Build configurado para servir em `/portal/svelte/`:

```js
// vite.config.js
base: '/portal/svelte/'
```

## 🎨 Estilização

Usa CSS custom properties do Glow theme:
- `--glow-blue`, `--glow-purple`, etc
- Design consistente com o resto do sistema
- Tailwind inline para utilidades

## 📝 Scripts Disponíveis

- `npm run dev` - Desenvolvimento com HMR
- `npm run build` - Build para produção
- `npm run preview` - Preview do build
- `npm run deploy` - Build + copy para public/

## 🚀 Deploy

### Automático (Recomendado)
```bash
npm run deploy
```

### Manual
```bash
# 1. Build
npm run build

# 2. Arquivos estarão em ../public/portal/svelte/
# 3. Fazer commit e push
git add ../public/portal/svelte
git commit -m "Deploy portal Svelte"
git push
```

---

**Criado em**: 24/12/2025  
**Stack**: Svelte 4 + Vite 5 + Axios
