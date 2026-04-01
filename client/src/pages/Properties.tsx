import { useState, useEffect, useMemo, useRef, useCallback } from 'react';
import {
  Search, Plus, Eye, Pencil, Trash2, RefreshCw, Download,
  Star, Globe, Loader2, X, ChevronUp, ChevronDown, ChevronsUpDown, Zap, MessageCircle,
} from 'lucide-react';
import { useLocation } from 'wouter';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';
import { toast } from 'sonner';

interface ImovelRow {
  id: string;
  codigo: string;
  referencia: string;
  titulo: string;
  tipo: string;
  finalidade: string;
  preco: number;
  dormitorios: number;
  banheiros: number;
  area: number;
  localizacao: string;
  captador_nome: string | null;
  inserido_por_nome: string | null;
  proprietario_nome: string | null;
  proprietario_telefone: string | null;
  imobi_brasil_sent: boolean;
  imagem: string;
  ativo: boolean;
  exibir: boolean;
  destaque: boolean;
  created_at: string;
  updated_at: string;
  deleted_at?: string | null;
  trash_source?: string | null;
  trash_reason?: string | null;
}

type SortField = 'codigo' | 'referencia' | 'titulo' | 'tipo' | 'finalidade' | 'preco' | 'dormitorios' | 'area' | 'localizacao';
type ViewMode = 'active' | 'trash';

const formatMoney = (value: number) =>
  Number(value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 0 });

const formatDateTime = (value?: string | null) => {
  if (!value) return '—';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '—';
  return date.toLocaleString('pt-BR', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  });
};

const normalizeWhatsAppPhone = (phone?: string | null) => {
  const digits = String(phone || '').replace(/\D/g, '');
  if (!digits) return '';
  if (digits.length === 10 || digits.length === 11) {
    return `55${digits}`;
  }
  return digits;
};

const buildAvailabilityCheckMessage = (property: ImovelRow) => {
  const finalidade = property.finalidade === 'aluguel' ? 'aluguel' : 'venda';
  return [
    'Olá, tudo bem?',
    `Estou conferindo a disponibilidade do imóvel ${property.codigo}${property.titulo ? ` - ${property.titulo}` : ''}.`,
    `Ele ainda está disponível para ${finalidade}?`,
  ].join(' ');
};

const tipoLabel = (tipo: string) => {
  const map: Record<string, string> = {
    apartamento: 'Apartamento',
    casa: 'Casa',
    comercial: 'Comercial',
    terreno: 'Terreno',
  };
  return map[tipo] || tipo || '-';
};

type AdsStatus = 'ACTIVE' | 'PUBLISHING' | 'PAUSED' | 'ERROR' | null;

export default function Properties() {
  const [, setLocation] = useLocation();
  const currentUser = (() => { try { return JSON.parse(localStorage.getItem('user') || '{}'); } catch { return {}; } })();
  const isTrainee = currentUser?.role === 'trainee';
  const [imoveis, setImoveis] = useState<ImovelRow[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isExporting, setIsExporting] = useState(false);
  const [isSyncing, setIsSyncing] = useState(false);
  const [viewMode, setViewMode] = useState<ViewMode>('active');
  const [busca, setBusca] = useState('');
  const [tipoFiltro, setTipoFiltro] = useState('todos');
  const [finalidadeFiltro, setFinalidadeFiltro] = useState('todos');
  const [portalFiltro, setPortalFiltro] = useState('todos');
  const [destaqueFiltro, setDestaqueFiltro] = useState('todos');
  const [atualizacaoFiltro, setAtualizacaoFiltro] = useState('todos');
  const [pagina, setPagina] = useState(1);
  const [togglingId, setTogglingId] = useState<string | null>(null);
  const [sortField, setSortField] = useState<SortField | null>(null);
  const [sortDir, setSortDir] = useState<'asc' | 'desc'>('asc');
  const [itensPorPagina, setItensPorPagina] = useState(5);
  const [adsStatusMap, setAdsStatusMap] = useState<Record<string, AdsStatus>>({});
  const [previewId, setPreviewId] = useState<string | null>(null);
  const searchRef = useRef<HTMLInputElement>(null);

  const fetchImoveis = async (scope: ViewMode = viewMode) => {
    setIsLoading(true);
    try {
      const response = await api.get('/imoveis', {
        params: {
          per_page: 'all',
          scope: scope === 'trash' ? 'trash' : 'active',
        },
      });
      const rows = Array.isArray(response.data?.data) ? response.data.data : [];
      setImoveis(
        rows.map((item: any) => ({
          id: String(item.id),
          codigo: item.codigo_imovel || item.codigo || '-',
          referencia: item.referencia_imovel || '-',
          titulo: item.titulo || item.codigo_imovel || 'Sem título',
          tipo: (item.tipo_imovel || '').toLowerCase(),
          finalidade: (item.finalidade_imovel || 'venda').toLowerCase().includes('aluguel')
            ? 'aluguel'
            : 'venda',
          preco: parseFloat(item.valor_venda) || 0,
          dormitorios: parseInt(item.dormitorios) || 0,
          banheiros: parseInt(item.banheiros) || 0,
          area: parseFloat(item.area_total) || 0,
          localizacao: [item.bairro, item.cidade].filter(Boolean).join(', ') || '-',
          captador_nome: item.captador_nome || null,
          inserido_por_nome: item.inserido_por_nome || null,
          proprietario_nome: item.proprietario_nome || null,
          proprietario_telefone: item.proprietario_telefone || null,
          imobi_brasil_sent: Boolean(item.imobi_brasil_sent),
          created_at: item.created_at || '',
          updated_at: item.updated_at || item.created_at || '',
          imagem:
            Array.isArray(item.imagens) && item.imagens.length > 0
              ? item.imagens[0]
              : item.imagem_destaque || item.foto_capa || '',
          ativo: Boolean(item.active),
          exibir: Boolean(item.exibir_imovel),
          destaque: Boolean(item.destaque),
          deleted_at: item.deleted_at || null,
          trash_source: item.trash_source || null,
          trash_reason: item.trash_reason || null,
        })),
      );
    } catch {
      toast.error('Erro ao carregar imóveis');
    } finally {
      setIsLoading(false);
    }
  };

  const handleOpenPortalPreview = (property: ImovelRow) => {
    setPreviewId(property.id);
  };

  useEffect(() => {
    fetchImoveis(viewMode);
  }, [viewMode]);

  useEffect(() => {
    setPagina(1);
  }, [busca, tipoFiltro, finalidadeFiltro, portalFiltro, destaqueFiltro, atualizacaoFiltro, sortField, sortDir, itensPorPagina]);

  const fetchAdsStatuses = useCallback(async (ids: string[]) => {
    if (ids.length === 0) return;
    const results = await Promise.allSettled(
      ids.map((id) => api.get(`/listings/${id}/ads/status`)),
    );
    const map: Record<string, AdsStatus> = {};
    results.forEach((r, i) => {
      if (r.status === 'fulfilled') {
        const statuses: { provider: string; publish_status: string }[] =
          r.value.data?.data ?? [];
        // Pick the "best" status across providers: ACTIVE > PUBLISHING > ERROR > PAUSED
        const priority: Record<string, number> = { ACTIVE: 4, PUBLISHING: 3, ERROR: 2, PAUSED: 1 };
        let best: AdsStatus = null;
        statuses.forEach((s) => {
          const status = s.publish_status as AdsStatus;
          if (status && (best === null || (priority[status] ?? 0) > (priority[best] ?? 0))) {
            best = status;
          }
        });
        map[ids[i]] = best;
      }
    });
    setAdsStatusMap((prev) => ({ ...prev, ...map }));
  }, []);

  const handleSort = (field: SortField) => {
    if (sortField === field) {
      setSortDir((d) => (d === 'asc' ? 'desc' : 'asc'));
    } else {
      setSortField(field);
      setSortDir('asc');
    }
  };

  const imoveisFiltrados = useMemo(() => {
    const termo = busca.trim().toLowerCase();
    const staleCutoff = new Date();
    staleCutoff.setDate(staleCutoff.getDate() - 30);

    return imoveis.filter((im) => {
      if (
        termo &&
        !`${im.titulo} ${im.codigo} ${im.referencia} ${im.localizacao}`
          .toLowerCase()
          .includes(termo)
      )
        return false;
      if (tipoFiltro !== 'todos' && im.tipo !== tipoFiltro) return false;
      if (finalidadeFiltro !== 'todos' && im.finalidade !== finalidadeFiltro) return false;
      if (portalFiltro === 'publicado' && !im.exibir) return false;
      if (portalFiltro === 'oculto' && im.exibir) return false;
      if (destaqueFiltro === 'sim' && !im.destaque) return false;
      if (destaqueFiltro === 'nao' && im.destaque) return false;
      if (atualizacaoFiltro === 'sem_30_dias') {
        if (!im.updated_at) return true;
        const updatedAt = new Date(im.updated_at);
        if (!Number.isNaN(updatedAt.getTime()) && updatedAt > staleCutoff) return false;
      }
      return true;
    });
  }, [imoveis, busca, tipoFiltro, finalidadeFiltro, portalFiltro, destaqueFiltro, atualizacaoFiltro]);

  const imoveisOrdenados = useMemo(() => {
    if (!sortField) {
      return [...imoveisFiltrados].sort((a, b) =>
        b.updated_at.localeCompare(a.updated_at),
      );
    }
    return [...imoveisFiltrados].sort((a, b) => {
      const av = a[sortField];
      const bv = b[sortField];
      if (typeof av === 'number' && typeof bv === 'number') {
        return sortDir === 'asc' ? av - bv : bv - av;
      }
      return sortDir === 'asc'
        ? String(av).localeCompare(String(bv), 'pt-BR')
        : String(bv).localeCompare(String(av), 'pt-BR');
    });
  }, [imoveisFiltrados, sortField, sortDir]);

  const totalPaginas = Math.max(1, Math.ceil(imoveisOrdenados.length / itensPorPagina));

  const imoveisPaginados = useMemo(() => {
    const start = (pagina - 1) * itensPorPagina;
    return imoveisOrdenados.slice(start, start + itensPorPagina);
  }, [imoveisOrdenados, pagina]);

  // Fetch ads statuses for visible listings whenever the page changes
  useEffect(() => {
    const ids = imoveisPaginados
      .map((im) => im.id)
      .filter((id) => !(id in adsStatusMap));
    fetchAdsStatuses(ids);
  }, [imoveisPaginados]); // eslint-disable-line react-hooks/exhaustive-deps

  const AdsStatusBadge = ({ id }: { id: string }) => {
    const status = adsStatusMap[id];
    if (!status) return null;
    const map: Record<NonNullable<AdsStatus>, { label: string; cls: string }> = {
      ACTIVE:     { label: 'Ativo',       cls: 'bg-emerald-100 text-emerald-800' },
      PUBLISHING: { label: 'Publicando',  cls: 'bg-blue-100 text-blue-800' },
      PAUSED:     { label: 'Pausado',     cls: 'bg-amber-100 text-amber-800' },
      ERROR:      { label: 'Erro',        cls: 'bg-red-100 text-red-800' },
    };
    const cfg = map[status];
    return (
      <span className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium ${cfg.cls}`}>
        <Zap size={9} />
        {cfg.label}
      </span>
    );
  };

  const handleToggle = async (id: string, field: 'exibir' | 'destaque', current: boolean) => {
    setTogglingId(id);
    try {
      const body = field === 'exibir' ? { exibir_imovel: !current } : { destaque: !current };
      await api.put(`/imoveis/${id}`, body);
      setImoveis((prev) =>
        prev.map((im) => (im.id === id ? { ...im, [field]: !current } : im)),
      );
      toast.success(
        field === 'exibir'
          ? !current
            ? 'Publicado no portal'
            : 'Ocultado do portal'
          : !current
            ? 'Marcado como destaque'
            : 'Removido dos destaques',
      );
    } catch (error: any) {
      toast.error(error?.response?.data?.error || 'Erro ao atualizar imóvel');
    } finally {
      setTogglingId(null);
    }
  };

  const handleDelete = async (im: ImovelRow) => {
    if (!window.confirm(`Mover o imóvel ${im.codigo} para a lixeira?`)) return;
    try {
      await api.delete(`/imoveis/${im.id}`);
      toast.success('Imóvel movido para a lixeira');
      await fetchImoveis(viewMode);
    } catch (error: any) {
      toast.error(error?.response?.data?.error || 'Erro ao excluir imóvel');
    }
  };

  const handleRestore = async (im: ImovelRow) => {
    if (!window.confirm(`Restaurar o imóvel ${im.codigo} da lixeira?`)) return;
    try {
      await api.post(`/imoveis/${im.id}/restore`);
      toast.success('Imóvel restaurado');
      await fetchImoveis(viewMode);
    } catch (error: any) {
      toast.error(error?.response?.data?.error || 'Erro ao restaurar imóvel');
    }
  };

  const handleForceDelete = async (im: ImovelRow) => {
    if (!window.confirm(`Excluir definitivamente o imóvel ${im.codigo}? Fotos e vídeos locais serão apagados.`)) return;
    try {
      await api.delete(`/imoveis/${im.id}/force`);
      toast.success('Imóvel removido definitivamente');
      await fetchImoveis(viewMode);
    } catch (error: any) {
      toast.error(error?.response?.data?.error || 'Erro ao excluir definitivamente');
    }
  };

  const handleSync = async () => {
    setIsSyncing(true);
    try {
      const response = await api.get('/properties/sync');
      if (response.data?.success) {
        toast.success('Imóveis sincronizados');
        await fetchImoveis(viewMode);
      } else {
        toast.error(response.data?.error || 'Erro ao sincronizar');
      }
    } catch {
      toast.error('Erro ao sincronizar imóveis');
    } finally {
      setIsSyncing(false);
    }
  };

  const handleExport = async () => {
    setIsExporting(true);
    try {
      const response = await api.get('/imoveis/export', { responseType: 'blob' });
      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.download = `imoveis_${new Date().toISOString().slice(0, 10)}.csv`;
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
      toast.success('CSV exportado');
    } catch {
      toast.error('Erro ao exportar');
    } finally {
      setIsExporting(false);
    }
  };

  const limparFiltros = () => {
    setBusca('');
    setTipoFiltro('todos');
    setFinalidadeFiltro('todos');
    setPortalFiltro('todos');
    setDestaqueFiltro('todos');
    setAtualizacaoFiltro('todos');
  };

  const filtrosAtivos = [
    busca !== '',
    tipoFiltro !== 'todos',
    finalidadeFiltro !== 'todos',
    portalFiltro !== 'todos',
    destaqueFiltro !== 'todos',
    atualizacaoFiltro !== 'todos',
  ].filter(Boolean).length;

  const copyText = useCallback((text: string, label: string) => {
    if (text === '-' || !text) return;
    navigator.clipboard.writeText(text).then(() => toast.success(label + ' copiado'));
  }, []);

  const openOwnerWhatsApp = useCallback((property: ImovelRow) => {
    const phone = normalizeWhatsAppPhone(property.proprietario_telefone);
    if (!phone) {
      toast.error('Este imóvel não tem WhatsApp do proprietário cadastrado');
      return;
    }

    const url = `https://wa.me/${phone}?text=${encodeURIComponent(buildAvailabilityCheckMessage(property))}`;
    window.open(url, '_blank', 'noopener,noreferrer');
  }, []);

  // Keyboard shortcut: '/' focuses search
  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      if (e.key === '/' && !(e.target instanceof HTMLInputElement) && !(e.target instanceof HTMLTextAreaElement)) {
        e.preventDefault();
        searchRef.current?.focus();
      }
    };
    document.addEventListener('keydown', handler);
    return () => document.removeEventListener('keydown', handler);
  }, []);

  const SortIcon = ({ field }: { field: SortField }) => {
    if (sortField !== field) return <ChevronsUpDown size={11} className="opacity-30" />;
    return sortDir === 'asc' ? <ChevronUp size={11} /> : <ChevronDown size={11} />;
  };

  const thSort = (field: SortField, label: React.ReactNode, extra = '') =>
    `p-3 text-xs text-muted-foreground cursor-pointer select-none hover:text-foreground whitespace-nowrap ${extra}`;

  return (
    <div className="flex">
      <Sidebar />
      <div className="page-shell">
        <div className="max-w-7xl mx-auto space-y-6">

          {/* Header */}
          <div className="page-header">
            <div>
              <h1 className="page-title">{viewMode === 'trash' ? 'Lixeira de Imóveis' : 'Imóveis'}</h1>
              <p className="page-subtitle">
                {imoveis.length} imóvel(is){' '}
                {!isLoading && viewMode === 'active' && (
                  <>
                    — {imoveis.filter((i) => i.exibir).length} publicados,{' '}
                    {imoveis.filter((i) => i.destaque).length} em destaque,{' '}
                    {imoveis.filter((i) => i.imobi_brasil_sent).length} no Imobi Brasil
                  </>
                )}
                {!isLoading && viewMode === 'trash' && (
                  <>
                    — {imoveis.filter((i) => i.trash_source === 'admin').length} enviados manualmente,{' '}
                    {imoveis.filter((i) => i.trash_source === 'sync').length} removidos pela sincronização
                  </>
                )}
              </p>
            </div>
            <div className="flex flex-wrap gap-2">
              <button
                type="button"
                onClick={() => setViewMode((prev) => (prev === 'active' ? 'trash' : 'active'))}
                className="flex items-center gap-2 px-4 py-2 rounded-lg border border-border bg-card hover:bg-accent transition-colors text-sm"
              >
                <Trash2 size={15} />
                {viewMode === 'trash' ? 'Ver imóveis ativos' : 'Ver lixeira'}
              </button>
              {viewMode === 'active' && (
                <>
              <button
                type="button"
                onClick={handleSync}
                disabled={isSyncing}
                className="flex items-center gap-2 px-4 py-2 rounded-lg border border-border bg-card hover:bg-accent transition-colors disabled:opacity-60 text-sm"
              >
                <RefreshCw size={15} className={isSyncing ? 'animate-spin' : ''} />
                {isSyncing ? 'Sincronizando...' : 'Sincronizar'}
              </button>
              <button
                type="button"
                onClick={handleExport}
                disabled={isExporting}
                className="flex items-center gap-2 px-4 py-2 rounded-lg border border-border bg-card hover:bg-accent transition-colors disabled:opacity-60 text-sm"
              >
                <Download size={15} />
                {isExporting ? 'Exportando...' : 'Exportar CSV'}
              </button>
              <button
                type="button"
                onClick={() => setLocation('/properties/novo')}
                className="flex items-center gap-2 px-4 py-2 rounded-lg bg-primary text-primary-foreground hover:opacity-90 transition-opacity text-sm"
              >
                <Plus size={15} />
                Novo Imóvel
              </button>
                </>
              )}
            </div>
          </div>

          {/* Filtros */}
          <div className="glass-panel rounded-2xl p-4 flex flex-wrap gap-3 items-end">
            <div className="flex-1 min-w-[200px]">
              <label className="block text-xs text-muted-foreground mb-1">Buscar</label>
              <div className="relative">
                <Search size={13} className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
                <input
                  ref={searchRef}
                  value={busca}
                  onChange={(e) => setBusca(e.target.value)}
                  placeholder="Código, referência, título ou localização... (/)"  
                  className="w-full bg-background border border-border rounded-lg pl-8 pr-3 py-2 text-sm"
                />
              </div>
            </div>
            <div>
              <label className="block text-xs text-muted-foreground mb-1">Tipo</label>
              <select
                value={tipoFiltro}
                onChange={(e) => setTipoFiltro(e.target.value)}
                className="bg-background border border-border rounded-lg px-3 py-2 text-sm"
              >
                <option value="todos">Todos</option>
                <option value="apartamento">Apartamento</option>
                <option value="casa">Casa</option>
                <option value="comercial">Comercial</option>
                <option value="terreno">Terreno</option>
              </select>
            </div>
            <div>
              <label className="block text-xs text-muted-foreground mb-1">Finalidade</label>
              <select
                value={finalidadeFiltro}
                onChange={(e) => setFinalidadeFiltro(e.target.value)}
                className="bg-background border border-border rounded-lg px-3 py-2 text-sm"
              >
                <option value="todos">Todas</option>
                <option value="venda">Venda</option>
                <option value="aluguel">Aluguel</option>
              </select>
            </div>
            <div>
              <label className="block text-xs text-muted-foreground mb-1">Portal</label>
              <select
                value={portalFiltro}
                onChange={(e) => setPortalFiltro(e.target.value)}
                className="bg-background border border-border rounded-lg px-3 py-2 text-sm"
              >
                <option value="todos">Todos</option>
                <option value="publicado">Publicado</option>
                <option value="oculto">Oculto</option>
              </select>
            </div>
            <div>
              <label className="block text-xs text-muted-foreground mb-1">Destaque</label>
              <select
                value={destaqueFiltro}
                onChange={(e) => setDestaqueFiltro(e.target.value)}
                className="bg-background border border-border rounded-lg px-3 py-2 text-sm"
              >
                <option value="todos">Todos</option>
                <option value="sim">Em destaque</option>
                <option value="nao">Sem destaque</option>
              </select>
            </div>
            <div>
              <label className="block text-xs text-muted-foreground mb-1">Atualização</label>
              <select
                value={atualizacaoFiltro}
                onChange={(e) => setAtualizacaoFiltro(e.target.value)}
                className="bg-background border border-border rounded-lg px-3 py-2 text-sm"
              >
                <option value="todos">Todas</option>
                <option value="sem_30_dias">Sem atualização há 30 dias</option>
              </select>
            </div>
            <button
              type="button"
              onClick={limparFiltros}
              className="relative flex items-center gap-1 px-4 py-2 rounded-lg border border-border hover:bg-accent text-sm"
            >
              <X size={13} /> Limpar
              {filtrosAtivos > 0 && (
                <span className="absolute -top-1.5 -right-1.5 w-4 h-4 rounded-full bg-primary text-primary-foreground text-[10px] font-bold flex items-center justify-center">
                  {filtrosAtivos}
                </span>
              )}
            </button>
          </div>

          {/* Contagem de resultados */}
          {!isLoading && imoveisOrdenados.length !== imoveis.length && (
            <p className="text-sm text-muted-foreground px-1">
              {imoveisOrdenados.length} resultado(s) para os filtros aplicados
            </p>
          )}

          {/* Conteúdo principal */}
          {isLoading ? (
            <div className="glass-panel rounded-2xl p-12 flex justify-center">
              <Loader2 className="animate-spin" />
            </div>
          ) : imoveisPaginados.length === 0 ? (
            <div className="glass-panel rounded-2xl p-12 text-center text-sm text-muted-foreground">
              {viewMode === 'trash'
                ? 'Nenhum imóvel na lixeira para os filtros atuais.'
                : 'Nenhum imóvel encontrado para os filtros atuais.'}
            </div>
          ) : (
            <>
              {/* Cards — mobile */}
              <div className="grid gap-3 md:hidden">
                {imoveisPaginados.map((im) => (
                  <div
                    key={im.id}
                    className="glass-panel rounded-2xl p-4 flex gap-3"
                  >
                    {/* Foto */}
                    <div className="shrink-0">
                      {im.imagem ? (
                        <img
                          src={im.imagem}
                          alt={im.titulo}
                          className="w-20 h-16 object-cover rounded-xl"
                        />
                      ) : (
                        <div className="w-20 h-16 rounded-xl bg-muted flex items-center justify-center text-2xl">
                          🏢
                        </div>
                      )}
                    </div>

                    {/* Conteúdo */}
                    <div className="flex-1 min-w-0 space-y-1.5">
                      <div className="flex items-start justify-between gap-2">
                        <p className="text-sm font-medium leading-snug line-clamp-2">{im.titulo}</p>
                        <span
                          className={`shrink-0 inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${
                            im.finalidade === 'venda'
                              ? 'bg-blue-100 text-blue-800'
                              : 'bg-emerald-100 text-emerald-800'
                          }`}
                        >
                          {im.finalidade === 'venda' ? 'Venda' : 'Aluguel'}
                        </span>
                      </div>

                      <p className="text-xs text-muted-foreground font-mono">{im.codigo}</p>

                      {im.captador_nome && (
                        <p className="text-xs text-muted-foreground">
                          <span className="font-medium">Captador:</span> {im.captador_nome}
                        </p>
                      )}

                      {!isTrainee && (im.proprietario_telefone || im.created_at || im.updated_at) && (
                        <div className="space-y-1 text-xs text-muted-foreground">
                          {im.proprietario_telefone && (
                            <button
                              type="button"
                              onClick={() => copyText(im.proprietario_telefone || '', 'Telefone do proprietário')}
                              className="text-left hover:text-foreground transition-colors"
                            >
                              <span className="font-medium">WhatsApp prop.:</span> {im.proprietario_telefone}
                            </button>
                          )}
                          <p>
                            <span className="font-medium">Inserido:</span> {formatDateTime(im.created_at)}
                          </p>
                          <p>
                            <span className="font-medium">Atualizado:</span> {formatDateTime(im.updated_at)}
                          </p>
                        </div>
                      )}

                      <div className="flex flex-wrap items-center gap-x-3 gap-y-0.5 text-xs text-muted-foreground">
                        <span>{tipoLabel(im.tipo)}</span>
                        {im.dormitorios > 0 && <span>{im.dormitorios} dorm.</span>}
                        {im.area > 0 && <span>{im.area}m²</span>}
                        {im.localizacao !== '-' && <span>{im.localizacao}</span>}
                      </div>

                      <p className="text-sm font-semibold">R$ {formatMoney(im.preco)}</p>

                      {/* Badges + Ações */}
                      <div className="flex items-center justify-between pt-1">
                        <div className="flex items-center gap-1.5 flex-wrap">
                          {viewMode === 'trash' ? (
                            <>
                              <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-muted text-muted-foreground">
                                {im.trash_source === 'sync' ? 'Sincronização' : 'Admin'}
                              </span>
                              {im.deleted_at && (
                                <span className="text-xs text-muted-foreground">
                                  {new Date(im.deleted_at).toLocaleDateString('pt-BR')}
                                </span>
                              )}
                            </>
                          ) : (
                            <>
                          <button
                            type="button"
                            onClick={() => handleToggle(im.id, 'exibir', im.exibir)}
                            disabled={togglingId === im.id}
                            className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium transition-colors disabled:opacity-60 ${
                              im.exibir
                                ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200'
                                : 'bg-muted text-muted-foreground hover:bg-accent'
                            }`}
                          >
                            <Globe size={10} />
                            {im.exibir ? 'Publicado' : 'Oculto'}
                          </button>
                          <button
                            type="button"
                            onClick={() => handleToggle(im.id, 'destaque', im.destaque)}
                            disabled={togglingId === im.id}
                            className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium transition-colors disabled:opacity-60 ${
                              im.destaque
                                ? 'bg-amber-100 text-amber-800 hover:bg-amber-200'
                                : 'bg-muted text-muted-foreground hover:bg-accent'
                            }`}
                          >
                            <Star size={10} />
                            {im.destaque ? 'Destaque' : 'Normal'}
                          </button>
                          <AdsStatusBadge id={im.id} />
                            </>
                          )}
                        </div>

                        <div className="flex items-center gap-0.5 flex-wrap justify-end">
                          {!isTrainee && viewMode === 'active' && im.proprietario_telefone && (
                            <button
                              type="button"
                              onClick={() => openOwnerWhatsApp(im)}
                              title="Falar com o proprietário"
                              className="inline-flex items-center gap-1 px-2 py-1.5 rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition-colors text-xs font-medium"
                            >
                              <MessageCircle size={13} />
                              Conferir
                            </button>
                          )}
                          {viewMode === 'trash' ? (
                            <>
                              <button
                                type="button"
                                onClick={() => handleRestore(im)}
                                title="Restaurar"
                                className="px-2 py-1.5 rounded-lg hover:bg-emerald-50 text-emerald-600 transition-colors text-xs font-medium"
                              >
                                Restaurar
                              </button>
                              <button
                                type="button"
                                onClick={() => handleForceDelete(im)}
                                title="Excluir definitivamente"
                                className="px-2 py-1.5 rounded-lg hover:bg-red-50 text-red-600 transition-colors text-xs font-medium"
                              >
                                Excluir
                              </button>
                            </>
                          ) : (
                            <>
                          <button
                            type="button"
                            onClick={() => handleOpenPortalPreview(im)}
                            title="Ver no portal"
                            className="p-1.5 rounded-lg hover:bg-accent transition-colors"
                          >
                            <Eye size={15} />
                          </button>
                          <button
                            type="button"
                            onClick={() => setLocation(`/properties/${im.id}/editar`)}
                            title="Editar"
                            className="p-1.5 rounded-lg hover:bg-accent transition-colors"
                          >
                            <Pencil size={15} />
                          </button>
                          {!isTrainee && (
                          <button
                            type="button"
                            onClick={() => handleDelete(im)}
                            title="Excluir"
                            className="p-1.5 rounded-lg hover:bg-red-50 text-red-500 transition-colors"
                          >
                            <Trash2 size={15} />
                          </button>
                          )}
                            </>
                          )}
                        </div>
                      </div>
                    </div>
                  </div>
                ))}
              </div>

              {/* Tabela — desktop */}
              <div className="glass-panel rounded-2xl overflow-auto hidden md:block">
                <table className="w-full min-w-[720px]">
                  <thead className="sticky top-0 z-10 bg-card">
                    <tr className="border-b border-border">
                      {/* Coluna 1: Imóvel (foto + código + título) */}
                      <th
                        className={thSort('titulo', null, 'text-left w-[34%]')}
                        onClick={() => handleSort('titulo')}
                      >
                        <span className="inline-flex items-center gap-1">
                          Imóvel <SortIcon field="titulo" />
                        </span>
                      </th>

                      {/* Coluna 2: Características (tipo + finalidade + dorm + área) */}
                      <th
                        className={thSort('tipo', null, 'text-left w-[18%]')}
                        onClick={() => handleSort('tipo')}
                      >
                        <span className="inline-flex items-center gap-1">
                          Características <SortIcon field="tipo" />
                        </span>
                      </th>

                      {/* Coluna 3: Preço */}
                      <th
                        className={thSort('preco', null, 'text-right w-[14%]')}
                        onClick={() => handleSort('preco')}
                      >
                        <span className="inline-flex items-center justify-end gap-1">
                          Preço <SortIcon field="preco" />
                        </span>
                      </th>

                      {/* Coluna 4: Localização */}
                      <th
                        className={thSort('localizacao', null, 'text-left w-[15%]')}
                        onClick={() => handleSort('localizacao')}
                      >
                        <span className="inline-flex items-center gap-1">
                          Localização <SortIcon field="localizacao" />
                        </span>
                      </th>

                      {/* Coluna 4b: Captador */}
                      <th className="p-3 text-xs text-muted-foreground text-left whitespace-nowrap w-[12%]">
                        Captador
                      </th>

                      {viewMode === 'active' && !isTrainee && (
                        <>
                          <th className="p-3 text-xs text-muted-foreground text-left whitespace-nowrap w-[16%]">
                            Proprietário
                          </th>
                          <th className="p-3 text-xs text-muted-foreground text-left whitespace-nowrap w-[14%]">
                            Inserção / atualização
                          </th>
                        </>
                      )}

                      {/* Coluna 5: Status (portal + destaque + anúncio) */}
                      <th className="p-3 text-center text-xs text-muted-foreground whitespace-nowrap w-[10%]">
                        Status
                      </th>

                      {/* Coluna 6: Ações — sticky */}
                      <th className="sticky right-0 z-20 bg-card text-left p-3 text-xs text-muted-foreground shadow-[-4px_0_8px_rgba(0,0,0,0.08)] whitespace-nowrap">
                        Ações
                      </th>
                    </tr>
                  </thead>
                  <tbody>
                    {imoveisPaginados.map((im) => (
                      <tr
                        key={im.id}
                        className="border-b border-border/50 hover:bg-accent/30 transition-colors"
                      >
                        {/* Coluna 1: Foto + Código/Ref + Título */}
                        <td className="p-3">
                          <div className="flex items-center gap-3">
                            {im.imagem ? (
                              <img
                                src={im.imagem}
                                alt={im.titulo}
                                className="shrink-0 w-16 h-12 object-cover rounded-xl"
                              />
                            ) : (
                              <div className="shrink-0 w-16 h-12 rounded-xl bg-muted flex items-center justify-center text-xl">
                                🏢
                              </div>
                            )}
                            <div className="min-w-0">
                              <p
                                className="text-sm font-medium leading-snug line-clamp-2 mb-0.5"
                                title={im.titulo}
                              >
                                {im.titulo}
                              </p>
                              <div className="flex items-center gap-2 flex-wrap">
                                <span
                                  className="font-mono text-xs text-muted-foreground cursor-pointer hover:text-foreground"
                                  title="Copiar código"
                                  onClick={() => copyText(im.codigo, 'Código')}
                                >
                                  {im.codigo}
                                </span>
                                {im.referencia !== '-' && (
                                  <span
                                    className="font-mono text-xs text-muted-foreground/60 cursor-pointer hover:text-muted-foreground"
                                    title="Copiar referência"
                                    onClick={() => copyText(im.referencia, 'Referência')}
                                  >
                                    · {im.referencia}
                                  </span>
                                )}
                              </div>
                            </div>
                          </div>
                        </td>

                        {/* Coluna 2: Tipo + Finalidade + Dorm + Área */}
                        <td className="p-3">
                          <div className="flex flex-col gap-1">
                            <div className="flex items-center gap-1.5 flex-wrap">
                              <span className="text-xs text-foreground/80">{tipoLabel(im.tipo)}</span>
                              <span
                                className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${
                                  im.finalidade === 'venda'
                                    ? 'bg-blue-100 text-blue-800'
                                    : 'bg-emerald-100 text-emerald-800'
                                }`}
                              >
                                {im.finalidade === 'venda' ? 'Venda' : 'Aluguel'}
                              </span>
                            </div>
                            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                              {im.dormitorios > 0 && (
                                <span>{im.dormitorios} dorm.</span>
                              )}
                              {im.dormitorios > 0 && im.area > 0 && <span className="opacity-40">·</span>}
                              {im.area > 0 && <span>{im.area} m²</span>}
                              {im.dormitorios === 0 && im.area === 0 && <span className="opacity-40">—</span>}
                            </div>
                          </div>
                        </td>

                        {/* Coluna 3: Preço */}
                        <td className="p-3 text-sm font-semibold whitespace-nowrap text-right">
                          R$ {formatMoney(im.preco)}
                        </td>

                        {/* Coluna 4: Localização */}
                        <td className="p-3">
                          <span className="text-sm text-foreground/90 leading-snug">
                            {im.localizacao}
                          </span>
                        </td>

                        {/* Coluna 4b: Captador / Proprietário */}
                        <td className="p-3">
                          <div className="flex flex-col gap-0.5">
                            {im.captador_nome
                              ? <span className="text-sm text-foreground/80">{im.captador_nome}</span>
                              : <span className="text-xs text-muted-foreground/40">—</span>}
                          </div>
                        </td>

                        {viewMode === 'active' && !isTrainee && (
                          <>
                            <td className="p-3">
                              <div className="flex flex-col gap-1">
                                {im.proprietario_nome ? (
                                  <span className="text-sm text-foreground/90">{im.proprietario_nome}</span>
                                ) : (
                                  <span className="text-xs text-muted-foreground/40">Sem proprietário</span>
                                )}
                                {im.proprietario_telefone ? (
                                  <button
                                    type="button"
                                    onClick={() => copyText(im.proprietario_telefone || '', 'Telefone do proprietário')}
                                    className="text-xs text-left text-emerald-700 hover:text-emerald-800 transition-colors"
                                    title="Copiar telefone do proprietário"
                                  >
                                    {im.proprietario_telefone}
                                  </button>
                                ) : (
                                  <span className="text-xs text-muted-foreground/40">Sem WhatsApp</span>
                                )}
                              </div>
                            </td>
                            <td className="p-3">
                              <div className="flex flex-col gap-1 text-xs">
                                <span className="text-foreground/80">
                                  <span className="text-muted-foreground">Inserido:</span> {formatDateTime(im.created_at)}
                                </span>
                                {im.inserido_por_nome && (
                                  <span className="text-foreground/80">
                                    <span className="text-muted-foreground">Cadastrado por:</span> {im.inserido_por_nome}
                                  </span>
                                )}
                                <span className="text-foreground/80">
                                  <span className="text-muted-foreground">Atualizado:</span> {formatDateTime(im.updated_at)}
                                </span>
                              </div>
                            </td>
                          </>
                        )}

                        {/* Coluna 5: Status agrupado (portal + destaque + ads) */}
                        <td className="p-3">
                          <div className="flex flex-col items-center gap-1.5">
                            {viewMode === 'trash' ? (
                              <>
                                <span className="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium bg-muted text-muted-foreground w-full justify-center">
                                  {im.trash_source === 'sync' ? 'Sincronização' : 'Admin'}
                                </span>
                                <span className="text-xs text-muted-foreground text-center">
                                  {im.deleted_at
                                    ? new Date(im.deleted_at).toLocaleString('pt-BR')
                                    : 'Na lixeira'}
                                </span>
                              </>
                            ) : (
                              <>
                            <button
                              type="button"
                              onClick={() => handleToggle(im.id, 'exibir', im.exibir)}
                              disabled={togglingId === im.id}
                              title={im.exibir ? 'Publicado — clique para ocultar' : 'Oculto — clique para publicar'}
                              className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium w-full justify-center transition-colors disabled:opacity-60 ${
                                im.exibir
                                  ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200'
                                  : 'bg-muted text-muted-foreground hover:bg-accent'
                              }`}
                            >
                              <Globe size={10} />
                              {im.exibir ? 'Portal' : 'Oculto'}
                            </button>

                            <button
                              type="button"
                              onClick={() => handleToggle(im.id, 'destaque', im.destaque)}
                              disabled={togglingId === im.id}
                              title={im.destaque ? 'Em destaque — clique para remover' : 'Sem destaque — clique para destacar'}
                              className={`inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium w-full justify-center transition-colors disabled:opacity-60 ${
                                im.destaque
                                  ? 'bg-amber-100 text-amber-800 hover:bg-amber-200'
                                  : 'bg-muted text-muted-foreground hover:bg-accent'
                              }`}
                            >
                              <Star size={10} />
                              {im.destaque ? 'Destaque' : 'Normal'}
                            </button>

                            <button
                              type="button"
                              onClick={() => setLocation(`/properties/${im.id}/editar`)}
                              title="Gerenciar anúncios"
                              className="w-full flex items-center justify-center"
                            >
                              {adsStatusMap[im.id] ? (
                                <AdsStatusBadge id={im.id} />
                              ) : (
                                <Zap size={12} className="text-muted-foreground/30" />
                              )}
                            </button>
                              </>
                            )}
                          </div>
                        </td>

                        {/* Coluna 6: Ações */}
                        <td className="sticky right-0 bg-card p-3 shadow-[-4px_0_8px_rgba(0,0,0,0.06)]">
                          <div className="flex items-center gap-1">
                            {viewMode === 'trash' ? (
                              <>
                                <button
                                  type="button"
                                  onClick={() => handleRestore(im)}
                                  title="Restaurar"
                                  className="px-2 py-1.5 rounded-lg hover:bg-emerald-50 text-emerald-600 transition-colors text-xs font-medium"
                                >
                                  Restaurar
                                </button>
                                <button
                                  type="button"
                                  onClick={() => handleForceDelete(im)}
                                  title="Excluir definitivamente"
                                  className="px-2 py-1.5 rounded-lg hover:bg-red-50 text-red-600 transition-colors text-xs font-medium"
                                >
                                  Excluir
                                </button>
                              </>
                            ) : (
                              <>
                            {!isTrainee && im.proprietario_telefone && (
                              <button
                                type="button"
                                onClick={() => openOwnerWhatsApp(im)}
                                title="Falar com o proprietário"
                                className="inline-flex items-center gap-1 px-2 py-1.5 rounded-lg bg-emerald-50 text-emerald-700 hover:bg-emerald-100 transition-colors text-xs font-medium mr-1"
                              >
                                <MessageCircle size={13} />
                                Conferir
                              </button>
                            )}
                            <button
                              type="button"
                              onClick={() => handleOpenPortalPreview(im)}
                              title="Ver no portal"
                              className="p-1.5 rounded-lg hover:bg-accent transition-colors"
                            >
                              <Eye size={15} />
                            </button>
                            <button
                              type="button"
                              onClick={() => setLocation(`/properties/${im.id}/editar`)}
                              title="Editar"
                              className="p-1.5 rounded-lg hover:bg-accent transition-colors"
                            >
                              <Pencil size={15} />
                            </button>
                            {!isTrainee && (
                            <button
                              type="button"
                              onClick={() => handleDelete(im)}
                              title="Excluir"
                              className="p-1.5 rounded-lg hover:bg-red-50 text-red-500 transition-colors"
                            >
                              <Trash2 size={15} />
                            </button>
                            )}
                                </>
                              )}
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </>
          )}

          {/* Paginação */}
          {!isLoading && (
            <div className="glass-panel rounded-2xl p-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
              <div className="flex items-center gap-3 flex-wrap">
                <p className="text-sm text-muted-foreground">
                  {Math.min((pagina - 1) * itensPorPagina + 1, imoveisOrdenados.length)}–{Math.min(pagina * itensPorPagina, imoveisOrdenados.length)} de {imoveisOrdenados.length} imóvel(is)
                </p>
                <div className="flex items-center gap-1.5">
                  <label className="text-xs text-muted-foreground">Por página</label>
                  <select
                    value={itensPorPagina}
                    onChange={(e) => { setItensPorPagina(Number(e.target.value)); setPagina(1); }}
                    className="bg-background border border-border rounded-lg px-2 py-1 text-xs"
                  >
                    <option value={5}>5</option>
                    <option value={10}>10</option>
                    <option value={25}>25</option>
                    <option value={50}>50</option>
                    <option value={100}>100</option>
                  </select>
                </div>
              </div>
              <div className="flex items-center gap-2">
                <button
                  type="button"
                  onClick={() => setPagina(1)}
                  disabled={pagina <= 1}
                  className="px-3 py-1.5 rounded-lg border border-border hover:bg-accent disabled:opacity-60 text-sm"
                >
                  «
                </button>
                <button
                  type="button"
                  onClick={() => setPagina((p) => Math.max(1, p - 1))}
                  disabled={pagina <= 1}
                  className="px-3 py-1.5 rounded-lg border border-border hover:bg-accent disabled:opacity-60 text-sm"
                >
                  Anterior
                </button>
                <span className="text-sm text-muted-foreground px-1">
                  {pagina} / {totalPaginas}
                </span>
                <button
                  type="button"
                  onClick={() => setPagina((p) => Math.min(totalPaginas, p + 1))}
                  disabled={pagina >= totalPaginas}
                  className="px-3 py-1.5 rounded-lg border border-border hover:bg-accent disabled:opacity-60 text-sm"
                >
                  Próxima
                </button>
                <button
                  type="button"
                  onClick={() => setPagina(totalPaginas)}
                  disabled={pagina >= totalPaginas}
                  className="px-3 py-1.5 rounded-lg border border-border hover:bg-accent disabled:opacity-60 text-sm"
                >
                  »
                </button>
              </div>
            </div>
          )}

        </div>
      </div>

      {/* Modal de preview do portal */}
      {previewId && (
        <div
          className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm"
          onClick={() => setPreviewId(null)}
        >
          <div
            className="relative bg-background rounded-xl shadow-2xl flex flex-col"
            style={{ width: '90vw', height: '90vh' }}
            onClick={(e) => e.stopPropagation()}
          >
            {/* Header */}
            <div className="flex items-center justify-between px-4 py-2.5 border-b border-border shrink-0">
              <span className="text-sm font-medium text-muted-foreground">
                Visualizar imóvel no portal
              </span>
              <div className="flex items-center gap-2">
                <a
                  href={`/portal/imovel/${previewId}`}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="text-xs text-primary underline hover:no-underline"
                >
                  Abrir em nova aba
                </a>
                <button
                  type="button"
                  onClick={() => setPreviewId(null)}
                  className="p-1.5 rounded-lg hover:bg-accent transition-colors"
                >
                  <X size={16} />
                </button>
              </div>
            </div>
            {/* iframe */}
            <iframe
              src={`/portal/imovel/${previewId}`}
              className="flex-1 w-full rounded-b-xl"
              title="Preview do imóvel"
            />
          </div>
        </div>
      )}
    </div>
  );
}
