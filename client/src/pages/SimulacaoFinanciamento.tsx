import { useEffect, useMemo, useState } from 'react';
import { useLocation } from 'wouter';
import { AnimatePresence, motion } from 'framer-motion';
import {
  ArrowLeft,
  Calculator,
  CheckCircle2,
  ExternalLink,
  Loader2,
  MessageCircle,
  TrendingDown,
  TrendingUp,
} from 'lucide-react';
import api from '@/lib/api';
import { fetchTenantBranding, TenantBranding } from '@/lib/tenantBranding';

interface TenantConfig extends TenantBranding {
  contact_phone?: string;
}

// ---------- Cálculos de financiamento ----------

/** SAC — parcelas decrescentes, amortização constante */
function calcSAC(principal: number, monthlyRate: number, months: number) {
  const amortizacao = principal / months;
  let balance = principal;
  let totalPago = 0;
  const primeira = amortizacao + balance * monthlyRate;
  for (let i = 0; i < months; i++) {
    totalPago += amortizacao + balance * monthlyRate;
    balance -= amortizacao;
  }
  const ultima = amortizacao + amortizacao * monthlyRate;
  return { primeira, ultima, totalPago, totalJuros: totalPago - principal };
}

/** PRICE — parcelas fixas (sistema francês) */
function calcPRICE(principal: number, monthlyRate: number, months: number) {
  const pmt =
    (principal * (monthlyRate * Math.pow(1 + monthlyRate, months))) /
    (Math.pow(1 + monthlyRate, months) - 1);
  const totalPago = pmt * months;
  return { parcela: pmt, totalPago, totalJuros: totalPago - principal };
}

// ---------- Utilitários ----------

function fmtBRL(value: number) {
  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
    maximumFractionDigits: 2,
  }).format(value);
}

/** Formata string de dígitos como moeda BRL digitada (ex: "35000000" → "R$ 350.000,00") */
function parseBRL(raw: string): string {
  const digits = raw.replace(/\D/g, '');
  if (!digits) return '';
  const num = parseInt(digits, 10) / 100;
  return num.toLocaleString('pt-BR', {
    style: 'currency',
    currency: 'BRL',
    minimumFractionDigits: 2,
  });
}

/** Converte string BRL formatada para número */
function brlToNumber(value: string): number {
  const cleaned = value.replace(/R\$\s?/g, '').replace(/\./g, '').replace(',', '.');
  return parseFloat(cleaned) || 0;
}

/** Formata telefone BR */
function fmtPhone(value: string) {
  const d = value.replace(/\D/g, '').slice(0, 11);
  if (d.length <= 2) return d;
  if (d.length <= 7) return `(${d.slice(0, 2)}) ${d.slice(2)}`;
  if (d.length <= 11) return `(${d.slice(0, 2)}) ${d.slice(2, 7)}-${d.slice(7)}`;
  return `(${d.slice(0, 2)}) ${d.slice(2, 7)}-${d.slice(7, 11)}`;
}

// ---------- Opções de seleção ----------

const TAXAS = [
  { label: '8,00% a.a. (SFH mínimo)', value: '8.00' },
  { label: '8,16% a.a. (Caixa SFH padrão)', value: '8.16' },
  { label: '9,00% a.a.', value: '9.00' },
  { label: '9,50% a.a.', value: '9.50' },
  { label: '10,00% a.a.', value: '10.00' },
  { label: '10,50% a.a.', value: '10.50' },
  { label: '11,00% a.a.', value: '11.00' },
  { label: '12,00% a.a.', value: '12.00' },
];

const PRAZOS = [10, 15, 20, 25, 30, 35].map((v) => ({
  label: `${v} anos (${v * 12} meses)`,
  value: String(v),
}));

// ---------- Tipos ----------

interface FormData {
  nome: string;
  telefone: string;
  email: string;
  valor_imovel: string;
  entrada: string;
  prazo: string;
  taxa: string;
  renda_mensal: string;
}

interface SimResult {
  principal: number;
  entradaPct: number;
  meses: number;
  taxaAnual: number;
  sac: { primeira: number; ultima: number; totalPago: number; totalJuros: number };
  price: { parcela: number; totalPago: number; totalJuros: number };
  rendaMinSac: number;
  rendaMinPrice: number;
}

// ---------- Componente principal ----------

export default function SimulacaoFinanciamento() {
  const [, navigate] = useLocation();
  const [tenant, setTenant] = useState<TenantConfig | null>(null);
  const [form, setForm] = useState<FormData>({
    nome: '',
    telefone: '',
    email: '',
    valor_imovel: '',
    entrada: '',
    prazo: '30',
    taxa: '10.00',
    renda_mensal: '',
  });
  const [result, setResult] = useState<SimResult | null>(null);
  const [leadSent, setLeadSent] = useState(false);
  const [sending, setSending] = useState(false);
  const [error, setError] = useState('');
  const [success, setSuccess] = useState(false);

  useEffect(() => {
    fetchTenantBranding().then((b) => setTenant((b as TenantConfig) || null));
  }, []);

  const primary = tenant?.primary_color || '#0f172a';
  const secondary = tenant?.secondary_color || '#c39a66';

  const whatsappLink = useMemo(() => {
    const phone = tenant?.contact_phone?.replace(/\D/g, '');
    if (!phone) return '';
    const msg = encodeURIComponent(
      `Olá! Fiz uma simulação de financiamento no portal da ${tenant?.name || 'imobiliária'} e gostaria de ajuda para encontrar o imóvel ideal.`,
    );
    return `https://wa.me/${phone}?text=${msg}`;
  }, [tenant]);

  function handleField(field: keyof FormData, value: string) {
    setForm((f) => ({ ...f, [field]: value }));
  }

  function handleCurrency(field: 'valor_imovel' | 'entrada' | 'renda_mensal', raw: string) {
    setForm((f) => ({ ...f, [field]: parseBRL(raw) }));
  }

  async function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    setError('');

    const valorImovel = brlToNumber(form.valor_imovel);
    const entrada = brlToNumber(form.entrada);
    const rendaMensal = brlToNumber(form.renda_mensal);
    const prazo = parseInt(form.prazo, 10);
    const taxa = parseFloat(form.taxa);

    // Validações
    if (!form.nome.trim()) { setError('Informe seu nome completo.'); return; }
    if (form.telefone.replace(/\D/g, '').length < 10) { setError('Informe um telefone válido com DDD.'); return; }
    if (valorImovel < 30_000) { setError('Valor do imóvel deve ser maior que R$ 30.000.'); return; }
    if (entrada < 0 || entrada >= valorImovel) { setError('Valor de entrada inválido.'); return; }
    const entradaPct = (entrada / valorImovel) * 100;
    if (entradaPct < 5) { setError('A entrada mínima é de 5% do valor do imóvel.'); return; }

    // Ativar loading imediatamente após validações passarem
    setSending(true);
    try {

    const principal = valorImovel - entrada;
    const months = prazo * 12;
    const monthlyRate = Math.pow(1 + taxa / 100, 1 / 12) - 1;

    const sac = calcSAC(principal, monthlyRate, months);
    const price = calcPRICE(principal, monthlyRate, months);

    const simResult: SimResult = {
      principal,
      entradaPct,
      meses: months,
      taxaAnual: taxa,
      sac,
      price,
      rendaMinSac: sac.primeira / 0.3,
      rendaMinPrice: price.parcela / 0.3,
    };

    setResult(simResult);

    // Captura de lead automática
    if (!leadSent) {
      const rendaInfo = rendaMensal > 0 ? ` | Renda informada: ${fmtBRL(rendaMensal)}` : '';
      const obs =
        `[Simulação Financiamento ${new Date().toLocaleDateString('pt-BR')}] ` +
        `Imóvel: ${fmtBRL(valorImovel)} | Entrada: ${fmtBRL(entrada)} (${entradaPct.toFixed(1)}%) | ` +
        `Financiado: ${fmtBRL(principal)} | Prazo: ${prazo} anos | Taxa: ${taxa}% a.a.${rendaInfo} | ` +
        `1ª parcela SAC: ${fmtBRL(sac.primeira)} | Parcela PRICE: ${fmtBRL(price.parcela)}`;

      try {
        await api.post('/portal/simulacao-lead', {
          nome: form.nome.trim(),
          telefone: form.telefone.replace(/\D/g, ''),
          email: form.email.trim() || null,
          observacoes: obs,
        });
        setLeadSent(true);
        setSuccess(true);
      } catch {
        // Não bloqueia exibição dos resultados se lead falhar
      }
    }

    setTimeout(() => {
      document.getElementById('resultado')?.scrollIntoView({ behavior: 'smooth' });
    }, 100);
    } finally {
      setSending(false);
    }
  }

  const inputCls =
    'w-full h-11 rounded-xl border border-black/15 bg-slate-50 px-4 text-sm text-slate-900 placeholder:text-slate-400 outline-none focus:border-slate-400 transition-colors';
  const labelCls = 'block text-xs font-medium uppercase tracking-[0.12em] text-slate-600 mb-1';

  return (
    <div className="portal-public min-h-screen" style={{ backgroundColor: '#f4efe8' }}>
      {/* Header */}
      <header className="sticky top-0 z-40 border-b border-white/10 bg-[#0b111f]/92 backdrop-blur-xl">
        <div className="mx-auto max-w-7xl px-4 lg:px-8 py-3 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <button
              type="button"
              onClick={() => navigate('/portal')}
              className="w-9 h-9 rounded-full border border-white/30 text-white flex items-center justify-center hover:bg-white/10 transition-colors"
              aria-label="Voltar ao portal"
            >
              <ArrowLeft className="w-4 h-4" />
            </button>
            {tenant?.logo_url || tenant?.logo ? (
              <img
                src={tenant.logo_url || tenant.logo}
                alt={tenant?.name || 'Logo'}
                className="w-9 h-9 rounded-full bg-white object-contain p-1"
              />
            ) : (
              <div className="w-9 h-9 rounded-full bg-white/10 border border-white/30 flex items-center justify-center text-white text-sm font-semibold">
                {(tenant?.name || 'IM').slice(0, 2).toUpperCase()}
              </div>
            )}
            <div className="min-w-0">
              <p className="text-sm tracking-[0.15em] uppercase text-white truncate">
                {tenant?.name || 'Imobiliária'}
              </p>
              <p className="text-[11px] text-white/65 uppercase tracking-[0.12em]">
                Simulador de Financiamento
              </p>
            </div>
          </div>
          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={() => navigate('/login')}
              className="hidden sm:inline-flex rounded-full border border-white/30 px-4 py-2 text-xs uppercase tracking-[0.12em] text-white hover:bg-white/10 transition-colors"
            >
              Entrar
            </button>
            <button
              type="button"
              onClick={() => navigate('/portal')}
              className="rounded-full border border-white/30 px-4 py-2 text-xs uppercase tracking-[0.12em] text-white hover:bg-white/10 transition-colors"
            >
              Ver Imóveis
            </button>
          </div>
        </div>
      </header>

      {/* Hero */}
      <section
        className="relative overflow-hidden py-12 lg:py-16"
        style={{ background: `linear-gradient(115deg, ${primary}f0 0%, #0a0d16 100%)` }}
      >
        <div className="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(255,255,255,0.12),transparent_40%)]" />
        <div className="relative mx-auto max-w-3xl px-4 text-center">
          <div className="inline-flex items-center gap-2 rounded-full border border-white/25 bg-white/10 px-3 py-1 text-[11px] uppercase tracking-[0.2em] text-white/85 mb-4">
            <Calculator className="w-3 h-3" />
            Simulador de Financiamento
          </div>
          <h1 className="text-3xl md:text-5xl leading-tight text-white">
            Simule seu financiamento imobiliário
          </h1>
          <p className="mt-3 text-sm md:text-base text-white/75 max-w-xl mx-auto">
            Calcule as parcelas pelos sistemas SAC e PRICE, veja a renda mínima necessária e
            receba assessoria personalizada da nossa equipe.
          </p>
        </div>
      </section>

      {/* Formulário */}
      <section className="mx-auto max-w-3xl px-4 lg:px-8 py-10">
        <motion.div
          className="rounded-3xl border border-black/10 bg-white p-6 lg:p-8 shadow-[0_16px_42px_rgba(15,23,42,0.10)]"
          initial={{ opacity: 0, y: 16 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.4 }}
        >
          <p className="text-xs uppercase tracking-[0.2em] text-slate-500">Seus dados</p>
          <p className="mt-1 text-sm text-slate-500">
            Preencha para receber os resultados e para nossa equipe entrar em contato e
            assessorá-lo na compra do seu imóvel.
          </p>

          <form onSubmit={handleSubmit} className="mt-6 space-y-5">
            {/* Dados pessoais */}
            <div className="grid gap-4 sm:grid-cols-2">
              <div className="sm:col-span-2">
                <label className={labelCls}>Nome completo *</label>
                <input
                  type="text"
                  value={form.nome}
                  onChange={(e) => handleField('nome', e.target.value)}
                  placeholder="Seu nome completo"
                  className={inputCls}
                />
              </div>
              <div>
                <label className={labelCls}>WhatsApp / Telefone *</label>
                <input
                  type="tel"
                  value={form.telefone}
                  onChange={(e) => handleField('telefone', fmtPhone(e.target.value))}
                  placeholder="(31) 99999-9999"
                  className={inputCls}
                />
              </div>
              <div>
                <label className={labelCls}>E-mail (opcional)</label>
                <input
                  type="email"
                  value={form.email}
                  onChange={(e) => handleField('email', e.target.value)}
                  placeholder="seu@email.com"
                  className={inputCls}
                />
              </div>
            </div>

            <hr className="border-black/8" />

            {/* Dados do financiamento */}
            <div>
              <p className="text-xs uppercase tracking-[0.2em] text-slate-500 mb-4">
                Dados do financiamento
              </p>
              <div className="grid gap-4 sm:grid-cols-2">
                <div>
                  <label className={labelCls}>Valor do imóvel *</label>
                  <input
                    type="text"
                    inputMode="numeric"
                    value={form.valor_imovel}
                    onChange={(e) => handleCurrency('valor_imovel', e.target.value)}
                    placeholder="R$ 350.000,00"
                    className={inputCls}
                  />
                </div>
                <div>
                  <label className={labelCls}>Valor da entrada *</label>
                  <input
                    type="text"
                    inputMode="numeric"
                    value={form.entrada}
                    onChange={(e) => handleCurrency('entrada', e.target.value)}
                    placeholder="R$ 70.000,00"
                    className={inputCls}
                  />
                  <p className="mt-1 text-xs text-slate-400">Mínimo de 5% do valor do imóvel.</p>
                </div>
                <div>
                  <label className={labelCls}>Prazo do financiamento *</label>
                  <select
                    value={form.prazo}
                    onChange={(e) => handleField('prazo', e.target.value)}
                    className={inputCls}
                  >
                    {PRAZOS.map((p) => (
                      <option key={p.value} value={p.value}>
                        {p.label}
                      </option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className={labelCls}>Taxa de juros ao ano *</label>
                  <select
                    value={form.taxa}
                    onChange={(e) => handleField('taxa', e.target.value)}
                    className={inputCls}
                  >
                    {TAXAS.map((t) => (
                      <option key={t.value} value={t.value}>
                        {t.label}
                      </option>
                    ))}
                  </select>
                </div>
                <div className="sm:col-span-2">
                  <label className={labelCls}>Renda mensal bruta (opcional)</label>
                  <input
                    type="text"
                    inputMode="numeric"
                    value={form.renda_mensal}
                    onChange={(e) => handleCurrency('renda_mensal', e.target.value)}
                    placeholder="R$ 8.000,00"
                    className={inputCls}
                  />
                  <p className="mt-1 text-xs text-slate-400">
                    Usada para verificar se a parcela é compatível com sua renda (regra dos 30%).
                  </p>
                </div>
              </div>
            </div>

            {error && (
              <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded-xl px-4 py-2.5">
                {error}
              </p>
            )}

            <button
              type="submit"
              disabled={sending}
              className="w-full h-12 rounded-xl text-sm font-semibold uppercase tracking-[0.12em] transition-all disabled:opacity-80 flex items-center justify-center gap-2"
              style={{ backgroundColor: secondary, color: '#111827' }}
            >
              {sending ? (
                <Loader2 className="w-4 h-4 animate-spin" />
              ) : (
                <Calculator className="w-4 h-4" />
              )}
              {sending ? 'Aguarde, enviando...' : 'Simular Financiamento'}
            </button>

            {success && (
              <motion.p
                initial={{ opacity: 0, y: 4 }}
                animate={{ opacity: 1, y: 0 }}
                className="text-center text-sm text-emerald-700 flex items-center justify-center gap-1.5"
              >
                <CheckCircle2 className="w-4 h-4" />
                Dados recebidos! Nossa equipe entrará em contato em breve.
              </motion.p>
            )}
          </form>
        </motion.div>
      </section>

      {/* Resultados */}
      <AnimatePresence>
        {result && (
          <motion.section
            id="resultado"
            className="mx-auto max-w-3xl px-4 lg:px-8 pb-16 space-y-5"
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ duration: 0.5 }}
          >
            {/* Banner de loading enquanto envia lead */}
            <AnimatePresence>
              {sending && (
                <motion.div
                  initial={{ opacity: 0, height: 0 }}
                  animate={{ opacity: 1, height: 'auto' }}
                  exit={{ opacity: 0, height: 0 }}
                  className="overflow-hidden"
                >
                  <div className="flex items-center gap-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                    <Loader2 className="w-4 h-4 shrink-0 animate-spin text-amber-600" />
                    <span>Registrando sua simulação, aguarde um momento...</span>
                  </div>
                </motion.div>
              )}
            </AnimatePresence>

            {/* Resumo */}
            <div className="rounded-3xl border border-black/10 bg-white p-6 shadow-[0_10px_30px_rgba(15,23,42,0.08)]">
              <p className="text-xs uppercase tracking-[0.2em] text-slate-500">Resumo da simulação</p>
              <div className="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3">
                {[
                  { label: 'Valor financiado', value: fmtBRL(result.principal) },
                  { label: '% de entrada', value: `${result.entradaPct.toFixed(1)}%` },
                  { label: 'Prazo', value: `${result.meses / 12} anos` },
                  { label: 'Taxa', value: `${result.taxaAnual}% a.a.` },
                ].map((item) => (
                  <div
                    key={item.label}
                    className="rounded-2xl border border-slate-100 bg-slate-50 px-3 py-3 text-center"
                  >
                    <p className="text-base font-semibold text-slate-900">{item.value}</p>
                    <p className="mt-0.5 text-[10px] uppercase tracking-[0.14em] text-slate-500">
                      {item.label}
                    </p>
                  </div>
                ))}
              </div>
            </div>

            {/* SAC */}
            <div className="rounded-3xl border border-black/10 bg-white p-6 shadow-[0_10px_30px_rgba(15,23,42,0.08)]">
              <div className="flex items-center gap-2 mb-4">
                <div className="w-9 h-9 rounded-full bg-emerald-50 flex items-center justify-center">
                  <TrendingDown className="w-5 h-5 text-emerald-600" />
                </div>
                <div>
                  <p className="font-semibold text-slate-900">
                    Sistema SAC — Parcelas Decrescentes
                  </p>
                  <p className="text-xs text-slate-500">
                    Amortização constante. Primeira parcela maior, mas total de juros menor.
                  </p>
                </div>
              </div>
              <div className="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div className="rounded-2xl bg-emerald-50 border border-emerald-100 px-3 py-3 text-center">
                  <p className="text-base font-bold text-emerald-800">
                    {fmtBRL(result.sac.primeira)}
                  </p>
                  <p className="mt-0.5 text-[10px] uppercase text-emerald-700">1ª parcela</p>
                </div>
                <div className="rounded-2xl bg-slate-50 border border-slate-100 px-3 py-3 text-center">
                  <p className="text-base font-semibold text-slate-700">
                    {fmtBRL(result.sac.ultima)}
                  </p>
                  <p className="mt-0.5 text-[10px] uppercase text-slate-500">Última parcela</p>
                </div>
                <div className="rounded-2xl bg-slate-50 border border-slate-100 px-3 py-3 text-center">
                  <p className="text-base font-semibold text-slate-700">
                    {fmtBRL(result.sac.totalJuros)}
                  </p>
                  <p className="mt-0.5 text-[10px] uppercase text-slate-500">Total em juros</p>
                </div>
                <div className="rounded-2xl bg-slate-50 border border-slate-100 px-3 py-3 text-center">
                  <p className="text-base font-semibold text-slate-700">
                    {fmtBRL(result.sac.totalPago)}
                  </p>
                  <p className="mt-0.5 text-[10px] uppercase text-slate-500">Total pago</p>
                </div>
              </div>
              <p className="mt-3 text-xs text-slate-500">
                Renda mínima sugerida (30%):{' '}
                <span className="font-semibold text-slate-700">{fmtBRL(result.rendaMinSac)}</span>
                {brlToNumber(form.renda_mensal) > 0 &&
                  (brlToNumber(form.renda_mensal) >= result.rendaMinSac ? (
                    <span className="ml-1.5 text-emerald-600 font-medium">
                      ✓ Sua renda é compatível
                    </span>
                  ) : (
                    <span className="ml-1.5 text-amber-600 font-medium">
                      ⚠ Renda abaixo do mínimo sugerido
                    </span>
                  ))}
              </p>
            </div>

            {/* PRICE */}
            <div className="rounded-3xl border border-black/10 bg-white p-6 shadow-[0_10px_30px_rgba(15,23,42,0.08)]">
              <div className="flex items-center gap-2 mb-4">
                <div className="w-9 h-9 rounded-full bg-blue-50 flex items-center justify-center">
                  <TrendingUp className="w-5 h-5 text-blue-600" />
                </div>
                <div>
                  <p className="font-semibold text-slate-900">Tabela PRICE — Parcelas Fixas</p>
                  <p className="text-xs text-slate-500">
                    Parcela constante durante todo o contrato. Previsibilidade no orçamento.
                  </p>
                </div>
              </div>
              <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                <div className="rounded-2xl bg-blue-50 border border-blue-100 px-3 py-3 text-center">
                  <p className="text-base font-bold text-blue-800">
                    {fmtBRL(result.price.parcela)}
                  </p>
                  <p className="mt-0.5 text-[10px] uppercase text-blue-700">Parcela fixa</p>
                </div>
                <div className="rounded-2xl bg-slate-50 border border-slate-100 px-3 py-3 text-center">
                  <p className="text-base font-semibold text-slate-700">
                    {fmtBRL(result.price.totalJuros)}
                  </p>
                  <p className="mt-0.5 text-[10px] uppercase text-slate-500">Total em juros</p>
                </div>
                <div className="rounded-2xl bg-slate-50 border border-slate-100 px-3 py-3 text-center">
                  <p className="text-base font-semibold text-slate-700">
                    {fmtBRL(result.price.totalPago)}
                  </p>
                  <p className="mt-0.5 text-[10px] uppercase text-slate-500">Total pago</p>
                </div>
              </div>
              <p className="mt-3 text-xs text-slate-500">
                Renda mínima sugerida (30%):{' '}
                <span className="font-semibold text-slate-700">
                  {fmtBRL(result.rendaMinPrice)}
                </span>
                {brlToNumber(form.renda_mensal) > 0 &&
                  (brlToNumber(form.renda_mensal) >= result.rendaMinPrice ? (
                    <span className="ml-1.5 text-emerald-600 font-medium">
                      ✓ Sua renda é compatível
                    </span>
                  ) : (
                    <span className="ml-1.5 text-amber-600 font-medium">
                      ⚠ Renda abaixo do mínimo sugerido
                    </span>
                  ))}
              </p>
            </div>

            {/* CTA */}
            <div
              className="rounded-3xl p-6 text-white shadow-[0_14px_40px_rgba(15,23,42,0.22)]"
              style={{ backgroundColor: primary }}
            >
              <p className="text-xs uppercase tracking-[0.2em] text-white/70">Próximos passos</p>
              <h3 className="mt-2 text-xl">Fale com um especialista</h3>
              <p className="mt-2 text-sm text-white/80">
                Nossa equipe pode ajudá-lo a encontrar o imóvel ideal, preparar a documentação e
                intermediar o processo de financiamento com o banco.
              </p>
              <div className="mt-5 flex flex-wrap gap-3">
                {whatsappLink && (
                  <a
                    href={whatsappLink}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex items-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold"
                    style={{ backgroundColor: secondary, color: '#111827' }}
                  >
                    <MessageCircle className="w-4 h-4" />
                    Falar no WhatsApp
                  </a>
                )}
                <a
                  href="https://simuladorhabitacao.caixa.gov.br/home"
                  target="_blank"
                  rel="noreferrer"
                  className="inline-flex items-center gap-2 rounded-full border border-white/30 px-5 py-2.5 text-sm text-white hover:bg-white/10 transition-colors"
                >
                  <ExternalLink className="w-4 h-4" />
                  Simulação oficial — Caixa
                </a>
                <button
                  type="button"
                  onClick={() => navigate('/portal')}
                  className="inline-flex items-center gap-2 rounded-full border border-white/30 px-5 py-2.5 text-sm text-white hover:bg-white/10 transition-colors"
                >
                  Ver imóveis disponíveis
                </button>
              </div>
              <p className="mt-5 text-xs text-white/45 leading-relaxed">
                * Esta simulação é apenas uma estimativa educacional. Os valores reais dependem de
                análise bancária, seguros obrigatórios (MIP e DFI), taxa de administração e
                atualização pelo índice TR/IPCA. Consulte a Caixa Econômica Federal ou seu banco
                para uma simulação oficial.
              </p>
            </div>
          </motion.section>
        )}
      </AnimatePresence>
    </div>
  );
}
