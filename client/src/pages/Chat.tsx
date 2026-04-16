import { useState, useEffect, useRef, useMemo, useCallback, useLayoutEffect } from 'react';
import FullCalendar from '@fullcalendar/react';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin from '@fullcalendar/interaction';
import ptBrLocale from '@fullcalendar/core/locales/pt-br';
import type { EventClickArg, EventContentArg } from '@fullcalendar/core';
import {
  Send,
  Phone,
  Search,
  MoreVertical,
  Trash2,
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
  ExternalLink,
  Info,
  Tag,
  Bot,
  AlertTriangle
} from 'lucide-react';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';
import { toast } from 'sonner';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Button } from '@/components/ui/button';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import './chat-calendar.css';

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
  leadStatus?: LeadStatus | null;
  startedAt?: string | null;
  lastActivityAt?: string | null;
  createdAt?: string | null;
  status?: string | null;
}

type LeadStatus = 'novo' | 'em_atendimento' | 'qualificado' | 'proposta' | 'fechado' | 'perdido';
type ContactFilter = 'all' | 'unread' | 'priority';
const CONTACTS_BATCH_SIZE = 40;
type ChatViewMode = 'chat' | 'calendar';
const LEAD_STATUS_OPTIONS: Array<{ value: LeadStatus; label: string }> = [
  { value: 'novo', label: 'Novo' },
  { value: 'em_atendimento', label: 'Em atendimento' },
  { value: 'qualificado', label: 'Qualificado' },
  { value: 'proposta', label: 'Proposta' },
  { value: 'fechado', label: 'Fechado' },
  { value: 'perdido', label: 'Perdido' },
];

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
  const [contactFilter, setContactFilter] = useState<ContactFilter>('all');
  const [activeView, setActiveView] = useState<ChatViewMode>('chat');
  const [contactsRenderLimit, setContactsRenderLimit] = useState(CONTACTS_BATCH_SIZE);
  const [isDeleteDialogOpen, setIsDeleteDialogOpen] = useState(false);
  const [isDeletingConversation, setIsDeletingConversation] = useState(false);
  const [isUpdatingLeadStatus, setIsUpdatingLeadStatus] = useState(false);

  const messagesEndRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);
  const scrollAreaRef = useRef<HTMLDivElement>(null);
  const sidebarScrollAreaRef = useRef<HTMLDivElement>(null);

  const fetchSeqRef = useRef(0);
  const intervalRef = useRef<number | null>(null);
  const hasLoadedMessagesRef = useRef(false);

  const pendingScrollRestoreRef = useRef<null | { top: number; height: number; nearBottom: boolean }>(null);
  const chatPatternSvg =
    '<svg xmlns="http://www.w3.org/2000/svg" width="220" height="220" viewBox="0 0 220 220"><g fill="none" stroke="#c8d8eb" stroke-width="1.2" opacity="0.34"><path d="M28 34h30v30H28z"/><circle cx="164" cy="50" r="12"/><path d="M108 168l18-18 18 18"/><circle cx="54" cy="164" r="8"/><path d="M156 158h28v28h-28z"/><path d="M84 88h18v18H84z"/><circle cx="118" cy="92" r="7"/></g></svg>';
  const chatPatternDataUrl = `data:image/svg+xml;utf8,${encodeURIComponent(chatPatternSvg)}`;

  const decodeHtml = (value: string) => {
    if (typeof window === 'undefined') return value;
    const doc = new DOMParser().parseFromString(`<!doctype html><body>${value}`, 'text/html');
    return doc.body.textContent || '';
  };

  const escapeRegExpForNotes = (value: string) => value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

  const normalizeObservacoes = (value?: string | null) => {
    if (!value) return '';
    const withBreaks = value.replace(/<\s*br\s*\/?>/gi, '\n');
    const withoutTags = withBreaks.replace(/<\/?[^>]+(>|$)/g, '');
    let normalized = decodeHtml(withoutTags).replace(/\r\n?/g, '\n').trim();

    // Legacy payloads were stored in one line; force visual sections for better readability.
    const sectionMarkers = [
      '💬 Mensagem:',
      '🔗 Origem:',
      '🏠 Imóvel:',
      '🚗 Veículo:',
      '📋 Referência:',
      '📝 Anúncio:',
    ];

    sectionMarkers.forEach((marker) => {
      const markerRegex = new RegExp(`\\s*${escapeRegExpForNotes(marker)}`, 'g');
      normalized = normalized.replace(markerRegex, (match, offset) => (offset === 0 ? marker : `\n${marker}`));
    });

    return normalized.replace(/\n{3,}/g, '\n\n').trim();
  };

  const getClassificationMeta = (value?: string | null) => {
    const normalized = value?.trim().toLowerCase();

    if (normalized === 'quente') {
      return {
        label: 'Quente',
        badgeClass: 'border-[#ff5b66]/35 bg-[#ff1d2d]/14 text-[#d91422]',
        dotClass: 'bg-[#ff1d2d]',
      };
    }

    if (normalized === 'morno') {
      return {
        label: 'Morno',
        badgeClass: 'border-[#ffd04a]/45 bg-[#ffc51a]/18 text-[#7a5b00]',
        dotClass: 'bg-[#ffc51a]',
      };
    }

    if (normalized === 'frio') {
      return {
        label: 'Frio',
        badgeClass: 'border-[#4f89c3]/35 bg-[#3d78b4]/16 text-[#225992]',
        dotClass: 'bg-[#3d78b4]',
      };
    }

    if (!value) return null;

    return {
      label: value,
      badgeClass: 'border-[#a6a6a3]/40 bg-[#9b9b98]/14 text-[#4d5560]',
      dotClass: 'bg-[#9b9b98]',
    };
  };

  const getLeadStatusMeta = (value?: string | null) => {
    const normalized = value?.trim().toLowerCase() as LeadStatus | undefined;

    switch (normalized) {
      case 'novo':
        return {
          value: 'novo' as LeadStatus,
          label: 'Novo',
          badgeClass: 'border-[#85b8ff]/55 bg-[#dbeafe] text-[#1d4ed8]',
          dotClass: 'bg-[#2563eb]',
          selectClass: 'border-[#85b8ff] bg-[#dbeafe] text-[#1d4ed8] focus:border-[#2563eb] focus:ring-[#2563eb]/20',
          calendarBackground: '#2563eb',
          calendarBorder: '#1d4ed8',
          calendarText: '#ffffff',
        };
      case 'em_atendimento':
        return {
          value: 'em_atendimento' as LeadStatus,
          label: 'Em atendimento',
          badgeClass: 'border-[#ffd27a]/55 bg-[#fff4d6] text-[#b45309]',
          dotClass: 'bg-[#d97706]',
          selectClass: 'border-[#ffd27a] bg-[#fff4d6] text-[#b45309] focus:border-[#d97706] focus:ring-[#d97706]/20',
          calendarBackground: '#f59e0b',
          calendarBorder: '#b45309',
          calendarText: '#050308',
        };
      case 'qualificado':
        return {
          value: 'qualificado' as LeadStatus,
          label: 'Qualificado',
          badgeClass: 'border-[#d3b5ff]/55 bg-[#f3e8ff] text-[#7e22ce]',
          dotClass: 'bg-[#9333ea]',
          selectClass: 'border-[#d3b5ff] bg-[#f3e8ff] text-[#7e22ce] focus:border-[#9333ea] focus:ring-[#9333ea]/20',
          calendarBackground: '#9333ea',
          calendarBorder: '#7e22ce',
          calendarText: '#ffffff',
        };
      case 'proposta':
        return {
          value: 'proposta' as LeadStatus,
          label: 'Proposta',
          badgeClass: 'border-[#8ee3ef]/55 bg-[#d9fbff] text-[#0f766e]',
          dotClass: 'bg-[#06b6d4]',
          selectClass: 'border-[#8ee3ef] bg-[#d9fbff] text-[#0f766e] focus:border-[#0891b2] focus:ring-[#0891b2]/20',
          calendarBackground: '#06b6d4',
          calendarBorder: '#0f766e',
          calendarText: '#ffffff',
        };
      case 'fechado':
        return {
          value: 'fechado' as LeadStatus,
          label: 'Fechado',
          badgeClass: 'border-[#f3a6af]/55 bg-[#ffe1e6] text-[#c1121f]',
          dotClass: 'bg-[#e11d48]',
          selectClass: 'border-[#f3a6af] bg-[#ffe1e6] text-[#c1121f] focus:border-[#e11d48] focus:ring-[#e11d48]/20',
          calendarBackground: '#e11d48',
          calendarBorder: '#be123c',
          calendarText: '#ffffff',
        };
      case 'perdido':
        return {
          value: 'perdido' as LeadStatus,
          label: 'Perdido',
          badgeClass: 'border-[#cfcfd4]/55 bg-[#ececee] text-[#4b5563]',
          dotClass: 'bg-[#6b7280]',
          selectClass: 'border-[#cfcfd4] bg-[#ececee] text-[#4b5563] focus:border-[#6b7280] focus:ring-[#6b7280]/20',
          calendarBackground: '#6b7280',
          calendarBorder: '#4b5563',
          calendarText: '#ffffff',
        };
      default:
        return null;
    }
  };

  const selectedContact = contacts.find((c) => c.id === selectedContactId);
  const currentUserRole = useMemo(() => {
    if (typeof window === 'undefined') return null;

    try {
      const rawUser = localStorage.getItem('user');
      if (!rawUser) return null;

      const parsedUser = JSON.parse(rawUser);
      return typeof parsedUser?.role === 'string' ? parsedUser.role : null;
    } catch {
      return null;
    }
  }, []);
  const canDeleteConversation = currentUserRole === 'admin' || currentUserRole === 'super_admin';
  const selectedClassificationMeta = useMemo(
    () => getClassificationMeta(selectedContact?.classificacao),
    [selectedContact?.classificacao]
  );
  const selectedLeadStatusMeta = useMemo(
    () => getLeadStatusMeta(selectedContact?.leadStatus),
    [selectedContact?.leadStatus]
  );

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
  }, []);

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

    // Reflexo imediato no UI enquanto o backend marca como lida.
    setContacts((prev) =>
      prev.map((contact) =>
        contact.id === selectedContactId && contact.unread > 0
          ? { ...contact, unread: 0 }
          : contact
      )
    );

    if (intervalRef.current) {
      window.clearInterval(intervalRef.current);
      intervalRef.current = null;
    }

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

  const formatCalendarDateTime = (dateString?: string | null) => {
    if (!dateString) return 'Sem data';
    const date = new Date(dateString);
    if (Number.isNaN(date.getTime())) return 'Sem data';

    return date.toLocaleString('pt-BR', {
      day: '2-digit',
      month: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
    });
  };

  const formatCardDateLabel = (contact: Contact) => {
    const source = contact.lastActivityAt || contact.createdAt || contact.startedAt;
    if (!source) return 'Sem data';

    const date = new Date(source);
    if (Number.isNaN(date.getTime())) return 'Sem data';

    return date.toLocaleDateString('pt-BR', {
      day: '2-digit',
      month: '2-digit',
      year: 'numeric',
    });
  };

  const openConversation = useCallback((contactId: string) => {
    setSelectedContactId(contactId);
    setActiveView('chat');
    setShowMobileContacts(false);
  }, []);

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
              leadStatus: item.lead_status || null,
              startedAt: item.iniciada_em || item.created_at || null,
              lastActivityAt: item.ultima_atividade || item.created_at || null,
              createdAt: item.created_at || null,
              status: item.status || null,
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
              o.needsHumanIntervention !== n.needsHumanIntervention ||
              o.leadStatus !== n.leadStatus
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

      // Após abrir a conversa, o backend marca incoming como lida.
      // Zera a badge local da conversa e sincroniza a badge do topo.
      setContacts((prev) =>
        prev.map((contact) =>
          contact.id === contactId && contact.unread > 0
            ? { ...contact, unread: 0 }
            : contact
        )
      );
      if (typeof window !== 'undefined') {
        window.dispatchEvent(new Event('socimob:chat-unread-changed'));
      }

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
      fetchContacts();
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
    let formatted = html.replace(/<br\s*\/?>/gi, '\n');
    formatted = formatted.replace(/<[^>]+>/g, '');
    return formatted;
  };

  const highlightText = (text: string, term: string) => {
    const formattedText = formatHtmlMessage(text);

    if (!term) return formattedText;
    const safeTerm = escapeRegExp(term);
    const parts = formattedText.split(new RegExp(`(${safeTerm})`, 'ig'));
    return parts.map((part, index) =>
      part.toLowerCase() === term.toLowerCase() ? (
        <mark key={`${part}-${index}`} className="rounded bg-[#f8d89a] px-1.5 py-0.5 text-[#5a3b00]">
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

  const unreadContactsCount = useMemo(
    () => contacts.filter((contact) => contact.unread > 0).length,
    [contacts]
  );

  const priorityContactsCount = useMemo(
    () =>
      contacts.filter(
        (contact) =>
          contact.needsHumanIntervention || contact.classificacao?.trim().toLowerCase() === 'quente'
      ).length,
    [contacts]
  );

  const filteredContacts = useMemo(() => {
    let filtered = contacts;

    if (contactFilter === 'unread') {
      filtered = filtered.filter((contact) => contact.unread > 0);
    }

    if (contactFilter === 'priority') {
      filtered = filtered.filter(
        (contact) =>
          contact.needsHumanIntervention || contact.classificacao?.trim().toLowerCase() === 'quente'
      );
    }

    if (!searchTerm) return filtered;

    const term = searchTerm.toLowerCase();
    return filtered.filter(
      (contact) =>
        contact.name.toLowerCase().includes(term) ||
        contact.phone?.toLowerCase().includes(term) ||
        contact.lastMessage?.toLowerCase().includes(term)
    );
  }, [contacts, contactFilter, searchTerm]);

  const contactFilters: {
    id: ContactFilter;
    label: string;
    count: number;
    helper: string;
  }[] = [
    { id: 'all', label: 'Todas', count: contacts.length, helper: 'fila completa' },
    { id: 'unread', label: 'Não lidas', count: unreadContactsCount, helper: 'pedindo resposta' },
    { id: 'priority', label: 'Prioridade', count: priorityContactsCount, helper: 'quentes ou humanas' },
  ];
  const visibleContacts = useMemo(
    () => filteredContacts.slice(0, contactsRenderLimit),
    [filteredContacts, contactsRenderLimit]
  );
  const hasMoreContacts = visibleContacts.length < filteredContacts.length;

  useEffect(() => {
    setContactsRenderLimit(CONTACTS_BATCH_SIZE);
  }, [searchTerm, contactFilter, contacts.length]);

  const loadMoreContacts = useCallback(() => {
    setContactsRenderLimit((current) => current + CONTACTS_BATCH_SIZE);
  }, []);

  const handleSidebarScroll = useCallback(() => {
    if (!hasMoreContacts || !sidebarScrollAreaRef.current) return;

    const viewport = sidebarScrollAreaRef.current.querySelector(
      '[data-radix-scroll-area-viewport]'
    ) as HTMLDivElement | null;

    if (!viewport) return;

    const distanceToBottom = viewport.scrollHeight - (viewport.scrollTop + viewport.clientHeight);
    if (distanceToBottom < 180) {
      loadMoreContacts();
    }
  }, [hasMoreContacts, loadMoreContacts]);

  const handleRefresh = async () => {
    setIsRefreshing(true);
    await fetchContacts();
    if (selectedContactId) {
      await fetchMessages(selectedContactId);
    }
  };

  const handleDeleteConversation = async () => {
    if (!selectedContact) return;

    const conversationId = selectedContact.id;
    const contactName = selectedContact.name;

    try {
      setIsDeletingConversation(true);
      await api.delete(`/admin/conversas/${conversationId}`);

      setContacts((prev) => prev.filter((contact) => contact.id !== conversationId));
      setSelectedContactId((current) => (current === conversationId ? null : current));
      setMessages([]);
      setShowMobileContacts(true);
      setIsDeleteDialogOpen(false);

      toast.success(`Conversa com ${contactName} excluida com sucesso`);
      await fetchContacts();
    } catch (error: any) {
      const message = error?.response?.data?.error || 'Erro ao excluir conversa';
      toast.error(message);
    } finally {
      setIsDeletingConversation(false);
    }
  };

  const handleLeadStatusChange = async (newStatus: LeadStatus) => {
    if (!selectedContact || isUpdatingLeadStatus || selectedContact.leadStatus === newStatus) return;

    const previousStatus = selectedContact.leadStatus || null;

    setIsUpdatingLeadStatus(true);
    setContacts((prev) =>
      prev.map((contact) =>
        contact.id === selectedContact.id ? { ...contact, leadStatus: newStatus } : contact
      )
    );

    try {
      await api.patch(`/leads/${selectedContact.leadId}/status`, { status: newStatus });
      toast.success(`Etapa do lead atualizada para ${getLeadStatusMeta(newStatus)?.label || newStatus}`);
    } catch (error: any) {
      setContacts((prev) =>
        prev.map((contact) =>
          contact.id === selectedContact.id ? { ...contact, leadStatus: previousStatus } : contact
        )
      );
      const message = error?.response?.data?.error || 'Erro ao atualizar etapa do lead';
      toast.error(message);
    } finally {
      setIsUpdatingLeadStatus(false);
    }
  };

  const calendarEvents = useMemo(() => {
    return filteredContacts
      .map((contact) => {
        const startSource = contact.startedAt || contact.createdAt || contact.lastActivityAt;
        if (!startSource) return null;

        const startDate = new Date(startSource);
        if (Number.isNaN(startDate.getTime())) return null;

        const endSource = contact.lastActivityAt || contact.createdAt || startSource;
        const parsedEndDate = endSource ? new Date(endSource) : new Date(startDate.getTime());
        const endDate = !Number.isNaN(parsedEndDate.getTime()) ? parsedEndDate : new Date(startDate.getTime());

        if (endDate.getTime() <= startDate.getTime()) {
          endDate.setTime(startDate.getTime() + 30 * 60 * 1000);
        }

        const normalizedClassificacao = contact.classificacao?.trim().toLowerCase();
        const leadStatusMeta = getLeadStatusMeta(contact.leadStatus);
        let backgroundColor = leadStatusMeta?.calendarBackground || '#2d6fab';
        let borderColor = leadStatusMeta?.calendarBorder || '#17365d';
        let textColor = leadStatusMeta?.calendarText || '#ffffff';

        if (!leadStatusMeta && contact.needsHumanIntervention) {
          backgroundColor = '#f1132b';
          borderColor = '#b90c21';
        } else if (!leadStatusMeta && normalizedClassificacao === 'quente') {
          backgroundColor = '#f9bf0a';
          borderColor = '#d99f00';
          textColor = '#050308';
        } else if (!leadStatusMeta && normalizedClassificacao === 'frio') {
          backgroundColor = '#467fc2';
          borderColor = '#2d6fab';
        }

        return {
          id: contact.id,
          title: contact.name,
          start: startDate.toISOString(),
          end: endDate.toISOString(),
          backgroundColor,
          borderColor,
          textColor,
          extendedProps: {
            phone: contact.phone,
            leadId: contact.leadId,
            lastMessage: contact.lastMessage,
            startedAt: startSource,
            lastActivityAt: contact.lastActivityAt,
            classificacao: contact.classificacao,
            leadStatus: contact.leadStatus,
            needsHumanIntervention: contact.needsHumanIntervention,
          },
        };
      })
      .filter(Boolean);
  }, [filteredContacts]);

  const timelineContacts = useMemo(() => {
    return [...filteredContacts]
      .sort((a, b) => {
        const aStart = new Date(a.startedAt || a.createdAt || a.lastActivityAt || 0).getTime();
        const bStart = new Date(b.startedAt || b.createdAt || b.lastActivityAt || 0).getTime();
        return bStart - aStart;
      })
      .slice(0, 10);
  }, [filteredContacts]);

  const handleCalendarEventClick = useCallback(
    (info: EventClickArg) => {
      openConversation(info.event.id);
    },
    [openConversation]
  );

  const renderCalendarEventContent = useCallback((eventInfo: EventContentArg) => {
    return (
      <div className="min-w-0 px-0.5 py-0.5">
        <div className="truncate text-[11px] font-semibold leading-4">{eventInfo.event.title}</div>
        <div className="truncate text-[10px] leading-4 opacity-85">{eventInfo.timeText || 'Atendimento'}</div>
      </div>
    );
  }, []);

  const renderCalendarPanel = () => (
    <div className="flex min-h-0 flex-1 flex-col bg-[radial-gradient(circle_at_14%_12%,rgba(45,111,171,0.12),transparent_26%),radial-gradient(circle_at_86%_14%,rgba(255,29,45,0.10),transparent_24%),radial-gradient(circle_at_52%_100%,rgba(255,197,26,0.12),transparent_30%),linear-gradient(180deg,#ffffff_0%,#fffdf7_100%)]">
      <div className="border-b border-[#ffc51a] bg-[#ffffff] px-4 py-4 md:px-5">
        <div className="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-[#617489]">Atendimentos por data</p>
            <h2 className="mt-1 text-[1.7rem] font-semibold leading-none tracking-[-0.05em] text-[#132b4c]">Calendário de conversas</h2>
            <p className="mt-2 text-sm leading-6 text-[#5a646f]">
              Cada faixa mostra do início do atendimento até a última atividade registrada. Clique em um atendimento para abrir o chat.
            </p>
          </div>
          <div className="flex flex-wrap items-center gap-2 text-xs text-[#5a646f]">
            <span className="rounded-full border border-[#ffc51a] bg-[#ffffff] px-3 py-1.5">{calendarEvents.length} atendimento(s)</span>
            <span className="rounded-full border border-[#2d6fab] bg-[#ffffff] px-3 py-1.5">Filtro atual: {contactFilters.find((filter) => filter.id === contactFilter)?.label || 'Todas'}</span>
          </div>
        </div>
      </div>

      <div className="min-h-0 flex-1 overflow-hidden p-3 md:p-4">
        <div className="grid h-full min-h-0 gap-3 xl:grid-cols-[minmax(0,1fr)_320px]">
          <div className="chat-atendimentos-calendar min-h-0 overflow-hidden rounded-[22px] border border-[#2d6fab] bg-[#ffffff] shadow-[0_16px_36px_rgba(0,0,0,0.08)]">
            {calendarEvents.length > 0 ? (
              <FullCalendar
                plugins={[dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin]}
                locale={ptBrLocale}
                initialView="timeGridWeek"
                headerToolbar={{
                  left: 'prev,next today',
                  center: 'title',
                  right: 'dayGridMonth,timeGridWeek,listMonth',
                }}
                buttonText={{
                  today: 'Hoje',
                  month: 'Mês',
                  week: 'Semana',
                  list: 'Lista',
                }}
                events={calendarEvents}
                eventClick={handleCalendarEventClick}
                eventContent={renderCalendarEventContent}
                height="100%"
                nowIndicator
                editable={false}
                selectable={false}
                dayMaxEvents={3}
                slotMinTime="06:00:00"
                slotMaxTime="22:00:00"
                expandRows
              />
            ) : (
              <div className="flex h-full min-h-[420px] flex-col items-center justify-center gap-4 px-6 py-12 text-center">
                <div className="flex h-16 w-16 items-center justify-center rounded-[22px] border border-[#ffc51a] bg-[#132b4c] text-white shadow-[0_12px_28px_rgba(0,0,0,0.16)]">
                  <Clock className="h-7 w-7" />
                </div>
                <div>
                  <p className="font-medium text-[#132b4c]">Nenhum atendimento para exibir</p>
                  <p className="mt-2 text-sm leading-6 text-[#5a646f]">
                    Ajuste a busca ou o filtro lateral para montar esta visão do calendário.
                  </p>
                </div>
              </div>
            )}
          </div>

          <aside className="flex min-h-0 flex-col overflow-hidden rounded-[22px] border border-[#ff1d2d] bg-[#ffffff] shadow-[0_16px_36px_rgba(0,0,0,0.08)]">
            <div className="border-b border-[#ffc51a] bg-[#ffffff] px-4 py-4">
              <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-[#617489]">Abrir chat</p>
              <h3 className="mt-1 text-lg font-semibold text-[#132b4c]">Atendimentos recentes</h3>
              <p className="mt-1 text-sm leading-6 text-[#5a646f]">Selecione um atendimento para ir direto à conversa.</p>
            </div>

            <ScrollArea className="min-h-0 flex-1">
              <div className="space-y-2 p-3">
                {timelineContacts.length > 0 ? (
                  timelineContacts.map((contact) => {
                    const classificationMeta = getClassificationMeta(contact.classificacao);
                    const leadStatusMeta = getLeadStatusMeta(contact.leadStatus);
                    return (
                      <button
                        key={`calendar-link-${contact.id}`}
                        type="button"
                        onClick={() => openConversation(contact.id)}
                        className="w-full rounded-[18px] border border-[#ffc51a] bg-[#ffffff] px-3 py-3 text-left transition hover:border-[#2d6fab] hover:bg-[#f8fbff]"
                      >
                        <div className="flex items-start justify-between gap-3">
                          <div className="min-w-0">
                            <p className="truncate text-sm font-semibold text-[#132b4c]">{contact.name}</p>
                            <p className="mt-0.5 truncate text-[12px] text-[#5a646f]">{contact.phone || 'Telefone não informado'}</p>
                          </div>
                          <span className="rounded-full border border-[#ffc51a] bg-white px-2 py-1 text-[10px] font-semibold uppercase tracking-[0.14em] text-[#2d6fab]">
                            Abrir
                          </span>
                        </div>

                        <div className="mt-2 space-y-1 text-[12px] leading-5 text-[#4d5560]">
                          <p><strong className="font-semibold text-[#132b4c]">Início:</strong> {formatCalendarDateTime(contact.startedAt || contact.createdAt || contact.lastActivityAt)}</p>
                          <p><strong className="font-semibold text-[#132b4c]">Fim:</strong> {formatCalendarDateTime(contact.lastActivityAt || contact.createdAt || contact.startedAt)}</p>
                        </div>

                        <p className="mt-2 line-clamp-2 text-[12px] leading-5 text-[#5a646f]">{contact.lastMessage || 'Sem mensagens registradas.'}</p>

                        <div className="mt-2 flex flex-wrap items-center gap-1.5">
                          {leadStatusMeta && (
                            <span className={cn('inline-flex items-center gap-1 rounded-full border px-2 py-1 text-[11px] font-semibold', leadStatusMeta.badgeClass)}>
                              <span className={cn('h-1.5 w-1.5 rounded-full', leadStatusMeta.dotClass)} />
                              {leadStatusMeta.label}
                            </span>
                          )}
                          {classificationMeta && (
                            <span className={cn('inline-flex items-center gap-1 rounded-full border px-2 py-1 text-[11px] font-semibold', classificationMeta.badgeClass)}>
                              <span className={cn('h-1.5 w-1.5 rounded-full', classificationMeta.dotClass)} />
                              {classificationMeta.label}
                            </span>
                          )}
                          {contact.needsHumanIntervention && (
                            <span className="inline-flex items-center gap-1 rounded-full border border-[#ffd04a]/45 bg-[#ffc51a]/18 px-2 py-1 text-[11px] font-semibold text-[#7a5b00]">
                              <AlertTriangle className="h-3 w-3" />
                              Humano
                            </span>
                          )}
                        </div>
                      </button>
                    );
                  })
                ) : (
                  <div className="px-3 py-8 text-center text-sm text-[#5a646f]">
                    Nenhum atendimento recente com o filtro atual.
                  </div>
                )}
              </div>
            </ScrollArea>
          </aside>
        </div>
      </div>
    </div>
  );

  const MessageStatus = ({
    status,
    tone = 'default',
  }: {
    status?: string;
    tone?: 'default' | 'inverted';
  }) => {
    const baseClass = tone === 'inverted' ? 'text-white/70' : 'text-muted-foreground';
    const readClass = tone === 'inverted' ? 'text-[#9ed0ff]' : 'text-primary';

    if (status === 'sending') return <Clock className={cn('h-3.5 w-3.5', baseClass)} />;
    if (status === 'sent') return <Check className={cn('h-3.5 w-3.5', baseClass)} />;
    if (status === 'delivered') return <CheckCheck className={cn('h-3.5 w-3.5', baseClass)} />;
    if (status === 'read') return <CheckCheck className={cn('h-3.5 w-3.5', readClass)} />;
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

  const isTwilioGenericMedia = (message: Message) => {
    if (!message.mediaUrl) return false;
    if (!message.mediaUrl.includes('twilio.com')) return false;
    return !isAudioMessage(message) && !isImageMessage(message) && !isVideoMessage(message) && !isDocumentMessage(message);
  };

  const getMediaUrl = (url: string) => {
    if (!url) return '';

    if (url.includes('twilio.com')) {
      return `/api/conversas/media/proxy?url=${encodeURIComponent(url)}`;
    }

    if (/^https?:\/\//i.test(url)) return url;

    const storageBaseUrl = typeof window !== 'undefined' ? window.location.origin : '';

    if (url.startsWith('/')) {
      return `${storageBaseUrl}${url}`;
    }
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

  const getMessageSenderMeta = (message: Message) => {
    if (message.senderName) {
      return {
        label: message.senderName,
        context: message.senderContext,
      };
    }

    if (message.senderKind === 'assistant') {
      return {
        label: 'Assistente virtual',
        context: 'Automação',
      };
    }

    if (message.senderKind === 'human') {
      return {
        label: 'Equipe comercial',
        context: 'Atendimento humano',
      };
    }

    return {
      label: selectedContact?.name || 'Lead',
      context: 'Cliente',
    };
  };

  const renderChatLayout = () => (
    <div className="flex h-[100dvh] overflow-hidden bg-[radial-gradient(circle_at_12%_10%,rgba(45,111,171,0.10),transparent_28%),radial-gradient(circle_at_92%_16%,rgba(255,29,45,0.09),transparent_24%),radial-gradient(circle_at_66%_96%,rgba(255,197,26,0.12),transparent_30%),linear-gradient(180deg,#ffffff_0%,#fffdf8_100%)] text-[#f3f4f6]">
      <Sidebar />
      <div className="page-shell flex h-full min-h-0 flex-col overflow-hidden !px-0 !pb-0">
        <div
          className="box-border flex h-[calc(100dvh-var(--app-header-offset,0px))] min-h-0 flex-1 overflow-hidden p-2 md:p-3"
        >
          <div className="flex min-h-0 flex-1 overflow-hidden rounded-[20px] border border-[#ffc51a] bg-[#ffffff] shadow-[0_24px_60px_rgba(0,0,0,0.18)]">
            <aside className={cn('min-h-0 w-full flex-shrink-0 flex-col border-r border-[#274d7b] bg-[linear-gradient(180deg,#132b4c_0%,#0d2038_100%)] md:flex md:w-[360px] lg:w-[380px]', showMobileContacts ? 'flex' : 'hidden md:flex')}>
              <div className="border-b border-[#274d7b] p-3.5 md:p-4">
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <p className="text-[11px] font-semibold uppercase tracking-[0.28em] text-[#9fb2c9]">Atendimentos</p>
                    <h1 className="mt-1.5 text-[1.65rem] font-semibold leading-none tracking-[-0.05em] text-white">Conversas</h1>
                    <p className="mt-1.5 text-[13px] text-[#c6d2e2]">{searchTerm ? `${filteredContacts.length} resultado(s) na busca` : `${contacts.length} conversa(s) na fila total`}</p>
                  </div>
                  <Button variant="ghost" size="icon" onClick={handleRefresh} disabled={isRefreshing} className="h-10 w-10 rounded-2xl border border-[#365e8f] bg-[#1d3f69] text-white hover:bg-[#2d6fab]">
                    <RefreshCw className={cn('h-4 w-4', isRefreshing && 'animate-spin')} />
                  </Button>
                </div>
                <div className="mt-3 flex items-center gap-2 rounded-2xl border border-[#274d7b] bg-[#102744] p-1">
                  <button
                    type="button"
                    onClick={() => setActiveView('chat')}
                    className={cn(
                      'flex-1 rounded-xl px-3 py-2 text-xs font-semibold transition',
                      activeView === 'chat' ? 'bg-[#ffc51a] text-[#0a0a12]' : 'text-[#dbe4ef] hover:bg-[#173153]'
                    )}
                  >
                    Chat
                  </button>
                  <button
                    type="button"
                    onClick={() => {
                      setActiveView('calendar');
                      setShowMobileContacts(false);
                    }}
                    className={cn(
                      'flex-1 rounded-xl px-3 py-2 text-xs font-semibold transition',
                      activeView === 'calendar' ? 'bg-[#ffc51a] text-[#0a0a12]' : 'text-[#dbe4ef] hover:bg-[#173153]'
                    )}
                  >
                    Calendário
                  </button>
                </div>
                <div className="mt-3 flex items-center justify-between rounded-2xl border border-[#274d7b] bg-[#0a0a12] px-3 py-1.5 text-[11px]">
                  <span className="text-[#f2f2f0]">Exibindo {visibleContacts.length} de {filteredContacts.length}</span>
                  <span className="rounded-full bg-[#ff1d2d] px-2 py-0.5 font-semibold text-white">{filteredContacts.length}</span>
                </div>
                <div className="relative mt-3">
                  <Search className="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#b8c7d8]" />
                  <input type="text" value={searchTerm} onChange={(e) => setSearchTerm(e.target.value)} placeholder="Buscar pessoas ou trechos..." className="h-10 w-full rounded-2xl border border-[#365e8f] bg-[#f2f2f0] py-2 pl-11 pr-4 text-sm text-[#0a0a12] placeholder:text-[#7a838d] outline-none focus:border-[#ffc51a] focus:ring-4 focus:ring-[#ffc51a]/20" />
                </div>
                <div className="mt-3 flex flex-wrap gap-1.5">
                  {contactFilters.map((filter) => {
                    const isActive = contactFilter === filter.id;
                    return (
                      <button key={filter.id} type="button" onClick={() => setContactFilter(filter.id)} className={cn('inline-flex items-center gap-2 rounded-full border px-3 py-1.5 text-xs font-semibold transition', isActive ? 'border-[#ffc51a] bg-[#ffc51a] text-[#0a0a12]' : 'border-[#365e8f] bg-[#173153] text-[#dbe4ef] hover:border-[#4c83bc] hover:bg-[#1d3f69]')}>
                        <span>{filter.label}</span>
                        <span className={cn('inline-flex min-w-5 items-center justify-center rounded-full px-1.5 py-0.5 text-[10px]', isActive ? 'bg-[#0a0a12]/12 text-[#0a0a12]' : 'bg-[#2d6fab] text-white')}>{filter.count}</span>
                      </button>
                    );
                  })}
                </div>
              </div>
              <ScrollArea ref={sidebarScrollAreaRef} className="min-h-0 flex-1" onScrollCapture={handleSidebarScroll}>
                {isLoadingContacts ? (
                  <div className="flex flex-col items-center justify-center gap-3 py-12"><Loader2 className="h-8 w-8 animate-spin text-[#ffc51a]" /><p className="text-sm text-[#c6d2e2]">Carregando conversas...</p></div>
                ) : visibleContacts.length === 0 ? (
                  <div className="flex flex-col items-center justify-center gap-4 px-5 py-12"><div className="flex h-16 w-16 items-center justify-center rounded-3xl border border-[#274d7b] bg-[#1d3f69]"><MessageCircle className="h-7 w-7 text-white" /></div><div className="text-center"><p className="font-medium text-white">{searchTerm ? 'Nenhuma conversa encontrada' : 'Nenhuma conversa ainda'}</p><p className="mt-1 text-sm text-[#b8c7d8]">{searchTerm ? 'Ajuste a busca ou troque o filtro.' : 'Novos atendimentos aparecerão aqui.'}</p></div></div>
                ) : (
                  <div className="space-y-1 p-2">
                    {visibleContacts.map((contact) => {
                      const isActive = selectedContactId === contact.id;
                      const classificationMeta = getClassificationMeta(contact.classificacao);
                      const leadStatusMeta = getLeadStatusMeta(contact.leadStatus);
                      const isPriority = contact.needsHumanIntervention || contact.classificacao?.trim().toLowerCase() === 'quente';
                      return (
                        <button key={contact.id} type="button" onClick={() => openConversation(contact.id)} className={cn('w-full rounded-[20px] border px-3 py-2.5 text-left transition', isActive && activeView === 'chat' ? 'border-[#4c83bc] bg-[#f2f2f0] shadow-[0_12px_24px_rgba(0,0,0,0.22)]' : 'border-transparent bg-transparent hover:border-[#274d7b] hover:bg-[#173153]')}>
                          <div className="flex items-start gap-3">
                            <div className="relative flex-shrink-0">
                              <Avatar className="h-10 w-10"><AvatarFallback className={cn('font-semibold', isActive ? 'bg-[#2d6fab]/18 text-[#173153]' : 'bg-[#2d6fab] text-white')}>{contact.initials}</AvatarFallback></Avatar>
                              {contact.unread > 0 && <span className="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-[#ff1d2d] px-1 text-[10px] font-bold text-white">{contact.unread > 9 ? '9+' : contact.unread}</span>}
                            </div>
                            <div className="min-w-0 flex-1">
                              <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                  <div className="flex items-center gap-2"><h3 className={cn('truncate text-sm font-semibold', isActive ? 'text-[#0a0a12]' : 'text-white')}>{contact.name}</h3>{isPriority && <span className="h-2 w-2 rounded-full bg-[#ffc51a]" />}</div>
                                  <p className={cn('mt-0.5 truncate text-[11px]', isActive ? 'text-[#5a646f]' : 'text-[#b8c7d8]')}>{contact.phone || 'Telefone não informado'}</p>
                                </div>
                                <span className={cn('text-[11px]', isActive ? 'text-[#76808a]' : 'text-[#9fb2c9]')}>{contact.timestamp}</span>
                              </div>
                              <p className={cn('mt-1.5 line-clamp-2 text-[13px] leading-5', isActive ? 'text-[#4d5560]' : 'text-[#dbe4ef]')}>{contact.lastMessage}</p>
                              <div className="mt-1.5 flex flex-wrap items-center gap-1.5">
                                <span className={cn('inline-flex items-center rounded-full border px-2 py-1 text-[11px] font-semibold', isActive ? 'border-[#c6ccd3] bg-white text-[#4d5560]' : 'border-[#365e8f] bg-[#112b4a] text-[#dbe4ef]')}>
                                  Data {formatCardDateLabel(contact)}
                                </span>
                                {leadStatusMeta && <span className={cn('inline-flex items-center gap-1 rounded-full border px-2 py-1 text-[11px] font-semibold', leadStatusMeta.badgeClass)}><span className={cn('h-1.5 w-1.5 rounded-full', leadStatusMeta.dotClass)} />{leadStatusMeta.label}</span>}
                                {classificationMeta && <span className={cn('inline-flex items-center gap-1 rounded-full border px-2 py-1 text-[11px] font-semibold', classificationMeta.badgeClass)}><span className={cn('h-1.5 w-1.5 rounded-full', classificationMeta.dotClass)} />{classificationMeta.label}</span>}
                                {contact.needsHumanIntervention && <span className="inline-flex items-center gap-1 rounded-full border border-[#ffd04a]/45 bg-[#ffc51a]/18 px-2 py-1 text-[11px] font-semibold text-[#7a5b00]"><AlertTriangle className="h-3 w-3" />Humano</span>}
                              </div>
                            </div>
                          </div>
                        </button>
                      );
                    })}
                    {hasMoreContacts && (
                      <div className="px-2 pt-2">
                        <Button
                          type="button"
                          variant="ghost"
                          onClick={loadMoreContacts}
                          className="h-9 w-full rounded-xl border border-[#365e8f] bg-[#173153] text-xs font-semibold text-[#dbe4ef] hover:bg-[#1d3f69]"
                        >
                          Carregar mais conversas
                        </Button>
                      </div>
                    )}
                  </div>
                )}
              </ScrollArea>
            </aside>
            <main className={cn('min-h-0 flex-1 flex-col bg-[#ffffff]', showMobileContacts ? 'hidden md:flex' : 'flex')}>
              {activeView === 'calendar' ? renderCalendarPanel() : !selectedContact ? (
                <div className="flex flex-1 items-center justify-center bg-[radial-gradient(circle_at_14%_12%,rgba(45,111,171,0.12),transparent_26%),radial-gradient(circle_at_86%_14%,rgba(255,29,45,0.10),transparent_24%),radial-gradient(circle_at_52%_100%,rgba(255,197,26,0.12),transparent_30%),linear-gradient(180deg,#ffffff_0%,#fffdf8_100%)] p-8"><div className="max-w-xl text-center"><div className="mx-auto flex h-16 w-16 items-center justify-center rounded-[22px] border border-[#ffc51a]/70 bg-[#132b4c] text-white shadow-[0_12px_28px_rgba(0,0,0,0.16)]"><MessageCircle className="h-7 w-7" /></div><h2 className="mt-6 text-[2rem] font-semibold leading-none tracking-[-0.05em] text-[#132b4c]">Selecione uma conversa</h2><p className="mt-3 text-sm leading-7 text-[#4d5560] md:text-[15px]">A fila fica na lateral. O histórico abre aqui no centro, com leitura limpa e resposta rápida.</p></div></div>
              ) : (
                <>
                  <header className="border-b border-[#ffc51a] bg-[#ffffff] px-4 py-3 md:px-5">
                    <div className="flex items-start justify-between gap-4">
                      <div className="flex min-w-0 items-start gap-3">
                        <Button variant="ghost" size="icon" className="mt-0.5 rounded-full text-[#132b4c] hover:bg-[#132b4c]/8 md:hidden" onClick={() => setShowMobileContacts(true)}><ArrowLeft className="h-5 w-5" /></Button>
                        <Avatar className="h-10 w-10 flex-shrink-0"><AvatarFallback className="bg-[#2d6fab] font-semibold text-white">{selectedContact.initials}</AvatarFallback></Avatar>
                        <div className="min-w-0">
                          <div className="flex flex-wrap items-center gap-2">
                            <h2 className="truncate text-lg font-semibold text-[#132b4c] md:text-xl">{selectedContact.name}</h2>
                            {selectedLeadStatusMeta && <span className={cn('inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-[11px] font-semibold', selectedLeadStatusMeta.badgeClass)}><Tag className="h-3 w-3" />{selectedLeadStatusMeta.label}</span>}
                            {selectedClassificationMeta && <span className={cn('inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-[11px] font-semibold', selectedClassificationMeta.badgeClass)}><Tag className="h-3 w-3" />{selectedClassificationMeta.label}</span>}
                            {selectedContact.needsHumanIntervention && <span className="inline-flex items-center gap-1 rounded-full border border-[#ffd04a]/45 bg-[#ffc51a]/18 px-2.5 py-1 text-[11px] font-semibold text-[#7a5b00]"><AlertTriangle className="h-3 w-3" />Ação humana</span>}
                          </div>
                          <div className="mt-0.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[13px] text-[#5a646f]"><span>{selectedContact.phone}</span><span>Lead #{selectedContact.leadId}</span><span>{messages.length} mensagens</span><span>Última atividade {selectedContact.timestamp}</span></div>
                          <div className="mt-3 flex flex-wrap items-end gap-2.5">
                            <label className="flex min-w-[220px] flex-col gap-1">
                              <span className="text-[10px] font-semibold uppercase tracking-[0.18em] text-[#617489]">Etapa do lead</span>
                              <select
                                value={selectedContact.leadStatus || 'novo'}
                                onChange={(e) => handleLeadStatusChange(e.target.value as LeadStatus)}
                                disabled={isUpdatingLeadStatus}
                                className={cn(
                                  'h-10 rounded-xl border px-3 text-sm font-semibold outline-none transition disabled:cursor-not-allowed disabled:opacity-70',
                                  selectedLeadStatusMeta?.selectClass || 'border-[#ffc51a] bg-white text-[#132b4c] focus:border-[#ff9f0a] focus:ring-4 focus:ring-[#ffc51a]/20'
                                )}
                              >
                                {LEAD_STATUS_OPTIONS.map((option) => (
                                  <option key={option.value} value={option.value}>
                                    {option.label}
                                  </option>
                                ))}
                              </select>
                            </label>
                            {isUpdatingLeadStatus && <span className="inline-flex h-10 items-center gap-2 rounded-xl border border-[#ffc51a] bg-white px-3 text-sm text-[#5a646f]"><Loader2 className="h-4 w-4 animate-spin" />Salvando etapa...</span>}
                          </div>
                        </div>
                      </div>
                      <div className="flex items-center gap-2"><Button variant="ghost" size="icon" className="h-10 w-10 rounded-full border border-[#ffc51a] bg-white text-[#132b4c] hover:bg-[#ececea]"><Phone className="h-4 w-4" /></Button><DropdownMenu><DropdownMenuTrigger asChild><Button variant="ghost" size="icon" className="h-10 w-10 rounded-full border border-[#ffc51a] bg-white text-[#132b4c] hover:bg-[#ececea]"><MoreVertical className="h-4 w-4" /></Button></DropdownMenuTrigger><DropdownMenuContent align="end" className="w-56"><DropdownMenuItem disabled={!canDeleteConversation || isDeletingConversation} variant="destructive" onSelect={(event) => { event.preventDefault(); if (!canDeleteConversation || isDeletingConversation) return; setIsDeleteDialogOpen(true); }}><Trash2 className="h-4 w-4" />Excluir conversa</DropdownMenuItem></DropdownMenuContent></DropdownMenu></div>
                    </div>
                    {observacoesText && <div className="mt-3 flex items-start gap-3 rounded-2xl border border-[#ffc51a] bg-white px-3.5 py-2.5 text-sm text-[#4d5560]"><div className="mt-0.5 flex h-8 w-8 flex-shrink-0 items-center justify-center rounded-xl bg-[#132b4c] text-white"><Info className="h-4 w-4" /></div><div><p className="text-[11px] font-semibold uppercase tracking-[0.22em] text-[#617489]">Observações do lead</p><p className="mt-1 whitespace-pre-wrap leading-6">{observacoesText}</p></div></div>}
                  </header>
                  <div className="relative flex min-h-0 flex-1 flex-col overflow-hidden">
                    <div className="absolute inset-0" style={{ backgroundImage: `radial-gradient(circle at 14% 12%, rgba(45,111,171,0.10), transparent 26%), radial-gradient(circle at 86% 14%, rgba(255,29,45,0.09), transparent 24%), radial-gradient(circle at 52% 100%, rgba(255,197,26,0.11), transparent 30%), linear-gradient(180deg, rgba(255,255,255,0.98), rgba(255,253,248,0.98)), url("${chatPatternDataUrl}")`, backgroundSize: 'auto, auto, auto, auto, 220px 220px' }} />
                    <ScrollArea ref={scrollAreaRef} className="relative min-h-0 flex-1">
                      <div className="mx-auto flex h-full w-full max-w-[calc(100%-1rem)] flex-col gap-4 px-3 py-4 md:max-w-[calc(100%-2rem)] md:px-5 md:py-5">
                        {searchTerm && <div className="flex items-center justify-between gap-3 rounded-full border border-[#ffc51a] bg-white px-4 py-1.5 text-xs text-[#4d5560] shadow-[0_8px_20px_rgba(0,0,0,0.05)]"><span>Filtrando por <strong className="font-semibold text-[#132b4c]">"{searchTerm}"</strong></span><button type="button" onClick={() => setSearchTerm('')} className="font-semibold text-[#ff1d2d]">Limpar</button></div>}
                        {isLoadingMessages ? (
                          <div className="flex justify-center py-12"><Loader2 className="h-7 w-7 animate-spin text-[#2d6fab]" /></div>
                        ) : filteredMessages.length === 0 ? (
                          <div className="flex flex-col items-center justify-center gap-4 py-14 text-center"><div className="flex h-16 w-16 items-center justify-center rounded-3xl border border-[#ffc51a] bg-white"><MessageCircle className="h-7 w-7 text-[#2d6fab]" /></div><div><p className="font-medium text-[#132b4c]">{searchTerm ? 'Nenhum trecho encontrado' : 'Nenhuma mensagem ainda'}</p><p className="mt-1 text-sm text-[#5a646f]">{searchTerm ? 'Tente outro termo para localizar a conversa.' : 'Envie uma mensagem para iniciar o atendimento.'}</p></div></div>
                        ) : (
                          groupedFilteredMessages.map((group) => (
                            <div key={group.date} className="space-y-4">
                              <div className="flex items-center justify-center"><div className="rounded-full border border-[#ffc51a] bg-white/92 px-3 py-1 text-[11px] font-medium text-[#4d5560]">{group.date}</div></div>
                              {group.messages.map((message) => {
                                const isUser = message.sender === 'user';
                                const senderMeta = getMessageSenderMeta(message);
                                const messageTextContent = getMessageDisplayText(message);
                                return (
                                  <div key={message.id} className={cn('flex gap-2.5', isUser ? 'justify-end' : 'justify-start')}>
                                    {!isUser && <div className="mt-7 hidden h-8 w-8 flex-shrink-0 items-center justify-center rounded-2xl border border-[#ffc51a] bg-white text-[#132b4c] shadow-[0_8px_18px_rgba(0,0,0,0.05)] md:flex">{message.senderKind === 'assistant' ? <Bot className="h-4 w-4" /> : <User className="h-4 w-4" />}</div>}
                                    <div className={cn('flex max-w-[92%] flex-col md:max-w-[75%]', isUser && 'items-end')}>
                                      <div className={cn('mb-1.5 inline-flex items-center gap-2 px-1 text-[11px] font-semibold', isUser ? 'text-[#2d6fab]' : 'text-[#4d5560]')}>{message.senderKind === 'assistant' && <Bot className="h-3.5 w-3.5" />}<span>{senderMeta.label}</span>{senderMeta.context && <span className="text-[#8a8e93]">{senderMeta.context}</span>}</div>
                                      <div className={cn('overflow-hidden rounded-[22px] border px-3.5 py-3 shadow-[0_12px_24px_rgba(0,0,0,0.06)]', isUser ? 'border-[#2d6fab] bg-[#2d6fab] text-white' : 'border-[#ffc51a] bg-white text-[#0a0a12]')}>
                                        <div className="space-y-2">
                                          {isAudioMessage(message) && message.mediaUrl && <audio controls className="w-full max-w-xs"><source src={getMediaUrl(message.mediaUrl)} /></audio>}
                                          {isImageMessage(message) && message.mediaUrl && <img src={getMediaUrl(message.mediaUrl)} alt="Imagem enviada" loading="lazy" className={cn('w-full max-w-sm rounded-[18px] border object-contain', isUser ? 'border-white/20 bg-white/10' : 'border-[#ffc51a] bg-[#ececea]')} />}
                                          {isVideoMessage(message) && message.mediaUrl && <video controls className={cn('w-full max-w-sm rounded-[18px] border', isUser ? 'border-white/20 bg-black' : 'border-[#ffc51a] bg-black')} preload="metadata"><source src={getMediaUrl(message.mediaUrl)} />Vídeo não suportado pelo navegador.</video>}
                                          {(isDocumentMessage(message) || isTwilioGenericMedia(message)) && message.mediaUrl && <a href={getMediaUrl(message.mediaUrl)} target="_blank" rel="noopener noreferrer" className={cn('flex max-w-sm items-center gap-3 rounded-[18px] border p-3 transition-colors', isUser ? 'border-white/20 bg-white/10 hover:bg-white/14' : 'border-[#ffc51a] bg-[#ececea] hover:bg-[#e0e0dd]')}><div className={cn('flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl', isUser ? 'bg-white/14' : 'bg-white text-[#132b4c]')}><FileText className="h-5 w-5" /></div><div className="min-w-0 flex-1"><p className={cn('truncate text-sm font-medium', isUser ? 'text-white' : 'text-[#0a0a12]')}>{getDocumentLabel(message)}</p><p className={cn('text-xs', isUser ? 'text-white/70' : 'text-[#5a646f]')}>Clique para abrir</p></div><ExternalLink className={cn('h-4 w-4 flex-shrink-0', isUser ? 'text-white/75' : 'text-[#5a646f]')} /></a>}
                                          {messageTextContent && <p className={cn('whitespace-pre-wrap break-words text-[14px] leading-6', isUser ? 'text-white' : 'text-[#0a0a12]')}>{highlightText(messageTextContent, searchTerm)}</p>}
                                          {message.messageType === 'audio' && message.transcription && <p className={cn('text-xs leading-5', isUser ? 'text-white/78' : 'text-[#4d5560]')}><span className="font-semibold">Transcrição:</span> {highlightText(message.transcription, searchTerm)}</p>}
                                        </div>
                                      </div>
                                      <div className={cn('mt-1.5 flex items-center gap-1.5 px-1 text-[11px]', isUser ? 'justify-end text-[#617489]' : 'justify-start text-[#7a838d]')}><span>{message.timestamp}</span>{isUser && <MessageStatus status={message.status} />}</div>
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
                    <div className="border-t border-[#ffc51a] bg-[#ffffff] px-4 py-3 md:px-5">
                      <div className="mx-auto flex w-full max-w-[calc(100%-1rem)] flex-col gap-1.5 md:max-w-[calc(100%-2rem)]">
                        <div className="flex flex-wrap items-center justify-between gap-2 text-[11px] text-[#5a646f]"><span>{selectedContact.needsHumanIntervention ? 'Conversa marcada para atendimento humano.' : 'Resposta direta e contexto completo.'}</span><span>Enter para enviar</span></div>
                        <div className="flex items-end gap-2.5 rounded-[24px] border border-[#ffc51a] bg-white p-1.5 shadow-[0_14px_30px_rgba(0,0,0,0.06)]">
                          <Button variant="ghost" size="icon" className="h-10 w-10 flex-shrink-0 rounded-full text-[#617489] hover:bg-[#ececea] hover:text-[#132b4c]"><Paperclip className="h-5 w-5" /></Button>
                          <div className="relative flex-1 rounded-[18px] border border-[#ffc51a] bg-[#f8fafc] px-1 shadow-[inset_0_1px_0_rgba(255,255,255,0.75)]"><input ref={inputRef} type="text" value={messageText} onChange={(e) => setMessageText(e.target.value)} onKeyDown={(e) => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); handleSendMessage(); } }} placeholder="Escreva uma resposta objetiva..." className="h-10 w-full rounded-[18px] border-0 bg-transparent px-3 text-sm text-[#0a0a12] placeholder:text-[#8a8e93] outline-none" disabled={isSending} /></div>
                          <Button onClick={handleSendMessage} disabled={!messageText.trim() || isSending} size="icon" className="h-10 w-10 flex-shrink-0 rounded-full bg-[#ff1d2d] text-white shadow-[0_12px_24px_rgba(255,29,45,0.24)] hover:bg-[#e31626] disabled:shadow-none">{isSending ? <Loader2 className="h-5 w-5 animate-spin" /> : <Send className="h-5 w-5" />}</Button>
                        </div>
                      </div>
                    </div>
                  </div>
                </>
              )}
            </main>
          </div>
        </div>
      </div>
      <AlertDialog open={isDeleteDialogOpen} onOpenChange={setIsDeleteDialogOpen}>
        <AlertDialogContent className="border border-[#24456f] bg-[#132b4c] text-white">
          <AlertDialogHeader>
            <AlertDialogTitle>Excluir conversa</AlertDialogTitle>
            <AlertDialogDescription className="text-[#c6d2e2]">
              {selectedContact
                ? `A conversa com ${selectedContact.name} será removida junto com o histórico de mensagens relacionado.`
                : 'Esta conversa será removida junto com o histórico de mensagens relacionado.'}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel className="border-[#365e8f] bg-transparent text-white hover:bg-white/10 hover:text-white">
              Cancelar
            </AlertDialogCancel>
            <AlertDialogAction
              onClick={(event) => {
                event.preventDefault();
                void handleDeleteConversation();
              }}
              className="bg-[#ff1d2d] text-white hover:bg-[#e31626]"
              disabled={isDeletingConversation}
            >
              {isDeletingConversation ? 'Excluindo...' : 'Excluir conversa'}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );

  return renderChatLayout();
  /*
  return (
    <div className="flex min-h-screen overflow-hidden bg-[#edf3fb] text-[#142033]">
      <Sidebar />

      <div className="page-shell relative flex min-h-0 flex-col overflow-hidden !px-0 !pb-0">
        <div className="pointer-events-none absolute inset-0 -z-10 overflow-hidden">
          <div className="absolute left-[-8rem] top-[-3rem] h-80 w-80 rounded-full bg-[#183a62]/16 blur-3xl" />
          <div className="absolute right-[-6rem] top-28 h-96 w-96 rounded-full bg-[#4a86cb]/16 blur-3xl" />
          <div className="absolute bottom-[-8rem] left-1/3 h-80 w-80 rounded-full bg-white/45 blur-3xl" />
          <div className="absolute inset-0 bg-[linear-gradient(180deg,rgba(244,248,255,0.42),rgba(237,243,251,0.82))]" />
        </div>

        <div className="mx-auto flex w-full max-w-[1420px] flex-1 min-h-0 flex-col gap-5 px-4 pb-5 pt-4 md:px-6 md:pt-6">
          <section className="relative overflow-hidden rounded-[34px] border border-white/55 bg-[linear-gradient(135deg,rgba(16,35,61,0.98)_0%,rgba(23,54,93,0.98)_34%,rgba(240,246,255,0.98)_34.1%,rgba(248,251,255,0.98)_100%)] p-[1px] shadow-[0_24px_70px_rgba(20,32,51,0.14)]">
            <div className="relative overflow-hidden rounded-[33px] bg-[#f8fbff]/96 px-5 py-5 md:px-6 md:py-6">
              <div className="pointer-events-none absolute inset-0">
                <div className="absolute left-0 top-0 h-40 w-40 rounded-full bg-white/12 blur-2xl" />
                <div className="absolute right-12 top-8 h-36 w-36 rounded-full bg-[#7fb4ff]/18 blur-3xl" />
                <div className="absolute inset-y-0 left-[36.8%] w-px bg-gradient-to-b from-transparent via-white/20 to-transparent max-xl:hidden" />
              </div>

              <div className="relative flex flex-col gap-6 xl:flex-row xl:items-end xl:justify-between">
                <div className="max-w-2xl">
                  <div className="inline-flex items-center rounded-full border border-[#d7e2f0] bg-white/78 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.28em] text-[#788aa2]">
                    Central de atendimento
                  </div>
                  <h1 className="mt-4 max-w-xl text-3xl font-semibold leading-[1.02] tracking-[-0.04em] text-[#10233d] md:text-[2.7rem]">
                    Chat elegante, rápido e claro para seus leads.
                  </h1>
                  <p className="mt-3 max-w-2xl text-sm leading-7 text-[#5a6b84] md:text-[15px]">
                    Visual mais limpo, foco no que pede ação e contexto do lead sem ruído desnecessário.
                  </p>
                </div>

                <div className="grid grid-cols-2 gap-3 md:grid-cols-3 xl:min-w-[430px]">
                  <div className="rounded-[24px] border border-[#dce6f3] bg-white/82 p-4 shadow-[0_10px_30px_rgba(20,32,51,0.06)] backdrop-blur">
                    <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7d8fa9]">Conversas</p>
                    <p className="mt-3 text-3xl font-semibold tracking-[-0.05em] text-[#10233d]">{contacts.length}</p>
                    <p className="mt-1 text-xs text-[#62748b]">fila total em andamento</p>
                  </div>
                  <div className="rounded-[24px] border border-[#dce6f3] bg-white/82 p-4 shadow-[0_10px_30px_rgba(20,32,51,0.06)] backdrop-blur">
                    <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7d8fa9]">Pendentes</p>
                    <p className="mt-3 text-3xl font-semibold tracking-[-0.05em] text-[#10233d]">{unreadContactsCount}</p>
                    <p className="mt-1 text-xs text-[#62748b]">aguardando resposta</p>
                  </div>
                  <div className="col-span-2 rounded-[24px] border border-[#dce6f3] bg-white/82 p-4 shadow-[0_10px_30px_rgba(20,32,51,0.06)] backdrop-blur md:col-span-1">
                    <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-[#7d8fa9]">Prioridade</p>
                    <p className="mt-3 text-3xl font-semibold tracking-[-0.05em] text-[#10233d]">{priorityContactsCount}</p>
                    <p className="mt-1 text-xs text-[#62748b]">quentes ou com ação humana</p>
                  </div>
                </div>
              </div>

              <div className="relative mt-6 flex flex-col gap-3 lg:flex-row lg:items-center">
                <div className="relative flex-1">
                  <Search className="absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-[#71829a]" />
                  <input
                    type="text"
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    placeholder="Pesquisar pessoas, conversas e mensagens..."
                    className="h-14 w-full rounded-[22px] border border-[#d6e1ef] bg-white/92 py-3 pl-11 pr-4 text-sm text-[#142033] placeholder:text-[#8092ab] shadow-[0_14px_34px_rgba(20,32,51,0.08)] outline-none transition-all focus:border-[#1a446f]/30 focus:ring-4 focus:ring-[#1a446f]/10"
                  />
                </div>

                <div className="flex items-center gap-2">
                  {searchTerm && (
                    <Button
                      variant="ghost"
                      size="sm"
                      className="h-11 rounded-full px-4 text-[#5d6f87] hover:bg-[#17365d]/8 hover:text-[#17365d]"
                      onClick={() => setSearchTerm('')}
                    >
                      Limpar
                    </Button>
                  )}
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={handleRefresh}
                    disabled={isRefreshing}
                    className="h-11 rounded-full border border-[#d7e2f0] bg-white/78 px-4 text-[#17365d] shadow-[0_8px_24px_rgba(20,32,51,0.06)] hover:bg-white"
                  >
                    <RefreshCw className={cn('mr-2 h-4 w-4', isRefreshing && 'animate-spin')} />
                    Atualizar
                  </Button>
                  <Button
                    variant="ghost"
                    size="sm"
                    className="hidden h-11 rounded-full border border-[#d7e2f0] bg-white/78 px-4 text-[#17365d] shadow-[0_8px_24px_rgba(20,32,51,0.06)] hover:bg-white md:inline-flex"
                    onClick={() => setIsChatCollapsed((prev) => !prev)}
                  >
                    {isChatCollapsed ? 'Abrir chat' : 'Minimizar chat'}
                  </Button>
                </div>
              </div>
            </div>
          </section>

          <div className="flex min-h-0 flex-1 gap-5">
            <div
              className={cn(
                'relative z-10 flex w-full flex-shrink-0 flex-col overflow-hidden rounded-[30px] border border-white/60 bg-white/72 shadow-[0_24px_60px_rgba(20,32,51,0.12)] backdrop-blur-xl md:w-[390px]',
                'transition-all duration-300 ease-out',
                isChatCollapsed && 'md:w-full',
                !showMobileContacts && 'hidden md:flex'
              )}
            >
              <div className="border-b border-[#e4ecf6] bg-[linear-gradient(180deg,rgba(248,251,255,0.98),rgba(238,244,252,0.96))] px-5 py-5">
                <p className="text-[11px] font-semibold uppercase tracking-[0.3em] text-[#7b8da6]">
                  Fila inteligente
                </p>
                <div className="mt-3 flex items-end justify-between gap-3">
                  <div>
                    <h2 className="text-[1.9rem] font-semibold leading-none tracking-[-0.04em] text-[#142033]">
                      Atendimentos
                    </h2>
                    <p className="mt-2 text-sm text-[#677992]">
                      {searchTerm
                        ? `${filteredContacts.length} conversa(s) combinam com a busca atual`
                        : 'Priorize respostas e acompanhe leads sem poluição visual.'}
                    </p>
                  </div>
                  <div className="rounded-2xl border border-[#dbe5f2] bg-white/84 px-3 py-2 text-right shadow-[0_10px_22px_rgba(20,32,51,0.05)]">
                    <p className="text-[10px] font-semibold uppercase tracking-[0.24em] text-[#7d8fa9]">Visíveis</p>
                    <p className="mt-1 text-xl font-semibold tracking-[-0.04em] text-[#17365d]">
                      {filteredContacts.length}
                    </p>
                  </div>
                </div>

                <div className="mt-5 grid grid-cols-3 gap-2">
                  {contactFilters.map((filter) => {
                    const isActive = contactFilter === filter.id;

                    return (
                      <button
                        key={filter.id}
                        type="button"
                        onClick={() => setContactFilter(filter.id)}
                        className={cn(
                          'rounded-[22px] border px-3 py-3 text-left transition-all',
                          isActive
                            ? 'border-[#17365d]/20 bg-[#17365d] text-white shadow-[0_16px_28px_rgba(23,54,93,0.22)]'
                            : 'border-[#dce6f3] bg-white/78 text-[#53657c] hover:border-[#d7e2f0] hover:bg-white'
                        )}
                      >
                        <div className="flex items-center justify-between gap-2">
                          <span className="text-xs font-semibold">{filter.label}</span>
                          <span
                            className={cn(
                              'inline-flex min-w-6 items-center justify-center rounded-full px-2 py-0.5 text-[10px] font-bold',
                              isActive ? 'bg-white/16 text-white' : 'bg-[#e9f0f9] text-[#17365d]'
                            )}
                          >
                            {filter.count}
                          </span>
                        </div>
                        <p className={cn('mt-1 text-[10px]', isActive ? 'text-white/72' : 'text-[#7b8da5]')}>
                          {filter.helper}
                        </p>
                      </button>
                    );
                  })}
                </div>
              </div>

              <ScrollArea className="min-h-0 flex-1">
                {isLoadingContacts ? (
                  <div className="flex flex-col items-center justify-center gap-3 py-16">
                    <Loader2 className="h-8 w-8 animate-spin text-[#17365d]" />
                    <p className="text-sm text-[#756953]">Carregando conversas...</p>
                  </div>
                ) : filteredContacts.length === 0 ? (
                  <div className="flex flex-col items-center justify-center gap-4 px-5 py-16">
                    <div className="flex h-[4.5rem] w-[4.5rem] items-center justify-center rounded-full border border-[#e4ecf6] bg-white/88 shadow-[0_12px_28px_rgba(20,32,51,0.08)]">
                      <MessageCircle className="h-8 w-8 text-[#7f91aa]" />
                    </div>
                    <div className="text-center">
                      <p className="font-medium text-[#142033]">
                        {searchTerm ? 'Nenhuma conversa encontrada' : 'Nenhuma conversa ainda'}
                      </p>
                      <p className="mt-1 text-sm text-[#71839c]">
                        {searchTerm
                          ? 'Refine a busca ou altere o filtro para ampliar os resultados.'
                          : 'Novos leads aparecerão aqui automaticamente.'}
                      </p>
                    </div>
                  </div>
                ) : (
                  <div className="space-y-3 p-3">
                    {filteredContacts.map((contact) => {
                      const isActive = selectedContactId === contact.id;
                      const classificationMeta = getClassificationMeta(contact.classificacao);
                      const isPriority =
                        contact.needsHumanIntervention ||
                        contact.classificacao?.trim().toLowerCase() === 'quente';

                      return (
                        <button
                          key={contact.id}
                          type="button"
                          onClick={() => {
                            setSelectedContactId(contact.id);
                            if (isChatCollapsed) setIsChatCollapsed(false);
                          }}
                          className={cn(
                            'group relative w-full overflow-hidden rounded-[26px] border p-4 text-left transition-all',
                            isActive
                              ? 'border-[#17365d]/16 bg-[linear-gradient(180deg,rgba(20,42,72,0.08),rgba(255,255,255,0.98))] shadow-[0_18px_34px_rgba(23,54,93,0.16)]'
                              : 'border-transparent bg-white/80 shadow-[0_8px_22px_rgba(20,32,51,0.05)] hover:border-[#dbe5f2] hover:bg-white/94'
                          )}
                        >
                          <div className="absolute inset-x-0 top-0 h-20 bg-[linear-gradient(180deg,rgba(240,246,255,0.9),transparent)]" />

                          <div className="relative flex items-start gap-3">
                            <div className="relative flex-shrink-0">
                              <Avatar className="h-12 w-12 ring-2 ring-white/90 shadow-[0_10px_22px_rgba(20,32,51,0.1)]">
                                <AvatarFallback className="bg-[#17365d]/12 font-semibold text-[#17365d]">
                                  {contact.initials}
                                </AvatarFallback>
                              </Avatar>
                              {contact.unread > 0 && (
                                <span className="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full border-2 border-white bg-[#c47647] px-1 text-[10px] font-bold text-white">
                                  {contact.unread > 9 ? '9+' : contact.unread}
                                </span>
                              )}
                            </div>

                            <div className="min-w-0 flex-1">
                              <div className="flex items-start justify-between gap-3">
                                <div className="min-w-0">
                                  <div className="flex items-center gap-2">
                                    <h3 className="truncate text-[15px] font-semibold text-[#142033]">
                                      {contact.name}
                                    </h3>
                                    {isPriority && <span className="h-2 w-2 rounded-full bg-[#c47647]" />}
                                  </div>
                                  <p className="mt-1 truncate text-xs text-[#71829a]">
                                    {contact.phone || 'Telefone não informado'}
                                  </p>
                                </div>
                                <span className="text-[11px] font-medium text-[#8092ab]">
                                  {contact.timestamp}
                                </span>
                              </div>

                              <p className="mt-3 line-clamp-2 text-sm leading-6 text-[#56687f]">
                                {contact.lastMessage}
                              </p>

                              <div className="mt-3 flex flex-wrap items-center gap-2">
                                {classificationMeta && (
                                  <span
                                    className={cn(
                                      'inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-[11px] font-semibold',
                                      classificationMeta.badgeClass
                                    )}
                                  >
                                    <span className={cn('h-1.5 w-1.5 rounded-full', classificationMeta.dotClass)} />
                                    {classificationMeta.label}
                                  </span>
                                )}
                                {contact.needsHumanIntervention && (
                                  <span className="inline-flex items-center gap-1 rounded-full border border-[#f5cfb9] bg-[#fff2ea] px-2.5 py-1 text-[11px] font-semibold text-[#a45331]">
                                    <AlertTriangle className="h-3.5 w-3.5" />
                                    ação humana
                                  </span>
                                )}
                                {!contact.unread && !contact.needsHumanIntervention && (
                                  <span className="inline-flex items-center gap-1 rounded-full border border-[#dbe5f2] bg-[#f7faff] px-2.5 py-1 text-[11px] text-[#7688a1]">
                                    conversa estável
                                  </span>
                                )}
                              </div>
                            </div>
                          </div>
                        </button>
                      );
                    })}
                  </div>
                )}
              </ScrollArea>
            </div>

            <div
              className={cn(
                'relative z-10 flex min-h-0 flex-1 flex-col overflow-hidden rounded-[34px] border border-white/60 bg-[#fffaf4]/80 shadow-[0_28px_65px_rgba(20,32,51,0.14)] backdrop-blur-xl',
                showMobileContacts && 'hidden md:flex',
                isChatCollapsed && 'md:hidden'
              )}
            >
              {selectedContact ? (
                <>
                  <div className="relative overflow-hidden border-b border-[#e8dccb] bg-[linear-gradient(135deg,rgba(16,35,61,0.98)_0%,rgba(22,53,91,0.98)_48%,rgba(242,232,216,0.96)_48.1%,rgba(251,247,240,0.98)_100%)] p-[1px]">
                    <div className="relative overflow-hidden bg-[#fbf7f0]/98 px-5 py-5 md:px-6">
                      <div className="pointer-events-none absolute inset-0">
                        <div className="absolute -left-6 top-0 h-24 w-24 rounded-full bg-white/12 blur-2xl" />
                        <div className="absolute right-10 top-4 h-20 w-20 rounded-full bg-[#d6b391]/20 blur-2xl" />
                      </div>

                      <div className="relative flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                        <div className="flex items-start gap-4">
                          <Button
                            variant="ghost"
                            size="icon"
                            className="mt-1 flex-shrink-0 rounded-full text-[#17365d] hover:bg-[#17365d]/8 md:hidden"
                            onClick={() => setShowMobileContacts(true)}
                          >
                            <ArrowLeft className="h-5 w-5" />
                          </Button>

                          <Avatar className="h-14 w-14 flex-shrink-0 ring-2 ring-white/90 shadow-[0_12px_24px_rgba(20,32,51,0.12)]">
                            <AvatarFallback className="bg-[#17365d]/14 text-base font-semibold text-[#17365d]">
                              {selectedContact.initials}
                            </AvatarFallback>
                          </Avatar>

                          <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-2">
                              <h2 className="truncate text-[2rem] font-semibold leading-none tracking-[-0.05em] text-[#10233d]">
                                {selectedContact.name}
                              </h2>
                              {selectedClassificationMeta && (
                                <span
                                  className={cn(
                                    'inline-flex items-center gap-1 rounded-full border px-2.5 py-1 text-[11px] font-semibold',
                                    selectedClassificationMeta.badgeClass
                                  )}
                                >
                                  <Tag className="h-3.5 w-3.5" />
                                  {selectedClassificationMeta.label}
                                </span>
                              )}
                              {selectedContact.needsHumanIntervention && (
                                <span className="inline-flex items-center gap-1 rounded-full border border-[#f4cfbc] bg-[#fff1e8] px-2.5 py-1 text-[11px] font-semibold text-[#a25030]">
                                  <AlertTriangle className="h-3.5 w-3.5" />
                                  atenção humana
                                </span>
                              )}
                            </div>

                            <p className="mt-2 text-sm text-[#6e634f]">
                              {selectedContact.phone || 'Telefone não informado'}
                            </p>

                            <div className="mt-4 flex flex-wrap gap-2.5">
                              <div className="rounded-2xl border border-[#e4d8c8] bg-white/80 px-3 py-2 shadow-[0_8px_20px_rgba(20,32,51,0.05)]">
                                <p className="text-[10px] font-semibold uppercase tracking-[0.2em] text-[#8b775f]">Lead</p>
                                <p className="mt-1 text-sm font-semibold text-[#17365d]">#{selectedContact.leadId}</p>
                              </div>
                              <div className="rounded-2xl border border-[#e4d8c8] bg-white/80 px-3 py-2 shadow-[0_8px_20px_rgba(20,32,51,0.05)]">
                                <p className="text-[10px] font-semibold uppercase tracking-[0.2em] text-[#8b775f]">Mensagens</p>
                                <p className="mt-1 text-sm font-semibold text-[#17365d]">{messages.length}</p>
                              </div>
                              <div className="rounded-2xl border border-[#e4d8c8] bg-white/80 px-3 py-2 shadow-[0_8px_20px_rgba(20,32,51,0.05)]">
                                <p className="text-[10px] font-semibold uppercase tracking-[0.2em] text-[#8b775f]">Última atividade</p>
                                <p className="mt-1 text-sm font-semibold text-[#17365d]">{selectedContact.timestamp}</p>
                              </div>
                            </div>
                          </div>
                        </div>

                        <div className="flex items-center gap-2">
                          <Button
                            variant="ghost"
                            size="icon"
                            className="rounded-full border border-[#dfd3c2] bg-white/70 text-[#17365d] shadow-[0_8px_20px_rgba(20,32,51,0.06)] hover:bg-white"
                          >
                            <Phone className="h-5 w-5" />
                          </Button>
                          <Button
                            variant="ghost"
                            size="icon"
                            className="rounded-full border border-[#dfd3c2] bg-white/70 text-[#17365d] shadow-[0_8px_20px_rgba(20,32,51,0.06)] hover:bg-white"
                          >
                            <MoreVertical className="h-5 w-5" />
                          </Button>
                        </div>
                      </div>

                      {(observacoesText || selectedContact.lastMessage) && (
                        <div className="relative mt-5 grid gap-3 lg:grid-cols-[minmax(0,1fr)_260px]">
                          {observacoesText ? (
                            <div className="rounded-[24px] border border-[#e5d9c9] bg-white/82 p-4 shadow-[0_12px_28px_rgba(20,32,51,0.06)]">
                              <div className="flex items-center gap-2 text-[#17365d]">
                                <Info className="h-4 w-4" />
                                <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-[#8b755d]">
                                  Observações do lead
                                </p>
                              </div>
                              <p className="mt-3 line-clamp-3 text-sm leading-6 text-[#5f5647]">
                                {observacoesText}
                              </p>
                            </div>
                          ) : (
                            <div className="rounded-[24px] border border-[#e5d9c9] bg-white/82 p-4 shadow-[0_12px_28px_rgba(20,32,51,0.06)]">
                              <div className="flex items-center gap-2 text-[#17365d]">
                                <Bot className="h-4 w-4" />
                                <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-[#8b755d]">
                                  Contexto
                                </p>
                              </div>
                              <p className="mt-3 text-sm leading-6 text-[#5f5647]">
                                Atendimento limpo e sem anotações adicionais no momento.
                              </p>
                            </div>
                          )}

                          <div className="rounded-[24px] border border-[#e5d9c9] bg-[linear-gradient(180deg,rgba(255,255,255,0.84),rgba(245,236,224,0.86))] p-4 shadow-[0_12px_28px_rgba(20,32,51,0.06)]">
                            <p className="text-[11px] font-semibold uppercase tracking-[0.24em] text-[#8b755d]">
                              Última prévia
                            </p>
                            <p className="mt-3 line-clamp-3 text-sm leading-6 text-[#5f5647]">
                              {selectedContact.lastMessage || 'Sem mensagens registradas ainda.'}
                            </p>
                          </div>
                        </div>
                      )}
                    </div>
                  </div>

                  <div className="relative min-h-0 flex-1 overflow-hidden bg-[radial-gradient(circle_at_top_left,rgba(23,54,93,0.08),transparent_32%),linear-gradient(180deg,#f7efe4_0%,#fcfaf6_100%)]">
                    <div className="pointer-events-none absolute inset-0 opacity-45">
                      <div className="absolute inset-0" style={{ backgroundImage: `url('${chatPatternDataUrl}')` }} />
                    </div>
                    <div className="pointer-events-none absolute inset-x-0 top-0 h-20 bg-gradient-to-b from-white/66 to-transparent" />

                    <ScrollArea ref={scrollAreaRef} className="h-full">
                      <div className="relative mx-auto flex w-full max-w-5xl flex-col gap-6 px-4 py-5 md:px-8 md:py-7">
                        {(searchTerm || selectedContact.needsHumanIntervention) && (
                          <div className="flex flex-wrap items-center justify-center gap-2">
                            {searchTerm && (
                              <div className="rounded-full border border-[#dfd3c2] bg-white/90 px-4 py-1.5 text-xs text-[#6d624f] shadow-[0_8px_20px_rgba(20,32,51,0.06)]">
                                {filteredMessages.length} resultado(s) nesta conversa
                              </div>
                            )}
                            {selectedContact.needsHumanIntervention && (
                              <div className="rounded-full border border-[#f3cfbb] bg-[#fff2ea] px-4 py-1.5 text-xs font-semibold text-[#a45331] shadow-[0_8px_20px_rgba(20,32,51,0.06)]">
                                Conversa sinalizada para intervenção humana
                              </div>
                            )}
                          </div>
                        )}

                        {isLoadingMessages ? (
                          <div className="flex justify-center py-14">
                            <Loader2 className="h-7 w-7 animate-spin text-[#17365d]" />
                          </div>
                        ) : filteredMessages.length === 0 ? (
                          <div className="flex flex-col items-center justify-center gap-4 py-20">
                            <div className="flex h-20 w-20 items-center justify-center rounded-full border border-[#eadfce] bg-white/86 shadow-[0_16px_34px_rgba(20,32,51,0.08)]">
                              <MessageCircle className="h-10 w-10 text-[#8d7f6a]" />
                            </div>
                            <div className="text-center">
                              <p className="font-medium text-[#142033]">
                                {searchTerm ? 'Nenhum resultado encontrado' : 'Nenhuma mensagem ainda'}
                              </p>
                              <p className="mt-1 text-sm text-[#7f735f]">
                                {searchTerm
                                  ? 'Tente outro termo para localizar trechos da conversa.'
                                  : 'Envie uma mensagem para iniciar o atendimento.'}
                              </p>
                            </div>
                          </div>
                        ) : (
                          groupedFilteredMessages.map((group) => (
                            <div key={group.date} className="space-y-4">
                              <div className="flex items-center justify-center py-1">
                                <div className="rounded-full border border-[#dfd3c2] bg-white/90 px-4 py-1.5 text-xs font-medium text-[#6d624f] shadow-[0_8px_20px_rgba(20,32,51,0.06)]">
                                  {group.date}
                                </div>
                              </div>

                              {group.messages.map((message) => {
                                const isUser = message.sender === 'user';
                                const senderMeta = getMessageSenderMeta(message);

                                return (
                                  <div
                                    key={message.id}
                                    className={cn('flex gap-3', isUser ? 'justify-end' : 'justify-start')}
                                  >
                                    {!isUser && (
                                      <div className="mt-8 flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-2xl border border-white/80 bg-white/86 shadow-[0_10px_22px_rgba(20,32,51,0.08)]">
                                        <User className="h-4 w-4 text-[#7c705f]" />
                                      </div>
                                    )}

                                    <div
                                      className={cn(
                                        'flex max-w-[92%] flex-col md:max-w-[74%]',
                                        isUser && 'items-end'
                                      )}
                                    >
                                      <div
                                        className={cn(
                                          'mb-2 inline-flex items-center gap-2 rounded-full px-3 py-1 text-[11px] font-semibold shadow-[0_8px_18px_rgba(20,32,51,0.05)]',
                                          isUser
                                            ? 'bg-[#17365d] text-white'
                                            : 'border border-[#e7dac9] bg-white/88 text-[#17365d]'
                                        )}
                                      >
                                        {message.senderKind === 'assistant' ? (
                                          <Bot className="h-3.5 w-3.5" />
                                        ) : (
                                          <span
                                            className={cn(
                                              'h-1.5 w-1.5 rounded-full',
                                              isUser ? 'bg-white/80' : 'bg-[#c47647]'
                                            )}
                                          />
                                        )}
                                        <span>{senderMeta.label}</span>
                                        {senderMeta.context && (
                                          <span className={isUser ? 'text-white/70' : 'text-[#857964]'}>
                                            {senderMeta.context}
                                          </span>
                                        )}
                                      </div>

                                      <div
                                        className={cn(
                                          'relative overflow-hidden rounded-[24px] border px-4 py-3.5 shadow-[0_18px_36px_rgba(20,32,51,0.08)]',
                                          isUser
                                            ? 'border-[#17365d]/12 bg-[linear-gradient(135deg,#17365d_0%,#2b588d_100%)] text-white'
                                            : 'border-white/80 bg-white/90 text-[#142033] backdrop-blur'
                                        )}
                                      >
                                        {isUser && (
                                          <div className="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(255,255,255,0.16),transparent_52%)]" />
                                        )}

                                        <div className="relative space-y-2.5">
                                          {isAudioMessage(message) && message.mediaUrl && (
                                            <div>
                                              <audio controls className="w-full max-w-xs">
                                                <source src={getMediaUrl(message.mediaUrl)} />
                                              </audio>
                                            </div>
                                          )}

                                          {isImageMessage(message) && message.mediaUrl && (
                                            <div className="relative">
                                              <img
                                                src={getMediaUrl(message.mediaUrl)}
                                                alt="Imagem enviada"
                                                loading="lazy"
                                                className={cn(
                                                  'w-full max-w-sm rounded-[18px] border object-contain',
                                                  isUser
                                                    ? 'border-white/18 bg-white/10'
                                                    : 'border-[#e7dac9] bg-[#f7f2ea]'
                                                )}
                                                onError={(e) => {
                                                  const img = e.currentTarget;
                                                  img.style.display = 'none';
                                                  const parent = img.parentElement;
                                                  if (parent && !parent.querySelector('.image-error-placeholder')) {
                                                    parent.insertAdjacentHTML(
                                                      'beforeend',
                                                      '<div class="image-error-placeholder flex flex-col items-center justify-center rounded-[18px] border border-dashed border-[#d4c7b6] bg-[#f6eee2] p-8"><p class="text-xs text-[#756953] text-center">Imagem não disponível</p><p class="mt-1 text-center text-xs text-[#958774]">Requer autenticação Twilio</p></div>'
                                                    );
                                                  }
                                                }}
                                              />
                                            </div>
                                          )}

                                          {isVideoMessage(message) && message.mediaUrl && (
                                            <div>
                                              <video
                                                controls
                                                className={cn(
                                                  'w-full max-w-sm rounded-[18px] border',
                                                  isUser ? 'border-white/18 bg-black' : 'border-[#e7dac9] bg-black'
                                                )}
                                                preload="metadata"
                                              >
                                                <source src={getMediaUrl(message.mediaUrl)} />
                                                Vídeo não suportado pelo navegador.
                                              </video>
                                            </div>
                                          )}

                                          {(isDocumentMessage(message) || isTwilioGenericMedia(message)) &&
                                            message.mediaUrl && (
                                              <a
                                                href={getMediaUrl(message.mediaUrl)}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className={cn(
                                                  'flex max-w-sm items-center gap-3 rounded-[18px] border p-3 transition-colors',
                                                  isUser
                                                    ? 'border-white/18 bg-white/10 hover:bg-white/14'
                                                    : 'border-[#e2d6c5] bg-[#f7f0e5] hover:bg-[#f2e7d6]'
                                                )}
                                              >
                                                <div
                                                  className={cn(
                                                    'flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-xl',
                                                    isUser ? 'bg-white/14' : 'bg-[#17365d]/10'
                                                  )}
                                                >
                                                  <FileText
                                                    className={cn(
                                                      'h-5 w-5',
                                                      isUser ? 'text-white' : 'text-[#17365d]'
                                                    )}
                                                  />
                                                </div>
                                                <div className="min-w-0 flex-1">
                                                  <p
                                                    className={cn(
                                                      'truncate text-sm font-medium',
                                                      isUser ? 'text-white' : 'text-[#142033]'
                                                    )}
                                                  >
                                                    {getDocumentLabel(message)}
                                                  </p>
                                                  <p className={cn('text-xs', isUser ? 'text-white/70' : 'text-[#7b6f5e]')}>
                                                    Clique para abrir
                                                  </p>
                                                </div>
                                                <ExternalLink
                                                  className={cn(
                                                    'h-4 w-4 flex-shrink-0',
                                                    isUser ? 'text-white/80' : 'text-[#7b6f5e]'
                                                  )}
                                                />
                                              </a>
                                            )}

                                          {getMessageDisplayText(message) && (
                                            <p
                                              className={cn(
                                                'whitespace-pre-wrap break-words text-[15px] leading-7',
                                                isUser ? 'text-white' : 'text-[#142033]'
                                              )}
                                            >
                                              {highlightText(getMessageDisplayText(message), searchTerm)}
                                            </p>
                                          )}

                                          {message.messageType === 'audio' && message.transcription && (
                                            <p
                                              className={cn(
                                                'text-xs leading-6',
                                                isUser ? 'text-white/78' : 'text-[#6c5f4d]'
                                              )}
                                            >
                                              <span className="font-semibold">Transcrição:</span>{' '}
                                              {highlightText(message.transcription, searchTerm)}
                                            </p>
                                          )}
                                        </div>
                                      </div>

                                      <div
                                        className={cn(
                                          'mt-2 flex items-center gap-1.5 px-1 text-[11px]',
                                          isUser ? 'justify-end text-[#f3eadb]' : 'justify-start text-[#8c7d68]'
                                        )}
                                      >
                                        <span>{message.timestamp}</span>
                                        {isUser && <MessageStatus status={message.status} tone="inverted" />}
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

                  <div className="border-t border-[#eadfce] bg-[linear-gradient(180deg,rgba(255,251,244,0.96),rgba(249,240,227,0.98))] px-4 py-4 md:px-6">
                    <div className="mx-auto flex w-full max-w-5xl flex-col gap-3">
                      <div className="flex flex-wrap items-center justify-between gap-2 text-xs text-[#7a6f5b]">
                        <span>
                          {selectedContact.needsHumanIntervention
                            ? 'Conversa marcada para atendimento humano.'
                            : 'Resposta rápida com contexto completo.'}
                        </span>
                        <span>Enter para enviar</span>
                      </div>

                      <div className="flex items-center gap-3 rounded-[28px] border border-[#e5d8c8] bg-white/92 p-2 shadow-[0_16px_34px_rgba(20,32,51,0.08)]">
                        <Button
                          variant="ghost"
                          size="icon"
                          className="h-11 w-11 flex-shrink-0 rounded-full text-[#6e614d] hover:bg-[#17365d]/8 hover:text-[#17365d]"
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
                            placeholder="Escreva uma resposta concisa..."
                            className="h-12 w-full rounded-[20px] border border-transparent bg-transparent px-2 text-sm text-[#142033] placeholder:text-[#8d7f6a] outline-none"
                            disabled={isSending}
                          />
                        </div>

                        <Button
                          onClick={handleSendMessage}
                          disabled={!messageText.trim() || isSending}
                          size="icon"
                          className="h-12 w-12 flex-shrink-0 rounded-full bg-[#17365d] text-white shadow-[0_16px_28px_rgba(23,54,93,0.32)] hover:bg-[#143150] disabled:shadow-none"
                        >
                          {isSending ? <Loader2 className="h-5 w-5 animate-spin" /> : <Send className="h-5 w-5" />}
                        </Button>
                      </div>
                    </div>
                  </div>
                </>
              ) : (
                <div className="relative flex flex-1 items-center justify-center overflow-hidden bg-[linear-gradient(180deg,#f8f1e6_0%,#fcfaf6_100%)] p-8">
                  <div className="pointer-events-none absolute inset-0 opacity-50">
                    <div className="absolute left-10 top-10 h-28 w-28 rounded-full bg-[#17365d]/8 blur-3xl" />
                    <div className="absolute right-12 top-20 h-24 w-24 rounded-full bg-[#c5874c]/12 blur-3xl" />
                  </div>

                  <div className="relative max-w-xl rounded-[32px] border border-white/70 bg-white/72 p-8 text-center shadow-[0_28px_60px_rgba(20,32,51,0.12)] backdrop-blur-xl">
                    <div className="mx-auto mb-6 flex h-24 w-24 items-center justify-center rounded-full border border-[#e8dccb] bg-[linear-gradient(180deg,#17365d_0%,#2a588c_100%)] shadow-[0_18px_34px_rgba(23,54,93,0.25)]">
                      <MessageCircle className="h-11 w-11 text-white" />
                    </div>
                    <h2 className="text-[2rem] font-semibold leading-none tracking-[-0.05em] text-[#10233d]">
                      Sua central de mensagens
                    </h2>
                    <p className="mt-4 text-sm leading-7 text-[#6f6450] md:text-[15px]">
                      Abra uma conversa à esquerda para ver o histórico completo, localizar trechos rapidamente e responder com clareza.
                    </p>
                  </div>
                </div>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
  */
}
