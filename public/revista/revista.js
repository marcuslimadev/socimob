const API_BASE = document.body?.dataset?.apiBase || '';
const API_PREFIX = `${API_BASE}/api`;
const TENANT_ID = new URLSearchParams(window.location.search).get('tenant');
const CDN_FALLBACKS = [
    'https://images.unsplash.com/photo-1505692794403-34d4982c87e4?auto=format&fit=crop&w=1600&q=80',
    'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=1600&q=80',
    'https://images.unsplash.com/photo-1493809842364-78817add7ffb?auto=format&fit=crop&w=1600&q=80',
    'https://images.unsplash.com/photo-1493663284031-b7e3aefcae8e?auto=format&fit=crop&w=1600&q=80',
    'https://images.unsplash.com/photo-1484154218962-a197022b5858?auto=format&fit=crop&w=1600&q=80'
];

const PDF_WORKER = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/4.2.67/pdf.worker.min.js';
const FLIP_DURATION = 620;
const PAGE_MARGIN = 28;

const viewerState = {
    pages: [],
    cache: new Map(),
    current: 0, // sempre índice da página esquerda (spread)
    zoom: 1,
    mode: window.matchMedia('(max-width: 768px)').matches ? 'scroll' : 'flip',
    animating: false,
};

const canvas = document.getElementById('revistaCanvas');
const ctx = canvas?.getContext('2d');

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

function configurePdfWorker() {
    if (window?.pdfjsLib) {
        pdfjsLib.GlobalWorkerOptions.workerSrc = PDF_WORKER;
    }
}

function clamp(value, min, max) {
    return Math.min(Math.max(value, min), max);
}

function buildEndpoint(path) {
    if (!TENANT_ID) return `${API_PREFIX}${path}`;
    const separator = path.includes('?') ? '&' : '?';
    return `${API_PREFIX}${path}${separator}tenant=${encodeURIComponent(TENANT_ID)}`;
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
    const titulo = imovel?.titulo || 'Imóvel em destaque';
    const finalidade = imovel?.finalidade || 'venda/aluguel';
    const preco = imovel?.preco ? `R$ ${formatPrice(imovel.preco)}` : 'Sob consulta';
    const imagem = resolveImage(imovel, index);
    const descricao = (imovel?.descricao || '').slice(0, 260) || 'Catálogo multimídia com efeito de revista estilo Flipsnack.';
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
    const endpoints = ['/portal/imoveis', '/portal/properties'];
    const parseList = (body) => {
        if (Array.isArray(body?.data)) return body.data;
        if (Array.isArray(body?.properties)) return body.properties;
        if (Array.isArray(body)) return body;
        return [];
    };

    for (const path of endpoints) {
        try {
            const url = buildEndpoint(path);
            const response = await fetch(url, { cache: 'force-cache' });
            if (!response.ok) {
                console.warn(`Endpoint ${url} retornou status ${response.status}`);
                continue;
            }

            const body = await response.json();
            const lista = parseList(body);
            if (lista.length) {
                viewerState.pages = lista.map(mapImovelToPage);
                break;
            }
        } catch (error) {
            console.warn(`Falha ao buscar imóveis em ${path}:`, error);
        }
    }

    if (!viewerState.pages.length) {
        viewerState.pages = [];
    }

    if (!viewerState.pages.length) {
        viewerState.pages = CDN_FALLBACKS.map((url, index) => ({
            id: `fallback-${index}`,
            titulo: 'Edição especial do catálogo',
            finalidade: 'Catálogo',
            preco: 'Design & Arte',
            descricao: 'Visual geométrico entregue via CDN e cache agressivo.',
            imagem: url,
            type: 'image'
        }));
    }

    // garante número par de páginas para folha dupla
    if (viewerState.pages.length % 2 !== 0) {
        viewerState.pages.push({
            id: 'blank-page',
            titulo: 'Contracapa',
            finalidade: '',
            preco: '',
            descricao: 'Folha extra para manter a dobra no estilo Flipsnack.',
            imagem: CDN_FALLBACKS[0],
            type: 'image'
        });
    }

    updateCounter();
    if (viewerState.mode === 'scroll') {
        renderScrollMode();
    } else {
        drawSpread(viewerState.current, { instant: true });
    }
    elements.loading.classList.add('hidden');
}

async function loadAsset(page) {
    if (!page?.imagem) return null;
    if (viewerState.cache.has(page.imagem)) {
        return viewerState.cache.get(page.imagem);
    }

    const promise = (async () => {
        try {
            return page.type === 'pdf' ? await renderPdf(page.imagem) : await loadImage(page.imagem);
        } catch (error) {
            console.error('Falha ao carregar asset da revista:', error);
            viewerState.cache.delete(page.imagem);
            return null;
        }
    })();

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
    if (!window?.pdfjsLib) return null;
    configurePdfWorker();
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
    if (!ctx || !canvas) return;
    ctx.save();
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.fillStyle = '#0a0b12';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.restore();
}

function buildSpread(index) {
    const leftIndex = Math.max(0, index - (index % 2));
    const rightIndex = leftIndex + 1;
    return {
        leftIndex,
        rightIndex,
        left: viewerState.pages[leftIndex],
        right: viewerState.pages[rightIndex]
    };
}

async function drawSpread(targetIndex, { instant = false } = {}) {
    if (!ctx || !canvas) {
        viewerState.mode = 'scroll';
        renderScrollMode();
        return;
    }

    if (viewerState.animating && !instant) return;

    const normalized = Math.max(0, targetIndex - (targetIndex % 2));
    const currentSpread = buildSpread(viewerState.current);
    const targetSpread = buildSpread(clamp(normalized, 0, Math.max(viewerState.pages.length - 2, 0)));

    const assets = await Promise.all([
        loadAsset(currentSpread.left),
        loadAsset(currentSpread.right),
        loadAsset(targetSpread.left),
        loadAsset(targetSpread.right)
    ]);

    const [leftFrom, rightFrom, leftTo, rightTo] = assets;
    const direction = targetSpread.leftIndex >= currentSpread.leftIndex ? 1 : -1;
    const start = performance.now();
    const duration = instant ? 0 : FLIP_DURATION;

    viewerState.animating = true;

    function frame(now) {
        const progress = duration === 0 ? 1 : Math.min((now - start) / duration, 1);
        const eased = easeInOut(progress);
        renderSpread(currentSpread, targetSpread, { leftFrom, rightFrom, leftTo, rightTo }, eased, direction);
        if (progress < 1) {
            requestAnimationFrame(frame);
        } else {
            viewerState.current = targetSpread.leftIndex;
            viewerState.animating = false;
            updateCounter();
        }
    }

    requestAnimationFrame(frame);
}

function easeInOut(t) {
    return 0.5 - Math.cos(Math.PI * t) / 2;
}

function renderSpread(current, target, assets, t, direction) {
    if (!ctx || !canvas) return;
    clearCanvas();

    const w = canvas.width;
    const h = canvas.height;
    const padding = PAGE_MARGIN * viewerState.zoom;
    const innerW = w - padding * 2;
    const innerH = h - padding * 2;
    const pageWidth = innerW / 2 - PAGE_MARGIN;
    const pageHeight = innerH;

    // fundo e lombada
    const spineX = padding + innerW / 2;
    const gradient = ctx.createLinearGradient(padding, 0, w - padding, 0);
    gradient.addColorStop(0, '#0b0c12');
    gradient.addColorStop(0.45, '#0f101a');
    gradient.addColorStop(0.5, '#1e1f2b');
    gradient.addColorStop(0.55, '#0f101a');
    gradient.addColorStop(1, '#0b0c12');
    ctx.fillStyle = gradient;
    ctx.fillRect(padding, padding, innerW, innerH);

    ctx.fillStyle = 'rgba(0,0,0,0.35)';
    ctx.fillRect(spineX - 2, padding, 4, innerH);

    // páginas de fundo (alvo) para dar sensação de camada
    if (assets.leftTo) {
        drawAsset(assets.leftTo, padding, padding, pageWidth, pageHeight, 0.92, true);
    }
    if (assets.rightTo) {
        drawAsset(assets.rightTo, spineX + PAGE_MARGIN / 2, padding, pageWidth, pageHeight, 0.92, true);
    }

    // página esquerda fixa (estado atual)
    if (assets.leftFrom) {
        drawAsset(assets.leftFrom, padding, padding, pageWidth, pageHeight, 1, false, current.left?.titulo);
    }

    // animação da página direita (flip)
    const flipFrom = direction === 1 ? assets.rightFrom : assets.leftTo;
    const flipTo = direction === 1 ? assets.leftTo : assets.rightFrom;
    const baseX = direction === 1 ? spineX + PAGE_MARGIN / 2 : padding + pageWidth;
    const dirMultiplier = direction === 1 ? -1 : 1;

    if (flipFrom) {
        drawFlipPage(flipFrom, flipTo, {
            x: baseX,
            y: padding,
            width: pageWidth,
            height: pageHeight,
            progress: t,
            direction: dirMultiplier,
            meta: direction === 1 ? current.right : target.left,
            backMeta: direction === 1 ? target.left : current.right,
        });
    }

    // página direita finalizada
    if (t === 1 && assets.rightTo) {
        drawAsset(assets.rightTo, spineX + PAGE_MARGIN / 2, padding, pageWidth, pageHeight, 1, false, target.right?.titulo);
    }
}

function drawFlipPage(frontAsset, backAsset, options) {
    const { x, y, width, height, progress, direction, meta, backMeta } = options;
    if (!ctx) return;

    const fold = Math.sin(progress * Math.PI);
    const compression = 1 - fold * 0.65;
    const skew = fold * 0.6 * direction;
    const translateX = x + (direction === 1 ? -fold * width * 0.95 : fold * width * 0.05);

    ctx.save();
    ctx.translate(translateX, y);
    ctx.transform(compression * direction, 0, skew, 1, 0, 0);
    drawAsset(frontAsset, 0, 0, width, height, 1, false, meta?.titulo);

    // sombra frontal
    const shadow = ctx.createLinearGradient(0, 0, width, 0);
    const startShadow = direction === 1 ? 0 : 1;
    shadow.addColorStop(startShadow, 'rgba(0,0,0,0.35)');
    shadow.addColorStop(0.5, 'rgba(0,0,0,0.15)');
    shadow.addColorStop(direction === 1 ? 1 : 0, 'rgba(0,0,0,0)');
    ctx.fillStyle = shadow;
    ctx.fillRect(0, 0, width, height);

    // verso da página
    if (backAsset) {
        ctx.globalAlpha = 0.55;
        ctx.globalCompositeOperation = 'lighter';
        drawAsset(backAsset, 0, 0, width, height, 1, true, backMeta?.titulo);
        ctx.globalCompositeOperation = 'source-over';
        ctx.globalAlpha = 1;
    }

    ctx.restore();
}

function drawAsset(asset, x, y, width, height, opacity = 1, muted = false, title = '') {
    if (!ctx || !asset) return;
    ctx.save();
    ctx.globalAlpha = opacity;

    const ratio = asset.width / asset.height;
    const target = width / height;
    let drawW = width;
    let drawH = height;

    if (ratio > target) {
        drawH = width / ratio;
    } else {
        drawW = height * ratio;
    }

    const offsetX = x + (width - drawW) / 2;
    const offsetY = y + (height - drawH) / 2;

    ctx.fillStyle = muted ? 'rgba(255,255,255,0.04)' : '#11131c';
    ctx.fillRect(x, y, width, height);
    ctx.drawImage(asset, offsetX, offsetY, drawW, drawH);

    // legenda minimalista como no Flipsnack
    if (title) {
        ctx.fillStyle = 'rgba(0,0,0,0.55)';
        ctx.fillRect(x, y + height - 46, width, 46);
        ctx.fillStyle = '#f6f6f6';
        ctx.font = '700 16px "Inter", sans-serif';
        ctx.fillText(title.slice(0, 40), x + 14, y + height - 16);
    }

    ctx.restore();
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
    const left = Math.max(0, viewerState.current - (viewerState.current % 2));
    const from = clamp(left + 1, 1, total);
    const to = clamp(left + 2, 1, total);
    elements.counter.textContent = `${from} - ${to} / ${total}`;
    elements.goTo.value = from;
}

function nextPage() {
    const target = Math.min(viewerState.current + 2, Math.max(viewerState.pages.length - 2, 0));
    if (target === viewerState.current) return;
    drawSpread(target);
}

function prevPage() {
    const target = Math.max(viewerState.current - 2, 0);
    if (target === viewerState.current) return;
    drawSpread(target);
}

function goToPage(value) {
    const numeric = clamp(Number(value) - 1, 0, viewerState.pages.length - 1);
    const normalized = numeric - (numeric % 2);
    drawSpread(normalized);
}

function setZoom(value) {
    viewerState.zoom = clamp(Number(value), 0.75, 1.35);
    drawSpread(viewerState.current, { instant: true });
}

function fitToScreen() {
    const ratio = canvas.clientWidth / canvas.clientHeight;
    viewerState.zoom = ratio > 1 ? 1 : 1.08;
    elements.zoom.value = viewerState.zoom.toFixed(2);
    drawSpread(viewerState.current, { instant: true });
}

function renderScrollMode() {
    elements.canvasWrapper.classList.add('hidden');
    elements.scrollWrapper.classList.remove('hidden');
    elements.loading.classList.add('hidden');
    elements.scrollList.innerHTML = '';

    viewerState.pages.forEach((page, index) => {
        const card = document.createElement('article');
        card.className = 'revista-scroll-card grid grid-cols-1 gap-4 md:grid-cols-[1.05fr_0.95fr]';
        card.innerHTML = `
            <div class="relative min-h-[260px] overflow-hidden">
                <img src="${page.imagem}" loading="lazy" alt="${page.titulo}" class="w-full h-full object-cover">
                <div class="absolute inset-0 bg-gradient-to-b from-black/10 via-transparent to-black/40"></div>
                <div class="absolute left-4 bottom-4 bg-white/90 text-[var(--bauhaus-black)] text-xs font-black px-3 py-1 rounded-full tracking-[0.16em]">${index === 0 ? 'CAPA' : `PÁGINA ${index + 1}`}</div>
            </div>
            <div class="p-6 flex flex-col gap-2">
                <div class="text-xs font-black uppercase revista-meta text-[var(--bauhaus-blue)]">${page.finalidade}</div>
                <h2 class="text-2xl font-black text-[var(--bauhaus-black)]">${page.titulo}</h2>
                <div class="text-lg font-bold text-[var(--bauhaus-ink)]">${page.preco}</div>
                <p class="text-[var(--bauhaus-ink)] leading-relaxed">${page.descricao}</p>
                <div class="mt-auto flex items-center gap-3 text-xs text-[var(--bauhaus-ink)] uppercase tracking-[0.14em]">
                    <span>Página ${index + 1} de ${viewerState.pages.length}</span>
                    <div class="h-1 w-12 bg-[var(--bauhaus-yellow)]"></div>
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
        const isMobile = window.matchMedia('(max-width: 768px)').matches;
        if (isMobile && viewerState.mode !== 'scroll') {
            viewerState.mode = 'scroll';
            renderScrollMode();
            return;
        }
        if (!isMobile && viewerState.mode !== 'flip') {
            viewerState.mode = 'flip';
            elements.canvasWrapper.classList.remove('hidden');
            elements.scrollWrapper.classList.add('hidden');
            drawSpread(viewerState.current, { instant: true });
        }
    });

    window.magazineViewer = {
        next: nextPage,
        prev: prevPage,
        goToPage,
        zoom: setZoom
    };
}

(function init() {
    configurePdfWorker();
    bindEvents();
    if (viewerState.mode === 'scroll') {
        elements.canvasWrapper.classList.add('hidden');
        elements.scrollWrapper.classList.remove('hidden');
    }
    loadData();
})();
