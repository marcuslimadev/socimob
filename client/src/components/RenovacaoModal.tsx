import { useState } from 'react';
import { toast } from 'sonner';
import { api } from '@/lib/api';

interface Props {
  contratoId: number;
  contratoCodigo: string;
  fimAtual?: string;
  valorAtual?: number;
  onClose: () => void;
  onSuccess: () => void;
}

export default function RenovacaoModal({ contratoId, contratoCodigo, fimAtual, valorAtual, onClose, onSuccess }: Props) {
  const [form, setForm] = useState({
    novo_fim: '',
    novo_valor_aluguel: valorAtual ? String(valorAtual).replace('.', ',') : '',
  });
  const [loading, setLoading] = useState(false);

  const formatMoney = (v?: number) => Number(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!form.novo_fim) {
      toast.error('Informe a nova data de término.');
      return;
    }
    setLoading(true);
    try {
      const payload: Record<string, unknown> = { novo_fim: form.novo_fim };
      if (form.novo_valor_aluguel.trim()) {
        payload.novo_valor_aluguel = parseFloat(form.novo_valor_aluguel.replace(',', '.'));
      }
      await api.post(`/admin/financeiro/contratos/${contratoId}/renovar`, payload);
      toast.success('Contrato renovado com sucesso.');
      onSuccess();
      onClose();
    } catch (err: any) {
      toast.error(err?.response?.data?.message || 'Erro ao renovar contrato.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div className="glass-panel rounded-2xl p-6 w-full max-w-md mx-4">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-semibold">Renovar Contrato {contratoCodigo}</h2>
          <button type="button" onClick={onClose} className="text-muted-foreground hover:text-foreground text-xl leading-none">&times;</button>
        </div>

        {fimAtual && (
          <div className="mb-4 p-3 bg-muted rounded-xl text-sm">
            <span className="text-muted-foreground">Término atual: </span>
            <span className="font-semibold">{fimAtual.split('-').reverse().join('/')}</span>
          </div>
        )}

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-sm font-medium mb-1">Nova data de término *</label>
            <input
              type="date"
              value={form.novo_fim}
              onChange={(e) => setForm((f) => ({ ...f, novo_fim: e.target.value }))}
              required
              min={fimAtual || undefined}
              className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm"
            />
          </div>

          <div>
            <label className="block text-sm font-medium mb-1">
              Novo valor de aluguel (R$)
              {valorAtual && <span className="text-muted-foreground ml-1 text-xs">— atual: R$ {formatMoney(valorAtual)}</span>}
            </label>
            <input
              type="text"
              value={form.novo_valor_aluguel}
              onChange={(e) => setForm((f) => ({ ...f, novo_valor_aluguel: e.target.value }))}
              placeholder="Deixe em branco para manter o atual"
              className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm"
            />
          </div>

          <div className="flex justify-end gap-2 pt-2">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 rounded-lg border border-border hover:bg-accent text-sm"
            >
              Cancelar
            </button>
            <button
              type="submit"
              disabled={loading}
              className="px-4 py-2 rounded-lg bg-primary text-primary-foreground text-sm disabled:opacity-60"
            >
              {loading ? 'Renovando...' : 'Confirmar Renovação'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
