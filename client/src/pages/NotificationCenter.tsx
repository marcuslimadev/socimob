import { useEffect, useMemo, useState } from 'react';
import { motion } from 'framer-motion';
import {
  AlertCircle,
  Bell,
  CheckCircle,
  ClipboardCheck,
  ExternalLink,
  FileSignature,
  Home,
  Info,
  Loader2,
  MessageSquare,
  RefreshCcw,
  Trash2,
  Users,
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
  action_url?: string | null;
  property_id?: number | null;
  intention_id?: number | null;
  data?: Record<string, unknown> | null;
}

interface NotificationSummary {
  total: number;
  unread: number;
  by_type: Array<{ type: string; count: number }>;
}

export default function NotificationCenter() {
  const [filter, setFilter] = useState('todos');
  const [typeFilter, setTypeFilter] = useState('todos');
  const [notifications, setNotifications] = useState<Notification[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [summary, setSummary] = useState<NotificationSummary>({ total: 0, unread: 0, by_type: [] });

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
    void fetchNotifications();
  }, [filter, page, typeFilter]);

  useEffect(() => {
    void fetchSummary();
  }, []);

  const fetchSummary = async () => {
    try {
      const response = await api.get('/notifications/summary');
      setSummary({
        total: response.data.total || 0,
        unread: response.data.unread || 0,
        by_type: response.data.by_type || [],
      });
    } catch (error) {
      console.error('Erro ao carregar resumo de notificações:', error);
    }
  };

  const fetchNotifications = async () => {
    try {
      setIsLoading(true);
      const status = filter === 'nao-lidas' ? 'unread' : filter === 'lidas' ? 'read' : undefined;
      const type = typeFilter !== 'todos' ? typeFilter : undefined;
      const response = await api.get('/notifications', {
        params: {
          status,
          type,
          per_page: 10,
          page,
        },
      });
      setNotifications(response.data.data || []);
      setPage(response.data.current_page || 1);
      setLastPage(response.data.last_page || 1);
    } catch (error) {
      console.error('Erro ao carregar notificações:', error);
      toast.error('Erro ao carregar notificações');
    } finally {
      setIsLoading(false);
    }
  };

  const getNotificationMeta = (type: string) => {
    if (type?.startsWith('vistoria')) {
      return { icon: <ClipboardCheck size={24} className="text-amber-400" />, accent: 'border-l-amber-500', label: 'Vistoria' };
    }
    if (type?.startsWith('assinatura')) {
      return { icon: <FileSignature size={24} className="text-sky-400" />, accent: 'border-l-sky-500', label: 'Assinatura' };
    }
    switch (type) {
      case 'new_lead':
        return { icon: <Users size={24} className="text-cyan-400" />, accent: 'border-l-cyan-500', label: 'Lead' };
      case 'property_interest':
      case 'property_new':
        return { icon: <Home size={24} className="text-purple-400" />, accent: 'border-l-purple-500', label: 'Imóvel' };
      case 'message':
        return { icon: <MessageSquare size={24} className="text-blue-400" />, accent: 'border-l-blue-500', label: 'Mensagem' };
      case 'price_change':
      case 'status_change':
        return { icon: <AlertCircle size={24} className="text-yellow-400" />, accent: 'border-l-yellow-500', label: 'Alerta' };
      case 'system':
        return { icon: <Info size={24} className="text-slate-300" />, accent: 'border-l-slate-400', label: 'Sistema' };
      default:
        return { icon: <CheckCircle size={24} className="text-green-400" />, accent: 'border-l-green-500', label: 'Atualização' };
    }
  };

  const typeOptions = useMemo(
    () => [
      { type: 'todos', count: summary.total, label: 'Todos os tipos' },
      ...summary.by_type.map((item) => ({
        type: item.type,
        count: item.count,
        label: getNotificationMeta(item.type).label,
      })),
    ],
    [summary],
  );

  const unreadCount = summary.unread;
  const readCount = Math.max(0, summary.total - summary.unread);

  const formatDate = (dateString: string) => {
    if (!dateString) return '';
    const date = new Date(dateString);
    return date.toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
  };

  const resolveActionUrl = (notification: Notification) => {
    const nestedActionUrl = typeof notification.data?.action_url === 'string' ? notification.data.action_url : null;
    const leadId = typeof notification.data?.lead_id === 'number'
      ? notification.data.lead_id
      : typeof notification.data?.lead_id === 'string' && !Number.isNaN(Number(notification.data.lead_id))
        ? Number(notification.data.lead_id)
        : null;

    if (notification.action_url) return notification.action_url;
    if (nestedActionUrl) return nestedActionUrl;
    if (notification.type?.startsWith('vistoria')) return '/vistorias';
    if (notification.type?.startsWith('assinatura')) return '/assinaturas';
    if (notification.type === 'new_lead' || notification.type === 'lead' || notification.type === 'lead_created') {
      return leadId ? `/leads/${leadId}` : '/leads';
    }
    if (notification.type === 'message' || notification.type === 'nova_conversa') {
      return leadId ? `/chat?leadId=${leadId}` : '/chat';
    }
    if (notification.type === 'property_match' && notification.property_id) {
      return `/portal/imovel/${notification.property_id}`;
    }
    if ((notification.type === 'property_new' || notification.type === 'property_interest' || notification.type === 'price_change' || notification.type === 'status_change') && notification.property_id) {
      return `/properties/${notification.property_id}/editar`;
    }
    if (notification.type === 'property_new' || notification.type === 'property_interest') return '/properties';
    if (notification.type === 'system_error' || notification.type === 'security_alert' || notification.type === 'system') return '/notifications';
    return null;
  };

  const markAsRead = async (id: number, options?: { silent?: boolean }) => {
    try {
      await api.post(`/notifications/${id}/read`);
      setNotifications((notifs) => notifs.map((n) => (n.id === id ? { ...n, is_read: true } : n)));
      setSummary((prev) => ({ ...prev, unread: Math.max(0, prev.unread - 1) }));
      if (!options?.silent) toast.success('Notificação marcada como lida');
    } catch (error) {
      console.error('Erro ao marcar notificação como lida:', error);
      toast.error('Erro ao marcar notificação como lida');
    }
  };

  const markAsUnread = async (id: number) => {
    try {
      await api.post(`/notifications/${id}/unread`);
      setNotifications((notifs) => notifs.map((n) => (n.id === id ? { ...n, is_read: false } : n)));
      setSummary((prev) => ({ ...prev, unread: prev.unread + 1 }));
      toast.success('Notificação marcada como não lida');
    } catch (error) {
      console.error('Erro ao marcar notificação como não lida:', error);
      toast.error('Erro ao marcar notificação como não lida');
    }
  };

  const deleteNotification = async (id: number) => {
    try {
      await api.delete(`/notifications/${id}`);
      setNotifications((notifs) => notifs.filter((n) => n.id !== id));
      await fetchSummary();
      toast.success('Notificação removida');
    } catch (error) {
      console.error('Erro ao excluir notificação:', error);
      toast.error('Erro ao excluir notificação');
    }
  };

  const markAllAsRead = async () => {
    try {
      await api.post('/notifications/mark-all-as-read');
      setNotifications((notifs) => notifs.map((n) => ({ ...n, is_read: true })));
      setSummary((prev) => ({ ...prev, unread: 0 }));
      toast.success('Todas as notificações foram marcadas como lidas');
    } catch (error) {
      console.error('Erro ao marcar todas como lidas:', error);
      toast.error('Erro ao marcar todas como lidas');
    }
  };

  const openNotificationTarget = async (notification: Notification) => {
    const targetUrl = resolveActionUrl(notification);
    if (!targetUrl) {
      toast.message('Essa notificação ainda não possui destino configurado.');
      return;
    }

    if (!notification.is_read) {
      await markAsRead(notification.id, { silent: true });
    }

    window.location.href = targetUrl;
  };

  return (
    <div className="flex">
      <Sidebar />

      <div className="page-shell">
        <motion.div variants={containerVariants} initial="hidden" animate="visible" className="mx-auto max-w-5xl">
          <motion.div variants={itemVariants} className="mb-8">
            <div className="page-header mb-4">
              <div>
                <h1 className="page-title mb-2 flex items-center gap-3">
                  <Bell size={40} />
                  Notificações
                </h1>
                <p className="page-subtitle">
                  {unreadCount > 0
                    ? `Você tem ${unreadCount} notificação${unreadCount !== 1 ? 's' : ''} pendente${unreadCount !== 1 ? 's' : ''}.`
                    : 'Tudo em dia. Nenhuma pendência de leitura no momento.'}
                </p>
              </div>

              <div className="flex w-full flex-col gap-3 sm:w-auto sm:flex-row">
                <motion.button
                  whileHover={{ scale: 1.03 }}
                  whileTap={{ scale: 0.98 }}
                  onClick={() => {
                    void fetchSummary();
                    void fetchNotifications();
                  }}
                  className="w-full rounded-lg border border-white/20 bg-white/10 px-4 py-3 font-semibold text-foreground transition-all hover:bg-white/20 sm:w-auto"
                >
                  <span className="inline-flex items-center gap-2"><RefreshCcw size={16} /> Atualizar</span>
                </motion.button>
                {unreadCount > 0 && (
                  <motion.button
                    whileHover={{ scale: 1.03 }}
                    whileTap={{ scale: 0.98 }}
                    onClick={markAllAsRead}
                    className="w-full rounded-lg bg-blue-600 px-6 py-3 font-semibold text-white transition-all hover:bg-blue-700 sm:w-auto"
                  >
                    Marcar todas como lidas
                  </motion.button>
                )}
              </div>
            </div>
          </motion.div>

          <motion.div variants={itemVariants} className="glass-panel mb-8 rounded-2xl p-6">
            <div className="flex flex-wrap gap-3">
              {['todos', 'nao-lidas', 'lidas'].map((option) => (
                <motion.button
                  key={option}
                  whileHover={{ scale: 1.03 }}
                  whileTap={{ scale: 0.98 }}
                  onClick={() => {
                    setPage(1);
                    setFilter(option);
                  }}
                  className={`w-full rounded-lg px-4 py-2 font-semibold transition-all sm:w-auto ${
                    filter === option
                      ? 'bg-gradient-to-r from-blue-500 to-blue-600 text-white'
                      : 'bg-white/10 text-foreground hover:bg-white/20'
                  }`}
                >
                  {option === 'todos' && 'Todas'}
                  {option === 'nao-lidas' && `Não lidas (${unreadCount})`}
                  {option === 'lidas' && `Lidas (${readCount})`}
                </motion.button>
              ))}
            </div>

            <div className="mt-4 flex flex-wrap gap-2">
              {typeOptions.map((option) => (
                <button
                  key={option.type}
                  type="button"
                  onClick={() => {
                    setPage(1);
                    setTypeFilter(option.type);
                  }}
                  className={`rounded-full border px-3 py-1.5 text-xs font-semibold transition-all ${
                    typeFilter === option.type
                      ? 'border-blue-500/30 bg-blue-500/15 text-blue-300'
                      : 'border-white/10 bg-white/5 text-muted-foreground hover:bg-white/10'
                  }`}
                >
                  {option.label} ({option.count})
                </button>
              ))}
            </div>
          </motion.div>

          <motion.div variants={containerVariants} initial="hidden" animate="visible" className="space-y-4">
            {isLoading ? (
              <motion.div variants={itemVariants} className="flex items-center justify-center py-16">
                <Loader2 className="h-8 w-8 animate-spin text-primary" />
              </motion.div>
            ) : notifications.length === 0 ? (
              <motion.div variants={itemVariants} className="py-12 text-center">
                <Bell size={48} className="mx-auto mb-4 text-muted-foreground opacity-50" />
                <p className="mb-2 text-lg font-semibold text-foreground">Nenhuma notificação</p>
                <p className="text-muted-foreground">
                  {filter === 'nao-lidas'
                    ? 'Você está em dia. Todas as notificações já foram lidas.'
                    : 'Nenhuma notificação para exibir com os filtros atuais.'}
                </p>
              </motion.div>
            ) : (
              notifications.map((notification, index) => {
                const meta = getNotificationMeta(notification.type);
                const actionUrl = resolveActionUrl(notification);

                return (
                  <motion.div
                    key={notification.id}
                    variants={itemVariants}
                    transition={{ delay: 0.2 + index * 0.04 }}
                    className={`glass-panel rounded-2xl border-l-4 p-6 transition-all hover:bg-white/10 ${
                      !notification.is_read ? `${meta.accent} bg-white/5` : 'border-l-white/20'
                    }`}
                  >
                    <div className="flex items-start gap-4">
                      <div className="flex-shrink-0">{meta.icon}</div>

                      <div className="min-w-0 flex-1">
                        <div className="mb-2 flex items-start justify-between gap-3">
                          <div className="min-w-0">
                            <div className="mb-2 flex flex-wrap items-center gap-2">
                              <span className="rounded-full border border-white/10 bg-white/5 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-muted-foreground">
                                {meta.label}
                              </span>
                              {!notification.is_read && (
                                <span className="rounded-full border border-blue-500/30 bg-blue-500/15 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-blue-300">
                                  Não lida
                                </span>
                              )}
                            </div>
                            <h3 className="truncate text-lg font-bold text-foreground">{notification.title}</h3>
                          </div>
                          <span className="shrink-0 text-xs text-muted-foreground">{formatDate(notification.created_at)}</span>
                        </div>

                        <p className="mb-4 text-muted-foreground">{notification.message}</p>

                        <div className="flex flex-wrap gap-2">
                          {actionUrl && (
                            <button
                              type="button"
                              onClick={() => void openNotificationTarget(notification)}
                              className="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-3 py-2 text-sm font-semibold text-white hover:bg-blue-700"
                            >
                              <ExternalLink size={14} /> Abrir
                            </button>
                          )}

                          {notification.is_read ? (
                            <button
                              type="button"
                              onClick={() => void markAsUnread(notification.id)}
                              className="rounded-xl border border-white/20 bg-white/5 px-3 py-2 text-sm font-medium text-foreground hover:bg-white/10"
                            >
                              Marcar como não lida
                            </button>
                          ) : (
                            <button
                              type="button"
                              onClick={() => void markAsRead(notification.id)}
                              className="rounded-xl border border-white/20 bg-white/5 px-3 py-2 text-sm font-medium text-foreground hover:bg-white/10"
                            >
                              Marcar como lida
                            </button>
                          )}
                        </div>
                      </div>

                      <motion.button
                        whileHover={{ scale: 1.08 }}
                        whileTap={{ scale: 0.95 }}
                        onClick={() => void deleteNotification(notification.id)}
                        className="flex-shrink-0 rounded-lg p-2 transition-all hover:bg-white/10"
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
            <div className="mt-10 flex items-center justify-center gap-2">
              <motion.button
                whileHover={{ scale: 1.03 }}
                whileTap={{ scale: 0.98 }}
                onClick={() => setPage((prev) => Math.max(1, prev - 1))}
                disabled={page === 1}
                className="rounded-lg border border-white/20 bg-white/10 px-4 py-2 text-foreground disabled:cursor-not-allowed disabled:opacity-40"
              >
                Anterior
              </motion.button>
              <span className="text-sm text-muted-foreground">Página {page} de {lastPage}</span>
              <motion.button
                whileHover={{ scale: 1.03 }}
                whileTap={{ scale: 0.98 }}
                onClick={() => setPage((prev) => Math.min(lastPage, prev + 1))}
                disabled={page === lastPage}
                className="rounded-lg border border-white/20 bg-white/10 px-4 py-2 text-foreground disabled:cursor-not-allowed disabled:opacity-40"
              >
                Próxima
              </motion.button>
            </div>
          )}
        </motion.div>
      </div>
    </div>
  );
}
