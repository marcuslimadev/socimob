import { useEffect, useRef, useState } from 'react';
import { useLocation } from 'wouter';
import { toast } from 'sonner';
import { api } from '@/lib/api';
import { fetchTenantBranding, hexToRgba, TenantBranding } from '@/lib/tenantBranding';
import { motion } from 'framer-motion';
import { Mail, Lock, Eye, EyeOff, ArrowRight } from 'lucide-react';

declare const google: any;

export default function Login() {
  const [showPassword, setShowPassword] = useState(false);
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [isLoading, setIsLoading] = useState(false);
  const [, setLocation] = useLocation();
  const [tenant, setTenant] = useState<TenantBranding | null>(null);

  useEffect(() => {
    const loadTenant = async () => {
      const data = await fetchTenantBranding();
      if (data) setTenant(data);
    };

    loadTenant();
  }, []);

  const getTenantInitials = (name?: string) => {
    if (!name) return 'S';
    const parts = name.trim().split(' ');
    if (parts.length >= 2) {
      return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }
    return name.slice(0, 2).toUpperCase();
  };

  const primary = tenant?.primary_color || '#091b42';
  const softBg = hexToRgba(primary, 0.12);
  const googleBtnRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    const clientId = import.meta.env.VITE_GOOGLE_CLIENT_ID;
    if (!clientId || !googleBtnRef.current) return;
    if (typeof google === 'undefined') return;

    google.accounts.id.initialize({
      client_id: clientId,
      callback: handleGoogleCredential,
    });
    google.accounts.id.renderButton(googleBtnRef.current, {
      theme: 'outline',
      size: 'large',
      width: googleBtnRef.current.offsetWidth || 360,
      text: 'continue_with',
      locale: 'pt-BR',
    });
  }, [tenant]);

  const handleGoogleCredential = async (response: { credential: string }) => {
    setIsLoading(true);
    try {
      const res = await api.post('/auth/google', { token: response.credential });
      if (res.data.token) {
        localStorage.setItem('token', res.data.token);
        localStorage.setItem('user', JSON.stringify(res.data.user));
        toast.success('Login com Google realizado!');
        const role = (res.data.user?.role || '').toLowerCase();
        if (role === 'admin' || role === 'super_admin' || role === 'corretor') {
          setLocation('/dashboard');
        } else {
          setLocation('/portal/meu-financeiro');
        }
      }
    } catch (error: any) {
      toast.error(error.response?.data?.message || 'Erro ao entrar com Google.');
    } finally {
      setIsLoading(false);
    }
  };

  const handleLogin = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!email || !password) {
      toast.error('Por favor, preencha todos os campos');
      return;
    }

    setIsLoading(true);

    try {
      const response = await api.post('/auth/login', {
        email,
        password
      });

      if (response.data.token) {
        localStorage.setItem('token', response.data.token);
        localStorage.setItem('user', JSON.stringify(response.data.user));
        toast.success('Login realizado com sucesso!');

        const role = (response.data.user?.role || '').toLowerCase();
        if (role === 'admin' || role === 'super_admin' || role === 'corretor') {
          setLocation('/dashboard');
        } else {
          setLocation('/portal/meu-financeiro');
        }
      } else {
        toast.error('Erro ao realizar login: Resposta inválida do servidor');
      }
    } catch (error: any) {
      console.error('Login error:', error);
      const errorMessage = error.response?.data?.message || 'Erro ao realizar login. Verifique suas credenciais.';
      toast.error(errorMessage);
    } finally {
      setIsLoading(false);
    }
  };

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: { staggerChildren: 0.1, delayChildren: 0.2 },
    },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: { opacity: 1, y: 0 },
  };

  return (
    <div
      className="min-h-screen flex items-center justify-center p-4"
      style={{ backgroundColor: softBg }}
    >
      <motion.div
        variants={containerVariants}
        initial="hidden"
        animate="visible"
        className="w-full max-w-md"
      >
        {/* Logo */}
        <motion.div variants={itemVariants} className="text-center mb-8">
          <motion.div
            initial={{ scale: 0, rotate: -180 }}
            animate={{ scale: 1, rotate: 0 }}
            transition={{ type: 'spring', stiffness: 200 }}
            className="w-16 h-16 rounded-2xl flex items-center justify-center text-white font-bold text-3xl mx-auto mb-4 overflow-hidden"
            style={{ backgroundColor: primary }}
          >
            {tenant?.logo_url || tenant?.logo ? (
              <img
                src={tenant.logo_url || tenant.logo}
                alt={tenant?.name || 'Logo'}
                className="w-full h-full object-contain bg-white/5 p-2"
              />
            ) : (
              getTenantInitials(tenant?.name)
            )}
          </motion.div>
          <h1 className="text-2xl sm:text-3xl font-bold text-foreground mb-2">
            {tenant?.name || 'SOCIMOB'}
          </h1>
          <p className="text-sm sm:text-base text-muted-foreground">
            {tenant?.slogan || 'Gestão Imobiliária Inteligente'}
          </p>
        </motion.div>

        {/* Login Card */}
        <motion.div
          variants={itemVariants}
          className="glass-panel p-6 sm:p-8 rounded-3xl mb-6"
        >
          <h2 className="text-xl sm:text-2xl font-bold text-foreground mb-6">Bem-vindo de volta</h2>

          <form onSubmit={handleLogin}>
            {/* Email Input */}
            <motion.div variants={itemVariants} className="mb-6">
              <label className="block text-sm font-semibold text-foreground mb-2">
                Email
              </label>
              <div className="relative">
                <Mail className="absolute left-4 top-1/2 -translate-y-1/2 text-muted-foreground" size={20} />
                <input
                  type="email"
                  value={email}
                  onChange={(e) => setEmail(e.target.value)}
                  placeholder="seu@email.com"
                  className="w-full pl-12 pr-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 transition-all"
                  disabled={isLoading}
                />
              </div>
            </motion.div>

            {/* Password Input */}
            <motion.div variants={itemVariants} className="mb-6">
              <label className="block text-sm font-semibold text-foreground mb-2">
                Senha
              </label>
              <div className="relative">
                <Lock className="absolute left-4 top-1/2 -translate-y-1/2 text-muted-foreground" size={20} />
                <input
                  type={showPassword ? 'text' : 'password'}
                  value={password}
                  onChange={(e) => setPassword(e.target.value)}
                  placeholder="••••••••"
                  className="w-full pl-12 pr-12 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground placeholder:text-muted-foreground focus:outline-none focus:ring-2 transition-all"
                  disabled={isLoading}
                />
                <motion.button
                  type="button"
                  whileHover={{ scale: 1.1 }}
                  whileTap={{ scale: 0.95 }}
                  onClick={() => setShowPassword(!showPassword)}
                  className="absolute right-4 top-1/2 -translate-y-1/2 text-muted-foreground hover:text-foreground transition-colors"
                  disabled={isLoading}
                >
                  {showPassword ? <EyeOff size={20} /> : <Eye size={20} />}
                </motion.button>
              </div>
            </motion.div>

            {/* Remember & Forgot */}
            <motion.div
              variants={itemVariants}
              className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between mb-6"
            >
              <label className="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  className="w-4 h-4 rounded bg-white/10 border border-white/20 cursor-pointer"
                  disabled={isLoading}
                />
                <span className="text-sm text-muted-foreground">Lembrar-me</span>
              </label>
              <a
                href="/forgot-password"
                className="text-sm transition-colors"
                style={{ color: primary }}
              >
                Esqueceu a senha?
              </a>
            </motion.div>

            {/* Login Button */}
            <motion.button
              type="submit"
              variants={itemVariants}
              whileHover={{ scale: 1.02 }}
              whileTap={{ scale: 0.98 }}
              className="w-full flex items-center justify-center gap-2 px-6 py-3 rounded-lg font-semibold text-white transition-all mb-4 disabled:opacity-50 disabled:cursor-not-allowed hover:opacity-90"
              style={{ backgroundColor: primary }}
              disabled={isLoading}
            >
              {isLoading ? 'Entrando...' : 'Entrar'}
              {!isLoading && <ArrowRight size={20} />}
            </motion.button>
          </form>

          {import.meta.env.VITE_GOOGLE_CLIENT_ID && (
            <>
              <div className="relative my-4 flex items-center">
                <div className="flex-1 border-t border-white/20" />
                <span className="mx-3 text-xs text-muted-foreground">ou</span>
                <div className="flex-1 border-t border-white/20" />
              </div>
              <div ref={googleBtnRef} className="w-full flex justify-center" />
            </>
          )}


        </motion.div>

        {/* Sign Up Link */}
        <motion.div variants={itemVariants} className="text-center">
          <p className="text-muted-foreground">
            Não tem uma conta?{' '}
            <a
              href="/portal/register"
              className="font-semibold transition-colors"
              style={{ color: primary }}
            >
              Crie uma agora
            </a>
          </p>
        </motion.div>

        {/* Footer */}
        <motion.div
          variants={itemVariants}
          className="mt-8 pt-6 border-t border-white/10 text-center text-xs text-muted-foreground"
        >
          <p>© 2026 {tenant?.name || 'SOCIMOB'}. Todos os direitos reservados.</p>
        </motion.div>
      </motion.div>

      {/* Background Decoration */}
      <div className="fixed inset-0 -z-10 overflow-hidden pointer-events-none">
        <div
          className="absolute top-0 right-0 w-96 h-96 rounded-full blur-3xl"
          style={{ backgroundColor: softBg }}
        />
        <div
          className="absolute bottom-0 left-0 w-96 h-96 rounded-full blur-3xl"
          style={{ backgroundColor: softBg }}
        />
      </div>
    </div>
  );
}
