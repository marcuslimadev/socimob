import { FormEvent, useEffect, useState } from 'react';
import { useLocation } from 'wouter';
import { motion } from 'framer-motion';
import { ArrowRight, Lock, Mail } from 'lucide-react';
import { toast } from 'sonner';
import { api } from '@/lib/api';
import { fetchTenantBranding, TenantBranding } from '@/lib/tenantBranding';

export default function PortalLogin() {
  const [, navigate] = useLocation();
  const [tenant, setTenant] = useState<TenantBranding | null>(null);
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);

  const primary = tenant?.primary_color || '#0f172a';
  const secondary = tenant?.secondary_color || '#c39a66';

  useEffect(() => {
    fetchTenantBranding().then((data) => setTenant(data));
  }, []);

  const handleSubmit = async (event: FormEvent) => {
    event.preventDefault();
    if (!email || !password) {
      toast.error('Preencha email e senha.');
      return;
    }

    try {
      setLoading(true);
      const response = await api.post('/portal/auth/login', { email, password });
      const token = response.data?.token;
      const user = response.data?.user;
      if (!token || !user) {
        toast.error('Resposta invalida ao autenticar.');
        return;
      }

      localStorage.setItem('token', token);
      localStorage.setItem('user', JSON.stringify(user));
      toast.success('Login realizado.');
      navigate('/portal/meu-financeiro');
    } catch (error: any) {
      toast.error(error?.response?.data?.message || 'Nao foi possivel entrar.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="min-h-screen bg-[#f4efe8] px-4 py-10">
      <div className="mx-auto max-w-md">
        <button type="button" onClick={() => navigate('/portal')} className="text-xs uppercase tracking-[0.14em] text-slate-600">
          Voltar ao portal
        </button>

        <motion.div initial={{ opacity: 0, y: 12 }} animate={{ opacity: 1, y: 0 }} className="mt-5 rounded-3xl border border-black/10 bg-white p-6 shadow-[0_16px_42px_rgba(15,23,42,0.10)]">
          <p className="text-xs uppercase tracking-[0.2em] text-slate-500">{tenant?.name || 'Portal do Cliente'}</p>
          <h1 className="mt-2 text-3xl text-slate-900">Entrar</h1>
          <p className="mt-1 text-sm text-slate-500">Acesse seu painel de locatario/locador.</p>

          <form onSubmit={handleSubmit} className="mt-6 space-y-4">
            <label className="block">
              <span className="mb-1 block text-xs uppercase tracking-[0.1em] text-slate-500">Email</span>
              <div className="relative">
                <Mail className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                  type="email"
                  value={email}
                  onChange={(event) => setEmail(event.target.value)}
                  className="h-11 w-full rounded-xl border border-slate-200 pl-10 pr-3 text-sm outline-none focus:border-slate-400"
                  placeholder="seu@email.com"
                  autoComplete="email"
                />
              </div>
            </label>

            <label className="block">
              <span className="mb-1 block text-xs uppercase tracking-[0.1em] text-slate-500">Senha</span>
              <div className="relative">
                <Lock className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                  type="password"
                  value={password}
                  onChange={(event) => setPassword(event.target.value)}
                  className="h-11 w-full rounded-xl border border-slate-200 pl-10 pr-3 text-sm outline-none focus:border-slate-400"
                  placeholder="Sua senha"
                  autoComplete="current-password"
                />
              </div>
            </label>

            <button
              type="submit"
              disabled={loading}
              className="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl text-sm font-semibold disabled:opacity-60"
              style={{ backgroundColor: primary, color: secondary }}
            >
              {loading ? 'Entrando...' : 'Entrar no painel'}
              {!loading && <ArrowRight className="h-4 w-4" />}
            </button>
          </form>

          <p className="mt-4 text-sm text-slate-500">
            Primeiro acesso?{' '}
            <button type="button" onClick={() => navigate('/portal/register')} className="font-semibold text-slate-800 underline underline-offset-4">
              Registrar conta
            </button>
          </p>
        </motion.div>
      </div>
    </div>
  );
}
