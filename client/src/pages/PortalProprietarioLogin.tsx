import { FormEvent, useEffect, useState } from 'react';
import { useLocation } from 'wouter';
import { toast } from 'sonner';
import { api } from '@/lib/api';
import { fetchTenantBranding, type TenantBranding } from '@/lib/tenantBranding';

export default function PortalProprietarioLogin() {
  const [, navigate] = useLocation();
  const [tenant, setTenant] = useState<TenantBranding | null>(null);
  const [email, setEmail] = useState('');
  const [cpfCnpj, setCpfCnpj] = useState('');
  const [loading, setLoading] = useState(false);

  useEffect(() => {
    fetchTenantBranding().then((data) => setTenant(data));
  }, []);

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    if (!email) { toast.error('Informe seu e-mail.'); return; }
    setLoading(true);
    try {
      const response = await api.post('/portal/proprietario/auth/login', {
        email,
        cpf_cnpj: cpfCnpj || undefined,
      });
      const { token, user } = response.data;
      if (!token || !user) {
        toast.error('Resposta inválida do servidor.');
        return;
      }
      localStorage.setItem('token', token);
      localStorage.setItem('user', JSON.stringify(user));
      toast.success('Bem-vindo(a), ' + user.name + '!');
      navigate('/portal/proprietario/dashboard');
    } catch (err: any) {
      toast.error(err?.response?.data?.message || 'Credenciais inválidas.');
    } finally {
      setLoading(false);
    }
  };

  const primaryColor = tenant?.primary_color || '#0f172a';
  const logoSrc = tenant?.logo_url || tenant?.logo || '';

  return (
    <div className="min-h-screen flex items-center justify-center bg-background p-4">
      <div className="w-full max-w-md space-y-6">
        {/* Header */}
        <div className="text-center space-y-3">
          {logoSrc ? (
            <img src={logoSrc} alt={tenant?.name || 'Logo'} className="h-12 w-auto mx-auto object-contain" />
          ) : (
            <div
              className="h-12 w-12 mx-auto rounded-xl flex items-center justify-center text-white font-bold text-xl"
              style={{ backgroundColor: primaryColor }}
            >
              {(tenant?.name || 'P').charAt(0).toUpperCase()}
            </div>
          )}
          <div>
            {tenant?.name && (
              <p className="text-xs font-semibold uppercase tracking-widest" style={{ color: primaryColor }}>
                {tenant.name}
              </p>
            )}
            <h1 className="text-2xl font-bold mt-1">Portal do Proprietário</h1>
            <p className="text-sm text-muted-foreground mt-1">
              Acesse seus contratos, cobranças e repasses.
            </p>
          </div>
        </div>

        {/* Form */}
        <div className="glass-panel rounded-2xl p-6 space-y-4">
          <form onSubmit={handleSubmit} className="space-y-4">
            <div>
              <label className="block text-sm font-medium mb-1">E-mail *</label>
              <input
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
                placeholder="seu@email.com"
                className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm"
              />
            </div>

            <div>
              <label className="block text-sm font-medium mb-1">
                CPF / CNPJ <span className="text-muted-foreground font-normal">(opcional, para maior segurança)</span>
              </label>
              <input
                type="text"
                value={cpfCnpj}
                onChange={(e) => setCpfCnpj(e.target.value)}
                placeholder="000.000.000-00"
                className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm"
              />
            </div>

            <button
              type="submit"
              disabled={loading}
              className="w-full py-2 rounded-lg text-sm font-medium text-white disabled:opacity-60 transition-colors"
              style={{ backgroundColor: primaryColor }}
            >
              {loading ? 'Entrando...' : 'Acessar Portal'}
            </button>
          </form>

          <p className="text-center text-xs text-muted-foreground">
            Dificuldades de acesso?{' '}
            <a href="/" className="text-primary hover:underline">
              Entre em contato com a imobiliária
            </a>
          </p>
        </div>
      </div>
    </div>
  );
}
