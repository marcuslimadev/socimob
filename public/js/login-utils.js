(() => {
    const API_URL = '/api';
    const adminRoles = new Set([
        'super_admin',
        'superadmin',
        'admin',
        'manager',
        'tenant',
        'tenant_admin',
        'tenantadmin'
    ]);

    function getRedirectForRole(role) {
        const normalized = (role || '').toString().toLowerCase();
        if (adminRoles.has(normalized)) {
            return '/app/dashboard.html';
        }
        return '/portal/imoveis.html';
    }

    function setButtonLoading({ button, loadingIndicator, label, isLoading, defaultText = 'Entrar' }) {
        if (!button) return;
        button.disabled = isLoading;
        if (label) {
            label.textContent = isLoading ? 'Entrando...' : defaultText;
        }
        if (loadingIndicator) {
            loadingIndicator.classList.toggle('hidden', !isLoading);
        }
    }

    const feedbackClasses = {
        success: 'glow-feedback success',
        error: 'glow-feedback error',
        info: 'glow-feedback info'
    };

    function showFeedback(el, type, message) {
        if (!el) return;
        el.textContent = message;
        const clazz = feedbackClasses[type] || feedbackClasses.info;
        el.className = clazz;
        el.classList.remove('hidden');
    }

    function clearFeedback(el) {
        if (!el) return;
        el.textContent = '';
        el.className = 'glow-feedback hidden';
    }

    async function fetchLogin(email, password) {
        try {
            const response = await fetch(`${API_URL}/auth/login`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ email, password })
            });

            const data = await response.json().catch(() => ({}));
            return { response, data };
        } catch (error) {
            return { error };
        }
    }

    window.LoginUtils = {
        API_URL,
        getRedirectForRole,
        setButtonLoading,
        showFeedback,
        clearFeedback,
        fetchLogin
    };
})();
