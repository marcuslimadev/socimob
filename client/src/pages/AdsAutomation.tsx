import { useEffect, useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import Sidebar from '@/components/Sidebar';
import { toast } from 'sonner';
import {
  Zap, CheckCircle2, XCircle, AlertTriangle, RefreshCw,
  Settings2, ChevronDown, ChevronUp, ExternalLink, BarChart3,
  Facebook, Globe, Loader2, LogOut
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

// ─── Types ────────────────────────────────────────────────────────────────────

interface ProviderStatus {
  connected: boolean;
  status: string;
  expires_at: string | null;
  last_refresh_at: string | null;
  campaign_status: string | null;
  active_listings: number;
  budget_daily: number;
}

interface AdsStatus {
  entitlement: {
    plan_code: string;
    providers_allowed: string[];
    max_listings_per_day: number;
    max_budget_daily_reais: number;
    valid_until: string | null;
  } | null;
  providers: Record<string, ProviderStatus>;
}

interface AuditLog {
  id: number;
  provider: string;
  entity_type: string;
  action: string;
  status: string;
  message: string;
  created_at: string;
}

interface AdsLeadStats {
  by_provider: { provider: string; total: number; duplicates: number; ingested: number }[];
  today: number;
}

// ─── Helpers ──────────────────────────────────────────────────────────────────

function statusBadge(status: string) {
  const map: Record<string, { color: string; label: string }> = {
    CONNECTED: { color: 'bg-green-100 text-green-800', label: 'Conectado' },
    READY:     { color: 'bg-green-100 text-green-800', label: 'Pronto'    },
    DRAFT:     { color: 'bg-gray-100 text-gray-600',   label: 'Pendente'  },
    ERROR:     { color: 'bg-red-100 text-red-700',     label: 'Erro'      },
    PAUSED:    { color: 'bg-yellow-100 text-yellow-800', label: 'Pausado'  },
  };
  const s = map[status] ?? { color: 'bg-gray-100 text-gray-500', label: status };
  return <span className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${s.color}`}>{s.label}</span>;
}

function logStatusBadge(status: string) {
  if (status === 'SUCCESS') return <Badge className="bg-green-100 text-green-800 text-xs">OK</Badge>;
  if (status === 'ERROR')   return <Badge className="bg-red-100 text-red-700 text-xs">Erro</Badge>;
  return <Badge className="bg-gray-100 text-gray-600 text-xs">Ignorado</Badge>;
}

function providerIcon(provider: string) {
  if (provider === 'meta')   return <Facebook className="h-5 w-5 text-blue-600" />;
  if (provider === 'google') return <Globe className="h-5 w-5 text-green-600" />;
  return <Zap className="h-5 w-5 text-gray-400" />;
}

function formatDate(d: string | null) {
  if (!d) return '-';
  return new Date(d).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
}

// ─── ProviderCard Component ───────────────────────────────────────────────────

function ProviderCard({
  provider,
  status,
  allowed,
  onConnect,
  onDisconnect,
  onSaveSettings,
  isConnecting,
}: {
  provider: string;
  status: ProviderStatus;
  allowed: boolean;
  onConnect: (provider: string) => void;
  onDisconnect: (provider: string) => void;
  onSaveSettings: (provider: string, budget: number, region: string) => void;
  isConnecting: boolean;
}) {
  const [showSettings, setShowSettings] = useState(false);
  const [budget, setBudget] = useState(String(status.budget_daily || ''));
  const [region, setRegion] = useState('');

  const label = provider === 'meta' ? 'Meta (Facebook/Instagram)' : 'Google Ads';

  return (
    <Card className={`border ${status.connected ? 'border-green-200' : 'border-gray-200'}`}>
      <CardHeader className="pb-3">
        <div className="flex items-center justify-between">
          <div className="flex items-center gap-2">
            {providerIcon(provider)}
            <CardTitle className="text-base">{label}</CardTitle>
          </div>
          <div className="flex items-center gap-2">
            {statusBadge(status.status)}
            {!allowed && (
              <Badge className="bg-purple-100 text-purple-700 text-xs">Requer upgrade</Badge>
            )}
          </div>
        </div>
        <CardDescription className="text-xs text-gray-500">
          {status.connected
            ? `Imóveis ativos: ${status.active_listings} · Orçamento: R$ ${status.budget_daily}/dia`
            : 'Não conectado'}
        </CardDescription>
      </CardHeader>
      <CardContent className="space-y-3">
        {/* Botões de ação */}
        <div className="flex gap-2">
          {!status.connected ? (
            <Button
              size="sm"
              onClick={() => onConnect(provider)}
              disabled={!allowed || isConnecting}
              className="flex-1"
            >
              {isConnecting ? <Loader2 className="h-4 w-4 animate-spin mr-1" /> : null}
              Conectar
            </Button>
          ) : (
            <>
              <Button
                size="sm"
                variant="outline"
                onClick={() => setShowSettings(!showSettings)}
                className="flex-1 gap-1"
              >
                <Settings2 className="h-3.5 w-3.5" />
                Configurar
                {showSettings ? <ChevronUp className="h-3.5 w-3.5" /> : <ChevronDown className="h-3.5 w-3.5" />}
              </Button>
              <Button
                size="sm"
                variant="ghost"
                className="text-red-600 hover:text-red-700"
                onClick={() => onDisconnect(provider)}
              >
                <LogOut className="h-4 w-4" />
              </Button>
            </>
          )}
        </div>

        {/* Configurações expandíveis */}
        {showSettings && status.connected && (
          <div className="space-y-3 pt-2 border-t border-gray-100">
            <div className="space-y-1">
              <Label className="text-xs">Orçamento diário (R$)</Label>
              <Input
                type="number"
                placeholder="Ex: 30"
                value={budget}
                onChange={e => setBudget(e.target.value)}
                className="h-8 text-sm"
              />
            </div>
            <div className="space-y-1">
              <Label className="text-xs">Região de segmentação</Label>
              <Input
                placeholder="Ex: São Paulo, SP"
                value={region}
                onChange={e => setRegion(e.target.value)}
                className="h-8 text-sm"
              />
            </div>
            <Button
              size="sm"
              className="w-full"
              onClick={() => onSaveSettings(provider, parseFloat(budget) || 0, region)}
            >
              Salvar configurações
            </Button>
          </div>
        )}

        {/* Info: última sync, expiração */}
        {status.connected && (
          <div className="text-xs text-gray-400 space-y-0.5">
            {status.expires_at && (
              <p>Token expira: {formatDate(status.expires_at)}</p>
            )}
            {status.campaign_status && (
              <p>Campanha: {status.campaign_status}</p>
            )}
          </div>
        )}
      </CardContent>
    </Card>
  );
}

// ─── Main Page ────────────────────────────────────────────────────────────────

export default function AdsAutomation() {
  const queryClient = useQueryClient();
  const [logFilter, setLogFilter] = useState<{ provider: string; status: string }>({ provider: '', status: '' });
  const [connectingProvider, setConnectingProvider] = useState<string | null>(null);

  // Fetch status
  const { data: statusData, isLoading: statusLoading, refetch: refetchStatus } = useQuery<AdsStatus>({
    queryKey: ['ads-status'],
    queryFn: async () => {
      const r = await api.get('/ads/status');
      return r.data;
    },
    refetchInterval: 30000,
  });

  // Fetch logs
  const { data: logsData, isLoading: logsLoading } = useQuery({
    queryKey: ['ads-logs', logFilter],
    queryFn: async () => {
      const r = await api.get('/ads/logs', {
        params: { ...logFilter, per_page: 50 },
      });
      return r.data;
    },
  });

  // Fetch lead stats
  const { data: leadStats } = useQuery<AdsLeadStats>({
    queryKey: ['ads-leads-stats'],
    queryFn: async () => {
      const r = await api.get('/ads/leads/stats');
      return r.data.data;
    },
    refetchInterval: 60000,
  });

  // Connect
  const connectMutation = useMutation({
    mutationFn: async (provider: string) => {
      const r = await api.post(`/ads/${provider}/connect/start`);
      return r.data;
    },
    onSuccess: (data) => {
      if (data.oauth_url) {
        window.open(data.oauth_url, '_blank', 'width=600,height=700');
        toast.success('Janela OAuth aberta. Autorize e volte para esta página.');
      }
    },
    onError: (err: any) => {
      toast.error(err?.response?.data?.error || 'Erro ao iniciar conexão.');
    },
    onSettled: () => setConnectingProvider(null),
  });

  // Disconnect
  const disconnectMutation = useMutation({
    mutationFn: async (provider: string) => {
      await api.delete(`/ads/${provider}/connect`);
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['ads-status'] });
      toast.success('Conta desconectada.');
    },
    onError: () => toast.error('Erro ao desconectar.'),
  });

  // Save settings
  const settingsMutation = useMutation({
    mutationFn: async ({ provider, budget, region }: { provider: string; budget: number; region: string }) => {
      await api.post('/ads/settings', { provider, budget_daily_reais: budget, region });
    },
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['ads-status'] });
      toast.success('Configurações salvas!');
    },
    onError: (err: any) => toast.error(err?.response?.data?.error || 'Erro ao salvar.'),
  });

  const handleConnect = (provider: string) => {
    setConnectingProvider(provider);
    connectMutation.mutate(provider);
  };

  const handleDisconnect = (provider: string) => {
    if (window.confirm(`Desconectar ${provider}? Os anúncios existentes serão pausados.`)) {
      disconnectMutation.mutate(provider);
    }
  };

  const handleSaveSettings = (provider: string, budget: number, region: string) => {
    settingsMutation.mutate({ provider, budget, region });
  };

  const entitlement = statusData?.entitlement;
  const providers = statusData?.providers ?? {};
  const allowedProviders = entitlement?.providers_allowed ?? [];

  const logs: AuditLog[] = logsData?.data ?? [];

  if (statusLoading) {
    return (
      <div className="flex h-screen bg-gray-50">
        <Sidebar />
        <div className="flex-1 flex items-center justify-center">
          <Loader2 className="h-8 w-8 animate-spin text-gray-400" />
        </div>
      </div>
    );
  }

  return (
    <div className="flex h-screen bg-gray-50">
      <Sidebar />
      <div className="flex-1 overflow-auto">
        <div className="p-6 max-w-5xl mx-auto space-y-6">

          {/* Header */}
          <div className="flex items-center justify-between">
            <div>
              <h1 className="text-2xl font-bold text-gray-900 flex items-center gap-2">
                <Zap className="h-6 w-6 text-yellow-500" />
                Marketing / Anúncios
              </h1>
              <p className="text-sm text-gray-500 mt-0.5">
                Publique imóveis no Meta e Google com 1 clique. Leads entram direto no CRM.
              </p>
            </div>
            <Button variant="outline" size="sm" onClick={() => refetchStatus()} className="gap-1">
              <RefreshCw className="h-4 w-4" />
              Atualizar
            </Button>
          </div>

          {/* Plan badge */}
          {entitlement ? (
            <div className="flex items-center gap-3 bg-white border border-gray-200 rounded-lg p-3 text-sm">
              <CheckCircle2 className="h-4 w-4 text-green-500 flex-shrink-0" />
              <span className="font-medium">{entitlement.plan_code}</span>
              <span className="text-gray-400">·</span>
              <span className="text-gray-600">Máx. {entitlement.max_listings_per_day} imóveis/dia</span>
              <span className="text-gray-400">·</span>
              <span className="text-gray-600">Orçamento máx. R$ {entitlement.max_budget_daily_reais}/dia</span>
              {entitlement.valid_until && (
                <>
                  <span className="text-gray-400">·</span>
                  <span className="text-gray-500 text-xs">Válido até {formatDate(entitlement.valid_until)}</span>
                </>
              )}
            </div>
          ) : (
            <div className="flex items-center gap-3 bg-amber-50 border border-amber-200 rounded-lg p-3 text-sm">
              <AlertTriangle className="h-4 w-4 text-amber-600 flex-shrink-0" />
              <span className="text-amber-800">Nenhum plano de Ads ativo. Contate o suporte para ativar.</span>
            </div>
          )}

          {/* Stats row */}
          {leadStats && (
            <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
              <Card className="border-0 shadow-sm">
                <CardContent className="p-4 text-center">
                  <p className="text-2xl font-bold text-gray-900">{leadStats.today}</p>
                  <p className="text-xs text-gray-500 mt-0.5">Leads hoje</p>
                </CardContent>
              </Card>
              {leadStats.by_provider?.map(p => (
                <Card key={p.provider} className="border-0 shadow-sm">
                  <CardContent className="p-4 text-center">
                    <p className="text-2xl font-bold text-gray-900">{p.total}</p>
                    <p className="text-xs text-gray-500 mt-0.5">{p.provider === 'meta' ? 'Leads Meta' : 'Leads Google'}</p>
                    {p.duplicates > 0 && (
                      <p className="text-xs text-amber-500">{p.duplicates} dupl.</p>
                    )}
                  </CardContent>
                </Card>
              ))}
              <Card className="border-0 shadow-sm">
                <CardContent className="p-4 text-center">
                  <p className="text-2xl font-bold text-gray-900">
                    {Object.values(providers).reduce((s, p) => s + (p.active_listings ?? 0), 0)}
                  </p>
                  <p className="text-xs text-gray-500 mt-0.5">Imóveis publicados</p>
                </CardContent>
              </Card>
            </div>
          )}

          {/* Provider cards */}
          <div>
            <h2 className="text-sm font-semibold text-gray-700 mb-3 uppercase tracking-wide">Provedores</h2>
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              {(['meta', 'google'] as const).map(provider => (
                <ProviderCard
                  key={provider}
                  provider={provider}
                  status={providers[provider] ?? { connected: false, status: 'DRAFT', expires_at: null, last_refresh_at: null, campaign_status: null, active_listings: 0, budget_daily: 0 }}
                  allowed={allowedProviders.includes(provider)}
                  onConnect={handleConnect}
                  onDisconnect={handleDisconnect}
                  onSaveSettings={handleSaveSettings}
                  isConnecting={connectingProvider === provider && connectMutation.isPending}
                />
              ))}
            </div>
          </div>

          {/* Audit Logs */}
          <Card className="border-gray-200">
            <CardHeader className="pb-3">
              <div className="flex items-center justify-between">
                <CardTitle className="text-base flex items-center gap-2">
                  <BarChart3 className="h-4 w-4" />
                  Logs recentes
                </CardTitle>
                <div className="flex gap-2">
                  <Select value={logFilter.provider} onValueChange={v => setLogFilter(f => ({ ...f, provider: v === 'all' ? '' : v }))}>
                    <SelectTrigger className="h-8 w-28 text-xs">
                      <SelectValue placeholder="Provider" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="all">Todos</SelectItem>
                      <SelectItem value="meta">Meta</SelectItem>
                      <SelectItem value="google">Google</SelectItem>
                    </SelectContent>
                  </Select>
                  <Select value={logFilter.status} onValueChange={v => setLogFilter(f => ({ ...f, status: v === 'all' ? '' : v }))}>
                    <SelectTrigger className="h-8 w-28 text-xs">
                      <SelectValue placeholder="Status" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="all">Todos</SelectItem>
                      <SelectItem value="success">OK</SelectItem>
                      <SelectItem value="error">Erro</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>
            </CardHeader>
            <CardContent className="p-0">
              {logsLoading ? (
                <div className="p-6 flex justify-center">
                  <Loader2 className="h-5 w-5 animate-spin text-gray-300" />
                </div>
              ) : logs.length === 0 ? (
                <div className="p-6 text-center text-sm text-gray-400">
                  Nenhum log encontrado.
                </div>
              ) : (
                <div className="divide-y divide-gray-50">
                  {logs.map(log => (
                    <div key={log.id} className="flex items-start justify-between px-4 py-2.5 text-sm hover:bg-gray-50">
                      <div className="flex items-start gap-2 min-w-0">
                        <span className="mt-0.5 flex-shrink-0">{providerIcon(log.provider || '')}</span>
                        <div className="min-w-0">
                          <span className="font-medium text-gray-700 text-xs">{log.action}</span>
                          {log.message && (
                            <p className="text-xs text-gray-400 truncate max-w-xs">{log.message}</p>
                          )}
                        </div>
                      </div>
                      <div className="flex items-center gap-2 flex-shrink-0 ml-2">
                        {logStatusBadge(log.status)}
                        <span className="text-xs text-gray-400">{formatDate(log.created_at)}</span>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>

        </div>
      </div>
    </div>
  );
}
