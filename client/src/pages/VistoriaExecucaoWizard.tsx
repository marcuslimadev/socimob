import { useEffect, useMemo, useRef, useState } from 'react';
import { Link, useRoute } from 'wouter';
import { Check, ChevronRight, Loader2 } from 'lucide-react';
import { toast } from 'sonner';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';

type Foto = { id: number; url_signed?: string; descricao?: string | null; mime_type?: string | null };
type Comentario = { id: number; comentario: string; autor_nome?: string | null; created_at?: string };
type Vistoria = { id: number; codigo?: string | null; status: string; fotos?: Foto[]; comentarios?: Comentario[] };

const steps = ['Checklist inicial', 'Mídias (fotos/vídeos)', 'Comentários técnicos', 'Fechamento'];

export default function VistoriaExecucaoWizard() {
  const [, params] = useRoute<{ id: string }>('/vistorias/:id/execucao');
  const fileRef = useRef<HTMLInputElement>(null);
  const [step, setStep] = useState(0);
  const [loading, setLoading] = useState(true);
  const [savingMedia, setSavingMedia] = useState(false);
  const [savingComment, setSavingComment] = useState(false);
  const [vistoria, setVistoria] = useState<Vistoria | null>(null);
  const [comentario, setComentario] = useState('');
  const [comodo, setComodo] = useState('');
  const [descricao, setDescricao] = useState('');

  const id = params?.id;
  const refresh = async () => {
    if (!id) return;
    setLoading(true);
    try {
      const { data } = await api.get(`/vistorias/${id}`);
      setVistoria(data);
    } catch {
      toast.error('Erro ao carregar vistoria.');
    } finally {
      setLoading(false);
    }
  };
  useEffect(() => { refresh(); }, [id]);

  const mediaResumo = useMemo(() => {
    const fotos = vistoria?.fotos || [];
    return {
      total: fotos.length,
      videos: fotos.filter((f) => String(f.mime_type || '').startsWith('video/')).length,
      imagens: fotos.filter((f) => String(f.mime_type || '').startsWith('image/')).length,
    };
  }, [vistoria]);

  const upload = async (files: FileList | null) => {
    if (!id || !files?.length) return;
    setSavingMedia(true);
    try {
      for (let i = 0; i < files.length; i++) {
        const fd = new FormData();
        fd.append('arquivo', files[i]);
        fd.append('comodo', comodo);
        fd.append('descricao', descricao);
        fd.append('legenda', descricao);
        await api.post(`/vistorias/${id}/fotos`, fd);
      }
      toast.success('Mídias anexadas.');
      setDescricao('');
      await refresh();
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Erro ao anexar mídia.');
    } finally {
      setSavingMedia(false);
      if (fileRef.current) fileRef.current.value = '';
    }
  };

  const enviarComentario = async () => {
    if (!id || !comentario.trim()) return;
    setSavingComment(true);
    try {
      await api.post(`/vistorias/${id}/comentarios`, { comentario });
      setComentario('');
      toast.success('Comentário registrado.');
      await refresh();
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Erro ao salvar comentário.');
    } finally {
      setSavingComment(false);
    }
  };

  const concluir = async () => {
    if (!id) return;
    try {
      await api.put(`/vistorias/${id}`, { status: 'concluida' });
      toast.success('Vistoria concluída.');
      await refresh();
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Erro ao concluir vistoria.');
    }
  };

  return (
    <div className="flex">
      <Sidebar />
      <div className="page-shell">
        <div className="max-w-5xl mx-auto space-y-6">
          <div className="page-header">
            <div>
              <h1 className="page-title">Execução de Vistoria</h1>
              <p className="page-subtitle">Fluxo operacional para vistoriador: evidências, comentários e fechamento.</p>
            </div>
            <Link to={id ? `/vistorias/${id}` : '/vistorias'} className="rounded-xl border border-white/15 bg-white/5 px-4 py-2 text-sm">Voltar</Link>
          </div>

          <div className="glass-panel rounded-2xl p-4 flex flex-wrap items-center gap-2 text-xs sm:text-sm">
            {steps.map((name, idx) => (
              <div key={name} className="flex items-center gap-2">
                <span className={`inline-flex h-7 w-7 items-center justify-center rounded-full border ${idx <= step ? 'border-emerald-400 bg-emerald-500/20 text-emerald-200' : 'border-white/15 text-muted-foreground'}`}>
                  {idx < step ? <Check size={14} /> : idx + 1}
                </span>
                <span className={idx === step ? 'text-foreground font-semibold' : 'text-muted-foreground'}>{name}</span>
                {idx < steps.length - 1 ? <ChevronRight size={16} className="text-muted-foreground" /> : null}
              </div>
            ))}
          </div>

          {loading ? <div className="glass-panel rounded-2xl p-10 flex justify-center"><Loader2 className="animate-spin" /></div> : (
            <div className="glass-panel rounded-2xl p-5 space-y-4">
              <p className="text-sm text-muted-foreground">Vistoria: <strong className="text-foreground">{vistoria?.codigo || `#${vistoria?.id}`}</strong> · status atual: <strong className="text-foreground">{vistoria?.status}</strong></p>

              {step === 0 && (
                <div className="space-y-2 text-sm">
                  <p>1) Confirme endereço, acesso e responsáveis.</p>
                  <p>2) Defina os cômodos/áreas que serão percorridos.</p>
                  <p>3) Em seguida avance para anexar evidências de campo.</p>
                </div>
              )}

              {step === 1 && (
                <div className="space-y-3">
                  <div className="grid md:grid-cols-2 gap-2">
                    <input value={comodo} onChange={(e) => setComodo(e.target.value)} placeholder="Cômodo / ambiente" className="rounded-xl border border-white/10 bg-white/5 px-3 py-2.5" />
                    <input value={descricao} onChange={(e) => setDescricao(e.target.value)} placeholder="Descrição da evidência (foto ou vídeo)" className="rounded-xl border border-white/10 bg-white/5 px-3 py-2.5" />
                  </div>
                  <input ref={fileRef} type="file" accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/webm" multiple className="hidden" onChange={(e) => upload(e.target.files)} />
                  <button onClick={() => fileRef.current?.click()} disabled={savingMedia} className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground">{savingMedia ? 'Enviando...' : 'Anexar fotos/vídeos'}</button>
                  <p className="text-xs text-muted-foreground">Resumo atual: {mediaResumo.total} mídia(s) · {mediaResumo.imagens} foto(s) · {mediaResumo.videos} vídeo(s).</p>
                </div>
              )}

              {step === 2 && (
                <div className="space-y-3">
                  <textarea value={comentario} onChange={(e) => setComentario(e.target.value)} rows={4} placeholder="Comentário técnico (estado do imóvel, inconformidades, observações)." className="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2.5" />
                  <button onClick={enviarComentario} disabled={savingComment} className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground">{savingComment ? 'Salvando...' : 'Registrar comentário'}</button>
                  <div className="space-y-2">
                    {(vistoria?.comentarios || []).slice(0, 5).map((c) => (
                      <div key={c.id} className="rounded-lg border border-white/10 bg-black/10 p-2 text-sm">
                        <p>{c.comentario}</p>
                        <p className="text-xs text-muted-foreground mt-1">{c.autor_nome || 'Equipe'} · {c.created_at ? new Date(c.created_at).toLocaleString('pt-BR') : ''}</p>
                      </div>
                    ))}
                  </div>
                </div>
              )}

              {step === 3 && (
                <div className="space-y-3 text-sm">
                  <p>Checklist final:</p>
                  <p>- Evidências anexadas: <strong>{mediaResumo.total}</strong></p>
                  <p>- Comentários registrados: <strong>{(vistoria?.comentarios || []).length}</strong></p>
                  <p className="text-muted-foreground">Se estiver tudo ok, conclua a vistoria para encerrar o fluxo operacional.</p>
                  <button onClick={concluir} className="rounded-xl bg-emerald-600 px-4 py-2 font-semibold text-white">Concluir vistoria</button>
                </div>
              )}

              <div className="flex justify-between pt-2">
                <button onClick={() => setStep((s) => Math.max(0, s - 1))} disabled={step === 0} className="rounded-xl border border-white/10 px-4 py-2 text-sm disabled:opacity-40">Anterior</button>
                <button onClick={() => setStep((s) => Math.min(steps.length - 1, s + 1))} disabled={step === steps.length - 1} className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground disabled:opacity-40">Próximo</button>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

