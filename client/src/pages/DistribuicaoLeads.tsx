import { useEffect, useMemo, useState } from 'react';
import { useInfiniteQuery, useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Link } from 'wouter';
import { AnimatePresence, motion } from 'framer-motion';
import { toast } from 'sonner';
import {
  ArrowRightLeft,
  BellRing,
  Check,
  CheckCircle2,
  ChevronDown,
  Clock,
  ExternalLink,
  Home,
  Inbox,
  Loader2,
  Mail,
  MessageCircle,
  MessageSquareText,
  Phone,
  RefreshCw,
  Search,
  Sparkles,
  Timer,
  Undo2,
  UserCheck,
  Users,
  X,
} from 'lucide-react';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Skeleton } from '@/components/ui/skeleton';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';

/* ------------------------------------------------------------------ tipos */

type Aba = 'pendentes' | 'distribuidos' | 'todos';
type OrigemKey = 'chaves' | 'mascote' | 'portal' | 'outros';

interface Corretor {
  id: number;
  nome: string;
  papel: 'corretor' | 'admin';
  carga: number;
  recebidos_hoje: number;
}

interface LeadItem {
  id: number;
  nome: string;
  telefone: string | null;
  email: string | null;
  status: string | null;
  classificacao: string | null;
  origem: { key: OrigemKey; label: string };
  interesse: string | null;
  mensagem: string | null;
  orcamento_max: number | null;
  criado_em: string | null;
  espera_min: number | null;
  pendente: boolean;
  corretor: { id: number; nome: string } | null;
  distribuido_em: string | null;
  conversa_id: number | null;
}

interface LeadDetail extends LeadItem {
  historico: { tipo: string; descricao: string; em: string }[];
  budget_min: number | null;
  localizacao: string | null;
  quartos: number | null;
  observacoes: string | null;
}

interface Resumo {
  pendentes: number;
  mais_antigo_min: number | null;
  pendentes_por_origem: Record<OrigemKey, number>;
  distribuidos_hoje: number;
  tempo_medio_min: number | null;
  corretores: Corretor[];
}

interface ListaPage {
  data: LeadItem[];
  counts: { pendentes: number; distribuidos: number; todos: number };
  meta: { page: number; last_page: number; total: number };
}

interface PlanoLinha {
  corretor_id: number;
  nome: string;
  carga_atual: number;
  receberia: number;
  carga_final: number;
}

/* ------------------------------------------------------------------ utilidades */

const ORIGEM_STYLE: Record<OrigemKey, string> = {
  chaves: 'bg-amber-500/12 text-amber-700 border-amber-500/25 dark:text-amber-300',
  mascote: 'bg-violet-500/12 text-violet-700 border-violet-500/25 dark:text-violet-300',
  portal: 'bg-sky-500/12 text-sky-700 border-sky-500/25 dark:text-sky-300',
  outros: 'bg-slate-500/12 text-slate-700 border-slate-500/25 dark:text-slate-300',
};

const ORIGENS: { key: '' | OrigemKey; label: string }[] = [
  { key: '', label: 'Todas as origens' },
  { key: 'chaves', label: 'Chaves na Mão' },
  { key: 'mascote', label: 'Mascote do portal' },
  { key: 'portal', label: 'Portal' },
  { key: 'outros', label: 'Outros' },
];

const PERIODOS = [
  { value: '7', label: 'Últimos 7 dias' },
  { value: '30', label: 'Últimos 30 dias' },
  { value: '90', label: 'Últimos 90 dias' },
  { value: 'todos', label: 'Todo o período' },
];

const STATUS_LABEL: Record<string, string> = {
  novo: 'Novo',
  em_atendimento: 'Em atendimento',
  qualificado: 'Qualificado',
  proposta: 'Proposta',
  fechado: 'Fechado',
  perdido: 'Perdido',
};

function formatDuration(minutes: number | null | undefined): string {
  if (minutes === null || minutes === undefined) return '—';
  if (minutes < 1) return 'agora';
  if (minutes < 60) return `${Math.round(minutes)} min`;
  const hours = minutes / 60;
  if (hours < 24) return `${Math.floor(hours)} h`;
  const days = hours / 24;
  return `${Math.floor(days)} d`;
}

function initials(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) return '?';
  if (parts.length === 1) return parts[0].slice(0, 2).toUpperCase();
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
}

function waitTone(minutes: number | null): { stripe: string; chip: string } {
  if (minutes === null) return { stripe: 'bg-border', chip: 'bg-muted text-muted-foreground' };
  if (minutes < 60) return { stripe: 'bg-emerald-500', chip: 'bg-emerald-500/12 text-emerald-700 dark:text-emerald-300' };
  if (minutes < 24 * 60) return { stripe: 'bg-amber-500', chip: 'bg-amber-500/12 text-amber-700 dark:text-amber-300' };
  return { stripe: 'bg-rose-500', chip: 'bg-rose-500/12 text-rose-700 dark:text-rose-300' };
}

function whatsappLink(phone: string | null): string | null {
  if (!phone) return null;
  const digits = phone.replace(/\D/g, '');
  if (digits.length < 10) return null;
  return `https://wa.me/${digits.length <= 11 ? `55${digits}` : digits}`;
}

function formatDateTime(iso: string | null): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' });
}

function apiError(error: any, fallback: string): string {
  return error?.response?.data?.message || fallback;
}

/* ------------------------------------------------------------------ seletor de corretor */

interface AssignMenuProps {
  corretores: Corretor[];
  currentId: number | null;
  disabled?: boolean;
  onPick: (corretorId: number | null) => void;
  children: React.ReactNode;
  align?: 'start' | 'center' | 'end';
}

function AssignMenu({ corretores, currentId, disabled, onPick, children, align = 'end' }: AssignMenuProps) {
  const [open, setOpen] = useState(false);
  const maxCarga = Math.max(1, ...corretores.map((c) => c.carga));
  const sorted = useMemo(() => [...corretores].sort((a, b) => a.carga - b.carga || a.nome.localeCompare(b.nome)), [corretores]);

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>{children}</PopoverTrigger>
      <PopoverContent align={align} className="w-80 p-0">
        <Command>
          <CommandInput placeholder="Buscar corretor…" />
          <CommandList>
            <CommandEmpty>Nenhum corretor encontrado.</CommandEmpty>
            <CommandGroup heading="Menor carga primeiro">
              {sorted.map((c) => (
                <CommandItem
                  key={c.id}
                  value={c.nome}
                  onSelect={() => {
                    setOpen(false);
                    onPick(c.id);
                  }}
                  className="flex items-center gap-3 py-2"
                >
                  <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-semibold text-primary">
                    {initials(c.nome)}
                  </span>
                  <span className="min-w-0 flex-1">
                    <span className="flex items-center gap-2">
                      <span className="truncate text-sm font-medium">{c.nome}</span>
                      {c.papel === 'admin' && <span className="rounded bg-muted px-1.5 text-[10px] uppercase tracking-wide text-muted-foreground">admin</span>}
                    </span>
                    <span className="mt-1 flex items-center gap-2">
                      <span className="h-1.5 w-24 overflow-hidden rounded-full bg-muted">
                        <span className="block h-full rounded-full bg-primary/70" style={{ width: `${(c.carga / maxCarga) * 100}%` }} />
                      </span>
                      <span className="text-[11px] text-muted-foreground">{c.carga} ativos</span>
                    </span>
                  </span>
                  {currentId === c.id && <Check className="h-4 w-4 text-primary" />}
                </CommandItem>
              ))}
            </CommandGroup>
            {currentId !== null && (
              <CommandGroup>
                <CommandItem
                  value="__devolver__"
                  onSelect={() => {
                    setOpen(false);
                    onPick(null);
                  }}
                  className="gap-2 text-rose-600 dark:text-rose-400"
                >
                  <Undo2 className="h-4 w-4" /> Devolver à fila
                </CommandItem>
              </CommandGroup>
            )}
          </CommandList>
        </Command>
      </PopoverContent>
    </Popover>
  );
}

/* ------------------------------------------------------------------ cartões de indicadores */

function KpiCard({ icon, label, value, hint, tone }: { icon: React.ReactNode; label: string; value: React.ReactNode; hint?: string; tone?: 'alert' | 'ok' | 'neutral' }) {
  const toneClass =
    tone === 'alert'
      ? 'border-rose-500/30 bg-rose-500/[0.06]'
      : tone === 'ok'
        ? 'border-emerald-500/30 bg-emerald-500/[0.06]'
        : 'border-border bg-card';
  return (
    <div className={cn('rounded-2xl border p-5 shadow-sm', toneClass)}>
      <div className="flex items-center justify-between">
        <p className="text-sm font-medium text-muted-foreground">{label}</p>
        <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-background/70 text-primary">{icon}</span>
      </div>
      <p className="mt-3 text-3xl font-bold tracking-tight text-foreground">{value}</p>
      {hint && <p className="mt-1 text-xs text-muted-foreground">{hint}</p>}
    </div>
  );
}

/* ------------------------------------------------------------------ linha de lead */

interface LeadRowProps {
  lead: LeadItem;
  corretores: Corretor[];
  selected: boolean;
  busy: boolean;
  onToggle: (id: number) => void;
  onOpen: (id: number) => void;
  onAssign: (lead: LeadItem, corretorId: number | null) => void;
}

function LeadRow({ lead, corretores, selected, busy, onToggle, onOpen, onAssign }: LeadRowProps) {
  const tone = waitTone(lead.pendente ? lead.espera_min : null);
  const wa = whatsappLink(lead.telefone);

  return (
    <motion.div
      layout
      initial={{ opacity: 0, y: 8 }}
      animate={{ opacity: 1, y: 0 }}
      exit={{ opacity: 0, x: 24, height: 0, marginBottom: 0 }}
      transition={{ duration: 0.2 }}
      className={cn(
        'group relative flex gap-3 overflow-hidden rounded-2xl border bg-card p-4 pl-5 shadow-sm transition-colors',
        selected ? 'border-primary/50 bg-primary/[0.04]' : 'border-border hover:border-primary/30'
      )}
    >
      <span className={cn('absolute inset-y-0 left-0 w-1', tone.stripe)} aria-hidden />

      <div className="pt-1">
        <Checkbox checked={selected} onCheckedChange={() => onToggle(lead.id)} aria-label={`Selecionar ${lead.nome}`} />
      </div>

      <button type="button" onClick={() => onOpen(lead.id)} className="flex min-w-0 flex-1 flex-col gap-2 text-left">
        <div className="flex flex-wrap items-center gap-2">
          <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">{initials(lead.nome)}</span>
          <span className="truncate text-base font-semibold text-foreground">{lead.nome}</span>
          <span className={cn('rounded-full border px-2 py-0.5 text-[11px] font-medium', ORIGEM_STYLE[lead.origem.key])}>{lead.origem.label}</span>
          {lead.status && lead.status !== 'novo' && (
            <span className="rounded-full bg-muted px-2 py-0.5 text-[11px] font-medium text-muted-foreground">{STATUS_LABEL[lead.status] ?? lead.status}</span>
          )}
          {lead.pendente ? (
            <span className={cn('inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-semibold', tone.chip)}>
              <Clock className="h-3 w-3" /> aguardando há {formatDuration(lead.espera_min)}
            </span>
          ) : lead.corretor ? (
            <span className="inline-flex items-center gap-1 rounded-full bg-emerald-500/12 px-2 py-0.5 text-[11px] font-semibold text-emerald-700 dark:text-emerald-300">
              <UserCheck className="h-3 w-3" /> com {lead.corretor.nome}
            </span>
          ) : null}
        </div>

        {(lead.interesse || lead.mensagem) && (
          <div className="space-y-1 text-sm">
            {lead.interesse && (
              <p className="flex items-start gap-1.5 text-foreground/90">
                <Home className="mt-0.5 h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                <span className="line-clamp-1">{lead.interesse}</span>
              </p>
            )}
            {lead.mensagem && (
              <p className="flex items-start gap-1.5 text-muted-foreground">
                <MessageSquareText className="mt-0.5 h-3.5 w-3.5 shrink-0" />
                <span className="line-clamp-2 italic">“{lead.mensagem}”</span>
              </p>
            )}
          </div>
        )}

        <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
          {lead.telefone && (
            <span className="inline-flex items-center gap-1">
              <Phone className="h-3 w-3" /> {lead.telefone}
            </span>
          )}
          {lead.email && (
            <span className="inline-flex items-center gap-1">
              <Mail className="h-3 w-3" /> {lead.email}
            </span>
          )}
          <span>Entrou em {formatDateTime(lead.criado_em)}</span>
        </div>
      </button>

      <div className="flex shrink-0 flex-col items-end justify-between gap-2">
        <div className="flex items-center gap-1.5">
          {wa && (
            <Button asChild variant="outline" size="icon" className="h-9 w-9" title="Abrir WhatsApp do cliente">
              <a href={wa} target="_blank" rel="noreferrer" aria-label="Abrir WhatsApp do cliente">
                <MessageCircle className="h-4 w-4 text-emerald-600" />
              </a>
            </Button>
          )}
        </div>

        <AssignMenu corretores={corretores} currentId={lead.corretor?.id ?? null} disabled={busy} onPick={(id) => onAssign(lead, id)}>
          <Button size="sm" variant={lead.pendente ? 'default' : 'outline'} className="gap-1.5" disabled={busy}>
            {busy ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : lead.pendente ? <UserCheck className="h-3.5 w-3.5" /> : <ArrowRightLeft className="h-3.5 w-3.5" />}
            {lead.pendente ? 'Distribuir' : 'Redistribuir'}
            <ChevronDown className="h-3.5 w-3.5 opacity-70" />
          </Button>
        </AssignMenu>
      </div>
    </motion.div>
  );
}

/* ------------------------------------------------------------------ painel de detalhe */

function LeadDetailSheet({
  leadId,
  corretores,
  busy,
  onClose,
  onAssign,
}: {
  leadId: number | null;
  corretores: Corretor[];
  busy: boolean;
  onClose: () => void;
  onAssign: (lead: LeadItem, corretorId: number | null) => void;
}) {
  const { data, isLoading } = useQuery({
    queryKey: ['dist-detalhe', leadId],
    enabled: leadId !== null,
    queryFn: async () => (await api.get(`/admin/distribuicao/lead/${leadId}`)).data.data as LeadDetail,
  });

  const wa = whatsappLink(data?.telefone ?? null);

  return (
    <Sheet open={leadId !== null} onOpenChange={(open) => !open && onClose()}>
      <SheetContent side="right" className="w-full overflow-y-auto sm:max-w-lg">
        <SheetHeader className="space-y-1 text-left">
          <SheetTitle className="text-xl">{data?.nome ?? 'Carregando…'}</SheetTitle>
          <SheetDescription>{data ? `${data.origem.label} · entrou em ${formatDateTime(data.criado_em)}` : ' '}</SheetDescription>
        </SheetHeader>

        {isLoading || !data ? (
          <div className="space-y-3 p-4">
            <Skeleton className="h-20 w-full" />
            <Skeleton className="h-32 w-full" />
          </div>
        ) : (
          <div className="space-y-5 px-4 pb-8">
            <div className="rounded-xl border bg-muted/40 p-3 text-sm">
              {data.pendente ? (
                <p className="flex items-center gap-2 font-medium text-amber-700 dark:text-amber-300">
                  <Clock className="h-4 w-4" /> Aguardando distribuição há {formatDuration(data.espera_min)}
                </p>
              ) : (
                <p className="flex items-center gap-2 font-medium text-emerald-700 dark:text-emerald-300">
                  <UserCheck className="h-4 w-4" /> Com {data.corretor?.nome}
                </p>
              )}
              <div className="mt-3">
                <AssignMenu corretores={corretores} currentId={data.corretor?.id ?? null} disabled={busy} onPick={(id) => onAssign(data, id)} align="start">
                  <Button size="sm" className="gap-1.5" disabled={busy}>
                    {data.pendente ? <UserCheck className="h-4 w-4" /> : <ArrowRightLeft className="h-4 w-4" />}
                    {data.pendente ? 'Distribuir para…' : 'Redistribuir para…'}
                    <ChevronDown className="h-4 w-4 opacity-70" />
                  </Button>
                </AssignMenu>
              </div>
            </div>

            <section className="space-y-2">
              <h3 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Contato</h3>
              <div className="space-y-1.5 text-sm">
                {data.telefone && (
                  <p className="flex items-center gap-2">
                    <Phone className="h-4 w-4 text-muted-foreground" /> {data.telefone}
                  </p>
                )}
                {data.email && (
                  <p className="flex items-center gap-2">
                    <Mail className="h-4 w-4 text-muted-foreground" /> {data.email}
                  </p>
                )}
              </div>
              <div className="flex flex-wrap gap-2 pt-1">
                {wa && (
                  <Button asChild size="sm" variant="outline" className="gap-1.5">
                    <a href={wa} target="_blank" rel="noreferrer">
                      <MessageCircle className="h-4 w-4 text-emerald-600" /> WhatsApp
                    </a>
                  </Button>
                )}
                <Button asChild size="sm" variant="outline" className="gap-1.5">
                  <Link to={`/leads/${data.id}`}>
                    <ExternalLink className="h-4 w-4" /> Abrir ficha do lead
                  </Link>
                </Button>
                {data.conversa_id && (
                  <Button asChild size="sm" variant="outline" className="gap-1.5">
                    <Link to={`/chat?conversationId=${data.conversa_id}`}>
                      <MessageSquareText className="h-4 w-4" /> Abrir conversa
                    </Link>
                  </Button>
                )}
              </div>
            </section>

            {(data.interesse || data.mensagem || data.localizacao || data.orcamento_max) && (
              <section className="space-y-2">
                <h3 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Interesse</h3>
                {data.interesse && <p className="text-sm font-medium">{data.interesse}</p>}
                {data.mensagem && <p className="rounded-lg bg-muted/50 p-3 text-sm italic text-muted-foreground">“{data.mensagem}”</p>}
                <dl className="grid grid-cols-2 gap-2 text-sm">
                  {data.localizacao && (
                    <div>
                      <dt className="text-xs text-muted-foreground">Localização</dt>
                      <dd>{data.localizacao}</dd>
                    </div>
                  )}
                  {data.orcamento_max ? (
                    <div>
                      <dt className="text-xs text-muted-foreground">Orçamento até</dt>
                      <dd>{Number(data.orcamento_max).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 })}</dd>
                    </div>
                  ) : null}
                  {data.quartos ? (
                    <div>
                      <dt className="text-xs text-muted-foreground">Quartos</dt>
                      <dd>{data.quartos}</dd>
                    </div>
                  ) : null}
                </dl>
              </section>
            )}

            <section className="space-y-2">
              <h3 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Histórico de distribuição</h3>
              {data.historico.length === 0 ? (
                <p className="text-sm text-muted-foreground">Ainda não foi distribuído.</p>
              ) : (
                <ol className="relative space-y-3 border-l border-border pl-4">
                  {data.historico.map((h, index) => (
                    <li key={`${h.em}-${index}`} className="relative text-sm">
                      <span className="absolute -left-[21px] top-1.5 h-2.5 w-2.5 rounded-full bg-primary" />
                      <p>{h.descricao}</p>
                      <p className="text-xs text-muted-foreground">{formatDateTime(h.em)}</p>
                    </li>
                  ))}
                </ol>
              )}
            </section>
          </div>
        )}
      </SheetContent>
    </Sheet>
  );
}

/* ------------------------------------------------------------------ distribuição automática */

function AutoDistributeDialog({
  open,
  onOpenChange,
  corretores,
  baseParams,
  selectedIds,
  onDone,
}: {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  corretores: Corretor[];
  baseParams: Record<string, string | undefined>;
  selectedIds: number[];
  onDone: () => void;
}) {
  const brokers = useMemo(() => corretores.filter((c) => c.papel === 'corretor'), [corretores]);
  const [included, setIncluded] = useState<number[]>([]);

  useEffect(() => {
    if (open) setIncluded(brokers.map((b) => b.id));
  }, [open, brokers]);

  const body = useMemo(
    () => ({
      corretor_ids: included,
      ...(selectedIds.length > 0 ? { lead_ids: selectedIds } : {}),
    }),
    [included, selectedIds]
  );

  const preview = useQuery({
    queryKey: ['dist-auto-preview', body, baseParams],
    enabled: open && included.length > 0,
    retry: false,
    queryFn: async () => (await api.post('/admin/distribuicao/distribuir-automatico', { ...body, preview: true }, { params: baseParams })).data as { total: number; plano: PlanoLinha[] },
  });

  const apply = useMutation({
    mutationFn: async () => (await api.post('/admin/distribuicao/distribuir-automatico', { ...body, preview: false }, { params: baseParams })).data as { distribuidos: number; erros: number },
    onSuccess: (data) => {
      toast.success(`${data.distribuidos} lead(s) distribuído(s) automaticamente`);
      onDone();
      onOpenChange(false);
    },
    onError: (error) => toast.error(apiError(error, 'Não foi possível distribuir automaticamente')),
  });

  const toggle = (id: number) => setIncluded((prev) => (prev.includes(id) ? prev.filter((x) => x !== id) : [...prev, id]));
  const planError = (preview.error as any)?.response?.data?.message as string | undefined;
  const maxFinal = Math.max(1, ...(preview.data?.plano ?? []).map((p) => p.carga_final));

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-xl">
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Sparkles className="h-5 w-5 text-primary" /> Distribuição automática
          </DialogTitle>
          <DialogDescription>
            Reparte {selectedIds.length > 0 ? `os ${selectedIds.length} leads selecionados` : 'toda a fila do período filtrado'} entre os corretores, sempre dando o próximo lead a quem tem menos
            leads ativos.
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4">
          <div>
            <p className="mb-2 text-sm font-medium">Quem recebe</p>
            <div className="flex flex-wrap gap-2">
              {brokers.map((b) => {
                const on = included.includes(b.id);
                return (
                  <button
                    key={b.id}
                    type="button"
                    onClick={() => toggle(b.id)}
                    className={cn(
                      'inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-sm transition-colors',
                      on ? 'border-primary bg-primary/10 text-primary' : 'border-border text-muted-foreground hover:border-primary/40'
                    )}
                  >
                    {on && <Check className="h-3.5 w-3.5" />} {b.nome}
                  </button>
                );
              })}
            </div>
          </div>

          <div className="rounded-xl border bg-muted/30 p-3">
            <p className="mb-2 text-sm font-medium">Prévia {preview.data ? `· ${preview.data.total} lead(s)` : ''}</p>
            {preview.isLoading || preview.isFetching ? (
              <div className="space-y-2">
                <Skeleton className="h-8 w-full" />
                <Skeleton className="h-8 w-full" />
              </div>
            ) : planError ? (
              <p className="text-sm text-muted-foreground">{planError}</p>
            ) : (
              <ul className="space-y-2">
                {(preview.data?.plano ?? []).map((p) => (
                  <li key={p.corretor_id} className="text-sm">
                    <div className="flex items-center justify-between">
                      <span className="font-medium">{p.nome}</span>
                      <span className="text-muted-foreground">
                        {p.carga_atual} → <strong className="text-foreground">{p.carga_final}</strong>
                        {p.receberia > 0 && <span className="ml-1 text-emerald-600 dark:text-emerald-400">(+{p.receberia})</span>}
                      </span>
                    </div>
                    <div className="mt-1 h-1.5 overflow-hidden rounded-full bg-muted">
                      <div className="h-full rounded-full bg-primary/70" style={{ width: `${(p.carga_final / maxFinal) * 100}%` }} />
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancelar
          </Button>
          <Button onClick={() => apply.mutate()} disabled={apply.isPending || !preview.data || (preview.data?.total ?? 0) === 0 || included.length === 0} className="gap-2">
            {apply.isPending && <Loader2 className="h-4 w-4 animate-spin" />}
            Distribuir {preview.data?.total ?? ''} lead(s)
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}

/* ------------------------------------------------------------------ página */

export default function DistribuicaoLeads() {
  const queryClient = useQueryClient();

  const [aba, setAba] = useState<Aba>('pendentes');
  const [periodo, setPeriodo] = useState('30');
  const [origem, setOrigem] = useState<'' | OrigemKey>('');
  const [busca, setBusca] = useState('');
  const [buscaDebounced, setBuscaDebounced] = useState('');
  const [corretorFiltro, setCorretorFiltro] = useState<number | null>(null);
  const [selected, setSelected] = useState<Set<number>>(new Set());
  const [busyIds, setBusyIds] = useState<Set<number>>(new Set());
  const [detailId, setDetailId] = useState<number | null>(null);
  const [autoOpen, setAutoOpen] = useState(false);

  // Abre o detalhe quando chega pelo link do aviso (/distribuicao?leadId=123)
  useEffect(() => {
    const param = new URLSearchParams(window.location.search).get('leadId');
    if (param && Number(param) > 0) {
      setDetailId(Number(param));
      setAba('todos');
      setPeriodo('todos');
    }
  }, []);

  useEffect(() => {
    const timer = setTimeout(() => setBuscaDebounced(busca.trim()), 350);
    return () => clearTimeout(timer);
  }, [busca]);

  const baseParams = useMemo(
    () => ({ periodo, origem: origem || undefined, q: buscaDebounced || undefined }),
    [periodo, origem, buscaDebounced]
  );

  const resumo = useQuery({
    queryKey: ['dist-resumo', periodo],
    queryFn: async () => (await api.get('/admin/distribuicao/resumo', { params: { periodo } })).data.data as Resumo,
    refetchInterval: 30000,
  });

  const lista = useInfiniteQuery({
    queryKey: ['dist-lista', aba, baseParams, corretorFiltro],
    initialPageParam: 1,
    queryFn: async ({ pageParam }) =>
      (await api.get('/admin/distribuicao', { params: { aba, ...baseParams, corretor_id: corretorFiltro ?? undefined, page: pageParam, per_page: 30 } })).data as ListaPage,
    getNextPageParam: (last) => (last.meta.page < last.meta.last_page ? last.meta.page + 1 : undefined),
    refetchInterval: 30000,
  });

  const items = useMemo(() => lista.data?.pages.flatMap((p) => p.data) ?? [], [lista.data]);
  const counts = lista.data?.pages[0]?.counts ?? { pendentes: 0, distribuidos: 0, todos: 0 };
  const corretores = resumo.data?.corretores ?? [];
  const forbidden = (lista.error as any)?.response?.status === 403;

  // Seleção some quando o filtro muda ou os leads saem da lista
  useEffect(() => {
    setSelected(new Set());
  }, [aba, baseParams, corretorFiltro]);

  useEffect(() => {
    setSelected((prev) => {
      const visible = new Set(items.map((i) => i.id));
      const next = new Set([...prev].filter((id) => visible.has(id)));
      return next.size === prev.size ? prev : next;
    });
  }, [items]);

  const refreshAll = () => {
    void queryClient.invalidateQueries({ queryKey: ['dist-lista'] });
    void queryClient.invalidateQueries({ queryKey: ['dist-resumo'] });
    void queryClient.invalidateQueries({ queryKey: ['dist-detalhe'] });
  };

  const assign = useMutation({
    mutationFn: async (vars: { ids: number[]; corretorId: number | null; corretorNome: string | null }) =>
      (await api.post('/admin/distribuicao/atribuir', { lead_ids: vars.ids, corretor_id: vars.corretorId })).data as { mensagem: string; resumo: Record<string, number> },
    onMutate: (vars) => setBusyIds((prev) => new Set([...prev, ...vars.ids])),
    onSuccess: (data, vars) => {
      toast.success(vars.corretorNome ? `${data.mensagem} · ${vars.corretorNome}` : data.mensagem);
      setSelected(new Set());
      refreshAll();
    },
    onError: (error) => toast.error(apiError(error, 'Não foi possível atribuir o lead')),
    onSettled: (_data, _error, vars) =>
      setBusyIds((prev) => {
        const next = new Set(prev);
        vars.ids.forEach((id) => next.delete(id));
        return next;
      }),
  });

  const reprocess = useMutation({
    mutationFn: async () => (await api.post('/admin/distribuicao/reprocessar')).data as { mensagem: string; alertados: number; falhas: number; pendentes: number },
    onSuccess: (data) => {
      if (data.falhas > 0) toast.warning(data.mensagem);
      else toast.success(data.mensagem);
      refreshAll();
    },
    onError: (error) => toast.error(apiError(error, 'Não foi possível reprocessar os avisos')),
  });

  const assignLead = (lead: LeadItem, corretorId: number | null) => {
    const corretor = corretores.find((c) => c.id === corretorId);
    assign.mutate({ ids: [lead.id], corretorId, corretorNome: corretor?.nome ?? null });
  };

  const assignSelected = (corretorId: number | null) => {
    const corretor = corretores.find((c) => c.id === corretorId);
    assign.mutate({ ids: [...selected], corretorId, corretorNome: corretor?.nome ?? null });
  };

  const toggle = (id: number) =>
    setSelected((prev) => {
      const next = new Set(prev);
      next.has(id) ? next.delete(id) : next.add(id);
      return next;
    });

  const allSelected = items.length > 0 && items.every((i) => selected.has(i.id));
  const toggleAll = () => setSelected(allSelected ? new Set() : new Set(items.map((i) => i.id)));

  const closeDetail = () => {
    setDetailId(null);
    if (window.location.search.includes('leadId')) {
      window.history.replaceState({}, '', window.location.pathname);
    }
  };

  const pending = resumo.data?.pendentes ?? counts.pendentes;
  const orderedTeam = useMemo(() => [...corretores].sort((a, b) => a.carga - b.carga || a.nome.localeCompare(b.nome)), [corretores]);
  const maxCarga = Math.max(1, ...corretores.map((c) => c.carga));
  const origemTotals = resumo.data?.pendentes_por_origem;

  return (
    <div className="flex">
      <Sidebar />

      <div className="page-shell">
        <div className="mx-auto max-w-7xl space-y-6 pb-28">
          {/* Cabeçalho */}
          <div className="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
              <h1 className="text-3xl font-bold tracking-tight text-foreground sm:text-4xl">Central de Distribuição</h1>
              <p className="mt-1 text-sm text-muted-foreground sm:text-base">Distribua, redistribua e acompanhe a carga da equipe. Atualiza sozinha a cada 30 segundos.</p>
            </div>
            <div className="flex flex-wrap items-center gap-2">
              <Button variant="outline" onClick={() => reprocess.mutate()} className="gap-2" disabled={reprocess.isPending} title="Reenvia por WhatsApp/e-mail os avisos de lead que ainda não chegaram à distribuidora. Também roda sozinho quando ela faz login.">
                {reprocess.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <BellRing className="h-4 w-4" />} Reprocessar avisos
              </Button>
              <Button variant="outline" onClick={refreshAll} className="gap-2" disabled={lista.isFetching}>
                <RefreshCw className={cn('h-4 w-4', lista.isFetching && 'animate-spin')} /> Atualizar
              </Button>
              <Button onClick={() => setAutoOpen(true)} className="gap-2" disabled={pending === 0}>
                <Sparkles className="h-4 w-4" /> Distribuir automaticamente
              </Button>
            </div>
          </div>

          {/* Indicadores */}
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <KpiCard
              icon={<Inbox className="h-5 w-5" />}
              label="Aguardando distribuição"
              value={resumo.isLoading ? <Skeleton className="h-9 w-16" /> : pending}
              hint={pending > 0 ? `O mais antigo espera há ${formatDuration(resumo.data?.mais_antigo_min)}` : 'Tudo distribuído'}
              tone={pending === 0 ? 'ok' : (resumo.data?.mais_antigo_min ?? 0) > 24 * 60 ? 'alert' : 'neutral'}
            />
            <KpiCard icon={<CheckCircle2 className="h-5 w-5" />} label="Distribuídos hoje" value={resumo.isLoading ? <Skeleton className="h-9 w-16" /> : resumo.data?.distribuidos_hoje ?? 0} hint="Inclui redistribuições" />
            <KpiCard
              icon={<Timer className="h-5 w-5" />}
              label="Tempo médio até distribuir"
              value={resumo.isLoading ? <Skeleton className="h-9 w-20" /> : formatDuration(resumo.data?.tempo_medio_min)}
              hint="Média dos últimos 30 dias"
            />
            <div className="rounded-2xl border border-border bg-card p-5 shadow-sm">
              <p className="text-sm font-medium text-muted-foreground">Fila por origem</p>
              <ul className="mt-3 space-y-1.5">
                {(['chaves', 'mascote', 'portal', 'outros'] as OrigemKey[]).map((key) => {
                  const total = origemTotals?.[key] ?? 0;
                  const max = Math.max(1, ...Object.values(origemTotals ?? { x: 1 }));
                  return (
                    <li key={key} className="flex items-center gap-2 text-xs">
                      <span className="w-28 truncate text-muted-foreground">{ORIGENS.find((o) => o.key === key)?.label}</span>
                      <span className="h-1.5 flex-1 overflow-hidden rounded-full bg-muted">
                        <span className="block h-full rounded-full bg-primary/70" style={{ width: `${(total / max) * 100}%` }} />
                      </span>
                      <span className="w-6 text-right font-semibold text-foreground">{total}</span>
                    </li>
                  );
                })}
              </ul>
            </div>
          </div>

          <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
            {/* Coluna principal */}
            <div className="space-y-4">
              <div className="space-y-3 rounded-2xl border border-border bg-card p-4 shadow-sm">
                <div className="flex flex-wrap items-center gap-2">
                  {([
                    ['pendentes', 'Aguardando', counts.pendentes],
                    ['distribuidos', 'Distribuídos', counts.distribuidos],
                    ['todos', 'Todos', counts.todos],
                  ] as [Aba, string, number][]).map(([key, label, total]) => (
                    <button
                      key={key}
                      type="button"
                      onClick={() => setAba(key)}
                      className={cn(
                        'inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-medium transition-colors',
                        aba === key ? 'bg-primary text-primary-foreground shadow-sm' : 'bg-muted text-muted-foreground hover:text-foreground'
                      )}
                    >
                      {label}
                      <span className={cn('rounded-full px-1.5 text-[11px]', aba === key ? 'bg-white/20' : 'bg-background')}>{total}</span>
                    </button>
                  ))}
                </div>

                <div className="grid gap-2 sm:grid-cols-[1fr_auto_auto]">
                  <div className="relative">
                    <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <Input value={busca} onChange={(e) => setBusca(e.target.value)} placeholder="Buscar por nome, telefone, e-mail ou imóvel…" className="pl-9" />
                  </div>
                  <select
                    value={origem}
                    onChange={(e) => setOrigem(e.target.value as '' | OrigemKey)}
                    className="h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground"
                    aria-label="Filtrar por origem"
                  >
                    {ORIGENS.map((o) => (
                      <option key={o.key} value={o.key}>
                        {o.label}
                      </option>
                    ))}
                  </select>
                  <select value={periodo} onChange={(e) => setPeriodo(e.target.value)} className="h-9 rounded-md border border-input bg-background px-3 text-sm text-foreground" aria-label="Período">
                    {PERIODOS.map((p) => (
                      <option key={p.value} value={p.value}>
                        {p.label}
                      </option>
                    ))}
                  </select>
                </div>

                {corretorFiltro !== null && (
                  <div className="flex items-center gap-2 text-sm">
                    <span className="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-primary">
                      <UserCheck className="h-3.5 w-3.5" /> Corretor: {corretores.find((c) => c.id === corretorFiltro)?.nome ?? `#${corretorFiltro}`}
                      <button type="button" onClick={() => setCorretorFiltro(null)} aria-label="Remover filtro de corretor">
                        <X className="h-3.5 w-3.5" />
                      </button>
                    </span>
                  </div>
                )}

                {items.length > 0 && (
                  <label className="flex cursor-pointer items-center gap-2 pt-1 text-sm text-muted-foreground">
                    <Checkbox checked={allSelected} onCheckedChange={toggleAll} />
                    Selecionar todos os {items.length} visíveis
                  </label>
                )}
              </div>

              {/* Lista */}
              {forbidden ? (
                <div className="rounded-2xl border border-rose-500/30 bg-rose-500/[0.06] p-8 text-center text-sm">Esta área é restrita a administradores.</div>
              ) : lista.isLoading ? (
                <div className="space-y-3">
                  {[0, 1, 2, 3].map((i) => (
                    <Skeleton key={i} className="h-32 w-full rounded-2xl" />
                  ))}
                </div>
              ) : lista.isError ? (
                <div className="rounded-2xl border border-border bg-card p-8 text-center">
                  <p className="text-sm text-muted-foreground">Não foi possível carregar os leads.</p>
                  <Button variant="outline" className="mt-3" onClick={() => lista.refetch()}>
                    Tentar novamente
                  </Button>
                </div>
              ) : items.length === 0 ? (
                <div className="rounded-2xl border border-dashed border-border bg-card/50 p-12 text-center">
                  <CheckCircle2 className="mx-auto h-10 w-10 text-emerald-500" />
                  <p className="mt-3 text-lg font-semibold">{aba === 'pendentes' ? 'Nenhum lead aguardando distribuição' : 'Nenhum lead encontrado'}</p>
                  <p className="mt-1 text-sm text-muted-foreground">{aba === 'pendentes' ? 'Quando um lead novo chegar, ele aparece aqui e você é avisada.' : 'Ajuste os filtros para ver mais resultados.'}</p>
                </div>
              ) : (
                <div className="space-y-3">
                  <AnimatePresence initial={false}>
                    {items.map((lead) => (
                      <LeadRow
                        key={lead.id}
                        lead={lead}
                        corretores={corretores}
                        selected={selected.has(lead.id)}
                        busy={busyIds.has(lead.id)}
                        onToggle={toggle}
                        onOpen={setDetailId}
                        onAssign={assignLead}
                      />
                    ))}
                  </AnimatePresence>

                  {lista.hasNextPage && (
                    <div className="flex justify-center pt-2">
                      <Button variant="outline" onClick={() => lista.fetchNextPage()} disabled={lista.isFetchingNextPage} className="gap-2">
                        {lista.isFetchingNextPage && <Loader2 className="h-4 w-4 animate-spin" />}
                        Carregar mais ({(lista.data?.pages[0]?.meta.total ?? 0) - items.length} restantes)
                      </Button>
                    </div>
                  )}
                </div>
              )}
            </div>

            {/* Equipe */}
            <aside className="space-y-3 lg:sticky lg:top-6 lg:self-start">
              <div className="rounded-2xl border border-border bg-card p-4 shadow-sm">
                <div className="mb-3 flex items-center justify-between">
                  <h2 className="flex items-center gap-2 text-base font-semibold">
                    <Users className="h-4 w-4 text-primary" /> Equipe
                  </h2>
                  <span className="text-xs text-muted-foreground">leads ativos</span>
                </div>

                {resumo.isLoading ? (
                  <div className="space-y-3">
                    {[0, 1, 2, 3].map((i) => (
                      <Skeleton key={i} className="h-12 w-full" />
                    ))}
                  </div>
                ) : (
                  <ul className="space-y-1">
                    {orderedTeam.map((c) => {
                      const active = corretorFiltro === c.id;
                      return (
                        <li key={c.id}>
                          <div className={cn('flex items-center gap-3 rounded-xl p-2 transition-colors', active ? 'bg-primary/10' : 'hover:bg-muted/60')}>
                            <button
                              type="button"
                              onClick={() => {
                                setCorretorFiltro(active ? null : c.id);
                                if (!active) setAba('distribuidos');
                              }}
                              className="flex min-w-0 flex-1 items-center gap-3 text-left"
                              title="Filtrar os leads deste corretor"
                            >
                              <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">{initials(c.nome)}</span>
                              <span className="min-w-0 flex-1">
                                <span className="flex items-center gap-2">
                                  <span className="truncate text-sm font-medium">{c.nome}</span>
                                  {c.papel === 'admin' && <span className="rounded bg-muted px-1.5 text-[10px] uppercase tracking-wide text-muted-foreground">admin</span>}
                                </span>
                                <span className="mt-1 flex items-center gap-2">
                                  <span className="h-1.5 flex-1 overflow-hidden rounded-full bg-muted">
                                    <span className="block h-full rounded-full bg-primary/70" style={{ width: `${(c.carga / maxCarga) * 100}%` }} />
                                  </span>
                                  <span className="w-5 text-right text-xs font-semibold">{c.carga}</span>
                                </span>
                                {c.recebidos_hoje > 0 && <span className="text-[11px] text-emerald-600 dark:text-emerald-400">+{c.recebidos_hoje} hoje</span>}
                              </span>
                            </button>
                            {selected.size > 0 && (
                              <Button size="sm" variant="secondary" className="h-8 px-2 text-xs" disabled={assign.isPending} onClick={() => assignSelected(c.id)}>
                                Atribuir {selected.size}
                              </Button>
                            )}
                          </div>
                        </li>
                      );
                    })}
                  </ul>
                )}
              </div>
              <p className="px-1 text-xs text-muted-foreground">Clique em um corretor para ver os leads dele. Com leads selecionados, use “Atribuir” para distribuir direto.</p>
            </aside>
          </div>
        </div>
      </div>

      {/* Barra de ações em lote */}
      <AnimatePresence>
        {selected.size > 0 && (
          <motion.div
            initial={{ y: 80, opacity: 0 }}
            animate={{ y: 0, opacity: 1 }}
            exit={{ y: 80, opacity: 0 }}
            className="fixed bottom-5 left-1/2 z-40 flex w-[calc(100%-2rem)] max-w-xl -translate-x-1/2 items-center gap-3 rounded-2xl border border-border bg-card p-3 shadow-2xl md:ml-36"
          >
            <span className="px-2 text-sm font-semibold">{selected.size} selecionado(s)</span>
            <div className="ml-auto flex items-center gap-2">
              <AssignMenu corretores={corretores} currentId={null} disabled={assign.isPending} onPick={assignSelected} align="end">
                <Button size="sm" className="gap-1.5" disabled={assign.isPending}>
                  {assign.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <UserCheck className="h-4 w-4" />} Atribuir a…
                  <ChevronDown className="h-4 w-4 opacity-70" />
                </Button>
              </AssignMenu>
              <Button size="sm" variant="outline" onClick={() => setAutoOpen(true)} className="gap-1.5">
                <Sparkles className="h-4 w-4" /> Automático
              </Button>
              <Button size="icon" variant="ghost" className="h-8 w-8" onClick={() => setSelected(new Set())} aria-label="Limpar seleção">
                <X className="h-4 w-4" />
              </Button>
            </div>
          </motion.div>
        )}
      </AnimatePresence>

      <LeadDetailSheet leadId={detailId} corretores={corretores} busy={assign.isPending} onClose={closeDetail} onAssign={assignLead} />

      <AutoDistributeDialog
        open={autoOpen}
        onOpenChange={setAutoOpen}
        corretores={corretores}
        baseParams={baseParams}
        selectedIds={[...selected]}
        onDone={() => {
          setSelected(new Set());
          refreshAll();
        }}
      />
    </div>
  );
}
