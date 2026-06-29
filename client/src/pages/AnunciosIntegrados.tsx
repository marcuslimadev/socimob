import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import {
  AlertTriangle,
  BarChart3,
  CheckCircle2,
  CircleDollarSign,
  Globe,
  Home,
  Loader2,
  PauseCircle,
  PlayCircle,
  RefreshCw,
  Search,
  Settings2,
  Tag,
  Target,
  TrendingUp,
  Users,
} from 'lucide-react';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';
import { toast } from 'sonner';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Switch } from '@/components/ui/switch';
import {
  Area,
  AreaChart,
  CartesianGrid,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from 'recharts';

interface ProviderStatus {
  connected: boolean;
  status: string;
  expires_at: string | null;
  last_refresh_at: string | null;
  campaign_status: string | null;
  active_listings: number;
  budget_daily: number;
  region?: string | null;
  external_account_id?: string | null;
  account_name?: string | null;
  campaign_metadata?: Record<string, unknown>;
}

interface AdsStatus {
  entitlement: {
    providers_allowed: string[];
    max_budget_daily_reais: number;
    max_listings_per_day: number;
    plan_code: string;
  } | null;
  providers: Record<string, ProviderStatus>;
}

interface PropertyRow {
  id: string;
  codigo: string;
  titulo: string;
  tipo: string;
  finalidade: string;
  preco: number;
  localizacao: string;
  dormitorios: number;
  banheiros: number;
  area: number;
  imagem: string;
  imagens: string[];
}

interface ListingStatus {
  provider: string;
  publish_status: 'DRAFT' | 'PUBLISHING' | 'ACTIVE' | 'PAUSED' | 'ERROR';
  last_error?: string | null;
  last_sync_at?: string | null;
  metadata?: Partial<ListingDraft>;
}

interface AnalyticsData {
  summary: {
    total_leads: number;
    leads_today: number;
    leads_week: number;
    ingested_crm: number;
    active_listings_google: number;
    budget_google_daily: number;
    total_spend_estimate: number;
  };
  timeline: Array<{ date: string; google: number; total: number }>;
  top_listings: Array<{ listing_id: number; titulo: string; google: number; total: number }>;
  recent_errors: Array<{ provider: string; action: string; message: string | null; created_at: string }>;
}

interface ListingDraft {
  headline_1: string;
  headline_2: string;
  headline_3: string;
  description_1: string;
  description_2: string;
  final_url: string;
  budget_daily_reais: string;
  region: string;
  keywords: string;
  negative_keywords: string;
  conversion_goal: string;
  utm_source: string;
  utm_campaign: string;
}

const DEFAULT_DRAFT: ListingDraft = {
  headline_1: '',
  headline_2: '',
  headline_3: '',
  description_1: '',
  description_2: '',
  final_url: '',
  budget_daily_reais: '',
  region: '',
  keywords: '',
  negative_keywords: '',
  conversion_goal: 'lead',
  utm_source: 'google',
  utm_campaign: 'imovel',
};

const formatMoney = (value: number) =>
  Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 });

const formatDate = (value: string) =>
  new Date(`${value}T00:00:00`).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });

const resolvePropertyPrice = (item: any) => {
  const salePrice = Number.parseFloat(item.valor_venda) || 0;
  const rentPrice = Number.parseFloat(item.valor_aluguel) || 0;
  const purpose = String(item.finalidade_imovel || '').toLowerCase();
  return purpose.includes('aluguel') ? rentPrice || salePrice : salePrice || rentPrice;
};

const collectImages = (item: any) =>
  Array.from(
    new Set(
      [item.imagem_destaque, item.foto_capa, ...(Array.isArray(item.imagens) ? item.imagens : [])]
        .filter((value): value is string => typeof value === 'string')
        .map((value) => value.trim())
        .filter(Boolean),
    ),
  );

const mapProperty = (item: any): PropertyRow => {
  const imagens = collectImages(item);
  return {
    id: String(item.id),
    codigo: item.codigo_imovel || item.codigo || '-',
    titulo: item.titulo || item.codigo_imovel || 'Imovel sem titulo',
    tipo: item.tipo_imovel || 'Imovel',
    finalidade: String(item.finalidade_imovel || 'venda').toLowerCase().includes('aluguel') ? 'Aluguel' : 'Venda',
    preco: resolvePropertyPrice(item),
    localizacao: [item.bairro, item.cidade].filter(Boolean).join(', ') || 'Localizacao sob consulta',
    dormitorios: Number.parseInt(item.dormitorios) || 0,
    banheiros: Number.parseInt(item.banheiros) || 0,
    area: Number.parseFloat(item.area_total || item.area_util || item.area_privativa) || 0,
    imagem: imagens[0] || '',
    imagens,
  };
};

const statusLabel: Record<string, string> = {
  ACTIVE: 'Ativo',
  PUBLISHING: 'Publicando',
  PAUSED: 'Pausado',
  ERROR: 'Erro',
  DRAFT: 'Rascunho',
};

const getPreviewHost = (value: string) => {
  try {
    return new URL(value || window.location.origin).hostname;
  } catch {
    return window.location.hostname;
  }
};

function buildDraft(property: PropertyRow, status?: ListingStatus): ListingDraft {
  const metadata = status?.metadata || {};
  return {
    ...DEFAULT_DRAFT,
    headline_1: metadata.headline_1 || property.titulo,
    headline_2: metadata.headline_2 || `${property.finalidade} em ${property.localizacao}`,
    headline_3: metadata.headline_3 || `${property.codigo} | ${formatMoney(property.preco)}`,
    description_1:
      metadata.description_1 ||
      `${property.tipo} para ${property.finalidade.toLowerCase()} com atendimento direto pelo Socimob.`,
    description_2:
      metadata.description_2 ||
      [property.dormitorios ? `${property.dormitorios} quartos` : '', property.area ? `${property.area} m2` : '']
        .filter(Boolean)
        .join(' | '),
    final_url: metadata.final_url || `${window.location.origin}/portal/imovel/${property.id}`,
    budget_daily_reais: String(metadata.budget_daily_reais || ''),
    region: metadata.region || property.localizacao,
    keywords: metadata.keywords || `${property.tipo}, ${property.localizacao}, imovel ${property.finalidade.toLowerCase()}`,
    negative_keywords: metadata.negative_keywords || '',
    conversion_goal: metadata.conversion_goal || 'lead',
    utm_source: metadata.utm_source || 'google',
    utm_campaign: metadata.utm_campaign || `imovel-${property.codigo}`,
  };
}

function StatusBadge({ status }: { status?: string }) {
  const value = status || 'DRAFT';
  const styles: Record<string, string> = {
    ACTIVE: 'border-emerald-200 bg-emerald-50 text-emerald-700',
    PUBLISHING: 'border-blue-200 bg-blue-50 text-blue-700',
    PAUSED: 'border-amber-200 bg-amber-50 text-amber-700',
    ERROR: 'border-red-200 bg-red-50 text-red-700',
    DRAFT: 'border-slate-200 bg-slate-50 text-slate-600',
  };

  return <Badge className={`${styles[value] || styles.DRAFT} border`}>{statusLabel[value] || value}</Badge>;
}

function KpiCard({
  label,
  value,
  helper,
  icon,
}: {
  label: string;
  value: string | number;
  helper: string;
  icon: React.ReactNode;
}) {
  return (
    <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-sm font-medium text-slate-500">{label}</p>
          <p className="mt-2 text-2xl font-semibold text-slate-950">{value}</p>
          <p className="mt-1 text-xs text-slate-500">{helper}</p>
        </div>
        <div className="rounded-lg border border-slate-200 bg-slate-50 p-2 text-blue-700">{icon}</div>
      </div>
    </div>
  );
}

export default function AnunciosIntegrados() {
  const queryClient = useQueryClient();
  const [period, setPeriod] = useState(30);
  const [search, setSearch] = useState('');
  const [selectedId, setSelectedId] = useState<string | null>(null);
  const [draft, setDraft] = useState<ListingDraft>(DEFAULT_DRAFT);
  const [globalSettings, setGlobalSettings] = useState({
    budget: '',
    region: '',
    monthlyLimit: '',
    tags: '',
    conversionTag: '',
    accountId: '',
    accountName: '',
  });
  const [listingStatuses, setListingStatuses] = useState<Record<string, ListingStatus | undefined>>({});

  const { data: statusData, isLoading: statusLoading } = useQuery<AdsStatus>({
    queryKey: ['integrated-ads-status'],
    queryFn: async () => (await api.get('/admin/ads/status')).data,
    refetchInterval: 30000,
  });

  const { data: analyticsData, isLoading: analyticsLoading } = useQuery<{ data: AnalyticsData }>({
    queryKey: ['integrated-ads-analytics', period],
    queryFn: async () => (await api.get(`/admin/ads/analytics?period=${period}`)).data,
    refetchInterval: 60000,
  });

  const { data: properties = [], isLoading: propertiesLoading } = useQuery<PropertyRow[]>({
    queryKey: ['integrated-ads-properties'],
    queryFn: async () => {
      const response = await api.get('/imoveis', { params: { per_page: 'all', scope: 'active' } });
      const rows = Array.isArray(response.data?.data) ? response.data.data : [];
      return rows.map(mapProperty);
    },
  });

  const googleStatus = statusData?.providers?.google;
  const entitlement = statusData?.entitlement;
  const googleAllowed = entitlement?.providers_allowed?.includes('google') ?? true;
  const analytics = analyticsData?.data;
  const selectedProperty = properties.find((property) => property.id === selectedId) || properties[0];
  const selectedStatus = selectedProperty ? listingStatuses[selectedProperty.id] : undefined;

  useEffect(() => {
    if (!googleStatus) return;
    const metadata = googleStatus.campaign_metadata || {};
    setGlobalSettings((current) => ({
      ...current,
      budget: String(googleStatus.budget_daily || ''),
      region: googleStatus.region || '',
      tags: String(metadata.tags || metadata.keywords || ''),
      conversionTag: String(metadata.conversion_tag || ''),
      monthlyLimit: String(metadata.monthly_limit_reais || ''),
      accountId: googleStatus.external_account_id || '',
      accountName: googleStatus.account_name || '',
    }));
  }, [googleStatus?.budget_daily, googleStatus?.region, googleStatus?.external_account_id, googleStatus?.account_name]);

  useEffect(() => {
    if (!selectedId && properties.length > 0) {
      setSelectedId(properties[0].id);
    }
  }, [properties, selectedId]);

  useEffect(() => {
    if (!selectedProperty) return;
    setDraft(buildDraft(selectedProperty, selectedStatus));
  }, [selectedProperty?.id, selectedStatus?.publish_status]);

  useEffect(() => {
    if (properties.length === 0) return;
    let active = true;
    const loadStatuses = async () => {
      const slice = properties.slice(0, 80);
      const results = await Promise.allSettled(slice.map((property) => api.get(`/listings/${property.id}/ads/status`)));
      if (!active) return;
      const next: Record<string, ListingStatus | undefined> = {};
      results.forEach((result, index) => {
        if (result.status !== 'fulfilled') return;
        const statuses: ListingStatus[] = result.value.data?.data || [];
        next[slice[index].id] = statuses.find((item) => item.provider === 'google');
      });
      setListingStatuses((current) => ({ ...current, ...next }));
    };

    void loadStatuses();
    return () => {
      active = false;
    };
  }, [properties]);

  const filteredProperties = useMemo(() => {
    const term = search.trim().toLowerCase();
    if (!term) return properties;
    return properties.filter((property) =>
      [property.codigo, property.titulo, property.localizacao, property.tipo]
        .join(' ')
        .toLowerCase()
        .includes(term),
    );
  }, [properties, search]);

  const connected = Boolean(googleStatus?.connected);

  const connectMutation = useMutation({
    mutationFn: async () => (await api.post('/admin/ads/google/connect/start')).data,
    onSuccess: (data) => {
      if (data.oauth_url) {
        window.open(data.oauth_url, '_blank', 'width=620,height=720,scrollbars=yes');
        toast.info('Autorize a conta Google Ads na janela aberta.');
      }
    },
    onError: (error: any) => toast.error(error?.response?.data?.error || 'Erro ao iniciar conexão Google Ads.'),
  });

  const saveAccountMutation = useMutation({
    mutationFn: async () =>
      api.post('/admin/ads/google/accounts', {
        external_account_id: globalSettings.accountId,
        name: globalSettings.accountName || undefined,
        currency: 'BRL',
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['integrated-ads-status'] });
      toast.success('Conta Google Ads salva.');
    },
    onError: (error: any) => toast.error(error?.response?.data?.error || 'Erro ao salvar conta.'),
  });

  const saveSettingsMutation = useMutation({
    mutationFn: async () =>
      api.post('/admin/ads/settings', {
        provider: 'google',
        budget_daily_reais: Number(globalSettings.budget || 0),
        region: globalSettings.region,
        tags: globalSettings.tags,
        keywords: globalSettings.tags,
        conversion_tag: globalSettings.conversionTag,
        monthly_limit_reais: Number(globalSettings.monthlyLimit || 0),
      }),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: ['integrated-ads-status'] });
      toast.success('Configurações de Google Ads salvas.');
    },
    onError: (error: any) => toast.error(error?.response?.data?.error || 'Erro ao salvar configurações.'),
  });

  const configureListingMutation = useMutation({
    mutationFn: async () => {
      if (!selectedProperty) throw new Error('Nenhum imóvel selecionado.');
      return api.post(`/admin/ads/listings/${selectedProperty.id}/configure`, { provider: 'google', ...draft });
    },
    onSuccess: async () => {
      if (selectedProperty) {
        const response = await api.get(`/listings/${selectedProperty.id}/ads/status`);
        const statuses: ListingStatus[] = response.data?.data || [];
        setListingStatuses((current) => ({
          ...current,
          [selectedProperty.id]: statuses.find((item) => item.provider === 'google'),
        }));
      }
      toast.success('Anúncio salvo como rascunho.');
    },
    onError: (error: any) => toast.error(error?.response?.data?.error || 'Erro ao salvar anúncio.'),
  });

  const toggleListingMutation = useMutation({
    mutationFn: async ({ propertyId, active }: { propertyId: string; active: boolean }) => {
      const url = active ? `/listings/${propertyId}/ads/unpublish` : `/listings/${propertyId}/ads/publish`;
      return api.post(url, { provider: 'google', ...draft });
    },
    onSuccess: async (_, variables) => {
      const response = await api.get(`/listings/${variables.propertyId}/ads/status`);
      const statuses: ListingStatus[] = response.data?.data || [];
      setListingStatuses((current) => ({
        ...current,
        [variables.propertyId]: statuses.find((item) => item.provider === 'google'),
      }));
      queryClient.invalidateQueries({ queryKey: ['integrated-ads-status'] });
      queryClient.invalidateQueries({ queryKey: ['integrated-ads-analytics'] });
      toast.success(variables.active ? 'Pausa solicitada.' : 'Publicação enviada para o Google Ads.');
    },
    onError: (error: any) => toast.error(error?.response?.data?.error || 'Erro ao alterar anúncio.'),
  });

  const chartData = (analytics?.timeline || []).slice(-14).map((item) => ({
    ...item,
    label: formatDate(item.date),
  }));

  return (
    <div className="min-h-screen bg-slate-100 text-slate-950">
      <Sidebar />
      <main className="mx-auto max-w-[1500px] px-4 pb-10 pt-28 md:px-6">
        <div className="mb-6 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
          <div>
            <div className="mb-2 inline-flex items-center gap-2 rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.12em] text-blue-700">
              <Globe size={14} />
              Google Ads
            </div>
            <h1 className="text-3xl font-bold tracking-tight text-slate-950">Anúncios Integrados</h1>
            <p className="mt-2 max-w-3xl text-sm text-slate-600">
              Gerencie anúncios reais dos imóveis no Google Ads, com orçamento, tags, editor, ativação e analytics em um só lugar.
            </p>
          </div>

          <div className="flex flex-wrap items-center gap-2">
            {[7, 14, 30, 90].map((days) => (
              <Button
                key={days}
                type="button"
                variant={period === days ? 'default' : 'outline'}
                size="sm"
                onClick={() => setPeriod(days)}
              >
                {days} dias
              </Button>
            ))}
          </div>
        </div>

        <div className="mb-6 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
          <KpiCard
            label="Leads Google"
            value={analytics?.summary.total_leads ?? 0}
            helper={`${analytics?.summary.leads_week ?? 0} nos ultimos 7 dias`}
            icon={<Users size={18} />}
          />
          <KpiCard
            label="Imóveis ativos"
            value={analytics?.summary.active_listings_google ?? googleStatus?.active_listings ?? 0}
            helper={connected ? 'Campanhas sincronizadas' : 'Conecte o Google Ads'}
            icon={<Home size={18} />}
          />
          <KpiCard
            label="Orçamento diário"
            value={formatMoney(analytics?.summary.budget_google_daily ?? googleStatus?.budget_daily ?? 0)}
            helper={entitlement ? `Limite ${formatMoney(entitlement.max_budget_daily_reais)}/dia` : 'Plano não carregado'}
            icon={<CircleDollarSign size={18} />}
          />
          <KpiCard
            label="CRM"
            value={analytics?.summary.ingested_crm ?? 0}
            helper="Leads ingeridos no funil"
            icon={<TrendingUp size={18} />}
          />
        </div>

        <div className="grid gap-6 xl:grid-cols-[390px_1fr]">
          <section className="space-y-4">
            <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
              <div className="mb-4 flex items-center justify-between gap-3">
                <div>
                  <h2 className="font-semibold text-slate-950">Conexão Google</h2>
                  <p className="text-xs text-slate-500">OAuth, conta, orçamento e tags globais.</p>
                </div>
                {statusLoading ? <Loader2 className="h-5 w-5 animate-spin text-slate-400" /> : <StatusBadge status={connected ? 'ACTIVE' : 'DRAFT'} />}
              </div>

              {!googleAllowed && (
                <div className="mb-4 rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                  O plano atual não libera Google Ads para este tenant.
                </div>
              )}

              <div className="grid gap-3">
                <Button
                  type="button"
                  onClick={() => connectMutation.mutate()}
                  disabled={!googleAllowed || connectMutation.isPending}
                  className="w-full"
                >
                  {connectMutation.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Globe className="mr-2 h-4 w-4" />}
                  {connected ? 'Reconectar Google Ads' : 'Conectar Google Ads'}
                </Button>

                <div className="grid gap-2">
                  <Label>Conta de anúncios</Label>
                  <Input
                    placeholder="Customer ID do Google Ads"
                    value={globalSettings.accountId}
                    onChange={(event) => setGlobalSettings((current) => ({ ...current, accountId: event.target.value }))}
                  />
                  <Input
                    placeholder="Nome da conta"
                    value={globalSettings.accountName}
                    onChange={(event) => setGlobalSettings((current) => ({ ...current, accountName: event.target.value }))}
                  />
                  <Button
                    type="button"
                    variant="outline"
                    onClick={() => saveAccountMutation.mutate()}
                    disabled={!globalSettings.accountId || saveAccountMutation.isPending}
                  >
                    Salvar conta
                  </Button>
                </div>

                <div className="grid grid-cols-2 gap-2">
                  <div>
                    <Label>Orçamento diário</Label>
                    <Input
                      type="number"
                      value={globalSettings.budget}
                      onChange={(event) => setGlobalSettings((current) => ({ ...current, budget: event.target.value }))}
                    />
                  </div>
                  <div>
                    <Label>Limite mensal</Label>
                    <Input
                      type="number"
                      value={globalSettings.monthlyLimit}
                      onChange={(event) => setGlobalSettings((current) => ({ ...current, monthlyLimit: event.target.value }))}
                    />
                  </div>
                </div>

                <div>
                  <Label>Região padrão</Label>
                  <Input
                    placeholder="Cidade, bairro ou raio"
                    value={globalSettings.region}
                    onChange={(event) => setGlobalSettings((current) => ({ ...current, region: event.target.value }))}
                  />
                </div>

                <div>
                  <Label>Tags e palavras-chave padrão</Label>
                  <Textarea
                    rows={3}
                    placeholder="apartamento, casa em condominio, imóveis em..."
                    value={globalSettings.tags}
                    onChange={(event) => setGlobalSettings((current) => ({ ...current, tags: event.target.value }))}
                  />
                </div>

                <div>
                  <Label>Tag de conversão</Label>
                  <Input
                    placeholder="AW-XXXXXXXXX / evento lead"
                    value={globalSettings.conversionTag}
                    onChange={(event) => setGlobalSettings((current) => ({ ...current, conversionTag: event.target.value }))}
                  />
                </div>

                <Button
                  type="button"
                  variant="secondary"
                  onClick={() => saveSettingsMutation.mutate()}
                  disabled={saveSettingsMutation.isPending}
                >
                  {saveSettingsMutation.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Settings2 className="mr-2 h-4 w-4" />}
                  Salvar configurações
                </Button>
              </div>
            </div>

            <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
              <div className="mb-3 flex items-center justify-between">
                <h2 className="font-semibold text-slate-950">Analytics</h2>
                {analyticsLoading && <Loader2 className="h-4 w-4 animate-spin text-slate-400" />}
              </div>
              <div className="h-56">
                <ResponsiveContainer width="100%" height="100%">
                  <AreaChart data={chartData}>
                    <defs>
                      <linearGradient id="googleLeads" x1="0" x2="0" y1="0" y2="1">
                        <stop offset="0%" stopColor="#2563eb" stopOpacity={0.28} />
                        <stop offset="100%" stopColor="#2563eb" stopOpacity={0} />
                      </linearGradient>
                    </defs>
                    <CartesianGrid stroke="#e2e8f0" strokeDasharray="3 3" />
                    <XAxis dataKey="label" tick={{ fontSize: 11, fill: '#64748b' }} axisLine={false} tickLine={false} />
                    <YAxis allowDecimals={false} tick={{ fontSize: 11, fill: '#64748b' }} axisLine={false} tickLine={false} />
                    <Tooltip />
                    <Area dataKey="google" name="Leads Google" stroke="#2563eb" strokeWidth={2} fill="url(#googleLeads)" />
                  </AreaChart>
                </ResponsiveContainer>
              </div>
              {(analytics?.recent_errors || []).filter((item) => item.provider === 'google').slice(0, 2).map((error, index) => (
                <div key={`${error.action}-${index}`} className="mt-3 rounded-lg border border-red-200 bg-red-50 p-3 text-xs text-red-700">
                  <AlertTriangle className="mr-1 inline h-3.5 w-3.5" />
                  {error.action}: {error.message || 'Erro registrado'}
                </div>
              ))}
            </div>
          </section>

          <section className="grid gap-6 lg:grid-cols-[minmax(320px,0.9fr)_minmax(420px,1.1fr)]">
            <div className="rounded-lg border border-slate-200 bg-white shadow-sm">
              <div className="border-b border-slate-200 p-4">
                <div className="flex items-center justify-between gap-3">
                  <div>
                    <h2 className="font-semibold text-slate-950">Imóveis anunciáveis</h2>
                    <p className="text-xs text-slate-500">{filteredProperties.length} imóveis encontrados</p>
                  </div>
                  <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    onClick={() => queryClient.invalidateQueries({ queryKey: ['integrated-ads-properties'] })}
                  >
                    <RefreshCw className="h-4 w-4" />
                  </Button>
                </div>
                <div className="relative mt-3">
                  <Search className="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-slate-400" />
                  <Input className="pl-9" placeholder="Buscar por título, código ou cidade" value={search} onChange={(event) => setSearch(event.target.value)} />
                </div>
              </div>

              <div className="max-h-[760px] overflow-y-auto p-2">
                {propertiesLoading ? (
                  <div className="flex justify-center py-12">
                    <Loader2 className="h-6 w-6 animate-spin text-slate-400" />
                  </div>
                ) : filteredProperties.length === 0 ? (
                  <div className="p-8 text-center text-sm text-slate-500">Nenhum imóvel encontrado.</div>
                ) : (
                  filteredProperties.map((property) => {
                    const status = listingStatuses[property.id]?.publish_status || 'DRAFT';
                    const isSelected = selectedProperty?.id === property.id;
                    return (
                      <button
                        key={property.id}
                        type="button"
                        onClick={() => setSelectedId(property.id)}
                        className={`mb-2 flex w-full gap-3 rounded-lg border p-3 text-left transition ${
                          isSelected ? 'border-blue-300 bg-blue-50' : 'border-slate-200 bg-white hover:bg-slate-50'
                        }`}
                      >
                        {property.imagem ? (
                          <img src={property.imagem} alt={property.titulo} className="h-20 w-24 rounded-md object-cover" />
                        ) : (
                          <div className="flex h-20 w-24 items-center justify-center rounded-md bg-slate-100 text-slate-400">
                            <Home size={22} />
                          </div>
                        )}
                        <div className="min-w-0 flex-1">
                          <div className="mb-1 flex items-start justify-between gap-2">
                            <p className="line-clamp-2 text-sm font-semibold text-slate-950">{property.titulo}</p>
                            <StatusBadge status={status} />
                          </div>
                          <p className="truncate text-xs text-slate-500">{property.codigo} | {property.localizacao}</p>
                          <p className="mt-1 text-sm font-semibold text-slate-800">{formatMoney(property.preco)}</p>
                        </div>
                      </button>
                    );
                  })
                )}
              </div>
            </div>

            <div className="space-y-4">
              {selectedProperty ? (
                <>
                  <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                      <div>
                        <div className="mb-2 flex flex-wrap items-center gap-2">
                          <StatusBadge status={selectedStatus?.publish_status || 'DRAFT'} />
                          {selectedStatus?.last_sync_at && (
                            <span className="text-xs text-slate-500">Sync {new Date(selectedStatus.last_sync_at).toLocaleString('pt-BR')}</span>
                          )}
                        </div>
                        <h2 className="text-xl font-semibold text-slate-950">{selectedProperty.titulo}</h2>
                        <p className="mt-1 text-sm text-slate-500">{selectedProperty.codigo} | {selectedProperty.localizacao}</p>
                      </div>
                      <div className="flex items-center gap-3">
                        <Label className="text-sm text-slate-600">Anúncio ativo</Label>
                        <Switch
                          checked={selectedStatus?.publish_status === 'ACTIVE' || selectedStatus?.publish_status === 'PUBLISHING'}
                          disabled={toggleListingMutation.isPending}
                          onCheckedChange={() =>
                            toggleListingMutation.mutate({
                              propertyId: selectedProperty.id,
                              active: selectedStatus?.publish_status === 'ACTIVE' || selectedStatus?.publish_status === 'PUBLISHING',
                            })
                          }
                        />
                      </div>
                    </div>

                    {selectedStatus?.last_error && (
                      <div className="mb-4 rounded-lg border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                        {selectedStatus.last_error}
                      </div>
                    )}

                    <div className="grid gap-4 md:grid-cols-3">
                      <div className="md:col-span-2">
                        <div className="grid gap-3">
                          <div className="grid gap-3 md:grid-cols-3">
                            <div>
                              <Label>Título 1</Label>
                              <Input value={draft.headline_1} onChange={(event) => setDraft((current) => ({ ...current, headline_1: event.target.value }))} />
                            </div>
                            <div>
                              <Label>Título 2</Label>
                              <Input value={draft.headline_2} onChange={(event) => setDraft((current) => ({ ...current, headline_2: event.target.value }))} />
                            </div>
                            <div>
                              <Label>Título 3</Label>
                              <Input value={draft.headline_3} onChange={(event) => setDraft((current) => ({ ...current, headline_3: event.target.value }))} />
                            </div>
                          </div>

                          <div>
                            <Label>Descrição principal</Label>
                            <Textarea rows={3} value={draft.description_1} onChange={(event) => setDraft((current) => ({ ...current, description_1: event.target.value }))} />
                          </div>
                          <div>
                            <Label>Descrição complementar</Label>
                            <Textarea rows={2} value={draft.description_2} onChange={(event) => setDraft((current) => ({ ...current, description_2: event.target.value }))} />
                          </div>

                          <div className="grid gap-3 md:grid-cols-2">
                            <div>
                              <Label>URL final</Label>
                              <Input value={draft.final_url} onChange={(event) => setDraft((current) => ({ ...current, final_url: event.target.value }))} />
                            </div>
                            <div>
                              <Label>Região do anúncio</Label>
                              <Input value={draft.region} onChange={(event) => setDraft((current) => ({ ...current, region: event.target.value }))} />
                            </div>
                          </div>

                          <div className="grid gap-3 md:grid-cols-2">
                            <div>
                              <Label>Orçamento diário do imóvel</Label>
                              <Input type="number" value={draft.budget_daily_reais} onChange={(event) => setDraft((current) => ({ ...current, budget_daily_reais: event.target.value }))} />
                            </div>
                            <div>
                              <Label>Meta de conversão</Label>
                              <Input value={draft.conversion_goal} onChange={(event) => setDraft((current) => ({ ...current, conversion_goal: event.target.value }))} />
                            </div>
                          </div>

                          <div>
                            <Label>Tags e palavras-chave</Label>
                            <Textarea rows={3} value={draft.keywords} onChange={(event) => setDraft((current) => ({ ...current, keywords: event.target.value }))} />
                          </div>
                          <div>
                            <Label>Palavras negativas</Label>
                            <Input value={draft.negative_keywords} onChange={(event) => setDraft((current) => ({ ...current, negative_keywords: event.target.value }))} />
                          </div>
                        </div>
                      </div>

                      <aside className="rounded-lg border border-slate-200 bg-slate-50 p-3">
                        <p className="mb-3 text-sm font-semibold text-slate-900">Preview</p>
                        {selectedProperty.imagem ? (
                          <img src={selectedProperty.imagem} alt={selectedProperty.titulo} className="mb-3 aspect-[4/3] w-full rounded-md object-cover" />
                        ) : null}
                        <div className="rounded-md border border-slate-200 bg-white p-3">
                          <p className="text-xs text-green-700">Anúncio · {getPreviewHost(draft.final_url)}</p>
                          <p className="mt-1 text-base font-semibold text-blue-700">{draft.headline_1}</p>
                          <p className="text-sm font-medium text-blue-700">{draft.headline_2}</p>
                          <p className="mt-2 text-sm text-slate-700">{draft.description_1}</p>
                        </div>

                        <div className="mt-4 space-y-2 text-xs text-slate-600">
                          <p className="flex items-center gap-2"><CheckCircle2 className="h-4 w-4 text-emerald-600" /> URL e UTM configuráveis</p>
                          <p className="flex items-center gap-2"><Target className="h-4 w-4 text-blue-600" /> Segmentação por região</p>
                          <p className="flex items-center gap-2"><Tag className="h-4 w-4 text-amber-600" /> Tags por imóvel</p>
                        </div>
                      </aside>
                    </div>

                    <div className="mt-4 flex flex-wrap justify-end gap-2 border-t border-slate-200 pt-4">
                      <Button
                        type="button"
                        variant="outline"
                        onClick={() => configureListingMutation.mutate()}
                        disabled={configureListingMutation.isPending}
                      >
                        {configureListingMutation.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : null}
                        Salvar rascunho
                      </Button>
                      <Button
                        type="button"
                        variant={selectedStatus?.publish_status === 'ACTIVE' || selectedStatus?.publish_status === 'PUBLISHING' ? 'destructive' : 'default'}
                        onClick={() =>
                          toggleListingMutation.mutate({
                            propertyId: selectedProperty.id,
                            active: selectedStatus?.publish_status === 'ACTIVE' || selectedStatus?.publish_status === 'PUBLISHING',
                          })
                        }
                        disabled={toggleListingMutation.isPending}
                      >
                        {toggleListingMutation.isPending ? (
                          <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                        ) : selectedStatus?.publish_status === 'ACTIVE' || selectedStatus?.publish_status === 'PUBLISHING' ? (
                          <PauseCircle className="mr-2 h-4 w-4" />
                        ) : (
                          <PlayCircle className="mr-2 h-4 w-4" />
                        )}
                        {selectedStatus?.publish_status === 'ACTIVE' || selectedStatus?.publish_status === 'PUBLISHING' ? 'Pausar anúncio' : 'Ativar anúncio'}
                      </Button>
                    </div>
                  </div>

                  <div className="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
                    <div className="mb-3 flex items-center gap-2">
                      <BarChart3 className="h-4 w-4 text-blue-700" />
                      <h3 className="font-semibold text-slate-950">Melhores imóveis no Google</h3>
                    </div>
                    {(analytics?.top_listings || []).filter((item) => item.google > 0).slice(0, 5).map((item, index) => (
                      <div key={item.listing_id} className="flex items-center justify-between border-t border-slate-100 py-2 text-sm">
                        <span className="min-w-0 truncate text-slate-700">{index + 1}. {item.titulo}</span>
                        <span className="font-semibold text-slate-950">{item.google} leads</span>
                      </div>
                    ))}
                    {(analytics?.top_listings || []).filter((item) => item.google > 0).length === 0 && (
                      <p className="text-sm text-slate-500">Ainda não há leads Google por imóvel.</p>
                    )}
                  </div>
                </>
              ) : (
                <div className="rounded-lg border border-slate-200 bg-white p-10 text-center text-slate-500">
                  Selecione um imóvel para editar o anúncio.
                </div>
              )}
            </div>
          </section>
        </div>
      </main>
    </div>
  );
}
