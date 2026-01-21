import { motion } from 'framer-motion';
import { Phone, MessageSquare, Mail, MoreVertical, Zap } from 'lucide-react';

interface LeadCardProps {
  name: string;
  phone: string;
  email?: string;
  status: 'novo' | 'contato' | 'interesse' | 'negociacao' | 'fechado';
  value?: number;
  lastContact?: string;
  delay?: number;
}

const statusConfig = {
  novo: {
    bg: 'from-blue-500/20 to-blue-600/20',
    border: 'border-blue-500/30',
    label: 'Novo',
    color: 'text-blue-400',
  },
  contato: {
    bg: 'from-cyan-500/20 to-cyan-600/20',
    border: 'border-cyan-500/30',
    label: 'Contato',
    color: 'text-cyan-400',
  },
  interesse: {
    bg: 'from-purple-500/20 to-purple-600/20',
    border: 'border-purple-500/30',
    label: 'Interesse',
    color: 'text-purple-400',
  },
  negociacao: {
    bg: 'from-orange-500/20 to-orange-600/20',
    border: 'border-orange-500/30',
    label: 'Negociação',
    color: 'text-orange-400',
  },
  fechado: {
    bg: 'from-green-500/20 to-green-600/20',
    border: 'border-green-500/30',
    label: 'Fechado',
    color: 'text-green-400',
  },
};

const LeadCard = ({
  name,
  phone,
  email,
  status,
  value,
  lastContact,
  delay = 0,
}: LeadCardProps) => {
  const config = statusConfig[status];

  return (
    <motion.div
      initial={{ opacity: 0, scale: 0.9 }}
      animate={{ opacity: 1, scale: 1 }}
      transition={{ delay, type: 'spring', stiffness: 200 }}
      whileHover={{ y: -4 }}
      className={`glass-panel p-4 rounded-xl border ${config.border} group cursor-pointer relative overflow-hidden`}
    >
      <div className={`absolute inset-0 bg-gradient-to-br ${config.bg} opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none`} />

      <div className="relative z-10">
        <div className="flex items-start justify-between mb-3">
          <div className="flex-1">
            <h3 className="font-bold text-lg text-foreground mb-1">{name}</h3>
            <div className={`inline-block px-2 py-1 rounded-lg text-xs font-semibold ${config.color} bg-white/5`}>
              {config.label}
            </div>
          </div>

          <motion.button
            whileHover={{ scale: 1.1 }}
            whileTap={{ scale: 0.95 }}
            className="p-2 hover:bg-white/10 rounded-lg transition-colors opacity-0 group-hover:opacity-100"
          >
            <MoreVertical size={16} className="text-muted-foreground" />
          </motion.button>
        </div>

        <div className="space-y-2 mb-4">
          <div className="flex items-center gap-2 text-sm text-muted-foreground">
            <Phone size={14} />
            <span>{phone}</span>
          </div>
          {email && (
            <div className="flex items-center gap-2 text-sm text-muted-foreground">
              <Mail size={14} />
              <span className="truncate">{email}</span>
            </div>
          )}
        </div>

        {value && (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            transition={{ delay: delay + 0.2 }}
            className="mb-4 p-3 bg-gradient-to-r from-green-500/20 to-emerald-500/20 border border-green-500/30 rounded-lg"
          >
            <p className="text-xs text-muted-foreground mb-1">Valor de Investimento</p>
            <p className="text-lg font-bold text-green-400">
              R$ {value.toLocaleString('pt-BR')}
            </p>
          </motion.div>
        )}

        {lastContact && (
          <p className="text-xs text-muted-foreground mb-3">
            Último contato: {lastContact}
          </p>
        )}

        <div className="flex gap-2">
          <motion.button
            whileHover={{ scale: 1.05 }}
            whileTap={{ scale: 0.95 }}
            className="flex-1 flex items-center justify-center gap-2 px-3 py-2 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 rounded-lg text-sm font-semibold text-white transition-all glow-sm hover:glow-md"
          >
            <MessageSquare size={14} />
            Chat
          </motion.button>

          <motion.button
            whileHover={{ scale: 1.05 }}
            whileTap={{ scale: 0.95 }}
            className="flex-1 flex items-center justify-center gap-2 px-3 py-2 bg-white/10 hover:bg-white/20 rounded-lg text-sm font-semibold text-white transition-all"
          >
            <Zap size={14} />
            IA
          </motion.button>

          <motion.button
            whileHover={{ scale: 1.05 }}
            whileTap={{ scale: 0.95 }}
            className="flex-1 flex items-center justify-center gap-2 px-3 py-2 bg-white/10 hover:bg-white/20 rounded-lg text-sm font-semibold text-white transition-all"
          >
            <Phone size={14} />
            Ligar
          </motion.button>
        </div>
      </div>
    </motion.div>
  );
};

export default LeadCard;
