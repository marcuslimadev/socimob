import { useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import { Image, Search, Loader2, Share2, Sparkles, Download } from 'lucide-react';
import { toast } from 'sonner';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';

interface Property {
  id: number;
  title: string;
  type: string;
  transaction_type: string;
  city: string;
  state: string;
  neighborhood: string;
  bedrooms: number;
  bathrooms: number;
  area: number;
  price: number;
  photos: string[];
  description: string;
}

interface AdGeneration {
  property: Property;
  generatedText: string;
  isGenerating: boolean;
}

export default function PropertyAds() {
  const [properties, setProperties] = useState<Property[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedProperty, setSelectedProperty] = useState<Property | null>(null);
  const [adDialog, setAdDialog] = useState(false);
  const [adGeneration, setAdGeneration] = useState<AdGeneration | null>(null);

  useEffect(() => {
    fetchProperties();
  }, []);

  const fetchProperties = async () => {
    try {
      setIsLoading(true);
      const response = await api.get('/imoveis', { params: { per_page: 100 } });
      setProperties(response.data.data || []);
    } catch (error) {
      console.error('Erro ao carregar imóveis:', error);
      toast.error('Erro ao carregar imóveis');
    } finally {
      setIsLoading(false);
    }
  };

  const handleGenerateAd = async (property: Property) => {
    setSelectedProperty(property);
    setAdDialog(true);
    setAdGeneration({
      property,
      generatedText: '',
      isGenerating: true,
    });

    try {
      const response = await api.post(`/properties/${property.id}/generate-ad-description`);
      
      if (response.data.success) {
        setAdGeneration(prev => prev ? {
          ...prev,
          generatedText: response.data.description,
          isGenerating: false,
        } : null);
        toast.success('Texto gerado com sucesso!');
      } else {
        throw new Error(response.data.error || 'Erro ao gerar texto');
      }
    } catch (error: any) {
      console.error('Erro ao gerar propaganda:', error);
      const errorMsg = error.response?.data?.error || 'Erro ao gerar texto com IA';
      toast.error(errorMsg);
      
      // Fallback: gerar texto simples sem IA
      const fallbackText = generateFallbackText(property);
      setAdGeneration(prev => prev ? {
        ...prev,
        generatedText: fallbackText,
        isGenerating: false,
      } : null);
    }
  };

  const generateFallbackText = (property: Property) => {
    const parts = [];
    
    if (property.transaction_type === 'venda') {
      parts.push(`🏡 ${property.type} à venda`);
    } else {
      parts.push(`🏡 ${property.type} para alugar`);
    }
    
    parts.push(`em ${property.neighborhood}, ${property.city}/${property.state}`);
    
    if (property.bedrooms > 0) parts.push(`${property.bedrooms} quarto${property.bedrooms > 1 ? 's' : ''}`);
    if (property.bathrooms > 0) parts.push(`${property.bathrooms} banheiro${property.bathrooms > 1 ? 's' : ''}`);
    if (property.area > 0) parts.push(`${property.area}m²`);
    
    if (property.price > 0) {
      parts.push(`R$ ${property.price.toLocaleString('pt-BR')}`);
    }
    
    return parts.join(' | ').substring(0, 400);
  };

  const handleCopyText = () => {
    if (adGeneration?.generatedText) {
      navigator.clipboard.writeText(adGeneration.generatedText);
      toast.success('Texto copiado!');
    }
  };

  const handleShare = async () => {
    if (!adGeneration?.property || !adGeneration?.generatedText) return;

    const shareData = {
      title: adGeneration.property.title,
      text: adGeneration.generatedText,
      url: window.location.origin + `/imoveis/${adGeneration.property.id}`,
    };

    if (navigator.share) {
      try {
        await navigator.share(shareData);
        toast.success('Compartilhado com sucesso!');
      } catch (error) {
        if ((error as Error).name !== 'AbortError') {
          handleCopyText();
        }
      }
    } else {
      handleCopyText();
    }
  };

  const filteredProperties = properties.filter(prop =>
    prop.title?.toLowerCase().includes(searchTerm.toLowerCase()) ||
    prop.neighborhood?.toLowerCase().includes(searchTerm.toLowerCase()) ||
    prop.city?.toLowerCase().includes(searchTerm.toLowerCase())
  );

  const getTransactionBadge = (type: string) => {
    return type === 'venda' 
      ? 'bg-green-500/20 text-green-300 border-green-500/40'
      : 'bg-blue-500/20 text-blue-300 border-blue-500/40';
  };

  return (
    <div className="flex">
      <Sidebar />
      <div className="page-shell">
        <motion.div
          initial={{ opacity: 0, y: 16 }}
          animate={{ opacity: 1, y: 0 }}
          className="max-w-7xl mx-auto"
        >
          <div className="page-header mb-8">
            <div>
              <h1 className="page-title mb-2">Propaganda de Imóveis</h1>
              <p className="page-subtitle">Gerencie anúncios e campanhas de marketing</p>
            </div>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
            {[
              { label: 'Total de Anúncios', value: properties.length, color: 'from-blue-500 to-blue-600' },
              { label: 'Ativos', value: properties.length, color: 'from-green-500 to-green-600' },
              { label: 'Total de Views', value: 0, color: 'from-purple-500 to-purple-600' },
              { label: 'Total de Cliques', value: 0, color: 'from-orange-500 to-orange-600' },
            ].map((stat) => (
              <motion.div
                key={stat.label}
                initial={{ opacity: 0, scale: 0.9 }}
                animate={{ opacity: 1, scale: 1 }}
                className="glass-panel p-4 rounded-xl text-center"
              >
                <p className="text-muted-foreground text-sm font-medium mb-1">{stat.label}</p>
                <p className="text-2xl font-bold text-foreground">{stat.value.toLocaleString()}</p>
              </motion.div>
            ))}
          </div>

          <div className="glass-panel p-6 rounded-2xl mb-6">
            <div className="relative">
              <Search className="absolute left-4 top-1/2 -translate-y-1/2 text-muted-foreground" size={20} />
              <input
                type="text"
                placeholder="Buscar por imóvel ou plataforma..."
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="w-full pl-12 pr-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
          </div>

          {isLoading ? (
            <div className="flex justify-center py-20">
              <Loader2 className="animate-spin h-12 w-12 text-blue-500" />
            </div>
          ) : filteredProperties.length === 0 ? (
            <div className="glass-panel rounded-2xl p-12 text-center">
              <Image size={64} className="mx-auto mb-4 text-muted-foreground opacity-50" />
              <h3 className="text-xl font-semibold text-foreground mb-2">Nenhum imóvel encontrado</h3>
              <p className="text-muted-foreground mb-4">Cadastre imóveis para começar a criar propagandas</p>
            </div>
          ) : (
            <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
              {filteredProperties.map((property) => (
                <motion.div
                  key={property.id}
                  initial={{ opacity: 0, scale: 0.95 }}
                  animate={{ opacity: 1, scale: 1 }}
                  className="glass-panel rounded-xl overflow-hidden hover:bg-white/10 transition-all"
                >
                  <div className="relative h-48 bg-gradient-to-br from-blue-500/20 to-purple-500/20">
                    {property.photos && property.photos.length > 0 ? (
                      <img
                        src={property.photos[0]}
                        alt={property.title}
                        className="w-full h-full object-cover"
                      />
                    ) : (
                      <div className="flex items-center justify-center h-full">
                        <Image size={48} className="text-muted-foreground opacity-30" />
                      </div>
                    )}
                    <div className="absolute top-3 right-3">
                      <span className={`px-3 py-1 rounded-full text-xs font-semibold border ${getTransactionBadge(property.transaction_type)}`}>
                        {property.transaction_type === 'venda' ? 'Venda' : 'Aluguel'}
                      </span>
                    </div>
                  </div>
                  
                  <div className="p-4">
                    <h3 className="font-semibold text-foreground mb-2 line-clamp-1">{property.title}</h3>
                    <p className="text-sm text-muted-foreground mb-3">
                      {property.neighborhood}, {property.city}/{property.state}
                    </p>
                    
                    <div className="flex items-center gap-4 text-xs text-muted-foreground mb-4">
                      {property.bedrooms > 0 && <span>{property.bedrooms} quartos</span>}
                      {property.bathrooms > 0 && <span>{property.bathrooms} banheiros</span>}
                      {property.area > 0 && <span>{property.area}m²</span>}
                    </div>
                    
                    {property.price > 0 && (
                      <p className="text-lg font-bold text-green-400 mb-4">
                        R$ {property.price.toLocaleString('pt-BR')}
                      </p>
                    )}
                    
                    <motion.button
                      whileHover={{ scale: 1.02 }}
                      whileTap={{ scale: 0.98 }}
                      onClick={() => handleGenerateAd(property)}
                      className="w-full flex items-center justify-center gap-2 px-4 py-2 bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg text-white font-semibold"
                    >
                      <Sparkles size={16} />
                      Gerar Propaganda IA
                    </motion.button>
                  </div>
                </motion.div>
              ))}
            </div>
          )}
        </motion.div>
      </div>

      {/* Dialog de Propaganda Gerada */}
      <Dialog open={adDialog} onOpenChange={setAdDialog}>
        <DialogContent className="bg-[#0f0f0f] border-0 max-w-[360px] p-0 h-[640px] overflow-hidden">
          {adGeneration?.isGenerating ? (
            <div className="flex flex-col items-center justify-center h-full">
              <Loader2 className="animate-spin h-12 w-12 text-purple-500 mb-4" />
              <p className="text-muted-foreground">Gerando texto com IA...</p>
            </div>
          ) : (
            <div className="flex flex-col h-full">
              {/* Grid de até 6 imagens - Formato 9:16 */}
              {adGeneration?.property.photos && adGeneration.property.photos.length > 0 && (
                <div className="grid grid-cols-3 gap-0.5 flex-1">
                  {adGeneration.property.photos.slice(0, 6).map((photo, index) => (
                    <div 
                      key={index}
                      className={`relative ${
                        adGeneration.property.photos.length === 1 ? 'col-span-3 h-full' : 
                        index === 0 && adGeneration.property.photos.length > 1 ? 'col-span-2 row-span-2' : 
                        ''
                      }`}
                    >
                      <img
                        src={photo}
                        alt={`${adGeneration.property.title} - Foto ${index + 1}`}
                        className="w-full h-full object-cover"
                      />
                    </div>
                  ))}
                </div>
              )}
              
              {/* Texto da propaganda */}
              <div className="px-4 py-4 bg-black/30">
                <p className="text-white whitespace-pre-wrap text-sm leading-relaxed">
                  {adGeneration?.generatedText}
                </p>
                <p className="text-[10px] text-gray-400 mt-2 text-right">
                  {adGeneration?.generatedText.length || 0} / 400
                </p>
              </div>
              
              {/* Botões de ação */}
              <div className="flex gap-2 px-4 pb-4">
                <motion.button
                  whileHover={{ scale: 1.02 }}
                  whileTap={{ scale: 0.98 }}
                  onClick={handleCopyText}
                  className="flex-1 flex items-center justify-center gap-1.5 px-3 py-2.5 bg-white/10 hover:bg-white/20 rounded-lg text-white font-medium text-xs transition-all"
                >
                  <Download size={14} />
                  Copiar
                </motion.button>
                <motion.button
                  whileHover={{ scale: 1.02 }}
                  whileTap={{ scale: 0.98 }}
                  onClick={handleShare}
                  className="flex-1 flex items-center justify-center gap-1.5 px-3 py-2.5 bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg text-white font-medium text-xs"
                >
                  <Share2 size={14} />
                  Compartilhar
                </motion.button>
              </div>
            </div>
          )}
        </DialogContent>
      </Dialog>
    </div>
  );
}
