import { useEffect, useState } from 'react';
import { useLocation } from 'wouter';
import { motion, AnimatePresence } from 'framer-motion';
import {
  ArrowLeft, ArrowRight, Calculator, CheckCircle2,
  ExternalLink, Loader2, MessageCircle, TrendingDown, TrendingUp,
} from 'lucide-react';
import api from '@/lib/api';
import { fetchTenantBranding, TenantBranding } from '@/lib/tenantBranding';

interface TenantConfig extends TenantBranding { contact_phone?: string; }

const CAIXA_URL = 'https://simuladorhabitacao.caixa.gov.br/simulacao';

const UFS = [
  'AC','AL','AM','AP','BA','CE','DF','ES','GO','MA',
  'MG','MS','MT','PA','PB','PE','PI','PR','RJ','RN',
  'RO','RR','RS','SC','SE','SP','TO',
];

const OBJETIVOS = [
  { value: 'novo',       label: 'Aquisição de Imóvel Novo',   desc: 'Comprar um imóvel que nunca foi usado' },
  { value: 'usado',      label: 'Aquisição de Imóvel Usado',  desc: 'Comprar um imóvel que já teve outro dono' },
  { value: 'terreno',    label: 'Aquisição de Terreno',       desc: 'Comprar um lote para construir' },
  { value: 'construcao', label: 'Construção',                 desc: 'Construir um imóvel do zero' },
  { value: 'reforma',    label: 'Reforma e/ou Ampliação',     desc: 'Reformar ou aumentar seu imóvel atual' },
  { value: 'emprestimo', label: 'Empréstimo com Garantia',    desc: 'Usar seu imóvel como garantia de empréstimo' },
];

// ---------- Formatadores ----------
function fmtCPF(v: string) {
  const d = v.replace(/\D/g, '').slice(0, 11);
  if (d.length <= 3) return d;
  if (d.length <= 6) return d.slice(0, 3) + '.' + d.slice(3);
  if (d.length <= 9) return d.slice(0, 3) + '.' + d.slice(3, 6) + '.' + d.slice(6);
  return d.slice(0, 3) + '.' + d.slice(3, 6) + '.' + d.slice(6, 9) + '-' + d.slice(9);
}
function fmtDate(v: string) {
  const d = v.replace(/\D/g, '').slice(0, 8);
  if (d.length <= 2) return d;
  if (d.length <= 4) return d.slice(0, 2) + '/' + d.slice(2);
  return d.slice(0, 2) + '/' + d.slice(2, 4) + '/' + d.slice(4);
}
function fmtPhone(v: string) {
  const d = v.replace(/\D/g, '').slice(0, 11);
  if (d.length <= 2) return d;
  if (d.length <= 7) return '(' + d.slice(0, 2) + ') ' + d.slice(2);
  return '(' + d.slice(0, 2) + ') ' + d.slice(2, 7) + '-' + d.slice(7);
}
function parseBRL(raw: string): string {
  const digits = raw.replace(/\D/g, '');
  if (!digits) return '';
  const num = parseInt(digits, 10) / 100;
  return num.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL', minimumFractionDigits: 2 });
}
function brlToNum(v: string) {
  return parseFloat(v.replace(/R\$\s?/g, '').replace(/\./g, '').replace(',', '.')) || 0;
}
function fmtBRL(n: number) {
  return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 2 });
}

// ---------- Cálculos ----------
function calcSAC(p: number, r: number, n: number) {
  const amort = p / n;
  let bal = p, total = 0;
  const primeira = amort + bal * r;
  for (let i = 0; i < n; i++) { total += amort + bal * r; bal -= amort; }
  const ultima = amort + amort * r;
  return { primeira, ultima, totalPago: total, totalJuros: total - p };
}
function calcPRICE(p: number, r: number, n: number) {
  const pmt = p * (r * Math.pow(1 + r, n)) / (Math.pow(1 + r, n) - 1);
  return { parcela: pmt, totalPago: pmt * n, totalJuros: pmt * n - p };
}

// ---------- Tipos ----------
interface Pessoal {
  nome: string; cpf: string; nascimento: string;
  celular: string; email: string; renda: string;
  fgts3anos: boolean | null; multiplosCompradores: boolean | null;
  aceite: boolean;
}
interface Imovel {
  tipo: 'residencial' | 'comercial' | '';
  objetivo: string; valor: string; entrada: string;
  prazo: string; uf: string; cidade: string;
  subsidio: boolean | null; outroImovel: boolean | null;
}

// ---------- Componente ----------
export default function SimulacaoFinanciamento() {
  const [, navigate] = useLocation();
  const [tenant, setTenant] = useState<TenantConfig | null>(null);
  const [step, setStep] = useState<1 | 2 | 3>(1);
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState('');

  const [pessoal, setPessoal] = useState<Pessoal>({
    nome: '', cpf: '', nascimento: '', celular: '', email: '',
    renda: '', fgts3anos: null, multiplosCompradores: null, aceite: false,
  });
  const [imovel, setImovel] = useState<Imovel>({
    tipo: '', objetivo: '', valor: '', entrada: '',
    prazo: '360', uf: '', cidade: '', subsidio: null, outroImovel: null,
  });

  useEffect(() => {
    fetchTenantBranding().then((b) => setTenant((b as TenantConfig) || null));
  }, []);

  const primary   = tenant?.primary_color   || '#0f172a';
  const secondary = tenant?.secondary_color || '#c39a66';

  const whatsappLink = (() => {
    const phone = tenant?.contact_phone?.replace(/\D/g, '');
    if (!phone) return '';
    const valorNum = brlToNum(imovel.valor);
    const msg = encodeURIComponent(
      'Olá! Acabei de simular um financiamento no portal da ' + (tenant?.name || 'imobiliária') +
      (valorNum > 0 ? '. Imóvel: ' + fmtBRL(valorNum) : '') +
      '. Gostaria de assessoria para dar os próximos passos.'
    );
    return 'https://wa.me/' + phone + '?text=' + msg;
  })();

  // ------- Resultado calculado -------
  const resultado = (() => {
    const valorImovel = brlToNum(imovel.valor);
    const entrada     = brlToNum(imovel.entrada);
    if (valorImovel < 30_000 || entrada < 0 || entrada >= valorImovel) return null;
    const principal  = valorImovel - entrada;
    const meses      = parseInt(imovel.prazo, 10) || 360;
    const renda      = brlToNum(pessoal.renda);
    const mcmv = pessoal.fgts3anos === true && imovel.tipo === 'residencial' &&
                 ['novo', 'usado'].includes(imovel.objetivo) && renda > 0 && renda <= 8_000;
    const taxaAnual   = mcmv ? 7.93 : 10.5;
    const monthlyRate = Math.pow(1 + taxaAnual / 100, 1 / 12) - 1;
    const sac   = calcSAC(principal, monthlyRate, meses);
    const price = calcPRICE(principal, monthlyRate, meses);
    return {
      valorImovel, entrada, principal,
      entradaPct: (entrada / valorImovel) * 100,
      meses, taxaAnual, mcmv, sac, price,
      rendaMinSac:   sac.primeira  / 0.3,
      rendaMinPrice: price.parcela / 0.3,
    };
  })();

  function setPF<K extends keyof Pessoal>(k: K, v: Pessoal[K]) {
    setPessoal(f => ({ ...f, [k]: v }));
  }
  function setIM<K extends keyof Imovel>(k: K, v: Imovel[K]) {
    setImovel(f => ({ ...f, [k]: v }));
  }

  async function goToStep2(e: React.FormEvent) {
    e.preventDefault(); setError('');
    if (!pessoal.nome.trim())                              { setError('Informe seu nome.'); return; }
    if (pessoal.cpf.replace(/\D/g, '').length < 11)       { setError('CPF inválido.'); return; }
    if (pessoal.nascimento.replace(/\D/g, '').length < 8) { setError('Data de nascimento inválida.'); return; }
    if (pessoal.celular.replace(/\D/g, '').length < 10)   { setError('Celular inválido.'); return; }
    if (!pessoal.renda)                                    { setError('Informe sua renda mensal.'); return; }
    if (pessoal.fgts3anos === null)                        { setError('Responda sobre o FGTS.'); return; }
    if (pessoal.multiplosCompradores === null)              { setError('Responda sobre compradores.'); return; }
    if (!pessoal.aceite)                                   { setError('Aceite a política de privacidade para continuar.'); return; }
    setStep(2);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  async function goToStep3(e: React.FormEvent) {
    e.preventDefault(); setError('');
    if (!imovel.tipo)                                      { setError('Selecione o tipo de imóvel.'); return; }
    if (!imovel.objetivo)                                  { setError('Selecione o objetivo.'); return; }
    if (brlToNum(imovel.valor) < 30_000)                   { setError('Valor do imóvel deve ser maior que R$ 30.000.'); return; }
    const ep = brlToNum(imovel.entrada) / brlToNum(imovel.valor) * 100;
    if (ep < 5)                                            { setError('Entrada mínima de 5% do valor do imóvel.'); return; }
    if (!imovel.uf)                                        { setError('Selecione o estado (UF).'); return; }
    if (!imovel.cidade.trim())                             { setError('Informe a cidade.'); return; }
    if (imovel.subsidio === null)                          { setError('Responda sobre subsídio anterior.'); return; }
    if (imovel.outroImovel === null)                       { setError('Responda sobre outro imóvel.'); return; }

    setSaving(true);
    try {
      await api.post('/portal/simulacao-lead', {
        nome:     pessoal.nome.trim(),
        telefone: pessoal.celular.replace(/\D/g, ''),
        email:    pessoal.email.trim() || null,
        observacoes:
          '[Simulação Financiamento ' + new Date().toLocaleDateString('pt-BR') + '] ' +
          'Imóvel: ' + fmtBRL(brlToNum(imovel.valor)) +
          ' | Entrada: ' + fmtBRL(brlToNum(imovel.entrada)) +
          ' | Renda: ' + fmtBRL(brlToNum(pessoal.renda)) +
          ' | Tipo: ' + imovel.tipo + ' | Objetivo: ' + imovel.objetivo +
          ' | UF: ' + imovel.uf + ' | Cidade: ' + imovel.cidade,
      });
    } catch { /* não bloqueia */ } finally { setSaving(false); }

    setStep(3);
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  const inputCls = 'w-full h-11 rounded-xl border border-black/15 bg-slate-50 px-4 text-sm text-slate-900 placeholder:text-slate-400 outline-none focus:border-slate-400 transition-colors';
  const labelCls = 'block text-xs font-medium uppercase tracking-[0.12em] text-slate-600 mb-1';
  const radioBtnCls = (active: boolean) =>
    'flex-1 flex flex-col items-center justify-center gap-1 rounded-xl border-2 px-3 py-3 text-xs font-medium cursor-pointer transition-all text-center ' +
    (active ? 'border-slate-700 bg-slate-700 text-white' : 'border-black/10 bg-slate-50 text-slate-700 hover:border-slate-400');

  const stepLabels = ['Dados pessoais', 'Dados do imóvel', 'Resultado'];

  return (
    <div className="portal-public min-h-screen flex flex-col" style={{ backgroundColor: '#f4efe8' }}>
      {/* Header */}
      <header className="sticky top-0 z-40 border-b border-white/10 bg-[#0b111f]/92 backdrop-blur-xl">
        <div className="mx-auto max-w-7xl px-4 lg:px-8 py-3 flex items-center justify-between">
          <div className="flex items-center gap-3">
            <button
              type="button"
              onClick={() => { if (step === 1) navigate('/portal'); else setStep((s) => (s - 1) as 1 | 2 | 3); }}
              className="w-9 h-9 rounded-full border border-white/30 text-white flex items-center justify-center hover:bg-white/10 transition-colors"
              aria-label="Voltar"
            >
              <ArrowLeft className="w-4 h-4" />
            </button>
            {tenant?.logo_url || tenant?.logo ? (
              <img src={tenant.logo_url || tenant.logo} alt={tenant?.name || 'Logo'} className="w-9 h-9 rounded-full bg-white object-contain p-1" />
            ) : (
              <div className="w-9 h-9 rounded-full bg-white/10 border border-white/30 flex items-center justify-center text-white text-sm font-semibold">
                {(tenant?.name || 'IM').slice(0, 2).toUpperCase()}
              </div>
            )}
            <div className="min-w-0">
              <p className="text-sm tracking-[0.15em] uppercase text-white truncate">{tenant?.name || 'Imobiliária'}</p>
              <p className="text-[11px] text-white/65 uppercase tracking-[0.12em]">Simulador de Financiamento</p>
            </div>
          </div>
          <div className="flex items-center gap-2">
            <button type="button" onClick={() => navigate('/portal')} className="rounded-full border border-white/30 px-4 py-2 text-xs uppercase tracking-[0.12em] text-white hover:bg-white/10 transition-colors">
              Ver Imóveis
            </button>
          </div>
        </div>
      </header>

      {/* Hero + Steps */}
      <section className="relative overflow-hidden py-10 lg:py-14" style={{ background: `linear-gradient(115deg, ${primary}f0 0%, #0a0d16 100%)` }}>
        <div className="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(255,255,255,0.12),transparent_40%)]" />
        <div className="relative mx-auto max-w-3xl px-4 text-center">
          <div className="inline-flex items-center gap-2 rounded-full border border-white/25 bg-white/10 px-3 py-1 text-[11px] uppercase tracking-[0.2em] text-white/85 mb-4">
            <Calculator className="w-3 h-3" />
            Simulador de Financiamento Imobiliário
          </div>
          <h1 className="text-2xl md:text-4xl leading-tight text-white">Simule seu financiamento imobiliário</h1>
          <p className="mt-2 text-sm text-white/70 max-w-lg mx-auto">
            Preencha os mesmos dados do simulador da Caixa Econômica Federal e veja uma estimativa completa.
          </p>
          {/* Steps indicator */}
          <div className="mt-6 flex items-center justify-center">
            {stepLabels.map((label, i) => {
              const n = (i + 1) as 1 | 2 | 3;
              const done = step > n;
              const active = step === n;
              return (
                <div key={n} className="flex items-center">
                  <div className="flex flex-col items-center">
                    <div className={`w-8 h-8 rounded-full flex items-center justify-center text-xs font-bold transition-all ${done ? 'bg-emerald-400 text-white' : active ? 'bg-white text-slate-900' : 'bg-white/20 text-white/60'}`}>
                      {done ? <CheckCircle2 className="w-4 h-4" /> : n}
                    </div>
                    <p className={`mt-1 text-[10px] uppercase tracking-wider ${active ? 'text-white' : 'text-white/50'}`}>{label}</p>
                  </div>
                  {i < stepLabels.length - 1 && (
                    <div className={`w-12 h-px mb-4 mx-1 ${step > n ? 'bg-emerald-400' : 'bg-white/20'}`} />
                  )}
                </div>
              );
            })}
          </div>
        </div>
      </section>

      <div className="mx-auto w-full max-w-2xl px-4 lg:px-8 py-8 flex-1">
        <AnimatePresence mode="wait">

          {/* ══ ETAPA 1: Dados Pessoais ══ */}
          {step === 1 && (
            <motion.div key="step1" initial={{ opacity: 0, x: 20 }} animate={{ opacity: 1, x: 0 }} exit={{ opacity: 0, x: -20 }} transition={{ duration: 0.3 }}>
              <form onSubmit={goToStep2} className="space-y-5">
                <div className="rounded-3xl border border-black/10 bg-white p-6 shadow-[0_10px_30px_rgba(15,23,42,0.08)] space-y-4">
                  <p className="text-xs uppercase tracking-[0.2em] text-slate-500 font-medium">Dados Pessoais</p>

                  <div>
                    <label className={labelCls}>Nome completo *</label>
                    <input type="text" value={pessoal.nome} onChange={e => setPF('nome', e.target.value)} placeholder="Seu nome completo" className={inputCls} />
                  </div>

                  <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                      <label className={labelCls}>Qual seu CPF? *</label>
                      <input type="text" inputMode="numeric" value={pessoal.cpf} onChange={e => setPF('cpf', fmtCPF(e.target.value))} placeholder="000.000.000-00" className={inputCls} />
                    </div>
                    <div>
                      <label className={labelCls}>Quando você nasceu? *</label>
                      <input type="text" inputMode="numeric" value={pessoal.nascimento} onChange={e => setPF('nascimento', fmtDate(e.target.value))} placeholder="DD/MM/AAAA" className={inputCls} />
                    </div>
                  </div>

                  <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                      <label className={labelCls}>Número de celular *</label>
                      <input type="tel" value={pessoal.celular} onChange={e => setPF('celular', fmtPhone(e.target.value))} placeholder="(92) 99999-9999" className={inputCls} />
                    </div>
                    <div>
                      <label className={labelCls}>E-mail (opcional)</label>
                      <input type="email" value={pessoal.email} onChange={e => setPF('email', e.target.value)} placeholder="seu@email.com" className={inputCls} />
                    </div>
                  </div>

                  <div>
                    <label className={labelCls}>Qual é sua renda bruta familiar mensal? *</label>
                    <input type="text" inputMode="numeric" value={pessoal.renda} onChange={e => setPF('renda', parseBRL(e.target.value))} placeholder="R$ 0,00" className={inputCls} />
                  </div>
                </div>

                <div className="rounded-3xl border border-black/10 bg-white p-6 shadow-[0_10px_30px_rgba(15,23,42,0.08)] space-y-5">
                  <div className="flex items-start gap-2">
                    <span className="text-base mt-0.5">💡</span>
                    <p className="text-sm font-medium text-slate-700">Algumas perguntas extras — suas respostas ajudam a encontrar as melhores condições</p>
                  </div>

                  <div>
                    <p className={labelCls}>Você tem pelo menos 3 anos de trabalho com depósito de FGTS? *</p>
                    <p className="text-xs text-slate-400 mb-3">Essa informação pode garantir condições especiais para você</p>
                    <div className="flex gap-3">
                      <button type="button" onClick={() => setPF('fgts3anos', true)}  className={radioBtnCls(pessoal.fgts3anos === true)}>Sim, tenho 3 anos ou mais</button>
                      <button type="button" onClick={() => setPF('fgts3anos', false)} className={radioBtnCls(pessoal.fgts3anos === false)}>Não, tenho menos de 3 anos</button>
                    </div>
                  </div>

                  <div>
                    <p className={labelCls}>Possui mais de um comprador e/ou dependente na proposta? *</p>
                    <div className="flex gap-3 mt-2">
                      <button type="button" onClick={() => setPF('multiplosCompradores', true)}  className={radioBtnCls(pessoal.multiplosCompradores === true)}>Sim, tenho!</button>
                      <button type="button" onClick={() => setPF('multiplosCompradores', false)} className={radioBtnCls(pessoal.multiplosCompradores === false)}>Não, só eu</button>
                    </div>
                  </div>
                </div>

                <div className="rounded-3xl border border-black/10 bg-white p-6 shadow-[0_10px_30px_rgba(15,23,42,0.08)]">
                  <label className="flex items-start gap-3 cursor-pointer" onClick={() => setPF('aceite', !pessoal.aceite)}>
                    <div className={`mt-0.5 w-5 h-5 rounded border-2 flex-shrink-0 flex items-center justify-center transition-colors ${pessoal.aceite ? 'border-slate-700 bg-slate-700' : 'border-slate-300'}`}>
                      {pessoal.aceite && <CheckCircle2 className="w-3 h-3 text-white" />}
                    </div>
                    <p className="text-xs text-slate-600 leading-relaxed">
                      Autorizo a coleta e armazenamento dos meus dados pessoais para que a imobiliária possa entrar em contato,
                      enviar notificações e oferecer assessoria sobre financiamento imobiliário. *
                    </p>
                  </label>
                </div>

                {error && <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded-xl px-4 py-2.5">{error}</p>}

                <button type="submit" className="w-full h-12 rounded-xl text-sm font-semibold uppercase tracking-[0.12em] flex items-center justify-center gap-2 transition-all" style={{ backgroundColor: secondary, color: '#111827' }}>
                  Próximo: Dados do Imóvel <ArrowRight className="w-4 h-4" />
                </button>
              </form>
            </motion.div>
          )}

          {/* ══ ETAPA 2: Dados do Imóvel ══ */}
          {step === 2 && (
            <motion.div key="step2" initial={{ opacity: 0, x: 20 }} animate={{ opacity: 1, x: 0 }} exit={{ opacity: 0, x: -20 }} transition={{ duration: 0.3 }}>
              <form onSubmit={goToStep3} className="space-y-5">

                <div className="rounded-3xl border border-black/10 bg-white p-6 shadow-[0_10px_30px_rgba(15,23,42,0.08)] space-y-5">
                  <p className="text-xs uppercase tracking-[0.2em] text-slate-500 font-medium">Tipo de Imóvel</p>

                  <div>
                    <p className={labelCls}>Para qual tipo de imóvel você deseja obter financiamento? *</p>
                    <div className="flex gap-3 mt-2">
                      <button type="button" onClick={() => setIM('tipo', 'residencial')} className={radioBtnCls(imovel.tipo === 'residencial')}>
                        <span className="text-base">🏠</span>
                        <span>Residencial</span>
                        <span className="text-[10px] font-normal opacity-70">Casa, apto, terreno</span>
                      </button>
                      <button type="button" onClick={() => setIM('tipo', 'comercial')} className={radioBtnCls(imovel.tipo === 'comercial')}>
                        <span className="text-base">🏢</span>
                        <span>Comercial</span>
                        <span className="text-[10px] font-normal opacity-70">Sala, loja, negócio</span>
                      </button>
                    </div>
                  </div>

                  <div>
                    <p className={labelCls}>Qual é o seu objetivo? *</p>
                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 mt-2">
                      {OBJETIVOS.map(o => (
                        <button key={o.value} type="button" onClick={() => setIM('objetivo', o.value)}
                          className={`rounded-xl border-2 px-3 py-2.5 text-left text-xs transition-all ${imovel.objetivo === o.value ? 'border-slate-700 bg-slate-700 text-white' : 'border-black/10 bg-slate-50 text-slate-700 hover:border-slate-400'}`}>
                          <p className="font-semibold leading-tight">{o.label}</p>
                          <p className="mt-0.5 opacity-70 leading-tight">{o.desc}</p>
                        </button>
                      ))}
                    </div>
                  </div>
                </div>

                <div className="rounded-3xl border border-black/10 bg-white p-6 shadow-[0_10px_30px_rgba(15,23,42,0.08)] space-y-4">
                  <p className="text-xs uppercase tracking-[0.2em] text-slate-500 font-medium">Valores</p>
                  <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                      <label className={labelCls}>Qual o valor do imóvel? *</label>
                      <input type="text" inputMode="numeric" value={imovel.valor} onChange={e => setIM('valor', parseBRL(e.target.value))} placeholder="R$ 0,00" className={inputCls} />
                    </div>
                    <div>
                      <label className={labelCls}>Valor de entrada *</label>
                      <input type="text" inputMode="numeric" value={imovel.entrada} onChange={e => setIM('entrada', parseBRL(e.target.value))} placeholder="R$ 0,00" className={inputCls} />
                      <p className="mt-1 text-xs text-slate-400">Mínimo de 5% do valor do imóvel</p>
                    </div>
                  </div>
                  <div>
                    <label className={labelCls}>Prazo desejado</label>
                    <select value={imovel.prazo} onChange={e => setIM('prazo', e.target.value)} className={inputCls}>
                      {[10, 15, 20, 25, 30, 35].map(v => (
                        <option key={v} value={String(v * 12)}>{v} anos ({v * 12} meses)</option>
                      ))}
                    </select>
                  </div>
                </div>

                <div className="rounded-3xl border border-black/10 bg-white p-6 shadow-[0_10px_30px_rgba(15,23,42,0.08)] space-y-4">
                  <p className="text-xs uppercase tracking-[0.2em] text-slate-500 font-medium">Localização</p>
                  <div className="grid gap-4 sm:grid-cols-2">
                    <div>
                      <label className={labelCls}>UF *</label>
                      <select value={imovel.uf} onChange={e => setIM('uf', e.target.value)} className={inputCls}>
                        <option value="">Selecione</option>
                        {UFS.map(uf => <option key={uf} value={uf}>{uf}</option>)}
                      </select>
                    </div>
                    <div>
                      <label className={labelCls}>Cidade *</label>
                      <input type="text" value={imovel.cidade} onChange={e => setIM('cidade', e.target.value)} placeholder="Nome da cidade" className={inputCls} />
                    </div>
                  </div>
                </div>

                <div className="rounded-3xl border border-black/10 bg-white p-6 shadow-[0_10px_30px_rgba(15,23,42,0.08)] space-y-5">
                  <p className="text-xs uppercase tracking-[0.2em] text-slate-500 font-medium">Mais algumas informações</p>

                  <div>
                    <p className={labelCls}>Já recebeu algum subsídio do governo (FGTS/União) a partir de maio de 2005? *</p>
                    <div className="flex gap-3 mt-2">
                      <button type="button" onClick={() => setIM('subsidio', true)}  className={radioBtnCls(imovel.subsidio === true)}>Sim</button>
                      <button type="button" onClick={() => setIM('subsidio', false)} className={radioBtnCls(imovel.subsidio === false)}>Não</button>
                    </div>
                  </div>

                  <div>
                    <p className={labelCls}>Possui outro imóvel na mesma cidade? *</p>
                    <div className="flex gap-3 mt-2">
                      <button type="button" onClick={() => setIM('outroImovel', true)}  className={radioBtnCls(imovel.outroImovel === true)}>Sim, tenho!</button>
                      <button type="button" onClick={() => setIM('outroImovel', false)} className={radioBtnCls(imovel.outroImovel === false)}>Não tenho</button>
                    </div>
                  </div>
                </div>

                {error && <p className="text-sm text-red-600 bg-red-50 border border-red-200 rounded-xl px-4 py-2.5">{error}</p>}

                <button type="submit" disabled={saving} className="w-full h-12 rounded-xl text-sm font-semibold uppercase tracking-[0.12em] flex items-center justify-center gap-2 transition-all disabled:opacity-70" style={{ backgroundColor: secondary, color: '#111827' }}>
                  {saving ? <Loader2 className="w-4 h-4 animate-spin" /> : <Calculator className="w-4 h-4" />}
                  {saving ? 'Calculando...' : 'Ver Resultado'}
                </button>
              </form>
            </motion.div>
          )}

          {/* ══ ETAPA 3: Resultado ══ */}
          {step === 3 && resultado && (
            <motion.div key="step3" initial={{ opacity: 0, y: 16 }} animate={{ opacity: 1, y: 0 }} transition={{ duration: 0.4 }} className="space-y-5">

              <div className="flex items-center gap-2 text-sm text-emerald-800 bg-emerald-50 border border-emerald-200 rounded-2xl px-4 py-3">
                <CheckCircle2 className="w-4 h-4 shrink-0 text-emerald-600" />
                <span>Dados registrados! Nossa equipe entrará em contato, <strong>{pessoal.nome.split(' ')[0]}</strong>.</span>
              </div>

              {/* Resumo */}
              <div className="rounded-3xl border border-black/10 bg-white p-6 shadow-[0_10px_30px_rgba(15,23,42,0.08)]">
                <p className="text-xs uppercase tracking-[0.2em] text-slate-500">Resumo da simulação</p>
                <div className="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-3">
                  {[
                    { label: 'Valor do imóvel',  value: fmtBRL(resultado.valorImovel) },
                    { label: 'Valor financiado',  value: fmtBRL(resultado.principal) },
                    { label: 'Entrada',           value: resultado.entradaPct.toFixed(1) + '%' },
                    { label: 'Prazo',             value: (resultado.meses / 12) + ' anos' },
                  ].map(item => (
                    <div key={item.label} className="rounded-2xl border border-slate-100 bg-slate-50 px-3 py-3 text-center">
                      <p className="text-base font-semibold text-slate-900">{item.value}</p>
                      <p className="mt-0.5 text-[10px] uppercase tracking-[0.14em] text-slate-500">{item.label}</p>
                    </div>
                  ))}
                </div>
                {resultado.mcmv && (
                  <div className="mt-3 flex items-center gap-2 rounded-xl bg-blue-50 border border-blue-100 px-3 py-2">
                    <span className="text-sm">🏠</span>
                    <p className="text-xs text-blue-800">Você pode ser elegível ao <strong>Programa Minha Casa Minha Vida</strong> — taxa estimada de {resultado.taxaAnual}% a.a.</p>
                  </div>
                )}
              </div>

              {/* SAC */}
              <div className="rounded-3xl border border-black/10 bg-white p-6 shadow-[0_10px_30px_rgba(15,23,42,0.08)]">
                <div className="flex items-center gap-2 mb-4">
                  <div className="w-9 h-9 rounded-full bg-emerald-50 flex items-center justify-center"><TrendingDown className="w-5 h-5 text-emerald-600" /></div>
                  <div>
                    <p className="font-semibold text-slate-900">SAC — Parcelas Decrescentes</p>
                    <p className="text-xs text-slate-500">Amortização constante, juros menores no total</p>
                  </div>
                </div>
                <div className="grid grid-cols-2 gap-3">
                  {[
                    { label: '1ª parcela',    value: fmtBRL(resultado.sac.primeira),    hi: true },
                    { label: 'Última parcela', value: fmtBRL(resultado.sac.ultima),     hi: false },
                  ].map(item => (
                    <div key={item.label} className={`rounded-2xl px-3 py-3 text-center ${item.hi ? 'bg-emerald-50 border border-emerald-100' : 'bg-slate-50 border border-slate-100'}`}>
                      <p className={`text-base font-bold ${item.hi ? 'text-emerald-800' : 'text-slate-700'}`}>{item.value}</p>
                      <p className={`mt-0.5 text-[10px] uppercase ${item.hi ? 'text-emerald-700' : 'text-slate-500'}`}>{item.label}</p>
                    </div>
                  ))}
                </div>
                <p className="mt-3 text-xs text-slate-500">Renda mínima sugerida (30%): <span className="font-semibold text-slate-700">{fmtBRL(resultado.rendaMinSac)}</span></p>
              </div>

              {/* PRICE */}
              <div className="rounded-3xl border border-black/10 bg-white p-6 shadow-[0_10px_30px_rgba(15,23,42,0.08)]">
                <div className="flex items-center gap-2 mb-4">
                  <div className="w-9 h-9 rounded-full bg-blue-50 flex items-center justify-center"><TrendingUp className="w-5 h-5 text-blue-600" /></div>
                  <div>
                    <p className="font-semibold text-slate-900">PRICE — Parcelas Fixas</p>
                    <p className="text-xs text-slate-500">Parcela constante, maior previsibilidade</p>
                  </div>
                </div>
                <div className="grid grid-cols-1 gap-3">
                  {[
                    { label: 'Parcela fixa',   value: fmtBRL(resultado.price.parcela),    hi: true },
                  ].map(item => (
                    <div key={item.label} className={`rounded-2xl px-3 py-3 text-center ${item.hi ? 'bg-blue-50 border border-blue-100' : 'bg-slate-50 border border-slate-100'}`}>
                      <p className={`text-base font-bold ${item.hi ? 'text-blue-800' : 'text-slate-700'}`}>{item.value}</p>
                      <p className={`mt-0.5 text-[10px] uppercase ${item.hi ? 'text-blue-700' : 'text-slate-500'}`}>{item.label}</p>
                    </div>
                  ))}
                </div>
                <p className="mt-3 text-xs text-slate-500">Renda mínima sugerida (30%): <span className="font-semibold text-slate-700">{fmtBRL(resultado.rendaMinPrice)}</span></p>
              </div>

              {/* CTA Caixa */}
              <div className="rounded-3xl p-6 text-white" style={{ backgroundColor: primary }}>
                <p className="text-xs uppercase tracking-[0.2em] text-white/70">Quer a simulação oficial?</p>
                <h3 className="mt-2 text-xl font-semibold">Confirme no Simulador da Caixa</h3>
                <p className="mt-2 text-sm text-white/80">
                  Esta é uma estimativa. Para valores oficiais com seguros, TR e condições reais de crédito, acesse o simulador da Caixa
                  e use os mesmos dados que você preencheu aqui.
                </p>

                {/* Dados para o usuário copiar/conferir */}
                <div className="mt-4 rounded-2xl bg-white/10 border border-white/20 p-4 space-y-1">
                  <p className="text-xs uppercase tracking-wider text-white/60 mb-2">Seus dados para preencher na Caixa</p>
                  <div className="grid grid-cols-2 gap-x-4 gap-y-1.5 text-sm">
                    <span className="text-white/60 text-xs">CPF:</span>              <span className="font-mono text-xs text-white">{pessoal.cpf}</span>
                    <span className="text-white/60 text-xs">Nascimento:</span>       <span className="font-mono text-xs text-white">{pessoal.nascimento}</span>
                    <span className="text-white/60 text-xs">Celular:</span>          <span className="font-mono text-xs text-white">{pessoal.celular}</span>
                    <span className="text-white/60 text-xs">Renda mensal:</span>     <span className="font-mono text-xs text-white">{pessoal.renda}</span>
                    <span className="text-white/60 text-xs">Valor do imóvel:</span>  <span className="font-mono text-xs text-white">{imovel.valor}</span>
                    <span className="text-white/60 text-xs">Valor de entrada:</span> <span className="font-mono text-xs text-white">{imovel.entrada}</span>
                    <span className="text-white/60 text-xs">UF / Cidade:</span>      <span className="font-mono text-xs text-white">{imovel.uf} / {imovel.cidade}</span>
                  </div>
                </div>

                <div className="mt-5 flex flex-wrap gap-3">
                  <a href={CAIXA_URL} target="_blank" rel="noreferrer"
                    className="inline-flex items-center gap-2 rounded-full px-5 py-2.5 text-sm font-semibold"
                    style={{ backgroundColor: secondary, color: '#111827' }}>
                    <ExternalLink className="w-4 h-4" />
                    Simular no Site da Caixa
                  </a>
                  {whatsappLink && (
                    <a href={whatsappLink} target="_blank" rel="noreferrer"
                      className="inline-flex items-center gap-2 rounded-full border border-white/30 px-5 py-2.5 text-sm text-white hover:bg-white/10 transition-colors">
                      <MessageCircle className="w-4 h-4" />
                      Falar com Corretor
                    </a>
                  )}
                  <button type="button" onClick={() => navigate('/portal')}
                    className="inline-flex items-center gap-2 rounded-full border border-white/30 px-5 py-2.5 text-sm text-white hover:bg-white/10 transition-colors">
                    Ver Imóveis
                  </button>
                </div>

                <p className="mt-4 text-xs text-white/40 leading-relaxed">
                  * Estimativa baseada nas fórmulas SAC/PRICE. Valores reais incluem seguros (MIP/DFI), taxa de administração e correção pela TR/IPCA. Consulte a Caixa Econômica Federal para simulação oficial.
                </p>
              </div>

              <button type="button" onClick={() => { setStep(1); window.scrollTo({ top: 0, behavior: 'smooth' }); }}
                className="w-full h-11 rounded-xl border border-black/15 text-sm text-slate-600 hover:bg-white transition-colors">
                Fazer nova simulação
              </button>
            </motion.div>
          )}

        </AnimatePresence>
      </div>
    </div>
  );
}
