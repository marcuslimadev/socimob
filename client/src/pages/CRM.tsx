import { useState, useEffect, useRef, useMemo, useCallback, useLayoutEffect, memo } from 'react';
import {
  Search,
  Send,
  ArrowLeft,
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

type StatusKey = 'novo' | 'em_atendimento' | 'qualificado' | 'proposta' | 'fechado' | 'perdido';

const STATUS_CONFIG: Record<StatusKey, { label: string; color: string; bg: string }> = {
  novo: { label: 'Novo', color: 'text-blue-600 dark:text-blue-400', bg: 'bg-blue-500/10 border-blue-500/30' },
  em_atendimento: { label: 'Atendimento', color: 'text-amber-600 dark:text-amber-400', bg: 'bg-amber-500/10 border-amber-500/30' },
  qualificado: { label: 'Qualificado', color: 'text-purple-600 dark:text-purple-400', bg: 'bg-purple-500/10 border-purple-500/30' },
  proposta: { label: 'Proposta', color: 'text-cyan-600 dark:text-cyan-400', bg: 'bg-cyan-500/10 border-cyan-500/30' },
  fechado: { label: 'Fechado', color: 'text-green-600 dark:text-green-400', bg: 'bg-green-500/10 border-green-500/30' },
  perdido: { label: 'Perdido', color: 'text-red-600 dark:text-red-400', bg: 'bg-red-500/10 border-red-500/30' },
};

const ALL_STATUSES: StatusKey[] = ['novo', 'em_atendimento', 'qualificado', 'proposta', 'fechado', 'perdido'];

const createEmptyCRMData = (): Record<StatusKey, CRMClient[]> => ({
  novo: [],
  em_atendimento: [],
  qualificado: [],
  proposta: [],
  fechado: [],
  perdido: [],
});

function normalizeCRMData(raw: unknown): Record<StatusKey, CRMClient[]> {
  const normalized = createEmptyCRMData();

  if (!raw) return normalized;

  if (Array.isArray(raw)) {
    raw.forEach((item) => {
      if (!item) return;
      const status = ((item as CRMClient).status || 'novo') as StatusKey;
      normalized[status] = [...normalized[status], item as CRMClient];
    });
    return normalized;
  }

  if (typeof raw === 'object') {
    ALL_STATUSES.forEach((status) => {
      const bucket = (raw as Record<string, unknown>)[status];
      if (Array.isArray(bucket)) {
        normalized[status] = bucket as CRMClient[];
      } else if (bucket && Array.isArray((bucket as { data?: unknown }).data)) {
        normalized[status] = (bucket as { data: CRMClient[] }).data;
      }
    });
  }

  return normalized;
}

function normalizeFlatClients(raw: unknown): CRMClient[] {
  if (Array.isArray(raw)) return raw as CRMClient[];
  if (raw && typeof raw === 'object') {
    const values = Object.values(raw as Record<string, unknown>);
    const flattened: CRMClient[] = [];
    values.forEach((v) => {
      if (Array.isArray(v)) {
        flattened.push(...(v as CRMClient[]));
      } else if (v && Array.isArray((v as { data?: unknown }).data)) {
        flattened.push(...((v as { data: CRMClient[] }).data));
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

const ClientCard = memo(({ client, isSelected, onSelect }: {
  client: CRMClient;
  isSelected: boolean;
  onSelect: (client: CRMClient) => void;
}) => {
  const classif = client.classificacao;
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
          <p className="text-xs text-muted-foreground truncate mt-0.5">{client.telefone}</p>
          {client.ultima_mensagem && (
            <p className="text-xs text-muted-foreground/70 truncate mt-1">
              {truncateMsg(client.ultima_mensagem, 40)}
            </p>
          )}
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

const DataRow = memo(({ client, isSelected, onSelect }: {
  client: CRMClient;
  isSelected: boolean;
  onSelect: (client: CRMClient) => void;
}) => {
  const statusConf = STATUS_CONFIG[client.status as StatusKey] || STATUS_CONFIG.novo;
  return (
    <tr
      onClick={() => onSelect(client)}
      className={cn(
        'cursor-pointer border-b border-white/5 hover:bg-white/5 transition-colors',
        isSelected && 'bg-primary/10'
      )}
    >
      <td className="px-3 py-3">
        <div className="flex items-center gap-3">
          <Avatar className="w-8 h-8 flex-shrink-0">
            <AvatarFallback className="bg-primary/15 text-primary text-xs font-semibold">
              {getInitials(client.nome)}
            </AvatarFallback>
          </Avatar>
          <div className="min-w-0">
            <p className="text-sm font-semibold text-foreground truncate">{client.nome}</p>
            <p className="text-xs text-muted-foreground truncate">{client.email || '-'}</p>
          </div>
        </div>
      </td>
      <td className="px-3 py-3 text-xs text-muted-foreground whitespace-nowrap">{client.telefone}</td>
      <td className="px-3 py-3">
        <span className={cn('text-[10px] font-semibold px-2 py-1 rounded-full border', statusConf.bg, statusConf.color)}>
          {statusConf.label}
        </span>
      </td>
      <td className="px-3 py-3 text-xs text-muted-foreground truncate max-w-[240px]">
        {client.ultima_mensagem ? truncateMsg(client.ultima_mensagem, 60) : '-'}
      </td>
      <td className="px-3 py-3 text-xs text-muted-foreground">{client.corretor_nome || '-'}</td>
      <td className="px-3 py-3 text-xs text-muted-foreground">{client.origem || '-'}</td>
      <td className="px-3 py-3 text-xs text-muted-foreground whitespace-nowrap">
        {client.updated_at ? formatRelativeTime(client.updated_at) : '-'}
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
  const [activeTab, setActiveTab] = useState<'chat' | 'perfil'>('chat');
  const [mobileStatus, setMobileStatus] = useState<StatusKey>('novo');
  const [drawerDocked, setDrawerDocked] = useState(false);
  const [drawerCollapsed, setDrawerCollapsed] = useState(false);
  const [obsExpanded, setObsExpanded] = useState(true);
  const [tableSearch, setTableSearch] = useState('');
  const debouncedTableSearch = useDebounce(tableSearch, 400);
  const [statusFilter, setStatusFilter] = useState<StatusKey | 'all'>('all');
  const [classificacaoFilter, setClassificacaoFilter] = useState<'all' | 'quente' | 'morno' | 'frio'>('all');
  const [corretorFilter, setCorretorFilter] = useState<string>('');
  const [sortKey, setSortKey] = useState<'updated_at' | 'nome' | 'status'>('updated_at');
  const [sortDir, setSortDir] = useState<'asc' | 'desc'>('desc');
  const [corretores, setCorretores] = useState<Array<{ id: number; name: string; email?: string }>>([]);
  const [tablePage, setTablePage] = useState(1);
  const [tablePerPage, setTablePerPage] = useState(50);
  const [isMobile, setIsMobile] = useState(false);
  const [flatTableError, setFlatTableError] = useState(false);

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

  const { data: crmData = createEmptyCRMData(), isLoading, refetch } = useQuery<Record<StatusKey, CRMClient[]>>({
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
  });

  const { data: tableData, isLoading: isLoadingTable } = useQuery({
    queryKey: ['crm-clientes-table', debouncedTableSearch, corretorFilter, classificacaoFilter, statusFilter, sortKey, sortDir, tablePage, tablePerPage],
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
        ...raw,
        data: flat,
      };
    },
    keepPreviousData: true,
    onError: () => setFlatTableError(true),
    onSuccess: () => setFlatTableError(false),
  });

  const allClients = useMemo(() => {
    if (!crmData) return [];
    return ALL_STATUSES.flatMap((s) => Array.isArray(crmData[s]) ? crmData[s] : []);
  }, [crmData]);

  const tableClients = useMemo(() => {
    if (!flatTableError && Array.isArray(tableData?.data)) {
      return tableData.data as CRMClient[];
    }

    const term = tableSearch.trim().toLowerCase();
    let filtered = term
      ? allClients.filter((client) => (
          client.nome?.toLowerCase().includes(term) ||
          client.telefone?.toLowerCase().includes(term) ||
          (client.email || '').toLowerCase().includes(term) ||
          (client.corretor_nome || '').toLowerCase().includes(term) ||
          (client.origem || '').toLowerCase().includes(term)
        ))
      : allClients;
    if (statusFilter !== 'all') {
      filtered = filtered.filter((client) => (client.status || 'novo') === statusFilter);
    }
    if (classificacaoFilter !== 'all') {
      filtered = filtered.filter((client) => client.classificacao === classificacaoFilter);
    }
    if (corretorFilter) {
      filtered = filtered.filter((client) => String(client.corretor_id || '') === String(corretorFilter));
    }

    const sorted = [...filtered].sort((a, b) => {
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
  }, [flatTableError, tableData, allClients, tableSearch, statusFilter, classificacaoFilter, corretorFilter, sortKey, sortDir, tablePage, tablePerPage]);

  const tableMeta = useMemo(() => {
    if (!flatTableError && tableData) {
      return {
        total: tableData.total || 0,
        current_page: tableData.current_page || 1,
        last_page: tableData.last_page || 1,
        per_page: tableData.per_page || tablePerPage,
      };
    }
    const term = tableSearch.trim().toLowerCase();
    let filtered = term
      ? allClients.filter((client) => (
          client.nome?.toLowerCase().includes(term) ||
          client.telefone?.toLowerCase().includes(term) ||
          (client.email || '').toLowerCase().includes(term) ||
          (client.corretor_nome || '').toLowerCase().includes(term) ||
          (client.origem || '').toLowerCase().includes(term)
        ))
      : allClients;
    if (statusFilter !== 'all') {
      filtered = filtered.filter((client) => (client.status || 'novo') === statusFilter);
    }
    if (classificacaoFilter !== 'all') {
      filtered = filtered.filter((client) => client.classificacao === classificacaoFilter);
    }
    if (corretorFilter) {
      filtered = filtered.filter((client) => String(client.corretor_id || '') === String(corretorFilter));
    }
    const total = filtered.length;
    const last_page = Math.max(1, Math.ceil(total / tablePerPage));
    return {
      total,
      current_page: Math.min(tablePage, last_page),
      last_page,
      per_page: tablePerPage,
    };
  }, [flatTableError, tableData, allClients, tableSearch, statusFilter, classificacaoFilter, corretorFilter, tablePage, tablePerPage]);

  // ─── Callbacks (stable references) ─────────────────────────────────

  const handleSelectClient = useCallback((client: CRMClient) => {
    setSelectedClient(client);
    setActiveTab('chat');
    setDrawerCollapsed(false);
  }, []);

  const handleStatusChange = useCallback(async (clientId: number, newStatus: StatusKey) => {
    try {
      await api.patch(`/crm/clientes/${clientId}/status`, { status: newStatus });
      toast.success('Status atualizado');
      queryClient.invalidateQueries({ queryKey: ['crm-clientes'] });
      setSelectedClient((prev) => prev?.id === clientId ? { ...prev, status: newStatus } : prev);
    } catch {
      toast.error('Erro ao atualizar status');
    }
  }, [queryClient]);

  useEffect(() => {
    setTablePage(1);
  }, [debouncedTableSearch, statusFilter, classificacaoFilter, corretorFilter, sortKey, sortDir, tablePerPage]);

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
    const selectedDocIds = Object.entries(selectedDownloads)
      .filter(([key, isSelected]) => isSelected && key.startsWith('doc:'))
      .map(([key]) => Number(key.split(':')[1]))
      .filter((id) => Number.isFinite(id));

    if (!selectedDocIds.length) {
      toast.error('Selecione pelo menos um arquivo ou foto');
      return;
    }

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
      toast.success('Download iniciado');
    } catch (error) {
      console.error('Erro ao baixar selecionados:', error);
      toast.error('Erro ao baixar selecionados');
    }
  }, [selectedClient, selectedDownloads]);

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

  // Open/close drawer with chat
  useEffect(() => {
    if (chatIntervalRef.current) { window.clearInterval(chatIntervalRef.current); chatIntervalRef.current = null; }
    if (!selectedClient?.conversa_id) { setMessages([]); hasLoadedMessagesRef.current = false; return; }
    const conversaId = selectedClient.conversa_id;
    hasLoadedMessagesRef.current = false;
    fetchMessages(conversaId);
    chatIntervalRef.current = window.setInterval(() => fetchMessages(conversaId), 15000);
    return () => { if (chatIntervalRef.current) { window.clearInterval(chatIntervalRef.current); chatIntervalRef.current = null; } };
  }, [selectedClient?.id, selectedClient?.conversa_id, fetchMessages]);

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

  // ─── Render: Drawer content ────────────────────────────────────────

  const renderChatTab = () => {
    if (!selectedClient?.conversa_id) {
      return (
        <div className="flex-1 flex flex-col items-center justify-center gap-3 p-8">
          <MessageCircle className="w-12 h-12 text-muted-foreground/40" />
          <p className="text-sm text-muted-foreground text-center">Nenhuma conversa encontrada para este cliente.</p>
        </div>
      );
    }

    const rawObs = selectedClient.observacoes || selectedClient.pessoa?.observacoes || '';
    const rawObsCliente = selectedClient.observacoes_cliente || '';
    const observacoesParts = [
      normalizeObservacoes(rawObs),
      normalizeObservacoes(rawObsCliente),
    ].filter((value, index, arr) => value && arr.indexOf(value) === index);
    const observacoesText = observacoesParts.join('\n\n');

    const origemValue = selectedClient.origem || selectedClient.pessoa?.origem || '';
    const isChavesNaMao = origemValue === 'chaves_na_mao' || rawObs.toLowerCase().includes('chaves na m');
    const showObsPanel = !!(observacoesText || isChavesNaMao);

    return (
      <div className="flex-1 flex flex-col min-h-0">
        {showObsPanel && (
          <div className="border-b border-border bg-amber-500/5 flex-shrink-0">
            <button
              onClick={() => setObsExpanded((p) => !p)}
              className="w-full flex items-center gap-2 px-4 py-2 hover:bg-amber-500/10 transition-colors"
            >
              <Key className="w-3.5 h-3.5 text-amber-500 flex-shrink-0" />
              <span className="text-xs font-semibold text-amber-600 dark:text-amber-400 truncate">
                {isChavesNaMao ? 'Chaves na Mão' : 'Observações'}
              </span>
              {!obsExpanded && observacoesText && (
                <span className="text-[10px] text-muted-foreground truncate flex-1 text-left">
                  — {observacoesText.split('\n')[0]}
                </span>
              )}
              <span className="ml-auto flex-shrink-0 text-muted-foreground">
                {obsExpanded ? <Minimize2 className="w-3.5 h-3.5" /> : <Maximize2 className="w-3.5 h-3.5" />}
              </span>
            </button>
            {obsExpanded && (
              <div className="px-4 pb-2.5" style={{ paddingLeft: '1.375rem' }}>
                {observacoesText ? (
                  <p className="text-xs text-muted-foreground leading-relaxed whitespace-pre-line ml-4">
                    {observacoesText}
                  </p>
                ) : (
                  <p className="text-xs text-muted-foreground/60 italic ml-4">Sem observações registradas</p>
                )}
              </div>
            )}
          </div>
        )}
        <div className="flex-1 min-h-0 overflow-hidden">
          <ScrollArea ref={scrollAreaRef} className="h-full">
            <div className="p-4 space-y-4">
              {isLoadingMessages ? (
                <div className="flex justify-center py-8"><Loader2 className="w-6 h-6 animate-spin text-primary" /></div>
              ) : messages.length === 0 ? (
                <div className="flex flex-col items-center justify-center py-12 gap-3">
                  <MessageCircle className="w-10 h-10 text-muted-foreground/40" />
                  <p className="text-sm text-muted-foreground">Nenhuma mensagem</p>
                </div>
              ) : (
                groupedMessages.map((group) => (
                  <div key={group.date} className="space-y-2">
                    <div className="flex justify-center py-2">
                      <span className="px-3 py-1 bg-muted/60 rounded-full text-[11px] text-muted-foreground font-medium">{group.date}</span>
                    </div>
                    {group.messages.map((message) => {
                      const isUser = message.sender === 'user';
                      return (
                        <div key={message.id} className={cn('flex gap-2 items-end', isUser ? 'justify-end' : 'justify-start')}>
                          {!isUser && (
                            <Avatar className="w-7 h-7 flex-shrink-0">
                              <AvatarFallback className="bg-muted/50 text-muted-foreground text-[10px]"><User className="w-3.5 h-3.5" /></AvatarFallback>
                            </Avatar>
                          )}
                          <div className={cn('max-w-[80%]', isUser && 'flex flex-col items-end')}>
                            <div className={cn('px-3 py-2 rounded-2xl shadow-sm text-sm', isUser ? 'bg-primary/10 text-foreground rounded-br-sm border border-primary/20' : 'bg-muted text-foreground rounded-bl-sm border border-border')}>
                              {message.senderName && (
                                <div className={cn("text-[10px] font-medium opacity-70 mb-1", message.senderName === 'Assistente IA' ? 'text-blue-600 dark:text-blue-400' : 'text-foreground')}>
                                  {message.senderName}
                                </div>
                              )}
                              {isAudioMessage(message) && message.mediaUrl && <audio controls className="w-full max-w-[250px] mb-1"><source src={getMediaUrl(message.mediaUrl)} /></audio>}
                              {isImageMessage(message) && message.mediaUrl && <img src={getMediaUrl(message.mediaUrl)} alt="" loading="lazy" className="max-w-[250px] rounded-lg border border-border mb-1" />}
                              {isVideoMessage(message) && message.mediaUrl && <video controls className="max-w-[250px] rounded-lg mb-1" preload="metadata"><source src={getMediaUrl(message.mediaUrl)} /></video>}
                              {isDocumentMessage(message) && message.mediaUrl && (
                                <a href={getMediaUrl(message.mediaUrl)} target="_blank" rel="noopener noreferrer" className="flex items-center gap-2 p-2 rounded-lg bg-muted/30 border border-border mb-1 hover:bg-muted/50 transition-colors">
                                  <FileText className="w-4 h-4 text-primary" /><span className="text-xs">Documento</span><ExternalLink className="w-3 h-3 text-muted-foreground ml-auto" />
                                </a>
                              )}
                              {message.text && <p className="whitespace-pre-wrap break-words leading-relaxed">{formatHtmlMessage(message.text)}</p>}
                              {message.messageType === 'audio' && message.transcription && <p className="mt-1 text-[11px] text-muted-foreground italic">{message.transcription}</p>}
                            </div>
                            <div className={cn('flex items-center gap-1 mt-0.5 px-1', isUser ? 'justify-end' : 'justify-start')}>
                              <span className="text-[10px] text-muted-foreground">{message.timestamp}</span>
                              {isUser && <MessageStatusIcon status={message.status} />}
                            </div>
                          </div>
                        </div>
                      );
                    })}
                  </div>
                ))
              )}
              <div ref={messagesEndRef} />
            </div>
          </ScrollArea>
        </div>

        <div className="p-3 border-t border-border bg-card">
          <div className="flex items-end gap-2">
            <div className="flex-1 relative">
              <input
                ref={inputRef}
                type="text"
                value={messageText}
                onChange={(e) => setMessageText(e.target.value)}
                onKeyDown={(e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); handleSendMessage(); } }}
                placeholder="Digite uma mensagem..."
                className="w-full px-3 py-2.5 bg-muted/40 border border-border rounded-2xl text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary/30"
                disabled={isSending}
              />
            </div>
            <Button onClick={handleSendMessage} disabled={!messageText.trim() || isSending} size="icon" className="flex-shrink-0 w-10 h-10 rounded-full bg-primary hover:bg-primary/90 text-primary-foreground">
              {isSending ? <Loader2 className="w-4 h-4 animate-spin" /> : <Send className="w-4 h-4" />}
            </Button>
          </div>
        </div>
      </div>
    );
  };

  const renderPerfilTab = () => {
    if (!selectedClient) return null;
    const p = selectedClient.pessoa;
    const imageDocs = documents.filter((doc) => doc.mime_type?.startsWith('image/'));
    const fileDocs = documents.filter((doc) => !doc.mime_type?.startsWith('image/'));
    const photoItems = imageDocs
      .map((doc) => ({ id: `doc:${doc.id}`, url: getMediaUrl(doc.arquivo_url), label: doc.nome }))
      .filter((item) => item.url);
    const fileItems = fileDocs.map((doc) => ({ id: `doc:${doc.id}`, url: getMediaUrl(doc.arquivo_url), label: doc.nome, mime: doc.mime_type }));
    const allSelectableIds = [...fileItems.map((f) => f.id), ...photoItems.map((p) => p.id)];
    const allSelected = allSelectableIds.length > 0 && allSelectableIds.every((id) => selectedDownloads[id]);

    return (
      <ScrollArea className="flex-1">
        <div className="p-4 space-y-4">
          <div className="space-y-3">
            <h4 className="text-xs uppercase tracking-wider text-muted-foreground font-semibold">Dados do Lead</h4>
            <div className="grid grid-cols-2 gap-3">
              <InfoField label="Nome" value={selectedClient.nome} />
              <InfoField label="Telefone" value={selectedClient.telefone} />
              <InfoField label="E-mail" value={selectedClient.email} />
              <InfoField label="Classificacao" value={selectedClient.classificacao} />
              <InfoField label="Corretor" value={selectedClient.corretor_nome} />
              <InfoField label="Origem" value={selectedClient.origem} />
              {selectedClient.valor && <InfoField label="Valor" value={`R$ ${selectedClient.valor.toLocaleString('pt-BR')}`} />}
            </div>
          </div>
          {p && (
            <div className="space-y-3 pt-4 border-t border-border">
              <h4 className="text-xs uppercase tracking-wider text-muted-foreground font-semibold">Dados da Pessoa</h4>
              <div className="grid grid-cols-2 gap-3">
                <InfoField label="Nome" value={p.nome} />
                <InfoField label="Tipo" value={p.tipo} />
                <InfoField label="CPF" value={p.cpf} />
                <InfoField label="E-mail" value={p.email} />
                <InfoField label="Telefone" value={p.telefone} />
                <InfoField label="Celular" value={p.celular} />
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
                        <p className="text-xs text-muted-foreground">{doc.mime}</p>
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
                        className="absolute top-2 left-2 h-4 w-4 rounded border-border bg-background/80"
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

  const totalClients = allClients.length;
  const totalUnread = allClients.reduce((sum, c) => sum + c.unread, 0);
  const selectedClientId = selectedClient?.id ?? null;

  return (
    <div className="flex h-screen overflow-hidden bg-background text-foreground">
      <Sidebar />

      <div className="relative flex-1 min-h-0 flex flex-col md:ml-80">
        {/* Top Bar */}
        <div className="border-b border-border bg-card px-4 py-3">
          <div className="flex items-center gap-3 flex-wrap">
            <div className="flex items-center gap-2">
              <Users className="w-5 h-5 text-primary" />
              <h1 className="text-lg font-bold text-foreground">CRM</h1>
              <span className="text-xs text-muted-foreground bg-muted px-2 py-0.5 rounded-full">{totalClients}</span>
              {totalUnread > 0 && (
                <span className="text-[10px] font-bold bg-primary text-primary-foreground px-1.5 py-0.5 rounded-full">{totalUnread}</span>
              )}
            </div>
            <div className="relative flex-1 max-w-sm">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground w-4 h-4" />
              <input
                type="text"
                value={search}
                onChange={(e) => setSearch(e.target.value)}
                placeholder="Buscar cliente..."
                className="w-full pl-9 pr-3 py-2 bg-muted/40 border border-border rounded-xl text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary/30"
              />
            </div>
            <Button variant="ghost" size="icon" onClick={() => refetch()} className="text-muted-foreground hover:text-foreground">
              <RefreshCw className={cn('w-4 h-4', isLoading && 'animate-spin')} />
            </Button>
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
            <div className="hidden lg:flex flex-1 min-h-0 p-4">
              <div className="w-full flex flex-col gap-3">
                <div className="flex flex-wrap items-center gap-3">
                  <input
                    type="text"
                    value={tableSearch}
                    onChange={(e) => setTableSearch(e.target.value)}
                    placeholder="Buscar clientes..."
                    className="w-full max-w-md px-4 py-2.5 bg-white/5 border border-white/10 rounded-xl text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary/30"
                  />
                  <select
                    value={statusFilter}
                    onChange={(e) => setStatusFilter(e.target.value as StatusKey | 'all')}
                    className="px-3 py-2.5 bg-white/5 border border-white/10 rounded-xl text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/30 [&>option]:text-gray-900 [&>option]:bg-white dark:[&>option]:text-gray-100 dark:[&>option]:bg-gray-900"
                  >
                    <option value="all">Todos os status</option>
                    {ALL_STATUSES.map((s) => (
                      <option key={s} value={s}>{STATUS_CONFIG[s].label}</option>
                    ))}
                  </select>
                  <select
                    value={classificacaoFilter}
                    onChange={(e) => setClassificacaoFilter(e.target.value as 'all' | 'quente' | 'morno' | 'frio')}
                    className="px-3 py-2.5 bg-white/5 border border-white/10 rounded-xl text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/30 [&>option]:text-gray-900 [&>option]:bg-white dark:[&>option]:text-gray-100 dark:[&>option]:bg-gray-900"
                  >
                    <option value="all">Classificação</option>
                    <option value="quente">Quente</option>
                    <option value="morno">Morno</option>
                    <option value="frio">Frio</option>
                  </select>
                  {corretores.length > 0 && (
                    <select
                      value={corretorFilter}
                      onChange={(e) => setCorretorFilter(e.target.value)}
                      className="px-3 py-2.5 bg-white/5 border border-white/10 rounded-xl text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/30 [&>option]:text-gray-900 [&>option]:bg-white dark:[&>option]:text-gray-100 dark:[&>option]:bg-gray-900"
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
                    value={sortKey}
                    onChange={(e) => setSortKey(e.target.value as 'updated_at' | 'nome' | 'status')}
                    className="px-3 py-2.5 bg-white/5 border border-white/10 rounded-xl text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-primary/30 [&>option]:text-gray-900 [&>option]:bg-white dark:[&>option]:text-gray-100 dark:[&>option]:bg-gray-900"
                  >
                    <option value="updated_at">Ordenar: Atualização</option>
                    <option value="nome">Ordenar: Nome</option>
                    <option value="status">Ordenar: Status</option>
                  </select>
                  <button
                    onClick={() => setSortDir((prev) => (prev === 'asc' ? 'desc' : 'asc'))}
                    className="px-3 py-2.5 bg-white/5 border border-white/10 rounded-xl text-sm text-foreground hover:bg-white/10 transition-colors"
                    title="Alternar ordem"
                  >
                    {sortDir === 'asc' ? '↑' : '↓'}
                  </button>
                  <span className="text-xs text-muted-foreground">
                    {tableMeta.total} resultado(s)
                  </span>
                </div>
                <div className="rounded-xl border border-white/10 overflow-hidden">
                  <ScrollArea className="max-h-[calc(100vh-240px)]">
                    <table className="w-full text-left">
                      <thead className="sticky top-0 bg-background/80 backdrop-blur border-b border-white/10">
                        <tr className="text-xs uppercase tracking-wider text-muted-foreground">
                          <th className="px-3 py-3">Cliente</th>
                          <th className="px-3 py-3">Telefone</th>
                          <th className="px-3 py-3">Status</th>
                          <th className="px-3 py-3">Última mensagem</th>
                          <th className="px-3 py-3">Corretor</th>
                          <th className="px-3 py-3">Origem</th>
                          <th className="px-3 py-3">Atualizado</th>
                        </tr>
                      </thead>
                      <tbody>
                        {isLoadingTable ? (
                          <tr>
                            <td colSpan={7} className="px-3 py-8 text-center text-sm text-muted-foreground">
                              <Loader2 className="w-4 h-4 animate-spin inline-block mr-2" />
                              Carregando...
                            </td>
                          </tr>
                        ) : tableClients.length === 0 ? (
                          <tr>
                            <td colSpan={7} className="px-3 py-8 text-center text-sm text-muted-foreground">
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
                            />
                          ))
                        )}
                      </tbody>
                    </table>
                  </ScrollArea>
                </div>
                <div className="flex flex-wrap items-center justify-between gap-3 text-xs text-muted-foreground">
                  <div>
                    Página {tableMeta.current_page} de {tableMeta.last_page} • Total {tableMeta.total}
                  </div>
                  <div className="flex items-center gap-2">
                    <select
                      value={tablePerPage}
                      onChange={(e) => setTablePerPage(Number(e.target.value))}
                      className="px-2 py-1.5 bg-white/5 border border-white/10 rounded-lg text-xs text-foreground focus:outline-none focus:ring-2 focus:ring-primary/30 [&>option]:text-gray-900 [&>option]:bg-white dark:[&>option]:text-gray-100 dark:[&>option]:bg-gray-900"
                    >
                      {[25, 50, 100, 200].map((n) => (
                        <option key={n} value={n}>{n}/página</option>
                      ))}
                    </select>
                    <button
                      onClick={() => setTablePage((p) => Math.max(1, p - 1))}
                      disabled={tableMeta.current_page <= 1}
                      className="px-3 py-1.5 bg-white/5 border border-white/10 rounded-lg text-xs text-foreground disabled:opacity-50"
                    >
                      Anterior
                    </button>
                    <button
                      onClick={() => setTablePage((p) => Math.min(tableMeta.last_page, p + 1))}
                      disabled={tableMeta.current_page >= tableMeta.last_page}
                      className="px-3 py-1.5 bg-white/5 border border-white/10 rounded-lg text-xs text-foreground disabled:opacity-50"
                    >
                      Próxima
                    </button>
                  </div>
                </div>
              </div>
            </div>

            {/* Mobile: Tabs + List */}
            <div className="lg:hidden flex-1 min-h-0 flex flex-col">
              <div className="flex border-b border-border bg-card overflow-x-auto scrollbar-hide">
                {ALL_STATUSES.map((s) => {
                  const count = (crmData?.[s] || []).length;
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
                      {conf.label} ({count})
                      {mobileStatus === s && <div className="absolute bottom-0 left-0 right-0 h-0.5 bg-current" />}
                    </button>
                  );
                })}
              </div>
              <ScrollArea className="flex-1 min-h-0">
                <div className="p-3 space-y-2">
                  {(crmData?.[mobileStatus] || []).length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-16 gap-3">
                      <Users className="w-10 h-10 text-muted-foreground/30" />
                      <p className="text-sm text-muted-foreground">Nenhum cliente neste status</p>
                    </div>
                  ) : (
                    (crmData?.[mobileStatus] || []).map((client) => (
                      <ClientCard key={client.id} client={client} isSelected={selectedClientId === client.id} onSelect={handleSelectClient} />
                    ))
                  )}
                </div>
              </ScrollArea>
            </div>
          </>
        )}
      </div>

      {/* Drawer */}
      {selectedClient && (
        <div
          className={cn(
            'fixed z-50',
            drawerDocked || isMobile
              ? 'inset-0 flex'
              : 'bottom-4 right-4 w-[380px] max-w-[90vw] h-[70vh] max-h-[720px]'
          )}
        >
          {(drawerDocked || isMobile) && (
            <div className="absolute inset-0 bg-black/40 lg:bg-black/20" onClick={() => setSelectedClient(null)} />
          )}

            <div
              className={cn(
              'relative bg-background border border-border flex flex-col overflow-hidden',
              (drawerDocked || isMobile)
                ? 'ml-auto w-full h-full lg:w-[55%] xl:w-[50%] rounded-none border-l'
                : 'shadow-2xl rounded-2xl h-full max-h-[80vh]'
              )}
            >
            {/* Header */}
            <div className="flex items-center gap-3 px-4 py-3 border-b border-border bg-card">
              {drawerDocked ? (
                <Button variant="ghost" size="icon" onClick={() => setSelectedClient(null)} className="flex-shrink-0">
                  <ArrowLeft className="w-5 h-5" />
                </Button>
              ) : (
                <div className="w-9 h-9 flex items-center justify-center rounded-full bg-primary/10 text-primary text-sm font-semibold">
                  {getInitials(selectedClient.nome)}
                </div>
              )}

              <div className="flex-1 min-w-0">
                <h2 className="font-semibold text-foreground truncate">{selectedClient.nome}</h2>
                <p className="text-xs text-muted-foreground truncate">{selectedClient.telefone}</p>
              </div>

              <div className="flex items-center gap-1">
                {!isMobile && (
                  <Button
                    variant="ghost"
                    size="icon"
                    className="w-8 h-8 text-muted-foreground hover:text-foreground"
                    onClick={() => setDrawerDocked((prev) => !prev)}
                    title={drawerDocked ? 'Flutuar' : 'Encaixar'}
                  >
                    {drawerDocked ? <Minimize2 className="w-4 h-4" /> : <Maximize2 className="w-4 h-4" />}
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
              </div>
            </div>

            {!drawerCollapsed && (
              <>
                {/* Tabs */}
                <div className="flex border-b border-border bg-card">
                  {(['chat', 'perfil'] as const).map((tab) => (
                    <button key={tab} onClick={() => setActiveTab(tab)} className={cn('flex-1 py-2.5 text-sm font-medium transition-colors relative', activeTab === tab ? 'text-primary' : 'text-muted-foreground hover:text-foreground')}>
                      {tab === 'chat' ? 'Chat' : 'Perfil'}
                      {activeTab === tab && <div className="absolute bottom-0 left-0 right-0 h-0.5 bg-primary" />}
                    </button>
                  ))}
                </div>
                {/* Tab content */}
                <div className="flex-1 min-h-0 flex flex-col overflow-hidden">
                  {activeTab === 'chat' ? renderChatTab() : renderPerfilTab()}
                </div>
              </>
            )}
          </div>
        </div>
      )}
    </div>
  );
}
