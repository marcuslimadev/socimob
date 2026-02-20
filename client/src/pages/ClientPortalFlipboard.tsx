import { useState, useEffect, useMemo } from 'react';
import { useLocation } from 'wouter';
import { motion } from 'framer-motion';
import api from '@/lib/api';
import { fetchTenantBranding, TenantBranding } from '@/lib/tenantBranding';
import { FlipboardContainer } from '@/components/FlipboardContainer';
import { FlipboardPage, FlipboardPropertyCard } from '@/components/FlipboardPage';
import { Button } from '@/components/ui/button';
import { MessageCircle, Home, Phone, Mail, ChevronDown } from 'lucide-react';

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
  logradouro?: string;
  quartos?: number;
  dormitorios?: number;
  banheiros?: number;
  garagem?: number;
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
  latitude?: number;
  longitude?: number;
  likes_count?: number;
}

interface TenantConfig extends TenantBranding {
  contact_phone?: string;
  contact_email?: string;
  portal_finalidades?: string[];
  metadata?: Record<string, any>;
  mascot_url?: string;
  creci?: string;
  about_text?: string;
  services?: string[];
  social_links?: Record<string, string>;
  endereco?: string;
  office_hours?: string;
}

interface ArtDirection {
  canvas: string;
  surface: string;
  ink: string;
  inkSoft: string;
  line: string;
  accent: string;
  accentSoft: string;
  heroOverlay: string;
  heroText: string;
  badge: string;
}

function hexToRgb(hex: string) {
  const clean = hex.replace('#', '');
  return {
    r: parseInt(clean.slice(0, 2), 16),
    g: parseInt(clean.slice(2, 4), 16),
    b: parseInt(clean.slice(4, 6), 16),
  };
}

function mixHex(a: string, b: string, weight: number): string {
  const p = Math.max(0, Math.min(1, weight));
  const c1 = hexToRgb(a);
  const c2 = hexToRgb(b);
  const toHex = (n: number) => Math.round(n).toString(16).padStart(2, '0');
  return `#${toHex(c1.r * (1 - p) + c2.r * p)}${toHex(c1.g * (1 - p) + c2.g * p)}${toHex(c1.b * (1 - p) + c2.b * p)}`.toUpperCase();
}

function toRgba(hex: string, alpha: number): string {
  const rgb = hexToRgb(hex);
  return `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, ${Math.max(0, Math.min(1, alpha))})`;
}

function contrastText(hex: string): string {
  const { r, g, b } = hexToRgb(hex);
  const yiq = (r * 299 + g * 587 + b * 114) / 1000;
  return yiq >= 146 ? '#111827' : '#F8FAFC';
}

function buildArtDirection(tenant: TenantConfig | null): ArtDirection {
  const primary = tenant?.primary_color || '#1F2937';
  const secondary = tenant?.secondary_color || '#B38A58';
  const name = (tenant?.name || '').toLowerCase();
  const coastal = /praia|mar|lagoa|beach|costa/.test(name);
  const urban = /city|centro|metropole|capital/.test(name);

  if (coastal) {
    return {
      canvas: mixHex(primary, '#ECF6F7', 0.92),
      surface: '#FFFFFF',
      ink: mixHex(primary, '#0E2A3A', 0.55),
      inkSoft: '#4B6475',
      line: '#CFE3EA',
      accent: secondary,
      accentSoft: mixHex(secondary, '#FFFFFF', 0.65),
      heroOverlay: `linear-gradient(115deg, ${toRgba(mixHex(primary, '#062130', 0.6), 0.92)} 0%, ${toRgba('#071119', 0.86)} 100%)`,
      heroText: '#F8FAFC',
      badge: 'Coastal Collection',
    };
  }

  if (urban) {
    return {
      canvas: mixHex(primary, '#F3F4F8', 0.92),
      surface: '#FFFFFF',
      ink: mixHex(primary, '#0B1220', 0.6),
      inkSoft: '#5A6478',
      line: '#DAE0EA',
      accent: secondary,
      accentSoft: mixHex(secondary, '#FFFFFF', 0.6),
      heroOverlay: `linear-gradient(115deg, ${toRgba(mixHex(primary, '#0D1323', 0.65), 0.93)} 0%, ${toRgba('#0A0D16', 0.88)} 100%)`,
      heroText: '#F8FAFC',
      badge: 'Metropolitan Portfolio',
    };
  }

  return {
    canvas: mixHex(primary, '#F4EEE6', 0.92),
    surface: '#FFFFFF',
    ink: mixHex(primary, '#0D1018', 0.58),
    inkSoft: '#60544A',
    line: '#E4D9C9',
    accent: secondary,
    accentSoft: mixHex(secondary, '#FFFFFF', 0.62),
    heroOverlay: `linear-gradient(115deg, ${toRgba(mixHex(primary, '#101014', 0.52), 0.93)} 0%, ${toRgba('#0B0D13', 0.88)} 100%)`,
    heroText: '#F8FAFC',
    badge: 'Private Estates',
  };
}

// Hero Section Page
function HeroPage({ tenant, art }: { tenant: TenantConfig; art: ArtDirection }) {
  return (
    <FlipboardPage
      className="items-center justify-center relative overflow-hidden"
      style={{ background: art.heroOverlay }}
    >
      <div className="absolute inset-0 bg-[radial-gradient(circle_at_15%_20%,rgba(255,255,255,0.2),transparent_35%),radial-gradient(circle_at_85%_85%,rgba(255,255,255,0.12),transparent_40%)]" />
      <div className="text-center px-8 relative" style={{ color: art.heroText }}>
        <p className="text-[11px] uppercase tracking-[0.24em] mb-4">{art.badge}</p>
        <motion.h1
          className="text-6xl font-medium mb-4"
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.2 }}
        >
          {tenant?.name || 'Imobiliaria'}
        </motion.h1>
        {tenant?.creci && (
          <motion.p
            className="text-sm opacity-80 mb-6 uppercase tracking-[0.16em]"
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.4 }}
          >
            CRECI: {tenant.creci}
          </motion.p>
        )}
        <motion.p
          className="text-2xl opacity-90 max-w-2xl mx-auto"
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.6 }}
        >
          {tenant?.slogan || 'Encontre o imovel perfeito para voce'}
        </motion.p>
        <motion.div
          className="mt-8"
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          transition={{ delay: 1 }}
        >
          <ChevronDown className="w-8 h-8 mx-auto animate-bounce" />
        </motion.div>
      </div>
    </FlipboardPage>
  );
}

// Services Section Page
function ServicesPage({
  tenant,
  art,
}: {
  tenant: TenantConfig;
  art: ArtDirection;
}) {
  if (!tenant?.services || tenant.services.length === 0) return null;

  return (
    <FlipboardPage className="items-center justify-center" style={{ backgroundColor: art.canvas }}>
      <div className="w-full h-full flex flex-col items-center justify-center px-8 py-16">
        <motion.h2
          className="text-5xl font-medium mb-12 text-center"
          style={{ color: art.ink }}
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
        >
          Nossos Servicos
        </motion.h2>
        <div className="grid grid-cols-2 gap-8 max-w-2xl">
          {tenant.services.map((svc, i) => (
            <motion.div
              key={i}
              className="flex flex-col items-center text-center p-6 rounded-2xl border"
              style={{ backgroundColor: art.surface, borderColor: art.line }}
              initial={{ opacity: 0, scale: 0.8 }}
              animate={{ opacity: 1, scale: 1 }}
              transition={{ delay: i * 0.1 }}
            >
              <Home className="w-12 h-12 mb-4" style={{ color: art.accent }} />
              <p className="font-semibold text-lg" style={{ color: art.ink }}>{svc}</p>
            </motion.div>
          ))}
        </div>
      </div>
    </FlipboardPage>
  );
}

// Contact Section Page
function ContactPage({
  tenant,
  art,
}: {
  tenant: TenantConfig;
  art: ArtDirection;
}) {
  return (
    <FlipboardPage className="items-center justify-center" style={{ backgroundColor: art.surface }}>
      <div className="w-full h-full flex flex-col items-center justify-center px-8 py-16">
        <motion.h2
          className="text-5xl font-medium mb-12"
          style={{ color: art.ink }}
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
        >
          Entre em Contato
        </motion.h2>
        <div className="space-y-6 max-w-md w-full">
          {tenant?.contact_phone && (
            <motion.a
              href={`tel:${tenant.contact_phone}`}
              className="flex items-center gap-4 p-6 rounded-2xl text-white font-semibold text-lg"
              style={{ backgroundColor: art.ink }}
              whileHover={{ scale: 1.05 }}
              initial={{ opacity: 0, x: -20 }}
              animate={{ opacity: 1, x: 0 }}
              transition={{ delay: 0.1 }}
            >
              <Phone className="w-6 h-6" />
              {tenant.contact_phone}
            </motion.a>
          )}
          {tenant?.contact_email && (
            <motion.a
              href={`mailto:${tenant.contact_email}`}
              className="flex items-center gap-4 p-6 rounded-2xl text-white font-semibold text-lg"
              style={{ backgroundColor: art.accent, color: contrastText(art.accent) }}
              whileHover={{ scale: 1.05 }}
              initial={{ opacity: 0, x: -20 }}
              animate={{ opacity: 1, x: 0 }}
              transition={{ delay: 0.2 }}
            >
              <Mail className="w-6 h-6" />
              {tenant.contact_email}
            </motion.a>
          )}
        </div>
      </div>
    </FlipboardPage>
  );
}

// Main Component
export default function ClientPortalFlipboard() {
  const [, navigate] = useLocation();
  const [isAuthenticated, setIsAuthenticated] = useState(() =>
    typeof window !== 'undefined' && Boolean(localStorage.getItem('token'))
  );
  const [tenant, setTenant] = useState<TenantConfig | null>(null);
  const [properties, setProperties] = useState<Property[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [currentPage, setCurrentPage] = useState(0);

  const primary = tenant?.primary_color || '#001775';
  const art = useMemo(() => buildArtDirection(tenant), [tenant]);

  // Fetch tenant config
  useEffect(() => {
    const loadTenant = async () => {
      try {
        const branding = await fetchTenantBranding();
        setTenant(branding as TenantConfig);
      } catch (err) {
        console.error('Failed to load tenant:', err);
      }
    };
    loadTenant();
  }, []);

  // Fetch properties
  useEffect(() => {
    const loadProperties = async () => {
      try {
        setLoading(true);
        const response = await api.get('/portal/imoveis');
        const data = response.data?.data || response.data || [];
        setProperties(Array.isArray(data) ? data.filter((p) => p.active !== false) : []);
      } catch (err) {
        console.error('Failed to load properties:', err);
        setError('Erro ao carregar imóveis');
      } finally {
        setLoading(false);
      }
    };
    loadProperties();
  }, []);

  useEffect(() => {
    const syncAuthState = () => {
      setIsAuthenticated(Boolean(localStorage.getItem('token')));
    };

    window.addEventListener('storage', syncAuthState);
    window.addEventListener('focus', syncAuthState);

    return () => {
      window.removeEventListener('storage', syncAuthState);
      window.removeEventListener('focus', syncAuthState);
    };
  }, []);

  // Build pages array
  const pages = useMemo(() => {
    const pageList = [];

    // Add hero page
    if (tenant) {
      pageList.push(
        <HeroPage key="hero" tenant={tenant} art={art} />
      );
    }

    // Add property pages
    if (properties.length > 0) {
      properties.forEach((property) => {
        pageList.push(
          <div key={`property-${property.id}`} className="w-full h-full">
            <FlipboardPropertyCard
              property={property}
              primary={primary}
              onContactClick={() => {
                // Could open chat or navigate to detail
                navigate(`/portal/imovel/${property.id}`);
              }}
            />
          </div>
        );
      });
    }

    // Add services page if available
    if (tenant?.services && tenant.services.length > 0) {
      pageList.push(
        <ServicesPage key="services" tenant={tenant} art={art} />
      );
    }

    // Add contact page
    if (tenant) {
      pageList.push(
        <ContactPage key="contact" tenant={tenant} art={art} />
      );
    }

    return pageList;
  }, [tenant, properties, primary, navigate, art]);

  const whatsappLink = useMemo(() => {
    const phone = tenant?.contact_phone?.replace(/\D/g, '');
    if (!phone) return '';
    const message = encodeURIComponent(`Ola! Vim pelo portal da ${tenant?.name || 'imobiliaria'} e quero atendimento.`);
    return `https://wa.me/${phone}?text=${message}`;
  }, [tenant?.contact_phone, tenant?.name]);

  if (loading) {
    return (
      <div className="w-full h-screen flex items-center justify-center" style={{ backgroundColor: art.canvas }}>
        <motion.div
          animate={{ rotate: 360 }}
          transition={{ repeat: Infinity, duration: 2 }}
          className="w-12 h-12 rounded-full border-4 border-gray-200"
          style={{ borderTopColor: art.accent }}
        />
      </div>
    );
  }

  if (error && properties.length === 0) {
    return (
      <div className="w-full h-screen flex items-center justify-center" style={{ backgroundColor: art.surface }}>
        <div className="text-center">
          <p className="text-lg text-red-600 mb-4">{error}</p>
          <Button onClick={() => window.location.reload()}>Tentar novamente</Button>
        </div>
      </div>
    );
  }

  return (
    <div className="w-full h-screen overflow-hidden" style={{ backgroundColor: art.canvas }}>
      {/* Remove dark mode for portal */}
      <style>{`
        html {
          color-scheme: light;
        }
        :root.dark {
          color-scheme: light;
          --background: 0 0% 100%;
          --foreground: 222.2 84% 4.9%;
        }
      `}</style>

      <FlipboardContainer
        primary={art.accent}
        onPageChange={setCurrentPage}
      >
        {pages}
      </FlipboardContainer>

      <div className="fixed top-4 right-4 z-[90] flex items-center gap-2">
        {isAuthenticated ? (
          <button
            type="button"
            onClick={() => navigate('/portal/meu-financeiro')}
            className="rounded-full px-4 py-2 text-xs font-semibold border"
            style={{ backgroundColor: art.surface, borderColor: art.line, color: art.ink }}
          >
            Meu Financeiro
          </button>
        ) : (
          <button
            type="button"
            onClick={() => navigate('/login')}
            className="rounded-full px-4 py-2 text-xs font-semibold border"
            style={{ backgroundColor: art.surface, borderColor: art.line, color: art.ink }}
          >
            Entrar
          </button>
        )}
      </div>

      {/* Keyboard navigation hint */}
      <div className="fixed bottom-4 left-4 text-xs pointer-events-none" style={{ color: art.inkSoft }}>
        ← Deslize ou use setas →
      </div>

      {whatsappLink && (
        <a
          href={whatsappLink}
          target="_blank"
          rel="noreferrer"
          className="fixed bottom-4 right-4 z-[80] w-14 h-14 rounded-2xl shadow-xl border overflow-hidden flex items-center justify-center"
          style={{ backgroundColor: art.ink, borderColor: art.line, color: art.heroText }}
          aria-label="Abrir WhatsApp"
        >
          {tenant?.mascot_url ? (
            <img src={tenant.mascot_url} alt="Mascote" className="w-full h-full object-cover" />
          ) : (
            <MessageCircle className="w-6 h-6" />
          )}
        </a>
      )}
    </div>
  );
}
