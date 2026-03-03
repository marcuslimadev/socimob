import { useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import {
  ClipboardCheck,
  Filter,
  Search,
  Loader2,
  Calendar,
  Check,
  X,
  Plus,
  Edit2,
  Trash2,
  Save,
} from 'lucide-react';
import { toast } from 'sonner';
import { Link } from 'wouter';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog';

interface Vistoria {
  id: number;
  codigo: string | null;
  status: string;
  cliente_nome: string | null;
  imovel_id: number | null;
  tipo: string | null;
  vistoriadores?: string[] | null;
  pessoas?: string[] | null;
  metragem?: string | null;
  mobiliado?: boolean | null;
  data_vistoria?: string | null;
  observacoes?: string | null;
}

export default function Vistorias() {
  const [vistorias, setVistorias] = useState<Vistoria[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [showModal, setShowModal] = useState(false);
  const [editingVistoria, setEditingVistoria] = useState<Vistoria | null>(null);
  const [isSaving, setIsSaving] = useState(false);
  const [filters, setFilters] = useState({
    codigo: '',
    status: 'todos',
    cliente: '',
    tipo: 'todos',
    imovel_id: '',
    vistoriador: '',
    pessoa: '',
    metragem_min: '',
    metragem_max: '',
    mobiliado: 'todos',
    data_inicio: '',
    data_fim: '',
  });
  const [deleteDialog, setDeleteDialog] = useState<{ open: boolean; id: number | null }>({
    open: false,
    id: null,
  });

  useEffect(() => {
    fetchVistorias();
  }, [page]);

  const getFilterParams = () => {
    const params: Record<string, string | number | boolean | undefined> = {};

    if (filters.codigo) params.codigo = filters.codigo;
    if (filters.status !== 'todos') params.status = filters.status;
    if (filters.cliente) params.cliente = filters.cliente;
    if (filters.tipo !== 'todos') params.tipo = filters.tipo;
    if (filters.imovel_id) params.imovel_id = filters.imovel_id;
    if (filters.vistoriador) params.vistoriador = filters.vistoriador;
    if (filters.pessoa) params.pessoa = filters.pessoa;
    if (filters.metragem_min) params.metragem_min = filters.metragem_min;
    if (filters.metragem_max) params.metragem_max = filters.metragem_max;
    if (filters.mobiliado !== 'todos') params.mobiliado = filters.mobiliado === 'sim';
    if (filters.data_inicio) params.data_inicio = filters.data_inicio;
    if (filters.data_fim) params.data_fim = filters.data_fim;

    return params;
  };

  const fetchVistorias = async () => {
    try {
      setIsLoading(true);
      const params: Record<string, string | number | boolean | undefined> = {
        page,
        per_page: 10,
      };

      Object.assign(params, getFilterParams());

      const response = await api.get('/vistorias', { params });
      setVistorias(response.data.data || []);
      setPage(response.data.current_page || 1);
      setLastPage(response.data.last_page || 1);
    } catch (error) {
      console.error('Erro ao carregar vistorias:', error);
      toast.error('Erro ao carregar vistorias');
    } finally {
      setIsLoading(false);
    }
  };

  const handleFilterChange = (field: string, value: string) => {
    setFilters((prev) => ({ ...prev, [field]: value }));
  };

  const applyFilters = () => {
    setPage(1);
    fetchVistorias();
  };

  const resetFilters = () => {
    setFilters({
      codigo: '',
      status: 'todos',
      cliente: '',
      tipo: 'todos',
      imovel_id: '',
      vistoriador: '',
      pessoa: '',
      metragem_min: '',
      metragem_max: '',
      mobiliado: 'todos',
      data_inicio: '',
      data_fim: '',
    });
    setPage(1);
    fetchVistorias();
  };

  const handleExport = async () => {
    try {
      const response = await api.get('/vistorias/export', {
        params: getFilterParams(),
        responseType: 'blob',
      });
      const blob = new Blob([response.data], { type: 'text/csv' });
      const url = window.URL.createObjectURL(blob);
      const link = document.createElement('a');
      link.href = url;
      link.download = `vistorias_${new Date().toISOString().slice(0, 10)}.csv`;
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
    } catch (error) {
      console.error('Erro ao exportar vistorias:', error);
      toast.error('Erro ao exportar vistorias');
    }
  };

  const formatDate = (dateString?: string | null) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR', { dateStyle: 'short' });
  };

  const handleCreate = () => {
    setEditingVistoria({
      id: 0,
      codigo: '',
      status: 'pendente',
      cliente_nome: '',
      imovel_id: null,
      tipo: 'entrada',
      vistoriadores: [],
      pessoas: [],
      metragem: '',
      mobiliado: false,
      data_vistoria: '',
      observacoes: '',
    });
    setShowModal(true);
  };

  const handleEdit = (vistoria: Vistoria) => {
    setEditingVistoria({
      ...vistoria,
      data_vistoria: vistoria.data_vistoria 
        ? new Date(vistoria.data_vistoria).toISOString().slice(0, 16)
        : '',
    });
    setShowModal(true);
  };

  const handleSave = async () => {
    if (!editingVistoria) return;

    try {
      setIsSaving(true);
      
      const payload = {
        ...editingVistoria,
        metragem: editingVistoria.metragem ? parseFloat(editingVistoria.metragem as string) : null,
        imovel_id: editingVistoria.imovel_id || null,
      };

      if (editingVistoria.id === 0) {
        // Create new
        const response = await api.post('/vistorias', payload);
        if (response.data?.success) {
          toast.success('Vistoria criada com sucesso');
          fetchVistorias();
          setShowModal(false);
          setEditingVistoria(null);
        }
      } else {
        // Update existing
        const response = await api.put(`/vistorias/${editingVistoria.id}`, payload);
        if (response.data?.success) {
          toast.success('Vistoria atualizada com sucesso');
          fetchVistorias();
          setShowModal(false);
          setEditingVistoria(null);
        }
      }
    } catch (error: any) {
      console.error('Erro ao salvar vistoria:', error);
      toast.error(error?.response?.data?.message || 'Erro ao salvar vistoria');
    } finally {
      setIsSaving(false);
    }
  };

  const handleDeleteClick = (id: number) => {
    setDeleteDialog({ open: true, id });
  };

  const handleDeleteConfirm = async () => {
    if (!deleteDialog.id) return;

    try {
      const response = await api.delete(`/vistorias/${deleteDialog.id}`);
      if (response.data?.success) {
        toast.success('Vistoria deletada com sucesso');
        fetchVistorias();
      }
    } catch (error: any) {
      toast.error(error?.response?.data?.message || 'Erro ao deletar vistoria');
    } finally {
      setDeleteDialog({ open: false, id: null });
    }
  };

  const getStatusBadge = (status: string) => {
    const map: Record<string, string> = {
      solicitada: 'bg-amber-500/20 text-amber-300 border-amber-500/40',
      designada: 'bg-blue-500/20 text-blue-300 border-blue-500/40',
      andamento: 'bg-purple-500/20 text-purple-300 border-purple-500/40',
      concluida: 'bg-green-500/20 text-green-300 border-green-500/40',
      cancelada: 'bg-red-500/20 text-red-300 border-red-500/40',
    };
    return map[status] || 'bg-white/10 text-foreground border-white/20';
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
              <h1 className="page-title mb-2">Vistorias</h1>
              <p className="page-subtitle">Gerencie solicitações e inspeções do portfólio.</p>
            </div>
            <div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row">
              <motion.button
                whileHover={{ scale: 1.05 }}
                whileTap={{ scale: 0.95 }}
                onClick={handleCreate}
                className="w-full rounded-lg bg-gradient-to-r from-blue-500 to-blue-600 px-5 py-3 font-semibold text-white hover:from-blue-600 hover:to-blue-700 sm:w-auto flex items-center justify-center gap-2"
              >
                <Plus size={20} />
                Nova Vistoria
              </motion.button>
              <Link to="/vistorias/solicitacoes">
                <motion.button
                  whileHover={{ scale: 1.05 }}
                  whileTap={{ scale: 0.95 }}
                  className="w-full rounded-lg border border-white/20 bg-white/10 px-4 py-3 font-semibold text-foreground sm:w-auto"
                >
                  Solicitações
                </motion.button>
              </Link>
            </div>
          </div>

          <div className="glass-panel p-6 rounded-2xl mb-8">
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div className="relative">
                <Search className="absolute left-4 top-1/2 -translate-y-1/2 text-muted-foreground" size={18} />
                <input
                  type="text"
                  placeholder="Codigo"
                  value={filters.codigo}
                  onChange={(e) => handleFilterChange('codigo', e.target.value)}
                  className="w-full pl-11 pr-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                />
              </div>
              <input
                type="text"
                placeholder="Cliente"
                value={filters.cliente}
                onChange={(e) => handleFilterChange('cliente', e.target.value)}
                className="px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
              />
              <input
                type="text"
                placeholder="Imovel ID"
                value={filters.imovel_id}
                onChange={(e) => handleFilterChange('imovel_id', e.target.value)}
                className="px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
              />
            </div>

            <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mt-4">
              <select
                value={filters.status}
                onChange={(e) => handleFilterChange('status', e.target.value)}
                className="px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
              >
                <option value="todos">Status</option>
                <option value="solicitada">Solicitada</option>
                <option value="designada">Designada</option>
                <option value="andamento">Em andamento</option>
                <option value="concluida">Concluida</option>
                <option value="cancelada">Cancelada</option>
              </select>
              <select
                value={filters.tipo}
                onChange={(e) => handleFilterChange('tipo', e.target.value)}
                className="px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
              >
                <option value="todos">Tipo</option>
                <option value="entrada">Entrada</option>
                <option value="saida">Saida</option>
                <option value="periodica">Periodica</option>
              </select>
              <select
                value={filters.mobiliado}
                onChange={(e) => handleFilterChange('mobiliado', e.target.value)}
                className="px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
              >
                <option value="todos">Mobiliado</option>
                <option value="sim">Sim</option>
                <option value="nao">Nao</option>
              </select>
              <div className="flex gap-2">
                <motion.button
                  whileHover={{ scale: 1.02 }}
                  whileTap={{ scale: 0.98 }}
                  onClick={applyFilters}
                  className="flex-1 flex items-center justify-center gap-2 px-4 py-3 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 rounded-lg text-white font-semibold"
                >
                  <Filter size={18} />
                  Filtrar
                </motion.button>
                <motion.button
                  whileHover={{ scale: 1.02 }}
                  whileTap={{ scale: 0.98 }}
                  onClick={resetFilters}
                  className="px-4 py-3 bg-white/10 hover:bg-white/20 border border-white/20 rounded-lg text-foreground"
                >
                  <X size={18} />
                </motion.button>
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-5 gap-4 mt-4">
              <input
                type="text"
                placeholder="Vistoriador"
                value={filters.vistoriador}
                onChange={(e) => handleFilterChange('vistoriador', e.target.value)}
                className="px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
              />
              <input
                type="text"
                placeholder="Pessoa"
                value={filters.pessoa}
                onChange={(e) => handleFilterChange('pessoa', e.target.value)}
                className="px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
              />
              <input
                type="number"
                placeholder="Metragem min"
                value={filters.metragem_min}
                onChange={(e) => handleFilterChange('metragem_min', e.target.value)}
                className="px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
              />
              <input
                type="number"
                placeholder="Metragem max"
                value={filters.metragem_max}
                onChange={(e) => handleFilterChange('metragem_max', e.target.value)}
                className="px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
              />
              <div className="grid grid-cols-2 gap-2">
                <div className="relative">
                  <Calendar className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" size={16} />
                  <input
                    type="date"
                    value={filters.data_inicio}
                    onChange={(e) => handleFilterChange('data_inicio', e.target.value)}
                    className="w-full pl-9 pr-3 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                  />
                </div>
                <div className="relative">
                  <Calendar className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" size={16} />
                  <input
                    type="date"
                    value={filters.data_fim}
                    onChange={(e) => handleFilterChange('data_fim', e.target.value)}
                    className="w-full pl-9 pr-3 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                  />
                </div>
              </div>
            </div>
          </div>

          <div className="glass-panel p-6 rounded-2xl">
            <div className="flex items-center justify-between mb-6">
              <h2 className="text-xl font-bold text-foreground flex items-center gap-2">
                <ClipboardCheck size={22} />
                Listagem de Vistorias
              </h2>
              <div className="flex items-center gap-3">
                <span className="text-sm text-muted-foreground">
                  Pagina {page} de {lastPage}
                </span>
                <motion.button
                  whileHover={{ scale: 1.05 }}
                  whileTap={{ scale: 0.95 }}
                  onClick={handleExport}
                  className="px-4 py-2 rounded-lg bg-white/10 border border-white/20 text-foreground text-sm font-semibold"
                >
                  Exportar CSV
                </motion.button>
              </div>
            </div>

            {isLoading ? (
              <div className="flex items-center justify-center py-12">
                <Loader2 className="w-8 h-8 animate-spin text-primary" />
              </div>
            ) : vistorias.length === 0 ? (
              <div className="text-center py-12 text-muted-foreground">
                Nenhuma vistoria encontrada.
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="text-left text-muted-foreground">
                      <th className="pb-3">Codigo</th>
                      <th className="pb-3">Status</th>
                      <th className="pb-3">Cliente</th>
                      <th className="pb-3">Imovel</th>
                      <th className="pb-3">Tipo</th>
                      <th className="pb-3">Metragem</th>
                      <th className="pb-3">Mobiliado</th>
                      <th className="pb-3">Data</th>
                      <th className="pb-3 text-right">Acoes</th>
                    </tr>
                  </thead>
                  <tbody>
                    {vistorias.map((vistoria) => (
                      <tr key={vistoria.id} className="border-t border-white/10">
                        <td className="py-3 text-foreground">{vistoria.codigo || `#${vistoria.id}`}</td>
                        <td className="py-3">
                          <span className={`inline-flex items-center px-3 py-1 rounded-full border text-xs font-semibold ${getStatusBadge(vistoria.status)}`}>
                            {vistoria.status}
                          </span>
                        </td>
                        <td className="py-3 text-foreground">{vistoria.cliente_nome || '-'}</td>
                        <td className="py-3 text-foreground">{vistoria.imovel_id || '-'}</td>
                        <td className="py-3 text-foreground">{vistoria.tipo || '-'}</td>
                        <td className="py-3 text-foreground">{vistoria.metragem ? `${vistoria.metragem} m2` : '-'}</td>
                        <td className="py-3">
                          {vistoria.mobiliado ? (
                            <span className="inline-flex items-center gap-1 text-green-300">
                              <Check size={14} /> Sim
                            </span>
                          ) : (
                            <span className="inline-flex items-center gap-1 text-muted-foreground">
                              <X size={14} /> Nao
                            </span>
                          )}
                        </td>
                        <td className="py-3 text-foreground">{formatDate(vistoria.data_vistoria)}</td>
                        <td className="py-3 text-right">
                          <div className="flex items-center justify-end gap-2">
                            <motion.button
                              whileHover={{ scale: 1.05 }}
                              whileTap={{ scale: 0.95 }}
                              onClick={() => handleEdit(vistoria)}
                              className="px-3 py-1.5 rounded-lg bg-blue-500/20 border border-blue-500/30 text-blue-300 text-xs font-semibold flex items-center gap-1"
                            >
                              <Edit2 size={12} />
                              Editar
                            </motion.button>
                            <motion.button
                              whileHover={{ scale: 1.05 }}
                              whileTap={{ scale: 0.95 }}
                              onClick={() => handleDeleteClick(vistoria.id)}
                              className="px-3 py-1.5 rounded-lg bg-red-500/20 border border-red-500/30 text-red-300 text-xs font-semibold flex items-center gap-1"
                            >
                              <Trash2 size={12} />
                              Deletar
                            </motion.button>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}

            {!isLoading && vistorias.length > 0 && (
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

      {/* Create/Edit Modal */}
      {showModal && editingVistoria && (
        <div className="fixed inset-0 bg-black/80 backdrop-blur-sm flex items-center justify-center z-50 p-4">
          <motion.div
            initial={{ opacity: 0, scale: 0.9 }}
            animate={{ opacity: 1, scale: 1 }}
            className="glass-panel p-6 rounded-2xl max-w-2xl w-full max-h-[90vh] overflow-y-auto"
          >
            <div className="flex items-center justify-between mb-6">
              <h2 className="text-2xl font-bold text-foreground flex items-center gap-2">
                {editingVistoria.id === 0 ? <Plus size={24} /> : <Edit2 size={24} />}
                {editingVistoria.id === 0 ? 'Nova Vistoria' : 'Editar Vistoria'}
              </h2>
              <button
                onClick={() => {
                  setShowModal(false);
                  setEditingVistoria(null);
                }}
                className="text-muted-foreground hover:text-foreground transition-colors"
              >
                <X size={24} />
              </button>
            </div>

            <div className="space-y-4">
              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-semibold text-foreground mb-2">
                    Código
                  </label>
                  <input
                    type="text"
                    value={editingVistoria.codigo || ''}
                    onChange={(e) =>
                      setEditingVistoria({ ...editingVistoria, codigo: e.target.value })
                    }
                    className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="Auto-gerado se vazio"
                  />
                </div>

                <div>
                  <label className="block text-sm font-semibold text-foreground mb-2">
                    Status *
                  </label>
                  <select
                    value={editingVistoria.status}
                    onChange={(e) =>
                      setEditingVistoria({ ...editingVistoria, status: e.target.value })
                    }
                    className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                  >
                    <option value="pendente">Pendente</option>
                    <option value="agendada">Agendada</option>
                    <option value="concluida">Concluída</option>
                    <option value="cancelada">Cancelada</option>
                  </select>
                </div>
              </div>

              <div>
                <label className="block text-sm font-semibold text-foreground mb-2">
                  Cliente *
                </label>
                <input
                  type="text"
                  value={editingVistoria.cliente_nome || ''}
                  onChange={(e) =>
                    setEditingVistoria({ ...editingVistoria, cliente_nome: e.target.value })
                  }
                  className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                  required
                />
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-semibold text-foreground mb-2">
                    Tipo *
                  </label>
                  <select
                    value={editingVistoria.tipo || 'entrada'}
                    onChange={(e) =>
                      setEditingVistoria({ ...editingVistoria, tipo: e.target.value })
                    }
                    className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                  >
                    <option value="entrada">Entrada</option>
                    <option value="saida">Saída</option>
                    <option value="periodica">Periódica</option>
                  </select>
                </div>

                <div>
                  <label className="block text-sm font-semibold text-foreground mb-2">
                    ID do Imóvel
                  </label>
                  <input
                    type="number"
                    value={editingVistoria.imovel_id || ''}
                    onChange={(e) =>
                      setEditingVistoria({
                        ...editingVistoria,
                        imovel_id: e.target.value ? parseInt(e.target.value) : null,
                      })
                    }
                    className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                  />
                </div>
              </div>

              <div className="grid grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-semibold text-foreground mb-2">
                    Metragem (m²)
                  </label>
                  <input
                    type="number"
                    step="0.01"
                    value={editingVistoria.metragem || ''}
                    onChange={(e) =>
                      setEditingVistoria({ ...editingVistoria, metragem: e.target.value })
                    }
                    className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                  />
                </div>

                <div>
                  <label className="block text-sm font-semibold text-foreground mb-2">
                    Data da Vistoria
                  </label>
                  <input
                    type="datetime-local"
                    value={editingVistoria.data_vistoria || ''}
                    onChange={(e) =>
                      setEditingVistoria({ ...editingVistoria, data_vistoria: e.target.value })
                    }
                    className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                  />
                </div>
              </div>

              <div>
                <label className="flex items-center gap-2 cursor-pointer">
                  <input
                    type="checkbox"
                    checked={editingVistoria.mobiliado || false}
                    onChange={(e) =>
                      setEditingVistoria({ ...editingVistoria, mobiliado: e.target.checked })
                    }
                    className="w-5 h-5 rounded border-white/20 bg-white/10 text-blue-500 focus:ring-2 focus:ring-blue-500"
                  />
                  <span className="text-sm font-semibold text-foreground">Imóvel Mobiliado</span>
                </label>
              </div>

              <div>
                <label className="block text-sm font-semibold text-foreground mb-2">
                  Observações
                </label>
                <textarea
                  value={editingVistoria.observacoes || ''}
                  onChange={(e) =>
                    setEditingVistoria({ ...editingVistoria, observacoes: e.target.value })
                  }
                  rows={3}
                  className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <div className="flex gap-4 pt-4">
                <motion.button
                  whileHover={{ scale: 1.02 }}
                  whileTap={{ scale: 0.98 }}
                  onClick={handleSave}
                  disabled={isSaving || !editingVistoria.cliente_nome}
                  className="flex-1 px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 rounded-lg font-semibold text-white transition-all flex items-center justify-center gap-2 disabled:opacity-60"
                >
                  {isSaving ? (
                    <Loader2 className="w-5 h-5 animate-spin" />
                  ) : (
                    <Save size={18} />
                  )}
                  Salvar
                </motion.button>

                <motion.button
                  whileHover={{ scale: 1.02 }}
                  whileTap={{ scale: 0.98 }}
                  onClick={() => {
                    setShowModal(false);
                    setEditingVistoria(null);
                  }}
                  className="px-6 py-3 bg-white/10 hover:bg-white/20 rounded-lg font-semibold text-foreground transition-all"
                >
                  Cancelar
                </motion.button>
              </div>
            </div>
          </motion.div>
        </div>
      )}

      <AlertDialog open={deleteDialog.open} onOpenChange={(open) => setDeleteDialog(prev => ({ ...prev, open }))}>
        <AlertDialogContent className="bg-[#0f0f0f] border border-white/10">
          <AlertDialogHeader>
            <AlertDialogTitle>Excluir Vistoria</AlertDialogTitle>
            <AlertDialogDescription>
              Tem certeza que deseja deletar esta vistoria? Esta ação não pode ser desfeita.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel className="border-white/20 hover:bg-white/10">Cancelar</AlertDialogCancel>
            <AlertDialogAction onClick={handleDeleteConfirm} className="bg-red-600 hover:bg-red-700">
              Excluir
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
