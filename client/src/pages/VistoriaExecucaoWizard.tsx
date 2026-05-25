import { useEffect, useMemo, useRef, useState, type PointerEvent } from 'react';
import { Link, useRoute } from 'wouter';
import { Camera, Check, ChevronRight, FileText, Loader2, PenLine, Plus, Sparkles, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';

type Foto = { id: number; url_signed?: string; descricao?: string | null; mime_type?: string | null; comodo?: string | null };
type Comentario = { id: number; comentario: string; autor_nome?: string | null; created_at?: string };
type Midia = { id: number; url?: string; mime_type?: string | null; legenda?: string | null };
type Item = { id: number; nome: string; estado?: string | null; observacoes?: string | null; possui_inconformidade?: boolean };
type Inconformidade = { id: number; descricao: string; severidade?: string | null; status?: string | null };
type Ambiente = { id: number; nome: string; estado_geral?: string | null; observacoes?: string | null; itens?: Item[]; midias?: Midia[]; inconformidades?: Inconformidade[] };
type Parte = { id: number; nome: string; documento?: string | null; funcao?: string | null; assinou?: boolean; data_assinatura?: string | null };
type Vistoria = { id: number; codigo?: string | null; status: string; fotos?: Foto[]; comentarios?: Comentario[]; ambientes?: Ambiente[]; midias?: Midia[]; partes?: Parte[] };

const steps = ['Início', 'Ambientes e evidências', 'Comentários', 'Fechamento'];
const ambientesRapidos = ['Sala', 'Cozinha', 'Banho social', 'Quarto 1', 'Quarto 2', 'Área de serviço', 'Garagem'];
const itensRapidos = ['Porta', 'Piso', 'Parede', 'Pintura', 'Janela', 'Tomadas', 'Luminária', 'Armário', 'Registro', 'Fechadura'];

export default function VistoriaExecucaoWizard() {
  const [, params] = useRoute<{ id: string }>('/vistorias/:id/execucao');
  const fileRef = useRef<HTMLInputElement>(null);
  const assinaturaCanvasRef = useRef<HTMLCanvasElement>(null);
  const desenhandoRef = useRef(false);
  const [step, setStep] = useState(0);
  const [loading, setLoading] = useState(true);
  const [savingMedia, setSavingMedia] = useState(false);
  const [savingComment, setSavingComment] = useState(false);
  const [gerandoLaudo, setGerandoLaudo] = useState(false);
  const [pdfUrl, setPdfUrl] = useState<string | null>(null);
  const [vistoria, setVistoria] = useState<Vistoria | null>(null);
  const [comentario, setComentario] = useState('');
  const [comodo, setComodo] = useState('');
  const [descricao, setDescricao] = useState('');
  const [ambienteNome, setAmbienteNome] = useState('');
  const [selectedAmbiente, setSelectedAmbiente] = useState('');
  const [itemNome, setItemNome] = useState('');
  const [itemEstado, setItemEstado] = useState('bom');
  const [deletingItemId, setDeletingItemId] = useState<number | null>(null);
  const [inconformidade, setInconformidade] = useState('');
  const [iniciando, setIniciando] = useState(false);
  const [assinando, setAssinando] = useState(false);
  const [parteAssinaturaId, setParteAssinaturaId] = useState('');
  const [checkChegada, setCheckChegada] = useState(false);
  const [checkAcesso, setCheckAcesso] = useState(false);
  const [checkEscopo, setCheckEscopo] = useState(false);

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
  const ambientes = vistoria?.ambientes || [];
  const partes = vistoria?.partes || [];
  const ambienteSelecionado = ambientes.find((ambiente) => String(ambiente.id) === selectedAmbiente) || null;
  const fotosDoAmbienteSelecionado = ambienteSelecionado
    ? (vistoria?.fotos || []).filter((foto) => (foto.comodo || '').trim().toLowerCase() === ambienteSelecionado.nome.trim().toLowerCase()).length
    : 0;
  const midias = [...(vistoria?.midias || []), ...(vistoria?.fotos || []).map((f) => ({ id: f.id, url: f.url_signed, mime_type: f.mime_type, legenda: f.descricao }))];
  const mediaResumoNovo = {
    total: midias.length,
    videos: midias.filter((f) => String(f.mime_type || '').startsWith('video/')).length,
    imagens: midias.filter((f) => String(f.mime_type || '').startsWith('image/')).length,
  };
  const started = ['andamento', 'em_andamento', 'concluida', 'finalizada'].includes(vistoria?.status || '');
  const canAdvance =
    step === 0
      ? checkChegada && checkAcesso && checkEscopo
      : step === 1
        ? ambientes.length > 0 || mediaResumoNovo.total > 0
        : step === 2
          ? (vistoria?.comentarios || []).length > 0
          : true;
  const isFinalizada = ['concluida', 'finalizada'].includes(vistoria?.status || '');
  const parteAssinatura = partes.find((parte) => String(parte.id) === parteAssinaturaId) || partes.find((parte) => !parte.assinou) || partes[0] || null;

  const upload = async (files: FileList | null) => {
    if (!id || !files?.length) return;
    if (!selectedAmbiente) {
      toast.error('Selecione o ambiente antes de anexar fotos ou vídeos.');
      return;
    }
    setSavingMedia(true);
    try {
      for (let i = 0; i < files.length; i++) {
        const fd = new FormData();
        fd.append('arquivo', files[i]);
        if (selectedAmbiente) fd.append('ambiente_id', selectedAmbiente);
        fd.append('comodo', comodo);
        fd.append('descricao', descricao);
        fd.append('legenda', descricao);
        await api.post(`/vistorias/${id}/midias`, fd);
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

  const enviarComentario = async (texto = comentario) => {
    if (!id || !texto.trim()) return;
    setSavingComment(true);
    try {
      await api.post(`/vistorias/${id}/comentarios`, { comentario: texto.trim() });
      setComentario('');
      await refresh();
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Erro ao salvar comentário.');
    } finally {
      setSavingComment(false);
    }
  };

  useEffect(() => {
    const texto = comentario.trim();
    if (step !== 2 || texto.length < 3 || savingComment) return;
    const timer = window.setTimeout(() => {
      enviarComentario(texto);
    }, 1200);
    return () => window.clearTimeout(timer);
  }, [comentario, step, savingComment]);

  useEffect(() => {
    return () => {
      if (pdfUrl) window.URL.revokeObjectURL(pdfUrl);
    };
  }, [pdfUrl]);

  const abrirPdf = async () => {
    if (!id) return;
    const response = await api.get(`/vistorias/${id}/download-pdf`, { responseType: 'blob' });
    const blob = new Blob([response.data], { type: response.headers?.['content-type'] || 'application/pdf' });
    const url = window.URL.createObjectURL(blob);
    setPdfUrl((previous) => {
      if (previous) window.URL.revokeObjectURL(previous);
      return url;
    });
  };

  const gerarAbrirLaudo = async () => {
    if (!id) return;
    setGerandoLaudo(true);
    try {
      if (!isFinalizada) {
        await api.post(`/vistorias/${id}/finalizar`);
      }
      await api.post(`/vistorias/${id}/gerar-pdf`);
      toast.success('Laudo gerado.');
      await refresh();
      await abrirPdf();
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Erro ao gerar laudo PDF.');
    } finally {
      setGerandoLaudo(false);
    }
  };

  const iniciarVistoria = async () => {
    if (!id || started) return;
    setIniciando(true);
    try {
      await api.post(`/vistorias/${id}/iniciar`);
      toast.success('Vistoria iniciada e registrada.');
      await refresh();
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Não foi possível iniciar a vistoria.');
    } finally {
      setIniciando(false);
    }
  };

  const adicionarAmbiente = async () => {
    await criarAmbiente(ambienteNome);
  };

  const criarAmbiente = async (nomeAmbiente: string) => {
    if (!id || !nomeAmbiente.trim()) return;
    const existente = ambientes.find((ambiente) => ambiente.nome.trim().toLowerCase() === nomeAmbiente.trim().toLowerCase());
    if (existente) {
      selecionarAmbiente(existente);
      setAmbienteNome('');
      return;
    }
    try {
      const nome = nomeAmbiente.trim();
      const { data } = await api.post(`/vistorias/${id}/ambientes`, { nome, estado_geral: 'bom' });
      const novo = data?.item || data;
      if (novo?.id) {
        setSelectedAmbiente(String(novo.id));
        setComodo(novo.nome || nome);
      }
      setAmbienteNome('');
      toast.success('Ambiente adicionado.');
      await refresh();
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Erro ao adicionar ambiente.');
    }
  };

  const adicionarItem = async () => {
    await criarItem(itemNome);
  };

  const criarItem = async (nomeItem: string) => {
    if (!selectedAmbiente) {
      toast.error('Selecione um ambiente antes de adicionar item.');
      return;
    }
    if (!id || !nomeItem.trim()) return;
    try {
      await api.post(`/vistorias/${id}/ambientes/${selectedAmbiente}/itens`, { nome: nomeItem.trim(), estado: itemEstado });
      setItemNome('');
      toast.success('Item adicionado.');
      await refresh();
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Erro ao adicionar item.');
    }
  };

  const excluirItem = async (item: Item) => {
    if (!id) return;
    setDeletingItemId(item.id);
    try {
      await api.delete(`/vistorias/${id}/itens/${item.id}`);
      toast.success('Item excluído.');
      await refresh();
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Erro ao excluir item.');
    } finally {
      setDeletingItemId(null);
    }
  };

  const registrarInconformidade = async () => {
    if (!selectedAmbiente) {
      toast.error('Selecione um ambiente antes de registrar inconformidade.');
      return;
    }
    if (!id || !inconformidade.trim()) return;
    try {
      await api.post(`/vistorias/${id}/inconformidades`, { ambiente_id: Number(selectedAmbiente), descricao: inconformidade.trim(), severidade: 'media' });
      setInconformidade('');
      toast.success('Inconformidade registrada.');
      await refresh();
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Erro ao registrar inconformidade.');
    }
  };

  const selecionarAmbiente = (ambiente: Ambiente) => {
    setSelectedAmbiente(String(ambiente.id));
    setComodo(ambiente.nome);
  };

  useEffect(() => {
    if (!selectedAmbiente && ambientes.length) {
      selecionarAmbiente(ambientes[0]);
    }
  }, [selectedAmbiente, ambientes.length]);

  useEffect(() => {
    if (!parteAssinaturaId && partes.length) {
      setParteAssinaturaId(String(partes.find((parte) => !parte.assinou)?.id || partes[0].id));
    }
  }, [parteAssinaturaId, partes.length]);

  const assinaturaPoint = (event: PointerEvent<HTMLCanvasElement>) => {
    const canvas = assinaturaCanvasRef.current;
    if (!canvas) return null;
    const rect = canvas.getBoundingClientRect();
    return {
      x: ((event.clientX - rect.left) / rect.width) * canvas.width,
      y: ((event.clientY - rect.top) / rect.height) * canvas.height,
    };
  };

  const iniciarAssinatura = (event: PointerEvent<HTMLCanvasElement>) => {
    const canvas = assinaturaCanvasRef.current;
    const point = assinaturaPoint(event);
    if (!canvas || !point) return;
    desenhandoRef.current = true;
    canvas.setPointerCapture(event.pointerId);
    const ctx = canvas.getContext('2d');
    if (!ctx) return;
    ctx.strokeStyle = '#0f172a';
    ctx.lineWidth = 3;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.beginPath();
    ctx.moveTo(point.x, point.y);
  };

  const desenharAssinatura = (event: PointerEvent<HTMLCanvasElement>) => {
    if (!desenhandoRef.current) return;
    const canvas = assinaturaCanvasRef.current;
    const point = assinaturaPoint(event);
    const ctx = canvas?.getContext('2d');
    if (!canvas || !ctx || !point) return;
    ctx.lineTo(point.x, point.y);
    ctx.stroke();
  };

  const finalizarAssinatura = () => {
    desenhandoRef.current = false;
  };

  const limparAssinatura = () => {
    const canvas = assinaturaCanvasRef.current;
    const ctx = canvas?.getContext('2d');
    if (!canvas || !ctx) return;
    ctx.clearRect(0, 0, canvas.width, canvas.height);
  };

  const salvarAssinatura = async () => {
    if (!id || !parteAssinatura) return;
    const canvas = assinaturaCanvasRef.current;
    if (!canvas) return;
    setAssinando(true);
    try {
      await api.post(`/vistorias/${id}/assinaturas`, {
        parte_id: parteAssinatura.id,
        assinatura: canvas.toDataURL('image/png'),
      });
      toast.success('Assinatura registrada.');
      limparAssinatura();
      await refresh();
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Erro ao salvar assinatura.');
    } finally {
      setAssinando(false);
    }
  };

  return (
    <div className="flex">
      <Sidebar />
      <div className="page-shell">
        <div className="max-w-5xl mx-auto space-y-6">
          <div className="flex flex-wrap items-center gap-2 text-sm text-muted-foreground">
            <Link to="/vistorias" className="hover:text-foreground">Vistorias</Link>
            <ChevronRight size={14} />
            <span>Execução</span>
            <ChevronRight size={14} />
            <span className="text-foreground">{vistoria?.codigo || (id ? `#${id}` : 'Vistoria')}</span>
          </div>

          <div className="page-header">
            <div>
              <h1 className="page-title">Execução de Vistoria</h1>
              <p className="page-subtitle">Fluxo operacional para vistoriador: evidências, comentários e fechamento.</p>
            </div>
            <Link to={id ? `/vistorias/${id}` : '/vistorias'} className="rounded-xl border border-white/15 bg-white/5 px-4 py-2 text-sm">Voltar</Link>
          </div>

          <div className="glass-panel rounded-2xl p-4">
            <div className="mb-3 h-1.5 overflow-hidden rounded-full bg-white/10">
              <div className="h-full rounded-full bg-emerald-400 transition-all" style={{ width: `${((step + 1) / steps.length) * 100}%` }} />
            </div>
            <div className="flex flex-wrap items-center gap-2 text-xs sm:text-sm">
            {steps.map((name, idx) => (
              <div key={name} className="flex items-center gap-2">
                <button
                  type="button"
                  onClick={() => idx <= step || started ? setStep(idx) : null}
                  className="inline-flex items-center gap-2 rounded-xl px-1 py-1"
                >
                  <span className={`inline-flex h-7 w-7 items-center justify-center rounded-full border ${idx <= step ? 'border-emerald-400 bg-emerald-500/20 text-emerald-200' : 'border-white/15 text-muted-foreground'}`}>
                    {idx < step ? <Check size={14} /> : idx + 1}
                  </span>
                  <span className={idx === step ? 'text-foreground font-semibold' : 'text-muted-foreground'}>{name}</span>
                </button>
                {idx < steps.length - 1 ? <ChevronRight size={16} className="text-muted-foreground" /> : null}
              </div>
            ))}
            </div>
          </div>

          {loading ? <div className="glass-panel rounded-2xl p-10 flex justify-center"><Loader2 className="animate-spin" /></div> : (
            <div className="glass-panel rounded-2xl p-5 space-y-4">
              <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-sm text-muted-foreground">Vistoria: <strong className="text-foreground">{vistoria?.codigo || `#${vistoria?.id}`}</strong> · status atual: <strong className="text-foreground">{vistoria?.status}</strong></p>
                {!started ? (
                  <button onClick={iniciarVistoria} disabled={iniciando} className="rounded-xl bg-amber-500 px-4 py-2 text-sm font-semibold text-black">
                    {iniciando ? 'Iniciando...' : 'Iniciar vistoria'}
                  </button>
                ) : (
                  <span className="rounded-xl border border-emerald-500/40 bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-200">Vistoria iniciada</span>
                )}
              </div>

              {step === 0 && (
                <div className="space-y-4 text-sm">
                  <div className="rounded-2xl border border-cyan-500/25 bg-cyan-500/10 p-4">
                    <p className="flex items-center gap-2 font-semibold text-cyan-50"><Sparkles size={16} />Checklist inicial</p>
                    <p className="mt-1 text-xs text-cyan-50/80">Confirme o básico no celular e siga para capturar ambientes, fotos e assinatura.</p>
                  </div>
                  <div className="grid gap-3 sm:grid-cols-3">
                    {[
                      ['Endereço confirmado', checkChegada, setCheckChegada],
                      ['Acesso liberado', checkAcesso, setCheckAcesso],
                      ['Escopo alinhado', checkEscopo, setCheckEscopo],
                    ].map(([label, checked, setter]) => (
                      <button
                        key={String(label)}
                        type="button"
                        onClick={() => (setter as (value: boolean) => void)(!(checked as boolean))}
                        className={`rounded-2xl border p-4 text-left transition ${(checked as boolean) ? 'border-emerald-400/50 bg-emerald-500/15 text-emerald-100' : 'border-white/10 bg-white/5 text-muted-foreground'}`}
                      >
                        <span className="mb-3 inline-flex h-8 w-8 items-center justify-center rounded-full border border-current">{checked ? <Check size={16} /> : null}</span>
                        <span className="block font-semibold">{String(label)}</span>
                      </button>
                    ))}
                  </div>
                  {!started ? <button onClick={iniciarVistoria} disabled={iniciando || !canAdvance} className="w-full rounded-xl bg-amber-500 px-4 py-3 text-sm font-semibold text-black disabled:opacity-50">{iniciando ? 'Iniciando...' : 'Iniciar vistoria'}</button> : null}
                </div>
              )}

              {step === 1 && (
                <div className="space-y-5">
                  <div className="rounded-2xl border border-cyan-500/25 bg-cyan-500/10 p-4">
                    <p className="font-semibold text-sm">Passo 2 — vistoria por ambiente</p>
                    <p className="mt-1 text-xs text-cyan-50/80">A ordem agora é simples: crie ou escolha um compartimento, anexe as fotos dele e registre itens/problemas no mesmo painel.</p>
                  </div>

                  <section className="rounded-2xl border border-white/10 bg-black/10 p-4">
                    <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                      <div>
                        <p className="text-sm font-semibold">Compartimentos da casa</p>
                        <p className="text-xs text-muted-foreground">Sala, cozinha, quartos, banheiros e outros compartimentos.</p>
                      </div>
                      <span className="rounded-full border border-white/10 px-3 py-1 text-xs text-muted-foreground">{ambientes.length} ambiente(s)</span>
                    </div>
                    <div className="flex gap-2 overflow-x-auto pb-2">
                      {ambientesRapidos.map((nome) => (
                        <button key={nome} type="button" onClick={() => criarAmbiente(nome)} className="shrink-0 rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs text-foreground">{nome}</button>
                      ))}
                    </div>
                    <div className="mt-2 grid gap-2 sm:grid-cols-[1fr_auto]">
                      <input value={ambienteNome} onChange={(e) => setAmbienteNome(e.target.value)} placeholder="Novo ambiente (Sala, Cozinha...)" className="rounded-xl border border-white/10 bg-white/5 px-3 py-2.5" />
                      <button onClick={adicionarAmbiente} className="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground"><Plus size={16} />Adicionar ambiente</button>
                    </div>
                    {ambientes.length ? (
                      <div className="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        {ambientes.map((ambiente) => {
                          const selecionado = String(ambiente.id) === selectedAmbiente;
                          return (
                            <button
                              key={ambiente.id}
                              type="button"
                              onClick={() => selecionarAmbiente(ambiente)}
                              className={`rounded-xl border p-3 text-left text-sm transition ${selecionado ? 'border-cyan-400/60 bg-cyan-500/15' : 'border-white/10 bg-white/5 hover:border-white/25'}`}
                            >
                              <div className="flex items-start justify-between gap-2">
                                <p className="font-semibold">{ambiente.nome}</p>
                                {selecionado ? <span className="rounded-full bg-cyan-400/20 px-2 py-0.5 text-[10px] font-semibold text-cyan-100">em edição</span> : null}
                              </div>
                              <p className="mt-1 text-xs text-muted-foreground">{ambiente.itens?.length || 0} item(ns) · {(ambiente.midias?.length || 0)} mídia(s) · {ambiente.inconformidades?.length || 0} inconformidade(s)</p>
                            </button>
                          );
                        })}
                      </div>
                    ) : (
                      <div className="mt-4 rounded-xl border border-dashed border-white/15 p-4 text-sm text-muted-foreground">Cadastre o primeiro ambiente para liberar os registros da vistoria.</div>
                    )}
                  </section>

                  {ambienteSelecionado ? (
                    <>
                      <section className="rounded-2xl border border-white/10 bg-black/10 p-4">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                          <div>
                            <p className="text-xs uppercase tracking-[0.16em] text-cyan-100/80">Registrando em</p>
                            <p className="mt-1 text-lg font-semibold text-cyan-50">{ambienteSelecionado.nome}</p>
                          </div>
                          <span className="rounded-full border border-cyan-400/30 bg-cyan-500/10 px-3 py-1 text-xs text-cyan-100">sai agrupado no laudo</span>
                        </div>
                        <div className="mt-4 grid gap-2 md:grid-cols-[1fr_auto]">
                          <input value={descricao} onChange={(e) => setDescricao(e.target.value)} placeholder="Legenda da foto/vídeo deste ambiente" className="rounded-xl border border-white/10 bg-white/5 px-3 py-2.5" />
                          <button onClick={() => fileRef.current?.click()} disabled={savingMedia} className="inline-flex items-center justify-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground disabled:opacity-40"><Camera size={16} />{savingMedia ? 'Enviando...' : 'Anexar fotos/vídeos'}</button>
                        </div>
                        <input ref={fileRef} type="file" accept="image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/webm" multiple className="hidden" onChange={(e) => upload(e.target.files)} />
                        <p className="mt-3 text-xs text-muted-foreground">Neste ambiente: {(ambienteSelecionado.midias?.length || 0) + fotosDoAmbienteSelecionado} mídia(s). Total da vistoria: {mediaResumoNovo.total} mídia(s) · {mediaResumoNovo.imagens} foto(s) · {mediaResumoNovo.videos} vídeo(s).</p>
                      </section>

                      <div className="grid gap-4 lg:grid-cols-2">
                        <section className="rounded-2xl border border-white/10 bg-black/10 p-4">
                          <p className="text-sm font-semibold">2. Itens avaliados</p>
                          <p className="mt-1 text-xs text-muted-foreground">Exemplos: porta, piso, janela, pintura, tomada.</p>
                          <div className="mt-3 flex gap-2 overflow-x-auto pb-2">
                            {itensRapidos.map((nome) => (
                              <button key={nome} type="button" onClick={() => criarItem(nome)} className="shrink-0 rounded-full border border-white/10 bg-white/5 px-3 py-2 text-xs">{nome}</button>
                            ))}
                          </div>
                          <div className="mt-3 grid gap-2 sm:grid-cols-[1fr_150px]">
                            <input value={itemNome} onChange={(e) => setItemNome(e.target.value)} placeholder="Item avaliado" className="rounded-xl border border-white/10 bg-white/5 px-3 py-2.5" />
                            <select value={itemEstado} onChange={(e) => setItemEstado(e.target.value)} className="rounded-xl border border-white/10 bg-white/5 px-3 py-2.5">
                              <option value="novo">Novo</option><option value="bom">Bom</option><option value="regular">Regular</option><option value="mau">Mau</option><option value="nao_aplicavel">N/A</option>
                            </select>
                          </div>
                          <button onClick={adicionarItem} className="mt-3 rounded-xl border border-white/10 px-4 py-2 text-sm">Adicionar item</button>
                          <div className="mt-3 flex flex-wrap gap-1.5">
                            {(ambienteSelecionado.itens || []).length ? (ambienteSelecionado.itens || []).map((item) => (
                              <div key={item.id} className="inline-flex items-center gap-1 rounded-lg bg-white/5 pl-2 pr-1 py-1 text-xs">
                                <span>{item.nome}: {item.estado || 'sem estado'}</span>
                                <button
                                  type="button"
                                  aria-label={`Excluir item ${item.nome}`}
                                  disabled={deletingItemId === item.id}
                                  onClick={() => excluirItem(item)}
                                  className="rounded-md p-1 text-red-300 hover:bg-red-500/15 disabled:opacity-50"
                                >
                                  {deletingItemId === item.id ? <Loader2 size={13} className="animate-spin" /> : <Trash2 size={13} />}
                                </button>
                              </div>
                            )) : <span className="text-xs text-muted-foreground">Nenhum item cadastrado neste ambiente.</span>}
                          </div>
                        </section>

                        <section className="rounded-2xl border border-amber-400/20 bg-amber-500/5 p-4">
                          <p className="text-sm font-semibold">3. Inconformidades</p>
                          <p className="mt-1 text-xs text-muted-foreground">Registre apenas problemas encontrados neste ambiente.</p>
                          <textarea value={inconformidade} onChange={(e) => setInconformidade(e.target.value)} rows={3} placeholder="Ex.: parede com infiltração próxima à janela" className="mt-3 w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2.5" />
                          <button onClick={registrarInconformidade} className="mt-3 rounded-xl border border-amber-400/40 bg-amber-500/10 px-4 py-2 text-sm text-amber-100">Registrar inconformidade</button>
                          <div className="mt-3 space-y-2">
                            {(ambienteSelecionado.inconformidades || []).length ? (ambienteSelecionado.inconformidades || []).map((item) => (
                              <div key={item.id} className="rounded-lg border border-white/10 bg-black/10 p-2 text-xs">{item.descricao}</div>
                            )) : <p className="text-xs text-muted-foreground">Nenhuma inconformidade registrada neste ambiente.</p>}
                          </div>
                        </section>
                      </div>

                    </>
                  ) : (
                    <div className="rounded-2xl border border-dashed border-white/15 bg-white/5 p-6 text-center text-sm text-muted-foreground">Selecione ou crie um ambiente para liberar itens, inconformidades e fotos.</div>
                  )}
                </div>
              )}

              {step === 2 && (
                <div className="space-y-3">
                  <p className="font-semibold text-sm">Passo 3 — comentários técnicos</p>
                  <textarea value={comentario} onChange={(e) => setComentario(e.target.value)} rows={4} placeholder="Digite e pare um instante: o comentário será salvo automaticamente." className="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2.5" />
                  <p className="text-xs text-muted-foreground">{savingComment ? 'Salvando automaticamente...' : comentario.trim().length >= 3 ? 'Auto save ativo.' : 'Comentários técnicos são salvos automaticamente.'}</p>
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
                <div className="space-y-4 text-sm">
                  <div className="rounded-2xl border border-emerald-500/25 bg-emerald-500/10 p-4">
                    <p className="font-semibold text-emerald-100">Fechamento inteligente</p>
                    <p className="mt-1 text-xs text-emerald-50/80">Ao finalizar, o sistema gera o laudo PDF e abre o documento automaticamente.</p>
                  </div>
                  <div className="grid gap-3 sm:grid-cols-3">
                    <div className="rounded-xl border border-white/10 bg-black/10 p-3"><p className="text-xs text-muted-foreground">Ambientes</p><p className="mt-1 text-2xl font-semibold">{ambientes.length}</p></div>
                    <div className="rounded-xl border border-white/10 bg-black/10 p-3"><p className="text-xs text-muted-foreground">Evidências</p><p className="mt-1 text-2xl font-semibold">{mediaResumoNovo.total}</p></div>
                    <div className="rounded-xl border border-white/10 bg-black/10 p-3"><p className="text-xs text-muted-foreground">Comentários</p><p className="mt-1 text-2xl font-semibold">{(vistoria?.comentarios || []).length}</p></div>
                  </div>
                  <section className="rounded-2xl border border-white/10 bg-black/10 p-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                      <div>
                        <p className="flex items-center gap-2 font-semibold"><PenLine size={16} />Assinaturas</p>
                        <p className="mt-1 text-xs text-muted-foreground">{partes.filter((parte) => parte.assinou).length} de {partes.length} parte(s) assinada(s).</p>
                      </div>
                      {parteAssinatura ? (
                        <select value={parteAssinaturaId || String(parteAssinatura.id)} onChange={(e) => setParteAssinaturaId(e.target.value)} className="rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 text-sm">
                          {partes.map((parte) => <option key={parte.id} value={parte.id}>{parte.assinou ? '✓ ' : ''}{parte.nome} - {parte.funcao || 'parte'}</option>)}
                        </select>
                      ) : null}
                    </div>
                    {parteAssinatura ? (
                      <div className="mt-4 space-y-3">
                        <div className="rounded-xl border border-white/10 bg-white/5 p-3 text-xs text-muted-foreground">
                          <strong className="text-foreground">{parteAssinatura.nome}</strong> · {parteAssinatura.documento || 'sem documento'} · {parteAssinatura.funcao || 'parte'}
                        </div>
                        <canvas
                          ref={assinaturaCanvasRef}
                          width={720}
                          height={220}
                          onPointerDown={iniciarAssinatura}
                          onPointerMove={desenharAssinatura}
                          onPointerUp={finalizarAssinatura}
                          onPointerCancel={finalizarAssinatura}
                          className="h-44 w-full touch-none rounded-xl border border-white/15 bg-white"
                        />
                        <div className="flex flex-wrap gap-2">
                          <button type="button" onClick={salvarAssinatura} disabled={assinando} className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground disabled:opacity-50">{assinando ? 'Salvando...' : 'Salvar assinatura'}</button>
                          <button type="button" onClick={limparAssinatura} className="rounded-xl border border-white/10 px-4 py-2 text-sm">Limpar</button>
                        </div>
                      </div>
                    ) : (
                      <p className="mt-3 text-sm text-muted-foreground">Nenhuma parte cadastrada para assinatura.</p>
                    )}
                  </section>
                  <button onClick={gerarAbrirLaudo} disabled={gerandoLaudo || !canAdvance} className="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-5 py-3 font-semibold text-white disabled:opacity-50">
                    {gerandoLaudo ? <Loader2 size={18} className="animate-spin" /> : <FileText size={18} />}
                    {gerandoLaudo ? 'Gerando laudo...' : isFinalizada ? 'Gerar e visualizar laudo' : 'Concluir e visualizar laudo'}
                  </button>
                </div>
              )}

              <div className="flex justify-between pt-2">
                <button onClick={() => setStep((s) => Math.max(0, s - 1))} disabled={step === 0} className="rounded-xl border border-white/10 px-4 py-2 text-sm disabled:opacity-40">Voltar etapa</button>
                {step < steps.length - 1 ? (
                  <button onClick={() => setStep((s) => Math.min(steps.length - 1, s + 1))} disabled={!started || !canAdvance} className="rounded-xl bg-primary px-5 py-2 text-sm font-semibold text-primary-foreground disabled:opacity-40">Continuar</button>
                ) : null}
              </div>
            </div>
          )}
        </div>
      </div>
      {pdfUrl ? (
        <div className="fixed inset-0 z-50 flex flex-col bg-slate-950/95">
          <div className="flex items-center justify-between gap-3 border-b border-white/10 px-4 py-3">
            <div>
              <p className="text-sm font-semibold text-foreground">Laudo da vistoria</p>
              <p className="text-xs text-muted-foreground">{vistoria?.codigo || `Vistoria #${id}`}</p>
            </div>
            <button
              type="button"
              onClick={() => setPdfUrl((previous) => {
                if (previous) window.URL.revokeObjectURL(previous);
                return null;
              })}
              className="rounded-xl border border-white/10 px-4 py-2 text-sm text-foreground"
            >
              Fechar
            </button>
          </div>
          <iframe title="Laudo PDF" src={pdfUrl} className="h-full w-full flex-1 bg-white" />
        </div>
      ) : null}
    </div>
  );
}
