import { FormEvent, useEffect, useState } from 'react';
import { useLocation } from 'wouter';
import { motion } from 'framer-motion';
import { ArrowRight, Building2, CheckCircle2, Lock, LogIn } from 'lucide-react';
import { toast } from 'sonner';
import { api } from '@/lib/api';
import { fetchTenantBranding, TenantBranding } from '@/lib/tenantBranding';

interface FormState {
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

export default function PortalVender() {
  const [, navigate] = useLocation();
  const [tenant, setTenant] = useState<TenantBranding | null>(null);
  const [isAuthenticated, setIsAuthenticated] = useState(false);
  const [form, setForm] = useState<FormState>(EMPTY_FORM);
  const [loading, setLoading] = useState(false);
  const [submitted, setSubmitted] = useState(false);

  const primary = tenant?.primary_color || '#0f172a';
  const secondary = tenant?.secondary_color || '#b9935a';
  const logoSrc = tenant?.logo_url || tenant?.logo || '';

  useEffect(() => {
    fetchTenantBranding().then((data) => setTenant(data));
    const token = localStorage.getItem('token');
    setIsAuthenticated(Boolean(token));
  }, []);

  const set = (field: keyof FormState) => (
    e: React.ChangeEvent<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>
  ) => setForm((prev) => ({ ...prev, [field]: e.target.value }));

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault();
    if (!form.tipo_imovel || !form.cidade || !form.nome_contato || !form.telefone_contato) {
      toast.error('Preencha os campos obrigatórios: tipo, cidade, nome e telefone.');
      return;
    }
    try {
      setLoading(true);
      await api.post('/portal/imoveis/solicitar', {
        ...form,
        area: form.area ? Number(form.area) : undefined,
        dormitorios: form.dormitorios ? Number(form.dormitorios) : undefined,
        valor_pretendido: form.valor_pretendido ? Number(form.valor_pretendido.replace(/\D/g, '')) : undefined,
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
    'h-11 w-full rounded-xl border border-slate-200 bg-white px-3 text-sm text-slate-900 outline-none focus:border-slate-400 focus:ring-0';
  const labelCls = 'mb-1 block text-xs uppercase tracking-[0.1em] text-slate-500';

  return (
    <div
      className="min-h-screen px-4 py-10"
      style={{ background: 'linear-gradient(150deg, #ece7dd 0%, #f7f4ee 55%, #e6dfd4 100%)' }}
    >
      <div className="mx-auto max-w-2xl">
        {/* Back */}
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
          className="mt-5 rounded-3xl border border-black/10 bg-white p-6 shadow-[0_16px_42px_rgba(15,23,42,0.10)]"
        >
          {/* Header */}
          <div className="mb-6 flex items-center gap-3">
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
              <p className="text-xs uppercase tracking-[0.2em] text-slate-500">
                {tenant?.name || 'Portal do Cliente'}
              </p>
              <p className="text-[11px] uppercase tracking-[0.14em]" style={{ color: secondary }}>
                Quero vender meu imóvel
              </p>
            </div>
          </div>

          {/* ─── NOT AUTHENTICATED ─── */}
          {!isAuthenticated && (
            <div className="py-6 text-center">
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
                  onClick={() => navigate('/portal/register')}
                  className="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-200 px-6 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                >
                  Criar conta gratuita
                </button>
              </div>
            </div>
          )}

          {/* ─── SUCCESS ─── */}
          {isAuthenticated && submitted && (
            <div className="py-6 text-center">
              <div
                className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-2xl"
                style={{ backgroundColor: `${secondary}20` }}
              >
                <CheckCircle2 className="h-8 w-8" style={{ color: secondary }} />
              </div>
              <h2 className="text-xl font-semibold text-slate-900">Solicitação enviada!</h2>
              <p className="mt-2 text-sm text-slate-600">
                Seu imóvel foi enviado para análise. Nossa equipe de corretores avaliará as informações
                e entrará em contato em breve.
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

          {/* ─── FORM ─── */}
          {isAuthenticated && !submitted && (
            <>
              <div className="mb-4 flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                <Building2 className="h-4 w-4 shrink-0 text-amber-600" />
                <p className="text-xs text-amber-800">
                  Após o envio, seus dados serão avaliados por nossa equipe antes da publicação. Não
                  cobramos nada por isso.
                </p>
              </div>

              <h1 className="mb-1 text-2xl text-slate-900">Cadastrar imóvel para venda</h1>
              <p className="mb-6 text-sm text-slate-500">
                Preencha as informações abaixo e nossa equipe entrará em contato.
              </p>

              <form onSubmit={handleSubmit} className="space-y-4">
                {/* Tipo + Finalidade */}
                <div className="grid grid-cols-2 gap-4">
                  <label className="block">
                    <span className={labelCls}>Tipo de imóvel *</span>
                    <select value={form.tipo_imovel} onChange={set('tipo_imovel')} className={inputCls} required>
                      <option value="">Selecione...</option>
                      <option value="casa">Casa</option>
                      <option value="apartamento">Apartamento</option>
                      <option value="terreno">Terreno</option>
                      <option value="comercial">Imóvel Comercial</option>
                      <option value="rural">Rural / Fazenda</option>
                      <option value="galpao">Galpão</option>
                      <option value="outro">Outro</option>
                    </select>
                  </label>
                  <label className="block">
                    <span className={labelCls}>Interesse</span>
                    <select value={form.finalidade} onChange={set('finalidade')} className={inputCls}>
                      <option value="venda">Venda</option>
                      <option value="aluguel">Aluguel</option>
                      <option value="venda_aluguel">Venda ou Aluguel</option>
                    </select>
                  </label>
                </div>

                {/* CEP */}
                <label className="block">
                  <span className={labelCls}>CEP</span>
                  <input
                    type="text"
                    value={form.cep}
                    onChange={set('cep')}
                    className={inputCls}
                    placeholder="00000-000"
                    maxLength={9}
                  />
                </label>

                {/* Local */}
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

                {/* Área + Dorm + Valor */}
                <div className="grid grid-cols-3 gap-4">
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
                  <label className="block">
                    <span className={labelCls}>Valor pretendido (R$)</span>
                    <input
                      type="text"
                      value={form.valor_pretendido}
                      onChange={set('valor_pretendido')}
                      className={inputCls}
                      placeholder="500000"
                    />
                  </label>
                </div>

                <hr className="border-slate-100" />
                <p className="text-xs font-semibold uppercase tracking-[0.12em] text-slate-400">
                  Seus dados de contato
                </p>

                {/* Contato */}
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
                      onChange={set('telefone_contato')}
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

                <label className="block">
                  <span className={labelCls}>Observações adicionais</span>
                  <textarea
                    value={form.observacoes}
                    onChange={set('observacoes')}
                    rows={3}
                    className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm text-slate-900 outline-none focus:border-slate-400 resize-none"
                    placeholder="Informe detalhes do imóvel, melhor horário para contato, etc."
                  />
                </label>

                <button
                  type="submit"
                  disabled={loading}
                  className="flex w-full items-center justify-center gap-2 rounded-xl py-3 text-sm font-semibold text-white transition-opacity disabled:opacity-60"
                  style={{ backgroundColor: primary }}
                >
                  {loading ? 'Enviando...' : 'Enviar para análise'}
                  {!loading && <ArrowRight className="h-4 w-4" />}
                </button>
              </form>
            </>
          )}
        </motion.div>
      </div>
    </div>
  );
}
