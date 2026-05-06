import { useEffect, useMemo, useState } from 'react';
import { Link, useLocation } from 'wouter';
import Select from 'react-select';
import { Check, ChevronRight, Loader2 } from 'lucide-react';
import { toast } from 'sonner';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';

type Pessoa = { id: number; nome: string; email?: string | null };
type Imovel = { id: number; titulo?: string | null; codigo?: string | null; logradouro?: string | null; bairro?: string | null; cidade?: string | null; estado?: string | null };
type Contrato = { id: number; numero_contrato?: string | null; locatario?: Pessoa | null; imovel?: Imovel | null };
type Option = { value: number; label: string };

const steps = ['Local', 'Participantes', 'Planejamento', 'Revisão'];

export default function VistoriaCadastroWizard() {
  const [, setLocation] = useLocation();
  const [step, setStep] = useState(0);
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [pessoas, setPessoas] = useState<Pessoa[]>([]);
  const [imoveis, setImoveis] = useState<Imovel[]>([]);
  const [contratos, setContratos] = useState<Contrato[]>([]);
  const [novoLoading, setNovoLoading] = useState(false);

  const [form, setForm] = useState({
    imovel_modo: 'cadastro' as 'cadastro' | 'livre',
    contrato_id: '',
    imovel_id: '',
    imovel_livre: { titulo: '', logradouro: '', bairro: '', cidade: '', estado: '', tipo_imovel: '', referencia: '' },
    cliente_nome: '',
    tipo: 'entrada',
    data_vistoria: '',
    observacoes: '',
    responsavel_pessoa_id: '',
    participantes_ids: [] as number[],
    status: 'designada',
  });

  const [novoParticipante, setNovoParticipante] = useState({
    nome: '',
    email: '',
    celular: '',
    create_vistoriador_user: false,
    user_email: '',
  });

  useEffect(() => {
    setLoading(true);
    api.get('/vistorias/meta')
      .then(({ data }) => {
        setPessoas(data.pessoas || []);
        setImoveis(data.imoveis || []);
        setContratos(data.contratos || []);
      })
      .catch(() => toast.error('Erro ao carregar dados do wizard.'))
      .finally(() => setLoading(false));
  }, []);

  const pessoaOpts = useMemo<Option[]>(() => pessoas.map((p) => ({ value: p.id, label: p.nome })), [pessoas]);

  const next = () => setStep((s) => Math.min(steps.length - 1, s + 1));
  const prev = () => setStep((s) => Math.max(0, s - 1));

  const criarParticipante = async () => {
    if (!novoParticipante.nome.trim()) return toast.error('Informe o nome.');
    setNovoLoading(true);
    try {
      const payload = {
        nome: novoParticipante.nome,
        email: novoParticipante.email || null,
        celular: novoParticipante.celular || null,
        create_vistoriador_user: novoParticipante.create_vistoriador_user,
        user_email: novoParticipante.user_email || novoParticipante.email || null,
      };
      const { data } = await api.post('/vistorias/participantes', payload);
      const p = data?.pessoa;
      if (p?.id) {
        setPessoas((prevState) => [{ id: p.id, nome: p.nome, email: p.email }, ...prevState]);
        setForm((prevState) => ({ ...prevState, participantes_ids: [...new Set([...prevState.participantes_ids, p.id])] }));
      }
      if (data?.user?.default_password) {
        toast.success(`Vistoriador criado. Senha padrão: ${data.user.default_password}. Troca obrigatória no 1º acesso.`);
      } else {
        toast.success('Participante cadastrado e adicionado.');
      }
      setNovoParticipante({ nome: '', email: '', celular: '', create_vistoriador_user: false, user_email: '' });
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Erro ao cadastrar participante.');
    } finally {
      setNovoLoading(false);
    }
  };

  const salvar = async () => {
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
      toast.success('Vistoria criada com sucesso.');
      setLocation(`/vistorias/${data?.vistoria?.id || data?.id || ''}/execucao`);
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Erro ao criar vistoria.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="flex">
      <Sidebar />
      <div className="page-shell">
        <div className="max-w-5xl mx-auto space-y-6">
          <div className="page-header">
            <div>
              <h1 className="page-title">Wizard de Vistoria</h1>
              <p className="page-subtitle">Fluxo passo a passo para cadastrar vistoria com participantes novos e vínculo operacional.</p>
            </div>
            <Link to="/vistorias" className="rounded-xl border border-white/15 bg-white/5 px-4 py-2 text-sm">Voltar</Link>
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
              {step === 0 && (
                <div className="space-y-4">
                  <div className="flex gap-2">
                    <button onClick={() => setForm((p) => ({ ...p, imovel_modo: 'cadastro' }))} className={`rounded-xl px-4 py-2 border ${form.imovel_modo === 'cadastro' ? 'border-blue-500 bg-blue-500/20' : 'border-white/10'}`}>Imóvel cadastrado</button>
                    <button onClick={() => setForm((p) => ({ ...p, imovel_modo: 'livre', contrato_id: '', imovel_id: '' }))} className={`rounded-xl px-4 py-2 border ${form.imovel_modo === 'livre' ? 'border-cyan-500 bg-cyan-500/20' : 'border-white/10'}`}>Novo local (sem cadastro)</button>
                  </div>
                  {form.imovel_modo === 'cadastro' ? (
                    <div className="grid md:grid-cols-2 gap-3">
                      <select value={form.contrato_id} onChange={(e) => setForm((p) => ({ ...p, contrato_id: e.target.value }))} className="rounded-xl border border-white/10 bg-white/5 px-3 py-2.5">
                        <option value="">Contrato (opcional)</option>
                        {contratos.map((c) => <option key={c.id} value={c.id}>{c.numero_contrato || `#${c.id}`}</option>)}
                      </select>
                      <select value={form.imovel_id} onChange={(e) => setForm((p) => ({ ...p, imovel_id: e.target.value }))} className="rounded-xl border border-white/10 bg-white/5 px-3 py-2.5">
                        <option value="">Imóvel</option>
                        {imoveis.map((i) => <option key={i.id} value={i.id}>{i.titulo || i.codigo || `#${i.id}`}</option>)}
                      </select>
                    </div>
                  ) : (
                    <div className="grid md:grid-cols-2 gap-3">
                      <input value={form.imovel_livre.titulo} onChange={(e) => setForm((p) => ({ ...p, imovel_livre: { ...p.imovel_livre, titulo: e.target.value } }))} placeholder="Título/referência" className="rounded-xl border border-white/10 bg-white/5 px-3 py-2.5" />
                      <input value={form.imovel_livre.logradouro} onChange={(e) => setForm((p) => ({ ...p, imovel_livre: { ...p.imovel_livre, logradouro: e.target.value } }))} placeholder="Logradouro" className="rounded-xl border border-white/10 bg-white/5 px-3 py-2.5" />
                      <input value={form.imovel_livre.bairro} onChange={(e) => setForm((p) => ({ ...p, imovel_livre: { ...p.imovel_livre, bairro: e.target.value } }))} placeholder="Bairro" className="rounded-xl border border-white/10 bg-white/5 px-3 py-2.5" />
                      <input value={form.imovel_livre.cidade} onChange={(e) => setForm((p) => ({ ...p, imovel_livre: { ...p.imovel_livre, cidade: e.target.value } }))} placeholder="Cidade" className="rounded-xl border border-white/10 bg-white/5 px-3 py-2.5" />
                    </div>
                  )}
                </div>
              )}

              {step === 1 && (
                <div className="space-y-4">
                  <Select isMulti options={pessoaOpts} value={pessoaOpts.filter((o) => form.participantes_ids.includes(o.value))} onChange={(v) => setForm((p) => ({ ...p, participantes_ids: (v || []).map((x) => x.value) }))} placeholder="Buscar participantes existentes..." />
                  <div className="rounded-xl border border-white/10 p-3 space-y-2">
                    <p className="text-sm font-semibold">Cadastrar novo participante agora</p>
                    <div className="grid md:grid-cols-2 gap-2">
                      <input value={novoParticipante.nome} onChange={(e) => setNovoParticipante((p) => ({ ...p, nome: e.target.value }))} placeholder="Nome" className="rounded-lg border border-white/10 bg-white/5 px-3 py-2" />
                      <input value={novoParticipante.email} onChange={(e) => setNovoParticipante((p) => ({ ...p, email: e.target.value }))} placeholder="Email" className="rounded-lg border border-white/10 bg-white/5 px-3 py-2" />
                      <input value={novoParticipante.celular} onChange={(e) => setNovoParticipante((p) => ({ ...p, celular: e.target.value }))} placeholder="Celular" className="rounded-lg border border-white/10 bg-white/5 px-3 py-2" />
                      <label className="inline-flex items-center gap-2 text-sm"><input type="checkbox" checked={novoParticipante.create_vistoriador_user} onChange={(e) => setNovoParticipante((p) => ({ ...p, create_vistoriador_user: e.target.checked }))} />Criar login de vistoriador (senha padrão exclusiva + troca no 1º acesso)</label>
                    </div>
                    {novoParticipante.create_vistoriador_user ? (
                      <input value={novoParticipante.user_email} onChange={(e) => setNovoParticipante((p) => ({ ...p, user_email: e.target.value }))} placeholder="Email de acesso do vistoriador" className="w-full rounded-lg border border-white/10 bg-white/5 px-3 py-2" />
                    ) : null}
                    <button disabled={novoLoading} onClick={criarParticipante} className="rounded-lg bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground">{novoLoading ? 'Criando...' : 'Cadastrar participante'}</button>
                  </div>
                </div>
              )}

              {step === 2 && (
                <div className="grid md:grid-cols-2 gap-3">
                  <input value={form.cliente_nome} onChange={(e) => setForm((p) => ({ ...p, cliente_nome: e.target.value }))} placeholder="Cliente / solicitante" className="rounded-xl border border-white/10 bg-white/5 px-3 py-2.5" />
                  <select value={form.tipo} onChange={(e) => setForm((p) => ({ ...p, tipo: e.target.value }))} className="rounded-xl border border-white/10 bg-white/5 px-3 py-2.5">
                    <option value="entrada">Entrada</option>
                    <option value="saida">Saída</option>
                    <option value="periodica">Periódica</option>
                  </select>
                  <input type="datetime-local" value={form.data_vistoria} onChange={(e) => setForm((p) => ({ ...p, data_vistoria: e.target.value }))} className="rounded-xl border border-white/10 bg-white/5 px-3 py-2.5" />
                  <select value={form.responsavel_pessoa_id} onChange={(e) => setForm((p) => ({ ...p, responsavel_pessoa_id: e.target.value }))} className="rounded-xl border border-white/10 bg-white/5 px-3 py-2.5">
                    <option value="">Responsável (opcional)</option>
                    {pessoas.map((x) => <option key={x.id} value={x.id}>{x.nome}</option>)}
                  </select>
                  <textarea value={form.observacoes} onChange={(e) => setForm((p) => ({ ...p, observacoes: e.target.value }))} rows={4} placeholder="Observações iniciais" className="md:col-span-2 rounded-xl border border-white/10 bg-white/5 px-3 py-2.5" />
                </div>
              )}

              {step === 3 && (
                <div className="text-sm space-y-2">
                  <p><strong>Modo imóvel:</strong> {form.imovel_modo === 'cadastro' ? 'Cadastro existente' : 'Novo local livre'}</p>
                  <p><strong>Participantes:</strong> {form.participantes_ids.length}</p>
                  <p><strong>Tipo:</strong> {form.tipo}</p>
                  <p><strong>Data:</strong> {form.data_vistoria || 'Não definida'}</p>
                  <p><strong>Cliente:</strong> {form.cliente_nome || '—'}</p>
                  <p className="text-muted-foreground">Ao criar, você será enviado para o wizard de execução para anexar fotos, vídeos e comentários.</p>
                </div>
              )}

              <div className="flex justify-between pt-2">
                <button onClick={prev} disabled={step === 0} className="rounded-xl border border-white/10 px-4 py-2 text-sm disabled:opacity-40">Anterior</button>
                {step < steps.length - 1 ? (
                  <button onClick={next} className="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground">Próximo</button>
                ) : (
                  <button onClick={salvar} disabled={saving} className="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white">{saving ? 'Salvando...' : 'Criar vistoria e abrir execução'}</button>
                )}
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}

