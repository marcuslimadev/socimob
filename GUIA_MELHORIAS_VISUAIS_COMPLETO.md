# 🎨 Guia Técnico Completo - Melhorias Visuais SOCIMOB/Exclusiva

## 📋 Índice
1. [Análise da Estrutura Atual](#análise-da-estrutura-atual)
2. [Design System Proposto](#design-system-proposto)
3. [Componentes por Seção](#componentes-por-seção)
4. [Melhorias de UX](#melhorias-de-ux)
5. [Responsividade](#responsividade)
6. [Acessibilidade](#acessibilidade)
7. [Performance Visual](#performance-visual)
8. [Roadmap de Implementação](#roadmap-de-implementação)

---

## 📊 Análise da Estrutura Atual

### Stack Tecnológico
- **Frontend**: HTML puro + jQuery 3.7.1
- **CSS**: TailwindCSS via CDN (sem build)
- **Estilo Custom**: `public/css/glow.css` (efeitos neon)
- **Loading States**: `public/css/loading.css` + `public/js/loading.js`
- **Servidor**: Single-server (porta 8000)

### Páginas Existentes
```
public/app/
├── login.html          # Login unificado (todos os roles)
├── dashboard.html      # Dashboard com cards
├── leads.html          # Gestão de leads + botão IA
├── imoveis.html        # Gestão de imóveis
├── conversas.html      # Chat estilo WhatsApp
├── configuracoes.html  # Abas (perfil, integrações, IA)
└── clientes.html       # Lista de clientes

public/portal/
├── index.html          # Landing page portal cliente
└── imoveis.html        # Catálogo de imóveis público
```

### Problemas Visuais Identificados

#### 🔴 CRÍTICOS
1. **Inconsistência de cores**: Múltiplas paletas sem padrão
2. **Tipografia desorganizada**: Tamanhos e pesos aleatórios
3. **Espaçamentos irregulares**: Falta de grid system consistente
4. **Componentes não reutilizáveis**: Código duplicado em HTML
5. **Feedback visual ausente**: Poucos estados hover/focus/active
6. **Hierarquia visual fraca**: Headers não se destacam do conteúdo
7. **Efeito glow excessivo**: Pode cansar visualmente

#### 🟡 MÉDIOS
1. **Cards sem elevação**: Flat demais, falta profundidade
2. **Formulários genéricos**: Campos sem identidade visual
3. **Tabelas pesadas**: Difíceis de escanear
4. **Modais sem animação**: Aparecem bruscamente
5. **Ícones inconsistentes**: Mistura de FontAwesome, emoji e texto

#### 🟢 BAIXOS
1. **Footer ausente**: Sem informações de copyright/versão
2. **Loading states básicos**: Spinner simples sem brand
3. **Empty states ausentes**: Sem ilustrações para listas vazias

---

## 🎨 Design System Proposto

### 1. Paleta de Cores (Sistema Multi-Tenant)

#### **Cores Primárias** (Configuráveis por tenant)
```css
:root {
  /* Primária (azul escuro elegante) */
  --primary-50: #eff6ff;
  --primary-100: #dbeafe;
  --primary-200: #bfdbfe;
  --primary-300: #93c5fd;
  --primary-400: #60a5fa;
  --primary-500: #3b82f6;  /* Base */
  --primary-600: #2563eb;
  --primary-700: #1d4ed8;
  --primary-800: #1e40af;
  --primary-900: #1e3a8a;
  
  /* Secundária (verde sucesso) */
  --secondary-500: #10b981;
  --secondary-600: #059669;
  
  /* Accent (roxo moderno) */
  --accent-500: #8b5cf6;
  --accent-600: #7c3aed;
}
```

#### **Cores Neutras** (Fixas)
```css
:root {
  --gray-50: #f9fafb;
  --gray-100: #f3f4f6;
  --gray-200: #e5e7eb;
  --gray-300: #d1d5db;
  --gray-400: #9ca3af;
  --gray-500: #6b7280;
  --gray-600: #4b5563;
  --gray-700: #374151;
  --gray-800: #1f2937;
  --gray-900: #111827;
  
  /* Fundo escuro (modo atual) */
  --bg-dark: #0f172a;
  --bg-dark-secondary: #1e293b;
  --bg-dark-tertiary: #334155;
}
```

#### **Cores Semânticas**
```css
:root {
  --success: #10b981;
  --success-light: #d1fae5;
  --warning: #f59e0b;
  --warning-light: #fef3c7;
  --error: #ef4444;
  --error-light: #fee2e2;
  --info: #3b82f6;
  --info-light: #dbeafe;
}
```

### 2. Tipografia

#### **Font Stack**
```css
:root {
  --font-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
  --font-mono: 'Fira Code', 'Courier New', monospace;
}

/* Importar do Google Fonts */
@import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;900&display=swap');
```

#### **Escala de Tamanhos**
```css
:root {
  --text-xs: 0.75rem;    /* 12px */
  --text-sm: 0.875rem;   /* 14px */
  --text-base: 1rem;     /* 16px */
  --text-lg: 1.125rem;   /* 18px */
  --text-xl: 1.25rem;    /* 20px */
  --text-2xl: 1.5rem;    /* 24px */
  --text-3xl: 1.875rem;  /* 30px */
  --text-4xl: 2.25rem;   /* 36px */
  --text-5xl: 3rem;      /* 48px */
}
```

#### **Pesos**
```css
:root {
  --font-light: 300;
  --font-normal: 400;
  --font-medium: 500;
  --font-semibold: 600;
  --font-bold: 700;
  --font-black: 900;
}
```

### 3. Espaçamento (Sistema 8px)

```css
:root {
  --space-1: 0.25rem;  /* 4px */
  --space-2: 0.5rem;   /* 8px */
  --space-3: 0.75rem;  /* 12px */
  --space-4: 1rem;     /* 16px */
  --space-5: 1.25rem;  /* 20px */
  --space-6: 1.5rem;   /* 24px */
  --space-8: 2rem;     /* 32px */
  --space-10: 2.5rem;  /* 40px */
  --space-12: 3rem;    /* 48px */
  --space-16: 4rem;    /* 64px */
  --space-20: 5rem;    /* 80px */
}
```

### 4. Sombras e Elevação

```css
:root {
  /* Sombras sutis (fundo escuro) */
  --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.3);
  --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.3), 
               0 2px 4px -1px rgba(0, 0, 0, 0.2);
  --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.4), 
               0 4px 6px -2px rgba(0, 0, 0, 0.3);
  --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.4), 
               0 10px 10px -5px rgba(0, 0, 0, 0.3);
  --shadow-2xl: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
  
  /* Glow (manter para destaques) */
  --glow-primary: 0 0 20px rgba(59, 130, 246, 0.5);
  --glow-success: 0 0 20px rgba(16, 185, 129, 0.5);
  --glow-warning: 0 0 20px rgba(245, 158, 11, 0.5);
  --glow-error: 0 0 20px rgba(239, 68, 68, 0.5);
}
```

### 5. Border Radius

```css
:root {
  --radius-sm: 0.25rem;   /* 4px */
  --radius-md: 0.5rem;    /* 8px */
  --radius-lg: 0.75rem;   /* 12px */
  --radius-xl: 1rem;      /* 16px */
  --radius-2xl: 1.5rem;   /* 24px */
  --radius-full: 9999px;  /* Circular */
}
```

### 6. Transições

```css
:root {
  --transition-fast: 150ms cubic-bezier(0.4, 0, 0.2, 1);
  --transition-base: 300ms cubic-bezier(0.4, 0, 0.2, 1);
  --transition-slow: 500ms cubic-bezier(0.4, 0, 0.2, 1);
  
  --ease-in: cubic-bezier(0.4, 0, 1, 1);
  --ease-out: cubic-bezier(0, 0, 0.2, 1);
  --ease-in-out: cubic-bezier(0.4, 0, 0.2, 1);
}
```

---

## 🧩 Componentes por Seção

### 1. **Sidebar (Navegação Principal)**

#### **Problemas Atuais**
- ❌ Links sem estados visuais claros
- ❌ Ícones muito pequenos
- ❌ Hover genérico
- ❌ Item ativo sem destaque suficiente

#### **Melhorias Propostas**

**HTML Estrutura**:
```html
<nav class="sidebar">
  <!-- Logo Section -->
  <div class="sidebar-header">
    <img id="sidebarLogo" src="/uploads/tenants/1/logo.png" alt="Logo" class="sidebar-logo">
    <h1 class="sidebar-title" id="sidebarTitle">SOCIMOB</h1>
  </div>
  
  <!-- Navigation Links -->
  <ul class="sidebar-nav">
    <li>
      <a href="/app/dashboard.html" class="sidebar-link" data-page="dashboard">
        <svg class="sidebar-icon"><!-- Dashboard icon --></svg>
        <span class="sidebar-text">Dashboard</span>
        <span class="sidebar-badge">3</span> <!-- Opcional: contador -->
      </a>
    </li>
    <li>
      <a href="/app/leads.html" class="sidebar-link active" data-page="leads">
        <svg class="sidebar-icon"><!-- Leads icon --></svg>
        <span class="sidebar-text">Leads</span>
        <span class="sidebar-badge badge-primary">12</span>
      </a>
    </li>
    <!-- ... outros links -->
  </ul>
  
  <!-- Footer Section -->
  <div class="sidebar-footer">
    <div class="user-profile">
      <div class="avatar">
        <img src="/assets/avatar-placeholder.png" alt="User">
      </div>
      <div class="user-info">
        <p class="user-name">João Silva</p>
        <p class="user-role">Admin</p>
      </div>
    </div>
    <button class="btn-logout">
      <svg class="icon"><!-- Logout icon --></svg>
    </button>
  </div>
</nav>
```

**CSS Melhorado**:
```css
/* public/css/sidebar.css */
.sidebar {
  width: 280px;
  height: 100vh;
  background: linear-gradient(180deg, var(--bg-dark) 0%, var(--bg-dark-secondary) 100%);
  border-right: 1px solid rgba(255, 255, 255, 0.1);
  display: flex;
  flex-direction: column;
  position: fixed;
  left: 0;
  top: 0;
  z-index: 1000;
  transition: transform var(--transition-base);
}

.sidebar-header {
  padding: var(--space-6);
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  display: flex;
  align-items: center;
  gap: var(--space-3);
}

.sidebar-logo {
  width: 48px;
  height: 48px;
  object-fit: contain;
  border-radius: var(--radius-lg);
  background: rgba(255, 255, 255, 0.05);
  padding: var(--space-2);
}

.sidebar-title {
  font-size: var(--text-xl);
  font-weight: var(--font-bold);
  color: white;
  letter-spacing: -0.5px;
}

.sidebar-nav {
  flex: 1;
  padding: var(--space-4);
  overflow-y: auto;
  list-style: none;
}

.sidebar-link {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  padding: var(--space-3) var(--space-4);
  margin-bottom: var(--space-2);
  border-radius: var(--radius-lg);
  color: var(--gray-300);
  text-decoration: none;
  font-weight: var(--font-medium);
  font-size: var(--text-sm);
  transition: all var(--transition-fast);
  position: relative;
  overflow: hidden;
}

.sidebar-link::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  height: 100%;
  width: 3px;
  background: var(--primary-500);
  transform: scaleY(0);
  transition: transform var(--transition-fast);
}

.sidebar-link:hover {
  background: rgba(59, 130, 246, 0.1);
  color: var(--primary-300);
  transform: translateX(4px);
}

.sidebar-link:hover::before {
  transform: scaleY(1);
}

.sidebar-link.active {
  background: rgba(59, 130, 246, 0.15);
  color: var(--primary-400);
  box-shadow: var(--glow-primary);
}

.sidebar-link.active::before {
  transform: scaleY(1);
}

.sidebar-icon {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
  stroke-width: 2;
}

.sidebar-text {
  flex: 1;
}

.sidebar-badge {
  background: var(--gray-700);
  color: var(--gray-300);
  font-size: var(--text-xs);
  font-weight: var(--font-semibold);
  padding: 2px 8px;
  border-radius: var(--radius-full);
  min-width: 20px;
  text-align: center;
}

.sidebar-badge.badge-primary {
  background: var(--primary-500);
  color: white;
}

.sidebar-footer {
  padding: var(--space-4);
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  display: flex;
  align-items: center;
  gap: var(--space-3);
}

.user-profile {
  flex: 1;
  display: flex;
  align-items: center;
  gap: var(--space-3);
}

.avatar {
  width: 40px;
  height: 40px;
  border-radius: var(--radius-full);
  overflow: hidden;
  border: 2px solid var(--primary-500);
}

.avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.user-info {
  flex: 1;
}

.user-name {
  font-size: var(--text-sm);
  font-weight: var(--font-semibold);
  color: white;
  margin: 0;
}

.user-role {
  font-size: var(--text-xs);
  color: var(--gray-400);
  margin: 0;
}

.btn-logout {
  background: rgba(239, 68, 68, 0.1);
  border: none;
  color: var(--error);
  width: 36px;
  height: 36px;
  border-radius: var(--radius-lg);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all var(--transition-fast);
}

.btn-logout:hover {
  background: rgba(239, 68, 68, 0.2);
  box-shadow: var(--glow-error);
}

/* Responsivo */
@media (max-width: 768px) {
  .sidebar {
    transform: translateX(-100%);
  }
  
  .sidebar.open {
    transform: translateX(0);
  }
}
```

---

### 2. **Cards (Dashboard e Listagens)**

#### **Problemas Atuais**
- ❌ Flat demais, sem profundidade
- ❌ Padding inconsistente
- ❌ Cores de fundo todas iguais
- ❌ Sem animações de hover

#### **Melhorias Propostas**

**HTML Card Padrão**:
```html
<!-- Card Estatística -->
<div class="stat-card">
  <div class="stat-card-icon stat-card-icon--primary">
    <svg><!-- Icon --></svg>
  </div>
  <div class="stat-card-content">
    <p class="stat-card-label">Total de Leads</p>
    <h3 class="stat-card-value">1,247</h3>
    <div class="stat-card-footer">
      <span class="stat-card-trend stat-card-trend--up">
        <svg class="icon-sm"><!-- Arrow up --></svg>
        12.5%
      </span>
      <span class="stat-card-period">vs. último mês</span>
    </div>
  </div>
</div>

<!-- Card de Lead/Cliente -->
<div class="entity-card">
  <div class="entity-card-header">
    <div class="entity-avatar">
      <span>JS</span>
    </div>
    <div class="entity-info">
      <h4 class="entity-name">João Silva</h4>
      <p class="entity-meta">joao@email.com • (31) 99999-9999</p>
    </div>
    <div class="entity-status">
      <span class="badge badge-success">Qualificado</span>
    </div>
  </div>
  
  <div class="entity-card-body">
    <div class="entity-details">
      <div class="detail-item">
        <svg class="icon-xs text-gray-400"><!-- Location --></svg>
        <span>Belo Horizonte, MG</span>
      </div>
      <div class="detail-item">
        <svg class="icon-xs text-gray-400"><!-- Budget --></svg>
        <span>R$ 300k - R$ 500k</span>
      </div>
    </div>
  </div>
  
  <div class="entity-card-footer">
    <button class="btn btn-sm btn-ghost">
      <svg class="icon-sm"><!-- Eye --></svg>
      Ver detalhes
    </button>
    <button class="btn btn-sm btn-primary">
      <svg class="icon-sm"><!-- Message --></svg>
      Contatar
    </button>
  </div>
</div>
```

**CSS Cards**:
```css
/* public/css/cards.css */

/* Stat Card (Dashboard) */
.stat-card {
  background: var(--bg-dark-secondary);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: var(--radius-xl);
  padding: var(--space-6);
  display: flex;
  gap: var(--space-4);
  transition: all var(--transition-base);
  position: relative;
  overflow: hidden;
}

.stat-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  height: 3px;
  background: linear-gradient(90deg, var(--primary-500), var(--accent-500));
  transform: scaleX(0);
  transform-origin: left;
  transition: transform var(--transition-base);
}

.stat-card:hover {
  transform: translateY(-4px);
  box-shadow: var(--shadow-xl);
  border-color: rgba(59, 130, 246, 0.3);
}

.stat-card:hover::before {
  transform: scaleX(1);
}

.stat-card-icon {
  width: 64px;
  height: 64px;
  border-radius: var(--radius-lg);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.stat-card-icon--primary {
  background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(59, 130, 246, 0.05));
  color: var(--primary-400);
}

.stat-card-icon--success {
  background: linear-gradient(135deg, rgba(16, 185, 129, 0.2), rgba(16, 185, 129, 0.05));
  color: var(--success);
}

.stat-card-icon--warning {
  background: linear-gradient(135deg, rgba(245, 158, 11, 0.2), rgba(245, 158, 11, 0.05));
  color: var(--warning);
}

.stat-card-icon svg {
  width: 32px;
  height: 32px;
}

.stat-card-content {
  flex: 1;
}

.stat-card-label {
  font-size: var(--text-sm);
  color: var(--gray-400);
  margin: 0 0 var(--space-2);
  font-weight: var(--font-medium);
}

.stat-card-value {
  font-size: var(--text-3xl);
  font-weight: var(--font-bold);
  color: white;
  margin: 0 0 var(--space-3);
  line-height: 1;
}

.stat-card-footer {
  display: flex;
  align-items: center;
  gap: var(--space-2);
}

.stat-card-trend {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: var(--text-sm);
  font-weight: var(--font-semibold);
  padding: 4px 8px;
  border-radius: var(--radius-md);
}

.stat-card-trend--up {
  background: rgba(16, 185, 129, 0.1);
  color: var(--success);
}

.stat-card-trend--down {
  background: rgba(239, 68, 68, 0.1);
  color: var(--error);
}

.stat-card-period {
  font-size: var(--text-xs);
  color: var(--gray-500);
}

/* Entity Card (Lead/Cliente) */
.entity-card {
  background: var(--bg-dark-secondary);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: var(--radius-xl);
  overflow: hidden;
  transition: all var(--transition-base);
}

.entity-card:hover {
  transform: translateY(-2px);
  box-shadow: var(--shadow-lg);
  border-color: rgba(59, 130, 246, 0.2);
}

.entity-card-header {
  padding: var(--space-5);
  display: flex;
  align-items: flex-start;
  gap: var(--space-3);
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

.entity-avatar {
  width: 48px;
  height: 48px;
  border-radius: var(--radius-full);
  background: linear-gradient(135deg, var(--primary-500), var(--accent-500));
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: var(--font-bold);
  color: white;
  font-size: var(--text-lg);
  flex-shrink: 0;
}

.entity-info {
  flex: 1;
}

.entity-name {
  font-size: var(--text-lg);
  font-weight: var(--font-semibold);
  color: white;
  margin: 0 0 4px;
}

.entity-meta {
  font-size: var(--text-sm);
  color: var(--gray-400);
  margin: 0;
}

.entity-status {
  flex-shrink: 0;
}

.entity-card-body {
  padding: var(--space-5);
}

.entity-details {
  display: flex;
  flex-direction: column;
  gap: var(--space-3);
}

.detail-item {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  font-size: var(--text-sm);
  color: var(--gray-300);
}

.entity-card-footer {
  padding: var(--space-4) var(--space-5);
  background: rgba(0, 0, 0, 0.2);
  display: flex;
  gap: var(--space-3);
  justify-content: flex-end;
}
```

---

### 3. **Botões (Sistema Completo)**

#### **Problemas Atuais**
- ❌ Poucos variantes
- ❌ Estados hover/focus/disabled inconsistentes
- ❌ Sem loading states integrados

#### **Melhorias Propostas**

**HTML Variantes**:
```html
<!-- Primary -->
<button class="btn btn-primary">
  <svg class="btn-icon"><!-- Icon --></svg>
  <span>Salvar</span>
</button>

<!-- Secondary -->
<button class="btn btn-secondary">Cancelar</button>

<!-- Ghost/Outline -->
<button class="btn btn-ghost">Voltar</button>

<!-- Danger -->
<button class="btn btn-danger">
  <svg class="btn-icon"><!-- Trash --></svg>
  <span>Excluir</span>
</button>

<!-- Success -->
<button class="btn btn-success">
  <svg class="btn-icon"><!-- Check --></svg>
  <span>Confirmar</span>
</button>

<!-- Loading State -->
<button class="btn btn-primary" disabled>
  <span class="btn-spinner"></span>
  <span>Salvando...</span>
</button>

<!-- Icon Only -->
<button class="btn btn-icon btn-ghost">
  <svg><!-- Icon --></svg>
</button>

<!-- Tamanhos -->
<button class="btn btn-sm btn-primary">Pequeno</button>
<button class="btn btn-primary">Médio (padrão)</button>
<button class="btn btn-lg btn-primary">Grande</button>
```

**CSS Botões**:
```css
/* public/css/buttons.css */

.btn {
  /* Base */
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--space-2);
  padding: var(--space-3) var(--space-5);
  border-radius: var(--radius-lg);
  font-size: var(--text-sm);
  font-weight: var(--font-semibold);
  font-family: var(--font-sans);
  border: 1px solid transparent;
  cursor: pointer;
  transition: all var(--transition-fast);
  position: relative;
  overflow: hidden;
  white-space: nowrap;
  text-decoration: none;
  
  /* Anti-aliasing */
  -webkit-font-smoothing: antialiased;
  -moz-osx-font-smoothing: grayscale;
}

.btn::before {
  content: '';
  position: absolute;
  top: 50%;
  left: 50%;
  width: 0;
  height: 0;
  border-radius: var(--radius-full);
  background: rgba(255, 255, 255, 0.3);
  transform: translate(-50%, -50%);
  transition: width var(--transition-base), height var(--transition-base);
}

.btn:active::before {
  width: 300px;
  height: 300px;
}

.btn-icon {
  width: 18px;
  height: 18px;
  flex-shrink: 0;
}

/* Primary */
.btn-primary {
  background: linear-gradient(135deg, var(--primary-600), var(--primary-500));
  color: white;
  box-shadow: 0 4px 14px 0 rgba(59, 130, 246, 0.4);
}

.btn-primary:hover {
  background: linear-gradient(135deg, var(--primary-700), var(--primary-600));
  transform: translateY(-2px);
  box-shadow: 0 6px 20px 0 rgba(59, 130, 246, 0.5);
}

.btn-primary:active {
  transform: translateY(0);
}

.btn-primary:disabled {
  background: var(--gray-700);
  color: var(--gray-500);
  cursor: not-allowed;
  box-shadow: none;
}

/* Secondary */
.btn-secondary {
  background: var(--bg-dark-tertiary);
  color: var(--gray-200);
  border-color: rgba(255, 255, 255, 0.1);
}

.btn-secondary:hover {
  background: var(--gray-700);
  border-color: rgba(255, 255, 255, 0.2);
  transform: translateY(-2px);
}

/* Ghost/Outline */
.btn-ghost {
  background: transparent;
  color: var(--gray-300);
  border-color: rgba(255, 255, 255, 0.15);
}

.btn-ghost:hover {
  background: rgba(255, 255, 255, 0.05);
  border-color: rgba(255, 255, 255, 0.3);
  color: white;
}

/* Danger */
.btn-danger {
  background: linear-gradient(135deg, #dc2626, var(--error));
  color: white;
  box-shadow: 0 4px 14px 0 rgba(239, 68, 68, 0.4);
}

.btn-danger:hover {
  background: linear-gradient(135deg, #b91c1c, #dc2626);
  box-shadow: 0 6px 20px 0 rgba(239, 68, 68, 0.5);
}

/* Success */
.btn-success {
  background: linear-gradient(135deg, var(--secondary-600), var(--secondary-500));
  color: white;
  box-shadow: 0 4px 14px 0 rgba(16, 185, 129, 0.4);
}

.btn-success:hover {
  background: linear-gradient(135deg, #047857, var(--secondary-600));
  box-shadow: 0 6px 20px 0 rgba(16, 185, 129, 0.5);
}

/* Tamanhos */
.btn-sm {
  padding: var(--space-2) var(--space-4);
  font-size: var(--text-xs);
  gap: var(--space-1);
}

.btn-sm .btn-icon {
  width: 14px;
  height: 14px;
}

.btn-lg {
  padding: var(--space-4) var(--space-8);
  font-size: var(--text-base);
  gap: var(--space-3);
}

.btn-lg .btn-icon {
  width: 20px;
  height: 20px;
}

/* Icon Only */
.btn-icon {
  padding: var(--space-3);
  width: 40px;
  height: 40px;
}

.btn-icon.btn-sm {
  padding: var(--space-2);
  width: 32px;
  height: 32px;
}

.btn-icon svg {
  width: 20px;
  height: 20px;
}

/* Loading Spinner */
.btn-spinner {
  width: 16px;
  height: 16px;
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-top-color: white;
  border-radius: var(--radius-full);
  animation: spin 0.6s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

/* Estados disabled */
.btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
  transform: none !important;
}
```

---

### 4. **Formulários**

#### **Problemas Atuais**
- ❌ Inputs genéricos sem personalidade
- ❌ Labels sem hierarquia
- ❌ Validação visual fraca
- ❌ Sem feedback de foco

#### **Melhorias Propostas**

**HTML Form Group**:
```html
<div class="form-group">
  <label class="form-label" for="nome">
    Nome completo
    <span class="form-required">*</span>
  </label>
  
  <div class="form-input-wrapper">
    <svg class="form-input-icon"><!-- User icon --></svg>
    <input 
      type="text" 
      id="nome" 
      class="form-input" 
      placeholder="Digite seu nome"
      required
    >
  </div>
  
  <p class="form-hint">Informe seu nome completo como consta no documento</p>
  
  <!-- Estado de erro -->
  <p class="form-error hidden">Nome é obrigatório</p>
</div>

<!-- Select customizado -->
<div class="form-group">
  <label class="form-label" for="status">Status</label>
  
  <div class="form-select-wrapper">
    <select id="status" class="form-select">
      <option value="">Selecione...</option>
      <option value="novo">Novo</option>
      <option value="qualificado">Qualificado</option>
    </select>
    <svg class="form-select-icon"><!-- Chevron down --></svg>
  </div>
</div>

<!-- Textarea -->
<div class="form-group">
  <label class="form-label" for="observacoes">Observações</label>
  <textarea 
    id="observacoes" 
    class="form-textarea" 
    rows="4"
    placeholder="Digite suas observações..."
  ></textarea>
</div>

<!-- Checkbox/Radio modernos -->
<div class="form-group">
  <label class="form-checkbox">
    <input type="checkbox" class="form-checkbox-input">
    <span class="form-checkbox-box"></span>
    <span class="form-checkbox-label">Aceito os termos e condições</span>
  </label>
</div>
```

**CSS Formulários**:
```css
/* public/css/forms.css */

.form-group {
  margin-bottom: var(--space-6);
}

.form-label {
  display: block;
  font-size: var(--text-sm);
  font-weight: var(--font-semibold);
  color: var(--gray-200);
  margin-bottom: var(--space-2);
}

.form-required {
  color: var(--error);
  margin-left: 2px;
}

.form-input-wrapper {
  position: relative;
  display: flex;
  align-items: center;
}

.form-input-icon {
  position: absolute;
  left: var(--space-4);
  width: 20px;
  height: 20px;
  color: var(--gray-500);
  pointer-events: none;
  z-index: 1;
}

.form-input {
  width: 100%;
  padding: var(--space-3) var(--space-4);
  padding-left: var(--space-12); /* Espaço para ícone */
  background: var(--bg-dark-tertiary);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: var(--radius-lg);
  color: white;
  font-size: var(--text-sm);
  font-family: var(--font-sans);
  transition: all var(--transition-fast);
}

.form-input::placeholder {
  color: var(--gray-500);
}

.form-input:focus {
  outline: none;
  border-color: var(--primary-500);
  background: var(--bg-dark-secondary);
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-input:hover:not(:focus) {
  border-color: rgba(255, 255, 255, 0.2);
}

/* Sem ícone */
.form-input-wrapper:not(:has(.form-input-icon)) .form-input {
  padding-left: var(--space-4);
}

/* Estados de validação */
.form-input.is-valid {
  border-color: var(--success);
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='%2310b981'%3e%3cpath fill-rule='evenodd' d='M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z' clip-rule='evenodd'/%3e%3c/svg%3e");
  background-position: right var(--space-3) center;
  background-size: 20px;
  background-repeat: no-repeat;
  padding-right: var(--space-10);
}

.form-input.is-invalid {
  border-color: var(--error);
}

/* Select */
.form-select-wrapper {
  position: relative;
}

.form-select {
  width: 100%;
  padding: var(--space-3) var(--space-10) var(--space-3) var(--space-4);
  background: var(--bg-dark-tertiary);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: var(--radius-lg);
  color: white;
  font-size: var(--text-sm);
  font-family: var(--font-sans);
  cursor: pointer;
  appearance: none;
  transition: all var(--transition-fast);
}

.form-select:focus {
  outline: none;
  border-color: var(--primary-500);
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.form-select-icon {
  position: absolute;
  right: var(--space-4);
  top: 50%;
  transform: translateY(-50%);
  width: 20px;
  height: 20px;
  color: var(--gray-500);
  pointer-events: none;
}

/* Textarea */
.form-textarea {
  width: 100%;
  padding: var(--space-3) var(--space-4);
  background: var(--bg-dark-tertiary);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: var(--radius-lg);
  color: white;
  font-size: var(--text-sm);
  font-family: var(--font-sans);
  resize: vertical;
  min-height: 100px;
  transition: all var(--transition-fast);
}

.form-textarea:focus {
  outline: none;
  border-color: var(--primary-500);
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

/* Checkbox customizado */
.form-checkbox {
  display: flex;
  align-items: center;
  gap: var(--space-3);
  cursor: pointer;
  user-select: none;
}

.form-checkbox-input {
  position: absolute;
  opacity: 0;
  width: 0;
  height: 0;
}

.form-checkbox-box {
  width: 20px;
  height: 20px;
  border: 2px solid rgba(255, 255, 255, 0.2);
  border-radius: var(--radius-md);
  background: var(--bg-dark-tertiary);
  transition: all var(--transition-fast);
  position: relative;
  flex-shrink: 0;
}

.form-checkbox-box::after {
  content: '';
  position: absolute;
  top: 2px;
  left: 6px;
  width: 4px;
  height: 9px;
  border: solid white;
  border-width: 0 2px 2px 0;
  transform: rotate(45deg) scale(0);
  transition: transform var(--transition-fast);
}

.form-checkbox-input:checked + .form-checkbox-box {
  background: var(--primary-500);
  border-color: var(--primary-500);
}

.form-checkbox-input:checked + .form-checkbox-box::after {
  transform: rotate(45deg) scale(1);
}

.form-checkbox:hover .form-checkbox-box {
  border-color: rgba(255, 255, 255, 0.4);
}

.form-checkbox-label {
  font-size: var(--text-sm);
  color: var(--gray-300);
}

/* Mensagens */
.form-hint {
  margin-top: var(--space-2);
  font-size: var(--text-xs);
  color: var(--gray-500);
}

.form-error {
  margin-top: var(--space-2);
  font-size: var(--text-xs);
  color: var(--error);
  display: flex;
  align-items: center;
  gap: var(--space-1);
}

.form-error::before {
  content: '⚠';
}

.form-error.hidden {
  display: none;
}
```

---

### 5. **Tabelas (Listagens)**

#### **Problemas Atuais**
- ❌ Difíceis de escanear
- ❌ Sem hover em linhas
- ❌ Headers sem destaque
- ❌ Paginação genérica

#### **Melhorias Propostas**

**HTML Tabela**:
```html
<div class="table-container">
  <!-- Header com filtros -->
  <div class="table-header">
    <div class="table-header-left">
      <h2 class="table-title">Leads</h2>
      <span class="table-count">127 resultados</span>
    </div>
    
    <div class="table-header-right">
      <div class="table-search">
        <svg class="table-search-icon"><!-- Search --></svg>
        <input type="text" class="table-search-input" placeholder="Buscar...">
      </div>
      
      <button class="btn btn-ghost btn-icon">
        <svg><!-- Filter --></svg>
      </button>
      
      <button class="btn btn-primary">
        <svg class="btn-icon"><!-- Plus --></svg>
        Novo lead
      </button>
    </div>
  </div>
  
  <!-- Tabela -->
  <div class="table-wrapper">
    <table class="table">
      <thead class="table-thead">
        <tr>
          <th class="table-th">
            <label class="form-checkbox">
              <input type="checkbox" class="form-checkbox-input">
              <span class="form-checkbox-box"></span>
            </label>
          </th>
          <th class="table-th table-th-sortable">
            Nome
            <svg class="table-sort-icon"><!-- Arrows --></svg>
          </th>
          <th class="table-th">Contato</th>
          <th class="table-th">Status</th>
          <th class="table-th">Orçamento</th>
          <th class="table-th">Criado em</th>
          <th class="table-th table-th-actions">Ações</th>
        </tr>
      </thead>
      
      <tbody class="table-tbody">
        <tr class="table-tr">
          <td class="table-td">
            <label class="form-checkbox">
              <input type="checkbox" class="form-checkbox-input">
              <span class="form-checkbox-box"></span>
            </label>
          </td>
          <td class="table-td">
            <div class="table-user">
              <div class="table-user-avatar">JS</div>
              <div>
                <p class="table-user-name">João Silva</p>
                <p class="table-user-meta">ID: #1234</p>
              </div>
            </div>
          </td>
          <td class="table-td">
            <p class="table-text">joao@email.com</p>
            <p class="table-text-sm">(31) 99999-9999</p>
          </td>
          <td class="table-td">
            <span class="badge badge-success">Qualificado</span>
          </td>
          <td class="table-td">
            <p class="table-text">R$ 300k - R$ 500k</p>
          </td>
          <td class="table-td">
            <p class="table-text">20/01/2026</p>
            <p class="table-text-sm">há 2 horas</p>
          </td>
          <td class="table-td">
            <div class="table-actions">
              <button class="btn-icon-sm" title="Ver">
                <svg><!-- Eye --></svg>
              </button>
              <button class="btn-icon-sm" title="Editar">
                <svg><!-- Edit --></svg>
              </button>
              <button class="btn-icon-sm text-error" title="Excluir">
                <svg><!-- Trash --></svg>
              </button>
            </div>
          </td>
        </tr>
        <!-- Mais linhas... -->
      </tbody>
    </table>
  </div>
  
  <!-- Paginação -->
  <div class="table-footer">
    <div class="table-footer-info">
      Mostrando <strong>1-10</strong> de <strong>127</strong> resultados
    </div>
    
    <div class="pagination">
      <button class="pagination-btn" disabled>
        <svg><!-- Chevron left --></svg>
      </button>
      
      <button class="pagination-btn pagination-btn-active">1</button>
      <button class="pagination-btn">2</button>
      <button class="pagination-btn">3</button>
      <span class="pagination-ellipsis">...</span>
      <button class="pagination-btn">13</button>
      
      <button class="pagination-btn">
        <svg><!-- Chevron right --></svg>
      </button>
    </div>
  </div>
</div>
```

**CSS Tabelas**:
```css
/* public/css/tables.css */

.table-container {
  background: var(--bg-dark-secondary);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: var(--radius-xl);
  overflow: hidden;
}

.table-header {
  padding: var(--space-5);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-4);
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  flex-wrap: wrap;
}

.table-header-left {
  display: flex;
  align-items: center;
  gap: var(--space-3);
}

.table-title {
  font-size: var(--text-xl);
  font-weight: var(--font-bold);
  color: white;
  margin: 0;
}

.table-count {
  font-size: var(--text-sm);
  color: var(--gray-400);
  background: rgba(255, 255, 255, 0.05);
  padding: 4px 12px;
  border-radius: var(--radius-full);
}

.table-header-right {
  display: flex;
  align-items: center;
  gap: var(--space-3);
}

.table-search {
  position: relative;
  width: 280px;
}

.table-search-icon {
  position: absolute;
  left: var(--space-3);
  top: 50%;
  transform: translateY(-50%);
  width: 18px;
  height: 18px;
  color: var(--gray-500);
}

.table-search-input {
  width: 100%;
  padding: var(--space-2) var(--space-3) var(--space-2) var(--space-10);
  background: var(--bg-dark-tertiary);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: var(--radius-lg);
  color: white;
  font-size: var(--text-sm);
  transition: all var(--transition-fast);
}

.table-search-input:focus {
  outline: none;
  border-color: var(--primary-500);
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}

.table-wrapper {
  overflow-x: auto;
}

.table {
  width: 100%;
  border-collapse: collapse;
}

.table-thead {
  background: rgba(0, 0, 0, 0.2);
}

.table-th {
  padding: var(--space-4) var(--space-5);
  text-align: left;
  font-size: var(--text-xs);
  font-weight: var(--font-semibold);
  color: var(--gray-400);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  white-space: nowrap;
}

.table-th-sortable {
  cursor: pointer;
  user-select: none;
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
}

.table-th-sortable:hover {
  color: var(--gray-200);
}

.table-sort-icon {
  width: 14px;
  height: 14px;
  opacity: 0.5;
  transition: opacity var(--transition-fast);
}

.table-th-sortable:hover .table-sort-icon {
  opacity: 1;
}

.table-th-actions {
  text-align: right;
}

.table-tbody {
  background: var(--bg-dark-secondary);
}

.table-tr {
  border-bottom: 1px solid rgba(255, 255, 255, 0.05);
  transition: background var(--transition-fast);
}

.table-tr:hover {
  background: rgba(59, 130, 246, 0.05);
}

.table-tr:last-child {
  border-bottom: none;
}

.table-td {
  padding: var(--space-4) var(--space-5);
  font-size: var(--text-sm);
  color: var(--gray-300);
  vertical-align: middle;
}

.table-user {
  display: flex;
  align-items: center;
  gap: var(--space-3);
}

.table-user-avatar {
  width: 36px;
  height: 36px;
  border-radius: var(--radius-full);
  background: linear-gradient(135deg, var(--primary-500), var(--accent-500));
  display: flex;
  align-items: center;
  justify-content: center;
  font-weight: var(--font-semibold);
  color: white;
  font-size: var(--text-xs);
  flex-shrink: 0;
}

.table-user-name {
  font-weight: var(--font-semibold);
  color: white;
  margin: 0;
}

.table-user-meta {
  font-size: var(--text-xs);
  color: var(--gray-500);
  margin: 0;
}

.table-text {
  margin: 0;
  color: var(--gray-300);
}

.table-text-sm {
  font-size: var(--text-xs);
  color: var(--gray-500);
  margin: 0;
}

.table-actions {
  display: flex;
  align-items: center;
  gap: var(--space-2);
  justify-content: flex-end;
}

.btn-icon-sm {
  width: 32px;
  height: 32px;
  padding: 0;
  border: none;
  background: transparent;
  color: var(--gray-400);
  border-radius: var(--radius-md);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all var(--transition-fast);
}

.btn-icon-sm svg {
  width: 16px;
  height: 16px;
}

.btn-icon-sm:hover {
  background: rgba(255, 255, 255, 0.1);
  color: white;
}

.btn-icon-sm.text-error:hover {
  background: rgba(239, 68, 68, 0.1);
  color: var(--error);
}

/* Footer e Paginação */
.table-footer {
  padding: var(--space-4) var(--space-5);
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-4);
  flex-wrap: wrap;
}

.table-footer-info {
  font-size: var(--text-sm);
  color: var(--gray-400);
}

.table-footer-info strong {
  color: white;
  font-weight: var(--font-semibold);
}

.pagination {
  display: flex;
  align-items: center;
  gap: var(--space-1);
}

.pagination-btn {
  min-width: 36px;
  height: 36px;
  padding: 0 var(--space-3);
  background: transparent;
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: var(--radius-md);
  color: var(--gray-300);
  font-size: var(--text-sm);
  font-weight: var(--font-medium);
  cursor: pointer;
  transition: all var(--transition-fast);
  display: flex;
  align-items: center;
  justify-content: center;
}

.pagination-btn svg {
  width: 16px;
  height: 16px;
}

.pagination-btn:hover:not(:disabled) {
  background: rgba(255, 255, 255, 0.05);
  border-color: rgba(255, 255, 255, 0.2);
  color: white;
}

.pagination-btn-active {
  background: var(--primary-500);
  border-color: var(--primary-500);
  color: white;
}

.pagination-btn:disabled {
  opacity: 0.4;
  cursor: not-allowed;
}

.pagination-ellipsis {
  padding: 0 var(--space-2);
  color: var(--gray-500);
}
```

---

### 6. **Badges e Tags**

**HTML**:
```html
<!-- Status badges -->
<span class="badge badge-success">Qualificado</span>
<span class="badge badge-warning">Em atendimento</span>
<span class="badge badge-error">Perdido</span>
<span class="badge badge-info">Novo</span>
<span class="badge badge-gray">Inativo</span>

<!-- Com ícone -->
<span class="badge badge-success">
  <svg class="badge-icon"><!-- Check --></svg>
  Aprovado
</span>

<!-- Tag removível -->
<span class="tag">
  Apartamento
  <button class="tag-remove">
    <svg><!-- X --></svg>
  </button>
</span>
```

**CSS**:
```css
/* public/css/badges.css */

.badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 4px 12px;
  border-radius: var(--radius-full);
  font-size: var(--text-xs);
  font-weight: var(--font-semibold);
  white-space: nowrap;
  line-height: 1.5;
}

.badge-icon {
  width: 12px;
  height: 12px;
  flex-shrink: 0;
}

.badge-success {
  background: rgba(16, 185, 129, 0.15);
  color: var(--success);
}

.badge-warning {
  background: rgba(245, 158, 11, 0.15);
  color: var(--warning);
}

.badge-error {
  background: rgba(239, 68, 68, 0.15);
  color: var(--error);
}

.badge-info {
  background: rgba(59, 130, 246, 0.15);
  color: var(--info);
}

.badge-gray {
  background: rgba(156, 163, 175, 0.15);
  color: var(--gray-400);
}

/* Tag removível */
.tag {
  display: inline-flex;
  align-items: center;
  gap: var(--space-2);
  padding: 6px 12px;
  background: var(--bg-dark-tertiary);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: var(--radius-lg);
  font-size: var(--text-sm);
  color: var(--gray-300);
}

.tag-remove {
  width: 16px;
  height: 16px;
  padding: 0;
  border: none;
  background: transparent;
  color: var(--gray-500);
  border-radius: var(--radius-sm);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all var(--transition-fast);
}

.tag-remove svg {
  width: 12px;
  height: 12px;
}

.tag-remove:hover {
  background: rgba(239, 68, 68, 0.1);
  color: var(--error);
}
```

---

### 7. **Modais e Overlays**

**HTML Modal**:
```html
<div class="modal-overlay" id="modalExample">
  <div class="modal">
    <!-- Header -->
    <div class="modal-header">
      <h3 class="modal-title">Confirmar exclusão</h3>
      <button class="modal-close">
        <svg><!-- X --></svg>
      </button>
    </div>
    
    <!-- Body -->
    <div class="modal-body">
      <p>Tem certeza que deseja excluir este lead? Esta ação não pode ser desfeita.</p>
    </div>
    
    <!-- Footer -->
    <div class="modal-footer">
      <button class="btn btn-secondary">Cancelar</button>
      <button class="btn btn-danger">Excluir</button>
    </div>
  </div>
</div>
```

**CSS Modal**:
```css
/* public/css/modals.css */

.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(4px);
  z-index: 9999;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: var(--space-4);
  opacity: 0;
  pointer-events: none;
  transition: opacity var(--transition-base);
}

.modal-overlay.show {
  opacity: 1;
  pointer-events: all;
}

.modal {
  background: var(--bg-dark-secondary);
  border: 1px solid rgba(255, 255, 255, 0.1);
  border-radius: var(--radius-2xl);
  box-shadow: var(--shadow-2xl);
  max-width: 500px;
  width: 100%;
  max-height: 90vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
  transform: scale(0.9);
  transition: transform var(--transition-base);
}

.modal-overlay.show .modal {
  transform: scale(1);
  animation: modalSlideUp 0.3s ease-out;
}

@keyframes modalSlideUp {
  from {
    transform: scale(0.9) translateY(20px);
  }
  to {
    transform: scale(1) translateY(0);
  }
}

.modal-header {
  padding: var(--space-6);
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: var(--space-4);
}

.modal-title {
  font-size: var(--text-xl);
  font-weight: var(--font-bold);
  color: white;
  margin: 0;
}

.modal-close {
  width: 36px;
  height: 36px;
  padding: 0;
  border: none;
  background: transparent;
  color: var(--gray-400);
  border-radius: var(--radius-lg);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all var(--transition-fast);
  flex-shrink: 0;
}

.modal-close svg {
  width: 20px;
  height: 20px;
}

.modal-close:hover {
  background: rgba(255, 255, 255, 0.1);
  color: white;
}

.modal-body {
  padding: var(--space-6);
  overflow-y: auto;
  flex: 1;
  color: var(--gray-300);
  line-height: 1.6;
}

.modal-footer {
  padding: var(--space-5) var(--space-6);
  border-top: 1px solid rgba(255, 255, 255, 0.1);
  display: flex;
  align-items: center;
  justify-content: flex-end;
  gap: var(--space-3);
}
```

---

## 📱 Responsividade

### Breakpoints Padrão
```css
:root {
  --breakpoint-sm: 640px;
  --breakpoint-md: 768px;
  --breakpoint-lg: 1024px;
  --breakpoint-xl: 1280px;
  --breakpoint-2xl: 1536px;
}
```

### Grid Responsivo
```css
/* public/css/grid.css */

.container {
  width: 100%;
  max-width: 1280px;
  margin: 0 auto;
  padding: 0 var(--space-4);
}

.grid {
  display: grid;
  gap: var(--space-6);
}

.grid-cols-1 { grid-template-columns: repeat(1, 1fr); }
.grid-cols-2 { grid-template-columns: repeat(2, 1fr); }
.grid-cols-3 { grid-template-columns: repeat(3, 1fr); }
.grid-cols-4 { grid-template-columns: repeat(4, 1fr); }

@media (max-width: 1024px) {
  .lg\:grid-cols-3 { grid-template-columns: repeat(2, 1fr); }
  .lg\:grid-cols-4 { grid-template-columns: repeat(3, 1fr); }
}

@media (max-width: 768px) {
  .md\:grid-cols-2 { grid-template-columns: repeat(1, 1fr); }
  .md\:grid-cols-3 { grid-template-columns: repeat(2, 1fr); }
  
  .sidebar {
    width: 100%;
    transform: translateX(-100%);
  }
  
  .sidebar.open {
    transform: translateX(0);
  }
  
  .main-content {
    margin-left: 0;
  }
}

@media (max-width: 640px) {
  .table-header {
    flex-direction: column;
    align-items: stretch;
  }
  
  .table-search {
    width: 100%;
  }
  
  .stat-card {
    flex-direction: column;
  }
}
```

---

## ♿ Acessibilidade

### Focus Visible
```css
/* public/css/accessibility.css */

*:focus-visible {
  outline: 2px solid var(--primary-500);
  outline-offset: 2px;
}

.btn:focus-visible,
.form-input:focus-visible,
.form-select:focus-visible {
  outline: none;
  box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.3);
}
```

### Skip Links
```html
<a href="#main-content" class="skip-link">
  Pular para o conteúdo principal
</a>
```

```css
.skip-link {
  position: absolute;
  top: -100px;
  left: 0;
  background: var(--primary-500);
  color: white;
  padding: var(--space-3) var(--space-5);
  border-radius: 0 0 var(--radius-lg) 0;
  font-weight: var(--font-semibold);
  z-index: 10000;
  transition: top var(--transition-fast);
}

.skip-link:focus {
  top: 0;
}
```

---

## 🚀 Roadmap de Implementação

### Fase 1: Design System Base (Prioridade ALTA) - 2-3 dias
- [ ] Criar `public/css/design-system.css` com todas as variáveis CSS
- [ ] Criar `public/css/utilities.css` com classes utilitárias
- [ ] Atualizar `public/css/glow.css` para usar variáveis
- [ ] Testar compatibilidade em todos os navegadores

### Fase 2: Componentes Core (Prioridade ALTA) - 3-4 dias
- [ ] Refatorar sidebar (`public/css/sidebar.css`)
- [ ] Sistema de botões completo (`public/css/buttons.css`)
- [ ] Formulários melhorados (`public/css/forms.css`)
- [ ] Cards padronizados (`public/css/cards.css`)

### Fase 3: Componentes de Listagem (Prioridade MÉDIA) - 2-3 dias
- [ ] Tabelas responsivas (`public/css/tables.css`)
- [ ] Badges e tags (`public/css/badges.css`)
- [ ] Paginação (`public/css/pagination.css`)

### Fase 4: Overlays e Feedback (Prioridade MÉDIA) - 2 dias
- [ ] Modais animados (`public/css/modals.css`)
- [ ] Toasts/notificações (`public/css/toasts.css`)
- [ ] Loading states aprimorados

### Fase 5: Páginas Específicas (Prioridade BAIXA) - 3-4 dias
- [ ] Dashboard com novos cards
- [ ] Leads com kanban melhorado
- [ ] Conversas com chat moderno
- [ ] Portal cliente com landing melhorada

### Fase 6: Polimento e Testes (Prioridade BAIXA) - 2 dias
- [ ] Testes de responsividade
- [ ] Testes de acessibilidade (WCAG 2.1)
- [ ] Otimização de performance
- [ ] Documentação de componentes

---

## 📊 Checklist Final

### Design System
- [ ] Variáveis CSS definidas
- [ ] Paleta de cores documentada
- [ ] Tipografia configurada
- [ ] Espaçamentos padronizados
- [ ] Sombras e elevações
- [ ] Transições e animações

### Componentes
- [ ] Sidebar moderna e responsiva
- [ ] Botões com todos os estados
- [ ] Formulários acessíveis
- [ ] Cards com elevação
- [ ] Tabelas scaneáveis
- [ ] Badges semânticas
- [ ] Modais animados

### UX/UI
- [ ] Feedback visual consistente
- [ ] Loading states em todas as ações
- [ ] Empty states com ilustrações
- [ ] Mensagens de erro claras
- [ ] Confirmações antes de ações destrutivas

### Responsividade
- [ ] Mobile first
- [ ] Breakpoints consistentes
- [ ] Touch targets adequados (min 44px)
- [ ] Sidebar colapsável em mobile

### Acessibilidade
- [ ] Contraste mínimo 4.5:1
- [ ] Focus visible em todos os interativos
- [ ] ARIA labels onde necessário
- [ ] Navegação por teclado
- [ ] Screen reader friendly

### Performance
- [ ] CSS otimizado (sem duplicação)
- [ ] Animações com GPU (transform/opacity)
- [ ] Lazy loading de imagens
- [ ] Fontes com font-display: swap

---

**Total Estimado**: 14-18 dias de desenvolvimento
**Prioridade**: ALTA para Fases 1-2, MÉDIA para Fases 3-4, BAIXA para Fases 5-6
**Impacto**: ⭐⭐⭐⭐⭐ ALTÍSSIMO - Melhora drasticamente a percepção de qualidade do sistema
