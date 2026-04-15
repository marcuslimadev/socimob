import { useState, useEffect, useRef, useMemo, useCallback, useLayoutEffect, memo } from 'react';
import {
  Search,
  Send,
  ArrowLeft,
  ArrowUp,
  ArrowDown,
  ArrowUpDown,
  Check,
  CheckCheck,
  Clock,
  Loader2,
  User,
  MessageCircle,
  ChevronDown,
  ChevronUp,
  FileText,
  ExternalLink,
  Download,
  Image,
  Info,
  Users,
  RefreshCw,
  Maximize2,
  Minimize2,
  Key,
  Eye,
  Filter,
  Trash2,
} from 'lucide-react';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';
import { toast } from 'sonner';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { useQuery, useQueryClient } from '@tanstack/react-query';

// ─── Types ───────────────────────────────────────────────────────────

interface CRMClient {
  id: number;
  pessoa_id: number | null;
  nome: string;
  telefone: string;
  email: string | null;
  status: string;
  classificacao: string | null;
  observacoes?: string | null;
  observacoes_cliente?: string | null;
  valor: number | null;
  corretor_id: number | null;
  corretor_nome: string | null;
  pessoa: any | null;
  conversa_id: number | null;
  ultima_mensagem: string | null;
  ultima_mensagem_at: string | null;
  unread: number;
  origem: string | null;
  sms_enviado: boolean;
  property_id?: number | null;
  property_title?: string | null;
  property_code?: string | null;
  property_location?: string | null;
  property_value?: number | null;
  property_link?: string | null;
  updated_at: string | null;
  created_at: string | null;
}

interface Message {
  id: string;
  sender: 'user' | 'contact';
  text: string;
  timestamp: string;
  rawDate: Date;
  read: boolean;
  status?: 'sending' | 'sent' | 'delivered' | 'read';
  messageType?: string;
  mediaUrl?: string | null;
  transcription?: string | null;
  senderName?: string;
  senderContext?: string;
  senderKind?: 'assistant' | 'human' | 'lead';
}

interface ClientDocument {
  id: number;
  nome: string;
  tipo?: string;
  mime_type: string;
  arquivo_url: string;
  status: string;
  created_at: string;
}

interface CRMTableResponse {
  data: CRMClient[];
  total: number;
  current_page: number;
  last_page: number;
  per_page: number;
}

interface LoggedUser {
  id: number | null;
  role: string | null;
  name: string | null;
}

type StatusKey = 'novo' | 'em_atendimento' | 'qualificado' | 'proposta' | 'fechado' | 'perdido';
type ClassificationFilter = 'all' | 'quente' | 'morno' | 'frio';

const STATUS_CONFIG: Record<StatusKey, { label: string; color: string; bg: string }> = {
  novo: { label: 'Novo', color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-500/10 border-blue-500/30' },
  em_atendimento: { label: 'Atendimento', color: 'text-amber-600 dark:text-amber-400', bg: 'bg-amber-500/10 border-amber-500/30' },
  qualificado: { label: 'Qualificado', color: 'text-purple-600 dark:text-purple-400', bg: 'bg-purple-500/10 border-purple-500/30' },
  proposta: { label: 'Proposta', color: 'text-cyan-600 dark:text-cyan-400', bg: 'bg-cyan-500/10 border-cyan-500/30' },
  fechado: { label: 'Fechado', color: 'text-green-600 dark:text-green-400', bg: 'bg-green-500/10 border-green-500/30' },
  perdido: { label: 'Perdido', color: 'text-red-600 dark:text-red-400', bg: 'bg-red-500/10 border-red-500/30' },
};

const ALL_STATUSES: StatusKey[] = ['novo', 'em_atendimento', 'qualificado', 'proposta', 'fechado', 'perdido'];
const ORIGIN_SORT_ORDER: Record<string, number> = {
  Site: 0,
  'Chaves na Mão': 1,
  WhatsApp: 2,
  SMS: 3,
};

const createEmptyCRMData = (): Record<StatusKey, CRMClient[]> => ({
  novo: [],
  em_atendimento: [],
  qualificado: [],
  proposta: [],
  fechado: [],
  perdido: [],
});

function normalizeStatusKey(status: unknown): StatusKey {
  const normalized = String(status ?? '').trim().toLowerCase();

  if (normalized === 'em_atendimento') return 'em_atendimento';
  if (normalized === 'qualificado') return 'qualificado';
  if (normalized === 'proposta' || normalized === 'negociacao') return 'proposta';
  if (normalized === 'fechado' || normalized === 'convertido') return 'fechado';
  if (normalized === 'perdido' || normalized === 'descartado') return 'perdido';

  return 'novo';
}

function normalizeCRMClient(raw: Partial<CRMClient> & { pessoa?: any; whatsapp_name?: string | null; whatsapp?: string | null }): CRMClient {
  const pessoa = raw.pessoa ?? null;

  return {
    id: Number(raw.id || 0),
    pessoa_id: raw.pessoa_id ?? null,
    nome: String(raw.nome || raw.whatsapp_name || pessoa?.nome || 'Lead sem nome').trim(),
    telefone: String(raw.telefone || raw.whatsapp || pessoa?.celular || pessoa?.telefone || '').trim(),
    email: raw.email || pessoa?.email || null,
    status: normalizeStatusKey(raw.status),
    classificacao: raw.classificacao ?? null,
    observacoes: raw.observacoes ?? null,
    observacoes_cliente: raw.observacoes_cliente ?? null,
    valor: raw.valor ?? null,
    corretor_id: raw.corretor_id ?? null,
    corretor_nome: raw.corretor_nome ?? null,
    pessoa,
    conversa_id: raw.conversa_id ?? null,
    ultima_mensagem: raw.ultima_mensagem ?? null,
    ultima_mensagem_at: raw.ultima_mensagem_at ?? null,
    unread: Number(raw.unread || 0),
    origem: normalizeOriginValue(raw.origem ?? pessoa?.origem ?? null),
    sms_enviado: Boolean(raw.sms_enviado),
    property_id: raw.property_id ?? null,
    property_title: raw.property_title ?? null,
    property_code: raw.property_code ?? null,
    property_location: raw.property_location ?? null,
    property_value: raw.property_value ?? null,
    property_link: raw.property_link ?? null,
    updated_at: raw.updated_at ?? null,
    created_at: raw.created_at ?? null,
  };
}

function normalizeOriginValue(value: unknown) {
  const rawValue = String(value ?? '').trim();
  if (!rawValue) return 'Site';

  const normalized = rawValue
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[_-]+/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();

  if (normalized === 'chaves na mao') return 'Chaves na Mão';
  if (
    ['site', 'form', 'formulario', 'portal', 'manual', 'crm', 'lead crm', 'outro'].includes(normalized) ||
    normalized.includes('form') ||
    normalized.includes('site') ||
    normalized.includes('portal') ||
    normalized.includes('crm')
  ) return 'Site';
  if (normalized === 'whatsapp') return 'WhatsApp';
  if (normalized === 'sms') return 'SMS';
  if (/^[A-Z0-9\s]+$/.test(rawValue)) return rawValue;

  return rawValue.replace(/[_-]+/g, ' ').trim();
}

function filterClients(
  clients: CRMClient[],
  filters: {
    term: string;
    statusFilter: StatusKey | 'all';
    classificacaoFilter: ClassificationFilter;
    corretorFilter: string;
    originFilter: string;
  },
) {
  const term = filters.term.trim().toLowerCase();

  let filtered = term
    ? clients.filter((client) => {
        const origin = normalizeOriginValue(client.origem).toLowerCase();
        return (
          client.nome?.toLowerCase().includes(term) ||
          client.telefone?.toLowerCase().includes(term) ||
          (client.email || '').toLowerCase().includes(term) ||
          (client.corretor_nome || '').toLowerCase().includes(term) ||
          origin.includes(term)
        );
      })
    : clients;

  if (filters.statusFilter !== 'all') {
    filtered = filtered.filter((client) => (client.status || 'novo') === filters.statusFilter);
  }

  if (filters.classificacaoFilter !== 'all') {
    filtered = filtered.filter((client) => client.classificacao === filters.classificacaoFilter);
  }

  if (filters.corretorFilter) {
    filtered = filtered.filter((client) => String(client.corretor_id || '') === String(filters.corretorFilter));
  }

  if (filters.originFilter !== 'all') {
    filtered = filtered.filter((client) => normalizeOriginValue(client.origem) === filters.originFilter);
  }

  return filtered;
}

function normalizeCRMData(raw: unknown): Record<StatusKey, CRMClient[]> {
  const normalized = createEmptyCRMData();

  if (!raw) return normalized;

  if (Array.isArray(raw)) {
    raw.forEach((item) => {
      if (!item) return;
      const client = normalizeCRMClient(item as CRMClient);
      normalized[client.status as StatusKey] = [...normalized[client.status as StatusKey], client];
    });
    return normalized;
  }

  if (typeof raw === 'object') {
    ALL_STATUSES.forEach((status) => {
      const bucket = (raw as Record<string, unknown>)[status];
      if (Array.isArray(bucket)) {
        normalized[status] = (bucket as CRMClient[]).map((item) => normalizeCRMClient(item));
      } else if (bucket && Array.isArray((bucket as { data?: unknown }).data)) {
        normalized[status] = (bucket as { data: CRMClient[] }).data.map((item) => normalizeCRMClient(item));
      }
    });
  }

  return normalized;
}

function normalizeFlatClients(raw: unknown): CRMClient[] {
  if (Array.isArray(raw)) return raw.map((item) => normalizeCRMClient(item as CRMClient));
  if (raw && typeof raw === 'object') {
    const values = Object.values(raw as Record<string, unknown>);
    const flattened: CRMClient[] = [];
    values.forEach((v) => {
      if (Array.isArray(v)) {
        flattened.push(...(v as CRMClient[]).map((item) => normalizeCRMClient(item)));
      } else if (v && Array.isArray((v as { data?: unknown }).data)) {
        flattened.push(...((v as { data: CRMClient[] }).data).map((item) => normalizeCRMClient(item)));
      }
    });
    return flattened;
  }
  return [];
}

// ─── Helpers ─────────────────────────────────────────────────────────

function getInitials(name: string) {
  if (!name) return '?';
  const parts = name.trim().split(' ');
  if (parts.length >= 2) return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
  return name.slice(0, 2).toUpperCase();
}

function formatRelativeTime(dateString: string | null) {
  if (!dateString) return '';
  const date = new Date(dateString);
  const now = new Date();
  const diffMs = now.getTime() - date.getTime();
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMs / 3600000);
  const diffDays = Math.floor(diffMs / 86400000);
  if (diffMins < 1) return 'Agora';
  if (diffMins < 60) return `${diffMins}min`;
  if (diffHours < 24) return `${diffHours}h`;
  if (diffDays < 7) return `${diffDays}d`;
  return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
}

function formatTime(dateString: string) {
  if (!dateString) return '';
  const date = new Date(dateString);
  return date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
}

function formatDateSeparator(date: Date) {
  const now = new Date();
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  const yesterday = new Date(today.getTime() - 86400000);
  const messageDate = new Date(date.getFullYear(), date.getMonth(), date.getDate());
  if (messageDate.getTime() === today.getTime()) return 'Hoje';
  if (messageDate.getTime() === yesterday.getTime()) return 'Ontem';
  return date.toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' });
}

function getMediaUrl(url: string) {
  if (!url) return '';
  if (url.includes('twilio.com')) return `/api/conversas/media/proxy?url=${encodeURIComponent(url)}`;
  if (/^https?:\/\//i.test(url)) return url;
  const base = typeof window !== 'undefined' ? window.location.origin : '';
  if (url.startsWith('/')) return `${base}${url}`;
  if (!url.startsWith('storage/')) return `${base}/storage/${url}`;
  return `${base}/${url}`;
}

function truncateMsg(text: string | null, max = 50) {
  if (!text) return '';
  const clean = text.replace(/<[^>]+>/g, '').replace(/\n/g, ' ').trim();
  return clean.length > max ? clean.slice(0, max) + '...' : clean;
}

function formatPhoneDisplay(phone: string | null | undefined) {
  if (!phone) return '-';

  const digits = phone.replace(/\D/g, '');
  if (!digits) return phone;

  let brDigits = digits;
  if ((digits.length === 12 || digits.length === 13) && digits.startsWith('55')) {
    brDigits = digits.slice(2);
  }

  if (brDigits.length === 11) {
    return `(${brDigits.slice(0, 2)}) ${brDigits.slice(2, 7)}-${brDigits.slice(7)}`;
  }

  if (brDigits.length === 10) {
    return `(${brDigits.slice(0, 2)}) ${brDigits.slice(2, 6)}-${brDigits.slice(6)}`;
  }

  return phone;
}

function normalizeWhatsAppPhone(phone: string | null | undefined) {
  if (!phone) return '';

  const digits = phone.replace(/\D/g, '');
  if (!digits) return '';
  if ((digits.length === 12 || digits.length === 13) && digits.startsWith('55')) return digits;
  if (digits.length === 10 || digits.length === 11) return `55${digits}`;

  return digits;
}

function buildWhatsAppUrl(phone: string | null | undefined, message?: string | null) {
  const normalizedPhone = normalizeWhatsAppPhone(phone);
  if (!normalizedPhone) return '';

  const encodedMessage = message?.trim() ? `?text=${encodeURIComponent(message.trim())}` : '';
  return `https://wa.me/${normalizedPhone}${encodedMessage}`;
}

function formatHtmlMessage(html: string) {
  return html.replace(/<br\s*\/?>/gi, '\n').replace(/<[^>]+>/g, '');
}

function decodeHtml(value: string) {
  if (typeof window === 'undefined') return value;
  const doc = new DOMParser().parseFromString(`<!doctype html><body>${value}`, 'text/html');
  return doc.body.textContent || '';
}

function normalizeObservacoes(value?: string | null) {
  if (!value) return '';
  const withBreaks = value.replace(/<\s*br\s*\/?>/gi, '\n');
  const withoutTags = withBreaks.replace(/<\/?[^>]+(>|$)/g, '');
  let text = decodeHtml(withoutTags).trim();
  // Remove repeated blocks (separated by "--- Atualização de Lead ---")
  const sepIdx = text.indexOf('--- Atualização de Lead ---');
  if (sepIdx > 0) text = text.slice(0, sepIdx).trim();
  return text;
}

function extractLeadInterest(client: CRMClient) {
  const normalizedSources = [
    normalizeObservacoes(client.observacoes_cliente),
    normalizeObservacoes(client.observacoes),
  ].filter(Boolean);

  let summary = '';
  let propertyLink = client.property_link || '';
  let propertyTitle = client.property_title || '';
  let propertyCode = client.property_code || '';
  let propertyLocation = client.property_location || '';
  let propertyValue = client.property_value ? `R$ ${Number(client.property_value).toLocaleString('pt-BR')}` : '';
  let captureSource = '';

  const summaryPatterns = [
    /tenho interesse no imóvel.+/i,
    /imóvel id\s*\d+:.+/i,
    /referência do imóvel:.+/i,
    /referência:.+/i,
    /solicitou avaliação de imóvel.+/i,
    /solicitou avaliacao de imovel.+/i,
    /solicitou cadastro de imóvel.+/i,
    /solicitou cadastro de imovel.+/i,
  ];

  const cleanFragment = (value: string) => value
    .replace(/^\[[^\]]+\]\s*/, '')
    .replace(/^[-:;,\s]+|[-:;,\s]+$/g, '')
    .replace(/\s+/g, ' ')
    .trim();

  for (const source of normalizedSources) {
    const compactSource = source.replace(/\s+/g, ' ').trim();

    if (!propertyLink) {
      const matchedLink = compactSource.match(/https?:\/\/[^\s]+\/portal\/imovel\/\d+/i);
      if (matchedLink) {
        propertyLink = matchedLink[0];
      }
    }

    if (!propertyTitle) {
      const quotedTitleMatch = compactSource.match(/tenho interesse no imóvel\s+["“]?([^"”.]+?)["”]?(?:\.|,| Localização:| Valor anunciado:| Link do imóvel:|$)/i);
      const idTitleMatch = compactSource.match(/imóvel id\s*(\d+)\s*:\s*([^\n]+?)(?:\.|$)/i);

      if (quotedTitleMatch?.[1]) {
        propertyTitle = cleanFragment(quotedTitleMatch[1]);
      } else if (idTitleMatch?.[2]) {
        propertyTitle = cleanFragment(idTitleMatch[2]);
        if (!propertyCode && idTitleMatch[1]) {
          propertyCode = `ID ${idTitleMatch[1]}`;
        }
      }
    }

    if (!propertyCode) {
      const codeMatch = compactSource.match(/refer[êe]ncia(?: do imóvel)?\s*:\s*([^\.\n]+)/i);
      if (codeMatch?.[1]) {
        propertyCode = cleanFragment(codeMatch[1]);
      }
    }

    if (!propertyLocation) {
      const locationMatch = compactSource.match(/localiza[cç][aã]o\s*:\s*([^\.]+?)(?:\.\s|\.$|\svalor\s*:|\svalor anunciado\s*:|\sorigem\s*:|\slink do imóvel\s*:|$)/i);
      if (locationMatch?.[1]) {
        propertyLocation = cleanFragment(locationMatch[1]);
      }
    }

    if (!propertyValue) {
      const valueMatch = compactSource.match(/valor(?: anunciado)?\s*:\s*([^\.]+?)(?:\.\s|\.$|\sorigem\s*:|\slink do imóvel\s*:|$)/i);
      if (valueMatch?.[1]) {
        propertyValue = cleanFragment(valueMatch[1]);
      }
    }

    if (!captureSource) {
      const sourceMatch = compactSource.match(/origem\s*:\s*([^\.]+?)(?:\.\s|\.$|\slink do imóvel\s*:|$)/i);
      if (sourceMatch?.[1]) {
        captureSource = cleanFragment(sourceMatch[1]);
      }
    }

    if (!summary) {
      const lines = source
        .split('\n')
        .map((line) => line.trim())
        .filter(Boolean)
        .map((line) => line.replace(/^\[[^\]]+\]\s*/, '').trim());

      for (const line of lines) {
        if (summaryPatterns.some((pattern) => pattern.test(line))) {
          summary = cleanFragment(line);
          break;
        }
      }
    }

    if (summary && propertyLink && propertyTitle && propertyCode) {
      break;
    }
  }

  const displayLabel = propertyTitle
    ? propertyCode && !propertyTitle.toLowerCase().includes(propertyCode.toLowerCase())
      ? `${propertyTitle} (${propertyCode})`
      : propertyTitle
    : propertyCode
      ? `Código ${propertyCode}`
      : summary;

  const compactSummary = [displayLabel, propertyLocation, propertyValue]
    .filter(Boolean)
    .join(' • ');

  return {
    summary,
    propertyLink,
    propertyTitle,
    propertyCode,
    propertyLocation,
    propertyValue,
    captureSource,
    displayLabel,
    compactSummary,
  };
}

function isAudioMessage(m: Message) {
  return !!(m.mediaUrl && (m.messageType === 'audio' || m.messageType === 'voice' || /\.(mp3|ogg|wav|m4a|opus)(\?|$)/i.test(m.mediaUrl)));
}
function isImageMessage(m: Message) {
  return !!(m.mediaUrl && (m.messageType === 'image' || m.messageType === 'photo' || /\.(png|jpe?g|gif|webp)(\?|$)/i.test(m.mediaUrl)));
}
function isVideoMessage(m: Message) {
  return !!(m.mediaUrl && m.messageType === 'video');
}
function isDocumentMessage(m: Message) {
  return !!(m.mediaUrl && (m.messageType === 'document' || m.messageType === 'file' || /\.(pdf|doc|docx|xls|xlsx)(\?|$)/i.test(m.mediaUrl)));
}

// ─── Debounce hook ───────────────────────────────────────────────────

function useDebounce<T>(value: T, delay: number): T {
  const [debounced, setDebounced] = useState(value);
  useEffect(() => {
    const t = setTimeout(() => setDebounced(value), delay);
    return () => clearTimeout(t);
  }, [value, delay]);
  return debounced;
}

// ─── Sub-components (outside main component to avoid re-creation) ────

const InfoField = memo(({ label, value }: { label: string; value: string | null | undefined }) => (
  <div>
    <p className="text-[11px] text-muted-foreground">{label}</p>
    <p className="text-sm text-foreground font-medium">{value || '-'}</p>
  </div>
));

const MessageStatusIcon = memo(({ status }: { status?: string }) => {
  if (status === 'sending') return <Clock className="w-3 h-3 text-muted-foreground" />;
  if (status === 'sent') return <Check className="w-3 h-3 text-muted-foreground" />;
  if (status === 'delivered') return <CheckCheck className="w-3 h-3 text-muted-foreground" />;
  if (status === 'read') return <CheckCheck className="w-3 h-3 text-primary" />;
  return null;
});

const WhatsAppShortcutButton = memo(({ phone, name, interestLabel, onClick }: {
  phone: string | null | undefined;
  name: string;
  interestLabel?: string;
  onClick?: (event: React.MouseEvent<HTMLButtonElement>) => void;
}) => {
  const whatsappUrl = buildWhatsAppUrl(
    phone,
    interestLabel ? `Olá, ${name}! Vi seu interesse em ${interestLabel}.` : `Olá, ${name}!`,
  );

  if (!whatsappUrl) return null;

  return (
    <Button
      type="button"
      variant="ghost"
      size="icon"
      className="h-7 w-7 text-emerald-400 hover:text-emerald-300 hover:bg-emerald-500/10"
      onClick={(event) => {
        event.stopPropagation();
        window.open(whatsappUrl, '_blank', 'noopener,noreferrer');
        onClick?.(event);
      }}
      title="Abrir WhatsApp"
    >
      <MessageCircle className="w-3.5 h-3.5" />
    </Button>
  );
});

const PropertyInterestActions = memo(({
  propertyLink,
  propertyLabel,
  onQuickView,
  compact = false,
}: {
  propertyLink?: string;
  propertyLabel?: string;
  onQuickView: () => void;
  compact?: boolean;
}) => {
  if (!propertyLink) return null;

  return (
    <div className={cn('flex items-center gap-2 flex-wrap', compact && 'gap-1.5')}>
      <a
        href={propertyLink}
        target="_blank"
        rel="noopener noreferrer"
        onClick={(event) => event.stopPropagation()}
        className={cn(
          'inline-flex items-center gap-1 text-xs text-primary hover:underline',
          compact && 'text-[11px]'
        )}
        title={propertyLabel ? `Abrir ${propertyLabel}` : 'Abrir imóvel'}
      >
        <ExternalLink className="w-3.5 h-3.5" />
        Ver imóvel
      </a>
      <button
        type="button"
        onClick={(event) => {
          event.stopPropagation();
          onQuickView();
        }}
        className={cn(
          'inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground transition-colors',
          compact && 'text-[11px]'
        )}
        title="Visualização rápida"
      >
        <Eye className="w-3.5 h-3.5" />
        Pré-visualizar
      </button>
    </div>
  );
});

const ClientCard = memo(({ client, isSelected, onSelect, onQuickViewProperty }: {
  client: CRMClient;
  isSelected: boolean;
  onSelect: (client: CRMClient) => void;
  onQuickViewProperty: (url: string, title: string) => void;
}) => {
  const classif = client.classificacao;
  const leadInterest = extractLeadInterest(client);
  return (
    <button
      onClick={() => onSelect(client)}
      className={cn(
        'w-full text-left p-3 rounded-xl border transition-all hover:shadow-md cursor-pointer',
        'bg-card border-border hover:border-primary/30',
        isSelected && 'ring-2 ring-primary/50 border-primary/50'
      )}
    >
      <div className="flex items-start gap-3">
        <Avatar className="w-10 h-10 flex-shrink-0">
          <AvatarFallback className="bg-primary/15 text-primary text-xs font-semibold">
            {getInitials(client.nome)}
          </AvatarFallback>
        </Avatar>
        <div className="flex-1 min-w-0">
          <div className="flex items-center justify-between gap-2">
            <h4 className="font-semibold text-sm text-foreground truncate">{client.nome}</h4>
            {client.unread > 0 && (
              <span className="flex-shrink-0 w-5 h-5 bg-primary rounded-full flex items-center justify-center text-[10px] font-bold text-primary-foreground">
                {client.unread > 9 ? '9+' : client.unread}
              </span>
            )}
          </div>
          <div className="flex items-center gap-1 mt-0.5">
            <p className="text-xs text-muted-foreground truncate">{formatPhoneDisplay(client.telefone)}</p>
            <WhatsAppShortcutButton
              phone={client.telefone}
              name={client.nome}
              interestLabel={leadInterest.displayLabel || leadInterest.summary}
            />
          </div>
          {client.ultima_mensagem && (
            <p className="text-xs text-muted-foreground/70 truncate mt-1">
              {truncateMsg(client.ultima_mensagem, 40)}
            </p>
          )}
          {leadInterest.compactSummary && (
            <p className="text-[11px] text-emerald-400/90 truncate mt-1">
              {leadInterest.compactSummary}
            </p>
          )}
          <div className="mt-1">
            <PropertyInterestActions
              propertyLink={leadInterest.propertyLink}
              propertyLabel={leadInterest.displayLabel}
              compact
              onQuickView={() => onQuickViewProperty(
                leadInterest.propertyLink || '',
                leadInterest.displayLabel || leadInterest.propertyTitle || 'Imóvel'
              )}
            />
          </div>
          <div className="flex items-center gap-2 mt-2">
            {classif && (
              <span className={cn(
                'text-[10px] font-semibold px-1.5 py-0.5 rounded-full',
                classif === 'quente' && 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
                classif === 'morno' && 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400',
                classif === 'frio' && 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
              )}>
                {classif === 'quente' ? 'Quente' : classif === 'morno' ? 'Morno' : 'Frio'}
              </span>
            )}
            {client.ultima_mensagem_at && (
              <span className="text-[10px] text-muted-foreground">{formatRelativeTime(client.ultima_mensagem_at)}</span>
            )}
            {client.corretor_nome && (
              <span className="text-[10px] text-muted-foreground truncate ml-auto">{client.corretor_nome}</span>
            )}
          </div>
        </div>
      </div>
    </button>
  );
});

const DataRow = memo(({ client, isSelected, onSelect, onDelete, isDeleting, onQuickViewProperty }: {
  client: CRMClient;
  isSelected: boolean;
  onSelect: (client: CRMClient) => void;
  onDelete: (client: CRMClient) => void;
  isDeleting: boolean;
  onQuickViewProperty: (url: string, title: string) => void;
}) => {
  const statusConf = STATUS_CONFIG[client.status as StatusKey] || STATUS_CONFIG.novo;
  const classif = client.classificacao;
  const leadInterest = extractLeadInterest(client);
  return (
    <tr
      onClick={() => onSelect(client)}
      className={cn(
        'cursor-pointer border-b border-border/40 transition-colors group',
        isSelected
          ? 'bg-primary/10 hover:bg-primary/15'
          : 'hover:bg-muted/40'
      )}
    >
      <td className="px-4 py-3">
        <div className="flex items-center gap-3">
          <div className="relative flex-shrink-0">
            <Avatar className="w-9 h-9">
              <AvatarFallback className={cn(
                'text-[11px] font-bold',
                isSelected ? 'bg-primary/25 text-primary' : 'bg-primary/10 text-primary'
              )}>
                {getInitials(client.nome)}
              </AvatarFallback>
            </Avatar>
            {client.unread > 0 && (
              <span className="absolute -top-1 -right-1 min-w-[16px] h-4 bg-primary rounded-full flex items-center justify-center text-[9px] font-bold text-primary-foreground px-1 ring-2 ring-background">
                {client.unread > 9 ? '9+' : client.unread}
              </span>
            )}
          </div>
          <div className="min-w-0">
            <div className="flex items-center gap-1.5">
              <p className="text-sm font-semibold text-foreground truncate">{client.nome}</p>
              {classif && (
                <span className={cn(
                  'flex-shrink-0 text-[10px] font-bold px-1.5 py-0.5 rounded-full',
                  classif === 'quente' && 'bg-red-500/15 text-red-500',
                  classif === 'morno' && 'bg-amber-500/15 text-amber-500',
                  classif === 'frio' && 'bg-blue-500/15 text-blue-500',
                )}>
                  {classif === 'quente' ? '🔥' : classif === 'morno' ? '🌡' : '❄️'}
                </span>
              )}
            </div>
            <p className="text-xs text-muted-foreground truncate mt-0.5">{client.email || <span className="opacity-40">—</span>}</p>
            {leadInterest.compactSummary && (
              <p className="text-[11px] text-emerald-400/90 truncate mt-1 max-w-[320px]">
                {leadInterest.compactSummary}
              </p>
            )}
            <div className="mt-1">
              <PropertyInterestActions
                propertyLink={leadInterest.propertyLink}
                propertyLabel={leadInterest.displayLabel}
                compact
                onQuickView={() => onQuickViewProperty(
                  leadInterest.propertyLink || '',
                  leadInterest.displayLabel || leadInterest.propertyTitle || 'Imóvel'
                )}
              />
            </div>
          </div>
        </div>
      </td>
      <td className="px-4 py-3 text-xs text-muted-foreground whitespace-nowrap">
        <div className="flex items-center gap-1.5">
          <span>{formatPhoneDisplay(client.telefone)}</span>
          <WhatsAppShortcutButton
            phone={client.telefone}
            name={client.nome}
            interestLabel={leadInterest.displayLabel || leadInterest.summary}
          />
        </div>
      </td>
      <td className="px-4 py-3">
        <span className={cn('text-[11px] font-semibold px-2.5 py-1 rounded-full border', statusConf.bg, statusConf.color)}>
          {statusConf.label}
        </span>
      </td>
      <td className="px-4 py-3 text-xs text-muted-foreground truncate max-w-[240px]">
        {client.ultima_mensagem ? truncateMsg(client.ultima_mensagem, 60) : <span className="text-muted-foreground/30">—</span>}
      </td>
      <td className="px-4 py-3 text-xs text-muted-foreground">{client.corretor_nome || <span className="text-muted-foreground/30">—</span>}</td>
      <td className="px-4 py-3 text-xs text-muted-foreground">{client.origem || <span className="text-muted-foreground/30">—</span>}</td>
      <td className="px-4 py-3 text-xs text-muted-foreground whitespace-nowrap">
        {client.updated_at ? formatRelativeTime(client.updated_at) : <span className="text-muted-foreground/30">—</span>}
      </td>
      <td className="px-4 py-3 text-right">
        <Button
          variant="ghost"
          size="icon"
          disabled={isDeleting}
          className="h-8 w-8 text-muted-foreground hover:text-red-500"
          onClick={(event) => {
            event.stopPropagation();
            onDelete(client);
          }}
          title="Excluir lead"
        >
          {isDeleting ? <Loader2 className="w-4 h-4 animate-spin" /> : <Trash2 className="w-4 h-4" />}
        </Button>
      </td>
    </tr>
  );
});

// ─── Main Component ──────────────────────────────────────────────────

export default function CRM() {
  const queryClient = useQueryClient();

  // State
  const [search, setSearch] = useState('');
  const debouncedSearch = useDebounce(search, 400);
  const [selectedClient, setSelectedClient] = useState<CRMClient | null>(null);
  const [mobileStatus, setMobileStatus] = useState<StatusKey>('novo');
  const [drawerDocked, setDrawerDocked] = useState(false);
  const [drawerCollapsed, setDrawerCollapsed] = useState(false);
  const [obsExpanded, setObsExpanded] = useState(true);
  const [tableSearch, setTableSearch] = useState('');
  const debouncedTableSearch = useDebounce(tableSearch, 400);
  const [statusFilter, setStatusFilter] = useState<StatusKey | 'all'>('all');
  const [classificacaoFilter, setClassificacaoFilter] = useState<ClassificationFilter>('all');
  const [corretorFilter, setCorretorFilter] = useState<string>('');
  const [originFilter, setOriginFilter] = useState<string>('all');
  const [sortKey, setSortKey] = useState<'updated_at' | 'nome' | 'status'>('updated_at');
  const [sortDir, setSortDir] = useState<'asc' | 'desc'>('desc');
  const [corretores, setCorretores] = useState<Array<{ id: number; name: string; email?: string }>>([]);
  const [currentUser, setCurrentUser] = useState<LoggedUser>({ id: null, role: null, name: null });
  const [assignmentSubmitting, setAssignmentSubmitting] = useState<'assume' | 'assign' | null>(null);
  const [selectedAssigneeId, setSelectedAssigneeId] = useState('');
  const [tablePage, setTablePage] = useState(1);
  const [tablePerPage, setTablePerPage] = useState(50);
  const [isMobile, setIsMobile] = useState(false);
  const [flatTableError, setFlatTableError] = useState(false);
  const [deletingLeadId, setDeletingLeadId] = useState<number | null>(null);
  const [quickViewProperty, setQuickViewProperty] = useState<{ url: string; title: string } | null>(null);

  // Chat state
  const [messages, setMessages] = useState<Message[]>([]);
  const [messageText, setMessageText] = useState('');
  const [isLoadingMessages, setIsLoadingMessages] = useState(false);
  const [isSending, setIsSending] = useState(false);
  const messagesEndRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const scrollAreaRef = useRef<HTMLDivElement>(null);
  const fetchSeqRef = useRef(0);
  const chatIntervalRef = useRef<number | null>(null);
  const hasLoadedMessagesRef = useRef(false);
  const pendingScrollRestoreRef = useRef<null | { top: number; height: number; nearBottom: boolean }>(null);

  // Documents state (perfil)
  const [documents, setDocuments] = useState<ClientDocument[]>([]);
  const [isLoadingDocuments, setIsLoadingDocuments] = useState(false);
  const [selectedDownloads, setSelectedDownloads] = useState<Record<string, boolean>>({});

  // ─── Fetch CRM data ───────────────────────────────────────────────

  const { data: crmData = createEmptyCRMData(), isLoading } = useQuery<Record<StatusKey, CRMClient[]>>({
    queryKey: ['crm-clientes', debouncedSearch, corretorFilter, classificacaoFilter],
    queryFn: async () => {
      const params: any = {};
      if (debouncedSearch) params.search = debouncedSearch;
      if (corretorFilter) params.corretor_id = corretorFilter;
      if (classificacaoFilter !== 'all') params.classificacao = classificacaoFilter;
      const res = await api.get('/crm/clientes', { params });
      const payload = res?.data?.data ?? res?.data ?? [];
      return normalizeCRMData(payload);
    },
    refetchInterval: 30000,
    staleTime: 15000,
    initialData: createEmptyCRMData,
    enabled: !isMobile,
  });

  const { data: tableData, isLoading: isLoadingTable, isError: isTableError } = useQuery<CRMTableResponse>({
    queryKey: ['crm-clientes-table', debouncedTableSearch, corretorFilter, classificacaoFilter, statusFilter, sortKey, sortDir, tablePage, tablePerPage, originFilter],
    queryFn: async () => {
      const params: any = {
        flat: 1,
        page: tablePage,
        per_page: tablePerPage,
        sort_by: sortKey,
        sort_dir: sortDir,
      };
      if (debouncedTableSearch) params.search = debouncedTableSearch;
      if (corretorFilter) params.corretor_id = corretorFilter;
      if (classificacaoFilter !== 'all') params.classificacao = classificacaoFilter;
      if (statusFilter !== 'all') params.status = statusFilter;
      const res = await api.get('/crm/clientes', { params });
      const raw = res?.data ?? {};
      const flat = normalizeFlatClients(raw?.data ?? raw);
      return {
        total: Number(raw?.total || 0),
        current_page: Number(raw?.current_page || tablePage),
        last_page: Number(raw?.last_page || 1),
        per_page: Number(raw?.per_page || tablePerPage),
        data: flat,
      };
    },
    enabled: originFilter === 'all',
    placeholderData: (previousData) => previousData,
  });

  const { data: originSummaryClients = [] } = useQuery<CRMClient[]>({
    queryKey: ['crm-clientes-origin-summary', debouncedTableSearch, corretorFilter, classificacaoFilter, statusFilter],
    queryFn: async () => {
      const params: any = {
        flat: 1,
        page: 1,
        per_page: 1000,
      };
      if (debouncedTableSearch) params.search = debouncedTableSearch;
      if (corretorFilter) params.corretor_id = corretorFilter;
      if (classificacaoFilter !== 'all') params.classificacao = classificacaoFilter;
      if (statusFilter !== 'all') params.status = statusFilter;

      const res = await api.get('/crm/clientes', { params });
      const raw = res?.data ?? {};
      return normalizeFlatClients(raw?.data ?? raw);
    },
    placeholderData: (previousData) => previousData ?? [],
    enabled: !isMobile,
  });

  const { data: mobileStatusData, isLoading: isLoadingMobileStatus } = useQuery<CRMTableResponse>({
    queryKey: ['crm-clientes-mobile-status', debouncedSearch, corretorFilter, classificacaoFilter, mobileStatus],
    queryFn: async () => {
      const params: any = {
        flat: 1,
        page: 1,
        per_page: 20,
        status: mobileStatus,
        sort_by: 'updated_at',
        sort_dir: 'desc',
      };
      if (debouncedSearch) params.search = debouncedSearch;
      if (corretorFilter) params.corretor_id = corretorFilter;
      if (classificacaoFilter !== 'all') params.classificacao = classificacaoFilter;

      const res = await api.get('/crm/clientes', { params });
      const raw = res?.data ?? {};
      const flat = normalizeFlatClients(raw?.data ?? raw);

      return {
        total: Number(raw?.total || flat.length || 0),
        current_page: Number(raw?.current_page || 1),
        last_page: Number(raw?.last_page || 1),
        per_page: Number(raw?.per_page || 20),
        data: flat,
      };
    },
    enabled: isMobile,
    placeholderData: (previousData) => previousData,
    staleTime: 15000,
  });

  useEffect(() => {
    setFlatTableError(isTableError);
  }, [isTableError]);

  const allClients = useMemo(() => {
    if (!crmData) return [];
    return ALL_STATUSES.flatMap((s) => Array.isArray(crmData[s]) ? crmData[s] : []);
  }, [crmData]);

  const summaryClients = useMemo(() => {
    if (isMobile && Array.isArray(mobileStatusData?.data) && mobileStatusData.data.length > 0) return mobileStatusData.data;
    if (originSummaryClients.length > 0) return originSummaryClients;
    if (allClients.length > 0) return allClients;
    if (Array.isArray(tableData?.data)) return tableData.data;
    return [];
  }, [isMobile, mobileStatusData, originSummaryClients, allClients, tableData]);

  const scopedSummaryClients = useMemo(() => filterClients(summaryClients, {
    term: tableSearch,
    statusFilter,
    classificacaoFilter,
    corretorFilter,
    originFilter: 'all',
  }), [summaryClients, tableSearch, statusFilter, classificacaoFilter, corretorFilter]);

  const useLocalTableData = flatTableError || originFilter !== 'all';

  const filteredClients = useMemo(() => {
    if (originFilter === 'all') return scopedSummaryClients;
    return scopedSummaryClients.filter((client) => normalizeOriginValue(client.origem) === originFilter);
  }, [scopedSummaryClients, originFilter]);

  const originStats = useMemo(() => {
    const counts = new Map<string, number>();

    scopedSummaryClients.forEach((client) => {
      const origin = normalizeOriginValue(client.origem);
      counts.set(origin, (counts.get(origin) || 0) + 1);
    });

    return Array.from(counts.entries())
      .map(([label, count]) => ({ label, count }))
      .sort((a, b) => {
        const orderA = ORIGIN_SORT_ORDER[a.label] ?? 999;
        const orderB = ORIGIN_SORT_ORDER[b.label] ?? 999;
        if (orderA !== orderB) return orderA - orderB;
        return a.label.localeCompare(b.label, 'pt-BR');
      });
  }, [scopedSummaryClients]);

  const mobileClients = useMemo(() => {
    if (Array.isArray(mobileStatusData?.data)) {
      return mobileStatusData.data;
    }

    return crmData?.[mobileStatus] || [];
  }, [mobileStatusData, crmData, mobileStatus]);

  const tableClients = useMemo(() => {
    if (!useLocalTableData && Array.isArray(tableData?.data)) {
      return tableData.data as CRMClient[];
    }

    const sorted = [...filteredClients].sort((a, b) => {
      if (sortKey === 'nome') {
        const aName = (a.nome || '').toLowerCase();
        const bName = (b.nome || '').toLowerCase();
        return sortDir === 'asc' ? aName.localeCompare(bName) : bName.localeCompare(aName);
      }
      if (sortKey === 'status') {
        const aStatus = a.status || 'novo';
        const bStatus = b.status || 'novo';
        return sortDir === 'asc' ? aStatus.localeCompare(bStatus) : bStatus.localeCompare(aStatus);
      }
      const aTime = new Date(a.updated_at || a.ultima_mensagem_at || a.created_at || 0).getTime();
      const bTime = new Date(b.updated_at || b.ultima_mensagem_at || b.created_at || 0).getTime();
      return sortDir === 'asc' ? aTime - bTime : bTime - aTime;
    });

    const start = (tablePage - 1) * tablePerPage;
    return sorted.slice(start, start + tablePerPage);
  }, [useLocalTableData, tableData, filteredClients, sortKey, sortDir, tablePage, tablePerPage]);

  const tableMeta = useMemo(() => {
    if (!useLocalTableData && tableData) {
      return {
        total: tableData.total || 0,
        current_page: tableData.current_page || 1,
        last_page: tableData.last_page || 1,
        per_page: tableData.per_page || tablePerPage,
      };
    }
    const total = filteredClients.length;
    const last_page = Math.max(1, Math.ceil(total / tablePerPage));
    return {
      total,
      current_page: Math.min(tablePage, last_page),
      last_page,
      per_page: tablePerPage,
    };
  }, [useLocalTableData, tableData, filteredClients, tablePage, tablePerPage]);

  // ─── Callbacks (stable references) ─────────────────────────────────

  const handleSelectClient = useCallback((client: CRMClient) => {
    setSelectedClient(client);
    setDrawerCollapsed(false);
  }, []);

  const handleStatusChange = useCallback(async (clientId: number, newStatus: StatusKey) => {
    try {
      await api.patch(`/crm/clientes/${clientId}/status`, { status: newStatus });
      toast.success('Status atualizado');
      queryClient.invalidateQueries({ queryKey: ['crm-clientes'] });
      queryClient.invalidateQueries({ queryKey: ['crm-clientes-table'] });
      queryClient.invalidateQueries({ queryKey: ['crm-clientes-origin-summary'] });
      queryClient.invalidateQueries({ queryKey: ['crm-clientes-mobile-status'] });
      setSelectedClient((prev) => prev?.id === clientId ? { ...prev, status: newStatus } : prev);
    } catch {
      toast.error('Erro ao atualizar status');
    }
  }, [queryClient]);

  const invalidateCRMQueries = useCallback(() => {
    queryClient.invalidateQueries({ queryKey: ['crm-clientes'] });
    queryClient.invalidateQueries({ queryKey: ['crm-clientes-table'] });
    queryClient.invalidateQueries({ queryKey: ['crm-clientes-origin-summary'] });
    queryClient.invalidateQueries({ queryKey: ['crm-clientes-mobile-status'] });
  }, [queryClient]);

  const handleDeleteLead = useCallback(async (client: CRMClient) => {
    const confirmed = window.confirm(`Excluir o lead de ${client.nome}? Esta ação não pode ser desfeita.`);
    if (!confirmed) return;

    try {
      setDeletingLeadId(client.id);
      await api.delete(`/leads/${client.id}`);
      toast.success('Lead excluído com sucesso');
      invalidateCRMQueries();
      if (selectedClient?.id === client.id) {
        setSelectedClient(null);
      }
    } catch {
      toast.error('Erro ao excluir lead');
    } finally {
      setDeletingLeadId(null);
    }
  }, [invalidateCRMQueries, selectedClient?.id]);

  const handleAssumeAtendimento = useCallback(async (client: CRMClient) => {
    try {
      setAssignmentSubmitting('assume');
      const res = await api.post(`/crm/clientes/${client.id}/assume`);
      const updatedClient = normalizeCRMClient(res?.data?.data ?? client);
      setSelectedClient((prev) => prev?.id === client.id ? updatedClient : prev);
      setSelectedAssigneeId(updatedClient.corretor_id ? String(updatedClient.corretor_id) : '');
      invalidateCRMQueries();
      toast.success(res?.data?.message || 'Atendimento assumido com sucesso');
    } catch (error: any) {
      toast.error(error?.response?.data?.message || 'Erro ao assumir atendimento');
    } finally {
      setAssignmentSubmitting(null);
    }
  }, [invalidateCRMQueries]);

  const handleAssignAtendimento = useCallback(async (client: CRMClient, corretorId: string) => {
    if (!corretorId) {
      toast.error('Selecione um atendente');
      return;
    }

    try {
      setAssignmentSubmitting('assign');
      const res = await api.post(`/crm/clientes/${client.id}/assign`, { corretor_id: Number(corretorId) });
      const updatedClient = normalizeCRMClient(res?.data?.data ?? client);
      setSelectedClient((prev) => prev?.id === client.id ? updatedClient : prev);
      setSelectedAssigneeId(updatedClient.corretor_id ? String(updatedClient.corretor_id) : corretorId);
      invalidateCRMQueries();
      toast.success(res?.data?.message || 'Atendente designado com sucesso');
    } catch (error: any) {
      toast.error(error?.response?.data?.message || 'Erro ao designar atendente');
    } finally {
      setAssignmentSubmitting(null);
    }
  }, [invalidateCRMQueries]);

  const handleOpenQuickView = useCallback((url: string, title: string) => {
    if (!url) return;
    setQuickViewProperty({ url, title });
  }, []);

  const handleSort = useCallback((key: 'updated_at' | 'nome' | 'status') => {
    if (sortKey === key) {
      setSortDir((prev) => (prev === 'asc' ? 'desc' : 'asc'));
    } else {
      setSortKey(key);
      setSortDir('desc');
    }
  }, [sortKey]);

  useEffect(() => {
    setTablePage(1);
  }, [debouncedTableSearch, statusFilter, classificacaoFilter, corretorFilter, originFilter, sortKey, sortDir, tablePerPage]);

  useEffect(() => {
    const update = () => {
      const mobile = typeof window !== 'undefined' && window.innerWidth < 1024;
      setIsMobile(mobile);
      if (mobile) {
        setDrawerDocked(true);
        setDrawerCollapsed(false);
      }
    };
    update();
    window.addEventListener('resize', update);
    return () => window.removeEventListener('resize', update);
  }, []);

  useEffect(() => {
    try {
      const storedUser = localStorage.getItem('user');
      if (!storedUser) return;
      const user = JSON.parse(storedUser);
      setCurrentUser({
        id: typeof user?.id === 'number' ? user.id : Number(user?.id || 0) || null,
        role: user?.role || null,
        name: user?.name || null,
      });
      const role = user?.role;
      if (role === 'admin' || role === 'super_admin') {
        api.get('/admin/corretores')
          .then((res) => {
            if (Array.isArray(res.data?.corretores)) {
              setCorretores(res.data.corretores);
            } else {
              setCorretores([]);
            }
          })
          .catch(() => {
            // silent
          });
      }
    } catch {
      // ignore
    }
  }, []);

  const isAdminUser = currentUser.role === 'admin' || currentUser.role === 'super_admin';
  const isBrokerUser = currentUser.role === 'corretor';

  const assignableUsers = useMemo(() => {
    const entries = new Map<number, { id: number; name: string; email?: string }>();

    corretores.forEach((item) => {
      if (!item?.id) return;
      entries.set(item.id, item);
    });

    if (isAdminUser && currentUser.id && currentUser.name && !entries.has(currentUser.id)) {
      entries.set(currentUser.id, { id: currentUser.id, name: currentUser.name });
    }

    return Array.from(entries.values()).sort((a, b) => a.name.localeCompare(b.name, 'pt-BR'));
  }, [corretores, currentUser.id, currentUser.name, isAdminUser]);

  useEffect(() => {
    if (!selectedClient) {
      setSelectedAssigneeId('');
      return;
    }

    if (selectedClient.corretor_id) {
      setSelectedAssigneeId(String(selectedClient.corretor_id));
      return;
    }

    if (isAdminUser && currentUser.id) {
      setSelectedAssigneeId(String(currentUser.id));
      return;
    }

    setSelectedAssigneeId('');
  }, [selectedClient, isAdminUser, currentUser.id]);

  const getDocumentsEndpoint = useCallback((client: CRMClient) => {
    if (client.pessoa_id) return `/pessoas/${client.pessoa_id}/documentos`;
    return `/leads/${client.id}/documents`;
  }, []);

  const getDocumentsExportEndpoint = useCallback((client: CRMClient) => {
    if (client.pessoa_id) return `/pessoas/${client.pessoa_id}/documentos/export`;
    return `/leads/${client.id}/documents/export`;
  }, []);

  const loadDocuments = useCallback(async (client: CRMClient) => {
    try {
      setIsLoadingDocuments(true);
      const res = await api.get(getDocumentsEndpoint(client));
      setDocuments(res.data.data || []);
    } catch (error) {
      console.error('Erro ao carregar documentos:', error);
      setDocuments([]);
    } finally {
      setIsLoadingDocuments(false);
    }
  }, [getDocumentsEndpoint]);

  useEffect(() => {
    if (!selectedClient) {
      setDocuments([]);
      setSelectedDownloads({});
      return;
    }
    loadDocuments(selectedClient);
    setSelectedDownloads({});
  }, [selectedClient?.id, selectedClient?.pessoa_id, loadDocuments]);

  const handleDownloadAll = useCallback(async () => {
    if (!selectedClient) return;
    try {
      const res = await api.get(getDocumentsExportEndpoint(selectedClient), { responseType: 'blob' });
      const url = window.URL.createObjectURL(new Blob([res.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', `cliente-${selectedClient.id}-documentos.zip`);
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
      toast.success('Download iniciado');
    } catch (error) {
      console.error('Erro ao baixar documentos:', error);
      toast.error('Erro ao baixar documentos');
    }
  }, [selectedClient, getDocumentsExportEndpoint]);

  const handleDownloadSelected = useCallback(async () => {
    if (!selectedClient) return;
    let hasStartedDownload = false;
    const selectedDocIds = Object.entries(selectedDownloads)
      .filter(([key, isSelected]) => isSelected && key.startsWith('doc:'))
      .map(([key]) => Number(key.split(':')[1]))
      .filter((id) => Number.isFinite(id));
    const selectedMessageIds = Object.entries(selectedDownloads)
      .filter(([key, isSelected]) => isSelected && key.startsWith('msg:'))
      .map(([key]) => key.split(':')[1])
      .filter((id) => !!id);

    if (!selectedDocIds.length && !selectedMessageIds.length) {
      toast.error('Selecione pelo menos um arquivo ou foto');
      return;
    }

    if (selectedDocIds.length > 0) {
      try {
        const endpoint = selectedClient.pessoa_id
          ? `/pessoas/${selectedClient.pessoa_id}/documentos/export`
          : `/leads/${selectedClient.id}/documents/export`;
        const res = await api.post(endpoint, { ids: selectedDocIds }, { responseType: 'blob' });
        const url = window.URL.createObjectURL(new Blob([res.data]));
        const link = document.createElement('a');
        link.href = url;
        link.setAttribute('download', `cliente-${selectedClient.id}-selecionados.zip`);
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(url);
        hasStartedDownload = true;
      } catch (error) {
        console.error('Erro ao baixar documentos selecionados:', error);
        toast.error('Erro ao baixar documentos selecionados');
      }
    }

    if (selectedMessageIds.length > 0) {
      const mediaUrls = selectedMessageIds
        .map((messageId) => messages.find((message) => message.id === messageId)?.mediaUrl)
        .filter((url): url is string => !!url)
        .map((url) => getMediaUrl(url));

      mediaUrls.forEach((url) => {
        const link = document.createElement('a');
        link.href = url;
        link.target = '_blank';
        link.rel = 'noopener noreferrer';
        document.body.appendChild(link);
        link.click();
        link.remove();
        hasStartedDownload = true;
      });
    }

    if (hasStartedDownload) {
      toast.success('Download iniciado');
    }
  }, [selectedClient, selectedDownloads, messages]);

  const toggleSelectAll = useCallback((allIds: string[], next?: boolean) => {
    setSelectedDownloads((prev) => {
      const shouldSelect = next ?? allIds.some((id) => !prev[id]);
      const updated: Record<string, boolean> = { ...prev };
      allIds.forEach((id) => {
        updated[id] = shouldSelect;
      });
      return updated;
    });
  }, []);

  // ─── Chat logic ────────────────────────────────────────────────────

  const getScrollViewport = useCallback(() => {
    if (!scrollAreaRef.current) return null;
    return scrollAreaRef.current.querySelector('[data-radix-scroll-area-viewport]') as HTMLDivElement | null;
  }, []);

  const scrollToBottom = useCallback(() => {
    setTimeout(() => { messagesEndRef.current?.scrollIntoView({ behavior: 'auto' }); }, 50);
  }, []);

  useLayoutEffect(() => {
    const pending = pendingScrollRestoreRef.current;
    if (!pending) return;
    const vp = getScrollViewport();
    if (!vp) { pendingScrollRestoreRef.current = null; return; }
    if (pending.nearBottom) vp.scrollTop = vp.scrollHeight;
    else vp.scrollTop = pending.top + (vp.scrollHeight - pending.height);
    pendingScrollRestoreRef.current = null;
  }, [messages, getScrollViewport]);

  const applyMessagesWithScrollPreserve = useCallback((incoming: Message[]) => {
    const vp = getScrollViewport();
    const snapshot = vp ? { top: vp.scrollTop, height: vp.scrollHeight, nearBottom: vp.scrollHeight - (vp.scrollTop + vp.clientHeight) < 100 } : null;

    setMessages((prev) => {
      if (prev.length === 0) {
        if (snapshot) pendingScrollRestoreRef.current = snapshot;
        return incoming;
      }
      const prevById = new Map(prev.map((m) => [m.id, m]));
      let hasChanges = false;
      const merged = incoming.map((m) => {
        const old = prevById.get(m.id);
        if (!old) { hasChanges = true; return m; }
        const changed = old.status !== m.status || old.text !== m.text || old.read !== m.read || old.mediaUrl !== m.mediaUrl;
        if (changed) { hasChanges = true; return { ...old, ...m }; }
        return old;
      });
      const pendingMsgs = prev.filter((m) => m.id.startsWith('temp-'));
      if (pendingMsgs.length) { hasChanges = true; merged.push(...pendingMsgs); merged.sort((a, b) => a.rawDate.getTime() - b.rawDate.getTime()); }
      if (!hasChanges) { pendingScrollRestoreRef.current = null; return prev; }
      if (snapshot) pendingScrollRestoreRef.current = snapshot;
      return merged;
    });
  }, [getScrollViewport]);

  const fetchMessages = useCallback(async (conversaId: number) => {
    const seq = ++fetchSeqRef.current;
    const isFirst = !hasLoadedMessagesRef.current;
    try {
      if (isFirst) setIsLoadingMessages(true);
      const res = await api.get(`/admin/conversas/${conversaId}/mensagens`);
      if (seq !== fetchSeqRef.current) return;
      if (!res.data.success) return;
      const mapped: Message[] = res.data.data
        .filter((item: any) => item?.id != null)
        .map((item: any) => ({
          id: item.id.toString(),
          sender: item.direction === 'outgoing' ? 'user' : 'contact',
          text: item.content,
          timestamp: formatTime(item.created_at),
          rawDate: new Date(item.created_at),
          read: !!item.read_at,
          status: item.read_at ? 'read' : item.delivered_at ? 'delivered' : 'sent',
          messageType: item.message_type,
          mediaUrl: item.media_url ?? null,
          transcription: item.transcription ?? null,
          senderName: item.sender_name ?? undefined,
          senderContext: item.sender_context ?? undefined,
          senderKind: item.sender_kind ?? (item.direction === 'outgoing' ? (item.user_id ? 'human' : 'assistant') : 'lead'),
        }))
        .sort((a: Message, b: Message) => a.rawDate.getTime() - b.rawDate.getTime());
      applyMessagesWithScrollPreserve(mapped);
      if (isFirst) hasLoadedMessagesRef.current = true;
    } catch {
      if (seq === fetchSeqRef.current && isFirst) toast.error('Erro ao carregar mensagens');
    } finally {
      if (seq === fetchSeqRef.current && isFirst) setIsLoadingMessages(false);
    }
  }, [applyMessagesWithScrollPreserve]);

  const handleSendMessage = useCallback(async () => {
    if (!messageText.trim() || !selectedClient?.conversa_id || isSending) return;
    const text = messageText.trim();
    const conversaId = selectedClient.conversa_id;
    setMessageText('');
    setIsSending(true);

    const tempId = `temp-${Date.now()}`;
    const newMsg: Message = { id: tempId, sender: 'user', text, timestamp: formatTime(new Date().toISOString()), rawDate: new Date(), read: false, status: 'sending' };
    setMessages((prev) => [...prev, newMsg]);
    scrollToBottom();

    try {
      const res = await api.post(`/admin/conversas/${conversaId}/mensagens`, { content: text });
      if (!res.data.success) throw new Error();
      setMessages((prev) => prev.map((m) => m.id === tempId ? { ...m, id: res.data.data?.id || tempId, status: 'sent' } : m));
      setTimeout(() => fetchMessages(conversaId), 1000);
    } catch {
      toast.error('Erro ao enviar mensagem');
      setMessages((prev) => prev.filter((m) => m.id !== tempId));
    } finally {
      setIsSending(false);
      inputRef.current?.focus();
    }
  }, [messageText, selectedClient?.conversa_id, isSending, scrollToBottom, fetchMessages]);

  // CRM desacoplado do chat: ao trocar de lead, limpa estado local de mensagens.
  useEffect(() => {
    if (chatIntervalRef.current) {
      window.clearInterval(chatIntervalRef.current);
      chatIntervalRef.current = null;
    }

    setMessages([]);
    hasLoadedMessagesRef.current = false;
  }, [selectedClient?.id]);

  // Group messages by date
  const groupedMessages = useMemo(() => {
    const groups: { date: string; messages: Message[] }[] = [];
    let currentDate = '';
    messages.forEach((m) => {
      const key = m.rawDate.toDateString();
      if (key !== currentDate) {
        currentDate = key;
        groups.push({ date: formatDateSeparator(m.rawDate), messages: [m] });
      } else groups[groups.length - 1].messages.push(m);
    });
    return groups;
  }, [messages]);

  const selectedLeadInterest = useMemo(() => {
    if (!selectedClient) return null;
    return extractLeadInterest(selectedClient);
  }, [selectedClient]);

  const selectedClientWhatsappUrl = useMemo(() => {
    if (!selectedClient) return '';

    const interestLabel = selectedLeadInterest?.displayLabel || selectedLeadInterest?.summary || '';
    return buildWhatsAppUrl(
      selectedClient.telefone,
      interestLabel
        ? `Olá, ${selectedClient.nome}! Vi seu interesse em ${interestLabel}.`
        : `Olá, ${selectedClient.nome}!`,
    );
  }, [selectedClient, selectedLeadInterest]);

  // ─── Render: Drawer content ────────────────────────────────────────

  const renderPerfilTab = () => {
    if (!selectedClient) return null;
    const p = selectedClient.pessoa;
    const leadInterest = selectedLeadInterest;
    const whatsappUrl = selectedClientWhatsappUrl;
    type PerfilMediaItem = {
      id: string;
      url: string;
      label: string;
      source: 'chat' | 'documento';
      mime?: string;
      createdAt?: Date;
    };

    const imageDocs = documents.filter((doc) => doc.mime_type?.startsWith('image/'));
    const fileDocs = documents.filter((doc) => !doc.mime_type?.startsWith('image/'));
    const chatMediaMessages = messages.filter((message) => !!message.mediaUrl);

    const chatPhotoItems: PerfilMediaItem[] = chatMediaMessages
      .filter((message) => isImageMessage(message))
      .map((message) => ({
        id: `msg:${message.id}`,
        url: getMediaUrl(message.mediaUrl || ''),
        label: message.text?.trim() || `Imagem ${message.timestamp}`,
        source: 'chat' as const,
        createdAt: message.rawDate,
      }))
      .filter((item) => item.url);

    const chatFileItems: PerfilMediaItem[] = chatMediaMessages
      .filter((message) => !isImageMessage(message))
      .map((message) => ({
        id: `msg:${message.id}`,
        url: getMediaUrl(message.mediaUrl || ''),
        label: message.text?.trim() || `Arquivo ${message.timestamp}`,
        mime: message.messageType || 'media',
        source: 'chat' as const,
        createdAt: message.rawDate,
      }))
      .filter((item) => item.url);

    const profilePhotoItems: PerfilMediaItem[] = imageDocs
      .map((doc) => ({
        id: `doc:${doc.id}`,
        url: getMediaUrl(doc.arquivo_url),
        label: doc.nome,
        source: 'documento' as const,
        createdAt: doc.created_at ? new Date(doc.created_at) : undefined,
      }))
      .filter((item) => item.url);

    const profileFileItems: PerfilMediaItem[] = fileDocs.map((doc) => ({
      id: `doc:${doc.id}`,
      url: getMediaUrl(doc.arquivo_url),
      label: doc.nome,
      mime: doc.mime_type,
      source: 'documento',
      createdAt: doc.created_at ? new Date(doc.created_at) : undefined,
    }));

    const photoItems = [...profilePhotoItems, ...chatPhotoItems]
      .filter((item, index, all) => index === all.findIndex((entry) => entry.url === item.url))
      .sort((a, b) => (b.createdAt?.getTime() || 0) - (a.createdAt?.getTime() || 0));
    const fileItems = [...profileFileItems, ...chatFileItems]
      .filter((item, index, all) => index === all.findIndex((entry) => entry.url === item.url))
      .sort((a, b) => (b.createdAt?.getTime() || 0) - (a.createdAt?.getTime() || 0));

    const allItems = [...fileItems, ...photoItems];
    const allSelectableIds = allItems.map((item) => item.id);
    const allSelected = allSelectableIds.length > 0 && allSelectableIds.every((id) => selectedDownloads[id]);
    const sourceStats = {
      documento: allItems.filter((item) => item.source === 'documento').length,
      chat: allItems.filter((item) => item.source === 'chat').length,
    };
    const formatItemDate = (date?: Date) =>
      date ? date.toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' }) : 'Sem data';
    const getSourceBadgeClass = (source: PerfilMediaItem['source']) =>
      source === 'chat'
        ? 'bg-blue-500/15 text-blue-400 border-blue-500/30'
        : 'bg-violet-500/15 text-violet-400 border-violet-500/30';
    const getSourceLabel = (source: PerfilMediaItem['source']) =>
      source === 'chat' ? 'Origem: Chat' : 'Origem: Documento';

    return (
      <ScrollArea className="flex-1">
        <div className="p-4 space-y-4">
          <div className="space-y-3">
            <div className="flex items-center justify-between gap-3">
              <h4 className="text-xs uppercase tracking-wider text-muted-foreground font-semibold">Dados do Lead</h4>
              {whatsappUrl && (
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  className="h-8 border-emerald-500/30 bg-emerald-500/10 text-emerald-400 hover:bg-emerald-500/20 hover:text-emerald-300"
                  onClick={() => window.open(whatsappUrl, '_blank', 'noopener,noreferrer')}
                >
                  <MessageCircle className="w-3.5 h-3.5 mr-1.5" />
                  WhatsApp
                </Button>
              )}
            </div>
            <div className="grid grid-cols-2 gap-3">
              <InfoField label="Nome" value={selectedClient.nome} />
              <InfoField label="Telefone" value={formatPhoneDisplay(selectedClient.telefone)} />
              <InfoField label="E-mail" value={selectedClient.email} />
              <InfoField label="Classificacao" value={selectedClient.classificacao} />
              <InfoField label="Corretor" value={selectedClient.corretor_nome} />
              <InfoField label="Origem" value={selectedClient.origem} />
              {selectedClient.valor && <InfoField label="Valor" value={`R$ ${selectedClient.valor.toLocaleString('pt-BR')}`} />}
            </div>
            {(leadInterest?.displayLabel || leadInterest?.propertyLink || leadInterest?.propertyCode || leadInterest?.propertyTitle) && (
              <div className="rounded-xl border border-border bg-muted/20 p-3 space-y-2">
                {leadInterest?.displayLabel && <InfoField label="Imóvel de interesse" value={leadInterest.displayLabel} />}
                <div className="grid grid-cols-2 gap-3">
                  {leadInterest?.propertyTitle && <InfoField label="Título do imóvel" value={leadInterest.propertyTitle} />}
                  {leadInterest?.propertyCode && <InfoField label="Código/Referência" value={leadInterest.propertyCode} />}
                  {leadInterest?.propertyLocation && <InfoField label="Localização" value={leadInterest.propertyLocation} />}
                  {leadInterest?.propertyValue && <InfoField label="Valor" value={leadInterest.propertyValue} />}
                  {leadInterest?.captureSource && <InfoField label="Origem da captura" value={leadInterest.captureSource} />}
                </div>
                {leadInterest?.summary && leadInterest.summary !== leadInterest.displayLabel && (
                  <InfoField label="Detalhe capturado" value={leadInterest.summary} />
                )}
                {leadInterest?.propertyLink && (
                  <PropertyInterestActions
                    propertyLink={leadInterest.propertyLink}
                    propertyLabel={leadInterest.displayLabel}
                    onQuickView={() => handleOpenQuickView(
                      leadInterest.propertyLink || '',
                      leadInterest.displayLabel || leadInterest.propertyTitle || 'Imóvel'
                    )}
                  />
                )}
              </div>
            )}
          </div>

          {p && (
            <div className="space-y-3 pt-4 border-t border-border">
              <h4 className="text-xs uppercase tracking-wider text-muted-foreground font-semibold">Dados da Pessoa</h4>
              <div className="grid grid-cols-2 gap-3">
                <InfoField label="Nome" value={p.nome} />
                <InfoField label="Tipo" value={p.tipo} />
                <InfoField label="CPF" value={p.cpf} />
                <InfoField label="E-mail" value={p.email} />
                <InfoField label="Telefone" value={formatPhoneDisplay(p.telefone)} />
                <InfoField label="Celular" value={formatPhoneDisplay(p.celular)} />
              </div>
            </div>
          )}

          <div className="space-y-3 pt-4 border-t border-border">
            <div className="flex items-center justify-between">
              <h4 className="text-xs uppercase tracking-wider text-muted-foreground font-semibold">Arquivos e Fotos</h4>
              <div className="flex items-center gap-2">
                {allSelectableIds.length > 0 && (
                  <Button
                    variant="ghost"
                    size="sm"
                    className="text-muted-foreground hover:text-foreground"
                    onClick={() => toggleSelectAll(allSelectableIds, !allSelected)}
                  >
                    {allSelected ? 'Desmarcar tudo' : 'Selecionar tudo'}
                  </Button>
                )}
                {documents.length > 0 && (
                  <Button variant="ghost" size="sm" className="text-muted-foreground hover:text-foreground" onClick={handleDownloadAll}>
                    <Download className="w-4 h-4 mr-2" />
                    Baixar ZIP
                  </Button>
                )}
                <Button variant="ghost" size="sm" className="text-muted-foreground hover:text-foreground" onClick={handleDownloadSelected}>
                  <Download className="w-4 h-4 mr-2" />
                  Baixar selecionados
                </Button>
              </div>
            </div>

            <div className="flex flex-wrap items-center gap-2 text-[11px]">
              <span className="px-2 py-0.5 rounded-full border border-white/10 bg-white/5 text-muted-foreground">
                Total: {allSelectableIds.length}
              </span>
              <span className="px-2 py-0.5 rounded-full border border-violet-500/30 bg-violet-500/15 text-violet-400">
                Documentos: {sourceStats.documento}
              </span>
              <span className="px-2 py-0.5 rounded-full border border-blue-500/30 bg-blue-500/15 text-blue-400">
                Chat: {sourceStats.chat}
              </span>
            </div>

            {isLoadingDocuments ? (
              <div className="flex items-center gap-2 text-sm text-muted-foreground">
                <Loader2 className="w-4 h-4 animate-spin" />
                Carregando arquivos...
              </div>
            ) : fileItems.length === 0 ? (
              <p className="text-sm text-muted-foreground">Nenhum arquivo enviado.</p>
            ) : (
              <div className="space-y-2">
                {fileItems.map((doc) => (
                  <div key={doc.id} className="flex items-center gap-3 p-3 rounded-lg border border-border bg-muted/30">
                    <input
                      type="checkbox"
                      className="h-4 w-4 rounded border-border"
                      checked={!!selectedDownloads[doc.id]}
                      onChange={(e) => setSelectedDownloads((prev) => ({ ...prev, [doc.id]: e.target.checked }))}
                    />
                    <a
                      href={doc.url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="flex items-center gap-3 flex-1 min-w-0 hover:text-foreground"
                      data-download-id={doc.id}
                      data-download-name={doc.label}
                    >
                      <div className="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center">
                        <FileText className="w-5 h-5 text-primary" />
                      </div>
                      <div className="flex-1 min-w-0">
                        <p className="text-sm font-medium text-foreground truncate">{doc.label}</p>
                        <div className="mt-1 flex flex-wrap items-center gap-2 text-[11px]">
                          <span className={cn('px-1.5 py-0.5 rounded-full border', getSourceBadgeClass(doc.source))}>
                            {getSourceLabel(doc.source)}
                          </span>
                          <span className="text-muted-foreground">{doc.mime || 'arquivo'}</span>
                          <span className="text-muted-foreground">{formatItemDate(doc.createdAt)}</span>
                        </div>
                      </div>
                      <ExternalLink className="w-4 h-4 text-muted-foreground flex-shrink-0" />
                    </a>
                  </div>
                ))}
              </div>
            )}

            <div className="pt-4 border-t border-border space-y-3">
              <div className="flex items-center gap-2">
                <Image className="w-4 h-4 text-muted-foreground" />
                <h4 className="text-xs uppercase tracking-wider text-muted-foreground font-semibold">Fotos</h4>
              </div>
              {photoItems.length === 0 ? (
                <p className="text-sm text-muted-foreground">Nenhuma foto disponível.</p>
              ) : (
                <div className="grid grid-cols-2 md:grid-cols-3 gap-3">
                  {photoItems.map((photo) => (
                    <label key={photo.id} className="group relative rounded-lg border border-border overflow-hidden bg-muted/30">
                      <input
                        type="checkbox"
                        className="absolute top-2 left-2 z-10 h-4 w-4 rounded border-border bg-background/80"
                        checked={!!selectedDownloads[photo.id]}
                        onChange={(e) => setSelectedDownloads((prev) => ({ ...prev, [photo.id]: e.target.checked }))}
                      />
                      <a
                        href={photo.url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="block"
                        data-download-id={photo.id}
                        data-download-name={photo.label}
                      >
                        <span className={cn('absolute right-2 top-2 z-10 px-1.5 py-0.5 rounded-full border text-[10px] backdrop-blur-sm', getSourceBadgeClass(photo.source))}>
                          {photo.source === 'chat' ? 'Chat' : 'Documento'}
                        </span>
                        <img
                          src={photo.url}
                          alt={photo.label}
                          className="w-full h-32 object-cover transition-transform duration-300 group-hover:scale-105"
                          loading="lazy"
                        />
                      </a>
                    </label>
                  ))}
                </div>
              )}
            </div>
          </div>
        </div>
      </ScrollArea>
    );
  };

  // ─── Render ────────────────────────────────────────────────────────

  const totalClients = summaryClients.length;
  const totalUnread = summaryClients.reduce((sum, c) => sum + c.unread, 0);
  const selectedClientId = selectedClient?.id ?? null;
  const renderConversationPanel = (mode: 'desktop' | 'mobile') => {
    if (!selectedClient) return null;

    const isDesktopPanel = mode === 'desktop';

    return (
      <div
        className={cn(
          'relative bg-background border border-border flex flex-col overflow-hidden',
          isDesktopPanel
            ? 'h-full min-h-0 rounded-[28px] border-white/8 bg-[#0b1322]/88 shadow-[0_18px_42px_rgba(2,6,23,0.24)]'
            : 'ml-auto w-full h-full rounded-none border-l lg:w-[55%] xl:w-[50%]'
        )}
      >
        <div className="flex items-start gap-3 px-4 py-3 border-b border-border bg-card/80">
          {isMobile ? (
            <Button variant="ghost" size="icon" onClick={() => setSelectedClient(null)} className="flex-shrink-0">
              <ArrowLeft className="w-5 h-5" />
            </Button>
          ) : (
            <div className="w-9 h-9 flex items-center justify-center rounded-full bg-primary/10 text-primary text-sm font-semibold">
              {getInitials(selectedClient.nome)}
            </div>
          )}

          <div className="flex-1 min-w-0 pr-2">
            <h2 className="font-semibold text-foreground truncate">{selectedClient.nome}</h2>
            <p className="text-xs text-muted-foreground truncate">{formatPhoneDisplay(selectedClient.telefone)}</p>
            <div className="mt-1 flex flex-wrap items-center gap-2 text-[11px] text-muted-foreground">
              <span>
                Atendente: <span className="font-semibold text-foreground">{selectedClient.corretor_nome || 'Não designado'}</span>
              </span>
            </div>
            {selectedLeadInterest?.compactSummary && (
              <p className="text-[11px] text-emerald-400/90 truncate mt-0.5">{selectedLeadInterest.compactSummary}</p>
            )}
            {selectedLeadInterest?.propertyLink && (
              <div className="mt-1.5">
                <PropertyInterestActions
                  propertyLink={selectedLeadInterest.propertyLink}
                  propertyLabel={selectedLeadInterest.displayLabel}
                  compact
                  onQuickView={() => handleOpenQuickView(
                    selectedLeadInterest.propertyLink || '',
                    selectedLeadInterest.displayLabel || selectedLeadInterest.propertyTitle || 'Imóvel'
                  )}
                />
              </div>
            )}
            {(isAdminUser || isBrokerUser) && (
              <div className="mt-2 flex flex-wrap items-center gap-2">
                <Button
                  variant="secondary"
                  size="sm"
                  disabled={assignmentSubmitting !== null || (!isAdminUser && Boolean(selectedClient.corretor_id && selectedClient.corretor_id !== currentUser.id))}
                  className="h-8 px-3 text-xs"
                  onClick={() => handleAssumeAtendimento(selectedClient)}
                  title={!isAdminUser && selectedClient.corretor_id && selectedClient.corretor_id !== currentUser.id ? 'Este atendimento já está com outro corretor' : 'Assumir atendimento'}
                >
                  {assignmentSubmitting === 'assume' ? <Loader2 className="w-3.5 h-3.5 animate-spin mr-1.5" /> : null}
                  {selectedClient.corretor_id === currentUser.id ? 'Atendimento comigo' : 'Assumir atendimento'}
                </Button>

                {isAdminUser && (
                  <>
                    <select
                      value={selectedAssigneeId}
                      onChange={(event) => setSelectedAssigneeId(event.target.value)}
                      disabled={assignmentSubmitting !== null}
                      className="h-8 min-w-[180px] rounded-lg border border-border bg-muted/30 px-2.5 text-xs text-foreground focus:outline-none focus:ring-2 focus:ring-primary/30"
                    >
                      <option value="">Selecionar atendente</option>
                      {assignableUsers.map((item) => (
                        <option key={item.id} value={item.id}>{item.name}</option>
                      ))}
                    </select>
                    <Button
                      variant="outline"
                      size="sm"
                      disabled={assignmentSubmitting !== null || !selectedAssigneeId}
                      className="h-8 px-3 text-xs"
                      onClick={() => handleAssignAtendimento(selectedClient, selectedAssigneeId)}
                    >
                      {assignmentSubmitting === 'assign' ? <Loader2 className="w-3.5 h-3.5 animate-spin mr-1.5" /> : null}
                      Designar atendente
                    </Button>
                  </>
                )}
              </div>
            )}
          </div>

          <div className="flex items-center gap-1 flex-shrink-0 self-start">
            <Button
              variant="ghost"
              size="sm"
              className="h-8 px-2.5 text-sky-400 hover:text-sky-300 hover:bg-sky-500/10"
              onClick={() => {
                window.location.href = selectedClient?.id ? `/chat?leadId=${selectedClient.id}` : '/chat';
              }}
              title="Abrir chat dedicado"
            >
              <MessageCircle className="w-4 h-4 sm:mr-1.5" />
              <span className="hidden sm:inline">Abrir Chat</span>
            </Button>
            {selectedClientWhatsappUrl && (
              <Button
                variant="ghost"
                size="sm"
                className="h-8 px-2.5 text-emerald-400 hover:text-emerald-300 hover:bg-emerald-500/10"
                onClick={() => window.open(selectedClientWhatsappUrl, '_blank', 'noopener,noreferrer')}
                title="Abrir conversa no WhatsApp"
              >
                <MessageCircle className="w-4 h-4 sm:mr-1.5" />
                <span className="hidden sm:inline">WhatsApp</span>
              </Button>
            )}
            {!isMobile && (
              <Button
                variant="ghost"
                size="sm"
                className="h-8 px-2.5 text-muted-foreground hover:text-foreground"
                onClick={() => setSelectedClient(null)}
                title="Fechar conversa"
              >
                Fechar
              </Button>
            )}
            <Button
              variant="ghost"
              size="icon"
              className="w-8 h-8 text-muted-foreground hover:text-foreground"
              onClick={() => setDrawerCollapsed((prev) => !prev)}
              title={drawerCollapsed ? 'Abrir' : 'Minimizar'}
            >
              {drawerCollapsed ? <ChevronUp className="w-4 h-4" /> : <ChevronDown className="w-4 h-4" />}
            </Button>
            <div className="relative group">
              <button className={cn('flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded-full border', (STATUS_CONFIG[selectedClient.status as StatusKey] || STATUS_CONFIG.novo).bg, (STATUS_CONFIG[selectedClient.status as StatusKey] || STATUS_CONFIG.novo).color)}>
                {(STATUS_CONFIG[selectedClient.status as StatusKey] || STATUS_CONFIG.novo).label}
                <ChevronDown className="w-3 h-3" />
              </button>
              <div className="hidden group-hover:block absolute right-0 top-full mt-1 w-40 bg-card border border-border rounded-xl shadow-xl z-10 py-1">
                {ALL_STATUSES.map((s) => (
                  <button key={s} onClick={() => handleStatusChange(selectedClient.id, s)} className={cn('w-full text-left px-3 py-2 text-xs hover:bg-muted/50 transition-colors', s === selectedClient.status && 'font-bold')}>
                    <span className={STATUS_CONFIG[s].color}>{STATUS_CONFIG[s].label}</span>
                  </button>
                ))}
              </div>
            </div>
            <Button
              variant="ghost"
              size="icon"
              disabled={deletingLeadId === selectedClient.id}
              className="w-8 h-8 text-muted-foreground hover:text-red-500"
              onClick={() => handleDeleteLead(selectedClient)}
              title="Excluir lead"
            >
              {deletingLeadId === selectedClient.id ? <Loader2 className="w-4 h-4 animate-spin" /> : <Trash2 className="w-4 h-4" />}
            </Button>
          </div>
        </div>

        {!drawerCollapsed && (
          <div className="flex-1 min-h-0 flex flex-col overflow-hidden">
            {renderPerfilTab()}
          </div>
        )}
      </div>
    );
  };

  return (
    <div className="flex min-h-screen bg-[#050814] text-foreground">
      <Sidebar />

      <div className="page-shell relative flex min-h-screen flex-col overflow-hidden">
        {/* Top Bar */}
        <div className="page-content">
          <div className="rounded-[28px] border border-white/8 bg-[#0b1322]/88 px-5 py-4 shadow-[0_18px_42px_rgba(2,6,23,0.28)]">
            <div className="flex items-center gap-3 flex-wrap">
            <div className="flex items-center gap-2">
              <Users className="w-5 h-5 text-primary" />
              <h1 className="text-lg font-bold text-foreground">CRM</h1>
              <span className="text-xs text-muted-foreground bg-muted px-2 py-0.5 rounded-full">{totalClients}</span>
              {totalUnread > 0 && (
                <span className="text-[10px] font-bold bg-primary text-primary-foreground px-1.5 py-0.5 rounded-full">{totalUnread}</span>
              )}
            </div>
            <div className="relative flex-1 max-w-sm lg:hidden">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground w-4 h-4" />
              <input
                type="text"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Buscar cliente..."
                className="w-full pl-9 pr-3 py-2 bg-muted/40 border border-border rounded-xl text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary/30"
              />
            </div>
            <Button variant="ghost" size="icon" onClick={invalidateCRMQueries} className="text-muted-foreground hover:text-foreground">
              <RefreshCw className={cn('w-4 h-4', (isLoading || isLoadingTable || isLoadingMobileStatus) && 'animate-spin')} />
            </Button>
            </div>
          </div>
        </div>

        {/* Content */}
        {isLoading && !crmData ? (
          <div className="flex-1 flex items-center justify-center">
            <Loader2 className="w-8 h-8 animate-spin text-primary" />
          </div>
        ) : (
          <>
            {/* Desktop: Kanban */}
            <div className="page-content hidden lg:flex flex-1 min-h-0 pt-4">
              <div className="flex w-full min-h-0 gap-4">
              <div className={cn(
                'flex min-h-0 flex-col gap-3 rounded-[28px] border border-white/8 bg-[#0b1322]/82 p-4 shadow-[0_18px_42px_rgba(2,6,23,0.24)] transition-all duration-200',
                selectedClient ? 'flex-[1.15] min-w-0' : 'flex-1'
              )}>
                <div className="flex flex-wrap items-center gap-2">
                  {/* Search */}
                  <div className="relative flex-1 min-w-[200px] max-w-sm">
                    <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground w-4 h-4 pointer-events-none" />
                    <input
                      type="text"
                      value={tableSearch}
                      onChange={(e) => setTableSearch(e.target.value)}
                      placeholder="Buscar clientes..."
                      className="w-full pl-9 pr-3 py-2.5 bg-muted/30 border border-border rounded-xl text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary/40"
                    />
                  </div>

                  <div className="w-px h-8 bg-border/60 hidden sm:block" />

                  {/* Status filter */}
                  <select
                    value={statusFilter}
                    onChange={(e) => setStatusFilter(e.target.value as StatusKey | 'all')}
                    className={cn(
                      'px-3 py-2.5 border rounded-xl text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/30 cursor-pointer [&>option]:text-gray-900 [&>option]:bg-white dark:[&>option]:text-gray-100 dark:[&>option]:bg-gray-900',
                      statusFilter !== 'all'
                        ? 'bg-primary/10 border-primary/40 text-primary font-medium'
                        : 'bg-muted/30 border-border'
                    )}
                  >
                    <option value="all">Todos os status</option>
                    {ALL_STATUSES.map((s) => (
                      <option key={s} value={s}>{STATUS_CONFIG[s].label}</option>
                    ))}
                  </select>

                  {/* Classification filter */}
                  <select
                    value={classificacaoFilter}
                    onChange={(e) => setClassificacaoFilter(e.target.value as 'all' | 'quente' | 'morno' | 'frio')}
                    className={cn(
                      'px-3 py-2.5 border rounded-xl text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/30 cursor-pointer [&>option]:text-gray-900 [&>option]:bg-white dark:[&>option]:text-gray-100 dark:[&>option]:bg-gray-900',
                      classificacaoFilter !== 'all'
                        ? 'bg-orange-500/10 border-orange-500/40 text-orange-500 font-medium'
                        : 'bg-muted/30 border-border'
                    )}
                  >
                    <option value="all">Classificação</option>
                    <option value="quente">🔥 Quente</option>
                    <option value="morno">🌡️ Morno</option>
                    <option value="frio">❄️ Frio</option>
                  </select>

                  {/* Broker filter */}
                  {corretores.length > 0 && (
                    <select
                      value={corretorFilter}
                      onChange={(e) => setCorretorFilter(e.target.value)}
                      className={cn(
                        'px-3 py-2.5 border rounded-xl text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/30 cursor-pointer [&>option]:text-gray-900 [&>option]:bg-white dark:[&>option]:text-gray-100 dark:[&>option]:bg-gray-900',
                        corretorFilter
                          ? 'bg-purple-500/10 border-purple-500/40 text-purple-500 font-medium'
                          : 'bg-muted/30 border-border'
                      )}
                    >
                      <option value="">Todos os corretores</option>
                      {corretores.map((corretor) => (
                        <option key={corretor.id} value={corretor.id}>
                          {corretor.name}
                        </option>
                      ))}
                    </select>
                  )}

                  <select
                    value={originFilter}
                    onChange={(e) => setOriginFilter(e.target.value)}
                    className={cn(
                      'px-3 py-2.5 border rounded-xl text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/30 cursor-pointer [&>option]:text-gray-900 [&>option]:bg-white dark:[&>option]:text-gray-100 dark:[&>option]:bg-gray-900',
                      originFilter !== 'all'
                        ? 'bg-emerald-500/10 border-emerald-500/40 text-emerald-500 font-medium'
                        : 'bg-muted/30 border-border'
                    )}
                  >
                    <option value="all">Todas as origens</option>
                    {originStats.map((origin) => (
                      <option key={origin.label} value={origin.label}>{origin.label} ({origin.count})</option>
                    ))}
                  </select>

                  <div className="w-px h-8 bg-border/60 hidden sm:block" />

                  {/* Sort controls */}
                  <div className="flex items-center gap-1">
                    <select
                      value={sortKey}
                      onChange={(e) => setSortKey(e.target.value as 'updated_at' | 'nome' | 'status')}
                      className="px-3 py-2.5 bg-muted/30 border border-border rounded-xl text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/30 cursor-pointer [&>option]:text-gray-900 [&>option]:bg-white dark:[&>option]:text-gray-100 dark:[&>option]:bg-gray-900"
                    >
                      <option value="updated_at">Ordenar: Atualização</option>
                      <option value="nome">Ordenar: Nome</option>
                      <option value="status">Ordenar: Status</option>
                    </select>
                    <button
                      onClick={() => setSortDir((prev) => (prev === 'asc' ? 'desc' : 'asc'))}
                      className="p-2.5 bg-muted/30 border border-border rounded-xl text-foreground hover:bg-muted/60 hover:border-primary/40 transition-colors"
                      title={sortDir === 'asc' ? 'Crescente — clique para inverter' : 'Decrescente — clique para inverter'}
                    >
                      {sortDir === 'asc'
                        ? <ArrowUp className="w-4 h-4" />
                        : <ArrowDown className="w-4 h-4" />
                      }
                    </button>
                  </div>

                  {/* Result count + active filter indicators */}
                  <div className="flex items-center gap-2 ml-auto">
                    {(statusFilter !== 'all' || classificacaoFilter !== 'all' || corretorFilter || originFilter !== 'all' || tableSearch) && (
                      <button
                        onClick={() => { setStatusFilter('all'); setClassificacaoFilter('all'); setCorretorFilter(''); setOriginFilter('all'); setTableSearch(''); }}
                        className="text-[11px] text-muted-foreground hover:text-foreground underline underline-offset-2 transition-colors"
                      >
                        Limpar filtros
                      </button>
                    )}
                    <span className="text-xs text-muted-foreground px-2.5 py-1.5 bg-muted/40 rounded-lg border border-border whitespace-nowrap font-medium">
                      {tableMeta.total} resultado{tableMeta.total !== 1 ? 's' : ''}
                    </span>
                  </div>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                  {originStats.map((origin) => (
                    <button
                      key={origin.label}
                      onClick={() => setOriginFilter((current) => current === origin.label ? 'all' : origin.label)}
                      className={cn(
                        'px-2.5 py-1 rounded-full border text-xs font-medium transition-colors',
                        originFilter === origin.label
                          ? 'bg-emerald-500/15 border-emerald-500/40 text-emerald-400'
                          : 'bg-muted/30 border-border text-muted-foreground hover:text-foreground hover:border-border/80'
                      )}
                    >
                      {origin.label}: {origin.count}
                    </button>
                  ))}
                </div>
                <div className="overflow-hidden rounded-[24px] border border-white/10 bg-[#07111d]/62">
                  <ScrollArea className="max-h-[calc(100vh-24rem)]">
                    <table className="w-full text-left">
                      <thead className="sticky top-0 bg-card border-b border-border z-10">
                        <tr className="text-xs text-muted-foreground">
                          <th
                            className="px-4 py-3 text-left font-semibold uppercase tracking-wider cursor-pointer select-none hover:text-foreground transition-colors"
                            onClick={() => handleSort('nome')}
                          >
                            <div className="flex items-center gap-1.5">
                              Cliente
                              {sortKey === 'nome'
                                ? (sortDir === 'asc' ? <ArrowUp className="w-3 h-3 text-primary" /> : <ArrowDown className="w-3 h-3 text-primary" />)
                                : <ArrowUpDown className="w-3 h-3 opacity-25" />
                              }
                            </div>
                          </th>
                          <th className="px-4 py-3 text-left font-semibold uppercase tracking-wider">Telefone</th>
                          <th
                            className="px-4 py-3 text-left font-semibold uppercase tracking-wider cursor-pointer select-none hover:text-foreground transition-colors"
                            onClick={() => handleSort('status')}
                          >
                            <div className="flex items-center gap-1.5">
                              Status
                              {sortKey === 'status'
                                ? (sortDir === 'asc' ? <ArrowUp className="w-3 h-3 text-primary" /> : <ArrowDown className="w-3 h-3 text-primary" />)
                                : <ArrowUpDown className="w-3 h-3 opacity-25" />
                              }
                            </div>
                          </th>
                          <th className="px-4 py-3 text-left font-semibold uppercase tracking-wider">Última mensagem</th>
                          <th className="px-4 py-3 text-left font-semibold uppercase tracking-wider">Corretor</th>
                          <th className="px-4 py-3 text-left font-semibold uppercase tracking-wider">Origem</th>
                          <th
                            className="px-4 py-3 text-left font-semibold uppercase tracking-wider cursor-pointer select-none hover:text-foreground transition-colors"
                            onClick={() => handleSort('updated_at')}
                          >
                            <div className="flex items-center gap-1.5">
                              Atualizado
                              {sortKey === 'updated_at'
                                ? (sortDir === 'asc' ? <ArrowUp className="w-3 h-3 text-primary" /> : <ArrowDown className="w-3 h-3 text-primary" />)
                                : <ArrowUpDown className="w-3 h-3 opacity-25" />
                              }
                            </div>
                          </th>
                          <th className="px-4 py-3 text-right font-semibold uppercase tracking-wider">Ações</th>
                        </tr>
                      </thead>
                      <tbody>
                        {isLoadingTable ? (
                          <tr>
                            <td colSpan={8} className="px-3 py-8 text-center text-sm text-muted-foreground">
                              <Loader2 className="w-4 h-4 animate-spin inline-block mr-2" />
                              Carregando...
                            </td>
                          </tr>
                        ) : tableClients.length === 0 ? (
                          <tr>
                            <td colSpan={8} className="px-3 py-8 text-center text-sm text-muted-foreground">
                              Nenhum cliente encontrado
                            </td>
                          </tr>
                        ) : (
                          tableClients.map((client) => (
                            <DataRow
                              key={client.id}
                              client={client}
                              isSelected={selectedClientId === client.id}
                              onSelect={handleSelectClient}
                              onDelete={handleDeleteLead}
                              isDeleting={deletingLeadId === client.id}
                              onQuickViewProperty={handleOpenQuickView}
                            />
                          ))
                        )}
                      </tbody>
                    </table>
                  </ScrollArea>
                </div>
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <p className="text-xs text-muted-foreground">
                    Página <span className="font-semibold text-foreground">{tableMeta.current_page}</span> de <span className="font-semibold text-foreground">{tableMeta.last_page}</span>
                    <span className="mx-1.5 text-border">•</span>
                    Total <span className="font-semibold text-foreground">{tableMeta.total}</span>
                  </p>
                  <div className="flex items-center gap-2">
                    <select
                      value={tablePerPage}
                      onChange={(e) => setTablePerPage(Number(e.target.value))}
                      className="px-2 py-1.5 bg-muted/30 border border-border rounded-lg text-xs text-foreground focus:outline-none focus:ring-2 focus:ring-primary/30 cursor-pointer [&>option]:text-gray-900 [&>option]:bg-white dark:[&>option]:text-gray-100 dark:[&>option]:bg-gray-900"
                    >
                      {[25, 50, 100, 200].map((n) => (
                        <option key={n} value={n}>{n}/página</option>
                      ))}
                    </select>
                    <div className="flex items-center gap-1">
                      <button
                        onClick={() => setTablePage((p) => Math.max(1, p - 1))}
                        disabled={tableMeta.current_page <= 1}
                        className="px-3 py-1.5 bg-muted/30 border border-border rounded-lg text-xs text-foreground hover:bg-muted/60 hover:border-primary/30 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                      >
                        Anterior
                      </button>
                      {/* Page numbers (show max 5) */}
                      {Array.from({ length: Math.min(5, tableMeta.last_page) }, (_, i) => {
                        const startPage = Math.max(1, Math.min(tableMeta.current_page - 2, tableMeta.last_page - 4));
                        const page = startPage + i;
                        return (
                          <button
                            key={page}
                            onClick={() => setTablePage(page)}
                            className={cn(
                              'w-8 h-[30px] rounded-lg text-xs font-medium border transition-colors',
                              page === tableMeta.current_page
                                ? 'bg-primary text-primary-foreground border-primary'
                                : 'bg-muted/30 border-border text-foreground hover:bg-muted/60 hover:border-primary/30'
                            )}
                          >
                            {page}
                          </button>
                        );
                      })}
                      <button
                        onClick={() => setTablePage((p) => Math.min(tableMeta.last_page, p + 1))}
                        disabled={tableMeta.current_page >= tableMeta.last_page}
                        className="px-3 py-1.5 bg-muted/30 border border-border rounded-lg text-xs text-foreground hover:bg-muted/60 hover:border-primary/30 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                      >
                        Próxima
                      </button>
                    </div>
                  </div>
                </div>
              </div>
              {selectedClient && (
                <div className="w-[460px] xl:w-[520px] 2xl:w-[580px] min-h-0 shrink-0">
                  {renderConversationPanel('desktop')}
                </div>
              )}
              </div>
            </div>

            {/* Mobile: Tabs + List */}
            <div className="page-content flex flex-1 min-h-0 flex-col pt-4 lg:hidden">
              <div className="overflow-hidden rounded-[28px] border border-white/8 bg-[#0b1322]/82 shadow-[0_18px_42px_rgba(2,6,23,0.24)]">
              <div className="flex border-b border-border bg-card overflow-x-auto scrollbar-hide">
                {ALL_STATUSES.map((s) => {
                  const count = isMobile
                    ? (s === mobileStatus ? Number(mobileStatusData?.total || mobileClients.length || 0) : null)
                    : (crmData?.[s] || []).length;
                  const conf = STATUS_CONFIG[s];
                  return (
                    <button
                      key={s}
                      onClick={() => setMobileStatus(s)}
                      className={cn(
                        'flex-shrink-0 px-4 py-2.5 text-xs font-medium whitespace-nowrap transition-colors relative',
                        mobileStatus === s ? conf.color : 'text-muted-foreground'
                      )}
                    >
                      {count === null ? conf.label : `${conf.label} (${count})`}
                      {mobileStatus === s && <div className="absolute bottom-0 left-0 right-0 h-0.5 bg-current" />}
                    </button>
                  );
                })}
              </div>
              <ScrollArea className="flex-1 min-h-0">
                <div className="space-y-2 p-3">
                  {isLoadingMobileStatus && mobileClients.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-16 gap-3">
                      <Loader2 className="w-10 h-10 text-muted-foreground/50 animate-spin" />
                      <p className="text-sm text-muted-foreground">Carregando clientes...</p>
                    </div>
                  ) : mobileClients.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-16 gap-3">
                      <Users className="w-10 h-10 text-muted-foreground/30" />
                      <p className="text-sm text-muted-foreground">Nenhum cliente neste status</p>
                    </div>
                  ) : (
                    mobileClients.map((client) => (
                      <ClientCard
                        key={client.id}
                        client={client}
                        isSelected={selectedClientId === client.id}
                        onSelect={handleSelectClient}
                        onQuickViewProperty={handleOpenQuickView}
                      />
                    ))
                  )}
                </div>
              </ScrollArea>
              </div>
            </div>
          </>
        )}
      </div>

      {selectedClient && isMobile && (
        <div
          className="fixed inset-0 z-50 flex"
        >
          <div className="absolute inset-0 bg-black/40" onClick={() => setSelectedClient(null)} />
          <div className="relative ml-auto w-full h-full lg:w-[55%] xl:w-[50%]">
            {renderConversationPanel('mobile')}
          </div>
        </div>
      )}

      {quickViewProperty && (
        <div className="fixed inset-0 z-[80] flex items-center justify-center bg-black/70 p-4">
          <div className="absolute inset-0" onClick={() => setQuickViewProperty(null)} />
          <div className="relative w-full max-w-6xl h-[85vh] rounded-2xl border border-border bg-background shadow-2xl overflow-hidden">
            <div className="flex items-center justify-between gap-3 px-4 py-3 border-b border-border bg-card">
              <div className="min-w-0">
                <h3 className="text-sm font-semibold text-foreground truncate">{quickViewProperty.title}</h3>
                <p className="text-[11px] text-muted-foreground truncate">Visualização rápida do imóvel</p>
              </div>
              <div className="flex items-center gap-2 flex-shrink-0">
                <a
                  href={quickViewProperty.url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="inline-flex items-center gap-1.5 text-xs text-primary hover:underline"
                >
                  <ExternalLink className="w-3.5 h-3.5" />
                  Abrir em nova aba
                </a>
                <Button variant="ghost" size="icon" onClick={() => setQuickViewProperty(null)} title="Fechar pré-visualização">
                  <ChevronDown className="w-4 h-4 rotate-45" />
                </Button>
              </div>
            </div>
            <iframe
              src={quickViewProperty.url}
              title={quickViewProperty.title}
              className="w-full h-[calc(85vh-57px)] bg-white"
            />
          </div>
        </div>
      )}
    </div>
  );
}
