import { motion } from 'framer-motion';
import { useLocation } from 'wouter';
import {
  UserPlus, MessageCircle, MessageSquare, Phone, Shield, Zap, Globe, Mail,
  MapPin, Calendar, FileText, FileSignature, ClipboardCheck, Activity,
  CircleDot, Headphones, Brain, Plug, type LucideIcon,
} from 'lucide-react';

export interface TimelineItemData {
  id: string;
  type: string;
  category: string;
  title: string;
  description: string;
  actor: { id: number | null; name: string | null };
  timestamp: string;
  icon: string;
  color: string;
  link: string | null;
  metadata: Record<string, any>;
}

const iconMap: Record<string, LucideIcon> = {
  'user-plus': UserPlus,
  'message-circle': MessageCircle,
  'message-square': MessageSquare,
  'brain': Brain,
  'phone': Phone,
  'shield': Shield,
  'zap': Zap,
  'plug': Plug,
  'headphones': Headphones,
  'map-pin': MapPin,
  'mail': Mail,
  'calendar': Calendar,
  'file-text': FileText,
  'file-signature': FileSignature,
  'clipboard-check': ClipboardCheck,
  'activity': Activity,
  'circle-dot': CircleDot,
  'globe': Globe,
  'circle': CircleDot,
};

const colorMap: Record<string, { bg: string; border: string; text: string }> = {
  blue:    { bg: 'bg-blue-500/20',    border: 'border-blue-500/40',    text: 'text-blue-400' },
  green:   { bg: 'bg-green-500/20',   border: 'border-green-500/40',   text: 'text-green-400' },
  violet:  { bg: 'bg-violet-500/20',  border: 'border-violet-500/40',  text: 'text-violet-400' },
  cyan:    { bg: 'bg-cyan-500/20',    border: 'border-cyan-500/40',    text: 'text-cyan-400' },
  gray:    { bg: 'bg-gray-500/20',    border: 'border-gray-500/40',    text: 'text-gray-400' },
  red:     { bg: 'bg-red-500/20',     border: 'border-red-500/40',     text: 'text-red-400' },
  yellow:  { bg: 'bg-yellow-500/20',  border: 'border-yellow-500/40',  text: 'text-yellow-400' },
  teal:    { bg: 'bg-teal-500/20',    border: 'border-teal-500/40',    text: 'text-teal-400' },
  emerald: { bg: 'bg-emerald-500/20', border: 'border-emerald-500/40', text: 'text-emerald-400' },
  sky:     { bg: 'bg-sky-500/20',     border: 'border-sky-500/40',     text: 'text-sky-400' },
  purple:  { bg: 'bg-purple-500/20',  border: 'border-purple-500/40',  text: 'text-purple-400' },
  amber:   { bg: 'bg-amber-500/20',   border: 'border-amber-500/40',   text: 'text-amber-400' },
  indigo:  { bg: 'bg-indigo-500/20',  border: 'border-indigo-500/40',  text: 'text-indigo-400' },
  orange:  { bg: 'bg-orange-500/20',  border: 'border-orange-500/40',  text: 'text-orange-400' },
};

const categoryLabels: Record<string, string> = {
  lead: 'Lead',
  conversa: 'Conversa',
  interacao: 'Interacao',
  whatsapp: 'WhatsApp',
  vistoria: 'Vistoria',
  assinatura: 'Assinatura',
  auth: 'Auth',
  ia: 'IA',
  automation: 'Automacao',
  webhook: 'Webhook',
  integration: 'Integracao',
  twilio: 'Twilio',
  sistema: 'Sistema',
};

function formatRelativeTime(timestamp: string): string {
  if (!timestamp) return '';
  const now = new Date();
  const date = new Date(timestamp);
  const diffMs = now.getTime() - date.getTime();
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMs / 3600000);
  const diffDays = Math.floor(diffMs / 86400000);

  if (diffMins < 1) return 'agora';
  if (diffMins < 60) return `${diffMins}min`;
  if (diffHours < 24) return `${diffHours}h`;
  if (diffDays < 7) return `${diffDays}d`;
  return date.toLocaleDateString('pt-BR', { day: '2-digit', month: 'short' });
}

interface Props {
  item: TimelineItemData;
  index: number;
  isLast: boolean;
}

export default function TimelineItem({ item, index, isLast }: Props) {
  const [, setLocation] = useLocation();
  const IconComponent = iconMap[item.icon] || Activity;
  const colors = colorMap[item.color] || colorMap.gray;
  const catLabel = categoryLabels[item.category] || item.category;

  const handleClick = () => {
    if (item.link) {
      setLocation(item.link);
    }
  };

  return (
    <motion.div
      initial={{ opacity: 0, x: -10 }}
      animate={{ opacity: 1, x: 0 }}
      transition={{ delay: Math.min(index * 0.03, 0.5) }}
      className="relative flex gap-2.5 sm:gap-4"
    >
      {/* Vertical line */}
      {!isLast && (
        <div
          className="absolute left-[15px] sm:left-[19px] top-[34px] sm:top-[36px] bottom-0 w-[2px]"
          style={{ background: 'rgba(255,255,255,0.08)' }}
        />
      )}

      {/* Icon node */}
      <div className={`relative z-10 flex-shrink-0 w-[32px] h-[32px] sm:w-[40px] sm:h-[40px] rounded-full ${colors.bg} border ${colors.border} flex items-center justify-center`}>
        <IconComponent size={14} className={`${colors.text} sm:!w-4 sm:!h-4`} />
      </div>

      {/* Content card */}
      <div
        onClick={handleClick}
        className={`flex-1 min-w-0 p-2.5 sm:p-4 rounded-xl bg-white/5 border border-white/10 hover:bg-white/[0.08] transition-all mb-2.5 sm:mb-3 ${item.link ? 'cursor-pointer' : 'cursor-default'}`}
      >
        {/* Header row */}
        <div className="flex items-start justify-between gap-2 mb-0.5 sm:mb-1">
          <h4 className="text-xs sm:text-sm font-semibold text-foreground leading-tight line-clamp-2">{item.title}</h4>
          <span className="text-[10px] sm:text-xs text-muted-foreground whitespace-nowrap flex-shrink-0 mt-0.5">
            {formatRelativeTime(item.timestamp)}
          </span>
        </div>

        {/* Description */}
        <p className="text-[11px] sm:text-sm text-muted-foreground leading-relaxed mb-1.5 sm:mb-2 line-clamp-2">
          {item.description}
        </p>

        {/* Footer badges */}
        <div className="flex items-center gap-1.5 sm:gap-2 flex-wrap">
          {item.actor?.name && (
            <span className="text-[9px] sm:text-[10px] px-1.5 sm:px-2 py-0.5 rounded-full bg-white/10 text-muted-foreground truncate max-w-[120px] sm:max-w-none">
              {item.actor.name}
            </span>
          )}
          <span className={`text-[9px] sm:text-[10px] px-1.5 sm:px-2 py-0.5 rounded-full ${colors.bg} ${colors.text}`}>
            {catLabel}
          </span>
        </div>
      </div>
    </motion.div>
  );
}
