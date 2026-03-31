import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { motion } from 'framer-motion';
import {
  FileText, Save, RotateCcw, Plus, Trash2, Info,
  ChevronDown, ChevronRight, Eye,
} from 'lucide-react';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';

// ─── Tipos ────────────────────────────────────────────────────────────────────

interface Template {
  tipo: string;
  titulo: string | null;
  intro_texto: string | null;
  clausulas_padrao: string[];
  rodape_texto: string | null;
  incluir_logo: boolean;
  ativo: boolean;
}

const TIPOS = [
  { value: 'contrato',  label: 'Contrato de Locação',     desc: 'Contrato principal entre locador e locatário' },
  { value: 'compra_venda', label: 'Compra e Venda',       desc: 'Promessa de compra e venda de imóvel' },
  { value: 'aditivo',   label: 'Aditivo Contratual',       desc: 'Alterações ao contrato em vigor' },
  { value: 'rescisao',  label: 'Termo de Rescisão',        desc: 'Encerramento antecipado do contrato' },
  { value: 'renovacao', label: 'Termo de Renovação',       desc: 'Renovação e prorrogação do contrato' },
  { value: 'recibo',    label: 'Recibo de Locação',        desc: 'Comprovante de pagamento de aluguel' },
];

const VARIAVEIS = [
  { grupo: 'Locador',   vars: ['[locador_nome]', '[locador_cpf]', '[locador_email]', '[locador_telefone]'] },
  { grupo: 'Locatário', vars: ['[locatario_nome]', '[locatario_cpf]', '[locatario_email]', '[locatario_telefone]'] },
  { grupo: 'Imóvel',    vars: ['[imovel_endereco]', '[imovel_cidade]', '[imovel_cep]', '[imovel_codigo]'] },
  { grupo: 'Contrato',  vars: ['[contrato_numero]', '[contrato_inicio]', '[contrato_fim]', '[contrato_valor]', '[contrato_dia_vencimento]', '[contrato_indice]'] },
  { grupo: 'Data',      vars: ['[data_hoje]', '[data_extenso]'] },
];

// ─── Componente principal ─────────────────────────────────────────────────────

export default function ContratoTemplates() {
  const [tipoAtivo, setTipoAtivo] = useState('contrato');
  const [templates, setTemplates] = useState<Record<string, Template>>({});
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [showVars, setShowVars] = useState(false);
  const [editando, setEditando] = useState<Template | null>(null);

  const tipoInfo = TIPOS.find((t) => t.value === tipoAtivo)!;

  // Carrega todos os templates
  const loadTemplates = async () => {
    setLoading(true);
    try {
      const { data } = await api.get('/admin/financeiro/contrato-templates');
      if (data.success) {
        setTemplates(data.items);
        setEditando(normalizar(data.items[tipoAtivo], tipoAtivo));
      }
    } catch {
      toast.error('Erro ao carregar templates.');
    } finally {
      setLoading(false);
    }
  };

  const loadTemplate = async (tipo: string) => {
    try {
      const { data } = await api.get(`/admin/financeiro/contrato-templates/${tipo}`);
      if (data.success) {
        setTemplates((prev) => ({ ...prev, [tipo]: data.item }));
        setEditando(normalizar(data.item, tipo));
      }
    } catch {
      toast.error('Erro ao carregar o template selecionado.');
    }
  };

  useEffect(() => { loadTemplates(); }, []);

  // Ao trocar de tipo, carrega o template correspondente
  useEffect(() => {
    if (templates[tipoAtivo]) {
      setEditando(normalizar(templates[tipoAtivo], tipoAtivo));
    }

    loadTemplate(tipoAtivo);
  }, [tipoAtivo]);

  useEffect(() => {
    const handleVisibilityRefresh = () => {
      if (document.visibilityState === 'visible') {
        loadTemplate(tipoAtivo);
      }
    };

    const handleFocusRefresh = () => {
      loadTemplate(tipoAtivo);
    };

    window.addEventListener('focus', handleFocusRefresh);
    document.addEventListener('visibilitychange', handleVisibilityRefresh);

    return () => {
      window.removeEventListener('focus', handleFocusRefresh);
      document.removeEventListener('visibilitychange', handleVisibilityRefresh);
    };
  }, [tipoAtivo]);

  function normalizar(t: any, tipo = tipoAtivo): Template {
    return {
      tipo:            t?.tipo ?? tipo,
      titulo:          t?.titulo ?? '',
      intro_texto:     t?.intro_texto ?? '',
      clausulas_padrao: Array.isArray(t?.clausulas_padrao) ? t.clausulas_padrao : [],
      rodape_texto:    t?.rodape_texto ?? '',
      incluir_logo:    t?.incluir_logo ?? true,
      ativo:           t?.ativo ?? true,
    };
  }

  const handleSave = async () => {
    if (!editando) return;
    setSaving(true);
    try {
      const payload = {
        ...editando,
        // Remove strings vazias para enviar null
        titulo:       editando.titulo?.trim() || null,
        intro_texto:  editando.intro_texto?.trim() || null,
        rodape_texto: editando.rodape_texto?.trim() || null,
        clausulas_padrao: editando.clausulas_padrao.filter((c) => c.trim()),
      };
      const { data } = await api.put(`/admin/financeiro/contrato-templates/${tipoAtivo}`, payload);
      if (data.success) {
        setTemplates((prev) => ({ ...prev, [tipoAtivo]: data.item }));
        toast.success('Template salvo com sucesso!');
      }
    } catch (err: any) {
      toast.error(err?.response?.data?.message || 'Erro ao salvar.');
    } finally {
      setSaving(false);
    }
  };

  const handleReset = async () => {
    if (!confirm('Redefinir este template para o padrão do sistema?')) return;
    try {
      await api.delete(`/admin/financeiro/contrato-templates/${tipoAtivo}`);
      toast.success('Template redefinido para o padrão.');
      await loadTemplates();
    } catch {
      toast.error('Erro ao redefinir.');
    }
  };

  const addClausula = () =>
    setEditando((e) => e ? { ...e, clausulas_padrao: [...e.clausulas_padrao, ''] } : e);

  const removeClausula = (i: number) =>
    setEditando((e) => e ? { ...e, clausulas_padrao: e.clausulas_padrao.filter((_, idx) => idx !== i) } : e);

  const updateClausula = (i: number, val: string) =>
    setEditando((e) => e ? {
      ...e,
      clausulas_padrao: e.clausulas_padrao.map((c, idx) => idx === i ? val : c),
    } : e);

  const moveClausula = (i: number, dir: -1 | 1) => {
    if (!editando) return;
    const arr = [...editando.clausulas_padrao];
    const j = i + dir;
    if (j < 0 || j >= arr.length) return;
    [arr[i], arr[j]] = [arr[j], arr[i]];
    setEditando({ ...editando, clausulas_padrao: arr });
  };

  return (
    <div className="flex min-h-screen bg-background">
      <Sidebar />

      <main className="page-shell">
        <div className="mx-auto max-w-6xl">
        {/* Page Header */}
        <motion.div
          initial={{ opacity: 0, y: -10 }}
          animate={{ opacity: 1, y: 0 }}
          className="mb-8"
        >
          <div className="flex items-center gap-3 mb-2">
            <div className="w-10 h-10 rounded-xl bg-blue-500/20 flex items-center justify-center">
              <FileText size={20} className="text-blue-400" />
            </div>
            <div>
              <h1 className="text-2xl font-bold text-foreground">Templates de Contrato</h1>
              <p className="text-sm text-muted-foreground">Personalize os textos, cláusulas e rodapé dos documentos gerados</p>
            </div>
          </div>
        </motion.div>

        <div className="grid grid-cols-[220px_1fr] gap-6">
          {/* Sidebar de tipos */}
          <div className="flex flex-col gap-1">
            <p className="text-xs font-semibold text-muted-foreground uppercase tracking-wider px-2 mb-1">Tipo de documento</p>
            {TIPOS.map((t) => {
              const tmpl = templates[t.value];
              const personalizado = tmpl && (tmpl.titulo || tmpl.intro_texto || (tmpl.clausulas_padrao?.length ?? 0) > 0);
              return (
                <button
                  key={t.value}
                  onClick={() => setTipoAtivo(t.value)}
                  className={`flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-left transition-all text-sm ${
                    tipoAtivo === t.value
                      ? 'bg-blue-500/20 text-blue-300 border border-blue-500/30'
                      : 'text-muted-foreground hover:bg-white/10'
                  }`}
                >
                  <FileText size={15} className="shrink-0" />
                  <span className="flex-1 font-medium">{t.label}</span>
                  {personalizado && (
                    <span className="w-1.5 h-1.5 rounded-full bg-emerald-400 shrink-0" title="Personalizado" />
                  )}
                </button>
              );
            })}
          </div>

          {/* Editor */}
          <div className="space-y-5">
            {loading || !editando ? (
              <div className="glass-panel rounded-2xl p-8 text-center text-muted-foreground text-sm">
                Carregando...
              </div>
            ) : (
              <>
                {/* Card principal */}
                <div className="glass-panel rounded-2xl p-6">
                  <div className="flex items-start justify-between mb-5">
                    <div>
                      <h2 className="text-base font-semibold">{tipoInfo.label}</h2>
                      <p className="text-xs text-muted-foreground mt-0.5">{tipoInfo.desc}</p>
                    </div>
                    <div className="flex items-center gap-2">
                      <button
                        type="button"
                        onClick={handleReset}
                        className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-border text-muted-foreground hover:bg-accent text-sm"
                        title="Redefinir para padrão"
                      >
                        <RotateCcw size={13} /> Redefinir
                      </button>
                      <button
                        type="button"
                        onClick={handleSave}
                        disabled={saving}
                        className="flex items-center gap-1.5 px-4 py-1.5 rounded-lg bg-primary text-primary-foreground hover:opacity-90 text-sm disabled:opacity-60"
                      >
                        <Save size={13} /> {saving ? 'Salvando...' : 'Salvar'}
                      </button>
                    </div>
                  </div>

                  {/* Título personalizado */}
                  <div className="mb-4">
                    <label className="block text-xs font-semibold text-muted-foreground mb-1.5">
                      Título do documento
                    </label>
                    <input
                      type="text"
                      value={editando.titulo ?? ''}
                      onChange={(e) => setEditando({ ...editando, titulo: e.target.value })}
                      placeholder={`Ex: ${tipoInfo.label} — ${'{'}nome da imobiliária{'}'}`}
                      className="w-full px-3 py-2 rounded-xl border border-border bg-background text-sm"
                    />
                    <p className="text-[11px] text-muted-foreground mt-1">
                      Deixe em branco para usar o título padrão do sistema.
                    </p>
                  </div>

                  {/* Texto de abertura */}
                  <div className="mb-4">
                    <label className="block text-xs font-semibold text-muted-foreground mb-1.5">
                      Texto de abertura / qualificação das partes
                    </label>
                    <textarea
                      value={editando.intro_texto ?? ''}
                      onChange={(e) => setEditando({ ...editando, intro_texto: e.target.value })}
                      rows={5}
                      placeholder={`Ex: Pelo presente instrumento particular, as partes abaixo qualificadas celebram o presente Contrato de Locação, que se regerá pelas disposições da Lei nº 8.245/91 e pelas cláusulas e condições seguintes:\n\nLOCADOR: [locador_nome], CPF [locador_cpf]...\nLOCATÁRIO: [locatario_nome], CPF [locatario_cpf]...`}
                      className="w-full px-3 py-2 rounded-xl border border-border bg-background text-sm resize-y leading-relaxed"
                    />
                    <p className="text-[11px] text-muted-foreground mt-1">
                      Aparece logo após o cabeçalho. Use as variáveis da lista ao lado.
                    </p>
                  </div>

                  {/* Rodapé */}
                  <div>
                    <label className="block text-xs font-semibold text-muted-foreground mb-1.5">
                      Rodapé do documento
                    </label>
                    <input
                      type="text"
                      value={editando.rodape_texto ?? ''}
                      onChange={(e) => setEditando({ ...editando, rodape_texto: e.target.value })}
                      placeholder="Ex: Documento gerado por Imobiliária Exemplo — CRECI 12345 | www.exemplo.com.br"
                      className="w-full px-3 py-2 rounded-xl border border-border bg-background text-sm"
                    />
                  </div>
                </div>

                {/* Cláusulas padrão */}
                <div className="glass-panel rounded-2xl p-6">
                  <div className="flex items-center justify-between mb-4">
                    <div>
                      <h3 className="text-sm font-semibold">Cláusulas padrão</h3>
                      <p className="text-xs text-muted-foreground mt-0.5">
                        Incluídas automaticamente em todos os contratos deste tipo gerados pelo seu tenant.
                      </p>
                    </div>
                    <button
                      type="button"
                      onClick={addClausula}
                      className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-primary/50 text-primary hover:bg-primary/5 text-sm"
                    >
                      <Plus size={13} /> Adicionar cláusula
                    </button>
                  </div>

                  {editando.clausulas_padrao.length === 0 ? (
                    <div className="text-center py-8 text-muted-foreground text-sm border border-dashed border-border rounded-xl">
                      Nenhuma cláusula padrão. Clique em "Adicionar cláusula" para incluir.
                    </div>
                  ) : (
                    <div className="space-y-2">
                      {editando.clausulas_padrao.map((c, i) => (
                        <div key={i} className="flex items-start gap-2">
                          <span className="text-xs text-muted-foreground pt-2.5 w-5 shrink-0 text-right">{i + 1}.</span>
                          <textarea
                            value={c}
                            onChange={(e) => updateClausula(i, e.target.value)}
                            rows={2}
                            placeholder="Digite o texto da cláusula..."
                            className="flex-1 px-3 py-2 rounded-xl border border-border bg-background text-sm resize-y"
                          />
                          <div className="flex flex-col gap-1 shrink-0 pt-1">
                            <button
                              type="button"
                              onClick={() => moveClausula(i, -1)}
                              disabled={i === 0}
                              className="p-1 rounded text-muted-foreground hover:text-foreground disabled:opacity-30"
                              title="Mover para cima"
                            >
                              <ChevronDown size={13} className="rotate-180" />
                            </button>
                            <button
                              type="button"
                              onClick={() => moveClausula(i, 1)}
                              disabled={i === editando.clausulas_padrao.length - 1}
                              className="p-1 rounded text-muted-foreground hover:text-foreground disabled:opacity-30"
                              title="Mover para baixo"
                            >
                              <ChevronDown size={13} />
                            </button>
                            <button
                              type="button"
                              onClick={() => removeClausula(i)}
                              className="p-1 rounded text-muted-foreground hover:text-destructive"
                              title="Remover"
                            >
                              <Trash2 size={13} />
                            </button>
                          </div>
                        </div>
                      ))}
                    </div>
                  )}

                  {editando.clausulas_padrao.length > 0 && (
                    <div className="mt-4 flex justify-end">
                      <button
                        type="button"
                        onClick={handleSave}
                        disabled={saving}
                        className="flex items-center gap-1.5 px-4 py-2 rounded-lg bg-primary text-primary-foreground hover:opacity-90 text-sm disabled:opacity-60"
                      >
                        <Save size={13} /> {saving ? 'Salvando...' : 'Salvar cláusulas'}
                      </button>
                    </div>
                  )}
                </div>

                {/* Variáveis disponíveis */}
                <div className="glass-panel rounded-2xl overflow-hidden">
                  <button
                    type="button"
                    onClick={() => setShowVars(!showVars)}
                    className="w-full flex items-center justify-between px-6 py-4 hover:bg-white/5 transition-colors"
                  >
                    <div className="flex items-center gap-2 text-sm font-medium">
                      <Info size={15} className="text-blue-400" />
                      Variáveis disponíveis para uso nos textos
                    </div>
                    <ChevronRight size={15} className={`text-muted-foreground transition-transform ${showVars ? 'rotate-90' : ''}`} />
                  </button>

                  {showVars && (
                    <div className="px-6 pb-5 grid grid-cols-2 md:grid-cols-3 gap-4 border-t border-border pt-4">
                      {VARIAVEIS.map(({ grupo, vars }) => (
                        <div key={grupo}>
                          <p className="text-xs font-semibold text-muted-foreground mb-1.5">{grupo}</p>
                          <div className="space-y-1">
                            {vars.map((v) => (
                              <code
                                key={v}
                                className="block text-xs bg-muted/60 px-2 py-0.5 rounded text-blue-300 cursor-pointer hover:bg-blue-500/20 transition-colors"
                                onClick={() => {
                                  navigator.clipboard.writeText(v);
                                  toast.success(`${v} copiado!`);
                                }}
                                title="Clique para copiar"
                              >
                                {v}
                              </code>
                            ))}
                          </div>
                        </div>
                      ))}
                      <div className="col-span-full mt-2 p-3 rounded-xl bg-yellow-500/10 border border-yellow-500/20 text-xs text-yellow-300">
                        <strong>Nota:</strong> As variáveis são substituídas automaticamente pelo sistema ao gerar o PDF. Clique em uma variável para copiar.
                        As variáveis funcionam no <strong>texto de abertura</strong> e nas <strong>cláusulas</strong>.
                      </div>
                    </div>
                  )}
                </div>
              </>
            )}
          </div>
        </div>
        </div>
      </main>
    </div>
  );
}
