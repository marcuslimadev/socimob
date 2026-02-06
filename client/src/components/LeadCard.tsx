import { motion } from 'framer-motion';
import { useState } from 'react';
import { Phone, MessageSquare, Mail, Zap, Trash2, MessageCircle } from 'lucide-react';

interface LeadCardProps {
  id?: number;
  name: string;
  phone: string;
  email?: string;
  status: 'novo' | 'em_atendimento' | 'qualificado' | 'proposta' | 'fechado' | 'perdido' | 'contato' | 'interesse' | 'negociacao';
  value?: number;
  lastContact?: string;
  delay?: number;
  onChat?: () => void;
  onAI?: () => void;
  onSMS?: () => void;
  smsDisabled?: boolean;
  onWhatsAppWeb?: () => void;
  onDelete?: () => void;
  onClick?: () => void;
}

const statusConfig = {
  novo: {
    bg: 'from-blue-500/20 to-blue-600/20',
    border: 'border-blue-500/30',
    label: 'Novo',
    color: 'text-blue-400',
  },
  em_atendimento: {
    bg: 'from-cyan-500/20 to-cyan-600/20',
    border: 'border-cyan-500/30',
    label: 'Em Atendimento',
    color: 'text-cyan-400',
  },
  qualificado: {
    bg: 'from-purple-500/20 to-purple-600/20',
    border: 'border-purple-500/30',
    label: 'Qualificado',
    color: 'text-purple-400',
  },
  proposta: {
    bg: 'from-orange-500/20 to-orange-600/20',
    border: 'border-orange-500/30',
    label: 'Proposta',
    color: 'text-orange-400',
  },
  fechado: {
    bg: 'from-green-500/20 to-green-600/20',
    border: 'border-green-500/30',
    label: 'Fechado',
    color: 'text-green-400',
  },
  perdido: {
    bg: 'from-red-500/20 to-red-600/20',
    border: 'border-red-500/30',
    label: 'Perdido',
    color: 'text-red-400',
  },
  // Fallbacks for compatibility
  contato: {
    bg: 'from-cyan-500/20 to-cyan-600/20',
    border: 'border-cyan-500/30',
    label: 'Em Atendimento',
    color: 'text-cyan-400',
  },
  interesse: {
    bg: 'from-purple-500/20 to-purple-600/20',
    border: 'border-purple-500/30',
    label: 'Qualificado',
    color: 'text-purple-400',
  },
  negociacao: {
    bg: 'from-orange-500/20 to-orange-600/20',
    border: 'border-orange-500/30',
    label: 'Proposta',
    color: 'text-orange-400',
  },
};

const LeadCard = ({
  id,
  name,
  phone,
  email,
  status,
  value,
  lastContact,
  delay = 0,
  onChat,
  onAI,
  onSMS,
  smsDisabled = false,
  onWhatsAppWeb,
  onDelete,
  onClick,
}: LeadCardProps) => {
  const config = statusConfig[status];
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);

  return (
    <motion.div
      initial={{ opacity: 0, scale: 0.9 }}
      animate={{ opacity: 1, scale: 1 }}
      transition={{ delay, type: 'spring', stiffness: 200 }}
      whileHover={{ y: -4 }}
      className={`glass-panel p-4 rounded-xl border ${config.border} group cursor-pointer relative overflow-hidden`}
      onClick={onClick}
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

          {onDelete && (
            <motion.button
              whileHover={{ scale: 1.05 }}
              whileTap={{ scale: 0.95 }}
              onClick={(e) => {
                e.stopPropagation();
                setShowDeleteConfirm(true);
              }}
              className="flex items-center gap-2 px-3 py-2 rounded-lg bg-white/10 hover:bg-red-500/20 text-red-400 hover:text-red-300 transition-colors"
              title="Excluir"
              aria-label="Excluir"
            >
              <Trash2 size={14} />
            </motion.button>
          )}
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
            onClick={(e) => {
              e.stopPropagation();
              onChat?.();
            }}
            title="Chat"
            aria-label="Chat"
            className="flex-1 flex items-center justify-center px-3 py-2 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 rounded-lg text-sm font-semibold text-white transition-all glow-sm hover:glow-md"
          >
            <MessageSquare size={14} />
          </motion.button>

          <motion.button
            whileHover={{ scale: 1.05 }}
            whileTap={{ scale: 0.95 }}
            onClick={(e) => {
              e.stopPropagation();
              if (!smsDisabled) {
                onSMS?.();
              }
            }}
            disabled={smsDisabled}
            title={smsDisabled ? 'SMS enviado' : 'SMS'}
            aria-label={smsDisabled ? 'SMS enviado' : 'SMS'}
            className={`flex-1 flex items-center justify-center px-3 py-2 rounded-lg text-sm font-semibold text-white transition-all ${
              smsDisabled
                ? 'bg-white/5 text-white/50 cursor-not-allowed'
                : 'bg-white/10 hover:bg-white/20'
            }`}
          >
            <Mail size={14} />
          </motion.button>

          <motion.button
            whileHover={{ scale: 1.05 }}
            whileTap={{ scale: 0.95 }}
            onClick={(e) => {
              e.stopPropagation();
              onWhatsAppWeb?.();
            }}
            title="WhatsApp Web"
            aria-label="WhatsApp Web"
            className="flex-1 flex items-center justify-center px-3 py-2 bg-green-500/90 hover:bg-green-500 rounded-lg text-sm font-semibold text-white transition-all"
          >
            <MessageCircle size={14} />
          </motion.button>
        </div>
      </div>

      {showDeleteConfirm && (
        <div
          className="absolute inset-0 z-30 flex items-center justify-center bg-black/60 backdrop-blur-sm"
          onClick={(e) => {
            e.stopPropagation();
            setShowDeleteConfirm(false);
          }}
        >
          <div
            className="w-full max-w-xs rounded-xl border border-white/10 bg-[#0B1018]/95 p-4 shadow-2xl"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="mb-3 flex items-center gap-2 text-red-400">
              <Trash2 size={16} />
              <span className="text-sm font-semibold">Confirmar exclusão</span>
            </div>
            <div className="mb-4 text-sm text-muted-foreground">
              <div className="font-semibold text-foreground">{name}</div>
              <div>{phone}</div>
              {email && <div className="truncate">{email}</div>}
              <div className="mt-2 text-xs">
                Status: <span className={config.color}>{config.label}</span>
              </div>
            </div>
            <div className="flex gap-2">
              <button
                className="flex-1 rounded-lg bg-white/10 px-3 py-2 text-sm text-white hover:bg-white/20"
                onClick={() => setShowDeleteConfirm(false)}
              >
                Cancelar
              </button>
              <button
                className="flex-1 rounded-lg bg-red-500/90 px-3 py-2 text-sm text-white hover:bg-red-500"
                onClick={() => {
                  setShowDeleteConfirm(false);
                  onDelete?.();
                }}
              >
                Excluir
              </button>
            </div>
          </div>
        </div>
      )}
    </motion.div>
  );
};

export default LeadCard;
