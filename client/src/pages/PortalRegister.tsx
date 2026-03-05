import { FormEvent, useEffect, useState } from 'react';
import { useLocation } from 'wouter';
import { motion } from 'framer-motion';
import { ArrowRight, Lock, Mail, Phone, UserRound } from 'lucide-react';
import { toast } from 'sonner';
import { api } from '@/lib/api';
import { fetchTenantBranding, TenantBranding } from '@/lib/tenantBranding';

export default function PortalRegister() {
  const [, navigate] = useLocation();
  const [tenant, setTenant] = useState<TenantBranding | null>(null);
  const [loading, setLoading] = useState(false);
  const [form, setForm] = useState({
    name: '',
    email: '',
    telefone: '',
    password: '',
    password_confirmation: '',
  });

  const primary = tenant?.primary_color || '#0f172a';
  const secondary = tenant?.secondary_color || '#b9935a';
  const logoSrc = tenant?.logo_url || tenant?.logo || '';

  useEffect(() => {
    fetchTenantBranding().then((data) => setTenant(data));
  }, []);

  const handleChange = (field: keyof typeof form, value: string) => {
    setForm((previous) => ({ ...previous, [field]: value }));
  };

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault();
    if (!form.name || !form.email || !form.password || !form.password_confirmation) {
      toast.error('Preencha os campos obrigatórios.');
      return;
    }
    if (form.password !== form.password_confirmation) {
      toast.error('A confirmação de senha não confere.');
      return;
    }

    const params = new URLSearchParams(window.location.search);
    const redirectTo = params.get('redirect') || '/portal/meu-financeiro';

    try {
      setLoading(true);
      const response = await api.post('/portal/auth/register', form);
      const token = response.data?.token;
      const user = response.data?.user;

      if (!token || !user) {
        toast.success('Conta criada. Faça login para continuar.');
        navigate('/portal/login');
        return;
      }

      localStorage.setItem('token', token);
      localStorage.setItem('user', JSON.stringify(user));
      toast.success('Cadastro realizado.');
      navigate(redirectTo);
    } catch (error: any) {
      const messages = error?.response?.data?.messages;
      const firstError = messages ? Object.values(messages)?.[0] : null;
      const text = Array.isArray(firstError) ? firstError[0] : null;
      toast.error(text || error?.response?.data?.message || 'Não foi possível registrar.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen px-4 py-10" style={{ background: 'linear-gradient(150deg, #ece7dd 0%, #f7f4ee 55%, #e6dfd4 100%)' }}>
      <div className="mx-auto max-w-md">
        <button type="button" onClick={() => navigate('/portal')} className="text-xs uppercase tracking-[0.14em] text-slate-700">
          Voltar ao portal
        </button>

        <motion.div initial={{ opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }} className="mt-5 rounded-3xl border border-black/10 bg-white p-6 shadow-[0_16px_42px_rgba(15,23,42,0.10)]">
          <div className="mb-4 flex items-center gap-3">
            {logoSrc ? (
              <img src={logoSrc} alt={tenant?.name || 'Logo'} className="h-12 w-12 rounded-xl border border-black/10 bg-white object-contain p-1.5" />
            ) : (
              <div className="flex h-12 w-12 items-center justify-center rounded-xl text-xs font-semibold text-white" style={{ backgroundColor: primary }}>
                {(tenant?.name || 'IM').slice(0, 2).toUpperCase()}
              </div>
            )}
            <div>
              <p className="text-xs uppercase tracking-[0.2em] text-slate-500">{tenant?.name || 'Portal do Cliente'}</p>
              <p className="text-[11px] uppercase tracking-[0.14em]" style={{ color: secondary }}>Criar acesso</p>
            </div>
          </div>
          <h1 className="mt-2 text-3xl text-slate-900">Registrar</h1>
          <p className="mt-1 text-sm text-slate-500">Crie seu acesso para acompanhar seu painel.</p>

          <form onSubmit={handleSubmit} className="mt-6 space-y-4">
            <label className="block">
              <span className="mb-1 block text-xs uppercase tracking-[0.1em] text-slate-500">Nome</span>
              <div className="relative">
                <UserRound className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                  type="text"
                  value={form.name}
                  onChange={(event) => handleChange('name', event.target.value)}
                  className="h-11 w-full rounded-xl border border-slate-200 bg-white pl-10 pr-3 text-sm text-slate-900 outline-none focus:border-slate-400"
                  placeholder="Seu nome completo"
                />
              </div>
            </label>

            <label className="block">
              <span className="mb-1 block text-xs uppercase tracking-[0.1em] text-slate-500">Email</span>
              <div className="relative">
                <Mail className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                  type="email"
                  value={form.email}
                  onChange={(event) => handleChange('email', event.target.value)}
                  className="h-11 w-full rounded-xl border border-slate-200 bg-white pl-10 pr-3 text-sm text-slate-900 outline-none focus:border-slate-400"
                  placeholder="seu@email.com"
                  autoComplete="email"
                />
              </div>
            </label>

            <label className="block">
              <span className="mb-1 block text-xs uppercase tracking-[0.1em] text-slate-500">Telefone (opcional)</span>
              <div className="relative">
                <Phone className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                  type="text"
                  value={form.telefone}
                  onChange={(event) => handleChange('telefone', event.target.value)}
                  className="h-11 w-full rounded-xl border border-slate-200 bg-white pl-10 pr-3 text-sm text-slate-900 outline-none focus:border-slate-400"
                  placeholder="(00) 00000-0000"
                />
              </div>
            </label>

            <label className="block">
              <span className="mb-1 block text-xs uppercase tracking-[0.1em] text-slate-500">Senha</span>
              <div className="relative">
                <Lock className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                  type="password"
                  value={form.password}
                  onChange={(event) => handleChange('password', event.target.value)}
                  className="h-11 w-full rounded-xl border border-slate-200 bg-white pl-10 pr-3 text-sm text-slate-900 outline-none focus:border-slate-400"
                  placeholder="Mínimo 6 caracteres"
                  autoComplete="new-password"
                />
              </div>
            </label>

            <label className="block">
              <span className="mb-1 block text-xs uppercase tracking-[0.1em] text-slate-500">Confirmar senha</span>
              <div className="relative">
                <Lock className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                  type="password"
                  value={form.password_confirmation}
                  onChange={(event) => handleChange('password_confirmation', event.target.value)}
                  className="h-11 w-full rounded-xl border border-slate-200 bg-white pl-10 pr-3 text-sm text-slate-900 outline-none focus:border-slate-400"
                  placeholder="Repita sua senha"
                  autoComplete="new-password"
                />
              </div>
            </label>

            <button
              type="submit"
              disabled={loading}
              className="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl text-sm font-semibold disabled:opacity-60"
              style={{ backgroundColor: primary, color: '#ffffff' }}
            >
              {loading ? 'Registrando...' : 'Criar conta'}
              {!loading && <ArrowRight className="h-4 w-4" />}
            </button>
          </form>

          <p className="mt-4 text-sm text-slate-500">
            Ja tem conta?{' '}
            <button type="button" onClick={() => navigate('/login')} className="font-semibold text-slate-800 underline underline-offset-4">
              Entrar
            </button>
          </p>
        </motion.div>
      </div>
    </div>
  );
}
