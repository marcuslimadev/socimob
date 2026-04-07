// Dashboard Principal - SOCIMOB v2 - Timeline Real do Sistema
import { useMemo } from 'react';
import { motion } from 'framer-motion';
import Highcharts from 'highcharts';
import HighchartsReact from 'highcharts-react-official';
import { api } from '@/lib/api';
import { useQuery } from '@tanstack/react-query';
import {
  ArrowUpRight,
  Building2,
  CheckCircle2,
  CircleAlert,
  Clock3,
  FileSignature,
  Home,
  MessageSquareText,
  Target,
  TrendingUp,
  Users,
} from 'lucide-react';
import PageLayout from '@/components/PageLayout';
import StatsGrid from '@/components/StatsGrid';
import TimelineFeed from '@/components/TimelineFeed';
import { useTheme } from '@/contexts/ThemeContext';

interface DashboardStats {
  leads: {
    total: number;
    novos: number;
    em_atendimento: number;
    qualificados: number;
    fechados_mes: number;
  };
  conversas: {
    ativas: number;
    hoje: number;
    aguardando: number;
  };
  corretores?: {
    total: number;
    online: number;
  };
  imoveis: {
    total: number;
    ativos: number;
  };
  vistorias: {
    total: number;
    solicitacoes_pendentes: number;
    em_andamento: number;
  };
  pessoas: {
    total: number;
    fisicas: number;
    juridicas: number;
  };
  contestacoes: {
    total: number;
    apontadas: number;
  };
  assinaturas: {
    total: number;
    pendentes: number;
    assinados: number;
  };
}

const numberFormatter = new Intl.NumberFormat('pt-BR');

function formatNumber(value: number): string {
  return numberFormatter.format(value);
}

function percentage(part: number, total: number): number {
  if (!total) return 0;
  return Math.round((part / total) * 100);
}

function clampPercentage(value: number): number {
  return Math.max(0, Math.min(100, value));
}

export default function Dashboard() {
  const { theme } = useTheme();
  const { data: stats, isLoading: statsLoading } = useQuery({
    queryKey: ['dashboard', 'stats'],
    queryFn: async () => {
      const res = await api.get('/dashboard/stats');
      if (res.data.success) {
        return res.data.data as DashboardStats;
      }
      return null;
    },
    staleTime: 2 * 60 * 1000,
  });

  const user = JSON.parse(localStorage.getItem('user') || '{}');
  const isAdmin = user?.role === 'admin' || user?.role === 'super_admin';
  const firstName = String(user?.name || '').trim().split(' ')[0] || 'Equipe';
  const isLightTheme = theme === 'light';

  const summaryMetrics = stats
    ? {
        pipeline: stats.leads.total,
        performanceRate: percentage(stats.leads.fechados_mes, stats.leads.total),
        qualificationRate: percentage(stats.leads.qualificados, stats.leads.total),
        inventoryRate: percentage(stats.imoveis.ativos, stats.imoveis.total),
        signatureRate: percentage(stats.assinaturas.assinados, stats.assinaturas.total),
        peopleMixRate: percentage(stats.pessoas.fisicas, stats.pessoas.total),
      }
    : null;

  const focusItems = stats
    ? [
        {
          label: 'Conversas aguardando corretor',
          value: stats.conversas.aguardando,
          helper: stats.conversas.aguardando > 0 ? 'Fila pedindo resposta comercial' : 'Fila sob controle',
          tone: stats.conversas.aguardando > 0 ? 'alert' : 'good',
          icon: MessageSquareText,
        },
        {
          label: 'Vistorias pendentes',
          value: stats.vistorias.solicitacoes_pendentes,
          helper: stats.vistorias.em_andamento > 0
            ? `${formatNumber(stats.vistorias.em_andamento)} em andamento`
            : 'Nenhuma em andamento',
          tone: stats.vistorias.solicitacoes_pendentes > 0 ? 'alert' : 'good',
          icon: Clock3,
        },
        {
          label: 'Assinaturas pendentes',
          value: stats.assinaturas.pendentes,
          helper: `${formatNumber(stats.assinaturas.assinados)} concluidas`,
          tone: stats.assinaturas.pendentes > 0 ? 'alert' : 'good',
          icon: FileSignature,
        },
        {
          label: 'Contestacoes apontadas',
          value: stats.contestacoes.apontadas,
          helper: `${formatNumber(stats.contestacoes.total)} registros no periodo`,
          tone: stats.contestacoes.apontadas > 0 ? 'alert' : 'good',
          icon: CircleAlert,
        },
      ]
    : [];

  const distributionCards = stats
    ? [
        {
          label: 'Leads qualificados',
          value: stats.leads.qualificados,
          total: stats.leads.total,
          percent: summaryMetrics?.qualificationRate ?? 0,
          accent: 'bg-[linear-gradient(135deg,rgba(27,94,32,0.28),rgba(76,175,80,0.08))]',
          bar: 'bg-green-400',
          icon: Target,
        },
        {
          label: 'Imoveis ativos',
          value: stats.imoveis.ativos,
          total: stats.imoveis.total,
          percent: summaryMetrics?.inventoryRate ?? 0,
          accent: 'bg-[linear-gradient(135deg,rgba(11,61,145,0.26),rgba(70,170,255,0.08))]',
          bar: 'bg-sky-400',
          icon: Home,
        },
        {
          label: 'Assinaturas concluidas',
          value: stats.assinaturas.assinados,
          total: stats.assinaturas.total,
          percent: summaryMetrics?.signatureRate ?? 0,
          accent: 'bg-[linear-gradient(135deg,rgba(127,59,8,0.26),rgba(245,158,11,0.08))]',
          bar: 'bg-amber-400',
          icon: CheckCircle2,
        },
      ]
    : [];

  const dashboardPulseChart = useMemo<Highcharts.Options>(() => {
    const categories = ['Novos', 'Atendimento', 'Qualificados', 'Fechados', 'Conversas', 'Assinaturas', 'Vistorias'];
    const operationalValues = stats
      ? [
          stats.leads.novos,
          stats.leads.em_atendimento,
          stats.leads.qualificados,
          stats.leads.fechados_mes,
          stats.conversas.aguardando,
          stats.assinaturas.pendentes,
          stats.vistorias.solicitacoes_pendentes,
        ]
      : [0, 0, 0, 0, 0, 0, 0];

    const benchmarkValues = stats
      ? [
          stats.leads.total > 0 ? Math.round(stats.leads.total * 0.22) : 0,
          stats.leads.total > 0 ? Math.round(stats.leads.total * 0.18) : 0,
          stats.leads.total > 0 ? Math.round(stats.leads.total * 0.12) : 0,
          stats.leads.total > 0 ? Math.max(stats.leads.fechados_mes, 1) : 0,
          Math.max(stats.conversas.hoje, 1),
          Math.max(stats.assinaturas.total - stats.assinaturas.assinados, 1),
          Math.max(stats.vistorias.em_andamento, 1),
        ]
      : [0, 0, 0, 0, 0, 0, 0];

    return {
      accessibility: { enabled: false },
      credits: { enabled: false },
      title: { text: undefined },
      chart: {
        backgroundColor: 'transparent',
        spacing: [8, 0, 0, 0],
        height: 220,
        style: {
          fontFamily: 'var(--font-secondary)',
        },
      },
      legend: {
        align: 'left',
        verticalAlign: 'top',
        x: 0,
        y: -6,
        itemStyle: {
          color: isLightTheme ? '#334155' : '#cbd5e1',
          fontSize: '11px',
          fontWeight: '500',
        },
      },
      xAxis: {
        categories,
        lineColor: isLightTheme ? 'rgba(148,163,184,0.3)' : 'rgba(255,255,255,0.12)',
        tickColor: 'transparent',
        labels: {
          style: {
            color: isLightTheme ? '#64748b' : '#94a3b8',
            fontSize: '11px',
          },
        },
      },
      yAxis: {
        title: { text: undefined },
        gridLineColor: isLightTheme ? 'rgba(148,163,184,0.16)' : 'rgba(255,255,255,0.08)',
        labels: {
          style: {
            color: isLightTheme ? '#64748b' : '#94a3b8',
            fontSize: '11px',
          },
        },
      },
      tooltip: {
        shared: true,
        backgroundColor: isLightTheme ? 'rgba(255,255,255,0.98)' : 'rgba(8,15,28,0.96)',
        borderColor: isLightTheme ? 'rgba(148,163,184,0.24)' : 'rgba(125,211,252,0.24)',
        borderRadius: 14,
        style: {
          color: isLightTheme ? '#0f172a' : '#e2e8f0',
        },
        shadow: false,
      },
      plotOptions: {
        series: {
          animation: { duration: 400 },
          states: {
            inactive: { opacity: 1 },
          },
        },
        column: {
          borderRadius: 8,
          borderWidth: 0,
          pointPadding: 0.12,
          groupPadding: 0.12,
        },
        spline: {
          marker: {
            enabled: true,
            radius: 3,
          },
        },
      },
      series: [
        {
          type: 'column',
          name: 'Volume atual',
          data: operationalValues,
          color: isLightTheme ? '#2563eb' : '#7dd3fc',
        },
        {
          type: 'spline',
          name: 'Ritmo esperado',
          data: benchmarkValues,
          color: isLightTheme ? '#f59e0b' : '#fbbf24',
          lineWidth: 2,
        },
      ],
    };
  }, [isLightTheme, stats]);

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: {
        staggerChildren: 0.1,
        delayChildren: 0.1,
      },
    },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: { opacity: 1, y: 0 },
  };

  return (
    <PageLayout>
      <motion.div
        variants={containerVariants}
        initial="hidden"
        animate="visible"
        className="mx-auto max-w-7xl"
      >
        <motion.div variants={itemVariants} className="mb-6 grid gap-4 xl:grid-cols-[1.7fr_1fr]">
          <div className="relative overflow-hidden rounded-[28px] border border-white/10 bg-[radial-gradient(circle_at_top_left,rgba(68,138,255,0.22),transparent_38%),linear-gradient(135deg,rgba(10,15,28,0.98),rgba(15,23,42,0.88))] p-5 sm:p-7">
            <div className="absolute inset-y-0 right-0 w-40 bg-[radial-gradient(circle_at_center,rgba(255,193,7,0.14),transparent_65%)]" />
            <div className="relative z-10">
              <div className="mb-5 flex flex-wrap items-center gap-2 text-[11px] font-semibold uppercase tracking-[0.24em] text-sky-200/80">
                <span className="rounded-full border border-sky-300/20 bg-sky-300/10 px-3 py-1">Radar operacional</span>
                <span className="rounded-full border border-white/10 bg-white/5 px-3 py-1">Atualiza automaticamente</span>
              </div>

              <div className="max-w-3xl">
                <h1 className="mb-2 text-2xl font-semibold leading-tight text-white sm:text-3xl md:text-4xl">
                  {isAdmin ? 'Painel de comando da operacao' : `Bom trabalho, ${firstName}`}
                </h1>
                <p className="max-w-2xl text-sm leading-6 text-slate-300 sm:text-base">
                  {isAdmin
                    ? 'Visao executiva do pipeline comercial, gargalos operacionais e atividade recente do tenant em um unico fluxo.'
                    : 'Acompanhe o ritmo da sua carteira, o que exige resposta imediata e o historico mais recente da operacao.'}
                </p>
              </div>

              <div className="mt-6 grid gap-3 sm:grid-cols-3">
                <div className="rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur-sm">
                  <div className="mb-2 flex items-center justify-between">
                    <span className="text-xs uppercase tracking-[0.2em] text-slate-400">Pipeline</span>
                    <Users size={16} className="text-sky-300" />
                  </div>
                  <div className="text-3xl font-semibold text-white">{formatNumber(stats?.leads.total ?? 0)}</div>
                  <p className="mt-1 text-xs text-slate-400">{formatNumber(stats?.leads.novos ?? 0)} novos em entrada</p>
                </div>

                <div className="rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur-sm">
                  <div className="mb-2 flex items-center justify-between">
                    <span className="text-xs uppercase tracking-[0.2em] text-slate-400">Fechamento do mes</span>
                    <TrendingUp size={16} className="text-emerald-300" />
                  </div>
                  <div className="text-3xl font-semibold text-white">{summaryMetrics ? `${summaryMetrics.performanceRate}%` : '0%'}</div>
                  <p className="mt-1 text-xs text-slate-400">{formatNumber(stats?.leads.fechados_mes ?? 0)} leads fechados neste mes</p>
                </div>

                <div className="rounded-2xl border border-white/10 bg-white/5 p-4 backdrop-blur-sm">
                  <div className="mb-2 flex items-center justify-between">
                    <span className="text-xs uppercase tracking-[0.2em] text-slate-400">Inventario ativo</span>
                    <Building2 size={16} className="text-amber-300" />
                  </div>
                  <div className="text-3xl font-semibold text-white">{summaryMetrics ? `${summaryMetrics.inventoryRate}%` : '0%'}</div>
                  <p className="mt-1 text-xs text-slate-400">{formatNumber(stats?.imoveis.ativos ?? 0)} de {formatNumber(stats?.imoveis.total ?? 0)} imoveis publicados</p>
                </div>
              </div>

              <div className="mt-6 rounded-[24px] border border-white/10 bg-white/5 p-4 backdrop-blur-sm">
                <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                  <div>
                    <p className="text-xs uppercase tracking-[0.22em] text-slate-400">Pulso operacional</p>
                    <h2 className="mt-1 text-sm font-semibold text-white sm:text-base">Highcharts de ritmo comercial e execucao</h2>
                  </div>
                  <div className="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] text-slate-300">
                    leitura dos principais gargalos agora
                  </div>
                </div>
                <HighchartsReact highcharts={Highcharts} options={dashboardPulseChart} />
              </div>
            </div>
          </div>

          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-1">
            <div className="glass-panel rounded-[24px] border border-white/10 p-5">
              <div className="mb-4 flex items-center justify-between">
                <div>
                  <p className="text-xs uppercase tracking-[0.22em] text-muted-foreground">Saude comercial</p>
                  <h2 className="mt-1 text-lg font-semibold text-foreground">Indicadores-chave</h2>
                </div>
                <ArrowUpRight size={18} className="text-muted-foreground" />
              </div>

              <div className="space-y-4">
                <div>
                  <div className="mb-1 flex items-center justify-between text-sm text-foreground">
                    <span>Leads qualificados</span>
                    <span>{summaryMetrics ? `${summaryMetrics.qualificationRate}%` : '0%'}</span>
                  </div>
                  <div className="h-2 rounded-full bg-white/10">
                    <div className="h-2 rounded-full bg-emerald-400" style={{ width: `${clampPercentage(summaryMetrics?.qualificationRate ?? 0)}%` }} />
                  </div>
                </div>

                <div>
                  <div className="mb-1 flex items-center justify-between text-sm text-foreground">
                    <span>Documentos assinados</span>
                    <span>{summaryMetrics ? `${summaryMetrics.signatureRate}%` : '0%'}</span>
                  </div>
                  <div className="h-2 rounded-full bg-white/10">
                    <div className="h-2 rounded-full bg-amber-400" style={{ width: `${clampPercentage(summaryMetrics?.signatureRate ?? 0)}%` }} />
                  </div>
                </div>

                <div className="grid grid-cols-2 gap-3 pt-1">
                  <div className="rounded-2xl bg-white/5 p-3">
                    <p className="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">Hoje</p>
                    <p className="mt-1 text-2xl font-semibold text-foreground">{formatNumber(stats?.conversas.hoje ?? 0)}</p>
                    <p className="text-xs text-muted-foreground">novas conversas</p>
                  </div>
                  <div className="rounded-2xl bg-white/5 p-3">
                    <p className="text-[11px] uppercase tracking-[0.18em] text-muted-foreground">Equipe</p>
                    <p className="mt-1 text-2xl font-semibold text-foreground">{formatNumber(stats?.corretores?.total ?? 0)}</p>
                    <p className="text-xs text-muted-foreground">corretores ativos</p>
                  </div>
                </div>
              </div>
            </div>

            <div className="glass-panel rounded-[24px] border border-white/10 p-5">
              <p className="text-xs uppercase tracking-[0.22em] text-muted-foreground">Base ativa</p>
              <h2 className="mt-1 text-lg font-semibold text-foreground">Composicao do cadastro</h2>
              <div className="mt-4 space-y-3">
                <div className="rounded-2xl bg-white/5 p-3">
                  <div className="flex items-center justify-between">
                    <span className="text-sm text-foreground">Pessoas fisicas</span>
                    <span className="text-sm font-medium text-foreground">{summaryMetrics ? `${summaryMetrics.peopleMixRate}%` : '0%'}</span>
                  </div>
                  <p className="mt-1 text-xs text-muted-foreground">{formatNumber(stats?.pessoas.fisicas ?? 0)} de {formatNumber(stats?.pessoas.total ?? 0)} cadastros ativos</p>
                </div>
                <div className="rounded-2xl bg-white/5 p-3">
                  <div className="flex items-center justify-between">
                    <span className="text-sm text-foreground">Pessoas juridicas</span>
                    <span className="text-sm font-medium text-foreground">{formatNumber(stats?.pessoas.juridicas ?? 0)}</span>
                  </div>
                  <p className="mt-1 text-xs text-muted-foreground">carteira empresarial e parceiros cadastrados</p>
                </div>
              </div>
            </div>
          </div>
        </motion.div>

        <motion.div variants={itemVariants} className="mb-6 grid gap-4 xl:grid-cols-[1.2fr_0.8fr]">
          <div className="glass-panel rounded-[24px] border border-white/10 p-4 sm:p-5">
            <div className="mb-4 flex items-end justify-between gap-3">
              <div>
                <p className="text-xs uppercase tracking-[0.22em] text-muted-foreground">Panorama operacional</p>
                <h2 className="mt-1 text-lg font-semibold text-foreground">Leitura rapida do tenant</h2>
              </div>
              <p className="text-xs text-muted-foreground">Atualizado em tempo real</p>
            </div>
            <StatsGrid stats={stats} loading={statsLoading} />
          </div>

          <div className="glass-panel rounded-[24px] border border-white/10 p-4 sm:p-5">
            <div className="mb-4">
              <p className="text-xs uppercase tracking-[0.22em] text-muted-foreground">Foco agora</p>
              <h2 className="mt-1 text-lg font-semibold text-foreground">O que pede atencao imediata</h2>
            </div>

            <div className="space-y-3">
              {focusItems.map((item) => {
                const Icon = item.icon;
                const isAlert = item.tone === 'alert';

                return (
                  <div
                    key={item.label}
                    className={`rounded-2xl border p-3 transition-colors ${isAlert ? 'border-amber-500/20 bg-amber-500/10' : 'border-emerald-500/20 bg-emerald-500/10'}`}
                  >
                    <div className="flex items-start gap-3">
                      <div className={`mt-0.5 flex h-10 w-10 items-center justify-center rounded-2xl ${isAlert ? 'bg-amber-500/15 text-amber-300' : 'bg-emerald-500/15 text-emerald-300'}`}>
                        <Icon size={18} />
                      </div>
                      <div className="min-w-0 flex-1">
                        <div className="flex items-baseline justify-between gap-3">
                          <p className="text-sm font-medium text-foreground">{item.label}</p>
                          <span className="text-2xl font-semibold text-foreground">{formatNumber(item.value)}</span>
                        </div>
                        <p className="mt-1 text-xs leading-5 text-muted-foreground">{item.helper}</p>
                      </div>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        </motion.div>

        <motion.div variants={itemVariants} className="mb-6 grid gap-4 lg:grid-cols-3">
          {distributionCards.map((card) => {
            const Icon = card.icon;

            return (
              <div key={card.label} className={`overflow-hidden rounded-[24px] border border-white/10 p-5 ${card.accent}`}>
                <div className="flex items-start justify-between gap-3">
                  <div>
                    <p className="text-xs uppercase tracking-[0.2em] text-white/65">{card.label}</p>
                    <div className="mt-3 flex items-end gap-2">
                      <span className="text-3xl font-semibold text-white">{formatNumber(card.value)}</span>
                      <span className="pb-1 text-sm text-white/70">de {formatNumber(card.total)}</span>
                    </div>
                  </div>
                  <div className="rounded-2xl bg-white/10 p-2.5 text-white">
                    <Icon size={18} />
                  </div>
                </div>

                <div className="mt-5">
                  <div className="mb-1 flex items-center justify-between text-sm text-white/80">
                    <span>Taxa atual</span>
                    <span>{card.percent}%</span>
                  </div>
                  <div className="h-2 rounded-full bg-white/15">
                    <div className={`h-2 rounded-full ${card.bar}`} style={{ width: `${clampPercentage(card.percent)}%` }} />
                  </div>
                </div>
              </div>
            );
          })}
        </motion.div>

        <motion.div variants={itemVariants}>
          <div className="glass-panel rounded-[24px] p-4 sm:p-5 md:p-6">
            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
              <div>
                <h2 className="flex items-center gap-2 text-lg font-bold text-foreground sm:text-xl">
                  <span className="h-2 w-2 rounded-full bg-green-400 animate-pulse" />
                  Timeline operacional
                </h2>
                <p className="mt-1 text-sm text-muted-foreground">
                  Feed unificado com eventos comerciais, atendimento, vistorias, assinaturas e logs relevantes.
                </p>
              </div>
              <div className="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-xs text-muted-foreground">
                leitura em fluxo continuo
              </div>
            </div>
            <TimelineFeed />
          </div>
        </motion.div>
      </motion.div>
    </PageLayout>
  );
}
