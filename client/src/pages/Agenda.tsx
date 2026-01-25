import { useEffect, useMemo, useState } from 'react';
import { motion } from 'framer-motion';
import { CalendarClock, RefreshCcw, Search } from 'lucide-react';
import { toast } from 'sonner';
import Sidebar from '@/components/Sidebar';
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

export default function Agenda() {
  const [visitas, setVisitas] = useState<Visita[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [search, setSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [updatingId, setUpdatingId] = useState<number | null>(null);

  const filteredVisitas = useMemo(() => {
    const normalizedSearch = search.trim().toLowerCase();
    return visitas.filter((visita) => {
      const matchSearch =
        !normalizedSearch ||
        visita.nome.toLowerCase().includes(normalizedSearch) ||
        visita.property_titulo?.toLowerCase().includes(normalizedSearch);
      const matchStatus = !statusFilter || visita.status === statusFilter;
      return matchSearch && matchStatus;
    });
  }, [search, statusFilter, visitas]);

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

  useEffect(() => {
    carregarVisitas();
  }, []);

  return (
    <div className="flex">
      <Sidebar />

      <div className="flex-1 md:ml-80 min-h-screen p-4 md:p-8">
        <motion.div
          initial={{ opacity: 0, y: 16 }}
          animate={{ opacity: 1, y: 0 }}
          className="max-w-6xl mx-auto"
        >
          <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
            <div>
              <h1 className="text-4xl font-bold gradient-text mb-2 flex items-center gap-3">
                <CalendarClock className="text-blue-300" size={32} />
                Agenda de Visitas
              </h1>
              <p className="text-muted-foreground">Acompanhe confirmações e prepare cada visita com antecedência.</p>
            </div>
            <button
              type="button"
              onClick={carregarVisitas}
              className="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-white/10 text-sm font-semibold text-foreground hover:bg-white/20 transition"
            >
              <RefreshCcw size={16} />
              Atualizar
            </button>
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
