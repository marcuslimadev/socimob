import { useState } from 'react';
import { motion } from 'framer-motion';
import { Search, Filter, Plus, Download, Zap } from 'lucide-react';
import Sidebar from '@/components/Sidebar';
import LeadCard from '@/components/LeadCard';

export default function Leads() {
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedStatus, setSelectedStatus] = useState('todos');
  const [selectedSort, setSelectedSort] = useState('recente');

  const leads = [
    { id: '1', name: 'João Silva', phone: '(11) 98765-4321', email: 'joao@email.com', status: 'novo' as const },
    { id: '2', name: 'Maria Santos', phone: '(11) 98765-4322', status: 'contato' as const, value: 450000 },
    { id: '3', name: 'Pedro Costa', phone: '(11) 98765-4323', status: 'interesse' as const, value: 180000, lastContact: 'Há 2 horas' },
    { id: '4', name: 'Ana Oliveira', phone: '(11) 98765-4324', status: 'negociacao' as const, value: 320000 },
    { id: '5', name: 'Carlos Souza', phone: '(11) 98765-4325', status: 'fechado' as const, value: 520000, lastContact: 'Ontem' },
    { id: '6', name: 'Lucia Mendes', phone: '(11) 98765-4326', status: 'novo' as const },
  ];

  const filteredLeads = leads.filter((lead) => {
    const matchSearch = lead.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
      lead.phone.includes(searchTerm);
    const matchStatus = selectedStatus === 'todos' || lead.status === selectedStatus;
    return matchSearch && matchStatus;
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
                <h1 className="text-4xl font-bold gradient-text mb-2">Leads</h1>
                <p className="text-muted-foreground">Gerencie e acompanhe todos os seus leads</p>
              </div>
              <motion.button
                whileHover={{ scale: 1.05 }}
                whileTap={{ scale: 0.95 }}
                className="flex items-center justify-center gap-2 px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 rounded-lg font-semibold text-white transition-all glow-md hover:glow-lg sm:self-auto"
              >
                <Plus size={20} />
                Novo Lead
              </motion.button>
            </div>
          </motion.div>

          <motion.div variants={itemVariants} className="glass-panel p-6 rounded-2xl mb-8">
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
              <div className="relative">
                <Search className="absolute left-4 top-1/2 -translate-y-1/2 text-muted-foreground" size={20} />
                <input
                  type="text"
                  placeholder="Buscar por nome ou telefone..."
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  className="w-full pl-12 pr-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                />
              </div>

              <select
                value={selectedStatus}
                onChange={(e) => setSelectedStatus(e.target.value)}
                className="px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
              >
                <option value="todos">Todos os Status</option>
                <option value="novo">Novo</option>
                <option value="contato">Primeiro Contato</option>
                <option value="interesse">Interesse</option>
                <option value="negociacao">Negociação</option>
                <option value="fechado">Fechado</option>
              </select>

              <select
                value={selectedSort}
                onChange={(e) => setSelectedSort(e.target.value)}
                className="px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
              >
                <option value="recente">Mais Recente</option>
                <option value="valor-alto">Maior Valor</option>
                <option value="valor-baixo">Menor Valor</option>
                <option value="nome">Nome (A-Z)</option>
              </select>

              <div className="flex gap-2">
                <motion.button
                  whileHover={{ scale: 1.05 }}
                  whileTap={{ scale: 0.95 }}
                  className="flex-1 flex items-center justify-center gap-2 px-4 py-3 bg-white/10 hover:bg-white/20 border border-white/20 rounded-lg text-foreground transition-all"
                >
                  <Filter size={20} />
                </motion.button>
                <motion.button
                  whileHover={{ scale: 1.05 }}
                  whileTap={{ scale: 0.95 }}
                  className="flex-1 flex items-center justify-center gap-2 px-4 py-3 bg-white/10 hover:bg-white/20 border border-white/20 rounded-lg text-foreground transition-all"
                >
                  <Download size={20} />
                </motion.button>
              </div>
            </div>
          </motion.div>

          <motion.div variants={itemVariants} className="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
            {[
              { label: 'Total', value: leads.length, color: 'from-blue-500 to-blue-600' },
              { label: 'Novo', value: leads.filter(l => l.status === 'novo').length, color: 'from-cyan-500 to-cyan-600' },
              { label: 'Contato', value: leads.filter(l => l.status === 'contato').length, color: 'from-purple-500 to-purple-600' },
              { label: 'Interesse', value: leads.filter(l => l.status === 'interesse').length, color: 'from-orange-500 to-orange-600' },
              { label: 'Fechado', value: leads.filter(l => l.status === 'fechado').length, color: 'from-green-500 to-green-600' },
            ].map((stat, index) => (
              <motion.div
                key={stat.label}
                initial={{ opacity: 0, scale: 0.9 }}
                animate={{ opacity: 1, scale: 1 }}
                transition={{ delay: 0.3 + index * 0.05 }}
                className={`glass-panel p-4 rounded-xl text-center`}
              >
                <p className="text-muted-foreground text-sm font-medium mb-1">{stat.label}</p>
                <p className="text-2xl font-bold text-foreground">{stat.value}</p>
              </motion.div>
            ))}
          </motion.div>

          <motion.div
            variants={containerVariants}
            initial="hidden"
            animate="visible"
            className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"
          >
            {filteredLeads.length === 0 ? (
              <motion.div
                variants={itemVariants}
                className="col-span-full text-center py-12"
              >
                <Zap size={48} className="mx-auto mb-4 text-muted-foreground opacity-50" />
                <p className="text-lg font-semibold text-foreground mb-2">Nenhum lead encontrado</p>
                <p className="text-muted-foreground">Tente ajustar seus filtros de busca</p>
              </motion.div>
            ) : (
              filteredLeads.map((lead, index) => (
                <motion.div
                  key={lead.id}
                  variants={itemVariants}
                  transition={{ delay: 0.3 + index * 0.05 }}
                >
                  <LeadCard
                    name={lead.name}
                    phone={lead.phone}
                    email={lead.email}
                    status={lead.status}
                    value={lead.value}
                    lastContact={lead.lastContact}
                  />
                </motion.div>
              ))
            )}
          </motion.div>
        </motion.div>
      </div>
    </div>
  );
}
