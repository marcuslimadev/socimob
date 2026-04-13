import { useEffect, useMemo, useState } from 'react';
import { motion } from 'framer-motion';
import { CalendarClock, CalendarDays, ExternalLink, RefreshCcw, Save, Search } from 'lucide-react';
import { toast } from 'sonner';
import Sidebar from '@/components/Sidebar';
import { Calendar } from '@/components/ui/calendar';
import { api } from '@/lib/api';

interface AgendaUser {
  id: number;
  name: string;
  role?: string;
}

interface Visita {
  id: number;
  property_id?: number | null;
  property_titulo?: string | null;
  nome: string;
  email?: string | null;
  telefone?: string | null;
  data_hora: string;
  status: 'pendente' | 'confirmada' | 'cancelada' | 'concluida';
  observacoes?: string | null;
  lead_id?: number | null;
  assigned_user_id?: number | null;
  assigned_user_name?: string | null;
  created_by_user_id?: number | null;
  created_by_user_name?: string | null;
  origem?: string | null;
}

interface CorretorOption {
  id: number;
  name: string;
  role?: string;
}

interface TenantSettingsResponse {
  config?: {
    google_calendar_embed_url?: string | null;
  } | null;
}

const statusConfig: Record<Visita['status'], { label: string; className: string }> = {
  pendente: {
    label: 'Pendente',
    className: 'bg-yellow-500/20 text-yellow-200 border border-yellow-500/30',
  },
  confirmada: {
    label: 'Confirmada',
    className: 'bg-sky-500/20 text-sky-200 border border-sky-500/30',
  },
  cancelada: {
    label: 'Cancelada',
    className: 'bg-red-500/20 text-red-200 border border-red-500/30',
  },
  concluida: {
    label: 'Concluída',
    className: 'bg-emerald-500/20 text-emerald-200 border border-emerald-500/30',
  },
};

const formatDateTime = (value: string) => {
  const date = new Date(value);
  const dateLabel = date.toLocaleDateString('pt-BR');
  const timeLabel = date.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
  return `${dateLabel} • ${timeLabel}`;
};

const formatLongDate = (value: Date) =>
  value.toLocaleDateString('pt-BR', {
    weekday: 'long',
    day: '2-digit',
    month: 'long',
    year: 'numeric',
  });

const sameCalendarDay = (left: Date, right: Date) =>
  left.getFullYear() === right.getFullYear() &&
  left.getMonth() === right.getMonth() &&
  left.getDate() === right.getDate();

const parseVisitaDate = (value: string) => {
  const parsed = new Date(value);
  return Number.isNaN(parsed.getTime()) ? null : parsed;
};

const toDateTimeLocalValue = (value: Date) => {
  const timezoneOffset = value.getTimezoneOffset() * 60_000;
  return new Date(value.getTime() - timezoneOffset).toISOString().slice(0, 16);
};

const formatVisitOrigin = (value?: string | null) => {
  switch (value) {
    case 'agenda_admin':
      return 'Criada na agenda';
    case 'portal_chat':
      return 'Chat do portal';
    case 'portal_home':
      return 'Tela inicial do portal';
    case 'portal_catalogo':
      return 'Catálogo do portal';
    case 'portal_publico':
      return 'Portal público';
    default:
      return 'Origem interna';
  }
};

const parseDateTimeLocalValue = (value: string) => {
  const parsed = new Date(value);
  return Number.isNaN(parsed.getTime()) ? null : parsed;
};

const toGoogleCalendarDate = (value: Date) => value.toISOString().replace(/[-:]/g, '').replace(/\.\d{3}Z$/, 'Z');

const buildGoogleCalendarUrl = (visita: Visita) => {
  const start = parseVisitaDate(visita.data_hora) ?? new Date();
  const end = new Date(start.getTime() + 60 * 60 * 1000);
  const title = visita.property_titulo ? `Visita ao imóvel: ${visita.property_titulo}` : `Visita com ${visita.nome}`;
  const details = [
    `Cliente: ${visita.nome}`,
    visita.email ? `Email: ${visita.email}` : null,
    visita.telefone ? `Telefone: ${visita.telefone}` : null,
    visita.observacoes ? `Observações: ${visita.observacoes}` : null,
  ].filter(Boolean).join('\n');

  const params = new URLSearchParams({
    action: 'TEMPLATE',
    text: title,
    dates: `${toGoogleCalendarDate(start)}/${toGoogleCalendarDate(end)}`,
    details,
    location: visita.property_titulo || 'Imóvel sob consulta',
  });

  return `https://calendar.google.com/calendar/render?${params.toString()}`;
};

export default function Agenda() {
  const [visitas, setVisitas] = useState<Visita[]>([]);
  const [currentUser, setCurrentUser] = useState<AgendaUser | null>(null);
  const [corretores, setCorretores] = useState<CorretorOption[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [assignedFilter, setAssignedFilter] = useState('');
  const [updatingId, setUpdatingId] = useState<number | null>(null);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [selectedDate, setSelectedDate] = useState<Date | undefined>();
  const [quickPeriodFilter, setQuickPeriodFilter] = useState<'all' | 'today' | 'upcoming' | 'overdue'>('all');
  const [googleCalendarEmbedUrl, setGoogleCalendarEmbedUrl] = useState('');
  const [googleCalendarDraft, setGoogleCalendarDraft] = useState('');
  const [showGoogleCalendarSettings, setShowGoogleCalendarSettings] = useState(false);
  const [isSavingGoogleCalendar, setIsSavingGoogleCalendar] = useState(false);
  const [isCreatingVisit, setIsCreatingVisit] = useState(false);
  const [savingQuickEditId, setSavingQuickEditId] = useState<number | null>(null);
  const [quickEdit, setQuickEdit] = useState<{
    assigned_user_id: string;
    data_hora: string;
    observacoes: string;
  }>({
    assigned_user_id: '',
    data_hora: '',
    observacoes: '',
  });
  const [newVisit, setNewVisit] = useState({
    property_titulo: '',
    nome: '',
    email: '',
    telefone: '',
    data_hora: toDateTimeLocalValue(new Date(Date.now() + 60 * 60 * 1000)),
    observacoes: '',
    assigned_user_id: '',
  });

  const isBrokerUser = currentUser?.role === 'corretor';

  const safeTrim = (value?: string | null) => (value || '').trim();

  const isVisitOverdue = (visita: Visita) => {
    if (visita.status === 'cancelada' || visita.status === 'concluida') return false;
    const visitaDate = parseVisitaDate(visita.data_hora);
    if (!visitaDate) return false;
    return visitaDate.getTime() < Date.now();
  };

  const isVisitToday = (visita: Visita) => {
    const visitaDate = parseVisitaDate(visita.data_hora);
    if (!visitaDate) return false;
    return sameCalendarDay(visitaDate, new Date());
  };

  const isVisitUpcoming = (visita: Visita) => {
    if (visita.status === 'cancelada' || visita.status === 'concluida') return false;
    const visitaDate = parseVisitaDate(visita.data_hora);
    if (!visitaDate) return false;
    return visitaDate.getTime() >= Date.now() && !sameCalendarDay(visitaDate, new Date());
  };

  const sortVisitas = (list: Visita[]) => {
    return [...list].sort((a, b) => {
      const aDate = parseVisitaDate(a.data_hora);
      const bDate = parseVisitaDate(b.data_hora);
      const aTime = aDate?.getTime() ?? 0;
      const bTime = bDate?.getTime() ?? 0;

      const score = (visita: Visita) => {
        if (isVisitOverdue(visita)) return 0;
        if (isVisitToday(visita)) return 1;
        if (isVisitUpcoming(visita)) return 2;
        if (visita.status === 'concluida') return 3;
        return 4;
      };

      const aScore = score(a);
      const bScore = score(b);
      if (aScore !== bScore) return aScore - bScore;

      if (aScore <= 2) {
        return aTime - bTime;
      }

      return bTime - aTime;
    });
  };

  const filteredVisitas = useMemo(() => {
    const normalizedSearch = search.trim().toLowerCase();
    const base = visitas.filter((visita) => {
      const visitaDate = parseVisitaDate(visita.data_hora);
      const matchSearch =
        !normalizedSearch ||
        visita.nome.toLowerCase().includes(normalizedSearch) ||
        visita.property_titulo?.toLowerCase().includes(normalizedSearch) ||
        visita.assigned_user_name?.toLowerCase().includes(normalizedSearch) ||
        safeTrim(visita.telefone).toLowerCase().includes(normalizedSearch);
      const matchStatus = !statusFilter || visita.status === statusFilter;
      const matchAssigned = !assignedFilter || String(visita.assigned_user_id || '') === assignedFilter;
      const matchDate = !selectedDate || (visitaDate ? sameCalendarDay(visitaDate, selectedDate) : false);
      const matchQuickPeriod =
        quickPeriodFilter === 'all' ||
        (quickPeriodFilter === 'today' && isVisitToday(visita)) ||
        (quickPeriodFilter === 'upcoming' && isVisitUpcoming(visita)) ||
        (quickPeriodFilter === 'overdue' && isVisitOverdue(visita));

      return matchSearch && matchStatus && matchAssigned && matchDate && matchQuickPeriod;
    });

    return sortVisitas(base);
  }, [assignedFilter, quickPeriodFilter, search, selectedDate, statusFilter, visitas]);

  const visitDays = useMemo(
    () => visitas.map((visita) => parseVisitaDate(visita.data_hora)).filter((value): value is Date => Boolean(value)),
    [visitas],
  );

  const todayCount = useMemo(() => {
    const today = new Date();
    return visitas.filter((visita) => {
      const visitaDate = parseVisitaDate(visita.data_hora);
      return visitaDate ? sameCalendarDay(visitaDate, today) : false;
    }).length;
  }, [visitas]);

  const confirmedCount = useMemo(
    () => visitas.filter((visita) => visita.status === 'confirmada').length,
    [visitas],
  );

  const assignedToMeCount = useMemo(
    () => visitas.filter((visita) => currentUser?.id && visita.assigned_user_id === currentUser.id).length,
    [currentUser?.id, visitas],
  );

  const overdueCount = useMemo(() => visitas.filter((visita) => isVisitOverdue(visita)).length, [visitas]);

  const carregarVisitas = async () => {
    setIsLoading(true);
    try {
      const response = await api.get('/admin/visitas', {
        params: assignedFilter ? { assigned_user_id: assignedFilter } : undefined,
      });
      if (response.data?.success) {
        setVisitas(response.data.data || []);
        return;
      }
      setVisitas([]);
    } catch (error) {
      console.error('Erro ao carregar visitas:', error);
      toast.error('Não foi possível carregar a agenda');
      setVisitas([]);
    } finally {
      setIsLoading(false);
    }
  };

  const openQuickEdit = (visita: Visita) => {
    setEditingId(visita.id);
    setQuickEdit({
      assigned_user_id: String(visita.assigned_user_id || currentUser?.id || ''),
      data_hora: toDateTimeLocalValue(parseVisitaDate(visita.data_hora) || new Date()),
      observacoes: visita.observacoes || '',
    });
  };

  const closeQuickEdit = () => {
    setEditingId(null);
    setQuickEdit({
      assigned_user_id: '',
      data_hora: '',
      observacoes: '',
    });
  };

  const salvarQuickEdit = async (visitaId: number) => {
    const parsedDate = parseDateTimeLocalValue(quickEdit.data_hora);
    if (!parsedDate) {
      toast.error('Informe data e hora válidas para reagendar.');
      return;
    }

    try {
      setSavingQuickEditId(visitaId);
      await api.patch(`/admin/visitas/${visitaId}`, {
        assigned_user_id: quickEdit.assigned_user_id ? Number(quickEdit.assigned_user_id) : null,
        data_hora: quickEdit.data_hora,
        observacoes: quickEdit.observacoes.trim() || null,
      });
      await carregarVisitas();
      closeQuickEdit();
      toast.success('Visita atualizada com sucesso');
    } catch (error: any) {
      console.error('Erro ao salvar edição rápida da visita:', error);
      toast.error(error?.response?.data?.error || 'Não foi possível salvar as alterações da visita');
    } finally {
      setSavingQuickEditId(null);
    }
  };

  const aplicarStatusRapido = async (id: number, status: Visita['status']) => {
    await atualizarStatus(id, status);
  };

  const carregarCorretores = async () => {
    try {
      const response = await api.get('/admin/corretores');
      const list = Array.isArray(response.data?.corretores) ? response.data.corretores : [];
      setCorretores(list);
    } catch (error) {
      console.error('Erro ao carregar responsáveis da agenda:', error);
      setCorretores([]);
    }
  };

  const carregarConfiguracaoAgenda = async () => {
    try {
      const response = await api.get<TenantSettingsResponse>('/admin/settings');
      const embedUrl = response.data?.config?.google_calendar_embed_url?.trim() || '';
      setGoogleCalendarEmbedUrl(embedUrl);
      setGoogleCalendarDraft(embedUrl);
    } catch (error) {
      console.error('Erro ao carregar configuração do Google Agenda:', error);
    }
  };

  const atualizarStatus = async (id: number, status: Visita['status']) => {
    setUpdatingId(id);
    try {
      await api.patch(`/admin/visitas/${id}`, { status });
      setVisitas((prev) => prev.map((visita) => (visita.id === id ? { ...visita, status } : visita)));
      toast.success('Status atualizado');
    } catch (error) {
      console.error('Erro ao atualizar status da visita:', error);
      toast.error('Não foi possível atualizar a visita');
    } finally {
      setUpdatingId(null);
    }
  };

  const criarVisita = async () => {
    const payload = {
      property_titulo: newVisit.property_titulo.trim() || null,
      nome: newVisit.nome.trim(),
      email: newVisit.email.trim() || null,
      telefone: newVisit.telefone.trim(),
      data_hora: newVisit.data_hora,
      observacoes: newVisit.observacoes.trim() || null,
      assigned_user_id: newVisit.assigned_user_id ? Number(newVisit.assigned_user_id) : undefined,
    };

    if (payload.nome.length < 2) {
      toast.error('Informe o nome do cliente para criar a visita');
      return;
    }

    if (payload.telefone.replace(/\D/g, '').length < 10) {
      toast.error('Informe um telefone válido com DDD');
      return;
    }

    if (!payload.data_hora) {
      toast.error('Escolha a data e o horário da visita');
      return;
    }

    try {
      setIsCreatingVisit(true);
      await api.post('/admin/visitas', payload);
      toast.success('Visita criada com sucesso');
      setNewVisit({
        property_titulo: '',
        nome: '',
        email: '',
        telefone: '',
        data_hora: toDateTimeLocalValue(new Date(Date.now() + 60 * 60 * 1000)),
        observacoes: '',
        assigned_user_id: isBrokerUser ? String(currentUser?.id || '') : String(currentUser?.id || ''),
      });
      await carregarVisitas();
    } catch (error: any) {
      console.error('Erro ao criar visita:', error);
      toast.error(error?.response?.data?.error || 'Não foi possível criar a visita');
    } finally {
      setIsCreatingVisit(false);
    }
  };

  const salvarGoogleCalendar = async () => {
    try {
      setIsSavingGoogleCalendar(true);
      const embedUrl = googleCalendarDraft.trim();
      await api.put('/admin/settings', {
        config: {
          google_calendar_embed_url: embedUrl || null,
        },
      });
      setGoogleCalendarEmbedUrl(embedUrl);
      setGoogleCalendarDraft(embedUrl);
      toast.success('Integrações de agenda atualizadas');
    } catch (error: any) {
      console.error('Erro ao salvar Google Agenda:', error);
      toast.error(error?.response?.data?.error || 'Não foi possível salvar as integrações de agenda');
    } finally {
      setIsSavingGoogleCalendar(false);
    }
  };

  useEffect(() => {
    try {
      const rawUser = localStorage.getItem('user');
      if (rawUser) {
        const parsed = JSON.parse(rawUser);
        setCurrentUser(parsed);
        setNewVisit((current) => ({
          ...current,
          assigned_user_id: parsed?.id ? String(parsed.id) : current.assigned_user_id,
        }));
      }
    } catch (error) {
      console.error('Erro ao recuperar usuário logado da agenda:', error);
    }

    carregarCorretores();
    carregarConfiguracaoAgenda();
  }, []);

  useEffect(() => {
    carregarVisitas();
  }, [assignedFilter]);

  return (
    <div className="flex">
      <Sidebar />

      <div className="page-shell">
        <motion.div
          initial={{ opacity: 0, y: 16 }}
          animate={{ opacity: 1, y: 0 }}
          className="page-content"
        >
          <div className="page-header gap-4 mb-8">
            <div>
              <h1 className="page-title mb-2 flex items-center gap-3">
                <CalendarClock className="text-blue-300" size={32} />
                Agenda de Visitas
              </h1>
              <p className="page-subtitle">Acompanhe as visitas no calendário, filtre por dia e envie cada compromisso para o Google Agenda.</p>
            </div>
            <div className="flex w-full flex-col gap-3 sm:w-auto sm:flex-row">
              <button
                type="button"
                onClick={() => window.open('https://calendar.google.com/calendar/u/0/r', '_blank', 'noopener,noreferrer')}
                className="inline-flex w-full items-center justify-center gap-2 rounded-xl border border-sky-400/30 bg-sky-500/15 px-4 py-2 text-sm font-semibold text-sky-100 transition hover:bg-sky-500/25 sm:w-auto"
              >
                <ExternalLink size={16} />
                Abrir Google Agenda
              </button>
              <button
                type="button"
                onClick={carregarVisitas}
                className="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-white/10 px-4 py-2 text-sm font-semibold text-foreground transition hover:bg-white/20 sm:w-auto"
              >
                <RefreshCcw size={16} />
                Atualizar
              </button>
            </div>
          </div>

          <div className="mb-6 grid grid-cols-1 gap-4 md:grid-cols-3">
            {[
              { label: 'Visitas cadastradas', value: visitas.length },
              { label: 'Visitas hoje', value: todayCount },
              {
                label: isBrokerUser ? 'Na minha agenda' : overdueCount > 0 ? 'Atrasadas' : 'Confirmadas',
                value: isBrokerUser ? assignedToMeCount : overdueCount > 0 ? overdueCount : confirmedCount,
              },
            ].map((item) => (
              <div key={item.label} className="glass-panel rounded-2xl p-5">
                <p className="text-sm text-muted-foreground">{item.label}</p>
                <p className="mt-2 text-3xl font-bold text-foreground">{item.value}</p>
              </div>
            ))}
          </div>

          <div className="mb-6 flex flex-wrap gap-2">
            {[
              { key: 'all', label: 'Todas' },
              { key: 'today', label: 'Hoje' },
              { key: 'upcoming', label: 'Próximas' },
              { key: 'overdue', label: 'Atrasadas' },
            ].map((item) => (
              <button
                key={item.key}
                type="button"
                onClick={() => setQuickPeriodFilter(item.key as 'all' | 'today' | 'upcoming' | 'overdue')}
                className={`rounded-xl border px-3 py-2 text-xs font-semibold transition ${
                  quickPeriodFilter === item.key
                    ? 'border-sky-300/60 bg-sky-500/20 text-sky-100'
                    : 'border-white/10 bg-white/5 text-muted-foreground hover:bg-white/10'
                }`}
              >
                {item.label}
              </button>
            ))}
          </div>

          <div className="glass-panel p-4 rounded-2xl mb-6">
            <div className="grid grid-cols-1 gap-4 xl:grid-cols-[1fr_220px_240px]">
              <div className="relative">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" size={18} />
                <input
                  type="text"
                  value={search}
                  onChange={(event) => setSearch(event.target.value)}
                  placeholder="Buscar por cliente, imóvel, telefone ou responsável"
                  className="w-full pl-10 pr-4 py-2 rounded-xl bg-white/5 border border-white/10 text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>
              <select
                value={statusFilter}
                onChange={(event) => setStatusFilter(event.target.value)}
                className="w-full px-4 py-2 rounded-xl bg-white/5 border border-white/10 text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="">Todos os status</option>
                <option value="pendente">Pendentes</option>
                <option value="confirmada">Confirmadas</option>
                <option value="concluida">Concluídas</option>
                <option value="cancelada">Canceladas</option>
              </select>
              <select
                value={assignedFilter}
                onChange={(event) => setAssignedFilter(event.target.value)}
                className="w-full px-4 py-2 rounded-xl bg-white/5 border border-white/10 text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                disabled={isBrokerUser}
              >
                <option value="">{isBrokerUser ? 'Minha agenda' : 'Todos os responsáveis'}</option>
                {corretores.map((corretor) => (
                  <option key={corretor.id} value={String(corretor.id)}>
                    {corretor.name}
                  </option>
                ))}
              </select>
            </div>
          </div>

          <div className="mb-6 glass-panel rounded-3xl p-5">
            <div className="mb-4 flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
              <div>
                <p className="text-sm font-semibold text-foreground">Nova visita</p>
                <p className="text-xs text-muted-foreground">
                  Crie compromissos para sua própria agenda ou distribua para os corretores do time.
                </p>
              </div>
              <div className="rounded-2xl border border-white/10 bg-white/5 px-3 py-2 text-xs text-muted-foreground">
                {isBrokerUser ? 'Como corretor, novas visitas entram automaticamente na sua agenda.' : 'Administradores podem atribuir cada visita ao corretor ou manter na própria agenda.'}
              </div>
            </div>

            <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
              <label className="block">
                <span className="mb-1.5 block text-sm font-medium text-foreground">Imóvel</span>
                <input
                  type="text"
                  value={newVisit.property_titulo}
                  onChange={(event) => setNewVisit((current) => ({ ...current, property_titulo: event.target.value }))}
                  placeholder="Ex: Apartamento 3 quartos no Centro"
                  className="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </label>
              <label className="block">
                <span className="mb-1.5 block text-sm font-medium text-foreground">Cliente</span>
                <input
                  type="text"
                  value={newVisit.nome}
                  onChange={(event) => setNewVisit((current) => ({ ...current, nome: event.target.value }))}
                  placeholder="Nome do cliente"
                  className="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </label>
              <label className="block">
                <span className="mb-1.5 block text-sm font-medium text-foreground">Telefone</span>
                <input
                  type="tel"
                  value={newVisit.telefone}
                  onChange={(event) => setNewVisit((current) => ({ ...current, telefone: event.target.value }))}
                  placeholder="(31) 99999-9999"
                  className="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </label>
              <label className="block">
                <span className="mb-1.5 block text-sm font-medium text-foreground">E-mail</span>
                <input
                  type="email"
                  value={newVisit.email}
                  onChange={(event) => setNewVisit((current) => ({ ...current, email: event.target.value }))}
                  placeholder="cliente@email.com"
                  className="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </label>
              <label className="block">
                <span className="mb-1.5 block text-sm font-medium text-foreground">Data e hora</span>
                <input
                  type="datetime-local"
                  value={newVisit.data_hora}
                  onChange={(event) => setNewVisit((current) => ({ ...current, data_hora: event.target.value }))}
                  className="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </label>
              <label className="block">
                <span className="mb-1.5 block text-sm font-medium text-foreground">Responsável</span>
                <select
                  value={newVisit.assigned_user_id}
                  onChange={(event) => setNewVisit((current) => ({ ...current, assigned_user_id: event.target.value }))}
                  disabled={isBrokerUser}
                  className="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-70"
                >
                  {currentUser?.id && <option value={String(currentUser.id)}>Minha agenda</option>}
                  {corretores
                    .filter((corretor) => String(corretor.id) !== String(currentUser?.id || ''))
                    .map((corretor) => (
                      <option key={corretor.id} value={String(corretor.id)}>
                        {corretor.name}
                      </option>
                    ))}
                </select>
              </label>
              <label className="block xl:col-span-2">
                <span className="mb-1.5 block text-sm font-medium text-foreground">Observações</span>
                <textarea
                  value={newVisit.observacoes}
                  onChange={(event) => setNewVisit((current) => ({ ...current, observacoes: event.target.value }))}
                  placeholder="Detalhes do encontro, ponto de referência, imóvel desejado ou contexto do lead"
                  rows={3}
                  className="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </label>
            </div>

            <div className="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
              <p className="text-xs text-muted-foreground">
                Novos agendamentos feitos pelo chat e pela tela inicial também entram nesta lista com a origem identificada.
              </p>
              <button
                type="button"
                onClick={criarVisita}
                disabled={isCreatingVisit}
                className="inline-flex items-center justify-center rounded-xl bg-gradient-to-r from-sky-500 to-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 disabled:opacity-60"
              >
                {isCreatingVisit ? 'Criando visita...' : 'Criar visita'}
              </button>
            </div>
          </div>

          <div className="mb-6 grid grid-cols-1 gap-6 xl:grid-cols-[420px_1fr]">
            <div className="glass-panel rounded-3xl p-5">
              <div className="mb-4 flex items-center justify-between gap-3">
                <div>
                  <p className="text-sm font-semibold text-foreground">Calendário</p>
                  <p className="text-xs text-muted-foreground">Selecione um dia para filtrar as visitas.</p>
                </div>
                <button
                  type="button"
                  onClick={() => setSelectedDate(undefined)}
                  className="rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-medium text-muted-foreground transition hover:bg-white/10"
                >
                  Limpar filtro
                </button>
              </div>

              <div className="overflow-x-auto rounded-2xl bg-white/5 p-2">
                <Calendar
                  mode="single"
                  selected={selectedDate}
                  onSelect={setSelectedDate}
                  modifiers={{ hasVisit: visitDays }}
                  modifiersClassNames={{
                    hasVisit: 'bg-amber-400/15 text-amber-100 font-semibold',
                  }}
                  className="w-full text-foreground"
                />
              </div>

              <div className="mt-4 rounded-2xl border border-white/10 bg-white/5 p-4">
                <p className="text-sm font-semibold text-foreground">
                  {selectedDate ? formatLongDate(selectedDate) : 'Todos os dias'}
                </p>
                <p className="mt-1 text-xs text-muted-foreground">
                  {selectedDate
                    ? `${filteredVisitas.length} visita(s) encontradas para a data selecionada.`
                    : 'Dias com visitas aparecem destacados no calendário.'}
                </p>
              </div>
            </div>

            <div className="glass-panel rounded-3xl p-5">
              <div className="mb-4 flex items-start justify-between gap-4">
                <div>
                  <div className="flex items-center gap-2 text-foreground">
                    <CalendarDays size={18} className="text-sky-300" />
                    <p className="text-sm font-semibold">Google Agenda</p>
                  </div>
                  <p className="mt-1 text-xs text-muted-foreground">
                    Acompanhe a agenda externa junto da agenda interna sem sair da tela.
                  </p>
                </div>
                <button
                  type="button"
                  onClick={() => setShowGoogleCalendarSettings((current) => !current)}
                  className="rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-medium text-muted-foreground transition hover:bg-white/10"
                >
                  {showGoogleCalendarSettings ? 'Ocultar configuração' : 'Configurar'}
                </button>
              </div>

              {showGoogleCalendarSettings && (
                <>
                  <div className="mb-4 rounded-2xl border border-sky-400/20 bg-sky-500/10 p-4">
                    <p className="text-sm font-semibold text-foreground">Onde copiar no Google</p>
                    <p className="mt-2 text-xs leading-6 text-muted-foreground">
                      No Google Calendar, abra as configurações do calendário desejado, entre em "Integrar agenda" e copie apenas a URL do atributo <span className="font-semibold text-foreground">src</span> do código de incorporação. Não cole o iframe inteiro.
                    </p>
                  </div>

                  <div className="grid grid-cols-1 gap-3 xl:grid-cols-[1fr_auto]">
                    <input
                      type="url"
                      value={googleCalendarDraft}
                      onChange={(event) => setGoogleCalendarDraft(event.target.value)}
                      placeholder="Google: https://calendar.google.com/calendar/embed?..."
                      className="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                    <button
                      type="button"
                      onClick={salvarGoogleCalendar}
                      disabled={isSavingGoogleCalendar}
                      className="inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-sky-500 to-cyan-400 px-4 py-3 text-sm font-semibold text-slate-950 disabled:opacity-60"
                    >
                      <Save size={16} />
                      {isSavingGoogleCalendar ? 'Salvando...' : 'Salvar'}
                    </button>
                  </div>

                  <p className="mt-3 text-xs text-muted-foreground">
                    Dica: se você copiar um código completo de iframe, extraia apenas o valor de <span className="font-semibold text-foreground">src="..."</span> e cole aqui.
                  </p>
                </>
              )}

              <div className="mt-4 rounded-2xl border border-white/10 bg-[#07111d]/70 p-3">
                {googleCalendarEmbedUrl ? (
                  <iframe
                    src={googleCalendarEmbedUrl}
                    title="Google Agenda"
                    className="h-[420px] w-full rounded-2xl border border-white/10 bg-white"
                    loading="lazy"
                    referrerPolicy="no-referrer-when-downgrade"
                  />
                ) : (
                  <div className="flex h-[220px] flex-col items-center justify-center rounded-2xl border border-dashed border-white/10 text-center text-muted-foreground">
                    <CalendarDays size={28} className="mb-3 text-sky-300" />
                    <p className="font-medium text-foreground">Google Agenda não configurado</p>
                    <p className="mt-2 max-w-md text-sm text-muted-foreground">
                      Abra a configuração para informar a URL de incorporação e visualizar seu calendário externo aqui.
                    </p>
                  </div>
                )}
              </div>
            </div>
          </div>

          <div className="space-y-4">
            {isLoading && (
              <div className="glass-panel p-6 rounded-2xl text-center text-muted-foreground">
                Carregando visitas...
              </div>
            )}

            {!isLoading && filteredVisitas.length === 0 && (
              <div className="glass-panel p-6 rounded-2xl text-center text-muted-foreground">
                Nenhuma visita encontrada com os filtros atuais.
              </div>
            )}

            {!isLoading &&
              filteredVisitas.map((visita) => {
                const config = statusConfig[visita.status];
                return (
                  <div key={visita.id} className="glass-panel p-6 rounded-2xl">
                    <div className="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                      <div className="space-y-2">
                        <h2 className="text-xl font-semibold text-foreground">
                          {visita.property_titulo || 'Imóvel sem título'}
                        </h2>
                        <div className="text-muted-foreground text-sm">
                          <span className="font-medium text-foreground">{visita.nome}</span>
                          {visita.email && <span className="ml-2">• {visita.email}</span>}
                          {visita.telefone && <span className="ml-2">• {visita.telefone}</span>}
                        </div>
                        <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                          <span className="rounded-full border border-white/10 bg-white/5 px-2.5 py-1 text-foreground">
                            {visita.assigned_user_name || 'Sem responsável'}
                          </span>
                          {isVisitOverdue(visita) && (
                            <span className="rounded-full border border-red-400/40 bg-red-500/15 px-2.5 py-1 text-red-200">
                              Atrasada
                            </span>
                          )}
                          <span>{formatVisitOrigin(visita.origem)}</span>
                          {visita.created_by_user_name && <span>• criado por {visita.created_by_user_name}</span>}
                        </div>
                        <div className="text-sm text-muted-foreground">{formatDateTime(visita.data_hora)}</div>
                        {visita.observacoes && (
                          <p className="text-sm text-muted-foreground">{visita.observacoes}</p>
                        )}
                        <div className="flex flex-wrap items-center gap-2 pt-1 text-xs">
                          {visita.lead_id && (
                            <a
                              href={`/leads/${visita.lead_id}`}
                              className="rounded-lg border border-white/10 bg-white/5 px-2.5 py-1 text-foreground transition hover:bg-white/10"
                            >
                              Ver lead #{visita.lead_id}
                            </a>
                          )}
                          {visita.property_id && (
                            <a
                              href={`/properties/${visita.property_id}/editar`}
                              className="rounded-lg border border-white/10 bg-white/5 px-2.5 py-1 text-foreground transition hover:bg-white/10"
                            >
                              Ver imóvel #{visita.property_id}
                            </a>
                          )}
                          {visita.telefone && (
                            <a
                              href={`https://wa.me/${visita.telefone.replace(/\D/g, '')}`}
                              target="_blank"
                              rel="noreferrer"
                              className="rounded-lg border border-emerald-400/30 bg-emerald-500/15 px-2.5 py-1 text-emerald-100 transition hover:bg-emerald-500/25"
                            >
                              WhatsApp
                            </a>
                          )}
                        </div>
                      </div>
                      <div className="flex flex-col sm:flex-row gap-3 items-start sm:items-center">
                        <span className={`px-3 py-1 rounded-full text-xs font-semibold ${config.className}`}>
                          {config.label}
                        </span>
                        <a
                          href={buildGoogleCalendarUrl(visita)}
                          target="_blank"
                          rel="noreferrer"
                          className="inline-flex items-center gap-2 rounded-xl border border-sky-400/30 bg-sky-500/10 px-3 py-2 text-sm font-medium text-sky-100 transition hover:bg-sky-500/20"
                        >
                          <ExternalLink size={14} />
                          Google Agenda
                        </a>
                        <select
                          value={visita.status}
                          onChange={(event) =>
                            atualizarStatus(visita.id, event.target.value as Visita['status'])
                          }
                          disabled={updatingId === visita.id}
                          className="px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-60"
                        >
                          <option value="pendente">Pendente</option>
                          <option value="confirmada">Confirmada</option>
                          <option value="concluida">Concluída</option>
                          <option value="cancelada">Cancelada</option>
                        </select>
                        <button
                          type="button"
                          onClick={() => openQuickEdit(visita)}
                          className="rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm font-medium text-foreground transition hover:bg-white/10"
                        >
                          Reagendar
                        </button>
                      </div>
                    </div>

                    <div className="mt-4 flex flex-wrap gap-2">
                      {visita.status !== 'confirmada' && (
                        <button
                          type="button"
                          onClick={() => aplicarStatusRapido(visita.id, 'confirmada')}
                          disabled={updatingId === visita.id}
                          className="rounded-lg border border-sky-400/30 bg-sky-500/15 px-2.5 py-1.5 text-xs font-semibold text-sky-100 transition hover:bg-sky-500/25 disabled:opacity-60"
                        >
                          Confirmar agora
                        </button>
                      )}
                      {visita.status !== 'concluida' && (
                        <button
                          type="button"
                          onClick={() => aplicarStatusRapido(visita.id, 'concluida')}
                          disabled={updatingId === visita.id}
                          className="rounded-lg border border-emerald-400/30 bg-emerald-500/15 px-2.5 py-1.5 text-xs font-semibold text-emerald-100 transition hover:bg-emerald-500/25 disabled:opacity-60"
                        >
                          Marcar concluída
                        </button>
                      )}
                      {visita.status !== 'cancelada' && (
                        <button
                          type="button"
                          onClick={() => aplicarStatusRapido(visita.id, 'cancelada')}
                          disabled={updatingId === visita.id}
                          className="rounded-lg border border-red-400/30 bg-red-500/15 px-2.5 py-1.5 text-xs font-semibold text-red-100 transition hover:bg-red-500/25 disabled:opacity-60"
                        >
                          Cancelar
                        </button>
                      )}
                    </div>

                    {editingId === visita.id && (
                      <div className="mt-4 grid grid-cols-1 gap-3 rounded-2xl border border-white/10 bg-white/5 p-4 xl:grid-cols-3">
                        <label className="block">
                          <span className="mb-1 block text-xs font-medium text-muted-foreground">Reagendar para</span>
                          <input
                            type="datetime-local"
                            value={quickEdit.data_hora}
                            onChange={(event) =>
                              setQuickEdit((current) => ({ ...current, data_hora: event.target.value }))
                            }
                            className="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                          />
                        </label>

                        <label className="block">
                          <span className="mb-1 block text-xs font-medium text-muted-foreground">Responsável</span>
                          <select
                            value={quickEdit.assigned_user_id}
                            onChange={(event) =>
                              setQuickEdit((current) => ({ ...current, assigned_user_id: event.target.value }))
                            }
                            disabled={isBrokerUser}
                            className="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 disabled:opacity-70"
                          >
                            {currentUser?.id && <option value={String(currentUser.id)}>Minha agenda</option>}
                            {corretores
                              .filter((corretor) => String(corretor.id) !== String(currentUser?.id || ''))
                              .map((corretor) => (
                                <option key={corretor.id} value={String(corretor.id)}>
                                  {corretor.name}
                                </option>
                              ))}
                          </select>
                        </label>

                        <label className="block xl:col-span-3">
                          <span className="mb-1 block text-xs font-medium text-muted-foreground">Observações</span>
                          <textarea
                            rows={2}
                            value={quickEdit.observacoes}
                            onChange={(event) =>
                              setQuickEdit((current) => ({ ...current, observacoes: event.target.value }))
                            }
                            className="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                          />
                        </label>

                        <div className="flex flex-wrap gap-2 xl:col-span-3">
                          <button
                            type="button"
                            onClick={() => salvarQuickEdit(visita.id)}
                            disabled={savingQuickEditId === visita.id}
                            className="rounded-xl bg-gradient-to-r from-sky-500 to-cyan-400 px-4 py-2 text-sm font-semibold text-slate-950 disabled:opacity-60"
                          >
                            {savingQuickEditId === visita.id ? 'Salvando...' : 'Salvar alterações'}
                          </button>
                          <button
                            type="button"
                            onClick={closeQuickEdit}
                            className="rounded-xl border border-white/10 bg-white/5 px-4 py-2 text-sm font-semibold text-foreground transition hover:bg-white/10"
                          >
                            Cancelar edição
                          </button>
                        </div>
                      </div>
                    )}
                  </div>
                );
              })}
          </div>
        </motion.div>
      </div>
    </div>
  );
}
