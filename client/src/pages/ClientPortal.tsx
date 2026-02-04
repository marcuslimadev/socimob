import { useState, useEffect, useMemo, useCallback, useRef } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import {
  Search,
  MapPin,
  Bed,
  Bath,
  Ruler,
  Heart,
  Share2,
  Eye,
  Grid,
  List,
  Map as MapIcon,
  ChevronDown,
  X,
  SlidersHorizontal,
  Home,
  Car,
  Phone,
  Mail,
  Loader2,
  MessageCircle,
  Sun,
  Moon
} from 'lucide-react';
import { useLocation } from 'wouter';
import { toast } from 'sonner';
import api from '@/lib/api';
import { fetchTenantBranding, hexToRgba, TenantBranding } from '@/lib/tenantBranding';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import { cn } from '@/lib/utils';
import { useTheme } from '@/contexts/ThemeContext';

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
    // House icon
    iconSvg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 36 45" width="36" height="45">
      <path d="M18 0C8.06 0 0 8.06 0 18c0 13.5 18 27 18 27s18-13.5 18-27C36 8.06 27.94 0 18 0z" fill="${color}"/>
      <path d="M18 4C10.27 4 4 10.27 4 18c0 10.5 14 21 14 21s14-10.5 14-21C32 10.27 25.73 4 18 4z" fill="white" opacity="0.2"/>
      <path d="M18 10l-8 6v10h5v-6h6v6h5V16l-8-6z" fill="white"/>
    </svg>`;
  } else if (isApartment) {
    // Apartment/Building icon
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
    // Terrain/Land icon
    iconSvg = `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 36 45" width="36" height="45">
      <path d="M18 0C8.06 0 0 8.06 0 18c0 13.5 18 27 18 27s18-13.5 18-27C36 8.06 27.94 0 18 0z" fill="${color}"/>
      <path d="M18 4C10.27 4 4 10.27 4 18c0 10.5 14 21 14 21s14-10.5 14-21C32 10.27 25.73 4 18 4z" fill="white" opacity="0.2"/>
      <path d="M8 24h20L23 16l-5 5-3-3-7 6z" fill="white"/>
      <circle cx="12" cy="12" r="2" fill="white"/>
    </svg>`;
  } else if (isCommercial) {
    // Commercial icon
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
    // Default icon
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
}

type ViewMode = 'grid' | 'list' | 'map';
type SortOption = 'recent' | 'price_asc' | 'price_desc' | 'area_asc' | 'area_desc';

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

// Map bounds type
interface MapBounds {
  north: number;
  south: number;
  east: number;
  west: number;
}

// Map component that fits bounds and tracks visible area
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

  // Track bounds changes
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

    // Initial bounds
    updateBounds();

    // Listen for zoom and move events
    map.on('moveend', updateBounds);
    map.on('zoomend', updateBounds);

    return () => {
      map.off('moveend', updateBounds);
      map.off('zoomend', updateBounds);
    };
  }, [map, onBoundsChange]);

  // Initial fit to all properties
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

// Brazilian city coordinates for geocoding fallback
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

// Get coordinates from city name
function getCityCoords(city: string | undefined): { lat: number; lng: number } | null {
  if (!city) return null;
  const normalized = city.toLowerCase().trim();
  return BRAZIL_CITY_COORDS[normalized] || null;
}

export default function ClientPortal() {
  const [, navigate] = useLocation();
  const [viewMode, setViewMode] = useState<ViewMode>('grid');
  const [searchTerm, setSearchTerm] = useState('');
  const [priceRangeIndex, setPriceRangeIndex] = useState(0);
  const [selectedBedrooms, setSelectedBedrooms] = useState<number | null>(null);
  const [selectedType, setSelectedType] = useState<string | null>(null);
  const [selectedPropertyType, setSelectedPropertyType] = useState('');
  const [sortOption, setSortOption] = useState<SortOption>('recent');
  const [properties, setProperties] = useState<Property[]>([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [likedProperties, setLikedProperties] = useState<Set<number>>(new Set());
  const [tenant, setTenant] = useState<TenantConfig | null>(null);
  const [showFilters, setShowFilters] = useState(false);
  const [hoveredProperty, setHoveredProperty] = useState<number | null>(null);
  const [mapBounds, setMapBounds] = useState<MapBounds | null>(null);
  const headerRef = useRef<HTMLDivElement>(null);
  const { theme, toggleTheme } = useTheme();

  // Load tenant configuration
  useEffect(() => {
    const loadTenant = async () => {
      try {
        const data = await fetchTenantBranding();
        if (data) {
          setTenant(data as TenantConfig);
          
          // Update document title and favicon based on tenant branding
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
        const response = await api.get('/portal/imoveis');
        const data = response.data.data || response.data || [];

        // Add coordinates based on city when not available
        const propertiesWithCoords = data.map((prop: Property) => {
          if (prop.latitude && prop.longitude) {
            return prop;
          }

          // Try to get coordinates from city name
          const cityCoords = getCityCoords(prop.cidade);
          if (cityCoords) {
            // Add small random offset to avoid all markers in same spot
            return {
              ...prop,
              latitude: cityCoords.lat + (Math.random() - 0.5) * 0.02,
              longitude: cityCoords.lng + (Math.random() - 0.5) * 0.02,
            };
          }

          // No coordinates available - property won't show on map
          return prop;
        });

        setProperties(propertiesWithCoords);
      } catch (err: any) {
        setError(err.message || 'Erro ao carregar imóveis');
      }
    };
    fetchProperties();
  }, []);

  // Theme colors
  const primary = tenant?.primary_color || '#1e40af';
  const secondary = tenant?.secondary_color || '#3b82f6';

  const handleLike = async (propertyId: number, e: React.MouseEvent) => {
    e.stopPropagation();
    try {
      const response = await api.post(`/portal/likes/${propertyId}`);
      if (response.data.success) {
        setLikedProperties((prev) => new Set([...prev, propertyId]));
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
        await navigator.share({
          title: property.titulo,
          url: shareUrl,
        });
      } catch {
        // User cancelled
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
      
      // Se houver contexto do imóvel, criar mensagem personalizada
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

  // Filter and sort properties
  const filteredProperties = useMemo(() => {
    let result = properties.filter((prop) => {
      // Search filter
      const searchMatch =
        !searchTerm ||
        prop.titulo?.toLowerCase().includes(searchTerm.toLowerCase()) ||
        prop.bairro?.toLowerCase().includes(searchTerm.toLowerCase()) ||
        prop.cidade?.toLowerCase().includes(searchTerm.toLowerCase()) ||
        prop.tipo_imovel?.toLowerCase().includes(searchTerm.toLowerCase()) ||
        prop.logradouro?.toLowerCase().includes(searchTerm.toLowerCase());

      // Price filter
      const price = prop.valor_venda || prop.valor_aluguel || 0;
      const priceRange = PRICE_RANGES[priceRangeIndex];
      const priceMatch = price >= priceRange.min && price <= priceRange.max;

      // Bedrooms filter
      const bedrooms = prop.quartos ?? prop.dormitorios ?? 0;
      const bedroomsMatch = !selectedBedrooms || bedrooms >= selectedBedrooms;

      // Transaction type filter
      const typeMatch =
        !selectedType ||
        prop.tipo_negocio?.toLowerCase() === selectedType.toLowerCase() ||
        prop.finalidade_imovel?.toLowerCase().includes(selectedType.toLowerCase());

      // Property type filter
      const propTypeMatch =
        !selectedPropertyType ||
        prop.tipo_imovel?.toLowerCase().includes(selectedPropertyType.toLowerCase());

      return searchMatch && priceMatch && bedroomsMatch && typeMatch && propTypeMatch;
    });

    // Sort
    switch (sortOption) {
      case 'price_asc':
        result.sort((a, b) => (a.valor_venda || 0) - (b.valor_venda || 0));
        break;
      case 'price_desc':
        result.sort((a, b) => (b.valor_venda || 0) - (a.valor_venda || 0));
        break;
      case 'area_asc':
        result.sort((a, b) => (a.area_total || 0) - (b.area_total || 0));
        break;
      case 'area_desc':
        result.sort((a, b) => (b.area_total || 0) - (a.area_total || 0));
        break;
      default:
        break;
    }

    return result;
  }, [properties, searchTerm, priceRangeIndex, selectedBedrooms, selectedType, selectedPropertyType, sortOption]);

  // Filter properties by map bounds (only when in map view)
  const mapVisibleProperties = useMemo(() => {
    if (!mapBounds || viewMode !== 'map') return filteredProperties;

    return filteredProperties.filter((prop) => {
      if (!prop.latitude || !prop.longitude) return false;

      return (
        prop.latitude >= mapBounds.south &&
        prop.latitude <= mapBounds.north &&
        prop.longitude >= mapBounds.west &&
        prop.longitude <= mapBounds.east
      );
    });
  }, [filteredProperties, mapBounds, viewMode]);

  const hasActiveFilters =
    searchTerm || priceRangeIndex > 0 || selectedBedrooms || selectedType || selectedPropertyType;

  const clearFilters = useCallback(() => {
    setSearchTerm('');
    setPriceRangeIndex(0);
    setSelectedBedrooms(null);
    setSelectedType(null);
    setSelectedPropertyType('');
  }, []);

  const formatPrice = (value: number) => {
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL',
      maximumFractionDigits: 0,
    }).format(value);
  };

  const PropertyCard = ({ property, variant = 'grid' }: { property: Property; variant?: 'grid' | 'list' }) => {
    const firstImage = property.fotos?.[0]?.url || property.imagens?.[0];
    const price = property.valor_venda || property.valor_aluguel || 0;
    const bedrooms = property.quartos ?? property.dormitorios;
    const area = property.area_util || property.area_total;
    const location = [property.bairro, property.cidade].filter(Boolean).join(', ') || 'Localização não informada';
    const isLiked = likedProperties.has(property.id);
    const isHovered = hoveredProperty === property.id;

    if (variant === 'list') {
      return (
        <motion.div
          layout
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          exit={{ opacity: 0, y: -20 }}
          whileHover={{ scale: 1.01 }}
          onHoverStart={() => setHoveredProperty(property.id)}
          onHoverEnd={() => setHoveredProperty(null)}
          onClick={() => navigate(`/portal/imovel/${property.id}`)}
          className="flex flex-col sm:flex-row bg-card border border-border rounded-2xl overflow-hidden cursor-pointer hover:shadow-xl transition-all"
        >
          <div className="relative w-full sm:w-72 h-48 sm:h-auto flex-shrink-0">
            {firstImage ? (
              <img
                src={firstImage}
                alt={property.titulo}
                className="w-full h-full object-cover"
                loading="lazy"
              />
            ) : (
              <div className="w-full h-full bg-muted flex items-center justify-center">
                <Home className="w-12 h-12 text-muted-foreground" />
              </div>
            )}
            {property.destaque && (
              <span className="absolute top-3 left-3 px-2 py-1 bg-amber-500 text-white text-xs font-semibold rounded-full">
                Destaque
              </span>
            )}
            <div className="absolute top-3 right-3 flex gap-2">
              <button
                onClick={(e) => handleLike(property.id, e)}
                className={cn(
                  'p-2 rounded-full backdrop-blur-sm transition-all',
                  isLiked ? 'bg-red-500 text-white' : 'bg-black/30 text-white hover:bg-black/50'
                )}
              >
                <Heart className={cn('w-4 h-4', isLiked && 'fill-current')} />
              </button>
            </div>
          </div>
          <div className="flex-1 p-5">
            <div className="flex items-start justify-between gap-4 mb-3">
              <div>
                <span
                  className="inline-block px-2 py-0.5 text-xs font-medium rounded mb-2"
                  style={{ backgroundColor: `${primary}20`, color: primary }}
                >
                  {property.tipo_negocio || property.finalidade_imovel}
                </span>
                <h3 className="text-lg font-semibold text-foreground line-clamp-1">{property.titulo}</h3>
                <p className="text-sm text-muted-foreground flex items-center gap-1 mt-1">
                  <MapPin className="w-3.5 h-3.5" />
                  {location}
                </p>
              </div>
              <div className="text-right">
                <p className="text-2xl font-bold" style={{ color: primary }}>
                  {formatPrice(price)}
                </p>
                {property.tipo_negocio?.toLowerCase() === 'aluguel' && (
                  <span className="text-xs text-muted-foreground">/mês</span>
                )}
              </div>
            </div>
            <div className="flex flex-wrap gap-4 text-sm text-muted-foreground mb-4">
              {bedrooms != null && (
                <span className="flex items-center gap-1">
                  <Bed className="w-4 h-4" /> {bedrooms} quartos
                </span>
              )}
              {property.banheiros != null && (
                <span className="flex items-center gap-1">
                  <Bath className="w-4 h-4" /> {property.banheiros} banheiros
                </span>
              )}
              {property.garagem != null && (
                <span className="flex items-center gap-1">
                  <Car className="w-4 h-4" /> {property.garagem} vagas
                </span>
              )}
              {area != null && (
                <span className="flex items-center gap-1">
                  <Ruler className="w-4 h-4" /> {area}m²
                </span>
              )}
            </div>
            <div className="flex items-center gap-2">
              <Button
                size="sm"
                style={{ backgroundColor: primary }}
                className="text-white"
                onClick={(e) => {
                  e.stopPropagation();
                  navigate(`/portal/imovel/${property.id}`);
                }}
              >
                <Eye className="w-4 h-4" />
                Ver detalhes
              </Button>
              <Button size="sm" variant="outline" onClick={(e) => handleShare(property, e)}>
                <Share2 className="w-4 h-4" />
              </Button>
              {tenant?.contact_phone && (
                <Button size="sm" variant="outline" className="text-green-600" onClick={(e) => handleWhatsApp(e, property)}>
                  <MessageCircle className="w-4 h-4" />
                </Button>
              )}
            </div>
          </div>
        </motion.div>
      );
    }

    return (
      <motion.div
        layout
        whileHover={{ y: -4 }}
        onHoverStart={() => setHoveredProperty(property.id)}
        onHoverEnd={() => setHoveredProperty(null)}
        onClick={() => navigate(`/portal/imovel/${property.id}`)}
        className={cn(
          'group bg-card border rounded-2xl overflow-hidden cursor-pointer transition-all',
          isHovered ? 'shadow-xl border-primary/30' : 'border-border hover:shadow-lg'
        )}
      >
        <div className="relative h-52 overflow-hidden">
          {firstImage ? (
            <img
              src={firstImage}
              alt={property.titulo}
              className="w-full h-full object-cover transition-transform duration-500 group-hover:scale-110"
              loading="lazy"
            />
          ) : (
            <div className="w-full h-full bg-muted flex items-center justify-center">
              <Home className="w-16 h-16 text-muted-foreground" />
            </div>
          )}
          <div className="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent" />

          {property.destaque && (
            <span className="absolute top-3 left-3 px-2.5 py-1 bg-amber-500 text-white text-xs font-semibold rounded-full shadow-lg">
              ⭐ Destaque
            </span>
          )}

          <div className="absolute top-3 right-3 flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
            <motion.button
              whileHover={{ scale: 1.1 }}
              whileTap={{ scale: 0.9 }}
              onClick={(e) => handleLike(property.id, e)}
              className={cn(
                'p-2 rounded-full backdrop-blur-sm transition-all',
                isLiked ? 'bg-red-500 text-white' : 'bg-white/90 text-gray-700 hover:bg-white'
              )}
            >
              <Heart className={cn('w-4 h-4', isLiked && 'fill-current')} />
            </motion.button>
            <motion.button
              whileHover={{ scale: 1.1 }}
              whileTap={{ scale: 0.9 }}
              onClick={(e) => handleShare(property, e)}
              className="p-2 rounded-full bg-white/90 text-gray-700 backdrop-blur-sm hover:bg-white transition-all"
            >
              <Share2 className="w-4 h-4" />
            </motion.button>
            {tenant?.contact_phone && (
              <motion.button
                whileHover={{ scale: 1.1 }}
                whileTap={{ scale: 0.9 }}
                onClick={(e) => handleWhatsApp(e, property)}
                className="p-2 rounded-full bg-green-500 text-white backdrop-blur-sm hover:bg-green-600 transition-all"
              >
                <MessageCircle className="w-4 h-4" />
              </motion.button>
            )}
          </div>

          <div className="absolute bottom-3 left-3 right-3">
            <p className="text-white text-2xl font-bold drop-shadow-lg">{formatPrice(price)}</p>
            {property.tipo_negocio?.toLowerCase() === 'aluguel' && (
              <span className="text-white/80 text-sm">/mês</span>
            )}
          </div>
        </div>

        <div className="p-4">
          <div className="flex items-center gap-2 mb-2">
            <span
              className="px-2 py-0.5 text-xs font-medium rounded"
              style={{ backgroundColor: `${primary}15`, color: primary }}
            >
              {property.tipo_imovel}
            </span>
            <span className="px-2 py-0.5 text-xs font-medium bg-muted rounded text-muted-foreground">
              {property.tipo_negocio || property.finalidade_imovel}
            </span>
          </div>

          <h3 className="font-semibold text-foreground line-clamp-1 mb-1">{property.titulo}</h3>
          <p className="text-sm text-muted-foreground flex items-center gap-1 mb-4">
            <MapPin className="w-3.5 h-3.5 flex-shrink-0" />
            <span className="truncate">{location}</span>
          </p>

          <div className="flex items-center gap-3 text-sm text-muted-foreground border-t border-border pt-3">
            {bedrooms != null && (
              <span className="flex items-center gap-1">
                <Bed className="w-4 h-4" /> {bedrooms}
              </span>
            )}
            {property.banheiros != null && (
              <span className="flex items-center gap-1">
                <Bath className="w-4 h-4" /> {property.banheiros}
              </span>
            )}
            {property.garagem != null && (
              <span className="flex items-center gap-1">
                <Car className="w-4 h-4" /> {property.garagem}
              </span>
            )}
            {area != null && (
              <span className="flex items-center gap-1 ml-auto">
                <Ruler className="w-4 h-4" /> {area}m²
              </span>
            )}
          </div>
        </div>
      </motion.div>
    );
  };

  return (
    <div className="min-h-screen bg-background">
      {/* Header */}
      <header
        ref={headerRef}
        className="sticky top-0 z-50 bg-card/95 backdrop-blur-md border-b border-border"
      >
        <div className="max-w-7xl mx-auto px-4 py-3">
          <div className="flex items-center justify-between gap-4">
            {/* Logo */}
            <div className="flex items-center gap-3">
              {tenant?.logo_url || tenant?.logo ? (
                <img
                  src={tenant.logo_url || tenant.logo}
                  alt={tenant?.name || 'Logo'}
                  className="h-10 w-auto object-contain"
                />
              ) : (
                <div
                  className="w-10 h-10 rounded-lg flex items-center justify-center text-white font-bold"
                  style={{ backgroundColor: primary }}
                >
                  {tenant?.name?.substring(0, 2).toUpperCase() || 'IM'}
                </div>
              )}
              <div className="hidden sm:block">
                <h1 className="font-bold text-foreground">{tenant?.name || 'Imobiliária'}</h1>
                {tenant?.slogan && (
                  <p className="text-xs text-muted-foreground">{tenant.slogan}</p>
                )}
              </div>
            </div>

              {/* Contact & Login */}
              <div className="flex items-center gap-2">
                <Button
                  variant="ghost"
                  size="sm"
                  className="text-muted-foreground"
                  onClick={toggleTheme}
                  aria-label="Alternar tema"
                >
                  {theme === 'dark' ? <Moon className="w-4 h-4" /> : <Sun className="w-4 h-4" />}
                </Button>
                {tenant?.contact_phone && (
                  <Button
                    variant="ghost"
                    size="sm"
                    className="hidden md:flex text-green-600"
                    onClick={(e) => handleWhatsApp(e)}
                >
                  <MessageCircle className="w-4 h-4" />
                  WhatsApp
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
            </div>
          </div>
        </div>
      </header>

      {/* Hero Section */}
      <section
        className="relative py-16 md:py-24"
        style={{
          background: `linear-gradient(135deg, ${hexToRgba(primary, 0.08)} 0%, ${hexToRgba(secondary, 0.08)} 100%)`,
        }}
      >
        <div className="max-w-7xl mx-auto px-4">
          <div className="text-center mb-8">
            <h2 className="text-3xl md:text-5xl font-bold text-foreground mb-4">
              Encontre o imóvel dos seus sonhos
            </h2>
            <p className="text-lg text-muted-foreground max-w-2xl mx-auto">
              {tenant?.slogan || 'Descubra as melhores oportunidades em imóveis'}
            </p>
          </div>

          {/* Search Bar */}
          <div className="max-w-4xl mx-auto"
          >
            <div className="bg-card rounded-2xl shadow-xl border border-border p-4 md:p-6">
              <div className="flex flex-col md:flex-row gap-3">
                <div className="flex-1 relative">
                  <Search className="absolute left-4 top-1/2 -translate-y-1/2 text-muted-foreground w-5 h-5" />
                  <input
                    type="text"
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    placeholder="Busque por bairro, cidade ou tipo de imóvel..."
                    className="w-full pl-12 pr-4 py-3 bg-muted/50 border-0 rounded-xl text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary/50"
                  />
                </div>
                <div className="flex gap-2">
                  <Button
                    variant="outline"
                    onClick={() => setShowFilters(!showFilters)}
                    className="flex-1 md:flex-none"
                  >
                    <SlidersHorizontal className="w-4 h-4" />
                    Filtros
                    {hasActiveFilters && (
                      <span
                        className="ml-1 w-5 h-5 rounded-full text-xs flex items-center justify-center text-white"
                        style={{ backgroundColor: primary }}
                      >
                        !
                      </span>
                    )}
                  </Button>
                  <Button
                    style={{ backgroundColor: primary }}
                    className="text-white flex-1 md:flex-none"
                  >
                    <Search className="w-4 h-4" />
                    Buscar
                  </Button>
                </div>
              </div>

              {/* Quick Filters */}
              <AnimatePresence>
                {showFilters && (
                  <motion.div
                    initial={{ height: 0, opacity: 0 }}
                    animate={{ height: 'auto', opacity: 1 }}
                    exit={{ height: 0, opacity: 0 }}
                    className="overflow-hidden"
                  >
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4 pt-4 mt-4 border-t border-border">
                      {/* Transaction Type */}
                      <div>
                        <label className="block text-sm font-medium text-foreground mb-2">
                          Finalidade
                        </label>
                        <div className="flex gap-2">
                          <Button
                            size="sm"
                            variant={selectedType === 'venda' ? 'default' : 'outline'}
                            onClick={() => setSelectedType(selectedType === 'venda' ? null : 'venda')}
                            style={selectedType === 'venda' ? { backgroundColor: primary } : {}}
                            className={selectedType === 'venda' ? 'text-white' : ''}
                          >
                            Comprar
                          </Button>
                          <Button
                            size="sm"
                            variant={selectedType === 'aluguel' ? 'default' : 'outline'}
                            onClick={() => setSelectedType(selectedType === 'aluguel' ? null : 'aluguel')}
                            style={selectedType === 'aluguel' ? { backgroundColor: primary } : {}}
                            className={selectedType === 'aluguel' ? 'text-white' : ''}
                          >
                            Alugar
                          </Button>
                        </div>
                      </div>

                      {/* Property Type */}
                      <div>
                        <label className="block text-sm font-medium text-foreground mb-2">
                          Tipo de imóvel
                        </label>
                        <select
                          value={selectedPropertyType}
                          onChange={(e) => setSelectedPropertyType(e.target.value)}
                          className="w-full px-3 py-2 bg-muted/50 border border-border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/50"
                        >
                          {PROPERTY_TYPES.map((type) => (
                            <option key={type.value} value={type.value}>
                              {type.label}
                            </option>
                          ))}
                        </select>
                      </div>

                      {/* Price Range */}
                      <div>
                        <label className="block text-sm font-medium text-foreground mb-2">
                          Faixa de preço
                        </label>
                        <select
                          value={priceRangeIndex}
                          onChange={(e) => setPriceRangeIndex(Number(e.target.value))}
                          className="w-full px-3 py-2 bg-muted/50 border border-border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/50"
                        >
                          {PRICE_RANGES.map((range, idx) => (
                            <option key={idx} value={idx}>
                              {range.label}
                            </option>
                          ))}
                        </select>
                      </div>

                      {/* Bedrooms */}
                      <div>
                        <label className="block text-sm font-medium text-foreground mb-2">
                          Quartos
                        </label>
                        <div className="flex gap-1">
                          {[1, 2, 3, 4].map((num) => (
                            <Button
                              key={num}
                              size="sm"
                              variant={selectedBedrooms === num ? 'default' : 'outline'}
                              onClick={() => setSelectedBedrooms(selectedBedrooms === num ? null : num)}
                              style={selectedBedrooms === num ? { backgroundColor: primary } : {}}
                              className={cn('flex-1', selectedBedrooms === num && 'text-white')}
                            >
                              {num}+
                            </Button>
                          ))}
                        </div>
                      </div>
                    </div>

                    {hasActiveFilters && (
                      <div className="flex justify-end pt-3">
                        <Button variant="ghost" size="sm" onClick={clearFilters}>
                          <X className="w-4 h-4" />
                          Limpar filtros
                        </Button>
                      </div>
                    )}
                  </motion.div>
                )}
              </AnimatePresence>
            </div>
          </div>
        </div>
      </section>

      {/* Results Section */}
      <section className="py-8 md:py-12">
        <div className="max-w-7xl mx-auto px-4">
          {/* Toolbar */}
          <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
              <p className="text-muted-foreground">
                <span className="font-semibold text-foreground">
                  {viewMode === 'map' ? mapVisibleProperties.length : filteredProperties.length}
                </span> imóveis {viewMode === 'map' ? 'visíveis' : 'encontrados'}
                {viewMode === 'map' && mapVisibleProperties.length < filteredProperties.length && (
                  <span className="text-xs ml-1">(de {filteredProperties.length})</span>
                )}
              </p>
            </div>

            <div className="flex items-center gap-2">
              {/* Sort */}
              <div className="relative">
                <select
                  value={sortOption}
                  onChange={(e) => setSortOption(e.target.value as SortOption)}
                  className="appearance-none pl-3 pr-8 py-2 bg-card border border-border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary/50"
                >
                  <option value="recent">Mais recentes</option>
                  <option value="price_asc">Menor preço</option>
                  <option value="price_desc">Maior preço</option>
                  <option value="area_desc">Maior área</option>
                </select>
                <ChevronDown className="absolute right-2 top-1/2 -translate-y-1/2 w-4 h-4 text-muted-foreground pointer-events-none" />
              </div>

              {/* View Mode */}
              <div className="flex border border-border rounded-lg overflow-hidden">
                <Button
                  size="icon-sm"
                  variant={viewMode === 'grid' ? 'default' : 'ghost'}
                  onClick={() => setViewMode('grid')}
                  style={viewMode === 'grid' ? { backgroundColor: primary } : {}}
                  className={viewMode === 'grid' ? 'text-white rounded-none' : 'rounded-none'}
                >
                  <Grid className="w-4 h-4" />
                </Button>
                <Button
                  size="icon-sm"
                  variant={viewMode === 'list' ? 'default' : 'ghost'}
                  onClick={() => setViewMode('list')}
                  style={viewMode === 'list' ? { backgroundColor: primary } : {}}
                  className={viewMode === 'list' ? 'text-white rounded-none' : 'rounded-none'}
                >
                  <List className="w-4 h-4" />
                </Button>
                <Button
                  size="icon-sm"
                  variant={viewMode === 'map' ? 'default' : 'ghost'}
                  onClick={() => setViewMode('map')}
                  style={viewMode === 'map' ? { backgroundColor: primary } : {}}
                  className={viewMode === 'map' ? 'text-white rounded-none' : 'rounded-none'}
                >
                  <MapIcon className="w-4 h-4" />
                </Button>
              </div>
            </div>
          </div>

          {/* Error */}
          {error && (
            <div className="bg-destructive/10 border border-destructive/20 rounded-xl p-8 text-center">
              <p className="text-destructive mb-4">{error}</p>
              <Button onClick={() => window.location.reload()}>Tentar novamente</Button>
            </div>
          )}

          {/* Content */}
          {!error && (
            <>
              {viewMode === 'map' ? (
                <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                  {/* Map */}
                  <div className="h-[600px] rounded-2xl overflow-hidden border border-border">
                    <MapContainer
                      center={(() => {
                        // Find first property with coordinates
                        const propWithCoords = filteredProperties.find(p => p.latitude && p.longitude);
                        if (propWithCoords) {
                          return [propWithCoords.latitude!, propWithCoords.longitude!] as [number, number];
                        }
                        // Default to Brazil center
                        return [-15.7801, -47.9292] as [number, number];
                      })()}
                      zoom={filteredProperties.length > 0 ? 12 : 4}
                      className="h-full w-full"
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
                              <div className="w-64">
                                {property.fotos?.[0]?.url && (
                                  <img
                                    src={property.fotos[0].url}
                                    alt={property.titulo}
                                    className="w-full h-32 object-cover rounded-t-lg -mt-3 -mx-3 mb-2"
                                    style={{ width: 'calc(100% + 24px)' }}
                                  />
                                )}
                                <div className="flex items-center gap-1 mb-1">
                                  <span className="text-xs px-2 py-0.5 rounded" style={{ backgroundColor: `${primary}20`, color: primary }}>
                                    {property.tipo_imovel}
                                  </span>
                                </div>
                                <h4 className="font-semibold text-sm line-clamp-1">{property.titulo}</h4>
                                <p className="text-xs text-muted-foreground mb-2">
                                  {property.bairro}, {property.cidade}
                                </p>
                                <p className="font-bold text-lg" style={{ color: primary }}>
                                  {formatPrice(price)}
                                </p>
                                <Button
                                  size="sm"
                                  className="w-full mt-2 text-white"
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
                  </div>

                  {/* Property List - Filtered by visible map area */}
                  <div className="flex flex-col h-[600px]">
                    <div className="flex-shrink-0 pb-3 border-b border-border mb-3">
                      <p className="text-sm text-muted-foreground">
                        <span className="font-semibold text-foreground">{mapVisibleProperties.length}</span> imóveis nesta área
                        {mapVisibleProperties.length < filteredProperties.length && (
                          <span className="text-xs ml-2">(de {filteredProperties.length} no total)</span>
                        )}
                      </p>
                      <p className="text-xs text-muted-foreground mt-1">
                        Mova ou aplique zoom no mapa para filtrar
                      </p>
                    </div>
                    <ScrollArea className="flex-1">
                      <div className="space-y-4 pr-4">
                        {mapVisibleProperties.length === 0 ? (
                          <div className="flex flex-col items-center justify-center py-12 text-center">
                            <MapIcon className="w-12 h-12 text-muted-foreground mb-3" />
                            <p className="text-muted-foreground">Nenhum imóvel nesta área</p>
                            <p className="text-xs text-muted-foreground mt-1">Tente ajustar o zoom ou mover o mapa</p>
                          </div>
                        ) : (
                          mapVisibleProperties.map((property) => (
                            <PropertyCard key={property.id} property={property} variant="list" />
                          ))
                        )}
                      </div>
                    </ScrollArea>
                  </div>
                </div>
              ) : (
                <motion.div
                  layout
                  className={cn(
                    viewMode === 'grid'
                      ? 'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6'
                      : 'space-y-4'
                  )}
                >
                  <AnimatePresence mode="popLayout">
                    {filteredProperties.length === 0 ? (
                      <motion.div
                        initial={{ opacity: 0 }}
                        animate={{ opacity: 1 }}
                        className="col-span-full bg-muted/30 rounded-2xl p-12 text-center"
                      >
                        <Home className="w-16 h-16 mx-auto mb-4 text-muted-foreground" />
                        <h3 className="text-xl font-semibold text-foreground mb-2">
                          Nenhum imóvel encontrado
                        </h3>
                        <p className="text-muted-foreground mb-4">
                          Tente ajustar os filtros para encontrar mais opções
                        </p>
                        {hasActiveFilters && (
                          <Button variant="outline" onClick={clearFilters}>
                            Limpar filtros
                          </Button>
                        )}
                      </motion.div>
                    ) : (
                      filteredProperties.map((property) => (
                        <PropertyCard key={property.id} property={property} variant={viewMode} />
                      ))
                    )}
                  </AnimatePresence>
                </motion.div>
              )}
            </>
          )}
        </div>
      </section>

      {/* Footer */}
      <footer className="bg-card border-t border-border py-8">
        <div className="max-w-7xl mx-auto px-4">
          <div className="flex flex-col md:flex-row items-center justify-between gap-4">
            <div className="flex items-center gap-3">
              {tenant?.logo_url || tenant?.logo ? (
                <img
                  src={tenant.logo_url || tenant.logo}
                  alt={tenant?.name}
                  className="h-8 w-auto object-contain"
                />
              ) : (
                <div
                  className="w-8 h-8 rounded-lg flex items-center justify-center text-white font-bold text-sm"
                  style={{ backgroundColor: primary }}
                >
                  {tenant?.name?.substring(0, 2).toUpperCase() || 'IM'}
                </div>
              )}
              <span className="font-semibold text-foreground">{tenant?.name || 'Imobiliária'}</span>
            </div>

            <div className="flex items-center gap-4">
              {tenant?.contact_phone && (
                <a
                  href={`tel:${tenant.contact_phone}`}
                  className="flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground transition-colors"
                >
                  <Phone className="w-4 h-4" />
                  {tenant.contact_phone}
                </a>
              )}
              {tenant?.contact_email && (
                <a
                  href={`mailto:${tenant.contact_email}`}
                  className="flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground transition-colors"
                >
                  <Mail className="w-4 h-4" />
                  {tenant.contact_email}
                </a>
              )}
            </div>

            <p className="text-sm text-muted-foreground">
              © {new Date().getFullYear()} {tenant?.name || 'Imobiliária'}. Todos os direitos reservados.
            </p>
          </div>
        </div>
      </footer>

      {/* WhatsApp FAB */}
      {tenant?.contact_phone && (
        <motion.button
          initial={{ scale: 0 }}
          animate={{ scale: 1 }}
          whileHover={{ scale: 1.1 }}
          whileTap={{ scale: 0.9 }}
          onClick={(e) => handleWhatsApp(e)}
          className="fixed bottom-6 right-6 w-14 h-14 bg-green-500 hover:bg-green-600 text-white rounded-full shadow-lg flex items-center justify-center z-50"
        >
          <MessageCircle className="w-6 h-6" />
        </motion.button>
      )}
    </div>
  );
}
