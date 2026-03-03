import { useEffect, useState } from 'react';
import { Loader2 } from 'lucide-react';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';

interface OverviewResponse {
  success: boolean;
  days: number;
  summary?: {
    pageviews: number;
    sessions: number;
    unique_visitors: number;
  };
  top_pages?: Array<{ path: string; total: number }>;
  top_referrers?: Array<{ referrer: string; total: number }>;
  devices?: Array<{ device_type: string; total: number }>;
  browsers?: Array<{ browser: string; total: number }>;
  events?: Array<{ event_name: string; total: number }>;
  tenants?: Array<{ tenant_id: number; tenant_name: string; tenant_domain: string; events: number; sessions: number; pageviews: number }>;
}

export default function Analytics() {
  const [loading, setLoading] = useState(true);
  const [days, setDays] = useState(30);
  const [data, setData] = useState<OverviewResponse | null>(null);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    const load = async () => {
      try {
        setLoading(true);
        setError(null);
        const userRaw = localStorage.getItem('user');
        const role = userRaw ? JSON.parse(userRaw)?.role : null;
        const url = role === 'super_admin'
          ? `/super-admin/analytics/overview?days=${days}`
          : `/admin/analytics/overview?days=${days}`;
        const response = await api.get(url);
        setData(response.data);
      } catch (err: any) {
        setError(err?.response?.data?.message || 'Erro ao carregar estatísticas');
      } finally {
        setLoading(false);
      }
    };
    load();
  }, [days]);

  return (
    <div className="flex">
      <Sidebar />
      <div className="page-shell">
        <div className="max-w-6xl mx-auto">
          <div className="mb-8">
            <h1 className="page-title mb-2">Estatísticas</h1>
            <p className="page-subtitle">Resumo de acessos e comportamento</p>
          </div>

          <div className="glass-panel p-4 rounded-2xl mb-6 flex items-center gap-3">
            <span className="text-sm text-muted-foreground">Período</span>
            <select
              value={days}
              onChange={(e) => setDays(Number(e.target.value))}
              className="px-3 py-2 bg-muted/50 border border-border rounded-lg text-sm focus:outline-none"
            >
              <option value={7}>7 dias</option>
              <option value={30}>30 dias</option>
              <option value={90}>90 dias</option>
              <option value={180}>180 dias</option>
            </select>
          </div>

          {loading && (
            <div className="flex items-center gap-2 text-muted-foreground">
              <Loader2 className="w-4 h-4 animate-spin" />
              Carregando...
            </div>
          )}

          {error && (
            <div className="text-destructive">{error}</div>
          )}

          {!loading && data?.summary && (
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
              <div className="glass-panel p-4 rounded-2xl">
                <div className="text-sm text-muted-foreground">Pageviews</div>
                <div className="text-2xl font-bold text-foreground">{data.summary.pageviews}</div>
              </div>
              <div className="glass-panel p-4 rounded-2xl">
                <div className="text-sm text-muted-foreground">Sessões</div>
                <div className="text-2xl font-bold text-foreground">{data.summary.sessions}</div>
              </div>
              <div className="glass-panel p-4 rounded-2xl">
                <div className="text-sm text-muted-foreground">Visitantes únicos</div>
                <div className="text-2xl font-bold text-foreground">{data.summary.unique_visitors}</div>
              </div>
            </div>
          )}

          {!loading && data?.tenants && (
            <div className="glass-panel p-6 rounded-2xl">
              <h2 className="text-lg font-bold text-foreground mb-4">Tenants</h2>
              <div className="space-y-2">
                {data.tenants.map((tenant) => (
                  <div key={tenant.tenant_id} className="flex items-center justify-between text-sm">
                    <div>
                      <div className="font-semibold text-foreground">{tenant.tenant_name}</div>
                      <div className="text-muted-foreground">{tenant.tenant_domain}</div>
                    </div>
                    <div className="text-right">
                      <div className="text-foreground">{tenant.pageviews} views</div>
                      <div className="text-muted-foreground">{tenant.sessions} sessões</div>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

          {!loading && data && !data.tenants && (
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
              <div className="glass-panel p-6 rounded-2xl">
                <h2 className="text-lg font-bold text-foreground mb-4">Top páginas</h2>
                <div className="space-y-2 text-sm">
                  {(data.top_pages || []).map((item) => (
                    <div key={item.path} className="flex items-center justify-between">
                      <span className="text-foreground">{item.path || '/'}</span>
                      <span className="text-muted-foreground">{item.total}</span>
                    </div>
                  ))}
                </div>
              </div>

              <div className="glass-panel p-6 rounded-2xl">
                <h2 className="text-lg font-bold text-foreground mb-4">Top referrers</h2>
                <div className="space-y-2 text-sm">
                  {(data.top_referrers || []).map((item) => (
                    <div key={item.referrer} className="flex items-center justify-between">
                      <span className="text-foreground">{item.referrer || 'Direto'}</span>
                      <span className="text-muted-foreground">{item.total}</span>
                    </div>
                  ))}
                </div>
              </div>

              <div className="glass-panel p-6 rounded-2xl">
                <h2 className="text-lg font-bold text-foreground mb-4">Dispositivos</h2>
                <div className="space-y-2 text-sm">
                  {(data.devices || []).map((item) => (
                    <div key={item.device_type} className="flex items-center justify-between">
                      <span className="text-foreground">{item.device_type || 'Indefinido'}</span>
                      <span className="text-muted-foreground">{item.total}</span>
                    </div>
                  ))}
                </div>
              </div>

              <div className="glass-panel p-6 rounded-2xl">
                <h2 className="text-lg font-bold text-foreground mb-4">Navegadores</h2>
                <div className="space-y-2 text-sm">
                  {(data.browsers || []).map((item) => (
                    <div key={item.browser} className="flex items-center justify-between">
                      <span className="text-foreground">{item.browser || 'Indefinido'}</span>
                      <span className="text-muted-foreground">{item.total}</span>
                    </div>
                  ))}
                </div>
              </div>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
