/**
 * Sidebar Component - Sistema SOCIMOB
 * Componente reutilizável de menu lateral com controle de acesso
 */

class Sidebar {
    constructor() {
        this.user = null;
        this.currentPage = this.getCurrentPage();
        this.isCollapsed = localStorage.getItem('sidebar-collapsed') === 'true';
        this.isMobile = window.innerWidth <= 768;
        
        this.init();
    }

    init() {
        this.loadUserData();
        this.loadTenantConfig();
        this.render();
        this.attachEventListeners();
        this.handleResize();
    }

    loadUserData() {
        const userStr = localStorage.getItem('user');
        if (userStr) {
            try {
                this.user = JSON.parse(userStr);
            } catch (e) {
                console.error('Erro ao carregar dados do usuário:', e);
            }
        }
    }

    loadTenantConfig() {
        const configStr = localStorage.getItem('tenantConfig');
        if (configStr) {
            try {
                this.tenantConfig = JSON.parse(configStr);
            } catch (e) {
                console.error('Erro ao carregar configuração do tenant:', e);
                this.tenantConfig = {};
            }
        } else {
            this.tenantConfig = {};
        }
    }

    getCurrentPage() {
        const path = window.location.pathname;
        const page = path.split('/').pop() || 'dashboard.html';
        return page.replace('.html', '');
    }

    getMenuItems() {
        if (!this.user) return [];

        const role = this.user.role || 'user';
        
        // Menu base para todos os usuários autenticados
        const baseMenu = [
            {
                section: 'Principal',
                items: [
                    {
                        id: 'dashboard',
                        label: 'Dashboard',
                        icon: 'bi-speedometer2',
                        href: 'dashboard.html',
                        roles: ['super_admin', 'admin', 'user']
                    },
                    {
                        id: 'leads',
                        label: 'Leads',
                        icon: 'bi-people-fill',
                        href: 'leads.html',
                        roles: ['super_admin', 'admin', 'user'],
                        badge: null // Pode ser preenchido dinamicamente
                    },
                    {
                        id: 'leads-kanban',
                        label: 'Funil de Leads',
                        icon: 'bi-kanban',
                        href: 'leads-kanban.html',
                        roles: ['super_admin', 'admin', 'user']
                    },
                    {
                        id: 'visitas',
                        label: 'Visitas',
                        icon: 'bi-calendar-check',
                        href: 'visitas.html',
                        roles: ['super_admin', 'admin', 'user']
                    }
                ]
            },
            {
                section: 'Gestão',
                items: [
                    {
                        id: 'imoveis',
                        label: 'Imóveis',
                        icon: 'bi-house-fill',
                        href: 'imoveis.html',
                        roles: ['super_admin', 'admin']
                    },
                    {
                        id: 'clientes',
                        label: 'Clientes',
                        icon: 'bi-people',
                        href: 'clientes.html',
                        roles: ['super_admin', 'admin', 'user']
                    },
                    {
                        id: 'conversas',
                        label: 'Conversas',
                        icon: 'bi-chat-dots-fill',
                        href: 'conversas.html',
                        roles: ['super_admin', 'admin', 'user']
                    },
                    {
                        id: 'financeiro',
                        label: 'Financeiro',
                        icon: 'bi-cash-coin',
                        href: 'financeiro.html',
                        roles: ['super_admin', 'admin']
                    }
                ]
            },
            {
                section: 'Sistema',
                items: [
                    {
                        id: 'usuarios',
                        label: 'Equipe',
                        icon: 'bi-people',
                        href: 'usuarios.html',
                        roles: ['super_admin', 'admin']
                    },
                    {
                        id: 'configuracoes',
                        label: 'Configurações',
                        icon: 'bi-gear-fill',
                        href: 'configuracoes.html',
                        roles: ['super_admin', 'admin']
                    }
                ]
            }
        ];

        // Menu adicional para super admin
        if (role === 'super_admin') {
            baseMenu.push({
                section: 'Administração',
                items: [
                    {
                        id: 'super-admin',
                        label: 'Super Admin',
                        icon: 'bi-shield-fill-check',
                        href: '#super-admin',
                        roles: ['super_admin'],
                        variant: 'danger'
                    },
                    {
                        id: 'tenants',
                        label: 'Tenants',
                        icon: 'bi-building',
                        href: '#tenants',
                        roles: ['super_admin']
                    },
                    {
                        id: 'usuarios',
                        label: 'Usuários',
                        icon: 'bi-person-badge',
                        href: '#usuarios',
                        roles: ['super_admin']
                    }
                ]
            });
        }

        // Filtrar seções e itens baseado no role do usuário
        return baseMenu.map(section => ({
            ...section,
            items: section.items.filter(item => item.roles.includes(role))
        })).filter(section => section.items.length > 0);
    }

    render() {
        const menuSections = this.getMenuItems();
        const sistemaNome = this.tenantConfig.sistema_nome || 'SOCIMOB';
        const logoUrl = this.tenantConfig.logo_url;
        
        const sidebarHTML = `
            <!-- Sidebar -->
            <aside class="sidebar ${this.isCollapsed ? 'collapsed' : ''}" id="sidebar">
                <!-- Header -->
                <div class="sidebar-header">
                    <a href="dashboard.html" class="sidebar-logo">
                        ${logoUrl ? 
                            `<img src="${logoUrl}" alt="${sistemaNome}" style="max-width: 100%; max-height: 40px; object-fit: contain;">` : 
                            `<i class="bi bi-building"></i><span class="sidebar-logo-text">${sistemaNome}</span>`
                        }
                    </a>
                    <button class="sidebar-toggle" id="sidebarToggle">
                        <i class="bi bi-chevron-left"></i>
                    </button>
                </div>

                <!-- User Info -->
                ${this.renderUserInfo()}

                <!-- Navigation -->
                <nav class="sidebar-nav">
                    ${menuSections.map(section => this.renderSection(section)).join('')}
                </nav>

                <!-- Footer -->
                <div class="sidebar-footer">
                    <button class="sidebar-footer-btn" id="themeSelectorBtn">
                        <i class="bi bi-palette-fill"></i>
                        <span>Temas</span>
                    </button>
                    <button class="sidebar-footer-btn" id="btnSidebarLogout">
                        <i class="bi bi-box-arrow-right"></i>
                        <span>Sair</span>
                    </button>
                </div>
            </aside>

            <!-- Overlay mobile -->
            <div class="sidebar-overlay" id="sidebarOverlay"></div>

            <!-- Botão mobile -->
            ${this.isMobile ? '<button class="mobile-menu-btn" id="mobileMenuBtn"><i class="bi bi-list"></i></button>' : ''}
        `;

        // Inserir no início do body
        const appLayout = document.createElement('div');
        appLayout.className = 'app-layout';
        appLayout.innerHTML = sidebarHTML;
        
        // Remove o container de sidebar antigo se existir
        const oldSidebarContainer = document.getElementById('sidebar-container');
        if (oldSidebarContainer) {
            oldSidebarContainer.remove();
        }
        
        // Mover conteúdo existente para main-content (exceto scripts)
        const bodyChildren = Array.from(document.body.children).filter(el => 
            el.tagName !== 'SCRIPT' && el.id !== 'sidebar-container'
        );
        
        document.body.innerHTML = '';
        document.body.appendChild(appLayout);
        
        const mainContent = document.createElement('div');
        mainContent.className = 'main-content';
        bodyChildren.forEach(child => mainContent.appendChild(child));
        appLayout.appendChild(mainContent);
    }

    renderUserInfo() {
        if (!this.user) return '';

        const initials = this.user.name
            .split(' ')
            .map(n => n[0])
            .join('')
            .substring(0, 2)
            .toUpperCase();

        const roleLabels = {
            'super_admin': 'Super Admin',
            'admin': 'Administrador',
            'user': 'Usuário'
        };

        return `
            <div class="sidebar-user">
                <div class="sidebar-user-avatar">${initials}</div>
                <div class="sidebar-user-info">
                    <div class="sidebar-user-name">${this.user.name}</div>
                    <div class="sidebar-user-role">${roleLabels[this.user.role] || this.user.role}</div>
                </div>
            </div>
        `;
    }

    renderSection(section) {
        return `
            <div class="sidebar-section">
                <div class="sidebar-section-title">${section.section}</div>
                <ul class="sidebar-menu">
                    ${section.items.map(item => this.renderMenuItem(item)).join('')}
                </ul>
            </div>
        `;
    }

    renderMenuItem(item) {
        const isActive = this.currentPage === item.id;
        const activeClass = isActive ? 'active' : '';
        const variantClass = item.variant ? `sidebar-menu-link-${item.variant}` : '';
        const badge = item.badge ? `<span class="sidebar-menu-badge">${item.badge}</span>` : '';

        return `
            <li class="sidebar-menu-item">
                <a href="${item.href}" 
                   class="sidebar-menu-link ${activeClass} ${variantClass}"
                   data-tooltip="${item.label}">
                    <i class="sidebar-menu-icon ${item.icon}"></i>
                    <span class="sidebar-menu-text">${item.label}</span>
                    ${badge}
                </a>
            </li>
        `;
    }

    attachEventListeners() {
        // Toggle sidebar
        const toggleBtn = document.getElementById('sidebarToggle');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => this.toggleSidebar());
        }

        // Mobile menu button
        const mobileBtn = document.getElementById('mobileMenuBtn');
        if (mobileBtn) {
            mobileBtn.addEventListener('click', () => this.openMobileSidebar());
        }

        // Overlay click (mobile)
        const overlay = document.getElementById('sidebarOverlay');
        if (overlay) {
            overlay.addEventListener('click', () => this.closeMobileSidebar());
        }

        // Theme Selector
        const themeSelectorBtn = document.getElementById('themeSelectorBtn');
        if (themeSelectorBtn) {
            themeSelectorBtn.addEventListener('click', () => {
                if (typeof window.openThemeSelector === 'function') {
                    window.openThemeSelector();
                }
            });
        }

        // Logout
        const logoutBtn = document.getElementById('btnSidebarLogout');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', () => this.handleLogout());
        }

        // Resize handler
        window.addEventListener('resize', () => this.handleResize());
    }

    toggleSidebar() {
        const sidebar = document.getElementById('sidebar');
        if (!sidebar) return;

        this.isCollapsed = !this.isCollapsed;
        sidebar.classList.toggle('collapsed');
        localStorage.setItem('sidebar-collapsed', this.isCollapsed);
    }

    openMobileSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        if (sidebar) sidebar.classList.add('open');
        if (overlay) overlay.classList.add('active');
    }

    closeMobileSidebar() {
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        
        if (sidebar) sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('active');
    }

    handleResize() {
        const wasMobile = this.isMobile;
        this.isMobile = window.innerWidth <= 768;

        // Se mudou de desktop para mobile ou vice-versa, re-renderizar
        if (wasMobile !== this.isMobile) {
            this.closeMobileSidebar();
        }
    }

    handleLogout() {
        const token = localStorage.getItem('token');

        // Tentar fazer logout na API
        $.ajax({
            url: '/api/auth/logout',
            method: 'POST',
            headers: token ? { 'Authorization': 'Bearer ' + token } : {},
            complete: () => {
                // Limpar dados locais
                localStorage.removeItem('token');
                localStorage.removeItem('user');
                localStorage.removeItem('sidebar-collapsed');
                
                // Redirecionar para login
                window.location.href = 'login.html';
            }
        });
    }

    // Método público para atualizar badge de um item
    updateBadge(itemId, value) {
        const link = document.querySelector(`[href*="${itemId}"]`);
        if (!link) return;

        let badge = link.querySelector('.sidebar-menu-badge');
        
        if (value && value > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'sidebar-menu-badge';
                link.appendChild(badge);
            }
            badge.textContent = value;
        } else if (badge) {
            badge.remove();
        }
    }

    updateBranding() {
        this.loadTenantConfig();
        const sistemaNome = (this.tenantConfig && this.tenantConfig.sistema_nome) || 'SOCIMOB';
        const logoUrl = this.tenantConfig && this.tenantConfig.logo_url;
        const logoElement = document.querySelector('.sidebar-logo');
        
        if (logoElement) {
            if (logoUrl) {
                logoElement.innerHTML = `<img src="${logoUrl}" alt="${sistemaNome}" style="max-width: 100%; max-height: 40px; object-fit: contain;">`;
            } else {
                logoElement.innerHTML = `<i class="bi bi-building"></i><span class="sidebar-logo-text">${sistemaNome}</span>`;
            }
        }
    }
}

// Inicializar sidebar quando o documento estiver pronto
document.addEventListener('DOMContentLoaded', function() {
    // Verificar se usuário está autenticado
    const token = localStorage.getItem('token');
    const user = localStorage.getItem('user');
    
    if (!token || !user) {
        console.log('Usuário não autenticado - sidebar não será carregada');
        return;
    }

    // Criar instância global da sidebar
    window.sidebar = new Sidebar();
    
    console.log('✓ Sidebar inicializada');
});
