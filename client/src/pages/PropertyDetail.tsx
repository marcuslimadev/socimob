import { useEffect, useMemo, useState } from 'react';
import { motion } from 'framer-motion';
import { useLocation, useRoute } from 'wouter';
import {
  ArrowLeft,
  ArrowUpRight,
  Bath,
  BedDouble,
  Car,
  Eye,
  Heart,
  ImageIcon,
  MapPin,
  MessageCircle,
  Share2,
  Square,
} from 'lucide-react';
import { toast } from 'sonner';
import api from '@/lib/api';
import { fetchTenantBranding, TenantBranding } from '@/lib/tenantBranding';

interface Property {
  id: number;
  titulo: string;
  tipo_negocio?: string;
  tipo_imovel: string;
  valor_venda?: number;
  valor_aluguel?: number;
  preco?: string;
  cidade: string;
  bairro: string;
  estado: string;
  quartos?: number;
  dormitorios?: number;
  banheiros?: number;
  suites?: number;
  vagas_garagem?: number;
  area_total?: number;
  area_util?: number;
  area_privativa?: number;
  descricao?: string;
  destaque?: boolean;
  active?: boolean;
  fotos?: Array<{ url: string; destaque: boolean }>;
  imagens?: string[];
  imagem_destaque?: string;
  finalidade_imovel?: string;
  codigo?: string;
  condominio?: number;
  iptu?: number;
}

function normalizeImages(propertyData: Property): Array<{ url: string; destaque: boolean }> {
  const photos: Array<{ url: string; destaque: boolean }> = [];

  if (propertyData.imagem_destaque) {
    photos.push({ url: propertyData.imagem_destaque, destaque: true });
  }

  if (propertyData.fotos?.length) {
    for (const foto of propertyData.fotos) {
      if (!photos.some((item) => item.url === foto.url)) {
        photos.push(foto);
      }
    }
  }

  if (propertyData.imagens?.length) {
    for (const image of propertyData.imagens) {
      if (!photos.some((item) => item.url === image)) {
        photos.push({ url: image, destaque: false });
      }
    }
  }

  return photos;
}

function formatPrice(property: Property): number {
  const fromString = property.preco ? Number(property.preco.replace(/[^\d]/g, '')) : 0;
  return property.valor_venda || property.valor_aluguel || fromString || 0;
}

function formatDescription(raw: string): string {
  const textarea = document.createElement('textarea');
  textarea.innerHTML = raw;
  let text = textarea.value;

  text = text.replace(/<br\s*\/?>/gi, '\n');
  text = text.replace(/<[^>]+>/g, '');
  text = text.replace(/\u00A0/g, ' ');

  const lines = text
    .split('\n')
    .map((line) => line.trim())
    .filter((line) => line.length > 0);

  const htmlParts: string[] = [];
  let currentList: string[] = [];

  const flushList = () => {
    if (currentList.length === 0) return;
    htmlParts.push(
      '<ul class="list-disc list-inside space-y-1 my-3">' +
        currentList.map((item) => `<li>${item}</li>`).join('') +
      '</ul>',
    );
    currentList = [];
  };

  for (const line of lines) {
    if (/^\*\s+/.test(line) || /^-\s+/.test(line)) {
      currentList.push(line.replace(/^[\*\-]\s+/, ''));
      continue;
    }

    flushList();
    htmlParts.push(`<p class="mb-2">${line}</p>`);
  }

  flushList();
  return htmlParts.join('');
}

function businessLabel(property: Property): string {
  const value = `${property.tipo_negocio || ''} ${property.finalidade_imovel || ''}`.toLowerCase();
  if (value.includes('alug')) return 'Aluguel';
  if (value.includes('vend')) return 'Venda';
  return property.tipo_negocio || 'Imovel';
}

export default function PropertyDetail() {
  const [, params] = useRoute('/portal/imovel/:id');
  const [, navigate] = useLocation();
  const [property, setProperty] = useState<Property | null>(null);
  const [tenant, setTenant] = useState<TenantBranding | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedImage, setSelectedImage] = useState<string | null>(null);
  const [isLiked, setIsLiked] = useState(false);

  const primary = tenant?.primary_color || '#0f172a';
  const secondary = tenant?.secondary_color || '#c39a66';

  useEffect(() => {
    const fetchData = async () => {
      if (!params?.id) return;

      try {
        setLoading(true);
        setError(null);
        const [propertyResp, tenantResp] = await Promise.all([
          api.get(`/portal/imoveis/${params.id}`),
          fetchTenantBranding(),
        ]);

        const propertyData = propertyResp.data?.data || propertyResp.data;
        const images = normalizeImages(propertyData);
        setProperty(propertyData);
        setSelectedImage(images[0]?.url || null);
        setTenant(tenantResp);
      } catch (err: any) {
        setError(err?.message || 'Erro ao carregar detalhes do imovel');
      } finally {
        setLoading(false);
      }
    };

    fetchData();
  }, [params?.id]);

  const handleLike = async () => {
    if (!property) return;
    try {
      const response = await api.post(`/portal/likes/${property.id}`);
      if (response.data?.success) {
        setIsLiked(true);
        toast.success('Imovel adicionado aos favoritos');
      }
    } catch (err: any) {
      if (err?.response?.status === 401) {
        toast.error('Faça login para favoritar imóveis');
      } else {
        toast.error('Erro ao adicionar aos favoritos');
      }
    }
  };

  const handleShare = async () => {
    if (!property) return;

    const shareUrl = window.location.href;
    const shareText = `Confira este imóvel: ${property.titulo}`;

    if (navigator.share) {
      try {
        await navigator.share({
          title: property.titulo,
          text: shareText,
          url: shareUrl,
        });
        return;
      } catch {
        // user canceled
      }
    }

    try {
      await navigator.clipboard.writeText(shareUrl);
      toast.success('Link copiado para a área de transferência');
    } catch {
      toast.error('Erro ao copiar link');
    }
  };

  const handleWhatsApp = () => {
    if (!property || !tenant?.contact_phone) return;

    const phone = tenant.contact_phone.replace(/\D/g, '');
    const price = formatPrice(property);
    const location = [property.bairro, property.cidade].filter(Boolean).join(', ') || 'Não informado';
    const message = encodeURIComponent(
      `Olá! Tenho interesse no imóvel:\n\n` +
      `Título: ${property.titulo}\n` +
      `Localização: ${location}\n` +
      `Valor: R$ ${price.toLocaleString('pt-BR')}\n` +
      `Link: ${window.location.href}\n\n` +
      `Gostaria de mais informações.`,
    );

    window.open(`https://wa.me/${phone}?text=${message}`, '_blank');
  };

  if (loading) {
    return (
      <div className="min-h-screen flex items-center justify-center" style={{ backgroundColor: '#0f172a' }}>
        <motion.div
          className="w-12 h-12 rounded-full border-4 border-white/20"
          style={{ borderTopColor: secondary }}
          animate={{ rotate: 360 }}
          transition={{ duration: 1.2, repeat: Infinity, ease: 'linear' }}
        />
      </div>
    );
  }

  if (error || !property) {
    return (
      <div className="min-h-screen flex items-center justify-center p-4" style={{ backgroundColor: '#f4efe8' }}>
        <div className="rounded-2xl border border-black/10 bg-white p-8 text-center max-w-md shadow-[0_12px_32px_rgba(15,23,42,0.10)]">
          <p className="text-red-600 mb-4">❌ {error || 'Imovel não encontrado'}</p>
          <button
            type="button"
            onClick={() => navigate('/portal')}
            className="px-4 py-2 rounded-lg text-white"
            style={{ backgroundColor: primary }}
          >
            Voltar ao portal
          </button>
        </div>
      </div>
    );
  }

  const images = normalizeImages(property);
  const displayImage = selectedImage || images[0]?.url || null;
  const price = formatPrice(property);
  const bedrooms = property.quartos ?? property.dormitorios;
  const area = property.area_util || property.area_privativa || property.area_total;
  const locationParts = [property.bairro, property.cidade, property.estado].filter(Boolean);

  return (
    <div className="min-h-screen" style={{ backgroundColor: '#f4efe8' }}>
      <header className="sticky top-0 z-40 border-b border-white/10 bg-[#0b111f]/92 backdrop-blur-xl">
        <div className="mx-auto max-w-7xl px-4 lg:px-8 py-3 flex items-center justify-between">
          <button
            type="button"
            onClick={() => navigate('/portal')}
            className="inline-flex items-center gap-2 text-sm text-white/85 hover:text-white transition-colors"
          >
            <ArrowLeft size={18} />
            Voltar aos imóveis
          </button>
          <div className="text-[11px] uppercase tracking-[0.16em] text-white/70">
            {tenant?.name || 'Imobiliaria'}
          </div>
        </div>
      </header>

      <section className="relative overflow-hidden" style={{ background: `linear-gradient(115deg, ${primary}f0 0%, #0a0d16 100%)` }}>
        {displayImage && (
          <img src={displayImage} alt={property.titulo} className="absolute inset-0 w-full h-full object-cover opacity-25" />
        )}
        <div className="absolute inset-0 bg-[radial-gradient(circle_at_20%_15%,rgba(255,255,255,0.16),transparent_34%)]" />

        <div className="relative mx-auto max-w-7xl px-4 lg:px-8 py-10 lg:py-14">
          <p className="inline-flex rounded-full border border-white/25 bg-white/10 px-3 py-1 text-[11px] uppercase tracking-[0.14em] text-white/85">
            {businessLabel(property)}
          </p>
          <h1 className="mt-4 text-3xl md:text-5xl leading-tight text-white max-w-4xl">{property.titulo}</h1>
          <p className="mt-3 text-white/75 flex items-center gap-2">
            <MapPin size={16} />
            {locationParts.length > 0 ? locationParts.join(', ') : 'Localização não informada'}
          </p>
        </div>
      </section>

      <section className="mx-auto max-w-7xl px-4 lg:px-8 py-8">
        <div className="grid grid-cols-1 xl:grid-cols-[1.1fr_0.9fr] gap-8">
          <div className="space-y-5">
            <motion.article
              initial={{ opacity: 0, y: 14 }}
              animate={{ opacity: 1, y: 0 }}
              className="overflow-hidden rounded-[28px] border border-black/10 bg-white shadow-[0_14px_40px_rgba(15,23,42,0.10)]"
            >
              <div className="relative h-[300px] sm:h-[420px] bg-slate-100">
                {displayImage ? (
                  <img src={displayImage} alt={property.titulo} className="w-full h-full object-cover" />
                ) : (
                  <div className="w-full h-full flex flex-col items-center justify-center text-slate-500">
                    <ImageIcon size={36} />
                    <p className="mt-2 text-sm">Sem imagens disponíveis</p>
                  </div>
                )}

                {property.destaque && (
                  <span className="absolute top-4 right-4 rounded-full bg-[#111827]/78 border border-white/30 px-3 py-1 text-[11px] uppercase tracking-[0.12em] text-white">
                    Destaque
                  </span>
                )}
              </div>
            </motion.article>

            {images.length > 0 && (
              <motion.div
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                transition={{ delay: 0.15 }}
                className="grid grid-cols-3 sm:grid-cols-5 gap-2"
              >
                {images.slice(0, 10).map((foto, index) => (
                  <button
                    key={foto.url + index}
                    type="button"
                    onClick={() => setSelectedImage(foto.url)}
                    className={`relative h-20 sm:h-24 rounded-xl overflow-hidden border transition ${
                      selectedImage === foto.url
                        ? 'border-slate-800'
                        : 'border-black/10 opacity-80 hover:opacity-100'
                    }`}
                  >
                    <img src={foto.url} alt={`Foto ${index + 1}`} className="w-full h-full object-cover" />
                  </button>
                ))}
                {images.length > 10 && (
                  <div className="h-20 sm:h-24 rounded-xl border border-black/10 bg-white flex flex-col items-center justify-center text-slate-500">
                    <Eye size={16} />
                    <span className="text-[11px] mt-1">+{images.length - 10}</span>
                  </div>
                )}
              </motion.div>
            )}

            <motion.article
              initial={{ opacity: 0, y: 14 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.2 }}
              className="rounded-2xl border border-black/10 bg-white p-6 shadow-[0_10px_32px_rgba(15,23,42,0.08)]"
            >
              <h2 className="text-xl mb-4">Descrição</h2>
              {property.descricao ? (
                <div
                  className="text-slate-600 leading-relaxed property-description"
                  dangerouslySetInnerHTML={{ __html: formatDescription(property.descricao) }}
                />
              ) : (
                <p className="text-slate-500">Sem descrição disponível.</p>
              )}
            </motion.article>
          </div>

          <div className="space-y-5">
            <motion.aside
              initial={{ opacity: 0, x: 18 }}
              animate={{ opacity: 1, x: 0 }}
              className="rounded-2xl border border-black/10 bg-white p-6 shadow-[0_12px_36px_rgba(15,23,42,0.10)] xl:sticky xl:top-20"
            >
              <p className="text-[11px] uppercase tracking-[0.16em] text-slate-500">Valor do imóvel</p>
              <p className="mt-2 text-4xl leading-none" style={{ color: primary }}>
                R$ {price.toLocaleString('pt-BR')}
              </p>

              <div className="mt-5 grid grid-cols-2 gap-3">
                {bedrooms ? (
                  <div className="rounded-xl border border-black/10 bg-slate-50 p-3">
                    <p className="text-[11px] uppercase tracking-[0.12em] text-slate-500">Quartos</p>
                    <p className="mt-1 flex items-center gap-1.5 text-sm text-slate-800">
                      <BedDouble size={15} />
                      {bedrooms}
                    </p>
                  </div>
                ) : null}
                {property.banheiros ? (
                  <div className="rounded-xl border border-black/10 bg-slate-50 p-3">
                    <p className="text-[11px] uppercase tracking-[0.12em] text-slate-500">Banheiros</p>
                    <p className="mt-1 flex items-center gap-1.5 text-sm text-slate-800">
                      <Bath size={15} />
                      {property.banheiros}
                    </p>
                  </div>
                ) : null}
                {area ? (
                  <div className="rounded-xl border border-black/10 bg-slate-50 p-3">
                    <p className="text-[11px] uppercase tracking-[0.12em] text-slate-500">Área</p>
                    <p className="mt-1 flex items-center gap-1.5 text-sm text-slate-800">
                      <Square size={15} />
                      {area}m²
                    </p>
                  </div>
                ) : null}
                {property.vagas_garagem ? (
                  <div className="rounded-xl border border-black/10 bg-slate-50 p-3">
                    <p className="text-[11px] uppercase tracking-[0.12em] text-slate-500">Garagem</p>
                    <p className="mt-1 flex items-center gap-1.5 text-sm text-slate-800">
                      <Car size={15} />
                      {property.vagas_garagem}
                    </p>
                  </div>
                ) : null}
              </div>

              {(property.condominio || property.iptu || property.codigo) && (
                <div className="mt-5 rounded-xl border border-black/10 p-4 space-y-2">
                  {property.condominio ? (
                    <div className="flex items-center justify-between text-sm">
                      <span className="text-slate-500">Condomínio</span>
                      <span className="text-slate-800">R$ {property.condominio.toLocaleString('pt-BR')}</span>
                    </div>
                  ) : null}
                  {property.iptu ? (
                    <div className="flex items-center justify-between text-sm">
                      <span className="text-slate-500">IPTU</span>
                      <span className="text-slate-800">R$ {property.iptu.toLocaleString('pt-BR')}</span>
                    </div>
                  ) : null}
                  {property.codigo ? (
                    <div className="flex items-center justify-between text-sm">
                      <span className="text-slate-500">Código</span>
                      <span className="font-mono text-slate-800">{property.codigo}</span>
                    </div>
                  ) : null}
                </div>
              )}

              <div className="mt-6 space-y-2.5">
                {tenant?.contact_phone ? (
                  <button
                    type="button"
                    onClick={handleWhatsApp}
                    className="w-full inline-flex items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold"
                    style={{ backgroundColor: secondary, color: '#111827' }}
                  >
                    <MessageCircle size={17} />
                    Falar com consultor
                  </button>
                ) : null}
                <div className="grid grid-cols-2 gap-2">
                  <button
                    type="button"
                    onClick={handleLike}
                    className={`inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm border ${
                      isLiked
                        ? 'bg-red-500 border-red-500 text-white'
                        : 'bg-white border-black/10 text-slate-800'
                    }`}
                  >
                    <Heart size={16} className={isLiked ? 'fill-white' : ''} />
                    {isLiked ? 'Favoritado' : 'Favoritar'}
                  </button>
                  <button
                    type="button"
                    onClick={handleShare}
                    className="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm border border-black/10 text-slate-800 bg-white"
                  >
                    <Share2 size={16} />
                    Compartilhar
                  </button>
                </div>
              </div>
            </motion.aside>

            <motion.article
              initial={{ opacity: 0, x: 18 }}
              animate={{ opacity: 1, x: 0 }}
              transition={{ delay: 0.1 }}
              className="rounded-2xl border border-black/10 bg-white p-5 shadow-[0_10px_32px_rgba(15,23,42,0.08)]"
            >
              <p className="text-[11px] uppercase tracking-[0.16em] text-slate-500">Próximo passo</p>
              <h3 className="mt-2 text-xl text-slate-900">Agende atendimento</h3>
              <p className="mt-2 text-sm text-slate-600">
                Envie uma mensagem para receber fotos adicionais, condições de negociação e análise de disponibilidade.
              </p>
              <button
                type="button"
                onClick={() => navigate('/portal')}
                className="mt-4 inline-flex items-center gap-2 text-sm text-slate-800 hover:text-black"
              >
                Voltar ao catálogo
                <ArrowUpRight size={15} />
              </button>
            </motion.article>
          </div>
        </div>
      </section>
    </div>
  );
}

