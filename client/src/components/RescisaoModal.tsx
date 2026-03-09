import { useState } from 'react';
import { toast } from 'sonner';
import { api } from '@/lib/api';

interface Props {
  contratoId: number;
  contratoCodigo: string;
  onClose: () => void;
  onSuccess: () => void;
}

export default function RescisaoModal({ contratoId, contratoCodigo, onClose, onSuccess }: Props) {
  const [form, setForm] = useState({
    motivo_rescisao: '',
    rescindido_em: new Date().toISOString().split('T')[0],
    multa_rescisao_calculada: '',
  });
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!form.motivo_rescisao.trim()) {
      toast.error('Informe o motivo da rescisão.');
      return;
    }
    setLoading(true);
    try {
      await api.post(`/admin/financeiro/contratos/${contratoId}/encerrar`, {
        motivo_rescisao: form.motivo_rescisao,
        rescindido_em: form.rescindido_em,
        multa_rescisao_calculada: form.multa_rescisao_calculada
          ? parseFloat(form.multa_rescisao_calculada.replace(',', '.'))
          : null,
      });
      toast.success('Contrato rescindido com sucesso.');
      onSuccess();
      onClose();
    } catch (err: any) {
      toast.error(err?.response?.data?.message || 'Erro ao rescindir contrato.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div className="glass-panel rounded-2xl p-6 w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-semibold">Rescindir Contrato {contratoCodigo}</h2>
          <button type="button" onClick={onClose} className="text-muted-foreground hover:text-foreground text-xl leading-none">&times;</button>
        </div>

        <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded-xl text-sm text-red-800">
          Esta ação encerrará o contrato. Certifique-se de que todas as cobranças foram liquidadas antes de prosseguir.
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-sm font-medium mb-1">Data da rescisão *</label>
            <input
              type="date"
              value={form.rescindido_em}
              onChange={(e) => setForm((f) => ({ ...f, rescindido_em: e.target.value }))}
              required
              className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm"
            />
          </div>

          <div>
            <label className="block text-sm font-medium mb-1">Motivo da rescisão *</label>
            <textarea
              value={form.motivo_rescisao}
              onChange={(e) => setForm((f) => ({ ...f, motivo_rescisao: e.target.value }))}
              required
              rows={3}
              placeholder="Descreva o motivo da rescisão..."
              className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm resize-none"
            />
          </div>

          <div>
            <label className="block text-sm font-medium mb-1">Multa rescisória (R$)</label>
            <input
              type="text"
              value={form.multa_rescisao_calculada}
              onChange={(e) => setForm((f) => ({ ...f, multa_rescisao_calculada: e.target.value }))}
              placeholder="0,00"
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
              className="px-4 py-2 rounded-lg bg-red-600 text-white text-sm disabled:opacity-60 hover:bg-red-700"
            >
              {loading ? 'Rescindindo...' : 'Confirmar Rescisão'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
