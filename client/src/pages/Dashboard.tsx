// Dashboard Principal - SOCIMOB v2 - Timeline Real do Sistema
import { motion } from 'framer-motion';
import { api } from '@/lib/api';
import { useQuery } from '@tanstack/react-query';
import PageLayout from '@/components/PageLayout';
import StatsGrid from '@/components/StatsGrid';
import TimelineFeed from '@/components/TimelineFeed';

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

export default function Dashboard() {
  const { data: stats, isLoading: statsLoading } = useQuery({
    queryKey: ['dashboard', 'stats'],
    queryFn: async () => {
      const res = await api.get('/dashboard/stats');
      if (res.data.success) {
        return res.data.data as DashboardStats;
      }
      return null;
    },
    staleTime: 2 * 60 * 1000,
  });

  const user = JSON.parse(localStorage.getItem('user') || '{}');
  const isAdmin = user?.role === 'admin' || user?.role === 'super_admin';

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: {
        staggerChildren: 0.1,
        delayChildren: 0.1,
      },
    },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: { opacity: 1, y: 0 },
  };

  return (
    <PageLayout>
      <motion.div
        variants={containerVariants}
        initial="hidden"
        animate="visible"
        className="max-w-5xl mx-auto"
      >
        {/* Header */}
        <motion.div variants={itemVariants} className="mb-4">
          <h1 className="text-xl sm:text-2xl md:text-3xl font-bold text-foreground mb-1">
            {isAdmin ? 'Painel Administrativo' : 'Meu Painel'}
          </h1>
          <p className="text-muted-foreground text-xs sm:text-sm">
            {isAdmin ? 'Atividade completa da equipe e do sistema' : 'Suas atividades e interacoes'}
          </p>
        </motion.div>

        {/* Stats Grid - mobile optimized */}
        <motion.div variants={itemVariants} className="mb-6">
          <StatsGrid stats={stats} loading={statsLoading} />
        </motion.div>

        {/* Timeline Feed - main body */}
        <motion.div variants={itemVariants}>
          <div className="glass-panel p-4 sm:p-5 md:p-6 rounded-2xl">
            <h2 className="text-lg sm:text-xl font-bold text-foreground mb-4 flex items-center gap-2">
              <span className="w-2 h-2 rounded-full bg-green-400 animate-pulse" />
              Timeline
            </h2>
            <TimelineFeed />
          </div>
        </motion.div>
      </motion.div>
    </PageLayout>
  );
}
