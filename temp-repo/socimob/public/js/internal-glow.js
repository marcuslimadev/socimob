(() => {
    function ensureGlowStyles() {
        const existing = document.querySelector('link[href="/css/glow.css"]');
        if (existing) return;
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = '/css/glow.css';
        document.head.appendChild(link);
    }

    function activateGlowBody() {
        document.body.classList.add('glow-page');
        document.body.classList.remove('bg-slate-50');
    }

    function restyleNav() {
        const nav = document.querySelector('nav');
        if (!nav) return;
        nav.classList.add('glow-nav', 'p-5');
        nav.classList.remove('bg-slate-900', 'text-white');
        const buttons = nav.querySelectorAll('button');
        buttons.forEach(btn => btn.classList.add('glow-button'));
    }

    function wrapMain() {
        const main = document.querySelector('main') || document.querySelector('.container.mx-auto');
        if (!main) return;
        main.classList.add('space-y-8');

        main.querySelectorAll('div').forEach(section => {
            const hasPanel = section.querySelector('h2, h3, h4, h5');
            if (hasPanel && !section.classList.contains('glow-panel')) {
                section.classList.add('glow-panel');
            }
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        ensureGlowStyles();
        activateGlowBody();
        restyleNav();
        wrapMain();
    });
})();
