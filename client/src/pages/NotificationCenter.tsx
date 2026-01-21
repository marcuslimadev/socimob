import { useState } from 'react';
import { motion } from 'framer-motion';
import { Bell, Trash2, CheckCircle, AlertCircle, Info, MessageSquare, Users, Home } from 'lucide-react';
import Sidebar from '@/components/Sidebar';

interface Notification {
  id: string;
  type: 'success' | 'warning' | 'info' | 'message';
  title: string;
  description: string;
  timestamp: string;
  read: boolean;
  icon: React.ReactNode;
}

export default function NotificationCenter() {
  const [notifications, setNotifications] = useState<Notification[]>([
    {
      id: '1',
      type: 'success',
      title: 'Lead Convertido!',
      description: 'João Silva foi movido para negociação',
      timestamp: 'Há 5 minutos',
      read: false,
      icon: <CheckCircle size={24} className="text-green-400" />,
    },
    {
      id: '2',
      type: 'message',
      title: 'Nova Mensagem',
      description: 'Maria Santos: Posso agendar uma visita?',
      timestamp: 'Há 15 minutos',
      read: false,
      icon: <MessageSquare size={24} className="text-blue-400" />,
    },
    {
      id: '3',
      type: 'warning',
      title: 'Alerta de Preço',
      description: 'Imóvel em Pinheiros teve preço reduzido',
      timestamp: 'Há 30 minutos',
      read: true,
      icon: <AlertCircle size={24} className="text-yellow-400" />,
    },
    {
      id: '4',
      type: 'info',
      title: 'Novo Lead',
      description: 'Pedro Costa entrou em contato pelo portal',
      timestamp: 'Há 1 hora',
      read: true,
      icon: <Users size={24} className="text-cyan-400" />,
    },
    {
      id: '5',
      type: 'success',
      title: 'Imóvel Alugado',
      description: 'Apartamento em Itaim Bibi foi alugado',
      timestamp: 'Há 2 horas',
      read: true,
      icon: <Home size={24} className="text-purple-400" />,
    },
  ]);

  const [filter, setFilter] = useState('todos');

  const filteredNotifications = notifications.filter((notif) => {
    if (filter === 'nao-lidas') return !notif.read;
    if (filter === 'lidas') return notif.read;
    return true;
  });

  const markAsRead = (id: string) => {
    setNotifications(notifs =>
      notifs.map(n => n.id === id ? { ...n, read: true } : n)
    );
  };

  const deleteNotification = (id: string) => {
    setNotifications(notifs => notifs.filter(n => n.id !== id));
  };

  const markAllAsRead = () => {
    setNotifications(notifs => notifs.map(n => ({ ...n, read: true })));
  };

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: { staggerChildren: 0.05, delayChildren: 0.1 },
    },
  };

  const itemVariants = {
    hidden: { opacity: 0, x: -20 },
    visible: { opacity: 1, x: 0 },
  };

  const unreadCount = notifications.filter(n => !n.read).length;

  return (
    <div className="flex">
      <Sidebar />

      <div className="flex-1 md:ml-80 min-h-screen p-4 md:p-8">
        <motion.div
          variants={containerVariants}
          initial="hidden"
          animate="visible"
          className="max-w-4xl mx-auto"
        >
          <motion.div variants={itemVariants} className="mb-8">
            <div className="flex items-center justify-between mb-4">
              <div>
                <h1 className="text-4xl font-bold gradient-text mb-2 flex items-center gap-3">
                  <Bell size={40} />
                  Notificações
                </h1>
                <p className="text-muted-foreground">
                  Você tem {unreadCount} notificação{unreadCount !== 1 ? 's' : ''} não lida{unreadCount !== 1 ? 's' : ''}
                </p>
              </div>
              {unreadCount > 0 && (
                <motion.button
                  whileHover={{ scale: 1.05 }}
                  whileTap={{ scale: 0.95 }}
                  onClick={markAllAsRead}
                  className="px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 rounded-lg font-semibold text-white transition-all glow-md hover:glow-lg"
                >
                  Marcar todas como lidas
                </motion.button>
              )}
            </div>
          </motion.div>

          <motion.div variants={itemVariants} className="glass-panel p-6 rounded-2xl mb-8">
            <div className="flex gap-3">
              {['todos', 'nao-lidas', 'lidas'].map((option) => (
                <motion.button
                  key={option}
                  whileHover={{ scale: 1.05 }}
                  whileTap={{ scale: 0.95 }}
                  onClick={() => setFilter(option)}
                  className={`px-4 py-2 rounded-lg font-semibold transition-all ${
                    filter === option
                      ? 'bg-gradient-to-r from-blue-500 to-blue-600 text-white'
                      : 'bg-white/10 text-foreground hover:bg-white/20'
                  }`}
                >
                  {option === 'todos' && 'Todas'}
                  {option === 'nao-lidas' && `Não lidas (${unreadCount})`}
                  {option === 'lidas' && `Lidas (${notifications.filter(n => n.read).length})`}
                </motion.button>
              ))}
            </div>
          </motion.div>

          <motion.div
            variants={containerVariants}
            initial="hidden"
            animate="visible"
            className="space-y-4"
          >
            {filteredNotifications.length === 0 ? (
              <motion.div
                variants={itemVariants}
                className="text-center py-12"
              >
                <Bell size={48} className="mx-auto mb-4 text-muted-foreground opacity-50" />
                <p className="text-lg font-semibold text-foreground mb-2">Nenhuma notificação</p>
                <p className="text-muted-foreground">
                  {filter === 'nao-lidas' ? 'Você está em dia! Todas as notificações foram lidas.' : 'Nenhuma notificação para exibir.'}
                </p>
              </motion.div>
            ) : (
              filteredNotifications.map((notification, index) => (
                <motion.div
                  key={notification.id}
                  variants={itemVariants}
                  transition={{ delay: 0.3 + index * 0.05 }}
                  className={`glass-panel p-6 rounded-2xl border-l-4 cursor-pointer transition-all hover:bg-white/10 ${
                    !notification.read ? 'border-l-blue-500 bg-white/5' : 'border-l-white/20'
                  }`}
                  onClick={() => markAsRead(notification.id)}
                >
                  <div className="flex items-start gap-4">
                    <div className="flex-shrink-0">
                      {notification.icon}
                    </div>

                    <div className="flex-1">
                      <div className="flex items-start justify-between mb-2">
                        <h3 className="font-bold text-lg text-foreground">{notification.title}</h3>
                        <span className="text-xs text-muted-foreground">{notification.timestamp}</span>
                      </div>
                      <p className="text-muted-foreground mb-3">{notification.description}</p>

                      {!notification.read && (
                        <motion.div
                          initial={{ scale: 0 }}
                          animate={{ scale: 1 }}
                          className="inline-block px-3 py-1 bg-gradient-to-r from-blue-500/20 to-blue-600/20 border border-blue-500/30 rounded-full text-xs font-semibold text-blue-400"
                        >
                          Não lida
                        </motion.div>
                      )}
                    </div>

                    <motion.button
                      whileHover={{ scale: 1.1 }}
                      whileTap={{ scale: 0.95 }}
                      onClick={(e) => {
                        e.stopPropagation();
                        deleteNotification(notification.id);
                      }}
                      className="flex-shrink-0 p-2 hover:bg-white/10 rounded-lg transition-all"
                    >
                      <Trash2 size={18} className="text-muted-foreground hover:text-destructive" />
                    </motion.button>
                  </div>
                </motion.div>
              ))
            )}
          </motion.div>
        </motion.div>
      </div>
    </div>
  );
}
