import { useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import { ClipboardCheck, Loader2, ArrowLeft } from 'lucide-react';
import { toast } from 'sonner';
import { Link, useRoute } from 'wouter';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';

interface VistoriaDetailData {
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
  created_at?: string | null;
}

export default function VistoriaDetail() {
  const [, params] = useRoute('/vistorias/:id');
  const [vistoria, setVistoria] = useState<VistoriaDetailData | null>(null);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    if (params?.id) {
      fetchVistoria(params.id);
    }
  }, [params?.id]);

  const fetchVistoria = async (id: string) => {
    try {
      setIsLoading(true);
      const response = await api.get(`/vistorias/${id}`);
      setVistoria(response.data);
    } catch (error) {
      console.error('Erro ao carregar vistoria:', error);
      toast.error('Erro ao carregar vistoria');
    } finally {
      setIsLoading(false);
    }
  };

  const formatDateTime = (dateString?: string | null) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
  };

  return (
    <div className="flex">
      <Sidebar />

      <div className="flex-1 md:ml-80 min-h-screen p-4 md:p-8">
        <motion.div
          initial={{ opacity: 0, y: 16 }}
          animate={{ opacity: 1, y: 0 }}
          className="max-w-4xl mx-auto"
        >
          <div className="mb-6 flex items-center justify-between">
            <div>
              <h1 className="text-4xl font-bold gradient-text mb-2">Detalhe da Vistoria</h1>
              <p className="text-muted-foreground">Informacoes completas da solicitacao.</p>
            </div>
            <Link to="/vistorias">
              <motion.button
                whileHover={{ scale: 1.05 }}
                whileTap={{ scale: 0.95 }}
                className="flex items-center gap-2 px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground"
              >
                <ArrowLeft size={16} />
                Voltar
              </motion.button>
            </Link>
          </div>

          <div className="glass-panel p-6 rounded-2xl">
            {isLoading ? (
              <div className="flex items-center justify-center py-12">
                <Loader2 className="w-8 h-8 animate-spin text-primary" />
              </div>
            ) : !vistoria ? (
              <div className="text-center py-12 text-muted-foreground">Vistoria nao encontrada.</div>
            ) : (
              <div className="space-y-6">
                <div className="flex items-center gap-3">
                  <div className="w-12 h-12 rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center text-white">
                    <ClipboardCheck size={24} />
                  </div>
                  <div>
                    <p className="text-lg font-semibold text-foreground">
                      {vistoria.codigo || `#${vistoria.id}`}
                    </p>
                    <p className="text-sm text-muted-foreground">{vistoria.status}</p>
                  </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Cliente</p>
                    <p className="text-base text-foreground">{vistoria.cliente_nome || '-'}</p>
                  </div>
                  <div>
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Imovel</p>
                    <p className="text-base text-foreground">{vistoria.imovel_id || '-'}</p>
                  </div>
                  <div>
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Tipo</p>
                    <p className="text-base text-foreground">{vistoria.tipo || '-'}</p>
                  </div>
                  <div>
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Metragem</p>
                    <p className="text-base text-foreground">
                      {vistoria.metragem ? `${vistoria.metragem} m2` : '-'}
                    </p>
                  </div>
                  <div>
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Mobiliado</p>
                    <p className="text-base text-foreground">{vistoria.mobiliado ? 'Sim' : 'Nao'}</p>
                  </div>
                  <div>
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Data</p>
                    <p className="text-base text-foreground">{formatDateTime(vistoria.data_vistoria)}</p>
                  </div>
                </div>

                <div>
                  <p className="text-xs uppercase tracking-wide text-muted-foreground">Vistoriadores</p>
                  <p className="text-base text-foreground">
                    {vistoria.vistoriadores?.length ? vistoria.vistoriadores.join(', ') : '-'}
                  </p>
                </div>

                <div>
                  <p className="text-xs uppercase tracking-wide text-muted-foreground">Pessoas</p>
                  <p className="text-base text-foreground">
                    {vistoria.pessoas?.length ? vistoria.pessoas.join(', ') : '-'}
                  </p>
                </div>

                <div>
                  <p className="text-xs uppercase tracking-wide text-muted-foreground">Observacoes</p>
                  <p className="text-base text-foreground">{vistoria.observacoes || '-'}</p>
                </div>

                <div>
                  <p className="text-xs uppercase tracking-wide text-muted-foreground">Criado em</p>
                  <p className="text-base text-foreground">{formatDateTime(vistoria.created_at)}</p>
                </div>
              </div>
            )}
          </div>
        </motion.div>
      </div>
    </div>
  );
}
