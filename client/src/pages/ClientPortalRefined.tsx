import { useEffect, useMemo, useRef, useState } from 'react';
import { useLocation } from 'wouter';
import { motion } from 'framer-motion';
import { ArrowUpRight, BadgeCheck, Bath, BedDouble, Calculator, Car, ChevronDown, ChevronLeft, ChevronRight, Clock, Hash, Mail, MapPin, MessageCircle, Minus, Phone, Plus, Search, Shield, Square, TrendingUp } from 'lucide-react';
import api from '@/lib/api';
import { fetchTenantBranding, TenantBranding } from '@/lib/tenantBranding';
import { getPortalTemplate } from '@/lib/portalTemplates';

const PORTAL_RETURN_STATE_KEY = 'portal:return-state';
const PROPERTIES_PER_PAGE = 12;
const MASCOT_SCALE_STORAGE_KEY = 'portal_mascot_scale';
const MASCOT_MIN_SCALE = 0.55;
const MASCOT_MAX_SCALE = 1;
const MASCOT_SCALE_STEP = 0.15;

const EXCLUSIVA_DEFAULT_ABOUT_TEXT = 'A imobiliária Exclusiva Lar Imóveis iniciou suas atividades visando construir sua história no mercado imobiliário de Belo Horizonte de forma sólida, confiável e duradoura. Trata-se de uma imobiliária atuante no mercado, com histórico íntegro e ótimas negociações. Ética profissional, transparência, dinamismo e atendimento personalizado são pilares que garantem segurança em todos os negócios realizados e fazem da Exclusiva Lar Imóveis uma das empresas mais eficientes do mercado imobiliário regional. Venha conosco e faça parte desta família você também!';

function getInitialPortalFilters() {
  if (typeof window === 'undefined') {
    return {
      searchTerm: '',
      businessType: '',
      propertyType: '',
      propertyCode: '',
      selectedCity: '',
      selectedNeighborhood: '',
      maxPrice: '',
      sortBy: 'preco_desc',
      currentPage: 1,
    };
  }

  const params = new URLSearchParams(window.location.search);
  const parsedPage = Number(params.get('page') || '1');

  return {
    searchTerm: params.get('q') || '',
    businessType: params.get('business') || '',
    propertyType: params.get('type') || '',
    propertyCode: params.get('code') || '',
    selectedCity: params.get('city') || '',
    selectedNeighborhood: params.get('neighborhood') || '',
    maxPrice: params.get('max_price') || '',
    sortBy: params.get('sort') || 'preco_desc',
    currentPage: Number.isFinite(parsedPage) && parsedPage > 0 ? Math.floor(parsedPage) : 1,
  };
}

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
  area_privativa?: number | string;
  area_terreno?: number | string;
  garagem?: number | string;
  vagas?: number | string;
  descricao?: string;
  destaque?: boolean;
  active?: boolean;
  fotos?: Array<{ url: string; destaque: boolean }>;
  imagens?: string[];
  imagem_destaque?: string;
  finalidade_imovel?: string;
  endereco_publico?: string;
  codigo?: string;
  codigo_imovel?: string;
  referencia?: string;
  referencia_imovel?: string;
}

interface TenantConfig extends TenantBranding {
  contact_phone?: string;
  contact_email?: string;
  creci?: string;
  about_text?: string;
  services?: string[];
  endereco?: string;
  office_hours?: string;
  theme?: string;
  portal_template?: string;
}

const PROPERTY_TYPES = [
  { value: '', label: 'Todos os tipos' },
  { value: 'apartamento', label: 'Apartamento' },
  { value: 'casa', label: 'Casa' },
  { value: 'cobertura', label: 'Cobertura' },
  { value: 'comercial', label: 'Comercial' },
  { value: 'terreno', label: 'Terreno' },
];

type PurposeKind = 'venda' | 'aluguel' | 'venda_aluguel' | 'imovel';
type PortalSortKey = 'preco_asc' | 'preco_desc' | 'titulo_asc' | 'titulo_desc' | 'area_desc' | 'area_asc';

function getPurposeKind(property: Property): PurposeKind {
  const value = `${property.finalidade_imovel || ''} ${property.tipo_negocio || ''}`.toLowerCase();
  const hasSale = value.includes('vend');
  const hasRent = value.includes('alug') || value.includes('loca');

  if (hasSale && hasRent) return 'venda_aluguel';
  if (hasRent) return 'aluguel';
  if (hasSale) return 'venda';
  return 'imovel';
}

function normalizeImages(property: Property): string[] {
  const list: string[] = [];
  if (property.imagem_destaque) list.push(property.imagem_destaque);
  if (property.fotos?.length) list.push(...property.fotos.map((item) => item.url));
  if (property.imagens?.length) list.push(...property.imagens);
  return Array.from(new Set(list.filter(Boolean)));
}

function formatPrice(property: Property): string {
  const currency = (value: number) => new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
    maximumFractionDigits: 0,
  }).format(value);

  const salePrice = Number(property.valor_venda) || 0;
  const rentPrice = Number(property.valor_aluguel) || 0;
  const purpose = getPurposeKind(property);

  if (purpose === 'venda_aluguel' && salePrice && rentPrice) {
    return `Venda ${currency(salePrice)} | Aluguel ${currency(rentPrice)}/mês`;
  }

  if (purpose === 'aluguel') {
    const value = rentPrice || salePrice;
    return value ? `${currency(value)}/mês` : 'Sob consulta';
  }

  if (purpose === 'venda_aluguel') {
    const value = salePrice || rentPrice;
    return value ? `Venda ou aluguel: ${currency(value)}` : 'Sob consulta';
  }

  const value = salePrice || rentPrice;
  return value ? currency(value) : 'Sob consulta';
}

function getPriceValue(property: Property): number {
  const purpose = getPurposeKind(property);
  if (purpose === 'aluguel') {
    return Number(property.valor_aluguel || property.valor_venda || 0) || 0;
  }

  return Number(property.valor_venda || property.valor_aluguel || 0) || 0;
}

function parseAreaValue(value: number | string | null | undefined): number {
  if (typeof value === 'number') return Number.isFinite(value) ? value : 0;
  if (value === null || value === undefined) return 0;

  const raw = String(value).trim();
  if (!raw) return 0;

  const normalized = raw.replace(/[^\d.,-]/g, '');
  if (!normalized) return 0;

  // Formato pt-BR com milhar em ponto: 5.000 ou 12.345,67
  if (/^\d{1,3}(\.\d{3})+(,\d+)?$/.test(normalized)) {
    const parsed = Number(normalized.replace(/\./g, '').replace(',', '.'));
    return Number.isFinite(parsed) ? parsed : 0;
  }

  // Formato decimal com vírgula: 123,45
  if (/^\d+,\d+$/.test(normalized)) {
    const parsed = Number(normalized.replace(',', '.'));
    return Number.isFinite(parsed) ? parsed : 0;
  }

  const parsed = Number(normalized);
  return Number.isFinite(parsed) ? parsed : 0;
}

function normalizeAreaScale(area: number, property: Property): number {
  if (area <= 0) return 0;
  if (area >= 20) return area;

  const privativaSignal = parseAreaValue(property.area_privativa);
  const terrenoSignal = parseAreaValue(property.area_terreno);
  const hasLargeCompanionArea = privativaSignal >= 120 || terrenoSignal >= 120;
  const hasLargeHomeProfile = Number(property.dormitorios || property.quartos || 0) >= 3 && Number(property.banheiros || 0) >= 3;

  // Alguns imóveis chegam com área em formato reduzido (5 => 5.000 m², 1,2 => 1.200 m²).
  if (hasLargeCompanionArea || hasLargeHomeProfile) {
    return area * 1000;
  }

  return area;
}

function getDisplayArea(property: Property): number | null {
  const areaUtil = normalizeAreaScale(parseAreaValue(property.area_util), property);
  const areaTotal = normalizeAreaScale(parseAreaValue(property.area_total), property);
  const areaPrivativa = normalizeAreaScale(parseAreaValue(property.area_privativa), property);
  const areaTerreno = normalizeAreaScale(parseAreaValue(property.area_terreno), property);

  if (areaTotal > 0) return areaTotal;
  if (areaTerreno > 0) return areaTerreno;
  if (areaPrivativa > 0) return areaPrivativa;
  if (areaUtil > 0) return areaUtil;

  return null;
}

function getPropertyDescription(property: Property): string {
  return property.descricao?.trim()
    || [property.tipo_imovel, getPublicLocation(property)].filter(Boolean).join(' em ')
    || property.titulo;
}

function formatArea(area: number | null): string {
  if (!area) return '--';

  return new Intl.NumberFormat('pt-BR', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(area);
}

function getPurpose(property: Property): 'Venda' | 'Aluguel' | 'Venda ou Aluguel' | 'Imóvel' {
  const purpose = getPurposeKind(property);
  if (purpose === 'venda_aluguel') return 'Venda ou Aluguel';
  if (purpose === 'aluguel') return 'Aluguel';
  if (purpose === 'venda') return 'Venda';
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
  const phone = (tenant?.tenant_phone || tenant?.contact_phone || '').replace(/\D/g, '');
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

function getMascotPhoneDigits(tenant: TenantConfig | null): string {
  return (tenant?.mascot_whatsapp_phone || tenant?.whatsapp_phone || tenant?.contact_phone || '').replace(/\D/g, '');
}

function buildGenericWhatsAppLink(tenant: TenantConfig | null, leadName: string, leadPhone: string): string {
  const phone = getMascotPhoneDigits(tenant);
  if (!phone) return '';

  const message = [
    `Olá! Sou ${leadName}.`,
    `Meu telefone é ${leadPhone}.`,
    `Acabei de me cadastrar pelo portal e gostaria de atendimento.`,
  ].join(' ');

  return `https://wa.me/${phone}?text=${encodeURIComponent(message)}`;
}

function clampMascotScale(scale: number): number {
  return Math.max(MASCOT_MIN_SCALE, Math.min(MASCOT_MAX_SCALE, Number(scale.toFixed(2))));
}

function getFloatingActionMetrics(viewportWidth: number, hasMascot: boolean, mascotScale = 1) {
  if (hasMascot) {
    const baseSize = viewportWidth >= 640 ? 320 : 220;
    const size = Math.round(baseSize * clampMascotScale(mascotScale));
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
  mascotScale = 1,
) {
  const sideInset = 12;
  const topInset = viewportWidth >= 1024 ? 12 : 88;
  const bottomInset = viewportWidth >= 640 ? 12 : 20;
  const { width, height } = getFloatingActionMetrics(viewportWidth, hasMascot, mascotScale);

  return {
    x: Math.max(sideInset, Math.min(viewportWidth - width - sideInset, x)),
    y: Math.max(topInset, Math.min(viewportHeight - height - bottomInset, y)),
  };
}

function getFloatingActionDefaultPosition(viewportWidth: number, viewportHeight: number, hasMascot: boolean, mascotScale = 1) {
  const { width, height } = getFloatingActionMetrics(viewportWidth, hasMascot, mascotScale);
  return clampFloatingActionPosition(viewportWidth - width - 16, viewportHeight - height - 16, viewportWidth, viewportHeight, hasMascot, mascotScale);
}

export default function ClientPortalRefined() {
  const [, navigate] = useLocation();
  const initialFilters = getInitialPortalFilters();
  const [tenant, setTenant] = useState<TenantConfig | null>(null);
  const [properties, setProperties] = useState<Property[]>([]);
  const [loading, setLoading] = useState(true);
  const [searchTerm] = useState(initialFilters.searchTerm);
  const [businessType, setBusinessType] = useState(initialFilters.businessType);
  const [propertyType, setPropertyType] = useState(initialFilters.propertyType);
  const [propertyCode, setPropertyCode] = useState(initialFilters.propertyCode);
  const [selectedCity, setSelectedCity] = useState(initialFilters.selectedCity);
  const [selectedNeighborhood, setSelectedNeighborhood] = useState(initialFilters.selectedNeighborhood);
  const [maxPrice, setMaxPrice] = useState(initialFilters.maxPrice);
  const [searchMode, setSearchMode] = useState<'quick' | 'code'>(initialFilters.propertyCode ? 'code' : 'quick');
  const [isSearchDocked, setIsSearchDocked] = useState(false);
  const [sortBy, setSortBy] = useState<PortalSortKey>(
    ['preco_asc', 'preco_desc', 'titulo_asc', 'titulo_desc', 'area_desc', 'area_asc'].includes(initialFilters.sortBy)
      ? initialFilters.sortBy as PortalSortKey
      : 'preco_desc',
  );
  const [currentPage, setCurrentPage] = useState(initialFilters.currentPage);
  const [venderOpen, setVenderOpen] = useState(false);
  const [catalogPhotoIndexes, setCatalogPhotoIndexes] = useState<Record<number, number>>({});
  const [leadModalProperty, setLeadModalProperty] = useState<Property | null>(null);
  const [leadModalSource, setLeadModalSource] = useState<'card' | 'mascot'>('card');
  const [leadName, setLeadName] = useState('');
  const [leadPhone, setLeadPhone] = useState('');
  const [leadVisitDateTime, setLeadVisitDateTime] = useState('');
  const [leadVisitNotes, setLeadVisitNotes] = useState('');
  const [leadModalError, setLeadModalError] = useState('');
  const [leadSubmitting, setLeadSubmitting] = useState(false);
  const [floatingActionDragging, setFloatingActionDragging] = useState(false);
  const [floatingActionScale, setFloatingActionScale] = useState(1);
  const [floatingActionPos, setFloatingActionPos] = useState<{ x: number; y: number }>(() => {
    if (typeof window === 'undefined') return { x: 16, y: 16 };
    return getFloatingActionDefaultPosition(window.innerWidth, window.innerHeight, false);
  });

  const heroRef = useRef<HTMLElement>(null);
  const searchDockThresholdRef = useRef<number | null>(null);
  const floatingActionPosRef = useRef(floatingActionPos);
  const suppressFloatingActionClickRef = useRef(false);
  const restoredReturnStateRef = useRef(false);

  floatingActionPosRef.current = floatingActionPos;

  useEffect(() => {
    const onScroll = () => {
      const bg = heroRef.current?.querySelector('.parallax-bg') as HTMLElement | null;
      if (bg) bg.style.transform = `translateY(${window.scrollY * 0.45}px)`;
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    return () => window.removeEventListener('scroll', onScroll);
  }, []);

  useEffect(() => {
    const updateSearchDock = () => {
      if (window.innerWidth < 1280) {
        setIsSearchDocked(false);
        searchDockThresholdRef.current = null;
        return;
      }

      if (isSearchDocked) {
        if (window.scrollY < (searchDockThresholdRef.current ?? 0)) {
          setIsSearchDocked(false);
        }
        return;
      }

      const searchPanel = document.getElementById('property-search-panel');
      if (searchPanel && searchPanel.getBoundingClientRect().bottom < 24) {
        searchDockThresholdRef.current = window.scrollY;
        setIsSearchDocked(true);
      }
    };

    window.addEventListener('scroll', updateSearchDock, { passive: true });
    window.addEventListener('resize', updateSearchDock);
    updateSearchDock();
    return () => {
      window.removeEventListener('scroll', updateSearchDock);
      window.removeEventListener('resize', updateSearchDock);
    };
  }, [isSearchDocked]);

  const primary = tenant?.primary_color || '#0f172a';
  const secondary = tenant?.secondary_color || '#c39a66';
  const portalTemplate = getPortalTemplate(tenant?.portal_template || tenant?.theme);
  const templateHeroBackground = portalTemplate.hero.replace('var(--portal-primary-99)', `${primary}99`);
  const isCondensedHero = portalTemplate.heroMode === 'compact';
  const isLightHeader = portalTemplate.header.includes('bg-white');
  const isDarkShell = portalTemplate.shell === '#080808';
  const heroGridClass = portalTemplate.heroMode === 'compact' || portalTemplate.heroMode === 'search'
    ? 'relative mx-auto max-w-7xl px-4 py-5 sm:py-7 lg:px-8 lg:py-9'
    : portalTemplate.heroMode === 'editorial'
      ? 'relative mx-auto grid max-w-7xl items-end gap-6 px-4 py-10 lg:grid-cols-[0.85fr_1.15fr] lg:gap-12 lg:px-8 lg:py-24'
      : 'relative mx-auto grid max-w-7xl items-stretch gap-6 px-4 py-8 lg:grid-cols-[1.08fr_0.92fr] lg:gap-10 lg:px-8 lg:py-20';
  const catalogGridClass = portalTemplate.catalogMode === 'list'
    ? 'mt-2 grid gap-4'
    : portalTemplate.catalogMode === 'magazine'
      ? 'mt-2 grid gap-5 md:grid-cols-2 xl:grid-cols-3'
      : 'mt-2 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4';
  const headerTextClass = isLightHeader ? 'text-slate-900' : 'text-white';
  const headerMutedClass = isLightHeader ? 'text-slate-500 hover:text-slate-950' : 'text-white/70 hover:text-white';
  const isLightHeroPanel = portalTemplate.heroPanel.includes('bg-white');
  const heroTextClass = isLightHeroPanel ? 'text-slate-950' : 'text-white';
  const heroMutedClass = isLightHeroPanel ? 'text-slate-600' : 'text-white/85';

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
          ? data.filter((property) => normalizeImages(property).length > 0)
          : [];
        setProperties(items);
      } finally {
        setLoading(false);
      }
    };

    loadProperties();
  }, []);

  useEffect(() => {
    if (typeof window === 'undefined' || !tenant) return;

    const host = window.location.hostname.toLowerCase();
    const isExclusiva = host === 'exclusivalarimoveis.com'
      || host === 'www.exclusivalarimoveis.com'
      || host === 'exclusivalarimoveis.com.br'
      || host === 'www.exclusivalarimoveis.com.br'
      || host === 'exclusivarlarimoveis.com'
      || host === 'www.exclusivarlarimoveis.com'
      || tenant.name?.toLowerCase().includes('exclusiva');
    const brandName = tenant.name || 'Imobiliária';
    const city = isExclusiva ? 'Belo Horizonte' : properties.find((property) => property.cidade)?.cidade;
    const title = isExclusiva
      ? 'Exclusiva Lar Imóveis | Comprar e Alugar em Belo Horizonte'
      : `${brandName} | Imóveis para comprar e alugar${city ? ` em ${city}` : ''}`;
    const description = isExclusiva
      ? 'Encontre apartamentos, casas e imóveis para comprar ou alugar em Belo Horizonte. Consulte ofertas atualizadas e fale com a Exclusiva Lar Imóveis.'
      : `${brandName}: imóveis para compra, venda e locação${city ? ` em ${city}` : ''}, com atendimento especializado.`;
    const canonicalUrl = isExclusiva
      ? 'https://exclusivalarimoveis.com/'
      : `${window.location.origin}${window.location.pathname === '/portal' ? '/' : window.location.pathname}`;
    const toAbsoluteUrl = (value?: string) => {
      if (!value) return '';
      try {
        return new URL(value, window.location.origin).toString();
      } catch {
        return '';
      }
    };
    const socialImage = isExclusiva
      ? 'https://exclusivalarimoveis.com/images/og-exclusiva.png'
      : toAbsoluteUrl(tenant.logo_url || tenant.logo || normalizeImages(properties[0] || {} as Property)[0]);
    const setMeta = (selector: string, attribute: 'name' | 'property', key: string, content: string) => {
      const element = document.head.querySelector<HTMLMetaElement>(selector) || document.createElement('meta');
      element.setAttribute(attribute, key);
      element.setAttribute('content', content);
      if (!element.parentNode) document.head.appendChild(element);
    };

    document.title = title;
    setMeta("meta[name='description']", 'name', 'description', description);
    setMeta("meta[name='robots']", 'name', 'robots', 'index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1');
    setMeta("meta[property='og:site_name']", 'property', 'og:site_name', brandName);
    setMeta("meta[property='og:title']", 'property', 'og:title', title);
    setMeta("meta[property='og:description']", 'property', 'og:description', description);
    setMeta("meta[property='og:url']", 'property', 'og:url', canonicalUrl);
    setMeta("meta[name='twitter:title']", 'name', 'twitter:title', title);
    setMeta("meta[name='twitter:description']", 'name', 'twitter:description', description);
    if (socialImage) {
      setMeta("meta[property='og:image']", 'property', 'og:image', socialImage);
      setMeta("meta[property='og:image:alt']", 'property', 'og:image:alt', `${brandName} — imóveis${city ? ` em ${city}` : ''}`);
      setMeta("meta[name='twitter:image']", 'name', 'twitter:image', socialImage);
      setMeta("meta[name='twitter:image:alt']", 'name', 'twitter:image:alt', `${brandName} — imóveis${city ? ` em ${city}` : ''}`);
    }

    const canonical = document.head.querySelector<HTMLLinkElement>("link[rel='canonical']") || document.createElement('link');
    canonical.setAttribute('rel', 'canonical');
    canonical.setAttribute('href', canonicalUrl);
    if (!canonical.parentNode) document.head.appendChild(canonical);

    const structuredData = document.head.querySelector<HTMLScriptElement>('#site-structured-data') || document.createElement('script');
    structuredData.id = 'site-structured-data';
    structuredData.type = 'application/ld+json';
    structuredData.textContent = JSON.stringify({
      '@context': 'https://schema.org',
      '@type': 'RealEstateAgent',
      '@id': `${canonicalUrl}#organization`,
      name: brandName,
      url: canonicalUrl,
      logo: toAbsoluteUrl(tenant.logo_url || tenant.logo),
      image: socialImage,
      description,
      telephone: tenant.tenant_phone || tenant.contact_phone,
      email: tenant.contact_email,
      address: tenant.endereco
        ? { '@type': 'PostalAddress', streetAddress: tenant.endereco, addressLocality: city, addressRegion: isExclusiva ? 'MG' : undefined, addressCountry: 'BR' }
        : undefined,
      areaServed: city ? { '@type': 'City', name: city } : undefined,
      priceRange: '$$'
    });
    if (!structuredData.parentNode) document.head.appendChild(structuredData);
  }, [properties, tenant]);

  useEffect(() => {
    if (typeof window === 'undefined') return;

    const params = new URLSearchParams();
    if (searchTerm) params.set('q', searchTerm);
    if (businessType) params.set('business', businessType);
    if (propertyType) params.set('type', propertyType);
    if (propertyCode) params.set('code', propertyCode);
    if (selectedCity) params.set('city', selectedCity);
    if (selectedNeighborhood) params.set('neighborhood', selectedNeighborhood);
    if (maxPrice) params.set('max_price', maxPrice);
    if (sortBy && sortBy !== 'preco_desc') params.set('sort', sortBy);
    if (currentPage > 1) params.set('page', String(currentPage));

    const nextUrl = params.toString()
      ? `${window.location.pathname}?${params.toString()}`
      : window.location.pathname;

    window.history.replaceState(window.history.state, '', nextUrl);
  }, [searchTerm, businessType, propertyType, propertyCode, selectedCity, selectedNeighborhood, maxPrice, sortBy, currentPage]);

  useEffect(() => {
    setCurrentPage(1);
  }, [searchTerm, businessType, propertyType, propertyCode, selectedCity, selectedNeighborhood, maxPrice, sortBy]);

  useEffect(() => {
    if (typeof window === 'undefined' || loading || restoredReturnStateRef.current) return;

    try {
      const rawState = sessionStorage.getItem(PORTAL_RETURN_STATE_KEY);
      if (!rawState) return;

      const state = JSON.parse(rawState) as { path?: string; scrollY?: number };
      const currentPath = `${window.location.pathname}${window.location.search}`;

      if (state.path === currentPath && typeof state.scrollY === 'number') {
        restoredReturnStateRef.current = true;
        requestAnimationFrame(() => {
          window.scrollTo({ top: state.scrollY, behavior: 'auto' });
          sessionStorage.removeItem(PORTAL_RETURN_STATE_KEY);
        });
      }
    } catch {
      sessionStorage.removeItem(PORTAL_RETURN_STATE_KEY);
    }
  }, [loading]);

  useEffect(() => {
    if (typeof window === 'undefined') return;
    try {
      const savedName = localStorage.getItem('portal_lead_name');
      const savedPhone = localStorage.getItem('portal_lead_phone');
      if (savedName) setLeadName(savedName);
      if (savedPhone) setLeadPhone(savedPhone);
    } catch {}
  }, []);

  const filteredProperties = useMemo(() => {
    return properties
      .filter((property) => {
      const term = searchTerm.toLowerCase().replace(/\s+/g, ' ').trim();
      const termCompact = term.replace(/\s/g, '');
      const codeHaystack = [
        property.codigo,
        property.codigo_imovel,
        property.referencia,
        property.referencia_imovel,
      ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase();
      const codeHaystackCompact = codeHaystack.replace(/\s/g, '');
      const codeTerm = propertyCode.toLowerCase().replace(/\s+/g, '').trim();
      const matchesSearch = !searchTerm
        || property.titulo?.toLowerCase().includes(term)
        || property.endereco_publico?.toLowerCase().includes(term)
        || property.bairro?.toLowerCase().includes(term)
        || property.cidade?.toLowerCase().includes(term)
        || property.tipo_imovel?.toLowerCase().includes(term)
        || (!!term && codeHaystack.includes(term))
        || (!!termCompact && termCompact.length >= 2 && codeHaystackCompact.includes(termCompact));

      const purpose = getPurposeKind(property);
      const matchesBusiness = !businessType
        || (businessType === 'venda' && (purpose === 'venda' || purpose === 'venda_aluguel'))
        || (businessType === 'aluguel' && (purpose === 'aluguel' || purpose === 'venda_aluguel'));
      const matchesType = !propertyType || property.tipo_imovel?.toLowerCase().includes(propertyType.toLowerCase());
      const matchesCode = !codeTerm || codeHaystackCompact.includes(codeTerm);
      const matchesCity = !selectedCity || property.cidade === selectedCity;
      const matchesNeighborhood = !selectedNeighborhood || property.bairro === selectedNeighborhood;
      const propertyPrice = getPriceValue(property);
      const matchesPrice = !maxPrice || (propertyPrice > 0 && propertyPrice <= Number(maxPrice));

      return matchesSearch && matchesBusiness && matchesType && matchesCode && matchesCity && matchesNeighborhood && matchesPrice;
    })
      .sort((a, b) => {
        const aPrice = getPriceValue(a);
        const bPrice = getPriceValue(b);
        const comparePrice = (direction: 'asc' | 'desc') => {
          if (!aPrice && !bPrice) return 0;
          if (!aPrice) return 1;
          if (!bPrice) return -1;
          return direction === 'desc' ? bPrice - aPrice : aPrice - bPrice;
        };
        const compareTitle = (direction: 'asc' | 'desc') => {
          const result = (a.titulo || '').localeCompare(b.titulo || '', 'pt-BR', { sensitivity: 'base' });
          return direction === 'desc' ? -result : result;
        };
        const compareArea = (direction: 'asc' | 'desc') => {
          const aArea = getDisplayArea(a) || 0;
          const bArea = getDisplayArea(b) || 0;
          if (!aArea && !bArea) return 0;
          if (!aArea) return 1;
          if (!bArea) return -1;
          return direction === 'desc' ? bArea - aArea : aArea - bArea;
        };

        if (sortBy === 'titulo_asc') return compareTitle('asc');
        if (sortBy === 'titulo_desc') return compareTitle('desc');
        if (sortBy === 'area_desc') return compareArea('desc');
        if (sortBy === 'area_asc') return compareArea('asc');
        if (sortBy === 'preco_desc') {
          return comparePrice('desc');
        }

        return comparePrice('asc');
      });
  }, [properties, searchTerm, businessType, propertyType, propertyCode, selectedCity, selectedNeighborhood, maxPrice, sortBy]);

  const totalPages = Math.max(1, Math.ceil(filteredProperties.length / PROPERTIES_PER_PAGE));
  const paginatedProperties = useMemo(() => {
    const start = (currentPage - 1) * PROPERTIES_PER_PAGE;
    return filteredProperties.slice(start, start + PROPERTIES_PER_PAGE);
  }, [currentPage, filteredProperties]);
  const cityCount = useMemo(
    () => new Set(properties.map((property) => property.cidade).filter(Boolean)).size,
    [properties],
  );
  const cityOptions = useMemo(
    () => Array.from(new Set(properties.map((property) => property.cidade).filter(Boolean))).sort((a, b) => a.localeCompare(b, 'pt-BR')),
    [properties],
  );
  const neighborhoodOptions = useMemo(
    () => Array.from(new Set(properties
      .filter((property) => !selectedCity || property.cidade === selectedCity)
      .map((property) => property.bairro)
      .filter(Boolean))).sort((a, b) => a.localeCompare(b, 'pt-BR')),
    [properties, selectedCity],
  );

  useEffect(() => {
    if (selectedNeighborhood && !neighborhoodOptions.includes(selectedNeighborhood)) {
      setSelectedNeighborhood('');
    }
  }, [neighborhoodOptions, selectedNeighborhood]);

  const showCatalogResults = () => {
    document.getElementById('catalogo')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };
  const heroStats = useMemo(() => ([
    {
      label: 'Imóveis no catálogo',
      value: properties.length > 0 ? `+${properties.length}` : 'Catálogo',
    },
    {
      label: 'Cidades atendidas',
      value: cityCount > 0 ? String(cityCount) : '14',
    },
    {
      label: 'Satisfação',
      value: '5★',
    },
    {
      label: 'Anos no mercado',
      value: '+15',
    },
  ]), [cityCount, properties.length]);

  useEffect(() => {
    if (currentPage > totalPages) {
      setCurrentPage(totalPages);
    }
  }, [currentPage, totalPages]);

  const tenantAboutText = useMemo(() => {
    if (tenant?.about_text?.trim()) {
      return tenant.about_text.trim();
    }

    if (tenant?.name?.toLowerCase().includes('exclusiva')) {
      return EXCLUSIVA_DEFAULT_ABOUT_TEXT;
    }

    return '';
  }, [tenant?.about_text, tenant?.name]);

  const whatsappLink = useMemo(() => {
    const phone = getMascotPhoneDigits(tenant);
    if (!phone) return '';
    const message = encodeURIComponent(`Olá! Vim pelo portal da ${tenant?.name || 'imobiliária'} e quero atendimento.`);
    return `https://wa.me/${phone}?text=${message}`;
  }, [tenant?.contact_phone, tenant?.mascot_whatsapp_phone, tenant?.name, tenant?.whatsapp_phone]);

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

  const handleFloatingActionClick = (event: React.MouseEvent<HTMLButtonElement>) => {
    if (suppressFloatingActionClickRef.current) {
      event.preventDefault();
      event.stopPropagation();
      suppressFloatingActionClickRef.current = false;
      return;
    }

    openMascotWhatsAppModal();
  };

  const closePropertyWhatsAppModal = () => {
    setLeadModalError('');
    setLeadSubmitting(false);
    setLeadVisitDateTime('');
    setLeadVisitNotes('');
    setLeadModalSource('card');
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

  const handleOpenPropertyDetail = (propertyId: number) => {
    if (typeof window !== 'undefined') {
      try {
        sessionStorage.setItem(PORTAL_RETURN_STATE_KEY, JSON.stringify({
          path: `${window.location.pathname}${window.location.search}`,
          scrollY: window.scrollY,
        }));
      } catch {}
    }

    navigate(`/portal/imovel/${propertyId}`);
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

      const controller = new AbortController();
      const timeoutId = window.setTimeout(() => controller.abort(), 12000);

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
          property_id: leadModalProperty?.id,
          property_titulo: leadModalProperty?.titulo,
          visita_data_hora: leadVisitDateTime || undefined,
          visita_observacoes: leadVisitNotes.trim() || undefined,
          origem_agendamento: leadModalProperty
            ? leadModalSource === 'mascot'
              ? 'portal_home'
              : 'portal_catalogo'
            : 'portal_home',
        }),
        signal: controller.signal,
      }).finally(() => window.clearTimeout(timeoutId));

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
                  leadVisitDateTime ? `Quero sugerir visita em ${new Date(leadVisitDateTime).toLocaleString('pt-BR')}.` : null,
                  `Localização: ${getPublicLocation(leadModalProperty)}.`,
                  `Valor anunciado: ${formatPrice(leadModalProperty)}.`,
                  leadVisitNotes.trim() ? `Observações: ${leadVisitNotes.trim()}.` : null,
                  `Link do imóvel: ${window.location.origin}/portal/imovel/${leadModalProperty.id}`,
                ].filter(Boolean).join(' ')
              : [
                  `Olá! Sou ${normalizedName}.`,
                  `Meu telefone é ${formattedPhone}.`,
                  'Acabei de me cadastrar pelo portal e gostaria de atendimento.',
                  leadVisitDateTime ? `Se possível, gostaria de visita em ${new Date(leadVisitDateTime).toLocaleString('pt-BR')}.` : null,
                  leadVisitNotes.trim() ? `Observações: ${leadVisitNotes.trim()}.` : null,
                ].filter(Boolean).join(' ')
          )}`
        : fallbackWhatsappUrl;

      if (!whatsappUrl) {
        throw new Error('O WhatsApp da imobiliária não está configurado.');
      }

      closePropertyWhatsAppModal();
      window.location.href = whatsappUrl;
    } catch (error) {
      if (error instanceof DOMException && error.name === 'AbortError') {
        setLeadModalError('A confirmação demorou mais do que o esperado. Tente novamente em alguns segundos.');
      } else {
        setLeadModalError(error instanceof Error ? error.message : 'Não foi possível registrar seu contato.');
      }
    } finally {
      setLeadSubmitting(false);
    }
  };

  const hasMascot = Boolean(tenant?.mascot_url);
  const floatingActionMetrics = useMemo(() => {
    if (typeof window === 'undefined') return getFloatingActionMetrics(1280, hasMascot, floatingActionScale);
    return getFloatingActionMetrics(window.innerWidth, hasMascot, floatingActionScale);
  }, [floatingActionScale, hasMascot]);

  useEffect(() => {
    if (typeof window === 'undefined') return;

    try {
      const savedScale = Number(localStorage.getItem(MASCOT_SCALE_STORAGE_KEY));
      if (Number.isFinite(savedScale) && savedScale > 0) {
        setFloatingActionScale(clampMascotScale(savedScale));
      }
    } catch {}
  }, []);

  useEffect(() => {
    if (typeof window === 'undefined') return;
    const defaultPos = getFloatingActionDefaultPosition(window.innerWidth, window.innerHeight, hasMascot, floatingActionScale);

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
      setFloatingActionPos(clampFloatingActionPosition(parsed.x, parsed.y, window.innerWidth, window.innerHeight, hasMascot, floatingActionScale));
    } catch {
      setFloatingActionPos(defaultPos);
    }
  }, [hasMascot]);

  useEffect(() => {
    if (typeof window === 'undefined') return;

    setFloatingActionPos((current) => clampFloatingActionPosition(
      current.x,
      current.y,
      window.innerWidth,
      window.innerHeight,
      hasMascot,
      floatingActionScale,
    ));

    try {
      localStorage.setItem(MASCOT_SCALE_STORAGE_KEY, String(floatingActionScale));
    } catch {}
  }, [floatingActionScale, hasMascot]);

  useEffect(() => {
    const onResize = () => {
      setFloatingActionPos((current) => clampFloatingActionPosition(
        current.x,
        current.y,
        window.innerWidth,
        window.innerHeight,
        hasMascot,
        floatingActionScale,
      ));
    };

    window.addEventListener('resize', onResize);
    return () => window.removeEventListener('resize', onResize);
  }, [floatingActionScale, hasMascot]);

  const handleFloatingActionScale = (direction: 'down' | 'up') => {
    setFloatingActionScale((current) => clampMascotScale(
      current + (direction === 'down' ? -MASCOT_SCALE_STEP : MASCOT_SCALE_STEP),
    ));
  };

  const handleFloatingActionPointerDown = (e: React.PointerEvent<HTMLButtonElement>) => {
    const startX = e.clientX;
    const startY = e.clientY;
    const offsetX = e.clientX - floatingActionPosRef.current.x;
    const offsetY = e.clientY - floatingActionPosRef.current.y;
    let dragged = false;
    suppressFloatingActionClickRef.current = false;

    const onMove = (event: PointerEvent) => {
      const nextPos = clampFloatingActionPosition(
        event.clientX - offsetX,
        event.clientY - offsetY,
        window.innerWidth,
        window.innerHeight,
        hasMascot,
        floatingActionScale,
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
        suppressFloatingActionClickRef.current = true;
        const sideInset = 12;
        const snappedX = floatingActionPosRef.current.x + (floatingActionMetrics.width / 2) >= (window.innerWidth / 2)
          ? window.innerWidth - floatingActionMetrics.width - sideInset
          : sideInset;
        const finalPos = clampFloatingActionPosition(
          snappedX,
          floatingActionPosRef.current.y,
          window.innerWidth,
          window.innerHeight,
          hasMascot,
          floatingActionScale,
        );
        setFloatingActionPos(finalPos);
        try {
          localStorage.setItem('portal_mascot_pos', JSON.stringify(finalPos));
        } catch {}
      }
    };

    window.addEventListener('pointermove', onMove, { passive: false });
    window.addEventListener('pointerup', onUp);
    window.addEventListener('pointercancel', onUp);
  };

  const renderSortButton = (label: string, asc: PortalSortKey, desc: PortalSortKey, align: 'left' | 'right' = 'left') => {
    const active = sortBy === asc || sortBy === desc;
    const isDesc = sortBy === desc;

    return (
      <button
        type="button"
        onClick={() => setSortBy(sortBy === desc ? asc : desc)}
        className={`inline-flex items-center gap-1.5 font-semibold ${align === 'right' ? 'justify-end text-right' : ''} ${active ? 'text-slate-900' : 'text-slate-500 hover:text-slate-900'}`}
      >
        {label}
        <ChevronDown className={`h-3.5 w-3.5 transition ${active && !isDesc ? 'rotate-180' : ''}`} />
      </button>
    );
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
    <div className="portal-public min-h-screen" style={{ backgroundColor: portalTemplate.shell }}>
      <div className="flex flex-col">
      <header className={`sticky top-0 z-40 order-1 border-b ${portalTemplate.header}`}>
        <div className="mx-auto max-w-7xl px-4 py-3.5 lg:px-8">
          <div className="flex items-center justify-between gap-3">
          <div className="flex items-center gap-3 min-w-0">
            {tenant?.logo_url || tenant?.logo ? (
              <img src={tenant.logo_url || tenant.logo} alt={tenant?.name || 'Logo'} className="h-14 w-14 rounded-full bg-white object-contain p-1.5 sm:h-16 sm:w-16 md:h-20 md:w-20" />
            ) : (
              <div className={`flex h-14 w-14 items-center justify-center rounded-full border text-sm font-semibold sm:h-16 sm:w-16 md:h-20 md:w-20 md:text-lg ${isLightHeader ? 'border-slate-200 bg-slate-100 text-slate-900' : 'border-white/30 bg-white/10 text-white'}`}>
                {(tenant?.name || 'IM').slice(0, 2).toUpperCase()}
              </div>
            )}
            <div className="min-w-0">
              <p className={`truncate text-xs uppercase tracking-[0.15em] sm:text-sm ${headerTextClass}`}>{tenant?.name || 'Imobiliária'}</p>
              <p className={`truncate text-[10px] uppercase tracking-[0.12em] sm:text-[11px] ${isLightHeader ? 'text-slate-500' : 'text-white/65'}`}>{portalTemplate.name}</p>
            </div>
          </div>
          <div className="hidden lg:flex items-center gap-6">
            <a href="#catalogo" className={`text-[11px] uppercase tracking-[0.16em] ${headerMutedClass}`}>Catálogo</a>
            <a href="#servicos" className={`text-[11px] uppercase tracking-[0.16em] ${headerMutedClass}`}>Serviços</a>
            <a href="/portal/simulacao" className={`text-[11px] uppercase tracking-[0.16em] ${headerMutedClass}`}>Simulação</a>
            <div className="relative" onMouseEnter={() => setVenderOpen(true)} onMouseLeave={() => setVenderOpen(false)}>
              <button type="button" className={`flex items-center gap-1 text-[11px] uppercase tracking-[0.16em] ${headerMutedClass}`}>
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
            <a href="#contato" className={`text-[11px] uppercase tracking-[0.16em] ${headerMutedClass}`}>Contato</a>
            <button
              type="button"
              onClick={() => navigate('/login')}
              className={`rounded-full border px-4 py-2 text-xs uppercase tracking-[0.12em] ${isLightHeader ? 'border-slate-300 text-slate-800' : 'border-white/30 text-white'}`}
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
              className={`rounded-full border px-4 py-2 text-xs uppercase tracking-[0.12em] ${isLightHeader ? 'border-slate-300 text-slate-800' : 'border-white/30 text-white'}`}
            >
              Explorar
            </button>
          </div>
        </div>
          <div className="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-4 lg:hidden">
            <button
              type="button"
              onClick={() => document.getElementById('catalogo')?.scrollIntoView({ behavior: 'smooth' })}
              className="inline-flex min-h-11 items-center justify-center rounded-full border border-white/15 bg-white/8 px-4 py-2.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-white"
            >
              Explorar
            </button>
            <button
              type="button"
              onClick={() => navigate('/portal/simulacao')}
              className="inline-flex min-h-11 items-center justify-center rounded-full px-4 py-2.5 text-[11px] font-semibold uppercase tracking-[0.08em]"
              style={{ backgroundColor: secondary, color: '#111827' }}
            >
              Simular
            </button>
            <button
              type="button"
              onClick={() => navigate('/portal/vender')}
              className="inline-flex min-h-11 items-center justify-center rounded-full border border-white/15 bg-white/8 px-4 py-2.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-white"
            >
              Vender
            </button>
            <button
              type="button"
              onClick={() => navigate('/login')}
              className="inline-flex min-h-11 items-center justify-center rounded-full border border-white/15 bg-white/8 px-4 py-2.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-white"
            >
              Entrar
            </button>
          </div>
        </div>
      </header>

      <section
        id="property-search-panel"
        className={isSearchDocked
          ? 'fixed left-4 top-24 z-50 w-[19rem] bg-transparent'
          : 'relative z-10 order-3 bg-[#f4efe8] px-4 py-4 sm:py-5 lg:px-8'}
        aria-label="Busca de imóveis"
      >
        <div className={isSearchDocked
          ? 'rounded-2xl border border-slate-200/80 bg-white px-4 shadow-[0_18px_40px_rgba(15,23,42,0.18)]'
          : 'mx-auto max-w-7xl rounded-2xl border border-slate-200/80 bg-white px-4 shadow-[0_12px_28px_rgba(15,23,42,0.08)] sm:px-5'}>
          <div className="flex gap-2 overflow-x-auto py-3">
            <button
              type="button"
              onClick={() => {
                setSearchMode('quick');
                setPropertyCode('');
              }}
              className={`inline-flex min-h-10 shrink-0 items-center gap-2 rounded-full border px-4 py-2 text-xs font-semibold uppercase tracking-[0.08em] transition-colors sm:px-5 ${searchMode === 'quick' ? 'border-slate-800 text-white shadow-sm' : 'border-slate-200 bg-slate-50 text-slate-600 hover:border-slate-300 hover:text-slate-900'}`}
              style={searchMode === 'quick' ? { backgroundColor: '#1f2937' } : undefined}
            >
              <Search className="h-4 w-4" />
              Busca rápida
            </button>
            <button
              type="button"
              onClick={() => {
                setSearchMode('code');
                setBusinessType('');
                setPropertyType('');
                setSelectedCity('');
                setSelectedNeighborhood('');
                setMaxPrice('');
              }}
              className={`inline-flex min-h-10 shrink-0 items-center gap-2 rounded-full border px-4 py-2 text-xs font-semibold uppercase tracking-[0.08em] transition-colors sm:px-5 ${searchMode === 'code' ? 'border-slate-800 text-white shadow-sm' : 'border-slate-200 bg-slate-50 text-slate-600 hover:border-slate-300 hover:text-slate-900'}`}
              style={searchMode === 'code' ? { backgroundColor: '#1f2937' } : undefined}
            >
              <Hash className="h-4 w-4" />
              Busca por código
            </button>
          </div>

          {searchMode === 'quick' ? (
            <form
              className={isSearchDocked ? 'grid gap-3 py-3' : 'grid gap-3 py-4 sm:grid-cols-2 lg:grid-cols-[1fr_1fr_1.1fr_1.1fr_1.1fr_auto] lg:items-end'}
              onSubmit={(event) => {
                event.preventDefault();
                showCatalogResults();
              }}
            >
              <label className="grid gap-1.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-600">
                Finalidade
                <select value={businessType} onChange={(event) => setBusinessType(event.target.value)} className="h-11 rounded-lg border border-slate-200 bg-white px-3 text-sm font-normal normal-case tracking-normal text-slate-900 outline-none focus:border-slate-400">
                  <option value="">Geral</option>
                  <option value="venda">Comprar</option>
                  <option value="aluguel">Alugar</option>
                </select>
              </label>
              <label className="grid gap-1.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-600">
                Tipo de imóvel
                <select value={propertyType} onChange={(event) => setPropertyType(event.target.value)} className="h-11 rounded-lg border border-slate-200 bg-white px-3 text-sm font-normal normal-case tracking-normal text-slate-900 outline-none focus:border-slate-400">
                  {PROPERTY_TYPES.map((option) => (
                    <option key={option.value} value={option.value}>{option.value ? option.label : 'Geral'}</option>
                  ))}
                </select>
              </label>
              <label className="grid gap-1.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-600">
                Cidade
                <select value={selectedCity} onChange={(event) => setSelectedCity(event.target.value)} className="h-11 rounded-lg border border-slate-200 bg-white px-3 text-sm font-normal normal-case tracking-normal text-slate-900 outline-none focus:border-slate-400">
                  <option value="">Geral</option>
                  {cityOptions.map((city) => <option key={city} value={city}>{city}</option>)}
                </select>
              </label>
              <label className="grid gap-1.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-600">
                Bairro
                <select value={selectedNeighborhood} onChange={(event) => setSelectedNeighborhood(event.target.value)} className="h-11 rounded-lg border border-slate-200 bg-white px-3 text-sm font-normal normal-case tracking-normal text-slate-900 outline-none focus:border-slate-400">
                  <option value="">Geral</option>
                  {neighborhoodOptions.map((neighborhood) => <option key={neighborhood} value={neighborhood}>{neighborhood}</option>)}
                </select>
              </label>
              <label className="grid gap-1.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-600">
                Faixa de preço
                <select value={maxPrice} onChange={(event) => setMaxPrice(event.target.value)} className="h-11 rounded-lg border border-slate-200 bg-white px-3 text-sm font-normal normal-case tracking-normal text-slate-900 outline-none focus:border-slate-400">
                  <option value="">Geral</option>
                  <option value="300000">Até R$ 300 mil</option>
                  <option value="500000">Até R$ 500 mil</option>
                  <option value="750000">Até R$ 750 mil</option>
                  <option value="1000000">Até R$ 1 milhão</option>
                  <option value="2000000">Até R$ 2 milhões</option>
                  <option value="5000000">Até R$ 5 milhões</option>
                </select>
              </label>
              <button type="submit" className="inline-flex h-11 items-center justify-center gap-2 rounded-lg px-6 text-sm font-semibold text-white shadow-sm transition hover:brightness-105 lg:min-w-28" style={{ backgroundColor: secondary }}>
                <Search className="h-4 w-4" />
                Buscar
              </button>
            </form>
          ) : (
            <form
              className={isSearchDocked ? 'grid gap-3 py-3' : 'grid gap-3 py-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end'}
              onSubmit={(event) => {
                event.preventDefault();
                showCatalogResults();
              }}
            >
              <label className="grid gap-1.5 text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-600">
                Código do imóvel
                <div className="relative">
                  <Hash className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                  <input
                    type="search"
                    value={propertyCode}
                    onChange={(event) => setPropertyCode(event.target.value)}
                    placeholder="Digite o código ou a referência"
                    className="h-11 w-full rounded-lg border border-slate-200 bg-white pl-10 pr-3 text-base font-normal normal-case tracking-normal text-slate-900 outline-none placeholder:text-slate-400 focus:border-slate-400 sm:text-sm"
                  />
                </div>
              </label>
              <button type="submit" className="inline-flex h-11 items-center justify-center gap-2 rounded-lg px-6 text-sm font-semibold text-white shadow-sm transition hover:brightness-105 sm:min-w-32" style={{ backgroundColor: secondary }}>
                <Search className="h-4 w-4" />
                Buscar
              </button>
            </form>
          )}
        </div>
      </section>

      <section ref={heroRef} className="relative order-2 overflow-hidden" style={{ background: templateHeroBackground }}>
        {/* Parallax background */}
        <div className="absolute inset-0 overflow-hidden pointer-events-none">
          <img
            src="https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?auto=format&fit=crop&w=1920&q=80"
            alt="Imóveis de alto padrão"
            className="parallax-bg absolute left-0 w-full object-cover opacity-60"
            style={{ height: '160%', top: '-30%', willChange: 'transform' }}
          />
        </div>
        <div className="absolute inset-0 bg-gradient-to-r from-black/46 via-black/24 to-black/30" />

        <div className={heroGridClass}>
          <div className={`max-w-2xl ${portalTemplate.heroPanel}`}>
            <div className={`inline-flex w-full items-center justify-center gap-2 rounded-full border px-3 py-1.5 text-[10px] uppercase tracking-[0.2em] sm:w-auto sm:justify-start sm:text-[11px] sm:tracking-[0.22em] ${isLightHeroPanel ? 'border-slate-200 bg-slate-50 text-slate-600' : 'border-white/20 bg-white/10 text-white/85'}`}>
              <span className="h-2 w-2 rounded-full" style={{ backgroundColor: secondary }} />
              {portalTemplate.accentLabel}
            </div>
            <h1 className={`mt-3 leading-[1.05] ${isCondensedHero ? 'text-3xl sm:text-4xl md:text-[2.7rem]' : 'text-[2.15rem] sm:text-5xl md:text-6xl'} ${heroTextClass}`}>Imóveis extraordinários para estilos de vida únicos</h1>
            <p className={`max-w-2xl text-sm leading-6 md:text-base ${isCondensedHero ? 'mt-2' : 'mt-4'} ${heroMutedClass}`}>{tenant?.slogan || 'Curadoria de residências e investimentos em localizações de alto potencial.'}</p>
            <div className={`mt-4 flex flex-wrap gap-2.5 ${isCondensedHero ? 'hidden' : ''}`}>
              {[
                'Compra, venda e locação',
                'Atendimento consultivo',
                'Avaliação gratuita',
              ].map((item) => (
                <span
                  key={item}
                  className={`inline-flex items-center rounded-full border px-3 py-1.5 text-[10px] font-medium uppercase tracking-[0.12em] sm:text-[11px] sm:tracking-[0.14em] ${isLightHeroPanel ? 'border-slate-200 bg-slate-50 text-slate-600' : 'border-white/18 bg-white/8 text-white/78'}`}
                >
                  {item}
                </span>
              ))}
            </div>
            <div className={`${isCondensedHero ? 'mt-4' : 'mt-6'} grid gap-2.5 sm:flex sm:flex-wrap`}>
              <button
                type="button"
                onClick={() => document.getElementById('catalogo')?.scrollIntoView({ behavior: 'smooth' })}
                className="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-full px-4 py-3 text-sm font-semibold sm:min-h-0 sm:w-auto"
                style={{ backgroundColor: secondary, color: '#111827' }}
              >
                Ver coleção
                <ArrowUpRight className="w-4 h-4" />
              </button>
              <button
                type="button"
                onClick={() => navigate('/portal/vender')}
                className={`inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-full border px-4 py-3 text-sm font-semibold transition-colors sm:min-h-0 sm:w-auto ${isLightHeroPanel ? 'border-slate-300 bg-white text-slate-800 hover:bg-slate-50' : 'border-white/55 bg-white/10 text-white hover:bg-white/18'}`}
              >
                <TrendingUp className="w-4 h-4" />
                Quero vender
              </button>
            </div>
            <div className={`mt-7 grid gap-2.5 sm:grid-cols-3 ${isCondensedHero ? 'hidden' : ''}`}>
              {[
                { value: properties.length > 0 ? `${properties.length}+` : 'Catálogo', label: 'imóveis com fotos e detalhes' },
                { value: cityCount > 0 ? String(cityCount) : '14', label: 'cidades com operação ativa' },
                { value: '48h', label: 'para retorno da avaliação' },
              ].map((item) => (
                <div key={item.label} className={`rounded-2xl border px-4 py-3 backdrop-blur-sm ${isLightHeroPanel ? 'border-slate-200 bg-slate-50' : 'border-white/15 bg-[#000000c2]'}`}>
                  <p className={`text-lg font-semibold ${heroTextClass}`}>{item.value}</p>
                  <p className={`mt-1 text-[11px] uppercase tracking-[0.12em] ${isLightHeroPanel ? 'text-slate-500' : 'text-white/68'}`}>{item.label}</p>
                </div>
              ))}
            </div>
          </div>

          <div className={`hidden content-start grid-cols-2 gap-2.5 md:gap-3 ${portalTemplate.heroMode === 'compact' || portalTemplate.heroMode === 'search' ? 'lg:hidden' : 'lg:grid'}`}>
            {heroStats.map((stat, index) => (
              <div
                key={stat.label}
                className={`rounded-[1.75rem] border p-4 md:p-5 text-white backdrop-blur-md shadow-[0_12px_32px_rgba(0,0,0,0.24)] ${index === 0 ? 'bg-[#000000c2] border-white/35 col-span-2' : 'bg-[#000000c2] border-white/25'}`}
              >
                <p className={`${index === 0 ? 'text-3xl md:text-4xl' : 'text-2xl md:text-3xl'} font-light`}>{stat.value}</p>
                <p className="mt-1 text-[11px] uppercase tracking-[0.16em] text-white/75">{stat.label}</p>
                {index === 0 && (
                  <p className="mt-3 max-w-sm text-sm text-white/78">
                    Explore imóveis com fotos, localização pública e contato imediato por WhatsApp.
                  </p>
                )}
              </div>
            ))}
          </div>
        </div>
      </section>

      </div>

      <section id="catalogo" className={`mx-auto max-w-7xl px-4 py-6 lg:px-8 lg:py-8 ${isSearchDocked ? 'xl:pl-[21rem]' : ''}`}>
        <div className="mb-3 flex flex-col items-start justify-between gap-3 sm:flex-row sm:items-end">
          <div>
            <p className="text-[11px] uppercase tracking-[0.2em] text-slate-500">Catálogo</p>
            <h2 className="mt-1 text-xl text-slate-900 sm:text-2xl">
              {filteredProperties.length > 0
                ? `${filteredProperties.length} ${filteredProperties.length !== 1 ? 'imóveis' : 'imóvel'} encontrado${filteredProperties.length !== 1 ? 's' : ''}`
                : 'Imóveis disponíveis'}
            </h2>
            {filteredProperties.length > 0 && (
              <p className="mt-1 text-sm text-slate-500">
                Página {currentPage} de {totalPages}
              </p>
            )}
          </div>
        </div>

        {portalTemplate.catalogMode === 'datatable' ? (
          <div className="mt-2 overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm">
            <div className="border-b border-slate-100 bg-slate-50/80 px-3 py-3 sm:px-4">
              <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                  <p className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-500">Tabela inteligente</p>
                  <p className="mt-0.5 text-sm text-slate-600">Pesquisa global ativa e ordenação pelos cabeçalhos.</p>
                </div>
                <select
                  value={sortBy}
                  onChange={(event) => setSortBy(event.target.value as PortalSortKey)}
                  className="h-10 rounded-lg border border-slate-200 bg-white px-3 text-sm font-semibold text-slate-800"
                >
                  <option value="preco_desc">Maior preço</option>
                  <option value="preco_asc">Menor preço</option>
                  <option value="titulo_asc">Descrição A-Z</option>
                  <option value="titulo_desc">Descrição Z-A</option>
                  <option value="area_desc">Maior área</option>
                  <option value="area_asc">Menor área</option>
                </select>
              </div>
            </div>
            <div className="w-full overflow-x-auto md:overflow-visible">
              <table className="w-full table-fixed text-left text-sm md:table-auto">
                <thead className="bg-slate-100 text-[11px] uppercase tracking-[0.12em] text-slate-500">
                  <tr>
                    <th className="w-[82px] px-2 py-3 sm:w-[116px] sm:px-4">Imagem</th>
                    <th className="px-2 py-3 sm:px-4">{renderSortButton('Descrição', 'titulo_asc', 'titulo_desc')}</th>
                    <th className="hidden px-4 py-3 md:table-cell">Finalidade</th>
                    <th className="hidden px-4 py-3 lg:table-cell">Localização</th>
                    <th className="hidden px-4 py-3 md:table-cell">{renderSortButton('Área', 'area_asc', 'area_desc')}</th>
                    <th className="hidden px-4 py-3 lg:table-cell">Quartos</th>
                    <th className="w-[118px] px-2 py-3 text-right sm:w-[170px] sm:px-4">{renderSortButton('Valor', 'preco_asc', 'preco_desc', 'right')}</th>
                    <th className="hidden px-4 py-3 text-right md:table-cell">Ações</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-100">
                  {paginatedProperties.map((property) => {
                    const images = normalizeImages(property);
                    const displayArea = getDisplayArea(property);
                    const description = getPropertyDescription(property);
                    return (
                      <tr key={property.id} className="align-top hover:bg-slate-50">
                        <td className="px-2 py-3 sm:px-4">
                          <button type="button" onClick={() => handleOpenPropertyDetail(property.id)} className="block overflow-hidden rounded-md border border-slate-200">
                            <img src={images[0]} alt={property.titulo} className="h-16 w-[70px] object-cover sm:h-16 sm:w-24" loading="lazy" />
                          </button>
                        </td>
                        <td className="min-w-0 px-2 py-3 sm:px-4">
                          <button type="button" onClick={() => handleOpenPropertyDetail(property.id)} className="block w-full text-left">
                            <span className="line-clamp-2 text-sm font-semibold leading-snug text-slate-900 sm:text-base">{property.titulo}</span>
                            <span className="mt-1 line-clamp-2 text-xs leading-snug text-slate-500 sm:text-sm">{description}</span>
                            <span className="mt-1 block text-[11px] font-semibold uppercase tracking-[0.08em] text-slate-400">
                              {property.codigo || property.codigo_imovel || property.tipo_imovel}
                            </span>
                          </button>
                        </td>
                        <td className="hidden px-4 py-3 text-slate-700 md:table-cell">{getPurpose(property)}</td>
                        <td className="hidden px-4 py-3 text-slate-600 lg:table-cell">{getPublicLocation(property)}</td>
                        <td className="hidden px-4 py-3 text-slate-700 md:table-cell">{formatArea(displayArea)}m²</td>
                        <td className="hidden px-4 py-3 text-slate-700 lg:table-cell">{property.quartos || property.dormitorios || '--'}</td>
                        <td className="px-2 py-3 text-right text-sm font-bold leading-snug sm:px-4 sm:text-base" style={{ color: primary }}>{formatPrice(property)}</td>
                        <td className="hidden px-4 py-3 md:table-cell">
                          <div className="flex justify-end gap-2">
                            <button type="button" onClick={() => handleOpenPropertyDetail(property.id)} className="rounded-md border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700">Detalhes</button>
                            <button type="button" onClick={() => openPropertyWhatsAppModal(property)} className="rounded-md px-3 py-2 text-xs font-semibold text-white" style={{ backgroundColor: primary }}>WhatsApp</button>
                          </div>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            </div>
          </div>
        ) : (
        <div className={catalogGridClass}>
          {paginatedProperties.map((property, index) => {
            const images = normalizeImages(property);
            const activePhotoIndex = Math.min(catalogPhotoIndexes[property.id] ?? 0, Math.max(0, images.length - 1));
            const activeImage = images[activePhotoIndex] || images[0];
            const displayArea = getDisplayArea(property);
            const parkingSpots = property.garagem ?? property.vagas ?? '--';
            return (
              <motion.article
                key={property.id}
                className={`group ${portalTemplate.card} ${portalTemplate.catalogMode === 'list' ? 'grid md:grid-cols-[280px_1fr]' : ''}`}
                initial={{ opacity: 0, y: 12 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ duration: 0.35, delay: Math.min(index * 0.04, 0.2) }}
              >
                <div className={`relative ${portalTemplate.catalogMode === 'list' ? 'h-64 md:h-full' : portalTemplate.cardImage}`}>
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
                        className="absolute left-3 top-1/2 -translate-y-1/2 flex h-9 w-9 items-center justify-center rounded-full bg-white/88 text-slate-700 shadow-sm backdrop-blur-sm sm:h-8 sm:w-8"
                      >
                        <ChevronLeft className="h-4 w-4" />
                      </button>
                      <button
                        type="button"
                        aria-label="Próxima foto"
                        onClick={() => handleCatalogPhotoChange(property.id, 'next', images.length)}
                        className="absolute right-3 top-1/2 -translate-y-1/2 flex h-9 w-9 items-center justify-center rounded-full bg-white/88 text-slate-700 shadow-sm backdrop-blur-sm sm:h-8 sm:w-8"
                      >
                        <ChevronRight className="h-4 w-4" />
                      </button>
                      <div className="property-gallery-scrollbar absolute bottom-3 left-3 right-3 flex items-center gap-1.5 overflow-x-auto overflow-y-hidden rounded-2xl bg-black/32 px-2 py-2 backdrop-blur-md">
                        {images.map((thumbImage, imageIndex) => (
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
                <div className="p-4 sm:p-5">
                  <h3 className="line-clamp-2 text-base font-medium leading-snug text-slate-900 sm:line-clamp-1 sm:text-lg">{property.titulo}</h3>
                  <p className="mt-1 flex items-start gap-1.5 text-xs text-slate-500"><MapPin className="mt-0.5 w-3.5 h-3.5 shrink-0" /><span className="line-clamp-2">{getPublicLocation(property)}</span></p>
                  <p className="mt-3 text-xl sm:text-2xl" style={{ color: primary }}>{formatPrice(property)}</p>
                  <div className="mt-3 grid grid-cols-2 gap-x-3 gap-y-2 text-xs text-slate-600 sm:grid-cols-4 sm:text-[11px]">
                    <p className="flex items-center gap-1"><BedDouble className="w-3.5 h-3.5" />{property.quartos || property.dormitorios || '--'}</p>
                    <p className="flex items-center gap-1"><Bath className="w-3.5 h-3.5" />{property.banheiros || '--'}</p>
                    <p className="flex items-center gap-1"><Square className="w-3.5 h-3.5" />{formatArea(displayArea)}m²</p>
                    <p className="flex items-center gap-1"><Car className="w-3.5 h-3.5" />{parkingSpots}</p>
                  </div>
                  <div className="mt-4 grid gap-2 sm:grid-cols-2">
                    <button
                      type="button"
                      onClick={() => handleOpenPropertyDetail(property.id)}
                      className="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl border border-slate-900/15 px-4 py-3 text-sm font-semibold text-slate-800"
                    >
                      Ver detalhes
                      <ArrowUpRight className="w-4 h-4" />
                    </button>
                    <button
                      type="button"
                      onClick={() => openPropertyWhatsAppModal(property)}
                      disabled={!(tenant?.tenant_phone || tenant?.contact_phone)}
                      className="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60"
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
        )}

        {filteredProperties.length === 0 && (
          <div className="mt-8 rounded-2xl border border-dashed border-black/20 bg-white/70 px-6 py-10 text-center text-sm text-slate-600">
            Nenhum imóvel encontrado com os filtros informados.
          </div>
        )}

        {filteredProperties.length > PROPERTIES_PER_PAGE && (
          <div className="mt-8 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-center text-sm text-slate-500 sm:text-left">
              Exibindo {(currentPage - 1) * PROPERTIES_PER_PAGE + 1} a {Math.min(currentPage * PROPERTIES_PER_PAGE, filteredProperties.length)} de {filteredProperties.length} imóveis
            </p>
            <div className="grid w-full grid-cols-2 gap-2 sm:flex sm:w-auto sm:flex-wrap sm:items-center sm:justify-end">
              <button
                type="button"
                onClick={() => setCurrentPage((page) => Math.max(1, page - 1))}
                disabled={currentPage === 1}
                className="inline-flex min-h-11 items-center justify-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 disabled:cursor-not-allowed disabled:opacity-50"
              >
                <ChevronLeft className="h-4 w-4" />
                Anterior
              </button>
              <div className="col-span-2 flex max-w-full items-center justify-center gap-1 overflow-x-auto pb-1 sm:col-span-1">
                {Array.from({ length: totalPages }, (_, index) => index + 1)
                  .filter((page) => page === 1 || page === totalPages || Math.abs(page - currentPage) <= 1)
                  .map((page, index, pages) => {
                    const previousPage = pages[index - 1];
                    const shouldShowGap = previousPage && page - previousPage > 1;

                    return (
                      <div key={page} className="flex items-center gap-1">
                        {shouldShowGap && <span className="px-1 text-slate-400">...</span>}
                        <button
                          type="button"
                          onClick={() => setCurrentPage(page)}
                          className={`h-11 w-11 rounded-full text-sm font-semibold transition ${page === currentPage ? 'text-white' : 'border border-slate-200 bg-white text-slate-700'}`}
                          style={page === currentPage ? { backgroundColor: primary } : undefined}
                        >
                          {page}
                        </button>
                      </div>
                    );
                  })}
              </div>
              <button
                type="button"
                onClick={() => setCurrentPage((page) => Math.min(totalPages, page + 1))}
                disabled={currentPage === totalPages}
                className="inline-flex min-h-11 items-center justify-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2.5 text-sm font-medium text-slate-700 disabled:cursor-not-allowed disabled:opacity-50"
              >
                Próxima
                <ChevronRight className="h-4 w-4" />
              </button>
            </div>
          </div>
        )}
      </section>

      {/* ─── QUERO VENDER ─── */}
      <section id="como-vender" className="mx-auto max-w-7xl px-4 pb-8 lg:px-8">
        <div className="rounded-3xl overflow-hidden border border-black/10 shadow-[0_16px_52px_rgba(15,23,42,0.12)] grid lg:grid-cols-2">
          <div className="relative h-56 sm:h-64 lg:h-auto">
            <img
              src="https://images.unsplash.com/photo-1570129477492-45c003edd2be?auto=format&fit=crop&w=900&q=80"
              alt="Venda seu imóvel"
              className="w-full h-full object-cover"
            />
            <div className="absolute inset-0 bg-gradient-to-t from-black/60 to-black/10" />
            <div className="absolute bottom-6 left-6 right-6">
              <div className="mb-3 inline-flex items-center gap-2 rounded-full border border-white/30 bg-white/10 px-3 py-1.5 backdrop-blur-sm">
                <BadgeCheck className="w-4 h-4 text-white" />
                <span className="text-xs font-semibold uppercase tracking-[0.14em] text-white">Avaliação gratuita</span>
              </div>
              <p className="text-xl font-light leading-snug text-white">
                Sua casa pode valer mais<br />do que você imagina.
              </p>
            </div>
          </div>

          <div className="flex flex-col justify-center bg-white p-5 sm:p-7 lg:p-10">
            <p className="mb-2 text-[11px] uppercase tracking-[0.2em] text-slate-500">Anuncie com a gente</p>
            <h2 className="text-2xl leading-tight text-slate-900 sm:text-3xl lg:text-4xl">
              Venda seu imóvel<br />
              <span style={{ color: secondary }}>pelo melhor preço</span>.
            </h2>
            <p className="mt-3 max-w-md text-sm text-slate-600">
              Nossa equipe cuida de tudo: avaliação de mercado, fotografia profissional, anúncios segmentados e
              acompanhamento jurídico sem custo antecipado.
            </p>

            <div className="mt-6 grid gap-3 sm:grid-cols-3">
              {[
                { icon: Clock, label: 'Avaliação em 48h', sub: 'Resposta garantida' },
                { icon: Shield, label: 'Sem taxas iniciais', sub: 'Só pagou se vender' },
                { icon: TrendingUp, label: 'Mais compradores', sub: 'Rede qualificada' },
              ].map((benefit) => (
                <div
                  key={benefit.label}
                  className="flex flex-col items-start gap-1 rounded-2xl border border-slate-100 bg-slate-50 p-3"
                >
                  <benefit.icon className="mb-0.5 h-5 w-5" style={{ color: secondary }} />
                  <p className="text-xs font-semibold text-slate-800">{benefit.label}</p>
                  <p className="text-[11px] text-slate-500">{benefit.sub}</p>
                </div>
              ))}
            </div>

            <div className="mt-7">
              <p className="mb-3 text-[11px] uppercase tracking-[0.15em] text-slate-400">Como funciona</p>
              <div className="space-y-2.5">
                {[
                  'Cadastre seu imóvel em minutos',
                  'Receba a avaliação gratuita em até 48h',
                  'Fotografia e anúncios profissionais',
                  'Nós cuidamos da negociação e documentos',
                ].map((step, i) => (
                  <div key={step} className="flex items-center gap-3">
                    <div
                      className="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold text-white"
                      style={{ backgroundColor: i < 2 ? primary : '#cbd5e1' }}
                    >
                      {i + 1}
                    </div>
                    <p className="text-sm text-slate-700">{step}</p>
                  </div>
                ))}
              </div>
            </div>

            <div className="mt-7 grid gap-3 sm:flex sm:flex-wrap">
              <button
                type="button"
                onClick={() => navigate('/portal/vender')}
                className="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-full px-6 py-3 text-sm font-semibold sm:min-h-0 sm:w-auto"
                style={{ backgroundColor: primary, color: '#fff' }}
              >
                Anunciar meu imóvel
                <ArrowUpRight className="w-4 h-4" />
              </button>
              <button
                type="button"
                onClick={() => navigate('/portal/vender')}
                className="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-full border border-slate-200 px-6 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50 sm:min-h-0 sm:w-auto"
              >
                <BadgeCheck className="w-4 h-4" style={{ color: secondary }} />
                Avaliação gratuita
              </button>
            </div>
          </div>
        </div>
      </section>

      {/* Simulação de Financiamento — banner promocional */}
      <section className="mx-auto max-w-7xl px-4 pb-8 lg:px-8">
        <div
          className="flex flex-col items-start justify-between gap-5 rounded-3xl p-5 sm:flex-row sm:items-center lg:p-8"
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
            className="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-full px-6 py-3 text-sm font-semibold sm:min-h-0 sm:w-auto"
            style={{ backgroundColor: secondary, color: '#111827' }}
          >
            <Calculator className="w-4 h-4" />
            Simular agora
          </button>
        </div>
      </section>

      <section id="servicos" className="mx-auto max-w-7xl px-4 pb-16 lg:px-8">
        <div className="grid gap-6 lg:grid-cols-2">
          <article className="rounded-3xl border border-black/10 bg-white p-5 shadow-[0_10px_34px_rgba(15,23,42,0.08)] sm:p-6">
            <p className="text-xs uppercase tracking-[0.2em] text-slate-500">Serviços</p>
            <h3 className="mt-2 text-xl text-slate-900 sm:text-2xl">Atendimento completo para compra, venda e locação</h3>
            <div className="mt-6 grid gap-2 sm:grid-cols-2">
              {(tenant?.services?.length
                ? tenant.services
                : ['Consultoria imobiliária', 'Avaliação de mercado', 'Curadoria de investimentos', 'Acompanhamento documental'])
                .map((service) => (
                  <div key={service} className="rounded-xl border border-slate-200 bg-slate-50 px-3 py-3 text-sm text-slate-700">{service}</div>
                ))}
            </div>
          </article>

          <article id="contato" className="rounded-3xl border border-black/10 p-5 text-white shadow-[0_14px_40px_rgba(15,23,42,0.22)] sm:p-6" style={{ backgroundColor: primary }}>
            <p className="text-xs uppercase tracking-[0.2em] text-white/70">Contato</p>
            <h3 className="mt-2 text-xl sm:text-2xl">Converse com nossa equipe</h3>
            <div className="mt-5 space-y-3 text-sm">
              {(tenant?.tenant_phone || tenant?.contact_phone) && <a className="flex items-start gap-2 text-white/90 break-all" href={`tel:${tenant?.tenant_phone || tenant?.contact_phone}`}><Phone className="mt-0.5 w-4 h-4 shrink-0" />{tenant?.tenant_phone || tenant?.contact_phone}</a>}
              {tenant?.contact_email && <a className="flex items-start gap-2 text-white/90 break-all" href={`mailto:${tenant.contact_email}`}><Mail className="mt-0.5 w-4 h-4 shrink-0" />{tenant.contact_email}</a>}
              {tenant?.endereco && <p className="flex items-start gap-2 text-white/80"><MapPin className="mt-0.5 w-4 h-4 shrink-0" />{tenant.endereco}</p>}
            </div>
          </article>
        </div>
      </section>

      {tenantAboutText && (
        <section id="empresa" className="mx-auto max-w-7xl px-4 pb-16 lg:px-8">
          <div className="rounded-3xl border border-black/10 bg-white px-5 py-7 text-left shadow-[0_12px_36px_rgba(15,23,42,0.08)] sm:px-6 sm:py-8 sm:text-center lg:px-12 lg:py-12">
            {(tenant?.logo_url || tenant?.logo) && (
              <div className="mb-5 flex justify-start sm:justify-center">
                <img src={tenant.logo_url || tenant.logo} alt={tenant?.name || 'Logo'} className="h-12 w-auto object-contain" />
              </div>
            )}
            <p className="text-[11px] uppercase tracking-[0.2em] text-slate-500">A Empresa</p>
            <h3 className="mt-2 text-2xl text-slate-900 sm:text-3xl">{tenant?.name || 'Nossa imobiliária'}</h3>
            {tenant?.creci && <p className="mt-2 text-xs font-semibold uppercase tracking-[0.14em]" style={{ color: secondary }}>CRECI {tenant.creci}</p>}
            <p className="mx-auto mt-5 max-w-4xl whitespace-pre-line text-justify text-sm leading-7 text-slate-600">
              {tenantAboutText}
            </p>
            <div className="mt-6 flex flex-col items-start gap-3 text-sm text-slate-600 sm:flex-row sm:flex-wrap sm:items-center sm:justify-center sm:gap-4">
              {tenant?.endereco && <span className="inline-flex items-center gap-2"><MapPin className="h-4 w-4" />{tenant.endereco}</span>}
              {(tenant?.tenant_phone || tenant?.contact_phone) && <a className="inline-flex items-center gap-2 hover:text-slate-900" href={`tel:${tenant?.tenant_phone || tenant?.contact_phone}`}><Phone className="h-4 w-4" />{tenant?.tenant_phone || tenant?.contact_phone}</a>}
              {tenant?.contact_email && <a className="inline-flex items-center gap-2 hover:text-slate-900" href={`mailto:${tenant.contact_email}`}><Mail className="h-4 w-4" />{tenant.contact_email}</a>}
            </div>
            {tenant?.office_hours && <p className="mt-3 text-xs uppercase tracking-[0.14em] text-slate-400">{tenant.office_hours}</p>}
          </div>
        </section>
      )}

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
            height: floatingActionMetrics.height + 52,
          }}
        >
          <div
            className="relative h-full w-full"
            style={{
              transform: floatingActionDragging ? 'scale(1.03)' : 'scale(1)',
              transition: floatingActionDragging ? 'none' : 'transform 0.2s ease, width 0.2s ease, height 0.2s ease',
            }}
          >
            <button
              type="button"
              onClick={handleFloatingActionClick}
              onPointerDown={handleFloatingActionPointerDown}
              className="block w-full"
              aria-label="Abrir atendimento no WhatsApp"
              style={{
                cursor: floatingActionDragging ? 'grabbing' : 'grab',
                height: floatingActionMetrics.height,
                touchAction: 'none',
              }}
            >
              <img
                src={tenant.mascot_url}
                alt="Mascote"
                draggable={false}
                className="h-full w-full object-contain drop-shadow-xl pointer-events-none"
              />
            </button>
            <div className="absolute bottom-0 left-1/2 flex -translate-x-1/2 items-center gap-1 rounded-full border border-white/55 bg-white/88 px-1.5 py-1 text-slate-700 shadow-[0_10px_28px_rgba(15,23,42,0.18)] backdrop-blur-md sm:gap-1.5 sm:px-2 sm:py-1.5">
              <button
                type="button"
                onClick={(event) => {
                  event.stopPropagation();
                  handleFloatingActionScale('down');
                }}
                disabled={floatingActionScale <= MASCOT_MIN_SCALE}
                aria-label="Diminuir mascote"
                className="flex h-7 w-7 items-center justify-center rounded-full text-slate-600 transition hover:bg-slate-900/6 disabled:cursor-not-allowed disabled:opacity-40 sm:h-8 sm:w-8"
              >
                <Minus className="h-3.5 w-3.5" />
              </button>
              <span className="min-w-[2.85rem] text-center text-[10px] font-semibold tracking-[0.12em] text-slate-600 sm:min-w-[3.25rem] sm:text-[11px] sm:tracking-[0.14em]">
                {Math.round(floatingActionScale * 100)}%
              </span>
              <button
                type="button"
                onClick={(event) => {
                  event.stopPropagation();
                  handleFloatingActionScale('up');
                }}
                disabled={floatingActionScale >= MASCOT_MAX_SCALE}
                aria-label="Aumentar mascote"
                className="flex h-7 w-7 items-center justify-center rounded-full text-slate-600 transition hover:bg-slate-900/6 disabled:cursor-not-allowed disabled:opacity-40 sm:h-8 sm:w-8"
              >
                <Plus className="h-3.5 w-3.5" />
              </button>
            </div>
          </div>
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
            onClick={handleFloatingActionClick}
            onPointerDown={handleFloatingActionPointerDown}
            className="flex h-full w-full items-center justify-center rounded-2xl border border-white/30 bg-[#0f172a] text-white shadow-[0_8px_24px_rgba(15,23,42,0.35)]"
            aria-label="Abrir WhatsApp"
            style={{ cursor: floatingActionDragging ? 'grabbing' : 'grab', touchAction: 'none' }}
          >
            <MessageCircle className="w-6 h-6 pointer-events-none" />
          </button>
        </div>
      ) : null}

      {(leadModalProperty || leadModalSource === 'mascot') && (
        <div className="fixed inset-0 z-[90] flex items-end justify-center bg-slate-950/55 px-3 py-3 backdrop-blur-sm sm:items-center sm:px-4 sm:py-6">
          <div className="max-h-[88vh] w-full max-w-md overflow-y-auto rounded-[1.75rem] bg-white p-5 shadow-[0_24px_70px_rgba(15,23,42,0.28)] sm:rounded-3xl sm:p-6">
            <div className="flex items-start justify-between gap-4">
              <div>
                <p className="text-[11px] uppercase tracking-[0.18em] text-slate-500">Atendimento via WhatsApp</p>
                <h3 className="mt-2 text-lg text-slate-900 sm:text-xl">{leadModalProperty ? 'Receber atendimento sobre este imóvel' : 'Falar com nossa equipe'}</h3>
              </div>
              <button
                type="button"
                onClick={closePropertyWhatsAppModal}
                className="min-h-10 rounded-full border border-slate-200 px-3 py-2 text-[11px] font-semibold text-slate-600 sm:min-h-0 sm:py-1.5 sm:text-xs"
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
                  className="h-12 w-full rounded-xl border border-slate-200 bg-white px-3 text-base text-slate-900 outline-none sm:h-11 sm:text-sm"
                />
              </label>

              <label className="block">
                <span className="mb-1.5 block text-sm font-medium text-slate-700">Seu telefone</span>
                <input
                  type="tel"
                  value={leadPhone}
                  onChange={(event) => setLeadPhone(formatPhoneInput(event.target.value))}
                  placeholder="(31) 99999-9999"
                  className="h-12 w-full rounded-xl border border-slate-200 bg-white px-3 text-base text-slate-900 outline-none sm:h-11 sm:text-sm"
                />
              </label>

              <label className="block">
                <span className="mb-1.5 block text-sm font-medium text-slate-700">Sugerir data e hora de visita</span>
                <input
                  type="datetime-local"
                  value={leadVisitDateTime}
                  onChange={(event) => setLeadVisitDateTime(event.target.value)}
                  className="h-12 w-full rounded-xl border border-slate-200 bg-white px-3 text-base text-slate-900 outline-none sm:h-11 sm:text-sm"
                />
              </label>

              <label className="block">
                <span className="mb-1.5 block text-sm font-medium text-slate-700">Observações</span>
                <textarea
                  value={leadVisitNotes}
                  onChange={(event) => setLeadVisitNotes(event.target.value)}
                  rows={3}
                  placeholder="Ex: melhor horário no fim da tarde, visita com família, imóvel semelhante ao anúncio"
                  className="w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-base text-slate-900 outline-none sm:py-2 sm:text-sm"
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
                className="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-200 px-4 py-3 text-sm font-semibold text-slate-700"
              >
                Cancelar
              </button>
              <button
                type="button"
                onClick={handleLeadModalSubmit}
                disabled={leadSubmitting}
                className="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-4 py-3 text-sm font-semibold text-white"
                style={{ backgroundColor: primary }}
              >
                <MessageCircle className="h-4 w-4" />
                {leadSubmitting ? 'Registrando...' : 'Registrar e abrir WhatsApp'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
