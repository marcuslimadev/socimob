import { useEffect, useState } from 'react';
import { CheckCircle2, Copy, ExternalLink, KeyRound, Loader2, Save } from 'lucide-react';
import { toast } from 'sonner';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';

interface IntegrationData {
  platform_name: string;
  platform_site: string;
  platform_logo_url: string;
  client_name: string;
  client_email?: string;
  token_configured: boolean;
  xml_url: string;
  leads_url: string;
  load_schedule: string;
}

export default function ChavesNaMaoIntegration() {
  const [data, setData] = useState<IntegrationData | null>(null);
  const [email, setEmail] = useState('');
  const [token, setToken] = useState('');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  const load = async () => {
    try {
      const response = await api.get('/admin/integrations/chaves-na-mao');
      setData(response.data);
      setEmail(response.data.client_email || '');
    } catch {
      toast.error('Não foi possível carregar a integração.');
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => { load(); }, []);

  const copy = async (value: string) => {
    await navigator.clipboard.writeText(value);
    toast.success('URL copiada.');
  };

  const save = async (event: React.FormEvent) => {
    event.preventDefault();
    try {
      setSaving(true);
      const response = await api.put('/admin/integrations/chaves-na-mao', {
        client_email: email.trim(),
        token: token.trim() || undefined,
      });
      setData(response.data);
      setToken('');
      toast.success('Credenciais salvas. O recebimento de leads está pronto.');
    } catch (error: any) {
      toast.error(error?.response?.data?.messages?.client_email?.[0] || 'Erro ao salvar credenciais.');
    } finally {
      setSaving(false);
    }
  };

  const UrlCard = ({ title, value, description }: { title: string; value: string; description: string }) => (
    <div className="glass-panel rounded-2xl border p-5">
      <p className="font-semibold text-foreground">{title}</p>
      <p className="mt-1 text-sm text-muted-foreground">{description}</p>
      <div className="mt-4 flex gap-2">
        <input readOnly value={value} className="h-11 min-w-0 flex-1 rounded-xl border border-border bg-white/5 px-3 text-sm text-foreground" />
        <button type="button" onClick={() => copy(value)} className="rounded-xl border border-border px-3 text-foreground hover:bg-white/10" title="Copiar URL"><Copy size={18} /></button>
        <a href={value} target="_blank" rel="noreferrer" className="flex items-center rounded-xl border border-border px-3 text-foreground hover:bg-white/10" title="Abrir"><ExternalLink size={18} /></a>
      </div>
    </div>
  );

  return (
    <div className="flex">
      <Sidebar />
      <main className="page-shell">
        <div className="mx-auto max-w-5xl space-y-6">
          <div className="page-header">
            <div>
              <h1 className="page-title">Chaves na Mão</h1>
              <p className="page-subtitle">XML de imóveis e recebimento automático de leads.</p>
            </div>
          </div>

          {loading ? <div className="flex justify-center py-20"><Loader2 className="h-10 w-10 animate-spin text-blue-500" /></div> : data ? <>
            <div className="grid gap-4 md:grid-cols-2">
              <UrlCard title="XML de imóveis" value={data.xml_url} description="Envie esta URL para a equipe do Chaves na Mão." />
              <UrlCard title="URL de integração de leads" value={data.leads_url} description="Cadastre esta URL como webhook de leads imobiliários." />
            </div>

            <form onSubmit={save} className="glass-panel rounded-2xl border p-6">
              <div className="flex items-start gap-3">
                <KeyRound className="mt-1 text-blue-400" size={22} />
                <div>
                  <h2 className="text-lg font-semibold text-foreground">Autenticação dos leads</h2>
                  <p className="text-sm text-muted-foreground">Use o e-mail da conta e o token fornecido na área “Meus Dados” do Chaves na Mão.</p>
                </div>
              </div>
              <div className="mt-5 grid gap-4 md:grid-cols-2">
                <label className="space-y-2 text-sm font-medium text-foreground">E-mail do cliente
                  <input type="email" required value={email} onChange={(e) => setEmail(e.target.value)} className="h-11 w-full rounded-xl border border-border bg-white/5 px-3" />
                </label>
                <label className="space-y-2 text-sm font-medium text-foreground">Token {data.token_configured ? '(deixe vazio para manter o atual)' : ''}
                  <input type="password" required={!data.token_configured} value={token} onChange={(e) => setToken(e.target.value)} className="h-11 w-full rounded-xl border border-border bg-white/5 px-3" />
                </label>
              </div>
              <div className="mt-5 flex items-center justify-between gap-3">
                <span className={`inline-flex items-center gap-2 text-sm ${data.token_configured ? 'text-emerald-400' : 'text-amber-400'}`}>
                  <CheckCircle2 size={17} /> {data.token_configured ? 'Token configurado' : 'Token pendente'}
                </span>
                <button disabled={saving} className="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 font-semibold text-white hover:bg-blue-700 disabled:opacity-60">
                  {saving ? <Loader2 size={17} className="animate-spin" /> : <Save size={17} />} Salvar
                </button>
              </div>
            </form>

            <div className="glass-panel rounded-2xl border p-6 text-sm text-muted-foreground">
              <h2 className="font-semibold text-foreground">Como encaminhar</h2>
              <ol className="mt-3 list-decimal space-y-2 pl-5">
                <li>Copie e envie o XML de imóveis para o atendimento do Chaves na Mão.</li>
                <li>Envie também a URL de leads acima e informe que ela utiliza Basic Auth.</li>
                <li>Cadastre nesta tela o mesmo e-mail e token configurados no portal Chaves na Mão.</li>
              </ol>
              <p className="mt-4"><strong className="text-foreground">Horário de carga:</strong> {data.load_schedule}</p>
            </div>
          </> : null}
        </div>
      </main>
    </div>
  );
}
