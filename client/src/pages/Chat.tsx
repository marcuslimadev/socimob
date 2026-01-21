import { useState } from 'react';
import { motion } from 'framer-motion';
import { Send, Phone, Video, Search, MoreVertical, Smile, Paperclip } from 'lucide-react';
import Sidebar from '@/components/Sidebar';

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
}

export default function Chat() {
  const [selectedContactId, setSelectedContactId] = useState('1');
  const [messageText, setMessageText] = useState('');
  const [messages, setMessages] = useState<Message[]>([
    { id: '1', sender: 'contact', text: 'Olá! Tudo bem?', timestamp: '10:30', read: true },
    { id: '2', sender: 'user', text: 'Oi! Tudo certo! Como posso ajudar?', timestamp: '10:31', read: true },
    { id: '3', sender: 'contact', text: 'Gostaria de saber mais sobre o apartamento em Pinheiros', timestamp: '10:32', read: true },
    { id: '4', sender: 'user', text: 'Claro! É um apartamento de 2 quartos, muito bem localizado 🏠', timestamp: '10:33', read: true },
  ]);

  const contacts: Contact[] = [
    { id: '1', name: 'João Silva', avatar: '👨‍💼', lastMessage: 'Claro! É um apartamento de 2 quartos...', timestamp: 'Agora', unread: 0, online: true },
    { id: '2', name: 'Maria Santos', avatar: '👩‍💼', lastMessage: 'Obrigada pela informação!', timestamp: 'Há 5m', unread: 2, online: true },
    { id: '3', name: 'Pedro Costa', avatar: '👨‍💻', lastMessage: 'Posso agendar uma visita?', timestamp: 'Há 1h', unread: 0, online: false },
    { id: '4', name: 'Ana Oliveira', avatar: '👩‍🎓', lastMessage: 'Qual o valor do aluguel?', timestamp: 'Ontem', unread: 1, online: false },
  ];

  const selectedContact = contacts.find(c => c.id === selectedContactId);

  const handleSendMessage = () => {
    if (messageText.trim()) {
      const newMessage: Message = {
        id: String(messages.length + 1),
        sender: 'user',
        text: messageText,
        timestamp: new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }),
        read: false,
      };
      setMessages([...messages, newMessage]);
      setMessageText('');

      setTimeout(() => {
        const replyMessage: Message = {
          id: String(messages.length + 2),
          sender: 'contact',
          text: 'Entendi! Deixe-me verificar e retorno em breve.',
          timestamp: new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' }),
          read: false,
        };
        setMessages(prev => [...prev, replyMessage]);
      }, 1000);
    }
  };

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
            {contacts.map((contact, index) => (
              <motion.button
                key={contact.id}
                initial={{ opacity: 0, x: -20 }}
                animate={{ opacity: 1, x: 0 }}
                transition={{ delay: index * 0.05 }}
                onClick={() => setSelectedContactId(contact.id)}
                className={`w-full p-4 border-b border-white/10 transition-all hover:bg-white/5 ${
                  selectedContactId === contact.id ? 'bg-white/10' : ''
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
            ))}
          </div>
        </div>

        {/* Chat Area */}
        {selectedContact && (
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
                    {selectedContact.online ? 'Online' : 'Offline'}
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
                    className={`max-w-xs px-4 py-2 rounded-2xl ${
                      message.sender === 'user'
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
        )}
      </div>
    </div>
  );
}
