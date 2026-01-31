import { useEffect, useMemo, useRef, useState } from 'react';
import {
  Banknote,
  ClipboardCopy,
  CreditCard,
  History,
  QrCode,
  RefreshCcw,
  User,
} from 'lucide-react';
import { toast } from 'sonner';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';

interface Corretor {
  id: number;
  name: string;
  email: string;
  pix_key?: string | null;
  pix_type?: string | null;
  banco?: string | null;
  agencia?: string | null;
  conta?: string | null;
}

interface Comissao {
  id: number;
  corretor_nome: string;
  valor_venda: number;
  percentual: number;
  valor_comissao: number;
  status: string;
  nfse_numero?: string | null;
  nfse_pdf_url?: string | null;
  created_at: string;
}

interface PaymentData {
  comissao_id: number;
  qrcode: string;
  qrcode_base64?: string;
  valor_comissao: number;
}

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

const formatCurrency = (value: number) =>
  value.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const statusStyles: Record<string, string> = {
  pendente: 'bg-yellow-500/20 text-yellow-200 border border-yellow-500/30',
  processando: 'bg-blue-500/20 text-blue-200 border border-blue-500/30',
  pago: 'bg-emerald-500/20 text-emerald-200 border border-emerald-500/30',
  cancelado: 'bg-red-500/20 text-red-200 border border-red-500/30',
  approved: 'bg-emerald-500/20 text-emerald-200 border border-emerald-500/30',
};

export default function Financeiro() {
  const [corretores, setCorretores] = useState<Corretor[]>([]);
  const [comissoes, setComissoes] = useState<Comissao[]>([]);
  const [corretorId, setCorretorId] = useState('');
  const [valorVenda, setValorVenda] = useState('');
  const [percentual, setPercentual] = useState('');
  const [observacoes, setObservacoes] = useState('');
  const [statusFiltro, setStatusFiltro] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [paymentData, setPaymentData] = useState<PaymentData | null>(null);
  const [paymentStatus, setPaymentStatus] = useState<string | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const statusInterval = useRef<number | null>(null);

  const corretorSelecionado = useMemo(
    () => corretores.find((corretor) => corretor.id === Number(corretorId)),
    [corretores, corretorId]
  );

  const valorComissao = useMemo(() => {
    const valor = parseCurrency(valorVenda);
    const percentualNum = Number(percentual) || 0;
    return (valor * percentualNum) / 100;
  }, [valorVenda, percentual]);

  const comissoesFiltradas = useMemo(() => {
    if (!statusFiltro) return comissoes;
    return comissoes.filter((comissao) => comissao.status === statusFiltro);
  }, [comissoes, statusFiltro]);

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

  const carregarComissoes = async () => {
    setIsLoading(true);
    try {
      const response = await api.get('/admin/comissoes');
      if (response.data?.success) {
        setComissoes(response.data.comissoes || []);
      } else {
        setComissoes([]);
      }
    } catch (error) {
      console.error('Erro ao carregar comissões:', error);
      toast.error('Não foi possível carregar o histórico');
    } finally {
      setIsLoading(false);
    }
  };

  const limparPagamento = () => {
    if (statusInterval.current) {
      window.clearInterval(statusInterval.current);
      statusInterval.current = null;
    }
    setPaymentData(null);
    setPaymentStatus(null);
  };

  const verificarStatusPagamento = async (comissaoId: number) => {
    try {
      const response = await api.get(`/admin/comissoes/${comissaoId}/status`);
      if (response.data?.success) {
        const status = response.data.status;
        setPaymentStatus(status);
        if (status === 'approved' || status === 'pago') {
          toast.success('Pagamento confirmado');
          limparPagamento();
          carregarComissoes();
        }
      }
    } catch (error) {
      console.error('Erro ao verificar status:', error);
    }
  };

  const iniciarMonitoramento = (comissaoId: number) => {
    if (statusInterval.current) {
      window.clearInterval(statusInterval.current);
    }
    statusInterval.current = window.setInterval(() => {
      verificarStatusPagamento(comissaoId);
    }, 3000);
  };

  const handleSubmit = async (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    const valorVendaNum = parseCurrency(valorVenda);
    const percentualNum = Number(percentual) || 0;

    if (!corretorId || valorVendaNum <= 0 || percentualNum <= 0) {
      toast.error('Preencha corretor, valor e percentual');
      return;
    }

    setIsSubmitting(true);

    try {
      const response = await api.post('/admin/comissoes', {
        corretor_id: Number(corretorId),
        valor_venda: valorVendaNum,
        percentual: percentualNum,
        observacoes: observacoes || undefined,
      });

      if (response.data?.success) {
        setPaymentData({
          comissao_id: response.data.comissao_id,
          qrcode: response.data.qrcode,
          qrcode_base64: response.data.qrcode_base64,
          valor_comissao: response.data.valor_comissao,
        });
        setPaymentStatus('processando');
        iniciarMonitoramento(response.data.comissao_id);
        toast.success('Comissão criada. Pagamento PIX gerado.');
        setValorVenda('');
        setPercentual('');
        setObservacoes('');
      } else {
        toast.error(response.data?.error || 'Erro ao criar comissão');
      }
    } catch (error: any) {
      console.error('Erro ao criar comissão:', error);
      toast.error(error?.response?.data?.error || 'Erro ao criar comissão');
    } finally {
      setIsSubmitting(false);
    }
  };

  const copiarCodigoPix = async () => {
    if (!paymentData?.qrcode) return;
    try {
      await navigator.clipboard.writeText(paymentData.qrcode);
      toast.success('Código PIX copiado');
    } catch (error) {
      console.error('Erro ao copiar PIX:', error);
      toast.error('Não foi possível copiar');
    }
  };

  useEffect(() => {
    carregarCorretores();
    carregarComissoes();
    return () => limparPagamento();
  }, []);

  return (
    <div className="flex">
      <Sidebar />

      <div className="page-shell">
        <div className="max-w-6xl mx-auto space-y-8">
          <div className="page-header gap-4">
            <div>
              <h1 className="page-title mb-2 flex items-center gap-3">
                <Banknote size={32} className="text-emerald-300" />
                Financeiro
              </h1>
              <p className="page-subtitle">Controle de comissões e pagamento via PIX.</p>
            </div>
            <button
              type="button"
              onClick={carregarComissoes}
              className="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-white/10 px-4 py-2 text-sm font-semibold text-foreground transition hover:bg-white/20 sm:w-auto"
            >
              <RefreshCcw size={16} />
              Atualizar histórico
            </button>
          </div>

          <div className="grid grid-cols-1 lg:grid-cols-[2fr_1fr] gap-6">
            <div className="glass-panel p-6 rounded-2xl space-y-6">
              <div className="flex items-center gap-2 text-lg font-semibold text-foreground">
                <CreditCard size={20} />
                Nova Comissão
              </div>

              <form onSubmit={handleSubmit} className="space-y-6">
                <div className="space-y-2">
                  <label className="text-sm font-semibold text-foreground flex items-center gap-2">
                    <User size={16} />
                    Corretor
                  </label>
                  <select
                    value={corretorId}
                    onChange={(event) => setCorretorId(event.target.value)}
                    className="w-full px-4 py-2 rounded-xl bg-white/5 border border-white/10 text-foreground"
                    required
                  >
                    <option value="">Selecione um corretor</option>
                    {corretores.map((corretor) => (
                      <option key={corretor.id} value={corretor.id}>
                        {corretor.name} - {corretor.email}
                      </option>
                    ))}
                  </select>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div className="space-y-2">
                    <label className="text-sm font-semibold text-foreground">Valor da venda</label>
                    <div className="flex items-center gap-2">
                      <span className="text-sm text-muted-foreground">R$</span>
                      <input
                        type="text"
                        value={valorVenda}
                        onChange={(event) => setValorVenda(formatCurrencyInput(event.target.value))}
                        placeholder="0,00"
                        className="w-full px-4 py-2 rounded-xl bg-white/5 border border-white/10 text-foreground"
                        required
                      />
                    </div>
                  </div>
                  <div className="space-y-2">
                    <label className="text-sm font-semibold text-foreground">Percentual</label>
                    <div className="flex items-center gap-2">
                      <input
                        type="number"
                        value={percentual}
                        onChange={(event) => setPercentual(event.target.value)}
                        placeholder="0"
                        min="0"
                        step="0.1"
                        className="w-full px-4 py-2 rounded-xl bg-white/5 border border-white/10 text-foreground"
                        required
                      />
                      <span className="text-sm text-muted-foreground">%</span>
                    </div>
                  </div>
                </div>

                <div className="rounded-2xl bg-white/5 border border-white/10 p-4 text-center">
                  <p className="text-sm text-muted-foreground">Valor da comissão</p>
                  <p className="text-3xl font-bold text-emerald-300">R$ {formatCurrency(valorComissao)}</p>
                </div>

                {corretorSelecionado && (
                  <div className="rounded-2xl bg-white/5 border border-white/10 p-4 space-y-2">
                    <p className="text-sm font-semibold text-foreground">Dados bancários</p>
                    {corretorSelecionado.pix_key && (
                      <div className="text-sm text-muted-foreground">
                        PIX ({corretorSelecionado.pix_type || 'N/A'}):{' '}
                        <span className="text-foreground font-semibold">{corretorSelecionado.pix_key}</span>
                      </div>
                    )}
                    {corretorSelecionado.banco && (
                      <div className="text-sm text-muted-foreground">
                        Banco: {corretorSelecionado.banco} • Agência: {corretorSelecionado.agencia || 'N/A'} •
                        Conta: {corretorSelecionado.conta || 'N/A'}
                      </div>
                    )}
                    {!corretorSelecionado.pix_key && !corretorSelecionado.banco && (
                      <p className="text-sm text-yellow-200">Corretor sem dados bancários cadastrados.</p>
                    )}
                  </div>
                )}

                <div className="space-y-2">
                  <label className="text-sm font-semibold text-foreground">Observações</label>
                  <textarea
                    value={observacoes}
                    onChange={(event) => setObservacoes(event.target.value)}
                    rows={3}
                    className="w-full px-4 py-2 rounded-xl bg-white/5 border border-white/10 text-foreground"
                    placeholder="Observações adicionais"
                  />
                </div>

                <button
                  type="submit"
                  disabled={isSubmitting}
                  className="w-full px-6 py-3 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-500 text-white font-semibold hover:from-emerald-600 hover:to-teal-600 transition disabled:opacity-60"
                >
                  {isSubmitting ? 'Processando...' : 'Gerar pagamento PIX'}
                </button>
              </form>
            </div>

            <div className="space-y-6">
              {paymentData && (
                <div className="glass-panel p-6 rounded-2xl space-y-4">
                  <div className="flex items-center gap-2 text-lg font-semibold text-foreground">
                    <QrCode size={20} />
                    Pagamento PIX
                  </div>
                  {paymentData.qrcode_base64 && (
                    <div className="flex justify-center">
                      <img
                        src={`data:image/png;base64,${paymentData.qrcode_base64}`}
                        alt="QR Code PIX"
                        className="w-48 h-48 rounded-xl bg-white p-2"
                      />
                    </div>
                  )}
                  <div className="space-y-2">
                    <label className="text-sm font-semibold text-foreground">Código PIX (copia e cola)</label>
                    <div className="flex items-center gap-2">
                      <input
                        type="text"
                        value={paymentData.qrcode}
                        readOnly
                        className="flex-1 px-3 py-2 rounded-xl bg-white/5 border border-white/10 text-foreground text-sm"
                      />
                      <button
                        type="button"
                        onClick={copiarCodigoPix}
                        className="p-2 rounded-xl bg-white/10 hover:bg-white/20 transition"
                      >
                        <ClipboardCopy size={16} />
                      </button>
                    </div>
                  </div>
                  <div className="rounded-xl bg-white/5 border border-white/10 p-3 text-sm text-muted-foreground">
                    Status: <span className="text-foreground font-semibold">{paymentStatus || 'Aguardando'}</span>
                  </div>
                </div>
              )}

              <div className="glass-panel p-6 rounded-2xl">
                <div className="flex items-center gap-2 text-lg font-semibold text-foreground mb-4">
                  <Banknote size={20} />
                  Como funciona
                </div>
                <ol className="space-y-2 text-sm text-muted-foreground">
                  <li>Selecione o corretor e informe a venda.</li>
                  <li>Confira o cálculo automático da comissão.</li>
                  <li>Gere o PIX e acompanhe o status em tempo real.</li>
                  <li>Após confirmação, a NFSe é emitida automaticamente.</li>
                </ol>
              </div>
            </div>
          </div>

          <div className="glass-panel p-6 rounded-2xl space-y-6">
            <div className="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
              <div className="flex items-center gap-2 text-lg font-semibold text-foreground">
                <History size={20} />
                Histórico de comissões
              </div>
              <select
                value={statusFiltro}
                onChange={(event) => setStatusFiltro(event.target.value)}
                className="px-4 py-2 rounded-xl bg-white/5 border border-white/10 text-foreground"
              >
                <option value="">Todos</option>
                <option value="pendente">Pendentes</option>
                <option value="processando">Processando</option>
                <option value="pago">Pagos</option>
                <option value="cancelado">Cancelados</option>
              </select>
            </div>

            {isLoading && <p className="text-muted-foreground">Carregando histórico...</p>}

            {!isLoading && comissoesFiltradas.length === 0 && (
              <p className="text-muted-foreground">Nenhuma comissão encontrada.</p>
            )}

            <div className="space-y-4">
              {!isLoading &&
                comissoesFiltradas.map((comissao) => (
                  <div
                    key={comissao.id}
                    className="flex flex-col md:flex-row md:items-center md:justify-between gap-4 rounded-2xl bg-white/5 border border-white/10 p-4"
                  >
                    <div>
                      <p className="font-semibold text-foreground">{comissao.corretor_nome}</p>
                      <p className="text-sm text-muted-foreground">
                        Venda: R$ {formatCurrency(comissao.valor_venda)} • Comissão: R$ {formatCurrency(comissao.valor_comissao)}
                      </p>
                      <p className="text-xs text-muted-foreground">
                        {new Date(comissao.created_at).toLocaleDateString('pt-BR')}
                      </p>
                      {comissao.nfse_pdf_url && (
                        <a
                          href={comissao.nfse_pdf_url}
                          target="_blank"
                          rel="noreferrer"
                          className="text-xs text-blue-300 hover:text-blue-200"
                        >
                          Baixar NFSe
                        </a>
                      )}
                    </div>
                    <span
                      className={`px-3 py-1 rounded-full text-xs font-semibold ${
                        statusStyles[comissao.status] || 'bg-white/10 text-muted-foreground'
                      }`}
                    >
                      {comissao.status.toUpperCase()}
                    </span>
                  </div>
                ))}
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
