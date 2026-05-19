import { useEffect, useMemo, useRef, useState } from 'react';
import { Link, useRoute } from 'wouter';
import { Camera, Check, ChevronRight, Loader2, Plus } from 'lucide-react';
import { toast } from 'sonner';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';

type Foto = { id: number; url_signed?: string; descricao?: string | null; mime_type?: string | null; comodo?: string | null };
type Comentario = { id: number; comentario: string; autor_nome?: string | null; created_at?: string };
type Midia = { id: number; url?: string; mime_type?: string | null; legenda?: string | null };
type Item = { id: number; nome: string; estado?: string | null; observacoes?: string | null; possui_inconformidade?: boolean };
type Inconformidade = { id: number; descricao: string; severidade?: string | null; status?: string | null };
type Ambiente = { id: number; nome: string; estado_geral?: string | null; observacoes?: string | null; itens?: Item[]; midias?: Midia[]; inconformidades?: Inconformidade[] };
type Vistoria = { id: number; codigo?: string | null; status: string; fotos?: Foto[]; comentarios?: Comentario[]; ambientes?: Ambiente[]; midias?: Midia[] };

const steps = ['Início', 'Ambientes e evidências', 'Comentários', 'Fechamento'];

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
  const [ambienteNome, setAmbienteNome] = useState('');
  const [selectedAmbiente, setSelectedAmbiente] = useState('');
  const [itemNome, setItemNome] = useState('');
  const [itemEstado, setItemEstado] = useState('bom');
  const [inconformidade, setInconformidade] = useState('');
  const [iniciando, setIniciando] = useState(false);
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
      await api.post(`/vistorias/${id}/finalizar`);
      toast.success('Vistoria concluída.');
      await refresh();
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Erro ao concluir vistoria.');
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
    if (!id || !ambienteNome.trim()) return;
    try {
      const nome = ambienteNome.trim();
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
    if (!selectedAmbiente) {
      toast.error('Selecione um ambiente antes de adicionar item.');
      return;
    }
    if (!id || !itemNome.trim()) return;
    try {
      await api.post(`/vistorias/${id}/ambientes/${selectedAmbiente}/itens`, { nome: itemNome.trim(), estado: itemEstado });
      setItemNome('');
      toast.success('Item adicionado.');
      await refresh();
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Erro ao adicionar item.');
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
                <div className="space-y-2 text-sm">
                  <p className="font-semibold">Passo 1 — confirmação de início</p>
                  <label className="flex items-center gap-2"><input type="checkbox" checked={checkChegada} onChange={(e) => setCheckChegada(e.target.checked)} />Cheguei ao imóvel e confirmei o endereço.</label>
                  <label className="flex items-center gap-2"><input type="checkbox" checked={checkAcesso} onChange={(e) => setCheckAcesso(e.target.checked)} />Acesso liberado (chaves/porteiro/proprietário).</label>
                  <label className="flex items-center gap-2"><input type="checkbox" checked={checkEscopo} onChange={(e) => setCheckEscopo(e.target.checked)} />Escopo alinhado (cômodos e itens a vistoriar).</label>
                  {!started ? <p className="text-amber-300 text-xs">Clique em <strong>Iniciar vistoria</strong> para registrar oficialmente o início.</p> : null}
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
                    <div className="grid gap-2 sm:grid-cols-[1fr_auto]">
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
                          <div className="mt-3 grid gap-2 sm:grid-cols-[1fr_150px]">
                            <input value={itemNome} onChange={(e) => setItemNome(e.target.value)} placeholder="Item avaliado" className="rounded-xl border border-white/10 bg-white/5 px-3 py-2.5" />
                            <select value={itemEstado} onChange={(e) => setItemEstado(e.target.value)} className="rounded-xl border border-white/10 bg-white/5 px-3 py-2.5">
                              <option value="novo">Novo</option><option value="bom">Bom</option><option value="regular">Regular</option><option value="mau">Mau</option><option value="nao_aplicavel">N/A</option>
                            </select>
                          </div>
                          <button onClick={adicionarItem} className="mt-3 rounded-xl border border-white/10 px-4 py-2 text-sm">Adicionar item</button>
                          <div className="mt-3 flex flex-wrap gap-1.5">
                            {(ambienteSelecionado.itens || []).length ? (ambienteSelecionado.itens || []).map((item) => (
                              <span key={item.id} className="rounded-lg bg-white/5 px-2 py-1 text-xs">{item.nome}: {item.estado || 'sem estado'}</span>
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
                  <p className="font-semibold">Passo 4 — concluir vistoria</p>
                  <p>- Ambientes cadastrados: <strong>{ambientes.length}</strong></p>
                  <p>- Evidências anexadas: <strong>{mediaResumoNovo.total}</strong></p>
                  <p>- Comentários registrados: <strong>{(vistoria?.comentarios || []).length}</strong></p>
                  <p className="text-muted-foreground">Se estiver tudo ok, conclua a vistoria para encerrar o fluxo operacional.</p>
                  <button onClick={concluir} className="rounded-xl bg-emerald-600 px-4 py-2 font-semibold text-white">Concluir vistoria</button>
                </div>
              )}

              <div className="flex justify-between pt-2">
                <button onClick={() => setStep((s) => Math.max(0, s - 1))} disabled={step === 0} className="rounded-xl border border-white/10 px-4 py-2 text-sm disabled:opacity-40">Anterior</button>
                <button onClick={() => setStep((s) => Math.min(steps.length - 1, s + 1))} disabled={step === steps.length - 1 || !started || !canAdvance} className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground disabled:opacity-40">Próximo</button>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
