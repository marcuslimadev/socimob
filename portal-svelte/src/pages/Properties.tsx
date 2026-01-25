import { useState } from 'react';
import { motion } from 'framer-motion';
import { Search, Filter, Plus, MapPin, Bed, Bath, Ruler, Heart, Share2, Eye } from 'lucide-react';
import Sidebar from '@/components/Sidebar';

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
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedType, setSelectedType] = useState('todos');
  const [selectedStatus, setSelectedStatus] = useState('todos');

  const properties: Property[] = [
    {
      id: '1',
      title: 'Apartamento Moderno 2 Quartos',
      price: 450000,
      type: 'apartamento',
      status: 'venda',
      location: 'Pinheiros, São Paulo',
      bedrooms: 2,
      bathrooms: 2,
      area: 75,
      image: '🏢',
    },
    {
      id: '2',
      title: 'Casa Térrea 3 Quartos',
      price: 850000,
      type: 'casa',
      status: 'venda',
      location: 'Vila Madalena, São Paulo',
      bedrooms: 3,
      bathrooms: 3,
      area: 180,
      image: '🏠',
    },
    {
      id: '3',
      title: 'Apartamento Studio',
      price: 2500,
      type: 'apartamento',
      status: 'aluguel',
      location: 'Itaim Bibi, São Paulo',
      bedrooms: 1,
      bathrooms: 1,
      area: 45,
      image: '🏢',
    },
  ];

  const filteredProperties = properties.filter((prop) => {
    const matchSearch = prop.title.toLowerCase().includes(searchTerm.toLowerCase());
    const matchType = selectedType === 'todos' || prop.type === selectedType;
    const matchStatus = selectedStatus === 'todos' || prop.status === selectedStatus;
    return matchSearch && matchType && matchStatus;
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

  return (
    <div className="flex">
      <Sidebar />

      <div className="flex-1 md:ml-80 min-h-screen px-4 pb-6 pt-20 md:p-8">
        <motion.div
          variants={containerVariants}
          initial="hidden"
          animate="visible"
          className="max-w-7xl mx-auto"
        >
          <motion.div variants={itemVariants} className="mb-8">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between mb-4">
              <div>
                <h1 className="text-4xl font-bold gradient-text mb-2">Imóveis</h1>
                <p className="text-muted-foreground">Gerencie seu portfólio de propriedades</p>
              </div>
              <motion.button
                whileHover={{ scale: 1.05 }}
                whileTap={{ scale: 0.95 }}
                className="flex items-center justify-center gap-2 px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 rounded-lg font-semibold text-white transition-all glow-md hover:glow-lg sm:self-auto"
              >
                <Plus size={20} />
                Novo Imóvel
              </motion.button>
            </div>
          </motion.div>

          <motion.div variants={itemVariants} className="glass-panel p-6 rounded-2xl mb-8">
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
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
                className="flex items-center justify-center gap-2 px-4 py-3 bg-white/10 hover:bg-white/20 border border-white/20 rounded-lg text-foreground transition-all"
              >
                <Filter size={20} />
              </motion.button>
            </div>
          </motion.div>

          <motion.div
            variants={containerVariants}
            initial="hidden"
            animate="visible"
            className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"
          >
            {filteredProperties.map((property, index) => (
              <motion.div
                key={property.id}
                variants={itemVariants}
                transition={{ delay: 0.3 + index * 0.05 }}
                whileHover={{ y: -4 }}
                className="glass-panel rounded-2xl overflow-hidden group cursor-pointer"
              >
                <div className="relative h-48 bg-gradient-to-br from-blue-500/20 to-purple-500/20 flex items-center justify-center text-6xl overflow-hidden">
                  <motion.div
                    whileHover={{ scale: 1.1 }}
                    transition={{ type: 'spring', stiffness: 200 }}
                  >
                    {property.image}
                  </motion.div>

                  <div className="absolute top-4 right-4">
                    <motion.div
                      initial={{ scale: 0 }}
                      animate={{ scale: 1 }}
                      className={`px-3 py-1 rounded-full text-xs font-bold text-white ${
                        property.status === 'venda'
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
        </motion.div>
      </div>
    </div>
  );
}
