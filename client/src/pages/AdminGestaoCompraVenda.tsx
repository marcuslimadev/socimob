import { useEffect, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { Plus, RefreshCcw, Search, Trash2, X } from 'lucide-react';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';
import PessoaFormModal from '@/components/PessoaFormModal';
import ContratoCompraVendaDetalheModal from '@/components/ContratoCompraVendaDetalheModal';

interface PessoaItem { id: number; nome: string; papeis?: string[]; }
interface ImovelItem { id: number; titulo?: string; codigo?: string; }
interface ContratoItem {
  id: number;
  numero_contrato?: string;
  status: string;
  data_contrato?: string;
  valor_total?: number;
  vendedor?: PessoaItem;
  comprador?: PessoaItem;
  vendedores?: PessoaItem[];
  compradores?: PessoaItem[];
  imovel?: ImovelItem;
}
interface ParcelaForm { descricao: string; valor: string; texto: string; }

const initialForm = {
  numero_contrato: '', vendedor_pessoa_id: '', comprador_pessoa_id: '', segundo_vendedor_id: '', segundo_comprador_id: '', co_vendedores_ids: [] as string[], co_compradores_ids: [] as string[], imovel_id: '', status: 'rascunho',
  data_contrato: '', data_escritura_prevista: '', valor_total: '', valor_sinal: '', valor_parcela_final: '',
  multa_percentual: '', multa_moratoria_percentual: '', juros_percentual_mes: '', corretagem_valor: '', corretagem_responsavel: '',
  intermediadora_nome: '', intermediadora_documento: '', intermediadora_fantasia: '',
  vendedor_novo_nome: '', vendedor_novo_cpf: '', comprador_novo_nome: '', comprador_novo_cpf: '', imovel_novo_titulo: '',
  objeto_descricao: '', matricula_numero: '', cartorio_nome: '', inscricao_cadastral: '',
  testemunha_um_nome: '', testemunha_um_email: '', testemunha_dois_nome: '', testemunha_dois_email: '', observacoes: '',
};

const emptyParcela = (): ParcelaForm => ({ descricao: '', valor: '', texto: '' });
const fmtMoney = (v?: number) => Number(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const fmtDate = (v?: string) => v ? `${v.slice(8, 10)}/${v.slice(5, 7)}/${v.slice(0, 4)}` : '-';
const currencyInput = (v: string) => v.replace(/[^\d,\.]/g, '');
const parseCurrency = (v: string) => !v?.trim() ? undefined : Number(v.includes(',') ? v.replace(/\./g, '').replace(',', '.') : v);

function PersonField({
  label, value, onChange, onQuickCreate, options,
}: { label: string; value: string; onChange: (v: string) => void; onQuickCreate: () => void; options: PessoaItem[] }) {
  return (
    <div>
      <div className="mb-1 flex items-center justify-between gap-2">
        <label className="text-xs text-muted-foreground">{label}</label>
        <button type="button" onClick={onQuickCreate} className="text-[11px] text-primary hover:underline">+ cadastro rápido</button>
      </div>
      <select value={value} onChange={(e) => onChange(e.target.value)} className="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm">
        <option value="">Selecione...</option>
        {options.map((item) => <option key={item.id} value={item.id}>{item.nome}</option>)}
      </select>
    </div>
  );
}

export default function AdminGestaoCompraVenda() {
  const [contratos, setContratos] = useState<ContratoItem[]>([]);
  const [pessoas, setPessoas] = useState<PessoaItem[]>([]);
  const [imoveis, setImoveis] = useState<ImovelItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [saving, setSaving] = useState(false);
  const [search, setSearch] = useState('');
  const [form, setForm] = useState<typeof initialForm>(initialForm);
  const [parcelas, setParcelas] = useState<ParcelaForm[]>([emptyParcela(), emptyParcela()]);
  const [selectedContratoId, setSelectedContratoId] = useState<number | null>(null);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [showPessoaForm, setShowPessoaForm] = useState<null | { tipo?: string; papeis?: string[] }>(null);

  const loadAll = async () => {
    setLoading(true);
    try {
      const [contratosResp, pessoasResp, imoveisResp] = await Promise.all([
        api.get('/admin/financeiro/compra-venda'),
        api.get('/pessoas?per_page=300'),
        api.get('/admin/imoveis'),
      ]);
      setContratos(contratosResp.data?.items || []);
      setPessoas(pessoasResp.data?.data || []);
      setImoveis(imoveisResp.data?.data || []);
    } catch {
      toast.error('Erro ao carregar compra e venda.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { void loadAll(); }, []);

  const filtered = useMemo(() => {
    const term = search.trim().toLowerCase();
    if (!term) return contratos;
    return contratos.filter((item) => [item.numero_contrato, item.vendedor?.nome, item.comprador?.nome, item.imovel?.titulo, item.imovel?.codigo]
      .filter(Boolean).join(' ').toLowerCase().includes(term));
  }, [contratos, search]);

  const resetForm = () => {
    setForm(initialForm);
    setParcelas([emptyParcela(), emptyParcela()]);
    setShowForm(false);
    setEditingId(null);
  };

  const openEdit = async (id: number) => {
    resetForm();
    setLoading(true);
    try {
      const { data } = await api.get(`/admin/financeiro/compra-venda/${id}`);
      const item = data.item || data;
      if (item) {
        setEditingId(item.id);
        const cv = item.vendedores?.filter((p: any) => p.id !== item.vendedor_pessoa_id).map((p: any) => p.id) || [];
        const cc = item.compradores?.filter((p: any) => p.id !== item.comprador_pessoa_id).map((p: any) => p.id) || [];
        setForm({
          numero_contrato: item.numero_contrato || '',
          vendedor_pessoa_id: String(item.vendedor_pessoa_id || ''),
          comprador_pessoa_id: String(item.comprador_pessoa_id || ''),
          segundo_vendedor_id: String(cv[0] || ''),
          co_vendedores_ids: cv.slice(1).map(String),
          segundo_comprador_id: String(cc[0] || ''),
          co_compradores_ids: cc.slice(1).map(String),
          imovel_id: String(item.imovel_id || ''),
          status: item.status || 'rascunho',
          data_contrato: item.data_contrato?.slice(0, 10) || '',
          data_escritura_prevista: item.data_escritura_prevista?.slice(0, 10) || '',
          valor_total: item.valor_total ? String(item.valor_total).replace('.', ',') : '',
          valor_sinal: item.valor_sinal ? String(item.valor_sinal).replace('.', ',') : '',
          valor_parcela_final: item.valor_parcela_final ? String(item.valor_parcela_final).replace('.', ',') : '',
          multa_percentual: item.multa_percentual ? String(item.multa_percentual).replace('.', ',') : '',
          multa_moratoria_percentual: item.multa_moratoria_percentual ? String(item.multa_moratoria_percentual).replace('.', ',') : '',
          juros_percentual_mes: item.juros_percentual_mes ? String(item.juros_percentual_mes).replace('.', ',') : '',
          corretagem_valor: item.corretagem_valor ? String(item.corretagem_valor).replace('.', ',') : '',
          corretagem_responsavel: item.corretagem_responsavel || '',
          intermediadora_nome: item.intermediadora_nome || '',
          intermediadora_documento: item.intermediadora_documento || '',
          intermediadora_fantasia: item.intermediadora_fantasia || '',
          objeto_descricao: item.objeto_descricao || '',
          matricula_numero: item.matricula_numero || '',
          cartorio_nome: item.cartorio_nome || '',
          inscricao_cadastral: item.inscricao_cadastral || '',
          testemunha_um_nome: item.testemunha_um_nome || '',
          testemunha_um_email: item.testemunha_um_email || '',
          testemunha_dois_nome: item.testemunha_dois_nome || '',
          testemunha_dois_email: item.testemunha_dois_email || '',
          observacoes: item.observacoes || '',
          vendedor_novo_nome: '', vendedor_novo_cpf: '', comprador_novo_nome: '', comprador_novo_cpf: '', imovel_novo_titulo: '',
        });
        if (item.parcelas_pagamento?.length) {
          setParcelas(item.parcelas_pagamento.map((p: any) => ({ descricao: p.descricao || '', valor: p.valor ? String(p.valor).replace('.', ',') : '', texto: p.texto || '' })));
        }
        setShowForm(true);
      }
    } catch {
      toast.error('Erro ao abrir contrato para edição.');
    } finally {
      setLoading(false);
    }
  };

  const save = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!form.vendedor_pessoa_id || !form.comprador_pessoa_id) {
      toast.error('Vendedor e comprador são obrigatórios.');
      return;
    }
    setSaving(true);
    try {
      const payload = {
        numero_contrato: form.numero_contrato || undefined,
        vendedor_pessoa_id: form.vendedor_pessoa_id ? Number(form.vendedor_pessoa_id) : undefined,
        comprador_pessoa_id: form.comprador_pessoa_id ? Number(form.comprador_pessoa_id) : undefined,
        vendedor_novo_nome: form.vendedor_novo_nome || undefined,
        vendedor_novo_cpf: form.vendedor_novo_cpf || undefined,
        comprador_novo_nome: form.comprador_novo_nome || undefined,
        comprador_novo_cpf: form.comprador_novo_cpf || undefined,
        imovel_novo_titulo: form.imovel_novo_titulo || undefined,
        co_vendedores_ids: [
          ...(form.segundo_vendedor_id ? [Number(form.segundo_vendedor_id)] : []),
          ...form.co_vendedores_ids.filter(Boolean).map(Number)
        ],
        co_compradores_ids: [
          ...(form.segundo_comprador_id ? [Number(form.segundo_comprador_id)] : []),
          ...form.co_compradores_ids.filter(Boolean).map(Number)
        ],
        imovel_id: form.imovel_id ? Number(form.imovel_id) : undefined,
        status: form.status,
        data_contrato: form.data_contrato || undefined,
        data_escritura_prevista: form.data_escritura_prevista || undefined,
        valor_total: parseCurrency(form.valor_total),
        valor_sinal: parseCurrency(form.valor_sinal),
        valor_parcela_final: parseCurrency(form.valor_parcela_final),
        multa_percentual: parseCurrency(form.multa_percentual),
        multa_moratoria_percentual: parseCurrency(form.multa_moratoria_percentual),
        juros_percentual_mes: parseCurrency(form.juros_percentual_mes),
        corretagem_valor: parseCurrency(form.corretagem_valor),
        corretagem_responsavel: form.corretagem_responsavel || undefined,
        intermediadora_nome: form.intermediadora_nome || undefined,
        intermediadora_documento: form.intermediadora_documento || undefined,
        intermediadora_fantasia: form.intermediadora_fantasia || undefined,
        objeto_descricao: form.objeto_descricao || undefined,
        matricula_numero: form.matricula_numero || undefined,
        cartorio_nome: form.cartorio_nome || undefined,
        inscricao_cadastral: form.inscricao_cadastral || undefined,
        testemunha_um_nome: form.testemunha_um_nome || undefined,
        testemunha_um_email: form.testemunha_um_email?.trim() || undefined,
        testemunha_dois_nome: form.testemunha_dois_nome || undefined,
        testemunha_dois_email: form.testemunha_dois_email?.trim() || undefined,
        observacoes: form.observacoes || undefined,
        parcelas_pagamento: parcelas
          .map((item) => ({ descricao: item.descricao || undefined, valor: parseCurrency(item.valor), texto: item.texto || undefined }))
          .filter((item) => item.descricao || item.valor || item.texto),
      };

      if (editingId) {
        await api.put(`/admin/financeiro/compra-venda/${editingId}`, payload);
        toast.success('Contrato atualizado.');
      } else {
        await api.post('/admin/financeiro/compra-venda', payload);
        toast.success('Contrato criado.');
      }
      resetForm();
      await loadAll();
    } catch (error: any) {
      const msgs = error?.response?.data?.errors ? Object.values(error.response.data.errors).flat().join(', ') : null;
      toast.error(msgs || error?.response?.data?.message || 'Erro ao criar contrato.');
    } finally {
      setSaving(false);
    }
  };

  const removeContrato = async (id: number) => {
    if (!confirm('Excluir este contrato?')) return;
    try {
      await api.delete(`/admin/financeiro/compra-venda/${id}`);
      toast.success('Contrato excluído.');
      await loadAll();
    } catch {
      toast.error('Erro ao excluir contrato.');
    }
  };

  return (
    <div className="flex min-h-screen bg-background">
      <Sidebar />
      <main className="page-shell">
        <div className="mx-auto max-w-7xl">
          <div className="mb-6 flex flex-wrap items-start justify-between gap-3">
            <div>
              <h1 className="text-2xl font-bold text-foreground">Compra e Venda</h1>
              <p className="text-sm text-muted-foreground">Cadastro do negócio, minuta e assinatura.</p>
            </div>
            <div className="flex gap-2">
              <button type="button" onClick={() => void loadAll()} className="flex items-center gap-2 rounded-lg border border-border px-4 py-2 text-sm hover:bg-accent">
                <RefreshCcw size={14} /> Atualizar
              </button>
              <button type="button" onClick={() => setShowForm(true)} className="flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm text-primary-foreground hover:opacity-90">
                <Plus size={14} /> Novo contrato
              </button>
            </div>
          </div>

          <div className="glass-panel rounded-2xl p-5">
            <div className="relative mb-4 max-w-md">
              <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
              <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Buscar por número, partes ou imóvel" className="w-full rounded-lg border border-border bg-background py-2 pl-9 pr-3 text-sm" />
            </div>

            {loading ? (
              <div className="py-12 text-center text-sm text-muted-foreground">Carregando contratos...</div>
            ) : filtered.length === 0 ? (
              <div className="py-12 text-center text-sm text-muted-foreground">Nenhum contrato cadastrado.</div>
            ) : (
              <div className="overflow-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b border-border text-left text-xs uppercase tracking-wider text-muted-foreground">
                      <th className="px-3 py-3">Contrato</th>
                      <th className="px-3 py-3">Partes</th>
                      <th className="px-3 py-3">Imóvel</th>
                      <th className="px-3 py-3">Data</th>
                      <th className="px-3 py-3">Valor</th>
                      <th className="px-3 py-3">Status</th>
                      <th className="px-3 py-3 text-right">Ações</th>
                    </tr>
                  </thead>
                  <tbody>
                    {filtered.map((item) => (
                      <tr key={item.id} className="border-b border-border/50">
                        <td className="px-3 py-3 font-medium">{item.numero_contrato || `#${item.id}`}</td>
                        <td className="px-3 py-3 text-xs text-muted-foreground">
                          <p><strong className="text-foreground">Vendedor:</strong> {item.vendedor?.nome || item.vendedores?.map((p) => p.nome).join(', ') || '-'}</p>
                          <p><strong className="text-foreground">Comprador:</strong> {item.comprador?.nome || item.compradores?.map((p) => p.nome).join(', ') || '-'}</p>
                        </td>
                        <td className="px-3 py-3">{item.imovel?.titulo || item.imovel?.codigo || '-'}</td>
                        <td className="px-3 py-3">{fmtDate(item.data_contrato)}</td>
                        <td className="px-3 py-3 font-medium">R$ {fmtMoney(item.valor_total)}</td>
                        <td className="px-3 py-3"><span className="rounded-full bg-muted px-2 py-0.5 text-xs font-medium">{item.status || 'rascunho'}</span></td>
                        <td className="px-3 py-3">
                          <div className="flex justify-end gap-2">
                            <button type="button" onClick={() => setSelectedContratoId(item.id)} className="rounded-lg border border-border px-3 py-1.5 text-xs hover:bg-accent">Visualizar</button>
                            <button type="button" onClick={() => openEdit(item.id)} className="rounded-lg border border-border px-3 py-1.5 text-xs hover:bg-accent">Editar</button>
                            <button type="button" onClick={() => void removeContrato(item.id)} className="rounded-lg border border-destructive/30 px-3 py-1.5 text-xs text-destructive hover:bg-destructive/10"><Trash2 size={12} className="inline" /></button>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        </div>
      </main>

      {showForm && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4">
          <div className="glass-panel max-h-[94vh] w-full max-w-5xl overflow-y-auto rounded-2xl p-6">
            <div className="mb-5 flex items-start justify-between">
              <div>
                <h2 className="text-lg font-semibold">{editingId ? 'Editar contrato' : 'Novo contrato de compra e venda'}</h2>
                <p className="text-sm text-muted-foreground">{editingId ? 'Altere os dados do negócio.' : 'Versão inicial com cadastro rápido de vendedor e comprador.'}</p>
              </div>
              <button type="button" onClick={resetForm} className="text-muted-foreground hover:text-foreground"><X size={20} /></button>
            </div>

            <form onSubmit={save} className="space-y-4">
              <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div className="rounded-xl border border-border p-3 bg-muted/20">
                  <label className="text-xs font-semibold text-foreground mb-2 block">Vendedor Principal</label>
                  <select value={form.vendedor_pessoa_id} onChange={(e) => setForm((p) => ({ ...p, vendedor_pessoa_id: e.target.value, vendedor_novo_nome: '', vendedor_novo_cpf: '' }))} className="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm mb-2">
                    <option value="">+ Criar novo na hora ou selecione...</option>
                    {pessoas.filter((p) => !p.papeis?.length || p.papeis.includes('vendedor') || p.papeis.includes('proprietario')).map((item) => <option key={item.id} value={item.id}>{item.nome}</option>)}
                  </select>
                  {!form.vendedor_pessoa_id && (
                    <div className="flex gap-2">
                      <input value={form.vendedor_novo_nome} onChange={(e) => setForm((p) => ({ ...p, vendedor_novo_nome: e.target.value }))} placeholder="Nome do Vendedor" className="flex-1 rounded-lg border border-border bg-background px-2 py-1.5 text-xs" />
                      <input value={form.vendedor_novo_cpf} onChange={(e) => setForm((p) => ({ ...p, vendedor_novo_cpf: e.target.value }))} placeholder="CPF/CNPJ" className="w-1/3 rounded-lg border border-border bg-background px-2 py-1.5 text-xs" />
                    </div>
                  )}
                </div>

                <div className="rounded-xl border border-border p-3 bg-muted/20">
                  <label className="text-xs font-semibold text-foreground mb-2 block">Comprador Principal</label>
                  <select value={form.comprador_pessoa_id} onChange={(e) => setForm((p) => ({ ...p, comprador_pessoa_id: e.target.value, comprador_novo_nome: '', comprador_novo_cpf: '' }))} className="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm mb-2">
                    <option value="">+ Criar novo na hora ou selecione...</option>
                    {pessoas.filter((p) => !p.papeis?.length || p.papeis.includes('comprador') || p.papeis.includes('cliente')).map((item) => <option key={item.id} value={item.id}>{item.nome}</option>)}
                  </select>
                  {!form.comprador_pessoa_id && (
                    <div className="flex gap-2">
                      <input value={form.comprador_novo_nome} onChange={(e) => setForm((p) => ({ ...p, comprador_novo_nome: e.target.value }))} placeholder="Nome do Comprador" className="flex-1 rounded-lg border border-border bg-background px-2 py-1.5 text-xs" />
                      <input value={form.comprador_novo_cpf} onChange={(e) => setForm((p) => ({ ...p, comprador_novo_cpf: e.target.value }))} placeholder="CPF/CNPJ" className="w-1/3 rounded-lg border border-border bg-background px-2 py-1.5 text-xs" />
                    </div>
                  )}
                </div>

                <div className="rounded-xl border border-border p-3 bg-muted/20">
                  <label className="mb-2 block text-xs font-semibold">Imóvel</label>
                  <select value={form.imovel_id} onChange={(e) => setForm((p) => ({ ...p, imovel_id: e.target.value, imovel_novo_titulo: '' }))} className="w-full rounded-lg border border-border bg-background px-3 py-2 text-sm mb-2">
                    <option value="">+ Criar rápido (Parceiro) ou selecione...</option>
                    {imoveis.map((item) => <option key={item.id} value={item.id}>{item.titulo || item.codigo || `#${item.id}`}</option>)}
                  </select>
                  {!form.imovel_id && (
                    <input value={form.imovel_novo_titulo} onChange={(e) => setForm((p) => ({ ...p, imovel_novo_titulo: e.target.value }))} placeholder="Título do imóvel (Rápido)" className="w-full rounded-lg border border-border bg-background px-2 py-1.5 text-xs" />
                  )}
                </div>

                <div className="space-y-4 rounded-xl border border-border p-3 bg-muted/20">
                  <PersonField label="Segundo Vendedor (Cônjuge)" value={form.segundo_vendedor_id} onChange={(v) => setForm((p) => ({ ...p, segundo_vendedor_id: v }))} onQuickCreate={() => setShowPessoaForm({ tipo: 'fisica', papeis: ['vendedor'] })} options={pessoas.filter((p) => String(p.id) !== form.vendedor_pessoa_id)} />
                  <div>
                    <label className="mb-1 block text-xs text-muted-foreground">Outros Co-Vendedores</label>
                    <select multiple value={form.co_vendedores_ids} onChange={(e) => setForm((p) => ({ ...p, co_vendedores_ids: Array.from(e.target.selectedOptions).map((option) => option.value) }))} className="min-h-[80px] w-full rounded-lg border border-border bg-background px-2 py-1.5 text-xs">
                      {pessoas.map((item) => <option key={item.id} value={item.id}>{item.nome}</option>)}
                    </select>
                  </div>
                </div>
                
                <div className="space-y-4 rounded-xl border border-border p-3 bg-muted/20">
                  <PersonField label="Segundo Comprador (Cônjuge)" value={form.segundo_comprador_id} onChange={(v) => setForm((p) => ({ ...p, segundo_comprador_id: v }))} onQuickCreate={() => setShowPessoaForm({ tipo: 'fisica', papeis: ['comprador'] })} options={pessoas.filter((p) => String(p.id) !== form.comprador_pessoa_id)} />
                  <div>
                    <label className="mb-1 block text-xs text-muted-foreground">Outros Co-Compradores</label>
                    <select multiple value={form.co_compradores_ids} onChange={(e) => setForm((p) => ({ ...p, co_compradores_ids: Array.from(e.target.selectedOptions).map((option) => option.value) }))} className="min-h-[80px] w-full rounded-lg border border-border bg-background px-2 py-1.5 text-xs">
                      {pessoas.map((item) => <option key={item.id} value={item.id}>{item.nome}</option>)}
                    </select>
                  </div>
                </div>
                <input value={form.numero_contrato} onChange={(e) => setForm((p) => ({ ...p, numero_contrato: e.target.value }))} className="rounded-lg border border-border bg-background px-3 py-2 text-sm" placeholder="Número do contrato" />
                <input type="date" title="Data Assinatura" value={form.data_contrato} onChange={(e) => setForm((p) => ({ ...p, data_contrato: e.target.value }))} className="rounded-lg border border-border bg-background px-3 py-2 text-sm" />
                <input type="date" title="Data Escritura" value={form.data_escritura_prevista} onChange={(e) => setForm((p) => ({ ...p, data_escritura_prevista: e.target.value }))} className="rounded-lg border border-border bg-background px-3 py-2 text-sm" />
                <input value={form.valor_total} onChange={(e) => setForm((p) => ({ ...p, valor_total: currencyInput(e.target.value) }))} className="col-span-full rounded-lg border border-border bg-background px-3 py-2 text-sm" placeholder="Valor total" />
                <input value={form.valor_sinal} onChange={(e) => setForm((p) => ({ ...p, valor_sinal: currencyInput(e.target.value) }))} className="rounded-lg border border-border bg-background px-3 py-2 text-sm" placeholder="Sinal" />
                <input value={form.valor_parcela_final} onChange={(e) => setForm((p) => ({ ...p, valor_parcela_final: currencyInput(e.target.value) }))} className="rounded-lg border border-border bg-background px-3 py-2 text-sm" placeholder="Parcela final (Financiamento etc)" />
                <div className="col-span-full grid grid-cols-3 gap-4">
                  <input value={form.multa_percentual} onChange={(e) => setForm((p) => ({ ...p, multa_percentual: currencyInput(e.target.value) }))} className="rounded-lg border border-border bg-background px-3 py-2 text-sm" placeholder="Multa Rescisão (%)" />
                  <input value={form.multa_moratoria_percentual} onChange={(e) => setForm((p) => ({ ...p, multa_moratoria_percentual: currencyInput(e.target.value) }))} className="rounded-lg border border-border bg-background px-3 py-2 text-sm" placeholder="Multa Atraso (%)" />
                  <input value={form.juros_percentual_mes} onChange={(e) => setForm((p) => ({ ...p, juros_percentual_mes: currencyInput(e.target.value) }))} className="rounded-lg border border-border bg-background px-3 py-2 text-sm" placeholder="Juros Mês (%)" />
                </div>
                <div className="col-span-full grid grid-cols-2 gap-4">
                  <input value={form.corretagem_valor} onChange={(e) => setForm((p) => ({ ...p, corretagem_valor: currencyInput(e.target.value) }))} className="rounded-lg border border-border bg-background px-3 py-2 text-sm" placeholder="Valor Corretagem" />
                  <input value={form.corretagem_responsavel} onChange={(e) => setForm((p) => ({ ...p, corretagem_responsavel: e.target.value }))} className="rounded-lg border border-border bg-background px-3 py-2 text-sm" placeholder="Responsável Pagto. Corretagem" />
                </div>
                <div className="col-span-full rounded-xl border border-border bg-muted/20 p-3">
                  <p className="mb-3 text-xs font-semibold text-foreground">Construtora / Intermediadora</p>
                  <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
                    <input value={form.intermediadora_nome} onChange={(e) => setForm((p) => ({ ...p, intermediadora_nome: e.target.value }))} className="rounded-lg border border-border bg-background px-3 py-2 text-sm" placeholder="Nome manual da construtora" />
                    <input value={form.intermediadora_documento} onChange={(e) => setForm((p) => ({ ...p, intermediadora_documento: e.target.value }))} className="rounded-lg border border-border bg-background px-3 py-2 text-sm" placeholder="CPF/CNPJ da construtora" />
                    <input value={form.intermediadora_fantasia} onChange={(e) => setForm((p) => ({ ...p, intermediadora_fantasia: e.target.value }))} className="rounded-lg border border-border bg-background px-3 py-2 text-sm" placeholder="Nome fantasia" />
                  </div>
                </div>
                <input value={form.matricula_numero} onChange={(e) => setForm((p) => ({ ...p, matricula_numero: e.target.value }))} className="rounded-lg border border-border bg-background px-3 py-2 text-sm" placeholder="Matrícula" />
                <input value={form.cartorio_nome} onChange={(e) => setForm((p) => ({ ...p, cartorio_nome: e.target.value }))} className="rounded-lg border border-border bg-background px-3 py-2 text-sm" placeholder="Cartório" />
                <input value={form.inscricao_cadastral} onChange={(e) => setForm((p) => ({ ...p, inscricao_cadastral: e.target.value }))} className="rounded-lg border border-border bg-background px-3 py-2 text-sm" placeholder="Inscrição cadastral" />
              </div>

              <textarea value={form.objeto_descricao} onChange={(e) => setForm((p) => ({ ...p, objeto_descricao: e.target.value }))} className="min-h-[120px] w-full rounded-lg border border-border bg-background px-3 py-2 text-sm" placeholder="Descrição do imóvel e do objeto da negociação." />

              <div className="space-y-3">
                {parcelas.map((item, index) => (
                  <div key={index} className="rounded-xl border border-border bg-muted/20 p-3">
                    <div className="mb-2 flex items-center justify-between">
                      <p className="text-sm font-medium">Parcela {index + 1}</p>
                      {parcelas.length > 1 && <button type="button" onClick={() => setParcelas((current) => current.filter((_, i) => i !== index))} className="text-destructive hover:opacity-80"><Trash2 size={13} /></button>}
                    </div>
                    <div className="grid grid-cols-1 gap-3 md:grid-cols-2">
                      <input value={item.descricao} onChange={(e) => setParcelas((current) => current.map((p, i) => i === index ? { ...p, descricao: e.target.value } : p))} className="rounded-lg border border-border bg-background px-3 py-2 text-sm" placeholder="Ex: a)" />
                      <input value={item.valor} onChange={(e) => setParcelas((current) => current.map((p, i) => i === index ? { ...p, valor: currencyInput(e.target.value) } : p))} className="rounded-lg border border-border bg-background px-3 py-2 text-sm" placeholder="Valor" />
                    </div>
                    <textarea value={item.texto} onChange={(e) => setParcelas((current) => current.map((p, i) => i === index ? { ...p, texto: e.target.value } : p))} className="mt-3 min-h-[80px] w-full rounded-lg border border-border bg-background px-3 py-2 text-sm" placeholder="Texto completo dessa parcela." />
                  </div>
                ))}
                <button type="button" onClick={() => setParcelas((current) => [...current, emptyParcela()])} className="text-xs text-primary hover:underline">+ adicionar parcela</button>
              </div>

              <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                <input value={form.testemunha_um_nome} onChange={(e) => setForm((p) => ({ ...p, testemunha_um_nome: e.target.value }))} className="rounded-lg border border-border bg-background px-3 py-2 text-sm" placeholder="Testemunha 1" />
                <input value={form.testemunha_um_email} onChange={(e) => setForm((p) => ({ ...p, testemunha_um_email: e.target.value }))} className="rounded-lg border border-border bg-background px-3 py-2 text-sm" placeholder="E-mail testemunha 1" />
                <input value={form.testemunha_dois_nome} onChange={(e) => setForm((p) => ({ ...p, testemunha_dois_nome: e.target.value }))} className="rounded-lg border border-border bg-background px-3 py-2 text-sm" placeholder="Testemunha 2" />
                <input value={form.testemunha_dois_email} onChange={(e) => setForm((p) => ({ ...p, testemunha_dois_email: e.target.value }))} className="rounded-lg border border-border bg-background px-3 py-2 text-sm" placeholder="E-mail testemunha 2" />
              </div>

              <textarea value={form.observacoes} onChange={(e) => setForm((p) => ({ ...p, observacoes: e.target.value }))} className="min-h-[100px] w-full rounded-lg border border-border bg-background px-3 py-2 text-sm" placeholder="Observações complementares." />

              <div className="flex justify-end gap-2 border-t border-border pt-4">
                <button type="button" onClick={resetForm} className="rounded-lg border border-border px-4 py-2 text-sm hover:bg-accent">Cancelar</button>
                <button type="submit" disabled={saving} className="rounded-lg bg-primary px-4 py-2 text-sm text-primary-foreground hover:opacity-90 disabled:opacity-60">{saving ? 'Salvando...' : 'Salvar contrato'}</button>
              </div>
            </form>
          </div>
        </div>
      )}

      {selectedContratoId && <ContratoCompraVendaDetalheModal contratoId={selectedContratoId} onClose={() => setSelectedContratoId(null)} />}
      {showPessoaForm && <PessoaFormModal pessoaInicial={showPessoaForm} onClose={() => setShowPessoaForm(null)} onSuccess={() => { setShowPessoaForm(null); loadAll(); }} />}
    </div>
  );
}
