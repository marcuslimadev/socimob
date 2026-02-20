import { useEffect, useMemo, useRef, useState } from 'react';
import { Link, useLocation } from 'wouter';
import { Loader2, RefreshCcw } from 'lucide-react';
import { toast } from 'sonner';
import { api } from '@/lib/api';

interface MeuImovel {
  id: number;
  status: string;
  imovel?: { id: number; titulo?: string; codigo?: string; cidade?: string; bairro?: string };
  locador?: { id: number; nome: string };
  locatario?: { id: number; nome: string };
}

interface MinhaCobranca {
  id: number;
  competencia: string;
  vencimento: string;
  valor_total: number;
  status: string;
}

interface MinhaNota {
  id: number;
  tipo: string;
  status: string;
  numero?: string;
  emitida_em?: string;
  url_pdf?: string;
}

interface MeuChamado {
  id: number;
  protocolo?: string;
  assunto: string;
  prioridade: string;
  status: string;
}

interface ChamadoMensagemItem {
  id: number;
  mensagem: string;
  interna?: boolean;
  created_at?: string;
}

const formatMoney = (value?: number) =>
  Number(value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const formatDatePtBr = (value?: string) => {
  if (!value) return '-';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleDateString('pt-BR');
};

const formatDateTimePtBr = (value?: string) => {
  if (!value) return '-';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString('pt-BR');
};

const statusBadgeClass = (status: string) => {
  if (status === 'aberto') return 'bg-amber-100 text-amber-800';
  if (status === 'parcial') return 'bg-blue-100 text-blue-800';
  if (status === 'liquidado') return 'bg-emerald-100 text-emerald-800';
  if (status === 'ativo') return 'bg-emerald-100 text-emerald-800';
  if (status === 'em_andamento') return 'bg-blue-100 text-blue-800';
  if (status === 'resolvido' || status === 'fechado') return 'bg-emerald-100 text-emerald-800';
  return 'bg-muted text-foreground';
};

const priorityBadgeClass = (priority: string) => {
  if (priority === 'urgente') return 'bg-red-100 text-red-800';
  if (priority === 'alta') return 'bg-orange-100 text-orange-800';
  if (priority === 'media') return 'bg-blue-100 text-blue-800';
  return 'bg-muted text-foreground';
};

const parseIsoDate = (value?: string) => {
  if (!value) return 0;
  const date = new Date(value);
  return Number.isNaN(date.getTime()) ? 0 : date.getTime();
};

const chamadoRowClass = (item: MeuChamado) => {
  if (item.prioridade === 'urgente') return 'bg-red-50/40';
  if (item.status === 'aberto') return 'bg-amber-50/40';
  if (item.status === 'em_andamento') return 'bg-blue-50/40';
  return '';
};

export default function PortalPessoaFinanceiro() {
  const getStored = (key: string, fallback: string) => {
    if (typeof window === 'undefined') return fallback;
    return localStorage.getItem(key) || fallback;
  };

  const [, navigate] = useLocation();
  const [isLoading, setIsLoading] = useState(true);
  const [isCreatingTicket, setIsCreatingTicket] = useState(false);
  const [meusImoveis, setMeusImoveis] = useState<MeuImovel[]>([]);
  const [minhasCobrancas, setMinhasCobrancas] = useState<MinhaCobranca[]>([]);
  const [minhasNotas, setMinhasNotas] = useState<MinhaNota[]>([]);
  const [meusChamados, setMeusChamados] = useState<MeuChamado[]>([]);

  const [assunto, setAssunto] = useState('');
  const [categoria, setCategoria] = useState('financeiro');
  const [prioridade, setPrioridade] = useState('media');
  const [descricao, setDescricao] = useState('');
  const [contratoBusca, setContratoBusca] = useState(() => getStored('portal_pessoa_contrato_busca', ''));
  const [notaStatusFiltro, setNotaStatusFiltro] = useState(() => getStored('portal_pessoa_nota_status', 'todos'));
  const [notaBusca, setNotaBusca] = useState(() => getStored('portal_pessoa_nota_busca', ''));
  const [cobrancaStatusFiltro, setCobrancaStatusFiltro] = useState(() => getStored('portal_pessoa_cobranca_status', 'todos'));
  const [chamadoStatusFiltro, setChamadoStatusFiltro] = useState(() => getStored('portal_pessoa_chamado_status', 'todos'));
  const [chamadoBusca, setChamadoBusca] = useState(() => getStored('portal_pessoa_chamado_busca', ''));
  const [cobrancaSomenteVencidas, setCobrancaSomenteVencidas] = useState(
    () => getStored('portal_pessoa_cobranca_vencidas', '0') === '1'
  );
  const [chamadoOrdenacao, setChamadoOrdenacao] = useState<'recentes' | 'status' | 'prioridade'>(
    () => getStored('portal_pessoa_chamado_ordenacao', 'recentes') as 'recentes' | 'status' | 'prioridade'
  );
  const [cobrancaPagina, setCobrancaPagina] = useState(1);
  const [notaPagina, setNotaPagina] = useState(1);
  const [chamadoPagina, setChamadoPagina] = useState(1);
  const [chamadoSelecionado, setChamadoSelecionado] = useState<MeuChamado | null>(null);
  const [chamadoMensagens, setChamadoMensagens] = useState<ChamadoMensagemItem[]>([]);
  const [novaMensagemChamado, setNovaMensagemChamado] = useState('');
  const [isLoadingMensagens, setIsLoadingMensagens] = useState(false);
  const [isSendingMensagem, setIsSendingMensagem] = useState(false);
  const conversaMensagensEndRef = useRef<HTMLDivElement | null>(null);
  const pageSize = 8;

  const loadAll = async () => {
    setIsLoading(true);
    try {
      const [imoveisResp, cobrancasResp, notasResp, chamadosResp] = await Promise.all([
        api.get('/portal/meus-imoveis'),
        api.get('/portal/financeiro/cobrancas'),
        api.get('/portal/financeiro/notas-fiscais'),
        api.get('/portal/chamados'),
      ]);

      setMeusImoveis(imoveisResp.data?.items || []);
      setMinhasCobrancas(cobrancasResp.data?.items || []);
      setMinhasNotas(notasResp.data?.items || []);
      setMeusChamados(chamadosResp.data?.items || []);
    } catch {
      toast.error('Não foi possível carregar seus dados financeiros');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    const token = localStorage.getItem('token');
    if (!token) {
      navigate('/login');
      return;
    }

    loadAll();
  }, [navigate]);

  useEffect(() => {
    if (!chamadoSelecionado) return;
    conversaMensagensEndRef.current?.scrollIntoView({ behavior: 'smooth', block: 'end' });
  }, [chamadoSelecionado, chamadoMensagens.length]);

  const handleCreateTicket = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!assunto || !descricao) {
      toast.error('Informe assunto e descrição do chamado');
      return;
    }

    setIsCreatingTicket(true);
    try {
      await api.post('/portal/chamados', {
        assunto,
        categoria,
        prioridade,
        descricao,
      });
      toast.success('Chamado aberto com sucesso');
      setAssunto('');
      setDescricao('');
      await loadAll();
    } catch (error: any) {
      toast.error(error?.response?.data?.message || 'Erro ao abrir chamado');
    } finally {
      setIsCreatingTicket(false);
    }
  };

  const handleLogout = () => {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    navigate('/login');
  };

  const cobrancasFiltradas = useMemo(() => {
    const today = new Date();
    const cutoff = new Date(today.getFullYear(), today.getMonth(), today.getDate()).getTime();

    return minhasCobrancas
      .filter((item) => cobrancaStatusFiltro === 'todos' || item.status === cobrancaStatusFiltro)
      .filter((item) => {
        if (!cobrancaSomenteVencidas) return true;
        if (item.status === 'liquidado') return false;
        const due = parseIsoDate(item.vencimento);
        return due > 0 && due < cutoff;
      })
      .sort((a, b) => parseIsoDate(a.vencimento) - parseIsoDate(b.vencimento));
  }, [minhasCobrancas, cobrancaStatusFiltro, cobrancaSomenteVencidas]);

  const chamadosFiltrados = useMemo(() => {
    const term = chamadoBusca.trim().toLowerCase();
    const priorityOrder: Record<string, number> = { urgente: 0, alta: 1, media: 2, baixa: 3 };
    const statusOrder: Record<string, number> = { aberto: 0, em_andamento: 1, resolvido: 2, fechado: 3 };

    const filtered = meusChamados.filter((item) => {
      const statusOk = chamadoStatusFiltro === 'todos' || item.status === chamadoStatusFiltro;
      const searchOk =
        !term ||
        (item.protocolo || '').toLowerCase().includes(term) ||
        (item.assunto || '').toLowerCase().includes(term);

      return statusOk && searchOk;
    });

    return filtered.sort((a, b) => {
      if (chamadoOrdenacao === 'prioridade') {
        return (priorityOrder[a.prioridade] ?? 99) - (priorityOrder[b.prioridade] ?? 99);
      }

      if (chamadoOrdenacao === 'status') {
        return (statusOrder[a.status] ?? 99) - (statusOrder[b.status] ?? 99);
      }

      return b.id - a.id;
    });
  }, [meusChamados, chamadoStatusFiltro, chamadoBusca, chamadoOrdenacao]);

  const contratosFiltrados = useMemo(() => {
    const term = contratoBusca.trim().toLowerCase();
    if (!term) return meusImoveis;

    return meusImoveis.filter((item) => {
      const texto = [
        item.imovel?.titulo,
        item.imovel?.codigo,
        item.imovel?.cidade,
        item.imovel?.bairro,
        item.locador?.nome,
        item.locatario?.nome,
      ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase();

      return texto.includes(term);
    });
  }, [meusImoveis, contratoBusca]);

  const notasFiltradas = useMemo(() => {
    const term = notaBusca.trim().toLowerCase();
    return minhasNotas.filter((item) => {
      const statusOk = notaStatusFiltro === 'todos' || item.status === notaStatusFiltro;
      const searchOk =
        !term ||
        (item.numero || '').toLowerCase().includes(term) ||
        (item.tipo || '').toLowerCase().includes(term);
      return statusOk && searchOk;
    });
  }, [minhasNotas, notaStatusFiltro, notaBusca]);

  const resumoCobrancas = useMemo(() => {
    const total = cobrancasFiltradas.reduce((acc, item) => acc + Number(item.valor_total || 0), 0);
    const abertas = cobrancasFiltradas
      .filter((item) => item.status === 'aberto' || item.status === 'parcial')
      .reduce((acc, item) => acc + Number(item.valor_total || 0), 0);
    const liquidadas = cobrancasFiltradas
      .filter((item) => item.status === 'liquidado')
      .reduce((acc, item) => acc + Number(item.valor_total || 0), 0);

    return { total, abertas, liquidadas };
  }, [cobrancasFiltradas]);

  const resumoChamados = useMemo(() => {
    const aberto = chamadosFiltrados.filter((item) => item.status === 'aberto').length;
    const emAndamento = chamadosFiltrados.filter((item) => item.status === 'em_andamento').length;
    const encerrados = chamadosFiltrados.filter((item) => item.status === 'resolvido' || item.status === 'fechado').length;

    return { aberto, emAndamento, encerrados };
  }, [chamadosFiltrados]);

  const totalPaginasCobrancas = Math.max(1, Math.ceil(cobrancasFiltradas.length / pageSize));
  const totalPaginasNotas = Math.max(1, Math.ceil(notasFiltradas.length / pageSize));
  const totalPaginasChamados = Math.max(1, Math.ceil(chamadosFiltrados.length / pageSize));

  const cobrancasPaginadas = useMemo(() => {
    const start = (cobrancaPagina - 1) * pageSize;
    return cobrancasFiltradas.slice(start, start + pageSize);
  }, [cobrancasFiltradas, cobrancaPagina]);

  const chamadosPaginados = useMemo(() => {
    const start = (chamadoPagina - 1) * pageSize;
    return chamadosFiltrados.slice(start, start + pageSize);
  }, [chamadosFiltrados, chamadoPagina]);

  const notasPaginadas = useMemo(() => {
    const start = (notaPagina - 1) * pageSize;
    return notasFiltradas.slice(start, start + pageSize);
  }, [notasFiltradas, notaPagina]);

  const handleCopyProtocol = async (value: string) => {
    try {
      await navigator.clipboard.writeText(value);
      toast.success('Protocolo copiado');
    } catch {
      toast.error('Não foi possível copiar o protocolo');
    }
  };

  const isOverdue = (item: MinhaCobranca) => {
    if (item.status === 'liquidado') return false;
    const due = parseIsoDate(item.vencimento);
    if (!due) return false;
    const today = new Date();
    const cutoff = new Date(today.getFullYear(), today.getMonth(), today.getDate()).getTime();
    return due < cutoff;
  };

  const exportToCsv = (rows: Array<Array<string | number>>, filename: string) => {
    const escape = (value: string | number) => `"${String(value).replace(/"/g, '""')}"`;
    const csv = rows.map((line) => line.map((cell) => escape(cell)).join(';')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `${filename}_${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
  };

  const handleExportCobrancasCsv = () => {
    const rows: Array<Array<string | number>> = [
      ['competencia', 'vencimento', 'valor_total', 'status'],
      ...cobrancasFiltradas.map((item) => [item.competencia, item.vencimento, Number(item.valor_total).toFixed(2), item.status]),
    ];
    exportToCsv(rows, 'minhas_cobrancas');
    toast.success('Cobranças exportadas em CSV');
  };

  const handleExportCobrancasPaginaCsv = () => {
    const rows: Array<Array<string | number>> = [
      ['competencia', 'vencimento', 'valor_total', 'status'],
      ...cobrancasPaginadas.map((item) => [item.competencia, item.vencimento, Number(item.valor_total).toFixed(2), item.status]),
    ];
    exportToCsv(rows, `minhas_cobrancas_pagina_${cobrancaPagina}`);
    toast.success('Página atual de cobranças exportada');
  };

  const handleExportChamadosCsv = () => {
    const rows: Array<Array<string | number>> = [
      ['protocolo', 'assunto', 'prioridade', 'status'],
      ...chamadosFiltrados.map((item) => [item.protocolo || `CH-${item.id}`, item.assunto, item.prioridade, item.status]),
    ];
    exportToCsv(rows, 'meus_chamados');
    toast.success('Chamados exportados em CSV');
  };

  const handleExportNotasCsv = () => {
    const rows: Array<Array<string | number>> = [
      ['numero', 'tipo', 'status', 'emitida_em', 'url_pdf'],
      ...notasFiltradas.map((item) => [item.numero || `#${item.id}`, item.tipo, item.status, item.emitida_em || '', item.url_pdf || '']),
    ];
    exportToCsv(rows, 'minhas_notas_fiscais');
    toast.success('Notas fiscais exportadas em CSV');
  };

  const handleExportChamadosPaginaCsv = () => {
    const rows: Array<Array<string | number>> = [
      ['protocolo', 'assunto', 'prioridade', 'status'],
      ...chamadosPaginados.map((item) => [item.protocolo || `CH-${item.id}`, item.assunto, item.prioridade, item.status]),
    ];
    exportToCsv(rows, `meus_chamados_pagina_${chamadoPagina}`);
    toast.success('Página atual de chamados exportada');
  };

  const handleCopyAllProtocols = async () => {
    const content = chamadosFiltrados.map((item) => item.protocolo || `CH-${item.id}`).join('\n');
    if (!content) {
      toast.error('Nenhum protocolo para copiar');
      return;
    }

    try {
      await navigator.clipboard.writeText(content);
      toast.success('Protocolos filtrados copiados');
    } catch {
      toast.error('Não foi possível copiar os protocolos');
    }
  };

  const loadChamadoMensagens = async (chamadoId: number) => {
    setIsLoadingMensagens(true);
    try {
      const resp = await api.get(`/portal/chamados/${chamadoId}/mensagens`);
      setChamadoMensagens(resp.data?.items || []);
    } catch {
      toast.error('Não foi possível carregar as mensagens do chamado');
    } finally {
      setIsLoadingMensagens(false);
    }
  };

  const handleSelecionarChamado = async (item: MeuChamado) => {
    setChamadoSelecionado(item);
    setNovaMensagemChamado('');
    await loadChamadoMensagens(item.id);
  };

  const handleEnviarMensagemChamado = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!chamadoSelecionado) return;
    if (!novaMensagemChamado.trim()) {
      toast.error('Informe a mensagem');
      return;
    }

    setIsSendingMensagem(true);
    try {
      await api.post(`/portal/chamados/${chamadoSelecionado.id}/mensagens`, {
        mensagem: novaMensagemChamado.trim(),
      });
      setNovaMensagemChamado('');
      await loadChamadoMensagens(chamadoSelecionado.id);
      toast.success('Mensagem enviada');
    } catch {
      toast.error('Não foi possível enviar a mensagem');
    } finally {
      setIsSendingMensagem(false);
    }
  };

  useEffect(() => {
    setCobrancaPagina(1);
  }, [cobrancaStatusFiltro, cobrancaSomenteVencidas]);

  useEffect(() => {
    setChamadoPagina(1);
  }, [chamadoStatusFiltro, chamadoBusca, chamadoOrdenacao]);

  useEffect(() => {
    setNotaPagina(1);
  }, [notaStatusFiltro, notaBusca]);

  useEffect(() => {
    if (cobrancaPagina > totalPaginasCobrancas) {
      setCobrancaPagina(totalPaginasCobrancas);
    }
  }, [cobrancaPagina, totalPaginasCobrancas]);

  useEffect(() => {
    if (chamadoPagina > totalPaginasChamados) {
      setChamadoPagina(totalPaginasChamados);
    }
  }, [chamadoPagina, totalPaginasChamados]);

  useEffect(() => {
    if (notaPagina > totalPaginasNotas) {
      setNotaPagina(totalPaginasNotas);
    }
  }, [notaPagina, totalPaginasNotas]);

  useEffect(() => {
    localStorage.setItem('portal_pessoa_cobranca_status', cobrancaStatusFiltro);
  }, [cobrancaStatusFiltro]);

  useEffect(() => {
    localStorage.setItem('portal_pessoa_cobranca_vencidas', cobrancaSomenteVencidas ? '1' : '0');
  }, [cobrancaSomenteVencidas]);

  useEffect(() => {
    localStorage.setItem('portal_pessoa_chamado_status', chamadoStatusFiltro);
  }, [chamadoStatusFiltro]);

  useEffect(() => {
    localStorage.setItem('portal_pessoa_chamado_busca', chamadoBusca);
  }, [chamadoBusca]);

  useEffect(() => {
    localStorage.setItem('portal_pessoa_chamado_ordenacao', chamadoOrdenacao);
  }, [chamadoOrdenacao]);

  useEffect(() => {
    localStorage.setItem('portal_pessoa_contrato_busca', contratoBusca);
  }, [contratoBusca]);

  useEffect(() => {
    localStorage.setItem('portal_pessoa_nota_status', notaStatusFiltro);
  }, [notaStatusFiltro]);

  useEffect(() => {
    localStorage.setItem('portal_pessoa_nota_busca', notaBusca);
  }, [notaBusca]);

  const handleResetPreferences = () => {
    setContratoBusca('');
    setNotaStatusFiltro('todos');
    setNotaBusca('');
    setCobrancaStatusFiltro('todos');
    setCobrancaSomenteVencidas(false);
    setChamadoStatusFiltro('todos');
    setChamadoBusca('');
    setChamadoOrdenacao('recentes');

    localStorage.removeItem('portal_pessoa_contrato_busca');
    localStorage.removeItem('portal_pessoa_nota_status');
    localStorage.removeItem('portal_pessoa_nota_busca');
    localStorage.removeItem('portal_pessoa_cobranca_status');
    localStorage.removeItem('portal_pessoa_cobranca_vencidas');
    localStorage.removeItem('portal_pessoa_chamado_status');
    localStorage.removeItem('portal_pessoa_chamado_busca');
    localStorage.removeItem('portal_pessoa_chamado_ordenacao');

    toast.success('Preferências de filtros resetadas');
  };

  return (
    <div className="min-h-screen bg-background text-foreground">
      <div className="max-w-6xl mx-auto px-4 py-8 space-y-6">
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-3xl font-bold">Meu Financeiro</h1>
            <p className="text-muted-foreground">Imóveis, cobranças, notas e atendimento</p>
          </div>

          <div className="flex items-center gap-2">
            <button
              onClick={loadAll}
              className="flex items-center gap-2 px-4 py-2 rounded-lg border border-border bg-card hover:bg-accent transition-colors"
            >
              <RefreshCcw size={16} /> Atualizar
            </button>
            <button
              onClick={handleResetPreferences}
              className="px-4 py-2 rounded-lg border border-border hover:bg-accent transition-colors"
            >
              Resetar filtros
            </button>
            <Link to="/portal" className="px-4 py-2 rounded-lg border border-border hover:bg-accent transition-colors">
              Voltar ao portal
            </Link>
            <button
              onClick={handleLogout}
              className="px-4 py-2 rounded-lg border border-border hover:bg-accent transition-colors"
            >
              Sair
            </button>
          </div>
        </div>

        {isLoading ? (
          <div className="glass-panel rounded-2xl p-12 flex justify-center">
            <Loader2 className="animate-spin" />
          </div>
        ) : (
          <>
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
              <div className="glass-panel rounded-xl p-4">
                <p className="text-sm text-muted-foreground">Meus contratos</p>
                <p className="text-2xl font-semibold">{meusImoveis.length}</p>
              </div>
              <div className="glass-panel rounded-xl p-4">
                <p className="text-sm text-muted-foreground">Cobranças</p>
                <p className="text-2xl font-semibold">{minhasCobrancas.length}</p>
              </div>
              <div className="glass-panel rounded-xl p-4">
                <p className="text-sm text-muted-foreground">Chamados</p>
                <p className="text-2xl font-semibold">{meusChamados.length}</p>
              </div>
            </div>

            <div className="glass-panel rounded-2xl overflow-auto">
              <div className="p-4 border-b border-border">
                <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                  <h2 className="text-lg font-semibold">Meus imóveis/contratos ({contratosFiltrados.length})</h2>
                  <input
                    value={contratoBusca}
                    onChange={(e) => setContratoBusca(e.target.value)}
                    placeholder="Buscar imóvel, cidade, locador, locatário"
                    className="bg-background border border-border rounded-lg px-3 py-2 text-sm"
                  />
                </div>
              </div>
              <table className="w-full min-w-[720px]">
                <thead>
                  <tr className="border-b border-border">
                    <th className="text-left p-3 text-sm text-muted-foreground">Imóvel</th>
                    <th className="text-left p-3 text-sm text-muted-foreground">Cidade</th>
                    <th className="text-left p-3 text-sm text-muted-foreground">Locador</th>
                    <th className="text-left p-3 text-sm text-muted-foreground">Locatário</th>
                    <th className="text-left p-3 text-sm text-muted-foreground">Status</th>
                  </tr>
                </thead>
                <tbody>
                  {contratosFiltrados.length === 0 ? (
                    <tr>
                      <td colSpan={5} className="p-6 text-center text-sm text-muted-foreground">
                        Nenhum contrato encontrado para a busca informada.
                      </td>
                    </tr>
                  ) : (
                    contratosFiltrados.map((item) => (
                      <tr key={item.id} className="border-b border-border/50">
                        <td className="p-3">{item.imovel?.titulo || item.imovel?.codigo || `Contrato #${item.id}`}</td>
                        <td className="p-3">{item.imovel?.cidade || '-'}</td>
                        <td className="p-3">{item.locador?.nome || '-'}</td>
                        <td className="p-3">{item.locatario?.nome || '-'}</td>
                        <td className="p-3">
                          <span className={`inline-flex px-2 py-1 rounded-full text-xs font-medium ${statusBadgeClass(item.status)}`}>
                            {item.status}
                          </span>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
              <div className="glass-panel rounded-2xl overflow-auto">
                <div className="p-4 border-b border-border">
                  <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                    <h2 className="text-lg font-semibold">Minhas cobranças ({cobrancasFiltradas.length})</h2>
                    <div className="flex gap-2">
                      <select
                        value={cobrancaStatusFiltro}
                        onChange={(e) => setCobrancaStatusFiltro(e.target.value)}
                        className="bg-background border border-border rounded-lg px-3 py-2 text-sm"
                      >
                        <option value="todos">todos</option>
                        <option value="aberto">aberto</option>
                        <option value="parcial">parcial</option>
                        <option value="liquidado">liquidado</option>
                      </select>
                      <button
                        type="button"
                        onClick={() => {
                          setCobrancaStatusFiltro('todos');
                          setCobrancaSomenteVencidas(false);
                        }}
                        className="px-3 py-2 rounded-lg border border-border text-sm hover:bg-accent"
                      >
                        Limpar
                      </button>
                      <button
                        type="button"
                        onClick={handleExportCobrancasCsv}
                        className="px-3 py-2 rounded-lg border border-border text-sm hover:bg-accent"
                      >
                        Exportar CSV
                      </button>
                      <button
                        type="button"
                        onClick={handleExportCobrancasPaginaCsv}
                        className="px-3 py-2 rounded-lg border border-border text-sm hover:bg-accent"
                      >
                        Exportar página
                      </button>
                    </div>
                  </div>
                </div>
                <div className="px-4 py-2 border-b border-border/60">
                  <label className="inline-flex items-center gap-2 text-sm text-muted-foreground">
                    <input
                      type="checkbox"
                      checked={cobrancaSomenteVencidas}
                      onChange={(e) => setCobrancaSomenteVencidas(e.target.checked)}
                    />
                    Somente vencidas
                  </label>
                </div>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-3 p-4 border-b border-border/60">
                  <div className="rounded-lg border border-border p-3">
                    <p className="text-xs text-muted-foreground">Total filtrado</p>
                    <p className="text-lg font-semibold">R$ {formatMoney(resumoCobrancas.total)}</p>
                  </div>
                  <div className="rounded-lg border border-border p-3">
                    <p className="text-xs text-muted-foreground">Em aberto/parcial</p>
                    <p className="text-lg font-semibold">R$ {formatMoney(resumoCobrancas.abertas)}</p>
                  </div>
                  <div className="rounded-lg border border-border p-3">
                    <p className="text-xs text-muted-foreground">Liquidadas</p>
                    <p className="text-lg font-semibold">R$ {formatMoney(resumoCobrancas.liquidadas)}</p>
                  </div>
                </div>
                <table className="w-full min-w-[520px]">
                  <thead>
                    <tr className="border-b border-border">
                      <th className="text-left p-3 text-sm text-muted-foreground">Competência</th>
                      <th className="text-left p-3 text-sm text-muted-foreground">Vencimento</th>
                      <th className="text-left p-3 text-sm text-muted-foreground">Situação</th>
                      <th className="text-left p-3 text-sm text-muted-foreground">Valor</th>
                      <th className="text-left p-3 text-sm text-muted-foreground">Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    {cobrancasFiltradas.length === 0 ? (
                      <tr>
                        <td colSpan={5} className="p-6 text-center text-sm text-muted-foreground">
                          Nenhuma cobrança encontrada para o filtro selecionado.
                        </td>
                      </tr>
                    ) : (
                      cobrancasPaginadas.map((item) => (
                        <tr key={item.id} className={`border-b border-border/50 ${isOverdue(item) ? 'bg-red-50/40' : ''}`}>
                          <td className="p-3">{item.competencia}</td>
                          <td className="p-3">{formatDatePtBr(item.vencimento)}</td>
                          <td className="p-3">
                            {isOverdue(item) ? (
                              <span className="inline-flex px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                vencida
                              </span>
                            ) : (
                              <span className="inline-flex px-2 py-1 rounded-full text-xs font-medium bg-emerald-100 text-emerald-800">
                                em dia
                              </span>
                            )}
                          </td>
                          <td className="p-3">R$ {formatMoney(item.valor_total)}</td>
                          <td className="p-3">
                            <span className={`inline-flex px-2 py-1 rounded-full text-xs font-medium ${statusBadgeClass(item.status)}`}>
                              {item.status}
                            </span>
                          </td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
                <div className="p-3 border-t border-border flex items-center justify-between text-sm text-muted-foreground">
                  <span>Página {cobrancaPagina} de {totalPaginasCobrancas}</span>
                  <div className="flex gap-2">
                    <button
                      type="button"
                      onClick={() => setCobrancaPagina((prev) => Math.max(1, prev - 1))}
                      disabled={cobrancaPagina <= 1}
                      className="px-3 py-1.5 rounded-lg border border-border hover:bg-accent disabled:opacity-60"
                    >
                      Anterior
                    </button>
                    <button
                      type="button"
                      onClick={() => setCobrancaPagina((prev) => Math.min(totalPaginasCobrancas, prev + 1))}
                      disabled={cobrancaPagina >= totalPaginasCobrancas}
                      className="px-3 py-1.5 rounded-lg border border-border hover:bg-accent disabled:opacity-60"
                    >
                      Próxima
                    </button>
                  </div>
                </div>
              </div>

              <div className="glass-panel rounded-2xl overflow-auto">
                <div className="p-4 border-b border-border">
                  <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                    <h2 className="text-lg font-semibold">Minhas notas fiscais ({notasFiltradas.length})</h2>
                    <div className="flex flex-col md:flex-row gap-2">
                      <input
                        value={notaBusca}
                        onChange={(e) => setNotaBusca(e.target.value)}
                        placeholder="Buscar por número/tipo"
                        className="bg-background border border-border rounded-lg px-3 py-2 text-sm"
                      />
                      <select
                        value={notaStatusFiltro}
                        onChange={(e) => setNotaStatusFiltro(e.target.value)}
                        className="bg-background border border-border rounded-lg px-3 py-2 text-sm"
                      >
                        <option value="todos">todos</option>
                        <option value="emitida">emitida</option>
                        <option value="pendente">pendente</option>
                        <option value="cancelada">cancelada</option>
                      </select>
                      <button
                        type="button"
                        onClick={() => {
                          setNotaStatusFiltro('todos');
                          setNotaBusca('');
                        }}
                        className="px-3 py-2 rounded-lg border border-border text-sm hover:bg-accent"
                      >
                        Limpar
                      </button>
                      <button
                        type="button"
                        onClick={handleExportNotasCsv}
                        className="px-3 py-2 rounded-lg border border-border text-sm hover:bg-accent"
                      >
                        Exportar CSV
                      </button>
                    </div>
                  </div>
                </div>
                <table className="w-full min-w-[520px]">
                  <thead>
                    <tr className="border-b border-border">
                      <th className="text-left p-3 text-sm text-muted-foreground">Número</th>
                      <th className="text-left p-3 text-sm text-muted-foreground">Tipo</th>
                      <th className="text-left p-3 text-sm text-muted-foreground">Status</th>
                      <th className="text-left p-3 text-sm text-muted-foreground">PDF</th>
                    </tr>
                  </thead>
                  <tbody>
                    {notasFiltradas.length === 0 ? (
                      <tr>
                        <td colSpan={4} className="p-6 text-center text-sm text-muted-foreground">
                          Nenhuma nota fiscal encontrada para os filtros/busca.
                        </td>
                      </tr>
                    ) : (
                      notasPaginadas.map((item) => (
                        <tr key={item.id} className="border-b border-border/50">
                          <td className="p-3">{item.numero || `#${item.id}`}</td>
                          <td className="p-3">{item.tipo}</td>
                          <td className="p-3">
                            <span className={`inline-flex px-2 py-1 rounded-full text-xs font-medium ${statusBadgeClass(item.status)}`}>
                              {item.status}
                            </span>
                          </td>
                          <td className="p-3">
                            {item.url_pdf ? (
                              <a href={item.url_pdf} target="_blank" rel="noreferrer" className="text-primary hover:underline">
                                Abrir
                              </a>
                            ) : (
                              '-'
                            )}
                          </td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
                <div className="p-3 border-t border-border flex items-center justify-between text-sm text-muted-foreground">
                  <span>Página {notaPagina} de {totalPaginasNotas}</span>
                  <div className="flex gap-2">
                    <button
                      type="button"
                      onClick={() => setNotaPagina((prev) => Math.max(1, prev - 1))}
                      disabled={notaPagina <= 1}
                      className="px-3 py-1.5 rounded-lg border border-border hover:bg-accent disabled:opacity-60"
                    >
                      Anterior
                    </button>
                    <button
                      type="button"
                      onClick={() => setNotaPagina((prev) => Math.min(totalPaginasNotas, prev + 1))}
                      disabled={notaPagina >= totalPaginasNotas}
                      className="px-3 py-1.5 rounded-lg border border-border hover:bg-accent disabled:opacity-60"
                    >
                      Próxima
                    </button>
                  </div>
                </div>
              </div>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
              <form onSubmit={handleCreateTicket} className="glass-panel rounded-2xl p-4 space-y-3">
                <h2 className="text-lg font-semibold">Abrir chamado</h2>

                <input
                  value={assunto}
                  onChange={(e) => setAssunto(e.target.value)}
                  placeholder="Assunto"
                  className="w-full bg-background border border-border rounded-lg px-3 py-2"
                />

                <div className="grid grid-cols-2 gap-2">
                  <select
                    value={categoria}
                    onChange={(e) => setCategoria(e.target.value)}
                    className="bg-background border border-border rounded-lg px-3 py-2"
                  >
                    <option value="financeiro">financeiro</option>
                    <option value="contrato">contrato</option>
                    <option value="manutencao">manutencao</option>
                    <option value="outros">outros</option>
                  </select>
                  <select
                    value={prioridade}
                    onChange={(e) => setPrioridade(e.target.value)}
                    className="bg-background border border-border rounded-lg px-3 py-2"
                  >
                    <option value="baixa">baixa</option>
                    <option value="media">media</option>
                    <option value="alta">alta</option>
                    <option value="urgente">urgente</option>
                  </select>
                </div>

                <textarea
                  value={descricao}
                  onChange={(e) => setDescricao(e.target.value)}
                  rows={4}
                  placeholder="Descreva sua solicitação"
                  className="w-full bg-background border border-border rounded-lg px-3 py-2"
                />

                <button
                  type="submit"
                  disabled={isCreatingTicket}
                  className="px-4 py-2 rounded-lg bg-primary text-primary-foreground disabled:opacity-60"
                >
                  {isCreatingTicket ? 'Enviando...' : 'Abrir chamado'}
                </button>
              </form>

              <div className="glass-panel rounded-2xl overflow-auto">
                <div className="p-4 border-b border-border">
                  <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-2">
                    <h2 className="text-lg font-semibold">Meus chamados ({chamadosFiltrados.length})</h2>
                    <div className="flex flex-col md:flex-row gap-2">
                      <input
                        value={chamadoBusca}
                        onChange={(e) => setChamadoBusca(e.target.value)}
                        placeholder="Buscar por protocolo/assunto"
                        className="bg-background border border-border rounded-lg px-3 py-2 text-sm"
                      />
                      <select
                        value={chamadoStatusFiltro}
                        onChange={(e) => setChamadoStatusFiltro(e.target.value)}
                        className="bg-background border border-border rounded-lg px-3 py-2 text-sm"
                      >
                        <option value="todos">todos</option>
                        <option value="aberto">aberto</option>
                        <option value="em_andamento">em_andamento</option>
                        <option value="resolvido">resolvido</option>
                        <option value="fechado">fechado</option>
                      </select>
                      <select
                        value={chamadoOrdenacao}
                        onChange={(e) => setChamadoOrdenacao(e.target.value as 'recentes' | 'status' | 'prioridade')}
                        className="bg-background border border-border rounded-lg px-3 py-2 text-sm"
                      >
                        <option value="recentes">recentes</option>
                        <option value="status">status</option>
                        <option value="prioridade">prioridade</option>
                      </select>
                      <button
                        type="button"
                        onClick={() => {
                          setChamadoStatusFiltro('todos');
                          setChamadoBusca('');
                          setChamadoOrdenacao('recentes');
                        }}
                        className="px-3 py-2 rounded-lg border border-border text-sm hover:bg-accent"
                      >
                        Limpar
                      </button>
                      <button
                        type="button"
                        onClick={handleExportChamadosCsv}
                        className="px-3 py-2 rounded-lg border border-border text-sm hover:bg-accent"
                      >
                        Exportar CSV
                      </button>
                      <button
                        type="button"
                        onClick={handleExportChamadosPaginaCsv}
                        className="px-3 py-2 rounded-lg border border-border text-sm hover:bg-accent"
                      >
                        Exportar página
                      </button>
                      <button
                        type="button"
                        onClick={handleCopyAllProtocols}
                        className="px-3 py-2 rounded-lg border border-border text-sm hover:bg-accent"
                      >
                        Copiar protocolos
                      </button>
                    </div>
                  </div>
                </div>
                <div className="grid grid-cols-1 md:grid-cols-3 gap-3 p-4 border-b border-border/60">
                  <div className="rounded-lg border border-border p-3">
                    <p className="text-xs text-muted-foreground">Abertos</p>
                    <p className="text-lg font-semibold">{resumoChamados.aberto}</p>
                  </div>
                  <div className="rounded-lg border border-border p-3">
                    <p className="text-xs text-muted-foreground">Em andamento</p>
                    <p className="text-lg font-semibold">{resumoChamados.emAndamento}</p>
                  </div>
                  <div className="rounded-lg border border-border p-3">
                    <p className="text-xs text-muted-foreground">Encerrados</p>
                    <p className="text-lg font-semibold">{resumoChamados.encerrados}</p>
                  </div>
                </div>
                <table className="w-full min-w-[520px]">
                  <thead>
                    <tr className="border-b border-border">
                      <th className="text-left p-3 text-sm text-muted-foreground">Protocolo</th>
                      <th className="text-left p-3 text-sm text-muted-foreground">Assunto</th>
                      <th className="text-left p-3 text-sm text-muted-foreground">Prioridade</th>
                      <th className="text-left p-3 text-sm text-muted-foreground">Status</th>
                      <th className="text-left p-3 text-sm text-muted-foreground">Ação</th>
                    </tr>
                  </thead>
                  <tbody>
                    {chamadosFiltrados.length === 0 ? (
                      <tr>
                        <td colSpan={5} className="p-6 text-center text-sm text-muted-foreground">
                          Nenhum chamado encontrado para os filtros/busca.
                        </td>
                      </tr>
                    ) : (
                      chamadosPaginados.map((item) => (
                        <tr key={item.id} className={`border-b border-border/50 ${chamadoRowClass(item)}`}>
                          <td className="p-3">
                            <button
                              type="button"
                              onClick={() => handleCopyProtocol(item.protocolo || `CH-${item.id}`)}
                              className="hover:underline"
                            >
                              {item.protocolo || `CH-${item.id}`}
                            </button>
                          </td>
                          <td className="p-3">{item.assunto}</td>
                          <td className="p-3">
                            <span className={`inline-flex px-2 py-1 rounded-full text-xs font-medium ${priorityBadgeClass(item.prioridade)}`}>
                              {item.prioridade}
                            </span>
                          </td>
                          <td className="p-3">
                            <span className={`inline-flex px-2 py-1 rounded-full text-xs font-medium ${statusBadgeClass(item.status)}`}>
                              {item.status}
                            </span>
                          </td>
                          <td className="p-3">
                            <button
                              type="button"
                              onClick={() => handleSelecionarChamado(item)}
                              className="px-3 py-1.5 rounded-lg border border-border text-sm hover:bg-accent"
                            >
                              Ver conversa
                            </button>
                          </td>
                        </tr>
                      ))
                    )}
                  </tbody>
                </table>
                <div className="p-3 border-t border-border flex items-center justify-between text-sm text-muted-foreground">
                  <span>Página {chamadoPagina} de {totalPaginasChamados}</span>
                  <div className="flex gap-2">
                    <button
                      type="button"
                      onClick={() => setChamadoPagina((prev) => Math.max(1, prev - 1))}
                      disabled={chamadoPagina <= 1}
                      className="px-3 py-1.5 rounded-lg border border-border hover:bg-accent disabled:opacity-60"
                    >
                      Anterior
                    </button>
                    <button
                      type="button"
                      onClick={() => setChamadoPagina((prev) => Math.min(totalPaginasChamados, prev + 1))}
                      disabled={chamadoPagina >= totalPaginasChamados}
                      className="px-3 py-1.5 rounded-lg border border-border hover:bg-accent disabled:opacity-60"
                    >
                      Próxima
                    </button>
                  </div>
                </div>

                <div className="border-t border-border/60 p-4 space-y-3">
                  <div className="flex items-center justify-between">
                    <h3 className="text-base font-semibold">Conversa do chamado</h3>
                    {chamadoSelecionado && (
                      <span className="text-xs text-muted-foreground">
                        {chamadoSelecionado.protocolo || `CH-${chamadoSelecionado.id}`}
                      </span>
                    )}
                  </div>

                  {!chamadoSelecionado ? (
                    <p className="text-sm text-muted-foreground">Selecione um chamado para visualizar e responder mensagens.</p>
                  ) : (
                    <>
                      <div className="max-h-60 overflow-auto space-y-2 border border-border rounded-lg p-3 bg-background/50">
                        {isLoadingMensagens ? (
                          <p className="text-sm text-muted-foreground">Carregando mensagens...</p>
                        ) : chamadoMensagens.length === 0 ? (
                          <p className="text-sm text-muted-foreground">Este chamado ainda não possui mensagens.</p>
                        ) : (
                          chamadoMensagens.map((msg) => (
                            <div key={msg.id} className="rounded-lg border border-border p-2">
                              <p className="text-sm">{msg.mensagem}</p>
                              <p className="text-[11px] text-muted-foreground mt-1">{formatDateTimePtBr(msg.created_at)}</p>
                            </div>
                          ))
                        )}
                        <div ref={conversaMensagensEndRef} />
                      </div>

                      <form onSubmit={handleEnviarMensagemChamado} className="space-y-2">
                        <textarea
                          value={novaMensagemChamado}
                          onChange={(e) => setNovaMensagemChamado(e.target.value)}
                          rows={3}
                          placeholder="Digite sua mensagem sobre o chamado"
                          className="w-full bg-background border border-border rounded-lg px-3 py-2"
                        />
                        <button
                          type="submit"
                          disabled={isSendingMensagem}
                          className="px-4 py-2 rounded-lg bg-primary text-primary-foreground disabled:opacity-60"
                        >
                          {isSendingMensagem ? 'Enviando...' : 'Enviar mensagem'}
                        </button>
                      </form>
                    </>
                  )}
                </div>
              </div>
            </div>
          </>
        )}
      </div>
    </div>
  );
}
