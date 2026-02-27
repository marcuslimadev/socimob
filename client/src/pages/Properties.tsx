import { useState, useEffect, useMemo, useRef, useCallback } from 'react';
import {
  Search, Plus, Eye, Pencil, Trash2, RefreshCw, Download,
  Star, Globe, Loader2, X, ChevronUp, ChevronDown, ChevronsUpDown, Zap,
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
  imagem: string;
  ativo: boolean;
  exibir: boolean;
  destaque: boolean;
}

type SortField = 'codigo' | 'referencia' | 'titulo' | 'tipo' | 'finalidade' | 'preco' | 'dormitorios' | 'area' | 'localizacao';

const formatMoney = (value: number) =>
  Number(value || 0).toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 0 });

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
  const [imoveis, setImoveis] = useState<ImovelRow[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isExporting, setIsExporting] = useState(false);
  const [isSyncing, setIsSyncing] = useState(false);
  const [busca, setBusca] = useState('');
  const [tipoFiltro, setTipoFiltro] = useState('todos');
  const [finalidadeFiltro, setFinalidadeFiltro] = useState('todos');
  const [portalFiltro, setPortalFiltro] = useState('todos');
  const [destaqueFiltro, setDestaqueFiltro] = useState('todos');
  const [pagina, setPagina] = useState(1);
  const [togglingId, setTogglingId] = useState<string | null>(null);
  const [sortField, setSortField] = useState<SortField | null>(null);
  const [sortDir, setSortDir] = useState<'asc' | 'desc'>('asc');
  const [itensPorPagina, setItensPorPagina] = useState(25);
  const [adsStatusMap, setAdsStatusMap] = useState<Record<string, AdsStatus>>({});
  const [previewId, setPreviewId] = useState<string | null>(null);
  const searchRef = useRef<HTMLInputElement>(null);

  const fetchImoveis = async () => {
    setIsLoading(true);
    try {
      const response = await api.get('/imoveis', { params: { per_page: 'all' } });
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
          imagem:
            Array.isArray(item.imagens) && item.imagens.length > 0
              ? item.imagens[0]
              : item.imagem_destaque || item.foto_capa || '',
          ativo: Boolean(item.active),
          exibir: Boolean(item.exibir_imovel),
          destaque: Boolean(item.destaque),
        })),
      );
    } catch {
      toast.error('Erro ao carregar imóveis');
    } finally {
      setIsLoading(false);
    }
  };

  useEffect(() => {
    fetchImoveis();
  }, []);

  useEffect(() => {
    setPagina(1);
  }, [busca, tipoFiltro, finalidadeFiltro, portalFiltro, destaqueFiltro, sortField, sortDir, itensPorPagina]);

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
      return true;
    });
  }, [imoveis, busca, tipoFiltro, finalidadeFiltro, portalFiltro, destaqueFiltro]);

  const imoveisOrdenados = useMemo(() => {
    if (!sortField) return imoveisFiltrados;
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
    if (!window.confirm(`Excluir o imóvel ${im.codigo}? Esta ação não pode ser desfeita.`)) return;
    try {
      await api.delete(`/imoveis/${im.id}`);
      toast.success('Imóvel excluído');
      await fetchImoveis();
    } catch (error: any) {
      toast.error(error?.response?.data?.error || 'Erro ao excluir imóvel');
    }
  };

  const handleSync = async () => {
    setIsSyncing(true);
    try {
      const response = await api.get('/properties/sync');
      if (response.data?.success) {
        toast.success('Imóveis sincronizados');
        await fetchImoveis();
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
  };

  const filtrosAtivos = [
    busca !== '',
    tipoFiltro !== 'todos',
    finalidadeFiltro !== 'todos',
    portalFiltro !== 'todos',
    destaqueFiltro !== 'todos',
  ].filter(Boolean).length;

  const copyText = useCallback((text: string, label: string) => {
    if (text === '-' || !text) return;
    navigator.clipboard.writeText(text).then(() => toast.success(label + ' copiado'));
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
              <h1 className="page-title">Imóveis</h1>
              <p className="page-subtitle">
                {imoveis.length} imóvel(is){' '}
                {!isLoading && (
                  <>
                    — {imoveis.filter((i) => i.exibir).length} publicados,{' '}
                    {imoveis.filter((i) => i.destaque).length} em destaque
                  </>
                )}
              </p>
            </div>
            <div className="flex flex-wrap gap-2">
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
              Nenhum imóvel encontrado para os filtros atuais.
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
                        </div>

                        <div className="flex items-center gap-0.5">
                          <button
                            type="button"
                            onClick={() => setPreviewId(im.id)}
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
                          <button
                            type="button"
                            onClick={() => handleDelete(im)}
                            title="Excluir"
                            className="p-1.5 rounded-lg hover:bg-red-50 text-red-500 transition-colors"
                          >
                            <Trash2 size={15} />
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>
                ))}
              </div>

              {/* Tabela — desktop */}
              <div className="glass-panel rounded-2xl overflow-auto hidden md:block">
                <table className="w-full min-w-[960px]">
                  <thead className="sticky top-0 z-10 bg-card">
                    <tr className="border-b border-border">
                      <th className="text-left p-3 text-xs text-muted-foreground w-16">Foto</th>
                      <th
                        className={thSort('codigo', null, 'text-left')}
                        onClick={() => handleSort('codigo')}
                      >
                        <span className="inline-flex items-center gap-1">
                          Código <SortIcon field="codigo" />
                        </span>
                      </th>
                      <th
                        className={thSort('referencia', null, 'text-left')}
                        onClick={() => handleSort('referencia')}
                      >
                        <span className="inline-flex items-center gap-1">
                          Referência <SortIcon field="referencia" />
                        </span>
                      </th>
                      <th
                        className={thSort('titulo', null, 'text-left')}
                        onClick={() => handleSort('titulo')}
                      >
                        <span className="inline-flex items-center gap-1">
                          Título <SortIcon field="titulo" />
                        </span>
                      </th>
                      <th
                        className={thSort('tipo', null, 'text-left')}
                        onClick={() => handleSort('tipo')}
                      >
                        <span className="inline-flex items-center gap-1">
                          Tipo <SortIcon field="tipo" />
                        </span>
                      </th>
                      <th
                        className={thSort('finalidade', null, 'text-left')}
                        onClick={() => handleSort('finalidade')}
                      >
                        <span className="inline-flex items-center gap-1">
                          Finalidade <SortIcon field="finalidade" />
                        </span>
                      </th>
                      <th
                        className={thSort('preco', null, 'text-left')}
                        onClick={() => handleSort('preco')}
                      >
                        <span className="inline-flex items-center gap-1">
                          Preço <SortIcon field="preco" />
                        </span>
                      </th>
                      <th
                        className={thSort('dormitorios', null, 'text-center')}
                        onClick={() => handleSort('dormitorios')}
                      >
                        <span className="inline-flex items-center justify-center gap-1">
                          Dorm. <SortIcon field="dormitorios" />
                        </span>
                      </th>
                      <th
                        className={thSort('area', null, 'text-left')}
                        onClick={() => handleSort('area')}
                      >
                        <span className="inline-flex items-center gap-1">
                          Área <SortIcon field="area" />
                        </span>
                      </th>
                      <th
                        className={thSort('localizacao', null, 'text-left')}
                        onClick={() => handleSort('localizacao')}
                      >
                        <span className="inline-flex items-center gap-1">
                          Localização <SortIcon field="localizacao" />
                        </span>
                      </th>
                      <th className="text-center p-3 text-xs text-muted-foreground whitespace-nowrap">
                        <span className="inline-flex items-center gap-1"><Globe size={11} /> Portal</span>
                      </th>
                      <th className="text-center p-3 text-xs text-muted-foreground whitespace-nowrap">
                        <span className="inline-flex items-center gap-1"><Star size={11} /> Destaque</span>
                      </th>
                      <th className="text-center p-3 text-xs text-muted-foreground whitespace-nowrap">
                        <span className="inline-flex items-center gap-1"><Zap size={11} /> Anúncio</span>
                      </th>
                      <th className="text-left p-3 text-xs text-muted-foreground">Ações</th>
                    </tr>
                  </thead>
                  <tbody>
                    {imoveisPaginados.map((im) => (
                      <tr
                        key={im.id}
                        className="border-b border-border/50 hover:bg-accent/30 transition-colors"
                      >
                        <td className="p-3">
                          {im.imagem ? (
                            <img
                              src={im.imagem}
                              alt={im.titulo}
                              className="w-14 h-10 object-cover rounded-lg"
                            />
                          ) : (
                            <div className="w-14 h-10 rounded-lg bg-muted flex items-center justify-center text-base">
                              🏢
                            </div>
                          )}
                        </td>
                        <td className="p-3 text-sm text-muted-foreground font-mono cursor-pointer hover:text-foreground" title="Copiar código" onClick={() => copyText(im.codigo, 'Código')}>{im.codigo}</td>
                        <td className="p-3 text-sm text-muted-foreground font-mono cursor-pointer hover:text-foreground" title="Copiar referência" onClick={() => copyText(im.referencia, 'Referência')}>{im.referencia}</td>
                        <td className="p-3 text-sm font-medium max-w-[200px]">
                          <span className="line-clamp-2">{im.titulo}</span>
                        </td>
                        <td className="p-3 text-sm">{tipoLabel(im.tipo)}</td>
                        <td className="p-3">
                          <span
                            className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${
                              im.finalidade === 'venda'
                                ? 'bg-blue-100 text-blue-800'
                                : 'bg-emerald-100 text-emerald-800'
                            }`}
                          >
                            {im.finalidade === 'venda' ? 'Venda' : 'Aluguel'}
                          </span>
                        </td>
                        <td className="p-3 text-sm whitespace-nowrap">R$ {formatMoney(im.preco)}</td>
                        <td className="p-3 text-sm text-center">
                          {im.dormitorios > 0 ? im.dormitorios : '-'}
                        </td>
                        <td className="p-3 text-sm whitespace-nowrap">
                          {im.area > 0 ? `${im.area}m²` : '-'}
                        </td>
                        <td className="p-3 text-sm text-muted-foreground">{im.localizacao}</td>

                        {/* Toggle Portal */}
                        <td className="p-3 text-center">
                          <button
                            type="button"
                            onClick={() => handleToggle(im.id, 'exibir', im.exibir)}
                            disabled={togglingId === im.id}
                            title={
                              im.exibir
                                ? 'Publicado — clique para ocultar'
                                : 'Oculto — clique para publicar'
                            }
                            className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium transition-colors disabled:opacity-60 ${
                              im.exibir
                                ? 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200'
                                : 'bg-muted text-muted-foreground hover:bg-accent'
                            }`}
                          >
                            <Globe size={11} />
                            {im.exibir ? 'Sim' : 'Não'}
                          </button>
                        </td>

                        {/* Toggle Destaque */}
                        <td className="p-3 text-center">
                          <button
                            type="button"
                            onClick={() => handleToggle(im.id, 'destaque', im.destaque)}
                            disabled={togglingId === im.id}
                            title={
                              im.destaque
                                ? 'Em destaque — clique para remover'
                                : 'Sem destaque — clique para destacar'
                            }
                            className={`inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium transition-colors disabled:opacity-60 ${
                              im.destaque
                                ? 'bg-amber-100 text-amber-800 hover:bg-amber-200'
                                : 'bg-muted text-muted-foreground hover:bg-accent'
                            }`}
                          >
                            <Star size={11} />
                            {im.destaque ? 'Sim' : 'Não'}
                          </button>
                        </td>

                        {/* Ads Status */}
                        <td className="p-3 text-center">
                          <button
                            type="button"
                            onClick={() => setLocation(`/properties/${im.id}/editar`)}
                            title="Gerenciar anúncios"
                            className="inline-flex items-center justify-center gap-1"
                          >
                            <AdsStatusBadge id={im.id} />
                            {!adsStatusMap[im.id] && (
                              <Zap size={13} className="text-muted-foreground/30" />
                            )}
                          </button>
                        </td>

                        {/* Ações */}
                        <td className="p-3">
                          <div className="flex items-center gap-1">
                            <button
                              type="button"
                              onClick={() => setPreviewId(im.id)}
                              title="Ver no portal"
                              className="p-1.5 rounded-lg hover:bg-accent transition-colors"
                            >
                              <Eye size={15} />
                            </button>
                            <button
                              type="button"
                              onClick={() => setLocation(`/properties/${im.id}/editar`)}
                              title="Editar (wizard)"
                              className="p-1.5 rounded-lg hover:bg-accent transition-colors"
                            >
                              <Pencil size={15} />
                            </button>
                            <button
                              type="button"
                              onClick={() => handleDelete(im)}
                              title="Excluir"
                              className="p-1.5 rounded-lg hover:bg-red-50 text-red-500 transition-colors"
                            >
                              <Trash2 size={15} />
                            </button>
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
