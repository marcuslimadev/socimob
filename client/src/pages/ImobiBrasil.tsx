import { useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
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
  X,
  Phone,
  Mail,
  MapPin as MapPinIcon,
  User,
  Hash,
  Calendar,
  FileText,
} from 'lucide-react';
import { toast } from 'sonner';
import Sidebar from '@/components/Sidebar';
import {
  useIbAccountStatus,
  useIbPessoas,
  useIbPessoa,
  useIbNegocios,
  useIbNegocio,
  useIbMensagens,
  useIbMensagem,
  useIbCorretores,
  useIbCorretor,
  useIbClientes,
  useIbCliente,
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

// ─── Shared helpers ──────────────────────────────────────────────────────────

function fmt(v: any) {
  if (v === null || v === undefined || v === '' || v === 0) return '-';
  return String(v);
}

function fmtPhone(v: any) {
  if (!v) return '-';
  const s = String(v).replace(/\D/g, '');
  if (s.length === 11) return `(${s.slice(0,2)}) ${s.slice(2,7)}-${s.slice(7)}`;
  if (s.length === 10) return `(${s.slice(0,2)}) ${s.slice(2,6)}-${s.slice(6)}`;
  return s;
}

function fmtCpfCnpj(v: any) {
  if (!v) return '-';
  const s = String(v).replace(/\D/g, '').padStart(v > 99999999999 ? 14 : 11, '0');
  if (s.length === 14) return s.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
  return s.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
}

function fmtDate(v: any) {
  if (!v) return '-';
  return String(v).slice(0, 10).split('-').reverse().join('/');
}

function DR({ label, value, icon }: { label: string; value: React.ReactNode; icon?: React.ReactNode }) {
  return (
    <div className="flex items-start gap-2 py-2 border-b border-white/5 last:border-0">
      {icon && <span className="text-muted-foreground mt-0.5 shrink-0">{icon}</span>}
      <div className="min-w-0">
        <p className="text-xs text-muted-foreground">{label}</p>
        <p className="text-sm text-foreground break-all">{value || '-'}</p>
      </div>
    </div>
  );
}

function DetailPanel({ title, onClose, loading, children }: { title: string; onClose: () => void; loading?: boolean; children: React.ReactNode }) {
  return (
    <AnimatePresence>
      <motion.div
        initial={{ x: '100%', opacity: 0 }}
        animate={{ x: 0, opacity: 1 }}
        exit={{ x: '100%', opacity: 0 }}
        transition={{ type: 'spring', damping: 28, stiffness: 300 }}
        className="fixed right-0 top-0 h-full w-full max-w-sm bg-[#0f1117] border-l border-white/10 shadow-2xl z-50 flex flex-col overflow-hidden"
      >
        <div className="flex items-center justify-between px-5 py-4 border-b border-white/10">
          <h3 className="font-semibold text-foreground">{title}</h3>
          <button onClick={onClose} className="p-1 rounded-lg hover:bg-white/10 text-muted-foreground hover:text-foreground">
            <X size={18} />
          </button>
        </div>
        <div className="flex-1 overflow-y-auto px-5 py-4">
          {loading ? (
            <div className="flex justify-center py-12"><Loader2 className="animate-spin text-primary" size={28} /></div>
          ) : children}
        </div>
      </motion.div>
    </AnimatePresence>
  );
}

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
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const { data, isLoading, isFetching } = useIbPessoas({ page, nomeResponsavel: search || undefined });
  const { data: detail, isLoading: loadingDetail } = useIbPessoa(selectedId);
  const rs    = data?.result_set;
  const list: any[] = rs?.data ?? [];
  const total = rs?.total_items ?? list.length;
  const d     = detail?.result_set as any;
  const phones = d ? [d.telefone1, d.telefone2, d.telefone3].filter(Boolean) : [];
  const enderecos: any[] = d && Array.isArray(d.endereco) ? d.endereco : [];

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
          <p className="text-xs text-muted-foreground">{total} registros — clique numa linha para ver detalhes</p>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-muted-foreground">
                  <th className="pb-2">Código</th>
                  <th className="pb-2">Nome</th>
                  <th className="pb-2">Tipo</th>
                  <th className="pb-2">Referência</th>
                  <th className="pb-2">Telefone</th>
                </tr>
              </thead>
              <tbody>
                {list.map((p: any) => {
                  const id = p.codigoPessoa ?? p.codigo;
                  return (
                    <tr
                      key={id}
                      onClick={() => setSelectedId(selectedId === id ? null : id)}
                      className={`border-t border-white/10 cursor-pointer transition-colors ${selectedId === id ? 'bg-blue-500/10' : 'hover:bg-white/5'}`}
                    >
                      <td className="py-2 text-muted-foreground font-mono text-xs">{id ?? '-'}</td>
                      <td className="py-2 text-foreground">{p.nomeResponsavel ?? p.nome ?? '-'}</td>
                      <td className="py-2 text-foreground">{p.tipoPessoa === 'F' ? 'Física' : p.tipoPessoa === 'J' ? 'Jurídica' : (p.tipoPessoa ?? '-')}</td>
                      <td className="py-2 text-foreground text-xs">{p.referenciaPessoa ?? '-'}</td>
                      <td className="py-2 text-foreground">{fmtPhone(p.telefone1 ?? p.telefone2 ?? p.telefone3)}</td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
          <Pagination page={page} onPrev={() => setPage((p) => Math.max(1, p - 1))} onNext={() => setPage((p) => p + 1)} hasMore={list.length >= 20} />
        </>
      )}

      {selectedId !== null && (
        <DetailPanel title="Detalhes da Pessoa" onClose={() => setSelectedId(null)} loading={loadingDetail}>
          {d && (
            <div className="space-y-1">
              <div className="flex items-center gap-3 mb-4">
                <div className="w-12 h-12 rounded-full bg-blue-500/20 flex items-center justify-center shrink-0">
                  <User size={22} className="text-blue-400" />
                </div>
                <div>
                  <p className="font-semibold text-foreground">{fmt(d.nomeResponsavel ?? d.nome)}</p>
                  {d.razaoSocial && <p className="text-xs text-muted-foreground">{d.razaoSocial}</p>}
                  {d.nomeFantasia && <p className="text-xs text-muted-foreground">{d.nomeFantasia}</p>}
                </div>
              </div>
              <DR label="Tipo" value={d.tipoPessoa === 'F' ? 'Pessoa Física' : d.tipoPessoa === 'J' ? 'Pessoa Jurídica' : fmt(d.tipoPessoa)} icon={<User size={13} />} />
              <DR label="CPF" value={fmtCpfCnpj(d.cpf)} icon={<Hash size={13} />} />
              <DR label="CNPJ" value={fmtCpfCnpj(d.cnpj)} icon={<Hash size={13} />} />
              <DR label="RG" value={fmt(d.rg)} icon={<Hash size={13} />} />
              <DR label="E-mail" value={fmt(d.email)} icon={<Mail size={13} />} />
              {phones.map((ph: any, i: number) => (
                <DR key={i} label={`Telefone ${i + 1}`} value={fmtPhone(ph)} icon={<Phone size={13} />} />
              ))}
              <DR label="Profissão" value={fmt(d.profissao)} icon={<FileText size={13} />} />
              <DR label="Nascimento" value={fmtDate(d.dataNascimento)} icon={<Calendar size={13} />} />
              <DR label="CRECI" value={fmt(d.creci)} icon={<Hash size={13} />} />
              <DR label="Referência" value={fmt(d.referenciaPessoa)} icon={<FileText size={13} />} />
              <DR label="Origem" value={fmt(d.origemCaptacao ?? d.origem)} icon={<FileText size={13} />} />
              {enderecos.length > 0 && (
                <>
                  <p className="text-xs font-semibold text-muted-foreground mt-3 mb-1 uppercase tracking-wide">Endereços</p>
                  {enderecos.map((e: any, i: number) => (
                    <div key={i} className="pl-2 border-l-2 border-blue-500/30 mb-2">
                      <DR label="Logradouro" value={[e.logradouro, e.numero, e.complemento].filter(Boolean).join(', ')} icon={<MapPinIcon size={13} />} />
                      <DR label="Bairro" value={fmt(e.bairro)} icon={<MapPinIcon size={13} />} />
                      <DR label="Cidade/UF" value={[e.cidade ?? e.nomeCidade, e.estado ?? e.siglaEstado].filter(Boolean).join(' - ')} icon={<MapPinIcon size={13} />} />
                      <DR label="CEP" value={fmt(e.cep)} icon={<MapPinIcon size={13} />} />
                    </div>
                  ))}
                </>
              )}
              <DR label="Cadastrado em" value={fmtDate(d.dataCadastro ?? d.cadastradoEm)} icon={<Calendar size={13} />} />
              <DR label="Atualizado em" value={fmtDate(d.dataAlteracao ?? d.ultimaAtualizacao)} icon={<Calendar size={13} />} />
            </div>
          )}
        </DetailPanel>
      )}
    </div>
  );
}

function NegociosTab() {
  const [page, setPage] = useState(1);
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const { data, isLoading, isFetching } = useIbNegocios({ page });
  const { data: detail, isLoading: loadingDetail } = useIbNegocio(selectedId);
  const rs   = data?.result_set;
  const list: any[] = rs?.data ?? [];
  const deleteMutation = useIbDeleteNegocio();
  const d    = detail?.result_set as any;

  return (
    <div className="space-y-4">
      {isLoading || isFetching ? (
        <div className="flex justify-center py-12"><Loader2 className="animate-spin text-primary" size={32} /></div>
      ) : list.length === 0 ? (
        <p className="text-center py-12 text-muted-foreground">Nenhum negócio encontrado.</p>
      ) : (
        <>
          <p className="text-xs text-muted-foreground">Clique numa linha para ver detalhes</p>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-muted-foreground">
                  <th className="pb-2">Código</th>
                  <th className="pb-2">Título / Tipo</th>
                  <th className="pb-2">Etapa</th>
                  <th className="pb-2">Valor</th>
                  <th className="pb-2 text-right">Ações</th>
                </tr>
              </thead>
              <tbody>
                {list.map((n: any) => {
                  const id = n.codigoNegocio ?? n.codigo;
                  return (
                    <tr
                      key={id}
                      onClick={(e) => { if ((e.target as HTMLElement).closest('button')) return; setSelectedId(selectedId === id ? null : id); }}
                      className={`border-t border-white/10 cursor-pointer transition-colors ${selectedId === id ? 'bg-blue-500/10' : 'hover:bg-white/5'}`}
                    >
                      <td className="py-2 text-muted-foreground font-mono text-xs">{id ?? '-'}</td>
                      <td className="py-2 text-foreground">{n.tituloNegocio ?? n.tipoNegocio ?? n.tipo ?? '-'}</td>
                      <td className="py-2 text-foreground">{n.tituloEtapaNegocio ?? n.etapa ?? n.etapaNegocio ?? '-'}</td>
                      <td className="py-2 text-foreground">{(n.valorGanhoNegocio ?? n.valor) ? `R$ ${Number(n.valorGanhoNegocio ?? n.valor).toLocaleString('pt-BR')}` : '-'}</td>
                      <td className="py-2 text-right">
                        <motion.button
                          whileHover={{ scale: 1.1 }} whileTap={{ scale: 0.9 }}
                          onClick={(e) => { e.stopPropagation(); const id2 = n.codigoNegocio ?? n.codigo; if (id2 && confirm('Excluir negócio ' + id2 + '?')) { deleteMutation.mutate(Number(id2), { onSuccess: () => toast.success('Negócio excluído'), onError: () => toast.error('Erro ao excluir negócio') }); } }}
                          className="p-2 rounded-lg bg-red-500/20 text-red-300 hover:bg-red-500/30"
                        >
                          <Trash2 size={14} />
                        </motion.button>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
          <Pagination page={page} onPrev={() => setPage((p) => Math.max(1, p - 1))} onNext={() => setPage((p) => p + 1)} hasMore={list.length >= 20} />
        </>
      )}

      {selectedId !== null && (
        <DetailPanel title="Detalhes do Negócio" onClose={() => setSelectedId(null)} loading={loadingDetail}>
          {d && (
            <div className="space-y-1">
              <div className="mb-4">
                <p className="font-semibold text-foreground text-lg">{fmt(d.tituloNegocio)}</p>
                <span className={`mt-1 inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${d.statusNegocio === 'Ganho' ? 'bg-emerald-500/20 text-emerald-400' : d.statusNegocio === 'Perdido' ? 'bg-red-500/20 text-red-400' : 'bg-blue-500/20 text-blue-400'}`}>
                  {fmt(d.statusNegocio)}
                </span>
              </div>
              <DR label="Etapa" value={fmt(d.tituloEtapaNegocio)} icon={<Briefcase size={13} />} />
              <DR label="Operação" value={fmt(d.operacaoNegocio ?? d.operacao)} icon={<Briefcase size={13} />} />
              <DR label="Probabilidade" value={d.probabilidadeNegocio != null ? `${d.probabilidadeNegocio}%` : '-'} icon={<Hash size={13} />} />
              <DR label="Valor ganho" value={d.valorGanhoNegocio ? `R$ ${Number(d.valorGanhoNegocio).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}` : '-'} icon={<Hash size={13} />} />
              <DR label="Descrição" value={fmt(d.descricaoNegocio ?? d.descricao)} icon={<FileText size={13} />} />
              <DR label="Anotação" value={fmt(d.anotacaoNegocio ?? d.anotacao)} icon={<FileText size={13} />} />
              <DR label="Motivo perdido" value={fmt(d.motivoPerdidoNegocio ?? d.motivoPerdido)} icon={<FileText size={13} />} />
              <DR label="Data prevista" value={fmtDate(d.dataPrevistaNegocio)} icon={<Calendar size={13} />} />
              <DR label="Data fechamento" value={fmtDate(d.dataFechamento)} icon={<Calendar size={13} />} />
              <DR label="Criado em" value={fmtDate(d.dataCriacao ?? d.cadastradoEm)} icon={<Calendar size={13} />} />
              <DR label="Atualizado em" value={fmtDate(d.dataAlteracao ?? d.ultimaAtualizacao)} icon={<Calendar size={13} />} />
              {d.sinalizaoNegocio != null && (
                <DR label="Sinalização" value={d.sinalizaoNegocio ? 'Sim' : 'Não'} icon={<Hash size={13} />} />
              )}
            </div>
          )}
        </DetailPanel>
      )}
    </div>
  );
}

function MensagensTab() {
  const [page, setPage] = useState(1);
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const { data, isLoading, isFetching } = useIbMensagens({ page });
  const { data: detail, isLoading: loadingDetail } = useIbMensagem(selectedId);
  const rs   = data?.result_set;
  const list: any[] = rs?.data ?? [];
  const deleteMutation = useIbDeleteMensagem();
  const d    = detail?.result_set as any;

  return (
    <div className="space-y-4">
      {isLoading || isFetching ? (
        <div className="flex justify-center py-12"><Loader2 className="animate-spin text-primary" size={32} /></div>
      ) : list.length === 0 ? (
        <p className="text-center py-12 text-muted-foreground">Nenhuma mensagem encontrada.</p>
      ) : (
        <>
          <p className="text-xs text-muted-foreground">Clique numa linha para ver o conteúdo</p>
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
                {list.map((m: any) => {
                  const id = m.codigoMensagem ?? m.codigo;
                  return (
                    <tr
                      key={id}
                      onClick={(e) => { if ((e.target as HTMLElement).closest('button')) return; setSelectedId(selectedId === id ? null : id); }}
                      className={`border-t border-white/10 cursor-pointer transition-colors ${selectedId === id ? 'bg-blue-500/10' : 'hover:bg-white/5'}`}
                    >
                      <td className="py-2 text-muted-foreground font-mono text-xs">{id ?? '-'}</td>
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
                          onClick={(e) => { e.stopPropagation(); if (id && confirm('Excluir mensagem ' + id + '?')) { deleteMutation.mutate(Number(id), { onSuccess: () => toast.success('Mensagem excluída'), onError: () => toast.error('Erro ao excluir mensagem') }); } }}
                          className="p-2 rounded-lg bg-red-500/20 text-red-300 hover:bg-red-500/30"
                        >
                          <Trash2 size={14} />
                        </motion.button>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
          <Pagination page={page} onPrev={() => setPage((p) => Math.max(1, p - 1))} onNext={() => setPage((p) => p + 1)} hasMore={list.length >= 20} />
        </>
      )}

      {selectedId !== null && (
        <DetailPanel title="Detalhes da Mensagem" onClose={() => setSelectedId(null)} loading={loadingDetail}>
          {d && (
            <div className="space-y-1">
              <DR label="Assunto" value={fmt(d.assunto ?? d.titulo)} icon={<MessageSquare size={13} />} />
              <DR label="Tipo" value={fmt(d.tipoMensagem ?? d.tipo)} icon={<FileText size={13} />} />
              <DR label="Lida" value={(d.lida || d.lido) ? 'Sim' : 'Não'} icon={<CheckCircle2 size={13} />} />
              <DR label="Remetente" value={fmt(d.remetente ?? d.nomeRemetente)} icon={<User size={13} />} />
              <DR label="Data" value={fmtDate(d.dataCriacao ?? d.data ?? d.dataMensagem)} icon={<Calendar size={13} />} />
              {(d.mensagem ?? d.texto ?? d.corpo) && (
                <div className="mt-3">
                  <p className="text-xs text-muted-foreground mb-1">Conteúdo</p>
                  <div className="p-3 rounded-lg bg-white/5 border border-white/10 text-sm text-foreground whitespace-pre-wrap break-words">
                    {d.mensagem ?? d.texto ?? d.corpo}
                  </div>
                </div>
              )}
            </div>
          )}
        </DetailPanel>
      )}
    </div>
  );
}

function CorretoresTab() {
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const { data, isLoading } = useIbCorretores();
  const { data: detail, isLoading: loadingDetail } = useIbCorretor(selectedId);
  const rs   = data?.result_set;
  const list: any[] = rs?.data ?? (Array.isArray(rs) ? rs : []);
  const d    = detail?.result_set as any;
  const phones = d ? [d.telefone1, d.telefone2, d.telefone3].filter(Boolean) : [];

  return (
    <div className="space-y-4">
      {isLoading ? (
        <div className="flex justify-center py-12"><Loader2 className="animate-spin text-primary" size={32} /></div>
      ) : list.length === 0 ? (
        <p className="text-center py-12 text-muted-foreground">Nenhum corretor encontrado.</p>
      ) : (
        <>
          <p className="text-xs text-muted-foreground">Clique numa linha para ver detalhes</p>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-muted-foreground">
                  <th className="pb-2">Código</th>
                  <th className="pb-2">Nome</th>
                  <th className="pb-2">Tipo</th>
                  <th className="pb-2">Referência</th>
                  <th className="pb-2">Status</th>
                </tr>
              </thead>
              <tbody>
                {list.map((c: any) => {
                  const id = c.codigoCorretor ?? c.codigo;
                  return (
                    <tr
                      key={id}
                      onClick={() => setSelectedId(selectedId === id ? null : id)}
                      className={`border-t border-white/10 cursor-pointer transition-colors ${selectedId === id ? 'bg-blue-500/10' : 'hover:bg-white/5'}`}
                    >
                      <td className="py-2 text-muted-foreground font-mono text-xs">{id ?? '-'}</td>
                      <td className="py-2 text-foreground">{c.nome ?? c.nomeCorretor ?? '-'}</td>
                      <td className="py-2 text-foreground">{c.tipoPessoa === 'F' ? 'Física' : c.tipoPessoa === 'J' ? 'Jurídica' : (c.tipoPessoa ?? '-')}</td>
                      <td className="py-2 text-foreground text-xs">{c.referenciaCorretor ?? '-'}</td>
                      <td className="py-2">
                        <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${c.statusCorretor ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400'}`}>
                          {c.statusCorretor ? 'Ativo' : 'Inativo'}
                        </span>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
        </>
      )}

      {selectedId !== null && (
        <DetailPanel title="Detalhes do Corretor" onClose={() => setSelectedId(null)} loading={loadingDetail}>
          {d && (
            <div className="space-y-1">
              <div className="flex items-center gap-3 mb-4">
                <div className="w-12 h-12 rounded-full bg-emerald-500/20 flex items-center justify-center shrink-0">
                  <UserCog size={22} className="text-emerald-400" />
                </div>
                <div>
                  <p className="font-semibold text-foreground">{fmt(d.nome ?? d.nomeCorretor)}</p>
                  {d.razaoSocial && <p className="text-xs text-muted-foreground">{d.razaoSocial}</p>}
                  {d.nomeFantasia && <p className="text-xs text-muted-foreground">{d.nomeFantasia}</p>}
                </div>
              </div>
              <DR label="CRECI" value={fmt(d.creciCorretor ?? d.creci)} icon={<Hash size={13} />} />
              <DR label="Tipo pessoa" value={d.tipoPessoa === 'F' ? 'Física' : d.tipoPessoa === 'J' ? 'Jurídica' : fmt(d.tipoPessoa)} icon={<User size={13} />} />
              <DR label="CPF" value={fmtCpfCnpj(d.cpf)} icon={<Hash size={13} />} />
              <DR label="CNPJ" value={fmtCpfCnpj(d.cnpj)} icon={<Hash size={13} />} />
              <DR label="E-mail" value={fmt(d.email)} icon={<Mail size={13} />} />
              {phones.map((ph: any, i: number) => (
                <DR key={i} label={`Telefone ${i + 1}`} value={fmtPhone(ph)} icon={<Phone size={13} />} />
              ))}
              <DR label="Status" value={d.statusCorretor ? 'Ativo' : 'Inativo'} icon={<CheckCircle2 size={13} />} />
              <DR label="Referência" value={fmt(d.referenciaCorretor)} icon={<FileText size={13} />} />
              <DR label="Logradouro" value={[d.logradouro, d.numero, d.complemento].filter(Boolean).join(', ')} icon={<MapPinIcon size={13} />} />
              <DR label="Bairro" value={fmt(d.bairro)} icon={<MapPinIcon size={13} />} />
              <DR label="Cidade/UF" value={[d.nomeCidade ?? d.cidade, d.siglaEstado ?? d.estado].filter(Boolean).join(' - ')} icon={<MapPinIcon size={13} />} />
              <DR label="CEP" value={fmt(d.cep)} icon={<MapPinIcon size={13} />} />
              <DR label="Cadastrado em" value={fmtDate(d.cadastradoEm ?? d.dataCadastro)} icon={<Calendar size={13} />} />
              <DR label="Atualizado em" value={fmtDate(d.ultimaAtualizacao ?? d.dataAlteracao)} icon={<Calendar size={13} />} />
            </div>
          )}
        </DetailPanel>
      )}
    </div>
  );
}

function ClientesTab() {
  const [page, setPage] = useState(1);
  const [selectedId, setSelectedId] = useState<number | null>(null);
  const { data, isLoading, isFetching } = useIbClientes({ page });
  const { data: detail, isLoading: loadingDetail } = useIbCliente(selectedId);
  const rs   = data?.result_set;
  const list: any[] = rs?.data ?? [];
  const d    = detail?.result_set as any;
  const phones = d ? [d.telefone1, d.telefone2, d.telefone3].filter(Boolean) : [];

  return (
    <div className="space-y-4">
      {isLoading || isFetching ? (
        <div className="flex justify-center py-12"><Loader2 className="animate-spin text-primary" size={32} /></div>
      ) : list.length === 0 ? (
        <p className="text-center py-12 text-muted-foreground">Nenhum cliente encontrado.</p>
      ) : (
        <>
          <p className="text-xs text-muted-foreground">Clique numa linha para ver detalhes</p>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="text-left text-muted-foreground">
                  <th className="pb-2">Código</th>
                  <th className="pb-2">Nome</th>
                  <th className="pb-2">Tipo</th>
                  <th className="pb-2">Status</th>
                </tr>
              </thead>
              <tbody>
                {list.map((c: any) => {
                  const id = c.codigoCliente ?? c.codigo;
                  return (
                    <tr
                      key={id}
                      onClick={() => setSelectedId(selectedId === id ? null : id)}
                      className={`border-t border-white/10 cursor-pointer transition-colors ${selectedId === id ? 'bg-blue-500/10' : 'hover:bg-white/5'}`}
                    >
                      <td className="py-2 text-muted-foreground font-mono text-xs">{id ?? '-'}</td>
                      <td className="py-2 text-foreground">{c.nome ?? c.nomeCliente ?? c.nomeResponsavel ?? '-'}</td>
                      <td className="py-2 text-foreground">{c.tipoPessoa === 'F' ? 'Física' : c.tipoPessoa === 'J' ? 'Jurídica' : (c.tipoPessoa ?? '-')}</td>
                      <td className="py-2">
                        <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${c.statusCliente ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400'}`}>
                          {c.statusCliente ? 'Ativo' : 'Inativo'}
                        </span>
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>
          <Pagination page={page} onPrev={() => setPage((p) => Math.max(1, p - 1))} onNext={() => setPage((p) => p + 1)} hasMore={list.length >= 20} />
        </>
      )}

      {selectedId !== null && (
        <DetailPanel title="Detalhes do Cliente" onClose={() => setSelectedId(null)} loading={loadingDetail}>
          {d && (
            <div className="space-y-1">
              <div className="flex items-center gap-3 mb-4">
                <div className="w-12 h-12 rounded-full bg-purple-500/20 flex items-center justify-center shrink-0">
                  <User size={22} className="text-purple-400" />
                </div>
                <div>
                  <p className="font-semibold text-foreground">{fmt(d.nome ?? d.nomeCliente ?? d.nomeResponsavel)}</p>
                  {d.razaoSocial && <p className="text-xs text-muted-foreground">{d.razaoSocial}</p>}
                  {d.nomeFantasia && <p className="text-xs text-muted-foreground">{d.nomeFantasia}</p>}
                </div>
              </div>
              <DR label="Tipo pessoa" value={d.tipoPessoa === 'F' ? 'Física' : d.tipoPessoa === 'J' ? 'Jurídica' : fmt(d.tipoPessoa)} icon={<User size={13} />} />
              <DR label="CPF" value={fmtCpfCnpj(d.cpf)} icon={<Hash size={13} />} />
              <DR label="CNPJ" value={fmtCpfCnpj(d.cnpj)} icon={<Hash size={13} />} />
              <DR label="E-mail" value={fmt(d.email)} icon={<Mail size={13} />} />
              {phones.map((ph: any, i: number) => (
                <DR key={i} label={`Telefone ${i + 1}`} value={fmtPhone(ph)} icon={<Phone size={13} />} />
              ))}
              <DR label="Status" value={d.statusCliente ? 'Ativo' : 'Inativo'} icon={<CheckCircle2 size={13} />} />
              <DR label="Referência" value={fmt(d.referenciaCliente)} icon={<FileText size={13} />} />
              <DR label="Corretor" value={fmt(d.codigoCorretor)} icon={<UserCog size={13} />} />
              <DR label="Logradouro" value={[d.logradouro, d.numero, d.complemento].filter(Boolean).join(', ')} icon={<MapPinIcon size={13} />} />
              <DR label="Bairro" value={fmt(d.bairro)} icon={<MapPinIcon size={13} />} />
              <DR label="Cidade/UF" value={[d.nomeCidade ?? d.cidade, d.siglaEstado ?? d.estado].filter(Boolean).join(' - ')} icon={<MapPinIcon size={13} />} />
              <DR label="CEP" value={fmt(d.cep)} icon={<MapPinIcon size={13} />} />
              <DR label="Cadastrado em" value={fmtDate(d.cadastradoEm ?? d.dataCadastro)} icon={<Calendar size={13} />} />
              <DR label="Atualizado em" value={fmtDate(d.ultimaAtualizacao ?? d.dataAlteracao)} icon={<Calendar size={13} />} />
            </div>
          )}
        </DetailPanel>
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
                  <td className="py-2 text-foreground">{c.nomeCidade ?? c.cidade ?? c.nome ?? '-'}</td>
                  <td className="py-2 text-foreground">{c.siglaEstado ?? c.uf ?? c.estado ?? '-'}</td>
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
