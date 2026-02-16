import { useState, useEffect, useMemo } from 'react';
import { useLocation } from 'wouter';
import { toast } from 'sonner';
import { motion } from 'framer-motion';
import api from '@/lib/api';
import { fetchTenantBranding, TenantBranding } from '@/lib/tenantBranding';
import { FlipboardContainer } from '@/components/FlipboardContainer';
import { FlipboardPage, FlipboardPropertyCard } from '@/components/FlipboardPage';
import { Button } from '@/components/ui/button';
import { MessageCircle, MapPin, Home, Phone, Mail, ChevronDown } from 'lucide-react';

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

// Hero Section Page
function HeroPage({ tenant, primary }: { tenant: TenantConfig; primary: string }) {
  return (
    <FlipboardPage className="items-center justify-center" style={{ backgroundColor: primary }}>
      <div className="text-center text-white px-8">
        <motion.h1
          className="text-6xl font-bold mb-4"
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ delay: 0.2 }}
        >
          {tenant?.name || 'Imobiliária'}
        </motion.h1>
        {tenant?.creci && (
          <motion.p
            className="text-lg opacity-80 mb-6"
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
          Encontre o imóvel perfeito para você
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
  primary,
}: {
  tenant: TenantConfig;
  primary: string;
}) {
  if (!tenant?.services || tenant.services.length === 0) return null;

  return (
    <FlipboardPage className="items-center justify-center bg-white">
      <div className="w-full h-full flex flex-col items-center justify-center px-8 py-16">
        <motion.h2
          className="text-5xl font-bold mb-12 text-center"
          style={{ color: primary }}
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
        >
          Nossos Serviços
        </motion.h2>
        <div className="grid grid-cols-2 gap-8 max-w-2xl">
          {tenant.services.map((svc, i) => (
            <motion.div
              key={i}
              className="flex flex-col items-center text-center p-6 rounded-2xl"
              style={{ backgroundColor: `${primary}10` }}
              initial={{ opacity: 0, scale: 0.8 }}
              animate={{ opacity: 1, scale: 1 }}
              transition={{ delay: i * 0.1 }}
            >
              <Home className="w-12 h-12 mb-4" style={{ color: primary }} />
              <p className="font-semibold text-lg">{svc}</p>
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
  primary,
}: {
  tenant: TenantConfig;
  primary: string;
}) {
  return (
    <FlipboardPage className="items-center justify-center bg-white">
      <div className="w-full h-full flex flex-col items-center justify-center px-8 py-16">
        <motion.h2
          className="text-5xl font-bold mb-12"
          style={{ color: primary }}
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
              style={{ backgroundColor: primary }}
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
              style={{ backgroundColor: primary }}
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
  const [tenant, setTenant] = useState<TenantConfig | null>(null);
  const [properties, setProperties] = useState<Property[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [currentPage, setCurrentPage] = useState(0);

  const primary = tenant?.primary_color || '#001775';

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

  // Build pages array
  const pages = useMemo(() => {
    const pageList = [];

    // Add hero page
    if (tenant) {
      pageList.push(
        <HeroPage key="hero" tenant={tenant} primary={primary} />
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
        <ServicesPage key="services" tenant={tenant} primary={primary} />
      );
    }

    // Add contact page
    if (tenant) {
      pageList.push(
        <ContactPage key="contact" tenant={tenant} primary={primary} />
      );
    }

    return pageList;
  }, [tenant, properties, primary, navigate]);

  if (loading) {
    return (
      <div className="w-full h-screen flex items-center justify-center bg-white">
        <motion.div
          animate={{ rotate: 360 }}
          transition={{ repeat: Infinity, duration: 2 }}
          className="w-12 h-12 rounded-full border-4 border-gray-200"
          style={{ borderTopColor: primary }}
        />
      </div>
    );
  }

  if (error && properties.length === 0) {
    return (
      <div className="w-full h-screen flex items-center justify-center bg-white">
        <div className="text-center">
          <p className="text-lg text-red-600 mb-4">{error}</p>
          <Button onClick={() => window.location.reload()}>Tentar novamente</Button>
        </div>
      </div>
    );
  }

  return (
    <div className="w-full h-screen overflow-hidden bg-white">
      {/* Remove dark mode for portal */}
      <style>{`
        html {
          color-scheme: light;
        }
        .dark {
          display: none !important;
        }
      `}</style>

      <FlipboardContainer
        primary={primary}
        onPageChange={setCurrentPage}
      >
        {pages}
      </FlipboardContainer>

      {/* Keyboard navigation hint */}
      <div className="fixed bottom-4 left-4 text-xs text-gray-500 pointer-events-none">
        ← Deslize ou use setas →
      </div>
    </div>
  );
}
