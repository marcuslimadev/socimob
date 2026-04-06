import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { api } from '@/lib/api';
import {
  CheckCircle2,
  ChevronDown,
  Clock,
  Download,
  ExternalLink,
  FileText,
  Info,
  Send,
  Trash2,
  X,
  AlertCircle,
  Plus,
} from 'lucide-react';

interface PessoaResumo {
  id: number;
  nome: string;
  email?: string;
  telefone?: string;
}

interface Documento {
  id: number;
  tipo: string;
  categoria?: string;
  versao?: number;
  referencia_documento_id?: number | null;
  nome?: string;
  status: string;
  url_documento?: string;
  d4sign_uuid?: string;
  assinado_em?: string;
  created_at: string;
}

interface ParcelaPagamento {
  descricao?: string;
  valor?: number;
  texto?: string;
}

interface ContratoCompraVendaDetalhes {
  id: number;
  numero_contrato?: string;
  status: string;
  data_contrato?: string;
  data_escritura_prevista?: string;
  data_entrega_chaves?: string;
  valor_total?: number;
  valor_sinal?: number;
  valor_parcela_final?: number;
  multa_percentual?: number;
  multa_moratoria_percentual?: number;
  juros_percentual_mes?: number;
  corretagem_valor?: number;
  corretagem_responsavel?: string;
  objeto_descricao?: string;
  matricula_numero?: string;
  cartorio_nome?: string;
  inscricao_cadastral?: string;
  testemunha_um_nome?: string;
  testemunha_um_email?: string;
  testemunha_dois_nome?: string;
  testemunha_dois_email?: string;
  observacoes?: string;
  parcelas_pagamento?: ParcelaPagamento[];
  vendedores?: PessoaResumo[];
  compradores?: PessoaResumo[];
  imovel?: { id: number; titulo?: string; codigo?: string; logradouro?: string; numero?: string; bairro?: string; cidade?: string; estado?: string };
  documentos?: Documento[];
}

interface Signatario {
  email: string;
  nome: string;
  papel: string;
  icp_brasil: boolean;
}

interface PdfPreviewState {
  url: string;
  titulo: string;
  documento: Documento | null;
}

interface Props {
  contratoId: number;
  onClose: () => void;
}

const tiposDocumento = [
  { value: 'compra_venda', label: 'Contrato de Compra e Venda' },
];

const categoriaDocLabel: Record<string, string> = {
  original: 'Original',
  revisado: 'Revisado',
  assinado: 'Assinado',
};

const categoriaDocOrder: Record<string, number> = {
  original: 0,
  revisado: 1,
  assinado: 2,
};

const statusDocLabel: Record<string, { label: string; color: string; icon: React.ReactNode }> = {
  nao_enviado: { label: 'Não enviado', color: 'text-muted-foreground bg-muted', icon: <FileText size={12} /> },
  aguardando: { label: 'Aguard. assinatura', color: 'text-yellow-700 bg-yellow-100', icon: <Clock size={12} /> },
  assinado: { label: 'Assinado', color: 'text-emerald-700 bg-emerald-100', icon: <CheckCircle2 size={12} /> },
  recusado: { label: 'Recusado', color: 'text-red-700 bg-red-100', icon: <AlertCircle size={12} /> },
};

function fmt(value?: string) {
  if (!value) return '-';
  const [year, month, day] = value.slice(0, 10).split('-');
  if (!year || !month || !day) return value;
  return `${day}/${month}/${year}`;
}

function fmtMoney(value?: number) {
  return Number(value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2 });
}

function documentoTitulo(doc: Documento) {
  const tipoLabel = tiposDocumento.find((item) => item.value === doc.tipo)?.label ?? doc.tipo;
  const categoria = categoriaDocLabel[doc.categoria || 'original'] || 'Documento';
  const versao = doc.versao ?? 1;
  return `${tipoLabel} • ${categoria} V${versao}`;
}

function ordenarDocumentos(documentos: Documento[]) {
  return [...documentos].sort((a, b) => {
    const versaoA = a.versao ?? 1;
    const versaoB = b.versao ?? 1;
    if (versaoA !== versaoB) return versaoB - versaoA;

    const ordemA = categoriaDocOrder[a.categoria || 'original'] ?? 99;
    const ordemB = categoriaDocOrder[b.categoria || 'original'] ?? 99;
    if (ordemA !== ordemB) return ordemA - ordemB;

    return new Date(b.created_at).getTime() - new Date(a.created_at).getTime();
  });
}

function AssinaturaModal({
  documento,
  contratoId,
  vendedores,
  compradores,
  testemunhas,
  onClose,
  onSent,
}: {
  documento: Documento;
  contratoId: number;
  vendedores: PessoaResumo[];
  compradores: PessoaResumo[];
  testemunhas: Array<{ nome?: string; email?: string }>;
  onClose: () => void;
  onSent: () => void;
}) {
  const [signatarios, setSignatarios] = useState<Signatario[]>(() => {
    const itens: Signatario[] = [];

    compradores.forEach((pessoa) => {
      if (pessoa.email) itens.push({ email: pessoa.email, nome: pessoa.nome, papel: 'Comprador', icp_brasil: true });
    });

    vendedores.forEach((pessoa) => {
      if (pessoa.email) itens.push({ email: pessoa.email, nome: pessoa.nome, papel: 'Vendedor', icp_brasil: true });
    });

    testemunhas.forEach((pessoa, index) => {
      if (pessoa.email && pessoa.nome) {
        itens.push({ email: pessoa.email, nome: pessoa.nome, papel: `Testemunha ${index + 1}`, icp_brasil: false });
      }
    });

    return itens;
  });
  const [loading, setLoading] = useState(false);
  const [showGovBr, setShowGovBr] = useState(false);

  const addSignatario = () =>
    setSignatarios((items) => [...items, { email: '', nome: '', papel: 'Testemunha', icp_brasil: false }]);

  const removeSignatario = (index: number) =>
    setSignatarios((items) => items.filter((_, itemIndex) => itemIndex !== index));

  const update = (index: number, field: keyof Signatario, value: string | boolean) =>
    setSignatarios((items) => items.map((sig, itemIndex) => itemIndex === index ? { ...sig, [field]: value } : sig));

  const handleEnviar = async () => {
    if (signatarios.some((item) => !item.email || !item.nome)) {
      toast.error('Preencha e-mail e nome de todos os signatários.');
      return;
    }

    setLoading(true);
    try {
      await api.post(
        `/admin/financeiro/compra-venda/${contratoId}/documentos/${documento.id}/enviar-assinatura`,
        { signatarios },
      );
      toast.success('Documento enviado para assinatura via D4Sign.');
      onSent();
    } catch (error: any) {
      toast.error(error?.response?.data?.message || 'Erro ao enviar para assinatura.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="fixed inset-0 z-[60] flex items-center justify-center bg-black/70">
      <div className="glass-panel mx-4 max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-2xl p-6">
        <div className="mb-4 flex items-center justify-between">
          <div>
            <h3 className="text-base font-semibold">Enviar para Assinatura</h3>
            <p className="mt-0.5 text-xs text-muted-foreground">{documento.nome || documento.tipo}</p>
          </div>
          <button type="button" onClick={onClose} className="text-muted-foreground hover:text-foreground"><X size={18} /></button>
        </div>

        <div className="mb-4 flex gap-2 rounded-xl border border-blue-500/20 bg-blue-500/10 p-3 text-xs text-blue-300">
          <Info size={14} className="mt-0.5 shrink-0" />
          <span>O documento será enviado via <strong>D4Sign</strong>, com o mesmo fluxo já usado na locação.</span>
        </div>

        <div className="mb-4 space-y-3">
          {signatarios.map((sig, index) => (
            <div key={index} className="space-y-2 rounded-xl border border-border bg-muted/40 p-3">
              <div className="flex items-center justify-between">
                <span className="text-xs font-medium text-muted-foreground">Signatário {index + 1}</span>
                {signatarios.length > 1 && (
                  <button type="button" onClick={() => removeSignatario(index)} className="text-destructive hover:opacity-80">
                    <Trash2 size={13} />
                  </button>
                )}
              </div>

              <div className="grid grid-cols-2 gap-2">
                <div>
                  <label className="text-[11px] text-muted-foreground">Nome</label>
                  <input
                    value={sig.nome}
                    onChange={(event) => update(index, 'nome', event.target.value)}
                    className="mt-0.5 w-full rounded-lg border border-border bg-background px-2 py-1.5 text-sm"
                  />
                </div>
                <div>
                  <label className="text-[11px] text-muted-foreground">Papel</label>
                  <input
                    value={sig.papel}
                    onChange={(event) => update(index, 'papel', event.target.value)}
                    className="mt-0.5 w-full rounded-lg border border-border bg-background px-2 py-1.5 text-sm"
                  />
                </div>
              </div>

              <div>
                <label className="text-[11px] text-muted-foreground">E-mail</label>
                <input
                  type="email"
                  value={sig.email}
                  onChange={(event) => update(index, 'email', event.target.value)}
                  className="mt-0.5 w-full rounded-lg border border-border bg-background px-2 py-1.5 text-sm"
                />
              </div>

              <label className="flex cursor-pointer items-center gap-2 text-xs">
                <input
                  type="checkbox"
                  checked={sig.icp_brasil}
                  onChange={(event) => update(index, 'icp_brasil', event.target.checked)}
                  className="rounded"
                />
                <span>Exigir certificado ICP-Brasil</span>
              </label>
            </div>
          ))}
        </div>

        <button type="button" onClick={addSignatario} className="mb-4 flex items-center gap-1.5 text-xs text-primary hover:underline">
          <Plus size={13} /> Adicionar signatário
        </button>

        <button
          type="button"
          onClick={() => setShowGovBr((current) => !current)}
          className="mb-4 flex w-full items-center justify-between rounded-xl border border-border p-2.5 text-xs text-muted-foreground hover:bg-accent"
        >
          <span className="flex items-center gap-1.5"><Info size={13} /> Como funciona a assinatura?</span>
          <ChevronDown size={13} className={showGovBr ? 'rotate-180' : ''} />
        </button>

        {showGovBr && (
          <div className="mb-4 space-y-1.5 rounded-xl bg-muted/40 p-3 text-xs text-muted-foreground">
            <p><strong className="text-foreground">Fluxo:</strong> gere o PDF, envie pela D4Sign e cada signatário recebe um link por e-mail.</p>
            <p><strong className="text-foreground">Com ICP-Brasil:</strong> o assinante precisará usar certificado digital compatível.</p>
            <p><strong className="text-foreground">Sem ICP-Brasil:</strong> a assinatura segue como eletrônica simples.</p>
            <p><strong className="text-foreground">Alternativa externa:</strong> também é possível baixar o PDF e assinar fora do sistema em <a href="https://assinador.iti.br" target="_blank" rel="noreferrer" className="inline-flex items-center gap-0.5 text-primary underline">assinador.iti.br <ExternalLink size={10} /></a>.</p>
          </div>
        )}

        <div className="flex justify-end gap-2">
          <button type="button" onClick={onClose} className="rounded-lg border border-border px-4 py-2 text-sm hover:bg-accent">Cancelar</button>
          <button
            type="button"
            onClick={handleEnviar}
            disabled={loading}
            className="flex items-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm text-primary-foreground hover:opacity-90 disabled:opacity-60"
          >
            {loading ? 'Enviando...' : <><Send size={14} /> Enviar para assinatura</>}
          </button>
        </div>
      </div>
    </div>
  );
}

function UploadAssinadoModal({
  documento,
  contratoId,
  onClose,
  onUploaded,
}: {
  documento: Documento;
  contratoId: number;
  onClose: () => void;
  onUploaded: () => void;
}) {
  const [arquivo, setArquivo] = useState<File | null>(null);
  const [loading, setLoading] = useState(false);

  const handleUpload = async () => {
    if (!arquivo) {
      toast.error('Selecione o PDF assinado para enviar.');
      return;
    }

    const formData = new FormData();
    formData.append('arquivo', arquivo);

    setLoading(true);
    try {
      await api.post(
        `/admin/financeiro/compra-venda/${contratoId}/documentos/${documento.id}/upload-assinado`,
        formData,
        { headers: { 'Content-Type': 'multipart/form-data' } },
      );
      toast.success('PDF assinado salvo no contrato.');
      onUploaded();
    } catch (error: any) {
      toast.error(error?.response?.data?.message || 'Erro ao enviar o PDF assinado.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="fixed inset-0 z-[60] flex items-center justify-center bg-black/70">
      <div className="glass-panel mx-4 w-full max-w-lg rounded-2xl p-6">
        <div className="mb-4 flex items-center justify-between">
          <div>
            <h3 className="text-base font-semibold">Enviar PDF Assinado</h3>
            <p className="mt-0.5 text-xs text-muted-foreground">{documentoTitulo(documento)}</p>
          </div>
          <button type="button" onClick={onClose} className="text-muted-foreground hover:text-foreground"><X size={18} /></button>
        </div>

        <div className="mb-4 space-y-1.5 rounded-xl border border-border bg-muted/40 p-3 text-xs text-muted-foreground">
          <p>Baixe a versão pronta, colete as assinaturas e envie aqui o PDF final.</p>
          <p>Se já existir um assinado para esta versão, ele será substituído.</p>
        </div>

        <label className="mb-4 block">
          <span className="text-[11px] text-muted-foreground">Arquivo PDF assinado</span>
          <input
            type="file"
            accept="application/pdf"
            onChange={(event) => setArquivo(event.target.files?.[0] ?? null)}
            className="mt-1.5 w-full rounded-lg border border-border bg-background px-3 py-2 text-sm file:mr-3 file:border-0 file:bg-transparent file:text-sm"
          />
        </label>

        <div className="flex justify-end gap-2">
          <button type="button" onClick={onClose} className="rounded-lg border border-border px-4 py-2 text-sm hover:bg-accent">Cancelar</button>
          <button
            type="button"
            onClick={handleUpload}
            disabled={loading}
            className="rounded-lg bg-primary px-4 py-2 text-sm text-primary-foreground hover:opacity-90 disabled:opacity-60"
          >
            {loading ? 'Enviando...' : 'Salvar assinado'}
          </button>
        </div>
      </div>
    </div>
  );
}

function PdfPreviewModal({
  preview,
  onClose,
  onEnviarAssinatura,
  onAssinarGovBr,
  onEnviarAssinado,
}: {
  preview: PdfPreviewState;
  onClose: () => void;
  onEnviarAssinatura: () => void;
  onAssinarGovBr: () => void;
  onEnviarAssinado: () => void;
}) {
  return (
    <div className="fixed inset-0 z-[70] flex items-center justify-center bg-black/80">
      <div className="glass-panel mx-4 flex h-[90vh] w-full max-w-6xl flex-col rounded-2xl p-4">
        <div className="mb-3 flex items-start justify-between gap-4">
          <div>
            <h3 className="text-base font-semibold">Pré-visualização: {preview.titulo}</h3>
            <p className="mt-0.5 text-xs text-muted-foreground">Fluxo sugerido: revisar PDF, enviar para assinatura e anexar versão assinada final.</p>
          </div>
          <button type="button" onClick={onClose} className="text-muted-foreground hover:text-foreground"><X size={18} /></button>
        </div>

        <div className="mb-3 flex flex-wrap gap-2 rounded-xl border border-blue-500/20 bg-blue-500/10 p-3 text-xs text-blue-200">
          <span>1) Revise o conteúdo.</span>
          <span>2) Assine via D4Sign ou Gov.br externo.</span>
          <span>3) Envie o PDF final assinado.</span>
        </div>

        <div className="min-h-0 flex-1 overflow-hidden rounded-xl border border-border bg-white">
          <iframe title={preview.titulo} src={preview.url} className="h-full w-full" />
        </div>

        <div className="mt-3 flex flex-wrap justify-end gap-2">
          <a
            href={preview.url}
            download
            className="flex items-center gap-1 rounded-lg border border-border px-3 py-2 text-sm text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
          >
            <Download size={14} /> Baixar PDF
          </a>
          <button
            type="button"
            onClick={onEnviarAssinatura}
            className="flex items-center gap-1 rounded-lg border border-primary/50 px-3 py-2 text-sm text-primary transition-colors hover:bg-primary/5"
          >
            <Send size={14} /> Assinar via D4Sign
          </button>
          <button
            type="button"
            onClick={onAssinarGovBr}
            className="flex items-center gap-1 rounded-lg border border-sky-500/40 px-3 py-2 text-sm text-sky-600 transition-colors hover:bg-sky-500/5"
          >
            <ExternalLink size={14} /> Assinar externamente (Gov.br)
          </button>
          <button
            type="button"
            onClick={onEnviarAssinado}
            className="flex items-center gap-1 rounded-lg border border-emerald-500/40 px-3 py-2 text-sm text-emerald-600 transition-colors hover:bg-emerald-500/5"
          >
            <CheckCircle2 size={14} /> Enviar assinado final
          </button>
        </div>
      </div>
    </div>
  );
}

export default function ContratoCompraVendaDetalheModal({ contratoId, onClose }: Props) {
  const [contrato, setContrato] = useState<ContratoCompraVendaDetalhes | null>(null);
  const [loading, setLoading] = useState(true);
  const [gerandoPdf, setGerandoPdf] = useState(false);
  const [pdfDropdown, setPdfDropdown] = useState(false);
  const [docParaAssinar, setDocParaAssinar] = useState<Documento | null>(null);
  const [docParaUpload, setDocParaUpload] = useState<Documento | null>(null);
  const [pdfPreview, setPdfPreview] = useState<PdfPreviewState | null>(null);
  const [deletandoDoc, setDeletandoDoc] = useState<number | null>(null);

  const iniciarAssinaturaExternaGovBr = () => {
    window.open('https://assinador.iti.br', '_blank', 'noopener,noreferrer');
    toast.info('Assine externamente no Gov.br/ITI e depois envie o PDF final assinado.');
  };

  const loadContrato = async () => {
    setLoading(true);
    try {
      const { data } = await api.get(`/admin/financeiro/compra-venda/${contratoId}`);
      setContrato(data?.item ?? data);
    } catch {
      toast.error('Erro ao carregar detalhes do contrato.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    void loadContrato();
  }, [contratoId]);

  const handleGerarPdf = async (tipo: string) => {
    setPdfDropdown(false);
    setGerandoPdf(true);
    try {
      const { data } = await api.post(
        `/admin/financeiro/compra-venda/${contratoId}/documentos/gerar-pdf`,
        { tipo },
      );

      const documentoGerado: Documento | null = data?.item ?? null;
      const url = data?.url_documento ?? documentoGerado?.url_documento;
      const titulo = tiposDocumento.find((item) => item.value === tipo)?.label ?? 'Documento';

      if (url) {
        setPdfPreview({
          url,
          titulo,
          documento: documentoGerado,
        });
      }

      toast.success('PDF gerado com sucesso.');
      await loadContrato();
    } catch (error: any) {
      toast.error(error?.response?.data?.message || 'Erro ao gerar PDF.');
    } finally {
      setGerandoPdf(false);
    }
  };

  const handleDeletarDoc = async (docId: number) => {
    if (!confirm('Remover este documento?')) return;
    setDeletandoDoc(docId);
    try {
      await api.delete(`/admin/financeiro/compra-venda/${contratoId}/documentos/${docId}`);
      toast.success('Documento removido.');
      await loadContrato();
    } catch {
      toast.error('Erro ao remover documento.');
    } finally {
      setDeletandoDoc(null);
    }
  };

  if (loading) {
    return (
      <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div className="glass-panel rounded-2xl p-8 text-center">
          <p className="text-sm text-muted-foreground">Carregando...</p>
        </div>
      </div>
    );
  }

  if (!contrato) return null;

  const vendedores = contrato.vendedores || [];
  const compradores = contrato.compradores || [];
  const testemunhas = [
    { nome: contrato.testemunha_um_nome, email: contrato.testemunha_um_email },
    { nome: contrato.testemunha_dois_nome, email: contrato.testemunha_dois_email },
  ];
  const documentosOrdenados = ordenarDocumentos(contrato.documentos || []);
  const documentoAssinavelRecente = documentosOrdenados.find((item) => (item.categoria ?? 'original') !== 'assinado') ?? null;

  return (
    <>
      <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={(event) => { if (event.target === event.currentTarget) onClose(); }}>
        <div className="glass-panel mx-4 flex max-h-[92vh] w-full max-w-4xl flex-col rounded-2xl">
          <div className="shrink-0 px-6 pt-6">
            <div className="mb-4 flex items-start justify-between">
              <div>
                <h2 className="text-lg font-semibold">Compra e Venda {contrato.numero_contrato || `#${contrato.id}`}</h2>
                <span className="mt-1 inline-flex rounded-full bg-muted px-2 py-0.5 text-xs font-medium text-foreground">
                  {contrato.status || 'rascunho'}
                </span>
              </div>
              <button type="button" onClick={onClose} className="ml-4 text-muted-foreground hover:text-foreground"><X size={20} /></button>
            </div>

            <div className="mb-4 flex flex-wrap gap-2 border-b border-border pb-4">
              <div className="relative">
                <button
                  type="button"
                  onClick={() => setPdfDropdown((current) => !current)}
                  disabled={gerandoPdf}
                  className="flex items-center gap-1.5 rounded-lg border border-border px-3 py-1.5 text-sm hover:bg-accent disabled:opacity-60"
                >
                  <FileText size={14} />
                  {gerandoPdf ? 'Gerando...' : 'Gerar PDF'}
                  <ChevronDown size={13} className={`transition-transform ${pdfDropdown ? 'rotate-180' : ''}`} />
                </button>
                {pdfDropdown && (
                  <>
                    <div className="fixed inset-0 z-[59]" onClick={() => setPdfDropdown(false)} />
                    <div className="absolute left-0 top-full z-[60] mt-1 min-w-[220px] rounded-xl border border-border bg-popover py-1 shadow-xl">
                      {tiposDocumento.map((tipo) => (
                        <button
                          key={tipo.value}
                          type="button"
                          onClick={() => handleGerarPdf(tipo.value)}
                          className="w-full px-3 py-2 text-left text-sm transition-colors hover:bg-accent"
                        >
                          {tipo.label}
                        </button>
                      ))}
                    </div>
                  </>
                )}
              </div>
            </div>
          </div>

          <div className="flex-1 overflow-y-auto px-6 pb-6">
            <div className="mb-4 grid grid-cols-1 gap-3 md:grid-cols-2">
              <div className="rounded-xl bg-muted/50 p-3 text-sm">
                <p className="mb-1 text-xs text-muted-foreground">Vendedores</p>
                {vendedores.length > 0 ? vendedores.map((item) => (
                  <div key={item.id} className="mb-2 last:mb-0">
                    <p className="font-semibold">{item.nome}</p>
                    {item.email && <p className="text-xs text-muted-foreground">{item.email}</p>}
                  </div>
                )) : <p>-</p>}
              </div>
              <div className="rounded-xl bg-muted/50 p-3 text-sm">
                <p className="mb-1 text-xs text-muted-foreground">Compradores</p>
                {compradores.length > 0 ? compradores.map((item) => (
                  <div key={item.id} className="mb-2 last:mb-0">
                    <p className="font-semibold">{item.nome}</p>
                    {item.email && <p className="text-xs text-muted-foreground">{item.email}</p>}
                  </div>
                )) : <p>-</p>}
              </div>
            </div>

            <div className="mb-4 rounded-xl bg-muted/50 p-3 text-sm">
              <p className="mb-1 text-xs text-muted-foreground">Imóvel</p>
              <p className="font-semibold">{contrato.imovel?.titulo || contrato.imovel?.codigo || '-'}</p>
              <p className="text-xs text-muted-foreground">
                {[contrato.imovel?.logradouro, contrato.imovel?.numero, contrato.imovel?.bairro, contrato.imovel?.cidade, contrato.imovel?.estado]
                  .filter(Boolean)
                  .join(', ') || '-'}
              </p>
            </div>

            <div className="mb-5">
              <h3 className="mb-2 px-1 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Condições principais</h3>
              <div className="grid grid-cols-2 gap-2 text-sm md:grid-cols-3">
                {[
                  { label: 'Data do contrato', value: fmt(contrato.data_contrato) },
                  { label: 'Escritura prevista', value: fmt(contrato.data_escritura_prevista) },
                  { label: 'Entrega das chaves', value: fmt(contrato.data_entrega_chaves) },
                  { label: 'Valor total', value: `R$ ${fmtMoney(contrato.valor_total)}`, bold: true },
                  { label: 'Sinal', value: `R$ ${fmtMoney(contrato.valor_sinal)}` },
                  { label: 'Parcela final', value: `R$ ${fmtMoney(contrato.valor_parcela_final)}` },
                  { label: 'Multa contratual', value: contrato.multa_percentual != null ? `${contrato.multa_percentual}%` : '-' },
                  { label: 'Multa moratória', value: contrato.multa_moratoria_percentual != null ? `${contrato.multa_moratoria_percentual}%` : '-' },
                  { label: 'Juros mês', value: contrato.juros_percentual_mes != null ? `${contrato.juros_percentual_mes}%` : '-' },
                  { label: 'Corretagem', value: `R$ ${fmtMoney(contrato.corretagem_valor)}` },
                  { label: 'Responsável corretagem', value: contrato.corretagem_responsavel || '-' },
                  { label: 'Matrícula', value: contrato.matricula_numero || '-' },
                ].map((item) => (
                  <div key={item.label} className="rounded-xl bg-muted/50 p-2.5">
                    <p className="text-[11px] text-muted-foreground">{item.label}</p>
                    <p className={item.bold ? 'font-semibold' : ''}>{item.value}</p>
                  </div>
                ))}
              </div>
            </div>

            {contrato.objeto_descricao && (
              <div className="mb-5">
                <h3 className="mb-2 px-1 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Objeto do contrato</h3>
                <div className="whitespace-pre-line rounded-xl bg-muted/50 p-3 text-sm">{contrato.objeto_descricao}</div>
              </div>
            )}

            {(contrato.parcelas_pagamento?.length ?? 0) > 0 && (
              <div className="mb-5">
                <h3 className="mb-2 px-1 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Forma de pagamento</h3>
                <div className="space-y-2">
                  {contrato.parcelas_pagamento!.map((item, index) => (
                    <div key={`${item.descricao || 'parcela'}-${index}`} className="rounded-xl border border-border/50 bg-muted/40 p-3 text-sm">
                      <p className="font-medium">{item.descricao || `Parcela ${index + 1}`} {item.valor != null ? `• R$ ${fmtMoney(item.valor)}` : ''}</p>
                      {item.texto && <p className="mt-1 whitespace-pre-line text-xs text-muted-foreground">{item.texto}</p>}
                    </div>
                  ))}
                </div>
              </div>
            )}

            {contrato.observacoes && (
              <div className="mb-5">
                <h3 className="mb-2 px-1 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Observações</h3>
                <div className="whitespace-pre-line rounded-xl bg-muted/50 p-3 text-sm">{contrato.observacoes}</div>
              </div>
            )}

            <div className="mb-5">
              <h3 className="mb-2 px-1 text-xs font-semibold uppercase tracking-wider text-muted-foreground">Documentos do contrato</h3>
              <div className="mb-3 rounded-xl border border-blue-500/20 bg-blue-500/10 p-3 text-xs text-blue-200">
                Gere o PDF, faça a assinatura via D4Sign ou fora do sistema e mantenha a versão final anexada no contrato.
              </div>
              {(contrato.documentos?.length ?? 0) === 0 ? (
                <p className="text-xs text-muted-foreground">Nenhum documento gerado.</p>
              ) : (
                <div className="space-y-2">
                  {documentosOrdenados.map((doc) => {
                    const status = statusDocLabel[doc.status] ?? statusDocLabel.nao_enviado;
                    const podeReceberAssinado = (doc.categoria ?? 'original') !== 'assinado';
                    return (
                      <div key={doc.id} className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-border/50 bg-muted/40 px-3 py-2.5 text-xs">
                        <div className="min-w-0 flex-1">
                          <p className="truncate text-sm font-medium text-foreground">{documentoTitulo(doc)}</p>
                          <p className="mt-0.5 text-muted-foreground">{doc.nome || documentoTitulo(doc)}</p>
                          <p className="mt-0.5 text-muted-foreground">
                            {doc.categoria === 'assinado' && doc.assinado_em
                              ? `Assinado em ${new Date(doc.assinado_em).toLocaleDateString('pt-BR')}`
                              : `Criado em ${new Date(doc.created_at).toLocaleDateString('pt-BR')}`}
                          </p>
                        </div>

                        <div className="flex shrink-0 flex-wrap items-center justify-end gap-2">
                          <span className={`inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium ${status.color}`}>
                            {status.icon} {status.label}
                          </span>

                          {doc.url_documento && (
                            <a
                              href={doc.url_documento}
                              target="_blank"
                              rel="noreferrer"
                              className="flex items-center gap-1 rounded-lg border border-border px-2.5 py-1 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                            >
                              <Download size={13} />
                              <span>Baixar</span>
                            </a>
                          )}

                          {doc.status === 'nao_enviado' && podeReceberAssinado && (
                            <button
                              type="button"
                              onClick={() => setDocParaAssinar(doc)}
                              className="flex items-center gap-1 rounded-lg border border-primary/50 px-2.5 py-1 text-primary transition-colors hover:bg-primary/5"
                            >
                              <Send size={13} />
                              <span>D4Sign</span>
                            </button>
                          )}

                          {podeReceberAssinado && (
                            <button
                              type="button"
                              onClick={() => {
                                iniciarAssinaturaExternaGovBr();
                                setDocParaUpload(doc);
                              }}
                              className="flex items-center gap-1 rounded-lg border border-sky-500/40 px-2.5 py-1 text-sky-600 transition-colors hover:bg-sky-500/5"
                            >
                              <ExternalLink size={13} />
                              <span>Gov.br externo</span>
                            </button>
                          )}

                          {podeReceberAssinado && (
                            <button
                              type="button"
                              onClick={() => setDocParaUpload(doc)}
                              className="flex items-center gap-1 rounded-lg border border-emerald-500/40 px-2.5 py-1 text-emerald-600 transition-colors hover:bg-emerald-500/5"
                            >
                              <CheckCircle2 size={13} />
                              <span>Enviar assinado</span>
                            </button>
                          )}

                          {(doc.status === 'nao_enviado' || (doc.categoria ?? 'original') === 'assinado') && (
                            <button
                              type="button"
                              onClick={() => handleDeletarDoc(doc.id)}
                              disabled={deletandoDoc === doc.id}
                              className="flex items-center gap-1 rounded-lg border border-border px-2 py-1 text-muted-foreground transition-colors hover:border-destructive/50 hover:text-destructive disabled:opacity-40"
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
            </div>

            <div className="flex justify-end border-t border-border pt-2">
              <button type="button" onClick={onClose} className="rounded-lg border border-border px-4 py-2 text-sm hover:bg-accent">Fechar</button>
            </div>
          </div>
        </div>
      </div>

      {docParaAssinar && (
        <AssinaturaModal
          documento={docParaAssinar}
          contratoId={contratoId}
          vendedores={vendedores}
          compradores={compradores}
          testemunhas={testemunhas}
          onClose={() => setDocParaAssinar(null)}
          onSent={async () => {
            setDocParaAssinar(null);
            await loadContrato();
          }}
        />
      )}

      {docParaUpload && (
        <UploadAssinadoModal
          documento={docParaUpload}
          contratoId={contratoId}
          onClose={() => setDocParaUpload(null)}
          onUploaded={async () => {
            setDocParaUpload(null);
            await loadContrato();
          }}
        />
      )}

      {pdfPreview && (
        <PdfPreviewModal
          preview={pdfPreview}
          onClose={() => setPdfPreview(null)}
          onEnviarAssinatura={() => {
            const documento = pdfPreview.documento ?? documentoAssinavelRecente;
            if (!documento) {
              toast.error('Não foi possível identificar o documento para assinatura. Atualize a lista e tente novamente.');
              return;
            }
            setPdfPreview(null);
            setDocParaAssinar(documento);
          }}
          onAssinarGovBr={() => {
            iniciarAssinaturaExternaGovBr();
            const documento = pdfPreview.documento ?? documentoAssinavelRecente;
            if (documento) {
              setPdfPreview(null);
              setDocParaUpload(documento);
            }
          }}
          onEnviarAssinado={() => {
            const documento = pdfPreview.documento ?? documentoAssinavelRecente;
            if (!documento) {
              toast.error('Não foi possível identificar o documento para envio do assinado. Atualize a lista e tente novamente.');
              return;
            }
            setPdfPreview(null);
            setDocParaUpload(documento);
          }}
        />
      )}
    </>
  );
}
