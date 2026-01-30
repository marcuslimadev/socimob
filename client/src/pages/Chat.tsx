import { useState, useEffect, useRef } from 'react';
import { motion } from 'framer-motion';
import { Send, Phone, Video, Search, MoreVertical, Smile, Paperclip } from 'lucide-react';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';
import { toast } from 'sonner';

interface Message {
  id: string;
  sender: 'user' | 'contact';
  text: string;
  timestamp: string;
  read: boolean;
}

interface Contact {
  id: string;
  name: string;
  avatar: string;
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
  const messagesEndRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    fetchContacts();
  }, []);

  useEffect(() => {
    if (selectedContactId) {
      fetchMessages(selectedContactId);
      // Optional: Set up polling
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

  const fetchContacts = async () => {
    try {
      setIsLoadingContacts(true);
      const response = await api.get('/admin/conversas');
      if (response.data.success) {
        const mappedContacts = response.data.data
          .filter((item: any) => item && item.id != null && item.lead_id != null) // Skip invalid conversations
          .map((item: any) => ({
            id: item.id.toString(),
            name: item.lead_nome || item.lead_telefone || 'Sem nome',
            avatar: getAvatar(item.lead_nome || item.lead_telefone),
            lastMessage: item.ultima_mensagem || 'Sem mensagens',
            timestamp: formatTime(item.ultima_atividade || item.created_at),
            unread: item.mensagens_nao_lidas || 0,
            online: false, // Not supported by API yet
            leadId: item.lead_id,
            phone: item.lead_telefone,
          }));
        setContacts(mappedContacts);
        
        // Auto-select contact from URL query param
        const query = new URLSearchParams(window.location.search);
        const targetLeadId = query.get('leadId');
        
        if (targetLeadId && !selectedContactId) {
          const target = mappedContacts.find((c: Contact) => c.leadId?.toString() === targetLeadId);
          if (target) {
            setSelectedContactId(target.id);
          } else {
            console.warn(`Lead ${targetLeadId} not found in contacts`);
            // toast.warning('Conversa não encontrada para este lead.');
          }
        }
        
        if (mappedContacts.length > 0 && !selectedContactId && !targetLeadId) {
          // Optional: Select first contact automatically
          // setSelectedContactId(mappedContacts[0].id);
        }
      }
    } catch (error) {
      console.error('Erro ao buscar conversas:', error);
      toast.error('Erro ao carregar conversas');
    } finally {
      setIsLoadingContacts(false);
    }
  };

  const fetchMessages = async (contactId: string) => {
    try {
      // Don't set loading on every poll to avoid sticky UI
      // setIsLoadingMessages(true); 
      const response = await api.get(`/admin/conversas/${contactId}/mensagens`);
      if (response.data.success) {
        const mappedMessages = response.data.data
          .filter((item: any) => item && item.id != null) // Skip messages without ID
          .map((item: any) => ({
            id: item.id.toString(),
            sender: item.direction === 'outgoing' ? 'user' : 'contact',
            text: item.content,
            timestamp: formatTime(item.created_at),
            read: !!item.read_at,
          }));
        setMessages(mappedMessages);
      }
    } catch (error) {
      console.error('Erro ao buscar mensagens:', error);
      // Don't toast on polling errors to avoid spam
    } finally {
      setIsLoadingMessages(false);
    }
  };

  const handleSendMessage = async () => {
    if (!messageText.trim() || !selectedContactId) return;

    try {
      const tempId = Date.now().toString();
      const newMessage: Message = {
        id: tempId,
        sender: 'user',
        text: messageText,
        timestamp: formatTime(new Date().toISOString()),
        read: false,
      };

      // Optimistic update
      setMessages((prev) => [...prev, newMessage]);
      setMessageText('');
      scrollToBottom();

      const response = await api.post(`/admin/conversas/${selectedContactId}/mensagens`, {
        content: newMessage.text,
      });

      if (!response.data.success) {
        throw new Error('Falha ao enviar');
      }

      // Update with real ID from server if needed, or just re-fetch
      // For now, simpler to just refetch or assume success
      fetchMessages(selectedContactId);

    } catch (error) {
      console.error('Erro ao enviar mensagem:', error);
      toast.error('Erro ao enviar mensagem');
      // Revert optimistic update ideally, but for now just letting user know
    }
  };

  const getAvatar = (name: string) => {
    // Simple avatar based on initials or emoji
    return '👤';
  };

  const formatTime = (dateString: string) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
  };

  const selectedContact = contacts.find(c => c.id === selectedContactId);

  return (
    <div className="flex">
      <Sidebar />

      <div className="flex-1 md:ml-80 min-h-screen bg-gradient-to-b from-background to-background/80 flex">
        {/* Contacts List */}
        <div className="hidden md:flex w-80 flex-col border-r border-white/10">
          <div className="p-4 border-b border-white/10">
            <h2 className="text-2xl font-bold text-foreground mb-4">Mensagens</h2>
            <div className="relative">
              <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" size={18} />
              <input
                type="text"
                placeholder="Buscar conversa..."
                className="w-full pl-10 pr-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
              />
            </div>
          </div>

          <div className="flex-1 overflow-y-auto">
            {isLoadingContacts ? (
              <div className="flex justify-center p-4">
                <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary"></div>
              </div>
            ) : (
              contacts.map((contact, index) => (
                <motion.button
                  key={contact.id}
                  initial={{ opacity: 0, x: -20 }}
                  animate={{ opacity: 1, x: 0 }}
                  transition={{ delay: index * 0.05 }}
                  onClick={() => setSelectedContactId(contact.id)}
                  className={`w-full p-4 border-b border-white/10 transition-all hover:bg-white/5 ${selectedContactId === contact.id ? 'bg-white/10' : ''
                    }`}
                >
                  <div className="flex items-start gap-3">
                    <div className="relative">
                      <div className="w-12 h-12 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-xl">
                        {contact.avatar}
                      </div>
                      {contact.online && (
                        <div className="absolute bottom-0 right-0 w-3 h-3 bg-green-500 rounded-full border-2 border-background" />
                      )}
                    </div>

                    <div className="flex-1 text-left">
                      <div className="flex items-center justify-between mb-1">
                        <h3 className="font-semibold text-foreground">{contact.name}</h3>
                        <span className="text-xs text-muted-foreground">{contact.timestamp}</span>
                      </div>
                      <p className="text-sm text-muted-foreground line-clamp-1">{contact.lastMessage}</p>
                    </div>

                    {contact.unread > 0 && (
                      <motion.div
                        initial={{ scale: 0 }}
                        animate={{ scale: 1 }}
                        className="w-5 h-5 bg-gradient-to-r from-red-500 to-pink-500 rounded-full flex items-center justify-center text-xs font-bold text-white"
                      >
                        {contact.unread}
                      </motion.div>
                    )}
                  </div>
                </motion.button>
              ))
            )}
          </div>
        </div>

        {/* Chat Area */}
        {selectedContact ? (
          <motion.div
            initial={{ opacity: 0 }}
            animate={{ opacity: 1 }}
            className="flex-1 flex flex-col"
          >
            {/* Chat Header */}
            <div className="p-4 border-b border-white/10 flex items-center justify-between bg-glass-panel">
              <div className="flex items-center gap-3">
                <div className="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-lg">
                  {selectedContact.avatar}
                </div>
                <div>
                  <h3 className="font-semibold text-foreground">{selectedContact.name}</h3>
                  <p className="text-xs text-muted-foreground">
                    {selectedContact.phone}
                  </p>
                </div>
              </div>

              <div className="flex items-center gap-2">
                <motion.button
                  whileHover={{ scale: 1.1 }}
                  whileTap={{ scale: 0.95 }}
                  className="p-2 hover:bg-white/10 rounded-lg transition-all"
                >
                  <Phone size={20} className="text-blue-400" />
                </motion.button>
                <motion.button
                  whileHover={{ scale: 1.1 }}
                  whileTap={{ scale: 0.95 }}
                  className="p-2 hover:bg-white/10 rounded-lg transition-all"
                >
                  <Video size={20} className="text-blue-400" />
                </motion.button>
                <motion.button
                  whileHover={{ scale: 1.1 }}
                  whileTap={{ scale: 0.95 }}
                  className="p-2 hover:bg-white/10 rounded-lg transition-all"
                >
                  <MoreVertical size={20} className="text-muted-foreground" />
                </motion.button>
              </div>
            </div>

            {/* Messages */}
            <div className="flex-1 overflow-y-auto p-4 space-y-4">
              {messages.map((message, index) => (
                <motion.div
                  key={message.id}
                  initial={{ opacity: 0, y: 10 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ delay: index * 0.05 }}
                  className={`flex ${message.sender === 'user' ? 'justify-end' : 'justify-start'}`}
                >
                  <div
                    className={`max-w-xs px-4 py-2 rounded-2xl ${message.sender === 'user'
                        ? 'bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-br-none'
                        : 'bg-white/10 text-foreground rounded-bl-none'
                      }`}
                  >
                    <p className="text-sm">{message.text}</p>
                    <p className={`text-xs mt-1 ${message.sender === 'user' ? 'text-blue-100' : 'text-muted-foreground'}`}>
                      {message.timestamp}
                    </p>
                  </div>
                </motion.div>
              ))}
              <div ref={messagesEndRef} />
            </div>

            {/* Input Area */}
            <div className="p-4 border-t border-white/10 bg-glass-panel">
              <div className="flex items-end gap-3">
                <motion.button
                  whileHover={{ scale: 1.1 }}
                  whileTap={{ scale: 0.95 }}
                  className="p-2 hover:bg-white/10 rounded-lg transition-all"
                >
                  <Paperclip size={20} className="text-muted-foreground" />
                </motion.button>

                <div className="flex-1 relative">
                  <input
                    type="text"
                    value={messageText}
                    onChange={(e) => setMessageText(e.target.value)}
                    onKeyPress={(e) => e.key === 'Enter' && handleSendMessage()}
                    placeholder="Digite uma mensagem..."
                    className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-2xl text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                  />
                  <motion.button
                    whileHover={{ scale: 1.1 }}
                    whileTap={{ scale: 0.95 }}
                    className="absolute right-2 top-1/2 -translate-y-1/2 p-2 hover:bg-white/10 rounded-lg transition-all"
                  >
                    <Smile size={20} className="text-muted-foreground" />
                  </motion.button>
                </div>

                <motion.button
                  whileHover={{ scale: 1.1 }}
                  whileTap={{ scale: 0.95 }}
                  onClick={handleSendMessage}
                  className="p-3 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 rounded-full text-white transition-all glow-md hover:glow-lg"
                >
                  <Send size={20} />
                </motion.button>
              </div>
            </div>
          </motion.div>
        ) : (
          <div className="flex-1 flex flex-col items-center justify-center p-8 text-center text-muted-foreground">
            <p className="text-xl font-semibold mb-2">Selecione uma conversa</p>
            <p>Escolha um contato para iniciar o chat</p>
          </div>
        )}
      </div>
    </div>
  );
}
