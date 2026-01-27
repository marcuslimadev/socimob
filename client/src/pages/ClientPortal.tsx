import { useState, useEffect } from 'react';
import { motion } from 'framer-motion';
import { Search, MapPin, Bed, Bath, Ruler, Heart, Share2, Eye, Filter, Grid, List, AlertCircle } from 'lucide-react';
import { useLocation } from 'wouter';
import { toast } from 'sonner';
import api from '@/lib/api';

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
  area_total?: number;
  area_util?: number;
  area_privativa?: number;
  descricao?: string;
  destaque?: boolean;
  active?: boolean;
  fotos?: Array<{url: string; destaque: boolean}>;
  imagens?: string[];
  finalidade_imovel?: string;
}

export default function ClientPortal() {
  const [, navigate] = useLocation();
  const [viewMode, setViewMode] = useState<'grid' | 'list'>('grid');
  const [searchTerm, setSearchTerm] = useState('');
  const [priceRange, setPriceRange] = useState([0, 2000000]);
  const [selectedBedrooms, setSelectedBedrooms] = useState<number | null>(null);
  const [selectedType, setSelectedType] = useState<string | null>(null); // Venda/Aluguel
  const [properties, setProperties] = useState<Property[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [likedProperties, setLikedProperties] = useState<Set<number>>(new Set());

  // Carregar imóveis da API
  useEffect(() => {
    const fetchProperties = async () => {
      try {
        setLoading(true);
        setError(null);
        const response = await api.get('/portal/imoveis');
        console.log('API Response:', response.data);
        setProperties(response.data.data || response.data || []);
      } catch (err: any) {
        console.error('Erro ao carregar imóveis:', err);
        setError(err.message || 'Erro ao carregar imóveis');
      } finally {
        setLoading(false);
      }
    };

    fetchProperties();
  }, []);

  const handleLike = async (propertyId: number, e: React.MouseEvent) => {
    e.stopPropagation();

    try {
      const response = await api.post(`/portal/likes/${propertyId}`);

      if (response.data.success) {
        setLikedProperties(prev => new Set([...prev, propertyId]));
        toast.success('Imóvel adicionado aos favoritos!');
      }
    } catch (err: any) {
      console.error('Erro ao curtir imóvel:', err);

      if (err.response?.status === 401) {
        toast.error('Faça login para favoritar imóveis');
      } else {
        toast.error('Erro ao adicionar aos favoritos');
      }
    }
  };

  const handleShare = async (property: Property, e: React.MouseEvent) => {
    e.stopPropagation();

    const shareUrl = `${window.location.origin}/portal/imovel/${property.id}`;
    const shareText = `Confira este imóvel: ${property.titulo}`;

    if (navigator.share) {
      try {
        await navigator.share({
          title: property.titulo,
          text: shareText,
          url: shareUrl,
        });
        toast.success('Compartilhado com sucesso!');
      } catch (err) {
        // User cancelled or error occurred
      }
    } else {
      // Fallback: copy to clipboard
      try {
        await navigator.clipboard.writeText(shareUrl);
        toast.success('Link copiado para a área de transferência!');
      } catch (err) {
        toast.error('Erro ao copiar link');
      }
    }
  };

  const filteredProperties = properties.filter((prop) => {
    // Filtro de busca textual
    const matchSearch = searchTerm === '' ||
                       prop.titulo?.toLowerCase().includes(searchTerm.toLowerCase()) ||
                       prop.bairro?.toLowerCase().includes(searchTerm.toLowerCase()) ||
                       prop.cidade?.toLowerCase().includes(searchTerm.toLowerCase()) ||
                       prop.tipo_imovel?.toLowerCase().includes(searchTerm.toLowerCase());

    // Filtro de preço
    const price = prop.valor_venda || prop.valor_aluguel ||
                  (prop.preco ? parseFloat(prop.preco.replace(/[^\d]/g, '')) : 0);
    const matchPrice = price >= priceRange[0] && price <= priceRange[1];

    // Filtro de quartos (considera tanto quartos quanto dormitorios)
    const bedrooms = prop.quartos ?? prop.dormitorios ?? 0;
    const matchBedrooms = selectedBedrooms === null || bedrooms >= selectedBedrooms;

    // Filtro de tipo de negócio (Venda/Aluguel)
    const matchType = selectedType === null ||
                     prop.tipo_negocio?.toLowerCase() === selectedType.toLowerCase() ||
                     prop.finalidade_imovel?.toLowerCase() === selectedType.toLowerCase();

    return matchSearch && matchPrice && matchBedrooms && matchType;
  });

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: { staggerChildren: 0.05, delayChildren: 0.1 },
    },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: { opacity: 1, y: 0 },
  };

  return (
    <div className="min-h-screen bg-gradient-to-b from-background to-background/80">
      {/* Header */}
      <motion.div
        initial={{ opacity: 0, y: -20 }}
        animate={{ opacity: 1, y: 0 }}
        className="sticky top-0 z-40 glass-panel border-b"
      >
        <div className="max-w-7xl mx-auto px-4 md:px-8 py-4 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-lg bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-bold">
              S
            </div>
            <h1 className="text-2xl font-bold gradient-text">SOCIMOB</h1>
          </div>
          <motion.button
            whileHover={{ scale: 1.05 }}
            whileTap={{ scale: 0.95 }}
            onClick={() => navigate('/login')}
            className="px-6 py-2 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 rounded-lg font-semibold text-white transition-all glow-md hover:glow-lg"
          >
            Entrar
          </motion.button>
        </div>
      </motion.div>

      {/* Hero Section */}
      <motion.div
        initial={{ opacity: 0 }}
        animate={{ opacity: 1 }}
        transition={{ delay: 0.1 }}
        className="bg-gradient-to-br from-blue-500/20 to-purple-500/20 py-12 md:py-20"
      >
        <div className="max-w-7xl mx-auto px-4 md:px-8">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.2 }}
            className="text-center mb-8"
          >
            <h2 className="text-4xl md:text-5xl font-bold gradient-text mb-4">
              Encontre seu Imóvel Perfeito
            </h2>
            <p className="text-xl text-muted-foreground max-w-2xl mx-auto">
              Explore nossa seleção de propriedades premium em São Paulo
            </p>
          </motion.div>

          {/* Search Bar */}
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.3 }}
            className="glass-panel p-6 rounded-2xl"
          >
            <div className="relative">
              <Search className="absolute left-4 top-1/2 -translate-y-1/2 text-muted-foreground" size={24} />
              <input
                type="text"
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                placeholder="Buscar por localização, tipo de imóvel..."
                className="w-full pl-14 pr-4 py-4 bg-white/10 border border-white/20 rounded-lg text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all text-lg"
              />
            </div>
          </motion.div>
        </div>
      </motion.div>

      {/* Filters & Results */}
      <div className="max-w-7xl mx-auto px-4 md:px-8 py-12">
        <motion.div
          variants={containerVariants}
          initial="hidden"
          animate="visible"
          className="grid grid-cols-1 lg:grid-cols-4 gap-8"
        >
          {/* Sidebar Filters */}
          <motion.div variants={itemVariants} className="lg:col-span-1">
            <div className="glass-panel p-6 rounded-2xl sticky top-24">
              <div className="flex items-center justify-between mb-6">
                <h3 className="text-lg font-bold text-foreground flex items-center gap-2">
                  <Filter size={20} />
                  Filtros
                </h3>
                {(searchTerm || selectedType || selectedBedrooms !== null || priceRange[0] > 0 || priceRange[1] < 2000000) && (
                  <div className="px-2 py-1 bg-blue-500 text-white text-xs rounded-full font-semibold">
                    Ativos
                  </div>
                )}
              </div>

              {/* Tipo de Negócio */}
              <div className="mb-6">
                <label className="block text-sm font-semibold text-foreground mb-3">
                  Tipo
                </label>
                <div className="grid grid-cols-2 gap-2">
                  <motion.button
                    whileHover={{ scale: 1.05 }}
                    whileTap={{ scale: 0.95 }}
                    onClick={() => setSelectedType(selectedType === 'venda' ? null : 'venda')}
                    className={`px-4 py-2 rounded-lg font-semibold transition-all ${
                      selectedType === 'venda'
                        ? 'bg-gradient-to-r from-blue-500 to-blue-600 text-white'
                        : 'bg-white/10 text-foreground hover:bg-white/20'
                    }`}
                  >
                    Venda
                  </motion.button>
                  <motion.button
                    whileHover={{ scale: 1.05 }}
                    whileTap={{ scale: 0.95 }}
                    onClick={() => setSelectedType(selectedType === 'aluguel' ? null : 'aluguel')}
                    className={`px-4 py-2 rounded-lg font-semibold transition-all ${
                      selectedType === 'aluguel'
                        ? 'bg-gradient-to-r from-blue-500 to-blue-600 text-white'
                        : 'bg-white/10 text-foreground hover:bg-white/20'
                    }`}
                  >
                    Aluguel
                  </motion.button>
                </div>
              </div>

              {/* Price Range */}
              <div className="mb-6">
                <label className="block text-sm font-semibold text-foreground mb-3">
                  Faixa de Preço
                </label>
                <div className="space-y-3">
                  <div>
                    <label className="block text-xs text-muted-foreground mb-1">Mínimo</label>
                    <input
                      type="range"
                      min="0"
                      max="2000000"
                      step="50000"
                      value={priceRange[0]}
                      onChange={(e) => {
                        const newMin = parseInt(e.target.value);
                        if (newMin < priceRange[1]) {
                          setPriceRange([newMin, priceRange[1]]);
                        }
                      }}
                      className="w-full"
                    />
                    <p className="text-xs text-muted-foreground mt-1">
                      R$ {priceRange[0].toLocaleString('pt-BR')}
                    </p>
                  </div>
                  <div>
                    <label className="block text-xs text-muted-foreground mb-1">Máximo</label>
                    <input
                      type="range"
                      min="0"
                      max="2000000"
                      step="50000"
                      value={priceRange[1]}
                      onChange={(e) => {
                        const newMax = parseInt(e.target.value);
                        if (newMax > priceRange[0]) {
                          setPriceRange([priceRange[0], newMax]);
                        }
                      }}
                      className="w-full"
                    />
                    <p className="text-xs text-muted-foreground mt-1">
                      R$ {priceRange[1].toLocaleString('pt-BR')}
                    </p>
                  </div>
                </div>
              </div>

              {/* Bedrooms */}
              <div className="mb-6">
                <label className="block text-sm font-semibold text-foreground mb-3">
                  Quartos
                </label>
                <div className="space-y-2">
                  {[1, 2, 3, 4].map((num) => (
                    <motion.button
                      key={num}
                      whileHover={{ scale: 1.05 }}
                      whileTap={{ scale: 0.95 }}
                      onClick={() => setSelectedBedrooms(selectedBedrooms === num ? null : num)}
                      className={`w-full px-4 py-2 rounded-lg font-semibold transition-all ${
                        selectedBedrooms === num
                          ? 'bg-gradient-to-r from-blue-500 to-blue-600 text-white'
                          : 'bg-white/10 text-foreground hover:bg-white/20'
                      }`}
                    >
                      {num}+
                    </motion.button>
                  ))}
                </div>
              </div>

              {/* Clear Filters */}
              <motion.button
                whileHover={{ scale: 1.05 }}
                whileTap={{ scale: 0.95 }}
                onClick={() => {
                  setSearchTerm('');
                  setPriceRange([0, 2000000]);
                  setSelectedBedrooms(null);
                  setSelectedType(null);
                }}
                className="w-full px-4 py-2 bg-white/10 hover:bg-white/20 rounded-lg font-semibold text-foreground transition-all"
              >
                Limpar Filtros
              </motion.button>
            </div>
          </motion.div>

          {/* Properties Grid */}
          <motion.div variants={itemVariants} className="lg:col-span-3">
            {/* View Mode & Count */}
            <div className="flex items-center justify-between mb-6">
              <p className="text-muted-foreground">
                {filteredProperties.length} imóvel{filteredProperties.length !== 1 ? 's' : ''} encontrado{filteredProperties.length !== 1 ? 's' : ''}
              </p>
              <div className="flex gap-2">
                <motion.button
                  whileHover={{ scale: 1.05 }}
                  whileTap={{ scale: 0.95 }}
                  onClick={() => setViewMode('grid')}
                  className={`p-2 rounded-lg transition-all ${
                    viewMode === 'grid'
                      ? 'bg-gradient-to-r from-blue-500 to-blue-600 text-white'
                      : 'bg-white/10 text-foreground hover:bg-white/20'
                  }`}
                >
                  <Grid size={20} />
                </motion.button>
                <motion.button
                  whileHover={{ scale: 1.05 }}
                  whileTap={{ scale: 0.95 }}
                  onClick={() => setViewMode('list')}
                  className={`p-2 rounded-lg transition-all ${
                    viewMode === 'list'
                      ? 'bg-gradient-to-r from-blue-500 to-blue-600 text-white'
                      : 'bg-white/10 text-foreground hover:bg-white/20'
                  }`}
                >
                  <List size={20} />
                </motion.button>
              </div>
            </div>

            {/* Loading State */}
            {loading && (
              <div className="flex items-center justify-center py-20">
                <div className="text-center">
                  <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-500 mx-auto mb-4"></div>
                  <p className="text-muted-foreground">Carregando imóveis...</p>
                </div>
              </div>
            )}

            {/* Error State */}
            {error && !loading && (
              <div className="glass-panel rounded-xl p-8 text-center">
                <p className="text-red-500 mb-4">❌ {error}</p>
                <button 
                  onClick={() => window.location.reload()} 
                  className="px-4 py-2 bg-blue-500 hover:bg-blue-600 rounded-lg text-white"
                >
                  Tentar Novamente
                </button>
              </div>
            )}

            {/* Properties */}
            {!loading && !error && (
              <motion.div
                variants={containerVariants}
                initial="hidden"
                animate="visible"
                className={viewMode === 'grid' ? 'grid grid-cols-1 sm:grid-cols-2 gap-6' : 'space-y-4'}
              >
                {filteredProperties.length === 0 ? (
                  <div className="col-span-2 glass-panel rounded-xl p-8 text-center">
                    <p className="text-muted-foreground">Nenhum imóvel encontrado com os filtros selecionados.</p>
                  </div>
                ) : (
                  filteredProperties.map((property, index) => {
                    const firstImage = property.fotos?.[0]?.url || property.imagens?.[0];
                    const price = property.valor_venda || property.valor_aluguel || 0;
                    const locationParts = [property.bairro, property.cidade].filter(Boolean);
                    const location = locationParts.length > 0 ? locationParts.join(', ') : 'Localização não informada';
                    const bedrooms = property.quartos ?? property.dormitorios;
                    const area = property.area_util || property.area_total;

                    return (
                      <motion.div
                        key={property.id}
                        variants={itemVariants}
                        transition={{ delay: 0.3 + index * 0.05 }}
                        whileHover={{ y: -4 }}
                        className="glass-panel rounded-2xl overflow-hidden group cursor-pointer"
                      >
                        {/* Image */}
                        <div className="relative h-48 bg-gradient-to-br from-blue-500/20 to-purple-500/20 overflow-hidden">
                          {firstImage ? (
                            <img 
                              src={firstImage} 
                              alt={property.titulo}
                              className="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300"
                              onError={(e) => {
                                console.error('Erro ao carregar imagem:', firstImage);
                                e.currentTarget.style.display = 'none';
                                const parent = e.currentTarget.parentElement;
                                if (parent) {
                                  const fallback = document.createElement('div');
                                  fallback.className = 'w-full h-full flex flex-col items-center justify-center text-muted-foreground';
                                  fallback.innerHTML = `<div class="text-6xl mb-2">${property.tipo_imovel?.includes('Casa') ? '🏠' : '🏢'}</div><div class="text-xs flex items-center gap-1"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>Imagem não disponível</div>`;
                                  parent.appendChild(fallback);
                                }
                              }}
                              loading="lazy"
                            />
                          ) : (
                            <div className="w-full h-full flex flex-col items-center justify-center text-muted-foreground">
                              <div className="text-6xl mb-2">
                                {property.tipo_imovel?.includes('Casa') ? '🏠' : '🏢'}
                              </div>
                              <div className="text-xs">Sem imagem</div>
                            </div>
                          )}

                          {property.destaque && (
                            <div className="absolute top-4 right-4">
                              <motion.div
                                initial={{ scale: 0 }}
                                animate={{ scale: 1 }}
                                className="px-3 py-1 rounded-full text-xs font-bold text-white bg-gradient-to-r from-yellow-500 to-orange-500"
                              >
                                ⭐ Destaque
                              </motion.div>
                            </div>
                          )}

                          <div className="absolute top-4 left-4">
                            <div className="px-3 py-1 rounded-full text-xs font-bold text-white bg-black/50 backdrop-blur-md">
                              {property.tipo_negocio}
                            </div>
                          </div>

                          <div className="absolute top-4 right-4 flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                            <motion.button
                              whileHover={{ scale: 1.1 }}
                              whileTap={{ scale: 0.95 }}
                              onClick={(e) => handleLike(property.id, e)}
                              className={`p-2 ${likedProperties.has(property.id) ? 'bg-red-500' : 'bg-white/20 hover:bg-white/30'} rounded-lg backdrop-blur-md transition-all`}
                            >
                              <Heart size={18} className={likedProperties.has(property.id) ? 'text-white fill-white' : 'text-red-400'} />
                            </motion.button>
                            <motion.button
                              whileHover={{ scale: 1.1 }}
                              whileTap={{ scale: 0.95 }}
                              onClick={(e) => handleShare(property, e)}
                              className="p-2 bg-white/20 hover:bg-white/30 rounded-lg backdrop-blur-md transition-all"
                            >
                              <Share2 size={18} className="text-blue-400" />
                            </motion.button>
                          </div>
                        </div>

                        {/* Content */}
                        <div className="p-6">
                          <h3 className="text-lg font-bold text-foreground mb-2 line-clamp-2">{property.titulo}</h3>

                          <div className="flex items-center gap-2 text-sm text-muted-foreground mb-4">
                            <MapPin size={16} />
                            <span>{location || 'Localização não informada'}</span>
                          </div>

                          <div className="mb-4 p-3 bg-gradient-to-r from-blue-500/20 to-purple-500/20 rounded-lg">
                            <p className="text-xs text-muted-foreground mb-1">
                              {property.tipo_negocio === 'Venda' ? 'Preço' : 'Aluguel'}
                            </p>
                            <p className="text-2xl font-bold gradient-text">
                              R$ {price.toLocaleString('pt-BR')}
                            </p>
                          </div>

                          <div className="flex gap-4 mb-6 text-sm text-muted-foreground">
                            {bedrooms && (
                              <div className="flex items-center gap-1">
                                <Bed size={16} />
                                <span>{bedrooms}</span>
                              </div>
                            )}
                            {property.banheiros && (
                              <div className="flex items-center gap-1">
                                <Bath size={16} />
                                <span>{property.banheiros}</span>
                              </div>
                            )}
                            {area && (
                              <div className="flex items-center gap-1">
                                <Ruler size={16} />
                                <span>{area}m²</span>
                              </div>
                            )}
                          </div>

                          <motion.button
                            onClick={() => navigate(`/portal/imovel/${property.id}`)}
                            whileHover={{ scale: 1.05 }}
                            whileTap={{ scale: 0.95 }}
                            className="w-full flex items-center justify-center gap-2 px-4 py-2 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 rounded-lg text-sm font-semibold text-white transition-all glow-sm hover:glow-md"
                          >
                            <Eye size={16} />
                            Ver Detalhes
                          </motion.button>
                        </div>
                      </motion.div>
                    );
                  })
                )}
              </motion.div>
            )}
          </motion.div>
        </motion.div>
      </div>
    </div>
  );
}
