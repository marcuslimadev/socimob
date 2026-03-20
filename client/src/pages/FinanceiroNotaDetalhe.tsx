import { useEffect, useState } from 'react';
import { ArrowLeft, ExternalLink, FileText, History, RefreshCcw } from 'lucide-react';
import { toast } from 'sonner';
import { useLocation, useRoute } from 'wouter';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';

type RegistroTipo = 'commission_invoice' | 'documento_fiscal';
type ContextoEmissao = 'comissao' | 'locatario' | 'construtora' | 'proprietario';

interface FinanceiroItemDetalhe {
  id: number;
  registro_tipo: RegistroTipo;
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
    flow_status?: string | null;
    iss_rate?: number | null;
    iss_tax_amount?: number | null;
    provider_municipal_tax_number?: string | null;
    national_tax_code?: string | null;
    warnings?: string[];
  };
  created_at?: string;
}

const formatCurrency = (value: number) =>
  value.toLocaleString('pt-BR', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });

const formatDateTime = (value?: string | null) => {
  if (!value) {
    return 'N/A';
  }

  const date = new Date(value);
  if (Number.isNaN(date.getTime())) {
    return value;
  }

  return date.toLocaleString('pt-BR');
};

const statusStyles: Record<string, string> = {
  issued: 'bg-blue-500/20 text-blue-200 border border-blue-400/30',
  pending: 'bg-amber-500/20 text-amber-200 border border-amber-400/30',
  error: 'bg-red-500/20 text-red-200 border border-red-400/30',
  paid: 'bg-emerald-500/20 text-emerald-200 border border-emerald-400/30',
  cancelled: 'bg-zinc-500/20 text-zinc-200 border border-zinc-400/30',
  emitido_manual: 'bg-zinc-500/20 text-zinc-200 border border-zinc-400/30',
};

export default function FinanceiroNotaDetalhe() {
  const [, navigate] = useLocation();
  const [, params] = useRoute('/financeiro/notas/:registroTipo/:id');
  const registroTipo = params?.registroTipo as RegistroTipo | undefined;
  const id = params?.id;

  const [item, setItem] = useState<FinanceiroItemDetalhe | null>(null);
  const [loading, setLoading] = useState(true);
  const [syncing, setSyncing] = useState(false);
  const danfseUrl = item ? `/api/admin/financeiro/notas-servico/${item.registro_tipo}/${item.id}/danfse` : null;

  useEffect(() => {
    const loadItem = async () => {
      if (!id || !registroTipo) {
        setLoading(false);
        return;
      }

      setLoading(true);
      try {
        const response = await api.get(`/admin/financeiro/notas-servico/${registroTipo}/${id}`);
        setItem(response.data?.item ?? null);
      } catch (error: any) {
        const message = error?.response?.data?.message || 'Não foi possível carregar a nota fiscal';
        toast.error(message);
        setItem(null);
      } finally {
        setLoading(false);
      }
    };

    loadItem();
  }, [id, registroTipo]);

  const sincronizarDocumento = async () => {
    if (!item || item.registro_tipo !== 'documento_fiscal') {
      return;
    }

    setSyncing(true);
    try {
      const response = await api.post(`/admin/financeiro/notas-servico/${item.id}/sincronizar`);
      const syncedItem = response.data?.item as FinanceiroItemDetalhe | undefined;
      if (syncedItem) {
        setItem(syncedItem);
      }

      if (syncedItem?.nfse.pdf_url || syncedItem?.nfse.xml_url || (syncedItem?.nfse.numero && syncedItem.nfse.numero !== '0')) {
        toast.success('NFSe sincronizada com sucesso');
      } else {
        toast.message('NFSe ainda está em processamento na NFe.io');
      }
    } catch (error: any) {
      const message = error?.response?.data?.message || error?.response?.data?.error || 'Erro ao sincronizar NFSe';
      toast.error(message);
    } finally {
      setSyncing(false);
    }
  };

  const podeSincronizar =
    !!item &&
    item.registro_tipo === 'documento_fiscal' &&
    (item.status === 'issued' || item.status === 'pending') &&
    !!item.nfse.integracao_id;

  return (
    <div className="min-h-screen bg-background text-foreground flex">
      <Sidebar />

      <div className="page-shell">
        <div className="max-w-5xl mx-auto space-y-6">
          <div className="glass-panel rounded-2xl p-6 space-y-4">
            <button
              type="button"
              onClick={() => navigate('/financeiro')}
              className="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
            >
              <ArrowLeft size={16} />
              Voltar ao financeiro
            </button>

            <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
              <div className="space-y-2">
                <div className="inline-flex items-center gap-2 text-sm text-emerald-300">
                  <History size={16} />
                  Verificação direta da nota
                </div>
                <h1 className="text-2xl font-semibold text-foreground">
                  {item?.titulo || 'Nota fiscal'}
                </h1>
                {item && (
                  <p className="text-sm text-muted-foreground">
                    {item.descricao_servico}
                  </p>
                )}
              </div>

              {item && (
                <div className="flex flex-wrap gap-2">
                  <span className={`px-3 py-1 rounded-full text-xs font-semibold ${statusStyles[item.status] || 'bg-white/10 text-muted-foreground'}`}>
                    Fiscal: {item.status}
                  </span>
                  <span className={`px-3 py-1 rounded-full text-xs font-semibold ${statusStyles[item.financeiro_status] || 'bg-white/10 text-muted-foreground'}`}>
                    Financeiro: {item.financeiro_status}
                  </span>
                </div>
              )}
            </div>
          </div>

          {loading && <div className="glass-panel rounded-2xl p-6 text-muted-foreground">Carregando nota fiscal...</div>}

          {!loading && !item && (
            <div className="glass-panel rounded-2xl p-6 text-muted-foreground">
              Não foi possível localizar esta nota fiscal.
            </div>
          )}

          {!loading && item && (
            <div className="space-y-6">
              <div className="grid gap-6 lg:grid-cols-2">
                <div className="glass-panel rounded-2xl p-6 space-y-4">
                  <h2 className="text-lg font-semibold">Resumo fiscal</h2>
                  <div className="space-y-3 text-sm text-muted-foreground">
                    <div className="flex items-center justify-between gap-4">
                      <span>Valor do serviço</span>
                      <span className="text-foreground">R$ {formatCurrency(item.valor_total)}</span>
                    </div>
                    <div className="flex items-center justify-between gap-4">
                      <span>ISS</span>
                      <span className="text-foreground">R$ {formatCurrency(item.valor_iss)}</span>
                    </div>
                    <div className="flex items-center justify-between gap-4">
                      <span>Alíquota ISS</span>
                      <span className="text-foreground">{item.aliquota_iss}%</span>
                    </div>
                    <div className="flex items-center justify-between gap-4">
                      <span>NFSe</span>
                      <span className="text-foreground">{item.nfse.numero || 'N/A'}</span>
                    </div>
                    <div className="flex items-center justify-between gap-4">
                      <span>Integração</span>
                      <span className="text-foreground break-all text-right">{item.nfse.integracao_id || 'N/A'}</span>
                    </div>
                    <div className="flex items-center justify-between gap-4">
                      <span>Código de verificação</span>
                      <span className="text-foreground">{item.nfse.codigo_verificacao || 'N/A'}</span>
                    </div>
                    <div className="flex items-center justify-between gap-4">
                      <span>RPS</span>
                      <span className="text-foreground">{item.nfse.rps || 'N/A'}</span>
                    </div>
                    <div className="flex items-center justify-between gap-4">
                      <span>Emitida em</span>
                      <span className="text-foreground">{formatDateTime(item.nfse.emitida_em)}</span>
                    </div>
                    <div className="flex items-center justify-between gap-4">
                      <span>Status NFe.io</span>
                      <span className="text-foreground">{item.nfse.status_externo || 'N/A'}</span>
                    </div>
                    <div className="flex items-center justify-between gap-4">
                      <span>Fluxo NFe.io</span>
                      <span className="text-foreground text-right">{item.nfse.flow_status || 'N/A'}</span>
                    </div>
                    <div className="flex items-center justify-between gap-4">
                      <span>ISS NFe.io</span>
                      <span className="text-foreground">
                        {typeof item.nfse.iss_tax_amount === 'number' ? `R$ ${formatCurrency(item.nfse.iss_tax_amount)}` : 'N/A'}
                      </span>
                    </div>
                    <div className="flex items-center justify-between gap-4">
                      <span>Alíquota NFe.io</span>
                      <span className="text-foreground">
                        {typeof item.nfse.iss_rate === 'number' ? `${item.nfse.iss_rate}%` : 'N/A'}
                      </span>
                    </div>
                    <div className="flex items-center justify-between gap-4">
                      <span>Código nacional</span>
                      <span className="text-foreground text-right">{item.nfse.national_tax_code || 'N/A'}</span>
                    </div>
                    <div className="flex items-center justify-between gap-4">
                      <span>IM emitente NFe.io</span>
                      <span className="text-foreground text-right">{item.nfse.provider_municipal_tax_number || 'N/A'}</span>
                    </div>
                    <div className="flex items-center justify-between gap-4">
                      <span>Código do serviço</span>
                      <span className="text-foreground">{item.codigo_servico || 'N/A'}</span>
                    </div>
                    <div className="flex items-center justify-between gap-4">
                      <span>Origem do código</span>
                      <span className="text-foreground text-right">{item.codigo_servico_fonte || 'N/A'}</span>
                    </div>
                    <div className="flex items-center justify-between gap-4">
                      <span>Criada em</span>
                      <span className="text-foreground">{formatDateTime(item.created_at)}</span>
                    </div>
                  </div>
                </div>

                <div className="glass-panel rounded-2xl p-6 space-y-4">
                  <h2 className="text-lg font-semibold">Tomador e cobrança</h2>
                  <div className="space-y-3 text-sm text-muted-foreground">
                    <div className="flex items-center justify-between gap-4">
                      <span>Tomador</span>
                      <span className="text-foreground text-right">{item.tomador?.nome || 'N/A'}</span>
                    </div>
                    <div className="flex items-center justify-between gap-4">
                      <span>Documento</span>
                      <span className="text-foreground">{item.tomador?.documento || 'N/A'}</span>
                    </div>
                    <div className="flex items-center justify-between gap-4">
                      <span>E-mail</span>
                      <span className="text-foreground text-right">{item.tomador?.email || 'N/A'}</span>
                    </div>
                    <div className="flex items-center justify-between gap-4">
                      <span>Forma de pagamento</span>
                      <span className="text-foreground">{item.forma_pagamento || 'N/A'}</span>
                    </div>
                    <div className="flex items-center justify-between gap-4">
                      <span>Vencimento</span>
                      <span className="text-foreground">{item.vencimento || 'N/A'}</span>
                    </div>
                    {item.corretor && (
                      <>
                        <div className="flex items-center justify-between gap-4">
                          <span>Corretor</span>
                          <span className="text-foreground text-right">{item.corretor.name}</span>
                        </div>
                        <div className="flex items-center justify-between gap-4">
                          <span>E-mail do corretor</span>
                          <span className="text-foreground text-right">{item.corretor.email || 'N/A'}</span>
                        </div>
                      </>
                    )}
                  </div>
                </div>
              </div>

              <div className="glass-panel rounded-2xl p-6 space-y-4">
                <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                  <h2 className="text-lg font-semibold">Ações diretas</h2>
                  {podeSincronizar && (
                    <button
                      type="button"
                      onClick={sincronizarDocumento}
                      disabled={syncing}
                      className="inline-flex items-center justify-center gap-2 rounded-lg border border-emerald-400/30 bg-emerald-500/10 px-4 py-2 text-sm font-semibold text-emerald-200 transition hover:bg-emerald-500/20 disabled:opacity-60"
                    >
                      <RefreshCcw size={16} />
                      {syncing ? 'Sincronizando NFSe...' : 'Atualizar dados da nota'}
                    </button>
                  )}
                </div>

                <div className="flex flex-wrap gap-3">
                  {danfseUrl && (
                    <a
                      href={danfseUrl}
                      target="_blank"
                      rel="noreferrer"
                      className="inline-flex items-center gap-2 rounded-lg border border-emerald-400/30 bg-emerald-500/10 px-4 py-2 text-sm font-semibold text-emerald-200 transition hover:bg-emerald-500/20"
                    >
                      <FileText size={16} />
                      Abrir DANFSe espelhado
                    </a>
                  )}
                  {item.nfse.pdf_url && (
                    <a
                      href={item.nfse.pdf_url}
                      target="_blank"
                      rel="noreferrer"
                      className="inline-flex items-center gap-2 rounded-lg border border-blue-400/30 bg-blue-500/10 px-4 py-2 text-sm font-semibold text-blue-200 transition hover:bg-blue-500/20"
                    >
                      <FileText size={16} />
                      Abrir PDF original da NFSe
                    </a>
                  )}
                  {item.nfse.xml_url && (
                    <a
                      href={item.nfse.xml_url}
                      target="_blank"
                      rel="noreferrer"
                      className="inline-flex items-center gap-2 rounded-lg border border-blue-400/30 bg-blue-500/10 px-4 py-2 text-sm font-semibold text-blue-200 transition hover:bg-blue-500/20"
                    >
                      <ExternalLink size={16} />
                      Abrir XML da NFSe
                    </a>
                  )}
                </div>

                {!item.nfse.pdf_url && !item.nfse.xml_url && (
                  <p className="text-sm text-amber-300">
                    Esta nota ainda não possui PDF/XML disponível. Use a atualização para consultar novamente a NFe.io.
                  </p>
                )}

                {item.erro_detalhe && (
                  <div className="rounded-xl border border-red-400/30 bg-red-500/10 p-4 text-sm text-red-200">
                    {item.erro_detalhe}
                  </div>
                )}

                {!!item.nfse.warnings?.length && (
                  <div className="rounded-xl border border-amber-400/30 bg-amber-500/10 p-4 text-sm text-amber-100">
                    <div className="font-semibold text-amber-200">Diagnóstico fiscal</div>
                    <ul className="mt-2 space-y-1 list-disc pl-5">
                      {item.nfse.warnings.map((warning) => (
                        <li key={warning}>{warning}</li>
                      ))}
                    </ul>
                  </div>
                )}
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}