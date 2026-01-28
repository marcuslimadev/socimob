import { useState, useEffect } from 'react';
import { motion } from 'framer-motion';
import { Search, Filter, Plus, MapPin, Bed, Bath, Ruler, Heart, Share2, Eye, X } from 'lucide-react';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';
import { toast } from 'sonner';

interface Property {
  id: string;
  title: string;
  price: number;
  type: string;
  status: string;
  location: string;
  bedrooms: number;
  bathrooms: number;
  area: number;
  image: string;
}

export default function Properties() {
  const [properties, setProperties] = useState<Property[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedType, setSelectedType] = useState('todos');
  const [selectedStatus, setSelectedStatus] = useState('todos');
  const [minPrice, setMinPrice] = useState('');
  const [maxPrice, setMaxPrice] = useState('');
  const [minBedrooms, setMinBedrooms] = useState('');
  const [minBathrooms, setMinBathrooms] = useState('');
  const [minArea, setMinArea] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const itemsPerPage = 9;

  useEffect(() => {
    fetchProperties();
  }, []);

  useEffect(() => {
    setCurrentPage(1);
  }, [searchTerm, selectedType, selectedStatus, minPrice, maxPrice, minBedrooms, minBathrooms, minArea]);

  const fetchProperties = async () => {
    try {
      setIsLoading(true);
      const response = await api.get('/properties');
      if (response.data.success) {
        const mappedProperties = response.data.data.map((item: any) => ({
          id: item.id.toString(),
          title: item.titulo || 'Sem título',
          price: parseFloat(item.valor_venda) || 0,
          type: (item.tipo_imovel || 'outros').toLowerCase(),
          status: (item.finalidade_imovel || 'venda').toLowerCase().includes('aluguel') ? 'aluguel' : 'venda',
          location: `${item.bairro || ''}, ${item.cidade || ''}`.replace(/^, /, '').replace(/, $/, '') || 'Localização não informada',
          bedrooms: parseInt(item.dormitorios) || 0,
          bathrooms: parseInt(item.banheiros) || 0,
          area: parseFloat(item.area_total) || 0,
          image: Array.isArray(item.imagens) && item.imagens.length > 0 ? item.imagens[0] : (item.foto_capa || '🏢'),
        }));
        setProperties(mappedProperties);
      }
    } catch (error) {
      console.error('Erro ao buscar imóveis:', error);
      toast.error('Erro ao carregar imóveis');
    } finally {
      setIsLoading(false);
    }
  };

  const filteredProperties = properties.filter((prop) => {
    const matchSearch = [prop.title, prop.location].some((value) =>
      value.toLowerCase().includes(searchTerm.toLowerCase())
    );
    const matchType = selectedType === 'todos' || prop.type === selectedType;
    const matchStatus = selectedStatus === 'todos' || prop.status === selectedStatus;
    const matchMinPrice = minPrice ? prop.price >= Number(minPrice) : true;
    const matchMaxPrice = maxPrice ? prop.price <= Number(maxPrice) : true;
    const matchBedrooms = minBedrooms ? prop.bedrooms >= Number(minBedrooms) : true;
    const matchBathrooms = minBathrooms ? prop.bathrooms >= Number(minBathrooms) : true;
    const matchArea = minArea ? prop.area >= Number(minArea) : true;
    return (
      matchSearch &&
      matchType &&
      matchStatus &&
      matchMinPrice &&
      matchMaxPrice &&
      matchBedrooms &&
      matchBathrooms &&
      matchArea
    );
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

  const formatPrice = (price: number, isRent: boolean) => {
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL',
    }).format(price) + (isRent ? '/mês' : '');
  };

  const totalPages = Math.max(1, Math.ceil(filteredProperties.length / itemsPerPage));
  const clampedPage = Math.min(currentPage, totalPages);
  const paginatedProperties = filteredProperties.slice(
    (clampedPage - 1) * itemsPerPage,
    clampedPage * itemsPerPage
  );

  const resetFilters = () => {
    setSearchTerm('');
    setSelectedType('todos');
    setSelectedStatus('todos');
    setMinPrice('');
    setMaxPrice('');
    setMinBedrooms('');
    setMinBathrooms('');
    setMinArea('');
    setCurrentPage(1);
  };

  const handlePageChange = (page: number) => {
    setCurrentPage(page);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  };

  return (
    <div className="flex">
      <Sidebar />

      <div className="flex-1 md:ml-80 min-h-screen p-4 md:p-8">
        <motion.div
          variants={containerVariants}
          initial="hidden"
          animate="visible"
          className="max-w-7xl mx-auto"
        >
          <motion.div variants={itemVariants} className="mb-8">
            <div className="flex items-center justify-between mb-4">
              <div>
                <h1 className="text-4xl font-bold gradient-text mb-2">Imóveis</h1>
                <p className="text-muted-foreground">Gerencie seu portfólio de propriedades</p>
              </div>
              <motion.button
                whileHover={{ scale: 1.05 }}
                whileTap={{ scale: 0.95 }}
                className="flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 rounded-lg font-semibold text-white transition-all glow-md hover:glow-lg"
              >
                <Plus size={20} />
                Novo Imóvel
              </motion.button>
            </div>
          </motion.div>

          <motion.div variants={itemVariants} className="glass-panel p-6 rounded-2xl mb-8">
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
              <div className="relative">
                <Search className="absolute left-4 top-1/2 -translate-y-1/2 text-muted-foreground" size={20} />
                <input
                  type="text"
                  placeholder="Buscar por título..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="w-full pl-12 pr-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                />
              </div>

              <select
                value={selectedType}
                onChange={(e) => setSelectedType(e.target.value)}
                className="px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
              >
                <option value="todos">Todos os Tipos</option>
                <option value="apartamento">Apartamento</option>
                <option value="casa">Casa</option>
                <option value="comercial">Comercial</option>
                <option value="terreno">Terreno</option>
              </select>

              <select
                value={selectedStatus}
                onChange={(e) => setSelectedStatus(e.target.value)}
                className="px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
              >
                <option value="todos">Todos os Status</option>
                <option value="venda">Venda</option>
                <option value="aluguel">Aluguel</option>
              </select>

              <motion.button
                whileHover={{ scale: 1.05 }}
                whileTap={{ scale: 0.95 }}
                onClick={resetFilters}
                className="flex items-center justify-center gap-2 px-4 py-3 bg-white/10 hover:bg-white/20 border border-white/20 rounded-lg text-foreground transition-all"
              >
                <X size={18} />
                Limpar
              </motion.button>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-5 gap-4">
              <input
                type="number"
                min={0}
                placeholder="Preço mín."
                value={minPrice}
                onChange={(e) => setMinPrice(e.target.value)}
                className="px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
              />
              <input
                type="number"
                min={0}
                placeholder="Preço máx."
                value={maxPrice}
                onChange={(e) => setMaxPrice(e.target.value)}
                className="px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
              />
              <input
                type="number"
                min={0}
                placeholder="Dorms mín."
                value={minBedrooms}
                onChange={(e) => setMinBedrooms(e.target.value)}
                className="px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
              />
              <input
                type="number"
                min={0}
                placeholder="Banhos mín."
                value={minBathrooms}
                onChange={(e) => setMinBathrooms(e.target.value)}
                className="px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
              />
              <input
                type="number"
                min={0}
                placeholder="Área mín. (m²)"
                value={minArea}
                onChange={(e) => setMinArea(e.target.value)}
                className="px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
              />
            </div>
          </motion.div>

          {isLoading ? (
            <div className="flex justify-center py-20">
              <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary"></div>
            </div>
          ) : (
            <>
              <div className="flex items-center justify-between mb-6 text-sm text-muted-foreground">
                <span>
                  {filteredProperties.length} imóvel{filteredProperties.length !== 1 ? 'is' : ''} encontrado
                  {filteredProperties.length !== 1 ? 's' : ''}
                </span>
                <span>Página {clampedPage} de {totalPages}</span>
              </div>

              <motion.div
                variants={containerVariants}
                initial="hidden"
                animate="visible"
                className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"
              >
                {paginatedProperties.map((property, index) => (
                  <motion.div
                    key={property.id}
                    variants={itemVariants}
                    transition={{ delay: 0.3 + index * 0.05 }}
                    whileHover={{ y: -4 }}
                    className="glass-panel rounded-2xl overflow-hidden group cursor-pointer"
                  >
                    <div className="relative h-48 bg-gradient-to-br from-blue-500/20 to-purple-500/20 flex items-center justify-center text-6xl overflow-hidden">
                      {/* Placeholder for real image since I am not sure about image URL format yet, assuming URL or emoji for now if text */}
                      {property.image.length > 5 ? (
                        <img src={property.image} alt={property.title} className="w-full h-full object-cover" />
                      ) : (
                        <motion.div
                          whileHover={{ scale: 1.1 }}
                          transition={{ type: 'spring', stiffness: 200 }}
                        >
                          {property.image}
                        </motion.div>
                      )}

                      <div className="absolute top-4 right-4">
                        <motion.div
                          initial={{ scale: 0 }}
                          animate={{ scale: 1 }}
                          className={`px-3 py-1 rounded-full text-xs font-bold text-white ${property.status === 'venda'
                              ? 'bg-gradient-to-r from-blue-500 to-blue-600'
                              : 'bg-gradient-to-r from-green-500 to-emerald-600'
                            }`}
                        >
                          {property.status === 'venda' ? 'Venda' : 'Aluguel'}
                        </motion.div>
                      </div>

                      <div className="absolute top-4 left-4 flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                        <motion.button
                          whileHover={{ scale: 1.1 }}
                          whileTap={{ scale: 0.95 }}
                          className="p-2 bg-white/20 hover:bg-white/30 rounded-lg backdrop-blur-md transition-all"
                        >
                          <Heart size={18} className="text-red-400" />
                        </motion.button>
                        <motion.button
                          whileHover={{ scale: 1.1 }}
                          whileTap={{ scale: 0.95 }}
                          className="p-2 bg-white/20 hover:bg-white/30 rounded-lg backdrop-blur-md transition-all"
                        >
                          <Share2 size={18} className="text-blue-400" />
                        </motion.button>
                      </div>
                    </div>

                    <div className="p-6">
                      <h3 className="text-lg font-bold text-foreground mb-2 line-clamp-2">{property.title}</h3>

                      <div className="flex items-center gap-2 text-sm text-muted-foreground mb-4">
                        <MapPin size={16} />
                        <span>{property.location}</span>
                      </div>

                      <div className="mb-4 p-3 bg-gradient-to-r from-blue-500/20 to-purple-500/20 rounded-lg">
                        <p className="text-xs text-muted-foreground mb-1">Preço</p>
                        <p className="text-2xl font-bold gradient-text">
                          {formatPrice(property.price, property.status === 'aluguel')}
                        </p>
                      </div>

                      <div className="flex gap-4 mb-6 text-sm text-muted-foreground">
                        {property.bedrooms > 0 && (
                          <div className="flex items-center gap-1">
                            <Bed size={16} />
                            <span>{property.bedrooms}</span>
                          </div>
                        )}
                        {property.bathrooms > 0 && (
                          <div className="flex items-center gap-1">
                            <Bath size={16} />
                            <span>{property.bathrooms}</span>
                          </div>
                        )}
                        <div className="flex items-center gap-1">
                          <Ruler size={16} />
                          <span>{property.area}m²</span>
                        </div>
                      </div>

                      <div className="flex gap-2">
                        <motion.button
                          whileHover={{ scale: 1.05 }}
                          whileTap={{ scale: 0.95 }}
                          className="flex-1 flex items-center justify-center gap-2 px-4 py-2 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 rounded-lg text-sm font-semibold text-white transition-all"
                        >
                          <Eye size={16} />
                          Ver
                        </motion.button>
                        <motion.button
                          whileHover={{ scale: 1.05 }}
                          whileTap={{ scale: 0.95 }}
                          className="px-4 py-2 bg-white/10 hover:bg-white/20 rounded-lg text-sm font-semibold text-foreground transition-all"
                        >
                          Editar
                        </motion.button>
                      </div>
                    </div>
                  </motion.div>
                ))}
              </motion.div>

              <div className="flex items-center justify-center gap-2 mt-10">
                <motion.button
                  whileHover={{ scale: 1.05 }}
                  whileTap={{ scale: 0.95 }}
                  onClick={() => handlePageChange(Math.max(1, clampedPage - 1))}
                  disabled={clampedPage === 1}
                  className="px-4 py-2 rounded-lg bg-white/10 border border-white/20 text-foreground disabled:opacity-40 disabled:cursor-not-allowed"
                >
                  Anterior
                </motion.button>
                {Array.from({ length: totalPages }, (_, index) => {
                  const page = index + 1;
                  return (
                    <motion.button
                      key={page}
                      whileHover={{ scale: 1.05 }}
                      whileTap={{ scale: 0.95 }}
                      onClick={() => handlePageChange(page)}
                      className={`w-10 h-10 rounded-lg border text-sm font-semibold transition-all ${
                        page === clampedPage
                          ? 'bg-gradient-to-r from-blue-500 to-blue-600 text-white border-blue-500/30'
                          : 'bg-white/10 text-foreground border-white/20 hover:bg-white/20'
                      }`}
                    >
                      {page}
                    </motion.button>
                  );
                })}
                <motion.button
                  whileHover={{ scale: 1.05 }}
                  whileTap={{ scale: 0.95 }}
                  onClick={() => handlePageChange(Math.min(totalPages, clampedPage + 1))}
                  disabled={clampedPage === totalPages}
                  className="px-4 py-2 rounded-lg bg-white/10 border border-white/20 text-foreground disabled:opacity-40 disabled:cursor-not-allowed"
                >
                  Próxima
                </motion.button>
              </div>
            </>
          )}
        </motion.div>
      </div>
    </div>
  );
}
