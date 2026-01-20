// ⚡ Loading Utilities - SOCIMOB
// Funções para gerenciar estados de loading na interface

const Loading = {
    /**
     * Exibir overlay de loading sobre um elemento
     * @param {string} elementId - ID do elemento (padrão: 'app')
     * @param {string} message - Mensagem a exibir (padrão: 'Carregando...')
     */
    show: function(elementId = 'app', message = 'Carregando...') {
        const element = document.getElementById(elementId);
        if (!element) {
            console.warn(`Elemento #${elementId} não encontrado`);
            return;
        }
        
        const overlayId = `loading-overlay-${elementId}`;
        
        // Remover overlay existente
        const existing = document.getElementById(overlayId);
        if (existing) existing.remove();
        
        const spinner = `
            <div class="loading-overlay" id="${overlayId}">
                <div class="loading-spinner">
                    <div class="spinner"></div>
                    <p class="loading-text">${message}</p>
                </div>
            </div>
        `;
        
        element.style.position = 'relative';
        element.insertAdjacentHTML('beforeend', spinner);
    },
    
    /**
     * Ocultar overlay de loading
     * @param {string} elementId - ID do elemento
     */
    hide: function(elementId = 'app') {
        const overlayId = `loading-overlay-${elementId}`;
        const overlay = document.getElementById(overlayId);
        if (overlay) {
            overlay.style.opacity = '0';
            setTimeout(() => overlay.remove(), 300);
        }
    },
    
    /**
     * Gerenciar estado de loading em botão
     * @param {HTMLElement} buttonElement - Elemento do botão
     * @param {boolean} loading - true para ativar, false para desativar
     * @param {string} loadingText - Texto durante loading (padrão: 'Processando...')
     */
    button: function(buttonElement, loading = true, loadingText = 'Processando...') {
        if (!buttonElement) {
            console.warn('Elemento de botão não fornecido');
            return;
        }
        
        if (loading) {
            buttonElement.disabled = true;
            buttonElement.dataset.originalText = buttonElement.textContent;
            buttonElement.dataset.originalClass = buttonElement.className;
            buttonElement.innerHTML = `<span class="spinner-small"></span> ${loadingText}`;
            buttonElement.classList.add('loading');
        } else {
            buttonElement.disabled = false;
            buttonElement.textContent = buttonElement.dataset.originalText || 'Enviar';
            buttonElement.className = buttonElement.dataset.originalClass || buttonElement.className;
            buttonElement.classList.remove('loading');
        }
    },
    
    /**
     * Loading inline (sem overlay)
     * @param {string} elementId - ID do elemento
     * @param {boolean} show - true para mostrar, false para ocultar
     */
    inline: function(elementId, show = true) {
        const element = document.getElementById(elementId);
        if (!element) return;
        
        if (show) {
            element.innerHTML = '<span class="spinner-inline"></span> Carregando...';
        } else {
            element.innerHTML = '';
        }
    },
    
    /**
     * Loading de tabela
     * @param {string} tableBodyId - ID do tbody
     * @param {number} colspan - Número de colunas
     */
    table: function(tableBodyId, colspan = 5) {
        const tbody = document.getElementById(tableBodyId);
        if (!tbody) return;
        
        tbody.innerHTML = `
            <tr class="loading-row">
                <td colspan="${colspan}" class="text-center py-4">
                    <div class="spinner-inline"></div>
                    <span class="ml-2">Carregando dados...</span>
                </td>
            </tr>
        `;
    },
    
    /**
     * Remover loading de tabela
     * @param {string} tableBodyId - ID do tbody
     * @param {string} emptyMessage - Mensagem quando vazio
     */
    tableHide: function(tableBodyId, emptyMessage = 'Nenhum registro encontrado') {
        const tbody = document.getElementById(tableBodyId);
        if (!tbody) return;
        
        const loadingRow = tbody.querySelector('.loading-row');
        if (loadingRow && tbody.children.length === 1) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="100" class="text-center py-4 text-gray-500">
                        ${emptyMessage}
                    </td>
                </tr>
            `;
        }
    }
};

// Adicionar ao window para acesso global
window.Loading = Loading;

// Helper para fetch com loading automático
async function fetchWithLoading(url, options = {}, elementId = 'app', message = 'Carregando...') {
    Loading.show(elementId, message);
    
    try {
        const response = await fetch(url, options);
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('Erro na requisição:', error);
        throw error;
    } finally {
        Loading.hide(elementId);
    }
}

window.fetchWithLoading = fetchWithLoading;
