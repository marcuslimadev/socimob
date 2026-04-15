import { useState, useEffect, useRef, useMemo, useCallback, useLayoutEffect } from 'react';
import {
  Send,
  Phone,
  Search,
  MoreVertical,
  Paperclip,
  ArrowLeft,
  Check,
  CheckCheck,
  MessageCircle,
  Clock,
  Loader2,
  User,
  RefreshCw,
  FileText,
  Film,
  Download,
  ExternalLink,
  Info,
  Tag
} from 'lucide-react';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';
import { toast } from 'sonner';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

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

interface Contact {
  id: string;
  name: string;
  initials: string;
  lastMessage: string;
  timestamp: string;
  unread: number;
  online: boolean;
  leadId: number;
  phone: string;
  needsHumanIntervention?: boolean;
  observacoes?: string | null;
  classificacao?: string | null;
}

export default function Chat() {
  const [selectedContactId, setSelectedContactId] = useState<string | null>(null);
  const [messageText, setMessageText] = useState('');
  const [messages, setMessages] = useState<Message[]>([]);
  const [contacts, setContacts] = useState<Contact[]>([]);
  const [isLoadingContacts, setIsLoadingContacts] = useState(true);
  const [isLoadingMessages, setIsLoadingMessages] = useState(false);
  const [isSending, setIsSending] = useState(false);
  const [searchTerm, setSearchTerm] = useState('');
  const [showMobileContacts, setShowMobileContacts] = useState(true);
  const [isRefreshing, setIsRefreshing] = useState(false);
  const [isChatCollapsed, setIsChatCollapsed] = useState(false);

  const messagesEndRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const scrollAreaRef = useRef<HTMLDivElement>(null);

  const fetchSeqRef = useRef(0);
  const intervalRef = useRef<number | null>(null);
  const hasLoadedMessagesRef = useRef(false);

  const pendingScrollRestoreRef = useRef<null | { top: number; height: number; nearBottom: boolean }>(null);
  const chatPatternSvg =
    '<svg xmlns="http://www.w3.org/2000/svg" width="160" height="160" viewBox="0 0 160 160"><g fill="none" stroke="#d9d2c8" stroke-width="1" opacity="0.22"><path d="M20 20h20v20H20z"/><circle cx="120" cy="40" r="10"/><path d="M80 120l15-15 15 15"/><circle cx="40" cy="120" r="6"/><path d="M120 120h20v20h-20z"/></g></svg>';
  const chatPatternDataUrl = `data:image/svg+xml;utf8,${encodeURIComponent(chatPatternSvg)}`;

  const decodeHtml = (value: string) => {
    if (typeof window === 'undefined') return value;
    const doc = new DOMParser().parseFromString(`<!doctype html><body>${value}`, 'text/html');
    return doc.body.textContent || '';
  };

  const normalizeObservacoes = (value?: string | null) => {
    if (!value) return '';
    const withBreaks = value.replace(/<\s*br\s*\/?>/gi, '\n');
    const withoutTags = withBreaks.replace(/<\/?[^>]+(>|$)/g, '');
    return decodeHtml(withoutTags).trim();
  };

  const selectedContact = contacts.find((c) => c.id === selectedContactId);

  const observacoesText = useMemo(
    () => normalizeObservacoes(selectedContact?.observacoes),
    [selectedContact?.observacoes]
  );

  const getScrollViewport = useCallback(() => {
    if (!scrollAreaRef.current) return null;
    const vp = scrollAreaRef.current.querySelector('[data-radix-scroll-area-viewport]') as HTMLDivElement | null;
    return vp;
  }, []);

  const scrollToBottom = useCallback((behavior: ScrollBehavior = 'auto') => {
    setTimeout(() => {
      messagesEndRef.current?.scrollIntoView({ behavior });
    }, 50);
  }, [getScrollViewport]);

  useLayoutEffect(() => {
    const pending = pendingScrollRestoreRef.current;
    if (!pending) return;

    const vp = getScrollViewport();
    if (!vp) {
      pendingScrollRestoreRef.current = null;
      return;
    }

    if (pending.nearBottom) {
      vp.scrollTop = vp.scrollHeight;
    } else {
      const heightDelta = vp.scrollHeight - pending.height;
      vp.scrollTop = pending.top + heightDelta;
    }

    pendingScrollRestoreRef.current = null;
  }, [messages, getScrollViewport]);

  useEffect(() => {
    fetchContacts();
  }, []);

  useEffect(() => {
    if (!selectedContactId) return;

    if (intervalRef.current) {
      window.clearInterval(intervalRef.current);
      intervalRef.current = null;
    }

    // Limpar mensagens imediatamente ao trocar de contato (evita exibir conversa errada)
    setMessages([]);
    hasLoadedMessagesRef.current = false;

    fetchMessages(selectedContactId);
    setShowMobileContacts(false);

    intervalRef.current = window.setInterval(() => {
      fetchMessages(selectedContactId);
    }, 15000);

    return () => {
      if (intervalRef.current) {
        window.clearInterval(intervalRef.current);
        intervalRef.current = null;
      }
    };
  }, [selectedContactId]);

  const getInitials = (name: string) => {
    if (!name) return '?';
    const parts = name.trim().split(' ');
    if (parts.length >= 2) return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    return name.slice(0, 2).toUpperCase();
  };

  const formatTime = (dateString: string) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
  };

  const formatRelativeTime = (dateString: string) => {
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
  };

  const formatDateSeparator = (date: Date) => {
    const now = new Date();
    const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const yesterday = new Date(today.getTime() - 86400000);
    const messageDate = new Date(date.getFullYear(), date.getMonth(), date.getDate());
    if (messageDate.getTime() === today.getTime()) return 'Hoje';
    if (messageDate.getTime() === yesterday.getTime()) return 'Ontem';
    return date.toLocaleDateString('pt-BR', { day: '2-digit', month: 'long', year: 'numeric' });
  };

  const applyMessagesWithScrollPreserve = (incoming: Message[], seq: number) => {
    const vp = getScrollViewport();
    const snapshot = vp
      ? {
          top: vp.scrollTop,
          height: vp.scrollHeight,
          nearBottom: vp.scrollHeight - (vp.scrollTop + vp.clientHeight) < 100,
        }
      : null;

    setMessages((prev) => {
      const prevLen = prev.length;

      if (prevLen === 0) {
        if (snapshot) pendingScrollRestoreRef.current = snapshot;
        return incoming;
      }

      const prevById = new Map(prev.map((m) => [m.id, m]));
      const incomingIds = new Set(incoming.map((m) => m.id));
      let hasChanges = false;

      const merged = incoming.map((m) => {
        const old = prevById.get(m.id);
        if (!old) {
          hasChanges = true;
          return m;
        }
        const changed =
          old.status !== m.status ||
          old.text !== m.text ||
          old.read !== m.read ||
          old.mediaUrl !== m.mediaUrl ||
          old.transcription !== m.transcription ||
          old.messageType !== m.messageType;

        if (changed) {
          hasChanges = true;
          return { ...old, ...m };
        }
        return old;
      });

      const pending = prev.filter((m) => m.id.startsWith('temp-') && !incomingIds.has(m.id));
      if (pending.length) {
        hasChanges = true;
        merged.push(...pending);
        merged.sort((a, b) => a.rawDate.getTime() - b.rawDate.getTime());
      }

      if (!hasChanges) {
        pendingScrollRestoreRef.current = null;
        return prev;
      }

      if (snapshot) pendingScrollRestoreRef.current = snapshot;
      return merged;
    });
  };

  const fetchContacts = async () => {
    try {
      setIsLoadingContacts(true);
      const response = await api.get('/admin/conversas');

      if (response.data.success) {
        const mappedContacts = response.data.data
          .filter((item: any) => item && item.id != null && item.lead_id != null)
          .map((item: any) => {
            const name = item.lead_nome || item.lead_telefone || 'Sem nome';
            return {
              id: item.id.toString(),
              name,
              initials: getInitials(name),
              lastMessage: item.ultima_mensagem || 'Sem mensagens',
              timestamp: formatRelativeTime(item.ultima_atividade || item.created_at),
              unread: item.mensagens_nao_lidas || 0,
              online: false,
              leadId: item.lead_id,
              phone: item.lead_telefone,
              needsHumanIntervention: item.needs_human_intervention || false,
              observacoes: item.lead_observacoes || null,
              classificacao: item.lead_classificacao || null,
            };
          });

        setContacts((prev) => {
          if (prev.length === 0) return mappedContacts;
          if (prev.length !== mappedContacts.length) return mappedContacts;

          const hasChanges = mappedContacts.some((n: Contact, idx: number) => {
            const o = prev[idx];
            return (
              !o ||
              o.id !== n.id ||
              o.lastMessage !== n.lastMessage ||
              o.unread !== n.unread ||
              o.needsHumanIntervention !== n.needsHumanIntervention
            );
          });

          return hasChanges ? mappedContacts : prev;
        });

        const query = new URLSearchParams(window.location.search);
        const targetLeadId = query.get('leadId');

        if (targetLeadId && !selectedContactId) {
          const target = mappedContacts.find((c: Contact) => c.leadId?.toString() === targetLeadId);
          if (target) {
            setSelectedContactId(target.id);
            setTimeout(() => scrollToBottom('auto'), 200);
          }
        }
      }
    } catch (e) {
      toast.error('Erro ao carregar conversas');
    } finally {
      setIsLoadingContacts(false);
      setIsRefreshing(false);
    }
  };

  const fetchMessages = async (contactId: string) => {
    const seq = ++fetchSeqRef.current;
    const isFirstLoad = !hasLoadedMessagesRef.current;

    try {
      if (isFirstLoad) setIsLoadingMessages(true);

      const response = await api.get(`/admin/conversas/${contactId}/mensagens`);

      if (seq !== fetchSeqRef.current) {
        return;
      }
      if (!response.data.success) return;

      const mappedMessages = response.data.data
        .filter((item: any) => item && item.id != null)
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

      applyMessagesWithScrollPreserve(mappedMessages, seq);
      
      if (isFirstLoad) hasLoadedMessagesRef.current = true;
    } catch (e) {
      if (seq !== fetchSeqRef.current) return;
      if (isFirstLoad) toast.error('Erro ao carregar mensagens');
    } finally {
      if (seq === fetchSeqRef.current && isFirstLoad) setIsLoadingMessages(false);
    }
  };

  const handleSendMessage = async () => {
    if (!messageText.trim() || !selectedContactId || isSending) return;

    const text = messageText.trim();
    setMessageText('');
    setIsSending(true);

    const tempId = `temp-${Date.now()}`;
    const newMessage: Message = {
      id: tempId,
      sender: 'user',
      text,
      timestamp: formatTime(new Date().toISOString()),
      rawDate: new Date(),
      read: false,
      status: 'sending',
    };

    setMessages((prev) => [...prev, newMessage]);
    scrollToBottom('auto');

    try {
      const response = await api.post(`/admin/conversas/${selectedContactId}/mensagens`, {
        content: text,
      });

      if (!response.data.success) {
        throw new Error('Falha ao enviar');
      }

      setMessages((prev) =>
        prev.map((m) =>
          m.id === tempId ? { ...m, id: response.data.data?.id || tempId, status: 'sent' } : m
        )
      );

      setTimeout(() => fetchMessages(selectedContactId), 1000);
      fetchContacts(); // Atualizar lista de conversas
    } catch (error) {
      console.error('Erro ao enviar mensagem:', error);
      toast.error('Erro ao enviar mensagem');
      setMessages((prev) => prev.filter((m) => m.id !== tempId));
    } finally {
      setIsSending(false);
      inputRef.current?.focus();
    }
  };

  const escapeRegExp = (value: string) => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

  const formatHtmlMessage = (html: string) => {
    // Converte <br>, <br/>, <br /> para quebras de linha
    let formatted = html.replace(/<br\s*\/?>/gi, '\n');
    
    // Remove outras tags HTML mas mantém o conteúdo
    formatted = formatted.replace(/<[^>]+>/g, '');
    
    return formatted;
  };

  const highlightText = (text: string, term: string) => {
    // Formata HTML primeiro
    const formattedText = formatHtmlMessage(text);
    
    if (!term) return formattedText;
    const safeTerm = escapeRegExp(term);
    const parts = formattedText.split(new RegExp(`(${safeTerm})`, 'ig'));
    return parts.map((part, index) =>
      part.toLowerCase() === term.toLowerCase() ? (
        <mark key={`${part}-${index}`} className="bg-primary/25 text-foreground rounded px-1">
          {part}
        </mark>
      ) : (
        <span key={`${part}-${index}`}>{part}</span>
      )
    );
  };

  const filteredMessages = useMemo(() => {
    if (!searchTerm) return messages;
    const term = searchTerm.toLowerCase();
    return messages.filter((m) => m.text?.toLowerCase().includes(term));
  }, [messages, searchTerm]);

  const groupedFilteredMessages = useMemo(() => {
    const src = filteredMessages;
    const groups: { date: string; messages: Message[] }[] = [];
    let currentDate = '';

    src.forEach((message) => {
      const dateKey = message.rawDate.toDateString();
      if (dateKey !== currentDate) {
        currentDate = dateKey;
        groups.push({ date: formatDateSeparator(message.rawDate), messages: [message] });
      } else {
        groups[groups.length - 1].messages.push(message);
      }
    });

    return groups;
  }, [filteredMessages]);

  const filteredContacts = useMemo(() => {
    if (!searchTerm) return contacts;
    const term = searchTerm.toLowerCase();
    return contacts.filter(
      (c) =>
        c.name.toLowerCase().includes(term) ||
        c.phone?.toLowerCase().includes(term) ||
        c.lastMessage?.toLowerCase().includes(term)
    );
  }, [contacts, searchTerm]);

  const handleRefresh = async () => {
    setIsRefreshing(true);
    await fetchContacts();
    if (selectedContactId) {
      await fetchMessages(selectedContactId);
    }
  };

  const MessageStatus = ({ status }: { status?: string }) => {
    if (status === 'sending') return <Clock className="w-3 h-3 text-muted-foreground" />;
    if (status === 'sent') return <Check className="w-3 h-3 text-muted-foreground" />;
    if (status === 'delivered') return <CheckCheck className="w-3 h-3 text-muted-foreground" />;
    if (status === 'read') return <CheckCheck className="w-3 h-3 text-primary" />;
    return null;
  };

  const isAudioMessage = (message: Message) => {
    if (!message.mediaUrl) return false;
    if (message.messageType === 'audio' || message.messageType === 'voice') return true;
    return /\.(mp3|ogg|wav|m4a|opus)(\?|$)/i.test(message.mediaUrl);
  };

  const isImageMessage = (message: Message) => {
    if (!message.mediaUrl) return false;
    if (message.messageType === 'image' || message.messageType === 'photo' || message.messageType === 'picture') return true;
    const url = message.mediaUrl.toLowerCase();
    return /\.(png|jpe?g|gif|webp|bmp|svg|heic|heif|tiff?)(\?|$|#)/i.test(url);
  };

  const isVideoMessage = (message: Message) => {
    if (!message.mediaUrl) return false;
    if (message.messageType === 'video') return true;
    return /\.(mp4|webm|mov|avi|mkv|3gp)(\?|$)/i.test(message.mediaUrl);
  };

  const isDocumentMessage = (message: Message) => {
    if (!message.mediaUrl) return false;
    if (message.messageType === 'document' || message.messageType === 'file') return true;
    return /\.(pdf|doc|docx|xls|xlsx|ppt|pptx|csv|txt|zip|rar)(\?|$)/i.test(message.mediaUrl);
  };

  const getDocumentLabel = (message: Message) => {
    const url = message.mediaUrl || '';
    const type = message.messageType || '';
    if (type === 'document' || type === 'file' || /\.pdf(\?|$)/i.test(url)) return 'Documento';
    if (/\.(doc|docx)(\?|$)/i.test(url)) return 'Documento Word';
    if (/\.(xls|xlsx|csv)(\?|$)/i.test(url)) return 'Planilha';
    if (/\.(ppt|pptx)(\?|$)/i.test(url)) return 'Apresentação';
    if (/\.(zip|rar)(\?|$)/i.test(url)) return 'Arquivo compactado';
    return 'Documento';
  };

  // Detecta tipo de media para URLs do Twilio (que não têm extensão)
  // Se o messageType não é audio/image/video, assume documento
  const isTwilioGenericMedia = (message: Message) => {
    if (!message.mediaUrl) return false;
    if (!message.mediaUrl.includes('twilio.com')) return false;
    return !isAudioMessage(message) && !isImageMessage(message) && !isVideoMessage(message) && !isDocumentMessage(message);
  };

  const getMediaUrl = (url: string) => {
    if (!url) return '';
    
    // Se é uma URL do Twilio (api.twilio.com), usar proxy para evitar erro de autenticação
    if (url.includes('twilio.com')) {
      return `/api/conversas/media/proxy?url=${encodeURIComponent(url)}`;
    }
    
    // Se já é URL completa
    if (/^https?:\/\//i.test(url)) return url;
    
    // Para arquivos de mídia, usa o domínio atual (tenant) para evitar CORS/host mismatch
    const storageBaseUrl = typeof window !== 'undefined' ? window.location.origin : '';
    
    // Se começa com /
    if (url.startsWith('/')) {
      return `${storageBaseUrl}${url}`;
    }
    // Se não tem protocolo nem barra, adiciona /storage/
    if (!url.startsWith('storage/')) {
      return `${storageBaseUrl}/storage/${url}`;
    }
    return `${storageBaseUrl}/${url}`;
  };

  const getMessageDisplayText = (message: Message) => {
    if (message.messageType === 'audio') {
      return message.text || 'Áudio';
    }
    return message.text || '';
  };

  return (
    <div className="flex min-h-screen overflow-hidden bg-[#f4efe7] text-[#1f2329]">
      <Sidebar />

      <div className="page-shell relative flex min-h-0 flex-col overflow-hidden !px-0 !pb-0">
        <div className="pointer-events-none absolute inset-0 -z-10">
          <div className="absolute -left-28 top-8 h-64 w-64 rounded-full bg-[#1f3f67]/15 blur-3xl" />
          <div className="absolute right-0 top-40 h-72 w-72 rounded-full bg-[#b37b34]/20 blur-3xl" />
          <div className="absolute inset-x-0 bottom-0 h-56 bg-gradient-to-t from-white/35 to-transparent" />
        </div>

        <div className="border-b border-[#cfbfab]/60 bg-[#fff8ef]/90 px-5 py-4 backdrop-blur-sm">
          <div className="mx-auto flex w-full max-w-6xl items-center gap-3">
            <div className="relative flex-1">
              <Search className="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#6f6556]" />
              <input
                type="text"
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                placeholder="Pesquisar pessoas, conversas e mensagens..."
                className="w-full rounded-2xl border border-[#cfbfab] bg-white/85 py-3 pl-11 pr-4 text-sm text-[#1f2329] placeholder:text-[#8d7f6a] shadow-[0_8px_24px_rgba(31,35,41,0.06)] transition-all focus:outline-none focus:ring-2 focus:ring-[#1f3f67]/30"
              />
            </div>
            {searchTerm && (
              <Button
                variant="ghost"
                size="sm"
                className="text-[#6f6556] hover:bg-[#1f3f67]/10 hover:text-[#1f3f67]"
                onClick={() => setSearchTerm('')}
              >
                Limpar
              </Button>
            )}
            <Button
              variant="ghost"
              size="sm"
              className="hidden md:inline-flex text-[#6f6556] hover:bg-[#1f3f67]/10 hover:text-[#1f3f67]"
              onClick={() => setIsChatCollapsed((prev) => !prev)}
            >
              {isChatCollapsed ? 'Abrir chat' : 'Minimizar chat'}
            </Button>
          </div>
        </div>

        <div className="relative mx-auto flex w-full max-w-6xl flex-1 min-h-0 gap-4 px-3 py-4 md:px-5 md:py-5">

          <div
            className={cn(
              'relative z-10 flex w-full flex-shrink-0 flex-col overflow-hidden rounded-[26px] border border-[#cfbfab]/70 bg-[#fffaf3]/90 shadow-[0_18px_45px_rgba(31,35,41,0.1)] md:w-[360px]',
              'transition-all duration-300 ease-out',
              isChatCollapsed && 'md:w-full',
              !showMobileContacts && 'hidden md:flex'
            )}
          >
            <div className="border-b border-[#d9ccb8]/80 bg-gradient-to-r from-[#fff8ef] to-[#f5ebdc] p-5">
              <div className="mb-4 flex items-center justify-between">
                <div>
                  <p className="text-[11px] uppercase tracking-[0.32em] text-[#9a8e7a]">Central de Conversas</p>
                  <h1 className="text-2xl font-semibold text-[#1f2329]">Atendimentos</h1>
                </div>
                <Button
                  variant="ghost"
                  size="icon"
                  onClick={handleRefresh}
                  disabled={isRefreshing}
                  className="text-[#6f6556] hover:bg-[#1f3f67]/10 hover:text-[#1f3f67]"
                >
                  <RefreshCw className={cn('h-4 w-4', isRefreshing && 'animate-spin')} />
                </Button>
              </div>
              <div className="flex items-center gap-2 text-[11px] text-[#756953]">
                <span className="rounded-full border border-[#1f3f67]/30 bg-[#1f3f67]/10 px-3 py-1 font-medium text-[#1f3f67]">Tudo</span>
                <span className="rounded-full border border-[#d1c3af] bg-white/70 px-3 py-1">Não lidas</span>
                <span className="rounded-full border border-[#d1c3af] bg-white/70 px-3 py-1">Prioridade</span>
              </div>
            </div>

            <ScrollArea className="min-h-0 flex-1">
              {isLoadingContacts ? (
                <div className="flex flex-col items-center justify-center gap-3 py-12">
                  <Loader2 className="h-8 w-8 animate-spin text-[#1f3f67]" />
                  <p className="text-sm text-[#7f735f]">Carregando conversas...</p>
                </div>
              ) : filteredContacts.length === 0 ? (
                <div className="flex flex-col items-center justify-center gap-3 px-4 py-12">
                  <div className="flex h-16 w-16 items-center justify-center rounded-full bg-[#efe4d6]">
                    <MessageCircle className="h-8 w-8 text-[#8d7f6a]" />
                  </div>
                  <p className="text-center text-sm text-[#7f735f]">
                    {searchTerm ? 'Nenhuma conversa encontrada' : 'Nenhuma conversa ainda'}
                  </p>
                </div>
              ) : (
                <div className="space-y-2 p-2.5">
                  {filteredContacts.map((contact) => (
                    <button
                      key={contact.id}
                      onClick={() => {
                        setSelectedContactId(contact.id);
                        if (isChatCollapsed) setIsChatCollapsed(false);
                      }}
                      className={cn(
                        'group w-full rounded-2xl border px-3 py-3 text-left transition-all',
                        selectedContactId === contact.id
                          ? 'border-[#1f3f67]/45 bg-[#1f3f67]/10 shadow-[0_8px_18px_rgba(31,63,103,0.18)]'
                          : 'border-transparent bg-white/70 hover:border-[#cfbfab] hover:bg-white'
                      )}
                    >
                      <div className="flex items-center gap-3">
                        <Avatar className="h-11 w-11 flex-shrink-0 ring-2 ring-white/80">
                          <AvatarFallback className="bg-[#1f3f67]/15 font-semibold text-[#1f3f67]">
                            {contact.initials}
                          </AvatarFallback>
                        </Avatar>

                        <div className="min-w-0 flex-1">
                          <div className="mb-1 flex items-center justify-between gap-2">
                            <h3 className="truncate font-semibold text-[#1f2329]">{contact.name}</h3>
                            <span className="text-[11px] text-[#8d7f6a]">{contact.timestamp}</span>
                          </div>
                          <p className="truncate text-sm text-[#7f735f]">{contact.lastMessage}</p>
                        </div>

                        {contact.unread > 0 && (
                          <span className="flex h-5 min-w-5 items-center justify-center rounded-full bg-[#b37b34] px-1 text-[10px] font-bold text-white">
                            {contact.unread > 9 ? '9+' : contact.unread}
                          </span>
                        )}
                      </div>
                    </button>
                  ))}
                </div>
              )}
            </ScrollArea>
          </div>

          <div
            className={cn(
              'relative z-10 flex min-h-0 flex-1 flex-col overflow-hidden rounded-[28px] border border-[#cfbfab]/70 bg-[#fffdfa]/95 shadow-[0_22px_46px_rgba(31,35,41,0.14)]',
              showMobileContacts && 'hidden md:flex',
              isChatCollapsed && 'md:hidden'
            )}
          >
            {selectedContact ? (
              <>
                <div className="border-b border-[#d9ccb8]/80 bg-gradient-to-r from-[#fff8ee] via-[#f8eede] to-[#f3e6d3] px-5 py-4">
                  <div className="flex items-start gap-3">
                    <Button
                      variant="ghost"
                      size="icon"
                      className="flex-shrink-0 md:hidden"
                      onClick={() => setShowMobileContacts(true)}
                    >
                      <ArrowLeft className="h-5 w-5" />
                    </Button>

                    <Avatar className="h-11 w-11 flex-shrink-0 ring-2 ring-white/70">
                      <AvatarFallback className="bg-[#1f3f67]/15 text-sm font-semibold text-[#1f3f67]">
                        {selectedContact.initials}
                      </AvatarFallback>
                    </Avatar>

                    <div className="min-w-0 flex-1">
                      <h2 className="truncate text-base font-semibold text-[#1f2329]">{selectedContact.name}</h2>
                      <p className="truncate text-xs text-[#776b58]">{selectedContact.phone}</p>
                      {(observacoesText || selectedContact.classificacao) && (
                        <div className="mt-2 flex flex-wrap items-start gap-2">
                          {selectedContact.classificacao && (
                            <span
                              className={cn(
                                'inline-flex items-center rounded-full border px-2.5 py-0.5 text-[11px] font-semibold',
                                selectedContact.classificacao === 'quente' && 'border-red-200 bg-red-100 text-red-700',
                                selectedContact.classificacao === 'morno' && 'border-amber-200 bg-amber-100 text-amber-700',
                                selectedContact.classificacao === 'frio' && 'border-blue-200 bg-blue-100 text-blue-700'
                              )}
                            >
                              <Tag className="mr-1 h-3 w-3" />
                              {selectedContact.classificacao === 'quente' ? 'Quente' : selectedContact.classificacao === 'morno' ? 'Morno' : 'Frio'}
                            </span>
                          )}
                          {observacoesText && (
                            <span className="inline-flex max-w-[420px] items-center gap-1 rounded-full border border-[#d6cab5] bg-white/70 px-2.5 py-0.5 text-[11px] text-[#6f6556]">
                              <Info className="h-3 w-3 text-[#1f3f67]" />
                              <span className="truncate">{observacoesText}</span>
                            </span>
                          )}
                        </div>
                      )}
                    </div>

                    <div className="flex items-center gap-1">
                      <Button variant="ghost" size="icon" className="text-[#6f6556] hover:bg-[#1f3f67]/10 hover:text-[#1f3f67]">
                        <Phone className="h-5 w-5" />
                      </Button>
                      <Button variant="ghost" size="icon" className="text-[#6f6556] hover:bg-[#1f3f67]/10 hover:text-[#1f3f67]">
                        <MoreVertical className="h-5 w-5" />
                      </Button>
                    </div>
                  </div>
                </div>

                <div className="relative min-h-0 flex-1 overflow-hidden bg-[#f8f1e5]">
                  <div className="pointer-events-none absolute inset-0 opacity-40">
                    <div className="absolute inset-0" style={{ backgroundImage: `url('${chatPatternDataUrl}')` }} />
                  </div>

                  <ScrollArea ref={scrollAreaRef} className="h-full">
                    <div className="relative mx-auto w-full max-w-4xl space-y-5 p-5 md:p-7">
                      {searchTerm && (
                        <div className="flex items-center justify-center">
                          <div className="rounded-full border border-[#cfbfab] bg-white/90 px-3 py-1 text-xs text-[#6f6556]">
                            {filteredMessages.length} resultado(s) na conversa
                          </div>
                        </div>
                      )}

                      {isLoadingMessages ? (
                        <div className="flex justify-center py-10">
                          <Loader2 className="h-6 w-6 animate-spin text-[#1f3f67]" />
                        </div>
                      ) : filteredMessages.length === 0 ? (
                        <div className="flex flex-col items-center justify-center gap-3 py-16">
                          <div className="flex h-20 w-20 items-center justify-center rounded-full bg-white/75">
                            <MessageCircle className="h-10 w-10 text-[#8d7f6a]" />
                          </div>
                          <p className="font-medium text-[#1f2329]">{searchTerm ? 'Nenhum resultado encontrado' : 'Nenhuma mensagem'}</p>
                          <p className="text-sm text-[#7f735f]">{searchTerm ? 'Tente outro termo de busca' : 'Envie uma mensagem para iniciar a conversa'}</p>
                        </div>
                      ) : (
                        groupedFilteredMessages.map((group) => (
                          <div key={group.date} className="space-y-3">
                            <div className="flex items-center justify-center py-2">
                              <div className="rounded-full border border-[#d6cab5] bg-white/85 px-4 py-1 text-xs font-medium text-[#756953]">
                                {group.date}
                              </div>
                            </div>

                            {group.messages.map((message) => {
                              const isUser = message.sender === 'user';

                              return (
                                <div key={message.id} className={cn('flex gap-2.5', isUser ? 'justify-end' : 'justify-start')}>
                                  {!isUser && (
                                    <Avatar className="mt-1 h-8 w-8 flex-shrink-0 ring-2 ring-white/70">
                                      <AvatarFallback className="bg-[#e9dcc9] text-[#6f6556]">
                                        <User className="h-4 w-4" />
                                      </AvatarFallback>
                                    </Avatar>
                                  )}

                                  <div className={cn('max-w-[86%] md:max-w-[68%]', isUser && 'items-end')}>
                                    <div
                                      className={cn(
                                        'rounded-2xl border px-4 py-3 shadow-[0_8px_20px_rgba(31,35,41,0.08)]',
                                        isUser
                                          ? 'border-[#1f3f67]/25 bg-[#dfeaf8] text-[#1f2f44]'
                                          : 'border-[#dfd2bf] bg-white text-[#1f2329]'
                                      )}
                                    >
                                      {message.senderName && (
                                        <div className="mb-1.5">
                                          <div className={cn('text-[10px] font-semibold tracking-wide', message.senderKind === 'assistant' ? 'text-[#1f3f67]' : 'text-[#5d533f]')}>
                                            {message.senderName}
                                          </div>
                                          {message.senderContext && <div className="text-[9px] text-[#8d7f6a]">{message.senderContext}</div>}
                                        </div>
                                      )}

                                      {isAudioMessage(message) && message.mediaUrl && (
                                        <div className="mb-2">
                                          <audio controls className="w-full max-w-xs">
                                            <source src={getMediaUrl(message.mediaUrl)} />
                                          </audio>
                                        </div>
                                      )}

                                      {isImageMessage(message) && message.mediaUrl && (
                                        <div className="relative mb-2">
                                          <img
                                            src={getMediaUrl(message.mediaUrl)}
                                            alt="Imagem enviada"
                                            loading="lazy"
                                            className="w-full max-w-sm rounded-xl border border-[#e1d6c4] bg-[#f7f2ea] object-contain"
                                            onError={(e) => {
                                              const img = e.currentTarget;
                                              img.style.display = 'none';
                                              const parent = img.parentElement;
                                              if (parent && !parent.querySelector('.image-error-placeholder')) {
                                                parent.insertAdjacentHTML(
                                                  'beforeend',
                                                  '<div class="image-error-placeholder flex flex-col items-center justify-center rounded-lg border border-dashed border-[#cfbfab] bg-[#f6eee2] p-8"><p class="text-xs text-[#7f735f] text-center">Imagem não disponível</p><p class="mt-1 text-center text-xs text-[#9a8e7a]">Requer autenticação Twilio</p></div>'
                                                );
                                              }
                                            }}
                                          />
                                        </div>
                                      )}

                                      {isVideoMessage(message) && message.mediaUrl && (
                                        <div className="mb-2">
                                          <video controls className="w-full max-w-sm rounded-xl border border-[#e1d6c4] bg-black" preload="metadata">
                                            <source src={getMediaUrl(message.mediaUrl)} />
                                            Vídeo não suportado pelo navegador.
                                          </video>
                                        </div>
                                      )}

                                      {(isDocumentMessage(message) || isTwilioGenericMedia(message)) && message.mediaUrl && (
                                        <a
                                          href={getMediaUrl(message.mediaUrl)}
                                          target="_blank"
                                          rel="noopener noreferrer"
                                          className="mb-2 flex max-w-sm items-center gap-3 rounded-xl border border-[#d9ccb8] bg-[#fbf5ea] p-3 transition-colors hover:bg-[#f3e8d8]"
                                        >
                                          <div className="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-lg bg-[#1f3f67]/12">
                                            <FileText className="h-5 w-5 text-[#1f3f67]" />
                                          </div>
                                          <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium text-[#1f2329]">{getDocumentLabel(message)}</p>
                                            <p className="text-xs text-[#7f735f]">Clique para abrir</p>
                                          </div>
                                          <ExternalLink className="h-4 w-4 flex-shrink-0 text-[#7f735f]" />
                                        </a>
                                      )}

                                      {getMessageDisplayText(message) && (
                                        <p className="whitespace-pre-wrap break-words text-[15px] leading-relaxed text-[#1f2329]">
                                          {highlightText(getMessageDisplayText(message), searchTerm)}
                                        </p>
                                      )}

                                      {message.messageType === 'audio' && message.transcription && (
                                        <p className="mt-2 text-xs text-[#7f735f]">
                                          <span className="font-semibold text-[#5d533f]">Transcrição:</span>{' '}
                                          {highlightText(message.transcription, searchTerm)}
                                        </p>
                                      )}
                                    </div>

                                    <div className={cn('mt-1.5 flex items-center gap-1.5 px-1', isUser ? 'justify-end' : 'justify-start')}>
                                      <span className="text-[10px] text-[#8d7f6a]">{message.timestamp}</span>
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

                <div className="border-t border-[#d9ccb8]/80 bg-[#fff8ee] p-4 md:p-5">
                  <div className="mx-auto flex w-full max-w-4xl items-end gap-3">
                    <Button
                      variant="ghost"
                      size="icon"
                      className="flex-shrink-0 text-[#6f6556] hover:bg-[#1f3f67]/10 hover:text-[#1f3f67]"
                    >
                      <Paperclip className="h-5 w-5" />
                    </Button>

                    <div className="relative flex-1">
                      <input
                        ref={inputRef}
                        type="text"
                        value={messageText}
                        onChange={(e) => setMessageText(e.target.value)}
                        onKeyDown={(e) => {
                          if (e.key === 'Enter' && !e.shiftKey) {
                            e.preventDefault();
                            handleSendMessage();
                          }
                        }}
                        placeholder="Digite uma mensagem..."
                        className="w-full rounded-2xl border border-[#cfbfab] bg-white py-3 pl-4 pr-4 text-sm text-[#1f2329] placeholder:text-[#8d7f6a] shadow-[0_8px_22px_rgba(31,35,41,0.06)] focus:outline-none focus:ring-2 focus:ring-[#1f3f67]/30"
                        disabled={isSending}
                      />
                    </div>

                    <Button
                      onClick={handleSendMessage}
                      disabled={!messageText.trim() || isSending}
                      size="icon"
                      className="h-11 w-11 flex-shrink-0 rounded-full bg-[#1f3f67] text-white shadow-[0_12px_24px_rgba(31,63,103,0.35)] hover:bg-[#1a3454] disabled:shadow-none"
                    >
                      {isSending ? <Loader2 className="h-5 w-5 animate-spin" /> : <Send className="h-5 w-5" />}
                    </Button>
                  </div>
                </div>
              </>
            ) : (
              <div className="flex flex-1 flex-col items-center justify-center bg-gradient-to-b from-[#fbf4e8] to-[#f5ebdc] p-8">
                <div className="max-w-md text-center">
                  <div className="mx-auto mb-6 flex h-24 w-24 items-center justify-center rounded-full border border-[#cfbfab] bg-white/80 shadow-[0_16px_30px_rgba(31,35,41,0.12)]">
                    <MessageCircle className="h-12 w-12 text-[#1f3f67]" />
                  </div>
                  <h2 className="mb-2 text-xl font-semibold text-[#1f2329]">Sua central de mensagens</h2>
                  <p className="text-[#7f735f]">Selecione uma conversa à esquerda para ver todo o histórico e responder seus leads.</p>
                </div>
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}
