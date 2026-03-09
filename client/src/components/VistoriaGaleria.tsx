import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { api } from '@/lib/api';

interface VistoriaFoto {
  id: number;
  comodo: string;
  descricao?: string;
  url?: string;
  created_at: string;
}

interface Props {
  contratoId: number;
  onClose: () => void;
}

const COMODOS = [
  'Entrada', 'Sala', 'Cozinha', 'Área de serviço', 'Banheiro', 'Quarto',
  'Quarto 2', 'Quarto 3', 'Varanda', 'Garagem', 'Área externa', 'Outro',
];

export default function VistoriaGaleria({ contratoId, onClose }: Props) {
  const [fotos, setFotos] = useState<VistoriaFoto[]>([]);
  const [loading, setLoading] = useState(true);
  const [uploading, setUploading] = useState(false);
  const [form, setForm] = useState({ comodo: 'Sala', descricao: '' });
  const [filtroComodo, setFiltroComodo] = useState('');
  const fileRef = useRef<HTMLInputElement>(null);

  const loadFotos = async () => {
    setLoading(true);
    try {
      const { data } = await api.get(`/admin/financeiro/contratos/${contratoId}/vistorias`);
      setFotos(Array.isArray(data) ? data : data.data ?? []);
    } catch {
      toast.error('Erro ao carregar fotos.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { loadFotos(); }, [contratoId]);

  const handleUpload = async (e: React.FormEvent) => {
    e.preventDefault();
    const files = fileRef.current?.files;
    if (!files || files.length === 0) {
      toast.error('Selecione pelo menos uma foto.');
      return;
    }
    setUploading(true);
    let uploaded = 0;
    try {
      for (const file of Array.from(files)) {
        const fd = new FormData();
        fd.append('foto', file);
        fd.append('comodo', form.comodo);
        if (form.descricao) fd.append('descricao', form.descricao);
        await api.post(`/admin/financeiro/contratos/${contratoId}/vistorias`, fd, {
          headers: { 'Content-Type': 'multipart/form-data' },
        });
        uploaded++;
      }
      toast.success(`${uploaded} foto(s) enviada(s) com sucesso.`);
      if (fileRef.current) fileRef.current.value = '';
      setForm((f) => ({ ...f, descricao: '' }));
      loadFotos();
    } catch (err: any) {
      toast.error(err?.response?.data?.message || 'Erro ao enviar fotos.');
    } finally {
      setUploading(false);
    }
  };

  const handleDelete = async (id: number) => {
    if (!confirm('Remover esta foto?')) return;
    try {
      await api.delete(`/admin/financeiro/contratos/${contratoId}/vistorias/${id}`);
      toast.success('Foto removida.');
      setFotos((prev) => prev.filter((f) => f.id !== id));
    } catch {
      toast.error('Erro ao remover foto.');
    }
  };

  const comodosList = Array.from(new Set(fotos.map((f) => f.comodo))).sort();
  const fotosFiltradas = filtroComodo ? fotos.filter((f) => f.comodo === filtroComodo) : fotos;

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div className="glass-panel rounded-2xl p-6 w-full max-w-4xl mx-4 max-h-[90vh] overflow-y-auto">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-semibold">Vistoria — Fotos do Contrato</h2>
          <button type="button" onClick={onClose} className="text-muted-foreground hover:text-foreground text-xl leading-none">&times;</button>
        </div>

        {/* Upload */}
        <form onSubmit={handleUpload} className="p-4 bg-muted/50 rounded-xl mb-5 space-y-3">
          <h3 className="text-sm font-semibold">Adicionar fotos</h3>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-3">
            <div>
              <label className="block text-xs font-medium mb-1">Cômodo</label>
              <select
                value={form.comodo}
                onChange={(e) => setForm((f) => ({ ...f, comodo: e.target.value }))}
                className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm"
              >
                {COMODOS.map((c) => <option key={c} value={c}>{c}</option>)}
              </select>
            </div>
            <div>
              <label className="block text-xs font-medium mb-1">Descrição (opcional)</label>
              <input
                type="text"
                value={form.descricao}
                onChange={(e) => setForm((f) => ({ ...f, descricao: e.target.value }))}
                placeholder="Ex: Parede com umidade"
                className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm"
              />
            </div>
            <div>
              <label className="block text-xs font-medium mb-1">Fotos (JPG/PNG)</label>
              <input
                ref={fileRef}
                type="file"
                accept="image/jpeg,image/png,image/webp"
                multiple
                className="w-full text-sm"
              />
            </div>
          </div>
          <button
            type="submit"
            disabled={uploading}
            className="px-4 py-2 rounded-lg bg-primary text-primary-foreground text-sm disabled:opacity-60"
          >
            {uploading ? 'Enviando...' : 'Enviar fotos'}
          </button>
        </form>

        {/* Filtro por cômodo */}
        {comodosList.length > 1 && (
          <div className="flex flex-wrap gap-2 mb-4">
            <button
              type="button"
              onClick={() => setFiltroComodo('')}
              className={`px-3 py-1 rounded-full text-xs border ${!filtroComodo ? 'bg-primary text-primary-foreground border-primary' : 'border-border hover:bg-accent'}`}
            >
              Todos ({fotos.length})
            </button>
            {comodosList.map((c) => (
              <button
                key={c}
                type="button"
                onClick={() => setFiltroComodo(c)}
                className={`px-3 py-1 rounded-full text-xs border ${filtroComodo === c ? 'bg-primary text-primary-foreground border-primary' : 'border-border hover:bg-accent'}`}
              >
                {c} ({fotos.filter((f) => f.comodo === c).length})
              </button>
            ))}
          </div>
        )}

        {/* Galeria */}
        {loading ? (
          <p className="text-sm text-muted-foreground text-center py-8">Carregando...</p>
        ) : fotosFiltradas.length === 0 ? (
          <p className="text-sm text-muted-foreground text-center py-8">Nenhuma foto encontrada.</p>
        ) : (
          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
            {fotosFiltradas.map((foto) => (
              <div key={foto.id} className="relative group rounded-xl overflow-hidden border border-border bg-muted/30">
                {foto.url ? (
                  <a href={foto.url} target="_blank" rel="noreferrer">
                    <img
                      src={foto.url}
                      alt={foto.descricao || foto.comodo}
                      className="w-full h-36 object-cover"
                    />
                  </a>
                ) : (
                  <div className="w-full h-36 flex items-center justify-center bg-muted text-xs text-muted-foreground">
                    Sem preview
                  </div>
                )}
                <div className="p-2 text-xs">
                  <p className="font-medium">{foto.comodo}</p>
                  {foto.descricao && <p className="text-muted-foreground truncate">{foto.descricao}</p>}
                </div>
                <button
                  type="button"
                  onClick={() => handleDelete(foto.id)}
                  className="absolute top-1 right-1 bg-red-600 text-white rounded-full w-5 h-5 text-xs flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
                  title="Remover"
                >
                  ×
                </button>
              </div>
            ))}
          </div>
        )}

        <div className="flex justify-end mt-4 pt-4 border-t border-border">
          <button type="button" onClick={onClose} className="px-4 py-2 rounded-lg border border-border hover:bg-accent text-sm">
            Fechar
          </button>
        </div>
      </div>
    </div>
  );
}
