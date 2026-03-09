import { useState, useEffect } from 'react';
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

function WaIcon() {
  return (
    <svg viewBox="0 0 24 24" className="w-3.5 h-3.5 fill-current" aria-hidden="true">
      <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
    </svg>
  );
}

function openWa(phone: any, name?: string) {
  const digits = String(phone ?? '').replace(/\D/g, '');
  if (!digits) return;
  const wa = digits.startsWith('55') ? digits : `55${digits}`;
  const text = name ? encodeURIComponent(`Olá ${name}!`) : '';
  window.open(`https://wa.me/${wa}${text ? `?text=${text}` : ''}`, '_blank');
}

function SortTh({ label, col, current, dir, onSort, className }: {
  label: string; col: string; current: string; dir: 'asc' | 'desc';
  onSort: (k: string) => void; className?: string;
}) {
  const active = current === col;
  return (
    <th
      onClick={() => onSort(col)}
      className={`px-4 py-3 text-xs font-semibold uppercase tracking-wider border-b border-white/10 cursor-pointer select-none transition-colors ${active ? 'text-blue-400' : 'text-muted-foreground hover:text-foreground'} ${className ?? ''}`}
    >
      <span className="inline-flex items-center gap-1">
        {label}
        <span className="opacity-70 text-[9px]">{active ? (dir === 'asc' ? '▲' : '▼') : '⇅'}</span>
      </span>
    </th>
  );
}

function DK({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div>
      <p className="text-[10px] uppercase tracking-wider text-muted-foreground mb-0.5">{label}</p>
      <p className="text-sm text-foreground break-all">{value || '-'}</p>
    </div>
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

function ExpandedRow({ colSpan, loading, children }: { colSpan: number; loading: boolean; children: React.ReactNode }) {
  return (
    <tr>
      <td colSpan={colSpan} className="px-0 py-0">
        <motion.div
          initial={{ opacity: 0, height: 0 }}
          animate={{ opacity: 1, height: 'auto' }}
          exit={{ opacity: 0, height: 0 }}
          transition={{ duration: 0.18 }}
          className="overflow-hidden"
        >
          <div className="px-6 py-5 bg-white/[0.03] border-t border-b border-white/10">
            {loading ? (
              <div className="flex justify-center py-4"><Loader2 className="animate-spin text-primary" size={22} /></div>
            ) : children}
          </div>
        </motion.div>
      </td>
    </tr>
  );
}

function PessoasTab() {
  const [page, setPage] = useState(1);
  const [search, setSearch] = useState('');
  const [inputVal, setInputVal] = useState('');
  const [expandedId, setExpandedId] = useState<number | null>(null);
  const [waTargetId, setWaTargetId] = useState<number | null>(null);
  const [sortKey, setSortKey] = useState('');
  const [sortDir, setSortDir] = useState<'asc' | 'desc'>('asc');
  const { data, isLoading, isFetching } = useIbPessoas({ page, nomeResponsavel: search || undefined });
  const { data: detail, isLoading: loadingDetail } = useIbPessoa(expandedId);
  const rs    = data?.result_set;
  const rawList: any[] = rs?.data ?? [];
  const total = rs?.total_items ?? rawList.length;
  const d     = detail?.result_set as any;
  const phones = d ? [d.telefone1, d.telefone2, d.telefone3].filter(Boolean) : [];

  useEffect(() => {
    if (!waTargetId || !d) return;
    const phone = d.telefone1 || d.telefone2 || d.telefone3;
    if (phone) { openWa(phone, d.nomeResponsavel); }
    setWaTargetId(null);
  }, [d, waTargetId]);

  function handleSortP(k: string) {
    if (sortKey === k) setSortDir((v) => (v === 'asc' ? 'desc' : 'asc'));
    else { setSortKey(k); setSortDir('asc'); }
  }
  const list = sortKey ? [...rawList].sort((a, b) => {
    const av = a[sortKey] ?? ''; const bv = b[sortKey] ?? '';
    const cmp = typeof av === 'number' ? av - bv
      : typeof av === 'boolean' ? (av === bv ? 0 : av ? -1 : 1)
      : String(av).localeCompare(String(bv), 'pt-BR', { numeric: true, sensitivity: 'base' });
    return sortDir === 'asc' ? cmp : -cmp;
  }) : rawList;

  const enderecos: any[] = d?.endereco && typeof d.endereco === 'object'
    ? (Array.isArray(d.endereco) ? d.endereco : [d.endereco])
    : [];
  const tipoCadastroLabels: Record<string, string> = {
    cliente: 'Cliente', corretor: 'Corretor', proprietario: 'Proprietário',
    locatario: 'Locatário', interessado: 'Interessado', outros: 'Outros',
  };
  const tiposCadastro: string[] = d?.tipoCadastro
    ? Object.entries(d.tipoCadastro).filter(([, v]) => v === true).map(([k]) => tipoCadastroLabels[k] ?? k)
    : [];
  const hasEndereco = enderecos.some((e: any) => e.logradouro || e.cidade);

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
          <p className="text-xs text-muted-foreground">{total} registros · clique numa linha para expandir</p>
          <div className="overflow-x-auto rounded-xl border border-white/10">
            <table className="w-full text-sm">
              <thead className="bg-white/[0.06]">
                <tr className="text-left">
                  <th className="px-4 py-3 border-b border-white/10 w-6"></th>
                  <SortTh label="Código" col="codigoPessoa" current={sortKey} dir={sortDir} onSort={handleSortP} />
                  <SortTh label="Nome" col="nomeResponsavel" current={sortKey} dir={sortDir} onSort={handleSortP} />
                  <SortTh label="Status" col="statusPessoa" current={sortKey} dir={sortDir} onSort={handleSortP} />
                  <SortTh label="Cadastrado" col="cadastradoEm" current={sortKey} dir={sortDir} onSort={handleSortP} />
                  <th className="px-4 py-3 border-b border-white/10 w-10"></th>
                </tr>
              </thead>
              <tbody className="divide-y divide-white/5">
                {list.map((p: any) => {
                  const id = p.codigoPessoa ?? p.codigo;
                  const open = expandedId === id;
                  return (
                    <AnimatePresence key={id} mode="wait">
                      <tr
                        onClick={() => setExpandedId(open ? null : id)}
                        className={`cursor-pointer transition-colors ${open ? 'bg-blue-500/10' : 'hover:bg-white/[0.04]'}`}
                      >
                        <td className="px-4 py-3">
                          <ChevronRight size={14} className={`text-muted-foreground transition-transform ${open ? 'rotate-90' : ''}`} />
                        </td>
                        <td className="px-4 py-3 text-muted-foreground font-mono text-xs">{id ?? '-'}</td>
                        <td className="px-4 py-3 text-foreground font-medium">{p.nomeResponsavel ?? '-'}</td>
                        <td className="px-4 py-3">
                          <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${p.statusPessoa ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400'}`}>
                            {p.statusPessoa ? 'Ativo' : 'Inativo'}
                          </span>
                        </td>
                        <td className="px-4 py-3 text-muted-foreground text-xs">{fmtDate(p.cadastradoEm)}</td>
                        <td className="px-4 py-3" onClick={(e) => e.stopPropagation()}>
                          <motion.button
                            whileHover={{ scale: 1.1 }} whileTap={{ scale: 0.9 }}
                            title="WhatsApp"
                            onClick={() => { setExpandedId(id); setWaTargetId(id); }}
                            className="p-1.5 rounded-lg bg-[#25D366]/20 text-[#25D366] hover:bg-[#25D366]/30 flex items-center justify-center"
                          >
                            {loadingDetail && expandedId === id && waTargetId === id
                              ? <Loader2 size={13} className="animate-spin" />
                              : <WaIcon />}
                          </motion.button>
                        </td>
                      </tr>
                      {open && (
                        <ExpandedRow colSpan={6} loading={loadingDetail}>
                          {d && (
                            <div className="space-y-4">
                              <div className="flex flex-wrap gap-1 mb-1">
                                {tiposCadastro.map((t) => (
                                  <span key={t} className="px-2 py-0.5 rounded-full text-xs font-medium bg-blue-500/20 text-blue-300">{t}</span>
                                ))}
                              </div>
                              <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-x-6 gap-y-4">
                                <DK label="Tipo" value={d.tipoPessoa === 'F' ? 'Pessoa Física' : d.tipoPessoa === 'J' ? 'Pessoa Jurídica' : fmt(d.tipoPessoa)} />
                                <DK label="CPF" value={fmtCpfCnpj(d.cpf)} />
                                <DK label="CNPJ" value={fmtCpfCnpj(d.cnpj)} />
                                <DK label="RG" value={fmt(d.rg)} />
                                {phones.map((ph: any, i: number) => (
                                  <DK key={i} label={`Telefone ${i + 1}`} value={fmtPhone(ph)} />
                                ))}
                                <DK label="Profissão" value={fmt(d.profissao)} />
                                <DK label="Nascimento" value={fmtDate(d.dataNascimento)} />
                                <DK label="CRECI" value={fmt(d.creci)} />
                                <DK label="Referência" value={fmt(d.referenciaPessoa)} />
                                <DK label="Origem" value={fmt(d.origemCaptacao)} />
                                <DK label="Cadastrado em" value={fmtDate(d.cadastradoEm)} />
                                <DK label="Atualizado em" value={fmtDate(d.ultimaAtualizacao)} />
                              </div>
                              {hasEndereco && enderecos.map((e: any, i: number) => (
                                <div key={i} className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-x-6 gap-y-4 pt-3 border-t border-white/10">
                                  <DK label="Logradouro" value={[e.logradouro, e.numero, e.complemento].filter(Boolean).join(', ')} />
                                  <DK label="Bairro" value={fmt(e.bairro)} />
                                  <DK label="Cidade/UF" value={[e.cidade, e.estado].filter(Boolean).join(' - ')} />
                                  <DK label="CEP" value={fmt(e.cep)} />
                                </div>
                              ))}
                            </div>
                          )}
                        </ExpandedRow>
                      )}
                    </AnimatePresence>
                  );
                })}
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
  const [expandedId, setExpandedId] = useState<number | null>(null);
  const { data, isLoading, isFetching } = useIbNegocios({ page });
  const { data: detail, isLoading: loadingDetail } = useIbNegocio(expandedId);
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
          <p className="text-xs text-muted-foreground">{list.length} registros · clique numa linha para expandir</p>
          <div className="overflow-x-auto rounded-xl border border-white/10">
            <table className="w-full text-sm">
              <thead className="bg-white/[0.06]">
                <tr className="text-left">
                  <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground border-b border-white/10 w-6"></th>
                  <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground border-b border-white/10">Código</th>
                  <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground border-b border-white/10">Título</th>
                  <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground border-b border-white/10">Status</th>
                  <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground border-b border-white/10">Criado em</th>
                  <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground border-b border-white/10">Fechado em</th>
                  <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground border-b border-white/10 text-right">Ações</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-white/5">
                {list.map((n: any) => {
                  const id = n.codigoNegocio ?? n.codigo;
                  const st = (n.statusNegocio ?? '').toLowerCase();
                  const open = expandedId === id;
                  return (
                    <AnimatePresence key={id} mode="wait">
                      <tr
                        onClick={(e) => { if ((e.target as HTMLElement).closest('button')) return; setExpandedId(open ? null : id); }}
                        className={`cursor-pointer transition-colors ${open ? 'bg-blue-500/10' : 'hover:bg-white/[0.04]'}`}
                      >
                        <td className="px-4 py-3">
                          <ChevronRight size={14} className={`text-muted-foreground transition-transform ${open ? 'rotate-90' : ''}`} />
                        </td>
                        <td className="px-4 py-3 text-muted-foreground font-mono text-xs">{id ?? '-'}</td>
                        <td className="px-4 py-3 text-foreground font-medium max-w-[240px] truncate">{n.tituloNegocio ?? '-'}</td>
                        <td className="px-4 py-3">
                          <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium capitalize ${st === 'ganho' ? 'bg-emerald-500/20 text-emerald-400' : st === 'perdido' ? 'bg-red-500/20 text-red-400' : 'bg-blue-500/20 text-blue-400'}`}>
                            {n.statusNegocio ?? '-'}
                          </span>
                        </td>
                        <td className="px-4 py-3 text-muted-foreground text-xs">{fmtDate(n.dataCriacao)}</td>
                        <td className="px-4 py-3 text-muted-foreground text-xs">{fmtDate(n.dataFechamento)}</td>
                        <td className="px-4 py-3 text-right">
                          <motion.button
                            whileHover={{ scale: 1.1 }} whileTap={{ scale: 0.9 }}
                            onClick={(e) => { e.stopPropagation(); if (id && confirm('Excluir negócio ' + id + '?')) { deleteMutation.mutate(Number(id), { onSuccess: () => toast.success('Negócio excluído'), onError: () => toast.error('Erro ao excluir negócio') }); } }}
                            className="p-1.5 rounded-lg bg-red-500/20 text-red-300 hover:bg-red-500/30"
                          >
                            <Trash2 size={13} />
                          </motion.button>
                        </td>
                      </tr>
                      {open && (
                        <ExpandedRow colSpan={7} loading={loadingDetail}>
                          {d && (
                            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-x-6 gap-y-4">
                              <DK label="Etapa" value={fmt(d.tituloEtapaNegocio)} />
                              <DK label="Operação" value={fmt(d.operacaoNegocio)} />
                              <DK label="Probabilidade" value={fmt(d.probabilidadeNegocio)} />
                              <DK label="Valor ganho" value={d.valorGanhoNegocio ? `R$ ${Number(d.valorGanhoNegocio).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}` : '-'} />
                              <DK label="Motivo perdido" value={fmt(d.motivoPerdidoNegocio)} />
                              <DK label="Data prevista" value={fmtDate(d.dataPrevistaNegocio)} />
                              <DK label="Criado em" value={fmtDate(d.dataCriacao)} />
                              <DK label="Atualizado em" value={fmtDate(d.dataAlteracao)} />
                              {(d.descricaoNegocio || d.anotacaoNegocio) && (
                                <div className="col-span-full pt-3 border-t border-white/10 space-y-2">
                                  {d.descricaoNegocio && <DK label="Descrição" value={d.descricaoNegocio} />}
                                  {d.anotacaoNegocio && <DK label="Anotação" value={d.anotacaoNegocio} />}
                                </div>
                              )}
                            </div>
                          )}
                        </ExpandedRow>
                      )}
                    </AnimatePresence>
                  );
                })}
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
  const [expandedId, setExpandedId] = useState<number | null>(null);
  const { data, isLoading, isFetching } = useIbMensagens({ page });
  const { data: detail, isLoading: loadingDetail } = useIbMensagem(expandedId);
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
          <p className="text-xs text-muted-foreground">{list.length} registros · clique numa linha para expandir</p>
          <div className="overflow-x-auto rounded-xl border border-white/10">
            <table className="w-full text-sm">
              <thead className="bg-white/[0.06]">
                <tr className="text-left">
                  <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground border-b border-white/10 w-6"></th>
                  <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground border-b border-white/10">Código</th>
                  <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground border-b border-white/10">Imóvel</th>
                  <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground border-b border-white/10">Data</th>
                  <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground border-b border-white/10">Assunto</th>
                  <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground border-b border-white/10 text-right">Ações</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-white/5">
                {list.map((m: any) => {
                  const id = m.codigoMensagem ?? m.codigo;
                  const open = expandedId === id;
                  return (
                    <AnimatePresence key={id} mode="wait">
                      <tr
                        onClick={(e) => { if ((e.target as HTMLElement).closest('button')) return; setExpandedId(open ? null : id); }}
                        className={`cursor-pointer transition-colors ${open ? 'bg-blue-500/10' : 'hover:bg-white/[0.04]'}`}
                      >
                        <td className="px-4 py-3">
                          <ChevronRight size={14} className={`text-muted-foreground transition-transform ${open ? 'rotate-90' : ''}`} />
                        </td>
                        <td className="px-4 py-3 text-muted-foreground font-mono text-xs">{id ?? '-'}</td>
                        <td className="px-4 py-3 text-muted-foreground text-xs">{m.codigoImovel ? `#${m.codigoImovel}` : '-'}</td>
                        <td className="px-4 py-3 text-muted-foreground text-xs">{fmtDate(m.dataRecebimentoMensagem)}</td>
                        <td className="px-4 py-3 text-foreground">{m.assunto ?? <span className="text-muted-foreground italic text-xs">(sem assunto)</span>}</td>
                        <td className="px-4 py-3 text-right">
                          <motion.button
                            whileHover={{ scale: 1.1 }} whileTap={{ scale: 0.9 }}
                            onClick={(e) => { e.stopPropagation(); if (id && confirm('Excluir mensagem ' + id + '?')) { deleteMutation.mutate(Number(id), { onSuccess: () => toast.success('Mensagem excluída'), onError: () => toast.error('Erro ao excluir mensagem') }); } }}
                            className="p-1.5 rounded-lg bg-red-500/20 text-red-300 hover:bg-red-500/30"
                          >
                            <Trash2 size={13} />
                          </motion.button>
                        </td>
                      </tr>
                      {open && (
                        <ExpandedRow colSpan={6} loading={loadingDetail}>
                          {d && (
                            <div className="space-y-4">
                              <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-x-6 gap-y-4">
                                <DK label="Contato" value={fmt(d.nomeContato)} />
                                <DK label="E-mail" value={fmt(d.emailContato)} />
                                <DK label="Telefone" value={fmtPhone(d.telefoneContato)} />
                                <DK label="Tipo" value={fmt(d.tipoMensagem)} />
                                <DK label="Lida" value={d.lido ? 'Sim' : 'Não'} />
                                <DK label="Imóvel" value={d.codigoImovel ? `#${d.codigoImovel}` : '-'} />
                                <DK label="Data recebimento" value={fmtDate(d.dataRecebimentoMensagem)} />
                              </div>
                              {(d.mensagem || d.texto || d.corpo) && (
                                <div className="pt-3 border-t border-white/10">
                                  <p className="text-[10px] uppercase tracking-wider text-muted-foreground mb-2">Conteúdo</p>
                                  <div className="p-3 rounded-lg bg-white/5 border border-white/10 text-sm text-foreground whitespace-pre-wrap break-words">
                                    {d.mensagem ?? d.texto ?? d.corpo}
                                  </div>
                                </div>
                              )}
                            </div>
                          )}
                        </ExpandedRow>
                      )}
                    </AnimatePresence>
                  );
                })}
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
  const [expandedId, setExpandedId] = useState<number | null>(null);
  const [waTargetId, setWaTargetId] = useState<number | null>(null);
  const [sortKey, setSortKey] = useState('');
  const [sortDir, setSortDir] = useState<'asc' | 'desc'>('asc');
  const { data, isLoading } = useIbCorretores();
  const { data: detail, isLoading: loadingDetail } = useIbCorretor(expandedId);
  const rs   = data?.result_set;
  const rawList: any[] = rs?.data ?? (Array.isArray(rs) ? rs : []);
  const d    = detail?.result_set as any;
  const phones = d ? [d.telefone1, d.telefone2, d.telefone3].filter(Boolean) : [];

  useEffect(() => {
    if (!waTargetId || !d) return;
    const phone = d.telefone1 || d.telefone2 || d.telefone3;
    if (phone) { openWa(phone, d.nome); }
    setWaTargetId(null);
  }, [d, waTargetId]);

  function handleSortC(k: string) {
    if (sortKey === k) setSortDir((v) => (v === 'asc' ? 'desc' : 'asc'));
    else { setSortKey(k); setSortDir('asc'); }
  }
  const list = sortKey ? [...rawList].sort((a, b) => {
    const av = a[sortKey] ?? ''; const bv = b[sortKey] ?? '';
    const cmp = typeof av === 'number' ? av - bv
      : typeof av === 'boolean' ? (av === bv ? 0 : av ? -1 : 1)
      : String(av).localeCompare(String(bv), 'pt-BR', { numeric: true, sensitivity: 'base' });
    return sortDir === 'asc' ? cmp : -cmp;
  }) : rawList;

  return (
    <div className="space-y-4">
      {isLoading ? (
        <div className="flex justify-center py-12"><Loader2 className="animate-spin text-primary" size={32} /></div>
      ) : list.length === 0 ? (
        <p className="text-center py-12 text-muted-foreground">Nenhum corretor encontrado.</p>
      ) : (
        <>
          <p className="text-xs text-muted-foreground">{list.length} registros · clique numa linha para expandir</p>
          <div className="overflow-x-auto rounded-xl border border-white/10">
            <table className="w-full text-sm">
              <thead className="bg-white/[0.06]">
                <tr className="text-left">
                  <th className="px-4 py-3 border-b border-white/10 w-6"></th>
                  <SortTh label="Código" col="codigoCorretor" current={sortKey} dir={sortDir} onSort={handleSortC} />
                  <SortTh label="Nome" col="nome" current={sortKey} dir={sortDir} onSort={handleSortC} />
                  <SortTh label="Razão Social" col="razaoSocial" current={sortKey} dir={sortDir} onSort={handleSortC} />
                  <th className="px-4 py-3 text-xs font-semibold uppercase tracking-wider text-muted-foreground border-b border-white/10">CRECI</th>
                  <SortTh label="Tipo" col="tipoPessoa" current={sortKey} dir={sortDir} onSort={handleSortC} />
                  <SortTh label="Status" col="statusCorretor" current={sortKey} dir={sortDir} onSort={handleSortC} />
                  <th className="px-4 py-3 border-b border-white/10 w-10"></th>
                </tr>
              </thead>
              <tbody className="divide-y divide-white/5">
                {list.map((c: any) => {
                  const id = c.codigoCorretor ?? c.codigo;
                  const open = expandedId === id;
                  return (
                    <AnimatePresence key={id} mode="wait">
                      <tr
                        onClick={() => setExpandedId(open ? null : id)}
                        className={`cursor-pointer transition-colors ${open ? 'bg-blue-500/10' : 'hover:bg-white/[0.04]'}`}
                      >
                        <td className="px-4 py-3">
                          <ChevronRight size={14} className={`text-muted-foreground transition-transform ${open ? 'rotate-90' : ''}`} />
                        </td>
                        <td className="px-4 py-3 text-muted-foreground font-mono text-xs">{id ?? '-'}</td>
                        <td className="px-4 py-3 text-foreground font-medium">{c.nome ?? '-'}</td>
                        <td className="px-4 py-3 text-muted-foreground text-xs">{c.razaoSocial ?? c.nomeFantasia ?? '-'}</td>
                        <td className="px-4 py-3 text-muted-foreground text-xs">-</td>
                        <td className="px-4 py-3 text-foreground text-xs">{c.tipoPessoa === 'F' ? 'PF' : c.tipoPessoa === 'J' ? 'PJ' : (c.tipoPessoa ?? '-')}</td>
                        <td className="px-4 py-3">
                          <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${c.statusCorretor ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400'}`}>
                            {c.statusCorretor ? 'Ativo' : 'Inativo'}
                          </span>
                        </td>
                        <td className="px-4 py-3" onClick={(e) => e.stopPropagation()}>
                          <motion.button
                            whileHover={{ scale: 1.1 }} whileTap={{ scale: 0.9 }}
                            title="WhatsApp"
                            onClick={() => { setExpandedId(id); setWaTargetId(id); }}
                            className="p-1.5 rounded-lg bg-[#25D366]/20 text-[#25D366] hover:bg-[#25D366]/30 flex items-center justify-center"
                          >
                            {loadingDetail && expandedId === id && waTargetId === id
                              ? <Loader2 size={13} className="animate-spin" />
                              : <WaIcon />}
                          </motion.button>
                        </td>
                      </tr>
                      {open && (
                        <ExpandedRow colSpan={8} loading={loadingDetail}>
                          {d && (
                            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-x-6 gap-y-4">
                              <DK label="CRECI" value={fmt(d.creciCorretor ?? d.creci)} />
                              <DK label="Tipo" value={d.tipoPessoa === 'F' ? 'Pessoa Física' : d.tipoPessoa === 'J' ? 'Pessoa Jurídica' : fmt(d.tipoPessoa)} />
                              <DK label="CPF" value={fmtCpfCnpj(d.cpf)} />
                              <DK label="CNPJ" value={fmtCpfCnpj(d.cnpj)} />
                              <DK label="E-mail" value={fmt(d.email)} />
                              {phones.map((ph: any, i: number) => (
                                <DK key={i} label={`Telefone ${i + 1}`} value={fmtPhone(ph)} />
                              ))}
                              <DK label="Referência" value={fmt(d.referenciaCorretor)} />
                              <DK label="Logradouro" value={[d.logradouro, d.numeroResidencia ? `nº ${d.numeroResidencia}` : null].filter(Boolean).join(', ')} />
                              <DK label="Bairro" value={fmt(d.bairro)} />
                              <DK label="Cidade/UF" value={[d.cidade, d.estado].filter(Boolean).join(' - ')} />
                              <DK label="CEP" value={fmt(d.cep)} />
                              <DK label="Cadastrado em" value={fmtDate(d.cadastradoEm)} />
                              <DK label="Atualizado em" value={fmtDate(d.ultimaAtualizacao)} />
                            </div>
                          )}
                        </ExpandedRow>
                      )}
                    </AnimatePresence>
                  );
                })}
              </tbody>
            </table>
          </div>
        </>
      )}
    </div>
  );
}

function ClientesTab() {
  const [page, setPage] = useState(1);
  const [expandedId, setExpandedId] = useState<number | null>(null);
  const [waTargetId, setWaTargetId] = useState<number | null>(null);
  const [sortKey, setSortKey] = useState('');
  const [sortDir, setSortDir] = useState<'asc' | 'desc'>('asc');
  const { data, isLoading, isFetching } = useIbClientes({ page });
  const { data: detail, isLoading: loadingDetail } = useIbCliente(expandedId);
  const rs   = data?.result_set;
  const rawList: any[] = rs?.data ?? [];
  const d    = detail?.result_set as any;
  const phones = d ? [d.telefone1, d.telefone2, d.telefone3].filter(Boolean) : [];

  useEffect(() => {
    if (!waTargetId || !d) return;
    const phone = d.telefone1 || d.telefone2 || d.telefone3;
    if (phone) { openWa(phone, d.nome ?? d.nomeResponsavel); }
    setWaTargetId(null);
  }, [d, waTargetId]);

  function handleSortCl(k: string) {
    if (sortKey === k) setSortDir((v) => (v === 'asc' ? 'desc' : 'asc'));
    else { setSortKey(k); setSortDir('asc'); }
  }
  const list = sortKey ? [...rawList].sort((a, b) => {
    const av = a[sortKey] ?? ''; const bv = b[sortKey] ?? '';
    const cmp = typeof av === 'number' ? av - bv
      : typeof av === 'boolean' ? (av === bv ? 0 : av ? -1 : 1)
      : String(av).localeCompare(String(bv), 'pt-BR', { numeric: true, sensitivity: 'base' });
    return sortDir === 'asc' ? cmp : -cmp;
  }) : rawList;

  return (
    <div className="space-y-4">
      {isLoading || isFetching ? (
        <div className="flex justify-center py-12"><Loader2 className="animate-spin text-primary" size={32} /></div>
      ) : list.length === 0 ? (
        <p className="text-center py-12 text-muted-foreground">Nenhum cliente encontrado.</p>
      ) : (
        <>
          <p className="text-xs text-muted-foreground">{list.length} registros · clique numa linha para expandir</p>
          <div className="overflow-x-auto rounded-xl border border-white/10">
            <table className="w-full text-sm">
              <thead className="bg-white/[0.06]">
                <tr className="text-left">
                  <th className="px-4 py-3 border-b border-white/10 w-6"></th>
                  <SortTh label="Código" col="codigoCliente" current={sortKey} dir={sortDir} onSort={handleSortCl} />
                  <SortTh label="Nome" col="nome" current={sortKey} dir={sortDir} onSort={handleSortCl} />
                  <SortTh label="Razão Social" col="razaoSocial" current={sortKey} dir={sortDir} onSort={handleSortCl} />
                  <SortTh label="Tipo" col="tipoPessoa" current={sortKey} dir={sortDir} onSort={handleSortCl} />
                  <SortTh label="Status" col="statusCliente" current={sortKey} dir={sortDir} onSort={handleSortCl} />
                  <th className="px-4 py-3 border-b border-white/10 w-10"></th>
                </tr>
              </thead>
              <tbody className="divide-y divide-white/5">
                {list.map((c: any) => {
                  const id = c.codigoCliente ?? c.codigo;
                  const open = expandedId === id;
                  return (
                    <AnimatePresence key={id} mode="wait">
                      <tr
                        onClick={() => setExpandedId(open ? null : id)}
                        className={`cursor-pointer transition-colors ${open ? 'bg-blue-500/10' : 'hover:bg-white/[0.04]'}`}
                      >
                        <td className="px-4 py-3">
                          <ChevronRight size={14} className={`text-muted-foreground transition-transform ${open ? 'rotate-90' : ''}`} />
                        </td>
                        <td className="px-4 py-3 text-muted-foreground font-mono text-xs">{id ?? '-'}</td>
                        <td className="px-4 py-3 text-foreground font-medium">{c.nome ?? '-'}</td>
                        <td className="px-4 py-3 text-muted-foreground text-xs">{c.razaoSocial ?? c.nomeFantasia ?? '-'}</td>
                        <td className="px-4 py-3 text-foreground text-xs">{c.tipoPessoa === 'F' ? 'PF' : c.tipoPessoa === 'J' ? 'PJ' : (c.tipoPessoa ?? '-')}</td>
                        <td className="px-4 py-3">
                          <span className={`inline-flex px-2 py-0.5 rounded-full text-xs font-medium ${c.statusCliente ? 'bg-emerald-500/20 text-emerald-400' : 'bg-red-500/20 text-red-400'}`}>
                            {c.statusCliente ? 'Ativo' : 'Inativo'}
                          </span>
                        </td>
                        <td className="px-4 py-3" onClick={(e) => e.stopPropagation()}>
                          <motion.button
                            whileHover={{ scale: 1.1 }} whileTap={{ scale: 0.9 }}
                            title="WhatsApp"
                            onClick={() => { setExpandedId(id); setWaTargetId(id); }}
                            className="p-1.5 rounded-lg bg-[#25D366]/20 text-[#25D366] hover:bg-[#25D366]/30 flex items-center justify-center"
                          >
                            {loadingDetail && expandedId === id && waTargetId === id
                              ? <Loader2 size={13} className="animate-spin" />
                              : <WaIcon />}
                          </motion.button>
                        </td>
                      </tr>
                      {open && (
                        <ExpandedRow colSpan={7} loading={loadingDetail}>
                          {d && (
                            <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-x-6 gap-y-4">
                              <DK label="Tipo" value={d.tipoPessoa === 'F' ? 'Pessoa Física' : d.tipoPessoa === 'J' ? 'Pessoa Jurídica' : fmt(d.tipoPessoa)} />
                              <DK label="CPF" value={fmtCpfCnpj(d.cpf)} />
                              <DK label="CNPJ" value={fmtCpfCnpj(d.cnpj)} />
                              <DK label="E-mail" value={fmt(d.email)} />
                              {phones.map((ph: any, i: number) => (
                                <DK key={i} label={`Telefone ${i + 1}`} value={fmtPhone(ph)} />
                              ))}
                              <DK label="Referência" value={fmt(d.referenciaCliente)} />
                              <DK label="Corretor" value={fmt(d.codigoCorretor)} />
                              <DK label="Logradouro" value={[d.logradouro, d.numeroResidencia ? `nº ${d.numeroResidencia}` : null].filter(Boolean).join(', ')} />
                              <DK label="Bairro" value={fmt(d.bairro)} />
                              <DK label="Cidade/UF" value={[d.cidade, d.estado].filter(Boolean).join(' - ')} />
                              <DK label="CEP" value={fmt(d.cep)} />
                              <DK label="Cadastrado em" value={fmtDate(d.cadastradoEm)} />
                              <DK label="Atualizado em" value={fmtDate(d.ultimaAtualizacao)} />
                            </div>
                          )}
                        </ExpandedRow>
                      )}
                    </AnimatePresence>
                  );
                })}
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
