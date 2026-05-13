import { motion } from 'framer-motion';
import { Users, MessageSquare, Home, Clock, ClipboardCheck, UserRound, Activity, FileSignature } from 'lucide-react';

interface DashboardStats {
  leads: { total: number; novos: number; em_atendimento: number; qualificados: number; fechados_mes: number };
  conversas: { ativas: number; hoje: number; aguardando: number };
  imoveis: { total: number; ativos: number };
  vistorias: { total: number; solicitacoes_pendentes: number; em_andamento: number };
  pessoas: { total: number; fisicas: number; juridicas: number };
  contestacoes: { total: number; apontadas: number };
  assinaturas: { total: number; pendentes: number; assinados: number };
}

interface StatsGridProps {
  stats: DashboardStats | null | undefined;
  loading: boolean;
}

export default function StatsGrid({ stats, loading }: StatsGridProps) {
  const statCards = [
    {
      label: 'Leads',
      value: stats?.leads.total ?? 0,
      icon: <Users size={20} />,
      gradient: 'from-blue-500/20 to-blue-600/20',
      border: 'border-blue-500/30',
      color: 'text-blue-700 dark:text-blue-400',
      bgColor: 'bg-blue-500/15 dark:bg-blue-500/10',
    },
    {
      label: 'Conversas',
      value: stats?.conversas.ativas ?? 0,
      icon: <MessageSquare size={20} />,
      gradient: 'from-green-500/20 to-green-600/20',
      border: 'border-green-500/30',
      color: 'text-green-700 dark:text-green-400',
      bgColor: 'bg-green-500/15 dark:bg-green-500/10',
    },
    {
      label: 'Imóveis',
      value: stats?.imoveis.ativos ?? 0,
      icon: <Home size={20} />,
      gradient: 'from-purple-500/20 to-purple-600/20',
      border: 'border-purple-500/30',
      color: 'text-purple-700 dark:text-purple-400',
      bgColor: 'bg-purple-500/15 dark:bg-purple-500/10',
    },
    {
      label: 'Aguardando',
      value: stats?.conversas.aguardando ?? 0,
      icon: <Clock size={20} />,
      gradient: 'from-cyan-500/20 to-cyan-600/20',
      border: 'border-cyan-500/30',
      color: 'text-cyan-700 dark:text-cyan-400',
      bgColor: 'bg-cyan-500/15 dark:bg-cyan-500/10',
    },
    {
      label: 'Vistorias',
      value: stats?.vistorias.solicitacoes_pendentes ?? 0,
      icon: <ClipboardCheck size={20} />,
      gradient: 'from-orange-500/20 to-orange-600/20',
      border: 'border-orange-500/30',
      color: 'text-orange-700 dark:text-orange-400',
      bgColor: 'bg-orange-500/15 dark:bg-orange-500/10',
    },
    {
      label: 'Pessoas',
      value: stats?.pessoas.total ?? 0,
      icon: <UserRound size={20} />,
      gradient: 'from-pink-500/20 to-pink-600/20',
      border: 'border-pink-500/30',
      color: 'text-pink-700 dark:text-pink-400',
      bgColor: 'bg-pink-500/15 dark:bg-pink-500/10',
    },
  ];

  if (loading) {
    return (
      <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
        {[...Array(6)].map((_, i) => (
          <div key={i} className="system-panel h-24 rounded-lg animate-pulse" />
        ))}
      </div>
    );
  }

  return (
    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
      {statCards.map((card, index) => (
        <motion.div
          key={card.label}
          initial={{ opacity: 0, scale: 0.9 }}
          animate={{ opacity: 1, scale: 1 }}
          transition={{ delay: index * 0.05 }}
          className={`system-panel p-4 rounded-xl border ${card.border} group cursor-default overflow-hidden`}
        >
          <div className={`absolute inset-0 bg-gradient-to-br ${card.gradient} opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none`} />

          <div className="relative z-10">
            <div className={`w-10 h-10 rounded-lg ${card.bgColor} flex items-center justify-center mb-2`}>
              <span className={card.color}>{card.icon}</span>
            </div>
            <p className="text-xs text-muted-foreground font-medium mb-1">{card.label}</p>
            <p className="text-2xl font-bold text-foreground">{card.value}</p>
          </div>
        </motion.div>
      ))}
    </div>
  );
}
