import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { api } from '@/lib/api';

interface ContratoDetalhes {
  id: number;
  numero_contrato?: string;
  status: string;
  inicio?: string;
  fim?: string;
  dia_vencimento?: number;
  valor_aluguel?: number;
  tipo_garantia?: string;
  valor_garantia?: number;
  comissao_administracao_percentual?: number;
  indice_reajuste?: string;
  periodicidade_reajuste?: number;
  observacoes?: string;
  rescindido_em?: string;
  motivo_rescisao?: string;
  multa_rescisao_calculada?: number;
  renovado_ate?: string;
  locador?: { id: number; nome: string; email?: string; telefone?: string };
  locatario?: { id: number; nome: string; email?: string; telefone?: string };
  imovel?: { id: number; titulo?: string; codigo?: string; endereco?: string };
  fiadores?: Array<{ id: number; pessoa: { nome: string }; tipo?: string }>;
  reajustes?: Array<{ id: number; competencia: string; indice: string; percentual_aplicado: number; valor_anterior: number; novo_valor: number; created_at: string }>;
  repasses?: Array<{ id: number; competencia: string; status: string; valor_repasse: number; data_pagamento?: string }>;
  documentos?: Array<{ id: number; tipo: string; status: string; url_documento?: string; created_at: string }>;
}

interface Props {
  contratoId: number;
  onClose: () => void;
  onEncerrar: () => void;
  onRenovar: () => void;
  onReajuste: () => void;
}

const tipoGarantiaLabel: Record<string, string> = {
  caucao: 'Caução',
  fiador: 'Fiador',
  seguro_fianca: 'Seguro Fiança',
  sem_garantia: 'Sem garantia',
};

export default function ContratoDetalheModal({ contratoId, onClose, onEncerrar, onRenovar, onReajuste }: Props) {
  const [contrato, setContrato] = useState<ContratoDetalhes | null>(null);
  const [loading, setLoading] = useState(true);
  const [gerandoPdf, setGerandoPdf] = useState(false);
  const [addFiador, setAddFiador] = useState({ open: false, pessoa_id: '' });

  const formatMoney = (v?: number) => Number(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const formatDate = (v?: string) => {
    if (!v) return '-';
    const [y, m, d] = v.slice(0, 10).split('-');
    return `${d}/${m}/${y}`;
  };

  const loadContrato = async () => {
    setLoading(true);
    try {
      const { data } = await api.get(`/admin/financeiro/contratos/${contratoId}`);
      setContrato(data);
    } catch {
      toast.error('Erro ao carregar detalhes do contrato.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { loadContrato(); }, [contratoId]);

  const handleGerarPdf = async (tipo: string) => {
    setGerandoPdf(true);
    try {
      const { data } = await api.post(`/admin/financeiro/contratos/${contratoId}/documentos/gerar-pdf`, { tipo });
      toast.success('PDF gerado com sucesso.');
      if (data.url_documento) window.open(data.url_documento, '_blank');
    } catch (err: any) {
      toast.error(err?.response?.data?.message || 'Erro ao gerar PDF.');
    } finally {
      setGerandoPdf(false);
    }
  };

  const handleRemoverFiador = async (fiadorId: number) => {
    if (!confirm('Remover este fiador?')) return;
    try {
      await api.delete(`/admin/financeiro/contratos/${contratoId}/fiadores/${fiadorId}`);
      toast.success('Fiador removido.');
      loadContrato();
    } catch {
      toast.error('Erro ao remover fiador.');
    }
  };

  if (loading) return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div className="glass-panel rounded-2xl p-8 text-center">
        <p className="text-sm text-muted-foreground">Carregando...</p>
      </div>
    </div>
  );

  if (!contrato) return null;
  const isRescindido = contrato.status === 'rescindido' || !!contrato.rescindido_em;

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div className="glass-panel rounded-2xl p-6 w-full max-w-3xl mx-4 max-h-[90vh] overflow-y-auto">
        <div className="flex items-center justify-between mb-4">
          <div>
            <h2 className="text-lg font-semibold">
              Contrato {contrato.numero_contrato || `#${contrato.id}`}
            </h2>
            <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium mt-0.5 ${
              isRescindido ? 'bg-red-100 text-red-700' :
              contrato.status === 'ativo' ? 'bg-emerald-100 text-emerald-700' :
              'bg-muted text-foreground'
            }`}>
              {isRescindido ? 'Rescindido' : contrato.status}
            </span>
          </div>
          <button type="button" onClick={onClose} className="text-muted-foreground hover:text-foreground text-xl leading-none">&times;</button>
        </div>

        {/* Ações */}
        {!isRescindido && (
          <div className="flex flex-wrap gap-2 mb-5 pb-4 border-b border-border">
            <button
              type="button"
              onClick={onReajuste}
              className="px-3 py-1.5 rounded-lg border border-border hover:bg-accent text-sm"
            >
              Reajustar aluguel
            </button>
            <button
              type="button"
              onClick={onRenovar}
              className="px-3 py-1.5 rounded-lg border border-primary text-primary hover:bg-primary/5 text-sm"
            >
              Renovar contrato
            </button>
            <button
              type="button"
              onClick={() => handleGerarPdf('contrato')}
              disabled={gerandoPdf}
              className="px-3 py-1.5 rounded-lg border border-border hover:bg-accent text-sm disabled:opacity-60"
            >
              {gerandoPdf ? '...' : 'Gerar PDF'}
            </button>
            <button
              type="button"
              onClick={onEncerrar}
              className="px-3 py-1.5 rounded-lg bg-red-100 text-red-700 hover:bg-red-200 text-sm"
            >
              Rescindir
            </button>
          </div>
        )}

        {/* Partes */}
        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4 text-sm">
          <div className="p-3 bg-muted/50 rounded-xl">
            <p className="text-xs text-muted-foreground mb-1">Locador (Proprietário)</p>
            <p className="font-semibold">{contrato.locador?.nome || '-'}</p>
            {contrato.locador?.email && <p className="text-muted-foreground">{contrato.locador.email}</p>}
            {contrato.locador?.telefone && <p className="text-muted-foreground">{contrato.locador.telefone}</p>}
          </div>
          <div className="p-3 bg-muted/50 rounded-xl">
            <p className="text-xs text-muted-foreground mb-1">Locatário (Inquilino)</p>
            <p className="font-semibold">{contrato.locatario?.nome || '-'}</p>
            {contrato.locatario?.email && <p className="text-muted-foreground">{contrato.locatario.email}</p>}
            {contrato.locatario?.telefone && <p className="text-muted-foreground">{contrato.locatario.telefone}</p>}
          </div>
        </div>

        {/* Imóvel */}
        <div className="p-3 bg-muted/50 rounded-xl mb-4 text-sm">
          <p className="text-xs text-muted-foreground mb-1">Imóvel</p>
          <p className="font-semibold">{contrato.imovel?.titulo || contrato.imovel?.codigo || '-'}</p>
          {contrato.imovel?.endereco && <p className="text-muted-foreground">{contrato.imovel.endereco}</p>}
        </div>

        {/* Condições financeiras */}
        <h3 className="text-sm font-semibold mb-2">Condições financeiras</h3>
        <div className="grid grid-cols-2 md:grid-cols-3 gap-3 mb-4 text-sm">
          <div className="p-3 bg-muted/50 rounded-xl">
            <p className="text-xs text-muted-foreground">Vigência</p>
            <p>{formatDate(contrato.inicio)} → {formatDate(contrato.fim)}</p>
          </div>
          <div className="p-3 bg-muted/50 rounded-xl">
            <p className="text-xs text-muted-foreground">Vencimento</p>
            <p>Dia {contrato.dia_vencimento ?? '-'}</p>
          </div>
          <div className="p-3 bg-muted/50 rounded-xl">
            <p className="text-xs text-muted-foreground">Aluguel</p>
            <p className="font-semibold">R$ {formatMoney(contrato.valor_aluguel)}</p>
          </div>
          <div className="p-3 bg-muted/50 rounded-xl">
            <p className="text-xs text-muted-foreground">Garantia</p>
            <p>{tipoGarantiaLabel[contrato.tipo_garantia || ''] || contrato.tipo_garantia || '-'}</p>
          </div>
          {contrato.valor_garantia ? (
            <div className="p-3 bg-muted/50 rounded-xl">
              <p className="text-xs text-muted-foreground">Valor garantia</p>
              <p>R$ {formatMoney(contrato.valor_garantia)}</p>
            </div>
          ) : null}
          <div className="p-3 bg-muted/50 rounded-xl">
            <p className="text-xs text-muted-foreground">Comissão adm.</p>
            <p>{contrato.comissao_administracao_percentual ?? '-'}{contrato.comissao_administracao_percentual != null ? '%' : ''}</p>
          </div>
          {contrato.indice_reajuste && (
            <div className="p-3 bg-muted/50 rounded-xl">
              <p className="text-xs text-muted-foreground">Índice reajuste</p>
              <p>{contrato.indice_reajuste} / {contrato.periodicidade_reajuste ?? 12} meses</p>
            </div>
          )}
        </div>

        {/* Rescisão */}
        {isRescindido && (
          <div className="p-3 bg-red-50 border border-red-200 rounded-xl mb-4 text-sm">
            <p className="font-medium text-red-800 mb-1">Contrato rescindido</p>
            <p>Data: {formatDate(contrato.rescindido_em)}</p>
            {contrato.motivo_rescisao && <p>Motivo: {contrato.motivo_rescisao}</p>}
            {contrato.multa_rescisao_calculada ? <p>Multa: R$ {formatMoney(contrato.multa_rescisao_calculada)}</p> : null}
          </div>
        )}

        {/* Fiadores */}
        <div className="mb-4">
          <div className="flex items-center justify-between mb-2">
            <h3 className="text-sm font-semibold">Fiadores</h3>
            {!isRescindido && (
              <button
                type="button"
                onClick={() => setAddFiador({ open: true, pessoa_id: '' })}
                className="text-xs text-primary hover:underline"
              >
                + Adicionar
              </button>
            )}
          </div>
          {(contrato.fiadores?.length ?? 0) === 0 ? (
            <p className="text-xs text-muted-foreground">Nenhum fiador cadastrado.</p>
          ) : (
            <div className="space-y-1">
              {contrato.fiadores!.map((f) => (
                <div key={f.id} className="flex items-center justify-between px-3 py-2 bg-muted/50 rounded-lg text-sm">
                  <span>{f.pessoa.nome}</span>
                  {!isRescindido && (
                    <button
                      type="button"
                      onClick={() => handleRemoverFiador(f.id)}
                      className="text-xs text-red-600 hover:underline"
                    >
                      Remover
                    </button>
                  )}
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Histórico de reajustes */}
        {(contrato.reajustes?.length ?? 0) > 0 && (
          <div className="mb-4">
            <h3 className="text-sm font-semibold mb-2">Histórico de reajustes</h3>
            <div className="overflow-auto">
              <table className="w-full text-xs">
                <thead>
                  <tr className="border-b border-border text-muted-foreground">
                    <th className="text-left p-2">Competência</th>
                    <th className="text-left p-2">Índice</th>
                    <th className="text-right p-2">%</th>
                    <th className="text-right p-2">Valor anterior</th>
                    <th className="text-right p-2">Novo valor</th>
                  </tr>
                </thead>
                <tbody>
                  {contrato.reajustes!.map((r) => (
                    <tr key={r.id} className="border-b border-border/40">
                      <td className="p-2">{r.competencia}</td>
                      <td className="p-2">{r.indice}</td>
                      <td className="p-2 text-right">{r.percentual_aplicado.toFixed(2)}%</td>
                      <td className="p-2 text-right">R$ {formatMoney(r.valor_anterior)}</td>
                      <td className="p-2 text-right font-medium">R$ {formatMoney(r.novo_valor)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}

        {/* Documentos */}
        {(contrato.documentos?.length ?? 0) > 0 && (
          <div className="mb-4">
            <h3 className="text-sm font-semibold mb-2">Documentos</h3>
            <div className="space-y-1">
              {contrato.documentos!.map((d) => (
                <div key={d.id} className="flex items-center justify-between px-3 py-2 bg-muted/50 rounded-lg text-xs">
                  <span className="capitalize">{d.tipo.replace(/_/g, ' ')}</span>
                  <div className="flex items-center gap-2">
                    <span className={`px-2 py-0.5 rounded-full ${d.status === 'assinado' ? 'bg-emerald-100 text-emerald-700' : 'bg-muted text-muted-foreground'}`}>
                      {d.status}
                    </span>
                    {d.url_documento && (
                      <a href={d.url_documento} target="_blank" rel="noreferrer" className="text-primary hover:underline">
                        Baixar
                      </a>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </div>
        )}

        <div className="flex justify-end pt-2 border-t border-border">
          <button type="button" onClick={onClose} className="px-4 py-2 rounded-lg border border-border hover:bg-accent text-sm">
            Fechar
          </button>
        </div>
      </div>
    </div>
  );
}
