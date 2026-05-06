import { useEffect, useMemo, useState } from 'react';
import { motion } from 'framer-motion';
import { Building2, ClipboardCheck, Loader2, Pencil, Plus, Search, Trash2, Users, X } from 'lucide-react';
import Select from 'react-select';
import { toast } from 'sonner';
import { Link } from 'wouter';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog';

type Pessoa = { id: number; nome: string; email?: string | null; celular?: string | null };
type Imovel = { id: number; codigo?: string | null; titulo?: string | null; endereco?: string | null; area_total?: number | null; metragem?: number | null; logradouro?: string | null; bairro?: string | null; cidade?: string | null; estado?: string | null };
type Contrato = { id: number; numero_contrato?: string | null; locador?: Pessoa | null; locatario?: Pessoa | null; imovel?: Imovel | null };
type ImovelLivrePayload = { titulo?: string; logradouro?: string; bairro?: string; cidade?: string; estado?: string; tipo_imovel?: string; referencia?: string };
type Vistoria = {
  id: number;
  codigo: string | null;
  status: string;
  tipo: string | null;
  cliente_nome: string | null;
  contrato_id: number | null;
  imovel_id: number | null;
  imovel_livre?: ImovelLivrePayload | null;
  responsavel_pessoa_id: number | null;
  participantes_ids?: number[];
  participantes_nomes?: string[];
  vistoriadores?: string[];
  metragem?: string | number | null;
  mobiliado?: boolean | null;
  data_vistoria?: string | null;
  observacoes?: string | null;
  assinatura_inquilino_status?: string | null;
  assinatura_proprietario_status?: string | null;
  contrato?: Contrato | null;
  imovel?: Imovel | null;
  responsavel?: Pessoa | null;
  fotos?: Array<{ id: number }>;
};

type ImovelModo = 'cadastro' | 'livre';

type ImovelLivreFields = {
  titulo: string;
  logradouro: string;
  bairro: string;
  cidade: string;
  estado: string;
  tipo_imovel: string;
  referencia: string;
};
type PessoaOption = { value: number; label: string };

type FormState = {
  id: number;
  imovel_modo: ImovelModo;
  imovel_livre: ImovelLivreFields;
  codigo: string;
  status: string;
  tipo: string;
  contrato_id: string;
  imovel_id: string;
  responsavel_pessoa_id: string;
  participantes_ids: number[];
  vistoriadores: string;
  cliente_nome: string;
  metragem: string;
  mobiliado: boolean;
  data_vistoria: string;
  observacoes: string;
  assinatura_inquilino_status: string;
  assinatura_proprietario_status: string;
};

const emptyImovelLivre = (): ImovelLivreFields => ({
  titulo: '',
  logradouro: '',
  bairro: '',
  cidade: '',
  estado: '',
  tipo_imovel: '',
  referencia: '',
});

const emptyForm = (): FormState => ({
  id: 0,
  imovel_modo: 'cadastro',
  imovel_livre: emptyImovelLivre(),
  codigo: '',
  status: 'solicitada',
  tipo: 'entrada',
  contrato_id: '',
  imovel_id: '',
  responsavel_pessoa_id: '',
  participantes_ids: [],
  vistoriadores: '',
  cliente_nome: '',
  metragem: '',
  mobiliado: false,
  data_vistoria: '',
  observacoes: '',
  assinatura_inquilino_status: 'pendente',
  assinatura_proprietario_status: 'pendente',
});
const emptyFilters = () => ({ codigo: '', cliente: '', status: 'todos', tipo: 'todos', contrato_id: '', imovel_id: '', responsavel_pessoa_id: '' });

const statusOptions = [['solicitada','Solicitada'],['designada','Designada'],['andamento','Em andamento'],['concluida','Concluída'],['cancelada','Cancelada']];
const tipoOptions = [['entrada','Entrada'],['saida','Saída'],['periodica','Periódica']];
const assinaturaOptions = [['pendente','Pendente'],['enviado','Enviado'],['assinado','Assinado'],['cancelado','Cancelado']];

const formatDate = (value?: string | null) => value ? new Date(value).toLocaleString('pt-BR') : '-';
const formatMetric = (value?: string | number | null) => value ? `${Number(value).toLocaleString('pt-BR', { maximumFractionDigits: 2 })} m²` : '-';
const formatLocalDateTimeInput = (value?: string | null) => {
  if (!value) return '';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '';
  const offset = date.getTimezoneOffset() * 60_000;
  return new Date(date.getTime() - offset).toISOString().slice(0, 16);
};
const parseDecimalInput = (value: string) => {
  const normalized = value?.trim();
  if (!normalized) return null;
  const parsed = Number(normalized.includes(',') ? normalized.replace(/\./g, '').replace(',', '.') : normalized);
  return Number.isFinite(parsed) ? parsed : null;
};
const personText = (p?: Pessoa | null) => p ? `${p.nome}${p.email ? ` • ${p.email}` : p.celular ? ` • ${p.celular}` : ''}` : 'Não definido';
const imovelText = (i?: Imovel | null) => {
  if (!i) return 'Sem imóvel';
  const hasId = i.id != null && i.id > 0;
  const n = i.titulo || (i.codigo ? `Imóvel ${i.codigo}` : hasId ? `Imóvel #${i.id}` : 'Local (vistoria)');
  const e = i.endereco || [i.logradouro, i.bairro, i.cidade].filter(Boolean).join(', ');
  return e ? `${n} • ${e}` : n;
};
const contratoText = (c?: Contrato | null) => c ? `${c.numero_contrato || `#${c.id}`} • ${c.locatario?.nome || 'Sem locatário'}` : 'Sem contrato';
const badgeClass = (status: string) => ({ solicitada: 'bg-amber-500/15 text-amber-300 border-amber-500/30', designada: 'bg-blue-500/15 text-blue-300 border-blue-500/30', andamento: 'bg-violet-500/15 text-violet-300 border-violet-500/30', concluida: 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30', cancelada: 'bg-red-500/15 text-red-300 border-red-500/30' }[status] || 'bg-white/10 text-foreground border-white/10');

/** Campos com altura tocável e texto ≥16px no mobile (evita zoom iOS em foco). */
const fieldTouchClass = 'min-h-11 text-base sm:min-h-10 sm:text-sm';

export default function Vistorias() {
  const [vistorias, setVistorias] = useState<Vistoria[]>([]);
  const [pessoas, setPessoas] = useState<Pessoa[]>([]);
  const [imoveis, setImoveis] = useState<Imovel[]>([]);
  const [contratos, setContratos] = useState<Contrato[]>([]);
  const [filters, setFilters] = useState(emptyFilters());
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [showForm, setShowForm] = useState(false);
  const [showAdvancedCreate, setShowAdvancedCreate] = useState(false);
  const [form, setForm] = useState<FormState>(emptyForm());
  const [deleteId, setDeleteId] = useState<number | null>(null);
  const [filtersExtraOpen, setFiltersExtraOpen] = useState(false);
  const [filterMinhas, setFilterMinhas] = useState(false);
  const [profile, setProfile] = useState<{ role: string; pessoa_id: number | null }>(() => {
    try {
      const raw = localStorage.getItem('user');
      if (!raw) return { role: '', pessoa_id: null };
      const u = JSON.parse(raw);
      const pid = u?.pessoa_id;
      return {
        role: String(u?.role || '').toLowerCase(),
        pessoa_id: pid != null && pid !== '' ? Number(pid) : null,
      };
    } catch {
      return { role: '', pessoa_id: null };
    }
  });

  useEffect(() => {
    api
      .get('/auth/me')
      .then((res) => {
        const d = res.data?.data;
        if (!d) return;
        try {
          const prev = JSON.parse(localStorage.getItem('user') || '{}');
          localStorage.setItem('user', JSON.stringify({ ...prev, ...d }));
        } catch {
          localStorage.setItem('user', JSON.stringify(d));
        }
        const pid = d.pessoa_id;
        setProfile({
          role: String(d.role || '').toLowerCase(),
          pessoa_id: pid != null && pid !== '' ? Number(pid) : null,
        });
      })
      .catch(() => {});
  }, []);

  const isAdminLike = ['admin', 'super_admin'].includes(profile.role);
  const isFieldUser = ['corretor', 'agent', 'trainee'].includes(profile.role);
  const pessoaLinked = profile.pessoa_id != null && Number.isFinite(profile.pessoa_id) && profile.pessoa_id > 0;

  const stats = useMemo(() => vistorias.reduce((acc, item) => ({ total: acc.total + 1, solicitada: acc.solicitada + (item.status === 'solicitada' ? 1 : 0), andamento: acc.andamento + ((item.status === 'designada' || item.status === 'andamento') ? 1 : 0), concluida: acc.concluida + (item.status === 'concluida' ? 1 : 0) }), { total: 0, solicitada: 0, andamento: 0, concluida: 0 }), [vistorias]);

  const currentContrato =
    form.imovel_modo === 'cadastro' ? contratos.find((item) => String(item.id) === form.contrato_id) || null : null;

  const currentImovel = useMemo(() => {
    if (form.imovel_modo === 'livre') {
      const liv = form.imovel_livre;
      const parts = [liv.logradouro, liv.bairro, liv.cidade, liv.estado].filter(Boolean).join(', ');
      if (!liv.titulo && !parts) return null;
      return {
        id: 0,
        codigo: null,
        titulo: liv.titulo || null,
        logradouro: liv.logradouro || null,
        bairro: liv.bairro || null,
        cidade: liv.cidade || null,
        estado: liv.estado || null,
        endereco: parts,
      } as Imovel;
    }
    return currentContrato?.imovel || imoveis.find((item) => String(item.id) === form.imovel_id) || null;
  }, [form.imovel_modo, form.imovel_livre, form.imovel_id, currentContrato, imoveis]);

  const participantesOptions = useMemo<PessoaOption[]>(
    () => pessoas.map((item) => ({ value: item.id, label: item.nome })),
    [pessoas]
  );

  const getParams = (nextPage = page, minhasFlag = filterMinhas) => {
    const params: Record<string, string | number> = { page: nextPage, per_page: 12 };
    Object.entries(filters).forEach(([key, value]) => {
      if (value && value !== 'todos') params[key] = value;
    });
    if (minhasFlag && pessoaLinked) {
      params.somente_minhas = '1';
    }
    return params;
  };

  const loadVistorias = async (nextPage = page, opts?: { minhas?: boolean }) => {
    setLoading(true);
    const minhas = opts?.minhas ?? filterMinhas;
    try {
      const { data } = await api.get('/vistorias', { params: getParams(nextPage, minhas) });
      setVistorias(data.data || []);
      setPage(data.current_page || 1);
      setLastPage(data.last_page || 1);
    } catch (error) {
      console.error(error);
      toast.error('Erro ao carregar vistorias.');
    } finally {
      setLoading(false);
    }
  };

  const loadMeta = async () => {
    const { data } = await api.get('/vistorias/meta');
    setPessoas(data.pessoas || []);
    setImoveis((data.imoveis || []).map((item: any) => ({ ...item, endereco: [item.logradouro, item.bairro, item.cidade, item.estado].filter(Boolean).join(', '), metragem: item.area_total })));
    setContratos(data.contratos || []);
  };

  useEffect(() => {
    Promise.all([loadMeta(), loadVistorias(1)]).catch(() => toast.error('Erro ao carregar dados de vistoria.'));
  }, []);
  useEffect(() => { if (page !== 1) loadVistorias(page); }, [page]);

  const applyFilters = () => {
    setPage(1);
    loadVistorias(1);
  };
  const resetFilters = () => {
    setFilters(emptyFilters());
    setFilterMinhas(false);
    setPage(1);
    setTimeout(() => loadVistorias(1, { minhas: false }), 0);
  };

  const openCreate = () => { setForm(emptyForm()); setShowAdvancedCreate(false); setShowForm(true); };
  const openEdit = (item: Vistoria) => {
    const liv = item.imovel_livre || {};
    const modoLivre = !item.contrato_id && !item.imovel_id && Boolean(item.imovel_livre && Object.keys(item.imovel_livre).length > 0);
    setForm({
      id: item.id,
      imovel_modo: modoLivre ? 'livre' : 'cadastro',
      imovel_livre: {
        titulo: liv.titulo ?? '',
        logradouro: liv.logradouro ?? '',
        bairro: liv.bairro ?? '',
        cidade: liv.cidade ?? '',
        estado: liv.estado ?? '',
        tipo_imovel: liv.tipo_imovel ?? '',
        referencia: liv.referencia ?? '',
      },
      codigo: item.codigo || '',
      status: item.status,
      tipo: item.tipo || 'entrada',
      contrato_id: item.contrato_id ? String(item.contrato_id) : '',
      imovel_id: item.imovel_id ? String(item.imovel_id) : '',
      responsavel_pessoa_id: item.responsavel_pessoa_id ? String(item.responsavel_pessoa_id) : '',
      participantes_ids: item.participantes_ids || [],
      vistoriadores: (item.vistoriadores || []).join(', '),
      cliente_nome: item.cliente_nome || '',
      metragem: item.metragem ? String(item.metragem) : '',
      mobiliado: Boolean(item.mobiliado),
      data_vistoria: formatLocalDateTimeInput(item.data_vistoria),
      observacoes: item.observacoes || '',
      assinatura_inquilino_status: item.assinatura_inquilino_status || 'pendente',
      assinatura_proprietario_status: item.assinatura_proprietario_status || 'pendente',
    });
    setShowAdvancedCreate(true);
    setShowForm(true);
  };

  const onContratoChange = (value: string) => {
    const contrato = contratos.find((item) => String(item.id) === value);
    setForm((prev) => ({
      ...prev,
      imovel_modo: 'cadastro',
      imovel_livre: emptyImovelLivre(),
      contrato_id: value,
      imovel_id: contrato?.imovel?.id ? String(contrato.imovel.id) : prev.imovel_id,
      cliente_nome: contrato?.locatario?.nome || prev.cliente_nome,
      participantes_ids: contrato ? [contrato.locador?.id, contrato.locatario?.id].filter((id): id is number => Boolean(id)) : prev.participantes_ids,
      metragem: contrato?.imovel?.metragem || contrato?.imovel?.area_total ? String(contrato.imovel?.metragem || contrato.imovel?.area_total) : prev.metragem,
    }));
  };

  const buildImovelLivrePayload = (): ImovelLivrePayload => {
    const liv = form.imovel_livre;
    const out: ImovelLivrePayload = {};
    (['titulo', 'logradouro', 'bairro', 'cidade', 'estado', 'tipo_imovel', 'referencia'] as const).forEach((key) => {
      const v = liv[key]?.trim();
      if (v) out[key] = key === 'estado' ? v.slice(0, 2).toUpperCase() : v;
    });
    return out;
  };

  const save = async () => {
    if (form.imovel_modo === 'cadastro') {
      if (!form.contrato_id && !form.imovel_id) return toast.error('Selecione um contrato ou um imóvel cadastrado.');
    } else {
      const liv = buildImovelLivrePayload();
      const hasAny =
        Boolean(liv.titulo) ||
        Boolean(liv.logradouro) ||
        Boolean(liv.bairro) ||
        Boolean(liv.cidade) ||
        Boolean(liv.estado) ||
        Boolean(liv.tipo_imovel) ||
        Boolean(liv.referencia);
      if (!hasAny) return toast.error('Preencha pelo menos título, endereço ou cidade do local na vistoria.');
    }
    setSaving(true);
    try {
      const imovelLivrePayload = form.imovel_modo === 'livre' ? buildImovelLivrePayload() : null;

      const payload = {
        codigo: form.codigo || null,
        status: form.id === 0 ? (form.data_vistoria ? 'designada' : 'solicitada') : form.status,
        tipo: form.tipo,
        contrato_id: form.imovel_modo === 'livre' ? null : form.contrato_id ? Number(form.contrato_id) : null,
        imovel_id:
          form.imovel_modo === 'livre'
            ? null
            : form.contrato_id
              ? null
              : form.imovel_id
                ? Number(form.imovel_id)
                : null,
        imovel_livre: form.imovel_modo === 'livre' ? imovelLivrePayload : null,
        responsavel_pessoa_id: form.responsavel_pessoa_id ? Number(form.responsavel_pessoa_id) : null,
        participantes_ids: form.participantes_ids,
        vistoriadores: form.vistoriadores
          .split(',')
          .map((item) => item.trim())
          .filter(Boolean),
        cliente_nome: form.cliente_nome || null,
        metragem: parseDecimalInput(form.metragem),
        mobiliado: form.mobiliado,
        data_vistoria: form.data_vistoria || null,
        observacoes: form.observacoes || null,
        assinatura_inquilino_status: form.assinatura_inquilino_status,
        assinatura_proprietario_status: form.assinatura_proprietario_status,
      };
      if (form.id === 0) await api.post('/vistorias', payload);
      else await api.put(`/vistorias/${form.id}`, payload);
      toast.success(form.id === 0 ? 'Vistoria criada.' : 'Vistoria atualizada.');
      setShowForm(false); setForm(emptyForm()); setPage(1); await loadVistorias(1);
    } catch (error: any) {
      console.error(error);
      toast.error(error?.response?.data?.message || 'Erro ao salvar vistoria.');
    } finally { setSaving(false); }
  };

  const remove = async () => {
    if (!deleteId) return;
    try { await api.delete(`/vistorias/${deleteId}`); toast.success('Vistoria excluída.'); setDeleteId(null); await loadVistorias(page); }
    catch (error: any) { console.error(error); toast.error(error?.response?.data?.message || 'Erro ao excluir vistoria.'); }
  };

  return (
    <>
    <div className="flex">
      <Sidebar />
      <div className="page-shell">
        <motion.div initial={{ opacity: 0, y: 14 }} animate={{ opacity: 1, y: 0 }} className="max-w-7xl mx-auto space-y-6">
    <div className="page-header"><div><h1 className="page-title mb-2">Central de Vistorias</h1><p className="page-subtitle">Módulo próprio para operar visitas ao imóvel: use o cadastro quando existir ou descreva o local só aqui.</p>{isFieldUser ? <p className="mt-3 rounded-xl border border-cyan-500/20 bg-cyan-500/5 px-3 py-2 text-xs leading-relaxed text-muted-foreground sm:text-sm"><span className="font-medium text-foreground">Área operacional:</span> marque <strong className="text-foreground">Minhas visitas</strong> quando seu usuário estiver vinculado a uma pessoa, ou use <strong className="text-foreground">Filtrar por responsável</strong> nos filtros avançados.</p> : null}</div><div className="flex w-full flex-col gap-2 sm:w-auto sm:flex-row"><button type="button" onClick={openCreate} className="flex min-h-11 touch-manipulation items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-blue-500 to-cyan-500 px-5 py-3 text-base font-semibold text-white sm:min-h-10 sm:text-sm"><Plus size={18} aria-hidden />Nova vistoria</button><Link to="/vistorias/solicitacoes" className="flex min-h-11 touch-manipulation items-center justify-center rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-center text-base font-medium text-foreground sm:min-h-10 sm:text-sm">Solicitações</Link><Link to="/vistorias/contestacoes" className="flex min-h-11 touch-manipulation items-center justify-center rounded-xl border border-white/10 bg-white/5 px-4 py-3 text-center text-base font-medium text-foreground sm:min-h-10 sm:text-sm">Contestações</Link></div></div>

    <div className="grid grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">{[{ label: 'No painel', value: stats.total, icon: ClipboardCheck }, { label: 'Solicitadas', value: stats.solicitada, icon: Users }, { label: 'Em operação', value: stats.andamento, icon: Building2 }, { label: 'Concluídas', value: stats.concluida, icon: ClipboardCheck }].map((card) => <div key={card.label} className="glass-panel rounded-2xl p-5"><div className="mb-3 flex items-center justify-between text-muted-foreground"><card.icon size={18} /><span className="text-xs uppercase tracking-[0.22em]">Vistorias</span></div><p className="text-sm text-muted-foreground">{card.label}</p><p className="mt-2 text-3xl font-semibold text-foreground">{card.value}</p></div>)}</div>

    <div className="glass-panel rounded-2xl p-5 space-y-4"><div className="flex flex-wrap items-center gap-3">{pessoaLinked ? <label className="flex min-h-11 cursor-pointer touch-manipulation items-center gap-2 rounded-xl border border-cyan-500/25 bg-cyan-500/5 px-3 py-2 text-sm text-cyan-100 sm:min-h-10"><input type="checkbox" className="h-4 w-4 rounded border-white/30 bg-white/10" checked={filterMinhas} onChange={(e) => { const v = e.target.checked; setFilterMinhas(v); setPage(1); loadVistorias(1, { minhas: v }); }} />Minhas visitas (sou responsável)</label> : null}{!pessoaLinked && isFieldUser ? <span className="text-xs leading-relaxed text-muted-foreground sm:text-sm">Para listar só as visitas em que você é o responsável, peça ao admin para vincular seu usuário a uma pessoa em <strong className="text-foreground">Usuários</strong>.</span> : null}</div><div className="grid gap-3 md:grid-cols-4"><label className="relative block"><Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" /><input value={filters.codigo} onChange={(e) => setFilters((prev) => ({ ...prev, codigo: e.target.value }))} placeholder="Código" className="w-full rounded-xl border border-white/10 bg-white/5 py-2.5 pl-9 pr-3 text-sm text-foreground" /></label><input value={filters.cliente} onChange={(e) => setFilters((prev) => ({ ...prev, cliente: e.target.value }))} placeholder="Cliente" className="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 text-sm text-foreground" /><select value={filters.status} onChange={(e) => setFilters((prev) => ({ ...prev, status: e.target.value }))} className="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 text-sm text-foreground"><option value="todos">Todos os status</option>{statusOptions.map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select><select value={filters.tipo} onChange={(e) => setFilters((prev) => ({ ...prev, tipo: e.target.value }))} className="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 text-sm text-foreground"><option value="todos">Todos os tipos</option>{tipoOptions.map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></div><button type="button" onClick={() => setFiltersExtraOpen((v) => !v)} className="touch-manipulation rounded-xl border border-dashed border-white/20 bg-white/5 py-3 text-base text-muted-foreground md:hidden sm:text-sm">{filtersExtraOpen ? 'Ocultar filtros extras' : 'Mais filtros — contrato, imóvel, responsável'}</button><div className={`grid gap-3 md:grid-cols-3 ${filtersExtraOpen ? '' : 'hidden md:grid'}`}><select value={filters.contrato_id} onChange={(e) => setFilters((prev) => ({ ...prev, contrato_id: e.target.value }))} className="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 text-sm text-foreground"><option value="">Filtrar por contrato</option>{contratos.map((item) => <option key={item.id} value={item.id}>{contratoText(item)}</option>)}</select><select value={filters.imovel_id} onChange={(e) => setFilters((prev) => ({ ...prev, imovel_id: e.target.value }))} className="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 text-sm text-foreground"><option value="">Filtrar por imóvel</option>{imoveis.map((item) => <option key={item.id} value={item.id}>{imovelText(item)}</option>)}</select><select value={filters.responsavel_pessoa_id} onChange={(e) => setFilters((prev) => ({ ...prev, responsavel_pessoa_id: e.target.value }))} className="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 text-sm text-foreground"><option value="">Filtrar por responsável</option>{pessoas.map((item) => <option key={item.id} value={item.id}>{item.nome}</option>)}</select></div><div className="flex items-center justify-end gap-2"><button onClick={resetFilters} className="rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-foreground">Limpar</button><button onClick={applyFilters} className="rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground">Aplicar</button></div></div>

    <div className="glass-panel rounded-2xl p-5">{loading ? <div className="flex items-center justify-center py-16 text-muted-foreground"><Loader2 className="mr-2 h-6 w-6 animate-spin" />Carregando vistorias...</div> : vistorias.length === 0 ? <div className="rounded-2xl border border-dashed border-white/10 px-6 py-14 text-center text-sm text-muted-foreground">Nenhuma vistoria encontrada.</div> : <div className="grid gap-4 lg:grid-cols-2">{vistorias.map((item) => <div key={item.id} className="rounded-2xl border border-white/10 bg-white/5 p-4 sm:p-5"><div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4"><div className="min-w-0"><div className="mb-2 flex flex-wrap items-center gap-2"><span className={`inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold ${badgeClass(item.status)}`}>{statusOptions.find(([value]) => value === item.status)?.[1] || item.status}</span><span className="rounded-full border border-white/10 px-2.5 py-1 text-xs text-muted-foreground">{tipoOptions.find(([value]) => value === item.tipo)?.[1] || item.tipo || '-'}</span></div><h3 className="text-lg font-semibold text-foreground">{item.codigo || `Vistoria #${item.id}`}</h3><p className="mt-1 text-sm text-muted-foreground">{item.cliente_nome || item.contrato?.locatario?.nome || 'Sem cliente vinculado'}</p></div><div className="flex flex-wrap gap-2 sm:shrink-0 sm:justify-end"><Link to={`/vistorias/${item.id}`} className="inline-flex min-h-10 flex-1 touch-manipulation items-center justify-center rounded-xl border border-white/10 px-3 py-2 text-center text-sm text-foreground sm:flex-none">Detalhe</Link><button type="button" onClick={() => { openEdit(item); setShowForm(true); }} className="inline-flex min-h-10 flex-1 touch-manipulation items-center justify-center gap-1 rounded-xl border border-blue-500/30 bg-blue-500/10 px-3 py-2 text-sm text-blue-300 sm:flex-none"><Pencil size={14} aria-hidden /><span>Editar</span></button>{isAdminLike ? <button type="button" onClick={() => setDeleteId(item.id)} className="inline-flex min-h-10 flex-1 touch-manipulation items-center justify-center gap-1 rounded-xl border border-red-500/30 bg-red-500/10 px-3 py-2 text-sm text-red-300 sm:w-auto"><Trash2 size={14} aria-hidden /><span>Excluir</span></button> : null}</div></div><div className="grid gap-3 md:grid-cols-2"><div className="rounded-xl border border-white/10 bg-black/10 p-3"><p className="mb-1 text-xs uppercase tracking-[0.18em] text-muted-foreground">Contrato</p><p className="text-sm text-foreground">{contratoText(item.contrato)}</p></div><div className="rounded-xl border border-white/10 bg-black/10 p-3"><p className="mb-1 text-xs uppercase tracking-[0.18em] text-muted-foreground">Imóvel</p><p className="text-sm text-foreground">{imovelText(item.imovel || item.contrato?.imovel)}</p></div><div className="rounded-xl border border-white/10 bg-black/10 p-3"><p className="mb-1 text-xs uppercase tracking-[0.18em] text-muted-foreground">Responsável</p><p className="text-sm text-foreground">{personText(item.responsavel)}</p></div><div className="rounded-xl border border-white/10 bg-black/10 p-3"><p className="mb-1 text-xs uppercase tracking-[0.18em] text-muted-foreground">Execução</p><p className="text-sm text-foreground">{formatDate(item.data_vistoria)}</p><p className="mt-1 text-xs text-muted-foreground">{formatMetric(item.metragem)} • {item.mobiliado ? 'Mobiliado' : 'Sem mobília'}</p></div></div><div className="mt-4 rounded-xl border border-white/10 bg-black/10 p-3"><p className="mb-2 text-xs uppercase tracking-[0.18em] text-muted-foreground">Participantes</p><p className="text-sm text-foreground">{item.participantes_nomes?.length ? item.participantes_nomes.join(', ') : 'Sem participantes vinculados.'}</p></div>{item.observacoes ? <p className="mt-4 text-sm leading-6 text-muted-foreground">{item.observacoes}</p> : null}</div>)}</div>}<div className="mt-6 flex items-center justify-center gap-3"><button type="button" onClick={() => setPage((prev) => Math.max(1, prev - 1))} disabled={page <= 1} className="min-h-10 touch-manipulation rounded-xl border border-white/10 px-4 py-2 text-sm text-foreground disabled:opacity-40">Anterior</button><span className="text-sm text-muted-foreground">Página {page} de {lastPage}</span><button type="button" onClick={() => setPage((prev) => Math.min(lastPage, prev + 1))} disabled={page >= lastPage} className="min-h-10 touch-manipulation rounded-xl border border-white/10 px-4 py-2 text-sm text-foreground disabled:opacity-40">Próxima</button></div></div>
        </motion.div>
      </div>
    </div>

  {showForm ? (
  <div className="fixed inset-0 z-50 flex items-end justify-center bg-black/70 backdrop-blur-sm sm:items-center sm:p-4"><div className="glass-panel flex max-h-[100dvh] w-full max-w-5xl flex-col overflow-hidden rounded-t-2xl border border-border shadow-2xl sm:max-h-[92vh] sm:rounded-3xl"><div className="flex-1 overflow-y-auto overscroll-y-contain px-4 pt-4 pb-2 sm:p-6 sm:pb-4"><div className="mb-6 flex items-start justify-between gap-3"><div><h2 className="text-xl font-semibold text-foreground sm:text-2xl">{form.id === 0 ? 'Nova vistoria' : `Editar ${form.codigo || 'vistoria #' + String(form.id)}`}</h2><p className="mt-1 text-sm text-muted-foreground">{form.id === 0 ? 'Escolha vínculo com cadastro ou informe o local direto nesta tela — não é obrigatório ter imóvel pré-cadastrado.' : 'Ajuste os dados da vistoria.'}</p></div><button type="button" onClick={() => setShowForm(false)} className="touch-manipulation rounded-xl border border-white/10 p-2.5 text-muted-foreground hover:text-foreground" aria-label="Fechar"><X size={18} /></button></div><div className="grid gap-6 lg:grid-cols-2"><div className="space-y-5"><section className="rounded-2xl border border-white/10 bg-black/10 p-4"><p className="text-sm font-semibold text-foreground">{form.id === 0 ? 'Criação rápida' : 'Vínculo e execução'}</p><div className="mt-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap"><button type="button" onClick={() => setForm((prev) => ({ ...prev, imovel_modo: 'cadastro', imovel_livre: emptyImovelLivre() }))} className={`touch-manipulation rounded-xl border px-4 py-3 text-left text-sm font-semibold transition sm:py-2 sm:text-xs ${form.imovel_modo === 'cadastro' ? 'border-blue-500/50 bg-blue-500/15 text-blue-100' : 'border-white/10 bg-white/5 text-muted-foreground hover:bg-white/10'}`}>Catálogo — contrato ou imóvel cadastrado</button><button type="button" onClick={() => setForm((prev) => ({ ...prev, imovel_modo: 'livre', contrato_id: '', imovel_id: '' }))} className={`touch-manipulation rounded-xl border px-4 py-3 text-left text-sm font-semibold transition sm:py-2 sm:text-xs ${form.imovel_modo === 'livre' ? 'border-cyan-500/50 bg-cyan-500/15 text-cyan-100' : 'border-white/10 bg-white/5 text-muted-foreground hover:bg-white/10'}`}>Somente nesta ficha (endereço livre)</button></div><p className="mt-2 text-xs leading-relaxed text-muted-foreground">{form.imovel_modo === 'livre' ? 'Descreva o endereço ou um título de referência. Os dados ficam nesta vistoria; você pode vincular a um imóvel cadastrado depois, pela edição.' : 'Contrato preenche partes e imóvel automaticamente; ou escolha só o imóvel do catálogo, sem contrato.'}</p>{form.imovel_modo === 'cadastro' ? <div className="mt-4 grid gap-4 md:grid-cols-2"><div><label className="mb-1.5 block text-xs uppercase tracking-[0.16em] text-muted-foreground">Contrato</label><select value={form.contrato_id} onChange={(e) => onContratoChange(e.target.value)} className={`w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 ${fieldTouchClass} text-foreground`}><option value="">Opcional</option>{contratos.map((item) => <option key={item.id} value={item.id}>{contratoText(item)}</option>)}</select></div><div><label className="mb-1.5 block text-xs uppercase tracking-[0.16em] text-muted-foreground">Imóvel</label><select value={form.imovel_id} onChange={(e) => setForm((prev) => ({ ...prev, imovel_modo: 'cadastro', imovel_id: e.target.value, imovel_livre: emptyImovelLivre() }))} className={`w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 ${fieldTouchClass} text-foreground`}><option value="">Opcional se já tiver contrato</option>{imoveis.map((item) => <option key={item.id} value={item.id}>{imovelText(item)}</option>)}</select></div></div> : <div className="mt-4 grid gap-4 md:grid-cols-2"><div className="md:col-span-2"><label className="mb-1.5 block text-xs uppercase tracking-[0.16em] text-muted-foreground">Título ou referência</label><input value={form.imovel_livre.titulo} onChange={(e) => setForm((p) => ({ ...p, imovel_livre: { ...p.imovel_livre, titulo: e.target.value } }))} placeholder="Ex.: Cobertura visita técnica — cliente Maria" className={`w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 ${fieldTouchClass} text-foreground`} /></div><div className="md:col-span-2"><label className="mb-1.5 block text-xs uppercase tracking-[0.16em] text-muted-foreground">Logradouro</label><input value={form.imovel_livre.logradouro} onChange={(e) => setForm((p) => ({ ...p, imovel_livre: { ...p.imovel_livre, logradouro: e.target.value } }))} placeholder="Rua, número, complemento" className={`w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 ${fieldTouchClass} text-foreground`} /></div><div><label className="mb-1.5 block text-xs uppercase tracking-[0.16em] text-muted-foreground">Bairro</label><input value={form.imovel_livre.bairro} onChange={(e) => setForm((p) => ({ ...p, imovel_livre: { ...p.imovel_livre, bairro: e.target.value } }))} className={`w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 ${fieldTouchClass} text-foreground`} /></div><div><label className="mb-1.5 block text-xs uppercase tracking-[0.16em] text-muted-foreground">Cidade</label><input value={form.imovel_livre.cidade} onChange={(e) => setForm((p) => ({ ...p, imovel_livre: { ...p.imovel_livre, cidade: e.target.value } }))} className={`w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 ${fieldTouchClass} text-foreground`} /></div><div><label className="mb-1.5 block text-xs uppercase tracking-[0.16em] text-muted-foreground">UF</label><input value={form.imovel_livre.estado} onChange={(e) => setForm((p) => ({ ...p, imovel_livre: { ...p.imovel_livre, estado: e.target.value.toUpperCase().slice(0, 2) } }))} placeholder="SP" maxLength={2} className={`w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 ${fieldTouchClass} uppercase text-foreground`} /></div><div><label className="mb-1.5 block text-xs uppercase tracking-[0.16em] text-muted-foreground">Tipo (texto livre)</label><input value={form.imovel_livre.tipo_imovel} onChange={(e) => setForm((p) => ({ ...p, imovel_livre: { ...p.imovel_livre, tipo_imovel: e.target.value } }))} placeholder="Casa, loja, terreno..." className={`w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 ${fieldTouchClass} text-foreground`} /></div><div className="md:col-span-2"><label className="mb-1.5 block text-xs uppercase tracking-[0.16em] text-muted-foreground">Outras referências</label><textarea value={form.imovel_livre.referencia} onChange={(e) => setForm((p) => ({ ...p, imovel_livre: { ...p.imovel_livre, referencia: e.target.value } }))} rows={2} placeholder="Condomínio, bloco, ponto de encontro, observações do local." className={`w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 ${fieldTouchClass} text-foreground`}></textarea></div></div>}<div className="mt-4 grid gap-4 md:grid-cols-2"><div><label className="mb-1.5 block text-xs uppercase tracking-[0.16em] text-muted-foreground">Tipo de vistoria</label><select value={form.tipo} onChange={(e) => setForm((prev) => ({ ...prev, tipo: e.target.value }))} className={`w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 ${fieldTouchClass} text-foreground`}>{tipoOptions.map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></div><div><label className="mb-1.5 block text-xs uppercase tracking-[0.16em] text-muted-foreground">Data e hora</label><input type="datetime-local" value={form.data_vistoria} onChange={(e) => setForm((prev) => ({ ...prev, data_vistoria: e.target.value }))} className={`w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 ${fieldTouchClass} text-foreground`} /></div><div><label className="mb-1.5 block text-xs uppercase tracking-[0.16em] text-muted-foreground">Responsável</label><select value={form.responsavel_pessoa_id} onChange={(e) => setForm((prev) => ({ ...prev, responsavel_pessoa_id: e.target.value }))} className={`w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 ${fieldTouchClass} text-foreground`}><option value="">Opcional</option>{pessoas.map((item) => <option key={item.id} value={item.id}>{item.nome}</option>)}</select></div><div><label className="mb-1.5 block text-xs uppercase tracking-[0.16em] text-muted-foreground">Cliente / solicitante</label><input value={form.cliente_nome} onChange={(e) => setForm((prev) => ({ ...prev, cliente_nome: e.target.value }))} placeholder={form.imovel_modo === 'cadastro' ? 'Pode vir do contrato' : 'Quem solicita ou recebe na visita'} className={`w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 ${fieldTouchClass} text-foreground`} /></div></div><div className="mt-4 flex flex-wrap gap-2"><button type="button" onClick={() => setForm((prev) => ({ ...prev, data_vistoria: formatLocalDateTimeInput(new Date().toISOString()) }))} className="rounded-xl border border-white/10 px-3 py-2 text-xs text-foreground hover:bg-white/5">Agora</button><button type="button" onClick={() => { const date = new Date(); date.setDate(date.getDate() + 1); date.setHours(9, 0, 0, 0); setForm((prev) => ({ ...prev, data_vistoria: formatLocalDateTimeInput(date.toISOString()) })); }} className="rounded-xl border border-white/10 px-3 py-2 text-xs text-foreground hover:bg-white/5">Amanhã 09:00</button><button type="button" onClick={() => setShowAdvancedCreate((prev) => !prev)} className="rounded-xl border border-blue-500/30 bg-blue-500/10 px-3 py-2 text-xs text-blue-300">{showAdvancedCreate || form.id !== 0 ? 'Ocultar opções avançadas' : 'Mostrar opções avançadas'}</button></div><textarea value={form.observacoes} onChange={(e) => setForm((prev) => ({ ...prev, observacoes: e.target.value }))} rows={4} placeholder="Observações rápidas para a equipe." className="mt-4 w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 text-sm text-foreground" /></section>{(showAdvancedCreate || form.id !== 0) && <section className="rounded-2xl border border-white/10 bg-black/10 p-4"><p className="text-sm font-semibold text-foreground">Opções avançadas</p><div className="mt-4 grid gap-4 md:grid-cols-3"><div><label className="mb-1.5 block text-xs uppercase tracking-[0.16em] text-muted-foreground">Status</label><select value={form.status} onChange={(e) => setForm((prev) => ({ ...prev, status: e.target.value }))} className="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 text-sm text-foreground">{statusOptions.map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></div><input value={form.codigo} onChange={(e) => setForm((prev) => ({ ...prev, codigo: e.target.value }))} placeholder="Código" className="rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 text-sm text-foreground" /><input value={form.vistoriadores} onChange={(e) => setForm((prev) => ({ ...prev, vistoriadores: e.target.value }))} placeholder="Vistoriadores" className="rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 text-sm text-foreground" /></div><div className="mt-4 grid gap-4 md:grid-cols-3"><input value={form.metragem} onChange={(e) => setForm((prev) => ({ ...prev, metragem: e.target.value }))} placeholder="Metragem" className="rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 text-sm text-foreground" /><label className="inline-flex items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 text-sm text-foreground"><input type="checkbox" checked={form.mobiliado} onChange={(e) => setForm((prev) => ({ ...prev, mobiliado: e.target.checked }))} />Imóvel mobiliado</label></div><div className="mt-4"><label className="mb-1.5 block text-xs uppercase tracking-[0.16em] text-muted-foreground">Participantes</label><Select isMulti options={participantesOptions} value={participantesOptions.filter((opt) => form.participantes_ids.includes(opt.value))} onChange={(selected) => setForm((prev) => ({ ...prev, participantes_ids: (selected || []).map((item) => item.value) }))} placeholder="Selecione participantes..." noOptionsMessage={() => 'Nenhuma pessoa encontrada'} styles={{ control: (base, state) => ({ ...base, minHeight: 48, backgroundColor: 'rgba(255,255,255,0.05)', borderColor: state.isFocused ? 'rgba(59,130,246,0.6)' : 'rgba(255,255,255,0.1)', boxShadow: 'none', borderRadius: 12 }), menu: (base) => ({ ...base, backgroundColor: '#0b1220', border: '1px solid rgba(255,255,255,0.1)' }), option: (base, state) => ({ ...base, backgroundColor: state.isFocused ? 'rgba(59,130,246,0.18)' : '#0b1220', color: '#e5e7eb', cursor: 'pointer' }), multiValue: (base) => ({ ...base, backgroundColor: 'rgba(59,130,246,0.25)' }), multiValueLabel: (base) => ({ ...base, color: '#dbeafe' }), input: (base) => ({ ...base, color: '#e5e7eb' }), singleValue: (base) => ({ ...base, color: '#e5e7eb' }), placeholder: (base) => ({ ...base, color: '#9ca3af' }), dropdownIndicator: (base) => ({ ...base, color: '#9ca3af' }), clearIndicator: (base) => ({ ...base, color: '#9ca3af' }) }} /></div>{form.id !== 0 && <div className="mt-4 grid gap-4 md:grid-cols-2"><select value={form.assinatura_inquilino_status} onChange={(e) => setForm((prev) => ({ ...prev, assinatura_inquilino_status: e.target.value }))} className="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 text-sm text-foreground">{assinaturaOptions.map(([value, label]) => <option key={value} value={value}>Inquilino: {label}</option>)}</select><select value={form.assinatura_proprietario_status} onChange={(e) => setForm((prev) => ({ ...prev, assinatura_proprietario_status: e.target.value }))} className="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 text-sm text-foreground">{assinaturaOptions.map(([value, label]) => <option key={value} value={value}>Proprietário: {label}</option>)}</select></div>}</section>}</div><div className="space-y-5"><section className="rounded-2xl border border-white/10 bg-black/10 p-4"><p className="text-sm font-semibold text-foreground">Resumo confiável</p><div className="mt-4 space-y-3 text-sm"><div className="rounded-xl border border-white/10 bg-white/5 p-3"><p className="text-xs uppercase tracking-[0.16em] text-muted-foreground">Contrato</p><p className="mt-1 text-foreground">{contratoText(currentContrato)}</p></div><div className="rounded-xl border border-white/10 bg-white/5 p-3"><p className="text-xs uppercase tracking-[0.16em] text-muted-foreground">Imóvel</p><p className="mt-1 text-foreground">{imovelText(currentImovel)}</p></div><div className="rounded-xl border border-white/10 bg-white/5 p-3"><p className="text-xs uppercase tracking-[0.16em] text-muted-foreground">Participantes</p><p className="mt-1 text-foreground">{form.participantes_ids.length ? pessoas.filter((item) => form.participantes_ids.includes(item.id)).map((item) => item.nome).join(', ') : currentContrato ? [currentContrato.locador?.nome, currentContrato.locatario?.nome].filter(Boolean).join(', ') : 'Nenhum participante vinculado'}</p></div><div className="rounded-xl border border-white/10 bg-white/5 p-3"><p className="text-xs uppercase tracking-[0.16em] text-muted-foreground">Status previsto</p><p className="mt-1 text-foreground">{form.id === 0 ? (form.data_vistoria ? 'Designada' : 'Solicitada') : statusOptions.find(([value]) => value === form.status)?.[1] || form.status}</p></div></div></section><section className="rounded-2xl border border-emerald-500/20 bg-emerald-500/10 p-4 text-sm text-emerald-100"><p className="font-semibold">Fluxo livre</p><p className="mt-2 text-emerald-50/90">1. Cadastro OU descrição do local só na vistoria.</p><p className="text-emerald-50/90">2. Tipo de vistoria, data e cliente quando fizer sentido.</p><p className="text-emerald-50/90">3. Salve: integração com contrato/catálogo continua opcional.</p></section></div></div></div><div className="flex shrink-0 flex-col gap-3 border-t border-border bg-card/95 px-4 py-4 pb-[max(1rem,env(safe-area-inset-bottom,0px))] backdrop-blur-md supports-[backdrop-filter]:bg-card/90 sm:flex-row sm:justify-end sm:bg-transparent sm:px-6 sm:py-5 sm:pb-5"><button type="button" onClick={() => setShowForm(false)} className="order-2 min-h-11 touch-manipulation rounded-xl border border-white/10 px-4 py-3 text-base text-foreground sm:order-1 sm:min-h-10 sm:py-2.5 sm:text-sm">Cancelar</button><button type="button" onClick={save} disabled={saving} className="order-1 min-h-11 touch-manipulation rounded-xl bg-primary px-4 py-3 text-base font-semibold text-primary-foreground disabled:opacity-60 sm:order-2 sm:min-h-10 sm:w-auto sm:py-2.5 sm:text-sm">{saving ? 'Salvando...' : form.id === 0 ? 'Criar vistoria' : 'Salvar alterações'}</button></div></div></div>
  ) : null}

  <AlertDialog open={deleteId !== null} onOpenChange={(open) => !open && setDeleteId(null)}><AlertDialogContent className="bg-[#0f0f0f] border border-white/10"><AlertDialogHeader><AlertDialogTitle>Excluir vistoria</AlertDialogTitle><AlertDialogDescription>Essa remoção apaga o vínculo operacional da vistoria.</AlertDialogDescription></AlertDialogHeader><AlertDialogFooter><AlertDialogCancel className="border-white/20 hover:bg-white/10">Cancelar</AlertDialogCancel><AlertDialogAction onClick={remove} className="bg-red-600 hover:bg-red-700">Excluir</AlertDialogAction></AlertDialogFooter></AlertDialogContent></AlertDialog>
    </>
  );
}

