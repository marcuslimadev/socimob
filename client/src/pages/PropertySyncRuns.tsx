import { useEffect, useMemo, useState } from 'react';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';
import { RefreshCw, PlayCircle, CheckCircle2, XCircle, Clock3, Timer } from 'lucide-react';
import { toast } from 'sonner';

type SyncRunStatus = 'running' | 'success' | 'failed';

interface SyncRun {
  id: number;
  tenant_id: number;
  triggered_by_user_id: number | null;
  trigger_type: string;
  status: SyncRunStatus;
  started_at: string | null;
  finished_at: string | null;
  duration_ms: number | null;
  result_payload: {
    success?: boolean;
    stats?: Record<string, number>;
    progress?: {
      phase?: string;
      processed?: number;
      total?: number;
      percent?: number;
      current_page?: number | null;
      total_pages?: number | null;
      current_code?: string | null;
      done?: boolean;
      updated_at?: string;
    };
    error?: string;
  } | null;
  error_message: string | null;
  created_at: string;
}

interface PaginatedResponse {
  data: SyncRun[];
  current_page: number;
  last_page: number;
  total: number;
}

function formatDateTime(value?: string | null) {
  if (!value) return '-';
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return '-';
  return date.toLocaleString('pt-BR');
}

function formatDuration(ms?: number | null) {
  if (!ms || ms < 0) return '-';
  if (ms < 1000) return `${ms} ms`;
  return `${(ms / 1000).toFixed(1)} s`;
}

function getProgressPercent(run?: SyncRun | null) {
  if (!run?.result_payload?.progress) return 0;
  const raw = Number(run.result_payload.progress.percent ?? 0);
  if (Number.isNaN(raw)) return 0;
  return Math.max(0, Math.min(100, Math.round(raw)));
}

export default function PropertySyncRuns() {
  const [runs, setRuns] = useState<SyncRun[]>([]);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [loading, setLoading] = useState(false);
  const [runningManual, setRunningManual] = useState(false);

  const activeRun = useMemo(() => runs.find((run) => run.status === 'running') || null, [runs]);

  const fetchRuns = async (targetPage = page, silent = false) => {
    if (!silent) {
      setLoading(true);
    }
    try {
      const response = await api.get<PaginatedResponse>('/admin/imoveis/sincronizacoes', {
        params: { page: targetPage, per_page: 15 },
      });

      setRuns(response.data.data || []);
      setPage(response.data.current_page || 1);
      setLastPage(response.data.last_page || 1);
      setTotal(response.data.total || 0);
    } catch (error) {
      if (!silent) {
        toast.error('Erro ao carregar histórico de sincronizações');
      }
    } finally {
      if (!silent) {
        setLoading(false);
      }
    }
  };

  const runManualSync = async () => {
    setRunningManual(true);
    try {
      const response = await api.post('/admin/imoveis/sincronizacoes/executar');
      if (response.data?.success) {
        toast.success('Sincronização executada com sucesso');
      } else {
        toast.error(response.data?.error || 'Sincronização finalizou com falha');
      }
      await fetchRuns(1);
    } catch (error: any) {
      const message = error?.response?.data?.error || 'Erro ao executar sincronização manual';
      toast.error(message);
      await fetchRuns(1);
    } finally {
      setRunningManual(false);
    }
  };

  useEffect(() => {
    fetchRuns(1);
  }, []);

  useEffect(() => {
    if (!activeRun) return;

    const interval = setInterval(() => {
      fetchRuns(page, true);
    }, 3000);

    return () => clearInterval(interval);
  }, [activeRun, page]);

  const activeProgress = activeRun?.result_payload?.progress;
  const activePercent = getProgressPercent(activeRun);

  return (
    <div className="flex min-h-screen bg-white text-gray-900 dark:bg-[#0f0f0f] dark:text-gray-100">
      <Sidebar />
      <main className="page-shell overflow-y-auto">
        <div className="mx-auto max-w-7xl space-y-6">
          <div className="page-header flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h1 className="text-3xl font-bold text-gray-900 dark:text-white">Sincronizações de Imóveis</h1>
              <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Veja o histórico das sincronizações e execute manualmente quando necessário.
              </p>
            </div>
            <div className="flex flex-wrap gap-2">
              <button
                onClick={() => fetchRuns(page)}
                disabled={loading}
                className="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 disabled:opacity-60 dark:border-gray-700 dark:bg-[#1a1a1a] dark:text-gray-200 dark:hover:bg-[#252525]"
              >
                <RefreshCw size={16} className={loading ? 'animate-spin' : ''} />
                Atualizar
              </button>
              <button
                onClick={runManualSync}
                disabled={runningManual || !!activeRun}
                className="inline-flex items-center gap-2 rounded-lg border border-gray-900 bg-gray-900 px-4 py-2 text-sm font-semibold text-white hover:bg-black disabled:opacity-60 dark:border-[#2d6fab] dark:bg-[#2d6fab] dark:hover:bg-[#245986]"
              >
                <PlayCircle size={16} className={runningManual ? 'animate-pulse' : ''} />
                Executar sincronização manual
              </button>
            </div>
          </div>

          <section className="grid grid-cols-1 gap-3 md:grid-cols-4">
            <div className="rounded-xl border border-gray-300 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-[#1a1a1a] dark:shadow-none">
              <p className="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-500">Total de execuções</p>
              <p className="mt-2 text-2xl font-bold text-gray-900 dark:text-white">{total}</p>
            </div>
            <div className="rounded-xl border border-gray-300 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-[#1a1a1a] dark:shadow-none">
              <p className="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-500">Sucesso nesta página</p>
              <p className="mt-2 text-2xl font-bold text-gray-900 dark:text-emerald-400">
                {runs.filter((run) => run.status === 'success').length}
              </p>
            </div>
            <div className="rounded-xl border border-gray-300 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-[#1a1a1a] dark:shadow-none">
              <p className="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-500">Falhas nesta página</p>
              <p className="mt-2 text-2xl font-bold text-gray-900 dark:text-rose-400">
                {runs.filter((run) => run.status === 'failed').length}
              </p>
            </div>
            <div className="rounded-xl border border-gray-300 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-[#1a1a1a] dark:shadow-none">
              <p className="text-xs uppercase tracking-wide text-gray-500 dark:text-gray-500">Execução em andamento</p>
              <p className="mt-2 text-sm font-semibold text-gray-800 dark:text-amber-300">
                {activeRun ? `ID #${activeRun.id}` : 'Nenhuma'}
              </p>
            </div>
          </section>

          {activeRun && (
            <section className="rounded-xl border border-blue-200 bg-blue-50/70 p-4 shadow-sm dark:border-blue-900/40 dark:bg-blue-950/20 dark:shadow-none">
              <div className="flex items-center justify-between text-xs font-semibold uppercase tracking-wide text-blue-800 dark:text-blue-300">
                <span>Progresso da execução #{activeRun.id}</span>
                <span>{activePercent}%</span>
              </div>
              <div className="mt-2 h-2 w-full overflow-hidden rounded-full bg-blue-100 dark:bg-blue-900/50">
                <div
                  className="h-full rounded-full bg-blue-600 transition-all duration-500"
                  style={{ width: `${activePercent}%` }}
                />
              </div>
              <p className="mt-2 text-xs text-blue-900 dark:text-blue-200">
                Processados: {activeProgress?.processed ?? 0}
                {activeProgress?.total ? ` de ${activeProgress.total}` : ''}
                {activeProgress?.current_page ? ` | Página ${activeProgress.current_page}` : ''}
                {activeProgress?.total_pages ? `/${activeProgress.total_pages}` : ''}
                {activeProgress?.current_code ? ` | Código atual: ${activeProgress.current_code}` : ''}
              </p>
            </section>
          )}

          <section className="overflow-hidden rounded-xl border border-gray-300 bg-white shadow-sm dark:border-gray-800 dark:bg-[#141414] dark:shadow-none">
            <div className="overflow-x-auto">
              <table className="w-full min-w-[980px] border-collapse">
                <thead className="bg-gray-100 dark:bg-[#1c1c1c]">
                  <tr className="text-left text-xs uppercase tracking-wide text-gray-600 dark:text-gray-400">
                    <th className="px-4 py-3">ID</th>
                    <th className="px-4 py-3">Status</th>
                    <th className="px-4 py-3">Início</th>
                    <th className="px-4 py-3">Fim</th>
                    <th className="px-4 py-3">Duração</th>
                    <th className="px-4 py-3">Resumo</th>
                    <th className="px-4 py-3">Erro</th>
                  </tr>
                </thead>
                <tbody>
                  {loading && runs.length === 0 ? (
                    <tr>
                      <td colSpan={7} className="px-4 py-10 text-center text-sm text-gray-400">
                        Carregando histórico...
                      </td>
                    </tr>
                  ) : runs.length === 0 ? (
                    <tr>
                      <td colSpan={7} className="px-4 py-10 text-center text-sm text-gray-400">
                        Ainda não existem sincronizações registradas.
                      </td>
                    </tr>
                  ) : (
                    runs.map((run) => {
                      const stats = run.result_payload?.stats || {};
                      const progress = run.result_payload?.progress;
                      const percent = getProgressPercent(run);
                      const summary = [
                        `Encontrados: ${stats.found ?? 0}`,
                        `Novos: ${stats.new ?? 0}`,
                        `Atualizados: ${stats.updated ?? 0}`,
                        `Erros: ${stats.errors ?? 0}`,
                      ].join(' | ');

                      const runningSummary = run.status === 'running'
                        ? `Processados: ${progress?.processed ?? 0}${progress?.total ? `/${progress.total}` : ''} (${percent}%)`
                        : summary;

                      return (
                        <tr key={run.id} className="border-t border-gray-200 text-sm text-gray-700 dark:border-gray-800 dark:text-gray-200">
                          <td className="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-300">#{run.id}</td>
                          <td className="px-4 py-3">
                            {run.status === 'success' && (
                              <span className="inline-flex items-center gap-1 rounded-full border border-gray-300 bg-gray-100 px-2 py-1 text-xs text-gray-700 dark:border-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-300">
                                <CheckCircle2 size={13} /> Sucesso
                              </span>
                            )}
                            {run.status === 'failed' && (
                              <span className="inline-flex items-center gap-1 rounded-full border border-gray-300 bg-gray-100 px-2 py-1 text-xs text-gray-700 dark:border-rose-800 dark:bg-rose-900/20 dark:text-rose-300">
                                <XCircle size={13} /> Falha
                              </span>
                            )}
                            {run.status === 'running' && (
                              <span className="inline-flex items-center gap-1 rounded-full border border-gray-300 bg-gray-100 px-2 py-1 text-xs text-gray-700 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
                                <Clock3 size={13} /> Em execução
                              </span>
                            )}
                          </td>
                          <td className="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">{formatDateTime(run.started_at)}</td>
                          <td className="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">{formatDateTime(run.finished_at)}</td>
                          <td className="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">
                            <span className="inline-flex items-center gap-1">
                              <Timer size={13} /> {formatDuration(run.duration_ms)}
                            </span>
                          </td>
                          <td className="px-4 py-3 text-xs text-gray-600 dark:text-gray-300">
                            <div>{runningSummary}</div>
                            {run.status === 'running' && (
                              <div className="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-gray-800">
                                <div
                                  className="h-full rounded-full bg-blue-600 transition-all duration-500"
                                  style={{ width: `${percent}%` }}
                                />
                              </div>
                            )}
                          </td>
                          <td className="max-w-[280px] px-4 py-3 text-xs text-gray-700 dark:text-rose-300">
                            {run.error_message || run.result_payload?.error || '-'}
                          </td>
                        </tr>
                      );
                    })
                  )}
                </tbody>
              </table>
            </div>

            <div className="flex items-center justify-between border-t border-gray-300 bg-gray-50 px-4 py-3 text-sm text-gray-700 dark:border-gray-800 dark:bg-[#1a1a1a] dark:text-gray-300">
              <span>
                Página {page} de {lastPage}
              </span>
              <div className="flex gap-2">
                <button
                  onClick={() => fetchRuns(page - 1)}
                  disabled={page <= 1 || loading}
                  className="rounded border border-gray-300 bg-white px-3 py-1.5 text-gray-700 hover:bg-gray-100 disabled:opacity-50 dark:border-gray-700 dark:bg-transparent dark:text-gray-300 dark:hover:bg-[#252525]"
                >
                  Anterior
                </button>
                <button
                  onClick={() => fetchRuns(page + 1)}
                  disabled={page >= lastPage || loading}
                  className="rounded border border-gray-300 bg-white px-3 py-1.5 text-gray-700 hover:bg-gray-100 disabled:opacity-50 dark:border-gray-700 dark:bg-transparent dark:text-gray-300 dark:hover:bg-[#252525]"
                >
                  Próxima
                </button>
              </div>
            </div>
          </section>
        </div>
      </main>
    </div>
  );
}
