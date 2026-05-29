import { useEffect, useMemo, useState } from 'react';
import { Edit2, ExternalLink, Link2, Loader2, Plus, Search, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog';

interface ImportantLink {
  id: number;
  title: string;
  url: string;
  category?: string | null;
  description?: string | null;
  sort_order?: number;
  is_active?: boolean;
  created_by?: { id: number; name: string } | null;
}

const emptyForm = {
  title: '',
  url: '',
  category: '',
  description: '',
  sort_order: '0',
  is_active: true,
};

const getCurrentUserRole = () => {
  try {
    const rawUser = localStorage.getItem('user');
    return rawUser ? String(JSON.parse(rawUser)?.role || '') : '';
  } catch {
    return '';
  }
};

export default function ImportantLinks() {
  const [links, setLinks] = useState<ImportantLink[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [search, setSearch] = useState('');
  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState<ImportantLink | null>(null);
  const [formData, setFormData] = useState(emptyForm);
  const [deleteDialog, setDeleteDialog] = useState<{ open: boolean; item: ImportantLink | null }>({ open: false, item: null });
  const role = getCurrentUserRole();
  const canManage = role === 'admin' || role === 'super_admin';

  const loadLinks = async () => {
    try {
      setLoading(true);
      const response = await api.get('/admin/important-links');
      setLinks(Array.isArray(response.data?.data) ? response.data.data : []);
    } catch {
      toast.error('Erro ao carregar links importantes.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    loadLinks();
  }, []);

  const filteredLinks = useMemo(() => {
    const term = search.trim().toLowerCase();
    if (!term) return links;

    return links.filter((item) =>
      `${item.title} ${item.url} ${item.category || ''} ${item.description || ''}`.toLowerCase().includes(term),
    );
  }, [links, search]);

  const groupedLinks = useMemo(() => {
    return filteredLinks.reduce<Record<string, ImportantLink[]>>((groups, item) => {
      const key = item.category?.trim() || 'Geral';
      groups[key] = groups[key] || [];
      groups[key].push(item);
      return groups;
    }, {});
  }, [filteredLinks]);

  const openCreateModal = () => {
    setEditing(null);
    setFormData(emptyForm);
    setModalOpen(true);
  };

  const openEditModal = (item: ImportantLink) => {
    setEditing(item);
    setFormData({
      title: item.title || '',
      url: item.url || '',
      category: item.category || '',
      description: item.description || '',
      sort_order: String(item.sort_order ?? 0),
      is_active: item.is_active ?? true,
    });
    setModalOpen(true);
  };

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!canManage) {
      toast.error('Apenas administradores podem cadastrar links.');
      return;
    }

    const payload = {
      title: formData.title.trim(),
      url: formData.url.trim(),
      category: formData.category.trim() || null,
      description: formData.description.trim() || null,
      sort_order: Number(formData.sort_order) || 0,
      is_active: formData.is_active,
    };

    try {
      setSaving(true);
      if (editing) {
        await api.put(`/admin/important-links/${editing.id}`, payload);
        toast.success('Link atualizado.');
      } else {
        await api.post('/admin/important-links', payload);
        toast.success('Link cadastrado.');
      }
      setModalOpen(false);
      await loadLinks();
    } catch (error: any) {
      const message = error?.response?.data?.message || error?.response?.data?.errors?.url?.[0] || 'Erro ao salvar link.';
      toast.error(message);
    } finally {
      setSaving(false);
    }
  };

  const handleDelete = async () => {
    if (!deleteDialog.item) return;

    try {
      await api.delete(`/admin/important-links/${deleteDialog.item.id}`);
      toast.success('Link excluído.');
      setDeleteDialog({ open: false, item: null });
      await loadLinks();
    } catch {
      toast.error('Erro ao excluir link.');
    }
  };

  return (
    <div className="flex">
      <Sidebar />
      <div className="page-shell">
        <div className="mx-auto max-w-7xl space-y-6">
          <div className="page-header">
            <div>
              <h1 className="page-title">Links importantes</h1>
              <p className="page-subtitle">Acesso rápido da equipe aos links usados no dia a dia do CRM.</p>
            </div>
            {canManage ? (
              <button
                type="button"
                onClick={openCreateModal}
                className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
              >
                <Plus size={18} />
                Novo link
              </button>
            ) : null}
          </div>

          <div className="glass-panel rounded-2xl p-4">
            <div className="relative">
              <Search className="absolute left-4 top-1/2 -translate-y-1/2 text-muted-foreground" size={18} />
              <input
                type="text"
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder="Buscar por título, categoria, descrição ou URL"
                className="h-11 w-full rounded-xl border border-black/10 bg-white pl-11 pr-4 text-sm text-slate-900"
              />
            </div>
          </div>

          {loading ? (
            <div className="flex justify-center py-20">
              <Loader2 className="h-10 w-10 animate-spin text-blue-500" />
            </div>
          ) : filteredLinks.length === 0 ? (
            <div className="glass-panel rounded-2xl p-10 text-center">
              <Link2 size={44} className="mx-auto mb-4 text-muted-foreground opacity-60" />
              <p className="font-semibold text-foreground">Nenhum link cadastrado</p>
              <p className="mt-1 text-sm text-muted-foreground">Quando a admin cadastrar links, eles aparecem aqui para os corretores.</p>
            </div>
          ) : (
            <div className="space-y-6">
              {Object.entries(groupedLinks).map(([category, items]) => (
                <section key={category} className="space-y-3">
                  <h2 className="text-sm font-semibold uppercase tracking-[0.14em] text-muted-foreground">{category}</h2>
                  <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    {items.map((item) => (
                      <div key={item.id} className={`glass-panel rounded-2xl border p-4 ${item.is_active === false ? 'opacity-60' : ''}`}>
                        <div className="flex items-start gap-3">
                          <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-blue-500/15 text-blue-300">
                            <Link2 size={18} />
                          </div>
                          <div className="min-w-0 flex-1">
                            <p className="truncate font-semibold text-foreground">{item.title}</p>
                            <p className="mt-1 break-all text-xs text-muted-foreground">{item.url}</p>
                          </div>
                        </div>
                        {item.description ? (
                          <p className="mt-3 line-clamp-3 text-sm text-muted-foreground">{item.description}</p>
                        ) : null}
                        <div className="mt-4 flex items-center justify-between gap-2">
                          <a
                            href={item.url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center gap-2 rounded-lg border border-border px-3 py-2 text-sm font-medium text-foreground hover:bg-white/10"
                          >
                            <ExternalLink size={15} />
                            Abrir
                          </a>
                          {canManage ? (
                            <div className="flex items-center gap-2">
                              <button
                                type="button"
                                onClick={() => openEditModal(item)}
                                className="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-blue-500/15 text-blue-300 hover:bg-blue-500/25"
                                aria-label="Editar link"
                              >
                                <Edit2 size={15} />
                              </button>
                              <button
                                type="button"
                                onClick={() => setDeleteDialog({ open: true, item })}
                                className="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-red-500/15 text-red-300 hover:bg-red-500/25"
                                aria-label="Excluir link"
                              >
                                <Trash2 size={15} />
                              </button>
                            </div>
                          ) : null}
                        </div>
                      </div>
                    ))}
                  </div>
                </section>
              ))}
            </div>
          )}
        </div>
      </div>

      {modalOpen && canManage ? (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 p-4 backdrop-blur-sm">
          <div className="w-full max-w-lg rounded-2xl border border-white/10 bg-[#0f0f0f] p-6">
            <h2 className="mb-4 text-xl font-bold text-foreground">{editing ? 'Editar link' : 'Novo link'}</h2>
            <form onSubmit={handleSubmit} className="space-y-4">
              <div>
                <label className="mb-1 block text-sm font-medium text-foreground">Título</label>
                <input
                  value={formData.title}
                  onChange={(event) => setFormData({ ...formData, title: event.target.value })}
                  className="w-full rounded-lg border border-white/20 bg-white/10 px-4 py-2 text-foreground"
                  required
                />
              </div>
              <div>
                <label className="mb-1 block text-sm font-medium text-foreground">URL</label>
                <input
                  type="url"
                  value={formData.url}
                  onChange={(event) => setFormData({ ...formData, url: event.target.value })}
                  placeholder="https://..."
                  className="w-full rounded-lg border border-white/20 bg-white/10 px-4 py-2 text-foreground"
                  required
                />
              </div>
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-[1fr_120px]">
                <div>
                  <label className="mb-1 block text-sm font-medium text-foreground">Categoria</label>
                  <input
                    value={formData.category}
                    onChange={(event) => setFormData({ ...formData, category: event.target.value })}
                    placeholder="Ex.: Portais, Documentos"
                    className="w-full rounded-lg border border-white/20 bg-white/10 px-4 py-2 text-foreground"
                  />
                </div>
                <div>
                  <label className="mb-1 block text-sm font-medium text-foreground">Ordem</label>
                  <input
                    type="number"
                    min="0"
                    value={formData.sort_order}
                    onChange={(event) => setFormData({ ...formData, sort_order: event.target.value })}
                    className="w-full rounded-lg border border-white/20 bg-white/10 px-4 py-2 text-foreground"
                  />
                </div>
              </div>
              <div>
                <label className="mb-1 block text-sm font-medium text-foreground">Descrição</label>
                <textarea
                  value={formData.description}
                  onChange={(event) => setFormData({ ...formData, description: event.target.value })}
                  rows={3}
                  className="w-full rounded-lg border border-white/20 bg-white/10 px-4 py-2 text-foreground"
                />
              </div>
              <label className="flex items-center gap-2 text-sm text-foreground">
                <input
                  type="checkbox"
                  checked={formData.is_active}
                  onChange={(event) => setFormData({ ...formData, is_active: event.target.checked })}
                  className="rounded"
                />
                Link ativo para corretores
              </label>
              <div className="flex gap-2 pt-2">
                <button
                  type="button"
                  onClick={() => setModalOpen(false)}
                  className="flex-1 rounded-lg bg-white/10 px-4 py-2 text-foreground hover:bg-white/20"
                >
                  Cancelar
                </button>
                <button
                  type="submit"
                  disabled={saving}
                  className="flex-1 rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700 disabled:opacity-60"
                >
                  {saving ? 'Salvando...' : 'Salvar'}
                </button>
              </div>
            </form>
          </div>
        </div>
      ) : null}

      <AlertDialog open={deleteDialog.open} onOpenChange={(open) => setDeleteDialog((current) => ({ ...current, open }))}>
        <AlertDialogContent className="border border-white/10 bg-[#0f0f0f]">
          <AlertDialogHeader>
            <AlertDialogTitle>Excluir link</AlertDialogTitle>
            <AlertDialogDescription>
              Tem certeza que deseja excluir <strong>{deleteDialog.item?.title}</strong>?
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel className="border-white/20 hover:bg-white/10">Cancelar</AlertDialogCancel>
            <AlertDialogAction onClick={handleDelete} className="bg-red-600 hover:bg-red-700">
              Excluir
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
