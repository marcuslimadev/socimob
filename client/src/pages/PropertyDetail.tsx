import { useState, useEffect } from 'react';
import { motion } from 'framer-motion';
import { useLocation, useRoute } from 'wouter';
import { ArrowLeft, MapPin, Bed, Bath, Ruler, Share2, Heart, Phone, ImageIcon, Car, Eye } from 'lucide-react';
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
  suites?: number;
  vagas_garagem?: number;
  area_total?: number;
  area_util?: number;
  area_privativa?: number;
  descricao?: string;
  destaque?: boolean;
  active?: boolean;
  fotos?: Array<{url: string; destaque: boolean}>;
  imagens?: string[];
  finalidade_imovel?: string;
  codigo?: string;
  condominio?: number;
  iptu?: number;
  ano_construcao?: string;
}

export default function PropertyDetail() {
  const [match, params] = useRoute('/portal/imovel/:id');
  const [, navigate] = useLocation();
  const [property, setProperty] = useState<Property | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [selectedImage, setSelectedImage] = useState<string | null>(null);

  useEffect(() => {
    const fetchProperty = async () => {
      if (!params?.id) return;
      
      try {
        setLoading(true);
        setError(null);
        const response = await api.get(`/api/portal/imoveis/${params.id}`);
        const propertyData = response.data.data || response.data;
        setProperty(propertyData);
        
        // Set first image as selected
        const imagens = propertyData.fotos?.length
          ? propertyData.fotos
          : propertyData.imagens?.map((url: string) => ({ url, destaque: false }));

        if (imagens && imagens.length > 0) {
          setSelectedImage(imagens[0].url);
        }
      } catch (err: any) {
        console.error('Erro ao carregar imóvel:', err);
        setError(err.message || 'Erro ao carregar detalhes do imóvel');
      } finally {
        setLoading(false);
      }
    };

    fetchProperty();
  }, [params?.id]);

  if (loading) {
    return (
      <div className="min-h-screen bg-background flex items-center justify-center">
        <div className="text-center">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary mx-auto mb-4"></div>
          <p className="text-muted-foreground">Carregando detalhes...</p>
        </div>
      </div>
    );
  }

  if (error || !property) {
    return (
      <div className="min-h-screen bg-background flex items-center justify-center p-4">
        <div className="glass-panel rounded-xl p-8 text-center max-w-md">
          <p className="text-red-500 mb-4">❌ {error || 'Imóvel não encontrado'}</p>
          <button 
            onClick={() => navigate('/portal')}
            className="px-4 py-2 bg-blue-500 hover:bg-blue-600 rounded-lg text-white"
          >
            Voltar ao Portal
          </button>
        </div>
      </div>
    );
  }

  const priceFromString = property.preco
    ? Number(property.preco.replace(/[^\d]/g, ''))
    : 0;
  const price = property.valor_venda || property.valor_aluguel || priceFromString || 0;
  const images = property.fotos?.length
    ? property.fotos
    : property.imagens?.map((url) => ({ url, destaque: false })) || [];
  const displayImage = selectedImage || images[0]?.url;
  const bedrooms = property.quartos ?? property.dormitorios;
  const area = property.area_util || property.area_privativa || property.area_total;
  const locationParts = [property.bairro, property.cidade, property.estado].filter(Boolean);

  return (
    <div className="min-h-screen bg-background">
      {/* Header */}
      <div className="glass-panel border-b">
        <div className="container mx-auto px-4 py-4">
          <button
            onClick={() => navigate('/portal')}
            className="flex items-center gap-2 text-muted-foreground hover:text-foreground transition-colors"
          >
            <ArrowLeft size={20} />
            <span>Voltar aos Imóveis</span>
          </button>
        </div>
      </div>

      <div className="container mx-auto px-4 py-8">
        <div className="grid grid-cols-1 lg:grid-cols-3 gap-8">
          {/* Left Column - Images */}
          <div className="lg:col-span-2 space-y-4">
            {/* Main Image */}
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              className="glass-panel rounded-2xl overflow-hidden"
            >
              <div className="relative h-64 sm:h-80 lg:h-96 bg-gradient-to-br from-blue-500/20 to-purple-500/20">
                {displayImage ? (
                  <img
                    src={displayImage}
                    alt={property.titulo}
                    className="w-full h-full object-cover"
                    onError={(e) => {
                      console.error('Erro ao carregar imagem:', displayImage);
                      e.currentTarget.style.display = 'none';
                    }}
                  />
                ) : (
                  <div className="w-full h-full flex flex-col items-center justify-center text-muted-foreground">
                    <div className="text-8xl mb-4">
                      {property.tipo_imovel?.includes('Casa') ? '🏠' : '🏢'}
                    </div>
                    <div className="flex items-center gap-2 text-sm">
                      <ImageIcon size={16} />
                      <span>Sem imagens disponíveis</span>
                    </div>
                  </div>
                )}
                
                {property.destaque && (
                  <div className="absolute top-4 right-4">
                    <div className="px-4 py-2 rounded-full text-sm font-bold text-white bg-gradient-to-r from-yellow-500 to-orange-500">
                      ⭐ Destaque
                    </div>
                  </div>
                )}

                <div className="absolute top-4 left-4">
                  <div className="px-4 py-2 rounded-full text-sm font-bold text-white bg-black/50 backdrop-blur-md">
                    {property.tipo_negocio}
                  </div>
                </div>
              </div>
            </motion.div>

            {/* Image Gallery */}
            {images.length > 0 && (
              <motion.div
                initial={{ opacity: 0 }}
                animate={{ opacity: 1 }}
                transition={{ delay: 0.2 }}
                className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-2"
              >
                {images.slice(0, 8).map((foto, index) => (
                  <button
                    key={index}
                    onClick={() => setSelectedImage(foto.url)}
                    className={`relative h-24 rounded-lg overflow-hidden transition-all ${
                      selectedImage === foto.url
                        ? 'ring-2 ring-blue-500'
                        : 'opacity-70 hover:opacity-100'
                    }`}
                  >
                    <img
                      src={foto.url}
                      alt={`Foto ${index + 1}`}
                      className="w-full h-full object-cover"
                      onError={(e) => {
                        e.currentTarget.style.display = 'none';
                      }}
                    />
                  </button>
                ))}
                {images.length > 8 && (
                  <div className="h-24 rounded-lg bg-gradient-to-br from-blue-500/20 to-purple-500/20 flex items-center justify-center">
                    <div className="text-center">
                      <Eye size={20} className="mx-auto mb-1" />
                      <span className="text-xs">+{images.length - 8}</span>
                    </div>
                  </div>
                )}
              </motion.div>
            )}

            {/* Description */}
            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.3 }}
              className="glass-panel rounded-2xl p-6"
            >
              <h2 className="text-xl font-bold mb-4">Descrição</h2>
              <p className="text-muted-foreground leading-relaxed">
                {property.descricao || 'Sem descrição disponível.'}
              </p>
            </motion.div>
          </div>

          {/* Right Column - Details */}
          <div className="space-y-4">
            <motion.div
              initial={{ opacity: 0, x: 20 }}
              animate={{ opacity: 1, x: 0 }}
              className="glass-panel rounded-2xl p-6 lg:sticky lg:top-4"
            >
              <h1 className="text-xl sm:text-2xl font-bold mb-4 break-words">{property.titulo}</h1>

              <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground mb-6">
                <MapPin size={18} />
                <span>{locationParts.length > 0 ? locationParts.join(', ') : 'Localização não informada'}</span>
              </div>

              <div className="mb-6 p-4 bg-gradient-to-r from-blue-500/20 to-purple-500/20 rounded-lg">
                <p className="text-sm text-muted-foreground mb-1">
                  {property.tipo_negocio === 'Venda' ? 'Preço de Venda' : 'Valor do Aluguel'}
                </p>
                <p className="text-3xl font-bold gradient-text">
                  R$ {price.toLocaleString('pt-BR')}
                </p>
              </div>

              {/* Features */}
              <div className="grid grid-cols-2 gap-4 mb-6">
                {bedrooms && (
                  <div className="flex items-center gap-2">
                    <Bed size={20} className="text-blue-500" />
                    <div>
                      <p className="text-xs text-muted-foreground">Quartos</p>
                      <p className="font-semibold">{bedrooms}</p>
                    </div>
                  </div>
                )}
                {property.banheiros && (
                  <div className="flex items-center gap-2">
                    <Bath size={20} className="text-blue-500" />
                    <div>
                      <p className="text-xs text-muted-foreground">Banheiros</p>
                      <p className="font-semibold">{property.banheiros}</p>
                    </div>
                  </div>
                )}
                {area && (
                  <div className="flex items-center gap-2">
                    <Ruler size={20} className="text-blue-500" />
                    <div>
                      <p className="text-xs text-muted-foreground">Área</p>
                      <p className="font-semibold">{area}m²</p>
                    </div>
                  </div>
                )}
                {property.vagas_garagem && (
                  <div className="flex items-center gap-2">
                    <Car size={20} className="text-blue-500" />
                    <div>
                      <p className="text-xs text-muted-foreground">Garagem</p>
                      <p className="font-semibold">{property.vagas_garagem} vagas</p>
                    </div>
                  </div>
                )}
                {property.suites && (
                  <div className="flex items-center gap-2">
                    <Bath size={20} className="text-blue-500" />
                    <div>
                      <p className="text-xs text-muted-foreground">Suítes</p>
                      <p className="font-semibold">{property.suites}</p>
                    </div>
                  </div>
                )}
              </div>

              {/* Additional Info */}
              {(property.condominio || property.iptu) && (
                <div className="border-t pt-4 mb-6 space-y-2">
                  {property.condominio && (
                    <div className="flex justify-between">
                      <span className="text-muted-foreground">Condomínio</span>
                      <span className="font-semibold">R$ {property.condominio.toLocaleString('pt-BR')}</span>
                    </div>
                  )}
                  {property.iptu && (
                    <div className="flex justify-between">
                      <span className="text-muted-foreground">IPTU</span>
                      <span className="font-semibold">R$ {property.iptu.toLocaleString('pt-BR')}</span>
                    </div>
                  )}
                </div>
              )}

              {/* Action Buttons */}
              <div className="space-y-3">
                <button className="w-full flex items-center justify-center gap-2 px-4 py-3 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 rounded-lg text-white font-semibold transition-all glow-sm hover:glow-md">
                  <Phone size={18} />
                  Entrar em Contato
                </button>
                <div className="flex gap-2">
                  <button className="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-white/10 hover:bg-white/20 rounded-lg transition-all">
                    <Heart size={18} />
                    Favoritar
                  </button>
                  <button className="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-white/10 hover:bg-white/20 rounded-lg transition-all">
                    <Share2 size={18} />
                    Compartilhar
                  </button>
                </div>
              </div>

              {/* Property Code */}
              {property.codigo && (
                <div className="mt-6 pt-6 border-t text-center">
                  <p className="text-xs text-muted-foreground">Código do Imóvel</p>
                  <p className="font-mono font-semibold">{property.codigo}</p>
                </div>
              )}
            </motion.div>
          </div>
        </div>
      </div>
    </div>
  );
}
