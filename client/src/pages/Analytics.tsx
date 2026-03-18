import { type ReactNode, useEffect, useState } from 'react';
import Highcharts from 'highcharts';
import HighchartsReact from 'highcharts-react-official';
import {
  Activity,
  ArrowUpRight,
  Building2,
  Compass,
  Loader2,
  Monitor,
  MousePointerClick,
  RefreshCw,
  Smartphone,
  Users,
} from 'lucide-react';
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
  tenants?: Array<{
    tenant_id: number;
    tenant_name: string;
    tenant_domain: string;
    events: number;
    sessions: number;
    pageviews: number;
  }>;
}

type UserRole = 'admin' | 'super_admin' | 'corretor' | 'user' | 'client' | null;

const EMPTY_DATA: OverviewResponse = {
  success: false,
  days: 0,
  summary: {
    pageviews: 0,
    sessions: 0,
    unique_visitors: 0,
  },
  top_pages: [],
  top_referrers: [],
  devices: [],
  browsers: [],
  events: [],
  tenants: [],
};

const PERIOD_OPTIONS = [
  { value: 7, label: '7 dias' },
  { value: 30, label: '30 dias' },
  { value: 90, label: '90 dias' },
  { value: 180, label: '180 dias' },
];

const CHART_COLORS = ['#7dd3fc', '#38bdf8', '#0ea5e9', '#0284c7', '#fbbf24', '#fb7185', '#a78bfa', '#34d399'];

const formatNumber = (value: number) => new Intl.NumberFormat('pt-BR').format(value || 0);
const formatCompact = (value: number) => new Intl.NumberFormat('pt-BR', { notation: 'compact', maximumFractionDigits: 1 }).format(value || 0);
const formatDecimal = (value: number) => value.toFixed(2).replace('.', ',');
const formatPercent = (value: number) => `${value.toFixed(1).replace('.', ',')}%`;

const normalizeLabel = (value?: string | null, fallback = 'Indefinido') => {
  if (!value) return fallback;
  const trimmed = value.trim();
  return trimmed.length > 0 ? trimmed : fallback;
};

const shorten = (value: string, maxLength = 30) => {
  const normalized = normalizeLabel(value, '/');
  if (normalized.length <= maxLength) return normalized;
  return `${normalized.slice(0, maxLength - 3)}...`;
};

const createBaseChartOptions = (): Highcharts.Options => ({
  chart: {
    backgroundColor: 'transparent',
    spacing: [12, 12, 12, 12],
    style: {
      fontFamily: 'var(--font-secondary)',
    },
  },
  title: { text: undefined },
  credits: { enabled: false },
  colors: CHART_COLORS,
  legend: {
    itemStyle: { color: '#dbe7ff', fontWeight: '500' },
    itemHoverStyle: { color: '#ffffff' },
  },
  xAxis: {
    lineColor: 'rgba(148, 163, 184, 0.15)',
    tickColor: 'rgba(148, 163, 184, 0.15)',
    labels: { style: { color: '#9fb0cf', fontSize: '11px' } },
  },
  yAxis: {
    title: { text: undefined },
    gridLineColor: 'rgba(148, 163, 184, 0.12)',
    labels: { style: { color: '#9fb0cf', fontSize: '11px' } },
  },
  tooltip: {
    backgroundColor: 'rgba(6, 11, 26, 0.94)',
    borderColor: 'rgba(125, 211, 252, 0.28)',
    borderRadius: 14,
    style: { color: '#eff6ff' },
    shadow: false,
  },
  plotOptions: {
    series: {
      animation: { duration: 450 },
      dataLabels: {
        style: {
          color: '#e2e8f0',
          textOutline: 'none',
        },
      },
    },
    pie: {
      borderWidth: 0,
      allowPointSelect: true,
      dataLabels: {
        enabled: true,
        format: '{point.name}',
      },
    },
  },
});

function MetricCard({
  title,
  value,
  detail,
  icon,
  tone,
}: {
  title: string;
  value: string;
  detail: string;
  icon: ReactNode;
  tone: 'cyan' | 'amber' | 'violet' | 'emerald';
}) {
  const toneClass = {
    cyan: 'from-cyan-400/24 via-sky-400/12 to-transparent border-cyan-300/20',
    amber: 'from-amber-300/24 via-yellow-300/10 to-transparent border-amber-300/20',
    violet: 'from-violet-400/24 via-fuchsia-400/10 to-transparent border-violet-300/20',
    emerald: 'from-emerald-400/24 via-teal-400/10 to-transparent border-emerald-300/20',
  }[tone];

  return (
    <div className={`relative overflow-hidden rounded-[28px] border bg-[#081223]/90 p-5 shadow-[0_20px_50px_rgba(2,6,23,0.45)] ${toneClass}`}>
      <div className="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(255,255,255,0.14),transparent_35%)]" />
      <div className="relative flex items-start justify-between gap-4">
        <div>
          <p className="text-[11px] uppercase tracking-[0.24em] text-slate-400">{title}</p>
          <p className="mt-3 text-3xl font-semibold tracking-tight text-white">{value}</p>
          <p className="mt-2 text-sm text-slate-400">{detail}</p>
        </div>
        <div className="flex h-12 w-12 items-center justify-center rounded-2xl border border-white/10 bg-white/6 text-slate-100">
          {icon}
        </div>
      </div>
    </div>
  );
}

function ChartShell({
  eyebrow,
  title,
  subtitle,
  children,
}: {
  eyebrow: string;
  title: string;
  subtitle: string;
  children: ReactNode;
}) {
  return (
    <section className="rounded-[30px] border border-white/8 bg-[#07111f]/88 p-5 shadow-[0_18px_48px_rgba(2,6,23,0.42)]">
      <div className="mb-4 flex flex-col gap-1">
        <span className="text-[10px] uppercase tracking-[0.24em] text-sky-200/70">{eyebrow}</span>
        <h3 className="text-xl font-semibold text-white">{title}</h3>
        <p className="text-sm text-slate-400">{subtitle}</p>
      </div>
      {children}
    </section>
  );
}

function InsightRow({ label, value, helper }: { label: string; value: string; helper: string }) {
  return (
    <div className="flex items-start justify-between gap-4 rounded-2xl border border-white/8 bg-white/[0.03] px-4 py-3">
      <div>
        <p className="text-sm font-medium text-white">{label}</p>
        <p className="mt-1 text-xs text-slate-400">{helper}</p>
      </div>
      <div className="text-right text-sm font-semibold text-cyan-200">{value}</div>
    </div>
  );
}

export default function Analytics() {
  const [loading, setLoading] = useState(true);
  const [days, setDays] = useState(30);
  const [reloadKey, setReloadKey] = useState(0);
  const [data, setData] = useState<OverviewResponse>(EMPTY_DATA);
  const [error, setError] = useState<string | null>(null);
  const [role, setRole] = useState<UserRole>(null);

  useEffect(() => {
    const load = async () => {
      try {
        setLoading(true);
        setError(null);

        const userRaw = localStorage.getItem('user');
        const currentRole = userRaw ? JSON.parse(userRaw)?.role ?? null : null;
        const url = currentRole === 'super_admin'
          ? `/super-admin/analytics/overview?days=${days}`
          : `/admin/analytics/overview?days=${days}`;

        const response = await api.get(url);
        setRole(currentRole);
        setData(response.data || EMPTY_DATA);
      } catch (err: any) {
        setError(err?.response?.data?.message || 'Erro ao carregar estatísticas');
        setData(EMPTY_DATA);
      } finally {
        setLoading(false);
      }
    };

    load();
  }, [days, reloadKey]);

  const isSuperAdmin = role === 'super_admin';
  const summary = data.summary || EMPTY_DATA.summary;
  const topPages = data.top_pages || [];
  const topReferrers = data.top_referrers || [];
  const devices = data.devices || [];
  const browsers = data.browsers || [];
  const events = data.events || [];
  const tenants = [...(data.tenants || [])].sort((left, right) => right.pageviews - left.pageviews);

  const totalTenantPageviews = tenants.reduce((sum, tenant) => sum + tenant.pageviews, 0);
  const totalTenantSessions = tenants.reduce((sum, tenant) => sum + tenant.sessions, 0);
  const totalTenantEvents = tenants.reduce((sum, tenant) => sum + tenant.events, 0);
  const leadTenant = tenants[0];
  const topPage = [...topPages].sort((left, right) => right.total - left.total)[0];
  const topEvent = [...events].sort((left, right) => right.total - left.total)[0];
  const sessionsPerVisitor = summary.unique_visitors ? summary.sessions / summary.unique_visitors : 0;
  const pageviewsPerSession = summary.sessions ? summary.pageviews / summary.sessions : 0;
  const mobileCount = devices.find((item) => normalizeLabel(item.device_type).toLowerCase() === 'mobile')?.total || 0;
  const desktopCount = devices.find((item) => normalizeLabel(item.device_type).toLowerCase() === 'desktop')?.total || 0;

  const baseChart = createBaseChartOptions();

  const topPagesChart: Highcharts.Options = {
    ...baseChart,
    chart: { ...baseChart.chart, type: 'bar', height: 340 },
    xAxis: {
      categories: topPages.map((item) => shorten(item.path)),
      lineColor: 'rgba(148, 163, 184, 0.15)',
      tickColor: 'rgba(148, 163, 184, 0.15)',
      labels: { style: { color: '#9fb0cf', fontSize: '11px' } },
    },
    series: [{ type: 'bar', name: 'Pageviews', data: topPages.map((item) => item.total), color: '#38bdf8' }],
  };

  const referrersChart: Highcharts.Options = {
    ...baseChart,
    chart: { ...baseChart.chart, type: 'bar', height: 340 },
    xAxis: {
      categories: topReferrers.map((item) => shorten(normalizeLabel(item.referrer, 'Direto'))),
      lineColor: 'rgba(148, 163, 184, 0.15)',
      tickColor: 'rgba(148, 163, 184, 0.15)',
      labels: { style: { color: '#9fb0cf', fontSize: '11px' } },
    },
    series: [{ type: 'bar', name: 'Acessos', data: topReferrers.map((item) => item.total), color: '#fbbf24' }],
  };

  const eventsChart: Highcharts.Options = {
    ...baseChart,
    chart: { ...baseChart.chart, type: 'column', height: 340 },
    xAxis: {
      categories: events.map((item) => normalizeLabel(item.event_name, 'Evento')),
      lineColor: 'rgba(148, 163, 184, 0.15)',
      tickColor: 'rgba(148, 163, 184, 0.15)',
      labels: { style: { color: '#9fb0cf', fontSize: '11px' } },
    },
    series: [{ type: 'column', name: 'Eventos', data: events.map((item) => item.total), colorByPoint: true }],
  };

  const devicesChart: Highcharts.Options = {
    ...baseChart,
    chart: { ...baseChart.chart, type: 'pie', height: 320 },
    plotOptions: {
      pie: {
        innerSize: '62%',
        borderWidth: 0,
        dataLabels: {
          enabled: true,
          distance: 12,
          style: { color: '#dbeafe', textOutline: 'none', fontSize: '11px' },
        },
      },
    },
    series: [{ type: 'pie', name: 'Dispositivos', data: devices.map((item) => ({ name: normalizeLabel(item.device_type), y: item.total })) }],
  };

  const browsersChart: Highcharts.Options = {
    ...baseChart,
    chart: { ...baseChart.chart, type: 'pie', height: 320 },
    plotOptions: {
      pie: {
        innerSize: '62%',
        borderWidth: 0,
        dataLabels: {
          enabled: true,
          distance: 12,
          style: { color: '#dbeafe', textOutline: 'none', fontSize: '11px' },
        },
      },
    },
    series: [{ type: 'pie', name: 'Navegadores', data: browsers.map((item) => ({ name: normalizeLabel(item.browser), y: item.total })) }],
  };

  const tenantPerformanceChart: Highcharts.Options = {
    ...baseChart,
    chart: { ...baseChart.chart, type: 'column', height: 380 },
    xAxis: {
      categories: tenants.map((tenant) => shorten(tenant.tenant_name)),
      crosshair: true,
      lineColor: 'rgba(148, 163, 184, 0.15)',
      tickColor: 'rgba(148, 163, 184, 0.15)',
      labels: { style: { color: '#9fb0cf', fontSize: '11px' } },
    },
    series: [
      { type: 'column', name: 'Pageviews', data: tenants.map((tenant) => tenant.pageviews), color: '#38bdf8' },
      { type: 'column', name: 'Sessões', data: tenants.map((tenant) => tenant.sessions), color: '#a78bfa' },
      { type: 'column', name: 'Eventos', data: tenants.map((tenant) => tenant.events), color: '#fbbf24' },
    ],
  };

  const tenantShareChart: Highcharts.Options = {
    ...baseChart,
    chart: { ...baseChart.chart, type: 'pie', height: 350 },
    plotOptions: {
      pie: {
        innerSize: '58%',
        borderWidth: 0,
        dataLabels: {
          enabled: true,
          format: '{point.name}',
          style: { color: '#dbeafe', textOutline: 'none', fontSize: '11px' },
        },
      },
    },
    series: [{ type: 'pie', name: 'Participação', data: tenants.map((tenant) => ({ name: shorten(tenant.tenant_name), y: tenant.pageviews })) }],
  };

  const tenantEventsChart: Highcharts.Options = {
    ...baseChart,
    chart: { ...baseChart.chart, type: 'bar', height: 350 },
    xAxis: {
      categories: tenants.map((tenant) => shorten(tenant.tenant_name)),
      lineColor: 'rgba(148, 163, 184, 0.15)',
      tickColor: 'rgba(148, 163, 184, 0.15)',
      labels: { style: { color: '#9fb0cf', fontSize: '11px' } },
    },
    series: [{ type: 'bar', name: 'Eventos totais', data: tenants.map((tenant) => tenant.events), colorByPoint: true }],
  };

  return (
    <div className="flex">
      <Sidebar />
      <div className="page-shell">
        <div className="page-content">
          <section className="relative overflow-hidden rounded-[36px] border border-white/10 bg-[#07111f]/88 px-6 py-7 shadow-[0_24px_60px_rgba(2,6,23,0.55)] sm:px-8">
            <div className="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(125,211,252,0.16),transparent_30%),radial-gradient(circle_at_80%_0%,rgba(251,191,36,0.18),transparent_24%)]" />
            <div className="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
              <div className="max-w-3xl">
                <div className="inline-flex items-center gap-2 rounded-full border border-cyan-300/18 bg-cyan-300/8 px-3 py-1 text-[10px] uppercase tracking-[0.28em] text-cyan-100/82">
                  <Compass className="h-3.5 w-3.5" />
                  Intelligence Console
                </div>
                <h1 className="mt-4 text-4xl font-semibold tracking-tight text-white sm:text-5xl">
                  Analytics com leitura executiva, não so numeros soltos.
                </h1>
                <p className="mt-4 max-w-2xl text-sm leading-6 text-slate-300 sm:text-base">
                  Visao unificada de trafego, comportamento e desempenho do periodo selecionado, com foco em prioridade, origem e concentracao de volume.
                </p>
              </div>

              <div className="flex flex-col gap-4 lg:items-end">
                <div className="flex flex-wrap gap-2">
                  {PERIOD_OPTIONS.map((option) => (
                    <button
                      key={option.value}
                      type="button"
                      onClick={() => setDays(option.value)}
                      className={`rounded-full border px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] transition-all ${days === option.value
                        ? 'border-cyan-300/40 bg-cyan-300/12 text-cyan-100 shadow-[0_0_0_1px_rgba(125,211,252,0.08)]'
                        : 'border-white/10 bg-white/[0.03] text-slate-300 hover:border-white/20 hover:bg-white/[0.05]'
                      }`}
                    >
                      {option.label}
                    </button>
                  ))}
                </div>

                <button
                  type="button"
                  onClick={() => setReloadKey((current) => current + 1)}
                  className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/[0.04] px-4 py-2 text-xs font-semibold uppercase tracking-[0.18em] text-slate-200 transition hover:border-white/20 hover:bg-white/[0.07]"
                >
                  <RefreshCw className={`h-3.5 w-3.5 ${loading ? 'animate-spin' : ''}`} />
                  Atualizar painel
                </button>
              </div>
            </div>
          </section>

          {loading && (
            <div className="mt-8 flex items-center gap-3 rounded-[24px] border border-white/10 bg-[#07111f]/82 px-5 py-4 text-slate-300">
              <Loader2 className="h-5 w-5 animate-spin" />
              Carregando visao analitica...
            </div>
          )}

          {error && (
            <div className="mt-8 rounded-[24px] border border-rose-300/18 bg-rose-400/10 px-5 py-4 text-sm text-rose-100">
              {error}
            </div>
          )}

          {!loading && !error && isSuperAdmin && (
            <>
              <div className="mt-8 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                <MetricCard
                  title="Tenants monitorados"
                  value={formatNumber(tenants.length)}
                  detail="Contas com trafego no periodo selecionado"
                  icon={<Building2 className="h-5 w-5" />}
                  tone="cyan"
                />
                <MetricCard
                  title="Pageviews agregados"
                  value={formatCompact(totalTenantPageviews)}
                  detail="Volume total de paginas vistas"
                  icon={<MousePointerClick className="h-5 w-5" />}
                  tone="amber"
                />
                <MetricCard
                  title="Sessoes"
                  value={formatCompact(totalTenantSessions)}
                  detail="Trafego consolidado entre portais"
                  icon={<Users className="h-5 w-5" />}
                  tone="violet"
                />
                <MetricCard
                  title="Eventos"
                  value={formatCompact(totalTenantEvents)}
                  detail="Interacoes coletadas pelo produto"
                  icon={<Activity className="h-5 w-5" />}
                  tone="emerald"
                />
              </div>

              <div className="mt-8 grid grid-cols-1 gap-6 xl:grid-cols-[1.45fr_0.95fr]">
                <ChartShell
                  eyebrow="Comparativo"
                  title="Desempenho entre tenants"
                  subtitle="Leitura cruzada entre pageviews, sessoes e eventos para identificar concentracao e dispersao de trafego."
                >
                  <HighchartsReact highcharts={Highcharts} options={tenantPerformanceChart} />
                </ChartShell>

                <div className="space-y-6">
                  <ChartShell
                    eyebrow="Participacao"
                    title="Share de audiencia"
                    subtitle="Quanto cada tenant representa dentro do volume total de pageviews."
                  >
                    <HighchartsReact highcharts={Highcharts} options={tenantShareChart} />
                  </ChartShell>

                  <section className="rounded-[30px] border border-white/8 bg-[#07111f]/88 p-5 shadow-[0_18px_48px_rgba(2,6,23,0.42)]">
                    <div className="mb-4 flex items-center gap-2 text-[10px] uppercase tracking-[0.24em] text-amber-100/70">
                      <ArrowUpRight className="h-3.5 w-3.5" />
                      Destaques do periodo
                    </div>
                    <div className="space-y-3">
                      <InsightRow
                        label="Tenant lider"
                        value={leadTenant ? leadTenant.tenant_name : 'Sem dados'}
                        helper={leadTenant ? `${formatNumber(leadTenant.pageviews)} pageviews e ${formatNumber(leadTenant.sessions)} sessoes.` : 'Ainda nao ha trafego consolidado.'}
                      />
                      <InsightRow
                        label="Media por tenant"
                        value={tenants.length ? formatNumber(Math.round(totalTenantPageviews / tenants.length)) : '0'}
                        helper="Pageviews medios por tenant no intervalo atual."
                      />
                      <InsightRow
                        label="Intensidade de eventos"
                        value={totalTenantSessions ? formatPercent((totalTenantEvents / totalTenantSessions) * 100) : '0,0%'}
                        helper="Relacao entre eventos coletados e sessoes registradas."
                      />
                    </div>
                  </section>
                </div>
              </div>

              <div className="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[1fr_0.95fr]">
                <ChartShell
                  eyebrow="Ranking"
                  title="Eventos por tenant"
                  subtitle="Corte direto para ver quem concentra mais atividade operacional e interacoes."
                >
                  <HighchartsReact highcharts={Highcharts} options={tenantEventsChart} />
                </ChartShell>

                <section className="rounded-[30px] border border-white/8 bg-[#07111f]/88 p-5 shadow-[0_18px_48px_rgba(2,6,23,0.42)]">
                  <div className="mb-4 flex flex-col gap-1">
                    <span className="text-[10px] uppercase tracking-[0.24em] text-cyan-100/70">Leaderboard</span>
                    <h3 className="text-xl font-semibold text-white">Tenants mais fortes</h3>
                    <p className="text-sm text-slate-400">Classificacao rapida por volume de pageviews e sessoes.</p>
                  </div>
                  <div className="space-y-3">
                    {tenants.map((tenant, index) => (
                      <div key={tenant.tenant_id} className="rounded-2xl border border-white/8 bg-white/[0.03] px-4 py-3">
                        <div className="flex items-start justify-between gap-4">
                          <div className="flex items-center gap-3">
                            <span className="flex h-8 w-8 items-center justify-center rounded-full bg-white/8 text-xs font-semibold text-slate-200">
                              {index + 1}
                            </span>
                            <div>
                              <p className="text-sm font-semibold text-white">{tenant.tenant_name}</p>
                              <p className="text-xs text-slate-400">{normalizeLabel(tenant.tenant_domain, 'Sem dominio')}</p>
                            </div>
                          </div>
                          <div className="text-right">
                            <p className="text-sm font-semibold text-cyan-200">{formatNumber(tenant.pageviews)} views</p>
                            <p className="text-xs text-slate-400">{formatNumber(tenant.sessions)} sessoes • {formatNumber(tenant.events)} eventos</p>
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>
                </section>
              </div>
            </>
          )}

          {!loading && !error && !isSuperAdmin && (
            <>
              <div className="mt-8 grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                <MetricCard
                  title="Pageviews"
                  value={formatCompact(summary.pageviews)}
                  detail="Volume bruto de navegacao no periodo"
                  icon={<MousePointerClick className="h-5 w-5" />}
                  tone="cyan"
                />
                <MetricCard
                  title="Sessoes"
                  value={formatCompact(summary.sessions)}
                  detail="Entradas ativas registradas"
                  icon={<Users className="h-5 w-5" />}
                  tone="violet"
                />
                <MetricCard
                  title="Visitantes unicos"
                  value={formatCompact(summary.unique_visitors)}
                  detail="Base aproximada de audiencia distinta"
                  icon={<Building2 className="h-5 w-5" />}
                  tone="amber"
                />
                <MetricCard
                  title="Views por sessao"
                  value={pageviewsPerSession ? formatDecimal(pageviewsPerSession) : '0,00'}
                  detail="Profundidade media da navegacao antes da saida"
                  icon={<Activity className="h-5 w-5" />}
                  tone="emerald"
                />
              </div>

              <div className="mt-8 grid grid-cols-1 gap-6 xl:grid-cols-[1.35fr_0.95fr]">
                <ChartShell
                  eyebrow="Paginas"
                  title="Top paginas por tracao"
                  subtitle="Onde o trafego realmente se concentra dentro da navegacao do portal."
                >
                  <HighchartsReact highcharts={Highcharts} options={topPagesChart} />
                </ChartShell>

                <section className="rounded-[30px] border border-white/8 bg-[#07111f]/88 p-5 shadow-[0_18px_48px_rgba(2,6,23,0.42)]">
                  <div className="mb-4 flex flex-col gap-1">
                    <span className="text-[10px] uppercase tracking-[0.24em] text-cyan-100/70">Leitura rapida</span>
                    <h3 className="text-xl font-semibold text-white">Sinais-chave</h3>
                    <p className="text-sm text-slate-400">Resumo acionavel para bater o olho e entender o estado do portal.</p>
                  </div>
                  <div className="space-y-3">
                    <InsightRow
                      label="Pagina lider"
                      value={topPage ? shorten(topPage.path) : 'Sem dados'}
                      helper={topPage ? `${formatNumber(topPage.total)} pageviews no periodo.` : 'Nenhuma pagina com trafego suficiente ainda.'}
                    />
                    <InsightRow
                      label="Evento dominante"
                      value={topEvent ? normalizeLabel(topEvent.event_name, 'Evento') : 'Sem dados'}
                      helper={topEvent ? `${formatNumber(topEvent.total)} ocorrencias registradas.` : 'Nao houve eventos coletados no periodo.'}
                    />
                    <InsightRow
                      label="Sessoes por visitante"
                      value={sessionsPerVisitor ? formatDecimal(sessionsPerVisitor) : '0,00'}
                      helper="Quanto a mesma audiencia retorna dentro da janela analisada."
                    />
                    <InsightRow
                      label="Mobile share"
                      value={summary.pageviews ? formatPercent((mobileCount / summary.pageviews) * 100) : '0,0%'}
                      helper="Participacao de trafego mobile dentro do total de pageviews."
                    />
                  </div>
                </section>
              </div>

              <div className="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-2">
                <ChartShell
                  eyebrow="Eventos"
                  title="Mistura de eventos"
                  subtitle="Distribuicao dos principais sinais de comportamento e conversao coletados pelo sistema."
                >
                  <HighchartsReact highcharts={Highcharts} options={eventsChart} />
                </ChartShell>

                <ChartShell
                  eyebrow="Origem"
                  title="Fontes de trafego"
                  subtitle="Quem mais empurra visitas para dentro do portal e com que peso relativo."
                >
                  <HighchartsReact highcharts={Highcharts} options={referrersChart} />
                </ChartShell>
              </div>

              <div className="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[0.95fr_0.95fr_1.1fr]">
                <ChartShell
                  eyebrow="Dispositivos"
                  title="Mix de dispositivos"
                  subtitle="Entenda se o uso esta concentrado em mobile, desktop ou tablet."
                >
                  <HighchartsReact highcharts={Highcharts} options={devicesChart} />
                </ChartShell>

                <ChartShell
                  eyebrow="Browsers"
                  title="Navegadores ativos"
                  subtitle="Mapeamento de compatibilidade real baseado no trafego observado."
                >
                  <HighchartsReact highcharts={Highcharts} options={browsersChart} />
                </ChartShell>

                <section className="rounded-[30px] border border-white/8 bg-[#07111f]/88 p-5 shadow-[0_18px_48px_rgba(2,6,23,0.42)]">
                  <div className="mb-4 flex flex-col gap-1">
                    <span className="text-[10px] uppercase tracking-[0.24em] text-amber-100/70">Radar operacional</span>
                    <h3 className="text-xl font-semibold text-white">Top linhas brutas</h3>
                    <p className="text-sm text-slate-400">As paginas mais fortes em lista corrida para leitura rapida e priorizacao.</p>
                  </div>
                  <div className="space-y-3">
                    {topPages.map((item, index) => (
                      <div key={`${item.path}-${index}`} className="rounded-2xl border border-white/8 bg-white/[0.03] px-4 py-3">
                        <div className="flex items-center justify-between gap-4">
                          <div className="min-w-0">
                            <p className="truncate text-sm font-semibold text-white">{normalizeLabel(item.path, '/')}</p>
                            <p className="mt-1 text-xs text-slate-400">Rank #{index + 1} em pageviews</p>
                          </div>
                          <div className="text-right">
                            <p className="text-sm font-semibold text-cyan-200">{formatNumber(item.total)}</p>
                            <p className="text-xs text-slate-400">views</p>
                          </div>
                        </div>
                      </div>
                    ))}
                  </div>

                  <div className="mt-4 grid grid-cols-2 gap-3 rounded-2xl border border-white/8 bg-white/[0.03] p-4">
                    <div>
                      <div className="flex items-center gap-2 text-xs uppercase tracking-[0.16em] text-slate-400">
                        <Smartphone className="h-3.5 w-3.5" />
                        Mobile
                      </div>
                      <p className="mt-2 text-lg font-semibold text-white">{formatNumber(mobileCount)}</p>
                    </div>
                    <div>
                      <div className="flex items-center gap-2 text-xs uppercase tracking-[0.16em] text-slate-400">
                        <Monitor className="h-3.5 w-3.5" />
                        Desktop
                      </div>
                      <p className="mt-2 text-lg font-semibold text-white">{formatNumber(desktopCount)}</p>
                    </div>
                  </div>
                </section>
              </div>
            </>
          )}
        </div>
      </div>
    </div>
  );
}
