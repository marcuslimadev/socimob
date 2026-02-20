import { useEffect, useMemo, useRef, useState } from 'react';
import { Loader2, RefreshCcw } from 'lucide-react';
import { toast } from 'sonner';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';

interface ContratoItem {
  id: number;
  status: string;
  inicio?: string;
  fim?: string;
  dia_vencimento?: number;
  valor_aluguel?: number;
  locador?: { id: number; nome: string };
  locatario?: { id: number; nome: string };
  imovel?: { id: number; titulo?: string; codigo?: string };
}

interface CobrancaItem {
  id: number;
  contrato_id: number;
  competencia: string;
  vencimento: string;
  status: string;
  valor_total: number;
}

interface ChamadoItem {
  id: number;
  protocolo?: string;
  assunto: string;
  categoria?: string;
  prioridade: string;
  status: string;
  created_at?: string;
  mensagens?: ChamadoMensagemItem[];
}

interface ChamadoMensagemItem {
  id: number;
  chamado_id: number;
  autor_user_id?: number | null;
  interna?: boolean;
  mensagem: string;
  created_at?: string;
}

interface LancamentoItem {
  id: number;
  tipo: 'conta_receber' | 'conta_pagar' | 'transferencia';
  categoria?: string;
  descricao?: string;
  vencimento?: string;
  valor: number;
  valor_em_aberto: number;
  status: string;
}

type Tab = 'contratos' | 'cobrancas' | 'lancamentos' | 'chamados';

// --- Helpers de label ---
const tipoLabel = (tipo: string) => {
  if (tipo === 'conta_receber') return 'A Receber';
  if (tipo === 'conta_pagar') return 'A Pagar';
  if (tipo === 'transferencia') return 'Transferência';
  return tipo;
};

const statusLabel = (status: string) => {
  if (status === 'aberto') return 'Em aberto';
  if (status === 'parcial') return 'Parcial';
  if (status === 'liquidado') return 'Pago';
  if (status === 'ativo') return 'Ativo';
  if (status === 'rascunho') return 'Rascunho';
  if (status === 'finalizado') return 'Finalizado';
  if (status === 'pendente') return 'Pendente';
  if (status === 'pago') return 'Pago';
  if (status === 'vencido') return 'Vencido';
  if (status === 'em_andamento') return 'Em andamento';
  if (status === 'resolvido') return 'Resolvido';
  if (status === 'fechado') return 'Fechado';
  return status;
};

const prioridadeLabel = (p: string) => {
  if (p === 'baixa') return 'Baixa';
  if (p === 'media') return 'Média';
  if (p === 'alta') return 'Alta';
  if (p === 'critica') return 'Crítica';
  return p;
};

const formatMoney = (value?: number) =>
  Number(value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const formatDate = (value?: string) => {
  if (!value) return '-';
  const [year, month, day] = value.slice(0, 10).split('-');
  if (!year || !month || !day) return value;
  return `${day}/${month}/${year}`;
};

const formatDateTime = (value?: string) => {
  if (!value) return '-';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString('pt-BR');
};

const normalizeCurrencyInput = (value: string) => value.replace(/[^\d,\.]/g, '');

const parsePtBrCurrency = (value: string) => {
  if (!value?.trim()) return 0;
  const normalized = value.includes(',')
    ? value.replace(/\./g, '').replace(',', '.')
    : value;
  const parsed = Number(normalized);
  return Number.isFinite(parsed) ? parsed : 0;
};

const statusRowClass = (status: string) => {
  if (status === 'aberto') return 'bg-amber-50/50';
  if (status === 'parcial') return 'bg-blue-50/50';
  if (status === 'liquidado') return 'bg-emerald-50/50';
  return '';
};

const statusBadgeClass = (status: string) => {
  if (status === 'aberto') return 'bg-amber-100 text-amber-800';
  if (status === 'parcial') return 'bg-blue-100 text-blue-800';
  if (status === 'liquidado' || status === 'pago') return 'bg-emerald-100 text-emerald-800';
  if (status === 'ativo') return 'bg-emerald-100 text-emerald-800';
  if (status === 'resolvido' || status === 'fechado') return 'bg-emerald-100 text-emerald-800';
  if (status === 'em_andamento') return 'bg-blue-100 text-blue-800';
  if (status === 'vencido') return 'bg-red-100 text-red-800';
  return 'bg-muted text-foreground';
};

export default function AdminGestaoLocacao() {
  const [activeTab, setActiveTab] = useState<Tab>('contratos');
  const [isLoading, setIsLoading] = useState(true);
  const [isGenerating, setIsGenerating] = useState(false);
  const [contratos, setContratos] = useState<ContratoItem[]>([]);
  const [cobrancas, setCobrancas] = useState<CobrancaItem[]>([]);
  const [lancamentos, setLancamentos] = useState<LancamentoItem[]>([]);
  const [chamados, setChamados] = useState<ChamadoItem[]>([]);
  const [contratoId, setContratoId] = useState('');
  const [competencia, setCompetencia] = useState(() => {
    const now = new Date();
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`;
  });
  const [isCreatingLancamento, setIsCreatingLancamento] = useState(false);
  const [isRegisteringBaixaId, setIsRegisteringBaixaId] = useState<number | null>(null);
  const [lancamentoTipoFiltro, setLancamentoTipoFiltro] = useState('todos');
  const [lancamentoStatusFiltro, setLancamentoStatusFiltro] = useState('todos');
  const [lancamentoVencimentoInicio, setLancamentoVencimentoInicio] = useState('');
  const [lancamentoVencimentoFim, setLancamentoVencimentoFim] = useState('');
  const [lancamentoSortBy, setLancamentoSortBy] = useState<'vencimento' | 'valor' | 'id'>('vencimento');
  const [lancamentoSortDir, setLancamentoSortDir] = useState<'asc' | 'desc'>('desc');
  const [lancamentoPagina, setLancamentoPagina] = useState(1);
  const lancamentoPageSize = 10;
  const [baixaParcial, setBaixaParcial] = useState<Record<number, { valor: string; data: string }>>({});
  const [novoLancamento, setNovoLancamento] = useState({
    tipo: 'conta_receber',
    categoria: '',
    descricao: '',
    vencimento: '',
    valor: '',
  });
  const [selectedChamadoId, setSelectedChamadoId] = useState<number | null>(null);
  const [novaMensagemChamado, setNovaMensagemChamado] = useState('');
  const [novaMensagemInterna, setNovaMensagemInterna] = useState(false);
  const [isSendingChamadoMensagem, setIsSendingChamadoMensagem] = useState(false);
  const [chamadoStatusFiltro, setChamadoStatusFiltro] = useState('todos');
  const [chamadoBusca, setChamadoBusca] = useState('');
  const chamadoMensagensEndRef = useRef<HTMLDivElement | null>(null);

  const contratosAtivos = useMemo(() => contratos.filter((c) => c.status === 'ativo').length, [contratos]);

  const lancamentosFiltrados = useMemo(() => {
    return lancamentos.filter((item) => {
      const tipoOk = lancamentoTipoFiltro === 'todos' || item.tipo === lancamentoTipoFiltro;
      const statusOk = lancamentoStatusFiltro === 'todos' || item.status === lancamentoStatusFiltro;
      const vencimentoDate = item.vencimento ? new Date(item.vencimento) : null;
      const inicioOk = !lancamentoVencimentoInicio || (vencimentoDate && vencimentoDate >= new Date(lancamentoVencimentoInicio));
      const fimOk = !lancamentoVencimentoFim || (vencimentoDate && vencimentoDate <= new Date(lancamentoVencimentoFim));
      return tipoOk && statusOk && Boolean(inicioOk) && Boolean(fimOk);
    });
  }, [lancamentos, lancamentoTipoFiltro, lancamentoStatusFiltro, lancamentoVencimentoInicio, lancamentoVencimentoFim]);

  const lancamentosOrdenados = useMemo(() => {
    return [...lancamentosFiltrados].sort((a, b) => {
      let compare = 0;
      if (lancamentoSortBy === 'id') compare = a.id - b.id;
      else if (lancamentoSortBy === 'valor') compare = Number(a.valor) - Number(b.valor);
      else {
        const av = a.vencimento ? new Date(a.vencimento).getTime() : 0;
        const bv = b.vencimento ? new Date(b.vencimento).getTime() : 0;
        compare = av - bv;
      }
      return lancamentoSortDir === 'asc' ? compare : -compare;
    });
  }, [lancamentosFiltrados, lancamentoSortBy, lancamentoSortDir]);

  const resumoLancamentos = useMemo(() => {
    const total = lancamentosFiltrados.reduce((acc, item) => acc + Number(item.valor || 0), 0);
    const aberto = lancamentosFiltrados.reduce((acc, item) => acc + Number(item.valor_em_aberto || 0), 0);
    return { total, aberto, liquidado: total - aberto };
  }, [lancamentosFiltrados]);

  const totalLancamentoPaginas = Math.max(1, Math.ceil(lancamentosOrdenados.length / lancamentoPageSize));
  const lancamentosPaginados = useMemo(() => {
    const start = (lancamentoPagina - 1) * lancamentoPageSize;
    return lancamentosOrdenados.slice(start, start + lancamentoPageSize);
  }, [lancamentosOrdenados, lancamentoPagina]);

  useEffect(() => { setLancamentoPagina(1); }, [lancamentoTipoFiltro, lancamentoStatusFiltro, lancamentoVencimentoInicio, lancamentoVencimentoFim, lancamentoSortBy, lancamentoSortDir]);

  useEffect(() => {
    if (lancamentoPagina > totalLancamentoPaginas) setLancamentoPagina(totalLancamentoPaginas);
  }, [lancamentoPagina, totalLancamentoPaginas]);

  const chamadoSelecionado = useMemo(() => chamados.find((c) => c.id === selectedChamadoId) || null, [chamados, selectedChamadoId]);

  const mensagensChamadoSelecionado = useMemo(() => {
    if (!chamadoSelecionado?.mensagens?.length) return [];
    return [...chamadoSelecionado.mensagens].sort((a, b) => {
      const av = a.created_at ? new Date(a.created_at).getTime() : 0;
      const bv = b.created_at ? new Date(b.created_at).getTime() : 0;
      return av - bv;
    });
  }, [chamadoSelecionado]);

  const chamadosFiltrados = useMemo(() => {
    const termo = chamadoBusca.trim().toLowerCase();
    return chamados.filter((item) => {
      const statusOk = chamadoStatusFiltro === 'todos' || item.status === chamadoStatusFiltro;
      const textoBase = `${item.protocolo || ''} ${item.assunto || ''} ${item.categoria || ''}`.toLowerCase();
      return statusOk && (!termo || textoBase.includes(termo));
    });
  }, [chamados, chamadoStatusFiltro, chamadoBusca]);

  useEffect(() => {
    if (activeTab !== 'chamados' || !chamadoSelecionado) return;
    chamadoMensagensEndRef.current?.scrollIntoView({ behavior: 'smooth', block: 'end' });
  }, [activeTab, chamadoSelecionado, mensagensChamadoSelecionado.length]);

  const loadAll = async () => {
    setIsLoading(true);
    try {
      const [contratosResp, cobrancasResp, lancamentosResp, chamadosResp] = await Promise.all([
        api.get('/admin/financeiro/contratos'),
        api.get('/admin/financeiro/cobrancas-contrato'),
        api.get('/admin/financeiro/lancamentos'),
        api.get('/admin/operacao/chamados'),
      ]);
      setContratos(contratosResp.data?.items || []);
      setCobrancas(cobrancasResp.data?.items || []);
      setLancamentos(lancamentosResp.data?.items || []);
      setChamados(chamadosResp.data?.items || []);
    } catch {
      toast.error('Não foi possível carregar os dados');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => { loadAll(); }, []);

  const handleGenerateCharge = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!contratoId || !competencia) { toast.error('Selecione o contrato e a competência'); return; }
    setIsGenerating(true);
    try {
      await api.post(`/admin/financeiro/contratos/${contratoId}/gerar-cobranca`, { competencia });
      toast.success('Cobrança gerada com sucesso');
      await loadAll();
    } catch (error: any) {
      toast.error(error?.response?.data?.message || 'Erro ao gerar cobrança');
    } finally {
      setIsGenerating(false);
    }
  };

  const handleUpdateTicketStatus = async (id: number, status: string) => {
    try {
      await api.patch(`/admin/operacao/chamados/${id}`, { status });
      toast.success('Chamado atualizado');
      await loadAll();
    } catch {
      toast.error('Não foi possível atualizar o chamado');
    }
  };

  const handleSelectChamado = (id: number) => {
    setSelectedChamadoId(id);
    setNovaMensagemChamado('');
    setNovaMensagemInterna(false);
  };

  const handleSendChamadoMensagem = async () => {
    if (!selectedChamadoId) { toast.error('Selecione um chamado'); return; }
    if (!novaMensagemChamado.trim()) { toast.error('Digite uma mensagem'); return; }
    setIsSendingChamadoMensagem(true);
    try {
      await api.post(`/admin/operacao/chamados/${selectedChamadoId}/mensagens`, {
        mensagem: novaMensagemChamado.trim(),
        interna: novaMensagemInterna,
      });
      toast.success('Mensagem enviada');
      setNovaMensagemChamado('');
      await loadAll();
    } catch (error: any) {
      toast.error(error?.response?.data?.message || 'Erro ao enviar mensagem');
    } finally {
      setIsSendingChamadoMensagem(false);
    }
  };

  const handleCreateLancamento = async (event: React.FormEvent) => {
    event.preventDefault();
    const valorLancamento = parsePtBrCurrency(novoLancamento.valor);
    if (!novoLancamento.tipo || !valorLancamento || valorLancamento <= 0) {
      toast.error('Informe tipo e valor do lançamento'); return;
    }
    setIsCreatingLancamento(true);
    try {
      await api.post('/admin/financeiro/lancamentos', {
        tipo: novoLancamento.tipo,
        categoria: novoLancamento.categoria || undefined,
        descricao: novoLancamento.descricao || undefined,
        vencimento: novoLancamento.vencimento || undefined,
        valor: valorLancamento,
      });
      toast.success('Lançamento criado');
      setNovoLancamento({ tipo: 'conta_receber', categoria: '', descricao: '', vencimento: '', valor: '' });
      await loadAll();
    } catch (error: any) {
      toast.error(error?.response?.data?.message || 'Erro ao criar lançamento');
    } finally {
      setIsCreatingLancamento(false);
    }
  };

  const handleQuickBaixa = async (item: LancamentoItem) => {
    if (!item.valor_em_aberto || item.valor_em_aberto <= 0) { toast.error('Lançamento já está pago'); return; }
    setIsRegisteringBaixaId(item.id);
    try {
      await api.post(`/admin/financeiro/lancamentos/${item.id}/baixas`, {
        data_baixa: new Date().toISOString().slice(0, 10),
        valor_baixa: Number(item.valor_em_aberto),
        meio_pagamento: 'manual',
        status_conciliacao: 'pendente',
      });
      toast.success('Baixa total registrada');
      await loadAll();
    } catch (error: any) {
      toast.error(error?.response?.data?.message || 'Erro ao registrar baixa');
    } finally {
      setIsRegisteringBaixaId(null);
    }
  };

  const handlePartialBaixa = async (item: LancamentoItem) => {
    if (!item.valor_em_aberto || item.valor_em_aberto <= 0) { toast.error('Lançamento já está pago'); return; }
    const draft = baixaParcial[item.id] || { valor: '', data: '' };
    const valorBaixa = parsePtBrCurrency(draft.valor);
    if (!valorBaixa || valorBaixa <= 0) { toast.error('Informe um valor de baixa válido'); return; }
    if (valorBaixa > Number(item.valor_em_aberto)) { toast.error('Valor maior que o saldo em aberto'); return; }
    setIsRegisteringBaixaId(item.id);
    try {
      await api.post(`/admin/financeiro/lancamentos/${item.id}/baixas`, {
        data_baixa: draft.data || new Date().toISOString().slice(0, 10),
        valor_baixa: valorBaixa,
        meio_pagamento: 'manual',
        status_conciliacao: 'pendente',
      });
      toast.success('Baixa parcial registrada');
      setBaixaParcial((prev) => ({ ...prev, [item.id]: { valor: '', data: '' } }));
      await loadAll();
    } catch (error: any) {
      toast.error(error?.response?.data?.message || 'Erro ao registrar baixa');
    } finally {
      setIsRegisteringBaixaId(null);
    }
  };

  const exportLancamentosCsv = (items: LancamentoItem[], filenamePrefix: string) => {
    const header = ['id', 'tipo', 'categoria', 'descricao', 'vencimento', 'valor', 'em_aberto', 'status'];
    const escape = (v: string | number | null | undefined) => `"${String(v ?? '').replace(/"/g, '""')}"`;
    const rows = items.map((item) => [
      item.id, item.tipo, item.categoria || '', item.descricao || '',
      item.vencimento || '', Number(item.valor || 0).toFixed(2),
      Number(item.valor_em_aberto || 0).toFixed(2), item.status,
    ]);
    const csv = [header, ...rows].map((line) => line.map((cell) => escape(cell)).join(';')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `${filenamePrefix}_${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
    toast.success('CSV exportado');
  };

  // ---------- Render ----------
  return (
    <div className="flex">
      <Sidebar />
      <div className="page-shell">
        <div className="max-w-7xl mx-auto space-y-6">

          {/* Header */}
          <div className="page-header">
            <div>
              <h1 className="page-title mb-2">Locação e Operação</h1>
              <p className="page-subtitle">Contratos, cobranças, lançamentos e chamados</p>
            </div>
            <button
              onClick={loadAll}
              className="flex items-center gap-2 px-4 py-2 rounded-lg border border-border bg-card hover:bg-accent transition-colors"
            >
              <RefreshCcw size={16} /> Atualizar
            </button>
          </div>

          {/* Cards de resumo global */}
          <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div className="glass-panel rounded-xl p-4">
              <p className="text-sm text-muted-foreground">Contratos</p>
              <p className="text-2xl font-semibold">{contratos.length}</p>
              <p className="text-xs text-muted-foreground mt-1">{contratosAtivos} ativo(s)</p>
            </div>
            <div className="glass-panel rounded-xl p-4">
              <p className="text-sm text-muted-foreground">Cobranças</p>
              <p className="text-2xl font-semibold">{cobrancas.length}</p>
            </div>
            <div className="glass-panel rounded-xl p-4">
              <p className="text-sm text-muted-foreground">Lançamentos</p>
              <p className="text-2xl font-semibold">{lancamentos.length}</p>
            </div>
            <div className="glass-panel rounded-xl p-4">
              <p className="text-sm text-muted-foreground">Chamados</p>
              <p className="text-2xl font-semibold">{chamados.length}</p>
            </div>
          </div>

          {/* Abas */}
          <div className="glass-panel rounded-2xl p-3 inline-flex gap-2">
            {(['contratos', 'cobrancas', 'lancamentos', 'chamados'] as Tab[]).map((tab) => {
              const labels: Record<Tab, string> = {
                contratos: 'Contratos',
                cobrancas: 'Cobranças',
                lancamentos: 'Lançamentos',
                chamados: 'Chamados',
              };
              return (
                <button
                  key={tab}
                  onClick={() => setActiveTab(tab)}
                  className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${activeTab === tab ? 'bg-primary text-primary-foreground' : 'hover:bg-accent'}`}
                >
                  {labels[tab]}
                </button>
              );
            })}
          </div>

          {/* Conteúdo */}
          {isLoading ? (
            <div className="glass-panel rounded-2xl p-12 flex justify-center">
              <Loader2 className="animate-spin" />
            </div>
          ) : (
            <>
              {/* ===== CONTRATOS ===== */}
              {activeTab === 'contratos' && (
                <div className="glass-panel rounded-2xl overflow-auto">
                  {contratos.length === 0 ? (
                    <p className="p-8 text-center text-sm text-muted-foreground">Nenhum contrato cadastrado.</p>
                  ) : (
                    <table className="w-full min-w-[720px]">
                      <thead>
                        <tr className="border-b border-border">
                          <th className="text-left p-3 text-sm text-muted-foreground">ID</th>
                          <th className="text-left p-3 text-sm text-muted-foreground">Imóvel</th>
                          <th className="text-left p-3 text-sm text-muted-foreground">Locador</th>
                          <th className="text-left p-3 text-sm text-muted-foreground">Locatário</th>
                          <th className="text-left p-3 text-sm text-muted-foreground">Aluguel</th>
                          <th className="text-left p-3 text-sm text-muted-foreground">Status</th>
                        </tr>
                      </thead>
                      <tbody>
                        {contratos.map((item) => (
                          <tr key={item.id} className="border-b border-border/50">
                            <td className="p-3 text-muted-foreground text-sm">#{item.id}</td>
                            <td className="p-3">{item.imovel?.titulo || item.imovel?.codigo || '-'}</td>
                            <td className="p-3">{item.locador?.nome || '-'}</td>
                            <td className="p-3">{item.locatario?.nome || '-'}</td>
                            <td className="p-3">R$ {formatMoney(item.valor_aluguel)}</td>
                            <td className="p-3">
                              <span className={`inline-flex px-2 py-1 rounded-full text-xs font-medium ${statusBadgeClass(item.status)}`}>
                                {statusLabel(item.status)}
                              </span>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  )}
                </div>
              )}

              {/* ===== COBRANÇAS ===== */}
              {activeTab === 'cobrancas' && (
                <div className="space-y-4">
                  {/* Gerar cobrança */}
                  <form onSubmit={handleGenerateCharge} className="glass-panel rounded-2xl p-4 flex flex-col md:flex-row gap-3">
                    <select
                      value={contratoId}
                      onChange={(e) => setContratoId(e.target.value)}
                      className="bg-background border border-border rounded-lg px-3 py-2 md:min-w-[320px]"
                    >
                      <option value="">Selecione o contrato</option>
                      {contratos.map((item) => (
                        <option key={item.id} value={item.id}>
                          #{item.id} — {item.locador?.nome || 'Locador'} › {item.locatario?.nome || 'Locatário'}
                          {item.imovel?.titulo ? ` (${item.imovel.titulo})` : ''}
                        </option>
                      ))}
                    </select>
                    <input
                      type="month"
                      value={competencia}
                      onChange={(e) => setCompetencia(e.target.value)}
                      className="bg-background border border-border rounded-lg px-3 py-2"
                    />
                    <button
                      type="submit"
                      disabled={isGenerating}
                      className="px-4 py-2 rounded-lg bg-primary text-primary-foreground disabled:opacity-60"
                    >
                      {isGenerating ? 'Gerando...' : 'Gerar cobrança'}
                    </button>
                  </form>

                  {/* Tabela */}
                  <div className="glass-panel rounded-2xl overflow-auto">
                    {cobrancas.length === 0 ? (
                      <p className="p-8 text-center text-sm text-muted-foreground">Nenhuma cobrança gerada.</p>
                    ) : (
                      <table className="w-full min-w-[680px]">
                        <thead>
                          <tr className="border-b border-border">
                            <th className="text-left p-3 text-sm text-muted-foreground">ID</th>
                            <th className="text-left p-3 text-sm text-muted-foreground">Contrato</th>
                            <th className="text-left p-3 text-sm text-muted-foreground">Competência</th>
                            <th className="text-left p-3 text-sm text-muted-foreground">Vencimento</th>
                            <th className="text-left p-3 text-sm text-muted-foreground">Valor</th>
                            <th className="text-left p-3 text-sm text-muted-foreground">Status</th>
                          </tr>
                        </thead>
                        <tbody>
                          {cobrancas.map((item) => {
                            const contrato = contratos.find((c) => c.id === item.contrato_id);
                            const contratoLabel = contrato
                              ? `${contrato.locador?.nome || 'Locador'} › ${contrato.locatario?.nome || 'Locatário'}`
                              : `#${item.contrato_id}`;
                            return (
                              <tr key={item.id} className="border-b border-border/50">
                                <td className="p-3 text-muted-foreground text-sm">#{item.id}</td>
                                <td className="p-3">{contratoLabel}</td>
                                <td className="p-3">{item.competencia}</td>
                                <td className="p-3">{formatDate(item.vencimento)}</td>
                                <td className="p-3">R$ {formatMoney(item.valor_total)}</td>
                                <td className="p-3">
                                  <span className={`inline-flex px-2 py-1 rounded-full text-xs font-medium ${statusBadgeClass(item.status)}`}>
                                    {statusLabel(item.status)}
                                  </span>
                                </td>
                              </tr>
                            );
                          })}
                        </tbody>
                      </table>
                    )}
                  </div>
                </div>
              )}

              {/* ===== CHAMADOS ===== */}
              {activeTab === 'chamados' && (
                <div className="space-y-4">
                  {/* Filtros */}
                  <div className="glass-panel rounded-2xl p-4 flex flex-wrap gap-3 items-end">
                    <div>
                      <label className="block text-xs text-muted-foreground mb-1">Status</label>
                      <select
                        value={chamadoStatusFiltro}
                        onChange={(e) => setChamadoStatusFiltro(e.target.value)}
                        className="bg-background border border-border rounded-lg px-3 py-2"
                      >
                        <option value="todos">Todos</option>
                        <option value="aberto">Aberto</option>
                        <option value="em_andamento">Em andamento</option>
                        <option value="resolvido">Resolvido</option>
                        <option value="fechado">Fechado</option>
                      </select>
                    </div>
                    <div className="flex-1 min-w-[200px]">
                      <label className="block text-xs text-muted-foreground mb-1">Buscar</label>
                      <input
                        value={chamadoBusca}
                        onChange={(e) => setChamadoBusca(e.target.value)}
                        placeholder="Protocolo, assunto ou categoria"
                        className="w-full bg-background border border-border rounded-lg px-3 py-2"
                      />
                    </div>
                    <button
                      type="button"
                      onClick={() => { setChamadoStatusFiltro('todos'); setChamadoBusca(''); }}
                      className="px-4 py-2 rounded-lg border border-border hover:bg-accent"
                    >
                      Limpar
                    </button>
                  </div>

                  {/* Tabela */}
                  <div className="glass-panel rounded-2xl overflow-auto">
                    {chamadosFiltrados.length === 0 ? (
                      <p className="p-8 text-center text-sm text-muted-foreground">Nenhum chamado encontrado.</p>
                    ) : (
                      <table className="w-full min-w-[760px]">
                        <thead>
                          <tr className="border-b border-border">
                            <th className="text-left p-3 text-sm text-muted-foreground">Protocolo</th>
                            <th className="text-left p-3 text-sm text-muted-foreground">Assunto</th>
                            <th className="text-left p-3 text-sm text-muted-foreground">Prioridade</th>
                            <th className="text-left p-3 text-sm text-muted-foreground">Status</th>
                            <th className="text-left p-3 text-sm text-muted-foreground">Alterar status</th>
                            <th className="text-left p-3 text-sm text-muted-foreground">Conversa</th>
                          </tr>
                        </thead>
                        <tbody>
                          {chamadosFiltrados.map((item) => (
                            <tr key={item.id} className="border-b border-border/50">
                              <td className="p-3 text-sm">{item.protocolo || `CH-${item.id}`}</td>
                              <td className="p-3">{item.assunto}</td>
                              <td className="p-3 text-sm">{prioridadeLabel(item.prioridade)}</td>
                              <td className="p-3">
                                <span className={`inline-flex px-2 py-1 rounded-full text-xs font-medium ${statusBadgeClass(item.status)}`}>
                                  {statusLabel(item.status)}
                                </span>
                              </td>
                              <td className="p-3">
                                <select
                                  value={item.status}
                                  onChange={(e) => handleUpdateTicketStatus(item.id, e.target.value)}
                                  className="bg-background border border-border rounded-lg px-2 py-1 text-sm"
                                >
                                  <option value="aberto">Aberto</option>
                                  <option value="em_andamento">Em andamento</option>
                                  <option value="resolvido">Resolvido</option>
                                  <option value="fechado">Fechado</option>
                                </select>
                              </td>
                              <td className="p-3">
                                <button
                                  type="button"
                                  onClick={() => handleSelectChamado(item.id)}
                                  className={`px-3 py-1.5 rounded-lg border text-sm ${selectedChamadoId === item.id ? 'bg-primary text-primary-foreground border-primary' : 'border-border hover:bg-accent'}`}
                                >
                                  {selectedChamadoId === item.id ? 'Selecionado' : 'Ver conversa'}
                                </button>
                              </td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    )}
                  </div>

                  {/* Painel de conversa */}
                  <div className="glass-panel rounded-2xl p-4 space-y-3">
                    {!chamadoSelecionado ? (
                      <p className="text-sm text-muted-foreground">Selecione um chamado acima para ver a conversa.</p>
                    ) : (
                      <>
                        <div className="flex flex-wrap items-center justify-between gap-2">
                          <p className="text-sm font-medium">
                            {chamadoSelecionado.protocolo || `CH-${chamadoSelecionado.id}`} — {chamadoSelecionado.assunto}
                          </p>
                          <p className="text-xs text-muted-foreground">{mensagensChamadoSelecionado.length} mensagem(ns)</p>
                        </div>

                        <div className="max-h-72 overflow-auto border border-border rounded-xl p-3 space-y-2 bg-background/50">
                          {mensagensChamadoSelecionado.length === 0 ? (
                            <p className="text-sm text-muted-foreground">Sem mensagens neste chamado.</p>
                          ) : (
                            mensagensChamadoSelecionado.map((mensagem) => (
                              <div key={mensagem.id} className="rounded-lg border border-border/60 bg-card px-3 py-2">
                                <div className="flex items-center justify-between gap-2">
                                  <span className={`text-xs font-medium px-2 py-0.5 rounded-full ${mensagem.interna ? 'bg-amber-100 text-amber-800' : 'bg-blue-100 text-blue-800'}`}>
                                    {mensagem.interna ? 'Interna' : 'Cliente / Admin'}
                                  </span>
                                  <p className="text-xs text-muted-foreground">{formatDateTime(mensagem.created_at)}</p>
                                </div>
                                <p className="text-sm mt-1 whitespace-pre-wrap">{mensagem.mensagem}</p>
                              </div>
                            ))
                          )}
                          <div ref={chamadoMensagensEndRef} />
                        </div>

                        <div className="space-y-2">
                          <textarea
                            value={novaMensagemChamado}
                            onChange={(e) => setNovaMensagemChamado(e.target.value)}
                            className="w-full min-h-20 bg-background border border-border rounded-lg px-3 py-2"
                            placeholder="Digite uma resposta..."
                          />
                          <div className="flex items-center justify-between gap-3 flex-wrap">
                            <label className="inline-flex items-center gap-2 text-sm text-muted-foreground">
                              <input
                                type="checkbox"
                                checked={novaMensagemInterna}
                                onChange={(e) => setNovaMensagemInterna(e.target.checked)}
                                className="rounded border-border"
                              />
                              Mensagem interna (não visível ao cliente)
                            </label>
                            <button
                              type="button"
                              onClick={handleSendChamadoMensagem}
                              disabled={isSendingChamadoMensagem || !novaMensagemChamado.trim()}
                              className="px-4 py-2 rounded-lg bg-primary text-primary-foreground disabled:opacity-60"
                            >
                              {isSendingChamadoMensagem ? 'Enviando...' : 'Enviar'}
                            </button>
                          </div>
                        </div>
                      </>
                    )}
                  </div>
                </div>
              )}

              {/* ===== LANÇAMENTOS ===== */}
              {activeTab === 'lancamentos' && (
                <div className="space-y-4">
                  {/* Resumo financeiro */}
                  <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div className="glass-panel rounded-xl p-4">
                      <p className="text-sm text-muted-foreground">Total filtrado</p>
                      <p className="text-2xl font-semibold">R$ {formatMoney(resumoLancamentos.total)}</p>
                    </div>
                    <div className="glass-panel rounded-xl p-4">
                      <p className="text-sm text-muted-foreground">Em aberto</p>
                      <p className="text-2xl font-semibold text-amber-600">R$ {formatMoney(resumoLancamentos.aberto)}</p>
                    </div>
                    <div className="glass-panel rounded-xl p-4">
                      <p className="text-sm text-muted-foreground">Pago</p>
                      <p className="text-2xl font-semibold text-emerald-600">R$ {formatMoney(resumoLancamentos.liquidado)}</p>
                    </div>
                  </div>

                  {/* Novo lançamento */}
                  <form onSubmit={handleCreateLancamento} className="glass-panel rounded-2xl p-4 grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
                    <div>
                      <label className="block text-xs text-muted-foreground mb-1">Tipo</label>
                      <select
                        value={novoLancamento.tipo}
                        onChange={(e) => setNovoLancamento((prev) => ({ ...prev, tipo: e.target.value }))}
                        className="w-full bg-background border border-border rounded-lg px-3 py-2"
                      >
                        <option value="conta_receber">A Receber</option>
                        <option value="conta_pagar">A Pagar</option>
                        <option value="transferencia">Transferência</option>
                      </select>
                    </div>
                    <div>
                      <label className="block text-xs text-muted-foreground mb-1">Categoria</label>
                      <input
                        value={novoLancamento.categoria}
                        onChange={(e) => setNovoLancamento((prev) => ({ ...prev, categoria: e.target.value }))}
                        className="w-full bg-background border border-border rounded-lg px-3 py-2"
                        placeholder="aluguel, taxa…"
                      />
                    </div>
                    <div className="md:col-span-2">
                      <label className="block text-xs text-muted-foreground mb-1">Descrição</label>
                      <input
                        value={novoLancamento.descricao}
                        onChange={(e) => setNovoLancamento((prev) => ({ ...prev, descricao: e.target.value }))}
                        className="w-full bg-background border border-border rounded-lg px-3 py-2"
                        placeholder="Descrição do lançamento"
                      />
                    </div>
                    <div>
                      <label className="block text-xs text-muted-foreground mb-1">Vencimento</label>
                      <input
                        type="date"
                        value={novoLancamento.vencimento}
                        onChange={(e) => setNovoLancamento((prev) => ({ ...prev, vencimento: e.target.value }))}
                        className="w-full bg-background border border-border rounded-lg px-3 py-2"
                      />
                    </div>
                    <div>
                      <label className="block text-xs text-muted-foreground mb-1">Valor (R$)</label>
                      <input
                        type="text"
                        inputMode="decimal"
                        value={novoLancamento.valor}
                        onChange={(e) => setNovoLancamento((prev) => ({ ...prev, valor: normalizeCurrencyInput(e.target.value) }))}
                        className="w-full bg-background border border-border rounded-lg px-3 py-2"
                        placeholder="0,00"
                      />
                    </div>
                    <button
                      type="submit"
                      disabled={isCreatingLancamento}
                      className="px-4 py-2 rounded-lg bg-primary text-primary-foreground disabled:opacity-60 md:col-span-6 md:justify-self-start"
                    >
                      {isCreatingLancamento ? 'Salvando...' : '+ Novo lançamento'}
                    </button>
                  </form>

                  {/* Filtros em uma linha */}
                  <div className="glass-panel rounded-2xl p-4 flex flex-wrap gap-3 items-end">
                    <div>
                      <label className="block text-xs text-muted-foreground mb-1">Tipo</label>
                      <select
                        value={lancamentoTipoFiltro}
                        onChange={(e) => setLancamentoTipoFiltro(e.target.value)}
                        className="bg-background border border-border rounded-lg px-3 py-2"
                      >
                        <option value="todos">Todos</option>
                        <option value="conta_receber">A Receber</option>
                        <option value="conta_pagar">A Pagar</option>
                        <option value="transferencia">Transferência</option>
                      </select>
                    </div>
                    <div>
                      <label className="block text-xs text-muted-foreground mb-1">Status</label>
                      <select
                        value={lancamentoStatusFiltro}
                        onChange={(e) => setLancamentoStatusFiltro(e.target.value)}
                        className="bg-background border border-border rounded-lg px-3 py-2"
                      >
                        <option value="todos">Todos</option>
                        <option value="aberto">Em aberto</option>
                        <option value="parcial">Parcial</option>
                        <option value="liquidado">Pago</option>
                      </select>
                    </div>
                    <div>
                      <label className="block text-xs text-muted-foreground mb-1">Venc. de</label>
                      <input
                        type="date"
                        value={lancamentoVencimentoInicio}
                        onChange={(e) => setLancamentoVencimentoInicio(e.target.value)}
                        className="bg-background border border-border rounded-lg px-3 py-2"
                      />
                    </div>
                    <div>
                      <label className="block text-xs text-muted-foreground mb-1">Venc. até</label>
                      <input
                        type="date"
                        value={lancamentoVencimentoFim}
                        onChange={(e) => setLancamentoVencimentoFim(e.target.value)}
                        className="bg-background border border-border rounded-lg px-3 py-2"
                      />
                    </div>
                    <div>
                      <label className="block text-xs text-muted-foreground mb-1">Ordenar por</label>
                      <select
                        value={lancamentoSortBy}
                        onChange={(e) => setLancamentoSortBy(e.target.value as 'vencimento' | 'valor' | 'id')}
                        className="bg-background border border-border rounded-lg px-3 py-2"
                      >
                        <option value="vencimento">Vencimento</option>
                        <option value="valor">Valor</option>
                        <option value="id">ID</option>
                      </select>
                    </div>
                    <div>
                      <label className="block text-xs text-muted-foreground mb-1">Direção</label>
                      <select
                        value={lancamentoSortDir}
                        onChange={(e) => setLancamentoSortDir(e.target.value as 'asc' | 'desc')}
                        className="bg-background border border-border rounded-lg px-3 py-2"
                      >
                        <option value="desc">Mais recente</option>
                        <option value="asc">Mais antigo</option>
                      </select>
                    </div>
                    <button
                      type="button"
                      onClick={() => {
                        setLancamentoTipoFiltro('todos');
                        setLancamentoStatusFiltro('todos');
                        setLancamentoVencimentoInicio('');
                        setLancamentoVencimentoFim('');
                      }}
                      className="px-4 py-2 rounded-lg border border-border hover:bg-accent"
                    >
                      Limpar
                    </button>
                  </div>

                  {/* Tabela */}
                  <div className="glass-panel rounded-2xl overflow-auto">
                    {lancamentosPaginados.length === 0 ? (
                      <p className="p-8 text-center text-sm text-muted-foreground">Nenhum lançamento encontrado para os filtros atuais.</p>
                    ) : (
                      <table className="w-full min-w-[860px]">
                        <thead>
                          <tr className="border-b border-border">
                            <th className="text-left p-3 text-sm text-muted-foreground">Tipo</th>
                            <th className="text-left p-3 text-sm text-muted-foreground">Categoria</th>
                            <th className="text-left p-3 text-sm text-muted-foreground">Descrição</th>
                            <th className="text-left p-3 text-sm text-muted-foreground">Vencimento</th>
                            <th className="text-left p-3 text-sm text-muted-foreground">Valor</th>
                            <th className="text-left p-3 text-sm text-muted-foreground">Em aberto</th>
                            <th className="text-left p-3 text-sm text-muted-foreground">Status</th>
                            <th className="text-left p-3 text-sm text-muted-foreground">Baixa</th>
                          </tr>
                        </thead>
                        <tbody>
                          {lancamentosPaginados.map((item) => (
                            <tr key={item.id} className={`border-b border-border/50 ${statusRowClass(item.status)}`}>
                              <td className="p-3 text-sm">{tipoLabel(item.tipo)}</td>
                              <td className="p-3 text-sm">{item.categoria || '-'}</td>
                              <td className="p-3 text-sm">{item.descricao || '-'}</td>
                              <td className="p-3 text-sm">{formatDate(item.vencimento)}</td>
                              <td className="p-3 text-sm">R$ {formatMoney(item.valor)}</td>
                              <td className="p-3 text-sm">R$ {formatMoney(item.valor_em_aberto)}</td>
                              <td className="p-3">
                                <span className={`inline-flex px-2 py-1 rounded-full text-xs font-medium ${statusBadgeClass(item.status)}`}>
                                  {statusLabel(item.status)}
                                </span>
                              </td>
                              <td className="p-3">
                                {item.valor_em_aberto > 0 ? (
                                  <div className="flex items-center gap-2 flex-wrap">
                                    <input
                                      type="text"
                                      inputMode="decimal"
                                      value={baixaParcial[item.id]?.valor || ''}
                                      onChange={(e) =>
                                        setBaixaParcial((prev) => ({
                                          ...prev,
                                          [item.id]: { valor: normalizeCurrencyInput(e.target.value), data: prev[item.id]?.data || '' },
                                        }))
                                      }
                                      onKeyDown={(e) => { if (e.key === 'Enter') { e.preventDefault(); handlePartialBaixa(item); } }}
                                      placeholder="Valor parcial"
                                      className="w-28 bg-background border border-border rounded-lg px-2 py-1.5 text-sm"
                                      disabled={isRegisteringBaixaId === item.id}
                                    />
                                    <button
                                      type="button"
                                      onClick={() =>
                                        setBaixaParcial((prev) => ({
                                          ...prev,
                                          [item.id]: { valor: Number(item.valor_em_aberto).toFixed(2).replace('.', ','), data: prev[item.id]?.data || '' },
                                        }))
                                      }
                                      disabled={isRegisteringBaixaId === item.id}
                                      title="Preencher com saldo total em aberto"
                                      className="px-2 py-1.5 rounded-lg border border-border hover:bg-accent disabled:opacity-60 text-xs"
                                    >
                                      Total
                                    </button>
                                    <button
                                      type="button"
                                      onClick={() => handlePartialBaixa(item)}
                                      disabled={isRegisteringBaixaId === item.id}
                                      className="px-3 py-1.5 rounded-lg bg-primary text-primary-foreground text-sm disabled:opacity-60"
                                    >
                                      {isRegisteringBaixaId === item.id ? '...' : 'Registrar'}
                                    </button>
                                    <button
                                      type="button"
                                      onClick={() => handleQuickBaixa(item)}
                                      disabled={isRegisteringBaixaId === item.id}
                                      className="px-3 py-1.5 rounded-lg border border-emerald-500 text-emerald-700 hover:bg-emerald-50 text-sm disabled:opacity-60"
                                    >
                                      Baixa total
                                    </button>
                                  </div>
                                ) : (
                                  <span className="text-xs text-muted-foreground">Pago</span>
                                )}
                              </td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    )}
                  </div>

                  {/* Paginação + exportar */}
                  <div className="glass-panel rounded-2xl p-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <p className="text-sm text-muted-foreground">
                      {lancamentosOrdenados.length} lançamento(s) — página {lancamentoPagina} de {totalLancamentoPaginas}
                    </p>
                    <div className="flex items-center gap-2 flex-wrap">
                      <button
                        type="button"
                        onClick={() => setLancamentoPagina((prev) => Math.max(1, prev - 1))}
                        disabled={lancamentoPagina <= 1}
                        className="px-3 py-1.5 rounded-lg border border-border hover:bg-accent disabled:opacity-60 text-sm"
                      >
                        Anterior
                      </button>
                      <button
                        type="button"
                        onClick={() => setLancamentoPagina((prev) => Math.min(totalLancamentoPaginas, prev + 1))}
                        disabled={lancamentoPagina >= totalLancamentoPaginas}
                        className="px-3 py-1.5 rounded-lg border border-border hover:bg-accent disabled:opacity-60 text-sm"
                      >
                        Próxima
                      </button>
                      <span className="text-muted-foreground text-xs">|</span>
                      <button
                        type="button"
                        onClick={() => exportLancamentosCsv(lancamentosPaginados, `lancamentos_p${lancamentoPagina}`)}
                        className="px-3 py-1.5 rounded-lg border border-border hover:bg-accent text-sm"
                      >
                        Exportar página
                      </button>
                      <button
                        type="button"
                        onClick={() => exportLancamentosCsv(lancamentosOrdenados, 'lancamentos_filtrados')}
                        className="px-3 py-1.5 rounded-lg border border-border hover:bg-accent text-sm"
                      >
                        Exportar tudo
                      </button>
                    </div>
                  </div>
                </div>
              )}
            </>
          )}
        </div>
      </div>
    </div>
  );
}
