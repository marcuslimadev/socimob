import { useEffect, useMemo, useState } from 'react';
import { motion } from 'framer-motion';
import {
  ArrowDownCircle,
  ArrowUpCircle,
  Check,
  CircleDollarSign,
  Plus,
  RefreshCw,
  Trash2,
  X,
} from 'lucide-react';
import { toast } from 'sonner';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';

type Tipo = 'conta_receber' | 'conta_pagar';

interface Lancamento {
  id: number;
  tipo: Tipo;
  categoria?: string | null;
  descricao?: string | null;
  vencimento?: string | null;
  competencia?: string | null;
  valor: number;
  valor_em_aberto: number;
  status: string;
  pessoa?: { id: number; nome: string } | null;
}

const fmtBRL = (v: number) =>
  v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });

const fmtDate = (d?: string | null) =>
  d ? new Date(d + 'T00:00:00').toLocaleDateString('pt-BR') : '—';

const isVencido = (item: Lancamento) => {
  if (item.status === 'liquidado') return false;
  if (!item.vencimento) return false;
  return new Date(item.vencimento + 'T00:00:00') < new Date(new Date().toDateString());
};

const statusLabel = (item: Lancamento) => {
  if (item.status === 'liquidado') return { label: 'Liquidado', cls: 'bg-green-500/20 text-green-300 border-green-500/40' };
  if (item.status === 'parcial')   return { label: 'Parcial',   cls: 'bg-blue-500/20 text-blue-300 border-blue-500/40' };
  if (isVencido(item))             return { label: 'Vencido',   cls: 'bg-red-500/20 text-red-300 border-red-500/40' };
  return { label: 'Em aberto', cls: 'bg-amber-500/20 text-amber-300 border-amber-500/40' };
};

const CATEGORIAS_RECEBER = ['Aluguel', 'Condomínio', 'IPTU', 'Taxa', 'Garantia', 'Rescisão', 'Outros'];
const CATEGORIAS_PAGAR   = ['Fornecedor', 'Manutenção', 'Comissão', 'Imposto', 'Salário', 'Serviço', 'Outros'];

const emptyForm = () => ({
  descricao: '',
  categoria: '',
  vencimento: '',
  competencia: '',
  valor: '',
});

export default function ContasFinanceiras() {
  const [tab, setTab] = useState<Tipo>('conta_receber');
  const [items, setItems] = useState<Lancamento[]>([]);
  const [loading, setLoading] = useState(true);
  const [statusFiltro, setStatusFiltro] = useState('todos');
  const [showForm, setShowForm] = useState(false);
  const [form, setForm] = useState(emptyForm());
  const [saving, setSaving] = useState(false);

  // Baixa modal
  const [baixaId, setBaixaId] = useState<number | null>(null);
  const [baixaForm, setBaixaForm] = useState({ data_baixa: '', valor_baixa: '', meio_pagamento: '' });
  const [savingBaixa, setSavingBaixa] = useState(false);

  const load = async () => {
    setLoading(true);
    try {
      const { data } = await api.get('/admin/financeiro/lancamentos', { params: { tipo: tab } });
      setItems(data.items ?? []);
    } catch {
      toast.error('Erro ao carregar lançamentos.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, [tab]);

  const filtered = useMemo(() => {
    return items.filter((item) => {
      if (statusFiltro === 'todos') return true;
      if (statusFiltro === 'vencido') return isVencido(item);
      return item.status === statusFiltro;
    });
  }, [items, statusFiltro]);

  const totals = useMemo(() => ({
    total:     items.reduce((s, i) => s + i.valor, 0),
    em_aberto: items.filter((i) => i.status !== 'liquidado').reduce((s, i) => s + i.valor_em_aberto, 0),
    vencido:   items.filter(isVencido).reduce((s, i) => s + i.valor_em_aberto, 0),
    liquidado: items.filter((i) => i.status === 'liquidado').reduce((s, i) => s + i.valor, 0),
  }), [items]);

  const handleCreate = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!form.valor || !form.descricao) { toast.error('Descrição e valor são obrigatórios.'); return; }
    setSaving(true);
    try {
      await api.post('/admin/financeiro/lancamentos', {
        tipo: tab,
        descricao: form.descricao,
        categoria: form.categoria || undefined,
        vencimento: form.vencimento || undefined,
        competencia: form.competencia || undefined,
        valor: parseFloat(form.valor.replace(',', '.')),
      });
      toast.success('Lançamento criado.');
      setShowForm(false);
      setForm(emptyForm());
      load();
    } catch (err: any) {
      toast.error(err?.response?.data?.message || 'Erro ao criar.');
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async (id: number) => {
    if (!confirm('Excluir este lançamento?')) return;
    try {
      await api.delete(`/admin/financeiro/lancamentos/${id}`);
      toast.success('Excluído.');
      setItems((p) => p.filter((i) => i.id !== id));
    } catch {
      toast.error('Erro ao excluir.');
    }
  };

  const handleBaixa = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!baixaId || !baixaForm.data_baixa || !baixaForm.valor_baixa) {
      toast.error('Data e valor são obrigatórios.'); return;
    }
    setSavingBaixa(true);
    try {
      await api.post(`/admin/financeiro/lancamentos/${baixaId}/baixas`, {
        data_baixa: baixaForm.data_baixa,
        valor_baixa: parseFloat(baixaForm.valor_baixa.replace(',', '.')),
        meio_pagamento: baixaForm.meio_pagamento || undefined,
      });
      toast.success('Baixa registrada.');
      setBaixaId(null);
      setBaixaForm({ data_baixa: '', valor_baixa: '', meio_pagamento: '' });
      load();
    } catch (err: any) {
      toast.error(err?.response?.data?.message || 'Erro ao registrar baixa.');
    } finally {
      setSavingBaixa(false);
    }
  };

  const categorias = tab === 'conta_receber' ? CATEGORIAS_RECEBER : CATEGORIAS_PAGAR;
  const tabColor   = tab === 'conta_receber' ? 'text-green-400' : 'text-red-400';

  return (
    <div className="flex">
      <Sidebar />
      <div className="page-shell">
        <motion.div initial={{ opacity: 0, y: 16 }} animate={{ opacity: 1, y: 0 }} className="max-w-7xl mx-auto">

          {/* Header */}
          <div className="page-header mb-6">
            <div>
              <h1 className="page-title mb-1">Contas Financeiras</h1>
              <p className="page-subtitle">Contas a receber e a pagar</p>
            </div>
            <div className="flex gap-2">
              <button onClick={load} className="p-2 rounded-lg border border-border hover:bg-accent" title="Atualizar">
                <RefreshCw size={16} />
              </button>
              <button
                onClick={() => { setShowForm(true); setForm(emptyForm()); }}
                className="flex items-center gap-2 px-4 py-2 rounded-lg bg-primary text-primary-foreground text-sm font-semibold"
              >
                <Plus size={16} /> Novo lançamento
              </button>
            </div>
          </div>

          {/* Tabs */}
          <div className="flex gap-1 mb-6 p-1 bg-muted/40 rounded-xl w-fit">
            {([['conta_receber', 'Contas a Receber', <ArrowDownCircle size={16} />], ['conta_pagar', 'Contas a Pagar', <ArrowUpCircle size={16} />]] as const).map(([t, label, icon]) => (
              <button
                key={t}
                onClick={() => { setTab(t); setStatusFiltro('todos'); }}
                className={`flex items-center gap-2 px-5 py-2.5 rounded-lg text-sm font-semibold transition-all ${tab === t ? 'bg-background shadow text-foreground' : 'text-muted-foreground hover:text-foreground'}`}
              >
                {icon}{label}
              </button>
            ))}
          </div>

          {/* Summary cards */}
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
            {[
              { label: 'Total',     value: totals.total,     color: 'text-foreground',  icon: <CircleDollarSign size={20} /> },
              { label: 'Em aberto', value: totals.em_aberto, color: 'text-amber-400',   icon: <CircleDollarSign size={20} /> },
              { label: 'Vencido',   value: totals.vencido,   color: 'text-red-400',     icon: <CircleDollarSign size={20} /> },
              { label: 'Liquidado', value: totals.liquidado, color: 'text-green-400',   icon: <Check size={20} /> },
            ].map((c) => (
              <div key={c.label} className="glass-panel rounded-xl p-4">
                <p className="text-xs text-muted-foreground mb-1">{c.label}</p>
                <p className={`text-lg font-bold ${c.color}`}>{fmtBRL(c.value)}</p>
              </div>
            ))}
          </div>

          {/* Filters */}
          <div className="flex flex-wrap gap-2 mb-4">
            {['todos', 'aberto', 'vencido', 'parcial', 'liquidado'].map((s) => (
              <button
                key={s}
                onClick={() => setStatusFiltro(s)}
                className={`px-3 py-1.5 rounded-full text-xs font-medium border transition-all ${statusFiltro === s ? 'bg-primary text-primary-foreground border-primary' : 'border-border hover:bg-accent text-muted-foreground'}`}
              >
                {s === 'todos' ? 'Todos' : s.charAt(0).toUpperCase() + s.slice(1)}
              </button>
            ))}
          </div>

          {/* Table */}
          <div className="glass-panel rounded-2xl overflow-hidden">
            {loading ? (
              <p className="text-center py-12 text-muted-foreground text-sm">Carregando...</p>
            ) : filtered.length === 0 ? (
              <p className="text-center py-12 text-muted-foreground text-sm">Nenhum lançamento encontrado.</p>
            ) : (
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-border bg-muted/30">
                    <th className="text-left px-4 py-3 text-xs font-semibold text-muted-foreground">Descrição</th>
                    <th className="text-left px-4 py-3 text-xs font-semibold text-muted-foreground hidden md:table-cell">Categoria</th>
                    <th className="text-left px-4 py-3 text-xs font-semibold text-muted-foreground">Vencimento</th>
                    <th className="text-right px-4 py-3 text-xs font-semibold text-muted-foreground">Valor</th>
                    <th className="text-right px-4 py-3 text-xs font-semibold text-muted-foreground hidden md:table-cell">Em aberto</th>
                    <th className="text-left px-4 py-3 text-xs font-semibold text-muted-foreground">Status</th>
                    <th className="px-4 py-3 text-xs font-semibold text-muted-foreground text-right">Ações</th>
                  </tr>
                </thead>
                <tbody>
                  {filtered.map((item) => {
                    const st = statusLabel(item);
                    return (
                      <tr key={item.id} className="border-b border-border/50 hover:bg-muted/20 transition-colors">
                        <td className="px-4 py-3">
                          <p className="font-medium">{item.descricao || '—'}</p>
                          {item.pessoa && <p className="text-xs text-muted-foreground">{item.pessoa.nome}</p>}
                        </td>
                        <td className="px-4 py-3 text-muted-foreground hidden md:table-cell">{item.categoria || '—'}</td>
                        <td className="px-4 py-3 text-muted-foreground">{fmtDate(item.vencimento)}</td>
                        <td className="px-4 py-3 text-right font-medium">{fmtBRL(item.valor)}</td>
                        <td className="px-4 py-3 text-right text-muted-foreground hidden md:table-cell">{fmtBRL(item.valor_em_aberto)}</td>
                        <td className="px-4 py-3">
                          <span className={`text-xs px-2 py-0.5 rounded-full border ${st.cls}`}>{st.label}</span>
                        </td>
                        <td className="px-4 py-3">
                          <div className="flex items-center justify-end gap-2">
                            {item.status !== 'liquidado' && (
                              <button
                                onClick={() => { setBaixaId(item.id); setBaixaForm({ data_baixa: new Date().toISOString().slice(0, 10), valor_baixa: item.valor_em_aberto.toFixed(2).replace('.', ','), meio_pagamento: '' }); }}
                                className="flex items-center gap-1 px-2 py-1 rounded-lg bg-green-600/20 text-green-400 hover:bg-green-600/30 text-xs"
                                title="Registrar baixa"
                              >
                                <Check size={12} /> Baixar
                              </button>
                            )}
                            <button onClick={() => handleDelete(item.id)} className="text-red-400 hover:text-red-300" title="Excluir">
                              <Trash2 size={14} />
                            </button>
                          </div>
                        </td>
                      </tr>
                    );
                  })}
                </tbody>
              </table>
            )}
          </div>
        </motion.div>
      </div>

      {/* New lancamento modal */}
      {showForm && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="glass-panel rounded-2xl p-6 w-full max-w-lg mx-4">
            <div className="flex items-center justify-between mb-4">
              <h2 className="text-base font-semibold">
                {tab === 'conta_receber' ? 'Nova conta a receber' : 'Nova conta a pagar'}
              </h2>
              <button onClick={() => setShowForm(false)} className="text-muted-foreground hover:text-foreground text-xl">&times;</button>
            </div>
            <form onSubmit={handleCreate} className="space-y-3">
              <div>
                <label className="block text-xs font-medium mb-1">Descrição *</label>
                <input
                  value={form.descricao}
                  onChange={(e) => setForm((f) => ({ ...f, descricao: e.target.value }))}
                  required
                  className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm"
                  placeholder="Ex: Aluguel referente a março"
                />
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium mb-1">Categoria</label>
                  <select
                    value={form.categoria}
                    onChange={(e) => setForm((f) => ({ ...f, categoria: e.target.value }))}
                    className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm"
                  >
                    <option value="">Selecione...</option>
                    {categorias.map((c) => <option key={c} value={c}>{c}</option>)}
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-medium mb-1">Valor *</label>
                  <input
                    value={form.valor}
                    onChange={(e) => setForm((f) => ({ ...f, valor: e.target.value }))}
                    required
                    placeholder="0,00"
                    className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm"
                  />
                </div>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium mb-1">Vencimento</label>
                  <input
                    type="date"
                    value={form.vencimento}
                    onChange={(e) => setForm((f) => ({ ...f, vencimento: e.target.value }))}
                    className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm"
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium mb-1">Competência</label>
                  <input
                    type="date"
                    value={form.competencia}
                    onChange={(e) => setForm((f) => ({ ...f, competencia: e.target.value }))}
                    className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm"
                  />
                </div>
              </div>
              <div className="flex justify-end gap-2 pt-2">
                <button type="button" onClick={() => setShowForm(false)} className="px-4 py-2 rounded-lg border border-border text-sm hover:bg-accent">Cancelar</button>
                <button type="submit" disabled={saving} className="px-4 py-2 rounded-lg bg-primary text-primary-foreground text-sm disabled:opacity-60">
                  {saving ? 'Salvando...' : 'Criar'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}

      {/* Registrar baixa modal */}
      {baixaId !== null && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
          <div className="glass-panel rounded-2xl p-6 w-full max-w-sm mx-4">
            <div className="flex items-center justify-between mb-4">
              <h2 className="text-base font-semibold">Registrar Baixa</h2>
              <button onClick={() => setBaixaId(null)} className="text-muted-foreground hover:text-foreground text-xl">&times;</button>
            </div>
            <form onSubmit={handleBaixa} className="space-y-3">
              <div>
                <label className="block text-xs font-medium mb-1">Data do pagamento *</label>
                <input
                  type="date"
                  value={baixaForm.data_baixa}
                  onChange={(e) => setBaixaForm((f) => ({ ...f, data_baixa: e.target.value }))}
                  required
                  className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm"
                />
              </div>
              <div>
                <label className="block text-xs font-medium mb-1">Valor pago *</label>
                <input
                  value={baixaForm.valor_baixa}
                  onChange={(e) => setBaixaForm((f) => ({ ...f, valor_baixa: e.target.value }))}
                  required
                  className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm"
                />
              </div>
              <div>
                <label className="block text-xs font-medium mb-1">Meio de pagamento</label>
                <select
                  value={baixaForm.meio_pagamento}
                  onChange={(e) => setBaixaForm((f) => ({ ...f, meio_pagamento: e.target.value }))}
                  className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm"
                >
                  <option value="">Selecione...</option>
                  <option value="pix">PIX</option>
                  <option value="ted">TED</option>
                  <option value="boleto">Boleto</option>
                  <option value="dinheiro">Dinheiro</option>
                  <option value="cartao">Cartão</option>
                  <option value="cheque">Cheque</option>
                </select>
              </div>
              <div className="flex justify-end gap-2 pt-2">
                <button type="button" onClick={() => setBaixaId(null)} className="px-4 py-2 rounded-lg border border-border text-sm hover:bg-accent">Cancelar</button>
                <button type="submit" disabled={savingBaixa} className="px-4 py-2 rounded-lg bg-green-600 text-white text-sm disabled:opacity-60">
                  {savingBaixa ? 'Salvando...' : 'Confirmar'}
                </button>
              </div>
            </form>
          </div>
        </div>
      )}
    </div>
  );
}
