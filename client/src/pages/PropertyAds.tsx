import { useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import { Image, Search, Loader2, Sparkles, Download } from 'lucide-react';
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

export default function PropertyAds() {
  const [properties, setProperties] = useState<Property[]>([]);
  const [tenant, setTenant] = useState<TenantBranding | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState('');
  const [downloadingPropertyId, setDownloadingPropertyId] = useState<number | null>(null);

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
    const cardX = 40;
    const cardY = 40;
    const cardWidth = STORY_WIDTH - 80;
    const cardHeight = STORY_HEIGHT - 80;
    const cardRadius = 78;
    const panelX = cardX + 48;
    const panelY = cardY + cardHeight - 720;
    const panelWidth = cardWidth - 96;
    const panelHeight = 640;
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

      ctx.fillStyle = 'rgba(7,17,29,0.88)';
      ctx.beginPath();
      ctx.roundRect(panelX, panelY, panelWidth, panelHeight, panelRadius);
      ctx.fill();

      const tenantLabel = (tenant?.name || 'Imobiliária').toUpperCase();
      ctx.fillStyle = secondaryColor;
      ctx.font = '700 26px sans-serif';
      ctx.fillText(tenantLabel, panelX + 44, panelY + 66);

      const photosLabel = `${photos.length} foto${photos.length === 1 ? '' : 's'}`;
      ctx.font = '600 24px sans-serif';
      const photosBadgeWidth = ctx.measureText(photosLabel).width + 40;
      drawPill(panelX + panelWidth - photosBadgeWidth - 36, panelY + 28, photosLabel, {
        fill: 'rgba(255,255,255,0.10)',
        textColor: 'rgba(255,255,255,0.78)',
        font: '600 24px sans-serif',
        horizontalPadding: 20,
        height: 58,
      });

      ctx.fillStyle = '#ffffff';
      ctx.font = '700 74px sans-serif';
      const titleLines = splitLines(getPropertyTitle(property), 22, 2);
      titleLines.forEach((line, index) => {
        ctx.fillText(line, panelX + 44, panelY + 150 + index * 84);
      });

      const locationY = panelY + 150 + titleLines.length * 84 + 22;
      ctx.fillStyle = 'rgba(255,255,255,0.78)';
      ctx.font = '500 38px sans-serif';
      ctx.fillText(getLocationText(property) || 'Localização sob consulta', panelX + 44, locationY);

      if (detailTags.length > 0) {
        ctx.fillStyle = 'rgba(255,255,255,0.74)';
        ctx.font = '600 28px sans-serif';
        ctx.fillText(detailTags.join('   •   '), panelX + 44, locationY + 54);
      }

      const priceY = locationY + 140;
      ctx.fillStyle = '#ffffff';
      ctx.font = '700 82px sans-serif';
      ctx.fillText(formatCurrency(getPrice(property)), panelX + 44, priceY);

      if (primarySpecs.length > 0) {
        ctx.fillStyle = 'rgba(255,255,255,0.82)';
        ctx.font = '600 31px sans-serif';
        ctx.fillText(primarySpecs.join('   •   '), panelX + 44, priceY + 72);
      }

      if (secondarySpecs.length > 0) {
        ctx.fillStyle = 'rgba(255,255,255,0.66)';
        ctx.font = '500 27px sans-serif';
        ctx.fillText(secondarySpecs.join('   •   '), panelX + 44, priceY + 122);
      }

      if (thumbUrls.length > 0) {
        const thumbSize = 108;
        const thumbGap = 16;
        const maxThumbs = 6;
        const totalThumbsWidth = thumbSize * Math.min(thumbUrls.length, maxThumbs) + thumbGap * (Math.min(thumbUrls.length, maxThumbs) - 1);
        let thumbX = panelX + 44;
        const thumbY = panelY + panelHeight - thumbSize - 38;

        if (totalThumbsWidth < panelWidth - 88) {
          thumbX = panelX + (panelWidth - totalThumbsWidth) / 2;
        }

        for (let index = 0; index < Math.min(thumbUrls.length, maxThumbs); index += 1) {
          const x = thumbX + index * (thumbSize + thumbGap);

          ctx.fillStyle = 'rgba(255,255,255,0.12)';
          ctx.beginPath();
          ctx.roundRect(x, thumbY, thumbSize, thumbSize, 24);
          ctx.fill();

          try {
            const thumb = await loadImage(thumbUrls[index], objectUrls);
            ctx.save();
            ctx.beginPath();
            ctx.roundRect(x, thumbY, thumbSize, thumbSize, 24);
            ctx.clip();
            const scale = Math.max(thumbSize / thumb.width, thumbSize / thumb.height);
            const width = thumb.width * scale;
            const height = thumb.height * scale;
            ctx.drawImage(thumb, x + (thumbSize - width) / 2, thumbY + (thumbSize - height) / 2, width, height);
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

  const handleDownloadStory = async (property: Property) => {
    try {
      setDownloadingPropertyId(property.id);
      const dataUrl = await createStoryImage(property);
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
      setDownloadingPropertyId(null);
    }
  };

  const filteredProperties = properties.filter((prop) =>
    getPropertyTitle(prop).toLowerCase().includes(searchTerm.toLowerCase()) ||
    getNeighborhood(prop).toLowerCase().includes(searchTerm.toLowerCase()) ||
    getCity(prop).toLowerCase().includes(searchTerm.toLowerCase()),
  );

  const getTransactionBadge = (type: string) => (
    type === 'venda'
      ? 'bg-emerald-500/20 text-emerald-200 border-emerald-400/40'
      : 'bg-sky-500/20 text-sky-200 border-sky-400/40'
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
                const thumbPhotos = photos.slice(1, 7);
                const transactionType = getTransactionType(property);
                const detailTags = getDetailTags(property);
                const primarySpecs = getPrimarySpecs(property);
                const secondarySpecs = getSecondarySpecs(property);

                return (
                  <motion.div
                    key={property.id}
                    initial={{ opacity: 0, scale: 0.95 }}
                    animate={{ opacity: 1, scale: 1 }}
                    className="glass-panel overflow-hidden rounded-[28px] transition-all hover:bg-white/10"
                  >
                    <div className="p-4">
                      <div className="relative mx-auto aspect-[9/16] w-full max-w-[330px] overflow-hidden rounded-[30px] border border-white/15 bg-[#0a1320]">
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

                        <div className="absolute inset-x-4 bottom-4 rounded-[26px] bg-[#07111d]/88 p-4 backdrop-blur-sm">
                          <div className="mb-2 flex items-center justify-between gap-3">
                            <p className="text-[10px] uppercase tracking-[0.28em] text-amber-300">
                              {tenant?.name || 'Tenant'}
                            </p>
                            <span className="rounded-full bg-white/10 px-2.5 py-1 text-[10px] text-white/75">
                              {photos.length} foto{photos.length === 1 ? '' : 's'}
                            </span>
                          </div>
                          <h3 className="line-clamp-2 text-xl font-bold leading-tight text-white">
                            {getPropertyTitle(property)}
                          </h3>
                          <p className="mt-2 line-clamp-2 text-xs text-white/72">
                            {getLocationText(property) || 'Localização sob consulta'}
                          </p>
                          {detailTags.length > 0 && (
                            <div className="mt-2 flex flex-wrap gap-1.5 text-[10px] text-white/62">
                              {detailTags.map((tag) => (
                                <span key={tag} className="rounded-full bg-white/8 px-2 py-1">
                                  {tag}
                                </span>
                              ))}
                            </div>
                          )}
                          <p className="mt-3 text-2xl font-bold text-white">
                            {formatCurrency(getPrice(property))}
                          </p>
                          {primarySpecs.length > 0 && (
                            <div className="mt-2 flex flex-wrap gap-2 text-[11px] text-white/78">
                              {primarySpecs.map((spec) => <span key={spec}>{spec}</span>)}
                            </div>
                          )}
                          {secondarySpecs.length > 0 && (
                            <div className="mt-1 flex flex-wrap gap-2 text-[10px] text-white/58">
                              {secondarySpecs.map((spec) => <span key={spec}>{spec}</span>)}
                            </div>
                          )}
                          {thumbPhotos.length > 0 && (
                            <div className="mt-3 grid grid-cols-6 gap-1.5">
                              {thumbPhotos.map((photo, index) => (
                                <div key={`${property.id}-${index}`} className="overflow-hidden rounded-xl bg-white/10 aspect-square">
                                  <img src={photo} alt={`${getPropertyTitle(property)} ${index + 2}`} className="h-full w-full object-cover" />
                                </div>
                              ))}
                            </div>
                          )}
                        </div>
                      </div>

                      <div className="mt-4">
                        <p className="text-sm font-semibold text-foreground">Prévia da arte</p>
                        <p className="mt-1 text-xs text-muted-foreground">
                          Abra para gerar o texto e baixar a imagem final com logo e fotos do imóvel.
                        </p>
                      </div>

                      <motion.button
                        whileHover={{ scale: 1.02 }}
                        whileTap={{ scale: 0.98 }}
                        onClick={() => handleDownloadStory(property)}
                        disabled={downloadingPropertyId === property.id}
                        className="mt-4 flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-sky-500 to-amber-500 px-4 py-3 font-semibold text-slate-950"
                      >
                        {downloadingPropertyId === property.id ? <Loader2 size={16} className="animate-spin" /> : <Download size={16} />}
                        {downloadingPropertyId === property.id ? 'Gerando imagem...' : 'Baixar imagem'}
                      </motion.button>
                    </div>
                  </motion.div>
                );
              })}
            </div>
          )}
        </motion.div>
      </div>
    </div>
  );
}
