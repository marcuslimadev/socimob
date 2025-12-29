# Documentação do Sistema de Sidebar - SOCIMOB

## 📋 Visão Geral

O sistema de sidebar é um componente reutilizável que fornece:
- Menu lateral fixo e responsivo
- Controle de acesso baseado em roles (super_admin, admin, user)
- Colapso/expansão do menu
- Suporte mobile com overlay
- Informações do usuário
- Logout integrado

## 🚀 Como Usar

### 1. Incluir os arquivos necessários

Adicione no `<head>` da sua página:

```html
<link rel="stylesheet" href="sidebar.css">
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="sidebar.js"></script>
```

### 2. Estrutura HTML

A sidebar será injetada **automaticamente** pelo JavaScript. Você só precisa incluir seu conteúdo normalmente:

```html
<body>
    <!-- A sidebar será injetada aqui automaticamente -->
    
    <div class="container py-4">
        <!-- Seu conteúdo aqui -->
    </div>
</body>
```

### 3. Verificação de Autenticação

Certifique-se de ter a verificação de autenticação:

```javascript
$(document).ready(function() {
    checkAuth();
    // ... resto do código
});

function checkAuth() {
    const token = localStorage.getItem('token');
    const user = localStorage.getItem('user');
    
    if (!token || !user) {
        window.location.href = 'login.html';
        return;
    }
}
```

## 🎨 Recursos

### Controle de Acesso por Role

Os itens do menu são exibidos automaticamente baseados no role do usuário:

- **super_admin**: Acesso total (Dashboard, Leads, Visitas, Imóveis, Conversas, Configurações, Super Admin, Tenants, Usuários)
- **admin**: Acesso administrativo (Dashboard, Leads, Visitas, Imóveis, Conversas, Configurações)
- **user**: Acesso limitado (Dashboard, Leads, Visitas, Conversas)

### Funcionalidades

1. **Colapso/Expansão**: Botão no topo da sidebar
2. **Mobile**: Menu hambúrguer automático em telas pequenas
3. **Página Ativa**: Destaque automático do item correspondente à página atual
4. **Tooltips**: Quando colapsada, mostra tooltips ao passar o mouse
5. **Logout**: Botão integrado no rodapé da sidebar

## 📱 Responsividade

### Desktop (> 768px)
- Sidebar fixa à esquerda
- Largura padrão: 260px
- Colapsada: 70px

### Mobile (≤ 768px)
- Sidebar oculta por padrão
- Botão hambúrguer flutuante
- Overlay escuro ao abrir
- Fecha ao clicar fora

## 🎯 API Pública

### Atualizar Badge

```javascript
// Atualizar badge de notificações
window.sidebar.updateBadge('conversas', 5); // Mostra badge com número 5
window.sidebar.updateBadge('leads', 0); // Remove o badge
```

## 🔧 Personalização

### Cores (CSS Variables)

```css
:root {
    --sidebar-width: 260px;
    --sidebar-collapsed-width: 70px;
    --sidebar-bg: #1e293b;
    --sidebar-hover: #334155;
    --sidebar-active: #3b82f6;
    --sidebar-text: #e2e8f0;
    --sidebar-text-muted: #94a3b8;
    --sidebar-border: #334155;
}
```

### Adicionar Novos Itens de Menu

Edite o arquivo `sidebar.js`, método `getMenuItems()`:

```javascript
{
    section: 'Nova Seção',
    items: [
        {
            id: 'novo-item',
            label: 'Novo Item',
            icon: 'bi-star-fill',
            href: 'novo-item.html',
            roles: ['super_admin', 'admin'] // Quem pode ver
        }
    ]
}
```

## 📄 Exemplo Completo

Ver arquivo: `_template-sidebar.html`

## 🔍 Estrutura de Dados do Usuário

O sistema espera que `localStorage.user` contenha:

```json
{
    "name": "Nome do Usuário",
    "role": "admin",
    "email": "usuario@exemplo.com"
}
```

## ⚠️ Observações Importantes

1. A sidebar **não** será renderizada se não houver usuário autenticado
2. O estado de colapso é salvo em `localStorage.sidebar-collapsed`
3. O logout limpa todos os dados do localStorage relacionados à sessão
4. Em mobile, o overlay fecha automaticamente ao clicar fora da sidebar

## 🎨 Compatibilidade

- ✅ Bootstrap Icons
- ✅ jQuery 3.7+
- ✅ Navegadores modernos (Chrome, Firefox, Safari, Edge)
- ✅ Mobile responsivo

## 📝 Migração de Páginas Antigas

### Antes (sem sidebar):

```html
<body>
    <nav class="navbar">...</nav>
    <main class="container">
        Conteúdo
    </main>
</body>
```

### Depois (com sidebar):

```html
<head>
    <link rel="stylesheet" href="sidebar.css">
    <script src="sidebar.js"></script>
</head>
<body>
    <!-- Remover navbar antiga -->
    <div class="container py-4">
        Conteúdo
    </div>
</body>
```

A sidebar substituirá completamente a navbar antiga!
