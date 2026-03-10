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
  bathrooms?: number;
  banheiros?: number;
  area?: number;
  area_total?: number;
  price?: number;
  valor_venda?: number;
  photos?: string[];
  imagens?: string[];
  imagem_destaque?: string;
  description?: string;
  descricao?: string;
}

const STORY_WIDTH = 1080;
const STORY_HEIGHT = 1920;

const loadImage = (src: string) =>
  new Promise<HTMLImageElement>((resolve, reject) => {
    const image = new window.Image();
    image.crossOrigin = 'anonymous';
    image.onload = () => resolve(image);
    image.onerror = reject;
    image.src = src;
  });

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
const getBathrooms = (property: Property) => property.bathrooms || property.banheiros || 0;
const getArea = (property: Property) => property.area || property.area_total || 0;
const getPrice = (property: Property) => property.price || property.valor_venda || 0;

const formatCurrency = (value: number) =>
  value > 0 ? `R$ ${value.toLocaleString('pt-BR')}` : 'Consulte valor';

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

  const generateFallbackText = (property: Property) => {
    const parts = [];
    const transactionType = getTransactionType(property);

    if (transactionType === 'venda') {
      parts.push(`${getPropertyType(property)} à venda`);
    } else if (transactionType === 'aluguel') {
      parts.push(`${getPropertyType(property)} para alugar`);
    } else {
      parts.push(getPropertyType(property));
    }

    const location = getLocationText(property);
    if (location) parts.push(`em ${location}`);
    if (getBedrooms(property) > 0) parts.push(`${getBedrooms(property)} quartos`);
    if (getBathrooms(property) > 0) parts.push(`${getBathrooms(property)} banheiros`);
    if (getArea(property) > 0) parts.push(`${getArea(property)}m²`);
    parts.push('Fale conosco para agendar uma visita.');

    return parts.join(' | ').substring(0, 400);
  };

  const fetchAdText = async (property: Property) => {
    try {
      const response = await api.post(`/properties/${property.id}/generate-ad-description`);

      if (response.data.success) {
        return response.data.description as string;
      }

      throw new Error(response.data.error || 'Erro ao gerar texto');
    } catch (error: any) {
      console.error('Erro ao gerar propaganda:', error);
      const errorMsg = error.response?.data?.error || 'Erro ao gerar texto com IA';
      toast.error(errorMsg);

      return generateFallbackText(property);
    }
  };

  const createStoryImage = async (property: Property, generatedText: string) => {
    const canvas = document.createElement('canvas');
    canvas.width = STORY_WIDTH;
    canvas.height = STORY_HEIGHT;
    const ctx = canvas.getContext('2d');

    if (!ctx) {
      throw new Error('Canvas não disponível');
    }

    const photos = normalizePhotos(property);
    const mainPhotoUrl = photos[0];
    const thumbUrls = photos.slice(1, 4);
    const primaryColor = tenant?.primary_color || '#0f172a';
    const secondaryColor = tenant?.secondary_color || '#d4a34f';

    ctx.fillStyle = '#09111f';
    ctx.fillRect(0, 0, STORY_WIDTH, STORY_HEIGHT);

    if (mainPhotoUrl) {
      try {
        const mainImage = await loadImage(mainPhotoUrl);
        const scale = Math.max(STORY_WIDTH / mainImage.width, 980 / mainImage.height);
        const drawWidth = mainImage.width * scale;
        const drawHeight = mainImage.height * scale;
        const drawX = (STORY_WIDTH - drawWidth) / 2;
        const drawY = 0;
        ctx.drawImage(mainImage, drawX, drawY, drawWidth, drawHeight);
      } catch {
        ctx.fillStyle = '#1e293b';
        ctx.fillRect(0, 0, STORY_WIDTH, 980);
      }
    } else {
      const topGradient = ctx.createLinearGradient(0, 0, STORY_WIDTH, 980);
      topGradient.addColorStop(0, primaryColor);
      topGradient.addColorStop(1, '#0b1220');
      ctx.fillStyle = topGradient;
      ctx.fillRect(0, 0, STORY_WIDTH, 980);
    }

    const overlay = ctx.createLinearGradient(0, 0, 0, STORY_HEIGHT);
    overlay.addColorStop(0, 'rgba(0,0,0,0.18)');
    overlay.addColorStop(0.45, 'rgba(0,0,0,0.38)');
    overlay.addColorStop(0.7, 'rgba(7,12,21,0.88)');
    overlay.addColorStop(1, 'rgba(7,12,21,1)');
    ctx.fillStyle = overlay;
    ctx.fillRect(0, 0, STORY_WIDTH, STORY_HEIGHT);

    ctx.fillStyle = 'rgba(255,255,255,0.10)';
    ctx.fillRect(72, 70, STORY_WIDTH - 144, 2);

    ctx.fillStyle = 'rgba(8,15,26,0.88)';
    ctx.fillRect(72, 1060, STORY_WIDTH - 144, 690);

    ctx.fillStyle = secondaryColor;
    ctx.fillRect(72, 1060, 12, 690);

    ctx.fillStyle = 'rgba(255,255,255,0.08)';
    ctx.fillRect(120, 1365, STORY_WIDTH - 240, 2);

    const logoUrl = tenant?.logo_url || tenant?.logo;
    if (logoUrl) {
      try {
        const logo = await loadImage(logoUrl);
        const logoMaxWidth = 220;
        const logoMaxHeight = 88;
        const scale = Math.min(logoMaxWidth / logo.width, logoMaxHeight / logo.height);
        const width = logo.width * scale;
        const height = logo.height * scale;
        ctx.fillStyle = 'rgba(255,255,255,0.96)';
        ctx.beginPath();
        ctx.roundRect(72, 96, 248, 116, 28);
        ctx.fill();
        ctx.drawImage(logo, 86, 110, width, height);
      } catch {
        // ignore logo load failure
      }
    }

    ctx.fillStyle = 'rgba(255,255,255,0.72)';
    ctx.font = '600 28px sans-serif';
    ctx.fillText((tenant?.name || 'Imobiliária').toUpperCase(), 360, 150);

    ctx.fillStyle = secondaryColor;
    ctx.font = '700 36px sans-serif';
    ctx.fillText(getTransactionType(property) === 'aluguel' ? 'PARA ALUGAR' : 'IMÓVEL EM DESTAQUE', 120, 1140);

    ctx.fillStyle = '#ffffff';
    ctx.font = '700 68px sans-serif';
    const titleLines = splitLines(getPropertyTitle(property), 22, 2);
    titleLines.forEach((line, index) => {
      ctx.fillText(line, 120, 1220 + index * 78);
    });

    ctx.fillStyle = 'rgba(255,255,255,0.78)';
    ctx.font = '500 36px sans-serif';
    ctx.fillText(getLocationText(property) || 'Localização sob consulta', 120, 1380);

    ctx.fillStyle = '#ffffff';
    ctx.font = '700 66px sans-serif';
    ctx.fillText(formatCurrency(getPrice(property)), 120, 1480);

    ctx.fillStyle = 'rgba(255,255,255,0.9)';
    ctx.font = '600 34px sans-serif';
    const specs = [
      getBedrooms(property) > 0 ? `${getBedrooms(property)} quartos` : null,
      getBathrooms(property) > 0 ? `${getBathrooms(property)} banheiros` : null,
      getArea(property) > 0 ? `${getArea(property)}m²` : null,
    ].filter(Boolean).join('  •  ');
    if (specs) {
      ctx.fillText(specs, 120, 1545);
    }

    ctx.fillStyle = 'rgba(255,255,255,0.92)';
    ctx.font = '500 34px sans-serif';
    const textLines = splitLines(generatedText, 44, 4);
    textLines.forEach((line, index) => {
      ctx.fillText(line, 120, 1635 + index * 48);
    });

    ctx.fillStyle = secondaryColor;
    ctx.beginPath();
    ctx.roundRect(120, 1780, 420, 84, 42);
    ctx.fill();
    ctx.fillStyle = '#08111d';
    ctx.font = '700 32px sans-serif';
    ctx.fillText('BAIXE E PUBLIQUE', 180, 1835);

    if (thumbUrls.length > 0) {
      for (let index = 0; index < thumbUrls.length; index += 1) {
        const x = 650 + index * 118;
        const y = 1766;
        ctx.fillStyle = 'rgba(255,255,255,0.18)';
        ctx.beginPath();
        ctx.roundRect(x, y, 96, 96, 24);
        ctx.fill();

        try {
          const thumb = await loadImage(thumbUrls[index]);
          ctx.save();
          ctx.beginPath();
          ctx.roundRect(x, y, 96, 96, 24);
          ctx.clip();
          const scale = Math.max(96 / thumb.width, 96 / thumb.height);
          const width = thumb.width * scale;
          const height = thumb.height * scale;
          ctx.drawImage(thumb, x + (96 - width) / 2, y + (96 - height) / 2, width, height);
          ctx.restore();
        } catch {
          // ignore thumb load failure
        }
      }
    }

    ctx.fillStyle = 'rgba(255,255,255,0.6)';
    ctx.font = '500 24px sans-serif';
    ctx.fillText(tenant?.contact_phone || 'Entre em contato para mais informações', 120, 1888);

    return canvas.toDataURL('image/png');
  };

  const handleDownloadStory = async (property: Property) => {
    try {
      setDownloadingPropertyId(property.id);
      const generatedText = await fetchAdText(property);
      const dataUrl = await createStoryImage(property, generatedText);
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
                const transactionType = getTransactionType(property);

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

                        <div className="absolute inset-x-4 top-4 flex items-center justify-between">
                          <span className={`rounded-full border px-3 py-1 text-[11px] font-semibold ${getTransactionBadge(transactionType)}`}>
                            {transactionType === 'aluguel' ? 'Aluguel' : 'Venda'}
                          </span>
                          <span className="rounded-full bg-black/45 px-2.5 py-1 text-[11px] text-white/85">
                            Story 9:16
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
                          <p className="mt-3 text-2xl font-bold text-white">
                            {formatCurrency(getPrice(property))}
                          </p>
                          <div className="mt-2 flex flex-wrap gap-2 text-[11px] text-white/78">
                            {getBedrooms(property) > 0 && <span>{getBedrooms(property)} quartos</span>}
                            {getBathrooms(property) > 0 && <span>{getBathrooms(property)} banheiros</span>}
                            {getArea(property) > 0 && <span>{getArea(property)}m²</span>}
                          </div>
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
