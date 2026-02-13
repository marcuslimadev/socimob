import { useState, useEffect, useMemo, useCallback, useRef } from 'react';
import {
  MapPin,
  Heart,
  Home,
  Phone,
  Mail,
  ChevronDown,
  ChevronLeft,
  ChevronRight,
  X,
  MessageCircle,
  Map as MapIcon,
  Send,
  Bot,
  ClipboardList,
  CheckCircle,
  Menu,
} from 'lucide-react';
import { useLocation } from 'wouter';
import { toast } from 'sonner';
import api from '@/lib/api';
import { fetchTenantBranding, TenantBranding } from '@/lib/tenantBranding';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
// Theme forced to light mode in portal

// Leaflet imports
import { MapContainer, TileLayer, Marker, Popup, useMap } from 'react-leaflet';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

// Fix Leaflet default icon
delete (L.Icon.Default.prototype as any)._getIconUrl;
L.Icon.Default.mergeOptions({
  iconRetinaUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon-2x.png',
  iconUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-icon.png',
  shadowUrl: 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/images/marker-shadow.png',
});

// Custom icons for different property types
const createPropertyIcon = (type: string, color: string) => {
  const isHouse = type?.toLowerCase().includes('casa');
  const isApartment = type?.toLowerCase().includes('apartamento') || type?.toLowerCase().includes('apto');
  const isTerrain = type?.toLowerCase().includes('terreno') || type?.toLowerCase().includes('lote');
  const isCommercial = type?.toLowerCase().includes('comercial') || type?.toLowerCase().includes('sala') || type?.toLowerCase().includes('loja');

  let iconSvg: string;

  if (isHouse) {
    iconSvg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 36 45" width="36" height="45">
      <path d="M18 0C8.06 0 0 8.06 0 18c0 13.5 18 27 18 27s18-13.5 18-27C36 8.06 27.94 0 18 0z" fill="${color}"/>
      <path d="M18 4C10.27 4 4 10.27 4 18c0 10.5 14 21 14 21s14-10.5 14-21C32 10.27 25.73 4 18 4z" fill="white" opacity="0.2"/>
      <path d="M18 10l-8 6v10h5v-6h6v6h5V16l-8-6z" fill="white"/>
    </svg>`;
  } else if (isApartment) {
    iconSvg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 36 45" width="36" height="45">
      <path d="M18 0C8.06 0 0 8.06 0 18c0 13.5 18 27 18 27s18-13.5 18-27C36 8.06 27.94 0 18 0z" fill="${color}"/>
      <path d="M18 4C10.27 4 4 10.27 4 18c0 10.5 14 21 14 21s14-10.5 14-21C32 10.27 25.73 4 18 4z" fill="white" opacity="0.2"/>
      <rect x="10" y="9" width="16" height="18" rx="1" fill="white"/>
      <rect x="12" y="11" width="4" height="3" fill="${color}"/>
      <rect x="20" y="11" width="4" height="3" fill="${color}"/>
      <rect x="12" y="16" width="4" height="3" fill="${color}"/>
      <rect x="20" y="16" width="4" height="3" fill="${color}"/>
      <rect x="15" y="21" width="6" height="6" fill="${color}"/>
    </svg>`;
  } else if (isTerrain) {
    iconSvg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 36 45" width="36" height="45">
      <path d="M18 0C8.06 0 0 8.06 0 18c0 13.5 18 27 18 27s18-13.5 18-27C36 8.06 27.94 0 18 0z" fill="${color}"/>
      <path d="M18 4C10.27 4 4 10.27 4 18c0 10.5 14 21 14 21s14-10.5 14-21C32 10.27 25.73 4 18 4z" fill="white" opacity="0.2"/>
      <path d="M8 24h20L23 16l-5 5-3-3-7 6z" fill="white"/>
      <circle cx="12" cy="12" r="2" fill="white"/>
    </svg>`;
  } else if (isCommercial) {
    iconSvg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 36 45" width="36" height="45">
      <path d="M18 0C8.06 0 0 8.06 0 18c0 13.5 18 27 18 27s18-13.5 18-27C36 8.06 27.94 0 18 0z" fill="${color}"/>
      <path d="M18 4C10.27 4 4 10.27 4 18c0 10.5 14 21 14 21s14-10.5 14-21C32 10.27 25.73 4 18 4z" fill="white" opacity="0.2"/>
      <rect x="9" y="11" width="18" height="15" rx="1" fill="white"/>
      <rect x="11" y="13" width="4" height="5" fill="${color}"/>
      <rect x="16" y="13" width="4" height="5" fill="${color}"/>
      <rect x="21" y="13" width="4" height="5" fill="${color}"/>
      <rect x="14" y="20" width="8" height="6" fill="${color}"/>
    </svg>`;
  } else {
    iconSvg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 36 45" width="36" height="45">
      <path d="M18 0C8.06 0 0 8.06 0 18c0 13.5 18 27 18 27s18-13.5 18-27C36 8.06 27.94 0 18 0z" fill="${color}"/>
      <path d="M18 4C10.27 4 4 10.27 4 18c0 10.5 14 21 14 21s14-10.5 14-21C32 10.27 25.73 4 18 4z" fill="white" opacity="0.2"/>
      <circle cx="18" cy="18" r="6" fill="white"/>
    </svg>`;
  }

  return L.divIcon({
    html: iconSvg,
    className: 'custom-marker-icon',
    iconSize: [36, 45],
    iconAnchor: [18, 45],
    popupAnchor: [0, -45],
  });
};

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

type SortOption = 'recent' | 'price_asc' | 'price_desc' | 'area_desc';

const PRICE_RANGES = [
  { label: 'Qualquer preço', min: 0, max: Infinity },
  { label: 'Até R$ 200 mil', min: 0, max: 200000 },
  { label: 'R$ 200 - 500 mil', min: 200000, max: 500000 },
  { label: 'R$ 500 mil - 1 mi', min: 500000, max: 1000000 },
  { label: 'R$ 1 - 2 milhões', min: 1000000, max: 2000000 },
  { label: 'Acima de R$ 2 mi', min: 2000000, max: Infinity },
];

const PROPERTY_TYPES = [
  { value: '', label: 'Todos os tipos' },
  { value: 'apartamento', label: 'Apartamento' },
  { value: 'casa', label: 'Casa' },
  { value: 'terreno', label: 'Terreno' },
  { value: 'comercial', label: 'Comercial' },
  { value: 'rural', label: 'Rural' },
  { value: 'cobertura', label: 'Cobertura' },
];

interface MapBounds {
  north: number;
  south: number;
  east: number;
  west: number;
}

function MapBoundsHandler({
  properties,
  onBoundsChange,
  initialFit = true
}: {
  properties: Property[];
  onBoundsChange?: (bounds: MapBounds) => void;
  initialFit?: boolean;
}) {
  const map = useMap();

  useEffect(() => {
    if (!onBoundsChange) return;
    const updateBounds = () => {
      const bounds = map.getBounds();
      onBoundsChange({
        north: bounds.getNorth(),
        south: bounds.getSouth(),
        east: bounds.getEast(),
        west: bounds.getWest(),
      });
    };
    updateBounds();
    map.on('moveend', updateBounds);
    map.on('zoomend', updateBounds);
    return () => {
      map.off('moveend', updateBounds);
      map.off('zoomend', updateBounds);
    };
  }, [map, onBoundsChange]);

  useEffect(() => {
    if (!initialFit || properties.length === 0) return;
    const validProperties = properties.filter(
      (p) => p.latitude && p.longitude && !isNaN(p.latitude) && !isNaN(p.longitude)
    );
    if (validProperties.length > 0) {
      const bounds = L.latLngBounds(
        validProperties.map((p) => [p.latitude!, p.longitude!])
      );
      map.fitBounds(bounds, { padding: [50, 50] });
    }
  }, [properties, map, initialFit]);

  return null;
}

const BRAZIL_CITY_COORDS: Record<string, { lat: number; lng: number }> = {
  'são paulo': { lat: -23.5505, lng: -46.6333 },
  'sao paulo': { lat: -23.5505, lng: -46.6333 },
  'rio de janeiro': { lat: -22.9068, lng: -43.1729 },
  'belo horizonte': { lat: -19.9167, lng: -43.9345 },
  'curitiba': { lat: -25.4284, lng: -49.2733 },
  'porto alegre': { lat: -30.0346, lng: -51.2177 },
  'salvador': { lat: -12.9714, lng: -38.5014 },
  'fortaleza': { lat: -3.7172, lng: -38.5433 },
  'brasília': { lat: -15.7801, lng: -47.9292 },
  'brasilia': { lat: -15.7801, lng: -47.9292 },
  'recife': { lat: -8.0476, lng: -34.877 },
  'manaus': { lat: -3.119, lng: -60.0217 },
  'goiânia': { lat: -16.6869, lng: -49.2648 },
  'goiania': { lat: -16.6869, lng: -49.2648 },
  'campinas': { lat: -22.9099, lng: -47.0626 },
  'florianópolis': { lat: -27.5954, lng: -48.548 },
  'florianopolis': { lat: -27.5954, lng: -48.548 },
  'santos': { lat: -23.9608, lng: -46.3336 },
  'sorocaba': { lat: -23.5015, lng: -47.4526 },
  'joinville': { lat: -26.3045, lng: -48.8487 },
  'londrina': { lat: -23.3045, lng: -51.1696 },
  'niterói': { lat: -22.8838, lng: -43.1034 },
  'niteroi': { lat: -22.8838, lng: -43.1034 },
  'ribeirão preto': { lat: -21.1775, lng: -47.8103 },
  'ribeirao preto': { lat: -21.1775, lng: -47.8103 },
  'uberlândia': { lat: -18.9186, lng: -48.2772 },
  'uberlandia': { lat: -18.9186, lng: -48.2772 },
  'laranjeiras': { lat: -20.0022, lng: -40.4098 },
  'vitória': { lat: -20.3155, lng: -40.3128 },
  'vitoria': { lat: -20.3155, lng: -40.3128 },
  'vila velha': { lat: -20.3297, lng: -40.2925 },
  'serra': { lat: -20.1281, lng: -40.3078 },
  'cariacica': { lat: -20.2635, lng: -40.4165 },
  'juiz de fora': { lat: -21.7642, lng: -43.3503 },
};

function getCityCoords(city: string | undefined): { lat: number; lng: number } | null {
  if (!city) return null;
  const normalized = city.toLowerCase().trim();
  return BRAZIL_CITY_COORDS[normalized] || null;
}

// ===== Photo Carousel =====
function PhotoCarousel({
  photos,
  onLike,
  isLiked,
  badges,
}: {
  photos: string[];
  onLike: (e: React.MouseEvent) => void;
  isLiked: boolean;
  badges: { label: string; className: string }[];
}) {
  const [current, setCurrent] = useState(0);
  const touchStartRef = useRef(0);

  const handleTouchStart = (e: React.TouchEvent) => {
    touchStartRef.current = e.touches[0].clientX;
  };

  const handleTouchEnd = (e: React.TouchEvent) => {
    const diff = touchStartRef.current - e.changedTouches[0].clientX;
    if (Math.abs(diff) > 50) {
      if (diff > 0 && current < photos.length - 1) setCurrent((c) => c + 1);
      if (diff < 0 && current > 0) setCurrent((c) => c - 1);
    }
  };

  if (photos.length === 0) {
    return (
      <div className="w-full h-full bg-muted/50 flex items-center justify-center">
        <Home className="w-12 h-12 text-muted-foreground/30" />
      </div>
    );
  }

  return (
    <div
      className="relative w-full h-full group/carousel"
      onTouchStart={handleTouchStart}
      onTouchEnd={handleTouchEnd}
    >
      {/* Photo strip */}
      <div
        className="flex h-full transition-transform duration-300 ease-out will-change-transform"
        style={{ transform: `translateX(-${current * 100}%)` }}
      >
        {photos.map((photo, i) => (
          <img
            key={i}
            src={photo}
            alt=""
            className="w-full h-full object-cover flex-shrink-0"
            loading="lazy"
          />
        ))}
      </div>

      {/* Navigation arrows */}
      {current > 0 && (
        <button
          onClick={(e) => {
            e.stopPropagation();
            setCurrent((c) => c - 1);
          }}
          className="absolute left-2 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full bg-white/90 shadow-md flex items-center justify-center opacity-0 group-hover/carousel:opacity-100 transition-opacity hover:bg-white"
        >
          <ChevronLeft className="w-4 h-4 text-gray-700" />
        </button>
      )}
      {current < photos.length - 1 && (
        <button
          onClick={(e) => {
            e.stopPropagation();
            setCurrent((c) => c + 1);
          }}
          className="absolute right-2 top-1/2 -translate-y-1/2 w-8 h-8 rounded-full bg-white/90 shadow-md flex items-center justify-center opacity-0 group-hover/carousel:opacity-100 transition-opacity hover:bg-white"
        >
          <ChevronRight className="w-4 h-4 text-gray-700" />
        </button>
      )}

      {/* Dot indicators */}
      {photos.length > 1 && (
        <div className="absolute bottom-2.5 left-0 right-0 flex justify-center gap-1.5">
          {photos.slice(0, 7).map((_, i) => (
            <button
              key={i}
              onClick={(e) => {
                e.stopPropagation();
                setCurrent(i);
              }}
              className={cn(
                'w-[6px] h-[6px] rounded-full transition-all',
                i === current ? 'bg-white scale-110' : 'bg-white/60'
              )}
            />
          ))}
          {photos.length > 7 && <span className="w-[6px] h-[6px] rounded-full bg-white/30" />}
        </div>
      )}

      {/* Badges */}
      {badges.length > 0 && (
        <div className="absolute top-3 left-3 flex gap-1.5">
          {badges.map((badge, i) => (
            <span
              key={i}
              className={cn('px-2 py-1 text-xs font-medium rounded shadow-sm', badge.className)}
            >
              {badge.label}
            </span>
          ))}
        </div>
      )}

      {/* Heart button */}
      <button
        onClick={onLike}
        className={cn(
          'absolute top-3 right-3 w-8 h-8 rounded-full flex items-center justify-center transition-all shadow-sm',
          isLiked
            ? 'bg-red-500 text-white'
            : 'bg-white/90 text-gray-500 hover:text-red-500 hover:bg-white'
        )}
      >
        <Heart className={cn('w-4 h-4', isLiked && 'fill-current')} />
      </button>
    </div>
  );
}

// ===== Mascot Avatar (configurable per tenant) =====
const MascotAvatar = ({ size = 48, mascotUrl, primary, roundedClass = 'rounded-full', fitClass = 'object-cover', imagePadding = 0, imageBackground = 'transparent' }: { size?: number; mascotUrl?: string; primary?: string; roundedClass?: string; fitClass?: 'object-cover' | 'object-contain'; imagePadding?: number; imageBackground?: string }) => {
  const [imgError, setImgError] = useState(false);
  
  if (mascotUrl && !imgError) {
    return (
      <img 
        src={mascotUrl} 
        alt="Mascote" 
        width={size} 
        height={size} 
        className={`${roundedClass} ${fitClass}`} 
        style={{ width: size, height: size, padding: imagePadding, backgroundColor: imageBackground }} 
        onError={(e) => {
          setImgError(true);
        }}
      />
    );
  }
  return (
    <div className={`${roundedClass} flex items-center justify-center`} style={{ width: size, height: size, backgroundColor: primary || '#1e40af' }}>
      <Bot className="text-white" style={{ width: size * 0.55, height: size * 0.55 }} />
    </div>
  );
};

// ===== Chat Widget Types =====
type ChatStep = 'greeting' | 'ask_name' | 'ask_whatsapp' | 'ask_email' | 'ask_interesse' | 'submitting' | 'done';
interface ChatMessage { from: 'bot' | 'user'; text: string; }

// ===== Chat Widget (per-tenant mascot) =====
function ChatWidget({ tenantPhone, tenantName, primary, mascotUrl, isOpen, onOpenChange, propertyContext }: { 
  tenantPhone?: string; 
  tenantName?: string; 
  primary: string; 
  mascotUrl?: string;
  isOpen: boolean;
  onOpenChange: (open: boolean) => void;
  propertyContext?: { id: number; titulo: string; preco: string };
}) {
  const [step, setStep] = useState<ChatStep>('greeting');
  const [messages, setMessages] = useState<ChatMessage[]>([]);
  const [input, setInput] = useState('');
  const [nome, setNome] = useState('');
  const [whatsapp, setWhatsapp] = useState('');
  const [email, setEmail] = useState('');
  const [interesse, setInteresse] = useState('');
  const [leadWhatsapp, setLeadWhatsapp] = useState('');
  const messagesEndRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const prevPropertyRef = useRef<number | undefined>(undefined);
  const effectiveMascotUrl = mascotUrl || '/assets/mascote.png';

  useEffect(() => { messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' }); }, [messages]);
  useEffect(() => { if (isOpen && inputRef.current) setTimeout(() => inputRef.current?.focus(), 300); }, [isOpen, step]);

  const addBot = (text: string) => setMessages(prev => [...prev, { from: 'bot', text }]);
  const addUser = (text: string) => setMessages(prev => [...prev, { from: 'user', text }]);

  // Auto-start conversation when opened
  useEffect(() => {
    if (isOpen && messages.length === 0) {
      setTimeout(() => {
        addBot(`Oi! Eu sou o assistente virtual da ${tenantName || 'imobiliária'}!`);
        setTimeout(() => {
          if (propertyContext) {
            addBot(`Vi que você se interessou por: ${propertyContext.titulo} - ${propertyContext.preco}`);
            setInteresse(`Imóvel ID ${propertyContext.id}: ${propertyContext.titulo}`);
            setTimeout(() => { addBot('Para continuar, qual é o seu nome?'); setStep('ask_name'); }, 600);
          } else {
            addBot('Posso te ajudar a encontrar o imóvel ideal. Para começar, qual é o seu nome?'); 
            setStep('ask_name');
          }
        }, 600);
      }, 300);
    }
  }, [isOpen, messages.length, propertyContext, tenantName]);

  // Reset when property context changes
  useEffect(() => {
    if (propertyContext && prevPropertyRef.current !== propertyContext.id) {
      prevPropertyRef.current = propertyContext.id;
      setMessages([]);
      setStep('greeting');
      setNome('');
      setWhatsapp('');
      setEmail('');
      setInteresse('');
      setLeadWhatsapp('');
    }
  }, [propertyContext]);

  const fmtWa = (v: string) => {
    const d = v.replace(/\D/g, '');
    if (d.length <= 2) return `(${d}`;
    if (d.length <= 7) return `(${d.slice(0,2)}) ${d.slice(2)}`;
    return `(${d.slice(0,2)}) ${d.slice(2,7)}-${d.slice(7,11)}`;
  };

  const handleSend = async () => {
    const val = input.trim();
    if (!val) return;
    setInput('');
    switch (step) {
      case 'ask_name':
        addUser(val); setNome(val);
        setTimeout(() => { addBot(`Prazer, ${val}!`); setTimeout(() => { addBot('Qual o seu WhatsApp? Assim posso te conectar com nosso time.'); setStep('ask_whatsapp'); }, 500); }, 400);
        break;
      case 'ask_whatsapp': {
        const digits = val.replace(/\D/g, '');
        if (digits.length < 10) { addBot('Hmm, número incompleto. Informe com DDD, ex: (31) 99999-8888'); return; }
        addUser(val); setWhatsapp(digits);
        setTimeout(() => { addBot('Perfeito! E o seu e-mail? (digite "pular" se preferir não informar)'); setStep('ask_email'); }, 400);
        break;
      }
      case 'ask_email':
        if (val.toLowerCase() === 'pular' || val.toLowerCase() === 'não' || val.toLowerCase() === 'nao') {
          addUser('Prefiro não informar');
          if (interesse) {
            // Se já tem interesse (propertyContext), submeter direto
            setStep('submitting');
            setTimeout(async () => {
              addBot('Registrando seu contato...');
              try {
                const resp = await fetch('/api/portal/chat-lead', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json', 'X-Tenant-Domain': window.location.hostname },
                  body: JSON.stringify({ nome, whatsapp, email: undefined, interesse }),
                });
                const data = await resp.json();
                if (data.success) {
                  const wpNum = data.whatsapp_number || (tenantPhone ? tenantPhone.replace(/\D/g, '') : '');
                  setLeadWhatsapp(wpNum);
                  setTimeout(() => { addBot(`Pronto, ${nome}! Seu contato foi registrado!`); setTimeout(() => { addBot('Um corretor vai entrar em contato em breve. Você pode iniciar uma conversa pelo WhatsApp agora mesmo!'); setStep('done'); }, 600); }, 500);
                } else {
                  addBot('Ops, tivemos um problema. Tente pelo WhatsApp direto!');
                  setLeadWhatsapp(tenantPhone ? tenantPhone.replace(/\D/g, '') : ''); setStep('done');
                }
              } catch {
                addBot('Ops, algo deu errado. Tente pelo WhatsApp direto!');
                setLeadWhatsapp(tenantPhone ? tenantPhone.replace(/\D/g, '') : ''); setStep('done');
              }
            }, 300);
          } else {
            setTimeout(() => { addBot('Sem problemas! Que tipo de imóvel você procura? (ex: apartamento 2 quartos, casa com quintal...)'); setStep('ask_interesse'); }, 400);
          }
        } else if (!val.includes('@')) {
          addBot('Hmm, e-mail inválido. Tente novamente ou digite "pular".'); return;
        } else {
          addUser(val); setEmail(val);
          if (interesse) {
            // Se já tem interesse (propertyContext), submeter direto
            setStep('submitting');
            setTimeout(async () => {
              addBot('Registrando seu contato...');
              try {
                const resp = await fetch('/api/portal/chat-lead', {
                  method: 'POST',
                  headers: { 'Content-Type': 'application/json', 'X-Tenant-Domain': window.location.hostname },
                  body: JSON.stringify({ nome, whatsapp, email: val, interesse }),
                });
                const data = await resp.json();
                if (data.success) {
                  const wpNum = data.whatsapp_number || (tenantPhone ? tenantPhone.replace(/\D/g, '') : '');
                  setLeadWhatsapp(wpNum);
                  setTimeout(() => { addBot(`Pronto, ${nome}! Seu contato foi registrado!`); setTimeout(() => { addBot('Um corretor vai entrar em contato em breve. Você pode iniciar uma conversa pelo WhatsApp agora mesmo!'); setStep('done'); }, 600); }, 500);
                } else {
                  addBot('Ops, tivemos um problema. Tente pelo WhatsApp direto!');
                  setLeadWhatsapp(tenantPhone ? tenantPhone.replace(/\D/g, '') : ''); setStep('done');
                }
              } catch {
                addBot('Ops, algo deu errado. Tente pelo WhatsApp direto!');
                setLeadWhatsapp(tenantPhone ? tenantPhone.replace(/\D/g, '') : ''); setStep('done');
              }
            }, 300);
          } else {
            setTimeout(() => { addBot('Ótimo! Que tipo de imóvel você procura? (ex: apartamento 2 quartos, casa com quintal...)'); setStep('ask_interesse'); }, 400);
          }
        }
        break;
      case 'ask_interesse':
        addUser(val); 
        const finalInteresse = interesse || val; // Use pre-set interesse if available
        setInteresse(finalInteresse); 
        setStep('submitting');
        setTimeout(async () => {
          addBot('Registrando seu contato...');
          try {
            const resp = await fetch('/api/portal/chat-lead', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json', 'X-Tenant-Domain': window.location.hostname },
              body: JSON.stringify({ nome, whatsapp, email: email || undefined, interesse: finalInteresse }),
            });
            const data = await resp.json();
            if (data.success) {
              const wpNum = data.whatsapp_number || (tenantPhone ? tenantPhone.replace(/\D/g, '') : '');
              setLeadWhatsapp(wpNum);
              setTimeout(() => { addBot(`Pronto, ${nome}! Seu contato foi registrado!`); setTimeout(() => { addBot('Um corretor vai entrar em contato em breve. Você pode iniciar uma conversa pelo WhatsApp agora mesmo!'); setStep('done'); }, 600); }, 500);
            } else {
              addBot('Ops, tivemos um problema. Tente pelo WhatsApp direto!');
              setLeadWhatsapp(tenantPhone ? tenantPhone.replace(/\D/g, '') : ''); setStep('done');
            }
          } catch {
            addBot('Ops, algo deu errado. Tente pelo WhatsApp direto!');
            setLeadWhatsapp(tenantPhone ? tenantPhone.replace(/\D/g, '') : ''); setStep('done');
          }
        }, 300);
        break;
    }
  };

  const waLink = leadWhatsapp
    ? `https://wa.me/${leadWhatsapp}?text=${encodeURIComponent(`Olá! Sou ${nome}, acabei de me cadastrar pelo portal. Estou interessado em: ${interesse}`)}`
    : tenantPhone ? `https://wa.me/${tenantPhone.replace(/\D/g, '')}?text=${encodeURIComponent('Olá! Tenho interesse em imóveis.')}` : '';

  return (
    <>
      {isOpen && (
        <div className="fixed bottom-[330px] right-6 z-[60] w-[480px] rounded-3xl shadow-2xl overflow-hidden flex flex-col" style={{ maxHeight: 'min(650px, calc(100vh - 360px))', backgroundColor: '#fff' }}>
          <div className="flex items-center gap-4 px-6 py-5 text-white flex-shrink-0" style={{ backgroundColor: primary }}>
            <MascotAvatar size={100} mascotUrl={effectiveMascotUrl} primary={primary} />
            <div className="flex-1 min-w-0">
              <div className="font-bold text-2xl leading-tight">{tenantName || 'Assistente'}</div>
              <div className="text-base opacity-90 flex items-center gap-2 mt-1"><Bot className="w-5 h-5" />Assistente Virtual</div>
            </div>
            <button onClick={() => onOpenChange(false)} className="w-7 h-7 rounded-full flex items-center justify-center hover:bg-white/20 transition-colors"><X className="w-4 h-4" /></button>
          </div>
          <div className="flex-1 overflow-y-auto p-4 space-y-3" style={{ backgroundColor: '#f0ede8', minHeight: '280px' }}>
            {messages.map((msg, i) => (
              <div key={i} className={`flex ${msg.from === 'user' ? 'justify-end' : 'justify-start'}`}>
                {msg.from === 'bot' && <div className="w-7 h-7 rounded-full flex items-center justify-center mr-2 flex-shrink-0 mt-0.5" style={{ backgroundColor: '#f5f5f5' }}><MascotAvatar size={22} mascotUrl={effectiveMascotUrl} primary={primary} /></div>}
                <div className={`max-w-[75%] px-3.5 py-2.5 rounded-2xl text-sm leading-relaxed ${msg.from === 'user' ? 'text-white rounded-br-md' : 'rounded-bl-md'}`} style={{ backgroundColor: msg.from === 'user' ? primary : '#fff', color: msg.from === 'user' ? '#fff' : '#333', boxShadow: '0 1px 2px rgba(0,0,0,0.06)' }}>{msg.text}</div>
              </div>
            ))}
            {step === 'done' && waLink && (
              <div className="flex justify-start">
                <div className="w-7 h-7 mr-2 flex-shrink-0" />
                <a href={waLink} target="_blank" rel="noopener noreferrer" className="flex items-center gap-2 px-4 py-3 bg-green-500 hover:bg-green-600 text-white rounded-2xl rounded-bl-md text-sm font-medium transition-colors shadow-sm">
                  <MessageCircle className="w-5 h-5" />Continuar no WhatsApp
                </a>
              </div>
            )}
            <div ref={messagesEndRef} />
          </div>
          {step !== 'done' && step !== 'submitting' && step !== 'greeting' && (
            <div className="flex items-center gap-2 px-3 py-3" style={{ borderTop: '1px solid #e8e4de' }}>
              <input
                ref={inputRef}
                type={step === 'ask_whatsapp' ? 'tel' : step === 'ask_email' ? 'email' : 'text'}
                value={input}
                onChange={(e) => step === 'ask_whatsapp' ? setInput(fmtWa(e.target.value)) : setInput(e.target.value)}
                onKeyDown={(e) => e.key === 'Enter' && handleSend()}
                placeholder={step === 'ask_name' ? 'Seu nome...' : step === 'ask_whatsapp' ? '(31) 99999-8888' : step === 'ask_email' ? 'seu@email.com' : 'Ex: apartamento 2 quartos...'}
                className="flex-1 px-3 py-2 rounded-xl text-sm focus:outline-none focus:ring-2"
                style={{ backgroundColor: '#f5f3ee', border: '1px solid #e0dcd5', color: '#333' }}
              />
              <button onClick={handleSend} className="w-9 h-9 rounded-xl flex items-center justify-center text-white flex-shrink-0" style={{ backgroundColor: primary }}><Send className="w-4 h-4" /></button>
            </div>
          )}
          <div className="px-4 py-1.5 text-center" style={{ borderTop: '1px solid #e8e4de', backgroundColor: '#fafaf8' }}>
            <span className="text-[10px]" style={{ color: '#aaa' }}>Atendimento automatizado por IA</span>
          </div>
        </div>
      )}
      <button
        onClick={() => onOpenChange(!isOpen)}
        className="fixed bottom-6 right-6 z-[60] w-[300px] h-[300px] flex items-center justify-center transition-all duration-300 hover:scale-105"
        style={{ backgroundColor: 'transparent' }}
      >
        {isOpen ? <X className="w-20 h-20" style={{ color: primary }} /> : (
          <div className="relative">
            <MascotAvatar size={280} mascotUrl={effectiveMascotUrl} primary={primary} roundedClass="rounded-2xl" fitClass="object-contain" imagePadding={0} imageBackground="transparent" />
          </div>
        )}
      </button>
      {!isOpen && !propertyContext && (
        <div className="fixed bottom-[330px] right-6 z-[59] rounded-2xl shadow-2xl px-6 py-4 text-2xl font-bold animate-bounce border" style={{ backgroundColor: '#fff', color: primary, animationDuration: '2s', borderColor: `${primary}33` }}>
          <div className="absolute bottom-0 right-[130px] w-6 h-6 rotate-45 translate-y-3 shadow-lg border-l border-b" style={{ backgroundColor: '#fff', borderColor: `${primary}33` }} />
          Posso te ajudar?
        </div>
      )}
    </>
  );
}

// ===== Main Component =====
export default function ClientPortal() {
  const [, navigate] = useLocation();
  const [searchTerm, setSearchTerm] = useState('');
  const [priceRangeIndex, setPriceRangeIndex] = useState(0);
  const [selectedBedrooms, setSelectedBedrooms] = useState<number | null>(null);
  const [selectedType, setSelectedType] = useState<string | null>(null);
  const [selectedPropertyType, setSelectedPropertyType] = useState('');
  const [sortOption, setSortOption] = useState<SortOption>('recent');
  const [properties, setProperties] = useState<Property[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [likedProperties, setLikedProperties] = useState<Set<number>>(new Set());
  const [tenant, setTenant] = useState<TenantConfig | null>(null);
  const [hoveredProperty, setHoveredProperty] = useState<number | null>(null);
  const [mapBounds, setMapBounds] = useState<MapBounds | null>(null);
  const [openDropdown, setOpenDropdown] = useState<string | null>(null);
  const [showMobileMap, setShowMobileMap] = useState(false);
  const [showEvalModal, setShowEvalModal] = useState(false);
  const [evalForm, setEvalForm] = useState({ nome: '', telefone: '', email: '', tipo_imovel: '', endereco: '', bairro: '', cidade: '', observacoes: '' });
  const [evalSubmitting, setEvalSubmitting] = useState(false);
  const [evalDone, setEvalDone] = useState(false);
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
  const [visibleCount, setVisibleCount] = useState(12);
  const [chatOpen, setChatOpen] = useState(false);
  const [chatPropertyContext, setChatPropertyContext] = useState<{ id: number; titulo: string; preco: string } | undefined>(undefined);
  // theme forced to light via useEffect below

  // Load tenant configuration
  useEffect(() => {
    const loadTenant = async () => {
      try {
        const data = await fetchTenantBranding();
        if (data) {
          setTenant(data as TenantConfig);
          if (data.name) {
            document.title = `${data.name} - Portal de Imóveis`;
          }
          const faviconUrl = data.favicon_url || data.logo_url || data.logo;
          if (faviconUrl) {
            let faviconLink = document.querySelector("link[rel~='icon']") as HTMLLinkElement | null;
            if (!faviconLink) {
              faviconLink = document.createElement("link");
              faviconLink.rel = "icon";
              document.head.appendChild(faviconLink);
            }
            faviconLink.href = faviconUrl;
          }
        }
      } catch (err) {
        console.error('Failed to load tenant branding:', err);
      }
    };
    loadTenant();
  }, []);

  // Load properties
  useEffect(() => {
    const fetchProperties = async () => {
      try {
        setError(null);
        setLoading(true);
        const response = await api.get('/portal/imoveis');
        const data = response.data.data || response.data || [];

        const propertiesWithCoords = data.map((prop: Property) => {
          if (prop.latitude && prop.longitude) return prop;
          const cityCoords = getCityCoords(prop.cidade);
          if (cityCoords) {
            return {
              ...prop,
              latitude: cityCoords.lat + (Math.random() - 0.5) * 0.02,
              longitude: cityCoords.lng + (Math.random() - 0.5) * 0.02,
            };
          }
          return prop;
        });

        setProperties(propertiesWithCoords);
      } catch (err: any) {
        setError(err.message || 'Erro ao carregar imóveis');
      } finally {
        setLoading(false);
      }
    };
    fetchProperties();
  }, []);

  // Close dropdown on outside click
  useEffect(() => {
    if (!openDropdown) return;
    const handler = () => setOpenDropdown(null);
    document.addEventListener('click', handler);
    return () => document.removeEventListener('click', handler);
  }, [openDropdown]);

  const primary = tenant?.primary_color || '#1e40af';

  const handleLike = async (propertyId: number, e: React.MouseEvent) => {
    e.stopPropagation();
    try {
      const response = await api.post(`/portal/likes/${propertyId}`);
      if (response.data.success) {
        setLikedProperties((prev) => new Set(Array.from(prev).concat(propertyId)));
        toast.success('Adicionado aos favoritos!');
      }
    } catch (err: any) {
      if (err.response?.status === 401) {
        toast.error('Faça login para favoritar');
      } else {
        toast.error('Erro ao favoritar');
      }
    }
  };

  const handleShare = async (property: Property, e: React.MouseEvent) => {
    e.stopPropagation();
    const shareUrl = `${window.location.origin}/portal/imovel/${property.id}`;
    if (navigator.share) {
      try {
        await navigator.share({ title: property.titulo, url: shareUrl });
      } catch {
        // cancelled
      }
    } else {
      await navigator.clipboard.writeText(shareUrl);
      toast.success('Link copiado!');
    }
  };

  const handleWhatsApp = (e: React.MouseEvent, property?: Property) => {
    e.stopPropagation();
    if (tenant?.contact_phone) {
      const phone = tenant.contact_phone.replace(/\D/g, '');
      let message = '';
      if (property) {
        const price = property.valor_venda || property.valor_aluguel || 0;
        const location = [property.bairro, property.cidade].filter(Boolean).join(', ') || 'Não informado';
        message = encodeURIComponent(
          `Olá! Tenho interesse no imóvel:\n\n` +
          `Título: ${property.titulo}\n` +
          `Localização: ${location}\n` +
          `Valor: ${formatPrice(price)}\n` +
          `Link: ${window.location.origin}/portal/imovel/${property.id}\n\n` +
          `Gostaria de mais informações.`
        );
      }
      const url = message
        ? `https://wa.me/${phone}?text=${message}`
        : `https://wa.me/${phone}`;
      window.open(url, '_blank');
    }
  };

  // Filter and sort
  const filteredProperties = useMemo(() => {
    let result = properties.filter((prop) => {
      const searchMatch =
        !searchTerm ||
        prop.titulo?.toLowerCase().includes(searchTerm.toLowerCase()) ||
        prop.bairro?.toLowerCase().includes(searchTerm.toLowerCase()) ||
        prop.cidade?.toLowerCase().includes(searchTerm.toLowerCase()) ||
        prop.tipo_imovel?.toLowerCase().includes(searchTerm.toLowerCase()) ||
        prop.logradouro?.toLowerCase().includes(searchTerm.toLowerCase());

      const price = prop.valor_venda || prop.valor_aluguel || 0;
      const priceRange = PRICE_RANGES[priceRangeIndex];
      const priceMatch = price >= priceRange.min && price <= priceRange.max;

      const bedrooms = prop.quartos ?? prop.dormitorios ?? 0;
      const bedroomsMatch = !selectedBedrooms || bedrooms >= selectedBedrooms;

      const typeMatch =
        !selectedType ||
        prop.tipo_negocio?.toLowerCase() === selectedType.toLowerCase() ||
        prop.finalidade_imovel?.toLowerCase().includes(selectedType.toLowerCase());

      const propTypeMatch =
        !selectedPropertyType ||
        prop.tipo_imovel?.toLowerCase().includes(selectedPropertyType.toLowerCase());

      return searchMatch && priceMatch && bedroomsMatch && typeMatch && propTypeMatch;
    });

    switch (sortOption) {
      case 'price_asc':
        result.sort((a, b) => (a.valor_venda || 0) - (b.valor_venda || 0));
        break;
      case 'price_desc':
        result.sort((a, b) => (b.valor_venda || 0) - (a.valor_venda || 0));
        break;
      case 'area_desc':
        result.sort((a, b) => (b.area_total || 0) - (a.area_total || 0));
        break;
      default:
        break;
    }

    return result;
  }, [properties, searchTerm, priceRangeIndex, selectedBedrooms, selectedType, selectedPropertyType, sortOption]);

  // Display properties (filtered by map bounds on desktop)
  const displayProperties = useMemo(() => {
    if (!mapBounds) return filteredProperties;
    if (typeof window !== 'undefined' && window.innerWidth < 1024) return filteredProperties;
    return filteredProperties.filter((prop) => {
      if (!prop.latitude || !prop.longitude) return true;
      return (
        prop.latitude >= mapBounds.south &&
        prop.latitude <= mapBounds.north &&
        prop.longitude >= mapBounds.west &&
        prop.longitude <= mapBounds.east
      );
    });
  }, [filteredProperties, mapBounds]);

  const hasActiveFilters =
    searchTerm || priceRangeIndex > 0 || selectedBedrooms || selectedType || selectedPropertyType;

  const clearFilters = useCallback(() => {
    setSearchTerm('');
    setPriceRangeIndex(0);
    setSelectedBedrooms(null);
    setSelectedType(null);
    setSelectedPropertyType('');
  }, []);

  const handleEvalSubmit = async () => {
    if (!evalForm.nome || !evalForm.telefone) { toast.error('Preencha nome e telefone'); return; }
    setEvalSubmitting(true);
    try {
      const resp = await fetch('/api/portal/avaliacao', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Tenant-Domain': window.location.hostname },
        body: JSON.stringify(evalForm),
      });
      const data = await resp.json();
      if (data.success) {
        setEvalDone(true);
        toast.success('Solicitacao enviada com sucesso!');
      } else {
        toast.error(data.error || 'Erro ao enviar solicitacao');
      }
    } catch {
      toast.error('Erro ao enviar solicitacao');
    } finally {
      setEvalSubmitting(false);
    }
  };

  const formatPrice = (value: number) => {
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL',
      maximumFractionDigits: 0,
    }).format(value);
  };

  const getPhotos = (property: Property): string[] => {
    const photos: string[] = [];
    if (property.fotos) {
      photos.push(...property.fotos.map((f) => f.url));
    }
    if (property.imagens) {
      photos.push(...property.imagens.filter((img) => !photos.includes(img)));
    }
    return photos;
  };

  const generateDescription = (property: Property) => {
    const tipo = property.tipo_imovel?.toLowerCase() || 'imóvel';
    const area = property.area_util || property.area_total;
    const bedrooms = property.quartos ?? property.dormitorios;
    const isRental = property.finalidade_imovel?.toLowerCase().includes('aluguel');
    let desc = isRental ? `Aluguel de ${tipo}` : `Comprar ${tipo}`;
    if (property.bairro) desc += ` em ${property.bairro}`;
    if (area) desc += `, ${area} m²`;
    if (bedrooms) desc += bedrooms === 1 ? '. 1 quarto' : `. ${bedrooms} quartos`;
    desc += '.';
    return desc;
  };

  // Map center
  const mapCenter = useMemo((): [number, number] => {
    const propWithCoords = filteredProperties.find((p) => p.latitude && p.longitude);
    if (propWithCoords) return [propWithCoords.latitude!, propWithCoords.longitude!];
    return [-15.7801, -47.9292];
  }, [filteredProperties]);

  // Finalidade filter visibility
  const showFinalidadeFilter = useMemo(() => {
    const pf = tenant?.portal_finalidades?.map((f) => f.toLowerCase()) ?? [];
    const showVenda = pf.length === 0 || pf.some((f) => f.includes('venda'));
    const showAluguel = pf.length === 0 || pf.some((f) => f.includes('aluguel') || f.includes('locação') || f.includes('locacao'));
    return showVenda && showAluguel;
  }, [tenant]);

  // ===== Property Card =====
  const PropertyCard = ({ property }: { property: Property }) => {
    const photos = getPhotos(property);
    const price = property.valor_venda || property.valor_aluguel || 0;
    const bedrooms = property.quartos ?? property.dormitorios;
    const area = property.area_util || property.area_total;
    const isLiked = likedProperties.has(property.id);
    const description = generateDescription(property);
    const address = [property.logradouro, property.bairro, property.cidade]
      .filter(Boolean)
      .join(', ');

    const badges: { label: string; className: string }[] = [];
    if (property.destaque) {
      badges.push({ label: 'Destaque', className: 'bg-amber-500 text-white' });
    }

    const details = [
      area ? `${area} m²` : null,
      bedrooms ? `${bedrooms} quarto${bedrooms > 1 ? 's' : ''}` : null,
    ]
      .filter(Boolean)
      .join(' \u00B7 ');

    return (
      <div
        onClick={() => navigate(`/portal/imovel/${property.id}`)}
        onMouseEnter={() => setHoveredProperty(property.id)}
        onMouseLeave={() => setHoveredProperty(null)}
        className={cn(
          'group rounded-xl overflow-hidden cursor-pointer transition-shadow duration-200',
          hoveredProperty === property.id ? 'shadow-lg' : 'shadow-sm hover:shadow-md'
        )}
        style={{ backgroundColor: '#ffffff' }}
      >
        {/* Photo carousel */}
        <div className="relative aspect-[4/3] overflow-hidden">
          <PhotoCarousel
            photos={photos}
            onLike={(e) => handleLike(property.id, e)}
            isLiked={isLiked}
            badges={badges}
          />
        </div>

        {/* Content */}
        <div className="p-4">
          <p className="text-sm line-clamp-2 leading-snug mb-2.5" style={{ color: '#444' }}>
            {description}
          </p>

          <p className="text-[17px] font-bold leading-tight" style={{ color: '#1a1a1a' }}>
            {formatPrice(price)}
          </p>
          {property.finalidade_imovel?.toLowerCase().includes('aluguel') && (
            <p className="text-xs mt-0.5" style={{ color: '#888' }}>/mês</p>
          )}

          {details && (
            <p className="text-sm mt-2.5" style={{ color: '#666' }}>{details}</p>
          )}

          {address && (
            <p className="text-xs mt-1 truncate" style={{ color: '#888' }}>{address}</p>
          )}

          <button
            onClick={(e) => {
              e.stopPropagation();
              setChatPropertyContext({ id: property.id, titulo: description, preco: formatPrice(price) });
              setChatOpen(true);
            }}
            className="w-full mt-3 py-2 px-4 rounded-lg text-sm font-medium transition-colors flex items-center justify-center gap-2"
            style={{ backgroundColor: `${primary}15`, color: primary }}
          >
            <MessageCircle className="w-4 h-4" />
            Entrar em Contato
          </button>
        </div>
      </div>
    );
  };

  // ===== Map Render (shared between desktop and mobile) =====
  const renderMap = (className?: string) => (
    <MapContainer
      center={mapCenter}
      zoom={filteredProperties.length > 0 ? 12 : 4}
      className={cn('h-full w-full', className)}
    >
      <TileLayer
        attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        url="https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png"
      />
      <MapBoundsHandler
        properties={filteredProperties}
        onBoundsChange={setMapBounds}
        initialFit={true}
      />
      {filteredProperties.map((property) => {
        if (!property.latitude || !property.longitude) return null;
        const price = property.valor_venda || property.valor_aluguel || 0;
        const propertyIcon = createPropertyIcon(property.tipo_imovel, primary);

        return (
          <Marker
            key={property.id}
            position={[property.latitude, property.longitude]}
            icon={propertyIcon}
            eventHandlers={{
              click: () => setHoveredProperty(property.id),
            }}
          >
            <Popup>
              <div className="w-56">
                {property.fotos?.[0]?.url && (
                  <img
                    src={property.fotos[0].url}
                    alt={property.titulo}
                    className="w-full h-28 object-cover rounded-t-lg -mt-3 -mx-3 mb-2"
                    style={{ width: 'calc(100% + 24px)' }}
                  />
                )}
                <span
                  className="text-[10px] px-1.5 py-0.5 rounded font-medium"
                  style={{ backgroundColor: `${primary}15`, color: primary }}
                >
                  {property.tipo_imovel}
                </span>
                <h4 className="font-semibold text-sm line-clamp-1 mt-1">{property.titulo}</h4>
                <p className="text-xs text-gray-500 mb-1.5">
                  {property.bairro}, {property.cidade}
                </p>
                <p className="font-bold text-base" style={{ color: primary }}>
                  {formatPrice(price)}
                </p>
                <Button
                  size="sm"
                  className="w-full mt-2 text-white text-xs"
                  style={{ backgroundColor: primary }}
                  onClick={() => navigate(`/portal/imovel/${property.id}`)}
                >
                  Ver detalhes
                </Button>
              </div>
            </Popup>
          </Marker>
        );
      })}
    </MapContainer>
  );

  // Force light theme for portal
  useEffect(() => {
    const root = document.documentElement;
    const wasDark = root.classList.contains('dark');
    root.classList.remove('dark');
    return () => { if (wasDark) root.classList.add('dark'); };
  }, []);

  // ===== RENDER =====
  return (
    <div className="portal-public min-h-screen" style={{ backgroundColor: '#f7f5f0' }}>
      {/* ===== STICKY HEADER ===== */}
      <div className="sticky top-0 z-50 border-b" style={{ backgroundColor: '#ffffff', borderColor: '#e8e4de' }}>
        {/* Row 1: Logo + Actions */}
        <div className="px-4 lg:px-6">
          <div className="flex items-center justify-between h-[60px]">
            <div className="flex items-center gap-3 min-w-0">
              {tenant?.logo_url || tenant?.logo ? (
                <img
                  src={tenant.logo_url || tenant.logo}
                  alt={tenant?.name || 'Logo'}
                  className="h-8 w-auto object-contain flex-shrink-0"
                />
              ) : (
                <div
                  className="w-8 h-8 rounded-lg flex items-center justify-center text-white font-bold text-sm flex-shrink-0"
                  style={{ backgroundColor: primary }}
                >
                  {tenant?.name?.substring(0, 2).toUpperCase() || 'IM'}
                </div>
              )}
              <span className="font-semibold hidden sm:block truncate" style={{ color: '#1a1a1a' }}>
                {tenant?.name || 'Imobiliária'}
              </span>
            </div>

            <div className="flex items-center gap-1.5">
              {tenant?.contact_phone && (
                <Button
                  variant="ghost"
                  size="sm"
                  className="hidden md:flex text-green-600 hover:text-green-700"
                  onClick={(e) => handleWhatsApp(e)}
                >
                  <MessageCircle className="w-4 h-4" />
                  <span className="ml-1">WhatsApp</span>
                </Button>
              )}
              <Button
                size="sm"
                style={{ backgroundColor: primary }}
                className="text-white"
                onClick={() => navigate('/login')}
              >
                Entrar
              </Button>
              <button
                className="md:hidden w-9 h-9 flex items-center justify-center rounded-lg hover:bg-gray-100"
                onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
              >
                {mobileMenuOpen ? <X className="w-5 h-5" style={{ color: '#555' }} /> : <Menu className="w-5 h-5" style={{ color: '#555' }} />}
              </button>
            </div>
          </div>
        </div>

        {/* Desktop Navigation */}
        <nav className="hidden md:flex items-center gap-0.5 px-4 lg:px-6 pb-1">
          {[
            { id: 'inicio', label: 'Início' },
            { id: 'imoveis', label: 'Imóveis' },
            { id: 'servicos', label: 'Serviços' },
            { id: 'avaliacao', label: 'Avaliação' },
            { id: 'sobre', label: 'Sobre Nós' },
          ].map(item => (
            <a
              key={item.id}
              href={`#${item.id}`}
              onClick={(e) => { e.preventDefault(); document.getElementById(item.id)?.scrollIntoView({ behavior: 'smooth' }); }}
              className="px-3 py-1.5 text-sm font-medium rounded-lg hover:bg-gray-100 transition-colors"
              style={{ color: '#555' }}
            >
              {item.label}
            </a>
          ))}
        </nav>

        {/* Row 2: Search + Filter Chips */}
        <div className="px-4 lg:px-6 pb-3 pt-0.5">
          <div className="flex items-center gap-2 flex-wrap">
            {/* Search input */}
            <div className="relative flex-shrink-0 w-56 lg:w-72">
              <MapPin className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4" style={{ color: '#999' }} />
              <input
                type="text"
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                placeholder="Qualquer lugar..."
                className="w-full pl-9 pr-8 py-2 rounded-lg text-sm focus:outline-none focus:ring-2"
                style={{ backgroundColor: '#f5f3ee', border: '1px solid #e0dcd5', color: '#333' }}
              />
              {searchTerm && (
                <button
                  onClick={() => setSearchTerm('')}
                  className="absolute right-2.5 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                >
                  <X className="w-3.5 h-3.5" />
                </button>
              )}
            </div>

            <div className="w-px h-6 bg-border flex-shrink-0" />

            {/* Finalidade chip */}
            {showFinalidadeFilter && (
              <div className="relative flex-shrink-0" onClick={(e) => e.stopPropagation()}>
                <button
                  type="button"
                  onClick={() => setOpenDropdown(openDropdown === 'finalidade' ? null : 'finalidade')}
                  className={cn(
                    'flex items-center gap-1.5 px-3 py-2 rounded-lg border text-sm font-medium transition-colors whitespace-nowrap',
                    selectedType
                      ? 'border-primary/40 bg-primary/5 text-primary'
                      : 'border-border text-foreground hover:bg-muted/50'
                  )}
                  style={selectedType ? { borderColor: `${primary}40`, backgroundColor: `${primary}08`, color: primary } : {}}
                >
                  {selectedType === 'aluguel' ? 'Alugar' : 'Comprar'}
                  {selectedType ? (
                    <X
                      className="w-3.5 h-3.5 ml-0.5"
                      onClick={(e) => {
                        e.stopPropagation();
                        setSelectedType(null);
                      }}
                    />
                  ) : (
                    <ChevronDown className="w-3.5 h-3.5" />
                  )}
                </button>
                {openDropdown === 'finalidade' && (
                  <div className="absolute top-full left-0 mt-1.5 rounded-lg shadow-xl py-1 z-50 min-w-[140px]" style={{ backgroundColor: '#fff', border: '1px solid #e0dcd5' }}>
                    <button
                      type="button"
                      onClick={() => {
                        setSelectedType('venda');
                        setOpenDropdown(null);
                      }}
                      className={cn(
                        'w-full px-4 py-2.5 text-sm text-left hover:bg-muted/50 transition-colors',
                        selectedType === 'venda' && 'font-semibold'
                      )}
                      style={selectedType === 'venda' ? { color: primary } : {}}
                    >
                      Comprar
                    </button>
                    <button
                      type="button"
                      onClick={() => {
                        setSelectedType('aluguel');
                        setOpenDropdown(null);
                      }}
                      className={cn(
                        'w-full px-4 py-2.5 text-sm text-left hover:bg-muted/50 transition-colors',
                        selectedType === 'aluguel' && 'font-semibold'
                      )}
                      style={selectedType === 'aluguel' ? { color: primary } : {}}
                    >
                      Alugar
                    </button>
                  </div>
                )}
              </div>
            )}

            {/* Price chip */}
            <div className="relative flex-shrink-0" onClick={(e) => e.stopPropagation()}>
              <button
                type="button"
                onClick={() => setOpenDropdown(openDropdown === 'price' ? null : 'price')}
                className={cn(
                  'flex items-center gap-1.5 px-3 py-2 rounded-lg border text-sm font-medium transition-colors whitespace-nowrap',
                  priceRangeIndex > 0
                    ? 'border-primary/40 bg-primary/5 text-primary'
                    : 'border-border text-foreground hover:bg-muted/50'
                )}
                style={
                  priceRangeIndex > 0
                    ? { borderColor: `${primary}40`, backgroundColor: `${primary}08`, color: primary }
                    : {}
                }
              >
                {priceRangeIndex > 0 ? PRICE_RANGES[priceRangeIndex].label : 'Valor do imóvel'}
                {priceRangeIndex > 0 ? (
                  <X
                    className="w-3.5 h-3.5 ml-0.5"
                    onClick={(e) => {
                      e.stopPropagation();
                      setPriceRangeIndex(0);
                    }}
                  />
                ) : (
                  <ChevronDown className="w-3.5 h-3.5" />
                )}
              </button>
              {openDropdown === 'price' && (
                <div className="absolute top-full left-0 mt-1.5 rounded-lg shadow-xl py-1 z-50 min-w-[180px]" style={{ backgroundColor: '#fff', border: '1px solid #e0dcd5' }}>
                  {PRICE_RANGES.map((range, idx) => (
                    <button
                      type="button"
                      key={idx}
                      onClick={() => {
                        setPriceRangeIndex(idx);
                        setOpenDropdown(null);
                      }}
                      className={cn(
                        'w-full px-4 py-2.5 text-sm text-left hover:bg-muted/50 transition-colors',
                        priceRangeIndex === idx && 'font-semibold'
                      )}
                      style={priceRangeIndex === idx ? { color: primary } : {}}
                    >
                      {range.label}
                    </button>
                  ))}
                </div>
              )}
            </div>

            {/* Property type chip */}
            <div className="relative flex-shrink-0" onClick={(e) => e.stopPropagation()}>
              <button
                type="button"
                onClick={() => setOpenDropdown(openDropdown === 'type' ? null : 'type')}
                className={cn(
                  'flex items-center gap-1.5 px-3 py-2 rounded-lg border text-sm font-medium transition-colors whitespace-nowrap',
                  selectedPropertyType
                    ? 'border-primary/40 bg-primary/5 text-primary'
                    : 'border-border text-foreground hover:bg-muted/50'
                )}
                style={
                  selectedPropertyType
                    ? { borderColor: `${primary}40`, backgroundColor: `${primary}08`, color: primary }
                    : {}
                }
              >
                {selectedPropertyType
                  ? PROPERTY_TYPES.find((t) => t.value === selectedPropertyType)?.label || selectedPropertyType
                  : 'Tipo de imóvel'}
                {selectedPropertyType ? (
                  <X
                    className="w-3.5 h-3.5 ml-0.5"
                    onClick={(e) => {
                      e.stopPropagation();
                      setSelectedPropertyType('');
                    }}
                  />
                ) : (
                  <ChevronDown className="w-3.5 h-3.5" />
                )}
              </button>
              {openDropdown === 'type' && (
                <div className="absolute top-full left-0 mt-1.5 rounded-lg shadow-xl py-1 z-50 min-w-[160px]" style={{ backgroundColor: '#fff', border: '1px solid #e0dcd5' }}>
                  {PROPERTY_TYPES.filter((t) => t.value).map((type) => (
                    <button
                      type="button"
                      key={type.value}
                      onClick={() => {
                        setSelectedPropertyType(type.value);
                        setOpenDropdown(null);
                      }}
                      className={cn(
                        'w-full px-4 py-2.5 text-sm text-left hover:bg-muted/50 transition-colors',
                        selectedPropertyType === type.value && 'font-semibold'
                      )}
                      style={selectedPropertyType === type.value ? { color: primary } : {}}
                    >
                      {type.label}
                    </button>
                  ))}
                </div>
              )}
            </div>

            {/* Bedrooms chip */}
            <div className="relative flex-shrink-0" onClick={(e) => e.stopPropagation()}>
              <button
                type="button"
                onClick={() => setOpenDropdown(openDropdown === 'bedrooms' ? null : 'bedrooms')}
                className={cn(
                  'flex items-center gap-1.5 px-3 py-2 rounded-lg border text-sm font-medium transition-colors whitespace-nowrap',
                  selectedBedrooms
                    ? 'border-primary/40 bg-primary/5 text-primary'
                    : 'border-border text-foreground hover:bg-muted/50'
                )}
                style={
                  selectedBedrooms
                    ? { borderColor: `${primary}40`, backgroundColor: `${primary}08`, color: primary }
                    : {}
                }
              >
                {selectedBedrooms ? `${selectedBedrooms}+ quartos` : 'Quartos'}
                {selectedBedrooms ? (
                  <X
                    className="w-3.5 h-3.5 ml-0.5"
                    onClick={(e) => {
                      e.stopPropagation();
                      setSelectedBedrooms(null);
                    }}
                  />
                ) : (
                  <ChevronDown className="w-3.5 h-3.5" />
                )}
              </button>
              {openDropdown === 'bedrooms' && (
                <div className="absolute top-full left-0 mt-1.5 rounded-lg shadow-xl p-2 z-50" style={{ backgroundColor: '#fff', border: '1px solid #e0dcd5' }}>
                  <div className="flex gap-1">
                    {[1, 2, 3, 4].map((num) => (
                      <button
                        type="button"
                        key={num}
                        onClick={() => {
                          setSelectedBedrooms(num);
                          setOpenDropdown(null);
                        }}
                        className={cn(
                          'px-4 py-2 rounded-lg text-sm font-medium transition-colors',
                          selectedBedrooms === num ? 'text-white' : 'hover:bg-muted/50 text-foreground'
                        )}
                        style={selectedBedrooms === num ? { backgroundColor: primary } : {}}
                      >
                        {num}+
                      </button>
                    ))}
                  </div>
                </div>
              )}
            </div>

            {/* Clear all */}
            {hasActiveFilters && (
              <button
                type="button"
                onClick={clearFilters}
                className="flex items-center gap-1 px-3 py-2 rounded-lg text-sm text-muted-foreground hover:text-foreground hover:bg-muted/50 flex-shrink-0 whitespace-nowrap transition-colors"
              >
                <X className="w-3.5 h-3.5" />
                Limpar
              </button>
            )}
          </div>
        </div>
      </div>

      {/* ===== MOBILE NAV MENU ===== */}
      {mobileMenuOpen && (
        <div className="md:hidden border-b" style={{ backgroundColor: '#fff', borderColor: '#e8e4de' }}>
          <nav className="px-4 py-3 space-y-1">
            {[
              { id: 'inicio', label: 'Início' },
              { id: 'imoveis', label: 'Imóveis' },
              { id: 'servicos', label: 'Serviços' },
              { id: 'avaliacao', label: 'Avaliação' },
              { id: 'sobre', label: 'Sobre Nós' },
            ].map(item => (
              <a
                key={item.id}
                href={`#${item.id}`}
                onClick={(e) => { e.preventDefault(); setMobileMenuOpen(false); document.getElementById(item.id)?.scrollIntoView({ behavior: 'smooth' }); }}
                className="block px-3 py-2.5 text-sm font-medium rounded-lg hover:bg-gray-100 transition-colors"
                style={{ color: '#555' }}
              >
                {item.label}
              </a>
            ))}
          </nav>
        </div>
      )}

      {/* ===== HERO SECTION (COMPACTO) ===== */}
      <section id="inicio" className="relative overflow-hidden" style={{ backgroundColor: primary }}>
        <div className="absolute inset-0 opacity-10">
          <div className="absolute inset-0" style={{ background: 'linear-gradient(135deg, rgba(255,255,255,0.2) 0%, transparent 50%)' }} />
        </div>
        <div className="relative max-w-6xl mx-auto px-4 lg:px-8 py-6 lg:py-8">
        
          <div className="flex flex-col items-center text-center gap-2">
            <h1 className="text-2xl lg:text-3xl font-bold text-white">
              {tenant?.name || 'Imobiliária'}
            </h1>
            {tenant?.creci && (
              <p className="text-xs font-medium text-white/70">CRECI: {tenant.creci}</p>
            )}
            <p className="text-sm text-white/80 max-w-2xl">
              Encontre o imóvel ideal para você. Use os filtros abaixo para buscar.
            </p>
          </div>
        </div>
      </section>

      {/* ===== SERVICES SECTION ===== */}
      {tenant?.services && tenant.services.length > 0 && (
        <section id="servicos" className="py-10 px-4 lg:px-8" style={{ backgroundColor: '#fff', borderBottom: '1px solid #e8e4de' }}>
          <div className="max-w-6xl mx-auto">
            <h2 className="text-xl font-bold text-center mb-6" style={{ color: '#1a1a1a' }}>Nossos Serviços</h2>
            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
              {tenant.services.map((svc, i) => (
                <div key={i} className="flex items-center gap-2.5 p-3 rounded-xl" style={{ backgroundColor: '#f7f5f0' }}>
                  <div className="w-8 h-8 rounded-lg flex items-center justify-center flex-shrink-0" style={{ backgroundColor: `${primary}15` }}>
                    <Home className="w-4 h-4" style={{ color: primary }} />
                  </div>
                  <span className="text-sm font-medium" style={{ color: '#444' }}>{svc}</span>
                </div>
              ))}
            </div>
          </div>
        </section>
      )}

      {/* ===== EVALUATION CTA BANNER ===== */}
      <section id="avaliacao" className="py-8 px-4 lg:px-8" style={{ backgroundColor: `${primary}08`, borderBottom: '1px solid #e8e4de' }}>
        <div className="max-w-4xl mx-auto flex flex-col sm:flex-row items-center gap-4 sm:gap-6">
          <div className="w-12 h-12 rounded-2xl flex items-center justify-center flex-shrink-0" style={{ backgroundColor: `${primary}15` }}>
            <ClipboardList className="w-6 h-6" style={{ color: primary }} />
          </div>
          <div className="flex-1 text-center sm:text-left">
            <h3 className="text-lg font-bold" style={{ color: '#1a1a1a' }}>Avaliação Gratuita do Seu Imóvel</h3>
            <p className="text-sm" style={{ color: '#666' }}>Quer saber quanto vale o seu imóvel? Solicite uma avaliação gratuita e sem compromisso.</p>
          </div>
          <Button
            onClick={() => { setShowEvalModal(true); setEvalDone(false); setEvalForm({ nome: '', telefone: '', email: '', tipo_imovel: '', endereco: '', bairro: '', cidade: '', observacoes: '' }); }}
            className="text-white px-6 py-2.5 rounded-xl font-medium flex-shrink-0"
            style={{ backgroundColor: primary }}
          >
            Solicitar Avaliação
          </Button>
        </div>
      </section>

      {/* ===== PROPERTY GRID SECTION ===== */}
      <section id="imoveis">
        <div className="flex" style={{ height: 'calc(100vh - 60px)' }}>
          {/* Cards Section */}
          <div className="flex-1 min-w-0 flex flex-col">
            {/* Results header */}
          <div className="px-4 lg:px-6 py-3 flex-shrink-0" style={{ borderBottom: '1px solid #e8e4de' }}>
            <div className="flex items-center justify-between">
              <div>
                <span className="text-base font-bold" style={{ color: '#1a1a1a' }}>
                  {displayProperties.length.toLocaleString('pt-BR')}{' '}
                  {displayProperties.length === 1 ? 'imóvel' : 'imóveis'}
                </span>
                <span className="text-sm ml-1.5" style={{ color: '#888' }}>
                  {selectedType === 'aluguel' ? 'para alugar' : 'à venda'}
                  {searchTerm ? ` em ${searchTerm}` : ''}
                </span>
              </div>
              <div className="relative">
                <select
                  value={sortOption}
                  onChange={(e) => setSortOption(e.target.value as SortOption)}
                  className="appearance-none pl-3 pr-8 py-1.5 bg-transparent border border-border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 cursor-pointer"
                >
                  <option value="recent">Mais recentes</option>
                  <option value="price_asc">Menor valor</option>
                  <option value="price_desc">Maior valor</option>
                  <option value="area_desc">Maior área</option>
                </select>
                <ChevronDown className="absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-muted-foreground pointer-events-none" />
              </div>
            </div>
          </div>

          {/* Property Grid */}
          <div className="flex-1 overflow-y-auto p-4 lg:p-5">
            {error ? (
              <div className="py-16 text-center">
                <p className="text-destructive mb-4">{error}</p>
                <Button variant="outline" onClick={() => window.location.reload()}>
                  Tentar novamente
                </Button>
              </div>
            ) : loading ? (
              <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 xl:grid-cols-3 gap-5 lg:gap-6">
                {Array.from({ length: 6 }).map((_, i) => (
                  <div key={i} className="rounded-xl overflow-hidden animate-pulse" style={{ backgroundColor: '#fff' }}>
                    <div className="aspect-[4/3]" style={{ backgroundColor: '#e8e4de' }} />
                    <div className="p-4 space-y-3">
                      <div className="h-4 rounded w-3/4" style={{ backgroundColor: '#e8e4de' }} />
                      <div className="h-5 rounded w-1/3" style={{ backgroundColor: '#e8e4de' }} />
                      <div className="h-3 rounded w-1/2" style={{ backgroundColor: '#e8e4de' }} />
                      <div className="h-3 rounded w-2/3" style={{ backgroundColor: '#e8e4de' }} />
                    </div>
                  </div>
                ))}
              </div>
            ) : displayProperties.length === 0 ? (
              <div className="py-16 text-center">
                <Home className="w-16 h-16 mx-auto mb-4 text-muted-foreground/30" />
                <h3 className="text-lg font-semibold text-foreground mb-2">
                  Nenhum imóvel encontrado
                </h3>
                <p className="text-sm text-muted-foreground mb-4">
                  Tente ajustar os filtros ou mover o mapa
                </p>
                {hasActiveFilters && (
                  <Button variant="outline" size="sm" onClick={clearFilters}>
                    Limpar filtros
                  </Button>
                )}
              </div>
            ) : (
              <>
                <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-2 xl:grid-cols-3 gap-5 lg:gap-6">
                  {displayProperties.slice(0, visibleCount).map((property) => (
                    <PropertyCard key={property.id} property={property} />
                  ))}
                </div>
                {visibleCount < displayProperties.length && (
                  <div className="text-center mt-8 pb-4">
                    <Button
                      variant="outline"
                      onClick={() => setVisibleCount(c => c + 12)}
                      className="px-8 py-2.5 rounded-xl"
                    >
                      Ver mais imóveis ({displayProperties.length - visibleCount} restantes)
                    </Button>
                  </div>
                )}
              </>
            )}
          </div>
        </div>

        {/* Map Section (Desktop) */}
        <div className="hidden lg:block w-[42%] flex-shrink-0 sticky top-[105px] h-[calc(100vh-105px)]" style={{ borderLeft: '1px solid #e8e4de' }}>
          {renderMap()}
        </div>
        </div>
      </section>

      {/* ===== MAP BUTTON ===== */}
      <div className="lg:hidden fixed bottom-20 left-1/2 -translate-x-1/2 z-40">
        <Button
          onClick={() => setShowMobileMap(true)}
          className="shadow-lg rounded-full text-white px-5"
          style={{ backgroundColor: primary }}
        >
          <MapIcon className="w-4 h-4 mr-1.5" />
          Ver mapa
        </Button>
      </div>

      {/* ===== MOBILE MAP OVERLAY ===== */}
      {showMobileMap && (
        <div className="fixed inset-0 z-[60]" style={{ backgroundColor: '#f7f5f0' }}>
          <div className="h-full flex flex-col">
            <div className="flex items-center justify-between px-4 py-3 border-b flex-shrink-0" style={{ borderColor: '#e8e4de', backgroundColor: '#fff' }}>
              <span className="font-semibold" style={{ color: '#1a1a1a' }}>
                {filteredProperties.length} imóveis no mapa
              </span>
              <Button size="sm" variant="ghost" onClick={() => setShowMobileMap(false)}>
                <X className="w-4 h-4 mr-1" />
                Fechar
              </Button>
            </div>
            <div className="flex-1">{renderMap()}</div>
          </div>
        </div>
      )}

      {/* ===== ABOUT SECTION ===== */}
      {tenant?.about_text && (
        <section id="sobre" className="py-10 px-4 lg:px-8" style={{ backgroundColor: '#fff', borderTop: '1px solid #e8e4de' }}>
          <div className="max-w-3xl mx-auto text-center">
            {(tenant.logo_url || tenant.logo) && (
              <div className="flex justify-center mb-4">
                <img src={tenant.logo_url || tenant.logo} alt={tenant.name} className="h-10 w-auto object-contain" />
              </div>
            )}
            <h2 className="text-xl font-bold mb-3" style={{ color: '#1a1a1a' }}>Sobre {tenant.name || 'Nós'}</h2>
            {tenant.creci && <p className="text-xs font-medium mb-3" style={{ color: primary }}>CRECI: {tenant.creci}</p>}
            <p className="text-sm leading-relaxed" style={{ color: '#555' }}>{tenant.about_text}</p>
            {tenant.endereco && <p className="text-xs mt-4 flex items-center justify-center gap-1" style={{ color: '#888' }}><MapPin className="w-3.5 h-3.5" />{tenant.endereco}</p>}
            {tenant.office_hours && <p className="text-xs mt-1" style={{ color: '#888' }}>{tenant.office_hours}</p>}
          </div>
        </section>
      )}

      {/* ===== EVALUATION MODAL ===== */}
      {showEvalModal && (
        <div className="fixed inset-0 z-[70] flex items-center justify-center bg-black/50 p-4">
          <div className="w-full max-w-md rounded-2xl shadow-2xl overflow-hidden" style={{ backgroundColor: '#fff' }}>
            <div className="flex items-center justify-between px-5 py-4 text-white" style={{ backgroundColor: primary }}>
              <div className="flex items-center gap-2">
                <ClipboardList className="w-5 h-5" />
                <span className="font-semibold">Solicitar Avaliação</span>
              </div>
              <button onClick={() => setShowEvalModal(false)} className="w-7 h-7 rounded-full flex items-center justify-center hover:bg-white/20"><X className="w-4 h-4" /></button>
            </div>
            {evalDone ? (
              <div className="p-8 text-center">
                <CheckCircle className="w-14 h-14 mx-auto mb-4" style={{ color: '#22c55e' }} />
                <h3 className="text-lg font-bold mb-2" style={{ color: '#1a1a1a' }}>Solicitação Enviada!</h3>
                <p className="text-sm mb-5" style={{ color: '#666' }}>Entraremos em contato em breve para agendar a avaliação do seu imóvel.</p>
                {tenant?.contact_phone && (
                  <a
                    href={`https://wa.me/${tenant.contact_phone.replace(/\D/g, '')}?text=${encodeURIComponent('Olá! Acabei de solicitar uma avaliação de imóvel pelo portal.')}`}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="inline-flex items-center gap-2 px-5 py-2.5 bg-green-500 hover:bg-green-600 text-white rounded-xl text-sm font-medium transition-colors"
                  >
                    <MessageCircle className="w-4 h-4" />Falar no WhatsApp
                  </a>
                )}
                <div className="mt-4">
                  <button onClick={() => setShowEvalModal(false)} className="text-sm underline" style={{ color: '#888' }}>Fechar</button>
                </div>
              </div>
            ) : (
              <div className="p-5 space-y-3 max-h-[70vh] overflow-y-auto">
                <div>
                  <label className="block text-xs font-medium mb-1" style={{ color: '#555' }}>Nome *</label>
                  <input value={evalForm.nome} onChange={(e) => setEvalForm(f => ({ ...f, nome: e.target.value }))} placeholder="Seu nome completo" className="w-full px-3 py-2 rounded-lg text-sm focus:outline-none focus:ring-2" style={{ backgroundColor: '#f5f3ee', border: '1px solid #e0dcd5', color: '#333' }} />
                </div>
                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="block text-xs font-medium mb-1" style={{ color: '#555' }}>Telefone *</label>
                    <input value={evalForm.telefone} onChange={(e) => setEvalForm(f => ({ ...f, telefone: e.target.value }))} placeholder="(31) 99999-8888" type="tel" className="w-full px-3 py-2 rounded-lg text-sm focus:outline-none focus:ring-2" style={{ backgroundColor: '#f5f3ee', border: '1px solid #e0dcd5', color: '#333' }} />
                  </div>
                  <div>
                    <label className="block text-xs font-medium mb-1" style={{ color: '#555' }}>E-mail</label>
                    <input value={evalForm.email} onChange={(e) => setEvalForm(f => ({ ...f, email: e.target.value }))} placeholder="seu@email.com" type="email" className="w-full px-3 py-2 rounded-lg text-sm focus:outline-none focus:ring-2" style={{ backgroundColor: '#f5f3ee', border: '1px solid #e0dcd5', color: '#333' }} />
                  </div>
                </div>
                <div>
                  <label className="block text-xs font-medium mb-1" style={{ color: '#555' }}>Tipo de Imóvel</label>
                  <select value={evalForm.tipo_imovel} onChange={(e) => setEvalForm(f => ({ ...f, tipo_imovel: e.target.value }))} className="w-full px-3 py-2 rounded-lg text-sm focus:outline-none focus:ring-2" style={{ backgroundColor: '#f5f3ee', border: '1px solid #e0dcd5', color: '#333' }}>
                    <option value="">Selecione...</option>
                    <option value="apartamento">Apartamento</option>
                    <option value="casa">Casa</option>
                    <option value="terreno">Terreno</option>
                    <option value="comercial">Comercial</option>
                    <option value="rural">Rural</option>
                    <option value="cobertura">Cobertura</option>
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-medium mb-1" style={{ color: '#555' }}>Endereço do Imóvel</label>
                  <input value={evalForm.endereco} onChange={(e) => setEvalForm(f => ({ ...f, endereco: e.target.value }))} placeholder="Rua, número" className="w-full px-3 py-2 rounded-lg text-sm focus:outline-none focus:ring-2" style={{ backgroundColor: '#f5f3ee', border: '1px solid #e0dcd5', color: '#333' }} />
                </div>
                <div className="grid grid-cols-2 gap-3">
                  <div>
                    <label className="block text-xs font-medium mb-1" style={{ color: '#555' }}>Bairro</label>
                    <input value={evalForm.bairro} onChange={(e) => setEvalForm(f => ({ ...f, bairro: e.target.value }))} placeholder="Bairro" className="w-full px-3 py-2 rounded-lg text-sm focus:outline-none focus:ring-2" style={{ backgroundColor: '#f5f3ee', border: '1px solid #e0dcd5', color: '#333' }} />
                  </div>
                  <div>
                    <label className="block text-xs font-medium mb-1" style={{ color: '#555' }}>Cidade</label>
                    <input value={evalForm.cidade} onChange={(e) => setEvalForm(f => ({ ...f, cidade: e.target.value }))} placeholder="Cidade" className="w-full px-3 py-2 rounded-lg text-sm focus:outline-none focus:ring-2" style={{ backgroundColor: '#f5f3ee', border: '1px solid #e0dcd5', color: '#333' }} />
                  </div>
                </div>
                <div>
                  <label className="block text-xs font-medium mb-1" style={{ color: '#555' }}>Observações</label>
                  <textarea value={evalForm.observacoes} onChange={(e) => setEvalForm(f => ({ ...f, observacoes: e.target.value }))} placeholder="Detalhes adicionais sobre o imóvel..." rows={3} className="w-full px-3 py-2 rounded-lg text-sm focus:outline-none focus:ring-2 resize-none" style={{ backgroundColor: '#f5f3ee', border: '1px solid #e0dcd5', color: '#333' }} />
                </div>
                <Button onClick={handleEvalSubmit} disabled={evalSubmitting} className="w-full text-white py-2.5 rounded-xl font-medium" style={{ backgroundColor: primary }}>
                  {evalSubmitting ? 'Enviando...' : 'Enviar Solicitação'}
                </Button>
              </div>
            )}
          </div>
        </div>
      )}

      {/* ===== FOOTER ===== */}
      <footer className="py-8 px-4 lg:px-8" style={{ backgroundColor: '#1a1a1a', borderTop: '1px solid #e8e4de' }}>
        <div className="max-w-5xl mx-auto">
          <div className="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-6">
            <div>
              <div className="flex items-center gap-2 mb-3">
                {(tenant?.logo_url || tenant?.logo) ? (
                  <img src={tenant.logo_url || tenant.logo} alt={tenant?.name} className="h-7 w-auto object-contain brightness-200" />
                ) : null}
                <span className="font-semibold text-sm text-white">{tenant?.name || 'Imobiliária'}</span>
              </div>
              {tenant?.creci && <p className="text-xs text-gray-400 mb-1">CRECI: {tenant.creci}</p>}
              {tenant?.endereco && <p className="text-xs text-gray-400">{tenant.endereco}</p>}
            </div>
            <div>
              <h4 className="text-sm font-semibold text-white mb-3">Contato</h4>
              {tenant?.contact_phone && (
                <a href={`tel:${tenant.contact_phone}`} className="flex items-center gap-1.5 text-xs text-gray-400 hover:text-white mb-1.5">
                  <Phone className="w-3.5 h-3.5" />{tenant.contact_phone}
                </a>
              )}
              {tenant?.contact_email && (
                <a href={`mailto:${tenant.contact_email}`} className="flex items-center gap-1.5 text-xs text-gray-400 hover:text-white mb-1.5">
                  <Mail className="w-3.5 h-3.5" />{tenant.contact_email}
                </a>
              )}
              {tenant?.office_hours && <p className="text-xs text-gray-400 mt-2">{tenant.office_hours}</p>}
            </div>
            <div>
              <h4 className="text-sm font-semibold text-white mb-3">Links</h4>
              <button onClick={() => { setShowEvalModal(true); setEvalDone(false); }} className="block text-xs text-gray-400 hover:text-white mb-1.5">Avaliação de Imóvel</button>
              {tenant?.contact_phone && (
                <a href={`https://wa.me/${tenant.contact_phone.replace(/\D/g, '')}`} target="_blank" rel="noopener noreferrer" className="block text-xs text-gray-400 hover:text-white mb-1.5">WhatsApp</a>
              )}
              {tenant?.social_links?.instagram && (
                <a href={tenant.social_links.instagram} target="_blank" rel="noopener noreferrer" className="block text-xs text-gray-400 hover:text-white mb-1.5">Instagram</a>
              )}
              {tenant?.social_links?.facebook && (
                <a href={tenant.social_links.facebook} target="_blank" rel="noopener noreferrer" className="block text-xs text-gray-400 hover:text-white mb-1.5">Facebook</a>
              )}
            </div>
          </div>
          <div className="pt-4" style={{ borderTop: '1px solid #333' }}>
            <p className="text-xs text-gray-500 text-center">&copy; {new Date().getFullYear()} {tenant?.name || 'Imobiliária'}. Todos os direitos reservados.</p>
          </div>
        </div>
      </footer>

      {/* ===== CHAT WIDGET ===== */}
      <ChatWidget 
        tenantPhone={tenant?.contact_phone} 
        tenantName={tenant?.name} 
        primary={primary} 
        mascotUrl={tenant?.mascot_url}
        isOpen={chatOpen}
        onOpenChange={setChatOpen}
        propertyContext={chatPropertyContext}
      />
    </div>
  );
}
