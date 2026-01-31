import { useState, useEffect } from 'react';
import { motion } from 'framer-motion';
import { Search, Filter, Plus, Download, Zap } from 'lucide-react';
import { toast } from 'sonner';
import { useLocation } from 'wouter';
import { api } from '@/lib/api';
import Sidebar from '@/components/Sidebar';
import LeadCard from '@/components/LeadCard';

interface Lead {
  id: string;
  name: string;
  phone: string;
  email: string;
  status: 'novo' | 'em_atendimento' | 'qualificado' | 'proposta' | 'fechado' | 'perdido';
  value?: number;
  lastContact?: string;
}

export default function Leads() {
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedStatus, setSelectedStatus] = useState('todos');
  const [selectedSort, setSelectedSort] = useState('recente');
  const [leads, setLeads] = useState<Lead[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [showNewLeadModal, setShowNewLeadModal] = useState(false);
  const [_, setLocation] = useLocation();

  useEffect(() => {
    fetchLeads();
  }, []);

  const fetchLeads = async () => {
    try {
      const response = await api.get('/leads');
      if (response.data.success) {
        // Map backend data to frontend interface - filter out invalid leads
        const mappedLeads = response.data.data
          .filter((item: any) => item && item.id != null) // Skip leads without ID
          .map((item: any) => ({
            id: item.id.toString(),
            name: item.nome || 'Sem nome',
            phone: item.telefone || '',
            email: item.email || '',
            status: item.status || 'novo',
            value: parseFloat(item.budget_max || item.budget_min || '0'),
            lastContact: formatDate(item.updated_at)
          }));
        setLeads(mappedLeads);
      }
    } catch (error) {
      console.error('Error fetching leads:', error);
      toast.error('Erro ao carregar leads');
    } finally {
      setIsLoading(false);
    }
  };

  const formatDate = (dateString: string) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    const now = new Date();
    const diffInHours = Math.abs(now.getTime() - date.getTime()) / 36e5;

    if (diffInHours < 24) {
      if (diffInHours < 1) return 'Há menos de 1 hora';
      return `Há ${Math.floor(diffInHours)} horas`;
    }
    return date.toLocaleDateString('pt-BR');
  };

  const handleExportCSV = () => {
    try {
      const csvContent = [
        ['Nome', 'Telefone', 'Email', 'Status', 'Valor', 'Último Contato'],
        ...sortedLeads.map(lead => [
          lead.name,
          lead.phone,
          lead.email || '',
          lead.status,
          lead.value ? `R$ ${lead.value.toLocaleString('pt-BR')}` : '',
          lead.lastContact || ''
        ])
      ].map(row => row.join(';')).join('\n');

      const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' });
      const link = document.createElement('a');
      link.href = URL.createObjectURL(blob);
      link.download = `leads_${new Date().toISOString().split('T')[0]}.csv`;
      link.click();

      toast.success('CSV exportado com sucesso!');
    } catch (error) {
      toast.error('Erro ao exportar CSV');
    }
  };

  const handleNewLead = () => {
    toast.info('Funcionalidade em desenvolvimento');
    // TODO: Implementar modal de novo lead
  };

  const handleOpenChat = (leadId: string, leadName: string) => {
    toast.info(`Abrindo chat com ${leadName}`);
    setLocation(`/chat?leadId=${leadId}`);
  };

  const handleCallAI = async (leadId: string, leadName: string) => {
    toast.info(`Iniciando atendimento IA para ${leadName}`);
    try {
      const response = await api.post(`/admin/leads/${leadId}/iniciar-atendimento`);
      if (response.data.success || response.status === 200) {
        toast.success(`Atendimento IA iniciado para ${leadName}`);
        fetchLeads(); // Refresh status
      }
    } catch (error) {
      console.error('Error starting AI:', error);
      toast.error('Erro ao iniciar atendimento IA');
    }
  };

  const handleCall = (phone: string, leadName: string) => {
    if (phone) {
      window.open(`tel:${phone}`);
      toast.success(`Ligando para ${leadName}`);
    } else {
      toast.error('Telefone não disponível');
    }
  };

  const handleDelete = async (leadId: string, leadName: string) => {
    if (!confirm(`Tem certeza que deseja excluir o lead ${leadName}? Esta ação não pode ser desfeita.`)) {
      return;
    }

    try {
      const response = await api.delete(`/leads/${leadId}`);

      if (response.data.success) {
        toast.success(`Lead ${leadName} excluído com sucesso`);
        // Remove lead from local state
        setLeads(prevLeads => prevLeads.filter(lead => lead.id !== leadId));
      }
    } catch (error) {
      console.error('Error deleting lead:', error);
      toast.error('Erro ao excluir lead');
    }
  };

  const filteredLeads = leads.filter((lead) => {
    const matchSearch = lead.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
      lead.phone.includes(searchTerm);
    const matchStatus = selectedStatus === 'todos' || lead.status === selectedStatus;
    return matchSearch && matchStatus;
  });

  // Aplicar ordenação
  const sortedLeads = [...filteredLeads].sort((a, b) => {
    switch (selectedSort) {
      case 'valor-alto':
        return (b.value || 0) - (a.value || 0);
      case 'valor-baixo':
        return (a.value || 0) - (b.value || 0);
      case 'nome':
        return a.name.localeCompare(b.name);
      case 'recente':
      default:
        return 0; // Já vem ordenado do backend
    }
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

      <div className="page-shell">
        <motion.div
          variants={containerVariants}
          initial="hidden"
          animate="visible"
          className="max-w-7xl mx-auto"
        >
          <motion.div variants={itemVariants} className="mb-8">
            <div className="page-header mb-4">
              <div>
                <h1 className="page-title mb-2">Leads</h1>
                <p className="page-subtitle">Gerencie e acompanhe todos os seus leads</p>
              </div>
              <motion.button
                whileHover={{ scale: 1.05 }}
                whileTap={{ scale: 0.95 }}
                onClick={handleNewLead}
                className="flex w-full items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-blue-500 to-blue-600 px-6 py-3 font-semibold text-white transition-all hover:from-blue-600 hover:to-blue-700 sm:w-auto glow-md hover:glow-lg"
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
                <option value="em_atendimento">Em Atendimento</option>
                <option value="qualificado">Qualificado</option>
                <option value="proposta">Proposta</option>
                <option value="fechado">Fechado</option>
                <option value="perdido">Perdido</option>
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
                  onClick={handleExportCSV}
                  title="Exportar para CSV"
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
              { label: 'Novo', value: leads.filter(l => l.status === 'novo').length, color: 'from-blue-500/50 to-blue-600/50' },
              { label: 'Em Atend.', value: leads.filter(l => l.status === 'em_atendimento').length, color: 'from-cyan-500 to-cyan-600' },
              { label: 'Qualificado', value: leads.filter(l => l.status === 'qualificado').length, color: 'from-purple-500 to-purple-600' },
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

          {isLoading ? (
            <div className="flex justify-center py-20">
              <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-primary"></div>
            </div>
          ) : (
            <motion.div
              variants={containerVariants}
              initial="hidden"
              animate="visible"
              className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"
            >
              {sortedLeads.length === 0 ? (
                <motion.div
                  variants={itemVariants}
                  className="col-span-full text-center py-12"
                >
                  <Zap size={48} className="mx-auto mb-4 text-muted-foreground opacity-50" />
                  <p className="text-lg font-semibold text-foreground mb-2">Nenhum lead encontrado</p>
                  <p className="text-muted-foreground">Tente ajustar seus filtros de busca</p>
                </motion.div>
              ) : (
                sortedLeads.map((lead, index) => (
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
                      onChat={() => handleOpenChat(lead.id, lead.name)}
                      onAI={() => handleCallAI(lead.id, lead.name)}
                      onCall={() => handleCall(lead.phone, lead.name)}
                      onDelete={() => handleDelete(lead.id, lead.name)}
                    />
                  </motion.div>
                ))
              )}
            </motion.div>
          )}
        </motion.div>
      </div>
    </div>
  );
}
