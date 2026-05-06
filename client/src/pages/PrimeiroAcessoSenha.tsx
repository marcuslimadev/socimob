import { useState } from 'react';
import { useLocation } from 'wouter';
import { toast } from 'sonner';
import { api } from '@/lib/api';

export default function PrimeiroAcessoSenha() {
  const [, setLocation] = useLocation();
  const [password, setPassword] = useState('');
  const [confirm, setConfirm] = useState('');
  const [saving, setSaving] = useState(false);

  const salvar = async (e: React.FormEvent) => {
    e.preventDefault();
    if (password.length < 8) return toast.error('A nova senha precisa ter ao menos 8 caracteres.');
    if (password !== confirm) return toast.error('A confirmação não confere.');
    setSaving(true);
    try {
      await api.post('/auth/change-password-first-access', {
        password,
        password_confirmation: confirm,
      });

      const raw = localStorage.getItem('user');
      if (raw) {
        try {
          const u = JSON.parse(raw);
          localStorage.setItem('user', JSON.stringify({ ...u, must_change_password: false }));
        } catch {
          // no-op
        }
      }
      toast.success('Senha atualizada. Acesso liberado.');
      setLocation('/dashboard');
    } catch (e: any) {
      toast.error(e?.response?.data?.message || 'Não foi possível atualizar sua senha.');
    } finally {
      setSaving(false);
    }
  };

  return (
    <div className="min-h-screen bg-background flex items-center justify-center p-4">
      <form onSubmit={salvar} className="w-full max-w-md rounded-2xl border border-white/10 bg-card p-6 space-y-4">
        <h1 className="text-xl font-semibold text-foreground">Primeiro acesso</h1>
        <p className="text-sm text-muted-foreground">Por segurança, troque a senha padrão exclusiva antes de continuar.</p>
        <input
          type="password"
          placeholder="Nova senha"
          value={password}
          onChange={(e) => setPassword(e.target.value)}
          className="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2.5"
          required
        />
        <input
          type="password"
          placeholder="Confirmar nova senha"
          value={confirm}
          onChange={(e) => setConfirm(e.target.value)}
          className="w-full rounded-xl border border-white/10 bg-white/5 px-3 py-2.5"
          required
        />
        <button type="submit" disabled={saving} className="w-full rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground">
          {saving ? 'Atualizando...' : 'Salvar nova senha'}
        </button>
      </form>
    </div>
  );
}

