import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { api } from '@/lib/api';

interface Props {
  contratoId: number;
  contratoCodigo: string;
  valorAtual: number;
  onClose: () => void;
  onSuccess: () => void;
}

interface PreviewResult {
  novo_valor: number;
  percentual_aplicado: number;
  indice_nome: string;
}

export default function ReajusteModal({ contratoId, contratoCodigo, valorAtual, onClose, onSuccess }: Props) {
  const [form, setForm] = useState({
    indice: 'IGPM',
    percentual_manual: '',
    competencia: new Date().toISOString().slice(0, 7),
  });
  const [preview, setPreview] = useState<PreviewResult | null>(null);
  const [loadingPreview, setLoadingPreview] = useState(false);
  const [loadingAplicar, setLoadingAplicar] = useState(false);

  const handlePreview = async () => {
    setLoadingPreview(true);
    setPreview(null);
    try {
      const params: Record<string, string> = {
        competencia: form.competencia,
        indice: form.indice,
      };
      if (form.indice === 'manual' && form.percentual_manual) {
        params.percentual_manual = form.percentual_manual;
      }
      const { data } = await api.get(`/admin/financeiro/contratos/${contratoId}/reajustes/preview`, { params });
      setPreview(data);
    } catch (err: any) {
      toast.error(err?.response?.data?.message || 'Erro ao calcular previsão.');
    } finally {
      setLoadingPreview(false);
    }
  };

  const handleAplicar = async () => {
    if (!preview) {
      toast.error('Calcule a previsão antes de aplicar.');
      return;
    }
    setLoadingAplicar(true);
    try {
      await api.post(`/admin/financeiro/contratos/${contratoId}/reajustes`, {
        indice: form.indice,
        percentual_manual: form.indice === 'manual' ? parseFloat(form.percentual_manual.replace(',', '.')) : undefined,
        competencia: form.competencia,
        percentual_aplicado: preview.percentual_aplicado,
        valor_anterior: valorAtual,
        novo_valor: preview.novo_valor,
      });
      toast.success('Reajuste aplicado com sucesso.');
      onSuccess();
      onClose();
    } catch (err: any) {
      toast.error(err?.response?.data?.message || 'Erro ao aplicar reajuste.');
    } finally {
      setLoadingAplicar(false);
    }
  };

  const formatMoney = (v?: number) => Number(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div className="glass-panel rounded-2xl p-6 w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-semibold">Reajuste — {contratoCodigo}</h2>
          <button type="button" onClick={onClose} className="text-muted-foreground hover:text-foreground text-xl leading-none">&times;</button>
        </div>

        <div className="mb-4 p-3 bg-muted rounded-xl text-sm">
          <span className="text-muted-foreground">Valor atual: </span>
          <span className="font-semibold">R$ {formatMoney(valorAtual)}</span>
        </div>

        <div className="space-y-4">
          <div>
            <label className="block text-sm font-medium mb-1">Índice</label>
            <select
              value={form.indice}
              onChange={(e) => { setForm((f) => ({ ...f, indice: e.target.value })); setPreview(null); }}
              className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm"
            >
              <option value="IGPM">IGP-M (FGV)</option>
              <option value="IPCA">IPCA (IBGE)</option>
              <option value="INPC">INPC (IBGE)</option>
              <option value="manual">Manual (percentual fixo)</option>
            </select>
          </div>

          {form.indice === 'manual' && (
            <div>
              <label className="block text-sm font-medium mb-1">Percentual (%) *</label>
              <input
                type="text"
                value={form.percentual_manual}
                onChange={(e) => { setForm((f) => ({ ...f, percentual_manual: e.target.value })); setPreview(null); }}
                placeholder="ex: 5,5"
                className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm"
              />
            </div>
          )}

          <div>
            <label className="block text-sm font-medium mb-1">Competência (mês de referência)</label>
            <input
              type="month"
              value={form.competencia}
              onChange={(e) => { setForm((f) => ({ ...f, competencia: e.target.value })); setPreview(null); }}
              className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm"
            />
          </div>

          <button
            type="button"
            onClick={handlePreview}
            disabled={loadingPreview}
            className="w-full px-4 py-2 rounded-lg border border-primary text-primary hover:bg-primary/5 text-sm disabled:opacity-60"
          >
            {loadingPreview ? 'Calculando...' : 'Calcular previsão'}
          </button>

          {preview && (
            <div className="p-4 bg-emerald-50 border border-emerald-200 rounded-xl space-y-1 text-sm">
              <p className="font-medium text-emerald-800">Previsão calculada ({preview.indice_nome}):</p>
              <p>Percentual: <strong>{preview.percentual_aplicado.toFixed(4)}%</strong></p>
              <p>Novo valor: <strong className="text-emerald-700">R$ {formatMoney(preview.novo_valor)}</strong></p>
            </div>
          )}

          <div className="flex justify-end gap-2 pt-2">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 rounded-lg border border-border hover:bg-accent text-sm"
            >
              Cancelar
            </button>
            <button
              type="button"
              onClick={handleAplicar}
              disabled={loadingAplicar || !preview}
              className="px-4 py-2 rounded-lg bg-primary text-primary-foreground text-sm disabled:opacity-60"
            >
              {loadingAplicar ? 'Aplicando...' : 'Aplicar Reajuste'}
            </button>
          </div>
        </div>
      </div>
    </div>
  );
}
