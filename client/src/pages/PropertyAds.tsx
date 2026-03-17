import { useEffect, useRef, useState, type Ref } from 'react';
import { motion } from 'framer-motion';
import { Image, Search, Loader2, Download } from 'lucide-react';
import { toPng } from 'html-to-image';
import { toast } from 'sonner';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';
import { fetchTenantBranding, type TenantBranding } from '@/lib/tenantBranding';

interface Property {
  id: number;
  title?: string;
  titulo?: string;
  codigo?: string;
  referencia?: string;
  type?: string;
  tipo_imovel?: string;
  transaction_type?: string;
  finalidade_imovel?: string;
  city?: string;
  cidade?: string;
  state?: string;
  estado?: string;
  neighborhood?: string;
  bairro?: string;
  bedrooms?: number;
  dormitorios?: number;
  suites?: number;
  bathrooms?: number;
  banheiros?: number;
  vagas_garagem?: number;
  garages?: number;
  area?: number;
  area_util?: number;
  area_privativa?: number;
  area_total?: number;
  price?: number;
  valor_venda?: number;
  valor_aluguel?: number;
  photos?: string[];
  imagens?: string[];
  imagem_destaque?: string;
  description?: string;
  descricao?: string;
}

const STORY_WIDTH = 1080;
const STORY_HEIGHT = 1920;

const isAbsoluteHttpUrl = (value: string) => /^https?:\/\//i.test(value);

const isCrossOriginUrl = (value: string) => {
  if (typeof window === 'undefined' || !isAbsoluteHttpUrl(value)) {
    return false;
  }

  try {
    return new URL(value).origin !== window.location.origin;
  } catch {
    return false;
  }
};

const fetchImageBlobUrl = async (src: string) => {
  const response = await api.get('/admin/property-ads/proxy-image', {
    params: { url: src },
    responseType: 'blob',
    headers: {
      Accept: 'image/*',
    },
  });

  return URL.createObjectURL(response.data);
};

const loadImage = async (src: string, objectUrls: string[] = []) => {
  const resolvedSrc = isCrossOriginUrl(src)
    ? await fetchImageBlobUrl(src)
    : src;

  if (resolvedSrc.startsWith('blob:')) {
    objectUrls.push(resolvedSrc);
  }

  return new Promise<HTMLImageElement>((resolve, reject) => {
    const image = new window.Image();
    image.onload = () => resolve(image);
    image.onerror = reject;
    image.src = resolvedSrc;
  });
};

const resolveAssetUrl = async (src?: string | null, objectUrls: string[] = []) => {
  if (!src) return '';

  const resolvedSrc = isCrossOriginUrl(src)
    ? await fetchImageBlobUrl(src)
    : src;

  if (resolvedSrc.startsWith('blob:')) {
    objectUrls.push(resolvedSrc);
  }

  return resolvedSrc;
};

const normalizePhotos = (property: Property): string[] => {
  const items = [
    property.imagem_destaque,
    ...(property.photos || []),
    ...(property.imagens || []),
  ].filter((item): item is string => Boolean(item));

  return Array.from(new Set(items));
};

const getPropertyTitle = (property: Property) => property.title || property.titulo || 'Imóvel';
const getPropertyType = (property: Property) => property.type || property.tipo_imovel || 'Imóvel';
const getTransactionType = (property: Property) => property.transaction_type || property.finalidade_imovel || '';
const getCity = (property: Property) => property.city || property.cidade || '';
const getState = (property: Property) => property.state || property.estado || '';
const getNeighborhood = (property: Property) => property.neighborhood || property.bairro || '';
const getBedrooms = (property: Property) => property.bedrooms || property.dormitorios || 0;
const getSuites = (property: Property) => property.suites || 0;
const getBathrooms = (property: Property) => property.bathrooms || property.banheiros || 0;
const getGarageSpots = (property: Property) => property.garages || property.vagas_garagem || 0;
const getArea = (property: Property) => property.area || property.area_util || property.area_privativa || property.area_total || 0;
const getPropertyCode = (property: Property) => property.codigo || property.referencia || '';
const getPrice = (property: Property) => {
  const transactionType = getTransactionType(property);
  if (transactionType === 'aluguel') {
    return property.valor_aluguel || property.price || property.valor_venda || 0;
  }

  return property.price || property.valor_venda || property.valor_aluguel || 0;
};

const formatArea = (value: number) => (value > 0 ? `${value.toLocaleString('pt-BR')}m²` : '');

const formatCurrency = (value: number) =>
  value > 0 ? `R$ ${value.toLocaleString('pt-BR')}` : 'Consulte valor';

const formatPhone = (value?: string | null) => {
  const digits = (value || '').replace(/\D/g, '');

  if (digits.length === 11) {
    return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
  }

  if (digits.length === 10) {
    return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`;
  }

  return value || '';
};

const getTransactionLabel = (type: string) => (type === 'aluguel' ? 'Aluguel' : 'Venda');

const getDetailTags = (property: Property) => {
  const tags = [getPropertyType(property)];
  const code = getPropertyCode(property);

  if (code) tags.push(`Cód. ${code}`);
  if (getSuites(property) > 0) tags.push(`${getSuites(property)} suíte${getSuites(property) === 1 ? '' : 's'}`);
  if (getGarageSpots(property) > 0) tags.push(`${getGarageSpots(property)} vaga${getGarageSpots(property) === 1 ? '' : 's'}`);

  return tags;
};

const getPrimarySpecs = (property: Property) => [
  getBedrooms(property) > 0 ? `${getBedrooms(property)} quartos` : null,
  getBathrooms(property) > 0 ? `${getBathrooms(property)} banheiros` : null,
  getArea(property) > 0 ? formatArea(getArea(property)) : null,
].filter(Boolean) as string[];

const getSecondarySpecs = (property: Property) => [
  getSuites(property) > 0 ? `${getSuites(property)} suíte${getSuites(property) === 1 ? '' : 's'}` : null,
  getGarageSpots(property) > 0 ? `${getGarageSpots(property)} vaga${getGarageSpots(property) === 1 ? '' : 's'}` : null,
  getPropertyCode(property) ? `Ref. ${getPropertyCode(property)}` : null,
].filter(Boolean) as string[];

const getLocationText = (property: Property) =>
  [getNeighborhood(property), `${getCity(property)}/${getState(property)}`]
    .filter(Boolean)
    .join(', ');

const splitLines = (text: string, maxLength: number, maxLines: number) => {
  const words = text.split(/\s+/).filter(Boolean);
  const lines: string[] = [];
  let current = '';

  for (const word of words) {
    const next = current ? `${current} ${word}` : word;
    if (next.length <= maxLength) {
      current = next;
      continue;
    }

    if (current) lines.push(current);
    current = word;

    if (lines.length === maxLines - 1) break;
  }

  if (lines.length < maxLines && current) {
    lines.push(current);
  }

  if (words.join(' ').length > lines.join(' ').length && lines.length > 0) {
    lines[lines.length - 1] = `${lines[lines.length - 1].replace(/[. ]+$/, '')}...`;
  }

  return lines;
};

const wrapCanvasText = (
  ctx: CanvasRenderingContext2D,
  text: string,
  maxWidth: number,
  maxLines: number,
) => {
  const words = text.split(/\s+/).filter(Boolean);
  const lines: string[] = [];
  let current = '';

  for (const word of words) {
    const next = current ? `${current} ${word}` : word;
    if (ctx.measureText(next).width <= maxWidth) {
      current = next;
      continue;
    }

    if (current) {
      lines.push(current);
    }

    current = word;

    if (lines.length === maxLines - 1) {
      break;
    }
  }

  if (lines.length < maxLines && current) {
    lines.push(current);
  }

  if (words.join(' ') !== lines.join(' ')) {
    const lastIndex = lines.length - 1;
    if (lastIndex >= 0) {
      let truncated = lines[lastIndex].replace(/[. ]+$/, '');
      while (truncated && ctx.measureText(`${truncated}...`).width > maxWidth) {
        truncated = truncated.slice(0, -1).trimEnd();
      }
      lines[lastIndex] = `${truncated}...`;
    }
  }

  return lines;
};

const fitCanvasFontSize = (
  ctx: CanvasRenderingContext2D,
  text: string,
  initialSize: number,
  minSize: number,
  maxWidth: number,
  fontWeight: string,
) => {
  let fontSize = initialSize;

  while (fontSize > minSize) {
    ctx.font = `${fontWeight} ${fontSize}px sans-serif`;
    if (ctx.measureText(text).width <= maxWidth) {
      return fontSize;
    }
    fontSize -= 2;
  }

  return minSize;
};

const measureTextBlockHeight = (lineCount: number, lineHeight: number) =>
  lineCount > 0 ? lineCount * lineHeight : 0;

const disableRemoteFontStylesheets = () => {
  const remoteFontLinks = Array.from(document.querySelectorAll<HTMLLinkElement>('link[rel="stylesheet"]'))
    .filter((link) => {
      const href = link.href || '';
      return href.includes('fonts.googleapis.com') || href.includes('fonts.cdnfonts.com');
    });

  remoteFontLinks.forEach((link) => {
    link.dataset.exportDisabled = link.disabled ? 'already-disabled' : 'enabled';
    link.disabled = true;
  });

  return () => {
    remoteFontLinks.forEach((link) => {
      if (link.dataset.exportDisabled === 'enabled') {
        link.disabled = false;
      }
      delete link.dataset.exportDisabled;
    });
  };
};

interface StoryPreviewCardProps {
  property: Property;
  tenant: TenantBranding | null;
  photos: string[];
  storyRef?: Ref<HTMLDivElement>;
  className?: string;
}

interface ExportStoryState {
  property: Property;
  photos: string[];
  logoSrc: string;
}

function StoryPreviewCard({ property, tenant, photos, storyRef, className }: StoryPreviewCardProps) {
  const thumbPhotos = photos.slice(1, 7);
  const transactionType = getTransactionType(property);
  const detailTags = getDetailTags(property);
  const primarySpecs = getPrimarySpecs(property);
  const secondarySpecs = getSecondarySpecs(property);
  const logoSrc = tenant?.logo_url || tenant?.logo;
  const tenantPhone = formatPhone(tenant?.tenant_phone || tenant?.contact_phone);

  const getTransactionBadge = (type: string) => (
    type === 'venda'
      ? 'bg-emerald-500/20 text-emerald-200 border-emerald-400/40'
      : 'bg-sky-500/20 text-sky-200 border-sky-400/40'
  );

  return (
    <div
      ref={storyRef}
      className={className || 'relative mx-auto aspect-[9/16] w-full max-w-[330px] overflow-hidden rounded-[30px] border border-white/15 bg-[#0a1320]'}
    >
      {photos.length > 0 ? (
        <img
          src={photos[0]}
          alt={getPropertyTitle(property)}
          className="absolute inset-0 h-full w-full object-cover"
        />
      ) : (
        <div className="absolute inset-0 bg-gradient-to-br from-slate-700 to-slate-950" />
      )}

      <div className="absolute inset-0 bg-gradient-to-b from-black/10 via-black/35 to-[#07111d]" />

      <div className="absolute inset-x-4 top-4 flex items-center justify-start">
        <span className={`rounded-full border px-3 py-1 text-[11px] font-semibold ${getTransactionBadge(transactionType)}`}>
          {getTransactionLabel(transactionType)}
        </span>
      </div>

      <div className="absolute inset-x-4 bottom-4 rounded-[26px] bg-[#07111d]/72 p-4 backdrop-blur-sm">
        <div className="mb-2 flex items-start justify-between gap-3">
          <div className="flex min-w-0 items-start gap-2">
            {logoSrc && (
              <div className="mt-0.5 flex h-7 items-center rounded-md bg-white/95 px-2 py-1">
                <img src={logoSrc} alt={tenant?.name || 'Logo'} className="max-h-5 w-auto object-contain" />
              </div>
            )}
            <div className="min-w-0">
              <p className="truncate text-[10px] uppercase tracking-[0.28em] text-amber-300">
                {tenant?.name || 'Tenant'}
              </p>
              {tenantPhone && (
                <p className="mt-1 truncate text-[10px] font-semibold tracking-[0.08em] text-white/82">
                  {tenantPhone}
                </p>
              )}
            </div>
          </div>
        </div>
        <h3 className="line-clamp-2 text-[1.38rem] font-bold leading-[1.08] text-white">
          {getPropertyTitle(property)}
        </h3>
        <p className="mt-2 line-clamp-2 text-sm text-white/72">
          {getLocationText(property) || 'Localização sob consulta'}
        </p>
        {detailTags.length > 0 && (
          <div className="mt-2 flex flex-wrap gap-1.5 text-[11px] text-white/62">
            {detailTags.map((tag) => (
              <span key={tag} className="rounded-full bg-white/8 px-2 py-1">
                {tag}
              </span>
            ))}
          </div>
        )}
        <p className="mt-3 text-[1.7rem] font-bold leading-none text-white">
          {formatCurrency(getPrice(property))}
        </p>
        {primarySpecs.length > 0 && (
          <div className="mt-2 flex flex-wrap gap-2 text-[12px] text-white/78">
            {primarySpecs.map((spec) => <span key={spec}>{spec}</span>)}
          </div>
        )}
        {secondarySpecs.length > 0 && (
          <div className="mt-1 flex flex-wrap gap-2 text-[11px] text-white/58">
            {secondarySpecs.map((spec) => <span key={spec}>{spec}</span>)}
          </div>
        )}
        {thumbPhotos.length > 0 && (
          <div className="mt-3 grid grid-cols-3 gap-2">
            {thumbPhotos.map((photo, index) => (
              <div
                key={`${property.id}-${index}`}
                className="relative aspect-square overflow-hidden rounded-xl border border-white/10 bg-white/5 shadow-[0_10px_22px_rgba(15,23,42,0.22),0_2px_6px_rgba(15,23,42,0.12)]"
              >
                <img src={photo} alt={`${getPropertyTitle(property)} ${index + 2}`} className="h-full w-full object-cover" />
                <div className="pointer-events-none absolute inset-0 bg-gradient-to-b from-white/5 via-transparent to-slate-950/10" />
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}

export default function PropertyAds() {
  const [properties, setProperties] = useState<Property[]>([]);
  const [tenant, setTenant] = useState<TenantBranding | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState('');
  const [downloadingPropertyId, setDownloadingPropertyId] = useState<number | null>(null);
  const [exportStory, setExportStory] = useState<ExportStoryState | null>(null);
  const exportStoryRef = useRef<HTMLDivElement | null>(null);
  const exportObjectUrlsRef = useRef<string[]>([]);

  useEffect(() => {
    fetchProperties();
    fetchTenantBranding().then(setTenant).catch(() => null);
  }, []);

  const fetchProperties = async () => {
    try {
      setIsLoading(true);
      const response = await api.get('/imoveis', { params: { per_page: 100 } });
      setProperties(response.data.data || []);
    } catch (error) {
      console.error('Erro ao carregar imóveis:', error);
      toast.error('Erro ao carregar imóveis');
    } finally {
      setIsLoading(false);
    }
  };

  const createStoryImage = async (property: Property) => {
    const objectUrls: string[] = [];
    const canvas = document.createElement('canvas');
    canvas.width = STORY_WIDTH;
    canvas.height = STORY_HEIGHT;
    const ctx = canvas.getContext('2d');

    if (!ctx) {
      throw new Error('Canvas não disponível');
    }

    const photos = normalizePhotos(property);
    const mainPhotoUrl = photos[0];
    const thumbUrls = photos.slice(1, 7);
    const transactionType = getTransactionType(property);
    const detailTags = getDetailTags(property);
    const primarySpecs = getPrimarySpecs(property);
    const secondarySpecs = getSecondarySpecs(property);
    const primaryColor = tenant?.primary_color || '#0f172a';
    const secondaryColor = tenant?.secondary_color || '#d4a34f';
    const tenantPhone = formatPhone(tenant?.tenant_phone || tenant?.contact_phone);
    const cardX = 40;
    const cardY = 40;
    const cardWidth = STORY_WIDTH - 80;
    const cardHeight = STORY_HEIGHT - 80;
    const cardRadius = 78;
    const panelX = cardX + 48;
    const panelY = cardY + cardHeight - 840;
    const panelWidth = cardWidth - 96;
    const panelHeight = 760;
    const panelRadius = 58;

    const drawPill = (
      x: number,
      y: number,
      text: string,
      options: {
        fill: string;
        textColor: string;
        stroke?: string;
        font?: string;
        horizontalPadding?: number;
        height?: number;
      },
    ) => {
      const {
        fill,
        textColor,
        stroke,
        font = '600 32px sans-serif',
        horizontalPadding = 28,
        height = 72,
      } = options;

      ctx.font = font;
      const textWidth = ctx.measureText(text).width;
      const width = textWidth + horizontalPadding * 2;

      ctx.save();
      ctx.fillStyle = fill;
      ctx.beginPath();
      ctx.roundRect(x, y, width, height, height / 2);
      ctx.fill();

      if (stroke) {
        ctx.strokeStyle = stroke;
        ctx.lineWidth = 2;
        ctx.stroke();
      }

      ctx.fillStyle = textColor;
      ctx.textBaseline = 'middle';
      ctx.fillText(text, x + horizontalPadding, y + height / 2);
      ctx.restore();

      return width;
    };

    ctx.fillStyle = '#050b14';
    ctx.fillRect(0, 0, STORY_WIDTH, STORY_HEIGHT);

    try {
      ctx.save();
      ctx.beginPath();
      ctx.roundRect(cardX, cardY, cardWidth, cardHeight, cardRadius);
      ctx.clip();

      if (mainPhotoUrl) {
        try {
          const mainImage = await loadImage(mainPhotoUrl, objectUrls);
          const scale = Math.max(cardWidth / mainImage.width, cardHeight / mainImage.height);
          const drawWidth = mainImage.width * scale;
          const drawHeight = mainImage.height * scale;
          const drawX = cardX + (cardWidth - drawWidth) / 2;
          const drawY = cardY + (cardHeight - drawHeight) / 2;
          ctx.drawImage(mainImage, drawX, drawY, drawWidth, drawHeight);
        } catch {
          const fallback = ctx.createLinearGradient(cardX, cardY, cardX + cardWidth, cardY + cardHeight);
          fallback.addColorStop(0, primaryColor);
          fallback.addColorStop(1, '#0b1220');
          ctx.fillStyle = fallback;
          ctx.fillRect(cardX, cardY, cardWidth, cardHeight);
        }
      } else {
        const fallback = ctx.createLinearGradient(cardX, cardY, cardX + cardWidth, cardY + cardHeight);
        fallback.addColorStop(0, primaryColor);
        fallback.addColorStop(1, '#0b1220');
        ctx.fillStyle = fallback;
        ctx.fillRect(cardX, cardY, cardWidth, cardHeight);
      }

      const overlay = ctx.createLinearGradient(0, cardY, 0, cardY + cardHeight);
      overlay.addColorStop(0, 'rgba(0,0,0,0.08)');
      overlay.addColorStop(0.48, 'rgba(0,0,0,0.28)');
      overlay.addColorStop(0.72, 'rgba(7,17,29,0.46)');
      overlay.addColorStop(1, 'rgba(7,17,29,0.92)');
      ctx.fillStyle = overlay;
      ctx.fillRect(cardX, cardY, cardWidth, cardHeight);

      const logoUrl = tenant?.logo_url || tenant?.logo;
      if (logoUrl) {
        try {
          const logo = await loadImage(logoUrl, objectUrls);
          const logoMaxWidth = 360;
          const logoMaxHeight = 160;
          const scale = Math.min(logoMaxWidth / logo.width, logoMaxHeight / logo.height);
          const width = logo.width * scale;
          const height = logo.height * scale;
          ctx.save();
          ctx.globalAlpha = 0.16;
          ctx.drawImage(logo, cardX + (cardWidth - width) / 2, cardY + 640, width, height);
          ctx.restore();
        } catch {
          // ignore logo load failure
        }
      }

      ctx.restore();

      ctx.strokeStyle = 'rgba(255,255,255,0.14)';
      ctx.lineWidth = 3;
      ctx.beginPath();
      ctx.roundRect(cardX, cardY, cardWidth, cardHeight, cardRadius);
      ctx.stroke();

      drawPill(cardX + 48, cardY + 48, getTransactionLabel(transactionType), {
        fill: transactionType === 'aluguel' ? 'rgba(14,165,233,0.22)' : 'rgba(16,185,129,0.22)',
        stroke: transactionType === 'aluguel' ? 'rgba(56,189,248,0.42)' : 'rgba(52,211,153,0.38)',
        textColor: transactionType === 'aluguel' ? '#d8f3ff' : '#d9ffef',
        font: '700 28px sans-serif',
        horizontalPadding: 26,
        height: 68,
      });

      ctx.fillStyle = 'rgba(7,17,29,0.72)';
      ctx.beginPath();
      ctx.roundRect(panelX, panelY, panelWidth, panelHeight, panelRadius);
      ctx.fill();

      let tenantLabelX = panelX + 44;

      if (logoUrl) {
        try {
          const headerLogo = await loadImage(logoUrl, objectUrls);
          const logoMaxWidth = 150;
          const logoMaxHeight = 48;
          const scale = Math.min(logoMaxWidth / headerLogo.width, logoMaxHeight / headerLogo.height);
          const width = headerLogo.width * scale;
          const height = headerLogo.height * scale;

          ctx.save();
          ctx.fillStyle = 'rgba(255,255,255,0.96)';
          ctx.beginPath();
          ctx.roundRect(panelX + 34, panelY + 24, width + 22, height + 18, 18);
          ctx.fill();
          ctx.drawImage(headerLogo, panelX + 45, panelY + 33, width, height);
          ctx.restore();
          tenantLabelX = panelX + 44 + width + 42;
        } catch {
          // ignore logo load failure
        }
      }

      const tenantLabel = (tenant?.name || 'Imobiliária').toUpperCase();
      ctx.fillStyle = secondaryColor;
      ctx.font = '700 26px sans-serif';
      ctx.fillText(tenantLabel, tenantLabelX, panelY + 66);

      if (tenantPhone) {
        ctx.fillStyle = 'rgba(255,255,255,0.84)';
        ctx.font = '500 24px sans-serif';
        ctx.fillText(tenantPhone, tenantLabelX, panelY + 102);
      }

      const infoMaxWidth = panelWidth - 88;
      const thumbSize = 176;
      const thumbGap = 18;
      const thumbsPerRow = 3;
      const maxThumbs = 6;
      const visibleThumbs = thumbUrls.slice(0, maxThumbs);
      const totalThumbsWidth = thumbSize * thumbsPerRow + thumbGap * (thumbsPerRow - 1);
      const thumbStartX = panelX + (panelWidth - totalThumbsWidth) / 2;
      const thumbStartY = panelY + panelHeight - thumbSize * 2 - thumbGap - 38;
      const textTopY = panelY + 150;
      const textBottomLimit = thumbStartY - 28;
      const titleText = getPropertyTitle(property);
      const locationText = getLocationText(property) || 'Localização sob consulta';
      const detailText = detailTags.join('   •   ');
      const priceText = formatCurrency(getPrice(property));
      const primaryText = primarySpecs.join('   •   ');
      const secondaryText = secondarySpecs.join('   •   ');

      let layoutScale = 1;
      let titleLines: string[] = [];
      let locationLines: string[] = [];
      let detailLines: string[] = [];
      let primaryLines: string[] = [];
      let secondaryLines: string[] = [];
      let titleFontSize = 74;
      let locationFontSize = 42;
      let detailFontSize = 31;
      let priceFontSize = 86;
      let primaryFontSize = 34;
      let secondaryFontSize = 30;
      let titleLineHeight = 84;
      let locationLineHeight = 46;
      let detailLineHeight = 36;
      let primaryLineHeight = 38;
      let secondaryLineHeight = 34;
      let titleBottomSpacing = 22;
      let detailTopSpacing = 18;
      let priceTopSpacing = 54;
      let primaryTopSpacing = 62;
      let secondaryTopSpacing = 16;

      while (layoutScale >= 0.72) {
        titleFontSize = Math.round(74 * layoutScale);
        locationFontSize = Math.round(42 * layoutScale);
        detailFontSize = Math.round(31 * layoutScale);
        primaryFontSize = Math.round(34 * layoutScale);
        secondaryFontSize = Math.round(30 * layoutScale);
        titleLineHeight = Math.round(84 * layoutScale);
        locationLineHeight = Math.round(46 * layoutScale);
        detailLineHeight = Math.round(36 * layoutScale);
        primaryLineHeight = Math.round(38 * layoutScale);
        secondaryLineHeight = Math.round(34 * layoutScale);
        titleBottomSpacing = Math.round(22 * layoutScale);
        detailTopSpacing = Math.round(18 * layoutScale);
        priceTopSpacing = Math.round(54 * layoutScale);
        primaryTopSpacing = Math.round(62 * layoutScale);
        secondaryTopSpacing = Math.round(16 * layoutScale);

        ctx.font = `700 ${titleFontSize}px sans-serif`;
        titleLines = wrapCanvasText(ctx, titleText, infoMaxWidth, 2);

        ctx.font = `500 ${locationFontSize}px sans-serif`;
        locationLines = wrapCanvasText(ctx, locationText, infoMaxWidth, 2);

        ctx.font = `600 ${detailFontSize}px sans-serif`;
        detailLines = detailText ? wrapCanvasText(ctx, detailText, infoMaxWidth, 2) : [];

        priceFontSize = fitCanvasFontSize(ctx, priceText, Math.round(86 * layoutScale), Math.round(60 * layoutScale), infoMaxWidth, '700');

        ctx.font = `600 ${primaryFontSize}px sans-serif`;
        primaryLines = primaryText ? wrapCanvasText(ctx, primaryText, infoMaxWidth, 2) : [];

        ctx.font = `500 ${secondaryFontSize}px sans-serif`;
        secondaryLines = secondaryText ? wrapCanvasText(ctx, secondaryText, infoMaxWidth, 2) : [];

        const totalHeight =
          measureTextBlockHeight(titleLines.length, titleLineHeight) +
          titleBottomSpacing +
          measureTextBlockHeight(locationLines.length, locationLineHeight) +
          (detailLines.length > 0 ? detailTopSpacing + measureTextBlockHeight(detailLines.length, detailLineHeight) : 0) +
          priceTopSpacing +
          priceFontSize +
          (primaryLines.length > 0 ? primaryTopSpacing + measureTextBlockHeight(primaryLines.length, primaryLineHeight) : 0) +
          (secondaryLines.length > 0 ? secondaryTopSpacing + measureTextBlockHeight(secondaryLines.length, secondaryLineHeight) : 0);

        if (textTopY + totalHeight <= textBottomLimit) {
          break;
        }

        layoutScale -= 0.04;
      }

      let currentY = textTopY;

      ctx.fillStyle = '#ffffff';
      ctx.font = `700 ${titleFontSize}px sans-serif`;
      titleLines.forEach((line, index) => {
        ctx.fillText(line, panelX + 44, currentY + index * titleLineHeight);
      });
      currentY += measureTextBlockHeight(titleLines.length, titleLineHeight) + titleBottomSpacing;

      ctx.fillStyle = 'rgba(255,255,255,0.78)';
      ctx.font = `500 ${locationFontSize}px sans-serif`;
      locationLines.forEach((line, index) => {
        ctx.fillText(line, panelX + 44, currentY + index * locationLineHeight);
      });
      currentY += measureTextBlockHeight(locationLines.length, locationLineHeight);

      if (detailLines.length > 0) {
        currentY += detailTopSpacing;
        ctx.fillStyle = 'rgba(255,255,255,0.74)';
        ctx.font = `600 ${detailFontSize}px sans-serif`;
        detailLines.forEach((line, index) => {
          ctx.fillText(line, panelX + 44, currentY + index * detailLineHeight);
        });
        currentY += measureTextBlockHeight(detailLines.length, detailLineHeight);
      }

      currentY += priceTopSpacing;
      ctx.fillStyle = '#ffffff';
      ctx.font = `700 ${priceFontSize}px sans-serif`;
      ctx.fillText(priceText, panelX + 44, currentY);
      currentY += priceFontSize;

      if (primaryLines.length > 0) {
        currentY += primaryTopSpacing;
        ctx.fillStyle = 'rgba(255,255,255,0.82)';
        ctx.font = `600 ${primaryFontSize}px sans-serif`;
        primaryLines.forEach((line, index) => {
          ctx.fillText(line, panelX + 44, currentY + index * primaryLineHeight);
        });
        currentY += measureTextBlockHeight(primaryLines.length, primaryLineHeight);
      }

      if (secondaryLines.length > 0) {
        currentY += secondaryTopSpacing;
        ctx.fillStyle = 'rgba(255,255,255,0.66)';
        ctx.font = `500 ${secondaryFontSize}px sans-serif`;
        secondaryLines.forEach((line, index) => {
          ctx.fillText(line, panelX + 44, currentY + index * secondaryLineHeight);
        });
      }

      if (visibleThumbs.length > 0) {
        for (let index = 0; index < visibleThumbs.length; index += 1) {
          const row = Math.floor(index / thumbsPerRow);
          const column = index % thumbsPerRow;
          const x = thumbStartX + column * (thumbSize + thumbGap);
          const y = thumbStartY + row * (thumbSize + thumbGap);

          ctx.save();
          ctx.shadowColor = 'rgba(15, 23, 42, 0.24)';
          ctx.shadowBlur = 20;
          ctx.shadowOffsetX = 0;
          ctx.shadowOffsetY = 10;
          ctx.fillStyle = 'rgba(255,255,255,0.10)';
          ctx.beginPath();
          ctx.roundRect(x, y, thumbSize, thumbSize, 26);
          ctx.fill();
          ctx.restore();

          const frameGradient = ctx.createLinearGradient(x, y, x + thumbSize, y + thumbSize);
          frameGradient.addColorStop(0, 'rgba(255,255,255,0.16)');
          frameGradient.addColorStop(1, 'rgba(255,255,255,0.03)');
          ctx.strokeStyle = frameGradient;
          ctx.lineWidth = 2;
          ctx.beginPath();
          ctx.roundRect(x + 1, y + 1, thumbSize - 2, thumbSize - 2, 25);
          ctx.stroke();

          try {
            const thumb = await loadImage(visibleThumbs[index], objectUrls);
            ctx.save();
            ctx.beginPath();
            ctx.roundRect(x, y, thumbSize, thumbSize, 26);
            ctx.clip();
            const scale = Math.max(thumbSize / thumb.width, thumbSize / thumb.height);
            const width = thumb.width * scale;
            const height = thumb.height * scale;
            ctx.drawImage(thumb, x + (thumbSize - width) / 2, y + (thumbSize - height) / 2, width, height);
            const thumbOverlay = ctx.createLinearGradient(x, y, x, y + thumbSize);
            thumbOverlay.addColorStop(0, 'rgba(255,255,255,0.07)');
            thumbOverlay.addColorStop(0.58, 'rgba(255,255,255,0.02)');
            thumbOverlay.addColorStop(1, 'rgba(15,23,42,0.10)');
            ctx.fillStyle = thumbOverlay;
            ctx.fillRect(x, y, thumbSize, thumbSize);
            ctx.restore();
          } catch {
            // ignore thumb load failure
          }
        }
      }

      return canvas.toDataURL('image/png');
    } finally {
      objectUrls.forEach((url) => URL.revokeObjectURL(url));
    }
  };

  const cleanupExportAssets = () => {
    exportObjectUrlsRef.current.forEach((url) => URL.revokeObjectURL(url));
    exportObjectUrlsRef.current = [];
  };

  const waitForNextPaint = async () => {
    await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
    await new Promise<void>((resolve) => requestAnimationFrame(() => resolve()));
  };

  const handleDownloadStory = async (property: Property) => {
    let restoreRemoteFonts = () => undefined;
    try {
      setDownloadingPropertyId(property.id);
      cleanupExportAssets();

      const objectUrls: string[] = [];
      const normalizedPhotos = normalizePhotos(property).slice(0, 7);
      const resolvedPhotos = await Promise.all(
        normalizedPhotos.map((photo) => resolveAssetUrl(photo, objectUrls)),
      );
      const tenantLogoSrc = tenant?.logo_url || tenant?.logo;
      const resolvedLogoSrc = await resolveAssetUrl(tenantLogoSrc, objectUrls);

      exportObjectUrlsRef.current = objectUrls;
      setExportStory({
        property,
        photos: resolvedPhotos.filter(Boolean),
        logoSrc: resolvedLogoSrc || tenantLogoSrc || '',
      });

      await waitForNextPaint();

      if (!exportStoryRef.current) {
        throw new Error('Prévia de exportação não disponível');
      }

      restoreRemoteFonts = disableRemoteFontStylesheets();

      const dataUrl = await toPng(exportStoryRef.current, {
        cacheBust: true,
        pixelRatio: 4,
        backgroundColor: '#0a1320',
        fontEmbedCSS: '',
      });
      const link = document.createElement('a');
      const safeTitle = getPropertyTitle(property)
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

      link.href = dataUrl;
      link.download = `status-imovel-${safeTitle || property.id}.png`;
      link.click();
      toast.success('Imagem pronta para download');
    } catch (error) {
      console.error('Erro ao gerar imagem da propaganda:', error);
      toast.error('Não foi possível gerar a imagem');
    } finally {
      restoreRemoteFonts();
      setExportStory(null);
      cleanupExportAssets();
      setDownloadingPropertyId(null);
    }
  };

  const filteredProperties = properties.filter((prop) =>
    getPropertyTitle(prop).toLowerCase().includes(searchTerm.toLowerCase()) ||
    getNeighborhood(prop).toLowerCase().includes(searchTerm.toLowerCase()) ||
    getCity(prop).toLowerCase().includes(searchTerm.toLowerCase()),
  );

  return (
    <div className="flex">
      <Sidebar />
      <div className="page-shell">
        <motion.div
          initial={{ opacity: 0, y: 16 }}
          animate={{ opacity: 1, y: 0 }}
          className="mx-auto max-w-7xl"
        >
          <div className="page-header mb-8">
            <div>
              <h1 className="page-title mb-2">Propaganda de Imóveis</h1>
              <p className="page-subtitle">Gere um status vertical por imóvel com texto, logo e fotos prontas para baixar</p>
            </div>
          </div>

          <div className="grid grid-cols-1 gap-4 mb-8 md:grid-cols-4">
            {[
              { label: 'Imóveis prontos', value: properties.length },
              { label: 'Com logo do tenant', value: tenant ? properties.length : 0 },
              { label: 'Com foto principal', value: properties.filter((item) => normalizePhotos(item).length > 0).length },
              { label: 'Formato status', value: '1080x1920' },
            ].map((stat) => (
              <motion.div
                key={stat.label}
                initial={{ opacity: 0, scale: 0.96 }}
                animate={{ opacity: 1, scale: 1 }}
                className="glass-panel rounded-2xl p-4 text-center"
              >
                <p className="mb-1 text-sm font-medium text-muted-foreground">{stat.label}</p>
                <p className="text-2xl font-bold text-foreground">{typeof stat.value === 'number' ? stat.value.toLocaleString() : stat.value}</p>
              </motion.div>
            ))}
          </div>

          <div className="glass-panel mb-6 rounded-2xl p-6">
            <div className="relative">
              <Search className="absolute left-4 top-1/2 -translate-y-1/2 text-muted-foreground" size={20} />
              <input
                type="text"
                placeholder="Buscar por imóvel, bairro ou cidade..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="w-full rounded-lg border border-white/20 bg-white/10 py-3 pl-12 pr-4 text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-sky-500"
              />
            </div>
          </div>

          {isLoading ? (
            <div className="flex justify-center py-20">
              <Loader2 className="h-12 w-12 animate-spin text-sky-500" />
            </div>
          ) : filteredProperties.length === 0 ? (
            <div className="glass-panel rounded-2xl p-12 text-center">
              <Image size={64} className="mx-auto mb-4 text-muted-foreground opacity-50" />
              <h3 className="mb-2 text-xl font-semibold text-foreground">Nenhum imóvel encontrado</h3>
              <p className="text-muted-foreground">Cadastre imóveis para começar a criar propagandas</p>
            </div>
          ) : (
            <div className="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
              {filteredProperties.map((property) => {
                const photos = normalizePhotos(property);

                return (
                  <motion.div
                    key={property.id}
                    initial={{ opacity: 0, scale: 0.95 }}
                    animate={{ opacity: 1, scale: 1 }}
                    className="glass-panel overflow-hidden rounded-[28px] transition-all hover:bg-white/10"
                  >
                    <div className="p-4">
                      <StoryPreviewCard property={property} tenant={tenant} photos={photos} />

                      <button
                        onClick={() => handleDownloadStory(property)}
                        disabled={downloadingPropertyId === property.id}
                        className="mt-4 flex w-full items-center justify-center gap-2 rounded-xl border border-white/12 bg-white/8 px-4 py-3 text-sm font-semibold text-white transition-colors hover:bg-white/12 disabled:cursor-not-allowed disabled:opacity-70"
                      >
                        {downloadingPropertyId === property.id ? <Loader2 size={16} className="animate-spin" /> : <Download size={16} />}
                        {downloadingPropertyId === property.id ? 'Gerando...' : 'Baixar'}
                      </button>
                    </div>
                  </motion.div>
                );
              })}
            </div>
          )}
        </motion.div>
      </div>
      {exportStory && (
        <div className="pointer-events-none fixed -left-[9999px] top-0 opacity-100">
          <StoryPreviewCard
            storyRef={exportStoryRef}
            property={exportStory.property}
            tenant={exportStory.logoSrc ? { ...tenant, logo_url: exportStory.logoSrc, logo: exportStory.logoSrc } : tenant}
            photos={exportStory.photos}
            className="relative aspect-[9/16] w-[330px] overflow-hidden rounded-[30px] border border-white/15 bg-[#0a1320] [font-family:Arial,sans-serif]"
          />
        </div>
      )}
    </div>
  );
}
