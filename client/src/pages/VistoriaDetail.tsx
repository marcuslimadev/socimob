import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { motion } from 'framer-motion';
import { ClipboardCheck, Loader2, ArrowLeft, MapPin, Camera, Trash2, RefreshCw } from 'lucide-react';
import { toast } from 'sonner';
import { Link, useRoute } from 'wouter';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';

interface VistoriaImovelVm {
  id?: number | null;
  codigo?: string | null;
  titulo?: string | null;
  label?: string | null;
  endereco?: string | null;
  tipo_imovel?: string | null;
  referencia_manual?: string | null;
}

interface PessoaVm {
  id: number;
  nome: string;
  email?: string | null;
}

interface VistoriaFotoVm {
  id: number;
  comodo?: string | null;
  descricao?: string | null;
  url_signed?: string;
  destaque?: boolean;
}

interface VistoriaDetailData {
  id: number;
  codigo: string | null;
  status: string;
  cliente_nome: string | null;
  imovel_id?: number | null;
  responsavel_pessoa_id?: number | null;
  imovel_livre?: Record<string, string> | null;
  tipo: string | null;
  vistoriadores?: string[] | null;
  pessoas?: string[] | null;
  participantes_nomes?: string[];
  responsavel?: PessoaVm | null;
  metragem?: string | null;
  mobiliado?: boolean | null;
  data_vistoria?: string | null;
  observacoes?: string | null;
  imovel?: VistoriaImovelVm | null;
  created_at?: string | null;
  fotos?: VistoriaFotoVm[];
}

const tipoLabels: Record<string, string> = {
  entrada: 'Entrada',
  saida: 'Saída',
  periodica: 'Periódica / Constatação',
};

const statusLabels: Record<string, string> = {
  solicitada: 'Solicitada',
  designada: 'Designada',
  andamento: 'Em andamento',
  concluida: 'Concluída',
  cancelada: 'Cancelada',
};

const STATUS_OPTIONS = ['solicitada', 'designada', 'andamento', 'concluida', 'cancelada'] as const;

const formatImovel = (vistoria: VistoriaDetailData) => {
  const i = vistoria.imovel;
  if (i?.label || i?.endereco || i?.titulo)
    return {
      principal: i.label || i.titulo || 'Imóvel vinculado',
      linhaExtra: i.referencia_manual || null,
      endereco: i.endereco || null,
      selo: !i?.id ? 'Descrição própria da vistoria (sem cadastro de imóvel)' : undefined,
      mapsQuery: [i.endereco, i.referencia_manual].filter(Boolean).join(' — ') || null,
    };
  if (vistoria.imovel_id) return { principal: `Imóvel #${vistoria.imovel_id}`, linhaExtra: null, endereco: null, selo: undefined, mapsQuery: null };
  return { principal: 'Não informado', linhaExtra: null, endereco: null, selo: undefined, mapsQuery: null };
};

export default function VistoriaDetail() {
  const [, params] = useRoute<{ id: string }>('/vistorias/:id');
  const fileRef = useRef<HTMLInputElement>(null);
  const [vistoria, setVistoria] = useState<VistoriaDetailData | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const [uploading, setUploading] = useState(false);
  const [patchingStatus, setPatchingStatus] = useState(false);
  const [deletingPhotoId, setDeletingPhotoId] = useState<number | null>(null);

  const fetchVistoria = useCallback(async (id: string) => {
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
  }, []);

  useEffect(() => {
    if (params?.id) {
      fetchVistoria(params.id);
    }
  }, [params?.id, fetchVistoria]);

  const formatDateTime = (dateString?: string | null) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleString('pt-BR', { dateStyle: 'short', timeStyle: 'short' });
  };

  const imovelBloco = vistoria ? formatImovel(vistoria) : null;

  const mapsHref = useMemo(() => {
    const q =
      imovelBloco?.mapsQuery?.trim() ||
      imovelBloco?.endereco?.trim() ||
      (!vistoria?.imovel_id && vistoria?.imovel_livre
        ? [vistoria.imovel_livre.logradouro, vistoria.imovel_livre.bairro, vistoria.imovel_livre.cidade, vistoria.imovel_livre.estado]
            .filter(Boolean)
            .join(', ')
        : '');
    if (!q) return null;
    return `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(q)}`;
  }, [imovelBloco, vistoria]);

  const patchStatus = async (next: string) => {
    if (!vistoria || vistoria.status === next) return;
    setPatchingStatus(true);
    try {
      await api.put(`/vistorias/${vistoria.id}`, { status: next });
      setVistoria((prev) => (prev ? { ...prev, status: next } : null));
      toast.success('Status atualizado.');
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Não foi possível alterar o status.');
    } finally {
      setPatchingStatus(false);
    }
  };

  const onPickPhotos = async (files: FileList | null) => {
    if (!params?.id || !files?.length) return;
    setUploading(true);
    try {
      for (let i = 0; i < files.length; i++) {
        const file = files[i];
        const fd = new FormData();
        fd.append('foto', file);
        await api.post(`/vistorias/${params.id}/fotos`, fd);
      }
      toast.success(files.length > 1 ? 'Fotos enviadas.' : 'Foto enviada.');
      await fetchVistoria(params.id);
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Erro ao enviar foto(s).');
    } finally {
      setUploading(false);
      if (fileRef.current) fileRef.current.value = '';
    }
  };

  const deleteFoto = async (fotoId: number) => {
    if (!params?.id) return;
    setDeletingPhotoId(fotoId);
    try {
      await api.delete(`/vistorias/${params.id}/fotos/${fotoId}`);
      toast.success('Foto removida.');
      await fetchVistoria(params.id);
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Erro ao remover foto.');
    } finally {
      setDeletingPhotoId(null);
    }
  };

  const fotos = vistoria?.fotos || [];

  return (
    <div className="flex">
      <Sidebar />

      <div className="page-shell pb-[max(1.5rem,env(safe-area-inset-bottom,0px))]">
        <motion.div initial={{ opacity: 0, y: 16 }} animate={{ opacity: 1, y: 0 }} className="max-w-4xl mx-auto px-1 sm:px-0">
          <div className="page-header mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div className="min-w-0">
              <h1 className="page-title mb-2">Detalhe da Vistoria</h1>
              <p className="page-subtitle">Informações da visita técnica, fotos de campo e atualização rápida de status.</p>
            </div>
            <div className="flex flex-wrap gap-2">
              {params?.id ? (
                <Link to={`/vistorias/${params.id}/execucao`}>
                  <button
                    type="button"
                    className="flex min-h-11 touch-manipulation items-center justify-center gap-2 rounded-lg border border-emerald-500/30 bg-emerald-500/10 px-4 py-2.5 text-base text-emerald-200 sm:min-h-10 sm:text-sm"
                  >
                    Iniciar vistoria
                  </button>
                </Link>
              ) : null}
              {params?.id ? (
                <button
                  type="button"
                  onClick={() => fetchVistoria(params.id)}
                  className="flex min-h-11 touch-manipulation items-center justify-center gap-2 rounded-lg border border-white/20 bg-white/10 px-4 py-2.5 text-base text-foreground sm:min-h-10 sm:text-sm"
                  disabled={isLoading}
                >
                  <RefreshCw size={16} aria-hidden className={isLoading ? 'animate-spin' : ''} />
                  Atualizar
                </button>
              ) : null}
              <Link to="/vistorias">
                <motion.button
                  whileHover={{ scale: 1.05 }}
                  whileTap={{ scale: 0.95 }}
                  type="button"
                  className="flex min-h-11 w-full touch-manipulation items-center justify-center gap-2 rounded-lg border border-white/20 bg-white/10 px-4 py-2.5 text-base text-foreground sm:min-h-10 sm:w-auto sm:text-sm"
                >
                  <ArrowLeft size={16} aria-hidden />
                  Voltar
                </motion.button>
              </Link>
            </div>
          </div>

          <div className="glass-panel rounded-2xl p-4 sm:p-6">
            {isLoading ? (
              <div className="flex items-center justify-center py-12">
                <Loader2 className="w-8 h-8 animate-spin text-primary" />
              </div>
            ) : !vistoria ? (
              <div className="text-center py-12 text-muted-foreground">Vistoria não encontrada.</div>
            ) : (
              <div className="space-y-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                  <div className="flex items-center gap-3">
                    <div className="w-12 h-12 rounded-xl bg-gradient-to-br from-amber-500 to-orange-600 flex items-center justify-center text-white">
                      <ClipboardCheck size={24} />
                    </div>
                    <div>
                      <p className="text-lg font-semibold text-foreground">{vistoria.codigo || `#${vistoria.id}`}</p>
                      <p className="text-sm text-muted-foreground">{statusLabels[vistoria.status] || vistoria.status}</p>
                    </div>
                  </div>
                  {mapsHref ? (
                    <a
                      href={mapsHref}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="inline-flex min-h-11 touch-manipulation items-center justify-center gap-2 rounded-xl border border-emerald-500/35 bg-emerald-500/10 px-4 py-2.5 text-base font-medium text-emerald-200 sm:min-h-10 sm:text-sm"
                    >
                      <MapPin size={18} aria-hidden />
                      Abrir no mapa
                    </a>
                  ) : null}
                </div>

                <section>
                  <p className="mb-2 text-xs uppercase tracking-wide text-muted-foreground">Fluxo rápido</p>
                  <div className="flex flex-wrap gap-2">
                    {STATUS_OPTIONS.map((s) => (
                      <button
                        key={s}
                        type="button"
                        disabled={patchingStatus || vistoria.status === s}
                        onClick={() => patchStatus(s)}
                        className={`min-h-11 touch-manipulation rounded-full border px-4 py-2 text-sm font-medium transition disabled:opacity-40 sm:min-h-9 ${
                          vistoria.status === s ? 'border-primary bg-primary/20 text-primary' : 'border-white/15 bg-white/5 text-foreground hover:bg-white/10'
                        }`}
                      >
                        {statusLabels[s] || s}
                      </button>
                    ))}
                  </div>
                </section>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Cliente</p>
                    <p className="text-base text-foreground">{vistoria.cliente_nome || '-'}</p>
                  </div>
                  <div>
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Responsável</p>
                    <p className="text-base text-foreground">{vistoria.responsavel?.nome || '-'}</p>
                  </div>
                  <div className="md:col-span-2">
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Imóvel / local</p>
                    <p className="text-base text-foreground">{imovelBloco?.principal}</p>
                    {imovelBloco?.selo ? (
                      <p className="mt-1 rounded-lg border border-cyan-500/25 bg-cyan-500/10 px-3 py-1.5 text-xs text-cyan-100">{imovelBloco.selo}</p>
                    ) : null}
                    {imovelBloco?.endereco ? <p className="mt-1 text-sm text-muted-foreground">{imovelBloco.endereco}</p> : null}
                    {imovelBloco?.linhaExtra ? <p className="mt-2 text-sm text-muted-foreground leading-relaxed">{imovelBloco.linhaExtra}</p> : null}
                  </div>
                  <div>
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Tipo</p>
                    <p className="text-base text-foreground">{vistoria.tipo ? tipoLabels[vistoria.tipo] || vistoria.tipo : '-'}</p>
                  </div>
                  <div>
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Metragem</p>
                    <p className="text-base text-foreground">{vistoria.metragem ? `${vistoria.metragem} m²` : '-'}</p>
                  </div>
                  <div>
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Mobiliado</p>
                    <p className="text-base text-foreground">{vistoria.mobiliado ? 'Sim' : 'Não'}</p>
                  </div>
                  <div>
                    <p className="text-xs uppercase tracking-wide text-muted-foreground">Data</p>
                    <p className="text-base text-foreground">{formatDateTime(vistoria.data_vistoria)}</p>
                  </div>
                </div>

                <div>
                  <p className="text-xs uppercase tracking-wide text-muted-foreground">Vistoriadores</p>
                  <p className="text-base text-foreground">{vistoria.vistoriadores?.length ? vistoria.vistoriadores.join(', ') : '-'}</p>
                </div>

                <div>
                  <p className="text-xs uppercase tracking-wide text-muted-foreground">Pessoas / participantes</p>
                  <p className="text-base text-foreground">{vistoria.participantes_nomes?.length ? vistoria.participantes_nomes.join(', ') : vistoria.pessoas?.length ? vistoria.pessoas.join(', ') : '-'}</p>
                </div>

                <div>
                  <p className="text-xs uppercase tracking-wide text-muted-foreground">Observações</p>
                  <p className="text-base text-foreground">{vistoria.observacoes || '-'}</p>
                </div>

                <div>
                  <p className="text-xs uppercase tracking-wide text-muted-foreground">Criado em</p>
                  <p className="text-base text-foreground">{formatDateTime(vistoria.created_at)}</p>
                </div>

                <section className="rounded-xl border border-white/10 bg-black/20 p-4">
                  <div className="mb-3 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                      <p className="text-sm font-semibold text-foreground">Fotos da vistoria</p>
                      <p className="text-xs text-muted-foreground">Envio direto do celular ou notebook ( JPG, PNG ou WebP, até ~10&nbsp;MB).</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                      <input ref={fileRef} type="file" accept="image/jpeg,image/png,image/webp" multiple className="hidden" onChange={(e) => onPickPhotos(e.target.files)} />
                      <button
                        type="button"
                        disabled={uploading}
                        onClick={() => fileRef.current?.click()}
                        className="inline-flex min-h-11 touch-manipulation items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-base font-semibold text-primary-foreground disabled:opacity-50 sm:min-h-10 sm:text-sm"
                      >
                        {uploading ? <Loader2 className="h-5 w-5 animate-spin" /> : <Camera size={18} />}
                        Adicionar fotos
                      </button>
                    </div>
                  </div>
                  {fotos.length === 0 ? (
                    <p className="py-8 text-center text-sm text-muted-foreground">Nenhuma foto ainda.</p>
                  ) : (
                    <ul className="grid gap-4 sm:grid-cols-2">
                      {fotos.map((f) => (
                        <li key={f.id} className="overflow-hidden rounded-xl border border-white/10 bg-white/5">
                          {f.url_signed ? (
                            <a href={f.url_signed} target="_blank" rel="noopener noreferrer" className="block aspect-[4/3] bg-black/30">
                              <img src={f.url_signed} alt={f.descricao || f.comodo || 'Foto da vistoria'} className="h-full w-full object-cover" loading="lazy" />
                            </a>
                          ) : (
                            <div className="flex aspect-[4/3] items-center justify-center text-xs text-muted-foreground">Sem URL da imagem</div>
                          )}
                          <div className="flex items-start justify-between gap-2 p-3">
                            <div className="min-w-0 text-sm">
                              {f.comodo ? <p className="font-medium text-foreground">{f.comodo}</p> : null}
                              {f.descricao ? <p className="text-muted-foreground">{f.descricao}</p> : null}
                              {!f.comodo && !f.descricao ? <span className="text-muted-foreground">Foto #{f.id}</span> : null}
                            </div>
                            <button
                              type="button"
                              aria-label="Excluir foto"
                              disabled={deletingPhotoId === f.id}
                              onClick={() => deleteFoto(f.id)}
                              className="touch-manipulation shrink-0 rounded-lg border border-red-500/30 bg-red-500/10 p-2 text-red-300 hover:bg-red-500/20 disabled:opacity-50"
                            >
                              {deletingPhotoId === f.id ? <Loader2 className="h-4 w-4 animate-spin" /> : <Trash2 className="h-4 w-4" />}
                            </button>
                          </div>
                        </li>
                      ))}
                    </ul>
                  )}
                </section>
              </div>
            )}
          </div>
        </motion.div>
      </div>
    </div>
  );
}
