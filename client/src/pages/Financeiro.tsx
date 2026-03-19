import { useEffect, useMemo, useState } from 'react';
import { ArrowUpDown, Banknote, Download, Eye, FileText, History, MoreVertical, RefreshCcw, Search, User } from 'lucide-react';
import { toast } from 'sonner';
import Sidebar from '@/components/Sidebar';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { api } from '@/lib/api';

type ContextoEmissao = 'comissao' | 'locatario' | 'construtora' | 'proprietario';

interface Corretor {
  id: number;
  name: string;
  email: string;
}

interface PessoaTomador {
  id: number;
  nome: string;
  tipo: 'fisica' | 'juridica';
  papeis?: string[] | null;
  cpf?: string | null;
  cnpj?: string | null;
  razao_social?: string | null;
  email?: string | null;
  telefone?: string | null;
  celular?: string | null;
  cep?: string | null;
  endereco?: string | null;
  numero?: string | null;
  bairro?: string | null;
  cidade?: string | null;
  estado?: string | null;
}

interface FinanceiroItem {
  id: number;
  registro_tipo: 'commission_invoice' | 'documento_fiscal';
  contexto_emissao: ContextoEmissao;
  tipo_nota: string;
  codigo_servico?: string | null;
  codigo_servico_fonte?: string | null;
  titulo: string;
  corretor: {
    id: number;
    name: string;
    email: string | null;
  } | null;
  tomador?: {
    id?: number | null;
    nome?: string | null;
    documento?: string | null;
    email?: string | null;
  } | null;
  valor_total: number;
  aliquota_iss: number;
  valor_iss: number;
  descricao_servico: string;
  status: string;
  financeiro_status: string;
  erro_detalhe?: string | null;
  forma_pagamento?: string | null;
  vencimento?: string | null;
  nfse: {
    numero?: string | null;
    pdf_url?: string | null;
    xml_url?: string | null;
    integracao_id?: string | null;
    codigo_verificacao?: string | null;
    rps?: string | null;
    emitida_em?: string | null;
    status_externo?: string | null;
  };
  created_at?: string;
}

type NotaSortKey = 'data' | 'contexto' | 'titulo' | 'tomador' | 'valor' | 'status' | 'financeiro';
type NotaSortDirection = 'asc' | 'desc';

const formatCurrencyInput = (value: string) => {
  const digits = value.replace(/\D/g, '');
  const numberValue = Number(digits) / 100;
  return numberValue.toLocaleString('pt-BR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
};

const parseCurrency = (value: string) => {
  const normalized = value.replace(/\./g, '').replace(',', '.');
  return Number(normalized) || 0;
};

const onlyDigits = (value: string) => value.replace(/\D/g, '');

const isValidCpf = (value: string) => {
  const digits = onlyDigits(value);
  if (digits.length !== 11 || /^([0-9])\1{10}$/.test(digits)) {
    return false;
  }

  let sum = 0;
  for (let index = 0; index < 9; index += 1) {
    sum += Number(digits[index]) * (10 - index);
  }

  let remainder = (sum * 10) % 11;
  if (remainder === 10) remainder = 0;
  if (remainder !== Number(digits[9])) {
    return false;
  }

  sum = 0;
  for (let index = 0; index < 10; index += 1) {
    sum += Number(digits[index]) * (11 - index);
  }

  remainder = (sum * 10) % 11;
  if (remainder === 10) remainder = 0;

  return remainder === Number(digits[10]);
};

const isValidCnpj = (value: string) => {
  const digits = onlyDigits(value);
  if (digits.length !== 14 || /^([0-9])\1{13}$/.test(digits)) {
    return false;
  }

  const calculateDigit = (base: string, factors: number[]) => {
    const total = factors.reduce((sum, factor, index) => sum + Number(base[index]) * factor, 0);
    const remainder = total % 11;
    return remainder < 2 ? 0 : 11 - remainder;
  };

  const base = digits.slice(0, 12);
  const firstDigit = calculateDigit(base, [5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);
  const secondDigit = calculateDigit(`${base}${firstDigit}`, [6, 5, 4, 3, 2, 9, 8, 7, 6, 5, 4, 3, 2]);

  return digits === `${base}${firstDigit}${secondDigit}`;
};

const isValidFederalTaxNumber = (value: string) => {
  const digits = onlyDigits(value);

  if (digits.length === 11) {
    return isValidCpf(digits);
  }

  if (digits.length === 14) {
    return isValidCnpj(digits);
  }

  return false;
};

const formatCurrency = (value: number) =>
  value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const formatDate = (value?: string | null) => {
  if (!value) return 'N/A';

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return date.toLocaleDateString('pt-BR');
};

const normalizeText = (value?: string | null) =>
  (value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase();

const statusStyles: Record<string, string> = {
  pending: 'bg-yellow-500/20 text-yellow-200 border border-yellow-500/30',
  issued: 'bg-blue-500/20 text-blue-200 border border-blue-500/30',
  paid: 'bg-emerald-500/20 text-emerald-200 border border-emerald-500/30',
  cancelled: 'bg-red-500/20 text-red-200 border border-red-500/30',
  error: 'bg-red-500/20 text-red-200 border border-red-500/30',
  created: 'bg-indigo-500/20 text-indigo-200 border border-indigo-500/30',
  lancado: 'bg-indigo-500/20 text-indigo-200 border border-indigo-500/30',
  pendente: 'bg-yellow-500/20 text-yellow-200 border border-yellow-500/30',
  cancelado: 'bg-red-500/20 text-red-200 border border-red-500/30',
};

const contextoLabels: Record<ContextoEmissao, string> = {
  comissao: 'Comissão',
  locatario: 'Locatário',
  construtora: 'Construtora',
  proprietario: 'Proprietário vendedor',
};

const paymentMethodLabels: Record<string, string> = {
  pix: 'PIX',
  boleto: 'Boleto',
};

const registroTipoLabels: Record<FinanceiroItem['registro_tipo'], string> = {
  commission_invoice: 'Comissão',
  documento_fiscal: 'Documento fiscal',
};

export default function Financeiro() {
  const [corretores, setCorretores] = useState<Corretor[]>([]);
  const [pessoasTomador, setPessoasTomador] = useState<PessoaTomador[]>([]);
  const [items, setItems] = useState<FinanceiroItem[]>([]);
  const [contextoEmissao, setContextoEmissao] = useState<ContextoEmissao>('comissao');
  const [corretorId, setCorretorId] = useState('');
  const [tipoNota, setTipoNota] = useState<'corretagem' | 'aluguel'>('corretagem');
  const [valor, setValor] = useState('');
  const [aliquotaIss, setAliquotaIss] = useState('5');
  const [descricao, setDescricao] = useState('');

  const [tomadorNome, setTomadorNome] = useState('');
  const [tomadorDocumento, setTomadorDocumento] = useState('');
  const [tomadorEmail, setTomadorEmail] = useState('');
  const [tomadorTelefone, setTomadorTelefone] = useState('');
  const [tomadorCep, setTomadorCep] = useState('');
  const [tomadorLogradouro, setTomadorLogradouro] = useState('');
  const [tomadorNumero, setTomadorNumero] = useState('');
  const [tomadorBairro, setTomadorBairro] = useState('');
  const [tomadorCidade, setTomadorCidade] = useState('Belo Horizonte');
  const [tomadorUf, setTomadorUf] = useState('MG');
  const [tomadorCodigoMunicipio, setTomadorCodigoMunicipio] = useState('3106200');
  const [pessoaTomadorId, setPessoaTomadorId] = useState('');

  const [formaPagamento, setFormaPagamento] = useState<'pix' | 'boleto'>('pix');
  const [vencimento, setVencimento] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [syncingIds, setSyncingIds] = useState<number[]>([]);
  const [notaBusca, setNotaBusca] = useState('');
  const [notaStatusFiltro, setNotaStatusFiltro] = useState('todos');
  const [notaPeriodoFiltro, setNotaPeriodoFiltro] = useState<'12m' | '90d' | '30d' | 'todos'>('12m');
  const [notaPagina, setNotaPagina] = useState(1);
  const [notaOrdenacao, setNotaOrdenacao] = useState<{ key: NotaSortKey; direction: NotaSortDirection }>({ key: 'data', direction: 'desc' });
  const [notasPorPagina, setNotasPorPagina] = useState(10);

  const valorNumerico = useMemo(() => parseCurrency(valor), [valor]);
  const valorIss = useMemo(() => (valorNumerico * (Number(aliquotaIss) || 0)) / 100, [valorNumerico, aliquotaIss]);
  const tomadoresDisponiveis = useMemo(() => {
    if (contextoEmissao === 'locatario') {
      return pessoasTomador.filter((pessoa) => (pessoa.papeis || []).includes('inquilino'));
    }

    if (contextoEmissao === 'construtora') {
      return pessoasTomador.filter((pessoa) => pessoa.tipo === 'juridica' && (pessoa.papeis || []).includes('construtora'));
    }

    if (contextoEmissao === 'proprietario') {
      return pessoasTomador.filter((pessoa) => pessoa.tipo === 'fisica' && (pessoa.papeis || []).includes('proprietario'));
    }

    return pessoasTomador;
  }, [contextoEmissao, pessoasTomador]);

  const getNotaDataReferencia = (item: FinanceiroItem) => item.nfse.emitida_em || item.created_at || null;

  const podeSincronizarDocumento = (item: FinanceiroItem) => {
    const precisaSincronizarNumero = !item.nfse.numero || item.nfse.numero === '0';
    const precisaSincronizarArquivos = !item.nfse.pdf_url || !item.nfse.xml_url;

    return (
      item.registro_tipo === 'documento_fiscal' &&
      (item.status === 'issued' || item.status === 'pending') &&
      !!item.nfse.integracao_id &&
      (precisaSincronizarNumero || precisaSincronizarArquivos)
    );
  };

  const notasFiltradas = useMemo(() => {
    const now = new Date();

    const filtered = [...items]
      .filter((item) => {
        const term = normalizeText(notaBusca);
        const matchesSearch =
          term === '' ||
          [
            contextoLabels[item.contexto_emissao],
            registroTipoLabels[item.registro_tipo],
            item.nfse.numero,
            item.nfse.rps,
            item.titulo,
            item.tomador?.nome,
            item.tomador?.documento,
            item.descricao_servico,
            item.codigo_servico,
            item.status,
          ].some((field) => normalizeText(field).includes(term));

        const matchesStatus = notaStatusFiltro === 'todos'
          ? true
          : item.status === notaStatusFiltro || item.financeiro_status === notaStatusFiltro;

        const dataReferencia = getNotaDataReferencia(item);
        const data = dataReferencia ? new Date(dataReferencia) : null;

        let matchesPeriodo = true;
        if (data && !Number.isNaN(data.getTime())) {
          if (notaPeriodoFiltro === '30d') {
            const limit = new Date(now);
            limit.setDate(limit.getDate() - 30);
            matchesPeriodo = data >= limit;
          } else if (notaPeriodoFiltro === '90d') {
            const limit = new Date(now);
            limit.setDate(limit.getDate() - 90);
            matchesPeriodo = data >= limit;
          } else if (notaPeriodoFiltro === '12m') {
            const limit = new Date(now);
            limit.setMonth(limit.getMonth() - 12);
            matchesPeriodo = data >= limit;
          }
        } else if (notaPeriodoFiltro !== 'todos') {
          matchesPeriodo = false;
        }

        return matchesSearch && matchesStatus && matchesPeriodo;
      });

    return filtered.sort((left, right) => {
      const leftDate = getNotaDataReferencia(left) ? new Date(getNotaDataReferencia(left) as string).getTime() : 0;
      const rightDate = getNotaDataReferencia(right) ? new Date(getNotaDataReferencia(right) as string).getTime() : 0;

      const leftValue = (() => {
        switch (notaOrdenacao.key) {
          case 'contexto':
            return contextoLabels[left.contexto_emissao];
          case 'titulo':
            return left.titulo || left.descricao_servico || '';
          case 'tomador':
            return left.tomador?.nome || '';
          case 'valor':
            return left.valor_total || 0;
          case 'status':
            return left.status || '';
          case 'financeiro':
            return left.financeiro_status || '';
          case 'data':
          default:
            return leftDate;
        }
      })();

      const rightValue = (() => {
        switch (notaOrdenacao.key) {
          case 'contexto':
            return contextoLabels[right.contexto_emissao];
          case 'titulo':
            return right.titulo || right.descricao_servico || '';
          case 'tomador':
            return right.tomador?.nome || '';
          case 'valor':
            return right.valor_total || 0;
          case 'status':
            return right.status || '';
          case 'financeiro':
            return right.financeiro_status || '';
          case 'data':
          default:
            return rightDate;
        }
      })();

      if (typeof leftValue === 'number' && typeof rightValue === 'number') {
        return notaOrdenacao.direction === 'asc' ? leftValue - rightValue : rightValue - leftValue;
      }

      const comparison = String(leftValue).localeCompare(String(rightValue), 'pt-BR', { sensitivity: 'base' });
      return notaOrdenacao.direction === 'asc' ? comparison : -comparison;
    });
  }, [items, notaBusca, notaOrdenacao, notaPeriodoFiltro, notaStatusFiltro]);

  const totalPaginasNotas = Math.max(1, Math.ceil(notasFiltradas.length / notasPorPagina));
  const notasPaginadas = notasFiltradas.slice((notaPagina - 1) * notasPorPagina, notaPagina * notasPorPagina);
  const notaInicio = notasFiltradas.length === 0 ? 0 : (notaPagina - 1) * notasPorPagina + 1;
  const notaFim = Math.min(notaPagina * notasPorPagina, notasFiltradas.length);

  useEffect(() => {
    setNotaPagina(1);
  }, [notaBusca, notaOrdenacao, notaPeriodoFiltro, notaStatusFiltro, notasPorPagina]);

  const alternarOrdenacao = (key: NotaSortKey) => {
    setNotaOrdenacao((current) => {
      if (current.key === key) {
        return { key, direction: current.direction === 'asc' ? 'desc' : 'asc' };
      }

      return { key, direction: key === 'data' ? 'desc' : 'asc' };
    });
  };

  const exportarNotasCsv = () => {
    const headers = ['Status', 'NF', 'RPS', 'Data', 'Tomador', 'Valor', 'Descricao', 'CodigoServico', 'Integracao'];
    const rows = notasFiltradas.map((item) => [
      item.status,
      item.nfse.numero || '',
      item.nfse.rps || '',
      formatDate(getNotaDataReferencia(item)),
      item.tomador?.nome || item.corretor?.name || '',
      String(item.valor_total),
      item.descricao_servico,
      item.codigo_servico || '',
      item.nfse.integracao_id || '',
    ]);

    const escapeCsv = (value: string) => `"${value.replace(/"/g, '""')}"`;
    const csv = [headers, ...rows]
      .map((row) => row.map((value) => escapeCsv(String(value ?? ''))).join(';'))
      .join('\n');

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `notas-fiscais-${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);
    toast.success('Notas fiscais exportadas em CSV');
  };

  const carregarCorretores = async () => {
    try {
      const response = await api.get('/admin/corretores');
      if (response.data?.success) {
        setCorretores(response.data.corretores || []);
      }
    } catch (error) {
      console.error('Erro ao carregar corretores:', error);
      toast.error('Não foi possível carregar corretores');
    }
  };

  const carregarHistorico = async () => {
    setIsLoading(true);
    try {
      const response = await api.get('/admin/financeiro/notas-servico');
      if (response.data?.success) {
        const loadedItems = response.data.items || [];
        setItems(loadedItems);

        const pendentesDeSincronizacao = loadedItems.filter((item: FinanceiroItem) => {
          const precisaSincronizarNumero = !item.nfse.numero || item.nfse.numero === '0';
          const precisaSincronizarArquivos = !item.nfse.pdf_url || !item.nfse.xml_url;

          return (
            item.registro_tipo === 'documento_fiscal' &&
            (item.status === 'issued' || item.status === 'pending') &&
            !!item.nfse.integracao_id &&
            (precisaSincronizarNumero || precisaSincronizarArquivos)
          );
        });

        if (pendentesDeSincronizacao.length > 0) {
          const idsParaSincronizar = pendentesDeSincronizacao.slice(0, 5).map((item: FinanceiroItem) => item.id);
          const resultados = await Promise.allSettled(
            idsParaSincronizar.map((id: number) => api.post(`/admin/financeiro/notas-servico/${id}/sincronizar`))
          );

          if (resultados.some((resultado) => resultado.status === 'fulfilled')) {
            const refreshed = await api.get('/admin/financeiro/notas-servico');
            if (refreshed.data?.success) {
              setItems(refreshed.data.items || []);
            }
          }
        }
      } else {
        setItems([]);
      }
    } catch (error) {
      console.error('Erro ao carregar histórico financeiro:', error);
      toast.error('Não foi possível carregar histórico financeiro');
      setItems([]);
    } finally {
      setIsLoading(false);
    }
  };

  const carregarPessoasTomador = async () => {
    try {
      const response = await api.get('/pessoas', {
        params: {
          per_page: 100,
          ativo: 1,
        },
      });

      setPessoasTomador(response.data?.data || []);
    } catch (error) {
      console.error('Erro ao carregar pessoas para tomador:', error);
      setPessoasTomador([]);
    }
  };

  const preencherTomadorPorPessoa = (pessoa: PessoaTomador | undefined) => {
    if (!pessoa) {
      return;
    }

    const documento = pessoa.tipo === 'juridica' ? pessoa.cnpj : pessoa.cpf;
    setTomadorNome((pessoa.tipo === 'juridica' ? pessoa.razao_social : pessoa.nome) || pessoa.nome || '');
    setTomadorDocumento(documento || '');
    setTomadorEmail(pessoa.email || '');
    setTomadorTelefone(pessoa.celular || pessoa.telefone || '');
    setTomadorCep(pessoa.cep || '');
    setTomadorLogradouro(pessoa.endereco || '');
    setTomadorNumero(pessoa.numero || '');
    setTomadorBairro(pessoa.bairro || '');
    setTomadorCidade(pessoa.cidade || 'Belo Horizonte');
    setTomadorUf(pessoa.estado || 'MG');
  };

  const limparFormulario = () => {
    setValor('');
    setDescricao('');
    setPessoaTomadorId('');
    setTomadorNome('');
    setTomadorDocumento('');
    setTomadorEmail('');
    setTomadorTelefone('');
    setTomadorCep('');
    setTomadorLogradouro('');
    setTomadorNumero('');
    setTomadorBairro('');
    setVencimento('');
  };

  const sincronizarDocumento = async (itemId: number) => {
    setSyncingIds((current) => [...current, itemId]);

    try {
      const response = await api.post(`/admin/financeiro/notas-servico/${itemId}/sincronizar`);
      const syncedItem = response.data?.item as FinanceiroItem | undefined;

      if (syncedItem) {
        setItems((current) => current.map((item) => (item.id === itemId && item.registro_tipo === 'documento_fiscal' ? syncedItem : item)));
      }

      if (syncedItem?.nfse.pdf_url || syncedItem?.nfse.xml_url || (syncedItem?.nfse.numero && syncedItem.nfse.numero !== '0')) {
        toast.success('NFSe sincronizada com sucesso');
      } else {
        toast.message('NFSe ainda está em processamento na NFe.io');
      }
    } catch (error: any) {
      const apiMessage = error?.response?.data?.message;
      const apiError = error?.response?.data?.error;
      toast.error(apiMessage || apiError || 'Erro ao sincronizar NFSe');
    } finally {
      setSyncingIds((current) => current.filter((id) => id !== itemId));
    }
  };

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if ((contextoEmissao === 'comissao' && !corretorId) || valorNumerico <= 0 || !tomadorNome || !tomadorDocumento) {
      toast.error('Preencha os campos obrigatórios');
      return;
    }

    if (!isValidFederalTaxNumber(tomadorDocumento)) {
      toast.error('CPF/CNPJ do tomador é inválido');
      return;
    }

    setIsSubmitting(true);

    try {
      const response = await api.post('/admin/financeiro/notas-servico', {
        contexto_emissao: contextoEmissao,
        corretor_id: contextoEmissao === 'comissao' ? Number(corretorId) : undefined,
        pessoa_tomador_id: pessoaTomadorId ? Number(pessoaTomadorId) : undefined,
        tipo_nota: tipoNota,
        valor: valorNumerico,
        aliquota_iss: Number(aliquotaIss) || 0,
        descricao: descricao || undefined,
        tomador: {
          nome: tomadorNome,
          documento: tomadorDocumento,
          email: tomadorEmail || undefined,
          telefone: tomadorTelefone || undefined,
          endereco: {
            cep: tomadorCep || undefined,
            logradouro: tomadorLogradouro || undefined,
            numero: tomadorNumero || undefined,
            bairro: tomadorBairro || undefined,
            cidade: tomadorCidade || undefined,
            uf: tomadorUf || undefined,
            codigoMunicipio: tomadorCodigoMunicipio || undefined,
          },
        },
        financeiro: {
          vencimento: vencimento || undefined,
          forma_pagamento: formaPagamento,
          descricao:
            contextoEmissao === 'locatario'
              ? 'Cobrança de aluguel - emissão com boleto'
              : contextoEmissao === 'construtora'
                ? 'Cobrança de serviços imobiliários para construtora'
                : contextoEmissao === 'proprietario'
                  ? 'Cobrança de corretagem imobiliária ao proprietário vendedor'
              : 'Cobrança de comissão',
        },
      });

      if (response.data?.success) {
        toast.success(
          contextoEmissao === 'locatario' && formaPagamento === 'boleto'
            ? 'Lançamento de aluguel emitido com fluxo de boleto'
            : contextoEmissao === 'proprietario'
              ? 'NFSe para proprietária emitida com sucesso'
              : 'Lançamento financeiro emitido com sucesso'
        );
        limparFormulario();
        await carregarHistorico();
      } else {
        toast.error(response.data?.message || 'Erro ao emitir lançamento');
      }
    } catch (error: any) {
      console.error('Erro ao emitir lançamento:', {
        status: error?.response?.status,
        data: error?.response?.data,
        message: error?.message,
      });

      const apiMessage = error?.response?.data?.message;
      const apiError = error?.response?.data?.error;

      toast.error(apiMessage || apiError || 'Erro ao emitir lançamento');
    } finally {
      setIsSubmitting(false);
    }
  };

  useEffect(() => {
    carregarCorretores();
    carregarPessoasTomador();
    carregarHistorico();
  }, []);

  return (
    <div className="flex">
      <Sidebar />

      <div className="page-shell">
        <div className="page-content space-y-8">
          <div className="page-header gap-4">
            <div>
              <h1 className="page-title mb-2 flex items-center gap-3">
                <Banknote size={32} className="text-emerald-300" />
                Financeiro
              </h1>
              <p className="page-subtitle">Gestão de comissão e aluguel com emissão fiscal e cobrança.</p>
            </div>
            <button
              type="button"
              onClick={carregarHistorico}
              className="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-white/10 px-4 py-2 text-sm font-semibold text-foreground transition hover:bg-white/20 sm:w-auto"
            >
              <RefreshCcw size={16} />
              Atualizar histórico
            </button>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-[2fr_1fr] gap-6">
            <div className="glass-panel p-6 rounded-2xl space-y-6">
              <div className="flex items-center gap-2 text-lg font-semibold text-foreground">
                <FileText size={20} />
                Novo Lançamento
              </div>

              <form onSubmit={handleSubmit} className="space-y-6">
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div className="space-y-2">
                    <label className="text-sm font-semibold text-foreground">Contexto de emissão</label>
                    <select
                      value={contextoEmissao}
                      onChange={(event) => {
                        const value = event.target.value as ContextoEmissao;
                        setContextoEmissao(value);
                        if (value === 'locatario') {
                          setFormaPagamento('boleto');
                          setTipoNota('aluguel');
                        } else if (value === 'construtora') {
                          setTipoNota('corretagem');
                          setFormaPagamento('pix');
                        } else if (value === 'proprietario') {
                          setTipoNota('corretagem');
                          setFormaPagamento('pix');
                        }
                      }}
                      className="w-full px-4 py-2 rounded-xl bg-white/5 border border-white/10 text-foreground"
                    >
                      <option value="comissao">Comissão</option>
                      <option value="locatario">Locatário</option>
                      <option value="construtora">Construtora</option>
                      <option value="proprietario">Proprietário vendedor</option>
                    </select>
                  </div>
                  <div className="space-y-2">
                    <label className="text-sm font-semibold text-foreground">Tipo de nota</label>
                    <select
                      value={tipoNota}
                      onChange={(event) => {
                        const value = event.target.value as 'corretagem' | 'aluguel';
                        setTipoNota(value);
                        if (contextoEmissao === 'locatario' || value === 'aluguel') setFormaPagamento('boleto');
                      }}
                      className="w-full px-4 py-2 rounded-xl bg-white/5 border border-white/10 text-foreground"
                      disabled={contextoEmissao !== 'comissao'}
                    >
                      <option value="corretagem">Corretagem</option>
                      <option value="aluguel">Aluguel</option>
                    </select>
                  </div>
                  {contextoEmissao === 'comissao' && (
                  <div className="space-y-2">
                    <label className="text-sm font-semibold text-foreground flex items-center gap-2">
                      <User size={16} /> Corretor
                    </label>
                    <select
                      value={corretorId}
                      onChange={(event) => setCorretorId(event.target.value)}
                      className="w-full px-4 py-2 rounded-xl bg-white/5 border border-white/10 text-foreground"
                      required
                    >
                      <option value="">Selecione</option>
                      {corretores.map((corretor) => (
                        <option key={corretor.id} value={corretor.id}>
                          {corretor.name} - {corretor.email}
                        </option>
                      ))}
                    </select>
                  </div>
                  )}
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div className="space-y-2">
                    <label className="text-sm font-semibold text-foreground">Valor</label>
                    <div className="flex items-center gap-2">
                      <span className="text-sm text-muted-foreground">R$</span>
                      <input
                        type="text"
                        value={valor}
                        onChange={(event) => setValor(formatCurrencyInput(event.target.value))}
                        placeholder="0,00"
                        className="w-full px-4 py-2 rounded-xl bg-white/5 border border-white/10 text-foreground"
                        required
                      />
                    </div>
                  </div>
                  <div className="space-y-2">
                    <label className="text-sm font-semibold text-foreground">Alíquota ISS (%)</label>
                    <input
                      type="number"
                      min="0"
                      step="0.01"
                      value={aliquotaIss}
                      onChange={(event) => setAliquotaIss(event.target.value)}
                      className="w-full px-4 py-2 rounded-xl bg-white/5 border border-white/10 text-foreground"
                    />
                  </div>
                  <div className="space-y-2">
                    <label className="text-sm font-semibold text-foreground">Vencimento</label>
                    <input
                      type="date"
                      value={vencimento}
                      onChange={(event) => setVencimento(event.target.value)}
                      className="w-full px-4 py-2 rounded-xl bg-white/5 border border-white/10 text-foreground"
                    />
                  </div>
                </div>

                <div className="rounded-2xl bg-white/5 border border-white/10 p-4 text-center">
                  <p className="text-sm text-muted-foreground">Base / ISS</p>
                  <p className="text-2xl font-bold text-emerald-300">
                    R$ {formatCurrency(valorNumerico)} / R$ {formatCurrency(valorIss)}
                  </p>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div className="space-y-2 md:col-span-2">
                    <label className="text-sm font-semibold text-foreground">Tomador (cadastro de pessoas)</label>
                    <select
                      value={pessoaTomadorId}
                      onChange={(event) => {
                        const id = event.target.value;
                        setPessoaTomadorId(id);
                        if (!id) return;

                        const pessoa = pessoasTomador.find((item) => String(item.id) === id);
                        preencherTomadorPorPessoa(pessoa);
                      }}
                      className="w-full px-4 py-2 rounded-xl bg-white/5 border border-white/10 text-foreground"
                    >
                      <option value="">Preencher manualmente</option>
                      {tomadoresDisponiveis.map((pessoa) => (
                        <option key={pessoa.id} value={pessoa.id}>
                          {pessoa.tipo === 'juridica'
                            ? `${pessoa.razao_social || pessoa.nome} (PJ)`
                            : `${pessoa.nome} (PF)`}
                        </option>
                      ))}
                    </select>
                  </div>

                  <div className="space-y-2">
                    <label className="text-sm font-semibold text-foreground">Tomador - Nome</label>
                    <input
                      type="text"
                      value={tomadorNome}
                      onChange={(event) => setTomadorNome(event.target.value)}
                      className="w-full px-4 py-2 rounded-xl bg-white/5 border border-white/10 text-foreground"
                      required
                    />
                  </div>
                  <div className="space-y-2">
                    <label className="text-sm font-semibold text-foreground">Tomador - CPF/CNPJ</label>
                    <input
                      type="text"
                      value={tomadorDocumento}
                      onChange={(event) => setTomadorDocumento(event.target.value)}
                      className="w-full px-4 py-2 rounded-xl bg-white/5 border border-white/10 text-foreground"
                      required
                    />
                  </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div className="space-y-2">
                    <label className="text-sm font-semibold text-foreground">Email</label>
                    <input
                      type="email"
                      value={tomadorEmail}
                      onChange={(event) => setTomadorEmail(event.target.value)}
                      className="w-full px-4 py-2 rounded-xl bg-white/5 border border-white/10 text-foreground"
                    />
                  </div>
                  <div className="space-y-2">
                    <label className="text-sm font-semibold text-foreground">Telefone</label>
                    <input
                      type="text"
                      value={tomadorTelefone}
                      onChange={(event) => setTomadorTelefone(event.target.value)}
                      className="w-full px-4 py-2 rounded-xl bg-white/5 border border-white/10 text-foreground"
                    />
                  </div>
                </div>

                <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                  <div className="space-y-2">
                    <label className="text-sm font-semibold text-foreground">CEP</label>
                    <input
                      type="text"
                      value={tomadorCep}
                      onChange={(event) => setTomadorCep(event.target.value)}
                      className="w-full px-4 py-2 rounded-xl bg-white/5 border border-white/10 text-foreground"
                    />
                  </div>
                  <div className="space-y-2 md:col-span-2">
                    <label className="text-sm font-semibold text-foreground">Logradouro</label>
                    <input
                      type="text"
                      value={tomadorLogradouro}
                      onChange={(event) => setTomadorLogradouro(event.target.value)}
                      className="w-full px-4 py-2 rounded-xl bg-white/5 border border-white/10 text-foreground"
                    />
                  </div>
                  <div className="space-y-2">
                    <label className="text-sm font-semibold text-foreground">Número</label>
                    <input
                      type="text"
                      value={tomadorNumero}
                      onChange={(event) => setTomadorNumero(event.target.value)}
                      className="w-full px-4 py-2 rounded-xl bg-white/5 border border-white/10 text-foreground"
                    />
                  </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div className="space-y-2">
                    <label className="text-sm font-semibold text-foreground">Bairro</label>
                    <input
                      type="text"
                      value={tomadorBairro}
                      onChange={(event) => setTomadorBairro(event.target.value)}
                      className="w-full px-4 py-2 rounded-xl bg-white/5 border border-white/10 text-foreground"
                    />
                  </div>
                  <div className="space-y-2">
                    <label className="text-sm font-semibold text-foreground">Cidade</label>
                    <input
                      type="text"
                      value={tomadorCidade}
                      onChange={(event) => setTomadorCidade(event.target.value)}
                      className="w-full px-4 py-2 rounded-xl bg-white/5 border border-white/10 text-foreground"
                    />
                  </div>
                  <div className="space-y-2">
                    <label className="text-sm font-semibold text-foreground">UF</label>
                    <input
                      type="text"
                      value={tomadorUf}
                      onChange={(event) => setTomadorUf(event.target.value)}
                      className="w-full px-4 py-2 rounded-xl bg-white/5 border border-white/10 text-foreground"
                    />
                  </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div className="space-y-2">
                    <label className="text-sm font-semibold text-foreground">Código do município (IBGE)</label>
                    <input
                      type="text"
                      value={tomadorCodigoMunicipio}
                      onChange={(event) => setTomadorCodigoMunicipio(event.target.value)}
                      className="w-full px-4 py-2 rounded-xl bg-white/5 border border-white/10 text-foreground"
                    />
                  </div>
                  <div className="space-y-2">
                    <label className="text-sm font-semibold text-foreground">Forma de pagamento</label>
                    <select
                      value={formaPagamento}
                      onChange={(event) => setFormaPagamento(event.target.value as 'pix' | 'boleto')}
                      className="w-full px-4 py-2 rounded-xl bg-white/5 border border-white/10 text-foreground"
                    >
                      <option value="pix">PIX</option>
                      <option value="boleto">Boleto</option>
                    </select>
                  </div>
                </div>

                <div className="space-y-2">
                  <label className="text-sm font-semibold text-foreground">Descrição de serviço (opcional)</label>
                  <textarea
                    value={descricao}
                    onChange={(event) => setDescricao(event.target.value)}
                    rows={3}
                    className="w-full px-4 py-2 rounded-xl bg-white/5 border border-white/10 text-foreground"
                    placeholder="Se vazio, o backend gera automaticamente"
                  />
                </div>

                <button
                  type="submit"
                  disabled={isSubmitting}
                  className="w-full px-6 py-3 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 text-white font-semibold hover:from-emerald-600 hover:to-teal-600 transition disabled:opacity-60"
                >
                  {isSubmitting
                    ? 'Emitindo...'
                    : `Emitir ${contextoEmissao === 'locatario' ? 'nota para locatário' : contextoEmissao === 'construtora' ? 'nota para construtora' : contextoEmissao === 'proprietario' ? 'nota para proprietária' : tipoNota === 'aluguel' ? 'aluguel' : 'comissão'} com NFSe`}
                </button>
              </form>
            </div>

            <div className="space-y-6">
              <div className="glass-panel p-6 rounded-2xl">
                <div className="flex items-center gap-2 text-lg font-semibold text-foreground mb-4">
                  <Banknote size={20} />
                  Fluxo operacional
                </div>
                <ol className="space-y-2 text-sm text-muted-foreground">
                  <li>Escolha se a emissão é para comissão, locatário, construtora ou proprietário vendedor.</li>
                  <li>O backend emite a NFS-e na NFE.io.</li>
                  <li>Para locatário, o fluxo padrão usa boleto.</li>
                  <li>Acompanhe status fiscal e financeiro no histórico abaixo.</li>
                </ol>
              </div>
            </div>
          </div>

          <div className="glass-panel p-6 rounded-2xl space-y-6">
            <div className="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
              <div>
                <div className="flex items-center gap-2 text-lg font-semibold text-foreground">
                  <History size={20} />
                  Histórico unificado (aluguel + comissão)
                </div>
                <p className="mt-1 text-sm text-muted-foreground">
                  Datatable operacional com busca, filtros, ordenação e ações rápidas para aluguel e comissão no mesmo lugar.
                </p>
              </div>
              <div className="flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                {notaPeriodoFiltro !== 'todos' && (
                  <button
                    type="button"
                    onClick={() => setNotaPeriodoFiltro('todos')}
                    className="rounded-full border border-white/10 bg-white/5 px-3 py-1.5 text-foreground hover:bg-white/10"
                  >
                    {notaPeriodoFiltro === '12m' ? 'Criado nos últimos 12 meses' : notaPeriodoFiltro === '90d' ? 'Criado nos últimos 90 dias' : 'Criado nos últimos 30 dias'}
                  </button>
                )}
              </div>
            </div>

            <div className="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
              <div className="flex flex-1 flex-col gap-3 lg:flex-row">
                <label className="relative flex-1">
                  <Search size={16} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
                  <input
                    value={notaBusca}
                    onChange={(event) => setNotaBusca(event.target.value)}
                    placeholder="Buscar por número, contexto, tomador, descrição ou código"
                    className="w-full rounded-xl border border-white/10 bg-white/5 py-2.5 pl-10 pr-4 text-sm text-foreground placeholder:text-muted-foreground"
                  />
                </label>
                <select
                  value={notaStatusFiltro}
                  onChange={(event) => setNotaStatusFiltro(event.target.value)}
                  className="rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-foreground"
                >
                  <option value="todos">Todos os status</option>
                  <option value="issued">Emitida</option>
                  <option value="pending">Pendente</option>
                  <option value="created">Criada</option>
                  <option value="cancelled">Cancelada</option>
                  <option value="error">Erro</option>
                </select>
                <select
                  value={notaPeriodoFiltro}
                  onChange={(event) => setNotaPeriodoFiltro(event.target.value as '12m' | '90d' | '30d' | 'todos')}
                  className="rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-foreground"
                >
                  <option value="12m">Últimos 12 meses</option>
                  <option value="90d">Últimos 90 dias</option>
                  <option value="30d">Últimos 30 dias</option>
                  <option value="todos">Todo o período</option>
                </select>
                <select
                  value={String(notasPorPagina)}
                  onChange={(event) => setNotasPorPagina(Number(event.target.value))}
                  className="rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm text-foreground"
                >
                  <option value="10">10 linhas</option>
                  <option value="25">25 linhas</option>
                  <option value="50">50 linhas</option>
                </select>
              </div>

              <div className="flex gap-2">
                <button
                  type="button"
                  onClick={() => {
                    setNotaBusca('');
                    setNotaStatusFiltro('todos');
                    setNotaPeriodoFiltro('12m');
                  }}
                  className="rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm font-medium text-foreground hover:bg-white/10"
                >
                  Limpar
                </button>
                <button
                  type="button"
                  onClick={exportarNotasCsv}
                  className="inline-flex items-center gap-2 rounded-xl border border-emerald-400/30 bg-emerald-500/10 px-4 py-2.5 text-sm font-semibold text-emerald-100 hover:bg-emerald-500/20"
                >
                  <Download size={16} />
                  Exportar
                </button>
              </div>
            </div>

            <div className="flex flex-col gap-2 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-muted-foreground md:flex-row md:items-center md:justify-between">
              <div>
                Exibindo {notaInicio}-{notaFim} de {notasFiltradas.length} registro(s)
              </div>
              <div className="flex items-center gap-3">
                <span>Ordenado por {notaOrdenacao.key} ({notaOrdenacao.direction === 'asc' ? 'crescente' : 'decrescente'})</span>
              </div>
            </div>

            {isLoading && <p className="text-muted-foreground">Carregando histórico...</p>}

            {!isLoading && notasFiltradas.length === 0 && (
              <p className="text-muted-foreground">Nenhum lançamento encontrado.</p>
            )}

            <div className="overflow-hidden rounded-2xl border border-white/10 bg-black/10">
              <div className="overflow-x-auto">
                <table className="w-full min-w-[1260px] text-sm">
                  <thead className="bg-white/5 text-left text-xs uppercase tracking-[0.18em] text-muted-foreground">
                    <tr>
                      <th className="px-4 py-3">Documento</th>
                      <th className="px-4 py-3">
                        <button type="button" onClick={() => alternarOrdenacao('data')} className="inline-flex items-center gap-2 hover:text-foreground">
                          Data
                          <ArrowUpDown size={14} />
                        </button>
                      </th>
                      <th className="px-4 py-3">
                        <button type="button" onClick={() => alternarOrdenacao('contexto')} className="inline-flex items-center gap-2 hover:text-foreground">
                          Contexto
                          <ArrowUpDown size={14} />
                        </button>
                      </th>
                      <th className="px-4 py-3">
                        <button type="button" onClick={() => alternarOrdenacao('titulo')} className="inline-flex items-center gap-2 hover:text-foreground">
                          Título
                          <ArrowUpDown size={14} />
                        </button>
                      </th>
                      <th className="px-4 py-3">
                        <button type="button" onClick={() => alternarOrdenacao('tomador')} className="inline-flex items-center gap-2 hover:text-foreground">
                          Tomador
                          <ArrowUpDown size={14} />
                        </button>
                      </th>
                      <th className="px-4 py-3 text-right">
                        <button type="button" onClick={() => alternarOrdenacao('valor')} className="inline-flex items-center gap-2 hover:text-foreground">
                          Valor (R$)
                          <ArrowUpDown size={14} />
                        </button>
                      </th>
                      <th className="px-4 py-3">
                        <button type="button" onClick={() => alternarOrdenacao('status')} className="inline-flex items-center gap-2 hover:text-foreground">
                          Status fiscal
                          <ArrowUpDown size={14} />
                        </button>
                      </th>
                      <th className="px-4 py-3">
                        <button type="button" onClick={() => alternarOrdenacao('financeiro')} className="inline-flex items-center gap-2 hover:text-foreground">
                          Status financeiro
                          <ArrowUpDown size={14} />
                        </button>
                      </th>
                      <th className="px-4 py-3">Pagamento</th>
                      <th className="px-4 py-3 text-right">Ações</th>
                    </tr>
                  </thead>
                  <tbody>
                    {!isLoading && notasPaginadas.map((item) => {
                      const isSyncing = syncingIds.includes(item.id);
                      const podeSincronizar = podeSincronizarDocumento(item);

                      return (
                        <tr key={`${item.registro_tipo}-${item.id}`} className="border-t border-white/10 text-foreground/90">
                          <td className="px-4 py-3">
                            <div className="font-medium text-foreground">{item.nfse.numero || 'Sem NF'}</div>
                            <div className="text-xs text-muted-foreground">{registroTipoLabels[item.registro_tipo]} · RPS {item.nfse.rps || '—'}</div>
                          </td>
                          <td className="px-4 py-3 text-muted-foreground">{formatDate(getNotaDataReferencia(item))}</td>
                          <td className="px-4 py-3">
                            <div className="font-medium text-foreground">{contextoLabels[item.contexto_emissao]}</div>
                            <div className="text-xs text-muted-foreground">{item.tipo_nota}</div>
                          </td>
                          <td className="max-w-[240px] px-4 py-3">
                            <div className="truncate font-medium text-foreground" title={item.titulo}>{item.titulo}</div>
                            <div className="truncate text-xs text-muted-foreground" title={item.descricao_servico}>{item.descricao_servico}</div>
                          </td>
                          <td className="px-4 py-3">
                            <div className="font-medium text-foreground">{item.tomador?.nome || 'Tomador não informado'}</div>
                            <div className="text-xs text-muted-foreground">{item.tomador?.documento || 'Documento não informado'}</div>
                          </td>
                          <td className="px-4 py-3 text-right font-medium">{formatCurrency(item.valor_total)}</td>
                          <td className="px-4 py-3">
                            <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${statusStyles[item.status] || 'bg-white/10 text-muted-foreground'}`}>
                              {item.status}
                            </span>
                          </td>
                          <td className="px-4 py-3">
                            <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ${statusStyles[item.financeiro_status] || 'bg-white/10 text-muted-foreground'}`}>
                              {item.financeiro_status || '—'}
                            </span>
                          </td>
                          <td className="px-4 py-3">
                            <div className="font-medium text-foreground">{paymentMethodLabels[item.forma_pagamento || ''] || item.forma_pagamento || '—'}</div>
                            <div className="text-xs text-muted-foreground">Venc. {formatDate(item.vencimento)}</div>
                          </td>
                          <td className="px-4 py-3 text-right">
                            <div className="flex items-center justify-end gap-2">
                              <a
                                href={`/financeiro/notas/${item.registro_tipo}/${item.id}`}
                                className="inline-flex items-center gap-1 rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-medium text-foreground hover:bg-white/10"
                              >
                                <Eye size={14} />
                                Ver
                              </a>
                              <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                  <button
                                    type="button"
                                    className="inline-flex items-center justify-center rounded-lg border border-white/10 bg-white/5 p-2 text-foreground hover:bg-white/10"
                                  >
                                    <MoreVertical size={14} />
                                  </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" className="w-56">
                                  <DropdownMenuItem onSelect={() => { window.location.href = `/financeiro/notas/${item.registro_tipo}/${item.id}`; }}>
                                    Verificar nota
                                  </DropdownMenuItem>
                                  <DropdownMenuItem onSelect={() => { window.open(`/api/admin/financeiro/notas-servico/${item.registro_tipo}/${item.id}/danfse`, '_blank', 'noopener,noreferrer'); }}>
                                    Abrir DANFSe espelhado
                                  </DropdownMenuItem>
                                  {item.nfse.pdf_url && (
                                    <DropdownMenuItem onSelect={() => { window.open(item.nfse.pdf_url, '_blank', 'noopener,noreferrer'); }}>
                                      Abrir PDF original
                                    </DropdownMenuItem>
                                  )}
                                  {item.nfse.xml_url && (
                                    <DropdownMenuItem onSelect={() => { window.open(item.nfse.xml_url, '_blank', 'noopener,noreferrer'); }}>
                                      Abrir XML da NFSe
                                    </DropdownMenuItem>
                                  )}
                                  {podeSincronizar && (
                                    <>
                                      <DropdownMenuSeparator />
                                      <DropdownMenuItem onSelect={() => sincronizarDocumento(item.id)} disabled={isSyncing}>
                                        {isSyncing ? 'Sincronizando NFSe...' : 'Buscar número/PDF/XML'}
                                      </DropdownMenuItem>
                                    </>
                                  )}
                                </DropdownMenuContent>
                              </DropdownMenu>
                            </div>
                          </td>
                        </tr>
                      );
                    })}
                    {!isLoading && notasPaginadas.length === 0 && (
                      <tr>
                        <td colSpan={10} className="px-4 py-10 text-center text-sm text-muted-foreground">
                          Nenhuma nota fiscal encontrada para os filtros aplicados.
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>

              <div className="flex flex-col gap-3 border-t border-white/10 px-4 py-3 text-sm text-muted-foreground md:flex-row md:items-center md:justify-between">
                <div className="flex items-center gap-4">
                  <span>Linhas por página: {notasPorPagina}</span>
                  <span>{notasFiltradas.length} registro(s)</span>
                </div>
                <div className="flex items-center gap-2">
                  <span>Página {notaPagina} de {totalPaginasNotas}</span>
                  <button
                    type="button"
                    onClick={() => setNotaPagina((current) => Math.max(1, current - 1))}
                    disabled={notaPagina <= 1}
                    className="rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-foreground hover:bg-white/10 disabled:opacity-50"
                  >
                    Anterior
                  </button>
                  <button
                    type="button"
                    onClick={() => setNotaPagina((current) => Math.min(totalPaginasNotas, current + 1))}
                    disabled={notaPagina >= totalPaginasNotas}
                    className="rounded-lg border border-white/10 bg-white/5 px-3 py-1.5 text-foreground hover:bg-white/10 disabled:opacity-50"
                  >
                    Próxima
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
