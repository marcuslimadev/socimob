import { useState, useEffect, useRef, useMemo, useCallback, useLayoutEffect } from 'react';
import {
  Search,
  Phone,
  Send,
  Paperclip,
  ArrowLeft,
  Check,
  CheckCheck,
  Clock,
  Loader2,
  User,
  MessageCircle,
  X,
  ChevronDown,
  FileText,
  ExternalLink,
  Tag,
  Users,
  Plus,
  RefreshCw,
  Flame,
  Thermometer,
  Snowflake,
  MoreVertical,
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

// ─── Component ───────────────────────────────────────────────────────

export default function CRM() {
  const queryClient = useQueryClient();

  // State
  const [search, setSearch] = useState('');
  const [selectedClient, setSelectedClient] = useState<CRMClient | null>(null);
  const [activeTab, setActiveTab] = useState<'chat' | 'perfil'>('chat');
  const [mobileStatus, setMobileStatus] = useState<StatusKey>('novo');

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

  // ─── Fetch CRM data ───────────────────────────────────────────────

  const { data: crmData, isLoading, refetch } = useQuery({
    queryKey: ['crm-clientes', search],
    queryFn: async () => {
      const params: any = {};
      if (search) params.search = search;
      const res = await api.get('/crm/clientes', { params });
      if (res.data.success) return res.data.data as Record<StatusKey, CRMClient[]>;
      return {} as Record<StatusKey, CRMClient[]>;
    },
    refetchInterval: 30000,
    staleTime: 15000,
  });

  const allClients = useMemo(() => {
    if (!crmData) return [];
    return ALL_STATUSES.flatMap((s) => crmData[s] || []);
  }, [crmData]);

  // ─── Status update ─────────────────────────────────────────────────

  const handleStatusChange = async (clientId: number, newStatus: StatusKey) => {
    try {
      await api.patch(`/crm/clientes/${clientId}/status`, { status: newStatus });
      toast.success('Status atualizado');
      queryClient.invalidateQueries({ queryKey: ['crm-clientes'] });
      if (selectedClient?.id === clientId) {
        setSelectedClient((prev) => prev ? { ...prev, status: newStatus } : null);
      }
    } catch {
      toast.error('Erro ao atualizar status');
    }
  };

  // ─── Chat logic (adapted from Chat.tsx) ────────────────────────────

  const getScrollViewport = useCallback(() => {
    if (!scrollAreaRef.current) return null;
    return scrollAreaRef.current.querySelector('[data-radix-scroll-area-viewport]') as HTMLDivElement | null;
  }, []);

  const scrollToBottom = useCallback(() => {
    setTimeout(() => {
      messagesEndRef.current?.scrollIntoView({ behavior: 'auto' });
    }, 50);
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

  const applyMessagesWithScrollPreserve = (incoming: Message[]) => {
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
      const pending = prev.filter((m) => m.id.startsWith('temp-'));
      if (pending.length) { hasChanges = true; merged.push(...pending); merged.sort((a, b) => a.rawDate.getTime() - b.rawDate.getTime()); }
      if (!hasChanges) { pendingScrollRestoreRef.current = null; return prev; }
      if (snapshot) pendingScrollRestoreRef.current = snapshot;
      return merged;
    });
  };

  const fetchMessages = async (conversaId: number) => {
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
  };

  const handleSendMessage = async () => {
    if (!messageText.trim() || !selectedClient?.conversa_id || isSending) return;
    const text = messageText.trim();
    setMessageText('');
    setIsSending(true);

    const tempId = `temp-${Date.now()}`;
    const newMsg: Message = { id: tempId, sender: 'user', text, timestamp: formatTime(new Date().toISOString()), rawDate: new Date(), read: false, status: 'sending' };
    setMessages((prev) => [...prev, newMsg]);
    scrollToBottom();

    try {
      const res = await api.post(`/admin/conversas/${selectedClient.conversa_id}/mensagens`, { content: text });
      if (!res.data.success) throw new Error();
      setMessages((prev) => prev.map((m) => m.id === tempId ? { ...m, id: res.data.data?.id || tempId, status: 'sent' } : m));
      setTimeout(() => selectedClient.conversa_id && fetchMessages(selectedClient.conversa_id), 1000);
    } catch {
      toast.error('Erro ao enviar mensagem');
      setMessages((prev) => prev.filter((m) => m.id !== tempId));
    } finally {
      setIsSending(false);
      inputRef.current?.focus();
    }
  };

  // Open/close drawer with chat
  useEffect(() => {
    if (chatIntervalRef.current) { window.clearInterval(chatIntervalRef.current); chatIntervalRef.current = null; }
    if (!selectedClient?.conversa_id) { setMessages([]); hasLoadedMessagesRef.current = false; return; }
    hasLoadedMessagesRef.current = false;
    fetchMessages(selectedClient.conversa_id);
    chatIntervalRef.current = window.setInterval(() => {
      if (selectedClient.conversa_id) fetchMessages(selectedClient.conversa_id);
    }, 15000);
    return () => { if (chatIntervalRef.current) { window.clearInterval(chatIntervalRef.current); chatIntervalRef.current = null; } };
  }, [selectedClient?.id, selectedClient?.conversa_id]);

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

  // Media helpers
  const isAudioMessage = (m: Message) => m.mediaUrl && (m.messageType === 'audio' || m.messageType === 'voice' || /\.(mp3|ogg|wav|m4a|opus)(\?|$)/i.test(m.mediaUrl));
  const isImageMessage = (m: Message) => m.mediaUrl && (m.messageType === 'image' || m.messageType === 'photo' || /\.(png|jpe?g|gif|webp)(\?|$)/i.test(m.mediaUrl));
  const isVideoMessage = (m: Message) => m.mediaUrl && m.messageType === 'video';
  const isDocumentMessage = (m: Message) => m.mediaUrl && (m.messageType === 'document' || m.messageType === 'file' || /\.(pdf|doc|docx|xls|xlsx)(\?|$)/i.test(m.mediaUrl));

  const MessageStatus = ({ status }: { status?: string }) => {
    if (status === 'sending') return <Clock className="w-3 h-3 text-muted-foreground" />;
    if (status === 'sent') return <Check className="w-3 h-3 text-muted-foreground" />;
    if (status === 'delivered') return <CheckCheck className="w-3 h-3 text-muted-foreground" />;
    if (status === 'read') return <CheckCheck className="w-3 h-3 text-primary" />;
    return null;
  };

  const formatHtmlMessage = (html: string) => {
    return html.replace(/<br\s*\/?>/gi, '\n').replace(/<[^>]+>/g, '');
  };

  // ─── Client Card ───────────────────────────────────────────────────

  const ClientCard = ({ client }: { client: CRMClient }) => {
    const classif = client.classificacao;
    return (
      <button
        onClick={() => { setSelectedClient(client); setActiveTab('chat'); }}
        className={cn(
          'w-full text-left p-3 rounded-xl border transition-all hover:shadow-md cursor-pointer',
          'bg-card border-border hover:border-primary/30',
          selectedClient?.id === client.id && 'ring-2 ring-primary/50 border-primary/50'
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
  };

  // ─── Kanban Column ─────────────────────────────────────────────────

  const KanbanColumn = ({ status }: { status: StatusKey }) => {
    const config = STATUS_CONFIG[status];
    const clients = crmData?.[status] || [];
    return (
      <div className="flex-shrink-0 w-[280px] flex flex-col h-full">
        <div className={cn('flex items-center gap-2 px-3 py-2 rounded-t-xl border', config.bg)}>
          <span className={cn('text-sm font-semibold', config.color)}>{config.label}</span>
          <span className={cn('text-xs font-bold px-1.5 py-0.5 rounded-full', config.bg, config.color)}>
            {clients.length}
          </span>
        </div>
        <ScrollArea className="flex-1 min-h-0">
          <div className="p-2 space-y-2">
            {clients.length === 0 ? (
              <p className="text-xs text-muted-foreground text-center py-8">Nenhum cliente</p>
            ) : (
              clients.map((client) => <ClientCard key={client.id} client={client} />)
            )}
          </div>
        </ScrollArea>
      </div>
    );
  };

  // ─── Drawer: Chat Tab ──────────────────────────────────────────────

  const ChatTab = () => {
    if (!selectedClient?.conversa_id) {
      return (
        <div className="flex-1 flex flex-col items-center justify-center gap-3 p-8">
          <MessageCircle className="w-12 h-12 text-muted-foreground/40" />
          <p className="text-sm text-muted-foreground text-center">
            Nenhuma conversa encontrada para este cliente.
          </p>
        </div>
      );
    }

    return (
      <div className="flex-1 flex flex-col min-h-0">
        {/* Messages */}
        <div className="flex-1 min-h-0 overflow-hidden">
          <ScrollArea ref={scrollAreaRef} className="h-full">
            <div className="p-4 space-y-4">
              {isLoadingMessages ? (
                <div className="flex justify-center py-8">
                  <Loader2 className="w-6 h-6 animate-spin text-primary" />
                </div>
              ) : messages.length === 0 ? (
                <div className="flex flex-col items-center justify-center py-12 gap-3">
                  <MessageCircle className="w-10 h-10 text-muted-foreground/40" />
                  <p className="text-sm text-muted-foreground">Nenhuma mensagem</p>
                </div>
              ) : (
                groupedMessages.map((group) => (
                  <div key={group.date} className="space-y-2">
                    <div className="flex justify-center py-2">
                      <span className="px-3 py-1 bg-muted/60 rounded-full text-[11px] text-muted-foreground font-medium">
                        {group.date}
                      </span>
                    </div>
                    {group.messages.map((message) => {
                      const isUser = message.sender === 'user';
                      return (
                        <div key={message.id} className={cn('flex gap-2 items-end', isUser ? 'justify-end' : 'justify-start')}>
                          {!isUser && (
                            <Avatar className="w-7 h-7 flex-shrink-0">
                              <AvatarFallback className="bg-muted/50 text-muted-foreground text-[10px]">
                                <User className="w-3.5 h-3.5" />
                              </AvatarFallback>
                            </Avatar>
                          )}
                          <div className={cn('max-w-[80%]', isUser && 'flex flex-col items-end')}>
                            <div className={cn(
                              'px-3 py-2 rounded-2xl shadow-sm text-sm',
                              isUser ? 'bg-primary/10 text-foreground rounded-br-sm border border-primary/20' : 'bg-muted text-foreground rounded-bl-sm border border-border'
                            )}>
                              {message.senderName && (
                                <div className={cn(
                                  "text-[10px] font-medium opacity-70 mb-1",
                                  message.senderName === 'Assistente IA' ? 'text-blue-600 dark:text-blue-400' : 'text-foreground'
                                )}>
                                  {message.senderName}
                                </div>
                              )}
                              {isAudioMessage(message) && message.mediaUrl && (
                                <audio controls className="w-full max-w-[250px] mb-1"><source src={getMediaUrl(message.mediaUrl)} /></audio>
                              )}
                              {isImageMessage(message) && message.mediaUrl && (
                                <img src={getMediaUrl(message.mediaUrl)} alt="" loading="lazy" className="max-w-[250px] rounded-lg border border-border mb-1" />
                              )}
                              {isVideoMessage(message) && message.mediaUrl && (
                                <video controls className="max-w-[250px] rounded-lg mb-1" preload="metadata"><source src={getMediaUrl(message.mediaUrl)} /></video>
                              )}
                              {isDocumentMessage(message) && message.mediaUrl && (
                                <a href={getMediaUrl(message.mediaUrl)} target="_blank" rel="noopener noreferrer" className="flex items-center gap-2 p-2 rounded-lg bg-muted/30 border border-border mb-1 hover:bg-muted/50 transition-colors">
                                  <FileText className="w-4 h-4 text-primary" />
                                  <span className="text-xs">Documento</span>
                                  <ExternalLink className="w-3 h-3 text-muted-foreground ml-auto" />
                                </a>
                              )}
                              {message.text && (
                                <p className="whitespace-pre-wrap break-words leading-relaxed">{formatHtmlMessage(message.text)}</p>
                              )}
                              {message.messageType === 'audio' && message.transcription && (
                                <p className="mt-1 text-[11px] text-muted-foreground italic">{message.transcription}</p>
                              )}
                            </div>
                            <div className={cn('flex items-center gap-1 mt-0.5 px-1', isUser ? 'justify-end' : 'justify-start')}>
                              <span className="text-[10px] text-muted-foreground">{message.timestamp}</span>
                              {isUser && <MessageStatus status={message.status} />}
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

        {/* Input */}
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
            <Button
              onClick={handleSendMessage}
              disabled={!messageText.trim() || isSending}
              size="icon"
              className="flex-shrink-0 w-10 h-10 rounded-full bg-primary hover:bg-primary/90 text-primary-foreground"
            >
              {isSending ? <Loader2 className="w-4 h-4 animate-spin" /> : <Send className="w-4 h-4" />}
            </Button>
          </div>
        </div>
      </div>
    );
  };

  // ─── Drawer: Profile Tab ───────────────────────────────────────────

  const PerfilTab = () => {
    if (!selectedClient) return null;
    const p = selectedClient.pessoa;
    return (
      <ScrollArea className="flex-1">
        <div className="p-4 space-y-4">
          {/* Lead info */}
          <div className="space-y-3">
            <h4 className="text-xs uppercase tracking-wider text-muted-foreground font-semibold">Dados do Lead</h4>
            <div className="grid grid-cols-2 gap-3">
              <InfoField label="Nome" value={selectedClient.nome} />
              <InfoField label="Telefone" value={selectedClient.telefone} />
              <InfoField label="E-mail" value={selectedClient.email} />
              <InfoField label="Classificação" value={selectedClient.classificacao} />
              <InfoField label="Corretor" value={selectedClient.corretor_nome} />
              <InfoField label="Origem" value={selectedClient.origem} />
              {selectedClient.valor && <InfoField label="Valor" value={`R$ ${selectedClient.valor.toLocaleString('pt-BR')}`} />}
            </div>
          </div>

          {/* Pessoa info */}
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
        </div>
      </ScrollArea>
    );
  };

  const InfoField = ({ label, value }: { label: string; value: string | null | undefined }) => (
    <div>
      <p className="text-[11px] text-muted-foreground">{label}</p>
      <p className="text-sm text-foreground font-medium">{value || '-'}</p>
    </div>
  );

  // ─── Drawer ────────────────────────────────────────────────────────

  const ClientDrawer = () => {
    if (!selectedClient) return null;
    const config = STATUS_CONFIG[selectedClient.status as StatusKey] || STATUS_CONFIG.novo;

    return (
      <div className={cn(
        'fixed inset-0 z-50 flex',
        'lg:inset-y-0 lg:left-auto lg:right-0 lg:w-[55%] xl:w-[50%]'
      )}>
        {/* Backdrop */}
        <div className="absolute inset-0 bg-black/40 lg:bg-black/20" onClick={() => setSelectedClient(null)} />

        {/* Panel */}
        <div className={cn(
          'relative ml-auto w-full bg-background border-l border-border flex flex-col h-full',
          'lg:w-full'
        )}>
          {/* Header */}
          <div className="flex items-center gap-3 px-4 py-3 border-b border-border bg-card">
            <Button variant="ghost" size="icon" onClick={() => setSelectedClient(null)} className="flex-shrink-0">
              <ArrowLeft className="w-5 h-5" />
            </Button>
            <Avatar className="w-10 h-10 flex-shrink-0">
              <AvatarFallback className="bg-primary/15 text-primary font-semibold text-sm">
                {getInitials(selectedClient.nome)}
              </AvatarFallback>
            </Avatar>
            <div className="flex-1 min-w-0">
              <h2 className="font-semibold text-foreground truncate">{selectedClient.nome}</h2>
              <p className="text-xs text-muted-foreground truncate">{selectedClient.telefone}</p>
            </div>

            {/* Status dropdown */}
            <div className="relative group">
              <button className={cn('flex items-center gap-1 text-xs font-semibold px-2.5 py-1 rounded-full border', config.bg, config.color)}>
                {config.label}
                <ChevronDown className="w-3 h-3" />
              </button>
              <div className="hidden group-hover:block absolute right-0 top-full mt-1 w-40 bg-card border border-border rounded-xl shadow-xl z-10 py-1">
                {ALL_STATUSES.map((s) => (
                  <button
                    key={s}
                    onClick={() => handleStatusChange(selectedClient.id, s)}
                    className={cn(
                      'w-full text-left px-3 py-2 text-xs hover:bg-muted/50 transition-colors',
                      s === selectedClient.status && 'font-bold'
                    )}
                  >
                    <span className={STATUS_CONFIG[s].color}>{STATUS_CONFIG[s].label}</span>
                  </button>
                ))}
              </div>
            </div>
          </div>

          {/* Tabs */}
          <div className="flex border-b border-border bg-card">
            {(['chat', 'perfil'] as const).map((tab) => (
              <button
                key={tab}
                onClick={() => setActiveTab(tab)}
                className={cn(
                  'flex-1 py-2.5 text-sm font-medium transition-colors relative',
                  activeTab === tab ? 'text-primary' : 'text-muted-foreground hover:text-foreground'
                )}
              >
                {tab === 'chat' ? 'Chat' : 'Perfil'}
                {activeTab === tab && <div className="absolute bottom-0 left-0 right-0 h-0.5 bg-primary" />}
              </button>
            ))}
          </div>

          {/* Tab content */}
          <div className="flex-1 min-h-0 flex flex-col">
            {activeTab === 'chat' && <ChatTab />}
            {activeTab === 'perfil' && <PerfilTab />}
          </div>
        </div>
      </div>
    );
  };

  // ─── Render ────────────────────────────────────────────────────────

  const totalClients = allClients.length;
  const totalUnread = allClients.reduce((sum, c) => sum + c.unread, 0);

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
        {isLoading ? (
          <div className="flex-1 flex items-center justify-center">
            <Loader2 className="w-8 h-8 animate-spin text-primary" />
          </div>
        ) : (
          <>
            {/* Desktop: Kanban */}
            <div className="hidden lg:flex flex-1 min-h-0 overflow-x-auto p-4 gap-3">
              {ALL_STATUSES.map((status) => (
                <KanbanColumn key={status} status={status} />
              ))}
            </div>

            {/* Mobile: Tabs + List */}
            <div className="lg:hidden flex-1 min-h-0 flex flex-col">
              {/* Status tabs */}
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

              {/* Client list */}
              <ScrollArea className="flex-1 min-h-0">
                <div className="p-3 space-y-2">
                  {(crmData?.[mobileStatus] || []).length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-16 gap-3">
                      <Users className="w-10 h-10 text-muted-foreground/30" />
                      <p className="text-sm text-muted-foreground">Nenhum cliente neste status</p>
                    </div>
                  ) : (
                    (crmData?.[mobileStatus] || []).map((client) => (
                      <ClientCard key={client.id} client={client} />
                    ))
                  )}
                </div>
              </ScrollArea>
            </div>
          </>
        )}
      </div>

      {/* Drawer */}
      {selectedClient && <ClientDrawer />}
    </div>
  );
}
