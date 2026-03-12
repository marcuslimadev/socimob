import { useEffect, useMemo, useRef, useState } from 'react';
import { useLocation } from 'wouter';
import { motion } from 'framer-motion';
import { ArrowUpRight, BadgeCheck, Bath, BedDouble, Calculator, ChevronDown, ChevronLeft, ChevronRight, Clock, GripVertical, Mail, MapPin, MessageCircle, Phone, Search, Shield, Square, TrendingUp, UserRound } from 'lucide-react';
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
  endereco_publico?: string;
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

function getPurpose(property: Property): 'Venda' | 'Aluguel' | 'Imóvel' {
  const value = `${property.finalidade_imovel || ''} ${property.tipo_negocio || ''}`.toLowerCase();
  if (value.includes('alug')) return 'Aluguel';
  if (value.includes('vend')) return 'Venda';
  return 'Imóvel';
}

function getPublicLocation(property: Property): string {
  return property.endereco_publico || [property.bairro, property.cidade].filter(Boolean).join(', ') || 'Localização sob consulta';
}

function formatPhoneInput(value: string): string {
  const digits = value.replace(/\D/g, '').slice(0, 11);
  if (digits.length <= 2) return digits;
  if (digits.length <= 7) return `(${digits.slice(0, 2)}) ${digits.slice(2)}`;
  if (digits.length <= 10) return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`;
  return `(${digits.slice(0, 2)}) ${digits.slice(2, 7)}-${digits.slice(7)}`;
}

function buildPropertyWhatsAppLink(property: Property, tenant: TenantConfig | null, leadName: string, leadPhone: string): string {
  const phone = tenant?.contact_phone?.replace(/\D/g, '');
  if (!phone) return '';

  const propertyUrl = typeof window !== 'undefined'
    ? `${window.location.origin}/portal/imovel/${property.id}`
    : `/portal/imovel/${property.id}`;

  const message = [
    `Olá! Sou ${leadName}.`,
    `Meu telefone é ${leadPhone}.`,
    `Tenho interesse no imóvel "${property.titulo}".`,
    `Localização: ${getPublicLocation(property)}.`,
    `Valor anunciado: ${formatPrice(property)}.`,
    `Link do imóvel: ${propertyUrl}`,
  ].join(' ');

  return `https://wa.me/${phone}?text=${encodeURIComponent(message)}`;
}

function buildGenericWhatsAppLink(tenant: TenantConfig | null, leadName: string, leadPhone: string): string {
  const phone = tenant?.contact_phone?.replace(/\D/g, '');
  if (!phone) return '';

  const message = [
    `Olá! Sou ${leadName}.`,
    `Meu telefone é ${leadPhone}.`,
    `Acabei de me cadastrar pelo portal e gostaria de atendimento.`,
  ].join(' ');

  return `https://wa.me/${phone}?text=${encodeURIComponent(message)}`;
}

function getFloatingActionMetrics(viewportWidth: number, hasMascot: boolean) {
  if (hasMascot) {
    const size = viewportWidth >= 640 ? 320 : 256;
    return { width: size, height: size };
  }

  return { width: 56, height: 56 };
}

function clampFloatingActionPosition(
  x: number,
  y: number,
  viewportWidth: number,
  viewportHeight: number,
  hasMascot: boolean,
) {
  const sideInset = 12;
  const topInset = viewportWidth >= 1024 ? 12 : 92;
  const bottomInset = 12;
  const { width, height } = getFloatingActionMetrics(viewportWidth, hasMascot);

  return {
    x: Math.max(sideInset, Math.min(viewportWidth - width - sideInset, x)),
    y: Math.max(topInset, Math.min(viewportHeight - height - bottomInset, y)),
  };
}

function getFloatingActionDefaultPosition(viewportWidth: number, viewportHeight: number, hasMascot: boolean) {
  const { width, height } = getFloatingActionMetrics(viewportWidth, hasMascot);
  return clampFloatingActionPosition(viewportWidth - width - 16, viewportHeight - height - 16, viewportWidth, viewportHeight, hasMascot);
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
  const [sortBy, setSortBy] = useState('preco_desc');
  const [slidePhotoIndex, setSlidePhotoIndex] = useState(0);
  const [thumbStart, setThumbStart] = useState(0);
  const [venderOpen, setVenderOpen] = useState(false);
  const [catalogPhotoIndexes, setCatalogPhotoIndexes] = useState<Record<number, number>>({});
  const [leadModalProperty, setLeadModalProperty] = useState<Property | null>(null);
  const [leadModalSource, setLeadModalSource] = useState<'card' | 'mascot'>('card');
  const [leadName, setLeadName] = useState('');
  const [leadPhone, setLeadPhone] = useState('');
  const [leadModalError, setLeadModalError] = useState('');
  const [leadSubmitting, setLeadSubmitting] = useState(false);
  const [floatingActionDragging, setFloatingActionDragging] = useState(false);
  const [floatingActionPos, setFloatingActionPos] = useState<{ x: number; y: number }>(() => {
    if (typeof window === 'undefined') return { x: 16, y: 16 };
    return getFloatingActionDefaultPosition(window.innerWidth, window.innerHeight, false);
  });

  const heroRef = useRef<HTMLElement>(null);
  const floatingActionPosRef = useRef(floatingActionPos);

  floatingActionPosRef.current = floatingActionPos;

  useEffect(() => {
    const onScroll = () => {
      const bg = heroRef.current?.querySelector('.parallax-bg') as HTMLElement | null;
      if (bg) bg.style.transform = `translateY(${window.scrollY * 0.45}px)`;
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    return () => window.removeEventListener('scroll', onScroll);
  }, []);

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

  useEffect(() => {
    if (typeof window === 'undefined') return;
    try {
      const savedName = localStorage.getItem('portal_lead_name');
      const savedPhone = localStorage.getItem('portal_lead_phone');
      if (savedName) setLeadName(savedName);
      if (savedPhone) setLeadPhone(savedPhone);
    } catch {}
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
  const currentSlidePhotos = useMemo(
    () => (currentSlide ? normalizeImages(currentSlide) : []),
    [currentSlide]
  );
  const selectedSlidePhoto = currentSlidePhotos[slidePhotoIndex] || currentSlidePhotos[0] || '';
  const thumbCount = 4;
  const visibleThumbs = useMemo(() => {
    if (currentSlidePhotos.length === 0) return [] as Array<{ photo: string; index: number }>;
    const maxStart = Math.max(0, currentSlidePhotos.length - thumbCount);
    const safeStart = Math.min(thumbStart, maxStart);
    const result: Array<{ photo: string; index: number }> = [];
    for (let i = 0; i < thumbCount; i += 1) {
      const idx = currentSlidePhotos.length <= thumbCount
        ? i % currentSlidePhotos.length
        : safeStart + i;
      result.push({ photo: currentSlidePhotos[idx], index: idx });
    }
    return result;
  }, [currentSlidePhotos, thumbStart]);

  // Sem avanço automático: navegação manual por setas/indicadores.

  // Reset slide index when properties change
  useEffect(() => {
    setSlideIndex(0);
  }, [slideshowProperties.length]);

  useEffect(() => {
    setSlidePhotoIndex(0);
    setThumbStart(0);
  }, [currentSlide?.id]);

  const filteredProperties = useMemo(() => {
    return properties
      .filter((property) => {
      const term = searchTerm.toLowerCase();
      const matchesSearch = !searchTerm
        || property.titulo?.toLowerCase().includes(term)
        || property.endereco_publico?.toLowerCase().includes(term)
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
    const message = encodeURIComponent(`Olá! Vim pelo portal da ${tenant?.name || 'imobiliária'} e quero atendimento.`);
    return `https://wa.me/${phone}?text=${message}`;
  }, [tenant?.contact_phone, tenant?.name]);

  const openPropertyWhatsAppModal = (property: Property) => {
    setLeadModalError('');
    setLeadModalSource('card');
    setLeadModalProperty(property);
  };

  const openMascotWhatsAppModal = () => {
    setLeadModalError('');
    setLeadModalSource('mascot');
    setLeadModalProperty(null);
  };

  const closePropertyWhatsAppModal = () => {
    setLeadModalError('');
    setLeadSubmitting(false);
    setLeadModalProperty(null);
  };

  const handleCatalogPhotoChange = (propertyId: number, direction: 'next' | 'prev', total: number) => {
    if (total <= 1) return;
    setCatalogPhotoIndexes((current) => {
      const base = current[propertyId] ?? 0;
      const next = direction === 'next'
        ? (base + 1) % total
        : (base - 1 + total) % total;
      return { ...current, [propertyId]: next };
    });
  };

  const handleLeadModalSubmit = async () => {
    const normalizedName = leadName.trim();
    const phoneDigits = leadPhone.replace(/\D/g, '');

    if (normalizedName.length < 2) {
      setLeadModalError('Informe seu nome para continuar.');
      return;
    }

    if (phoneDigits.length < 10) {
      setLeadModalError('Informe um telefone válido com DDD.');
      return;
    }

    const formattedPhone = formatPhoneInput(leadPhone);
    const fallbackWhatsappUrl = leadModalProperty
      ? buildPropertyWhatsAppLink(leadModalProperty, tenant, normalizedName, formattedPhone)
      : buildGenericWhatsAppLink(tenant, normalizedName, formattedPhone);

    try {
      localStorage.setItem('portal_lead_name', normalizedName);
      localStorage.setItem('portal_lead_phone', formattedPhone);
    } catch {}

    try {
      setLeadSubmitting(true);
      setLeadModalError('');

      const interesse = leadModalProperty
        ? `Interesse no imóvel \"${leadModalProperty.titulo}\". Localização: ${getPublicLocation(leadModalProperty)}. Valor: ${formatPrice(leadModalProperty)}. Origem: ${leadModalSource === 'mascot' ? 'mascote do portal' : 'card do catálogo'}.`
        : 'Atendimento solicitado pelo mascote do portal.';

      const response = await fetch('/api/portal/chat-lead', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Tenant-Domain': window.location.hostname,
        },
        body: JSON.stringify({
          nome: normalizedName,
          whatsapp: formattedPhone,
          interesse,
        }),
      });

      const data = await response.json();
      if (!response.ok || !data?.success) {
        throw new Error(data?.error || 'Não foi possível registrar seu contato.');
      }

      const tenantWhatsapp = data.whatsapp_number ? String(data.whatsapp_number).replace(/\D/g, '') : '';
      const whatsappUrl = tenantWhatsapp
        ? `https://wa.me/${tenantWhatsapp}?text=${encodeURIComponent(
            leadModalProperty
              ? [
                  `Olá! Sou ${normalizedName}.`,
                  `Meu telefone é ${formattedPhone}.`,
                  `Tenho interesse no imóvel \"${leadModalProperty.titulo}\".`,
                  `Localização: ${getPublicLocation(leadModalProperty)}.`,
                  `Valor anunciado: ${formatPrice(leadModalProperty)}.`,
                  `Link do imóvel: ${window.location.origin}/portal/imovel/${leadModalProperty.id}`,
                ].join(' ')
              : [
                  `Olá! Sou ${normalizedName}.`,
                  `Meu telefone é ${formattedPhone}.`,
                  'Acabei de me cadastrar pelo portal e gostaria de atendimento.',
                ].join(' ')
          )}`
        : fallbackWhatsappUrl;

      if (!whatsappUrl) {
        throw new Error('O WhatsApp da imobiliária não está configurado.');
      }

      closePropertyWhatsAppModal();
      window.location.href = whatsappUrl;
    } catch (error) {
      setLeadModalError(error instanceof Error ? error.message : 'Não foi possível registrar seu contato.');
    } finally {
      setLeadSubmitting(false);
    }
  };

  const hasMascot = Boolean(tenant?.mascot_url);
  const floatingActionMetrics = useMemo(() => {
    if (typeof window === 'undefined') return getFloatingActionMetrics(1280, hasMascot);
    return getFloatingActionMetrics(window.innerWidth, hasMascot);
  }, [hasMascot]);

  useEffect(() => {
    if (typeof window === 'undefined') return;
    const defaultPos = getFloatingActionDefaultPosition(window.innerWidth, window.innerHeight, hasMascot);

    try {
      const saved = localStorage.getItem('portal_mascot_pos');
      if (!saved) {
        setFloatingActionPos(defaultPos);
        return;
      }
      const parsed = JSON.parse(saved) as { x: number; y: number };
      if (typeof parsed.x !== 'number' || typeof parsed.y !== 'number') {
        setFloatingActionPos(defaultPos);
        return;
      }
      setFloatingActionPos(clampFloatingActionPosition(parsed.x, parsed.y, window.innerWidth, window.innerHeight, hasMascot));
    } catch {
      setFloatingActionPos(defaultPos);
    }
  }, [hasMascot]);

  useEffect(() => {
    const onResize = () => {
      setFloatingActionPos((current) => clampFloatingActionPosition(current.x, current.y, window.innerWidth, window.innerHeight, hasMascot));
    };

    window.addEventListener('resize', onResize);
    return () => window.removeEventListener('resize', onResize);
  }, [hasMascot]);

  const handleFloatingActionPointerDown = (e: React.PointerEvent<HTMLButtonElement>) => {
    const startX = e.clientX;
    const startY = e.clientY;
    const offsetX = e.clientX - floatingActionPosRef.current.x;
    const offsetY = e.clientY - floatingActionPosRef.current.y;
    let dragged = false;

    const onMove = (event: PointerEvent) => {
      const nextPos = clampFloatingActionPosition(
        event.clientX - offsetX,
        event.clientY - offsetY,
        window.innerWidth,
        window.innerHeight,
        hasMascot,
      );

      if (!dragged && Math.hypot(event.clientX - startX, event.clientY - startY) > 8) {
        dragged = true;
        setFloatingActionDragging(true);
      }

      if (dragged) {
        event.preventDefault();
        setFloatingActionPos(nextPos);
      }
    };

    const onUp = () => {
      window.removeEventListener('pointermove', onMove);
      window.removeEventListener('pointerup', onUp);
      window.removeEventListener('pointercancel', onUp);
      setFloatingActionDragging(false);

      if (dragged) {
        const sideInset = 12;
        const snappedX = floatingActionPosRef.current.x + (floatingActionMetrics.width / 2) >= (window.innerWidth / 2)
          ? window.innerWidth - floatingActionMetrics.width - sideInset
          : sideInset;
        const finalPos = clampFloatingActionPosition(snappedX, floatingActionPosRef.current.y, window.innerWidth, window.innerHeight, hasMascot);
        setFloatingActionPos(finalPos);
        try {
          localStorage.setItem('portal_mascot_pos', JSON.stringify(finalPos));
        } catch {}
      }
    };

    window.addEventListener('pointermove', onMove, { passive: false });
    window.addEventListener('pointerup', onUp);
    window.addEventListener('pointercancel', onUp);
    e.preventDefault();
  };

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
              <p className="text-sm tracking-[0.15em] uppercase text-white truncate">{tenant?.name || 'Imobiliária'}</p>
              <p className="text-[11px] text-white/65 uppercase tracking-[0.12em] truncate">Private Estates</p>
            </div>
          </div>
          <div className="hidden lg:flex items-center gap-6">
            <a href="#catalogo" className="text-[11px] uppercase tracking-[0.16em] text-white/70 hover:text-white">Catálogo</a>
            <a href="#servicos" className="text-[11px] uppercase tracking-[0.16em] text-white/70 hover:text-white">Serviços</a>
            <a href="/portal/simulacao" className="text-[11px] uppercase tracking-[0.16em] text-white/70 hover:text-white">Simulação</a>
            <div className="relative" onMouseEnter={() => setVenderOpen(true)} onMouseLeave={() => setVenderOpen(false)}>
              <button type="button" className="flex items-center gap-1 text-[11px] uppercase tracking-[0.16em] text-white/70 hover:text-white">
                Vender <ChevronDown className="w-3 h-3 mt-0.5" />
              </button>
              {venderOpen && (
                <div className="absolute top-full left-1/2 -translate-x-1/2 mt-3 w-56 rounded-2xl border border-white/10 bg-[#0b111f]/96 backdrop-blur-xl shadow-[0_20px_60px_rgba(0,0,0,0.5)] py-2 z-50">
                  <a
                    href="/portal/vender"
                    className="flex items-center gap-2.5 px-4 py-2.5 text-xs text-white/80 hover:text-white hover:bg-white/5 transition-colors"
                  >
                    <TrendingUp className="w-3.5 h-3.5" style={{ color: '#c39a66' }} />
                    Anunciar meu imóvel
                  </a>
                  <a
                    href="/portal/vender"
                    className="flex items-center gap-2.5 px-4 py-2.5 text-xs text-white/80 hover:text-white hover:bg-white/5 transition-colors"
                  >
                    <BadgeCheck className="w-3.5 h-3.5" style={{ color: '#c39a66' }} />
                    Avaliação gratuita
                  </a>
                  <a
                    href="#como-vender"
                    onClick={() => setVenderOpen(false)}
                    className="flex items-center gap-2.5 px-4 py-2.5 text-xs text-white/80 hover:text-white hover:bg-white/5 transition-colors"
                  >
                    <Clock className="w-3.5 h-3.5" style={{ color: '#c39a66' }} />
                    Como funciona?
                  </a>
                </div>
              )}
            </div>
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
              onClick={() => navigate('/portal/simulacao')}
              className="inline-flex items-center justify-center w-9 h-9 rounded-full border border-white/30 text-white"
              aria-label="Simular financiamento"
            >
              <Calculator className="w-4 h-4" />
            </button>
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

      <section ref={heroRef} className="relative overflow-hidden" style={{ background: `linear-gradient(115deg, ${primary}f0 0%, #0a0d16 100%)` }}>
        {/* Parallax background */}
        <div className="absolute inset-0 overflow-hidden pointer-events-none">
          {currentSlide ? (
            <img
              src={normalizeImages(currentSlide)[0]}
              alt="Destaque"
              className="parallax-bg absolute left-0 w-full object-cover opacity-25 transition-[opacity] duration-700"
              style={{ height: '160%', top: '-30%', willChange: 'transform' }}
            />
          ) : (
            <img
              src="https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?auto=format&fit=crop&w=1920&q=80"
              alt="Imóveis de alto padrão"
              className="parallax-bg absolute left-0 w-full object-cover opacity-20"
              style={{ height: '160%', top: '-30%', willChange: 'transform' }}
            />
          )}
        </div>
        <div className="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(255,255,255,0.15),transparent_35%)]" />

        <div className="relative mx-auto max-w-7xl px-4 lg:px-8 py-10 lg:py-20 grid lg:grid-cols-[1.1fr_0.9fr] gap-8 lg:gap-10">
          <div>
            <p className="text-[11px] uppercase tracking-[0.24em] text-white/80 mb-4">Signature Real Estate</p>
            <h1 className="text-3xl md:text-6xl leading-[1.05] text-white">Imóveis extraordinários para estilos de vida únicos</h1>
            <p className="mt-4 text-sm md:text-base text-white/75 max-w-2xl">{tenant?.slogan || 'Curadoria de residências e investimentos em localizações de alto potencial.'}</p>
            {currentSlide && (
              <p className="mt-4 inline-flex rounded-full border border-white/25 bg-white/10 px-3 py-1 text-[11px] uppercase tracking-[0.14em] text-white/85">
                Destaque: {getPublicLocation(currentSlide)}
              </p>
            )}
            <div className="mt-6 flex flex-wrap gap-2.5">
              <button
                type="button"
                onClick={() => document.getElementById('catalogo')?.scrollIntoView({ behavior: 'smooth' })}
                className="inline-flex items-center gap-2 rounded-full px-4 py-2.5 text-sm font-semibold"
                style={{ backgroundColor: secondary, color: '#111827' }}
              >
                Ver coleção
                <ArrowUpRight className="w-4 h-4" />
              </button>
              {currentSlide && (
                <button
                  type="button"
                  onClick={() => navigate(`/portal/imovel/${currentSlide.id}`)}
                  className="inline-flex items-center gap-2 rounded-full border border-white/30 px-4 py-2.5 text-sm font-semibold text-white"
                >
                  Imóvel em destaque
                </button>
              )}
              <button
                type="button"
                onClick={() => navigate('/portal/vender')}
                className="inline-flex items-center gap-2 rounded-full border border-white/30 px-4 py-2.5 text-sm font-semibold text-white hover:bg-white/10 transition-colors"
              >
                <TrendingUp className="w-4 h-4" />
                Quero vender
              </button>
            </div>
          </div>

          <div className="grid grid-cols-2 gap-2.5 md:gap-3">
            {[
              { label: 'Negócios fechados', value: '+5.000' },
              { label: 'Satisfação', value: '5★' },
              { label: 'Cidades', value: new Set(properties.map((property) => property.cidade)).size || '10+' },
              { label: 'Anos no mercado', value: '+15' },
            ].map((stat) => (
              <div key={stat.label} className="rounded-2xl border border-white/20 bg-white/10 p-4 md:p-5 text-white">
                <p className="text-2xl md:text-3xl font-light">{stat.value}</p>
                <p className="mt-1 text-[11px] uppercase tracking-[0.16em] text-white/70">{stat.label}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {/* ─── QUERO VENDER ─── */}
      <section id="como-vender" className="mx-auto max-w-7xl px-4 lg:px-8 pt-10 pb-4">
        <div className="rounded-3xl overflow-hidden border border-black/10 shadow-[0_16px_52px_rgba(15,23,42,0.12)] grid lg:grid-cols-2">
          {/* Photo side */}
          <div className="relative h-64 lg:h-auto">
            <img
              src="https://images.unsplash.com/photo-1570129477492-45c003edd2be?auto=format&fit=crop&w=900&q=80"
              alt="Venda seu imóvel"
              className="w-full h-full object-cover"
            />
            <div className="absolute inset-0 bg-gradient-to-t from-black/60 to-black/10" />
            <div className="absolute bottom-6 left-6 right-6">
              <div className="inline-flex items-center gap-2 rounded-full border border-white/30 bg-white/10 backdrop-blur-sm px-3 py-1.5 mb-3">
                <BadgeCheck className="w-4 h-4 text-white" />
                <span className="text-xs text-white font-semibold uppercase tracking-[0.14em]">Avaliação gratuita</span>
              </div>
              <p className="text-white text-xl font-light leading-snug">
                Sua casa pode valer mais<br />do que você imagina.
              </p>
            </div>
          </div>

          {/* Content side */}
          <div className="bg-white p-7 lg:p-10 flex flex-col justify-center">
            <p className="text-[11px] uppercase tracking-[0.2em] text-slate-500 mb-2">Anuncie com a gente</p>
            <h2 className="text-3xl lg:text-4xl text-slate-900 leading-tight">
              Venda seu imóvel<br />
              <span style={{ color: secondary }}>pelo melhor preço</span>.
            </h2>
            <p className="mt-3 text-sm text-slate-600 max-w-md">
              Nossa equipe cuida de tudo: avaliação de mercado, fotografia profissional, anúncios segmentados e
              acompanhamento jurídico — tudo sem custo antecipado.
            </p>

            {/* Benefits */}
            <div className="mt-6 grid gap-3 sm:grid-cols-3">
              {[
                { icon: Clock, label: 'Avaliação em 48h', sub: 'Resposta garantida' },
                { icon: Shield, label: 'Sem taxas iniciais', sub: 'Só pagou se vender' },
                { icon: TrendingUp, label: 'Mais compradores', sub: 'Rede qualificada' },
              ].map((benefit) => (
                <div
                  key={benefit.label}
                  className="rounded-2xl border border-slate-100 bg-slate-50 p-3 flex flex-col items-start gap-1"
                >
                  <benefit.icon className="w-5 h-5 mb-0.5" style={{ color: secondary }} />
                  <p className="text-xs font-semibold text-slate-800">{benefit.label}</p>
                  <p className="text-[11px] text-slate-500">{benefit.sub}</p>
                </div>
              ))}
            </div>

            {/* Process steps */}
            <div className="mt-7">
              <p className="text-[11px] uppercase tracking-[0.15em] text-slate-400 mb-3">Como funciona</p>
              <div className="space-y-2.5">
                {[
                  'Cadastre seu imóvel em minutos',
                  'Receba a avaliação gratuita em até 48h',
                  'Fotografia e anúncios profissionais',
                  'Nós cuidamos da negociação e documentos',
                ].map((step, i) => (
                  <div key={step} className="flex items-center gap-3">
                    <div
                      className="w-6 h-6 rounded-full flex items-center justify-center text-xs font-bold shrink-0 text-white"
                      style={{ backgroundColor: i < 2 ? primary : '#cbd5e1' }}
                    >
                      {i + 1}
                    </div>
                    <p className="text-sm text-slate-700">{step}</p>
                  </div>
                ))}
              </div>
            </div>

            {/* CTA */}
            <div className="mt-7 flex flex-wrap gap-3">
              <button
                type="button"
                onClick={() => navigate('/portal/vender')}
                className="inline-flex items-center gap-2 rounded-full px-6 py-3 text-sm font-semibold"
                style={{ backgroundColor: primary, color: '#fff' }}
              >
                Anunciar meu imóvel
                <ArrowUpRight className="w-4 h-4" />
              </button>
              <button
                type="button"
                onClick={() => navigate('/portal/vender')}
                className="inline-flex items-center gap-2 rounded-full border border-slate-200 px-6 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50"
              >
                <BadgeCheck className="w-4 h-4" style={{ color: secondary }} />
                Avaliação gratuita
              </button>
            </div>
          </div>
        </div>
      </section>

      <section id="catalogo" className="mx-auto max-w-7xl px-4 lg:px-8 py-10">
        <div className="rounded-3xl border border-black/10 bg-white/80 p-4 lg:p-5 backdrop-blur-md shadow-[0_16px_42px_rgba(15,23,42,0.10)]">
          <div className="grid gap-3 grid-cols-1 sm:grid-cols-2 md:grid-cols-[1fr_180px_190px_170px]">
            <div className="relative sm:col-span-2 md:col-span-1">
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
              <option value="preco_asc">Menor preço</option>
              <option value="preco_desc">Maior preço</option>
              <option value="destaque">Destaques primeiro</option>
            </select>
          </div>
        </div>

        {/* Slideshow — up to 6 destaque properties */}
        {slideshowProperties.length > 0 && currentSlide && (
          <motion.article
            className="mt-8 mx-auto max-w-[1240px] overflow-hidden rounded-[24px] border border-black/10 bg-white text-slate-900 shadow-[0_16px_44px_rgba(15,23,42,0.12)] lg:h-[460px]"
            initial={{ opacity: 0, y: 14 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.4 }}
          >
            <div className="grid h-full lg:grid-cols-[1.28fr_0.72fr]">
              {/* Image with prev/next controls */}
              <div className="relative h-[250px] sm:h-[300px] lg:h-full">
                <div className="h-full flex flex-col">
                  <div className="flex-1 min-h-0 p-1.5 pb-0">
                    <img
                      key={`${currentSlide.id}-${selectedSlidePhoto}`}
                      src={selectedSlidePhoto}
                      alt={currentSlide.titulo}
                      className="w-full h-full rounded-md object-cover transition-opacity duration-500"
                      loading="lazy"
                    />
                  </div>
                  <div className="h-[90px] p-1.5 pt-1 relative">
                    {currentSlidePhotos.length > 4 && (
                      <button
                        type="button"
                        aria-label="Miniaturas anteriores"
                        onClick={() => setThumbStart((start) => Math.max(0, start - 1))}
                        className="absolute left-2 top-1/2 -translate-y-1/2 z-10 w-7 h-7 rounded-full border border-black/10 bg-white hover:bg-slate-50 flex items-center justify-center text-slate-700 transition-colors"
                      >
                        <ChevronLeft className="w-4 h-4" />
                      </button>
                    )}
                    <div className={`h-full grid grid-cols-4 gap-1.5 ${currentSlidePhotos.length > 4 ? 'px-7' : 'px-0'}`}>
                      {visibleThumbs.map(({ photo, index: realIndex }) => {
                        const isActive = realIndex === slidePhotoIndex;
                        return (
                          <button
                            key={`${currentSlide.id}-thumb-${realIndex}`}
                            type="button"
                            onClick={() => setSlidePhotoIndex(realIndex)}
                            className={`overflow-hidden rounded-md border transition ${
                              isActive ? 'border-slate-900 ring-2 ring-slate-300' : 'border-black/15 hover:border-black/40'
                            }`}
                          >
                            <img
                              src={photo}
                              alt={`Miniatura ${realIndex + 1}`}
                              className={`w-full h-full object-cover ${isActive ? '' : 'opacity-85'}`}
                              loading="lazy"
                            />
                          </button>
                        );
                      })}
                    </div>
                    {currentSlidePhotos.length > 4 && (
                      <button
                        type="button"
                        aria-label="Próximas miniaturas"
                        onClick={() => setThumbStart((start) => Math.min(Math.max(0, currentSlidePhotos.length - 4), start + 1))}
                        className="absolute right-2 top-1/2 -translate-y-1/2 z-10 w-7 h-7 rounded-full border border-black/10 bg-white hover:bg-slate-50 flex items-center justify-center text-slate-700 transition-colors"
                      >
                        <ChevronRight className="w-4 h-4" />
                      </button>
                    )}
                  </div>
                </div>
                {/* Slide counter */}
                {slideshowProperties.length > 1 && (
                  <span className="absolute top-3 right-3 rounded-full bg-black/60 px-3 py-1 text-[11px] text-white tracking-wide">
                    {slideIndex + 1} / {slideshowProperties.length}
                  </span>
                )}
              </div>

              {/* Property info */}
              <div className="p-5 lg:p-6 flex flex-col overflow-hidden">
                <p className="inline-flex self-start rounded-full border border-black/10 bg-slate-50 px-3 py-1 text-[11px] uppercase tracking-[0.14em] text-slate-600">
                  {getPurpose(currentSlide)}
                </p>
                <h2 className="mt-4 text-2xl lg:text-3xl leading-tight line-clamp-2">{currentSlide.titulo}</h2>
                <p className="mt-2 text-sm text-slate-500 flex items-center gap-1.5 line-clamp-1"><MapPin className="w-4 h-4 shrink-0" />{getPublicLocation(currentSlide)}</p>
                <p className="mt-4 text-3xl" style={{ color: secondary }}>{formatPrice(currentSlide)}</p>
                <div className="mt-4 grid grid-cols-3 gap-2 text-[11px] text-slate-600">
                  <p className="flex items-center gap-1"><BedDouble className="w-3.5 h-3.5" />{currentSlide.quartos || currentSlide.dormitorios || '--'}</p>
                  <p className="flex items-center gap-1"><Bath className="w-3.5 h-3.5" />{currentSlide.banheiros || '--'}</p>
                  <p className="flex items-center gap-1"><Square className="w-3.5 h-3.5" />{currentSlide.area_util || currentSlide.area_total || '--'}m²</p>
                </div>

                {/* Dot indicators */}
                {slideshowProperties.length > 1 && (
                  <div className="mt-5 flex items-center gap-3">
                    {slideshowProperties.map((_, i) => (
                      <button
                        key={i}
                        type="button"
                        aria-label={`Slide ${i + 1}`}
                        onClick={() => setSlideIndex(i)}
                        className="w-2 h-2 rounded-full transition-all duration-300"
                        style={{ backgroundColor: i === slideIndex ? secondary : '#cbd5e1' }}
                      />
                    ))}
                    <button
                      type="button"
                      aria-label="Anterior"
                      onClick={() => setSlideIndex((i) => (i - 1 + slideshowProperties.length) % slideshowProperties.length)}
                      className="ml-1 inline-flex items-center justify-center w-7 h-7 rounded-full border border-black/10 bg-white hover:bg-slate-50 text-slate-700"
                    >
                      <ChevronLeft className="w-4 h-4" />
                    </button>
                    <button
                      type="button"
                      aria-label="Próximo"
                      onClick={() => setSlideIndex((i) => (i + 1) % slideshowProperties.length)}
                      className="inline-flex items-center justify-center w-7 h-7 rounded-full border border-black/10 bg-white hover:bg-slate-50 text-slate-700"
                    >
                      <ChevronRight className="w-4 h-4" />
                    </button>
                  </div>
                )}

                <button
                  type="button"
                  onClick={() => navigate(`/portal/imovel/${currentSlide.id}`)}
                  className="mt-auto pt-6 inline-flex items-center gap-2 rounded-full px-5 py-3 text-sm font-semibold self-start"
                  style={{ backgroundColor: secondary, color: '#111827' }}
                >
                  Ver imóvel em destaque
                  <ArrowUpRight className="w-4 h-4" />
                </button>
              </div>
            </div>
          </motion.article>
        )}

        <div className="mt-8 mb-2 flex items-end justify-between">
          <div>
            <p className="text-[11px] uppercase tracking-[0.2em] text-slate-500">Catálogo</p>
            <h2 className="mt-1 text-2xl text-slate-900">
              {filteredProperties.length > 0
                ? `${filteredProperties.length} imóvel${filteredProperties.length !== 1 ? 's' : ''} encontrado${filteredProperties.length !== 1 ? 's' : ''}`
                : 'Imóveis disponíveis'}
            </h2>
          </div>
        </div>

        <div className="mt-2 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
          {filteredProperties.map((property, index) => {
            const images = normalizeImages(property);
            const activePhotoIndex = Math.min(catalogPhotoIndexes[property.id] ?? 0, Math.max(0, images.length - 1));
            const activeImage = images[activePhotoIndex] || images[0];
            return (
              <motion.article
                key={property.id}
                className="group overflow-hidden rounded-2xl border border-black/10 bg-white shadow-[0_10px_26px_rgba(15,23,42,0.08)]"
                initial={{ opacity: 0, y: 12 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.35, delay: Math.min(index * 0.04, 0.2) }}
              >
                <div className="h-56 sm:h-52 relative">
                  <img src={activeImage} alt={property.titulo} className="w-full h-full object-cover transition duration-700 group-hover:scale-105" loading="lazy" />
                  <span className="absolute left-3 top-3 rounded-full border border-white/30 bg-black/35 px-3 py-1 text-[10px] uppercase tracking-[0.12em] text-white">
                    {getPurpose(property)}
                  </span>
                  {images.length > 1 && (
                    <>
                      <button
                        type="button"
                        aria-label="Foto anterior"
                        onClick={() => handleCatalogPhotoChange(property.id, 'prev', images.length)}
                        className="absolute left-3 top-1/2 -translate-y-1/2 flex h-8 w-8 items-center justify-center rounded-full bg-white/88 text-slate-700 shadow-sm backdrop-blur-sm"
                      >
                        <ChevronLeft className="h-4 w-4" />
                      </button>
                      <button
                        type="button"
                        aria-label="Próxima foto"
                        onClick={() => handleCatalogPhotoChange(property.id, 'next', images.length)}
                        className="absolute right-3 top-1/2 -translate-y-1/2 flex h-8 w-8 items-center justify-center rounded-full bg-white/88 text-slate-700 shadow-sm backdrop-blur-sm"
                      >
                        <ChevronRight className="h-4 w-4" />
                      </button>
                      <div className="absolute bottom-3 left-3 right-3 flex items-center gap-1.5 overflow-x-auto rounded-2xl bg-black/28 px-2 py-2 backdrop-blur-sm">
                        {images.slice(0, 5).map((thumbImage, imageIndex) => (
                          <button
                            key={`${property.id}-dot-${imageIndex}`}
                            type="button"
                            aria-label={`Foto ${imageIndex + 1}`}
                            onClick={() => setCatalogPhotoIndexes((current) => ({ ...current, [property.id]: imageIndex }))}
                            className={`h-10 w-12 shrink-0 overflow-hidden rounded-lg border transition ${imageIndex === activePhotoIndex ? 'border-white ring-2 ring-white/70' : 'border-white/35 opacity-80 hover:opacity-100'}`}
                          >
                            <img src={thumbImage} alt={`Miniatura ${imageIndex + 1}`} className="h-full w-full object-cover" loading="lazy" />
                          </button>
                        ))}
                      </div>
                    </>
                  )}
                </div>
                <div className="p-4">
                  <h3 className="text-base sm:text-lg text-slate-900 line-clamp-1">{property.titulo}</h3>
                  <p className="mt-1 text-xs text-slate-500 flex items-center gap-1.5"><MapPin className="w-3.5 h-3.5" />{getPublicLocation(property)}</p>
                  <p className="mt-3 text-xl sm:text-2xl" style={{ color: primary }}>{formatPrice(property)}</p>
                  <div className="mt-3 grid grid-cols-3 gap-2 text-[11px] text-slate-600">
                    <p className="flex items-center gap-1"><BedDouble className="w-3.5 h-3.5" />{property.quartos || property.dormitorios || '--'}</p>
                    <p className="flex items-center gap-1"><Bath className="w-3.5 h-3.5" />{property.banheiros || '--'}</p>
                    <p className="flex items-center gap-1"><Square className="w-3.5 h-3.5" />{property.area_util || property.area_total || '--'}m²</p>
                  </div>
                  <div className="mt-4 grid gap-2 sm:grid-cols-2">
                    <button
                      type="button"
                      onClick={() => navigate(`/portal/imovel/${property.id}`)}
                      className="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-slate-900/15 px-4 py-2 text-sm font-semibold text-slate-800"
                    >
                      Ver detalhes
                      <ArrowUpRight className="w-4 h-4" />
                    </button>
                    <button
                      type="button"
                      onClick={() => openPropertyWhatsAppModal(property)}
                      disabled={!tenant?.contact_phone}
                      className="inline-flex w-full items-center justify-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60"
                      style={{ backgroundColor: primary }}
                    >
                      <MessageCircle className="h-4 w-4" />
                      WhatsApp
                    </button>
                  </div>
                </div>
              </motion.article>
            );
          })}
        </div>

        {filteredProperties.length === 0 && (
          <div className="mt-8 rounded-2xl border border-dashed border-black/20 bg-white/70 px-6 py-10 text-center text-sm text-slate-600">
            Nenhum imóvel encontrado com os filtros informados.
          </div>
        )}
      </section>

      {/* Simulação de Financiamento — banner promocional */}
      <section className="mx-auto max-w-7xl px-4 lg:px-8 pb-8">
        <div
          className="rounded-3xl p-6 lg:p-8 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-5"
          style={{ background: `linear-gradient(135deg, ${primary}f5 0%, #0a0d16 100%)` }}
        >
          <div>
            <div className="inline-flex items-center gap-1.5 rounded-full border border-white/20 bg-white/10 px-2.5 py-1 text-[10px] uppercase tracking-[0.2em] text-white/80 mb-2">
              <Calculator className="w-3 h-3" />
              Financiamento Imobiliário
            </div>
            <h3 className="text-xl text-white">Simule seu financiamento em segundos</h3>
            <p className="mt-1 text-sm text-white/70 max-w-lg">
              Calcule parcelas SAC e PRICE, descubra a renda mínima necessária e nossa equipe entra em contato para assessorá-lo.
            </p>
          </div>
          <button
            type="button"
            onClick={() => navigate('/portal/simulacao')}
            className="flex-shrink-0 inline-flex items-center gap-2 rounded-full px-6 py-3 text-sm font-semibold"
            style={{ backgroundColor: secondary, color: '#111827' }}
          >
            <Calculator className="w-4 h-4" />
            Simular agora
          </button>
        </div>
      </section>

      <section id="servicos" className="mx-auto max-w-7xl px-4 lg:px-8 pb-16">
        <div className="grid gap-6 lg:grid-cols-2">
          <article className="rounded-3xl border border-black/10 bg-white p-6 shadow-[0_10px_34px_rgba(15,23,42,0.08)]">
            <p className="text-xs uppercase tracking-[0.2em] text-slate-500">Serviços</p>
            <h3 className="mt-2 text-2xl text-slate-900">Atendimento completo para compra, venda e locação</h3>
            <div className="mt-6 grid gap-2 sm:grid-cols-2">
              {(tenant?.services?.length
                ? tenant.services
                : ['Consultoria imobiliária', 'Avaliação de mercado', 'Curadoria de investimentos', 'Acompanhamento documental'])
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

      {/* Botão flutuante de Simulação — desktop: lateral direita | mobile: barra no topo */}
      {/* Mobile: barra fixa logo abaixo do header */}
      <div className="lg:hidden fixed top-[68px] left-0 right-0 z-30">
        <button
          type="button"
          onClick={() => navigate('/portal/simulacao')}
          className="w-full flex items-center justify-center gap-2 py-2.5 text-xs font-semibold uppercase tracking-[0.14em] shadow-md"
          style={{ backgroundColor: secondary, color: '#111827' }}
        >
          <Calculator className="w-3.5 h-3.5" />
          Simular financiamento — Caixa e bancos
        </button>
      </div>

      {/* Desktop: tab vertical fixo na lateral direita */}
      <button
        type="button"
        onClick={() => navigate('/portal/simulacao')}
        aria-label="Simular financiamento"
        className="hidden lg:flex fixed right-0 top-1/2 -translate-y-1/2 z-40 flex-col items-center gap-2 rounded-l-2xl border border-r-0 border-white/20 bg-[#0b111f]/90 px-3 py-5 text-white hover:bg-[#0b111f] transition-colors backdrop-blur-md shadow-[-4px_4px_20px_rgba(0,0,0,0.25)]"
        style={{ writingMode: 'vertical-rl' } as React.CSSProperties}
      >
        <Calculator className="w-4 h-4 rotate-90" style={{ color: secondary }} />
        <span className="text-[10px] uppercase tracking-[0.18em] rotate-180" style={{ color: secondary }}>
          Simular
        </span>
      </button>

      {tenant?.mascot_url ? (
        <div
          className="fixed z-50 select-none"
          style={{
            left: floatingActionPos.x,
            top: floatingActionPos.y,
            width: floatingActionMetrics.width,
            height: floatingActionMetrics.height,
            transform: floatingActionDragging ? 'scale(1.03)' : 'scale(1)',
            transition: floatingActionDragging ? 'none' : 'transform 0.2s ease',
          }}
        >
          <button
            type="button"
            onClick={openMascotWhatsAppModal}
            className="block h-full w-full"
            aria-label="Abrir atendimento no WhatsApp"
            style={{ cursor: 'pointer' }}
          >
            <img
              src={tenant.mascot_url}
              alt="Mascote"
              draggable={false}
              className="h-full w-full object-contain drop-shadow-xl pointer-events-none"
            />
          </button>
          <button
            type="button"
            onPointerDown={handleFloatingActionPointerDown}
            aria-label="Arrastar mascote"
            className="absolute right-2 top-2 flex h-9 w-9 items-center justify-center rounded-full border border-black/10 bg-white/92 text-slate-700 shadow-lg"
            style={{ cursor: floatingActionDragging ? 'grabbing' : 'grab', touchAction: 'none' }}
          >
            <GripVertical className="h-4 w-4 pointer-events-none" />
          </button>
        </div>
      ) : whatsappLink ? (
        <div
          className="fixed z-50 select-none"
          style={{
            left: floatingActionPos.x,
            top: floatingActionPos.y,
            width: floatingActionMetrics.width,
            height: floatingActionMetrics.height,
            transform: floatingActionDragging ? 'scale(1.03)' : 'scale(1)',
            transition: floatingActionDragging ? 'none' : 'transform 0.2s ease',
          }}
        >
          <button
            type="button"
            onClick={openMascotWhatsAppModal}
            className="flex h-full w-full items-center justify-center rounded-2xl border border-white/30 bg-[#0f172a] text-white shadow-[0_8px_24px_rgba(15,23,42,0.35)]"
            aria-label="Abrir WhatsApp"
          >
            <MessageCircle className="w-6 h-6 pointer-events-none" />
          </button>
          <button
            type="button"
            onPointerDown={handleFloatingActionPointerDown}
            aria-label="Arrastar atalho do WhatsApp"
            className="absolute -right-1 -top-1 flex h-8 w-8 items-center justify-center rounded-full border border-black/10 bg-white/92 text-slate-700 shadow-lg"
            style={{ cursor: floatingActionDragging ? 'grabbing' : 'grab', touchAction: 'none' }}
          >
            <GripVertical className="h-4 w-4 pointer-events-none" />
          </button>
        </div>
      ) : null}

      {leadModalProperty && (
        <div className="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/55 px-4 py-6 backdrop-blur-sm">
          <div className="w-full max-w-md rounded-3xl bg-white p-6 shadow-[0_24px_70px_rgba(15,23,42,0.28)]">
            <div className="flex items-start justify-between gap-4">
              <div>
                <p className="text-[11px] uppercase tracking-[0.18em] text-slate-500">Atendimento via WhatsApp</p>
                <h3 className="mt-2 text-xl text-slate-900">{leadModalProperty ? 'Receber atendimento sobre este imóvel' : 'Falar com nossa equipe'}</h3>
              </div>
              <button
                type="button"
                onClick={closePropertyWhatsAppModal}
                className="rounded-full border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600"
              >
                Fechar
              </button>
            </div>

            {leadModalProperty ? (
              <div className="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                <p className="text-sm font-semibold text-slate-900 line-clamp-1">{leadModalProperty.titulo}</p>
                <p className="mt-1 text-xs text-slate-500">{getPublicLocation(leadModalProperty)}</p>
                <p className="mt-2 text-sm font-semibold" style={{ color: secondary }}>{formatPrice(leadModalProperty)}</p>
              </div>
            ) : (
              <div className="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                Informe seu nome e telefone para abrir o WhatsApp já com seu atendimento iniciado.
              </div>
            )}

            <div className="mt-5 space-y-4">
              <label className="block">
                <span className="mb-1.5 block text-sm font-medium text-slate-700">Seu nome</span>
                <input
                  type="text"
                  value={leadName}
                  onChange={(event) => setLeadName(event.target.value)}
                  placeholder="Como você se chama?"
                  className="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-900 outline-none"
                />
              </label>

              <label className="block">
                <span className="mb-1.5 block text-sm font-medium text-slate-700">Seu telefone</span>
                <input
                  type="tel"
                  value={leadPhone}
                  onChange={(event) => setLeadPhone(formatPhoneInput(event.target.value))}
                  placeholder="(31) 99999-9999"
                  className="h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-900 outline-none"
                />
              </label>

              {leadModalError && (
                <p className="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">{leadModalError}</p>
              )}
            </div>

            <div className="mt-6 grid gap-2 sm:grid-cols-2">
              <button
                type="button"
                onClick={closePropertyWhatsAppModal}
                className="inline-flex items-center justify-center rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700"
              >
                Cancelar
              </button>
              <button
                type="button"
                onClick={handleLeadModalSubmit}
                disabled={leadSubmitting}
                className="inline-flex items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold text-white"
                style={{ backgroundColor: primary }}
              >
                <MessageCircle className="h-4 w-4" />
                {leadSubmitting ? 'Registrando...' : 'Abrir WhatsApp'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
