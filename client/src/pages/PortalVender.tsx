import { useEffect, useState } from 'react';
import { useLocation } from 'wouter';
import { motion, AnimatePresence } from 'framer-motion';
import {
  ArrowRight,
  ArrowLeft,
  Building2,
  CheckCircle2,
  Lock,
  LogIn,
  Search,
  User,
  Home,
  DollarSign,
  ClipboardList,
  Loader2,
  MapPin,
  BedDouble,
  Square,
  Phone,
  Mail,
  Tag,
} from 'lucide-react';
import { toast } from 'sonner';
import { api } from '@/lib/api';
import { fetchTenantBranding, TenantBranding } from '@/lib/tenantBranding';

interface FormState {
  cpf: string;
  tipo_imovel: string;
  finalidade: string;
  cep: string;
  cidade: string;
  bairro: string;
  area: string;
  dormitorios: string;
  valor_pretendido: string;
  nome_contato: string;
  telefone_contato: string;
  email_contato: string;
  observacoes: string;
}

const EMPTY_FORM: FormState = {
  cpf: '',
  tipo_imovel: '',
  finalidade: 'venda',
  cep: '',
  cidade: '',
  bairro: '',
  area: '',
  dormitorios: '',
  valor_pretendido: '',
  nome_contato: '',
  telefone_contato: '',
  email_contato: '',
  observacoes: '',
};

const PROPERTY_TYPES = [
  { value: 'casa', label: 'Casa', icon: '🏠' },
  { value: 'apartamento', label: 'Apartamento', icon: '🏢' },
  { value: 'terreno', label: 'Terreno', icon: '🌿' },
  { value: 'comercial', label: 'Comercial', icon: '🏪' },
  { value: 'rural', label: 'Rural', icon: '🌾' },
  { value: 'galpao', label: 'Galpão', icon: '🏭' },
];

const PURPOSES = [
  { value: 'venda', label: 'Vender' },
  { value: 'aluguel', label: 'Alugar' },
  { value: 'venda_aluguel', label: 'Vender ou Alugar' },
];

const STEPS = [
  { id: 1, label: 'Contato', icon: User },
  { id: 2, label: 'Imóvel', icon: Home },
  { id: 3, label: 'Valores', icon: DollarSign },
  { id: 4, label: 'Revisão', icon: ClipboardList },
];

function formatCpf(value: string) {
  const d = value.replace(/\D/g, '').slice(0, 11);
  return d
    .replace(/(\d{3})(\d)/, '$1.$2')
    .replace(/(\d{3})\.(\d{3})(\d)/, '$1.$2.$3')
    .replace(/(\d{3})\.(\d{3})\.(\d{3})(\d)/, '$1.$2.$3-$4');
}

function formatCep(value: string) {
  const d = value.replace(/\D/g, '').slice(0, 8);
  return d.replace(/(\d{5})(\d)/, '$1-$2');
}

function formatPhone(value: string) {
  const d = value.replace(/\D/g, '').slice(0, 11);
  if (d.length <= 10) return d.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3').replace(/-$/, '');
  return d.replace(/(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3').replace(/-$/, '');
}

function formatCurrency(value: string) {
  const digits = value.replace(/\D/g, '');
  if (!digits) return '';
  const number = parseInt(digits, 10);
  return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL', maximumFractionDigits: 0 }).format(number);
}

export default function PortalVender() {
  const [, navigate] = useLocation();
  const [tenant, setTenant] = useState<TenantBranding | null>(null);
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [loading, setLoading] = useState(false);
  const [submitted, setSubmitted] = useState(false);
  const [step, setStep] = useState(1);
  const [cpfLookupLoading, setCpfLookupLoading] = useState(false);
  const [cepLoading, setCepLoading] = useState(false);
  const [direction, setDirection] = useState<1 | -1>(1);

  const primary = tenant?.primary_color || '#0f172a';
  const secondary = tenant?.secondary_color || '#b9935a';
  const logoSrc = tenant?.logo_url || tenant?.logo || '';

  useEffect(() => {
    fetchTenantBranding().then((data: TenantBranding | null) => setTenant(data));
    const token = localStorage.getItem('token');
    setIsAuthenticated(Boolean(token));
    if (token) {
      try {
        const user = JSON.parse(localStorage.getItem('user') || '{}');
        if (user?.name || user?.email) {
          setForm((prev) => ({
            ...prev,
            nome_contato: user.name || prev.nome_contato,
            email_contato: user.email || prev.email_contato,
          }));
        }
      } catch {}
    }
  }, []);

  const set =
    (field: keyof FormState) =>
    (e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>) =>
      setForm((prev) => ({ ...prev, [field]: e.target.value }));

  const handleCpfLookup = async () => {
    const cpf = form.cpf.replace(/\D/g, '');
    if (cpf.length < 11) {
      toast.error('Informe um CPF válido com 11 dígitos.');
      return;
    }
    try {
      setCpfLookupLoading(true);
      const res = await api.get(`/portal/lookup-cpf?cpf=${cpf}`);
      if (res.data?.found && res.data?.data) {
        const d = res.data.data;
        setForm((prev) => ({
          ...prev,
          nome_contato: d.nome || prev.nome_contato,
          email_contato: d.email || prev.email_contato,
          telefone_contato: d.telefone ? formatPhone(d.telefone) : prev.telefone_contato,
          cidade: d.cidade || prev.cidade,
          bairro: d.bairro || prev.bairro,
          cep: d.cep ? formatCep(d.cep) : prev.cep,
        }));
        toast.success('Dados encontrados! Revise e edite se necessário.');
      } else {
        toast('Nenhum cadastro encontrado. Preencha os dados manualmente.', {
          icon: '📋',
        });
      }
    } catch {
      toast('Erro ao buscar CPF. Continue preenchendo manualmente.', { icon: '📋' });
    } finally {
      setCpfLookupLoading(false);
    }
  };

  const handleCepBlur = async () => {
    const cep = form.cep.replace(/\D/g, '');
    if (cep.length !== 8) return;
    try {
      setCepLoading(true);
      const res = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
      const data = await res.json();
      if (!data.erro) {
        setForm((prev) => ({
          ...prev,
          cidade: data.localidade || prev.cidade,
          bairro: data.bairro || prev.bairro,
        }));
      }
    } catch {}
    finally {
      setCepLoading(false);
    }
  };

  const canAdvance = () => {
    if (step === 1) return Boolean(form.nome_contato.trim() && form.telefone_contato.trim());
    if (step === 2) return Boolean(form.tipo_imovel && form.cidade.trim());
    if (step === 3) return true;
    return false;
  };

  const goNext = () => {
    if (!canAdvance()) {
      if (step === 1) toast.error('Preencha nome e telefone para continuar.');
      if (step === 2) toast.error('Selecione o tipo do imóvel e informe a cidade.');
      return;
    }
    setDirection(1);
    setStep((s) => s + 1);
  };

  const goBack = () => {
    setDirection(-1);
    setStep((s) => s - 1);
  };

  const handleSubmit = async () => {
    if (!form.tipo_imovel || !form.cidade || !form.nome_contato || !form.telefone_contato) {
      toast.error('Dados incompletos. Volte e verifique os campos obrigatórios.');
      return;
    }
    try {
      setLoading(true);
      await api.post('/portal/imoveis/solicitar', {
        tipo_imovel: form.tipo_imovel,
        finalidade: form.finalidade,
        cep: form.cep,
        cidade: form.cidade,
        bairro: form.bairro,
        area: form.area ? Number(form.area) : undefined,
        dormitorios: form.dormitorios ? Number(form.dormitorios) : undefined,
        valor_pretendido: form.valor_pretendido ? Number(form.valor_pretendido.replace(/\D/g, '')) : undefined,
        nome_contato: form.nome_contato,
        telefone_contato: form.telefone_contato,
        email_contato: form.email_contato,
        observacoes: form.observacoes,
      });
      setSubmitted(true);
    } catch (error: any) {
      const msg = error?.response?.data?.error || 'Erro ao enviar. Tente novamente.';
      toast.error(msg);
    } finally {
      setLoading(false);
    }
  };

  const inputCls =
    'h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-900 outline-none focus:border-slate-400 focus:ring-0 transition-colors';
  const labelCls = 'mb-1 block text-xs font-semibold uppercase tracking-[0.1em] text-slate-500';

  const slideVariants = {
    enter: (dir: number) => ({ x: dir > 0 ? 60 : -60, opacity: 0 }),
    center: { x: 0, opacity: 1 },
    exit: (dir: number) => ({ x: dir > 0 ? -60 : 60, opacity: 0 }),
  };

  const reviewItem = (icon: React.ReactNode, label: string, value: string) =>
    value ? (
      <div className="flex items-start gap-3 rounded-xl border border-slate-100 bg-slate-50 px-4 py-3">
        <span className="mt-0.5 shrink-0 text-slate-400">{icon}</span>
        <div className="min-w-0">
          <p className="text-[10px] font-semibold uppercase tracking-[0.1em] text-slate-400">{label}</p>
          <p className="mt-0.5 text-sm text-slate-800 break-words">{value}</p>
        </div>
      </div>
    ) : null;

  return (
    <div
      className="min-h-screen px-4 py-10"
      style={{ background: 'linear-gradient(150deg, #ece7dd 0%, #f7f4ee 55%, #e6dfd4 100%)' }}
    >
      <div className="mx-auto max-w-2xl">
        <button
          type="button"
          onClick={() => navigate('/portal')}
          className="text-xs uppercase tracking-[0.14em] text-slate-700 hover:text-slate-900"
        >
          ← Voltar ao portal
        </button>

        <motion.div
          initial={{ opacity: 0, y: 12 }}
          animate={{ opacity: 1, y: 0 }}
          className="mt-5 rounded-3xl border border-black/10 bg-white shadow-[0_16px_42px_rgba(15,23,42,0.10)] overflow-hidden"
        >
          {/* Header */}
          <div className="px-6 pt-6 pb-0">
            <div className="mb-5 flex items-center gap-3">
              {logoSrc ? (
                <img
                  src={logoSrc}
                  alt={tenant?.name || 'Logo'}
                  className="h-12 w-12 rounded-xl border border-black/10 bg-white object-contain p-1.5"
                />
              ) : (
                <div
                  className="flex h-12 w-12 items-center justify-center rounded-xl text-xs font-semibold text-white"
                  style={{ backgroundColor: primary }}
                >
                  {(tenant?.name || 'IM').slice(0, 2).toUpperCase()}
                </div>
              )}
              <div>
                <p className="text-xs uppercase tracking-[0.2em] text-slate-500">{tenant?.name || 'Portal do Cliente'}</p>
                <p className="text-[11px] uppercase tracking-[0.14em]" style={{ color: secondary }}>
                  Quero vender meu imóvel
                </p>
              </div>
            </div>
          </div>

          {/* NOT AUTHENTICATED */}
          {!isAuthenticated && (
            <div className="p-6 py-10 text-center">
              <div
                className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl"
                style={{ backgroundColor: `${primary}15` }}
              >
                <Lock className="h-7 w-7" style={{ color: primary }} />
              </div>
              <h2 className="text-xl font-semibold text-slate-900">Acesso necessário</h2>
              <p className="mt-2 text-sm text-slate-500">
                Para cadastrar seu imóvel gratuitamente, faça login ou registre-se no portal.
              </p>
              <div className="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-center">
                <button
                  type="button"
                  onClick={() => navigate('/portal/login?redirect=/portal/vender')}
                  className="inline-flex items-center justify-center gap-2 rounded-xl px-6 py-3 text-sm font-semibold text-white"
                  style={{ backgroundColor: primary }}
                >
                  <LogIn className="h-4 w-4" />
                  Entrar
                </button>
                <button
                  type="button"
                  onClick={() => navigate('/portal/register?redirect=/portal/vender')}
                  className="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 px-6 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                >
                  Criar conta gratuita
                </button>
              </div>
            </div>
          )}

          {/* SUCCESS */}
          {isAuthenticated && submitted && (
            <div className="p-6 py-10 text-center">
              <div
                className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl"
                style={{ backgroundColor: `${secondary}20` }}
              >
                <CheckCircle2 className="h-8 w-8" style={{ color: secondary }} />
              </div>
              <h2 className="text-xl font-semibold text-slate-900">Solicitação enviada!</h2>
              <p className="mt-2 text-sm text-slate-600 max-w-sm mx-auto">
                Seu imóvel foi enviado para análise. Nossa equipe de corretores avaliará as informações e entrará em contato em breve.
              </p>
              <button
                type="button"
                onClick={() => navigate('/portal')}
                className="mt-6 inline-flex items-center gap-2 rounded-xl px-6 py-3 text-sm font-semibold text-white"
                style={{ backgroundColor: primary }}
              >
                Voltar ao portal
                <ArrowRight className="h-4 w-4" />
              </button>
            </div>
          )}

          {/* WIZARD */}
          {isAuthenticated && !submitted && (
            <>
              {/* Step indicator */}
              <div className="px-6">
                <div className="flex items-center gap-0 mb-6">
                  {STEPS.map((s, i) => {
                    const StepIcon = s.icon;
                    const isActive = s.id === step;
                    const isDone = s.id < step;
                    return (
                      <div key={s.id} className="flex items-center flex-1 last:flex-none">
                        <div className="flex flex-col items-center">
                          <div
                            className="w-9 h-9 rounded-full flex items-center justify-center transition-all duration-300 text-xs font-semibold"
                            style={{
                              backgroundColor: isDone
                                ? `${secondary}20`
                                : isActive
                                ? primary
                                : '#f1f5f9',
                              color: isDone ? secondary : isActive ? '#fff' : '#94a3b8',
                              border: isActive ? `2px solid ${primary}` : isDone ? `2px solid ${secondary}40` : '2px solid #e2e8f0',
                            }}
                          >
                            {isDone ? <CheckCircle2 className="w-4 h-4" /> : <StepIcon className="w-4 h-4" />}
                          </div>
                          <span
                            className="mt-1 text-[10px] font-semibold uppercase tracking-[0.08em] whitespace-nowrap"
                            style={{ color: isActive ? primary : isDone ? secondary : '#94a3b8' }}
                          >
                            {s.label}
                          </span>
                        </div>
                        {i < STEPS.length - 1 && (
                          <div
                            className="flex-1 h-0.5 mx-1.5 mb-4 rounded-full transition-all duration-300"
                            style={{ backgroundColor: s.id < step ? `${secondary}50` : '#e2e8f0' }}
                          />
                        )}
                      </div>
                    );
                  })}
                </div>

                {/* Progress bar */}
                <div className="mb-5 h-1 w-full rounded-full bg-slate-100">
                  <div
                    className="h-full rounded-full transition-all duration-500"
                    style={{ width: `${((step - 1) / (STEPS.length - 1)) * 100}%`, backgroundColor: secondary }}
                  />
                </div>
              </div>

              {/* Info banner */}
              <div className="mx-6 mb-4 flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5">
                <Building2 className="h-4 w-4 shrink-0 text-amber-600" />
                <p className="text-xs text-amber-800">Avaliação e cadastro gratuitos. Sem taxas ou compromissos iniciais.</p>
              </div>

              {/* Animated step content */}
              <div className="px-6 pb-6 overflow-hidden min-h-[340px]">
                <AnimatePresence mode="wait" custom={direction}>
                  <motion.div
                    key={step}
                    custom={direction}
                    variants={slideVariants}
                    initial="enter"
                    animate="center"
                    exit="exit"
                    transition={{ duration: 0.28, ease: 'easeInOut' }}
                  >

                    {/* STEP 1: Contato */}
                    {step === 1 && (
                      <div className="space-y-4">
                        <div>
                          <h2 className="text-2xl text-slate-900">Seus dados de contato</h2>
                          <p className="mt-1 text-sm text-slate-500">
                            Informe seu CPF para buscar seus dados automaticamente — ou preencha diretamente.
                          </p>
                        </div>

                        <div>
                          <label className={labelCls}>CPF (opcional — para busca automática)</label>
                          <div className="flex gap-2">
                            <input
                              type="text"
                              value={form.cpf}
                              onChange={(e) =>
                                setForm((prev) => ({ ...prev, cpf: formatCpf(e.target.value) }))
                              }
                              className={`${inputCls} flex-1`}
                              placeholder="000.000.000-00"
                              maxLength={14}
                            />
                            <button
                              type="button"
                              onClick={handleCpfLookup}
                              disabled={cpfLookupLoading}
                              className="inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-sm font-semibold disabled:opacity-60 transition-opacity whitespace-nowrap"
                              style={{ backgroundColor: secondary, color: '#111827' }}
                            >
                              {cpfLookupLoading ? (
                                <Loader2 className="h-4 w-4 animate-spin" />
                              ) : (
                                <Search className="h-4 w-4" />
                              )}
                              Buscar
                            </button>
                          </div>
                          <p className="mt-1 text-[11px] text-slate-400">
                            Seus dados são pré-preenchidos mas você pode editar livremente.
                          </p>
                        </div>

                        <label className="block">
                          <span className={labelCls}>Nome completo *</span>
                          <input
                            type="text"
                            value={form.nome_contato}
                            onChange={set('nome_contato')}
                            className={inputCls}
                            placeholder="João Silva"
                            required
                          />
                        </label>

                        <div className="grid grid-cols-2 gap-4">
                          <label className="block">
                            <span className={labelCls}>Telefone / WhatsApp *</span>
                            <input
                              type="tel"
                              value={form.telefone_contato}
                              onChange={(e) =>
                                setForm((prev) => ({ ...prev, telefone_contato: formatPhone(e.target.value) }))
                              }
                              className={inputCls}
                              placeholder="(11) 99999-9999"
                              required
                            />
                          </label>
                          <label className="block">
                            <span className={labelCls}>E-mail</span>
                            <input
                              type="email"
                              value={form.email_contato}
                              onChange={set('email_contato')}
                              className={inputCls}
                              placeholder="joao@email.com"
                            />
                          </label>
                        </div>
                      </div>
                    )}

                    {/* STEP 2: Imóvel */}
                    {step === 2 && (
                      <div className="space-y-5">
                        <div>
                          <h2 className="text-2xl text-slate-900">Sobre o imóvel</h2>
                          <p className="mt-1 text-sm text-slate-500">Selecione o tipo e informe a localização.</p>
                        </div>

                        <div>
                          <span className={labelCls}>Tipo de imóvel *</span>
                          <div className="grid grid-cols-3 gap-2 mt-1">
                            {PROPERTY_TYPES.map((pt) => (
                              <button
                                key={pt.value}
                                type="button"
                                onClick={() => setForm((prev) => ({ ...prev, tipo_imovel: pt.value }))}
                                className="flex flex-col items-center gap-1.5 rounded-xl border py-3 px-2 text-xs font-semibold transition-all duration-200"
                                style={
                                  form.tipo_imovel === pt.value
                                    ? { borderColor: primary, backgroundColor: `${primary}08`, color: primary }
                                    : { borderColor: '#e2e8f0', color: '#64748b' }
                                }
                              >
                                <span className="text-2xl">{pt.icon}</span>
                                {pt.label}
                              </button>
                            ))}
                          </div>
                        </div>

                        <div>
                          <span className={labelCls}>Interesse</span>
                          <div className="flex gap-2 mt-1 flex-wrap">
                            {PURPOSES.map((p) => (
                              <button
                                key={p.value}
                                type="button"
                                onClick={() => setForm((prev) => ({ ...prev, finalidade: p.value }))}
                                className="rounded-full border px-4 py-2 text-xs font-semibold transition-all duration-200"
                                style={
                                  form.finalidade === p.value
                                    ? { borderColor: secondary, backgroundColor: `${secondary}15`, color: '#111827' }
                                    : { borderColor: '#e2e8f0', color: '#64748b' }
                                }
                              >
                                {p.label}
                              </button>
                            ))}
                          </div>
                        </div>

                        <div>
                          <label className={`${labelCls} flex items-center gap-1.5`}>
                            CEP
                            {cepLoading && <Loader2 className="h-3 w-3 animate-spin text-slate-400" />}
                          </label>
                          <input
                            type="text"
                            value={form.cep}
                            onChange={(e) =>
                              setForm((prev) => ({ ...prev, cep: formatCep(e.target.value) }))
                            }
                            onBlur={handleCepBlur}
                            className={inputCls}
                            placeholder="00000-000"
                            maxLength={9}
                          />
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                          <label className="block">
                            <span className={labelCls}>Cidade *</span>
                            <input
                              type="text"
                              value={form.cidade}
                              onChange={set('cidade')}
                              className={inputCls}
                              placeholder="Ex: São Paulo"
                              required
                            />
                          </label>
                          <label className="block">
                            <span className={labelCls}>Bairro</span>
                            <input
                              type="text"
                              value={form.bairro}
                              onChange={set('bairro')}
                              className={inputCls}
                              placeholder="Ex: Jardins"
                            />
                          </label>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                          <label className="block">
                            <span className={labelCls}>Área (m²)</span>
                            <input
                              type="number"
                              min={0}
                              value={form.area}
                              onChange={set('area')}
                              className={inputCls}
                              placeholder="120"
                            />
                          </label>
                          <label className="block">
                            <span className={labelCls}>Dormitórios</span>
                            <input
                              type="number"
                              min={0}
                              max={20}
                              value={form.dormitorios}
                              onChange={set('dormitorios')}
                              className={inputCls}
                              placeholder="3"
                            />
                          </label>
                        </div>
                      </div>
                    )}

                    {/* STEP 3: Valores */}
                    {step === 3 && (
                      <div className="space-y-5">
                        <div>
                          <h2 className="text-2xl text-slate-900">Valores e expectativas</h2>
                          <p className="mt-1 text-sm text-slate-500">
                            Quanto você pretende receber? (Opcional — nossa equipe também pode avaliar.)
                          </p>
                        </div>

                        <label className="block">
                          <span className={labelCls}>Valor pretendido (R$)</span>
                          <input
                            type="text"
                            value={form.valor_pretendido}
                            onChange={(e) =>
                              setForm((prev) => ({ ...prev, valor_pretendido: formatCurrency(e.target.value) }))
                            }
                            className={inputCls}
                            placeholder="R$ 500.000"
                          />
                          <p className="mt-1 text-[11px] text-slate-400">
                            Se não souber, deixe em branco. A avaliação é gratuita!
                          </p>
                        </label>

                        <label className="block">
                          <span className={labelCls}>Observações adicionais</span>
                          <textarea
                            value={form.observacoes}
                            onChange={set('observacoes')}
                            rows={5}
                            className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none focus:border-slate-400 resize-none transition-colors"
                            placeholder="Descreva detalhes do imóvel, reformas recentes, melhor horário para visita, etc."
                          />
                        </label>

                        <div
                          className="rounded-2xl p-4 flex items-start gap-3"
                          style={{ backgroundColor: `${primary}08`, borderLeft: `3px solid ${primary}` }}
                        >
                          <span className="text-2xl">🏆</span>
                          <div>
                            <p className="text-sm font-semibold text-slate-800">Avaliação gratuita inclusa</p>
                            <p className="mt-0.5 text-xs text-slate-500">
                              Nossa equipe de corretores avaliará seu imóvel no mercado atual sem nenhum custo. Você recebe o laudo em até 48h.
                            </p>
                          </div>
                        </div>
                      </div>
                    )}

                    {/* STEP 4: Revisão */}
                    {step === 4 && (
                      <div className="space-y-4">
                        <div>
                          <h2 className="text-2xl text-slate-900">Revisar e confirmar</h2>
                          <p className="mt-1 text-sm text-slate-500">Confira os dados antes de enviar para nossa equipe.</p>
                        </div>

                        <div className="space-y-2">
                          <p className="text-xs font-semibold uppercase tracking-[0.1em] text-slate-400">Contato</p>
                          {reviewItem(<User className="w-4 h-4" />, 'Nome', form.nome_contato)}
                          {reviewItem(<Phone className="w-4 h-4" />, 'Telefone / WhatsApp', form.telefone_contato)}
                          {reviewItem(<Mail className="w-4 h-4" />, 'E-mail', form.email_contato)}
                        </div>

                        <div className="space-y-2">
                          <p className="text-xs font-semibold uppercase tracking-[0.1em] text-slate-400">O Imóvel</p>
                          {reviewItem(
                            <Home className="w-4 h-4" />,
                            'Tipo',
                            PROPERTY_TYPES.find((pt) => pt.value === form.tipo_imovel)?.label || form.tipo_imovel
                          )}
                          {reviewItem(
                            <Tag className="w-4 h-4" />,
                            'Finalidade',
                            PURPOSES.find((p) => p.value === form.finalidade)?.label || form.finalidade
                          )}
                          {reviewItem(
                            <MapPin className="w-4 h-4" />,
                            'Localização',
                            [form.bairro, form.cidade, form.cep].filter(Boolean).join(' · ')
                          )}
                          {reviewItem(<Square className="w-4 h-4" />, 'Área', form.area ? `${form.area} m²` : '')}
                          {reviewItem(<BedDouble className="w-4 h-4" />, 'Dormitórios', form.dormitorios)}
                        </div>

                        {(form.valor_pretendido || form.observacoes) && (
                          <div className="space-y-2">
                            <p className="text-xs font-semibold uppercase tracking-[0.1em] text-slate-400">Valores</p>
                            {reviewItem(<DollarSign className="w-4 h-4" />, 'Valor pretendido', form.valor_pretendido)}
                            {reviewItem(<ClipboardList className="w-4 h-4" />, 'Observações', form.observacoes)}
                          </div>
                        )}

                        <button
                          type="button"
                          onClick={handleSubmit}
                          disabled={loading}
                          className="flex w-full items-center justify-center gap-2 rounded-xl py-3.5 text-sm font-semibold text-white transition-opacity disabled:opacity-60 mt-2"
                          style={{ backgroundColor: primary }}
                        >
                          {loading ? (
                            <Loader2 className="h-4 w-4 animate-spin" />
                          ) : (
                            <>
                              Enviar para análise
                              <ArrowRight className="h-4 w-4" />
                            </>
                          )}
                        </button>
                      </div>
                    )}
                  </motion.div>
                </AnimatePresence>
              </div>

              {/* Navigation buttons */}
              {step < 4 && (
                <div className="flex items-center justify-between border-t border-slate-100 px-6 py-4">
                  {step > 1 ? (
                    <button
                      type="button"
                      onClick={goBack}
                      className="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                      <ArrowLeft className="h-4 w-4" />
                      Voltar
                    </button>
                  ) : (
                    <div />
                  )}
                  <button
                    type="button"
                    onClick={goNext}
                    className="inline-flex items-center gap-2 rounded-xl px-5 py-2.5 text-sm font-semibold text-white transition-opacity"
                    style={{ backgroundColor: primary }}
                  >
                    Continuar
                    <ArrowRight className="h-4 w-4" />
                  </button>
                </div>
              )}
              {step === 4 && (
                <div className="flex items-center border-t border-slate-100 px-6 py-4">
                  <button
                    type="button"
                    onClick={goBack}
                    className="inline-flex items-center gap-2 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                  >
                    <ArrowLeft className="h-4 w-4" />
                    Voltar
                  </button>
                </div>
              )}
            </>
          )}
        </motion.div>
      </div>
    </div>
  );
}
