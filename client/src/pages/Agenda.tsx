import { useEffect, useMemo, useState } from 'react';
import { motion } from 'framer-motion';
import { CalendarClock, CalendarDays, ExternalLink, RefreshCcw, Save, Search } from 'lucide-react';
import { toast } from 'sonner';
import Sidebar from '@/components/Sidebar';
import { Calendar } from '@/components/ui/calendar';
import { api } from '@/lib/api';

interface Visita {
  id: number;
  property_titulo?: string | null;
  nome: string;
  email?: string | null;
  telefone?: string | null;
  data_hora: string;
  status: 'pendente' | 'confirmada' | 'cancelada' | 'concluida';
  observacoes?: string | null;
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
  const [isLoading, setIsLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [updatingId, setUpdatingId] = useState<number | null>(null);
  const [selectedDate, setSelectedDate] = useState<Date | undefined>();
  const [googleCalendarEmbedUrl, setGoogleCalendarEmbedUrl] = useState('');
  const [googleCalendarDraft, setGoogleCalendarDraft] = useState('');
  const [isSavingGoogleCalendar, setIsSavingGoogleCalendar] = useState(false);

  const filteredVisitas = useMemo(() => {
    const normalizedSearch = search.trim().toLowerCase();
    return visitas.filter((visita) => {
      const visitaDate = parseVisitaDate(visita.data_hora);
      const matchSearch =
        !normalizedSearch ||
        visita.nome.toLowerCase().includes(normalizedSearch) ||
        visita.property_titulo?.toLowerCase().includes(normalizedSearch);
      const matchStatus = !statusFilter || visita.status === statusFilter;
      const matchDate = !selectedDate || (visitaDate ? sameCalendarDay(visitaDate, selectedDate) : false);
      return matchSearch && matchStatus && matchDate;
    });
  }, [search, selectedDate, statusFilter, visitas]);

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

  const carregarVisitas = async () => {
    setIsLoading(true);
    try {
      const response = await api.get('/admin/visitas');
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
      toast.success(embedUrl ? 'Google Agenda conectado na tela' : 'Integração do Google Agenda removida');
    } catch (error: any) {
      console.error('Erro ao salvar Google Agenda:', error);
      toast.error(error?.response?.data?.error || 'Não foi possível salvar a integração do Google Agenda');
    } finally {
      setIsSavingGoogleCalendar(false);
    }
  };

  useEffect(() => {
    carregarVisitas();
    carregarConfiguracaoAgenda();
  }, []);

  return (
    <div className="flex">
      <Sidebar />

      <div className="page-shell">
        <motion.div
          initial={{ opacity: 0, y: 16 }}
          animate={{ opacity: 1, y: 0 }}
          className="max-w-6xl mx-auto"
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
              { label: 'Confirmadas', value: confirmedCount },
            ].map((item) => (
              <div key={item.label} className="glass-panel rounded-2xl p-5">
                <p className="text-sm text-muted-foreground">{item.label}</p>
                <p className="mt-2 text-3xl font-bold text-foreground">{item.value}</p>
              </div>
            ))}
          </div>

          <div className="glass-panel p-4 rounded-2xl mb-6">
            <div className="grid grid-cols-1 md:grid-cols-[1fr_220px] gap-4">
              <div className="relative">
                <Search className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" size={18} />
                <input
                  type="text"
                  value={search}
                  onChange={(event) => setSearch(event.target.value)}
                  placeholder="Buscar por lead ou imóvel"
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
                    Cole a URL de incorporação do Google Calendar para ver o calendário oficial lado a lado com a agenda interna.
                  </p>
                </div>
              </div>

              <div className="flex flex-col gap-3 md:flex-row">
                <input
                  type="url"
                  value={googleCalendarDraft}
                  onChange={(event) => setGoogleCalendarDraft(event.target.value)}
                  placeholder="https://calendar.google.com/calendar/embed?..."
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
                    <p className="font-medium text-foreground">Google Agenda ainda não configurado</p>
                    <p className="mt-2 max-w-md text-sm text-muted-foreground">
                      Use a URL de incorporação do seu calendário do Google para acompanhar a agenda externa sem sair desta tela.
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
                        <div className="text-sm text-muted-foreground">{formatDateTime(visita.data_hora)}</div>
                        {visita.observacoes && (
                          <p className="text-sm text-muted-foreground">{visita.observacoes}</p>
                        )}
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
                      </div>
                    </div>
                  </div>
                );
              })}
          </div>
        </motion.div>
      </div>
    </div>
  );
}
