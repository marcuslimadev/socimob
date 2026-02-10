import { motion, useMotionValue, useTransform } from 'framer-motion';
import { useState, useRef } from 'react';
import { Phone, MessageSquare, Mail, Trash2, MessageCircle, ChevronDown, X } from 'lucide-react';

interface LeadCardMobileProps {
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
  onStatusChange?: (newStatus: string) => void;
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

const LeadCardMobile = ({
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
  onStatusChange,
}: LeadCardMobileProps) => {
  const config = statusConfig[status];
  const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
  const [showStatusDropdown, setShowStatusDropdown] = useState(false);
  const [showActions, setShowActions] = useState(false);
  const dropdownRef = useRef<HTMLDivElement>(null);
  const x = useMotionValue(0);
  const opacity = useTransform(x, [-100, 0, 100], [0.5, 1, 0.5]);

  const handleStatusChange = (newStatus: string) => {
    setShowStatusDropdown(false);
    onStatusChange?.(newStatus);
  };

  const statusOptions = [
    { value: 'novo', label: 'Novo' },
    { value: 'em_atendimento', label: 'Em Atendimento' },
    { value: 'qualificado', label: 'Qualificado' },
    { value: 'proposta', label: 'Proposta' },
    { value: 'fechado', label: 'Fechado' },
    { value: 'perdido', label: 'Perdido' },
  ];

  return (
    <motion.div
      initial={{ opacity: 0, y: 20 }}
      animate={{ opacity: 1, y: 0 }}
      transition={{ delay, type: 'spring', stiffness: 200 }}
      className="relative"
    >
      {/* Background Actions */}
      {showActions && (
        <motion.div
          initial={{ opacity: 0 }}
          animate={{ opacity: 1 }}
          className="absolute inset-0 bg-gradient-to-r from-green-500/20 to-blue-500/20 rounded-xl pointer-events-none"
        />
      )}

      <motion.div
        drag="x"
        dragElastic={0.2}
        dragConstraints={{ left: -80, right: 80 }}
        x={x}
        style={{ opacity }}
        onDragEnd={(event, info) => {
          if (info.offset.x < -50) {
            setShowActions(true);
          } else if (info.offset.x > 50) {
            setShowActions(false);
          }
        }}
        className={`glass-panel p-4 rounded-xl border ${config.border} group cursor-pointer relative overflow-hidden`}
        onClick={onClick}
      >
        <div className={`absolute inset-0 bg-gradient-to-br ${config.bg} opacity-0 group-hover:opacity-100 transition-opacity pointer-events-none`} />

        <div className="relative z-10">
          {/* Header */}
          <div className="flex items-start justify-between mb-3">
            <div className="flex-1 min-w-0">
              <h3 className="font-bold text-base sm:text-lg text-foreground mb-1 truncate">{name}</h3>
              <div className="relative inline-block" ref={dropdownRef}>
                <button
                  onClick={(e) => {
                    e.stopPropagation();
                    setShowStatusDropdown(!showStatusDropdown);
                  }}
                  className={`inline-flex items-center gap-1 px-2 py-1 rounded-lg text-xs font-semibold ${config.color} bg-white/5 hover:bg-white/10 transition-colors`}
                >
                  {config.label}
                  <ChevronDown size={12} />
                </button>
                {showStatusDropdown && onStatusChange && (
                  <motion.div
                    initial={{ opacity: 0, y: -10 }}
                    animate={{ opacity: 1, y: 0 }}
                    className="absolute z-50 left-0 mt-1 w-48 bg-gray-900 dark:bg-gray-800 border border-gray-700 rounded-lg shadow-xl overflow-hidden"
                  >
                    {statusOptions.map((option) => (
                      <button
                        key={option.value}
                        onClick={(e) => {
                          e.stopPropagation();
                          handleStatusChange(option.value);
                        }}
                        className={`w-full text-left px-3 py-2 text-sm hover:bg-gray-800 dark:hover:bg-gray-700 transition-colors ${
                          option.value === status ? 'bg-gray-800/50 dark:bg-gray-700/50 font-semibold' : ''
                        }`}
                      >
                        {option.label}
                      </button>
                    ))}
                  </motion.div>
                )}
              </div>
            </div>

            {onDelete && (
              <motion.button
                whileTap={{ scale: 0.95 }}
                onClick={(e) => {
                  e.stopPropagation();
                  setShowDeleteConfirm(true);
                }}
                className="flex items-center gap-2 p-2 rounded-lg bg-white/10 hover:bg-red-500/20 text-red-400 hover:text-red-300 transition-colors ml-2"
                title="Excluir"
                aria-label="Excluir"
              >
                <Trash2 size={16} />
              </motion.button>
            )}
          </div>

          {/* Contact Info */}
          <div className="space-y-1.5 mb-3 text-sm">
            <div className="flex items-center gap-2 text-muted-foreground">
              <Phone size={14} className="flex-shrink-0" />
              <span className="truncate">{phone}</span>
            </div>
            {email && (
              <div className="flex items-center gap-2 text-muted-foreground">
                <Mail size={14} className="flex-shrink-0" />
                <span className="truncate text-xs">{email}</span>
              </div>
            )}
          </div>

          {/* Value */}
          {value && (
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              transition={{ delay: delay + 0.2 }}
              className="mb-3 p-2 bg-gradient-to-r from-green-500/20 to-emerald-500/20 border border-green-500/30 rounded-lg"
            >
              <p className="text-xs text-muted-foreground mb-0.5">Investimento</p>
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

          {/* Action Buttons - Responsive */}
          <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
            <motion.button
              whileTap={{ scale: 0.95 }}
              onClick={(e) => {
                e.stopPropagation();
                onChat?.();
              }}
              title="Chat"
              aria-label="Chat"
              className="flex items-center justify-center px-2 py-2 bg-blue-600 hover:bg-blue-700 rounded-lg text-xs font-semibold text-white transition-all"
            >
              <MessageSquare size={14} />
            </motion.button>

            <motion.button
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
              className={`flex items-center justify-center px-2 py-2 rounded-lg text-xs font-semibold text-white transition-all ${
                smsDisabled
                  ? 'bg-white/5 text-white/50 cursor-not-allowed'
                  : 'bg-white/10 hover:bg-white/20'
              }`}
            >
              <Mail size={14} />
            </motion.button>

            <motion.button
              whileTap={{ scale: 0.95 }}
              onClick={(e) => {
                e.stopPropagation();
                onWhatsAppWeb?.();
              }}
              title="WhatsApp"
              aria-label="WhatsApp"
              className="flex items-center justify-center px-2 py-2 bg-green-500/90 hover:bg-green-500 rounded-lg text-xs font-semibold text-white transition-all"
            >
              <MessageCircle size={14} />
            </motion.button>

            {onAI && (
              <motion.button
                whileTap={{ scale: 0.95 }}
                onClick={(e) => {
                  e.stopPropagation();
                  onAI?.();
                }}
                title="IA"
                aria-label="IA"
                className="flex items-center justify-center px-2 py-2 bg-purple-500/90 hover:bg-purple-500 rounded-lg text-xs font-semibold text-white transition-all"
              >
                ⚡
              </motion.button>
            )}
          </div>
        </div>
      </motion.div>

      {/* Delete Confirmation Modal */}
      {showDeleteConfirm && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4"
          onClick={(e) => {
            e.stopPropagation();
            setShowDeleteConfirm(false);
          }}
        >
          <motion.div
            initial={{ opacity: 0, scale: 0.9 }}
            animate={{ opacity: 1, scale: 1 }}
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
              {email && <div className="truncate text-xs">{email}</div>}
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
          </motion.div>
        </div>
      )}
    </motion.div>
  );
};

export default LeadCardMobile;
