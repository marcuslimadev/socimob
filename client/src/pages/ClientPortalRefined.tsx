import { useEffect, useMemo, useState } from 'react';
import { useLocation } from 'wouter';
import { motion } from 'framer-motion';
import { ArrowUpRight, Bath, BedDouble, ChevronLeft, ChevronRight, Mail, MapPin, MessageCircle, Phone, Search, Square, UserRound } from 'lucide-react';
import api from '@/lib/api';
import { fetchTenantBranding, TenantBranding } from '@/lib/tenantBranding';

interface Property {
  id: number;
  titulo: string;
  tipo_negocio?: string;
  tipo_imovel: string;
  valor_venda?: number;
  valor_aluguel?: number;
  cidade: string;
  bairro: string;
  quartos?: number;
  dormitorios?: number;
  banheiros?: number;
  area_total?: number;
  area_util?: number;
  descricao?: string;
  destaque?: boolean;
  active?: boolean;
  fotos?: Array<{ url: string; destaque: boolean }>;
  imagens?: string[];
  imagem_destaque?: string;
  finalidade_imovel?: string;
}

interface TenantConfig extends TenantBranding {
  contact_phone?: string;
  contact_email?: string;
  creci?: string;
  about_text?: string;
  services?: string[];
  endereco?: string;
}

const PROPERTY_TYPES = [
  { value: '', label: 'Todos os tipos' },
  { value: 'apartamento', label: 'Apartamento' },
  { value: 'casa', label: 'Casa' },
  { value: 'cobertura', label: 'Cobertura' },
  { value: 'comercial', label: 'Comercial' },
  { value: 'terreno', label: 'Terreno' },
];

const BUSINESS_TYPES = [
  { value: '', label: 'Comprar e alugar' },
  { value: 'venda', label: 'Comprar' },
  { value: 'aluguel', label: 'Alugar' },
];

function normalizeImages(property: Property): string[] {
  const list: string[] = [];
  if (property.imagem_destaque) list.push(property.imagem_destaque);
  if (property.fotos?.length) list.push(...property.fotos.map((item) => item.url));
  if (property.imagens?.length) list.push(...property.imagens);
  return Array.from(new Set(list.filter(Boolean)));
}

function formatPrice(property: Property): string {
  const value = property.valor_venda || property.valor_aluguel || 0;
  if (!value) return 'Sob consulta';
  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
    maximumFractionDigits: 0,
  }).format(value);
}

function getPriceValue(property: Property): number {
  const value = property.valor_venda || property.valor_aluguel || 0;
  return Number(value) || 0;
}

function getPurpose(property: Property): 'Venda' | 'Aluguel' | 'Imovel' {
  const value = `${property.finalidade_imovel || ''} ${property.tipo_negocio || ''}`.toLowerCase();
  if (value.includes('alug')) return 'Aluguel';
  if (value.includes('vend')) return 'Venda';
  return 'Imovel';
}

export default function ClientPortalRefined() {
  const [, navigate] = useLocation();
  const [tenant, setTenant] = useState<TenantConfig | null>(null);
  const [properties, setProperties] = useState<Property[]>([]);
  const [loading, setLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState('');
  const [businessType, setBusinessType] = useState('');
  const [propertyType, setPropertyType] = useState('');
  const [slideIndex, setSlideIndex] = useState(0);
  const [isPaused, setIsPaused] = useState(false);
  const [sortBy, setSortBy] = useState('preco_asc');

  const primary = tenant?.primary_color || '#0f172a';
  const secondary = tenant?.secondary_color || '#c39a66';

  useEffect(() => {
    const loadTenant = async () => {
      const branding = await fetchTenantBranding();
      setTenant((branding as TenantConfig) || null);
    };

    loadTenant();
  }, []);

  useEffect(() => {
    const loadProperties = async () => {
      try {
        setLoading(true);
        const response = await api.get('/portal/imoveis');
        const data = response.data?.data || response.data || [];
        const items = Array.isArray(data)
          ? data.filter((property) => property.active !== false && normalizeImages(property).length > 0)
          : [];
        setProperties(items);
      } finally {
        setLoading(false);
      }
    };

    loadProperties();
  }, []);

  // Up to 6 destaque properties for the slideshow
  const destaqueProperties = useMemo(
    () => properties.filter((p) => p.destaque).slice(0, 6),
    [properties]
  );

  // Fallback to first property if no destaque ones
  const slideshowProperties = useMemo(
    () => (destaqueProperties.length > 0 ? destaqueProperties : properties.slice(0, 1)),
    [destaqueProperties, properties]
  );

  const currentSlide = slideshowProperties[slideIndex] ?? null;

  // Auto-advance every 5 seconds
  useEffect(() => {
    if (slideshowProperties.length <= 1 || isPaused) return;
    const timer = setInterval(() => {
      setSlideIndex((i) => (i + 1) % slideshowProperties.length);
    }, 5000);
    return () => clearInterval(timer);
  }, [slideshowProperties.length, isPaused]);

  // Reset slide index when properties change
  useEffect(() => {
    setSlideIndex(0);
  }, [slideshowProperties.length]);

  const filteredProperties = useMemo(() => {
    return properties
      .filter((property) => {
      const term = searchTerm.toLowerCase();
      const matchesSearch = !searchTerm
        || property.titulo?.toLowerCase().includes(term)
        || property.bairro?.toLowerCase().includes(term)
        || property.cidade?.toLowerCase().includes(term)
        || property.tipo_imovel?.toLowerCase().includes(term);

      const business = `${property.finalidade_imovel || ''} ${property.tipo_negocio || ''}`.toLowerCase();
      const matchesBusiness = !businessType || business.includes(businessType.toLowerCase());
      const matchesType = !propertyType || property.tipo_imovel?.toLowerCase().includes(propertyType.toLowerCase());

      return matchesSearch && matchesBusiness && matchesType;
    })
      .sort((a, b) => {
        if (sortBy === 'destaque') {
          if (a.destaque && !b.destaque) return -1;
          if (!a.destaque && b.destaque) return 1;
          return 0;
        }
        const aPrice = getPriceValue(a);
        const bPrice = getPriceValue(b);
        if (sortBy === 'preco_desc') {
          if (!aPrice && !bPrice) return 0;
          if (!aPrice) return 1;
          if (!bPrice) return -1;
          return bPrice - aPrice;
        }
        // preco_asc (default)
        if (!aPrice && !bPrice) return 0;
        if (!aPrice) return 1;
        if (!bPrice) return -1;
        return aPrice - bPrice;
      });
  }, [properties, searchTerm, businessType, propertyType, sortBy]);

  const whatsappLink = useMemo(() => {
    const phone = tenant?.contact_phone?.replace(/\D/g, '');
    if (!phone) return '';
    const message = encodeURIComponent(`Ola! Vim pelo portal da ${tenant?.name || 'imobiliaria'} e quero atendimento.`);
    return `https://wa.me/${phone}?text=${message}`;
  }, [tenant?.contact_phone, tenant?.name]);

  if (loading) {
    return (
      <div className="portal-public min-h-screen flex items-center justify-center" style={{ backgroundColor: '#0f172a' }}>
        <motion.div
          className="w-12 h-12 rounded-full border-4 border-white/20"
          style={{ borderTopColor: secondary }}
          animate={{ rotate: 360 }}
          transition={{ duration: 1.2, repeat: Infinity, ease: 'linear' }}
        />
      </div>
    );
  }

  return (
    <div className="portal-public min-h-screen" style={{ backgroundColor: '#f4efe8' }}>
      <header className="sticky top-0 z-40 border-b border-white/10 bg-[#0b111f]/92 backdrop-blur-xl">
        <div className="mx-auto max-w-7xl px-4 lg:px-8 py-3 flex items-center justify-between">
          <div className="flex items-center gap-3 min-w-0">
            {tenant?.logo_url || tenant?.logo ? (
              <img src={tenant.logo_url || tenant.logo} alt={tenant?.name || 'Logo'} className="w-11 h-11 rounded-full bg-white object-contain p-1" />
            ) : (
              <div className="w-11 h-11 rounded-full bg-white/10 border border-white/30 flex items-center justify-center text-white text-sm font-semibold">
                {(tenant?.name || 'IM').slice(0, 2).toUpperCase()}
              </div>
            )}
            <div className="min-w-0">
              <p className="text-sm tracking-[0.15em] uppercase text-white truncate">{tenant?.name || 'Imobiliaria'}</p>
              <p className="text-[11px] text-white/65 uppercase tracking-[0.12em] truncate">Private Estates</p>
            </div>
          </div>
          <div className="hidden lg:flex items-center gap-6">
            <a href="#catalogo" className="text-[11px] uppercase tracking-[0.16em] text-white/70 hover:text-white">Catalogo</a>
            <a href="#servicos" className="text-[11px] uppercase tracking-[0.16em] text-white/70 hover:text-white">Servicos</a>
            <a href="#contato" className="text-[11px] uppercase tracking-[0.16em] text-white/70 hover:text-white">Contato</a>
            <button
              type="button"
              onClick={() => navigate('/login')}
              className="rounded-full border border-white/30 px-4 py-2 text-xs uppercase tracking-[0.12em] text-white"
            >
              Entrar
            </button>
            <button
              type="button"
              onClick={() => navigate('/portal/register')}
              className="rounded-full px-4 py-2 text-xs uppercase tracking-[0.12em] font-semibold"
              style={{ backgroundColor: secondary, color: '#111827' }}
            >
              Registrar
            </button>
            <button
              type="button"
              onClick={() => document.getElementById('catalogo')?.scrollIntoView({ behavior: 'smooth' })}
              className="rounded-full border border-white/30 px-4 py-2 text-xs uppercase tracking-[0.12em] text-white"
            >
              Explorar
            </button>
          </div>
          <div className="flex lg:hidden items-center gap-2">
            <button
              type="button"
              onClick={() => navigate('/login')}
              className="inline-flex items-center justify-center w-9 h-9 rounded-full border border-white/30 text-white"
              aria-label="Entrar no portal"
            >
              <UserRound className="w-4 h-4" />
            </button>
          </div>
        </div>
      </header>

      <section className="relative overflow-hidden" style={{ background: `linear-gradient(115deg, ${primary}f0 0%, #0a0d16 100%)` }}>
        {currentSlide && (
          <img src={normalizeImages(currentSlide)[0]} alt="Destaque" className="absolute inset-0 w-full h-full object-cover opacity-25 transition-all duration-700" />
        )}
        <div className="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(255,255,255,0.15),transparent_35%)]" />

        <div className="relative mx-auto max-w-7xl px-4 lg:px-8 py-14 lg:py-20 grid lg:grid-cols-[1.1fr_0.9fr] gap-10">
          <div>
            <p className="text-[11px] uppercase tracking-[0.24em] text-white/80 mb-4">Signature Real Estate</p>
            <h1 className="text-4xl md:text-6xl leading-[1.02] text-white">Imóveis extraordinarios para estilos de vida unicos</h1>
            <p className="mt-5 text-white/75 max-w-2xl">{tenant?.slogan || 'Curadoria de residencias e investimentos em localizacoes de alto potencial.'}</p>
            {currentSlide && (
              <p className="mt-4 inline-flex rounded-full border border-white/25 bg-white/10 px-3 py-1 text-[11px] uppercase tracking-[0.14em] text-white/85">
                Destaque: {currentSlide.bairro}, {currentSlide.cidade}
              </p>
            )}
            <div className="mt-7 flex flex-wrap gap-3">
              <button
                type="button"
                onClick={() => document.getElementById('catalogo')?.scrollIntoView({ behavior: 'smooth' })}
                className="inline-flex items-center gap-2 rounded-full px-5 py-3 text-sm font-semibold"
                style={{ backgroundColor: secondary, color: '#111827' }}
              >
                Ver colecao
                <ArrowUpRight className="w-4 h-4" />
              </button>
              {currentSlide && (
                <button
                  type="button"
                  onClick={() => navigate(`/portal/imovel/${currentSlide.id}`)}
                  className="inline-flex items-center gap-2 rounded-full border border-white/30 px-5 py-3 text-sm font-semibold text-white"
                >
                  Imovel em destaque
                </button>
              )}
            </div>
          </div>

          <div className="grid grid-cols-2 gap-3">
            {[
              { label: 'Imoveis ativos', value: properties.length || '--' },
              { label: 'Destaques', value: destaqueProperties.length || '--' },
              { label: 'Cidades', value: new Set(properties.map((property) => property.cidade)).size || '--' },
              { label: 'Atendimento', value: tenant?.contact_phone ? '24/7' : '--' },
            ].map((stat) => (
              <div key={stat.label} className="rounded-2xl border border-white/20 bg-white/10 p-5 text-white">
                <p className="text-3xl">{stat.value}</p>
                <p className="mt-1 text-[11px] uppercase tracking-[0.16em] text-white/70">{stat.label}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      <section id="catalogo" className="mx-auto max-w-7xl px-4 lg:px-8 py-10">
        <div className="rounded-3xl border border-black/10 bg-white/80 p-4 lg:p-5 backdrop-blur-md shadow-[0_16px_42px_rgba(15,23,42,0.10)]">
          <div className="grid gap-3 md:grid-cols-[1fr_180px_190px_170px]">
            <div className="relative">
              <Search className="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-slate-500" />
              <input
                type="text"
                value={searchTerm}
                onChange={(event) => setSearchTerm(event.target.value)}
                placeholder="Buscar por bairro, cidade ou tipo"
                className="w-full h-11 rounded-xl border border-black/10 bg-white pl-10 pr-3 text-sm text-slate-900 placeholder:text-slate-500 outline-none"
              />
            </div>

            <select value={businessType} onChange={(event) => setBusinessType(event.target.value)} className="h-11 rounded-xl border border-black/10 bg-white px-3 text-sm text-slate-900">
              {BUSINESS_TYPES.map((option) => (
                <option key={option.value} value={option.value}>{option.label}</option>
              ))}
            </select>

            <select value={propertyType} onChange={(event) => setPropertyType(event.target.value)} className="h-11 rounded-xl border border-black/10 bg-white px-3 text-sm text-slate-900">
              {PROPERTY_TYPES.map((option) => (
                <option key={option.value} value={option.value}>{option.label}</option>
              ))}
            </select>

            <select value={sortBy} onChange={(event) => setSortBy(event.target.value)} className="h-11 rounded-xl border border-black/10 bg-white px-3 text-sm text-slate-900">
              <option value="preco_asc">Menor preco</option>
              <option value="preco_desc">Maior preco</option>
              <option value="destaque">Destaques primeiro</option>
            </select>
          </div>
        </div>

        {/* Slideshow — up to 6 destaque properties */}
        {slideshowProperties.length > 0 && currentSlide && (
          <motion.article
            className="mt-8 overflow-hidden rounded-[28px] border border-black/10 bg-[#0f172a] text-white shadow-[0_16px_44px_rgba(15,23,42,0.22)]"
            initial={{ opacity: 0, y: 14 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.4 }}
            onMouseEnter={() => setIsPaused(true)}
            onMouseLeave={() => setIsPaused(false)}
          >
            <div className="grid lg:grid-cols-[1.05fr_0.95fr]">
              {/* Image with prev/next controls */}
              <div className="relative h-[300px] lg:h-full min-h-[300px]">
                <img
                  key={currentSlide.id}
                  src={normalizeImages(currentSlide)[0]}
                  alt={currentSlide.titulo}
                  className="w-full h-full object-cover transition-opacity duration-500"
                  loading="lazy"
                />
                {/* Slide counter */}
                {slideshowProperties.length > 1 && (
                  <span className="absolute top-3 right-3 rounded-full bg-black/50 px-3 py-1 text-[11px] text-white/90 tracking-wide">
                    {slideIndex + 1} / {slideshowProperties.length}
                  </span>
                )}
                {/* Prev / Next arrows */}
                {slideshowProperties.length > 1 && (
                  <>
                    <button
                      type="button"
                      aria-label="Anterior"
                      onClick={() => setSlideIndex((i) => (i - 1 + slideshowProperties.length) % slideshowProperties.length)}
                      className="absolute left-3 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-black/50 hover:bg-black/70 flex items-center justify-center text-white transition-colors"
                    >
                      <ChevronLeft className="w-5 h-5" />
                    </button>
                    <button
                      type="button"
                      aria-label="Proximo"
                      onClick={() => setSlideIndex((i) => (i + 1) % slideshowProperties.length)}
                      className="absolute right-3 top-1/2 -translate-y-1/2 w-9 h-9 rounded-full bg-black/50 hover:bg-black/70 flex items-center justify-center text-white transition-colors"
                    >
                      <ChevronRight className="w-5 h-5" />
                    </button>
                  </>
                )}
              </div>

              {/* Property info */}
              <div className="p-6 lg:p-8 flex flex-col">
                <p className="inline-flex self-start rounded-full border border-white/30 px-3 py-1 text-[11px] uppercase tracking-[0.14em] text-white/80">
                  {getPurpose(currentSlide)}
                </p>
                <h2 className="mt-4 text-2xl lg:text-3xl leading-tight">{currentSlide.titulo}</h2>
                <p className="mt-2 text-sm text-white/70 flex items-center gap-1.5"><MapPin className="w-4 h-4" />{currentSlide.bairro}, {currentSlide.cidade}</p>
                <p className="mt-4 text-3xl" style={{ color: secondary }}>{formatPrice(currentSlide)}</p>
                <div className="mt-4 grid grid-cols-3 gap-2 text-[11px] text-white/80">
                  <p className="flex items-center gap-1"><BedDouble className="w-3.5 h-3.5" />{currentSlide.quartos || currentSlide.dormitorios || '--'}</p>
                  <p className="flex items-center gap-1"><Bath className="w-3.5 h-3.5" />{currentSlide.banheiros || '--'}</p>
                  <p className="flex items-center gap-1"><Square className="w-3.5 h-3.5" />{currentSlide.area_util || currentSlide.area_total || '--'}m2</p>
                </div>

                {/* Dot indicators */}
                {slideshowProperties.length > 1 && (
                  <div className="mt-5 flex gap-2">
                    {slideshowProperties.map((_, i) => (
                      <button
                        key={i}
                        type="button"
                        aria-label={`Slide ${i + 1}`}
                        onClick={() => setSlideIndex(i)}
                        className="w-2 h-2 rounded-full transition-all duration-300"
                        style={{ backgroundColor: i === slideIndex ? secondary : 'rgba(255,255,255,0.3)' }}
                      />
                    ))}
                  </div>
                )}

                <button
                  type="button"
                  onClick={() => navigate(`/portal/imovel/${currentSlide.id}`)}
                  className="mt-auto pt-6 inline-flex items-center gap-2 rounded-full px-5 py-3 text-sm font-semibold self-start"
                  style={{ backgroundColor: secondary, color: '#111827' }}
                >
                  Ver imovel destaque
                  <ArrowUpRight className="w-4 h-4" />
                </button>
              </div>
            </div>
          </motion.article>
        )}

        <div className="mt-8 grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
          {filteredProperties.map((property, index) => {
            const image = normalizeImages(property)[0];
            return (
              <motion.article
                key={property.id}
                className="group overflow-hidden rounded-2xl border border-black/10 bg-white shadow-[0_10px_26px_rgba(15,23,42,0.08)]"
                initial={{ opacity: 0, y: 12 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.35, delay: Math.min(index * 0.04, 0.2) }}
              >
                <div className="h-52 relative">
                  <img src={image} alt={property.titulo} className="w-full h-full object-cover transition duration-700 group-hover:scale-105" loading="lazy" />
                  <span className="absolute left-3 top-3 rounded-full border border-white/30 bg-black/35 px-3 py-1 text-[10px] uppercase tracking-[0.12em] text-white">
                    {getPurpose(property)}
                  </span>
                </div>
                <div className="p-4">
                  <h3 className="text-lg text-slate-900 line-clamp-1">{property.titulo}</h3>
                  <p className="mt-1 text-xs text-slate-500 flex items-center gap-1.5"><MapPin className="w-3.5 h-3.5" />{property.bairro}, {property.cidade}</p>
                  <p className="mt-3 text-2xl" style={{ color: primary }}>{formatPrice(property)}</p>
                  <div className="mt-3 grid grid-cols-3 gap-2 text-[11px] text-slate-600">
                    <p className="flex items-center gap-1"><BedDouble className="w-3.5 h-3.5" />{property.quartos || property.dormitorios || '--'}</p>
                    <p className="flex items-center gap-1"><Bath className="w-3.5 h-3.5" />{property.banheiros || '--'}</p>
                    <p className="flex items-center gap-1"><Square className="w-3.5 h-3.5" />{property.area_util || property.area_total || '--'}m2</p>
                  </div>
                  <button
                    type="button"
                    onClick={() => navigate(`/portal/imovel/${property.id}`)}
                    className="mt-4 w-full inline-flex items-center justify-center gap-2 rounded-xl border border-slate-900/15 px-4 py-2 text-sm font-semibold text-slate-800"
                  >
                    Ver detalhes
                    <ArrowUpRight className="w-4 h-4" />
                  </button>
                </div>
              </motion.article>
            );
          })}
        </div>

        {filteredProperties.length === 0 && (
          <div className="mt-8 rounded-2xl border border-dashed border-black/20 bg-white/70 px-6 py-10 text-center text-sm text-slate-600">
            Nenhum imovel encontrado com os filtros informados.
          </div>
        )}
      </section>

      <section id="servicos" className="mx-auto max-w-7xl px-4 lg:px-8 pb-16">
        <div className="grid gap-6 lg:grid-cols-2">
          <article className="rounded-3xl border border-black/10 bg-white p-6 shadow-[0_10px_34px_rgba(15,23,42,0.08)]">
            <p className="text-xs uppercase tracking-[0.2em] text-slate-500">Servicos</p>
            <h3 className="mt-2 text-2xl text-slate-900">Atendimento completo para compra, venda e locacao</h3>
            <div className="mt-6 grid gap-2 sm:grid-cols-2">
              {(tenant?.services?.length
                ? tenant.services
                : ['Consultoria imobiliaria', 'Avaliacao de mercado', 'Curadoria de investimentos', 'Acompanhamento documental'])
                .map((service) => (
                  <div key={service} className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700">{service}</div>
                ))}
            </div>
          </article>

          <article id="contato" className="rounded-3xl border border-black/10 p-6 text-white shadow-[0_14px_40px_rgba(15,23,42,0.22)]" style={{ backgroundColor: primary }}>
            <p className="text-xs uppercase tracking-[0.2em] text-white/70">Contato</p>
            <h3 className="mt-2 text-2xl">Converse com nossa equipe</h3>
            <div className="mt-5 space-y-3 text-sm">
              {tenant?.contact_phone && <a className="flex items-center gap-2 text-white/90" href={`tel:${tenant.contact_phone}`}><Phone className="w-4 h-4" />{tenant.contact_phone}</a>}
              {tenant?.contact_email && <a className="flex items-center gap-2 text-white/90" href={`mailto:${tenant.contact_email}`}><Mail className="w-4 h-4" />{tenant.contact_email}</a>}
              {tenant?.endereco && <p className="flex items-center gap-2 text-white/80"><MapPin className="w-4 h-4" />{tenant.endereco}</p>}
            </div>
          </article>
        </div>
      </section>

      {tenant?.mascot_url ? (
        <a
          href={whatsappLink || undefined}
          target={whatsappLink ? '_blank' : undefined}
          rel={whatsappLink ? 'noreferrer' : undefined}
          className="fixed bottom-4 right-4 z-50"
          aria-label="Abrir WhatsApp"
          style={{ cursor: whatsappLink ? 'pointer' : 'default' }}
        >
          <img
            src={tenant.mascot_url}
            alt="Mascote"
            className="w-28 h-28 object-contain drop-shadow-xl"
          />
        </a>
      ) : whatsappLink ? (
        <a
          href={whatsappLink}
          target="_blank"
          rel="noreferrer"
          className="fixed bottom-5 right-5 z-50 w-14 h-14 rounded-2xl border border-white/30 bg-[#0f172a] text-white shadow-[0_8px_24px_rgba(15,23,42,0.35)] flex items-center justify-center"
          aria-label="Abrir WhatsApp"
        >
          <MessageCircle className="w-6 h-6" />
        </a>
      ) : null}
    </div>
  );
}
