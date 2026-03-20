import { useEffect, useMemo, useState } from 'react';
import { ArrowUpDown, Banknote, CheckCircle2, ChevronLeft, ChevronRight, CircleAlert, Download, Eye, FileText, History, MoreVertical, RefreshCcw, Search, Trash2, User } from 'lucide-react';
import { toast } from 'sonner';
import Sidebar from '@/components/Sidebar';
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useViaCep } from '@/hooks/useViaCep';
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

const formatCepInput = (value: string) => {
  const digits = onlyDigits(value).slice(0, 8);
  if (digits.length <= 5) {
    return digits;
  }

  return `${digits.slice(0, 5)}-${digits.slice(5)}`;
};

const formatFederalTaxNumberInput = (value: string) => {
  const digits = onlyDigits(value).slice(0, 14);

  if (digits.length <= 11) {
    return digits
      .replace(/^(\d{3})(\d)/, '$1.$2')
      .replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
      .replace(/\.(\d{3})(\d)/, '.$1-$2');
  }

  return digits
    .replace(/^(\d{2})(\d)/, '$1.$2')
    .replace(/^(\d{2})\.(\d{3})(\d)/, '$1.$2.$3')
    .replace(/\.(\d{3})(\d)/, '.$1/$2')
    .replace(/(\/\d{4})(\d)/, '$1-$2');
};

const formatPhoneInput = (value: string) => {
  const digits = onlyDigits(value).slice(0, 11);

  if (digits.length <= 10) {
    return digits
      .replace(/^(\d{2})(\d)/, '($1) $2')
      .replace(/(\d{4})(\d)/, '$1-$2');
  }

  return digits
    .replace(/^(\d{2})(\d)/, '($1) $2')
    .replace(/(\d{5})(\d)/, '$1-$2');
};

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

const wizardSteps = [
  { id: 0, title: 'Contexto' },
  { id: 1, title: 'Valores' },
  { id: 2, title: 'Tomador' },
  { id: 3, title: 'Revisão' },
] as const;

const contextoHints: Record<ContextoEmissao, string> = {
  comissao: 'Emissão de comissão para corretor com NFSe e lançamento financeiro.',
  locatario: 'Fluxo de aluguel com vencimento e boleto como padrão.',
  construtora: 'Cobrança de serviços imobiliários para pessoa jurídica.',
  proprietario: 'Cobrança de corretagem imobiliária para proprietário vendedor.',
};

const FINANCEIRO_DRAFT_KEY = 'financeiro-emissao-draft';

interface FinanceiroWizardDraft {
  contextoEmissao: ContextoEmissao;
  corretorId: string;
  tipoNota: 'corretagem' | 'aluguel';
  valor: string;
  aliquotaIss: string;
  descricao: string;
  tomadorNome: string;
  tomadorDocumento: string;
  tomadorEmail: string;
  tomadorTelefone: string;
  tomadorCep: string;
  tomadorLogradouro: string;
  tomadorNumero: string;
  tomadorBairro: string;
  tomadorCidade: string;
  tomadorUf: string;
  tomadorCodigoMunicipio: string;
  pessoaTomadorId: string;
  formaPagamento: 'pix' | 'boleto';
  vencimento: string;
  wizardStep: number;
  enderecoTravado: boolean;
  updatedAt: string;
}

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
  const [deletingKeys, setDeletingKeys] = useState<string[]>([]);
  const [deleteDialog, setDeleteDialog] = useState<{ open: boolean; item: FinanceiroItem | null }>({
    open: false,
    item: null,
  });
  const [notaBusca, setNotaBusca] = useState('');
  const [notaContextoFiltro, setNotaContextoFiltro] = useState<'todos' | ContextoEmissao>('todos');
  const [notaStatusFiltro, setNotaStatusFiltro] = useState('todos');
  const [notaPeriodoFiltro, setNotaPeriodoFiltro] = useState<'12m' | '90d' | '30d' | 'todos'>('12m');
  const [notaPagina, setNotaPagina] = useState(1);
  const [notaOrdenacao, setNotaOrdenacao] = useState<{ key: NotaSortKey; direction: NotaSortDirection }>({ key: 'data', direction: 'desc' });
  const [notasPorPagina, setNotasPorPagina] = useState(10);
  const [wizardStep, setWizardStep] = useState(0);
  const [rascunhoRestaurado, setRascunhoRestaurado] = useState(false);
  const [ultimoRascunhoSalvo, setUltimoRascunhoSalvo] = useState<string | null>(null);
  const [statusCep, setStatusCep] = useState<'idle' | 'success' | 'error'>('idle');
  const [enderecoTravado, setEnderecoTravado] = useState(false);
  const { buscarCep, isLoading: isLoadingCep } = useViaCep();

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

  const checklistEmissao = useMemo(() => {
    const itens = [
      { label: 'Contexto definido', done: Boolean(contextoEmissao) },
      { label: 'Responsável selecionado', done: contextoEmissao !== 'comissao' || Boolean(corretorId) },
      { label: 'Valor informado', done: valorNumerico > 0 },
      { label: 'Tomador identificado', done: Boolean(tomadorNome && tomadorDocumento) },
      { label: 'Documento válido', done: !tomadorDocumento || isValidFederalTaxNumber(tomadorDocumento) },
    ];

    return itens;
  }, [contextoEmissao, corretorId, tomadorDocumento, tomadorNome, valorNumerico]);

  const camposPendentes = useMemo(
    () => checklistEmissao.filter((item) => !item.done).map((item) => item.label),
    [checklistEmissao],
  );

  const resumoEmissao = useMemo(() => {
    const corretorSelecionado = corretores.find((item) => String(item.id) === corretorId);

    return {
      contexto: contextoLabels[contextoEmissao],
      descricaoContexto: contextoHints[contextoEmissao],
      tipoNota: tipoNota === 'aluguel' ? 'Aluguel' : 'Corretagem',
      responsavel: corretorSelecionado ? corretorSelecionado.name : 'Não se aplica',
      pagamento: paymentMethodLabels[formaPagamento] || formaPagamento,
      total: formatCurrency(valorNumerico),
      iss: formatCurrency(valorIss),
      tomador: tomadorNome || 'Não informado',
    };
  }, [aliquotaIss, contextoEmissao, corretorId, corretores, formaPagamento, tipoNota, tomadorNome, valorIss, valorNumerico]);

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

  const getItemKey = (item: Pick<FinanceiroItem, 'registro_tipo' | 'id'>) => `${item.registro_tipo}-${item.id}`;

  const canDeleteNota = (item: FinanceiroItem) => {
    const financeiroStatus = String(item.financeiro_status || '').toLowerCase();
    const hasIssuedNumber = Boolean(item.nfse.numero && item.nfse.numero !== '0');
    const hasExternalIntegration = Boolean(item.nfse.integracao_id);

    if (item.registro_tipo === 'commission_invoice') {
      if (item.status === 'issued' || hasIssuedNumber || hasExternalIntegration) {
        return false;
      }

      if (['paid', 'pago', 'lancado', 'pendente'].includes(financeiroStatus)) {
        return false;
      }

      return true;
    }

    if (item.registro_tipo === 'documento_fiscal') {
      return item.status !== 'issued' && !hasIssuedNumber && !hasExternalIntegration;
    }

    return false;
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

        const matchesContexto = notaContextoFiltro === 'todos'
          ? true
          : item.contexto_emissao === notaContextoFiltro;

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

        return matchesSearch && matchesStatus && matchesContexto && matchesPeriodo;
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
  }, [items, notaBusca, notaContextoFiltro, notaOrdenacao, notaPeriodoFiltro, notaStatusFiltro]);

  const totalPaginasNotas = Math.max(1, Math.ceil(notasFiltradas.length / notasPorPagina));
  const notasPaginadas = notasFiltradas.slice((notaPagina - 1) * notasPorPagina, notaPagina * notasPorPagina);
  const notaInicio = notasFiltradas.length === 0 ? 0 : (notaPagina - 1) * notasPorPagina + 1;
  const notaFim = Math.min(notaPagina * notasPorPagina, notasFiltradas.length);

  useEffect(() => {
    setNotaPagina(1);
  }, [notaBusca, notaContextoFiltro, notaOrdenacao, notaPeriodoFiltro, notaStatusFiltro, notasPorPagina]);

  const alternarOrdenacao = (key: NotaSortKey) => {
    setNotaOrdenacao((current) => {
      if (current.key === key) {
        return { key, direction: current.direction === 'asc' ? 'desc' : 'asc' };
      }

      return { key, direction: key === 'data' ? 'desc' : 'asc' };
    });
  };

  const validarEtapa = (step: number) => {
    if (step === 0) {
      if (contextoEmissao === 'comissao' && !corretorId) {
        toast.error('Selecione o corretor para continuar');
        return false;
      }

      return true;
    }

    if (step === 1) {
      if (valorNumerico <= 0) {
        toast.error('Informe um valor válido para continuar');
        return false;
      }

      return true;
    }

    if (step === 2) {
      if (!tomadorNome || !tomadorDocumento) {
        toast.error('Preencha nome e CPF/CNPJ do tomador');
        return false;
      }

      if (!isValidFederalTaxNumber(tomadorDocumento)) {
        toast.error('CPF/CNPJ do tomador é inválido');
        return false;
      }
    }

    return true;
  };

  const avancarEtapa = () => {
    if (!validarEtapa(wizardStep)) {
      return;
    }

    setWizardStep((current) => Math.min(current + 1, wizardSteps.length - 1));
  };

  const voltarEtapa = () => {
    setWizardStep((current) => Math.max(current - 1, 0));
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
    setTomadorDocumento(formatFederalTaxNumberInput(documento || ''));
    setTomadorEmail(pessoa.email || '');
    setTomadorTelefone(formatPhoneInput(pessoa.celular || pessoa.telefone || ''));
    setTomadorCep(formatCepInput(pessoa.cep || ''));
    setTomadorLogradouro(pessoa.endereco || '');
    setTomadorNumero(pessoa.numero || '');
    setTomadorBairro(pessoa.bairro || '');
    setTomadorCidade(pessoa.cidade || 'Belo Horizonte');
    setTomadorUf(pessoa.estado || 'MG');
    setStatusCep('idle');
    setEnderecoTravado(false);
  };

  const preencherEnderecoPorCep = async () => {
    const cepDigits = onlyDigits(tomadorCep);
    if (cepDigits.length !== 8) {
      setStatusCep('error');
      return;
    }

    const endereco = await buscarCep(tomadorCep);
    if (!endereco) {
      setStatusCep('error');
      return;
    }

    setTomadorCep(formatCepInput(endereco.cep || tomadorCep));
    setTomadorLogradouro((current) => current || endereco.logradouro || '');
    setTomadorBairro((current) => current || endereco.bairro || '');
    setTomadorCidade(endereco.localidade || '');
    setTomadorUf(endereco.uf || '');
    setTomadorCodigoMunicipio(endereco.ibge || '');
    setStatusCep('success');
    setEnderecoTravado(true);
  };

  const limparRascunho = () => {
    if (typeof window === 'undefined') {
      return;
    }

    window.localStorage.removeItem(FINANCEIRO_DRAFT_KEY);
    setUltimoRascunhoSalvo(null);
    setRascunhoRestaurado(false);
  };

  const limparFormulario = () => {
    setContextoEmissao('comissao');
    setCorretorId('');
    setTipoNota('corretagem');
    setValor('');
    setAliquotaIss('5');
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
    setTomadorCidade('Belo Horizonte');
    setTomadorUf('MG');
    setTomadorCodigoMunicipio('3106200');
    setFormaPagamento('pix');
    setVencimento('');
    setStatusCep('idle');
    setEnderecoTravado(false);
    setWizardStep(0);
    limparRascunho();
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

  const excluirNota = async (item: FinanceiroItem) => {
    const itemKey = getItemKey(item);

    setDeletingKeys((current) => [...current, itemKey]);

    try {
      const response = await api.delete(`/admin/financeiro/notas-servico/${item.registro_tipo}/${item.id}`);

      if (response.data?.success) {
        setItems((current) => current.filter((currentItem) => getItemKey(currentItem) !== itemKey));
        setDeleteDialog({ open: false, item: null });
        toast.success(response.data?.message || 'Lançamento excluído com sucesso');
        return;
      }

      toast.error(response.data?.message || 'Não foi possível excluir o lançamento');
    } catch (error: any) {
      const apiMessage = error?.response?.data?.message;
      const apiError = error?.response?.data?.error;
      toast.error(apiMessage || apiError || 'Erro ao excluir lançamento');
    } finally {
      setDeletingKeys((current) => current.filter((key) => key !== itemKey));
    }
  };

  const solicitarExclusao = (item: FinanceiroItem) => {
    setDeleteDialog({ open: true, item });
  };

  const confirmarExclusao = async () => {
    if (!deleteDialog.item) {
      return;
    }

    await excluirNota(deleteDialog.item);
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
          documento: onlyDigits(tomadorDocumento),
          email: tomadorEmail || undefined,
          telefone: onlyDigits(tomadorTelefone) || undefined,
          endereco: {
            cep: onlyDigits(tomadorCep) || undefined,
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

  useEffect(() => {
    if (typeof window === 'undefined') {
      return;
    }

    const rawDraft = window.localStorage.getItem(FINANCEIRO_DRAFT_KEY);
    if (!rawDraft) {
      return;
    }

    try {
      const draft = JSON.parse(rawDraft) as Partial<FinanceiroWizardDraft>;
      setContextoEmissao(draft.contextoEmissao || 'comissao');
      setCorretorId(draft.corretorId || '');
      setTipoNota(draft.tipoNota || 'corretagem');
      setValor(draft.valor || '');
      setAliquotaIss(draft.aliquotaIss || '5');
      setDescricao(draft.descricao || '');
      setTomadorNome(draft.tomadorNome || '');
      setTomadorDocumento(formatFederalTaxNumberInput(draft.tomadorDocumento || ''));
      setTomadorEmail(draft.tomadorEmail || '');
      setTomadorTelefone(formatPhoneInput(draft.tomadorTelefone || ''));
      setTomadorCep(formatCepInput(draft.tomadorCep || ''));
      setTomadorLogradouro(draft.tomadorLogradouro || '');
      setTomadorNumero(draft.tomadorNumero || '');
      setTomadorBairro(draft.tomadorBairro || '');
      setTomadorCidade(draft.tomadorCidade || 'Belo Horizonte');
      setTomadorUf(draft.tomadorUf || 'MG');
      setTomadorCodigoMunicipio(draft.tomadorCodigoMunicipio || '3106200');
      setPessoaTomadorId(draft.pessoaTomadorId || '');
      setFormaPagamento(draft.formaPagamento || 'pix');
      setVencimento(draft.vencimento || '');
      setEnderecoTravado(Boolean(draft.enderecoTravado));
      setStatusCep(draft.enderecoTravado ? 'success' : 'idle');
      setWizardStep(typeof draft.wizardStep === 'number' ? Math.max(0, Math.min(draft.wizardStep, wizardSteps.length - 1)) : 0);
      setUltimoRascunhoSalvo(draft.updatedAt || null);
      setRascunhoRestaurado(true);
    } catch {
      limparRascunho();
    }
  }, []);

  useEffect(() => {
    if (typeof window === 'undefined') {
      return;
    }

    const updatedAt = new Date().toISOString();

    const draft: FinanceiroWizardDraft = {
      contextoEmissao,
      corretorId,
      tipoNota,
      valor,
      aliquotaIss,
      descricao,
      tomadorNome,
      tomadorDocumento,
      tomadorEmail,
      tomadorTelefone,
      tomadorCep,
      tomadorLogradouro,
      tomadorNumero,
      tomadorBairro,
      tomadorCidade,
      tomadorUf,
      tomadorCodigoMunicipio,
      pessoaTomadorId,
      formaPagamento,
      vencimento,
      wizardStep,
      enderecoTravado,
      updatedAt,
    };

    const hasContent = Object.entries(draft).some(([key, value]) => {
      if (key === 'contextoEmissao') return value !== 'comissao';
      if (key === 'tipoNota') return value !== 'corretagem';
      if (key === 'aliquotaIss') return value !== '5';
      if (key === 'formaPagamento') return value !== 'pix';
      if (key === 'wizardStep') return value !== 0;
      if (key === 'enderecoTravado') return Boolean(value);
      return typeof value === 'string' ? value.trim() !== '' : Boolean(value);
    });

    if (!hasContent) {
      limparRascunho();
      return;
    }

    window.localStorage.setItem(FINANCEIRO_DRAFT_KEY, JSON.stringify(draft));
    setUltimoRascunhoSalvo(updatedAt);
  }, [aliquotaIss, contextoEmissao, corretorId, descricao, enderecoTravado, formaPagamento, pessoaTomadorId, tipoNota, tomadorBairro, tomadorCep, tomadorCidade, tomadorCodigoMunicipio, tomadorDocumento, tomadorEmail, tomadorLogradouro, tomadorNome, tomadorNumero, tomadorTelefone, tomadorUf, valor, vencimento, wizardStep]);

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

          <div className="grid grid-cols-1 lg:grid-cols-[minmax(0,1.7fr)_360px] gap-6">
            <div className="glass-panel p-6 rounded-2xl space-y-6">
              <div className="flex items-center gap-2 text-lg font-semibold text-foreground">
                <FileText size={20} />
                Wizard de emissão
              </div>

              <div className="flex flex-col gap-3 rounded-2xl border border-white/10 bg-white/5 p-4">
                <div className="flex items-center justify-between text-sm text-muted-foreground">
                  <span>Etapa {wizardStep + 1} de {wizardSteps.length}</span>
                  <span>{wizardSteps[wizardStep].title}</span>
                </div>
                <div className="h-2 rounded-full bg-black/20">
                  <div className="h-2 rounded-full bg-emerald-400 transition-all" style={{ width: `${((wizardStep + 1) / wizardSteps.length) * 100}%` }} />
                </div>
                <div className="grid grid-cols-2 gap-2 md:grid-cols-4">
                  {wizardSteps.map((step) => (
                    <button
                      key={step.id}
                      type="button"
                      onClick={() => {
                        if (step.id <= wizardStep || validarEtapa(wizardStep)) {
                          setWizardStep(step.id);
                        }
                      }}
                      className={`rounded-xl border px-3 py-2 text-left text-sm transition ${step.id === wizardStep ? 'border-emerald-400/50 bg-emerald-500/10 text-foreground' : 'border-white/10 bg-transparent text-muted-foreground hover:bg-white/5'}`}
                    >
                      {step.title}
                    </button>
                  ))}
                </div>
                <div className="flex items-center justify-between text-xs text-muted-foreground">
                  <span>
                    {rascunhoRestaurado ? 'Rascunho restaurado automaticamente' : 'Rascunho salvo automaticamente'}
                    {ultimoRascunhoSalvo ? ` · último rascunho salvo às ${new Date(ultimoRascunhoSalvo).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })}` : ''}
                  </span>
                  <button type="button" onClick={limparRascunho} className="hover:text-foreground">
                    Limpar rascunho local
                  </button>
                </div>
              </div>

              <form onSubmit={handleSubmit} className="space-y-6">
                {wizardStep === 0 && (
                  <div className="space-y-5">
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
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
                            } else {
                              setTipoNota('corretagem');
                              setFormaPagamento('pix');
                            }
                          }}
                          className="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-foreground"
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
                          className="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-foreground"
                          disabled={contextoEmissao !== 'comissao'}
                        >
                          <option value="corretagem">Corretagem</option>
                          <option value="aluguel">Aluguel</option>
                        </select>
                      </div>
                    </div>

                    {contextoEmissao === 'comissao' && (
                      <div className="space-y-2">
                        <label className="flex items-center gap-2 text-sm font-semibold text-foreground">
                          <User size={16} /> Corretor
                        </label>
                        <select
                          value={corretorId}
                          onChange={(event) => setCorretorId(event.target.value)}
                          className="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-foreground"
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

                    <div className="rounded-2xl border border-white/10 bg-black/15 p-4 text-sm text-muted-foreground">
                      {contextoHints[contextoEmissao]}
                    </div>
                  </div>
                )}

                {wizardStep === 1 && (
                  <div className="space-y-5">
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                      <div className="space-y-2 md:col-span-1">
                        <label className="text-sm font-semibold text-foreground">Valor</label>
                        <div className="flex items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-4 py-2.5">
                          <span className="text-sm text-muted-foreground">R$</span>
                          <input
                            type="text"
                            value={valor}
                            onChange={(event) => setValor(formatCurrencyInput(event.target.value))}
                            placeholder="0,00"
                            className="w-full bg-transparent text-foreground outline-none"
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
                          className="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-foreground"
                        />
                      </div>
                      <div className="space-y-2">
                        <label className="text-sm font-semibold text-foreground">Vencimento</label>
                        <input
                          type="date"
                          value={vencimento}
                          onChange={(event) => setVencimento(event.target.value)}
                          className="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-foreground"
                        />
                      </div>
                    </div>

                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                      <div className="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p className="text-xs uppercase tracking-[0.18em] text-muted-foreground">Base</p>
                        <p className="mt-2 text-2xl font-semibold text-foreground">R$ {formatCurrency(valorNumerico)}</p>
                      </div>
                      <div className="rounded-2xl border border-white/10 bg-white/5 p-4">
                        <p className="text-xs uppercase tracking-[0.18em] text-muted-foreground">ISS estimado</p>
                        <p className="mt-2 text-2xl font-semibold text-foreground">R$ {formatCurrency(valorIss)}</p>
                      </div>
                    </div>

                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                      <div className="space-y-2">
                        <label className="text-sm font-semibold text-foreground">Forma de pagamento</label>
                        <select
                          value={formaPagamento}
                          onChange={(event) => setFormaPagamento(event.target.value as 'pix' | 'boleto')}
                          className="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-foreground"
                        >
                          <option value="pix">PIX</option>
                          <option value="boleto">Boleto</option>
                        </select>
                      </div>
                      <div className="space-y-2">
                        <label className="text-sm font-semibold text-foreground">Descrição de serviço</label>
                        <textarea
                          value={descricao}
                          onChange={(event) => setDescricao(event.target.value)}
                          rows={3}
                          className="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-foreground"
                          placeholder="Se vazio, o backend gera automaticamente"
                        />
                      </div>
                    </div>
                  </div>
                )}

                {wizardStep === 2 && (
                  <div className="space-y-5">
                    <div className="space-y-2">
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
                        className="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-foreground"
                      >
                        <option value="">Preencher manualmente</option>
                        {tomadoresDisponiveis.map((pessoa) => (
                          <option key={pessoa.id} value={pessoa.id}>
                            {pessoa.tipo === 'juridica' ? `${pessoa.razao_social || pessoa.nome} (PJ)` : `${pessoa.nome} (PF)`}
                          </option>
                        ))}
                      </select>
                    </div>

                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                      <div className="space-y-2">
                        <label className="text-sm font-semibold text-foreground">Nome</label>
                        <input
                          type="text"
                          value={tomadorNome}
                          onChange={(event) => setTomadorNome(event.target.value)}
                          className="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-foreground"
                          required
                        />
                      </div>
                      <div className="space-y-2">
                        <label className="text-sm font-semibold text-foreground">CPF/CNPJ</label>
                        <input
                          type="text"
                          value={tomadorDocumento}
                          onChange={(event) => setTomadorDocumento(formatFederalTaxNumberInput(event.target.value))}
                          className="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-foreground"
                          required
                        />
                      </div>
                      <div className="space-y-2">
                        <label className="text-sm font-semibold text-foreground">Email</label>
                        <input
                          type="email"
                          value={tomadorEmail}
                          onChange={(event) => setTomadorEmail(event.target.value)}
                          className="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-foreground"
                        />
                      </div>
                      <div className="space-y-2">
                        <label className="text-sm font-semibold text-foreground">Telefone</label>
                        <input
                          type="text"
                          value={tomadorTelefone}
                          onChange={(event) => setTomadorTelefone(formatPhoneInput(event.target.value))}
                          className="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-foreground"
                        />
                      </div>
                    </div>

                    <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                      <div className="space-y-2">
                        <label className="text-sm font-semibold text-foreground">CEP</label>
                        <div className="flex gap-2">
                          <input
                            type="text"
                            value={tomadorCep}
                            onChange={(event) => {
                              setTomadorCep(formatCepInput(event.target.value));
                              setStatusCep('idle');
                              setEnderecoTravado(false);
                            }}
                            onBlur={preencherEnderecoPorCep}
                            className="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-foreground"
                          />
                          <button
                            type="button"
                            onClick={preencherEnderecoPorCep}
                            disabled={isLoadingCep}
                            className="rounded-xl border border-white/10 bg-white/5 px-3 py-2.5 text-xs font-medium text-foreground hover:bg-white/10 disabled:opacity-50"
                          >
                            {isLoadingCep ? 'Buscando' : 'CEP'}
                          </button>
                        </div>
                        <div className="flex items-center justify-between text-xs">
                          <span className={statusCep === 'success' ? 'text-emerald-300' : statusCep === 'error' ? 'text-red-300' : 'text-muted-foreground'}>
                            {statusCep === 'success' ? 'CEP encontrado' : statusCep === 'error' ? 'CEP não encontrado' : 'Informe um CEP para auto-preenchimento'}
                          </span>
                          {enderecoTravado && (
                            <button
                              type="button"
                              onClick={() => setEnderecoTravado(false)}
                              className="text-muted-foreground hover:text-foreground"
                            >
                              Editar endereço
                            </button>
                          )}
                        </div>
                      </div>
                      <div className="space-y-2 md:col-span-2">
                        <label className="text-sm font-semibold text-foreground">Logradouro</label>
                        <input type="text" value={tomadorLogradouro} onChange={(event) => setTomadorLogradouro(event.target.value)} disabled={enderecoTravado} className="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-foreground disabled:opacity-60" />
                      </div>
                      <div className="space-y-2">
                        <label className="text-sm font-semibold text-foreground">Número</label>
                        <input type="text" value={tomadorNumero} onChange={(event) => setTomadorNumero(event.target.value)} className="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-foreground" />
                      </div>
                    </div>

                    <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                      <div className="space-y-2">
                        <label className="text-sm font-semibold text-foreground">Bairro</label>
                        <input type="text" value={tomadorBairro} onChange={(event) => setTomadorBairro(event.target.value)} disabled={enderecoTravado} className="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-foreground disabled:opacity-60" />
                      </div>
                      <div className="space-y-2">
                        <label className="text-sm font-semibold text-foreground">Cidade</label>
                        <input type="text" value={tomadorCidade} onChange={(event) => setTomadorCidade(event.target.value)} disabled={enderecoTravado} className="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-foreground disabled:opacity-60" />
                      </div>
                      <div className="space-y-2">
                        <label className="text-sm font-semibold text-foreground">UF</label>
                        <input type="text" value={tomadorUf} onChange={(event) => setTomadorUf(event.target.value)} disabled={enderecoTravado} className="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-foreground disabled:opacity-60" />
                      </div>
                      <div className="space-y-2">
                        <label className="text-sm font-semibold text-foreground">Código IBGE</label>
                        <input type="text" value={tomadorCodigoMunicipio} onChange={(event) => setTomadorCodigoMunicipio(event.target.value)} disabled={enderecoTravado} className="w-full rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-foreground disabled:opacity-60" />
                      </div>
                    </div>
                  </div>
                )}

                {wizardStep === 3 && (
                  <div className="space-y-5">
                    <div className="rounded-2xl border border-white/10 bg-white/5 p-5">
                      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                          <p className="text-xs uppercase tracking-[0.18em] text-muted-foreground">Contexto</p>
                          <p className="mt-1 font-medium text-foreground">{resumoEmissao.contexto}</p>
                          <p className="mt-1 text-sm text-muted-foreground">{resumoEmissao.descricaoContexto}</p>
                        </div>
                        <div>
                          <p className="text-xs uppercase tracking-[0.18em] text-muted-foreground">Tomador</p>
                          <p className="mt-1 font-medium text-foreground">{resumoEmissao.tomador}</p>
                          <p className="mt-1 text-sm text-muted-foreground">{tomadorDocumento || 'Documento pendente'}</p>
                        </div>
                        <div>
                          <p className="text-xs uppercase tracking-[0.18em] text-muted-foreground">Tipo / pagamento</p>
                          <p className="mt-1 font-medium text-foreground">{resumoEmissao.tipoNota} · {resumoEmissao.pagamento}</p>
                        </div>
                        <div>
                          <p className="text-xs uppercase tracking-[0.18em] text-muted-foreground">Base / ISS</p>
                          <p className="mt-1 font-medium text-foreground">R$ {resumoEmissao.total} · R$ {resumoEmissao.iss}</p>
                        </div>
                      </div>
                    </div>

                    {camposPendentes.length > 0 && (
                      <div className="rounded-2xl border border-amber-500/20 bg-amber-500/10 p-4 text-sm text-amber-100">
                        <div className="mb-2 flex items-center gap-2 font-medium">
                          <CircleAlert size={16} /> Pendências antes da emissão
                        </div>
                        <ul className="space-y-1">
                          {camposPendentes.map((campo) => (
                            <li key={campo}>{campo}</li>
                          ))}
                        </ul>
                      </div>
                    )}
                  </div>
                )}

                <div className="flex flex-col gap-3 border-t border-white/10 pt-4 md:flex-row md:items-center md:justify-between">
                  <button
                    type="button"
                    onClick={voltarEtapa}
                    disabled={wizardStep === 0}
                    className="inline-flex items-center justify-center gap-2 rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm font-medium text-foreground hover:bg-white/10 disabled:opacity-50"
                  >
                    <ChevronLeft size={16} /> Voltar
                  </button>

                  <div className="flex flex-col-reverse gap-3 sm:flex-row">
                    <button
                      type="button"
                      onClick={limparFormulario}
                      className="rounded-xl border border-white/10 bg-transparent px-4 py-2.5 text-sm font-medium text-muted-foreground hover:bg-white/5"
                    >
                      Limpar
                    </button>

                    {wizardStep < wizardSteps.length - 1 ? (
                      <button
                        type="button"
                        onClick={avancarEtapa}
                        className="inline-flex items-center justify-center gap-2 rounded-xl bg-white text-slate-950 px-4 py-2.5 text-sm font-semibold hover:bg-slate-100"
                      >
                        Próxima etapa <ChevronRight size={16} />
                      </button>
                    ) : (
                      <button
                        type="submit"
                        disabled={isSubmitting || camposPendentes.length > 0}
                        className="inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 px-5 py-2.5 text-sm font-semibold text-white transition hover:from-emerald-600 hover:to-teal-600 disabled:opacity-60"
                      >
                        <CheckCircle2 size={16} />
                        {isSubmitting ? 'Emitindo...' : 'Emitir nota'}
                      </button>
                    )}
                  </div>
                </div>
              </form>
            </div>

            <div className="space-y-6">
              <div className="glass-panel p-6 rounded-2xl">
                <div className="mb-4 flex items-center gap-2 text-lg font-semibold text-foreground">
                  <Banknote size={20} />
                  Resumo da emissão
                </div>
                <div className="space-y-4 text-sm">
                  <div className="rounded-2xl border border-white/10 bg-white/5 p-4">
                    <p className="text-xs uppercase tracking-[0.18em] text-muted-foreground">Pronto para emitir</p>
                    <p className="mt-2 text-3xl font-semibold text-foreground">{Math.round((checklistEmissao.filter((item) => item.done).length / checklistEmissao.length) * 100)}%</p>
                  </div>
                  <div className="space-y-2">
                    {checklistEmissao.map((item) => (
                      <div key={item.label} className="flex items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-muted-foreground">
                        {item.done ? <CheckCircle2 size={16} className="text-emerald-300" /> : <CircleAlert size={16} className="text-amber-300" />}
                        <span className={item.done ? 'text-foreground' : ''}>{item.label}</span>
                      </div>
                    ))}
                  </div>
                  <div className="rounded-2xl border border-white/10 bg-black/15 p-4 text-muted-foreground">
                    <p className="font-medium text-foreground">{resumoEmissao.contexto}</p>
                    <p className="mt-1">{resumoEmissao.descricaoContexto}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div className="glass-panel p-6 rounded-2xl space-y-5">
            <div className="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
              <div>
                <div className="flex items-center gap-2 text-lg font-semibold text-foreground">
                  <History size={20} />
                  Histórico unificado (aluguel + comissão)
                </div>
                <p className="mt-1 text-sm text-muted-foreground">
                  Visual limpo, busca rápida e ações diretas por lançamento.
                </p>
              </div>
              <div className="text-sm text-muted-foreground">{notasFiltradas.length} registro(s)</div>
            </div>

            <div className="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
              <div className="flex flex-1 flex-col gap-3 md:flex-row">
                <label className="relative flex-1">
                  <Search size={16} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
                  <input
                    value={notaBusca}
                    onChange={(event) => setNotaBusca(event.target.value)}
                    placeholder="Buscar por número, contexto, tomador, descrição ou código"
                    className="w-full rounded-xl border border-white/10 bg-transparent py-2.5 pl-10 pr-4 text-sm text-foreground placeholder:text-muted-foreground"
                  />
                </label>
                <select
                  value={notaContextoFiltro}
                  onChange={(event) => setNotaContextoFiltro(event.target.value as 'todos' | ContextoEmissao)}
                  className="rounded-xl border border-white/10 bg-transparent px-4 py-2.5 text-sm text-foreground"
                >
                  <option value="todos">Todos os contextos</option>
                  <option value="comissao">Comissão</option>
                  <option value="locatario">Locatário</option>
                  <option value="construtora">Construtora</option>
                  <option value="proprietario">Proprietário vendedor</option>
                </select>
                <select
                  value={notaStatusFiltro}
                  onChange={(event) => setNotaStatusFiltro(event.target.value)}
                  className="rounded-xl border border-white/10 bg-transparent px-4 py-2.5 text-sm text-foreground"
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
                  className="rounded-xl border border-white/10 bg-transparent px-4 py-2.5 text-sm text-foreground"
                >
                  <option value="12m">Últimos 12 meses</option>
                  <option value="90d">Últimos 90 dias</option>
                  <option value="30d">Últimos 30 dias</option>
                  <option value="todos">Todo o período</option>
                </select>
                <select
                  value={String(notasPorPagina)}
                  onChange={(event) => setNotasPorPagina(Number(event.target.value))}
                  className="rounded-xl border border-white/10 bg-transparent px-4 py-2.5 text-sm text-foreground"
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
                    setNotaContextoFiltro('todos');
                    setNotaStatusFiltro('todos');
                    setNotaPeriodoFiltro('12m');
                  }}
                  className="rounded-xl border border-white/10 bg-transparent px-4 py-2.5 text-sm font-medium text-foreground hover:bg-white/5"
                >
                  Limpar
                </button>
                <button
                  type="button"
                  onClick={exportarNotasCsv}
                  className="inline-flex items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-4 py-2.5 text-sm font-semibold text-foreground hover:bg-white/10"
                >
                  <Download size={16} />
                  Exportar
                </button>
              </div>
            </div>

            {isLoading && <p className="text-muted-foreground">Carregando histórico...</p>}

            {!isLoading && notasFiltradas.length === 0 && (
              <p className="text-muted-foreground">Nenhum lançamento encontrado.</p>
            )}

            <div className="overflow-hidden rounded-2xl border border-white/10 bg-transparent">
              <div className="overflow-x-auto">
                <table className="w-full min-w-[980px] text-sm">
                  <thead className="border-b border-white/10 text-left text-xs text-muted-foreground">
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
                      <th className="px-4 py-3 text-right">Ações</th>
                    </tr>
                  </thead>
                  <tbody>
                    {!isLoading && notasPaginadas.map((item) => {
                      const isSyncing = syncingIds.includes(item.id);
                      const isDeleting = deletingKeys.includes(getItemKey(item));
                      const canDeleteItem = canDeleteNota(item);
                      const podeSincronizar = podeSincronizarDocumento(item);

                      return (
                        <tr key={`${item.registro_tipo}-${item.id}`} className="border-b border-white/10 text-foreground/90 last:border-b-0">
                          <td className="px-4 py-3">
                            <div className="font-medium text-foreground">{item.nfse.numero || 'Sem NF'}</div>
                            <div className="text-xs text-muted-foreground">{registroTipoLabels[item.registro_tipo]} · {formatDate(getNotaDataReferencia(item))}</div>
                          </td>
                          <td className="px-4 py-3 text-muted-foreground">{formatDate(getNotaDataReferencia(item))}</td>
                          <td className="px-4 py-3">
                            <div className="font-medium text-foreground">{contextoLabels[item.contexto_emissao]}</div>
                            <div className="text-xs text-muted-foreground">{item.tipo_nota} · {paymentMethodLabels[item.forma_pagamento || ''] || item.forma_pagamento || '—'}</div>
                          </td>
                          <td className="px-4 py-3">
                            <div className="font-medium text-foreground">{item.tomador?.nome || 'Tomador não informado'}</div>
                            <div className="text-xs text-muted-foreground">{item.tomador?.documento || item.titulo || 'Documento não informado'}</div>
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
                          <td className="px-4 py-3 text-right">
                            <div className="flex items-center justify-end gap-2">
                              <a
                                href={`/financeiro/notas/${item.registro_tipo}/${item.id}`}
                                className="inline-flex items-center gap-1 rounded-lg border border-white/10 px-3 py-1.5 text-xs font-medium text-foreground hover:bg-white/5"
                              >
                                <Eye size={14} />
                                Ver
                              </a>
                              <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                  <button
                                    type="button"
                                    disabled={isDeleting}
                                    className="inline-flex items-center justify-center rounded-lg border border-white/10 p-2 text-foreground hover:bg-white/5"
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
                                  {canDeleteItem && (
                                    <>
                                      <DropdownMenuSeparator />
                                      <DropdownMenuItem
                                        onSelect={() => solicitarExclusao(item)}
                                        disabled={isDeleting}
                                        className="text-red-300 focus:text-red-200"
                                      >
                                        <Trash2 size={14} className="mr-2" />
                                        {isDeleting ? 'Excluindo...' : 'Excluir lançamento'}
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
                        <td colSpan={8} className="px-4 py-10 text-center text-sm text-muted-foreground">
                          Nenhuma nota fiscal encontrada para os filtros aplicados.
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>

              <div className="flex flex-col gap-3 border-t border-white/10 px-4 py-3 text-sm text-muted-foreground md:flex-row md:items-center md:justify-between">
                <div className="flex items-center gap-4">
                  <span>{notaInicio}-{notaFim} de {notasFiltradas.length}</span>
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

      <AlertDialog
        open={deleteDialog.open}
        onOpenChange={(open) => setDeleteDialog((current) => ({ open, item: open ? current.item : null }))}
      >
        <AlertDialogContent className="border border-white/10 bg-[#0f0f0f]">
          <AlertDialogHeader>
            <AlertDialogTitle>Excluir lançamento</AlertDialogTitle>
            <AlertDialogDescription>
              {deleteDialog.item ? (
                <>
                  Confirma a exclusão de <strong>{deleteDialog.item.nfse.numero ? `NF ${deleteDialog.item.nfse.numero}` : deleteDialog.item.titulo || 'este lançamento'}</strong>?
                  Esta ação não pode ser desfeita.
                </>
              ) : (
                'Esta ação não pode ser desfeita.'
              )}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel className="border-white/20 hover:bg-white/10">Cancelar</AlertDialogCancel>
            <AlertDialogAction
              onClick={confirmarExclusao}
              className="bg-red-600 hover:bg-red-700"
              disabled={deleteDialog.item ? deletingKeys.includes(getItemKey(deleteDialog.item)) : false}
            >
              {deleteDialog.item && deletingKeys.includes(getItemKey(deleteDialog.item)) ? 'Excluindo...' : 'Excluir'}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
