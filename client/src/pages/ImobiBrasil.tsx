import { useState } from 'react';
import { motion } from 'framer-motion';
import {
  Building2,
  Users,
  MessageSquare,
  Briefcase,
  UserCog,
  MapPin,
  RefreshCw,
  CheckCircle2,
  XCircle,
  Loader2,
  Search,
  ChevronLeft,
  ChevronRight,
  ExternalLink,
  Trash2,
} from 'lucide-react';
import { toast } from 'sonner';
import Sidebar from '@/components/Sidebar';
import {
  useIbAccountStatus,
  useIbPessoas,
  useIbNegocios,
  useIbMensagens,
  useIbCorretores,
  useIbClientes,
  useIbCidades,
  useIbDeleteMensagem,
  useIbDeleteNegocio,
} from '@/hooks/useImobiBrasil';

// ─── Types used in this page ────────────────────────────────────────────────

type TabKey = 'status' | 'pessoas' | 'negocios' | 'mensagens' | 'corretores' | 'clientes' | 'cidades';

const TABS: { key: TabKey; label: string; icon: React.ReactNode }[] = [
  { key: 'status',     label: 'Status da Conta', icon: <CheckCircle2 size={16} /> },
  { key: 'pessoas',    label: 'Pessoas',          icon: <Users size={16} /> },
  { key: 'negocios',   label: 'Negócios',         icon: <Briefcase size={16} /> },
  { key: 'mensagens',  label: 'Mensagens',        icon: <MessageSquare size={16} /> },
  { key: 'corretores', label: 'Corretores',       icon: <UserCog size={16} /> },
  { key: 'clientes',   label: 'Clientes',         icon: <Users size={16} /> },
  { key: 'cidades',    label: 'Cidades',          icon: <MapPin size={16} /> },
];

// ─── Sub-components ──────────────────────────────────────────────────────────

function StatusCard() {
  const { data, isLoading, refetch, isFetching } = useIbAccountStatus();
  const rs = data?.result_set;

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <h3 className="text-lg font-semibold text-foreground">Status da Conta ImobiBrasil</h3>
        <motion.button
          whileHover={{ scale: 1.05 }}
          whileTap={{ scale: 0.95 }}
          onClick={() => refetch()}
          disabled={isFetching}
          className="flex items-center gap-2 px-3 py-2 rounded-lg bg-white/10 text-muted-foreground hover:text-foreground text-sm"
        >
          <RefreshCw size={14} className={isFetching ? 'animate-spin' : ''} />
          Atualizar
        </motion.button>
      </div>

      {isLoading ? (
        <div className="flex justify-center py-12">
          <Loader2 className="animate-spin text-primary" size={32} />
        </div>
      ) : !data?.success ? (
        <div className="p-6 rounded-xl border border-red-500/20 bg-red-500/10 text-red-400 text-sm">
          {(data?.result_set as any)?.message ?? data?.error ?? 'Não foi possível conectar ao ImobiBrasil. Verifique se a integração está configurada.'}
        </div>
      ) : (
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {rs && typeof rs === 'object' && Object.entries(rs).map(([key, value]) => (
            <div key={key} className="p-4 rounded-xl border border-white/10 bg-white/5">
              <p className="text-xs text-muted-foreground mb-1 capitalize">{key.replace(/([A-Z])/g, ' $1').trim()}</p>
              <p className="text-foreground font-medium text-sm">{String(value ?? '-')}</p>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

function PessoasTab() {
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [inputVal, setInputVal] = useState('');
  const { data, isLoading, isFetching } = useIbPessoas({ page, nomeResponsavel: search || undefined });
  const rs   = data?.result_set;
  const list: any[] = rs?.data ?? [];
  const total = rs?.total_items ?? list.length;

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-3">
        <div className="flex-1 relative">
          <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
          <input
            className="w-full pl-9 pr-4 py-2 bg-white/10 border border-white/20 rounded-lg text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="Buscar pessoa..."
            value={inputVal}
            onChange={(e) => setInputVal(e.target.value)}
            onKeyDown={(e) => e.key === 'Enter' && (setSearch(inputVal), setPage(1))}
          />
        </div>
        <motion.button
          whileHover={{ scale: 1.05 }} whileTap={{ scale: 0.95 }}
          onClick={() => { setSearch(inputVal); setPage(1); }}
          className="px-4 py-2 bg-blue-500 hover:bg-blue-600 rounded-lg text-white text-sm font-semibold"
        >
          Buscar
        </motion.button>
      </div>

      {isLoading || isFetching ? (
        <div className="flex justify-center py-12"><Loader2 className="animate-spin text-primary" size={32} /></div>
      ) : list.length === 0 ? (
        <p className="text-center py-12 text-muted-foreground">Nenhuma pessoa encontrada.</p>
      ) : (
        <>
          <p className="text-xs text-muted-foreground">{total} registros</p>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-muted-foreground">
                  <th className="pb-2">Código</th>
                  <th className="pb-2">Nome</th>
                  <th className="pb-2">Tipo</th>
                  <th className="pb-2">E-mail</th>
                  <th className="pb-2">Telefone</th>
                </tr>
              </thead>
              <tbody>
                {list.map((p: any) => (
                  <tr key={p.codigoPessoa ?? p.codigo} className="border-t border-white/10">
                    <td className="py-2 text-muted-foreground font-mono text-xs">{p.codigoPessoa ?? p.codigo ?? '-'}</td>
                    <td className="py-2 text-foreground">{p.nomeResponsavel ?? p.nome ?? '-'}</td>
                    <td className="py-2 text-foreground">{p.tipoPessoa === 'F' ? 'Física' : p.tipoPessoa === 'J' ? 'Jurídica' : (p.tipoPessoa ?? '-')}</td>
                    <td className="py-2 text-foreground">{p.email ?? '-'}</td>
                    <td className="py-2 text-foreground">{p.celular ?? p.fone ?? '-'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <Pagination page={page} onPrev={() => setPage((p) => Math.max(1, p - 1))} onNext={() => setPage((p) => p + 1)} hasMore={list.length >= 20} />
        </>
      )}
    </div>
  );
}

function NegociosTab() {
  const [page, setPage] = useState(1);
  const { data, isLoading, isFetching } = useIbNegocios({ page });
  const rs   = data?.result_set;
  const list: any[] = rs?.data ?? [];
  const deleteMutation = useIbDeleteNegocio();

  return (
    <div className="space-y-4">
      {isLoading || isFetching ? (
        <div className="flex justify-center py-12"><Loader2 className="animate-spin text-primary" size={32} /></div>
      ) : list.length === 0 ? (
        <p className="text-center py-12 text-muted-foreground">Nenhum negócio encontrado.</p>
      ) : (
        <>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-muted-foreground">
                  <th className="pb-2">Código</th>
                  <th className="pb-2">Tipo</th>
                  <th className="pb-2">Etapa</th>
                  <th className="pb-2">Valor</th>
                  <th className="pb-2 text-right">Ações</th>
                </tr>
              </thead>
              <tbody>
                {list.map((n: any) => (
                  <tr key={n.codigoNegocio ?? n.codigo} className="border-t border-white/10">
                    <td className="py-2 text-muted-foreground font-mono text-xs">{n.codigoNegocio ?? n.codigo ?? '-'}</td>
                    <td className="py-2 text-foreground">{n.tipoNegocio ?? n.tipo ?? '-'}</td>
                    <td className="py-2 text-foreground">{n.etapa ?? n.etapaNegocio ?? '-'}</td>
                    <td className="py-2 text-foreground">{n.valor ? `R$ ${Number(n.valor).toLocaleString('pt-BR')}` : '-'}</td>
                    <td className="py-2 text-right">
                      <motion.button
                        whileHover={{ scale: 1.1 }} whileTap={{ scale: 0.9 }}
                        onClick={() => {
                          const id = n.codigoNegocio ?? n.codigo;
                          if (id && confirm('Excluir negócio ' + id + '?')) {
                            deleteMutation.mutate(Number(id), {
                              onSuccess: () => toast.success('Negócio excluído'),
                              onError: () => toast.error('Erro ao excluir negócio'),
                            });
                          }
                        }}
                        className="p-2 rounded-lg bg-red-500/20 text-red-300 hover:bg-red-500/30"
                      >
                        <Trash2 size={14} />
                      </motion.button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <Pagination page={page} onPrev={() => setPage((p) => Math.max(1, p - 1))} onNext={() => setPage((p) => p + 1)} hasMore={list.length >= 20} />
        </>
      )}
    </div>
  );
}

function MensagensTab() {
  const [page, setPage] = useState(1);
  const { data, isLoading, isFetching } = useIbMensagens({ page });
  const rs   = data?.result_set;
  const list: any[] = rs?.data ?? [];
  const deleteMutation = useIbDeleteMensagem();

  return (
    <div className="space-y-4">
      {isLoading || isFetching ? (
        <div className="flex justify-center py-12"><Loader2 className="animate-spin text-primary" size={32} /></div>
      ) : list.length === 0 ? (
        <p className="text-center py-12 text-muted-foreground">Nenhuma mensagem encontrada.</p>
      ) : (
        <>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-muted-foreground">
                  <th className="pb-2">Código</th>
                  <th className="pb-2">Tipo</th>
                  <th className="pb-2">Assunto</th>
                  <th className="pb-2">Lida</th>
                  <th className="pb-2 text-right">Ações</th>
                </tr>
              </thead>
              <tbody>
                {list.map((m: any) => (
                  <tr key={m.codigoMensagem ?? m.codigo} className="border-t border-white/10">
                    <td className="py-2 text-muted-foreground font-mono text-xs">{m.codigoMensagem ?? m.codigo ?? '-'}</td>
                    <td className="py-2 text-foreground">{m.tipoMensagem ?? m.tipo ?? '-'}</td>
                    <td className="py-2 text-foreground">{m.assunto ?? m.titulo ?? '-'}</td>
                    <td className="py-2">
                      {m.lida || m.lido ? (
                        <CheckCircle2 size={14} className="text-green-400" />
                      ) : (
                        <XCircle size={14} className="text-muted-foreground" />
                      )}
                    </td>
                    <td className="py-2 text-right">
                      <motion.button
                        whileHover={{ scale: 1.1 }} whileTap={{ scale: 0.9 }}
                        onClick={() => {
                          const id = m.codigoMensagem ?? m.codigo;
                          if (id && confirm('Excluir mensagem ' + id + '?')) {
                            deleteMutation.mutate(Number(id), {
                              onSuccess: () => toast.success('Mensagem excluída'),
                              onError: () => toast.error('Erro ao excluir mensagem'),
                            });
                          }
                        }}
                        className="p-2 rounded-lg bg-red-500/20 text-red-300 hover:bg-red-500/30"
                      >
                        <Trash2 size={14} />
                      </motion.button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <Pagination page={page} onPrev={() => setPage((p) => Math.max(1, p - 1))} onNext={() => setPage((p) => p + 1)} hasMore={list.length >= 20} />
        </>
      )}
    </div>
  );
}

function CorretoresTab() {
  const { data, isLoading } = useIbCorretores();
  const rs   = data?.result_set;
  const list: any[] = rs?.data ?? (Array.isArray(rs) ? rs : []);

  return (
    <div className="space-y-4">
      {isLoading ? (
        <div className="flex justify-center py-12"><Loader2 className="animate-spin text-primary" size={32} /></div>
      ) : list.length === 0 ? (
        <p className="text-center py-12 text-muted-foreground">Nenhum corretor encontrado.</p>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="text-left text-muted-foreground">
                <th className="pb-2">Código</th>
                <th className="pb-2">Nome</th>
                <th className="pb-2">CRECI</th>
                <th className="pb-2">E-mail</th>
                <th className="pb-2">Telefone</th>
              </tr>
            </thead>
            <tbody>
              {list.map((c: any) => (
                <tr key={c.codigoCorretor ?? c.codigo} className="border-t border-white/10">
                  <td className="py-2 text-muted-foreground font-mono text-xs">{c.codigoCorretor ?? c.codigo ?? '-'}</td>
                  <td className="py-2 text-foreground">{c.nome ?? c.nomeCorretor ?? '-'}</td>
                  <td className="py-2 text-foreground">{c.creci ?? '-'}</td>
                  <td className="py-2 text-foreground">{c.email ?? '-'}</td>
                  <td className="py-2 text-foreground">{c.celular ?? c.fone ?? '-'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}

function ClientesTab() {
  const [page, setPage] = useState(1);
  const { data, isLoading, isFetching } = useIbClientes({ page });
  const rs   = data?.result_set;
  const list: any[] = rs?.data ?? [];

  return (
    <div className="space-y-4">
      {isLoading || isFetching ? (
        <div className="flex justify-center py-12"><Loader2 className="animate-spin text-primary" size={32} /></div>
      ) : list.length === 0 ? (
        <p className="text-center py-12 text-muted-foreground">Nenhum cliente encontrado.</p>
      ) : (
        <>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-muted-foreground">
                  <th className="pb-2">Código</th>
                  <th className="pb-2">Nome</th>
                  <th className="pb-2">E-mail</th>
                  <th className="pb-2">Telefone</th>
                </tr>
              </thead>
              <tbody>
                {list.map((c: any) => (
                  <tr key={c.codigoCliente ?? c.codigo} className="border-t border-white/10">
                    <td className="py-2 text-muted-foreground font-mono text-xs">{c.codigoCliente ?? c.codigo ?? '-'}</td>
                    <td className="py-2 text-foreground">{c.nome ?? c.nomeCliente ?? c.nomeResponsavel ?? '-'}</td>
                    <td className="py-2 text-foreground">{c.email ?? '-'}</td>
                    <td className="py-2 text-foreground">{c.celular ?? c.fone ?? '-'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
          <Pagination page={page} onPrev={() => setPage((p) => Math.max(1, p - 1))} onNext={() => setPage((p) => p + 1)} hasMore={list.length >= 20} />
        </>
      )}
    </div>
  );
}

function CidadesTab() {
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [inputVal, setInputVal] = useState('');
  const { data, isLoading } = useIbCidades({ page });
  const rs   = data?.result_set;
  const allList: any[] = rs?.data ?? (Array.isArray(rs) ? rs as any[] : []);
  // client-side filter by search
  const list = search
    ? allList.filter((c: any) => {
        const name = (c.cidade ?? c.nome ?? '').toLowerCase();
        return name.includes(search.toLowerCase());
      })
    : allList;

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-3">
        <div className="flex-1 relative">
          <Search size={16} className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground" />
          <input
            className="w-full pl-9 pr-4 py-2 bg-white/10 border border-white/20 rounded-lg text-sm text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
            placeholder="Buscar cidade..."
            value={inputVal}
            onChange={(e) => setInputVal(e.target.value)}
            onKeyDown={(e) => e.key === 'Enter' && setSearch(inputVal)}
          />
        </div>
        <motion.button
          whileHover={{ scale: 1.05 }} whileTap={{ scale: 0.95 }}
          onClick={() => setSearch(inputVal)}
          className="px-4 py-2 bg-blue-500 hover:bg-blue-600 rounded-lg text-white text-sm font-semibold"
        >
          Buscar
        </motion.button>
      </div>

      {isLoading ? (
        <div className="flex justify-center py-12"><Loader2 className="animate-spin text-primary" size={32} /></div>
      ) : list.length === 0 ? (
        <p className="text-center py-12 text-muted-foreground">{search ? 'Nenhuma cidade encontrada.' : 'Digite uma cidade para pesquisar.'}</p>
      ) : (
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead>
              <tr className="text-left text-muted-foreground">
                <th className="pb-2">Código</th>
                <th className="pb-2">Cidade</th>
                <th className="pb-2">UF</th>
              </tr>
            </thead>
            <tbody>
              {list.map((c: any, i: number) => (
                <tr key={c.codigoCidade ?? c.codigo ?? i} className="border-t border-white/10">
                  <td className="py-2 text-muted-foreground font-mono text-xs">{c.codigoCidade ?? c.codigo ?? '-'}</td>
                  <td className="py-2 text-foreground">{c.cidade ?? c.nome ?? '-'}</td>
                  <td className="py-2 text-foreground">{c.uf ?? c.estado ?? '-'}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}

function Pagination({ page, onPrev, onNext, hasMore }: { page: number; onPrev: () => void; onNext: () => void; hasMore: boolean }) {
  return (
    <div className="flex items-center justify-center gap-3 mt-4">
      <motion.button
        whileHover={{ scale: 1.05 }} whileTap={{ scale: 0.95 }}
        onClick={onPrev} disabled={page === 1}
        className="p-2 rounded-lg bg-white/10 text-foreground disabled:opacity-40"
      >
        <ChevronLeft size={16} />
      </motion.button>
      <span className="text-sm text-muted-foreground">Página {page}</span>
      <motion.button
        whileHover={{ scale: 1.05 }} whileTap={{ scale: 0.95 }}
        onClick={onNext} disabled={!hasMore}
        className="p-2 rounded-lg bg-white/10 text-foreground disabled:opacity-40"
      >
        <ChevronRight size={16} />
      </motion.button>
    </div>
  );
}

// ─── Main Page ───────────────────────────────────────────────────────────────

export default function ImobiBrasil() {
  const [activeTab, setActiveTab] = useState<TabKey>('status');

  function renderTab() {
    switch (activeTab) {
      case 'status':     return <StatusCard />;
      case 'pessoas':    return <PessoasTab />;
      case 'negocios':   return <NegociosTab />;
      case 'mensagens':  return <MensagensTab />;
      case 'corretores': return <CorretoresTab />;
      case 'clientes':   return <ClientesTab />;
      case 'cidades':    return <CidadesTab />;
    }
  }

  return (
    <div className="flex">
      <Sidebar />
      <div className="page-shell">
        <motion.div
          initial={{ opacity: 0, y: 16 }}
          animate={{ opacity: 1, y: 0 }}
          className="max-w-7xl mx-auto"
        >
          {/* Header */}
          <div className="flex items-center gap-3 mb-8">
            <div className="p-3 rounded-xl bg-emerald-500/20">
              <Building2 size={24} className="text-emerald-400" />
            </div>
            <div>
              <h1 className="text-3xl font-bold text-foreground">ImobiBrasil</h1>
              <p className="text-muted-foreground text-sm mt-1">Integração e gerenciamento de dados</p>
            </div>
            <a
              href="https://api.imobibrasil.com.br"
              target="_blank"
              rel="noopener noreferrer"
              className="ml-auto flex items-center gap-2 text-xs text-muted-foreground hover:text-foreground"
            >
              <ExternalLink size={14} />
              Abrir API
            </a>
          </div>

          {/* Tabs */}
          <div className="glass-panel rounded-2xl overflow-hidden">
            <div className="flex overflow-x-auto border-b border-white/10">
              {TABS.map((tab) => (
                <button
                  key={tab.key}
                  onClick={() => setActiveTab(tab.key)}
                  className={`flex items-center gap-2 px-5 py-3 text-sm font-medium whitespace-nowrap transition-all ${
                    activeTab === tab.key
                      ? 'text-emerald-400 border-b-2 border-emerald-400 bg-emerald-500/5'
                      : 'text-muted-foreground hover:text-foreground'
                  }`}
                >
                  {tab.icon}
                  {tab.label}
                </button>
              ))}
            </div>

            <div className="p-6">
              {renderTab()}
            </div>
          </div>
        </motion.div>
      </div>
    </div>
  );
}
