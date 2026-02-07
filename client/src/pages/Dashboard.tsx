// Dashboard Principal - SOCIMOB v2 - Deploy Automatico - Mobile First
import { useState, useEffect } from 'react';
import { motion } from 'framer-motion';
import { BarChart3, Users, TrendingUp, MessageSquare, Zap, Activity, ClipboardCheck, FileSignature, UserRound, Home } from 'lucide-react';
import { toast } from 'sonner';
import { api } from '@/lib/api';
import MetricCard from '@/components/MetricCard';
import LeadCard from '@/components/LeadCard';
import KanbanBoard from '@/components/KanbanBoard';
import PageLayout from '@/components/PageLayout';
import QuickAccessCard from '@/components/QuickAccessCard';

interface DashboardStats {
  leads: {
    total: number;
    novos: number;
    em_atendimento: number;
    qualificados: number;
    fechados_mes: number;
  };
  conversas: {
    ativas: number;
    hoje: number;
    aguardando: number;
  };
  imoveis: {
    total: number;
    ativos: number;
  };
  vistorias: {
    total: number;
    solicitacoes_pendentes: number;
    em_andamento: number;
  };
  pessoas: {
    total: number;
    fisicas: number;
    juridicas: number;
  };
  contestacoes: {
    total: number;
    apontadas: number;
  };
  assinaturas: {
    total: number;
    pendentes: number;
    assinados: number;
  };
}

interface RecentActivity {
  tipo: string;
  descricao: string;
  timestamp: string;
  data: any;
}

export default function Dashboard() {
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [recentLeads, setRecentLeads] = useState<any[]>([]);
  const [activities, setActivities] = useState<RecentActivity[]>([]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    fetchDashboardData();
  }, []);

  const fetchDashboardData = async () => {
    try {
      const [statsRes, leadsRes, activitiesRes] = await Promise.all([
        api.get('/dashboard/stats'),
        api.get('/leads'), // Getting all leads and slicing the top 3
        api.get('/dashboard/atividades')
      ]);

      if (statsRes.data.success) {
        setStats(statsRes.data.data);
      }

      if (leadsRes.data.success) {
        setRecentLeads(leadsRes.data.data.slice(0, 3));
      }

      if (activitiesRes.data.success) {
        setActivities(activitiesRes.data.data);
      }
    } catch (error) {
      console.error('Error fetching dashboard data:', error);
      toast.error('Erro ao carregar dados do dashboard');
    } finally {
      setIsLoading(false);
    }
  };

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: {
        staggerChildren: 0.1,
        delayChildren: 0.2,
      },
    },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: { opacity: 1, y: 0 },
  };

  const formatDate = (dateString: string) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR', { hour: '2-digit', minute: '2-digit' });
  };

  const quickAccessItems = [
    {
      title: 'Vistorias',
      description: 'Solicitações, agendas e laudos',
      href: '/vistorias',
      icon: <ClipboardCheck size={20} />,
      badge: 'Ativo',
      gradient: 'from-amber-500 to-orange-600',
    },
    {
      title: 'Assinaturas',
      description: 'Documentos e status de assinatura',
      href: '/assinaturas',
      icon: <FileSignature size={20} />,
      badge: 'Ativo',
      gradient: 'from-indigo-500 to-sky-600',
    },
    {
      title: 'Pessoas',
      description: 'Clientes, proprietários e contatos',
      href: '/pessoas',
      icon: <UserRound size={20} />,
      badge: 'Ativo',
      gradient: 'from-rose-500 to-pink-600',
    },
    {
      title: 'Imóveis',
      description: 'Cadastro e portfólio imobiliário',
      href: '/properties',
      icon: <Home size={20} />,
      badge: 'Ativo',
      gradient: 'from-blue-500 to-cyan-600',
    },
  ];

  return (
    <PageLayout>
      <motion.div
        variants={containerVariants}
        initial="hidden"
        animate="visible"
        className="max-w-7xl mx-auto"
      >
        <motion.div variants={itemVariants} className="mb-6 md:mb-8">
          <h1 className="text-2xl sm:text-3xl md:text-4xl lg:text-5xl font-bold gradient-text mb-1 sm:mb-2">
            Bem-vindo de volta! 👋
          </h1>
          <p className="text-muted-foreground text-sm sm:text-base md:text-lg">
            Aqui está um resumo do seu negócio imobiliário
          </p>
        </motion.div>

          <motion.div
            variants={itemVariants}
            className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8"
          >
            <MetricCard
              title="Total de Leads"
              value={stats?.leads.total.toString() || "0"}
              unit="leads"
              trend={0}
              icon={<Users size={24} />}
              gradient="from-blue-500 to-blue-600"
              delay={0}
            />
            <MetricCard
              title="Conversas Ativas"
              value={stats?.conversas.ativas.toString() || "0"}
              trend={0}
              icon={<TrendingUp size={24} />}
              gradient="from-green-500 to-emerald-600"
              delay={0.1}
            />
            <MetricCard
              title="Imóveis Ativos"
              value={stats?.imoveis.ativos.toString() || "0"}
              unit="propriedades"
              trend={0}
              icon={<BarChart3 size={24} />}
              gradient="from-purple-500 to-pink-600"
              delay={0.2}
            />
            <MetricCard
              title="Aguardando"
              value={stats?.conversas.aguardando.toString() || "0"}
              unit="conversas"
              trend={0}
              icon={<MessageSquare size={24} />}
              gradient="from-cyan-500 to-blue-600"
              delay={0.3}
            />
          </motion.div>

          <motion.div
            variants={itemVariants}
            className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 md:gap-6 mb-6 md:mb-8"
          >
            <MetricCard
              title="Vistorias Pendentes"
              value={stats?.vistorias.solicitacoes_pendentes.toString() || "0"}
              unit="solicitações"
              trend={0}
              icon={<ClipboardCheck size={24} />}
              gradient="from-orange-500 to-red-600"
              delay={0.4}
            />
            <MetricCard
              title="Total de Pessoas"
              value={stats?.pessoas.total.toString() || "0"}
              unit="cadastros"
              trend={0}
              icon={<UserRound size={24} />}
              gradient="from-pink-500 to-rose-600"
              delay={0.5}
            />
            <MetricCard
              title="Contestações Ativas"
              value={stats?.contestacoes.apontadas.toString() || "0"}
              unit="apontadas"
              trend={0}
              icon={<Activity size={24} />}
              gradient="from-yellow-500 to-orange-600"
              delay={0.6}
            />
            <MetricCard
              title="Assinaturas Pendentes"
              value={stats?.assinaturas.pendentes.toString() || "0"}
              unit="documentos"
              trend={0}
              icon={<FileSignature size={24} />}
              gradient="from-indigo-500 to-purple-600"
              delay={0.7}
            />
          </motion.div>

          <motion.div variants={itemVariants} className="mb-6 md:mb-8">
            <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between mb-4 md:mb-6 gap-2">
              <h2 className="text-xl sm:text-2xl font-bold text-foreground">Acessos rápidos</h2>
              <span className="text-xs sm:text-sm text-muted-foreground">Novos módulos em construção</span>
            </div>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 md:gap-6">
              {quickAccessItems.map((item, index) => (
                <QuickAccessCard key={item.title} {...item} delay={0.2 + index * 0.1} />
              ))}
            </div>
          </motion.div>

          <motion.div variants={itemVariants} className="grid grid-cols-1 lg:grid-cols-3 gap-4 md:gap-6 mb-6 md:mb-8">
            <div className="lg:col-span-2 glass-panel p-4 sm:p-5 md:p-6 rounded-2xl">
              <h2 className="text-lg sm:text-xl font-bold text-foreground mb-4 md:mb-6 flex items-center gap-2">
                <Zap size={20} className="text-yellow-400 sm:w-6 sm:h-6" />
                Funil de Vendas
              </h2>
              <div className="space-y-3 sm:space-y-4">
                {[
                  { label: 'Leads', value: stats?.leads.total || 0, percent: 100, color: 'from-blue-500 to-blue-600' },
                  { label: 'Novos', value: stats?.leads.novos || 0, percent: stats ? Math.round((stats.leads.novos / (stats.leads.total || 1)) * 100) : 0, color: 'from-cyan-500 to-cyan-600' },
                  { label: 'Em Atendimento', value: stats?.leads.em_atendimento || 0, percent: stats ? Math.round((stats.leads.em_atendimento / (stats.leads.total || 1)) * 100) : 0, color: 'from-purple-500 to-purple-600' },
                  { label: 'Qualificados', value: stats?.leads.qualificados || 0, percent: stats ? Math.round((stats.leads.qualificados / (stats.leads.total || 1)) * 100) : 0, color: 'from-orange-500 to-orange-600' },
                  { label: 'Fechados (Mês)', value: stats?.leads.fechados_mes || 0, percent: stats ? Math.round((stats.leads.fechados_mes / (stats.leads.total || 1)) * 100) : 0, color: 'from-green-500 to-green-600' },
                ].map((stage, index) => (
                  <motion.div
                    key={stage.label}
                    initial={{ opacity: 0, x: -20 }}
                    animate={{ opacity: 1, x: 0 }}
                    transition={{ delay: 0.4 + index * 0.1 }}
                  >
                    <div className="flex items-center justify-between mb-2">
                      <span className="font-medium text-foreground">{stage.label}</span>
                      <span className="text-sm text-muted-foreground">{stage.value} ({stage.percent}%)</span>
                    </div>
                    <div className="h-3 rounded-full bg-white/10 overflow-hidden">
                      <motion.div
                        initial={{ width: 0 }}
                        animate={{ width: `${stage.percent}%` }}
                        transition={{ delay: 0.5 + index * 0.1, duration: 1 }}
                        className={`h-full bg-gradient-to-r ${stage.color} rounded-full`}
                      />
                    </div>
                  </motion.div>
                ))}
              </div>
            </div>

            <motion.div
              initial={{ opacity: 0, y: 20 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.4 }}
              className="glass-panel p-4 sm:p-5 md:p-6 rounded-2xl"
            >
              <h2 className="text-lg sm:text-xl font-bold text-foreground mb-4 md:mb-6 flex items-center gap-2">
                <Activity size={20} className="text-pink-400 sm:w-6 sm:h-6" />
                Atividades Recentes
              </h2>
              <div className="space-y-3 sm:space-y-4">
                {activities.length > 0 ? (
                  activities.map((activity, index) => (
                    <motion.div
                      key={index}
                      initial={{ opacity: 0 }}
                      animate={{ opacity: 1 }}
                      transition={{ delay: 0.5 + index * 0.1 }}
                      className="p-2.5 sm:p-3 bg-white/5 rounded-lg border border-white/10 hover:bg-white/10 transition-all"
                    >
                      <p className="font-semibold text-foreground text-xs sm:text-sm">{activity.descricao}</p>
                      <p className="text-[10px] sm:text-xs text-muted-foreground mt-1">{formatDate(activity.timestamp)}</p>
                    </motion.div>
                  ))
                ) : (
                  <p className="text-muted-foreground text-sm">Nenhuma atividade recente</p>
                )}
              </div>
            </motion.div>
          </motion.div>

          <motion.div variants={itemVariants} className="mb-6 md:mb-8">
            <h2 className="text-xl sm:text-2xl font-bold text-foreground mb-4 md:mb-6">Leads Recentes</h2>
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 sm:gap-4">
              {recentLeads.map((lead, index) => (
                <LeadCard
                  key={lead.id}
                  name={lead.nome || 'Sem nome'}
                  phone={lead.telefone || ''}
                  email={lead.email}
                  status={lead.status}
                  value={parseFloat(lead.budget_max || lead.budget_min || '0')}
                  lastContact={formatDate(lead.updated_at)}
                  delay={0.5 + index * 0.1}
                />
              ))}
            </div>
          </motion.div>

          <motion.div variants={itemVariants}>
            <h2 className="text-xl sm:text-2xl font-bold text-foreground mb-4 md:mb-6">Funil de Leads</h2>
            <KanbanBoard />
          </motion.div>
        </motion.div>
    </PageLayout>
  );
}
