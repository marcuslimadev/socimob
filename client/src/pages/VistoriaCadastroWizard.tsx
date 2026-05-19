import { useEffect, useMemo, useState } from 'react';
import { Link, useLocation } from 'wouter';
import Select from 'react-select';
import { Building2, CalendarClock, Check, ChevronRight, ClipboardList, Loader2, MapPin, Sparkles, UserPlus, Users } from 'lucide-react';
import { toast } from 'sonner';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';

type Pessoa = { id: number; nome: string; email?: string | null; celular?: string | null };
type Imovel = { id: number; titulo?: string | null; codigo?: string | null; logradouro?: string | null; bairro?: string | null; cidade?: string | null; estado?: string | null };
type Contrato = { id: number; numero_contrato?: string | null; locador?: Pessoa | null; locatario?: Pessoa | null; imovel?: Imovel | null };
type Option = { value: number; label: string };

const steps = [
  { title: 'Local', icon: MapPin },
  { title: 'Partes', icon: Users },
  { title: 'Agenda', icon: CalendarClock },
  { title: 'Revisão', icon: ClipboardList },
];

const tipos = [
  ['entrada', 'Entrada'],
  ['saida', 'Saída'],
  ['conferencia', 'Conferência'],
  ['manutencao', 'Manutenção'],
  ['avulsa', 'Avulsa'],
];

const emptyImovelLivre = () => ({ titulo: '', logradouro: '', bairro: '', cidade: '', estado: '', tipo_imovel: '', referencia: '' });

const formatLocalDateTimeInput = (date: Date) => {
  const offset = date.getTimezoneOffset() * 60_000;
  return new Date(date.getTime() - offset).toISOString().slice(0, 16);
};

export default function VistoriaCadastroWizard() {
  const [, setLocation] = useLocation();
  const [step, setStep] = useState(0);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [pessoas, setPessoas] = useState<Pessoa[]>([]);
  const [imoveis, setImoveis] = useState<Imovel[]>([]);
  const [contratos, setContratos] = useState<Contrato[]>([]);
  const [novoLoading, setNovoLoading] = useState(false);
  const [novoParticipante, setNovoParticipante] = useState({ nome: '', email: '', celular: '', create_vistoriador_user: false, user_email: '' });
  const [form, setForm] = useState({
    imovel_modo: 'cadastro' as 'cadastro' | 'livre',
    contrato_id: '',
    imovel_id: '',
    imovel_livre: emptyImovelLivre(),
    cliente_nome: '',
    tipo: 'entrada',
    data_vistoria: '',
    observacoes: '',
    responsavel_pessoa_id: '',
    participantes_ids: [] as number[],
    status: 'designada',
  });

  useEffect(() => {
    setLoading(true);
    api.get('/vistorias/meta')
      .then(({ data }) => {
        setPessoas(data.pessoas || []);
        setImoveis(data.imoveis || []);
        setContratos(data.contratos || []);
      })
      .catch(() => toast.error('Erro ao carregar dados da vistoria.'))
      .finally(() => setLoading(false));
  }, []);

  const pessoaOpts = useMemo<Option[]>(() => pessoas.map((p) => ({ value: p.id, label: p.nome })), [pessoas]);
  const selectedContrato = contratos.find((item) => String(item.id) === form.contrato_id) || null;
  const selectedImovel = imoveis.find((item) => String(item.id) === form.imovel_id) || selectedContrato?.imovel || null;
  const participantes = pessoas.filter((p) => form.participantes_ids.includes(p.id));

  const imovelLabel = (imovel?: Imovel | null) => {
    if (!imovel) return 'Nenhum imóvel definido';
    return [imovel.titulo || imovel.codigo || `#${imovel.id}`, imovel.logradouro, imovel.bairro, imovel.cidade, imovel.estado].filter(Boolean).join(' · ');
  };

  const contratoLabel = (contrato?: Contrato | null) => {
    if (!contrato) return 'Sem contrato';
    return [contrato.numero_contrato || `Contrato #${contrato.id}`, contrato.locatario?.nome, contrato.imovel ? imovelLabel(contrato.imovel) : null].filter(Boolean).join(' · ');
  };

  const onContratoChange = (value: string) => {
    const contrato = contratos.find((item) => String(item.id) === value) || null;
    const ids = [contrato?.locatario?.id, contrato?.locador?.id].filter(Boolean) as number[];
    setForm((prev) => ({
      ...prev,
      imovel_modo: 'cadastro',
      contrato_id: value,
      imovel_id: contrato?.imovel?.id ? String(contrato.imovel.id) : prev.imovel_id,
      cliente_nome: contrato?.locatario?.nome || prev.cliente_nome,
      participantes_ids: Array.from(new Set([...prev.participantes_ids, ...ids])),
    }));
  };

  const canGoNext =
    step === 0
      ? form.imovel_modo === 'livre'
        ? Boolean(form.imovel_livre.titulo || form.imovel_livre.logradouro)
        : Boolean(form.contrato_id || form.imovel_id)
      : step === 1
        ? form.participantes_ids.length > 0 || Boolean(selectedContrato)
        : step === 2
          ? Boolean(form.tipo)
          : true;

  const next = () => {
    if (!canGoNext) {
      toast.error(step === 0 ? 'Informe contrato, imóvel ou local livre.' : 'Complete esta etapa para continuar.');
      return;
    }
    setStep((s) => Math.min(steps.length - 1, s + 1));
  };

  const criarParticipante = async () => {
    if (!novoParticipante.nome.trim()) return toast.error('Informe o nome do participante.');
    setNovoLoading(true);
    try {
      const { data } = await api.post('/vistorias/participantes', {
        nome: novoParticipante.nome,
        email: novoParticipante.email || null,
        celular: novoParticipante.celular || null,
        create_vistoriador_user: novoParticipante.create_vistoriador_user,
        user_email: novoParticipante.user_email || novoParticipante.email || null,
      });
      const pessoa = data?.pessoa;
      if (pessoa?.id) {
        setPessoas((prev) => [{ id: pessoa.id, nome: pessoa.nome, email: pessoa.email, celular: pessoa.celular }, ...prev]);
        setForm((prev) => ({ ...prev, participantes_ids: Array.from(new Set([...prev.participantes_ids, pessoa.id])) }));
      }
      toast.success(data?.user?.default_password ? `Login criado. Senha padrão: ${data.user.default_password}.` : 'Participante adicionado.');
      setNovoParticipante({ nome: '', email: '', celular: '', create_vistoriador_user: false, user_email: '' });
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Erro ao cadastrar participante.');
    } finally {
      setNovoLoading(false);
    }
  };

  const salvar = async () => {
    if (!canGoNext) return;
    setSaving(true);
    try {
      const payload = {
        status: form.status,
        tipo: form.tipo,
        cliente_nome: form.cliente_nome || null,
        contrato_id: form.imovel_modo === 'cadastro' && form.contrato_id ? Number(form.contrato_id) : null,
        imovel_id: form.imovel_modo === 'cadastro' && !form.contrato_id && form.imovel_id ? Number(form.imovel_id) : null,
        imovel_livre: form.imovel_modo === 'livre' ? form.imovel_livre : null,
        responsavel_pessoa_id: form.responsavel_pessoa_id ? Number(form.responsavel_pessoa_id) : null,
        participantes_ids: form.participantes_ids,
        data_vistoria: form.data_vistoria || null,
        observacoes: form.observacoes || null,
      };
      const { data } = await api.post('/vistorias', payload);
      const vistoriaId = data?.vistoria?.id || data?.id;
      toast.success('Vistoria criada.');
      setLocation(`/vistorias/${vistoriaId}/execucao`);
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Erro ao criar vistoria.');
    } finally {
      setSaving(false);
    }
  };

  const setAgendaRapida = (kind: 'agora' | 'amanha' | 'proxima-semana') => {
    const date = new Date();
    if (kind === 'amanha') date.setDate(date.getDate() + 1);
    if (kind === 'proxima-semana') date.setDate(date.getDate() + 7);
    if (kind !== 'agora') date.setHours(9, 0, 0, 0);
    setForm((prev) => ({ ...prev, data_vistoria: formatLocalDateTimeInput(date), status: 'designada' }));
  };

  return (
    <div className="flex">
      <Sidebar />
      <div className="page-shell">
        <div className="mx-auto max-w-6xl space-y-5">
          <div className="page-header">
            <div>
              <h1 className="page-title">Nova Vistoria</h1>
              <p className="page-subtitle">Cadastro guiado, rápido no celular e pronto para ir direto à execução com fotos, vídeos e assinatura.</p>
            </div>
            <Link to="/vistorias" className="rounded-xl border border-white/15 bg-white/5 px-4 py-2 text-sm">Voltar</Link>
          </div>

          <div className="glass-panel rounded-2xl p-3">
            <div className="flex gap-2 overflow-x-auto pb-1">
              {steps.map((item, idx) => {
                const Icon = item.icon;
                return (
                  <button
                    key={item.title}
                    type="button"
                    onClick={() => idx <= step && setStep(idx)}
                    className={`flex shrink-0 items-center gap-2 rounded-xl border px-3 py-2 text-sm ${idx <= step ? 'border-emerald-400/40 bg-emerald-500/15 text-emerald-100' : 'border-white/10 bg-white/5 text-muted-foreground'}`}
                  >
                    <span className="inline-flex h-7 w-7 items-center justify-center rounded-full border border-current">{idx < step ? <Check size={14} /> : <Icon size={14} />}</span>
                    <span className="font-medium">{item.title}</span>
                    {idx < steps.length - 1 ? <ChevronRight size={14} className="opacity-60" /> : null}
                  </button>
                );
              })}
            </div>
          </div>

          {loading ? (
            <div className="glass-panel rounded-2xl p-10 text-center text-muted-foreground"><Loader2 className="mx-auto mb-2 animate-spin" />Carregando dados...</div>
          ) : (
            <div className="grid gap-5 lg:grid-cols-[1fr_340px]">
              <div className="glass-panel rounded-2xl p-4 sm:p-6">
                {step === 0 && (
                  <div className="space-y-5">
                    <div className="grid gap-3 sm:grid-cols-2">
                      <button type="button" onClick={() => setForm((p) => ({ ...p, imovel_modo: 'cadastro' }))} className={`rounded-2xl border p-4 text-left ${form.imovel_modo === 'cadastro' ? 'border-blue-400/50 bg-blue-500/15' : 'border-white/10 bg-white/5'}`}>
                        <Building2 className="mb-3 text-blue-200" size={22} />
                        <p className="font-semibold">Usar cadastro existente</p>
                        <p className="mt-1 text-xs text-muted-foreground">Contrato preenche imóvel, cliente e partes automaticamente.</p>
                      </button>
                      <button type="button" onClick={() => setForm((p) => ({ ...p, imovel_modo: 'livre', contrato_id: '', imovel_id: '' }))} className={`rounded-2xl border p-4 text-left ${form.imovel_modo === 'livre' ? 'border-cyan-400/50 bg-cyan-500/15' : 'border-white/10 bg-white/5'}`}>
                        <MapPin className="mb-3 text-cyan-200" size={22} />
                        <p className="font-semibold">Local livre</p>
                        <p className="mt-1 text-xs text-muted-foreground">Para vistoria avulsa ou imóvel ainda não cadastrado.</p>
                      </button>
                    </div>

                    {form.imovel_modo === 'cadastro' ? (
                      <div className="grid gap-4">
                        <label className="space-y-1.5">
                          <span className="text-xs uppercase tracking-[0.16em] text-muted-foreground">Contrato</span>
                          <select value={form.contrato_id} onChange={(e) => onContratoChange(e.target.value)} className="min-h-12 w-full rounded-xl border border-white/10 bg-white/5 px-3 text-base">
                            <option value="">Sem contrato</option>
                            {contratos.map((c) => <option key={c.id} value={c.id}>{contratoLabel(c)}</option>)}
                          </select>
                        </label>
                        <label className="space-y-1.5">
                          <span className="text-xs uppercase tracking-[0.16em] text-muted-foreground">Imóvel</span>
                          <select value={form.imovel_id} onChange={(e) => setForm((p) => ({ ...p, imovel_id: e.target.value }))} className="min-h-12 w-full rounded-xl border border-white/10 bg-white/5 px-3 text-base">
                            <option value="">Selecione se não houver contrato</option>
                            {imoveis.map((i) => <option key={i.id} value={i.id}>{imovelLabel(i)}</option>)}
                          </select>
                        </label>
                      </div>
                    ) : (
                      <div className="grid gap-3 sm:grid-cols-2">
                        <input value={form.imovel_livre.titulo} onChange={(e) => setForm((p) => ({ ...p, imovel_livre: { ...p.imovel_livre, titulo: e.target.value } }))} placeholder="Título ou referência" className="min-h-12 rounded-xl border border-white/10 bg-white/5 px-3 text-base" />
                        <input value={form.imovel_livre.tipo_imovel} onChange={(e) => setForm((p) => ({ ...p, imovel_livre: { ...p.imovel_livre, tipo_imovel: e.target.value } }))} placeholder="Tipo: casa, apto, loja..." className="min-h-12 rounded-xl border border-white/10 bg-white/5 px-3 text-base" />
                        <input value={form.imovel_livre.logradouro} onChange={(e) => setForm((p) => ({ ...p, imovel_livre: { ...p.imovel_livre, logradouro: e.target.value } }))} placeholder="Rua, número, complemento" className="min-h-12 rounded-xl border border-white/10 bg-white/5 px-3 text-base sm:col-span-2" />
                        <input value={form.imovel_livre.bairro} onChange={(e) => setForm((p) => ({ ...p, imovel_livre: { ...p.imovel_livre, bairro: e.target.value } }))} placeholder="Bairro" className="min-h-12 rounded-xl border border-white/10 bg-white/5 px-3 text-base" />
                        <input value={form.imovel_livre.cidade} onChange={(e) => setForm((p) => ({ ...p, imovel_livre: { ...p.imovel_livre, cidade: e.target.value } }))} placeholder="Cidade" className="min-h-12 rounded-xl border border-white/10 bg-white/5 px-3 text-base" />
                        <input value={form.imovel_livre.estado} onChange={(e) => setForm((p) => ({ ...p, imovel_livre: { ...p.imovel_livre, estado: e.target.value.toUpperCase().slice(0, 2) } }))} placeholder="UF" maxLength={2} className="min-h-12 rounded-xl border border-white/10 bg-white/5 px-3 text-base uppercase" />
                        <input value={form.imovel_livre.referencia} onChange={(e) => setForm((p) => ({ ...p, imovel_livre: { ...p.imovel_livre, referencia: e.target.value } }))} placeholder="Referência de acesso" className="min-h-12 rounded-xl border border-white/10 bg-white/5 px-3 text-base" />
                      </div>
                    )}
                  </div>
                )}

                {step === 1 && (
                  <div className="space-y-5">
                    <Select
                      isMulti
                      options={pessoaOpts}
                      value={pessoaOpts.filter((o) => form.participantes_ids.includes(o.value))}
                      onChange={(v) => setForm((p) => ({ ...p, participantes_ids: (v || []).map((x) => x.value) }))}
                      placeholder="Buscar locatário, locador, fiador, vistoriador..."
                      noOptionsMessage={() => 'Nenhuma pessoa encontrada'}
                    />
                    <div className="rounded-2xl border border-white/10 bg-black/10 p-4">
                      <p className="mb-3 flex items-center gap-2 font-semibold"><UserPlus size={16} />Cadastrar participante sem sair da tela</p>
                      <div className="grid gap-3 sm:grid-cols-2">
                        <input value={novoParticipante.nome} onChange={(e) => setNovoParticipante((p) => ({ ...p, nome: e.target.value }))} placeholder="Nome" className="min-h-12 rounded-xl border border-white/10 bg-white/5 px-3 text-base" />
                        <input value={novoParticipante.email} onChange={(e) => setNovoParticipante((p) => ({ ...p, email: e.target.value }))} placeholder="Email" className="min-h-12 rounded-xl border border-white/10 bg-white/5 px-3 text-base" />
                        <input value={novoParticipante.celular} onChange={(e) => setNovoParticipante((p) => ({ ...p, celular: e.target.value }))} placeholder="Celular" className="min-h-12 rounded-xl border border-white/10 bg-white/5 px-3 text-base" />
                        <label className="flex min-h-12 items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-3 text-sm"><input type="checkbox" checked={novoParticipante.create_vistoriador_user} onChange={(e) => setNovoParticipante((p) => ({ ...p, create_vistoriador_user: e.target.checked }))} />Criar login de vistoriador</label>
                      </div>
                      {novoParticipante.create_vistoriador_user ? <input value={novoParticipante.user_email} onChange={(e) => setNovoParticipante((p) => ({ ...p, user_email: e.target.value }))} placeholder="Email de acesso" className="mt-3 min-h-12 w-full rounded-xl border border-white/10 bg-white/5 px-3 text-base" /> : null}
                      <button type="button" onClick={criarParticipante} disabled={novoLoading} className="mt-3 rounded-xl bg-primary px-4 py-3 text-sm font-semibold text-primary-foreground disabled:opacity-60">{novoLoading ? 'Criando...' : 'Adicionar participante'}</button>
                    </div>
                  </div>
                )}

                {step === 2 && (
                  <div className="space-y-5">
                    <div className="grid gap-3 sm:grid-cols-2">
                      {tipos.map(([value, label]) => (
                        <button key={value} type="button" onClick={() => setForm((p) => ({ ...p, tipo: value }))} className={`rounded-2xl border p-4 text-left font-semibold ${form.tipo === value ? 'border-emerald-400/50 bg-emerald-500/15 text-emerald-100' : 'border-white/10 bg-white/5'}`}>{label}</button>
                      ))}
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2">
                      <input type="datetime-local" value={form.data_vistoria} onChange={(e) => setForm((p) => ({ ...p, data_vistoria: e.target.value }))} className="min-h-12 rounded-xl border border-white/10 bg-white/5 px-3 text-base" />
                      <select value={form.responsavel_pessoa_id} onChange={(e) => setForm((p) => ({ ...p, responsavel_pessoa_id: e.target.value }))} className="min-h-12 rounded-xl border border-white/10 bg-white/5 px-3 text-base">
                        <option value="">Responsável/vistoriador</option>
                        {pessoas.map((p) => <option key={p.id} value={p.id}>{p.nome}</option>)}
                      </select>
                    </div>
                    <div className="flex flex-wrap gap-2">
                      <button type="button" onClick={() => setAgendaRapida('agora')} className="rounded-xl border border-white/10 px-3 py-2 text-sm">Agora</button>
                      <button type="button" onClick={() => setAgendaRapida('amanha')} className="rounded-xl border border-white/10 px-3 py-2 text-sm">Amanhã 09:00</button>
                      <button type="button" onClick={() => setAgendaRapida('proxima-semana')} className="rounded-xl border border-white/10 px-3 py-2 text-sm">Próxima semana</button>
                    </div>
                    <input value={form.cliente_nome} onChange={(e) => setForm((p) => ({ ...p, cliente_nome: e.target.value }))} placeholder="Cliente principal / solicitante" className="min-h-12 w-full rounded-xl border border-white/10 bg-white/5 px-3 text-base" />
                    <textarea value={form.observacoes} onChange={(e) => setForm((p) => ({ ...p, observacoes: e.target.value }))} rows={4} placeholder="Observações para o vistoriador" className="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-3 text-base" />
                  </div>
                )}

                {step === 3 && (
                  <div className="space-y-4">
                    <div className="rounded-2xl border border-emerald-500/25 bg-emerald-500/10 p-4">
                      <p className="flex items-center gap-2 font-semibold text-emerald-100"><Sparkles size={16} />Tudo pronto para executar</p>
                      <p className="mt-1 text-sm text-emerald-50/80">Ao criar, o sistema abre a execução mobile para adicionar ambientes, fotos, vídeos, comentários e assinaturas.</p>
                    </div>
                    {[
                      ['Contrato', contratoLabel(selectedContrato)],
                      ['Imóvel', form.imovel_modo === 'livre' ? [form.imovel_livre.titulo, form.imovel_livre.logradouro, form.imovel_livre.cidade].filter(Boolean).join(' · ') : imovelLabel(selectedImovel)],
                      ['Partes', participantes.length ? participantes.map((p) => p.nome).join(', ') : 'Usar partes do contrato, se houver'],
                      ['Tipo e agenda', `${tipos.find(([value]) => value === form.tipo)?.[1] || form.tipo} · ${form.data_vistoria || 'sem data definida'}`],
                    ].map(([label, value]) => (
                      <div key={label} className="rounded-xl border border-white/10 bg-white/5 p-3">
                        <p className="text-xs uppercase tracking-[0.16em] text-muted-foreground">{label}</p>
                        <p className="mt-1 text-sm text-foreground">{value || '-'}</p>
                      </div>
                    ))}
                  </div>
                )}

                <div className="mt-6 flex gap-3 border-t border-white/10 pt-4">
                  <button type="button" onClick={() => setStep((s) => Math.max(0, s - 1))} disabled={step === 0} className="min-h-12 flex-1 rounded-xl border border-white/10 px-4 text-sm disabled:opacity-40">Anterior</button>
                  {step < steps.length - 1 ? (
                    <button type="button" onClick={next} className="min-h-12 flex-[2] rounded-xl bg-primary px-4 text-sm font-semibold text-primary-foreground">Continuar</button>
                  ) : (
                    <button type="button" onClick={salvar} disabled={saving} className="min-h-12 flex-[2] rounded-xl bg-emerald-600 px-4 text-sm font-semibold text-white disabled:opacity-60">{saving ? 'Criando...' : 'Criar e abrir execução'}</button>
                  )}
                </div>
              </div>

              <aside className="glass-panel h-fit rounded-2xl p-4">
                <p className="mb-3 text-sm font-semibold">Resumo inteligente</p>
                <div className="space-y-3 text-sm">
                  <div className="rounded-xl border border-white/10 bg-white/5 p-3"><p className="text-xs text-muted-foreground">Local</p><p className="mt-1">{form.imovel_modo === 'livre' ? (form.imovel_livre.titulo || form.imovel_livre.logradouro || 'Local livre') : imovelLabel(selectedImovel)}</p></div>
                  <div className="rounded-xl border border-white/10 bg-white/5 p-3"><p className="text-xs text-muted-foreground">Partes</p><p className="mt-1">{participantes.length || (selectedContrato ? 2 : 0)} participante(s)</p></div>
                  <div className="rounded-xl border border-white/10 bg-white/5 p-3"><p className="text-xs text-muted-foreground">Próxima ação</p><p className="mt-1">{step < 3 ? `Concluir etapa ${step + 1}` : 'Criar vistoria e executar'}</p></div>
                </div>
              </aside>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
