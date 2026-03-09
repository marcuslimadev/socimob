import { useState } from 'react';
import { toast } from 'sonner';
import { api } from '@/lib/api';

interface CobrancaItem {
  id: number;
  contrato_id?: number;
  competencia: string;
  vencimento: string;
  status: string;
  valor_aluguel?: number;
  valor_total: number;
  valor_pago?: number;
  multa?: number;
  juros?: number;
  desconto?: number;
  data_pagamento?: string;
  forma_pagamento?: string;
  numero_contrato?: string;
}

interface Props {
  cobranca: CobrancaItem;
  onClose: () => void;
  onSuccess: () => void;
}

const formasPagamento = [
  { value: 'pix', label: 'PIX' },
  { value: 'boleto', label: 'Boleto' },
  { value: 'transferencia', label: 'Transferência' },
  { value: 'dinheiro', label: 'Dinheiro' },
  { value: 'cartao', label: 'Cartão' },
  { value: 'deposito', label: 'Depósito' },
];

export default function CobrancaDetalheModal({ cobranca, onClose, onSuccess }: Props) {
  const [form, setForm] = useState({
    valor_pago: cobranca.valor_total ? String(cobranca.valor_total).replace('.', ',') : '',
    data_pagamento: new Date().toISOString().split('T')[0],
    forma_pagamento: 'pix',
    multa: cobranca.multa ? String(cobranca.multa).replace('.', ',') : '',
    juros: cobranca.juros ? String(cobranca.juros).replace('.', ',') : '',
    desconto: cobranca.desconto ? String(cobranca.desconto).replace('.', ',') : '',
  });
  const [loading, setLoading] = useState(false);
  const isPago = cobranca.status === 'pago' || cobranca.status === 'liquidado';

  const formatMoney = (v?: number) => Number(v || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const formatDate = (v?: string) => {
    if (!v) return '-';
    const [y, m, d] = v.slice(0, 10).split('-');
    return `${d}/${m}/${y}`;
  };

  const handlePagar = async (e: React.FormEvent) => {
    e.preventDefault();
    setLoading(true);
    try {
      await api.patch(`/admin/financeiro/cobrancas-contrato/${cobranca.id}/status`, {
        status: 'pago',
        valor_pago: parseFloat(form.valor_pago.replace(',', '.')),
        data_pagamento: form.data_pagamento,
        forma_pagamento: form.forma_pagamento,
        multa: form.multa ? parseFloat(form.multa.replace(',', '.')) : undefined,
        juros: form.juros ? parseFloat(form.juros.replace(',', '.')) : undefined,
        desconto: form.desconto ? parseFloat(form.desconto.replace(',', '.')) : undefined,
      });
      toast.success('Pagamento registrado com sucesso.');
      onSuccess();
      onClose();
    } catch (err: any) {
      toast.error(err?.response?.data?.message || 'Erro ao registrar pagamento.');
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div className="glass-panel rounded-2xl p-6 w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-semibold">
            Cobrança — {cobranca.competencia}
            {cobranca.numero_contrato && <span className="text-muted-foreground text-sm ml-2">({cobranca.numero_contrato})</span>}
          </h2>
          <button type="button" onClick={onClose} className="text-muted-foreground hover:text-foreground text-xl leading-none">&times;</button>
        </div>

        {/* Detalhes */}
        <div className="grid grid-cols-2 gap-3 mb-5 text-sm">
          <div className="p-3 bg-muted rounded-xl">
            <p className="text-muted-foreground text-xs">Vencimento</p>
            <p className="font-medium">{formatDate(cobranca.vencimento)}</p>
          </div>
          <div className="p-3 bg-muted rounded-xl">
            <p className="text-muted-foreground text-xs">Status</p>
            <p className={`font-medium capitalize ${isPago ? 'text-emerald-700' : 'text-amber-600'}`}>
              {cobranca.status}
            </p>
          </div>
          <div className="p-3 bg-muted rounded-xl">
            <p className="text-muted-foreground text-xs">Valor total</p>
            <p className="font-semibold">R$ {formatMoney(cobranca.valor_total)}</p>
          </div>
          {isPago && (
            <>
              <div className="p-3 bg-muted rounded-xl">
                <p className="text-muted-foreground text-xs">Valor pago</p>
                <p className="font-semibold text-emerald-700">R$ {formatMoney(cobranca.valor_pago)}</p>
              </div>
              <div className="p-3 bg-muted rounded-xl">
                <p className="text-muted-foreground text-xs">Data do pagamento</p>
                <p className="font-medium">{formatDate(cobranca.data_pagamento)}</p>
              </div>
              <div className="p-3 bg-muted rounded-xl">
                <p className="text-muted-foreground text-xs">Forma de pagamento</p>
                <p className="font-medium capitalize">{cobranca.forma_pagamento || '-'}</p>
              </div>
            </>
          )}
        </div>

        {/* Formulário de pagamento */}
        {!isPago && (
          <>
            <h3 className="text-sm font-semibold mb-3 border-t border-border pt-4">Registrar pagamento</h3>
            <form onSubmit={handlePagar} className="space-y-3">
              <div className="grid grid-cols-2 gap-3">
                <div>
                  <label className="block text-xs font-medium mb-1">Valor pago (R$) *</label>
                  <input
                    type="text"
                    value={form.valor_pago}
                    onChange={(e) => setForm((f) => ({ ...f, valor_pago: e.target.value }))}
                    required
                    className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm"
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium mb-1">Data do pagamento *</label>
                  <input
                    type="date"
                    value={form.data_pagamento}
                    onChange={(e) => setForm((f) => ({ ...f, data_pagamento: e.target.value }))}
                    required
                    className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm"
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium mb-1">Forma de pagamento</label>
                  <select
                    value={form.forma_pagamento}
                    onChange={(e) => setForm((f) => ({ ...f, forma_pagamento: e.target.value }))}
                    className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm"
                  >
                    {formasPagamento.map((fp) => (
                      <option key={fp.value} value={fp.value}>{fp.label}</option>
                    ))}
                  </select>
                </div>
                <div>
                  <label className="block text-xs font-medium mb-1">Multa (R$)</label>
                  <input
                    type="text"
                    value={form.multa}
                    onChange={(e) => setForm((f) => ({ ...f, multa: e.target.value }))}
                    placeholder="0,00"
                    className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm"
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium mb-1">Juros (R$)</label>
                  <input
                    type="text"
                    value={form.juros}
                    onChange={(e) => setForm((f) => ({ ...f, juros: e.target.value }))}
                    placeholder="0,00"
                    className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm"
                  />
                </div>
                <div>
                  <label className="block text-xs font-medium mb-1">Desconto (R$)</label>
                  <input
                    type="text"
                    value={form.desconto}
                    onChange={(e) => setForm((f) => ({ ...f, desconto: e.target.value }))}
                    placeholder="0,00"
                    className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm"
                  />
                </div>
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
                  className="px-4 py-2 rounded-lg bg-emerald-600 text-white text-sm disabled:opacity-60 hover:bg-emerald-700"
                >
                  {loading ? 'Registrando...' : 'Registrar Pagamento'}
                </button>
              </div>
            </form>
          </>
        )}

        {isPago && (
          <div className="flex justify-end pt-2">
            <button type="button" onClick={onClose} className="px-4 py-2 rounded-lg border border-border hover:bg-accent text-sm">
              Fechar
            </button>
          </div>
        )}
      </div>
    </div>
  );
}
