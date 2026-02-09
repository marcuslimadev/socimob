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
  RefreshCw
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

  const selectedContact = contacts.find((c) => c.id === selectedContactId);

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
    // Verifica pelo tipo de mensagem primeiro
    if (message.messageType === 'image' || message.messageType === 'photo' || message.messageType === 'picture') return true;
    // Verifica pela extensão do arquivo
    const url = message.mediaUrl.toLowerCase();
    return /\.(png|jpe?g|gif|webp|bmp|svg|heic|heif|tiff?)(\?|$|#)/i.test(url);
  };

  const getMediaUrl = (url: string) => {
    if (!url) return '';
    
    // Se é uma URL do Twilio (api.twilio.com), usar proxy para evitar erro de autenticação
    if (url.includes('twilio.com')) {
      return `https://lojadaesquina.store/api/admin/conversas/media/proxy?url=${encodeURIComponent(url)}`;
    }
    
    // Se já é URL completa
    if (/^https?:\/\//i.test(url)) return url;
    
    // Para arquivos de mídia, sempre usa o domínio da API backend
    // onde os arquivos estão armazenados fisicamente
    const storageBaseUrl = 'https://lojadaesquina.store';
    
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
    <div className="flex h-screen overflow-hidden bg-background text-foreground">
      <Sidebar />

      <div className="relative flex-1 min-h-0 flex flex-col md:ml-80">
        {/* Global Search */}
        <div className="border-b border-border bg-card px-5 py-4">
          <div className="max-w-5xl mx-auto flex items-center gap-3">
            <div className="relative flex-1">
              <Search className="absolute left-4 top-1/2 -translate-y-1/2 text-muted-foreground w-4 h-4" />
              <input
                type="text"
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                placeholder="Pesquisar pessoas, conversas e mensagens..."
                className="w-full pl-11 pr-4 py-3 bg-muted/40 border border-border rounded-2xl text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary/30 transition-all"
              />
            </div>
            {searchTerm && (
              <Button
                variant="ghost"
                size="sm"
                className="text-muted-foreground hover:text-foreground"
                onClick={() => setSearchTerm('')}
              >
                Limpar
              </Button>
            )}
          </div>
        </div>

        <div className="relative flex-1 min-h-0 flex">

        {/* Contacts Panel */}
        <div
          className={cn(
            'relative z-10 w-full md:w-[380px] flex-shrink-0 flex flex-col border-r border-border bg-card',
            'transition-transform duration-300 ease-in-out',
            !showMobileContacts && 'hidden md:flex'
          )}
        >
          {/* Header */}
          <div className="p-5 border-b border-border bg-card">
            <div className="flex items-center justify-between mb-4">
              <div>
                <p className="text-xs uppercase tracking-[0.25em] text-muted-foreground">Central</p>
                <h1 className="text-2xl font-semibold text-foreground">Conversas</h1>
              </div>
              <Button
                variant="ghost"
                size="icon"
                onClick={handleRefresh}
                disabled={isRefreshing}
                className="text-muted-foreground hover:text-foreground"
              >
                <RefreshCw className={cn("w-4 h-4", isRefreshing && "animate-spin")} />
              </Button>
            </div>
            <div className="mt-4 flex items-center gap-2 text-xs text-muted-foreground">
              <span className="px-3 py-1 rounded-full bg-primary/15 text-primary border border-primary/30">
                Tudo
              </span>
              <span className="px-3 py-1 rounded-full bg-muted/40 border border-border">
                Não lidas
              </span>
              <span className="px-3 py-1 rounded-full bg-muted/40 border border-border">
                Favoritas
              </span>
            </div>
          </div>

          {/* Contacts List */}
          <ScrollArea className="flex-1 min-h-0">
            {isLoadingContacts ? (
              <div className="flex flex-col items-center justify-center py-12 gap-3">
                <Loader2 className="w-8 h-8 animate-spin text-primary" />
                <p className="text-sm text-muted-foreground">Carregando conversas...</p>
              </div>
            ) : filteredContacts.length === 0 ? (
              <div className="flex flex-col items-center justify-center py-12 gap-3 px-4">
                <div className="w-16 h-16 rounded-full bg-muted/40 flex items-center justify-center">
                  <MessageCircle className="w-8 h-8 text-muted-foreground" />
                </div>
                <p className="text-sm text-muted-foreground text-center">
                  {searchTerm ? 'Nenhuma conversa encontrada' : 'Nenhuma conversa ainda'}
                </p>
              </div>
            ) : (
              <div className="divide-y divide-border">
                {filteredContacts.map((contact) => (
                  <button
                    key={contact.id}
                    onClick={() => setSelectedContactId(contact.id)}
                    className={cn(
                      'w-full p-4 flex items-center gap-3 transition-colors text-left',
                      'hover:bg-muted/40',
                      selectedContactId === contact.id && 'bg-muted/60',
                      contact.needsHumanIntervention && selectedContactId !== contact.id && 'bg-yellow-100/60 dark:bg-yellow-900/20 hover:bg-yellow-100/80 dark:hover:bg-yellow-900/30'
                    )}
                  >
                    <Avatar className="w-12 h-12 flex-shrink-0">
                      <AvatarFallback className="bg-primary/20 text-primary font-semibold">
                        {contact.initials}
                      </AvatarFallback>
                    </Avatar>

                    <div className="flex-1 min-w-0">
                      <div className="flex items-center justify-between mb-1">
                        <h3 className="font-semibold text-foreground truncate pr-2">
                          {contact.name}
                        </h3>
                        <span className="text-xs text-muted-foreground flex-shrink-0">
                          {contact.timestamp}
                        </span>
                      </div>
                      <p className="text-sm text-muted-foreground truncate">
                        {contact.lastMessage}
                      </p>
                    </div>

                    {contact.unread > 0 && (
                      <span className="w-5 h-5 bg-primary rounded-full flex items-center justify-center text-[10px] font-bold text-primary-foreground flex-shrink-0">
                        {contact.unread > 9 ? '9+' : contact.unread}
                      </span>
                    )}
                  </button>
                ))}
              </div>
            )}
          </ScrollArea>
        </div>

        {/* Chat Area */}
        <div className={cn(
          'relative z-10 flex-1 min-h-0 flex flex-col',
          showMobileContacts && 'hidden md:flex'
        )}>
          {selectedContact ? (
            <>
              {/* Chat Header */}
              <div className="px-5 py-4 border-b border-border bg-card flex items-center gap-3">
                <Button
                  variant="ghost"
                  size="icon"
                  className="md:hidden flex-shrink-0"
                  onClick={() => setShowMobileContacts(true)}
                >
                  <ArrowLeft className="w-5 h-5" />
                </Button>

                <Avatar className="w-10 h-10 flex-shrink-0">
                  <AvatarFallback className="bg-primary/20 text-primary font-semibold text-sm">
                    {selectedContact.initials}
                  </AvatarFallback>
                </Avatar>

                <div className="flex-1 min-w-0">
                  <h2 className="font-semibold text-foreground truncate">
                    {selectedContact.name}
                  </h2>
                  <p className="text-xs text-muted-foreground truncate">
                    {selectedContact.phone}
                  </p>
                </div>

                <div className="flex items-center gap-1">
                  <Button variant="ghost" size="icon" className="text-muted-foreground hover:text-foreground">
                    <Phone className="w-5 h-5" />
                  </Button>
                  <Button variant="ghost" size="icon" className="text-muted-foreground hover:text-foreground">
                    <MoreVertical className="w-5 h-5" />
                  </Button>
                </div>
              </div>

              {/* Messages Area */}
              <div className="flex-1 min-h-0 overflow-hidden relative bg-[linear-gradient(180deg,rgba(0,0,0,0.02),transparent_35%)]">
                <div className="pointer-events-none absolute inset-0 opacity-50">
                  <div
                    className="absolute inset-0"
                    style={{ backgroundImage: `url('${chatPatternDataUrl}')` }}
                  />
                </div>
                <ScrollArea ref={scrollAreaRef} className="h-full">
                  <div className="max-w-5xl mx-auto p-6 space-y-6 relative">
                    {searchTerm && (
                      <div className="flex items-center justify-center">
                        <div className="px-3 py-1 rounded-full bg-muted/60 text-xs text-muted-foreground">
                          {filteredMessages.length} resultado(s) na conversa
                        </div>
                      </div>
                    )}
                    {isLoadingMessages ? (
                      <div className="flex justify-center py-8">
                        <Loader2 className="w-6 h-6 animate-spin text-primary" />
                      </div>
                    ) : filteredMessages.length === 0 ? (
                      <div className="flex flex-col items-center justify-center py-16 gap-4">
                        <div className="w-20 h-20 rounded-full bg-muted/40 flex items-center justify-center">
                          <MessageCircle className="w-10 h-10 text-muted-foreground" />
                        </div>
                        <div className="text-center">
                          <p className="text-foreground font-medium">
                            {searchTerm ? 'Nenhum resultado encontrado' : 'Nenhuma mensagem'}
                          </p>
                          <p className="text-sm text-muted-foreground">
                            {searchTerm ? 'Tente outro termo de busca' : 'Envie uma mensagem para iniciar a conversa'}
                          </p>
                        </div>
                      </div>
                    ) : (
                      <>
                        {groupedFilteredMessages.map((group) => (
                          <div key={group.date} className="space-y-3">
                            {/* Date Separator */}
                            <div className="flex items-center justify-center py-3">
                              <div className="px-4 py-1.5 bg-muted/60 backdrop-blur-sm rounded-full text-xs text-muted-foreground font-medium shadow-sm">
                                {group.date}
                              </div>
                            </div>

                            {/* Messages */}
                            {group.messages.map((message, index) => {
                              const isUser = message.sender === 'user';
                              const isLastInGroup = index === group.messages.length - 1 || 
                                                    group.messages[index + 1]?.sender !== message.sender;
                              
                              return (
                                <div
                                  key={message.id}
                                  className={cn(
                                    'flex gap-2 items-end',
                                    isUser ? 'justify-end' : 'justify-start'
                                  )}
                                >
                                  {!isUser && (
                                    <Avatar className="w-8 h-8 flex-shrink-0">
                                      <AvatarFallback className="bg-muted/50 text-muted-foreground text-xs">
                                        <User className="w-4 h-4" />
                                      </AvatarFallback>
                                    </Avatar>
                                  )}
                                  
                                  <div
                                    className={cn(
                                      'max-w-[75%] md:max-w-[65%]',
                                      isUser && 'flex flex-col items-end'
                                    )}
                                  >
                                    <div
                                      className={cn(
                                        'relative px-4 py-3 rounded-2xl shadow-sm',
                                        isUser
                                          ? 'bg-primary/10 text-foreground rounded-br-sm border border-primary/20'
                                          : 'bg-muted text-foreground rounded-bl-sm border border-border'
                                      )}
                                    >
                                      <span
                                        className={cn(
                                          'pointer-events-none absolute bottom-0 w-3 h-3',
                                          isUser
                                            ? 'right-[-6px] bg-primary/10 border-r border-t border-primary/20 rotate-45 rounded-sm'
                                            : 'left-[-6px] bg-muted border-l border-b border-border rotate-45 rounded-sm'
                                        )}
                                      />
                                      {/* Nome do remetente e contexto */}
                                      {message.senderName && (
                                        <div className="mb-1.5">
                                          <div className={cn(
                                            "text-[10px] font-medium opacity-70",
                                            message.senderName === 'Assistente IA' 
                                              ? 'text-blue-600 dark:text-blue-400' 
                                              : 'text-foreground'
                                          )}>
                                            {message.senderName}
                                          </div>
                                          {message.senderContext && (
                                            <div className="text-[9px] text-muted-foreground/60 mt-0.5 font-normal">
                                              {message.senderContext}
                                            </div>
                                          )}
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
                                        <div className="mb-2 relative">
                                          <img
                                            src={getMediaUrl(message.mediaUrl)}
                                            alt="Imagem enviada"
                                            loading="lazy"
                                            className="w-full max-w-sm rounded-lg border border-border object-contain bg-muted/20"
                                            onError={(e) => {
                                              console.error('Erro ao carregar imagem:', message.mediaUrl);
                                              console.log('URL processada:', message.mediaUrl ? getMediaUrl(message.mediaUrl) : 'N/A');
                                              
                                              // Substitui por placeholder ao invés de esconder
                                              const img = e.currentTarget;
                                              img.style.display = 'none';
                                              const parent = img.parentElement;
                                              if (parent && !parent.querySelector('.image-error-placeholder')) {
                                                parent.insertAdjacentHTML('beforeend', `
                                                  <div class="image-error-placeholder flex flex-col items-center justify-center p-8 bg-muted/40 rounded-lg border border-dashed border-border">
                                                    <svg class="w-12 h-12 text-muted-foreground mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                    </svg>
                                                    <p class="text-xs text-muted-foreground text-center">Imagem não disponível</p>
                                                    <p class="text-xs text-muted-foreground/60 text-center mt-1">Requer autenticação Twilio</p>
                                                  </div>
                                                `);
                                              }
                                            }}
                                          />
                                        </div>
                                      )}
                                      {/* Mostra legenda/texto da mensagem se houver */}
                                      {getMessageDisplayText(message) && (
                                        <p className="text-base font-semibold whitespace-pre-wrap break-words leading-relaxed">
                                          {highlightText(getMessageDisplayText(message), searchTerm)}
                                        </p>
                                      )}
                                      {message.messageType === 'audio' && message.transcription && (
                                        <p className="mt-2 text-xs text-muted-foreground">
                                          <span className="font-semibold text-foreground/80">Transcrição:</span>{' '}
                                          {highlightText(message.transcription, searchTerm)}
                                        </p>
                                      )}
                                    </div>
                                    <div
                                      className={cn(
                                        'flex items-center gap-1.5 mt-1 px-1',
                                        isUser ? 'justify-end' : 'justify-start'
                                      )}
                                    >
                                      <span className="text-[10px] text-muted-foreground">
                                        {message.timestamp}
                                      </span>
                                      {isUser && (
                                        <MessageStatus status={message.status} />
                                      )}
                                    </div>
                                  </div>
                                </div>
                              );
                            })}
                          </div>
                        ))}
                      </>
                    )}
                    <div ref={messagesEndRef} />
                  </div>
                </ScrollArea>
              </div>

              {/* Input Area */}
              <div className="p-5 border-t border-border bg-card">
                <div className="max-w-5xl mx-auto flex items-end gap-3">
                  <Button
                    variant="ghost"
                    size="icon"
                    className="flex-shrink-0 text-muted-foreground hover:text-foreground"
                  >
                    <Paperclip className="w-5 h-5" />
                  </Button>

                  <div className="flex-1 relative">
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
                      className="w-full px-4 py-3 bg-muted/40 border border-border rounded-2xl text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary/30 transition-all"
                      disabled={isSending}
                    />
                  </div>

                  <Button
                    onClick={handleSendMessage}
                    disabled={!messageText.trim() || isSending}
                    size="icon"
                    className="flex-shrink-0 w-11 h-11 rounded-full bg-primary hover:bg-primary/90 text-primary-foreground shadow-lg shadow-primary/20 disabled:opacity-50 disabled:shadow-none"
                  >
                    {isSending ? (
                      <Loader2 className="w-5 h-5 animate-spin" />
                    ) : (
                      <Send className="w-5 h-5" />
                    )}
                  </Button>
                </div>
              </div>
            </>
          ) : (
            /* Empty State */
            <div className="flex-1 flex flex-col items-center justify-center p-8 bg-muted/10">
              <div className="text-center max-w-md">
                <div className="w-24 h-24 mx-auto mb-6 rounded-full bg-primary/10 flex items-center justify-center">
                  <MessageCircle className="w-12 h-12 text-primary" />
                </div>
                <h2 className="text-xl font-semibold text-foreground mb-2">
                  Suas mensagens
                </h2>
                <p className="text-muted-foreground">
                  Selecione uma conversa ao lado para visualizar as mensagens e responder seus leads.
                </p>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  </div>
  );
}
