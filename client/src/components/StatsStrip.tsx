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

interface StatsStripProps {
  stats: DashboardStats | null | undefined;
  loading: boolean;
}

export default function StatsStrip({ stats, loading }: StatsStripProps) {
  if (loading) {
    return (
      <div className="flex gap-2 overflow-x-auto pb-2 scrollbar-hide">
        {[...Array(8)].map((_, i) => (
          <div key={i} className="flex items-center gap-2 px-3 py-2 rounded-full bg-white/5 border border-white/10 animate-pulse min-w-[120px]">
            <div className="w-4 h-4 rounded-full bg-white/10" />
            <div className="w-8 h-4 rounded bg-white/10" />
            <div className="w-12 h-3 rounded bg-white/5" />
          </div>
        ))}
      </div>
    );
  }

  const badges = [
    { label: 'Leads', value: stats?.leads.total ?? 0, icon: <Users size={14} />, color: 'text-blue-700 dark:text-blue-400' },
    { label: 'Conversas', value: stats?.conversas.ativas ?? 0, icon: <MessageSquare size={14} />, color: 'text-green-700 dark:text-green-400' },
    { label: 'Imoveis', value: stats?.imoveis.ativos ?? 0, icon: <Home size={14} />, color: 'text-purple-700 dark:text-purple-400' },
    { label: 'Aguardando', value: stats?.conversas.aguardando ?? 0, icon: <Clock size={14} />, color: 'text-cyan-700 dark:text-cyan-400' },
    { label: 'Vistorias', value: stats?.vistorias.solicitacoes_pendentes ?? 0, icon: <ClipboardCheck size={14} />, color: 'text-orange-700 dark:text-orange-400' },
    { label: 'Pessoas', value: stats?.pessoas.total ?? 0, icon: <UserRound size={14} />, color: 'text-pink-700 dark:text-pink-400' },
    { label: 'Contestacoes', value: stats?.contestacoes.apontadas ?? 0, icon: <Activity size={14} />, color: 'text-amber-700 dark:text-yellow-400' },
    { label: 'Assinaturas', value: stats?.assinaturas.pendentes ?? 0, icon: <FileSignature size={14} />, color: 'text-indigo-700 dark:text-indigo-400' },
  ];

  return (
    <div className="flex gap-2 overflow-x-auto pb-2 scrollbar-hide">
      {badges.map((badge, index) => (
        <motion.div
          key={badge.label}
          initial={{ opacity: 0, scale: 0.9 }}
          animate={{ opacity: 1, scale: 1 }}
          transition={{ delay: index * 0.05 }}
          className="flex items-center gap-1.5 px-3 py-2 rounded-full bg-white/5 border border-white/10 hover:bg-white/10 transition-colors cursor-default whitespace-nowrap"
        >
          <span className={badge.color}>{badge.icon}</span>
          <span className="text-sm font-bold text-foreground">{badge.value}</span>
          <span className="text-xs text-muted-foreground">{badge.label}</span>
        </motion.div>
      ))}
    </div>
  );
}
