import { FormEvent, useEffect, useRef, useState } from 'react';
import { useLocation } from 'wouter';
import { motion } from 'framer-motion';
import { ArrowRight, Lock, Mail } from 'lucide-react';
import { toast } from 'sonner';
import { api } from '@/lib/api';
import { fetchTenantBranding, TenantBranding } from '@/lib/tenantBranding';
import {
  getGoogleIdentityUnavailableMessage,
  initializeGoogleIdentity,
  isGoogleIdentitySupportedOrigin,
  renderGoogleIdentityButton,
} from '@/lib/googleIdentity';

export default function PortalLogin() {
  const [, navigate] = useLocation();
  const [tenant, setTenant] = useState<TenantBranding | null>(null);
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [loading, setLoading] = useState(false);

  const primary = tenant?.primary_color || '#0f172a';
  const secondary = tenant?.secondary_color || '#b9935a';
  const logoSrc = tenant?.logo_url || tenant?.logo || '';
  const googleBtnRef = useRef<HTMLDivElement>(null);
  const googleAvailable = Boolean(import.meta.env.VITE_GOOGLE_CLIENT_ID) && isGoogleIdentitySupportedOrigin();

  useEffect(() => {
    fetchTenantBranding().then((data: TenantBranding | null) => setTenant(data));
  }, []);

  useEffect(() => {
    const clientId = import.meta.env.VITE_GOOGLE_CLIENT_ID;
    if (!clientId || !googleBtnRef.current) return;
    if (!initializeGoogleIdentity(clientId, handleGoogleCredential)) return;

    renderGoogleIdentityButton(googleBtnRef.current, {
      theme: 'outline',
      size: 'large',
      width: googleBtnRef.current.offsetWidth || 360,
      text: 'continue_with',
      locale: 'pt-BR',
    });
  }, [tenant]);

  const handleGoogleCredential = async (response: { credential: string }) => {
    if (!response?.credential) {
      toast.error('Resposta inválida ao entrar com Google.');
      return;
    }

    try {
      setLoading(true);
      const res = await api.post('/auth/google', { token: response.credential });
      const token = res.data?.token;
      const user = res.data?.user;
      if (!token || !user) {
        toast.error('Resposta inválida ao autenticar com Google.');
        return;
      }
      localStorage.setItem('token', token);
      localStorage.setItem('user', JSON.stringify(user));
      toast.success('Login com Google realizado!');
      const params = new URLSearchParams(window.location.search);
      const redirectTo = params.get('redirect') || '/portal/meu-financeiro';
      navigate(redirectTo);
    } catch (error: any) {
      toast.error(error?.response?.data?.message || 'Erro ao entrar com Google.');
    } finally {
      setLoading(false);
    }
  };

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
        toast.error('Resposta inválida ao autenticar.');
        return;
      }

      localStorage.setItem('token', token);
      localStorage.setItem('user', JSON.stringify(user));
      toast.success('Login realizado.');
      const params = new URLSearchParams(window.location.search);
      const redirectTo = params.get('redirect') || '/portal/meu-financeiro';
      navigate(redirectTo);
    } catch (error: any) {
      toast.error(error?.response?.data?.message || 'Não foi possível entrar.');
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
              <p className="text-[11px] uppercase tracking-[0.14em]" style={{ color: secondary }}>Acesso seguro</p>
            </div>
          </div>
          <h1 className="mt-2 text-3xl text-slate-900">Entrar</h1>
          <p className="mt-1 text-sm text-slate-500">Acesse seu painel de locatário/locador.</p>

          <form onSubmit={handleSubmit} className="mt-6 space-y-4">
            <label className="block">
              <span className="mb-1 block text-xs uppercase tracking-[0.1em] text-slate-500">Email</span>
              <div className="relative">
                <Mail className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                <input
                  type="email"
                  value={email}
                  onChange={(event) => setEmail(event.target.value)}
                  className="h-11 w-full rounded-xl border border-slate-200 bg-white pl-10 pr-3 text-sm text-slate-900 outline-none focus:border-slate-400"
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
                  className="h-11 w-full rounded-xl border border-slate-200 bg-white pl-10 pr-3 text-sm text-slate-900 outline-none focus:border-slate-400"
                  placeholder="Sua senha"
                  autoComplete="current-password"
                />
              </div>
            </label>

            <button
              type="submit"
              disabled={loading}
              className="inline-flex h-11 w-full items-center justify-center gap-2 rounded-xl text-sm font-semibold disabled:opacity-60"
              style={{ backgroundColor: primary, color: '#ffffff' }}
            >
              {loading ? 'Entrando...' : 'Entrar no painel'}
              {!loading && <ArrowRight className="h-4 w-4" />}
            </button>
          </form>

          {import.meta.env.VITE_GOOGLE_CLIENT_ID && (
            <>
              <div className="relative my-4 flex items-center">
                <div className="flex-1 border-t border-slate-200" />
                <span className="mx-3 text-xs text-slate-400">ou</span>
                <div className="flex-1 border-t border-slate-200" />
              </div>
              {googleAvailable ? (
                <div ref={googleBtnRef} className="w-full flex justify-center" />
              ) : (
                <p className="text-center text-xs text-slate-400">
                  {getGoogleIdentityUnavailableMessage()}
                </p>
              )}
            </>
          )}

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
