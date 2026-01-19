const API_URL = '/api';
const CDN_FALLBACKS = [
    'https://images.unsplash.com/photo-1505692794403-34d4982c87e4?auto=format&fit=crop&w=1400&q=80',
    'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=1400&q=80',
    'https://images.unsplash.com/photo-1493809842364-78817add7ffb?auto=format&fit=crop&w=1400&q=80',
    'https://images.unsplash.com/photo-1493663284031-b7e3aefcae8e?auto=format&fit=crop&w=1400&q=80',
    'https://images.unsplash.com/photo-1484154218962-a197022b5858?auto=format&fit=crop&w=1400&q=80'
];

const viewerState = {
    pages: [],
    cache: new Map(),
    current: 0,
    zoom: 1,
    mode: window.matchMedia('(max-width: 768px)').matches ? 'scroll' : 'flip',
    animating: false
};

const canvas = document.getElementById('revistaCanvas');
const ctx = canvas.getContext('2d');

const elements = {
    counter: document.getElementById('revistaCounter'),
    loading: document.getElementById('revistaLoading'),
    prev: document.getElementById('revistaPrev'),
    next: document.getElementById('revistaNext'),
    zoom: document.getElementById('revistaZoom'),
    fit: document.getElementById('revistaFit'),
    goTo: document.getElementById('revistaGoTo'),
    go: document.getElementById('revistaGo'),
    canvasWrapper: document.getElementById('revistaCanvasWrapper'),
    scrollWrapper: document.getElementById('revistaScrollWrapper'),
    scrollList: document.getElementById('revistaScrollList'),
    fullscreen: document.getElementById('btnFullScreen'),
};

function clamp(value, min, max) {
    return Math.min(Math.max(value, min), max);
}

function resolveImage(imovel, fallbackIndex) {
    if (!imovel) return CDN_FALLBACKS[fallbackIndex % CDN_FALLBACKS.length];
    const fotos = imovel.fotos || imovel.imagens || imovel.images || [];
    if (Array.isArray(fotos) && fotos.length) {
        const first = fotos[0];
        return first.url || first.caminho || first.path || first;
    }
    if (imovel.capa_url) return imovel.capa_url;
    if (imovel.capa) return imovel.capa;
    return CDN_FALLBACKS[fallbackIndex % CDN_FALLBACKS.length];
}

function mapImovelToPage(imovel, index) {
    const titulo = imovel?.titulo || 'ImÃ³vel em destaque';
    const finalidade = imovel?.finalidade || 'venda/aluguel';
    const preco = imovel?.preco ? `R$ ${formatPrice(imovel.preco)}` : 'Sob consulta';
    const imagem = resolveImage(imovel, index);
    const descricao = (imovel?.descricao || '').slice(0, 260) || 'CatÃ¡logo multimÃ­dia com efeito de revista inspirado na estÃ©tica Glow.';
    return {
        id: imovel?.id || `page-${index}`,
        titulo,
        finalidade,
        preco,
        descricao,
        imagem,
        type: String(imagem).toLowerCase().endsWith('.pdf') ? 'pdf' : 'image'
    };
}

function formatPrice(value) {
    if (!value && value !== 0) return '';
    return Number(value).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

async function loadData() {
    try {
        const response = await fetch(`${API_URL}/portal/imoveis`, { cache: 'force-cache' });
        const body = await response.json();
        const lista = Array.isArray(body?.data) ? body.data : Array.isArray(body) ? body : [];
        viewerState.pages = lista.map(mapImovelToPage);
    } catch (error) {
        console.warn('Falha ao buscar imÃ³veis, usando CDN:', error);
        viewerState.pages = CDN_FALLBACKS.map((url, index) => ({
            id: `fallback-${index}`,
            titulo: 'EdiÃ§Ã£o especial Glow',
            finalidade: 'CatÃ¡logo',
            preco: 'Design & Arte',
            descricao: 'Visual geomÃ©trico com assets em CDN e cache agressivo.',
            imagem: url,
            type: 'image'
        }));
    }

    if (!viewerState.pages.length) {
        viewerState.pages = CDN_FALLBACKS.map((url, index) => ({
            id: `base-${index}`,
            titulo: 'ColeÃ§Ã£o Imersiva',
            finalidade: 'ExperiÃªncia digital',
            preco: 'DisponÃ­vel sob consulta',
            descricao: 'Fallback de alta disponibilidade entregue via CDN.',
            imagem: url,
            type: 'image'
        }));
    }

    updateCounter();
    if (viewerState.mode === 'scroll') {
        renderScrollMode();
    } else {
        drawPage(viewerState.current, true);
    }
    elements.loading.classList.add('hidden');
}

async function loadAsset(page) {
    if (!page?.imagem) return null;
    if (viewerState.cache.has(page.imagem)) {
        return viewerState.cache.get(page.imagem);
    }

    const promise = page.type === 'pdf' ? renderPdf(page.imagem) : loadImage(page.imagem);
    viewerState.cache.set(page.imagem, promise);
    return promise;
}

function loadImage(src) {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = () => resolve(img);
        img.onerror = reject;
        img.src = src;
    });
}

async function renderPdf(url) {
    if (!window['pdfjsLib']) return null;
    const pdf = await pdfjsLib.getDocument({ url, cMapUrl: 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.2.67/cmaps/', cMapPacked: true }).promise;
    const page = await pdf.getPage(1);
    const viewport = page.getViewport({ scale: 1 });
    const offscreen = document.createElement('canvas');
    offscreen.width = viewport.width;
    offscreen.height = viewport.height;
    const context = offscreen.getContext('2d');
    await page.render({ canvasContext: context, viewport }).promise;
    return offscreen;
}

function clearCanvas() {
    ctx.save();
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.fillStyle = '#f6f3eb';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.restore();
}

async function drawPage(index, instant) {
    const page = viewerState.pages[index];
    if (!page) return;
    const asset = await loadAsset(page);
    if (!asset) return;

    const targetZoom = viewerState.zoom;
    const start = performance.now();
    const duration = instant ? 0 : 380;
    const startPage = viewerState.current;
    viewerState.current = index;

    function frame(now) {
        const progress = duration === 0 ? 1 : Math.min((now - start) / duration, 1);
        const eased = 0.5 - Math.cos(Math.PI * progress) / 2;
        renderFrame(page, asset, eased, targetZoom);
        if (progress < 1) {
            requestAnimationFrame(frame);
        }
    }

    requestAnimationFrame(frame);
    updateCounter();
}

function renderFrame(page, asset, t, zoom) {
    clearCanvas();
    const w = canvas.width;
    const h = canvas.height;
    const padding = 36 * zoom;
    const innerW = w - padding * 2;
    const innerH = h - padding * 2;

    ctx.save();
    ctx.translate(padding, padding);
    ctx.scale(zoom, zoom);

    // PÃ¡gina atual e prÃ³xima (efeito flip)
    const angle = (1 - t) * 0.35; // radianos aproximados
    ctx.save();
    ctx.transform(Math.cos(angle), 0, Math.sin(angle) * -1, 1, 0, 0);
    drawAsset(asset, innerW, innerH);
    ctx.restore();

    // Overlay de texto
    ctx.fillStyle = 'rgba(0,0,0,0.5)';
    ctx.fillRect(0, innerH - 140, innerW, 140);

    ctx.fillStyle = '#fff8e7';
    ctx.font = '700 28px "Glow", "Inter", sans-serif';
    ctx.fillText(page.titulo, 24, innerH - 88);
    ctx.font = '600 16px "Inter", sans-serif';
    ctx.fillText(`${page.finalidade} â¢ ${page.preco}`, 24, innerH - 58);
    ctx.font = '400 14px "Inter", sans-serif';
    wrapText(ctx, page.descricao, 24, innerH - 32, innerW - 48, 18);

    ctx.restore();
}

function drawAsset(asset, width, height) {
    const ratio = asset.width / asset.height;
    const target = width / height;
    let drawW = width;
    let drawH = height;

    if (ratio > target) {
        drawH = width / ratio;
    } else {
        drawW = height * ratio;
    }

    const offsetX = (width - drawW) / 2;
    const offsetY = (height - drawH) / 2;
    ctx.drawImage(asset, offsetX, offsetY, drawW, drawH);
}

function wrapText(context, text, x, y, maxWidth, lineHeight) {
    const words = text.split(' ');
    let line = '';
    for (let n = 0; n < words.length; n++) {
        const testLine = line + words[n] + ' ';
        const metrics = context.measureText(testLine);
        if (metrics.width > maxWidth && n > 0) {
            context.fillText(line, x, y);
            line = words[n] + ' ';
            y += lineHeight;
        } else {
            line = testLine;
        }
    }
    context.fillText(line, x, y);
}

function updateCounter() {
    const total = Math.max(viewerState.pages.length, 1);
    const current = clamp(viewerState.current + 1, 1, total);
    elements.counter.textContent = `${current} / ${total}`;
    elements.goTo.value = current;
}

function nextPage() {
    if (viewerState.current >= viewerState.pages.length - 1) return;
    drawPage(viewerState.current + 1);
}

function prevPage() {
    if (viewerState.current <= 0) return;
    drawPage(viewerState.current - 1);
}

function goToPage(value) {
    const target = clamp(Number(value) - 1, 0, viewerState.pages.length - 1);
    drawPage(target);
}

function setZoom(value) {
    viewerState.zoom = clamp(Number(value), 0.75, 1.5);
    drawPage(viewerState.current, true);
}

function fitToScreen() {
    const ratio = canvas.clientWidth / canvas.clientHeight;
    viewerState.zoom = ratio > 1 ? 1 : 1.1;
    elements.zoom.value = viewerState.zoom.toFixed(2);
    drawPage(viewerState.current, true);
}

function renderScrollMode() {
    elements.canvasWrapper.classList.add('hidden');
    elements.scrollWrapper.classList.remove('hidden');
    elements.loading.classList.add('hidden');
    elements.scrollList.innerHTML = '';

    viewerState.pages.forEach((page, index) => {
        const card = document.createElement('article');
        card.className = 'revista-scroll-card grid grid-cols-1 gap-4 md:grid-cols-[1.1fr_0.9fr]';
        card.innerHTML = `
            <div class="relative min-h-[240px] overflow-hidden">
                <img src="${page.imagem}" loading="lazy" alt="${page.titulo}" class="w-full h-full object-cover">
            </div>
            <div class="p-6 flex flex-col gap-2">
                <div class="text-xs font-black uppercase revista-meta text-[var(--glow-blue)]">${page.finalidade}</div>
                <h2 class="text-2xl font-black text-[var(--glow-black)]">${page.titulo}</h2>
                <div class="text-lg font-bold text-[var(--glow-black)]">${page.preco}</div>
                <p class="text-[var(--glow-black)] leading-relaxed">${page.descricao}</p>
                <div class="mt-auto flex items-center gap-3 text-xs text-[var(--glow-black)] uppercase tracking-[0.14em]">
                    <span>PÃ¡gina ${index + 1} de ${viewerState.pages.length}</span>
                    <div class="h-1 w-12 bg-[var(--glow-yellow)]"></div>
                </div>
            </div>
        `;
        elements.scrollList.appendChild(card);
    });
}

function handleFullScreen() {
    const el = elements.canvasWrapper;
    if (!el) return;
    if (!document.fullscreenElement) {
        el.requestFullscreen?.();
    } else {
        document.exitFullscreen?.();
    }
}

function bindEvents() {
    elements.prev.addEventListener('click', prevPage);
    elements.next.addEventListener('click', nextPage);
    elements.zoom.addEventListener('input', (e) => setZoom(e.target.value));
    elements.fit.addEventListener('click', fitToScreen);
    elements.go.addEventListener('click', () => goToPage(elements.goTo.value));
    elements.goTo.addEventListener('change', (e) => goToPage(e.target.value));
    elements.fullscreen.addEventListener('click', handleFullScreen);

    document.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowRight') nextPage();
        if (event.key === 'ArrowLeft') prevPage();
    });

    window.addEventListener('resize', () => {
        if (window.matchMedia('(max-width: 768px)').matches && viewerState.mode !== 'scroll') {
            viewerState.mode = 'scroll';
            renderScrollMode();
        }
    });

    // API mÃ­nima exportada
    window.magazineViewer = {
        next: nextPage,
        prev: prevPage,
        goToPage,
        zoom: setZoom
    };
}

(function init() {
    bindEvents();
    if (viewerState.mode === 'scroll') {
        elements.canvasWrapper.classList.add('hidden');
        elements.scrollWrapper.classList.remove('hidden');
    }
    loadData();
})();


