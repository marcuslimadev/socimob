import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { ChevronDown, ChevronUp, Plus, Trash2 } from 'lucide-react';
import { api } from '@/lib/api';

interface VistoriaFoto {
  id: number;
  comodo: string;
  descricao?: string;
  url_signed?: string;
  created_at: string;
}

interface VistoriaRecord {
  id: number;
  tipo: string;
  status: string;
  data_vistoria?: string | null;
  vistoriadores?: string[] | null;
  observacoes?: string | null;
  fotos?: VistoriaFoto[];
}

interface Props {
  contratoId: number;
  onClose: () => void;
}

const TIPO_LABELS: Record<string, string> = {
  entrada: 'Entrada',
  saida: 'Saída',
  periodica: 'Periódica',
};

const STATUS_COLORS: Record<string, string> = {
  solicitada: 'bg-amber-500/20 text-amber-300 border-amber-500/40',
  designada:  'bg-blue-500/20 text-blue-300 border-blue-500/40',
  andamento:  'bg-purple-500/20 text-purple-300 border-purple-500/40',
  concluida:  'bg-green-500/20 text-green-300 border-green-500/40',
  cancelada:  'bg-red-500/20 text-red-300 border-red-500/40',
};

const COMODOS = [
  'Entrada', 'Sala', 'Cozinha', 'Área de serviço', 'Banheiro',
  'Quarto', 'Quarto 2', 'Quarto 3', 'Varanda', 'Garagem', 'Área externa', 'Outro',
];

export default function VistoriaGaleria({ contratoId, onClose }: Props) {
  const [vistorias, setVistorias] = useState<VistoriaRecord[]>([]);
  const [loading, setLoading] = useState(true);
  const [expandedId, setExpandedId] = useState<number | null>(null);
  const [loadingFotos, setLoadingFotos] = useState<Record<number, boolean>>({});
  const [uploading, setUploading] = useState(false);
  const [fotoForm, setFotoForm] = useState({ comodo: 'Sala', descricao: '' });
  const fileRef = useRef<HTMLInputElement>(null);

  // Nova vistoria form
  const [showNewForm, setShowNewForm] = useState(false);
  const [saving, setSaving] = useState(false);
  const [newForm, setNewForm] = useState({
    tipo: 'entrada',
    status: 'solicitada',
    data_vistoria: '',
    vistoriador: '',
    observacoes: '',
  });

  const loadVistorias = async () => {
    setLoading(true);
    try {
      const { data } = await api.get(`/admin/financeiro/contratos/${contratoId}/vistorias`);
      setVistorias(data.items ?? []);
    } catch {
      toast.error('Erro ao carregar vistorias.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { loadVistorias(); }, [contratoId]);

  const loadFotos = async (vistoriaId: number) => {
    setLoadingFotos((p) => ({ ...p, [vistoriaId]: true }));
    try {
      const { data } = await api.get(`/admin/vistorias/${vistoriaId}/fotos`);
      setVistorias((prev) =>
        prev.map((v) => v.id === vistoriaId ? { ...v, fotos: data.items ?? [] } : v),
      );
    } catch {
      toast.error('Erro ao carregar fotos.');
    } finally {
      setLoadingFotos((p) => ({ ...p, [vistoriaId]: false }));
    }
  };

  const toggleExpand = (id: number) => {
    if (expandedId === id) {
      setExpandedId(null);
    } else {
      setExpandedId(id);
      const v = vistorias.find((x) => x.id === id);
      if (!v?.fotos) loadFotos(id);
    }
  };

  const handleCreateVistoria = async (e: React.FormEvent) => {
    e.preventDefault();
    setSaving(true);
    try {
      const payload: Record<string, unknown> = {
        tipo: newForm.tipo,
        status: newForm.status,
      };
      if (newForm.data_vistoria) payload.data_vistoria = newForm.data_vistoria;
      if (newForm.vistoriador) payload.vistoriadores = [newForm.vistoriador];
      if (newForm.observacoes) payload.observacoes = newForm.observacoes;
      await api.post(`/admin/financeiro/contratos/${contratoId}/vistorias`, payload);
      toast.success('Vistoria criada com sucesso.');
      setShowNewForm(false);
      setNewForm({ tipo: 'entrada', status: 'solicitada', data_vistoria: '', vistoriador: '', observacoes: '' });
      loadVistorias();
    } catch (err: any) {
      toast.error(err?.response?.data?.message || 'Erro ao criar vistoria.');
    } finally {
      setSaving(false);
    }
  };

  const handleDeleteVistoria = async (id: number) => {
    if (!confirm('Excluir esta vistoria e todas as fotos?')) return;
    try {
      await api.delete(`/admin/financeiro/contratos/${contratoId}/vistorias/${id}`);
      toast.success('Vistoria excluída.');
      setVistorias((p) => p.filter((v) => v.id !== id));
      if (expandedId === id) setExpandedId(null);
    } catch {
      toast.error('Erro ao excluir vistoria.');
    }
  };

  const handleUploadFoto = async (e: React.FormEvent, vistoriaId: number) => {
    e.preventDefault();
    const files = fileRef.current?.files;
    if (!files || files.length === 0) { toast.error('Selecione pelo menos uma foto.'); return; }
    setUploading(true);
    let uploaded = 0;
    try {
      for (const file of Array.from(files)) {
        const fd = new FormData();
        fd.append('foto', file);
        fd.append('comodo', fotoForm.comodo);
        if (fotoForm.descricao) fd.append('descricao', fotoForm.descricao);
        await api.post(`/admin/vistorias/${vistoriaId}/fotos`, fd, {
          headers: { 'Content-Type': 'multipart/form-data' },
        });
        uploaded++;
      }
      toast.success(`${uploaded} foto(s) enviada(s).`);
      if (fileRef.current) fileRef.current.value = '';
      setFotoForm((f) => ({ ...f, descricao: '' }));
      loadFotos(vistoriaId);
    } catch (err: any) {
      toast.error(err?.response?.data?.message || 'Erro ao enviar fotos.');
    } finally {
      setUploading(false);
    }
  };

  const handleDeleteFoto = async (vistoriaId: number, fotoId: number) => {
    if (!confirm('Remover esta foto?')) return;
    try {
      await api.delete(`/admin/vistorias/${vistoriaId}/fotos/${fotoId}`);
      toast.success('Foto removida.');
      setVistorias((prev) =>
        prev.map((v) =>
          v.id === vistoriaId
            ? { ...v, fotos: v.fotos?.filter((f) => f.id !== fotoId) }
            : v,
        ),
      );
    } catch {
      toast.error('Erro ao remover foto.');
    }
  };

  const formatDate = (d?: string | null) =>
    d ? new Date(d).toLocaleDateString('pt-BR') : '—';

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div className="glass-panel rounded-2xl p-6 w-full max-w-3xl mx-4 max-h-[90vh] flex flex-col">
        {/* Header */}
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-semibold">Vistorias do Contrato</h2>
          <div className="flex items-center gap-3">
            <button
              type="button"
              onClick={() => setShowNewForm((v) => !v)}
              className="flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-primary text-primary-foreground text-sm"
            >
              <Plus size={15} /> Nova Vistoria
            </button>
            <button type="button" onClick={onClose} className="text-muted-foreground hover:text-foreground text-xl leading-none">&times;</button>
          </div>
        </div>

        {/* New vistoria form */}
        {showNewForm && (
          <form onSubmit={handleCreateVistoria} className="p-4 bg-muted/50 rounded-xl mb-4 space-y-3">
            <p className="text-sm font-semibold">Nova Vistoria</p>
            <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
              <div>
                <label className="block text-xs font-medium mb-1">Tipo</label>
                <select
                  value={newForm.tipo}
                  onChange={(e) => setNewForm((f) => ({ ...f, tipo: e.target.value }))}
                  className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm"
                >
                  <option value="entrada">Entrada</option>
                  <option value="saida">Saída</option>
                  <option value="periodica">Periódica</option>
                </select>
              </div>
              <div>
                <label className="block text-xs font-medium mb-1">Status</label>
                <select
                  value={newForm.status}
                  onChange={(e) => setNewForm((f) => ({ ...f, status: e.target.value }))}
                  className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm"
                >
                  <option value="solicitada">Solicitada</option>
                  <option value="designada">Designada</option>
                  <option value="andamento">Em andamento</option>
                  <option value="concluida">Concluída</option>
                  <option value="cancelada">Cancelada</option>
                </select>
              </div>
              <div>
                <label className="block text-xs font-medium mb-1">Data</label>
                <input
                  type="date"
                  value={newForm.data_vistoria}
                  onChange={(e) => setNewForm((f) => ({ ...f, data_vistoria: e.target.value }))}
                  className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm"
                />
              </div>
              <div>
                <label className="block text-xs font-medium mb-1">Vistoriador</label>
                <input
                  type="text"
                  value={newForm.vistoriador}
                  onChange={(e) => setNewForm((f) => ({ ...f, vistoriador: e.target.value }))}
                  placeholder="Nome"
                  className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm"
                />
              </div>
            </div>
            <div>
              <label className="block text-xs font-medium mb-1">Observações</label>
              <textarea
                value={newForm.observacoes}
                onChange={(e) => setNewForm((f) => ({ ...f, observacoes: e.target.value }))}
                rows={2}
                className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm resize-none"
              />
            </div>
            <div className="flex items-center gap-2">
              <button type="submit" disabled={saving}
                className="px-4 py-2 rounded-lg bg-primary text-primary-foreground text-sm disabled:opacity-60">
                {saving ? 'Salvando...' : 'Criar Vistoria'}
              </button>
              <button type="button" onClick={() => setShowNewForm(false)}
                className="px-4 py-2 rounded-lg border border-border text-sm hover:bg-accent">
                Cancelar
              </button>
            </div>
          </form>
        )}

        {/* Vistorias list */}
        <div className="flex-1 overflow-y-auto space-y-3">
          {loading ? (
            <p className="text-sm text-center text-muted-foreground py-8">Carregando...</p>
          ) : vistorias.length === 0 ? (
            <p className="text-sm text-center text-muted-foreground py-8">Nenhuma vistoria registrada neste contrato.</p>
          ) : (
            vistorias.map((v) => (
              <div key={v.id} className="border border-border rounded-xl overflow-hidden">
                {/* Row */}
                <div className="flex items-center gap-3 px-4 py-3">
                  <span className="text-sm font-medium">{TIPO_LABELS[v.tipo] ?? v.tipo}</span>
                  <span className={`text-xs px-2 py-0.5 rounded-full border ${STATUS_COLORS[v.status] ?? 'bg-muted text-muted-foreground border-border'}`}>{v.status}</span>
                  <span className="text-xs text-muted-foreground">{formatDate(v.data_vistoria)}</span>
                  {v.vistoriadores?.length ? (
                    <span className="text-xs text-muted-foreground hidden md:block">Vistoriador: {v.vistoriadores.join(', ')}</span>
                  ) : null}
                  <div className="ml-auto flex items-center gap-2">
                    <button
                      type="button"
                      onClick={() => handleDeleteVistoria(v.id)}
                      className="text-red-400 hover:text-red-300"
                      title="Excluir vistoria"
                    >
                      <Trash2 size={14} />
                    </button>
                    <button
                      type="button"
                      onClick={() => toggleExpand(v.id)}
                      className="flex items-center gap-1 text-xs text-primary hover:underline"
                    >
                      {expandedId === v.id ? <ChevronUp size={14} /> : <ChevronDown size={14} />}
                      {expandedId === v.id ? 'Ocultar fotos' : `Fotos${v.fotos ? ` (${v.fotos.length})` : ''}`}
                    </button>
                  </div>
                </div>

                {/* Photos panel */}
                {expandedId === v.id && (
                  <div className="border-t border-border p-4 bg-muted/30">
                    {/* Upload form */}
                    <form onSubmit={(e) => handleUploadFoto(e, v.id)} className="mb-4 p-3 bg-background rounded-xl space-y-2">
                      <p className="text-xs font-semibold">Adicionar fotos</p>
                      <div className="grid grid-cols-1 md:grid-cols-3 gap-2">
                        <div>
                          <label className="block text-xs mb-1">Cômodo</label>
                          <select
                            value={fotoForm.comodo}
                            onChange={(e) => setFotoForm((f) => ({ ...f, comodo: e.target.value }))}
                            className="w-full bg-muted border border-border rounded-lg px-2 py-1.5 text-sm"
                          >
                            {COMODOS.map((c) => <option key={c} value={c}>{c}</option>)}
                          </select>
                        </div>
                        <div>
                          <label className="block text-xs mb-1">Descrição</label>
                          <input
                            type="text"
                            value={fotoForm.descricao}
                            onChange={(e) => setFotoForm((f) => ({ ...f, descricao: e.target.value }))}
                            placeholder="Ex: Parede com umidade"
                            className="w-full bg-muted border border-border rounded-lg px-2 py-1.5 text-sm"
                          />
                        </div>
                        <div>
                          <label className="block text-xs mb-1">Fotos (JPG/PNG)</label>
                          <input ref={fileRef} type="file" accept="image/jpeg,image/png,image/webp" multiple className="w-full text-xs" />
                        </div>
                      </div>
                      <button
                        type="submit"
                        disabled={uploading}
                        className="px-3 py-1.5 rounded-lg bg-primary text-primary-foreground text-xs disabled:opacity-60"
                      >
                        {uploading ? 'Enviando...' : 'Enviar fotos'}
                      </button>
                    </form>

                    {/* Photo grid */}
                    {loadingFotos[v.id] ? (
                      <p className="text-xs text-muted-foreground text-center py-4">Carregando fotos...</p>
                    ) : !v.fotos || v.fotos.length === 0 ? (
                      <p className="text-xs text-muted-foreground text-center py-4">Nenhuma foto.</p>
                    ) : (
                      <div className="grid grid-cols-2 md:grid-cols-4 gap-2">
                        {v.fotos.map((foto) => (
                          <div key={foto.id} className="relative group rounded-lg overflow-hidden border border-border bg-muted/30">
                            {foto.url_signed ? (
                              <a href={foto.url_signed} target="_blank" rel="noreferrer">
                                <img
                                  src={foto.url_signed}
                                  alt={foto.descricao || foto.comodo}
                                  className="w-full h-28 object-cover"
                                />
                              </a>
                            ) : (
                              <div className="w-full h-28 flex items-center justify-center bg-muted text-xs text-muted-foreground">Sem preview</div>
                            )}
                            <div className="p-1.5 text-xs">
                              <p className="font-medium">{foto.comodo}</p>
                              {foto.descricao && <p className="text-muted-foreground truncate">{foto.descricao}</p>}
                            </div>
                            <button
                              type="button"
                              onClick={() => handleDeleteFoto(v.id, foto.id)}
                              className="absolute top-1 right-1 bg-red-600 text-white rounded-full w-5 h-5 text-xs flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
                              title="Remover"
                            >×</button>
                          </div>
                        ))}
                      </div>
                    )}
                  </div>
                )}
              </div>
            ))
          )}
        </div>

        <div className="flex justify-end mt-4 pt-4 border-t border-border">
          <button type="button" onClick={onClose} className="px-4 py-2 rounded-lg border border-border hover:bg-accent text-sm">Fechar</button>
        </div>
      </div>
    </div>
  );
}
