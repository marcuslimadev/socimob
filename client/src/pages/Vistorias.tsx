import { useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import { ClipboardCheck, Loader2, Check, X } from 'lucide-react';
import { toast } from 'sonner';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';

interface Vistoria {
  id: number;
  codigo: string | null;
  status: string;
  cliente_nome: string | null;
  imovel_id: number | null;
  tipo: string | null;
  metragem?: string | null;
  mobiliado?: boolean | null;
  data_vistoria?: string | null;
}

export default function Vistorias() {
  const [vistorias, setVistorias] = useState<Vistoria[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);

  useEffect(() => {
    fetchVistorias();
  }, [page]);

  const fetchVistorias = async () => {
    try {
      setIsLoading(true);
      const response = await api.get('/vistorias', {
        params: { page, per_page: 10 },
      });
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

  const formatDate = (dateString?: string | null) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR', { dateStyle: 'short' });
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

      <div className="flex-1 md:ml-80 min-h-screen p-4 md:p-8">
        <motion.div
          initial={{ opacity: 0, y: 16 }}
          animate={{ opacity: 1, y: 0 }}
          className="max-w-7xl mx-auto"
        >
          <div className="mb-8">
            <h1 className="text-4xl font-bold gradient-text mb-2">Vistorias</h1>
            <p className="text-muted-foreground">Gerencie solicitacoes e inspecoes do portifolio.</p>
          </div>

          <div className="glass-panel p-6 rounded-2xl">
            <div className="flex items-center justify-between mb-6">
              <h2 className="text-xl font-bold text-foreground flex items-center gap-2">
                <ClipboardCheck size={22} />
                Listagem de Vistorias
              </h2>
              <div className="text-sm text-muted-foreground">
                Pagina {page} de {lastPage}
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
    </div>
  );
}
