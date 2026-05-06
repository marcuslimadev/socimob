import { useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import { Loader2, Plus, GripVertical, ArrowRight } from 'lucide-react';
import { toast } from 'sonner';
import { Link, useLocation } from 'wouter';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';

interface Solicitacao {
  id: number;
  codigo: string | null;
  status: string;
  cliente_nome: string;
  tipo: string;
  imovel_id: number | null;
  vistoria_id?: number | null;
  created_at?: string | null;
}

interface KanbanColumn {
  id: string;
  title: string;
  status: string;
  items: Solicitacao[];
}

const COLUMNS: { id: string; title: string; status: string; color: string }[] = [
  { id: 'solicitada', title: 'Solicitada', status: 'solicitada', color: 'bg-blue-500' },
  { id: 'designada', title: 'Designada', status: 'designada', color: 'bg-yellow-500' },
  { id: 'andamento', title: 'Em Andamento', status: 'andamento', color: 'bg-purple-500' },
  { id: 'concluida', title: 'Concluída', status: 'concluida', color: 'bg-green-500' },
  { id: 'cancelada', title: 'Cancelada', status: 'cancelada', color: 'bg-red-500' },
];

export default function VistoriaSolicitacoesKanban() {
  const [, setLocation] = useLocation();
  const [columns, setColumns] = useState<KanbanColumn[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [draggedItem, setDraggedItem] = useState<Solicitacao | null>(null);
  const [draggedFromColumn, setDraggedFromColumn] = useState<string | null>(null);
  const [convertingId, setConvertingId] = useState<number | null>(null);

  useEffect(() => {
    fetchSolicitacoes();
  }, []);

  const fetchSolicitacoes = async () => {
    try {
      setIsLoading(true);
      const response = await api.get('/vistorias/solicitacoes', {
        params: { per_page: 100 }
      });

      const solicitacoes = response.data.data || [];

      // Agrupar por status
      const groupedColumns = COLUMNS.map(col => ({
        id: col.id,
        title: col.title,
        status: col.status,
        items: solicitacoes.filter((s: Solicitacao) => s.status === col.status)
      }));

      setColumns(groupedColumns);
    } catch (error) {
      console.error('Erro ao carregar solicitações:', error);
      toast.error('Erro ao carregar solicitações');
    } finally {
      setIsLoading(false);
    }
  };

  const converterEmVistoria = async (item: Solicitacao) => {
    if (item.vistoria_id || item.status === 'cancelada') return;
    setConvertingId(item.id);
    try {
      const { data } = await api.post(`/vistorias/solicitacoes/${item.id}/converter`);
      const vid = typeof data?.vistoria_id === 'number' ? data.vistoria_id : Number(data?.vistoria_id);
      toast.success(
        data?.already_converted
          ? 'Esta solicitação já havia sido convertida.'
          : data?.message || 'Solicitação convertida.',
        {
          action:
            vid > 0
              ? {
                  label: 'Abrir vistoria',
                  onClick: () => setLocation(`/vistorias/${vid}`),
                }
              : undefined,
        }
      );
      await fetchSolicitacoes();
    } catch (e: any) {
      console.error(e);
      toast.error(e?.response?.data?.message || 'Não foi possível converter.');
    } finally {
      setConvertingId(null);
    }
  };

  const handleDragStart = (item: Solicitacao, columnId: string) => {
    setDraggedItem(item);
    setDraggedFromColumn(columnId);
  };

  const handleDragOver = (e: React.DragEvent) => {
    e.preventDefault();
  };

  const handleDrop = async (targetColumnId: string) => {
    if (!draggedItem || !draggedFromColumn || draggedFromColumn === targetColumnId) {
      setDraggedItem(null);
      setDraggedFromColumn(null);
      return;
    }

    try {
      // Atualizar status no backend
      await api.put(`/vistorias/solicitacoes/${draggedItem.id}/status`, {
        status: targetColumnId
      });

      // Atualizar UI localmente
      setColumns(prevColumns => {
        const newColumns = prevColumns.map(col => ({
          ...col,
          items: col.items.filter(item => item.id !== draggedItem.id)
        }));

        const targetColumn = newColumns.find(col => col.id === targetColumnId);
        if (targetColumn) {
          targetColumn.items.push({ ...draggedItem, status: targetColumnId });
        }

        return newColumns;
      });

      toast.success('Status atualizado com sucesso');
    } catch (error) {
      console.error('Erro ao atualizar status:', error);
      toast.error('Erro ao atualizar status');
    } finally {
      setDraggedItem(null);
      setDraggedFromColumn(null);
    }
  };

  const formatDate = (dateString?: string | null) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR', { dateStyle: 'short' });
  };

  return (
    <div className="flex">
      <Sidebar />

      <div className="page-shell">
        <motion.div
          initial={{ opacity: 0, y: 16 }}
          animate={{ opacity: 1, y: 0 }}
          className="max-w-7xl mx-auto"
        >
          <div className="page-header mb-8">
            <div>
              <h1 className="page-title mb-2">Kanban - Solicitações</h1>
              <p className="page-subtitle">Visualização em quadro Kanban</p>
            </div>
            <div className="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:flex-wrap">
              <Link to="/vistorias/solicitacoes">
                <motion.button
                  whileHover={{ scale: 1.05 }}
                  whileTap={{ scale: 0.95 }}
                  className="w-full rounded-lg border border-white/20 bg-white/10 px-5 py-3 font-semibold text-foreground sm:w-auto"
                >
                  Ver Lista
                </motion.button>
              </Link>
              <Link to="/vistorias/solicitacoes/calendario">
                <motion.button
                  whileHover={{ scale: 1.05 }}
                  whileTap={{ scale: 0.95 }}
                  className="w-full rounded-lg border border-white/20 bg-white/10 px-5 py-3 font-semibold text-foreground sm:w-auto"
                >
                  Ver Calendário
                </motion.button>
              </Link>
              <Link to="/vistorias/solicitacoes/nova">
                <motion.button
                  whileHover={{ scale: 1.05 }}
                  whileTap={{ scale: 0.95 }}
                  className="flex w-full items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-blue-500 to-blue-600 px-5 py-3 font-semibold text-white sm:w-auto"
                >
                  <Plus size={18} />
                  Nova Solicitação
                </motion.button>
              </Link>
            </div>
          </div>

          {isLoading ? (
            <div className="flex items-center justify-center py-12">
              <Loader2 className="w-8 h-8 animate-spin text-primary" />
            </div>
          ) : (
            <div className="flex gap-4 overflow-x-auto pb-4">
              {COLUMNS.map((col) => {
                const column = columns.find(c => c.id === col.id);
                const items = column?.items || [];

                return (
                  <div
                    key={col.id}
                    className="flex-shrink-0 w-80"
                    onDragOver={handleDragOver}
                    onDrop={() => handleDrop(col.id)}
                  >
                    <div className="glass-panel rounded-2xl p-4">
                      <div className="flex items-center gap-2 mb-4">
                        <div className={`w-3 h-3 rounded-full ${col.color}`} />
                        <h3 className="font-bold text-foreground">{col.title}</h3>
                        <span className="ml-auto text-sm text-muted-foreground bg-white/10 px-2 py-1 rounded">
                          {items.length}
                        </span>
                      </div>

                      <div className="space-y-3 min-h-[400px]">
                        {items.map((item) => (
                          <motion.div
                            key={item.id}
                            draggable
                            onDragStart={() => handleDragStart(item, col.id)}
                            whileHover={{ scale: 1.02 }}
                            className="bg-white/5 border border-white/10 rounded-lg p-4 cursor-move hover:bg-white/10 transition-all"
                          >
                            <div className="flex items-start gap-2 mb-2">
                              <GripVertical size={16} className="text-muted-foreground mt-1" />
                              <div className="flex-1">
                                <div className="font-semibold text-foreground mb-1">
                                  {item.codigo || `#${item.id}`}
                                </div>
                                <div className="text-sm text-muted-foreground mb-2">
                                  {item.cliente_nome}
                                </div>
                                <div className="flex items-center gap-2 text-xs">
                                  <span className="px-2 py-1 bg-blue-500/20 text-blue-300 rounded">
                                    {item.tipo}
                                  </span>
                                  {item.imovel_id && (
                                    <span className="text-muted-foreground">
                                      Imóvel #{item.imovel_id}
                                    </span>
                                  )}
                                </div>
                                <div className="text-xs text-muted-foreground mt-2">
                                  {formatDate(item.created_at)}
                                </div>
                                <div
                                  className="mt-3 flex flex-wrap gap-2 border-t border-white/10 pt-2"
                                  onMouseDown={(e) => e.stopPropagation()}
                                >
                                  {item.vistoria_id ? (
                                    <Link
                                      to={`/vistorias/${item.vistoria_id}`}
                                      className="inline-flex items-center gap-1 rounded-md border border-emerald-500/35 bg-emerald-500/10 px-2 py-1 text-[11px] font-medium text-emerald-100"
                                    >
                                      Vistoria <ArrowRight size={12} aria-hidden />
                                    </Link>
                                  ) : item.status !== 'cancelada' ? (
                                    <button
                                      type="button"
                                      disabled={convertingId === item.id}
                                      onClick={() => converterEmVistoria(item)}
                                      className="rounded-md border border-blue-500/35 bg-blue-500/15 px-2 py-1 text-[11px] font-semibold text-blue-100 disabled:opacity-50 touch-manipulation"
                                    >
                                      {convertingId === item.id ? '…' : 'Gerar vistoria'}
                                    </button>
                                  ) : null}
                                </div>
                              </div>
                            </div>
                          </motion.div>
                        ))}

                        {items.length === 0 && (
                          <div className="text-center py-8 text-muted-foreground text-sm">
                            Nenhuma solicitação
                          </div>
                        )}
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>
          )}
        </motion.div>
      </div>
    </div>
  );
}
