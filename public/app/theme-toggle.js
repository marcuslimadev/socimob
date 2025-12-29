// Theme Toggle System
(function() {
    'use strict';

    // CSS para o botão de toggle e variáveis de tema
    const themeToggleCSS = `
        /* Botão de tema na sidebar */
        
        /* Tema Escuro (padrão) */
        :root {
            --bg-primary: #0f172a;
            --bg-secondary: rgba(30, 41, 59, 0.9);
            --bg-tertiary: rgba(15, 23, 42, 0.8);
            --text-primary: #f8fafc;
            --text-secondary: rgba(255,255,255,0.7);
            --text-muted: rgba(255,255,255,0.5);
            --border-color: rgba(255,255,255,0.1);
        }
        
        /* Tema Claro */
        [data-theme="light"] {
            --bg-primary: #f1f5f9;
            --bg-secondary: #ffffff;
            --bg-tertiary: #f8fafc;
            --text-primary: #0f172a;
            --text-secondary: rgba(15, 23, 42, 0.7);
            --text-muted: rgba(15, 23, 42, 0.5);
            --border-color: rgba(15, 23, 42, 0.1);
        }
        
        /* Ajustes específicos para tema claro */
        [data-theme="light"] body {
            background: #f1f5f9 !important;
            color: #0f172a !important;
        }
        
        [data-theme="light"] .form-control,
        [data-theme="light"] .form-select {
            background: #fff !important;
            color: #0f172a !important;
            border-color: rgba(15, 23, 42, 0.2) !important;
        }
        
        [data-theme="light"] .modal-content {
            background: #fff !important;
            color: #0f172a !important;
        }
        
        [data-theme="light"] .spinner-border {
            color: #3b82f6 !important;
        }
        
        [data-theme="light"] .lead-card,
        [data-theme="light"] .filter-card,
        [data-theme="light"] .visit-card,
        [data-theme="light"] .property-card,
        [data-theme="light"] .settings-card {
            background: linear-gradient(145deg, #ffffff, #f8fafc) !important;
            color: #0f172a !important;
            border-color: rgba(15, 23, 42, 0.1) !important;
        }
        
        [data-theme="light"] .text-white {
            color: #0f172a !important;
        }
        
        [data-theme="light"] .text-muted {
            color: rgba(15, 23, 42, 0.6) !important;
        }
        
        [data-theme="light"] .border-secondary {
            border-color: rgba(15, 23, 42, 0.2) !important;
        }
        
        [data-theme="light"] .leads-sidebar,
        [data-theme="light"] .chat-header,
        [data-theme="light"] .chat-input-area {
            background: #ffffff !important;
            color: #0f172a !important;
        }
        
        [data-theme="light"] .message-received {
            background: #f1f5f9 !important;
            color: #0f172a !important;
        }
    `;

    // Injeta CSS
    const styleEl = document.createElement('style');
    styleEl.textContent = themeToggleCSS;
    document.head.appendChild(styleEl);

    // Inicializa tema
    function initTheme() {
        const savedTheme = localStorage.getItem('theme') || 'dark';
        applyTheme(savedTheme);
        
        // Botão será criado pela sidebar
        // Adicionar listener quando o botão for criado
        setTimeout(() => {
            const btn = document.getElementById('themeToggle');
            if (btn && !btn.hasAttribute('data-theme-listener')) {
                btn.setAttribute('data-theme-listener', 'true');
                btn.addEventListener('click', toggleTheme);
            }
        }, 100);
    }

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        document.body.setAttribute('data-theme', theme);
    }

    function toggleTheme() {
        const currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
        const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
        
        applyTheme(newTheme);
        localStorage.setItem('theme', newTheme);
        
        const btn = document.getElementById('themeToggle');
        if (btn) {
            const icon = btn.querySelector('i');
            const span = btn.querySelector('span');
            if (icon) {
                icon.className = 'bi ' + (newTheme === 'dark' ? 'bi-sun-fill' : 'bi-moon-fill');
            }
            if (span) {
                span.textContent = newTheme === 'dark' ? 'Modo Claro' : 'Modo Escuro';
            }
        }
        
        console.log('Tema alterado para:', newTheme);
    }

    // Inicializa quando DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTheme);
    } else {
        initTheme();
    }
})();
