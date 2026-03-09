import { useEffect, useState } from 'react';
import { useLocation } from 'wouter';
import { api } from '@/lib/api';
import { toast } from 'sonner';
import { LogOut, RefreshCcw } from 'lucide-react';

interface ContratoItem {
  id: number;
  numero_contrato?: string;
  status: string;
  inicio?: string;
  fim?: string;
  valor_aluguel?: number;
  imovel?: { id: number; titulo?: string; codigo?: string; endereco?: string };
  locatario?: { id: number; nome: string };
}

interface RepasseItem {
  id: number;
  competencia: string;
  status: string;
  valor_aluguel_recebido: number;
  valor_taxa_administracao: number;
  valor_repasse: number;
  data_pagamento?: string;
  contrato?: { imovel?: { titulo?: string; codigo?: string } };
}

interface DashboardData {
  proprietario: { id: number; nome: string };
  resumo: { total_contratos: number; total_a_receber: number };
  contratos_ativos: ContratoItem[];
  repasses_recentes: RepasseItem[];
}

type ViewTab = 'dashboard' | 'contratos' | 'repasses' | 'cobrancas';

const formatMoney = (v?: number) =>
  Number(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

const formatDate = (v?: string) => {
  if (!v) return '-';
  const [y, m, d] = v.slice(0, 10).split('-');
  return `${d}/${m}/${y}`;
};

export default function PortalProprietarioDashboard() {
  const [, navigate] = useLocation();
  const [activeTab, setActiveTab] = useState<ViewTab>('dashboard');
  const [data, setData] = useState<DashboardData | null>(null);
  const [contratos, setContratos] = useState<ContratoItem[]>([]);
  const [repasses, setRepasses] = useState<RepasseItem[]>([]);
  const [cobrancas, setCobrancas] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);

  const user = (() => {
    try { return JSON.parse(localStorage.getItem('user') || 'null'); } catch { return null; }
  })();

  const handleLogout = () => {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    navigate('/portal/proprietario/login');
  };

  const loadDashboard = async () => {
    setLoading(true);
    try {
      const { data: d } = await api.get('/portal/proprietario/dashboard');
      setData(d);
    } catch (err: any) {
      if (err?.response?.status === 401) {
        handleLogout();
      } else {
        toast.error('Erro ao carregar dados.');
      }
    } finally {
      setLoading(false);
    }
  };

  const loadContratos = async () => {
    try {
      const { data: d } = await api.get('/portal/proprietario/contratos');
      setContratos(d.items ?? []);
    } catch { toast.error('Erro ao carregar contratos.'); }
  };

  const loadRepasses = async () => {
    try {
      const { data: d } = await api.get('/portal/proprietario/repasses');
      setRepasses(d.items ?? []);
    } catch { toast.error('Erro ao carregar repasses.'); }
  };

  const loadCobrancas = async () => {
    try {
      const { data: d } = await api.get('/portal/proprietario/cobrancas');
      setCobrancas(d.items ?? []);
    } catch { toast.error('Erro ao carregar cobranças.'); }
  };

  useEffect(() => {
    const token = localStorage.getItem('token');
    if (!token) { navigate('/portal/proprietario/login'); return; }
    loadDashboard();
  }, []);

  useEffect(() => {
    if (activeTab === 'contratos' && contratos.length === 0) loadContratos();
    if (activeTab === 'repasses' && repasses.length === 0) loadRepasses();
    if (activeTab === 'cobrancas' && cobrancas.length === 0) loadCobrancas();
  }, [activeTab]);

  return (
    <div className="min-h-screen bg-background">
      {/* Header */}
      <header className="border-b border-border bg-card sticky top-0 z-10">
        <div className="max-w-6xl mx-auto px-4 py-3 flex items-center justify-between">
          <div>
            <h1 className="text-base font-semibold">Portal do Proprietário</h1>
            <p className="text-xs text-muted-foreground">{user?.name || ''}</p>
          </div>
          <button
            type="button"
            onClick={handleLogout}
            className="flex items-center gap-2 px-3 py-2 rounded-lg border border-border hover:bg-accent text-sm"
          >
            <LogOut size={14} /> Sair
          </button>
        </div>
      </header>

      <div className="max-w-6xl mx-auto px-4 py-6 space-y-6">
        {/* Tabs */}
        <div className="flex gap-2 flex-wrap">
          {(['dashboard', 'contratos', 'repasses', 'cobrancas'] as ViewTab[]).map((tab) => {
            const labels: Record<ViewTab, string> = {
              dashboard: 'Início',
              contratos: 'Contratos',
              repasses: 'Repasses',
              cobrancas: 'Cobranças',
            };
            return (
              <button
                key={tab}
                type="button"
                onClick={() => setActiveTab(tab)}
                className={`px-4 py-2 rounded-lg text-sm font-medium transition-colors ${
                  activeTab === tab ? 'bg-primary text-primary-foreground' : 'bg-card border border-border hover:bg-accent'
                }`}
              >
                {labels[tab]}
              </button>
            );
          })}
        </div>

        {/* Dashboard */}
        {activeTab === 'dashboard' && (
          <div className="space-y-5">
            {loading ? (
              <div className="p-8 text-center text-sm text-muted-foreground">Carregando...</div>
            ) : data ? (
              <>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div className="glass-panel rounded-xl p-5">
                    <p className="text-sm text-muted-foreground">Contratos ativos</p>
                    <p className="text-3xl font-bold mt-1">{data.resumo.total_contratos}</p>
                  </div>
                  <div className="glass-panel rounded-xl p-5">
                    <p className="text-sm text-muted-foreground">Repasses pendentes</p>
                    <p className="text-3xl font-bold mt-1 text-amber-600">R$ {formatMoney(data.resumo.total_a_receber)}</p>
                  </div>
                </div>

                <div>
                  <h2 className="text-base font-semibold mb-3">Contratos em andamento</h2>
                  {data.contratos_ativos.length === 0 ? (
                    <div className="glass-panel rounded-xl p-6 text-center text-sm text-muted-foreground">
                      Nenhum contrato ativo no momento.
                    </div>
                  ) : (
                    <div className="space-y-2">
                      {data.contratos_ativos.map((c) => (
                        <div key={c.id} className="glass-panel rounded-xl p-4 flex flex-col md:flex-row md:items-center justify-between gap-2">
                          <div>
                            <p className="font-medium">{c.imovel?.titulo || c.imovel?.codigo || `Imóvel #${c.id}`}</p>
                            <p className="text-sm text-muted-foreground">
                              Locatário: {c.locatario?.nome || '-'} · Vencto. dia {c.fim ? '' : '-'}
                            </p>
                          </div>
                          <div className="text-right shrink-0">
                            <p className="text-sm font-semibold">R$ {formatMoney(c.valor_aluguel)}/mês</p>
                            <p className="text-xs text-muted-foreground">{c.inicio ? formatDate(c.inicio) : ''} → {c.fim ? formatDate(c.fim) : 'Indeterminado'}</p>
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </div>

                {data.repasses_recentes.length > 0 && (
                  <div>
                    <h2 className="text-base font-semibold mb-3">Últimos repasses</h2>
                    <div className="glass-panel rounded-xl overflow-auto">
                      <table className="w-full min-w-[480px] text-sm">
                        <thead>
                          <tr className="border-b border-border">
                            <th className="text-left p-3 text-muted-foreground font-medium">Competência</th>
                            <th className="text-left p-3 text-muted-foreground font-medium">Imóvel</th>
                            <th className="text-right p-3 text-muted-foreground font-medium">Valor</th>
                            <th className="text-left p-3 text-muted-foreground font-medium">Status</th>
                          </tr>
                        </thead>
                        <tbody>
                          {data.repasses_recentes.map((r) => (
                            <tr key={r.id} className="border-b border-border/50">
                              <td className="p-3">{r.competencia}</td>
                              <td className="p-3">{r.contrato?.imovel?.titulo || r.contrato?.imovel?.codigo || '-'}</td>
                              <td className="p-3 text-right font-semibold">R$ {formatMoney(r.valor_repasse)}</td>
                              <td className="p-3">
                                <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${r.status === 'pago' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'}`}>
                                  {r.status === 'pago' ? 'Pago' : 'Pendente'}
                                </span>
                              </td>
                            </tr>
                          ))}
                        </tbody>
                      </table>
                    </div>
                  </div>
                )}
              </>
            ) : null}
          </div>
        )}

        {/* Contratos */}
        {activeTab === 'contratos' && (
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <h2 className="text-base font-semibold">Meus contratos</h2>
              <button type="button" onClick={loadContratos} className="flex items-center gap-2 px-3 py-2 rounded-lg border border-border hover:bg-accent text-sm">
                <RefreshCcw size={14} /> Atualizar
              </button>
            </div>

            {contratos.length === 0 ? (
              <div className="glass-panel rounded-xl p-6 text-center text-sm text-muted-foreground">Nenhum contrato encontrado.</div>
            ) : (
              <div className="space-y-3">
                {contratos.map((c) => (
                  <div key={c.id} className="glass-panel rounded-xl p-5 space-y-3">
                    <div className="flex items-start justify-between gap-2">
                      <div>
                        <p className="font-semibold">{c.imovel?.titulo || c.imovel?.codigo || `Imóvel #${c.id}`}</p>
                        {c.imovel?.endereco && <p className="text-xs text-muted-foreground">{c.imovel.endereco}</p>}
                      </div>
                      <span className={`shrink-0 inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${c.status === 'ativo' ? 'bg-emerald-100 text-emerald-700' : c.status === 'rescindido' ? 'bg-red-100 text-red-700' : 'bg-muted text-muted-foreground'}`}>
                        {c.status}
                      </span>
                    </div>
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-3 text-sm">
                      <div>
                        <p className="text-xs text-muted-foreground">Locatário</p>
                        <p>{c.locatario?.nome || '-'}</p>
                      </div>
                      <div>
                        <p className="text-xs text-muted-foreground">Aluguel</p>
                        <p className="font-semibold">R$ {formatMoney(c.valor_aluguel)}</p>
                      </div>
                      <div>
                        <p className="text-xs text-muted-foreground">Início</p>
                        <p>{formatDate(c.inicio)}</p>
                      </div>
                      <div>
                        <p className="text-xs text-muted-foreground">Término</p>
                        <p>{formatDate(c.fim)}</p>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        )}

        {/* Repasses */}
        {activeTab === 'repasses' && (
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <h2 className="text-base font-semibold">Meus repasses</h2>
              <button type="button" onClick={loadRepasses} className="flex items-center gap-2 px-3 py-2 rounded-lg border border-border hover:bg-accent text-sm">
                <RefreshCcw size={14} /> Atualizar
              </button>
            </div>

            {repasses.length === 0 ? (
              <div className="glass-panel rounded-xl p-6 text-center text-sm text-muted-foreground">Nenhum repasse encontrado.</div>
            ) : (
              <div className="glass-panel rounded-xl overflow-auto">
                <table className="w-full min-w-[560px] text-sm">
                  <thead>
                    <tr className="border-b border-border">
                      <th className="text-left p-3 text-muted-foreground font-medium">Competência</th>
                      <th className="text-left p-3 text-muted-foreground font-medium">Imóvel</th>
                      <th className="text-right p-3 text-muted-foreground font-medium">Bruto</th>
                      <th className="text-right p-3 text-muted-foreground font-medium">Taxa adm.</th>
                      <th className="text-right p-3 text-muted-foreground font-medium">Líquido</th>
                      <th className="text-left p-3 text-muted-foreground font-medium">Status</th>
                      <th className="text-left p-3 text-muted-foreground font-medium">Pgto.</th>
                    </tr>
                  </thead>
                  <tbody>
                    {repasses.map((r) => (
                      <tr key={r.id} className="border-b border-border/50">
                        <td className="p-3">{r.competencia}</td>
                        <td className="p-3">{r.contrato?.imovel?.titulo || r.contrato?.imovel?.codigo || '-'}</td>
                        <td className="p-3 text-right">R$ {formatMoney(r.valor_aluguel_recebido)}</td>
                        <td className="p-3 text-right text-red-600">-R$ {formatMoney(r.valor_taxa_administracao)}</td>
                        <td className="p-3 text-right font-semibold text-emerald-700">R$ {formatMoney(r.valor_repasse)}</td>
                        <td className="p-3">
                          <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${r.status === 'pago' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700'}`}>
                            {r.status === 'pago' ? 'Pago' : 'Pendente'}
                          </span>
                        </td>
                        <td className="p-3">{r.data_pagamento ? formatDate(r.data_pagamento) : '-'}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        )}

        {/* Cobranças */}
        {activeTab === 'cobrancas' && (
          <div className="space-y-4">
            <div className="flex items-center justify-between">
              <h2 className="text-base font-semibold">Cobranças dos meus imóveis</h2>
              <button type="button" onClick={loadCobrancas} className="flex items-center gap-2 px-3 py-2 rounded-lg border border-border hover:bg-accent text-sm">
                <RefreshCcw size={14} /> Atualizar
              </button>
            </div>

            {cobrancas.length === 0 ? (
              <div className="glass-panel rounded-xl p-6 text-center text-sm text-muted-foreground">Nenhuma cobrança encontrada.</div>
            ) : (
              <div className="glass-panel rounded-xl overflow-auto">
                <table className="w-full min-w-[560px] text-sm">
                  <thead>
                    <tr className="border-b border-border">
                      <th className="text-left p-3 text-muted-foreground font-medium">Competência</th>
                      <th className="text-left p-3 text-muted-foreground font-medium">Imóvel</th>
                      <th className="text-left p-3 text-muted-foreground font-medium">Locatário</th>
                      <th className="text-left p-3 text-muted-foreground font-medium">Vencimento</th>
                      <th className="text-right p-3 text-muted-foreground font-medium">Valor</th>
                      <th className="text-left p-3 text-muted-foreground font-medium">Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    {cobrancas.map((c: any) => (
                      <tr key={c.id} className="border-b border-border/50">
                        <td className="p-3">{c.competencia}</td>
                        <td className="p-3">{c.contrato?.imovel?.titulo || c.contrato?.imovel?.codigo || '-'}</td>
                        <td className="p-3">{c.contrato?.locatario?.nome || '-'}</td>
                        <td className="p-3">{formatDate(c.vencimento)}</td>
                        <td className="p-3 text-right font-semibold">R$ {formatMoney(c.valor_total)}</td>
                        <td className="p-3">
                          <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${
                            c.status === 'pago' || c.status === 'liquidado' ? 'bg-emerald-100 text-emerald-700' :
                            c.status === 'vencido' ? 'bg-red-100 text-red-700' :
                            'bg-amber-100 text-amber-700'
                          }`}>
                            {c.status}
                          </span>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}
          </div>
        )}
      </div>
    </div>
  );
}
