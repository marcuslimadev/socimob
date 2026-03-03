import { useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import { ClipboardCheck, Loader2, Filter, Plus } from 'lucide-react';
import { toast } from 'sonner';
import { Link } from 'wouter';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';

interface Solicitacao {
  id: number;
  codigo: string | null;
  status: string;
  cliente_nome: string;
  tipo: string;
  imovel_id: number | null;
  created_at?: string | null;
}

export default function VistoriaSolicitacoes() {
  const [solicitacoes, setSolicitacoes] = useState<Solicitacao[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [statusFilter, setStatusFilter] = useState('todos');
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);

  useEffect(() => {
    fetchSolicitacoes();
  }, [page, statusFilter]);

  const fetchSolicitacoes = async () => {
    try {
      setIsLoading(true);
      const params: Record<string, string | number> = { page, per_page: 10 };
      if (statusFilter !== 'todos') {
        params.status = statusFilter;
      }
      const response = await api.get('/vistorias/solicitacoes', { params });
      setSolicitacoes(response.data.data || []);
      setPage(response.data.current_page || 1);
      setLastPage(response.data.last_page || 1);
    } catch (error) {
      console.error('Erro ao carregar solicitacoes:', error);
      toast.error('Erro ao carregar solicitacoes');
    } finally {
      setIsLoading(false);
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
          className="max-w-6xl mx-auto"
        >
          <div className="page-header mb-8">
            <div>
              <h1 className="page-title mb-2">Solicitações</h1>
              <p className="page-subtitle">Acompanhe as solicitações de vistoria.</p>
            </div>
            <div className="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:flex-wrap">
              <Link to="/vistorias/solicitacoes/kanban">
                <motion.button
                  whileHover={{ scale: 1.05 }}
                  whileTap={{ scale: 0.95 }}
                  className="w-full rounded-lg border border-white/20 bg-white/10 px-5 py-3 font-semibold text-foreground sm:w-auto"
                >
                  Ver Kanban
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
                  Nova solicitação
                </motion.button>
              </Link>
            </div>
          </div>

          <div className="glass-panel p-6 rounded-2xl mb-6 flex flex-wrap items-center gap-3">
            <Filter size={18} className="text-muted-foreground" />
            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className="px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
            >
              <option value="todos">Todos</option>
              <option value="solicitada">Solicitada</option>
              <option value="designada">Designada</option>
              <option value="andamento">Em andamento</option>
              <option value="concluida">Concluída</option>
              <option value="cancelada">Cancelada</option>
            </select>
          </div>

          <div className="glass-panel p-6 rounded-2xl">
            <div className="flex items-center justify-between mb-6">
              <h2 className="text-xl font-bold text-foreground flex items-center gap-2">
                <ClipboardCheck size={22} />
                Lista de solicitacoes
              </h2>
              <span className="text-sm text-muted-foreground">
                Pagina {page} de {lastPage}
              </span>
            </div>

            {isLoading ? (
              <div className="flex items-center justify-center py-12">
                <Loader2 className="w-8 h-8 animate-spin text-primary" />
              </div>
            ) : solicitacoes.length === 0 ? (
              <div className="text-center py-12 text-muted-foreground">
                Nenhuma solicitacao encontrada.
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="text-left text-muted-foreground">
                      <th className="pb-3">Codigo</th>
                      <th className="pb-3">Cliente</th>
                      <th className="pb-3">Tipo</th>
                      <th className="pb-3">Imovel</th>
                      <th className="pb-3">Status</th>
                      <th className="pb-3">Criado em</th>
                    </tr>
                  </thead>
                  <tbody>
                    {solicitacoes.map((item) => (
                      <tr key={item.id} className="border-t border-white/10">
                        <td className="py-3 text-foreground">{item.codigo || `#${item.id}`}</td>
                        <td className="py-3 text-foreground">{item.cliente_nome}</td>
                        <td className="py-3 text-foreground">{item.tipo}</td>
                        <td className="py-3 text-foreground">{item.imovel_id || '-'}</td>
                        <td className="py-3 text-foreground">{item.status}</td>
                        <td className="py-3 text-foreground">{formatDate(item.created_at)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}

            {!isLoading && solicitacoes.length > 0 && (
              <div className="flex items-center justify-center gap-3 mt-8">
                <motion.button
                  whileHover={{ scale: 1.05 }}
                  whileTap={{ scale: 0.95 }}
                  onClick={() => setPage((prev) => Math.max(1, prev - 1))}
                  disabled={page === 1}
                  className="px-4 py-2 rounded-lg bg-white/10 border border-white/20 text-foreground disabled:opacity-40"
                >
                  Anterior
                </motion.button>
                <motion.button
                  whileHover={{ scale: 1.05 }}
                  whileTap={{ scale: 0.95 }}
                  onClick={() => setPage((prev) => Math.min(lastPage, prev + 1))}
                  disabled={page === lastPage}
                  className="px-4 py-2 rounded-lg bg-white/10 border border-white/20 text-foreground disabled:opacity-40"
                >
                  Proxima
                </motion.button>
              </div>
            )}
          </div>
        </motion.div>
      </div>
    </div>
  );
}
