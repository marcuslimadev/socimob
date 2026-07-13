import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { api } from '@/lib/api';
import Sidebar from '@/components/Sidebar';
import { toast } from 'sonner';
import {
  AreaChart, Area, BarChart, Bar, XAxis, YAxis, CartesianGrid, Tooltip,
  ResponsiveContainer,
} from 'recharts';
import {
  Zap, AlertTriangle, RefreshCw,
  Settings2, ChevronDown, ChevronUp, ExternalLink, BarChart3,
  Facebook, Globe, Loader2, LogOut, TrendingUp, Users, DollarSign,
  Home, Activity, ArrowUpRight, ArrowDownRight, Minus, Tag,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

interface ProviderStatus {
  connected: boolean;
  status: string;
  expires_at: string | null;
  last_refresh_at: string | null;
  campaign_status: string | null;
  active_listings: number;
  budget_daily: number;
  external_account_id: string | null;
  account_name: string | null;
  external_business_id: string | null;
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

interface AnalyticsSummary {
  total_leads: number;
  leads_today: number;
  leads_week: number;
  duplicate_rate: number;
  ingested_crm: number;
  active_listings_meta: number;
  active_listings_google: number;
  active_listings_olx: number;
  total_spend_estimate: number;
  budget_meta_daily: number;
  budget_google_daily: number;
  budget_olx_daily: number;
}

interface TimelinePoint { date: string; meta: number; google: number; olx: number; total: number; }
interface TopListing { listing_id: number; titulo: string; meta: number; google: number; olx: number; total: number; }
interface AnalyticsData {
  period_days: number;
  summary: AnalyticsSummary;
  timeline: TimelinePoint[];
  top_listings: TopListing[];
  recent_errors: { provider: string; action: string; message: string | null; created_at: string }[];
}

// ── Helpers ──────────────────────────────────────────────────────────────────

function fmt(d: string | null) {
  if (!d) return '–';
  return new Date(d).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
}
function fmtDate(d: string) {
  return new Date(d + 'T00:00:00').toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
}
function fmtR$(n: number) {
  return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL', minimumFractionDigits: 0 });
}
function TrendBadge({ curr, prev }: { curr: number; prev: number }) {
  if (prev === 0 && curr === 0) return <span className="text-xs text-gray-400">—</span>;
  if (prev === 0) return <span className="text-xs text-emerald-400 flex items-center gap-0.5"><ArrowUpRight size={12} />novo</span>;
  const pct = Math.round(((curr - prev) / prev) * 100);
  if (pct > 0) return <span className="text-xs text-emerald-400 flex items-center gap-0.5"><ArrowUpRight size={12} />{pct}%</span>;
  if (pct < 0) return <span className="text-xs text-rose-400 flex items-center gap-0.5"><ArrowDownRight size={12} />{Math.abs(pct)}%</span>;
  return <span className="text-xs text-gray-400 flex items-center gap-0.5"><Minus size={12} />0%</span>;
}
function providerIcon(p: string, size = 16) {
  if (p === 'meta')   return <Facebook size={size} className="text-blue-400" />;
  if (p === 'google') return <Globe    size={size} className="text-emerald-400" />;
  if (p === 'olx')    return <Tag      size={size} className="text-orange-400" />;
  return <Zap size={size} className="text-gray-400" />;
}

// ── KPI Card ─────────────────────────────────────────────────────────────────

function KpiCard({ label, value, sub, icon, color, trend }: {
  label: string; value: string | number; sub?: string;
  icon: React.ReactNode; color: string; trend?: React.ReactNode;
}) {
  return (
    <div className={`relative overflow-hidden rounded-2xl p-5 bg-gradient-to-br ${color} border border-white/10`}>
      <div className="flex items-start justify-between mb-3">
        <div className="p-2 rounded-xl bg-white/10">{icon}</div>
        {trend}
      </div>
      <p className="text-2xl font-bold text-white">{value}</p>
      <p className="text-sm text-white/70 mt-0.5">{label}</p>
      {sub && <p className="text-xs text-white/50 mt-1">{sub}</p>}
    </div>
  );
}

// ── Provider Card ─────────────────────────────────────────────────────────────

function ProviderCard({ provider, status, allowed, onConnect, onDisconnect, onSaveSettings, onConnectCredentials, onSaveAccount, isConnecting }: {
  provider: string; status: ProviderStatus; allowed: boolean;
  onConnect: (p: string) => void; onDisconnect: (p: string) => void;
  onSaveSettings: (p: string, budget: number, region: string) => void;
  onConnectCredentials: (p: string, id: string, secret: string) => void;
  onSaveAccount: (p: string, accountId: string, businessId: string, name: string) => void;
  isConnecting: boolean;
}) {
  const [open, setOpen] = useState(false);
  const [budget, setBudget] = useState(String(status.budget_daily || ''));
  const [region, setRegion] = useState('');
  const [showCredForm, setShowCredForm] = useState(false);
  const [credId, setCredId] = useState('');
  const [credSecret, setCredSecret] = useState('');
  const [accountId, setAccountId] = useState(status.external_account_id ?? '');
  const [businessId, setBusinessId] = useState(status.external_business_id ?? '');
  const [accountName, setAccountName] = useState(status.account_name ?? '');
  const label = provider === 'meta' ? 'Meta Ads (Facebook / Instagram)'
    : provider === 'olx' ? 'OLX Pro (Autoupload)'
    : 'Google Ads';
  const connected = status.connected;
  const statusColor: Record<string,string> = {
    CONNECTED: 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
    READY:     'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
    ERROR:     'bg-rose-500/20 text-rose-300 border-rose-500/30',
    PAUSED:    'bg-amber-500/20 text-amber-300 border-amber-500/30',
    DRAFT:     'bg-white/5 text-gray-400 border-white/10',
  };
  const sc = statusColor[status.status] ?? 'bg-white/5 text-gray-400 border-white/10';
  return (
    <div className={`rounded-2xl border p-5 space-y-4 transition-all ${connected ? 'bg-white/5 border-white/15' : 'bg-white/3 border-white/8 opacity-80'}`}>
      <div className="flex items-center justify-between">
        <div className="flex items-center gap-3">
          <div className="w-10 h-10 rounded-xl bg-white/8 flex items-center justify-center">
            {providerIcon(provider, 22)}
          </div>
          <div>
            <p className="text-sm font-semibold text-white">{label}</p>
            <p className="text-xs text-gray-400 mt-0.5">
              {connected ? `${status.active_listings} imóveis · R$ ${status.budget_daily}/dia` : 'Não conectado'}
            </p>
          </div>
        </div>
        <span className={`text-xs px-2.5 py-1 rounded-full border font-medium ${sc}`}>
          {connected ? 'Conectado' : 'Desconectado'}
        </span>
      </div>
      <div className="flex gap-2">
        {!connected ? (
          provider === 'olx' ? (
            <Button size="sm" onClick={() => setShowCredForm(v => !v)} disabled={!allowed}
              className="flex-1 bg-orange-600 hover:bg-orange-700 text-white gap-1.5">
              <Tag size={13} /> {showCredForm ? 'Cancelar' : 'Conectar com API Key'}
            </Button>
          ) : (
            <Button size="sm" onClick={() => onConnect(provider)} disabled={!allowed || isConnecting}
              className="flex-1 bg-blue-600 hover:bg-blue-700 text-white">
              {isConnecting ? <Loader2 className="h-3.5 w-3.5 animate-spin mr-1.5" /> : null}
              Conectar via OAuth
            </Button>
          )
        ) : (
          <>
            <Button size="sm" variant="outline" onClick={() => setOpen(!open)}
              className="flex-1 border-white/20 text-gray-300 hover:text-white hover:border-white/40 gap-1.5">
              <Settings2 size={13} /> Configurar {open ? <ChevronUp size={13} /> : <ChevronDown size={13} />}
            </Button>
            <Button size="sm" variant="ghost" onClick={() => onDisconnect(provider)}
              className="text-rose-400 hover:text-rose-300 hover:bg-rose-500/10">
              <LogOut size={14} />
            </Button>
          </>
        )}
        {!allowed && (
          <Badge className="bg-purple-500/20 text-purple-300 border border-purple-500/30 text-xs">Upgrade</Badge>
        )}
      </div>
      {/* OLX credential form */}
      {provider === 'olx' && !connected && showCredForm && (
        <div className="space-y-3 pt-3 border-t border-white/10">
          <div>
            <Label className="text-xs text-gray-400 mb-1.5 block">Client ID (OLX Pro)</Label>
            <Input placeholder="Ex: abc123" value={credId} onChange={e => setCredId(e.target.value)}
              className="h-8 text-sm bg-white/5 border-white/15 text-white" />
          </div>
          <div>
            <Label className="text-xs text-gray-400 mb-1.5 block">Client Secret</Label>
            <Input type="password" placeholder="Seu secret" value={credSecret} onChange={e => setCredSecret(e.target.value)}
              className="h-8 text-sm bg-white/5 border-white/15 text-white" />
          </div>
          <Button size="sm"
            className="w-full bg-orange-600 hover:bg-orange-700 text-white gap-1.5"
            disabled={!credId || !credSecret || isConnecting}
            onClick={() => { onConnectCredentials(provider, credId, credSecret); setShowCredForm(false); }}>
            {isConnecting ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : <Tag size={13} />}
            Salvar e conectar
          </Button>
          <p className="text-[11px] text-gray-500">
            Obtenha as credenciais em <a href="https://www.olx.com.br/autos/gest%C3%A3o" target="_blank" rel="noreferrer" className="text-orange-400 underline">OLX Pro</a> → Integrações → API Autoupload.
          </p>
        </div>
      )}
      {open && connected && (
        <div className="space-y-3 pt-3 border-t border-white/10">
          {/* Conta de anúncios — Meta/Google */}
          {provider !== 'olx' && (
            <>
              <div>
                <Label className="text-xs text-gray-400 mb-1.5 block">
                  Ad Account ID <span className="text-gray-600">(ex: act_123456789)</span>
                </Label>
                <Input placeholder="act_XXXXXXXXX" value={accountId} onChange={e => setAccountId(e.target.value)}
                  className="h-8 text-sm bg-white/5 border-white/15 text-white font-mono" />
              </div>
              <div>
                <Label className="text-xs text-gray-400 mb-1.5 block">
                  Business ID <span className="text-gray-600">(opcional)</span>
                </Label>
                <Input placeholder="Gerenciador de Negócios ID" value={businessId} onChange={e => setBusinessId(e.target.value)}
                  className="h-8 text-sm bg-white/5 border-white/15 text-white font-mono" />
              </div>
              <div>
                <Label className="text-xs text-gray-400 mb-1.5 block">Nome da conta</Label>
                <Input placeholder="Ex: Imobiliária Exemplo" value={accountName} onChange={e => setAccountName(e.target.value)}
                  className="h-8 text-sm bg-white/5 border-white/15 text-white" />
              </div>
              <Button size="sm" className="w-full bg-indigo-600 hover:bg-indigo-700 text-white"
                disabled={!accountId}
                onClick={() => { onSaveAccount(provider, accountId, businessId, accountName); }}>
                Salvar conta de anúncios
              </Button>
              <div className="border-t border-white/10 pt-3" />
            </>
          )}
          <div>
            <Label className="text-xs text-gray-400 mb-1.5 block">Orçamento diário (R$)</Label>
            <Input type="number" placeholder="Ex: 30" value={budget} onChange={e => setBudget(e.target.value)}
              className="h-8 text-sm bg-white/5 border-white/15 text-white" />
          </div>
          <div>
            <Label className="text-xs text-gray-400 mb-1.5 block">Região de segmentação</Label>
            <Input placeholder="Ex: São Paulo, SP" value={region} onChange={e => setRegion(e.target.value)}
              className="h-8 text-sm bg-white/5 border-white/15 text-white" />
          </div>
          <Button size="sm" className="w-full bg-blue-600 hover:bg-blue-700 text-white"
            onClick={() => { onSaveSettings(provider, parseFloat(budget) || 0, region); setOpen(false); }}>
            Salvar orçamento
          </Button>
          {status.expires_at && <p className="text-xs text-gray-500">Token expira: {fmt(status.expires_at)}</p>}
          {status.campaign_status && <p className="text-xs text-gray-500">Campanha: {status.campaign_status}</p>}
          {status.external_account_id && (
            <p className="text-xs text-gray-500 font-mono">Conta: {status.external_account_id}{status.account_name ? ` · ${status.account_name}` : ''}</p>
          )}
        </div>
      )}
    </div>
  );
}

// ── Chart Tooltip ─────────────────────────────────────────────────────────────

function ChartTooltip({ active, payload, label }: any) {
  if (!active || !payload?.length) return null;
  return (
    <div className="bg-gray-900 border border-white/15 rounded-xl px-3 py-2 text-xs shadow-xl">
      <p className="text-gray-400 mb-1.5 font-medium">{label}</p>
      {payload.map((p: any) => (
        <p key={p.dataKey} style={{ color: p.color }} className="flex items-center gap-1.5">
          <span className="inline-block w-2 h-2 rounded-full" style={{ background: p.color }} />
          {p.name}: <span className="font-semibold">{p.value}</span>
        </p>
      ))}
    </div>
  );
}

// ── Main Page ─────────────────────────────────────────────────────────────────

type Tab = 'analytics' | 'connections' | 'logs';

export default function AdsAutomation() {
  const queryClient = useQueryClient();
  const [tab, setTab] = useState<Tab>('analytics');
  const [period, setPeriod] = useState(30);
  const [logFilter, setLogFilter] = useState({ provider: '', status: '' });
  const [connectingProvider, setConnectingProvider] = useState<string | null>(null);

  const { data: statusData, isLoading: statusLoading, refetch: refetchStatus } = useQuery<AdsStatus>({
    queryKey: ['ads-status'],
    queryFn: async () => (await api.get('/admin/ads/status')).data,
    refetchInterval: 30000,
  });


  const { data: analyticsData, isLoading: analyticsLoading, refetch: refetchAnalytics } = useQuery<{ data: AnalyticsData }>({
    queryKey: ['ads-analytics', period],
    queryFn: async () => (await api.get(`/admin/ads/analytics?period=${period}`)).data,
    refetchInterval: 60000,
  });

  const { data: logsData, isLoading: logsLoading } = useQuery({
    queryKey: ['ads-logs', logFilter],
    queryFn: async () => (await api.get('/admin/ads/logs', { params: { ...logFilter, per_page: 50 } })).data,
  });

  const connectMutation = useMutation({
    mutationFn: async (provider: string) => (await api.post(`/admin/ads/${provider}/connect/start`)).data,
  });

  const olxCredMutation = useMutation({
    mutationFn: async ({ clientId, clientSecret }: { clientId: string; clientSecret: string }) =>
      (await api.post('/admin/ads/olx/connect/credentials', { client_id: clientId, client_secret: clientSecret })).data,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['ads-status'] });
      toast.success('OLX conectado com sucesso!');
    },
    onError: (err: any) => toast.error(err?.response?.data?.error || 'Credenciais OLX inválidas.'),
    onSettled: () => setConnectingProvider(null),
  });

  const disconnectMutation = useMutation({
    mutationFn: async (provider: string) => { await api.delete(`/admin/ads/${provider}/connect`); },
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['ads-status'] }); toast.success('Conta desconectada.'); },
    onError: () => toast.error('Erro ao desconectar.'),
  });

  const settingsMutation = useMutation({
    mutationFn: async ({ provider, budget, region }: { provider: string; budget: number; region: string }) => {
      await api.post('/admin/ads/settings', { provider, budget_daily_reais: budget, region });
    },
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['ads-status'] }); toast.success('Configurações salvas!'); },
    onError: (err: any) => toast.error(err?.response?.data?.error || 'Erro ao salvar.'),
  });

  const saveAccountMutation = useMutation({
    mutationFn: async ({ provider, accountId, businessId, name }: { provider: string; accountId: string; businessId: string; name: string }) => {
      await api.post(`/admin/ads/${provider}/accounts`, {
        external_account_id: accountId,
        external_business_id: businessId || undefined,
        name: name || undefined,
        currency: 'BRL',
      });
    },
    onSuccess: () => { queryClient.invalidateQueries({ queryKey: ['ads-status'] }); toast.success('Conta de anúncios salva!'); },
    onError: (err: any) => toast.error(err?.response?.data?.error || 'Erro ao salvar conta.'),
  });

  const handleConnect = (p: string) => {
    setConnectingProvider(p);
    connectMutation.mutateAsync(p).then(data => {
      if (data.oauth_url) {
        window.open(data.oauth_url, '_blank', 'width=620,height=720,scrollbars=yes');
        // Escutar postMessage do popup (ADS_OAUTH_SUCCESS / ADS_OAUTH_ERROR)
        const onMessage = (event: MessageEvent) => {
          if (event.data?.type === 'ADS_OAUTH_SUCCESS' && event.data.provider === p) {
            window.removeEventListener('message', onMessage);
            setConnectingProvider(null);
            queryClient.invalidateQueries({ queryKey: ['ads-status'] });
            toast.success(`${p === 'meta' ? 'Meta Ads' : 'Google Ads'} conectado! Configure sua conta de anúncios abaixo.`);
          } else if (event.data?.type === 'ADS_OAUTH_ERROR' && event.data.provider === p) {
            window.removeEventListener('message', onMessage);
            setConnectingProvider(null);
            toast.error(event.data.error || 'Erro ao conectar');
          }
        };
        window.addEventListener('message', onMessage);
        // Fallback: remover listener após 5 minutos
        setTimeout(() => { window.removeEventListener('message', onMessage); setConnectingProvider(null); }, 300_000);
      }
    }).catch((err: any) => {
      toast.error(err?.response?.data?.error || 'Erro ao iniciar conexão.');
      setConnectingProvider(null);
    });
  };
  const handleDisconnect   = (p: string) => { if (window.confirm(`Desconectar ${p}?`)) disconnectMutation.mutate(p); };
  const handleSaveSettings = (p: string, b: number, r: string) => settingsMutation.mutate({ provider: p, budget: b, region: r });
  const handleConnectCredentials = (p: string, id: string, secret: string) => {
    setConnectingProvider(p);
    olxCredMutation.mutate({ clientId: id, clientSecret: secret });
  };
  const handleSaveAccount = (p: string, accountId: string, businessId: string, name: string) =>
    saveAccountMutation.mutate({ provider: p, accountId, businessId, name });

  const entitlement = statusData?.entitlement;
  const providers   = statusData?.providers ?? {};
  const allowed     = entitlement?.providers_allowed ?? [];
  const logs: AuditLog[] = logsData?.data ?? [];
  const analytics   = analyticsData?.data;
  const summary     = analytics?.summary;

  const chartData = (analytics?.timeline ?? []).slice(-14).map(p => ({ ...p, label: fmtDate(p.date) }));

  const TABS: { id: Tab; label: string; icon: React.ReactNode }[] = [
    { id: 'analytics',   label: 'Analytics',  icon: <BarChart3   size={15} /> },
    { id: 'connections', label: 'Conexões',   icon: <Zap         size={15} /> },
    { id: 'logs',        label: 'Logs',       icon: <Activity    size={15} /> },
  ];

  if (statusLoading) {
    return (
      <div className="flex min-h-screen bg-background text-foreground">
        <Sidebar />
        <div className="page-shell !pb-0 flex items-center justify-center">
          <Loader2 className="h-8 w-8 animate-spin text-blue-400" />
        </div>
      </div>
    );
  }

  return (
    <div className="flex min-h-screen bg-background text-foreground overflow-hidden">
      <Sidebar />
      <div className="page-shell overflow-auto">
        <div className="max-w-6xl mx-auto space-y-6">

          {/* Header */}
          <div className="flex items-start justify-between gap-4 flex-wrap">
            <div>
              <h1 className="text-2xl font-bold text-white flex items-center gap-2.5">
                <div className="w-9 h-9 rounded-xl bg-gradient-to-br from-yellow-500 to-orange-500 flex items-center justify-center">
                  <Zap size={18} className="text-white" />
                </div>
                Marketing / Anúncios
              </h1>
              <p className="text-sm text-gray-400 mt-1.5">Gerencie campanhas Meta, Google e OLX · Leads entram direto no CRM</p>
            </div>
            <div className="flex items-center gap-2 flex-shrink-0 flex-wrap">
              {entitlement ? (
                <span className="text-xs bg-emerald-500/15 text-emerald-300 border border-emerald-500/25 px-3 py-1.5 rounded-full">
                  ✓ {entitlement.plan_code}
                </span>
              ) : (
                <span className="text-xs bg-amber-500/15 text-amber-300 border border-amber-500/25 px-3 py-1.5 rounded-full flex items-center gap-1.5">
                  <AlertTriangle size={12} /> Sem plano ativo
                </span>
              )}
              <Button variant="outline" size="sm" onClick={() => { refetchStatus(); refetchAnalytics(); }}
                className="border-white/15 text-gray-300 hover:text-white hover:border-white/30 gap-1.5">
                <RefreshCw size={13} /> Atualizar
              </Button>
            </div>
          </div>

          {/* Tabs */}
          <div className="flex gap-1 p-1 bg-white/5 rounded-xl border border-white/10 w-fit">
            {TABS.map(t => (
              <button key={t.id} onClick={() => setTab(t.id)}
                className={`flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium transition-all ${
                  tab === t.id ? 'bg-white/12 text-white shadow-sm' : 'text-gray-400 hover:text-white hover:bg-white/5'
                }`}>
                {t.icon}{t.label}
              </button>
            ))}
          </div>

          {/* ═══════ ANALYTICS ═══════ */}
          {tab === 'analytics' && (
            <div className="space-y-6">
              {/* Period selector */}
              <div className="flex items-center justify-between">
                <p className="text-sm text-gray-400">Período de análise</p>
                <div className="flex gap-1">
                  {[7, 14, 30, 90].map(p => (
                    <button key={p} onClick={() => setPeriod(p)}
                      className={`px-3 py-1.5 rounded-lg text-xs font-medium transition-all ${
                        period === p ? 'bg-blue-600/80 text-white' : 'bg-white/5 text-gray-400 hover:text-white hover:bg-white/10'
                      }`}>{p}d</button>
                  ))}
                </div>
              </div>

              {analyticsLoading ? (
                <div className="flex items-center justify-center py-16">
                  <Loader2 className="h-8 w-8 animate-spin text-blue-400" />
                </div>
              ) : !summary ? (
                <div className="text-center py-16 text-gray-500">
                  <BarChart3 size={40} className="mx-auto mb-3 opacity-30" />
                  <p>Nenhum dado de anúncio disponível ainda.</p>
                  <p className="text-xs mt-1">Conecte um provider na aba Conexões para começar.</p>
                </div>
              ) : (
                <>
                  {/* KPI Cards */}
                  <div className="grid grid-cols-2 lg:grid-cols-4 gap-4">
                    <KpiCard label="Leads no período" value={summary.total_leads} sub={`${summary.leads_week} esta semana`}
                      icon={<Users size={18} className="text-blue-300" />} color="from-blue-600/40 to-blue-800/40"
                      trend={<TrendBadge curr={summary.leads_week} prev={Math.max(1, Math.round(summary.total_leads / (period / 7) * 0.8))} />} />
                    <KpiCard label="Leads hoje" value={summary.leads_today} sub={`${Math.round(summary.duplicate_rate * 100)}% duplicados`}
                      icon={<TrendingUp size={18} className="text-emerald-300" />} color="from-emerald-600/40 to-emerald-800/40" />
                    <KpiCard label="Imóveis ativos" value={summary.active_listings_meta + summary.active_listings_google + summary.active_listings_olx}
                      sub={`Meta: ${summary.active_listings_meta} · Google: ${summary.active_listings_google} · OLX: ${summary.active_listings_olx}`}
                      icon={<Home size={18} className="text-purple-300" />} color="from-purple-600/40 to-purple-800/40" />
                    <KpiCard label="Gasto estimado" value={fmtR$(summary.total_spend_estimate)}
                      sub={`Meta: ${fmtR$(summary.budget_meta_daily)}/dia`}
                      icon={<DollarSign size={18} className="text-amber-300" />} color="from-amber-600/40 to-amber-800/40" />
                  </div>

                  {/* Area Chart */}
                  <div className="rounded-2xl bg-white/4 border border-white/10 p-5">
                    <div className="flex items-center justify-between mb-5">
                      <div>
                        <p className="text-sm font-semibold text-white">Leads captados por dia</p>
                        <p className="text-xs text-gray-500 mt-0.5">Últimos 14 dias · Meta vs Google vs OLX</p>
                      </div>
                      <div className="flex items-center gap-4 text-xs text-gray-400">
                        <span className="flex items-center gap-1.5"><span className="w-3 h-1 rounded bg-blue-500 inline-block" /> Meta</span>
                        <span className="flex items-center gap-1.5"><span className="w-3 h-1 rounded bg-emerald-500 inline-block" /> Google</span>
                        <span className="flex items-center gap-1.5"><span className="w-3 h-1 rounded bg-orange-500 inline-block" /> OLX</span>
                      </div>
                    </div>
                    {chartData.length === 0 ? (
                      <div className="h-48 flex items-center justify-center text-gray-500 text-sm">Sem dados para exibir</div>
                    ) : (
                      <ResponsiveContainer width="100%" height={220}>
                        <AreaChart data={chartData} margin={{ top: 4, right: 4, left: -20, bottom: 0 }}>
                          <defs>
                            <linearGradient id="gMeta" x1="0" y1="0" x2="0" y2="1">
                              <stop offset="5%"  stopColor="#3b82f6" stopOpacity={0.35} />
                              <stop offset="95%" stopColor="#3b82f6" stopOpacity={0}    />
                            </linearGradient>
                            <linearGradient id="gGoogle" x1="0" y1="0" x2="0" y2="1">
                              <stop offset="5%"  stopColor="#10b981" stopOpacity={0.35} />
                              <stop offset="95%" stopColor="#10b981" stopOpacity={0}    />
                            </linearGradient>
                            <linearGradient id="gOlx" x1="0" y1="0" x2="0" y2="1">
                              <stop offset="5%"  stopColor="#f97316" stopOpacity={0.35} />
                              <stop offset="95%" stopColor="#f97316" stopOpacity={0}    />
                            </linearGradient>
                          </defs>
                          <CartesianGrid strokeDasharray="3 3" stroke="#ffffff10" />
                          <XAxis dataKey="label" tick={{ fill: '#9ca3af', fontSize: 11 }} axisLine={false} tickLine={false} />
                          <YAxis tick={{ fill: '#9ca3af', fontSize: 11 }} axisLine={false} tickLine={false} allowDecimals={false} />
                          <Tooltip content={<ChartTooltip />} />
                          <Area type="monotone" dataKey="meta"   name="Meta"   stroke="#3b82f6" strokeWidth={2} fill="url(#gMeta)"   />
                          <Area type="monotone" dataKey="google" name="Google" stroke="#10b981" strokeWidth={2} fill="url(#gGoogle)" />
                          <Area type="monotone" dataKey="olx"    name="OLX"    stroke="#f97316" strokeWidth={2} fill="url(#gOlx)"    />
                        </AreaChart>
                      </ResponsiveContainer>
                    )}
                  </div>

                  {/* Bar + Top Listings */}
                  <div className="grid grid-cols-1 lg:grid-cols-2 gap-5">
                    <div className="rounded-2xl bg-white/4 border border-white/10 p-5">
                      <p className="text-sm font-semibold text-white mb-1">Distribuição por dia (7d)</p>
                      <p className="text-xs text-gray-500 mb-4">Total de leads por provider</p>
                      <ResponsiveContainer width="100%" height={180}>
                        <BarChart data={(analytics?.timeline ?? []).slice(-7).map(p => ({ ...p, label: fmtDate(p.date) }))}
                          margin={{ top: 4, right: 4, left: -25, bottom: 0 }} barGap={2} barCategoryGap="30%">
                          <CartesianGrid strokeDasharray="3 3" stroke="#ffffff10" vertical={false} />
                          <XAxis dataKey="label" tick={{ fill: '#9ca3af', fontSize: 11 }} axisLine={false} tickLine={false} />
                          <YAxis tick={{ fill: '#9ca3af', fontSize: 11 }} axisLine={false} tickLine={false} allowDecimals={false} />
                          <Tooltip content={<ChartTooltip />} />
                          <Bar dataKey="meta"   name="Meta"   fill="#3b82f6" radius={[4, 4, 0, 0]} />
                          <Bar dataKey="google" name="Google" fill="#10b981" radius={[4, 4, 0, 0]} />
                          <Bar dataKey="olx"    name="OLX"    fill="#f97316" radius={[4, 4, 0, 0]} />
                        </BarChart>
                      </ResponsiveContainer>
                    </div>
                    <div className="rounded-2xl bg-white/4 border border-white/10 p-5">
                      <p className="text-sm font-semibold text-white mb-1">Top imóveis por leads</p>
                      <p className="text-xs text-gray-500 mb-4">Imóveis que mais geraram contatos</p>
                      {(analytics?.top_listings ?? []).length === 0 ? (
                        <p className="text-xs text-gray-500 mt-8 text-center">Nenhum imóvel com leads ainda.</p>
                      ) : (
                        <div className="space-y-2">
                          {(analytics?.top_listings ?? []).slice(0, 6).map((l, i) => (
                            <div key={l.listing_id} className="flex items-center gap-3 py-1.5">
                              <span className="text-xs text-gray-500 w-4 flex-shrink-0">{i + 1}</span>
                              <div className="flex-1 min-w-0">
                                <p className="text-xs text-white truncate">{l.titulo}</p>
                                <div className="flex items-center gap-3 mt-0.5">
                                  {l.meta   > 0 && <span className="text-[11px] text-blue-400">{l.meta} Meta</span>}
                                  {l.google > 0 && <span className="text-[11px] text-emerald-400">{l.google} Google</span>}
                                  {l.olx    > 0 && <span className="text-[11px] text-orange-400">{l.olx} OLX</span>}
                                </div>
                              </div>
                              <span className="text-sm font-bold text-white">{l.total}</span>
                            </div>
                          ))}
                        </div>
                      )}
                    </div>
                  </div>

                  {/* Funnel */}
                  <div className="rounded-2xl bg-white/4 border border-white/10 p-5">
                    <p className="text-sm font-semibold text-white mb-4">Funil de conversão</p>
                    <div className="grid grid-cols-3 gap-4 text-center">
                      {[
                        { label: 'Leads recebidos',    value: summary.total_leads,                                   color: 'text-blue-300'    },
                        { label: 'Ingressaram no CRM', value: summary.ingested_crm,                                  color: 'text-emerald-300' },
                        { label: 'Duplicados filtrados', value: Math.round(summary.total_leads * summary.duplicate_rate), color: 'text-amber-300' },
                      ].map(s => (
                        <div key={s.label} className="rounded-xl bg-white/4 p-4 border border-white/8">
                          <p className={`text-2xl font-bold ${s.color}`}>{s.value}</p>
                          <p className="text-xs text-gray-400 mt-1">{s.label}</p>
                        </div>
                      ))}
                    </div>
                  </div>

                  {/* Errors */}
                  {(analytics?.recent_errors ?? []).length > 0 && (
                    <div className="rounded-2xl bg-rose-900/20 border border-rose-500/20 p-5">
                      <p className="text-sm font-semibold text-rose-300 mb-3 flex items-center gap-2">
                        <AlertTriangle size={14} /> Erros recentes
                      </p>
                      <div className="space-y-2">
                        {analytics?.recent_errors.map((e, i) => (
                          <div key={i} className="flex items-start gap-3 text-xs">
                            {providerIcon(e.provider, 13)}
                            <div className="flex-1 min-w-0">
                              <span className="text-gray-300 font-medium">{e.action}</span>
                              {e.message && <p className="text-gray-500 truncate mt-0.5">{e.message}</p>}
                            </div>
                            <span className="text-gray-600 flex-shrink-0">{fmt(e.created_at)}</span>
                          </div>
                        ))}
                      </div>
                    </div>
                  )}
                </>
              )}
            </div>
          )}

          {/* ═══════ CONEXÕES ═══════ */}
          {tab === 'connections' && (
            <div className="space-y-5">
              {!entitlement && (
                <div className="rounded-2xl bg-amber-500/10 border border-amber-500/25 p-4 flex items-center gap-3">
                  <AlertTriangle size={16} className="text-amber-400 flex-shrink-0" />
                  <p className="text-sm text-amber-300">Nenhum plano de Ads ativo. Contate o suporte para ativar seu plano.</p>
                </div>
              )}
              {entitlement && (
                <div className="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                  {[
                    { label: 'Plano',          value: entitlement.plan_code },
                    { label: 'Imóveis/dia',    value: `Máx. ${entitlement.max_listings_per_day}` },
                    { label: 'Orçamento máx.', value: `R$ ${entitlement.max_budget_daily_reais}/dia` },
                    { label: 'Válido até',     value: entitlement.valid_until ? fmt(entitlement.valid_until) : 'Sem expiração' },
                  ].map(s => (
                    <div key={s.label} className="rounded-xl bg-white/4 border border-white/10 p-3">
                      <p className="text-gray-500">{s.label}</p>
                      <p className="text-white font-semibold mt-0.5">{s.value}</p>
                    </div>
                  ))}
                </div>
              )}
              <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                {(['meta', 'google', 'olx'] as const).map(provider => (
                  <ProviderCard key={provider} provider={provider}
                    status={providers[provider] ?? { connected: false, status: 'DRAFT', expires_at: null, last_refresh_at: null, campaign_status: null, active_listings: 0, budget_daily: 0, external_account_id: null, account_name: null, external_business_id: null }}
                    allowed={allowed.includes(provider)}
                    onConnect={handleConnect} onDisconnect={handleDisconnect}
                    onSaveSettings={handleSaveSettings} onConnectCredentials={handleConnectCredentials}
                    onSaveAccount={handleSaveAccount}
                    isConnecting={connectingProvider === provider && (connectMutation.isPending || olxCredMutation.isPending)} />
                ))}
              </div>
              <div className="rounded-2xl bg-blue-900/20 border border-blue-500/20 p-5 space-y-2 text-xs text-gray-300">
                <p className="font-semibold text-blue-300 text-sm">Como conectar o Meta Ads</p>
                <ol className="list-decimal list-inside space-y-1 text-gray-400">
                  <li>Acesse <a href="https://developers.facebook.com" target="_blank" rel="noreferrer" className="text-blue-400 underline">developers.facebook.com</a> e crie um App tipo Business.</li>
                  <li>Adicione o produto <strong className="text-white">Marketing API</strong>.</li>
                  <li>Adicione <code className="bg-white/10 px-1 rounded">META_APP_ID</code> e <code className="bg-white/10 px-1 rounded">META_APP_SECRET</code> ao <code className="bg-white/10 px-1 rounded">.env</code> do servidor.</li>
                  <li>Clique em <strong className="text-white">Conectar via OAuth</strong> acima e autorize.</li>
                </ol>
              </div>
            </div>
          )}

          {/* ═══════ LOGS ═══════ */}
          {tab === 'logs' && (
            <div className="space-y-4">
              <div className="flex gap-2 flex-wrap">
                <Select value={logFilter.provider || 'all'}
                  onValueChange={v => setLogFilter(f => ({ ...f, provider: v === 'all' ? '' : v }))}>
                  <SelectTrigger className="h-8 w-32 text-xs bg-white/5 border-white/15 text-gray-300">
                    <SelectValue placeholder="Provider" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">Todos</SelectItem>
                    <SelectItem value="meta">Meta</SelectItem>
                    <SelectItem value="google">Google</SelectItem>
                  </SelectContent>
                </Select>
                <Select value={logFilter.status || 'all'}
                  onValueChange={v => setLogFilter(f => ({ ...f, status: v === 'all' ? '' : v }))}>
                  <SelectTrigger className="h-8 w-28 text-xs bg-white/5 border-white/15 text-gray-300">
                    <SelectValue placeholder="Status" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">Todos</SelectItem>
                    <SelectItem value="success">OK</SelectItem>
                    <SelectItem value="error">Erro</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <div className="rounded-2xl bg-white/3 border border-white/10 overflow-hidden">
                {logsLoading ? (
                  <div className="flex justify-center py-10"><Loader2 className="h-5 w-5 animate-spin text-gray-400" /></div>
                ) : logs.length === 0 ? (
                  <div className="text-center py-10 text-sm text-gray-500">Nenhum log encontrado.</div>
                ) : (
                  <div className="divide-y divide-white/5">
                    {logs.map(log => (
                      <div key={log.id} className="flex items-start justify-between px-5 py-3 text-xs hover:bg-white/3 transition-colors">
                        <div className="flex items-start gap-3 min-w-0">
                          <span className="mt-0.5 flex-shrink-0">{providerIcon(log.provider || '', 14)}</span>
                          <div className="min-w-0">
                            <span className="font-medium text-gray-200">{log.action}</span>
                            {log.message && <p className="text-gray-500 truncate max-w-xs mt-0.5">{log.message}</p>}
                          </div>
                        </div>
                        <div className="flex items-center gap-3 flex-shrink-0 ml-3">
                          <span className={`px-2 py-0.5 rounded text-[11px] font-medium ${
                            log.status === 'SUCCESS' ? 'bg-emerald-500/15 text-emerald-300' :
                            log.status === 'ERROR'   ? 'bg-rose-500/15 text-rose-300' :
                            'bg-white/5 text-gray-400'
                          }`}>
                            {log.status === 'SUCCESS' ? 'OK' : log.status === 'ERROR' ? 'Erro' : log.status}
                          </span>
                          <span className="text-gray-600">{fmt(log.created_at)}</span>
                        </div>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            </div>
          )}

        </div>
      </div>
    </div>
  );
}
