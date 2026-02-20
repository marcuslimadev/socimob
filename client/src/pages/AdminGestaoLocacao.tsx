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

const formatMoney = (value?: number) =>
  Number(value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

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
  if (status === 'liquidado') return 'bg-emerald-100 text-emerald-800';
  if (status === 'ativo') return 'bg-emerald-100 text-emerald-800';
  if (status === 'resolvido' || status === 'fechado') return 'bg-emerald-100 text-emerald-800';
  if (status === 'em_andamento') return 'bg-blue-100 text-blue-800';
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

  const contratosAtivos = useMemo(() => contratos.filter((item) => item.status === 'ativo').length, [contratos]);
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

      if (lancamentoSortBy === 'id') {
        compare = a.id - b.id;
      } else if (lancamentoSortBy === 'valor') {
        compare = Number(a.valor) - Number(b.valor);
      } else {
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
    const liquidado = total - aberto;

    return { total, aberto, liquidado };
  }, [lancamentosFiltrados]);

  const resumoPorTipo = useMemo(() => {
    const base: Record<LancamentoItem['tipo'], number> = {
      conta_receber: 0,
      conta_pagar: 0,
      transferencia: 0,
    };

    return lancamentosFiltrados.reduce((acc, item) => {
      acc[item.tipo] = (acc[item.tipo] || 0) + Number(item.valor || 0);
      return acc;
    }, base);
  }, [lancamentosFiltrados]);

  const totalLancamentoPaginas = Math.max(1, Math.ceil(lancamentosOrdenados.length / lancamentoPageSize));
  const lancamentosPaginados = useMemo(() => {
    const start = (lancamentoPagina - 1) * lancamentoPageSize;
    return lancamentosOrdenados.slice(start, start + lancamentoPageSize);
  }, [lancamentosOrdenados, lancamentoPagina]);

  useEffect(() => {
    setLancamentoPagina(1);
  }, [lancamentoTipoFiltro, lancamentoStatusFiltro, lancamentoVencimentoInicio, lancamentoVencimentoFim, lancamentoSortBy, lancamentoSortDir]);

  useEffect(() => {
    if (lancamentoPagina > totalLancamentoPaginas) {
      setLancamentoPagina(totalLancamentoPaginas);
    }
  }, [lancamentoPagina, totalLancamentoPaginas]);

  const chamadoSelecionado = useMemo(
    () => chamados.find((item) => item.id === selectedChamadoId) || null,
    [chamados, selectedChamadoId],
  );

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
      const buscaOk = !termo || textoBase.includes(termo);
      return statusOk && buscaOk;
    });
  }, [chamados, chamadoStatusFiltro, chamadoBusca]);

  useEffect(() => {
    if (activeTab !== 'chamados') return;
    if (!chamadoSelecionado) return;

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
    } catch (error) {
      toast.error('Não foi possível carregar contratos, cobranças, lançamentos e chamados');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    loadAll();
  }, []);

  const handleGenerateCharge = async (event: React.FormEvent) => {
    event.preventDefault();

    if (!contratoId || !competencia) {
      toast.error('Informe contrato e competência');
      return;
    }

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
      toast.success('Status do chamado atualizado');
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
    if (!selectedChamadoId) {
      toast.error('Selecione um chamado');
      return;
    }

    if (!novaMensagemChamado.trim()) {
      toast.error('Digite uma mensagem para enviar');
      return;
    }

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
      toast.error(error?.response?.data?.message || 'Não foi possível enviar a mensagem');
    } finally {
      setIsSendingChamadoMensagem(false);
    }
  };

  const handleCreateLancamento = async (event: React.FormEvent) => {
    event.preventDefault();

    const valorLancamento = parsePtBrCurrency(novoLancamento.valor);

    if (!novoLancamento.tipo || !valorLancamento || valorLancamento <= 0) {
      toast.error('Informe tipo e valor do lançamento');
      return;
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
      toast.success('Lançamento criado com sucesso');
      setNovoLancamento({ tipo: 'conta_receber', categoria: '', descricao: '', vencimento: '', valor: '' });
      await loadAll();
    } catch (error: any) {
      toast.error(error?.response?.data?.message || 'Erro ao criar lançamento');
    } finally {
      setIsCreatingLancamento(false);
    }
  };

  const handleQuickBaixa = async (item: LancamentoItem) => {
    if (!item.valor_em_aberto || item.valor_em_aberto <= 0) {
      toast.error('Este lançamento já está liquidado');
      return;
    }

    setIsRegisteringBaixaId(item.id);
    try {
      const hoje = new Date().toISOString().slice(0, 10);
      await api.post(`/admin/financeiro/lancamentos/${item.id}/baixas`, {
        data_baixa: hoje,
        valor_baixa: Number(item.valor_em_aberto),
        meio_pagamento: 'manual',
        status_conciliacao: 'pendente',
      });
      toast.success('Baixa registrada com sucesso');
      await loadAll();
    } catch (error: any) {
      toast.error(error?.response?.data?.message || 'Erro ao registrar baixa');
    } finally {
      setIsRegisteringBaixaId(null);
    }
  };

  const handlePartialBaixa = async (item: LancamentoItem) => {
    if (!item.valor_em_aberto || item.valor_em_aberto <= 0) {
      toast.error('Este lançamento já está liquidado');
      return;
    }

    const draft = baixaParcial[item.id] || { valor: '', data: '' };
    const valorBaixa = parsePtBrCurrency(draft.valor);

    if (!valorBaixa || valorBaixa <= 0) {
      toast.error('Informe um valor de baixa válido');
      return;
    }

    if (valorBaixa > Number(item.valor_em_aberto)) {
      toast.error('Valor da baixa não pode ser maior que o saldo em aberto');
      return;
    }

    setIsRegisteringBaixaId(item.id);
    try {
      await api.post(`/admin/financeiro/lancamentos/${item.id}/baixas`, {
        data_baixa: draft.data || new Date().toISOString().slice(0, 10),
        valor_baixa: valorBaixa,
        meio_pagamento: 'manual',
        status_conciliacao: 'pendente',
      });
      toast.success('Baixa parcial registrada com sucesso');
      setBaixaParcial((prev) => ({
        ...prev,
        [item.id]: { valor: '', data: '' },
      }));
      await loadAll();
    } catch (error: any) {
      toast.error(error?.response?.data?.message || 'Erro ao registrar baixa parcial');
    } finally {
      setIsRegisteringBaixaId(null);
    }
  };

  const exportLancamentosCsv = (items: LancamentoItem[], filenamePrefix: string) => {
    const header = ['id', 'tipo', 'categoria', 'descricao', 'vencimento', 'valor', 'valor_em_aberto', 'status'];

    const escape = (value: string | number | null | undefined) => {
      const raw = String(value ?? '').replace(/"/g, '""');
      return `"${raw}"`;
    };

    const rows = items.map((item) => [
      item.id,
      item.tipo,
      item.categoria || '',
      item.descricao || '',
      item.vencimento || '',
      Number(item.valor || 0).toFixed(2),
      Number(item.valor_em_aberto || 0).toFixed(2),
      item.status,
    ]);

    const csv = [header, ...rows]
      .map((line) => line.map((cell) => escape(cell)).join(';'))
      .join('\n');

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `${filenamePrefix}_${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);

    toast.success('CSV exportado com sucesso');
  };

  const handleExportLancamentosCsv = () => {
    exportLancamentosCsv(lancamentosOrdenados, 'lancamentos_filtrados');
  };

  const handleExportLancamentosPaginaCsv = () => {
    exportLancamentosCsv(lancamentosPaginados, `lancamentos_pagina_${lancamentoPagina}`);
  };

  return (
    <div className="flex">
      <Sidebar />
      <div className="page-shell">
        <div className="max-w-7xl mx-auto space-y-6">
          <div className="page-header">
            <div>
              <h1 className="page-title mb-2">Locação e Operação</h1>
              <p className="page-subtitle">Gestão de contratos, cobranças e chamados operacionais</p>
            </div>
            <button
              onClick={loadAll}
              className="flex items-center gap-2 px-4 py-2 rounded-lg border border-border bg-card hover:bg-accent transition-colors"
            >
              <RefreshCcw size={16} /> Atualizar
            </button>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div className="glass-panel rounded-xl p-4">
              <p className="text-sm text-muted-foreground">Contratos</p>
              <p className="text-2xl font-semibold">{contratos.length}</p>
              <p className="text-xs text-muted-foreground mt-1">Ativos: {contratosAtivos}</p>
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

          <div className="glass-panel rounded-2xl p-3 inline-flex gap-2">
            <button
              onClick={() => setActiveTab('contratos')}
              className={`px-4 py-2 rounded-lg text-sm font-medium ${activeTab === 'contratos' ? 'bg-primary text-primary-foreground' : 'hover:bg-accent'}`}
            >
              Contratos
            </button>
            <button
              onClick={() => setActiveTab('cobrancas')}
              className={`px-4 py-2 rounded-lg text-sm font-medium ${activeTab === 'cobrancas' ? 'bg-primary text-primary-foreground' : 'hover:bg-accent'}`}
            >
              Cobranças
            </button>
            <button
              onClick={() => setActiveTab('chamados')}
              className={`px-4 py-2 rounded-lg text-sm font-medium ${activeTab === 'chamados' ? 'bg-primary text-primary-foreground' : 'hover:bg-accent'}`}
            >
              Chamados
            </button>
            <button
              onClick={() => setActiveTab('lancamentos')}
              className={`px-4 py-2 rounded-lg text-sm font-medium ${activeTab === 'lancamentos' ? 'bg-primary text-primary-foreground' : 'hover:bg-accent'}`}
            >
              Lançamentos
            </button>
          </div>

          {isLoading ? (
            <div className="glass-panel rounded-2xl p-12 flex justify-center">
              <Loader2 className="animate-spin" />
            </div>
          ) : (
            <>
              {activeTab === 'contratos' && (
                <div className="glass-panel rounded-2xl overflow-auto">
                  <table className="w-full min-w-[720px]">
                    <thead>
                      <tr className="border-b border-border">
                        <th className="text-left p-3 text-sm text-muted-foreground">ID</th>
                        <th className="text-left p-3 text-sm text-muted-foreground">Imóvel</th>
                        <th className="text-left p-3 text-sm text-muted-foreground">Locador</th>
                        <th className="text-left p-3 text-sm text-muted-foreground">Locatário</th>
                        <th className="text-left p-3 text-sm text-muted-foreground">Valor</th>
                        <th className="text-left p-3 text-sm text-muted-foreground">Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      {contratos.map((item) => (
                        <tr key={item.id} className="border-b border-border/50">
                          <td className="p-3">#{item.id}</td>
                          <td className="p-3">{item.imovel?.titulo || item.imovel?.codigo || '-'}</td>
                          <td className="p-3">{item.locador?.nome || '-'}</td>
                          <td className="p-3">{item.locatario?.nome || '-'}</td>
                          <td className="p-3">R$ {formatMoney(item.valor_aluguel)}</td>
                          <td className="p-3">
                            <span className={`inline-flex px-2 py-1 rounded-full text-xs font-medium ${statusBadgeClass(item.status)}`}>
                              {item.status}
                            </span>
                          </td>
                        </tr>
                      ))}
                    </tbody>
                  </table>
                </div>
              )}

              {activeTab === 'cobrancas' && (
                <div className="space-y-4">
                  <form onSubmit={handleGenerateCharge} className="glass-panel rounded-2xl p-4 flex flex-col md:flex-row gap-3">
                    <select
                      value={contratoId}
                      onChange={(e) => setContratoId(e.target.value)}
                      className="bg-background border border-border rounded-lg px-3 py-2 md:min-w-[280px]"
                    >
                      <option value="">Selecione o contrato</option>
                      {contratos.map((item) => (
                        <option key={item.id} value={item.id}>
                          #{item.id} • {item.locador?.nome || 'Locador'} x {item.locatario?.nome || 'Locatário'}
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

                  <div className="glass-panel rounded-2xl overflow-auto">
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
                        {cobrancas.map((item) => (
                          <tr key={item.id} className="border-b border-border/50">
                            <td className="p-3">#{item.id}</td>
                            <td className="p-3">#{item.contrato_id}</td>
                            <td className="p-3">{item.competencia}</td>
                            <td className="p-3">{item.vencimento}</td>
                            <td className="p-3">R$ {formatMoney(item.valor_total)}</td>
                            <td className="p-3">
                              <span className={`inline-flex px-2 py-1 rounded-full text-xs font-medium ${statusBadgeClass(item.status)}`}>
                                {item.status}
                              </span>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>
                </div>
              )}

              {activeTab === 'chamados' && (
                <div className="space-y-4">
                  <div className="glass-panel rounded-2xl p-4 grid grid-cols-1 md:grid-cols-4 gap-3">
                    <div>
                      <label className="block text-xs text-muted-foreground mb-1">Filtrar por status</label>
                      <select
                        value={chamadoStatusFiltro}
                        onChange={(e) => setChamadoStatusFiltro(e.target.value)}
                        className="w-full bg-background border border-border rounded-lg px-3 py-2"
                      >
                        <option value="todos">todos</option>
                        <option value="aberto">aberto</option>
                        <option value="em_andamento">em_andamento</option>
                        <option value="resolvido">resolvido</option>
                        <option value="fechado">fechado</option>
                      </select>
                    </div>
                    <div className="md:col-span-2">
                      <label className="block text-xs text-muted-foreground mb-1">Buscar chamado</label>
                      <input
                        value={chamadoBusca}
                        onChange={(e) => setChamadoBusca(e.target.value)}
                        placeholder="Protocolo, assunto ou categoria"
                        className="w-full bg-background border border-border rounded-lg px-3 py-2"
                      />
                    </div>
                    <div className="flex items-end">
                      <button
                        type="button"
                        onClick={() => {
                          setChamadoStatusFiltro('todos');
                          setChamadoBusca('');
                        }}
                        className="px-4 py-2 rounded-lg border border-border hover:bg-accent"
                      >
                        Limpar filtros
                      </button>
                    </div>
                  </div>

                  <div className="glass-panel rounded-2xl overflow-auto">
                    <table className="w-full min-w-[840px]">
                      <thead>
                        <tr className="border-b border-border">
                          <th className="text-left p-3 text-sm text-muted-foreground">Protocolo</th>
                          <th className="text-left p-3 text-sm text-muted-foreground">Assunto</th>
                          <th className="text-left p-3 text-sm text-muted-foreground">Prioridade</th>
                          <th className="text-left p-3 text-sm text-muted-foreground">Status</th>
                          <th className="text-left p-3 text-sm text-muted-foreground">Ação</th>
                          <th className="text-left p-3 text-sm text-muted-foreground">Conversa</th>
                        </tr>
                      </thead>
                      <tbody>
                        {chamadosFiltrados.map((item) => (
                          <tr key={item.id} className="border-b border-border/50">
                            <td className="p-3">{item.protocolo || `CH-${item.id}`}</td>
                            <td className="p-3">{item.assunto}</td>
                            <td className="p-3">{item.prioridade}</td>
                            <td className="p-3">
                              <span className={`inline-flex px-2 py-1 rounded-full text-xs font-medium ${statusBadgeClass(item.status)}`}>
                                {item.status}
                              </span>
                            </td>
                            <td className="p-3">
                              <select
                                value={item.status}
                                onChange={(e) => handleUpdateTicketStatus(item.id, e.target.value)}
                                className="bg-background border border-border rounded-lg px-2 py-1"
                              >
                                <option value="aberto">aberto</option>
                                <option value="em_andamento">em_andamento</option>
                                <option value="resolvido">resolvido</option>
                                <option value="fechado">fechado</option>
                              </select>
                            </td>
                            <td className="p-3">
                              <button
                                type="button"
                                onClick={() => handleSelectChamado(item.id)}
                                className={`px-3 py-1.5 rounded-lg border ${selectedChamadoId === item.id ? 'bg-primary text-primary-foreground border-primary' : 'border-border hover:bg-accent'}`}
                              >
                                Ver conversa
                              </button>
                            </td>
                          </tr>
                        ))}
                        {!chamadosFiltrados.length && (
                          <tr>
                            <td colSpan={6} className="p-4 text-sm text-muted-foreground">
                              Nenhum chamado encontrado para os filtros atuais.
                            </td>
                          </tr>
                        )}
                      </tbody>
                    </table>
                  </div>

                  <div className="glass-panel rounded-2xl p-4 space-y-3">
                    {!chamadoSelecionado ? (
                      <p className="text-sm text-muted-foreground">Selecione um chamado para visualizar a conversa.</p>
                    ) : (
                      <>
                        <div className="flex flex-wrap items-center justify-between gap-2">
                          <p className="text-sm font-medium">
                            Conversa • {chamadoSelecionado.protocolo || `CH-${chamadoSelecionado.id}`}
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
                                  <p className="text-xs font-medium text-muted-foreground">
                                    {mensagem.interna ? 'Interna' : 'Cliente/Admin'}
                                  </p>
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
                            className="w-full min-h-24 bg-background border border-border rounded-lg px-3 py-2"
                            placeholder="Digite uma resposta para este chamado"
                          />
                          <label className="inline-flex items-center gap-2 text-sm text-muted-foreground">
                            <input
                              type="checkbox"
                              checked={novaMensagemInterna}
                              onChange={(e) => setNovaMensagemInterna(e.target.checked)}
                              className="rounded border-border"
                            />
                            Mensagem interna (não enviada para o cliente)
                          </label>
                          <div>
                            <button
                              type="button"
                              onClick={handleSendChamadoMensagem}
                              disabled={isSendingChamadoMensagem || !novaMensagemChamado.trim()}
                              className="px-4 py-2 rounded-lg bg-primary text-primary-foreground disabled:opacity-60"
                            >
                              {isSendingChamadoMensagem ? 'Enviando...' : 'Enviar mensagem'}
                            </button>
                          </div>
                        </div>
                      </>
                    )}
                  </div>
                </div>
              )}

              {activeTab === 'lancamentos' && (
                <div className="space-y-4">
                  <div className="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4">
                    <div className="glass-panel rounded-xl p-4">
                      <p className="text-sm text-muted-foreground">Total filtrado</p>
                      <p className="text-2xl font-semibold">R$ {formatMoney(resumoLancamentos.total)}</p>
                    </div>
                    <div className="glass-panel rounded-xl p-4">
                      <p className="text-sm text-muted-foreground">Em aberto</p>
                      <p className="text-2xl font-semibold">R$ {formatMoney(resumoLancamentos.aberto)}</p>
                    </div>
                    <div className="glass-panel rounded-xl p-4">
                      <p className="text-sm text-muted-foreground">Liquidado</p>
                      <p className="text-2xl font-semibold">R$ {formatMoney(resumoLancamentos.liquidado)}</p>
                    </div>
                    <div className="glass-panel rounded-xl p-4">
                      <p className="text-sm text-muted-foreground">Conta a receber</p>
                      <p className="text-2xl font-semibold">R$ {formatMoney(resumoPorTipo.conta_receber)}</p>
                    </div>
                    <div className="glass-panel rounded-xl p-4">
                      <p className="text-sm text-muted-foreground">Conta a pagar</p>
                      <p className="text-2xl font-semibold">R$ {formatMoney(resumoPorTipo.conta_pagar)}</p>
                    </div>
                    <div className="glass-panel rounded-xl p-4">
                      <p className="text-sm text-muted-foreground">Transferência</p>
                      <p className="text-2xl font-semibold">R$ {formatMoney(resumoPorTipo.transferencia)}</p>
                    </div>
                  </div>

                  <div className="glass-panel rounded-xl p-4 flex flex-col md:flex-row gap-2 md:items-center md:justify-end">
                    <button
                      type="button"
                      onClick={handleExportLancamentosPaginaCsv}
                      className="px-4 py-2 rounded-lg border border-border hover:bg-accent"
                    >
                      Exportar página atual
                    </button>
                    <button
                      type="button"
                      onClick={handleExportLancamentosCsv}
                      className="px-4 py-2 rounded-lg border border-border hover:bg-accent"
                    >
                      Exportar filtrado
                    </button>
                  </div>

                  <div className="glass-panel rounded-xl p-3 text-sm text-muted-foreground">
                    Exportação usa filtros e ordenação atuais; "página atual" respeita a paginação visível.
                  </div>

                  <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div className="glass-panel rounded-xl p-4">
                      <p className="text-sm text-muted-foreground">Lançamentos visíveis</p>
                      <p className="text-2xl font-semibold">{lancamentosPaginados.length}</p>
                    </div>
                    <div className="glass-panel rounded-xl p-4">
                      <p className="text-sm text-muted-foreground">Lançamentos filtrados</p>
                      <p className="text-2xl font-semibold">{lancamentosOrdenados.length}</p>
                    </div>
                    <div className="glass-panel rounded-xl p-4">
                      <p className="text-sm text-muted-foreground">Página atual</p>
                      <p className="text-2xl font-semibold">{lancamentoPagina}</p>
                    </div>
                    <div className="glass-panel rounded-xl p-4">
                      <p className="text-sm text-muted-foreground">Total de páginas</p>
                      <p className="text-2xl font-semibold">{totalLancamentoPaginas}</p>
                    </div>
                  </div>

                  <form onSubmit={handleCreateLancamento} className="glass-panel rounded-2xl p-4 grid grid-cols-1 md:grid-cols-6 gap-3 items-end">
                    <div>
                      <label className="block text-xs text-muted-foreground mb-1">Tipo</label>
                      <select
                        value={novoLancamento.tipo}
                        onChange={(e) => setNovoLancamento((prev) => ({ ...prev, tipo: e.target.value }))}
                        className="w-full bg-background border border-border rounded-lg px-3 py-2"
                      >
                        <option value="conta_receber">conta_receber</option>
                        <option value="conta_pagar">conta_pagar</option>
                        <option value="transferencia">transferencia</option>
                      </select>
                    </div>

                    <div>
                      <label className="block text-xs text-muted-foreground mb-1">Categoria</label>
                      <input
                        value={novoLancamento.categoria}
                        onChange={(e) => setNovoLancamento((prev) => ({ ...prev, categoria: e.target.value }))}
                        className="w-full bg-background border border-border rounded-lg px-3 py-2"
                        placeholder="aluguel, taxa, manutenção"
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
                        onChange={(e) =>
                          setNovoLancamento((prev) => ({
                            ...prev,
                            valor: normalizeCurrencyInput(e.target.value),
                          }))
                        }
                        className="w-full bg-background border border-border rounded-lg px-3 py-2"
                        placeholder="0,00"
                      />
                    </div>

                    <button
                      type="submit"
                      disabled={isCreatingLancamento}
                      className="px-4 py-2 rounded-lg bg-primary text-primary-foreground disabled:opacity-60 md:col-span-6 md:justify-self-start"
                    >
                      {isCreatingLancamento ? 'Salvando...' : 'Novo lançamento'}
                    </button>
                  </form>

                  <div className="glass-panel rounded-2xl p-4 grid grid-cols-1 md:grid-cols-3 gap-3">
                    <div>
                      <label className="block text-xs text-muted-foreground mb-1">Filtrar por tipo</label>
                      <select
                        value={lancamentoTipoFiltro}
                        onChange={(e) => setLancamentoTipoFiltro(e.target.value)}
                        className="w-full bg-background border border-border rounded-lg px-3 py-2"
                      >
                        <option value="todos">todos</option>
                        <option value="conta_receber">conta_receber</option>
                        <option value="conta_pagar">conta_pagar</option>
                        <option value="transferencia">transferencia</option>
                      </select>
                    </div>
                    <div>
                      <label className="block text-xs text-muted-foreground mb-1">Filtrar por status</label>
                      <select
                        value={lancamentoStatusFiltro}
                        onChange={(e) => setLancamentoStatusFiltro(e.target.value)}
                        className="w-full bg-background border border-border rounded-lg px-3 py-2"
                      >
                        <option value="todos">todos</option>
                        <option value="aberto">aberto</option>
                        <option value="parcial">parcial</option>
                        <option value="liquidado">liquidado</option>
                      </select>
                    </div>
                    <div className="flex items-end">
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
                        Limpar filtros
                      </button>
                    </div>
                  </div>

                  <div className="glass-panel rounded-2xl p-4 grid grid-cols-1 md:grid-cols-4 gap-3">
                    <div>
                      <label className="block text-xs text-muted-foreground mb-1">Vencimento inicial</label>
                      <input
                        type="date"
                        value={lancamentoVencimentoInicio}
                        onChange={(e) => setLancamentoVencimentoInicio(e.target.value)}
                        className="w-full bg-background border border-border rounded-lg px-3 py-2"
                      />
                    </div>
                    <div>
                      <label className="block text-xs text-muted-foreground mb-1">Vencimento final</label>
                      <input
                        type="date"
                        value={lancamentoVencimentoFim}
                        onChange={(e) => setLancamentoVencimentoFim(e.target.value)}
                        className="w-full bg-background border border-border rounded-lg px-3 py-2"
                      />
                    </div>
                    <div>
                      <label className="block text-xs text-muted-foreground mb-1">Ordenar por</label>
                      <select
                        value={lancamentoSortBy}
                        onChange={(e) => setLancamentoSortBy(e.target.value as 'vencimento' | 'valor' | 'id')}
                        className="w-full bg-background border border-border rounded-lg px-3 py-2"
                      >
                        <option value="vencimento">vencimento</option>
                        <option value="valor">valor</option>
                        <option value="id">id</option>
                      </select>
                    </div>
                    <div>
                      <label className="block text-xs text-muted-foreground mb-1">Direção</label>
                      <select
                        value={lancamentoSortDir}
                        onChange={(e) => setLancamentoSortDir(e.target.value as 'asc' | 'desc')}
                        className="w-full bg-background border border-border rounded-lg px-3 py-2"
                      >
                        <option value="desc">desc</option>
                        <option value="asc">asc</option>
                      </select>
                    </div>
                  </div>

                  <div className="glass-panel rounded-2xl overflow-auto">
                    <table className="w-full min-w-[980px]">
                      <thead>
                        <tr className="border-b border-border">
                          <th className="text-left p-3 text-sm text-muted-foreground">ID</th>
                          <th className="text-left p-3 text-sm text-muted-foreground">Tipo</th>
                          <th className="text-left p-3 text-sm text-muted-foreground">Categoria</th>
                          <th className="text-left p-3 text-sm text-muted-foreground">Descrição</th>
                          <th className="text-left p-3 text-sm text-muted-foreground">Vencimento</th>
                          <th className="text-left p-3 text-sm text-muted-foreground">Valor</th>
                          <th className="text-left p-3 text-sm text-muted-foreground">Aberto</th>
                          <th className="text-left p-3 text-sm text-muted-foreground">Status</th>
                          <th className="text-left p-3 text-sm text-muted-foreground">Baixa parcial</th>
                          <th className="text-left p-3 text-sm text-muted-foreground">Ação</th>
                        </tr>
                      </thead>
                      <tbody>
                        {lancamentosPaginados.map((item) => (
                          <tr key={item.id} className={`border-b border-border/50 ${statusRowClass(item.status)}`}>
                            <td className="p-3">#{item.id}</td>
                            <td className="p-3">{item.tipo}</td>
                            <td className="p-3">{item.categoria || '-'}</td>
                            <td className="p-3">{item.descricao || '-'}</td>
                            <td className="p-3">{item.vencimento || '-'}</td>
                            <td className="p-3">R$ {formatMoney(item.valor)}</td>
                            <td className="p-3">R$ {formatMoney(item.valor_em_aberto)}</td>
                            <td className="p-3">
                              <span className={`inline-flex px-2 py-1 rounded-full text-xs font-medium ${statusBadgeClass(item.status)}`}>
                                {item.status}
                              </span>
                            </td>
                            <td className="p-3">
                              <div className="flex items-center gap-2">
                                <input
                                  type="text"
                                  inputMode="decimal"
                                  value={baixaParcial[item.id]?.valor || ''}
                                  onChange={(e) =>
                                    setBaixaParcial((prev) => ({
                                      ...prev,
                                      [item.id]: {
                                        valor: normalizeCurrencyInput(e.target.value),
                                        data: prev[item.id]?.data || '',
                                      },
                                    }))
                                  }
                                  onKeyDown={(e) => {
                                    if (e.key === 'Enter') {
                                      e.preventDefault();
                                      handlePartialBaixa(item);
                                    }
                                  }}
                                  placeholder="Valor"
                                  className="w-28 bg-background border border-border rounded-lg px-2 py-1.5"
                                  disabled={item.valor_em_aberto <= 0 || isRegisteringBaixaId === item.id}
                                />
                                <button
                                  type="button"
                                  onClick={() =>
                                    setBaixaParcial((prev) => ({
                                      ...prev,
                                      [item.id]: {
                                        valor: Number(item.valor_em_aberto || 0).toFixed(2).replace('.', ','),
                                        data: prev[item.id]?.data || '',
                                      },
                                    }))
                                  }
                                  disabled={item.valor_em_aberto <= 0 || isRegisteringBaixaId === item.id}
                                  className="px-2 py-1.5 rounded-lg border border-border hover:bg-accent disabled:opacity-60 text-xs"
                                >
                                  usar saldo total
                                </button>
                                <input
                                  type="date"
                                  value={baixaParcial[item.id]?.data || ''}
                                  onChange={(e) =>
                                    setBaixaParcial((prev) => ({
                                      ...prev,
                                      [item.id]: {
                                        valor: prev[item.id]?.valor || '',
                                        data: e.target.value,
                                      },
                                    }))
                                  }
                                  className="bg-background border border-border rounded-lg px-2 py-1.5"
                                  disabled={item.valor_em_aberto <= 0 || isRegisteringBaixaId === item.id}
                                />
                              </div>
                            </td>
                            <td className="p-3">
                              <div className="flex items-center gap-2">
                                <button
                                  onClick={() => handlePartialBaixa(item)}
                                  disabled={item.valor_em_aberto <= 0 || isRegisteringBaixaId === item.id}
                                  className="px-3 py-1.5 rounded-lg border border-border hover:bg-accent disabled:opacity-60"
                                >
                                  {isRegisteringBaixaId === item.id ? 'Salvando...' : 'Registrar parcial'}
                                </button>
                                <button
                                  onClick={() => handleQuickBaixa(item)}
                                  disabled={item.valor_em_aberto <= 0 || isRegisteringBaixaId === item.id}
                                  className="px-3 py-1.5 rounded-lg border border-border hover:bg-accent disabled:opacity-60"
                                >
                                  Baixa total
                                </button>
                              </div>
                            </td>
                          </tr>
                        ))}
                      </tbody>
                    </table>
                  </div>

                  <div className="glass-panel rounded-2xl p-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                    <p className="text-sm text-muted-foreground">
                      {lancamentosOrdenados.length} lançamento(s) • Página {lancamentoPagina} de {totalLancamentoPaginas}
                    </p>
                    <div className="flex items-center gap-2">
                      <button
                        type="button"
                        onClick={() => setLancamentoPagina((prev) => Math.max(1, prev - 1))}
                        disabled={lancamentoPagina <= 1}
                        className="px-3 py-1.5 rounded-lg border border-border hover:bg-accent disabled:opacity-60"
                      >
                        Anterior
                      </button>
                      <button
                        type="button"
                        onClick={() => setLancamentoPagina((prev) => Math.min(totalLancamentoPaginas, prev + 1))}
                        disabled={lancamentoPagina >= totalLancamentoPaginas}
                        className="px-3 py-1.5 rounded-lg border border-border hover:bg-accent disabled:opacity-60"
                      >
                        Próxima
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
