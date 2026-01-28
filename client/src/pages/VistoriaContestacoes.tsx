import { useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import { AlertTriangle, Loader2, Filter, Plus, LayoutGrid, List } from 'lucide-react';
import { toast } from 'sonner';
import { Link } from 'wouter';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';

interface Contestacao {
  id: number;
  codigo: string | null;
  status: string;
  tipo: string;
  descricao: string;
  cliente_nome: string | null;
  imovel_referencia: string | null;
  data_contestacao?: string | null;
  data_resolucao?: string | null;
}

const getStatusColor = (status: string) => {
  const colors: Record<string, string> = {
    apontada: 'bg-red-500/20 text-red-300',
    em_analise: 'bg-yellow-500/20 text-yellow-300',
    finalizada: 'bg-green-500/20 text-green-300',
  };
  return colors[status] || 'bg-gray-500/20 text-gray-300';
};

const getStatusLabel = (status: string) => {
  const labels: Record<string, string> = {
    apontada: 'Apontada',
    em_analise: 'Em Análise',
    finalizada: 'Finalizada',
  };
  return labels[status] || status;
};

const getTipoLabel = (tipo: string) => {
  const labels: Record<string, string> = {
    dano: 'Dano',
    divergencia: 'Divergência',
    outro: 'Outro',
  };
  return labels[tipo] || tipo;
};

export default function VistoriaContestacoes() {
  const [contestacoes, setContestacoes] = useState<Contestacao[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [viewMode, setViewMode] = useState<'list' | 'kanban'>('list');
  const [statusFilter, setStatusFilter] = useState('todos');
  const [tipoFilter, setTipoFilter] = useState('todos');
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);

  useEffect(() => {
    fetchContestacoes();
  }, [page, statusFilter, tipoFilter]);

  const fetchContestacoes = async () => {
    try {
      setIsLoading(true);
      const params: Record<string, string | number> = { page, per_page: 10 };
      if (statusFilter !== 'todos') {
        params.status = statusFilter;
      }
      if (tipoFilter !== 'todos') {
        params.tipo = tipoFilter;
      }
      const response = await api.get('/vistorias/contestacoes', { params });
      setContestacoes(response.data.data || []);
      setPage(response.data.current_page || 1);
      setLastPage(response.data.last_page || 1);
    } catch (error) {
      console.error('Erro ao carregar contestações:', error);
      toast.error('Erro ao carregar contestações');
    } finally {
      setIsLoading(false);
    }
  };

  const formatDate = (dateString?: string | null) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR', { dateStyle: 'short' });
  };

  const renderListView = () => (
    <div className="glass-panel p-6 rounded-2xl">
      <div className="flex items-center justify-between mb-6">
        <h2 className="text-xl font-bold text-foreground flex items-center gap-2">
          <AlertTriangle size={22} />
          Lista de contestações
        </h2>
        <span className="text-sm text-muted-foreground">
          Página {page} de {lastPage}
        </span>
      </div>

      {isLoading ? (
        <div className="flex items-center justify-center py-12">
          <Loader2 className="w-8 h-8 animate-spin text-primary" />
        </div>
      ) : contestacoes.length === 0 ? (
        <div className="text-center py-12 text-muted-foreground">
          Nenhuma contestação encontrada.
        </div>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="text-left text-muted-foreground">
                <th className="pb-3">Código</th>
                <th className="pb-3">Tipo</th>
                <th className="pb-3">Cliente</th>
                <th className="pb-3">Imóvel</th>
                <th className="pb-3">Status</th>
                <th className="pb-3">Data</th>
              </tr>
            </thead>
            <tbody>
              {contestacoes.map((item) => (
                <tr key={item.id} className="border-t border-white/10">
                  <td className="py-3 text-foreground">{item.codigo || `#${item.id}`}</td>
                  <td className="py-3 text-foreground">{getTipoLabel(item.tipo)}</td>
                  <td className="py-3 text-foreground">{item.cliente_nome || '-'}</td>
                  <td className="py-3 text-foreground">{item.imovel_referencia || '-'}</td>
                  <td className="py-3">
                    <span className={`px-2 py-1 rounded text-xs ${getStatusColor(item.status)}`}>
                      {getStatusLabel(item.status)}
                    </span>
                  </td>
                  <td className="py-3 text-foreground">{formatDate(item.data_contestacao)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}

      {!isLoading && contestacoes.length > 0 && (
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
            Próxima
          </motion.button>
        </div>
      )}
    </div>
  );

  const renderKanbanView = () => {
    const columns = [
      { id: 'apontada', title: 'Apontadas', color: 'bg-red-500' },
      { id: 'em_analise', title: 'Em Análise', color: 'bg-yellow-500' },
      { id: 'finalizada', title: 'Finalizadas', color: 'bg-green-500' },
    ];

    const groupedContestacoes = columns.map(col => ({
      ...col,
      items: contestacoes.filter(c => c.status === col.id)
    }));

    return (
      <div className="flex gap-4 overflow-x-auto pb-4">
        {groupedContestacoes.map((col) => (
          <div key={col.id} className="flex-shrink-0 w-80">
            <div className="glass-panel rounded-2xl p-4">
              <div className="flex items-center gap-2 mb-4">
                <div className={`w-3 h-3 rounded-full ${col.color}`} />
                <h3 className="font-bold text-foreground">{col.title}</h3>
                <span className="ml-auto text-sm text-muted-foreground bg-white/10 px-2 py-1 rounded">
                  {col.items.length}
                </span>
              </div>

              <div className="space-y-3 min-h-[400px]">
                {col.items.map((item) => (
                  <motion.div
                    key={item.id}
                    whileHover={{ scale: 1.02 }}
                    className="bg-white/5 border border-white/10 rounded-lg p-4 hover:bg-white/10 transition-all"
                  >
                    <div className="font-semibold text-foreground mb-1">
                      {item.codigo || `#${item.id}`}
                    </div>
                    <div className="text-sm text-muted-foreground mb-2">
                      {item.cliente_nome || 'Cliente não informado'}
                    </div>
                    <div className="flex items-center gap-2 text-xs">
                      <span className="px-2 py-1 bg-blue-500/20 text-blue-300 rounded">
                        {getTipoLabel(item.tipo)}
                      </span>
                      {item.imovel_referencia && (
                        <span className="text-muted-foreground">
                          {item.imovel_referencia}
                        </span>
                      )}
                    </div>
                    <div className="text-xs text-muted-foreground mt-2">
                      {formatDate(item.data_contestacao)}
                    </div>
                  </motion.div>
                ))}

                {col.items.length === 0 && (
                  <div className="text-center py-8 text-muted-foreground text-sm">
                    Nenhuma contestação
                  </div>
                )}
              </div>
            </div>
          </div>
        ))}
      </div>
    );
  };

  return (
    <div className="flex">
      <Sidebar />

      <div className="flex-1 md:ml-80 min-h-screen p-4 md:p-8">
        <motion.div
          initial={{ opacity: 0, y: 16 }}
          animate={{ opacity: 1, y: 0 }}
          className="max-w-7xl mx-auto"
        >
          <div className="mb-8 flex items-center justify-between">
            <div>
              <h1 className="text-4xl font-bold gradient-text mb-2">Contestações</h1>
              <p className="text-muted-foreground">Gerencie contestações de vistoria.</p>
            </div>
            <div className="flex gap-3">
              <motion.button
                whileHover={{ scale: 1.05 }}
                whileTap={{ scale: 0.95 }}
                onClick={() => setViewMode(viewMode === 'list' ? 'kanban' : 'list')}
                className="px-5 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground font-semibold flex items-center gap-2"
              >
                {viewMode === 'list' ? <LayoutGrid size={18} /> : <List size={18} />}
                {viewMode === 'list' ? 'Ver Kanban' : 'Ver Lista'}
              </motion.button>
              <Link to="/vistorias">
                <motion.button
                  whileHover={{ scale: 1.05 }}
                  whileTap={{ scale: 0.95 }}
                  className="px-5 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground font-semibold"
                >
                  Ver Vistorias
                </motion.button>
              </Link>
            </div>
          </div>

          <div className="glass-panel p-6 rounded-2xl mb-6">
            <div className="flex items-center gap-3 flex-wrap">
              <Filter size={18} className="text-muted-foreground" />
              <select
                value={statusFilter}
                onChange={(e) => setStatusFilter(e.target.value)}
                className="px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
              >
                <option value="todos">Todos os status</option>
                <option value="apontada">Apontada</option>
                <option value="em_analise">Em Análise</option>
                <option value="finalizada">Finalizada</option>
              </select>

              <select
                value={tipoFilter}
                onChange={(e) => setTipoFilter(e.target.value)}
                className="px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
              >
                <option value="todos">Todos os tipos</option>
                <option value="dano">Dano</option>
                <option value="divergencia">Divergência</option>
                <option value="outro">Outro</option>
              </select>
            </div>
          </div>

          {viewMode === 'list' ? renderListView() : renderKanbanView()}
        </motion.div>
      </div>
    </div>
  );
}
