import { useState, FormEvent } from 'react';
import { motion } from 'framer-motion';
import { Search, Filter, Plus, Download, Zap } from 'lucide-react';
import { toast } from 'sonner';
import { useLocation } from 'wouter';
import { api } from '@/lib/api';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import Sidebar from '@/components/Sidebar';
import LeadCard from '@/components/LeadCard';
import { SkeletonLoader } from '@/components/SkeletonLoader';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';

interface Lead {
  id: string;
  name: string;
  phone: string;
  email: string;
  status: 'novo' | 'em_atendimento' | 'qualificado' | 'proposta' | 'fechado' | 'perdido';
  value?: number;
  lastContact?: string;
  sms_enviado?: boolean;
}

export default function Leads() {
  const [searchTerm, setSearchTerm] = useState('');
  const [selectedStatus, setSelectedStatus] = useState('todos');
  const [selectedSort, setSelectedSort] = useState('recente');
  const [showNewLeadModal, setShowNewLeadModal] = useState(false);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [smsSentLeads, setSmsSentLeads] = useState<Record<string, boolean>>({});
  const [smsSendingLeadId, setSmsSendingLeadId] = useState<string | null>(null);
  const [newLead, setNewLead] = useState({
    nome: '',
    telefone: '',
    email: '',
    status: 'novo',
    budget_min: '',
    budget_max: '',
    localizacao: '',
    observacoes: '',
  });
  const [_, setLocation] = useLocation();
  const queryClient = useQueryClient();

  // Fetch leads com React Query
  const { data: leads = [], isLoading } = useQuery({
    queryKey: ['leads'],
    queryFn: async () => {
      const response = await api.get('/leads');
      if (response.data.success) {
        return response.data.data
          .filter((item: any) => item && item.id != null)
          .map((item: any) => ({
            id: item.id.toString(),
            name: item.nome || 'Sem nome',
            phone: item.telefone || '',
            email: item.email || '',
            status: item.status || 'novo',
            value: parseFloat(item.budget_max || item.budget_min || '0'),
            lastContact: formatDate(item.updated_at),
            sms_enviado: Boolean(item.sms_enviado)
          }));
      }
      return [];
    },
    refetchInterval: 30000, // Refetch a cada 30 segundos
    staleTime: 15000, // 15 segundos
  });

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
    setShowNewLeadModal(true);
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
        queryClient.invalidateQueries({ queryKey: ['leads'] });
      }
    } catch (error) {
      console.error('Error starting AI:', error);
      toast.error('Erro ao iniciar atendimento IA');
    }
  };

  const handleSendSMS = async (leadId: string, leadName: string) => {
    // Check if SMS was already sent (from backend or local state)
    const lead = leads.find((l: any) => l.id === leadId);
    if (lead?.sms_enviado || smsSentLeads[leadId] || smsSendingLeadId === leadId) {
      return;
    }

    toast.info(`Enviando SMS para ${leadName}`);
    setSmsSendingLeadId(leadId);
    try {
      const response = await api.post(`/admin/leads/${leadId}/sms`);
      if (response.data.success || response.status === 200) {
        toast.success(`SMS enviado para ${leadName}`);
        setSmsSentLeads((prev) => ({
          ...prev,
          [leadId]: true,
        }));
        queryClient.invalidateQueries({ queryKey: ['leads'] });
      }
    } catch (error) {
      console.error('Error sending SMS:', error);
      toast.error('Erro ao enviar SMS');
    } finally {
      setSmsSendingLeadId((current) => (current === leadId ? null : current));
    }
  };

  const handleWhatsAppWeb = (phone: string, leadName: string) => {
    const digits = (phone || '').replace(/\D/g, '');
    if (!digits) {
      toast.error('Telefone não disponível');
      return;
    }

    const formatted = digits.startsWith('55') ? digits : `55${digits}`;
    const message = encodeURIComponent(`Olá ${leadName}, tudo bem?`);
    const url = `https://web.whatsapp.com/send?phone=${formatted}&text=${message}`;
    window.open(url, '_blank', 'noopener,noreferrer');
    toast.success(`Abrindo WhatsApp Web para ${leadName}`);
  };

  const handleDeleteClick = async (leadId: string, leadName: string) => {
    try {
      const response = await api.delete(`/leads/${leadId}`);

      if (response.data.success) {
        toast.success(`Lead ${leadName} excluído com sucesso`);
        queryClient.invalidateQueries({ queryKey: ['leads'] });
      }
    } catch (error) {
      toast.error('Erro ao excluir lead');
    }
  };

  const handleStatusChange = async (leadId: string, newStatus: string) => {
    try {
      await api.put(`/leads/${leadId}`, { status: newStatus });
      toast.success('Status atualizado com sucesso');
      queryClient.invalidateQueries({ queryKey: ['leads'] });
    } catch (error) {
      console.error('Erro ao atualizar status:', error);
      toast.error('Erro ao atualizar status');
    }
  };

  const resetNewLeadForm = () => {
    setNewLead({
      nome: '',
      telefone: '',
      email: '',
      status: 'novo',
      budget_min: '',
      budget_max: '',
      localizacao: '',
      observacoes: '',
    });
  };

  const parseCurrencyValue = (value: string) => {
    if (!value) return null;
    const normalized = value.replace(/[^\d,.-]/g, '').replace(/\./g, '').replace(',', '.');
    const parsed = Number.parseFloat(normalized);
    return Number.isNaN(parsed) ? null : parsed;
  };

  const handleCreateLead = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (!newLead.nome.trim() || !newLead.telefone.trim()) {
      toast.error('Nome e telefone são obrigatórios');
      return;
    }

    const budgetMin = parseCurrencyValue(newLead.budget_min);
    const budgetMax = parseCurrencyValue(newLead.budget_max);

    if (budgetMin !== null && budgetMax !== null && budgetMax < budgetMin) {
      toast.error('O orçamento máximo não pode ser menor que o mínimo');
      return;
    }

    const payload: Record<string, string | number | null> = {
      nome: newLead.nome.trim(),
      telefone: newLead.telefone.trim(),
      email: newLead.email.trim() || null,
      status: newLead.status,
      localizacao: newLead.localizacao.trim() || null,
      observacoes: newLead.observacoes.trim() || null,
    };

    if (budgetMin !== null) {
      payload.budget_min = budgetMin;
    }
    if (budgetMax !== null) {
      payload.budget_max = budgetMax;
    }

    setIsSubmitting(true);

    try {
      const response = await api.post('/leads', payload);
      if (response.data.success) {
        toast.success('Lead criado com sucesso');
        setShowNewLeadModal(false);
        resetNewLeadForm();
        queryClient.invalidateQueries({ queryKey: ['leads'] });
      } else {
        toast.error(response.data.error || 'Erro ao criar lead');
      }
    } catch (error: any) {
      console.error('Error creating lead:', error);
      toast.error(error?.response?.data?.error || 'Erro ao criar lead');
    } finally {
      setIsSubmitting(false);
    }
  };

  const filteredLeads = leads.filter((lead: any) => {
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
                className="px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all [&>option]:text-gray-900 [&>option]:bg-white dark:[&>option]:text-gray-100 dark:[&>option]:bg-gray-800"
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
                className="px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all [&>option]:text-gray-900 [&>option]:bg-white dark:[&>option]:text-gray-100 dark:[&>option]:bg-gray-800"
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
              { label: 'Novo', value: leads.filter((l: any) => l.status === 'novo').length, color: 'from-blue-500/50 to-blue-600/50' },
              { label: 'Em Atend.', value: leads.filter((l: any) => l.status === 'em_atendimento').length, color: 'from-cyan-500 to-cyan-600' },
              { label: 'Qualificado', value: leads.filter((l: any) => l.status === 'qualificado').length, color: 'from-purple-500 to-purple-600' },
              { label: 'Fechado', value: leads.filter((l: any) => l.status === 'fechado').length, color: 'from-green-500 to-green-600' },
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
                      id={parseInt(lead.id)}
                      name={lead.name}
                      phone={lead.phone}
                      email={lead.email}
                      status={lead.status}
                      value={lead.value}
                      lastContact={lead.lastContact}
                      onClick={async () => {
                        try {
                          // Buscar pessoa pelo telefone
                          const phoneDigits = lead.phone ? lead.phone.replace(/\D/g, '') : '';
                          if (phoneDigits) {
                            const response = await api.get('/pessoas', { params: { search: phoneDigits, per_page: 1 } });
                            const pessoas = response.data.data || [];
                            if (pessoas.length > 0) {
                              // Redirecionar para o perfil da pessoa encontrada
                              setLocation(`/pessoas/${pessoas[0].id}`);
                            } else {
                              toast.error('Pessoa não encontrada para este lead');
                            }
                          } else {
                            toast.error('Lead sem telefone cadastrado');
                          }
                        } catch (error) {
                          console.error('Erro ao buscar pessoa:', error);
                          toast.error('Erro ao buscar pessoa');
                        }
                      }}
                      onChat={() => handleOpenChat(lead.id, lead.name)}
                      onAI={() => handleCallAI(lead.id, lead.name)}
                      onSMS={() => handleSendSMS(lead.id, lead.name)}
                      smsDisabled={lead.sms_enviado || Boolean(smsSentLeads[lead.id]) || smsSendingLeadId === lead.id}
                      onWhatsAppWeb={() => handleWhatsAppWeb(lead.phone, lead.name)}
                      onDelete={() => handleDeleteClick(lead.id, lead.name)}
                      onStatusChange={(newStatus) => handleStatusChange(lead.id, newStatus)}
                    />
                  </motion.div>
                ))
              )}
            </motion.div>
          )}
        </motion.div>
      </div>

      <Dialog
        open={showNewLeadModal}
        onOpenChange={(open) => {
          setShowNewLeadModal(open);
          if (!open) {
            resetNewLeadForm();
          }
        }}
      >
        <DialogContent className="bg-[#0f0f0f] border border-white/10 text-foreground max-w-lg">
          <DialogHeader>
            <DialogTitle className="text-xl">Novo Lead</DialogTitle>
            <DialogDescription>
              Cadastre um novo lead para iniciar o atendimento.
            </DialogDescription>
          </DialogHeader>

          <form onSubmit={handleCreateLead} className="space-y-4">
            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="lead-nome">Nome *</Label>
                <Input
                  id="lead-nome"
                  value={newLead.nome}
                  onChange={(e) => setNewLead((prev) => ({ ...prev, nome: e.target.value }))}
                  placeholder="Nome do lead"
                  required
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="lead-telefone">Telefone *</Label>
                <Input
                  id="lead-telefone"
                  value={newLead.telefone}
                  onChange={(e) => setNewLead((prev) => ({ ...prev, telefone: e.target.value }))}
                  placeholder="(00) 00000-0000"
                  required
                />
              </div>
            </div>

            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="lead-email">E-mail</Label>
                <Input
                  id="lead-email"
                  type="email"
                  value={newLead.email}
                  onChange={(e) => setNewLead((prev) => ({ ...prev, email: e.target.value }))}
                  placeholder="email@exemplo.com"
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="lead-status">Status</Label>
                <select
                  id="lead-status"
                  value={newLead.status}
                  onChange={(e) => setNewLead((prev) => ({ ...prev, status: e.target.value }))}
                  className="w-full px-3 py-2 rounded-md border border-white/10 bg-transparent text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 [&>option]:text-gray-900 [&>option]:bg-white dark:[&>option]:text-gray-100 dark:[&>option]:bg-gray-800"
                >
                  <option value="novo">Novo</option>
                  <option value="em_atendimento">Em Atendimento</option>
                  <option value="qualificado">Qualificado</option>
                  <option value="proposta">Proposta</option>
                  <option value="fechado">Fechado</option>
                  <option value="perdido">Perdido</option>
                </select>
              </div>
            </div>

            <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
              <div className="space-y-2">
                <Label htmlFor="lead-budget-min">Orçamento mínimo</Label>
                <Input
                  id="lead-budget-min"
                  value={newLead.budget_min}
                  onChange={(e) => setNewLead((prev) => ({ ...prev, budget_min: e.target.value }))}
                  placeholder="R$ 300.000"
                />
              </div>
              <div className="space-y-2">
                <Label htmlFor="lead-budget-max">Orçamento máximo</Label>
                <Input
                  id="lead-budget-max"
                  value={newLead.budget_max}
                  onChange={(e) => setNewLead((prev) => ({ ...prev, budget_max: e.target.value }))}
                  placeholder="R$ 500.000"
                />
              </div>
            </div>

            <div className="space-y-2">
              <Label htmlFor="lead-localizacao">Localização</Label>
              <Input
                id="lead-localizacao"
                value={newLead.localizacao}
                onChange={(e) => setNewLead((prev) => ({ ...prev, localizacao: e.target.value }))}
                placeholder="Cidade ou bairro"
              />
            </div>

            <div className="space-y-2">
              <Label htmlFor="lead-observacoes">Observações</Label>
              <Textarea
                id="lead-observacoes"
                value={newLead.observacoes}
                onChange={(e) => setNewLead((prev) => ({ ...prev, observacoes: e.target.value }))}
                placeholder="Observações sobre o lead"
                rows={3}
              />
            </div>

            <DialogFooter className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
              <button
                type="button"
                onClick={() => setShowNewLeadModal(false)}
                className="px-4 py-2 rounded-md border border-white/20 text-foreground hover:bg-white/10 transition"
              >
                Cancelar
              </button>
              <button
                type="submit"
                disabled={isSubmitting}
                className="px-4 py-2 rounded-md bg-blue-600 text-white hover:bg-blue-700 transition disabled:opacity-60"
              >
                {isSubmitting ? 'Salvando...' : 'Salvar lead'}
              </button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>

    </div>
  );
}
