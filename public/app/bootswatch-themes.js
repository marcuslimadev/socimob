// Bootswatch Theme Selector System
(function() {
    'use strict';

    // Temas disponíveis do Bootswatch
    const bootswatchThemes = [
        { value: 'default', name: 'Bootstrap Padrão', preview: '#0d6efd' },
        { value: 'cerulean', name: 'Cerulean', preview: '#2FA4E7' },
        { value: 'cosmo', name: 'Cosmo', preview: '#2780E3' },
        { value: 'cyborg', name: 'Cyborg', preview: '#060606' },
        { value: 'darkly', name: 'Darkly', preview: '#375a7f' },
        { value: 'flatly', name: 'Flatly', preview: '#18BC9C' },
        { value: 'journal', name: 'Journal', preview: '#EB6864' },
        { value: 'litera', name: 'Litera', preview: '#4582EC' },
        { value: 'lumen', name: 'Lumen', preview: '#158CBA' },
        { value: 'lux', name: 'Lux', preview: '#1a1a1a' },
        { value: 'materia', name: 'Materia', preview: '#2196F3' },
        { value: 'minty', name: 'Minty', preview: '#78C2AD' },
        { value: 'morph', name: 'Morph', preview: '#378DFC' },
        { value: 'pulse', name: 'Pulse', preview: '#593196' },
        { value: 'quartz', name: 'Quartz', preview: '#438496' },
        { value: 'sandstone', name: 'Sandstone', preview: '#93C54B' },
        { value: 'simplex', name: 'Simplex', preview: '#D9230F' },
        { value: 'sketchy', name: 'Sketchy', preview: '#333' },
        { value: 'slate', name: 'Slate', preview: '#3A3F44' },
        { value: 'solar', name: 'Solar', preview: '#b58900' },
        { value: 'spacelab', name: 'Spacelab', preview: '#3399F3' },
        { value: 'superhero', name: 'Superhero', preview: '#DF691A' },
        { value: 'united', name: 'United', preview: '#E95420' },
        { value: 'vapor', name: 'Vapor', preview: '#6f42c1' },
        { value: 'yeti', name: 'Yeti', preview: '#008cba' },
        { value: 'zephyr', name: 'Zephyr', preview: '#00b3b3' }
    ];

    const BOOTSWATCH_CDN = 'https://cdn.jsdelivr.net/npm/bootswatch@5.3.2/dist';

    function initThemeSelector() {
        const savedTheme = localStorage.getItem('bootswatch-theme') || 'default';
        applyTheme(savedTheme);
        createThemeUI();
    }

    function applyTheme(themeName) {
        // Remove link antigo do Bootswatch se existir
        const oldLink = document.getElementById('bootswatch-theme-link');
        if (oldLink) {
            oldLink.remove();
        }

        // Encontra o link do Bootstrap padrão
        const bootstrapLink = document.querySelector('link[href*="bootstrap"][href*=".min.css"]:not([id="bootswatch-theme-link"])');
        
        if (themeName !== 'default') {
            // Substitui o Bootstrap pelo tema Bootswatch
            const link = document.createElement('link');
            link.id = 'bootswatch-theme-link';
            link.rel = 'stylesheet';
            link.href = `${BOOTSWATCH_CDN}/${themeName}/bootstrap.min.css`;
            
            if (bootstrapLink) {
                // Oculta o Bootstrap padrão
                bootstrapLink.disabled = true;
                // Insere o tema após o Bootstrap original
                bootstrapLink.parentNode.insertBefore(link, bootstrapLink.nextSibling);
            } else {
                document.head.appendChild(link);
            }
        } else {
            // Restaura o Bootstrap padrão
            if (bootstrapLink) {
                bootstrapLink.disabled = false;
            }
        }

        localStorage.setItem('bootswatch-theme', themeName);
        console.log('✅ Tema aplicado:', themeName);
    }

    function createThemeUI() {
        // Verifica se já existe
        if (document.getElementById('theme-selector-modal')) {
            return;
        }

        // Cria modal de seleção de temas
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.id = 'theme-selector-modal';
        modal.tabIndex = -1;
        modal.innerHTML = `
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="bi bi-palette-fill me-2"></i>
                            Escolher Tema Visual
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="text-muted mb-4">
                            Selecione um tema visual para personalizar a aparência do sistema.
                            Powered by <a href="https://bootswatch.com/" target="_blank">Bootswatch</a>
                        </p>
                        <div class="row g-3" id="theme-grid">
                            ${bootswatchThemes.map(theme => `
                                <div class="col-md-6 col-lg-4">
                                    <div class="theme-option card h-100" data-theme="${theme.value}">
                                        <div class="card-body text-center">
                                            <div class="theme-preview mb-3" style="background: ${theme.preview}; height: 80px; border-radius: 8px;"></div>
                                            <h6 class="card-title">${theme.name}</h6>
                                            <button class="btn btn-sm btn-outline-primary select-theme-btn">
                                                <i class="bi bi-check-circle"></i> Selecionar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        // Adiciona event listeners
        const themeOptions = modal.querySelectorAll('.theme-option');
        themeOptions.forEach(option => {
            const btn = option.querySelector('.select-theme-btn');
            const themeName = option.dataset.theme;
            
            btn.addEventListener('click', () => {
                applyTheme(themeName);
                
                // Atualiza UI
                themeOptions.forEach(opt => {
                    opt.classList.remove('border-primary', 'border-3');
                    opt.querySelector('.select-theme-btn').innerHTML = '<i class="bi bi-check-circle"></i> Selecionar';
                });
                
                option.classList.add('border-primary', 'border-3');
                btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Selecionado';
                
                // Fecha modal após 500ms
                setTimeout(() => {
                    const modalEl = document.getElementById('theme-selector-modal');
                    const modalInstance = bootstrap.Modal.getInstance(modalEl);
                    if (modalInstance) {
                        modalInstance.hide();
                    }
                }, 500);
            });

            // Marca tema atual
            const currentTheme = localStorage.getItem('bootswatch-theme') || 'default';
            if (themeName === currentTheme) {
                option.classList.add('border-primary', 'border-3');
                btn.innerHTML = '<i class="bi bi-check-circle-fill"></i> Selecionado';
            }
        });

        // Adiciona CSS customizado
        const style = document.createElement('style');
        style.textContent = `
            .theme-option {
                cursor: pointer;
                transition: all 0.3s ease;
                border: 2px solid transparent;
            }
            .theme-option:hover {
                transform: translateY(-4px);
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            }
            .theme-preview {
                box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            }
        `;
        document.head.appendChild(style);
    }

    // Inicializa quando DOM estiver pronto
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initThemeSelector);
    } else {
        initThemeSelector();
    }

    // Expõe função global para abrir seletor
    window.openThemeSelector = function() {
        const modalEl = document.getElementById('theme-selector-modal');
        if (modalEl) {
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
        }
    };
})();
