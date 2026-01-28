import { useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import {
  Bell,
  Trash2,
  CheckCircle,
  AlertCircle,
  Info,
  MessageSquare,
  Users,
  Home,
  ClipboardCheck,
  FileSignature,
  Loader2,
} from 'lucide-react';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';
import { toast } from 'sonner';

interface Notification {
  id: number;
  type: string;
  title: string;
  message: string;
  created_at: string;
  is_read: boolean;
}

export default function NotificationCenter() {
  const [filter, setFilter] = useState('todos');
  const [notifications, setNotifications] = useState<Notification[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [summary, setSummary] = useState<{ total: number; unread: number }>({ total: 0, unread: 0 });

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

  useEffect(() => {
    fetchNotifications();
  }, [filter, page]);

  useEffect(() => {
    fetchSummary();
  }, []);

  const fetchSummary = async () => {
    try {
      const response = await api.get('/notifications/summary');
      setSummary({
        total: response.data.total || 0,
        unread: response.data.unread || 0,
      });
    } catch (error) {
      console.error('Erro ao carregar resumo de notificacoes:', error);
    }
  };

  const fetchNotifications = async () => {
    try {
      setIsLoading(true);
      const status =
        filter === 'nao-lidas' ? 'unread' : filter === 'lidas' ? 'read' : undefined;
      const response = await api.get('/notifications', {
        params: {
          status,
          per_page: 10,
          page,
        },
      });
      setNotifications(response.data.data || []);
      setPage(response.data.current_page || 1);
      setLastPage(response.data.last_page || 1);
    } catch (error) {
      console.error('Erro ao carregar notificacoes:', error);
      toast.error('Erro ao carregar notificacoes');
    } finally {
      setIsLoading(false);
    }
  };

  const markAsRead = async (id: number) => {
    try {
      await api.post(`/notifications/${id}/read`);
      setNotifications((notifs) =>
        notifs.map((n) => (n.id === id ? { ...n, is_read: true } : n))
      );
      setSummary((prev) => ({ ...prev, unread: Math.max(0, prev.unread - 1) }));
    } catch (error) {
      console.error('Erro ao marcar notificacao como lida:', error);
      toast.error('Erro ao marcar notificacao como lida');
    }
  };

  const deleteNotification = async (id: number) => {
    try {
      await api.delete(`/notifications/${id}`);
      setNotifications((notifs) => notifs.filter((n) => n.id !== id));
      fetchSummary();
    } catch (error) {
      console.error('Erro ao excluir notificacao:', error);
      toast.error('Erro ao excluir notificacao');
    }
  };

  const markAllAsRead = async () => {
    try {
      await api.post('/notifications/mark-all-as-read');
      setNotifications((notifs) => notifs.map((n) => ({ ...n, is_read: true })));
      setSummary((prev) => ({ ...prev, unread: 0 }));
    } catch (error) {
      console.error('Erro ao marcar todas como lidas:', error);
      toast.error('Erro ao marcar todas como lidas');
    }
  };

  const unreadCount = summary.unread;
  const readCount = Math.max(0, summary.total - summary.unread);

  const formatDate = (dateString: string) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
  };

  const getNotificationMeta = (type: string) => {
    if (type?.startsWith('vistoria')) {
      return { icon: <ClipboardCheck size={24} className="text-amber-400" />, accent: 'border-l-amber-500' };
    }
    if (type?.startsWith('assinatura')) {
      return { icon: <FileSignature size={24} className="text-sky-400" />, accent: 'border-l-sky-500' };
    }
    switch (type) {
      case 'new_lead':
        return { icon: <Users size={24} className="text-cyan-400" />, accent: 'border-l-cyan-500' };
      case 'property_interest':
      case 'property_new':
        return { icon: <Home size={24} className="text-purple-400" />, accent: 'border-l-purple-500' };
      case 'message':
        return { icon: <MessageSquare size={24} className="text-blue-400" />, accent: 'border-l-blue-500' };
      case 'price_change':
      case 'status_change':
        return { icon: <AlertCircle size={24} className="text-yellow-400" />, accent: 'border-l-yellow-500' };
      case 'system':
        return { icon: <Info size={24} className="text-slate-300" />, accent: 'border-l-slate-400' };
      default:
        return { icon: <CheckCircle size={24} className="text-green-400" />, accent: 'border-l-green-500' };
    }
  };

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
                  Notificacoes
                </h1>
                <p className="text-muted-foreground">
                  Voce tem {unreadCount} notificacao{unreadCount !== 1 ? 's' : ''} nao lida{unreadCount !== 1 ? 's' : ''}
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
                  onClick={() => {
                    setPage(1);
                    setFilter(option);
                    fetchSummary();
                  }}
                  className={`px-4 py-2 rounded-lg font-semibold transition-all ${
                    filter === option
                      ? 'bg-gradient-to-r from-blue-500 to-blue-600 text-white'
                      : 'bg-white/10 text-foreground hover:bg-white/20'
                  }`}
                >
                  {option === 'todos' && 'Todas'}
                  {option === 'nao-lidas' && `Nao lidas (${unreadCount})`}
                  {option === 'lidas' && `Lidas (${readCount})`}
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
            {isLoading ? (
              <motion.div variants={itemVariants} className="flex items-center justify-center py-16">
                <Loader2 className="w-8 h-8 animate-spin text-primary" />
              </motion.div>
            ) : notifications.length === 0 ? (
              <motion.div
                variants={itemVariants}
                className="text-center py-12"
              >
                <Bell size={48} className="mx-auto mb-4 text-muted-foreground opacity-50" />
                <p className="text-lg font-semibold text-foreground mb-2">Nenhuma notificacao</p>
                <p className="text-muted-foreground">
                  {filter === 'nao-lidas' ? 'Voce esta em dia! Todas as notificacoes foram lidas.' : 'Nenhuma notificacao para exibir.'}
                </p>
              </motion.div>
            ) : (
              notifications.map((notification, index) => {
                const meta = getNotificationMeta(notification.type);
                return (
                  <motion.div
                    key={notification.id}
                    variants={itemVariants}
                    transition={{ delay: 0.3 + index * 0.05 }}
                    className={`glass-panel p-6 rounded-2xl border-l-4 cursor-pointer transition-all hover:bg-white/10 ${
                      !notification.is_read ? `${meta.accent} bg-white/5` : 'border-l-white/20'
                    }`}
                    onClick={() => markAsRead(notification.id)}
                  >
                    <div className="flex items-start gap-4">
                      <div className="flex-shrink-0">
                        {meta.icon}
                      </div>

                      <div className="flex-1">
                        <div className="flex items-start justify-between mb-2">
                          <h3 className="font-bold text-lg text-foreground">{notification.title}</h3>
                          <span className="text-xs text-muted-foreground">{formatDate(notification.created_at)}</span>
                        </div>
                        <p className="text-muted-foreground mb-3">{notification.message}</p>

                        {!notification.is_read && (
                          <motion.div
                            initial={{ scale: 0 }}
                            animate={{ scale: 1 }}
                            className="inline-block px-3 py-1 bg-gradient-to-r from-blue-500/20 to-blue-600/20 border border-blue-500/30 rounded-full text-xs font-semibold text-blue-400"
                          >
                            Nao lida
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
                );
              })
            )}
          </motion.div>

          {!isLoading && notifications.length > 0 && (
            <div className="flex items-center justify-center gap-2 mt-10">
              <motion.button
                whileHover={{ scale: 1.05 }}
                whileTap={{ scale: 0.95 }}
                onClick={() => setPage((prev) => Math.max(1, prev - 1))}
                disabled={page === 1}
                className="px-4 py-2 rounded-lg bg-white/10 border border-white/20 text-foreground disabled:opacity-40 disabled:cursor-not-allowed"
              >
                Anterior
              </motion.button>
              <span className="text-sm text-muted-foreground">Pagina {page} de {lastPage}</span>
              <motion.button
                whileHover={{ scale: 1.05 }}
                whileTap={{ scale: 0.95 }}
                onClick={() => setPage((prev) => Math.min(lastPage, prev + 1))}
                disabled={page === lastPage}
                className="px-4 py-2 rounded-lg bg-white/10 border border-white/20 text-foreground disabled:opacity-40 disabled:cursor-not-allowed"
              >
                Proxima
              </motion.button>
            </div>
          )}
        </motion.div>
      </div>
    </div>
  );
}
