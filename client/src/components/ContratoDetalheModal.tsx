import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { api } from '@/lib/api';
import {
  FileText,
  Download,
  Send,
  Trash2,
  Plus,
  X,
  ChevronDown,
  Info,
  ExternalLink,
  CheckCircle2,
  Clock,
  AlertCircle,
} from 'lucide-react';

// ─── Types ───────────────────────────────────────────────────────────────────

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
  reajustes?: Array<{
    id: number; competencia: string; indice: string;
    percentual_aplicado: number; valor_anterior: number; novo_valor: number; created_at: string;
  }>;
  repasses?: Array<{ id: number; competencia: string; status: string; valor_repasse: number; data_pagamento?: string }>;
  documentos?: Documento[];
}

interface Documento {
  id: number;
  tipo: string;
  nome?: string;
  status: string; // assinatura_status
  url_documento?: string;
  d4sign_uuid?: string;
  created_at: string;
}

interface Signatario {
  email: string;
  nome: string;
  papel: string;
  icp_brasil: boolean;
}

interface Props {
  contratoId: number;
  onClose: () => void;
  onEncerrar: () => void;
  onRenovar: () => void;
  onReajuste: () => void;
}

// ─── Helpers ─────────────────────────────────────────────────────────────────

const tipoGarantiaLabel: Record<string, string> = {
  caucao: 'Caução', fiador: 'Fiador',
  seguro_fianca: 'Seguro Fiança', sem_garantia: 'Sem garantia',
};

const tiposDocumento = [
  { value: 'contrato',  label: 'Contrato de Locação' },
  { value: 'aditivo',   label: 'Aditivo Contratual' },
  { value: 'rescisao',  label: 'Termo de Rescisão' },
  { value: 'renovacao', label: 'Termo de Renovação' },
  { value: 'recibo',    label: 'Recibo de Locação' },
];

const statusDocLabel: Record<string, { label: string; color: string; icon: React.ReactNode }> = {
  nao_enviado: { label: 'Não enviado', color: 'text-muted-foreground bg-muted',      icon: <FileText size={12} /> },
  aguardando:  { label: 'Aguard. assinatura', color: 'text-yellow-700 bg-yellow-100', icon: <Clock size={12} /> },
  assinado:    { label: 'Assinado',    color: 'text-emerald-700 bg-emerald-100',      icon: <CheckCircle2 size={12} /> },
  recusado:    { label: 'Recusado',    color: 'text-red-700 bg-red-100',              icon: <AlertCircle size={12} /> },
};

function fmt(v?: string) {
  if (!v) return '-';
  const [y, m, d] = v.slice(0, 10).split('-');
  return `${d}/${m}/${y}`;
}
function fmtMoney(v?: number) {
  return Number(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
}

// ─── Subcomponentes ──────────────────────────────────────────────────────────

function Section({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <div className="mb-5">
      <h3 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-2 px-1">{title}</h3>
      {children}
    </div>
  );
}

// ─── Modal de Assinatura ─────────────────────────────────────────────────────

function AssinaturaModal({
  documento,
  contratoId,
  locador,
  locatario,
  onClose,
  onSent,
}: {
  documento: Documento;
  contratoId: number;
  locador?: { nome: string; email?: string };
  locatario?: { nome: string; email?: string };
  onClose: () => void;
  onSent: () => void;
}) {
  const [signatarios, setSignatarios] = useState<Signatario[]>(() => {
    const inicial: Signatario[] = [];
    if (locatario?.email) inicial.push({ email: locatario.email, nome: locatario.nome, papel: 'Locatário', icp_brasil: true });
    if (locador?.email)   inicial.push({ email: locador.email,   nome: locador.nome,   papel: 'Locador',   icp_brasil: true });
    return inicial;
  });
  const [loading, setLoading] = useState(false);
  const [showGovBr, setShowGovBr] = useState(false);

  const addSignatario = () =>
    setSignatarios((s) => [...s, { email: '', nome: '', papel: 'Testemunha', icp_brasil: false }]);

  const removeSignatario = (i: number) =>
    setSignatarios((s) => s.filter((_, idx) => idx !== i));

  const update = (i: number, field: keyof Signatario, value: string | boolean) =>
    setSignatarios((s) => s.map((sig, idx) => idx === i ? { ...sig, [field]: value } : sig));

  const handleEnviar = async () => {
    if (signatarios.some((s) => !s.email || !s.nome)) {
      toast.error('Preencha e-mail e nome de todos os signatários.');
      return;
    }
    setLoading(true);
    try {
      await api.post(
        `/admin/financeiro/contratos/${contratoId}/documentos/${documento.id}/enviar-assinatura`,
        { signatarios },
      );
      toast.success('Documento enviado para assinatura via D4Sign!');
      onSent();
    } catch (err: any) {
      toast.error(err?.response?.data?.message || 'Erro ao enviar para assinatura.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="fixed inset-0 bg-black/70 flex items-center justify-center z-[60]">
      <div className="glass-panel rounded-2xl p-6 w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
        <div className="flex items-center justify-between mb-4">
          <div>
            <h3 className="font-semibold text-base">Enviar para Assinatura</h3>
            <p className="text-xs text-muted-foreground mt-0.5">{documento.nome || documento.tipo}</p>
          </div>
          <button type="button" onClick={onClose} className="text-muted-foreground hover:text-foreground"><X size={18} /></button>
        </div>

        {/* Aviso D4Sign */}
        <div className="p-3 rounded-xl bg-blue-500/10 border border-blue-500/20 text-xs text-blue-300 mb-4 flex gap-2">
          <Info size={14} className="shrink-0 mt-0.5" />
          <span>O documento será enviado via <strong>D4Sign</strong> — plataforma de assinatura eletrônica com validade jurídica (Lei 14.063/2020).</span>
        </div>

        {/* Signatários */}
        <div className="space-y-3 mb-4">
          {signatarios.map((sig, i) => (
            <div key={i} className="p-3 rounded-xl bg-muted/40 border border-border space-y-2">
              <div className="flex items-center justify-between">
                <span className="text-xs font-medium text-muted-foreground">Signatário {i + 1}</span>
                {signatarios.length > 1 && (
                  <button type="button" onClick={() => removeSignatario(i)} className="text-destructive hover:opacity-80">
                    <Trash2 size={13} />
                  </button>
                )}
              </div>
              <div className="grid grid-cols-2 gap-2">
                <div>
                  <label className="text-[11px] text-muted-foreground">Nome</label>
                  <input
                    value={sig.nome}
                    onChange={(e) => update(i, 'nome', e.target.value)}
                    className="w-full mt-0.5 px-2 py-1.5 rounded-lg border border-border bg-background text-sm"
                    placeholder="Nome completo"
                  />
                </div>
                <div>
                  <label className="text-[11px] text-muted-foreground">Papel</label>
                  <input
                    value={sig.papel}
                    onChange={(e) => update(i, 'papel', e.target.value)}
                    className="w-full mt-0.5 px-2 py-1.5 rounded-lg border border-border bg-background text-sm"
                    placeholder="Ex: Locatário"
                  />
                </div>
              </div>
              <div>
                <label className="text-[11px] text-muted-foreground">E-mail</label>
                <input
                  type="email"
                  value={sig.email}
                  onChange={(e) => update(i, 'email', e.target.value)}
                  className="w-full mt-0.5 px-2 py-1.5 rounded-lg border border-border bg-background text-sm"
                  placeholder="email@exemplo.com"
                />
              </div>
              <label className="flex items-center gap-2 cursor-pointer select-none">
                <input
                  type="checkbox"
                  checked={sig.icp_brasil}
                  onChange={(e) => update(i, 'icp_brasil', e.target.checked)}
                  className="rounded"
                />
                <span className="text-xs">Exigir certificado ICP-Brasil (Gov.br)</span>
              </label>
            </div>
          ))}
        </div>

        <button type="button" onClick={addSignatario} className="flex items-center gap-1.5 text-xs text-primary hover:underline mb-4">
          <Plus size={13} /> Adicionar signatário
        </button>

        {/* Dica Gov.br */}
        <button
          type="button"
          onClick={() => setShowGovBr(!showGovBr)}
          className="w-full flex items-center justify-between p-2.5 rounded-xl border border-border hover:bg-accent text-xs text-muted-foreground mb-4"
        >
          <span className="flex items-center gap-1.5"><Info size={13} /> Como funciona a assinatura Gov.br?</span>
          <ChevronDown size={13} className={showGovBr ? 'rotate-180' : ''} />
        </button>

        {showGovBr && (
          <div className="p-3 rounded-xl bg-muted/40 text-xs text-muted-foreground mb-4 space-y-1.5">
            <p><strong className="text-foreground">Como o sistema funciona hoje:</strong> você gera o PDF aqui e envia pela D4Sign. Cada signatário recebe um e-mail com o link para assinar.</p>
            <p><strong className="text-foreground">Se marcar "Exigir certificado ICP-Brasil":</strong> a D4Sign vai exigir assinatura com certificado ICP-Brasil. Na prática, o signatário poderá concluir usando certificado digital compatível e, quando disponível no fluxo dele, autenticação vinculada ao <strong>Gov.br</strong> com conta Prata ou Ouro.</p>
            <p><strong className="text-foreground">Se não marcar:</strong> a assinatura segue como eletrônica simples pela D4Sign, com validade jurídica, mas sem a exigência do padrão ICP-Brasil.</p>
            <p><strong className="text-foreground">Opção externa gratuita:</strong> se preferir fazer tudo fora da D4Sign, baixe o PDF e envie pelo <a href="https://assinador.iti.br" target="_blank" rel="noreferrer" className="text-primary underline inline-flex items-center gap-0.5">assinador.iti.br <ExternalLink size={10} /></a>. Isso usa a estrutura oficial do governo, mas hoje não está integrado ao sistema.</p>
          </div>
        )}

        <div className="flex justify-end gap-2">
          <button type="button" onClick={onClose} className="px-4 py-2 rounded-lg border border-border hover:bg-accent text-sm">
            Cancelar
          </button>
          <button
            type="button"
            onClick={handleEnviar}
            disabled={loading}
            className="px-4 py-2 rounded-lg bg-primary text-primary-foreground hover:opacity-90 text-sm disabled:opacity-60 flex items-center gap-2"
          >
            {loading ? 'Enviando...' : <><Send size={14} /> Enviar para assinatura</>}
          </button>
        </div>
      </div>
    </div>
  );
}

// ─── Modal Principal ──────────────────────────────────────────────────────────

export default function ContratoDetalheModal({ contratoId, onClose, onEncerrar, onRenovar, onReajuste }: Props) {
  const [contrato, setContrato] = useState<ContratoDetalhes | null>(null);
  const [loading, setLoading] = useState(true);
  const [gerandoPdf, setGerandoPdf] = useState(false);
  const [pdfDropdown, setPdfDropdown] = useState(false);
  const [addFiador, setAddFiador] = useState({ open: false, pessoa_id: '' });
  const [docParaAssinar, setDocParaAssinar] = useState<Documento | null>(null);
  const [deletandoDoc, setDeletandoDoc] = useState<number | null>(null);

  const loadContrato = async () => {
    setLoading(true);
    try {
      const { data } = await api.get(`/admin/financeiro/contratos/${contratoId}`);
      // Controller retorna { success, item } ou diretamente o item
      setContrato(data?.item ?? data);
    } catch {
      toast.error('Erro ao carregar detalhes do contrato.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { loadContrato(); }, [contratoId]);

  const handleGerarPdf = async (tipo: string) => {
    setPdfDropdown(false);
    setGerandoPdf(true);
    try {
      const { data } = await api.post(
        `/admin/financeiro/contratos/${contratoId}/documentos/gerar-pdf`,
        { tipo },
      );
      const url = data.url_documento ?? data.item?.url_documento;
      toast.success(`PDF "${tiposDocumento.find((t) => t.value === tipo)?.label}" gerado!`);
      if (url) window.open(url, '_blank');
      await loadContrato(); // recarrega lista de documentos
    } catch (err: any) {
      toast.error(err?.response?.data?.message || 'Erro ao gerar PDF.');
    } finally {
      setGerandoPdf(false);
    }
  };

  const handleDeletarDoc = async (docId: number) => {
    if (!confirm('Remover este documento?')) return;
    setDeletandoDoc(docId);
    try {
      await api.delete(`/admin/financeiro/contratos/${contratoId}/documentos/${docId}`);
      toast.success('Documento removido.');
      await loadContrato();
    } catch {
      toast.error('Erro ao remover documento.');
    } finally {
      setDeletandoDoc(null);
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
    <>
      <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50" onClick={(e) => { if (e.target === e.currentTarget) onClose(); }}>
        <div className="glass-panel rounded-2xl w-full max-w-3xl mx-4 max-h-[92vh] flex flex-col">

          {/* Header + Ações — fixos, fora do scroll */}
          <div className="px-6 pt-6 shrink-0">
            <div className="flex items-start justify-between mb-4">
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
              <button type="button" onClick={onClose} className="text-muted-foreground hover:text-foreground ml-4"><X size={20} /></button>
            </div>

            {/* Ações rápidas */}
            {!isRescindido && (
              <div className="flex flex-wrap gap-2 mb-4 pb-4 border-b border-border">
                <button type="button" onClick={onReajuste} className="px-3 py-1.5 rounded-lg border border-border hover:bg-accent text-sm">
                  Reajustar aluguel
                </button>
                <button type="button" onClick={onRenovar} className="px-3 py-1.5 rounded-lg border border-primary text-primary hover:bg-primary/5 text-sm">
                  Renovar contrato
                </button>

                {/* Dropdown Gerar PDF — z-[60] garante que flutua acima de tudo */}
                <div className="relative">
                  <button
                    type="button"
                    onClick={() => setPdfDropdown(!pdfDropdown)}
                    disabled={gerandoPdf}
                    className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-border hover:bg-accent text-sm disabled:opacity-60"
                  >
                    <FileText size={14} />
                    {gerandoPdf ? 'Gerando...' : 'Gerar PDF'}
                    <ChevronDown size={13} className={`transition-transform ${pdfDropdown ? 'rotate-180' : ''}`} />
                  </button>
                  {pdfDropdown && (
                    <>
                      {/* overlay invisível para fechar ao clicar fora */}
                      <div className="fixed inset-0 z-[59]" onClick={() => setPdfDropdown(false)} />
                      <div className="absolute top-full left-0 mt-1 bg-popover border border-border rounded-xl shadow-xl z-[60] py-1 min-w-[210px]">
                        {tiposDocumento.map((t) => (
                          <button
                            key={t.value}
                            type="button"
                            onClick={() => handleGerarPdf(t.value)}
                            className="w-full text-left px-3 py-2 text-sm hover:bg-accent transition-colors"
                          >
                            {t.label}
                          </button>
                        ))}
                      </div>
                    </>
                  )}
                </div>

                <button type="button" onClick={onEncerrar} className="px-3 py-1.5 rounded-lg bg-red-100 text-red-700 hover:bg-red-200 text-sm">
                  Rescindir
                </button>
              </div>
            )}
          </div>

          {/* Conteúdo scrollável */}
          <div className="flex-1 overflow-y-auto px-6 pb-6">

          {/* Partes */}
          <div className="grid grid-cols-1 md:grid-cols-2 gap-3 mb-4 text-sm">
            {[
              { label: 'Locador (Proprietário)', p: contrato.locador },
              { label: 'Locatário (Inquilino)',  p: contrato.locatario },
            ].map(({ label, p }) => (
              <div key={label} className="p-3 bg-muted/50 rounded-xl">
                <p className="text-xs text-muted-foreground mb-1">{label}</p>
                <p className="font-semibold">{p?.nome || '-'}</p>
                {p?.email    && <p className="text-muted-foreground text-xs">{p.email}</p>}
                {p?.telefone && <p className="text-muted-foreground text-xs">{p.telefone}</p>}
              </div>
            ))}
          </div>

          {/* Imóvel */}
          <div className="p-3 bg-muted/50 rounded-xl mb-4 text-sm">
            <p className="text-xs text-muted-foreground mb-1">Imóvel</p>
            <p className="font-semibold">{contrato.imovel?.titulo || contrato.imovel?.codigo || '-'}</p>
            {contrato.imovel?.endereco && <p className="text-muted-foreground text-xs">{contrato.imovel.endereco}</p>}
          </div>

          {/* Condições financeiras */}
          <Section title="Condições financeiras">
            <div className="grid grid-cols-2 md:grid-cols-3 gap-2 text-sm">
              {[
                { label: 'Vigência',       value: `${fmt(contrato.inicio)} → ${fmt(contrato.fim)}` },
                { label: 'Vencimento',     value: `Dia ${contrato.dia_vencimento ?? '-'}` },
                { label: 'Aluguel',        value: `R$ ${fmtMoney(contrato.valor_aluguel)}`, bold: true },
                { label: 'Garantia',       value: tipoGarantiaLabel[contrato.tipo_garantia || ''] || contrato.tipo_garantia || '-' },
                ...(contrato.valor_garantia ? [{ label: 'Valor garantia', value: `R$ ${fmtMoney(contrato.valor_garantia)}` }] : []),
                { label: 'Comissão adm.', value: contrato.comissao_administracao_percentual != null ? `${contrato.comissao_administracao_percentual}%` : '-' },
                ...(contrato.indice_reajuste ? [{ label: 'Índice reajuste', value: `${contrato.indice_reajuste} / ${contrato.periodicidade_reajuste ?? 12} meses` }] : []),
              ].map(({ label, value, bold }) => (
                <div key={label} className="p-2.5 bg-muted/50 rounded-xl">
                  <p className="text-[11px] text-muted-foreground">{label}</p>
                  <p className={bold ? 'font-semibold' : ''}>{value}</p>
                </div>
              ))}
            </div>
          </Section>

          {/* Rescisão */}
          {isRescindido && (
            <div className="p-3 bg-red-50 border border-red-200 rounded-xl mb-4 text-sm">
              <p className="font-medium text-red-800 mb-1">Contrato rescindido</p>
              <p>Data: {fmt(contrato.rescindido_em)}</p>
              {contrato.motivo_rescisao && <p>Motivo: {contrato.motivo_rescisao}</p>}
              {contrato.multa_rescisao_calculada ? <p>Multa: R$ {fmtMoney(contrato.multa_rescisao_calculada)}</p> : null}
            </div>
          )}

          {/* Fiadores */}
          <Section title="Fiadores">
            {(contrato.fiadores?.length ?? 0) === 0 ? (
              <p className="text-xs text-muted-foreground">Nenhum fiador cadastrado.</p>
            ) : (
              <div className="space-y-1">
                {contrato.fiadores!.map((f) => (
                  <div key={f.id} className="flex items-center justify-between px-3 py-2 bg-muted/50 rounded-lg text-sm">
                    <span>{f.pessoa.nome}</span>
                    {!isRescindido && (
                      <button type="button" onClick={() => handleRemoverFiador(f.id)} className="text-xs text-red-600 hover:underline">
                        Remover
                      </button>
                    )}
                  </div>
                ))}
              </div>
            )}
            {!isRescindido && (
              <button type="button" onClick={() => setAddFiador({ open: true, pessoa_id: '' })} className="text-xs text-primary hover:underline mt-2 flex items-center gap-1">
                <Plus size={12} /> Adicionar fiador
              </button>
            )}
          </Section>

          {/* Histórico de reajustes */}
          {(contrato.reajustes?.length ?? 0) > 0 && (
            <Section title="Histórico de reajustes">
              <div className="overflow-auto rounded-xl border border-border">
                <table className="w-full text-xs">
                  <thead className="bg-muted/50">
                    <tr className="border-b border-border text-muted-foreground">
                      {['Competência', 'Índice', '%', 'Valor anterior', 'Novo valor'].map((h) => (
                        <th key={h} className="text-left p-2 last:text-right">{h}</th>
                      ))}
                    </tr>
                  </thead>
                  <tbody>
                    {contrato.reajustes!.map((r) => (
                      <tr key={r.id} className="border-b border-border/40">
                        <td className="p-2">{r.competencia}</td>
                        <td className="p-2">{r.indice}</td>
                        <td className="p-2">{r.percentual_aplicado.toFixed(2)}%</td>
                        <td className="p-2">R$ {fmtMoney(r.valor_anterior)}</td>
                        <td className="p-2 font-medium">R$ {fmtMoney(r.novo_valor)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </Section>
          )}

          {/* Documentos */}
          <Section title="Documentos gerados">
            {(contrato.documentos?.length ?? 0) === 0 ? (
              <p className="text-xs text-muted-foreground">
                Nenhum documento gerado. Use "Gerar PDF" acima para criar o contrato.
              </p>
            ) : (
              <div className="space-y-2">
                {contrato.documentos!.map((doc) => {
                  const st = statusDocLabel[doc.status] ?? statusDocLabel['nao_enviado'];
                  const tipoLabel = tiposDocumento.find((t) => t.value === doc.tipo)?.label ?? doc.tipo;
                  return (
                    <div key={doc.id} className="flex items-center justify-between px-3 py-2.5 bg-muted/40 border border-border/50 rounded-xl text-xs">
                      <div className="flex-1 min-w-0 mr-3">
                        <p className="font-medium text-foreground text-sm truncate">{doc.nome || tipoLabel}</p>
                        <p className="text-muted-foreground mt-0.5">{new Date(doc.created_at).toLocaleDateString('pt-BR')}</p>
                      </div>

                      <div className="flex items-center gap-2 shrink-0">
                        {/* Status badge */}
                        <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[11px] font-medium ${st.color}`}>
                          {st.icon} {st.label}
                        </span>

                        {/* Baixar PDF */}
                        {doc.url_documento && (
                          <a
                            href={doc.url_documento}
                            target="_blank"
                            rel="noreferrer"
                            className="flex items-center gap-1 px-2 py-1 rounded-lg border border-border hover:bg-accent text-muted-foreground hover:text-foreground transition-colors"
                            title="Baixar PDF"
                          >
                            <Download size={13} />
                          </a>
                        )}

                        {/* Enviar para assinatura */}
                        {doc.status === 'nao_enviado' && !isRescindido && (
                          <button
                            type="button"
                            onClick={() => setDocParaAssinar(doc)}
                            className="flex items-center gap-1 px-2 py-1 rounded-lg border border-primary/50 text-primary hover:bg-primary/5 transition-colors"
                            title="Enviar para assinatura"
                          >
                            <Send size={13} />
                          </button>
                        )}

                        {/* Deletar */}
                        {doc.status === 'nao_enviado' && (
                          <button
                            type="button"
                            onClick={() => handleDeletarDoc(doc.id)}
                            disabled={deletandoDoc === doc.id}
                            className="flex items-center gap-1 px-2 py-1 rounded-lg border border-border text-muted-foreground hover:text-destructive hover:border-destructive/50 transition-colors disabled:opacity-40"
                            title="Remover documento"
                          >
                            <Trash2 size={13} />
                          </button>
                        )}
                      </div>
                    </div>
                  );
                })}
              </div>
            )}
          </Section>

          {/* Footer */}
          <div className="flex justify-end pt-2 border-t border-border">
            <button type="button" onClick={onClose} className="px-4 py-2 rounded-lg border border-border hover:bg-accent text-sm">
              Fechar
            </button>
          </div>
          </div>{/* fim conteúdo scrollável */}
        </div>
      </div>

      {/* Modal de assinatura */}
      {docParaAssinar && (
        <AssinaturaModal
          documento={docParaAssinar}
          contratoId={contratoId}
          locador={contrato.locador}
          locatario={contrato.locatario}
          onClose={() => setDocParaAssinar(null)}
          onSent={async () => {
            setDocParaAssinar(null);
            await loadContrato();
          }}
        />
      )}
    </>
  );
}
