import { useState, useEffect, useRef, useMemo } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
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
  Loader2
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
  const messagesEndRef = useRef<HTMLDivElement>(null);
  const inputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    fetchContacts();
  }, []);

  useEffect(() => {
    if (selectedContactId) {
      fetchMessages(selectedContactId);
      setShowMobileContacts(false);
      const interval = setInterval(() => fetchMessages(selectedContactId), 10000);
      return () => clearInterval(interval);
    }
  }, [selectedContactId]);

  useEffect(() => {
    scrollToBottom();
  }, [messages]);

  const scrollToBottom = () => {
    messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
  };

  const getInitials = (name: string) => {
    if (!name) return '?';
    const parts = name.trim().split(' ');
    if (parts.length >= 2) {
      return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }
    return name.slice(0, 2).toUpperCase();
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
            };
          });
        setContacts(mappedContacts);

        const query = new URLSearchParams(window.location.search);
        const targetLeadId = query.get('leadId');

        if (targetLeadId && !selectedContactId) {
          const target = mappedContacts.find((c: Contact) => c.leadId?.toString() === targetLeadId);
          if (target) {
            setSelectedContactId(target.id);
          }
        }
      }
    } catch {
      toast.error('Erro ao carregar conversas');
    } finally {
      setIsLoadingContacts(false);
    }
  };

  const fetchMessages = async (contactId: string) => {
    try {
      const response = await api.get(`/admin/conversas/${contactId}/mensagens`);
      if (response.data.success) {
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
          }));
        setMessages(mappedMessages);
      }
    } catch {
      // Silent fail on polling
    } finally {
      setIsLoadingMessages(false);
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

    try {
      const response = await api.post(`/admin/conversas/${selectedContactId}/mensagens`, {
        content: text,
      });

      if (!response.data.success) {
        throw new Error('Falha ao enviar');
      }

      setMessages((prev) =>
        prev.map((m) =>
          m.id === tempId ? { ...m, status: 'sent' } : m
        )
      );

      setTimeout(() => fetchMessages(selectedContactId), 1000);
    } catch {
      toast.error('Erro ao enviar mensagem');
      setMessages((prev) => prev.filter((m) => m.id !== tempId));
    } finally {
      setIsSending(false);
      inputRef.current?.focus();
    }
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

  const groupedMessages = useMemo(() => {
    const groups: { date: string; messages: Message[] }[] = [];
    let currentDate = '';

    messages.forEach((message) => {
      const dateKey = message.rawDate.toDateString();
      if (dateKey !== currentDate) {
        currentDate = dateKey;
        groups.push({
          date: formatDateSeparator(message.rawDate),
          messages: [message],
        });
      } else {
        groups[groups.length - 1].messages.push(message);
      }
    });

    return groups;
  }, [messages]);

  const filteredContacts = useMemo(() => {
    if (!searchTerm) return contacts;
    const term = searchTerm.toLowerCase();
    return contacts.filter(
      (c) =>
        c.name.toLowerCase().includes(term) ||
        c.phone?.toLowerCase().includes(term)
    );
  }, [contacts, searchTerm]);

  const selectedContact = contacts.find((c) => c.id === selectedContactId);

  const MessageStatus = ({ status }: { status?: string }) => {
    if (status === 'sending') return <Clock className="w-3 h-3 text-muted-foreground" />;
    if (status === 'sent') return <Check className="w-3 h-3 text-muted-foreground" />;
    if (status === 'delivered') return <CheckCheck className="w-3 h-3 text-muted-foreground" />;
    if (status === 'read') return <CheckCheck className="w-3 h-3 text-blue-400" />;
    return null;
  };

  return (
    <div className="flex h-screen overflow-hidden">
      <Sidebar />

      <div className="flex-1 flex md:ml-80">
        {/* Contacts Panel */}
        <div
          className={cn(
            'w-full md:w-96 flex-shrink-0 flex flex-col bg-card border-r border-border',
            'transition-transform duration-300 ease-in-out',
            !showMobileContacts && 'hidden md:flex'
          )}
        >
          {/* Header */}
          <div className="p-4 border-b border-border bg-card/80 backdrop-blur-sm">
            <h1 className="text-2xl font-bold text-foreground mb-4">Conversas</h1>
            <div className="relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground w-4 h-4" />
              <input
                type="text"
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                placeholder="Buscar conversa..."
                className="w-full pl-10 pr-4 py-2.5 bg-muted/50 border-0 rounded-xl text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary/50 transition-all"
              />
            </div>
          </div>

          {/* Contacts List */}
          <ScrollArea className="flex-1">
            {isLoadingContacts ? (
              <div className="flex flex-col items-center justify-center py-12 gap-3">
                <Loader2 className="w-8 h-8 animate-spin text-primary" />
                <p className="text-sm text-muted-foreground">Carregando conversas...</p>
              </div>
            ) : filteredContacts.length === 0 ? (
              <div className="flex flex-col items-center justify-center py-12 gap-3 px-4">
                <div className="w-16 h-16 rounded-full bg-muted/50 flex items-center justify-center">
                  <MessageCircle className="w-8 h-8 text-muted-foreground" />
                </div>
                <p className="text-sm text-muted-foreground text-center">
                  {searchTerm ? 'Nenhuma conversa encontrada' : 'Nenhuma conversa ainda'}
                </p>
              </div>
            ) : (
              <div className="divide-y divide-border">
                {filteredContacts.map((contact, index) => (
                  <motion.button
                    key={contact.id}
                    initial={{ opacity: 0, y: 10 }}
                    animate={{ opacity: 1, y: 0 }}
                    transition={{ delay: index * 0.03 }}
                    onClick={() => setSelectedContactId(contact.id)}
                    className={cn(
                      'w-full p-4 flex items-center gap-3 transition-colors text-left',
                      'hover:bg-muted/50',
                      selectedContactId === contact.id && 'bg-primary/10 hover:bg-primary/15'
                    )}
                  >
                    <Avatar className="w-12 h-12 flex-shrink-0">
                      <AvatarFallback className="bg-gradient-to-br from-primary/80 to-primary text-primary-foreground font-semibold">
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
                  </motion.button>
                ))}
              </div>
            )}
          </ScrollArea>
        </div>

        {/* Chat Area */}
        <div className={cn(
          'flex-1 flex flex-col bg-background',
          showMobileContacts && 'hidden md:flex'
        )}>
          {selectedContact ? (
            <>
              {/* Chat Header */}
              <div className="px-4 py-3 border-b border-border bg-card/80 backdrop-blur-sm flex items-center gap-3">
                <Button
                  variant="ghost"
                  size="icon"
                  className="md:hidden flex-shrink-0"
                  onClick={() => setShowMobileContacts(true)}
                >
                  <ArrowLeft className="w-5 h-5" />
                </Button>

                <Avatar className="w-10 h-10 flex-shrink-0">
                  <AvatarFallback className="bg-gradient-to-br from-primary/80 to-primary text-primary-foreground font-semibold text-sm">
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
              <ScrollArea className="flex-1 p-4">
                <div className="max-w-3xl mx-auto space-y-4">
                  {isLoadingMessages ? (
                    <div className="flex justify-center py-8">
                      <Loader2 className="w-6 h-6 animate-spin text-primary" />
                    </div>
                  ) : messages.length === 0 ? (
                    <div className="flex flex-col items-center justify-center py-16 gap-4">
                      <div className="w-20 h-20 rounded-full bg-muted/30 flex items-center justify-center">
                        <MessageCircle className="w-10 h-10 text-muted-foreground/50" />
                      </div>
                      <div className="text-center">
                        <p className="text-muted-foreground font-medium">Nenhuma mensagem</p>
                        <p className="text-sm text-muted-foreground/70">Envie uma mensagem para iniciar a conversa</p>
                      </div>
                    </div>
                  ) : (
                    <AnimatePresence initial={false}>
                      {groupedMessages.map((group) => (
                        <div key={group.date} className="space-y-3">
                          {/* Date Separator */}
                          <div className="flex items-center justify-center py-2">
                            <span className="px-3 py-1 bg-muted/50 rounded-full text-xs text-muted-foreground font-medium">
                              {group.date}
                            </span>
                          </div>

                          {/* Messages */}
                          {group.messages.map((message) => (
                            <motion.div
                              key={message.id}
                              initial={{ opacity: 0, y: 10, scale: 0.95 }}
                              animate={{ opacity: 1, y: 0, scale: 1 }}
                              transition={{ duration: 0.2 }}
                              className={cn(
                                'flex',
                                message.sender === 'user' ? 'justify-end' : 'justify-start'
                              )}
                            >
                              <div
                                className={cn(
                                  'max-w-[75%] md:max-w-[65%] px-4 py-2.5 rounded-2xl shadow-sm',
                                  message.sender === 'user'
                                    ? 'bg-primary text-primary-foreground rounded-br-md'
                                    : 'bg-card border border-border rounded-bl-md'
                                )}
                              >
                                <p className="text-sm whitespace-pre-wrap break-words leading-relaxed">
                                  {message.text}
                                </p>
                                <div
                                  className={cn(
                                    'flex items-center justify-end gap-1.5 mt-1',
                                    message.sender === 'user'
                                      ? 'text-primary-foreground/70'
                                      : 'text-muted-foreground'
                                  )}
                                >
                                  <span className="text-[10px]">{message.timestamp}</span>
                                  {message.sender === 'user' && (
                                    <MessageStatus status={message.status} />
                                  )}
                                </div>
                              </div>
                            </motion.div>
                          ))}
                        </div>
                      ))}
                    </AnimatePresence>
                  )}
                  <div ref={messagesEndRef} />
                </div>
              </ScrollArea>

              {/* Input Area */}
              <div className="p-4 border-t border-border bg-card/80 backdrop-blur-sm">
                <div className="max-w-3xl mx-auto flex items-end gap-3">
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
                      className="w-full px-4 py-3 bg-muted/50 border-0 rounded-2xl text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-primary/50 transition-all"
                      disabled={isSending}
                    />
                  </div>

                  <Button
                    onClick={handleSendMessage}
                    disabled={!messageText.trim() || isSending}
                    size="icon"
                    className="flex-shrink-0 w-11 h-11 rounded-full bg-primary hover:bg-primary/90 text-primary-foreground shadow-lg shadow-primary/25 disabled:opacity-50 disabled:shadow-none"
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
            <div className="flex-1 flex flex-col items-center justify-center p-8 bg-muted/5">
              <div className="text-center max-w-md">
                <div className="w-24 h-24 mx-auto mb-6 rounded-full bg-gradient-to-br from-primary/20 to-primary/5 flex items-center justify-center">
                  <MessageCircle className="w-12 h-12 text-primary/60" />
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
  );
}
