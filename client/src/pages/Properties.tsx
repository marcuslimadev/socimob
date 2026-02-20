import { useState, useEffect, useMemo } from 'react';
import { Search, Plus, Eye, Pencil, Trash2, RefreshCw, Download, Star, Globe, Loader2, X } from 'lucide-react';
import { useLocation } from 'wouter';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';
import { toast } from 'sonner';

interface ImovelRow {
  id: string;
  codigo: string;
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
  const itensPorPagina = 25;

  const fetchImoveis = async () => {
    setIsLoading(true);
    try {
      const response = await api.get('/imoveis', { params: { per_page: 'all' } });
      const rows = Array.isArray(response.data?.data) ? response.data.data : [];
      setImoveis(
        rows.map((item: any) => ({
          id: String(item.id),
          codigo: item.codigo_imovel || item.codigo || '-',
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
  }, [busca, tipoFiltro, finalidadeFiltro, portalFiltro, destaqueFiltro]);

  const imoveisFiltrados = useMemo(() => {
    const termo = busca.trim().toLowerCase();
    return imoveis.filter((im) => {
      if (termo && !`${im.titulo} ${im.codigo} ${im.localizacao}`.toLowerCase().includes(termo))
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

  const totalPaginas = Math.max(1, Math.ceil(imoveisFiltrados.length / itensPorPagina));

  const imoveisPaginados = useMemo(() => {
    const start = (pagina - 1) * itensPorPagina;
    return imoveisFiltrados.slice(start, start + itensPorPagina);
  }, [imoveisFiltrados, pagina]);

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
                  value={busca}
                  onChange={(e) => setBusca(e.target.value)}
                  placeholder="Código, título ou localização..."
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
              className="flex items-center gap-1 px-4 py-2 rounded-lg border border-border hover:bg-accent text-sm"
            >
              <X size={13} /> Limpar
            </button>
          </div>

          {/* Tabela */}
          {isLoading ? (
            <div className="glass-panel rounded-2xl p-12 flex justify-center">
              <Loader2 className="animate-spin" />
            </div>
          ) : (
            <div className="glass-panel rounded-2xl overflow-auto">
              {imoveisPaginados.length === 0 ? (
                <p className="p-8 text-center text-sm text-muted-foreground">
                  Nenhum imóvel encontrado para os filtros atuais.
                </p>
              ) : (
                <table className="w-full min-w-[960px]">
                  <thead>
                    <tr className="border-b border-border">
                      <th className="text-left p-3 text-xs text-muted-foreground w-16">Foto</th>
                      <th className="text-left p-3 text-xs text-muted-foreground">Código</th>
                      <th className="text-left p-3 text-xs text-muted-foreground">Título</th>
                      <th className="text-left p-3 text-xs text-muted-foreground">Tipo</th>
                      <th className="text-left p-3 text-xs text-muted-foreground">Finalidade</th>
                      <th className="text-left p-3 text-xs text-muted-foreground">Preço</th>
                      <th className="text-center p-3 text-xs text-muted-foreground">Dorm.</th>
                      <th className="text-left p-3 text-xs text-muted-foreground">Área</th>
                      <th className="text-left p-3 text-xs text-muted-foreground">Localização</th>
                      <th className="text-center p-3 text-xs text-muted-foreground">
                        <span className="inline-flex items-center gap-1"><Globe size={11} /> Portal</span>
                      </th>
                      <th className="text-center p-3 text-xs text-muted-foreground">
                        <span className="inline-flex items-center gap-1"><Star size={11} /> Destaque</span>
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
                        <td className="p-3 text-sm text-muted-foreground font-mono">{im.codigo}</td>
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

                        {/* Ações */}
                        <td className="p-3">
                          <div className="flex items-center gap-1">
                            <button
                              type="button"
                              onClick={() => setLocation(`/portal/imovel/${im.id}`)}
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
              )}
            </div>
          )}

          {/* Paginação */}
          {!isLoading && (
            <div className="glass-panel rounded-2xl p-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
              <p className="text-sm text-muted-foreground">
                {imoveisFiltrados.length} imóvel(is) — página {pagina} de {totalPaginas}
              </p>
              <div className="flex items-center gap-2">
                <button
                  type="button"
                  onClick={() => setPagina((p) => Math.max(1, p - 1))}
                  disabled={pagina <= 1}
                  className="px-3 py-1.5 rounded-lg border border-border hover:bg-accent disabled:opacity-60 text-sm"
                >
                  Anterior
                </button>
                <button
                  type="button"
                  onClick={() => setPagina((p) => Math.min(totalPaginas, p + 1))}
                  disabled={pagina >= totalPaginas}
                  className="px-3 py-1.5 rounded-lg border border-border hover:bg-accent disabled:opacity-60 text-sm"
                >
                  Próxima
                </button>
              </div>
            </div>
          )}

        </div>
      </div>
    </div>
  );
}
