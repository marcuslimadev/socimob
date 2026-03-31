import { useState } from 'react';
import { toast } from 'sonner';
import { api } from '@/lib/api';

interface PessoaForm {
  nome: string;
  email: string;
  telefone: string;
  cpf_cnpj: string;
  tipo: string;
  papeis: string[];
}

interface Props {
  pessoaId?: number;
  pessoaInicial?: Partial<PessoaForm>;
  onClose: () => void;
  onSuccess: () => void;
}

const PAPEIS_OPTIONS = [
  { value: 'proprietario', label: 'Proprietário' },
  { value: 'inquilino', label: 'Inquilino' },
  { value: 'vendedor', label: 'Vendedor' },
  { value: 'comprador', label: 'Comprador' },
  { value: 'fiador', label: 'Fiador' },
  { value: 'cliente', label: 'Cliente' },
];

const TIPO_OPTIONS = [
  { value: 'fisica', label: 'Pessoa Física' },
  { value: 'juridica', label: 'Pessoa Jurídica' },
];

export default function PessoaFormModal({ pessoaId, pessoaInicial, onClose, onSuccess }: Props) {
  const [form, setForm] = useState<PessoaForm>({
    nome: pessoaInicial?.nome ?? '',
    email: pessoaInicial?.email ?? '',
    telefone: pessoaInicial?.telefone ?? '',
    cpf_cnpj: pessoaInicial?.cpf_cnpj ?? '',
    tipo: pessoaInicial?.tipo ?? 'fisica',
    papeis: pessoaInicial?.papeis ?? [],
  });
  const [loading, setLoading] = useState(false);

  const isEditing = Boolean(pessoaId);

  const togglePapel = (papel: string) => {
    setForm((f) => ({
      ...f,
      papeis: f.papeis.includes(papel) ? f.papeis.filter((p) => p !== papel) : [...f.papeis, papel],
    }));
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!form.nome.trim()) {
      toast.error('Informe o nome.');
      return;
    }
    setLoading(true);
    try {
      const payload = {
        nome: form.nome,
        email: form.email || undefined,
        telefone: form.telefone || undefined,
        celular: form.telefone || undefined,
        tipo: form.tipo,
        cpf: form.tipo === 'fisica' ? (form.cpf_cnpj || undefined) : undefined,
        cnpj: form.tipo === 'juridica' ? (form.cpf_cnpj || undefined) : undefined,
        papeis: form.papeis,
      };

      if (isEditing) {
        await api.put(`/pessoas/${pessoaId}`, payload);
        toast.success('Pessoa atualizada com sucesso.');
      } else {
        await api.post('/pessoas', payload);
        toast.success('Pessoa cadastrada com sucesso.');
      }
      onSuccess();
      onClose();
    } catch (err: any) {
      const errs = err?.response?.data?.errors;
      if (errs) {
        const first = Object.values(errs)[0];
        toast.error(Array.isArray(first) ? (first as string[])[0] : String(first));
      } else {
        toast.error(err?.response?.data?.message || 'Erro ao salvar pessoa.');
      }
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50">
      <div className="glass-panel rounded-2xl p-6 w-full max-w-lg mx-4 max-h-[90vh] overflow-y-auto">
        <div className="flex items-center justify-between mb-4">
          <h2 className="text-lg font-semibold">{isEditing ? 'Editar Pessoa' : 'Nova Pessoa'}</h2>
          <button type="button" onClick={onClose} className="text-muted-foreground hover:text-foreground text-xl leading-none">&times;</button>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="block text-sm font-medium mb-1">Nome completo *</label>
            <input
              type="text"
              value={form.nome}
              onChange={(e) => setForm((f) => ({ ...f, nome: e.target.value }))}
              required
              placeholder="Nome da pessoa ou razão social"
              className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm"
            />
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-sm font-medium mb-1">Tipo</label>
              <select
                value={form.tipo}
                onChange={(e) => setForm((f) => ({ ...f, tipo: e.target.value }))}
                className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm"
              >
                {TIPO_OPTIONS.map((t) => <option key={t.value} value={t.value}>{t.label}</option>)}
              </select>
            </div>
            <div>
              <label className="block text-sm font-medium mb-1">{form.tipo === 'juridica' ? 'CNPJ' : 'CPF'}</label>
              <input
                type="text"
                value={form.cpf_cnpj}
                onChange={(e) => setForm((f) => ({ ...f, cpf_cnpj: e.target.value }))}
                placeholder={form.tipo === 'juridica' ? '00.000.000/0000-00' : '000.000.000-00'}
                className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm"
              />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-3">
            <div>
              <label className="block text-sm font-medium mb-1">E-mail</label>
              <input
                type="email"
                value={form.email}
                onChange={(e) => setForm((f) => ({ ...f, email: e.target.value }))}
                placeholder="email@exemplo.com"
                className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm"
              />
            </div>
            <div>
              <label className="block text-sm font-medium mb-1">Telefone / WhatsApp</label>
              <input
                type="tel"
                value={form.telefone}
                onChange={(e) => setForm((f) => ({ ...f, telefone: e.target.value }))}
                placeholder="(11) 99999-9999"
                className="w-full bg-background border border-border rounded-lg px-3 py-2 text-sm"
              />
            </div>
          </div>

          <div>
            <label className="block text-sm font-medium mb-2">Papéis</label>
            <div className="flex flex-wrap gap-2">
              {PAPEIS_OPTIONS.map((p) => (
                <button
                  key={p.value}
                  type="button"
                  onClick={() => togglePapel(p.value)}
                  className={`px-3 py-1.5 rounded-full text-xs border transition-colors ${
                    form.papeis.includes(p.value)
                      ? 'bg-primary text-primary-foreground border-primary'
                      : 'border-border hover:bg-accent'
                  }`}
                >
                  {p.label}
                </button>
              ))}
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
              className="px-4 py-2 rounded-lg bg-primary text-primary-foreground text-sm disabled:opacity-60"
            >
              {loading ? 'Salvando...' : isEditing ? 'Salvar alterações' : 'Cadastrar pessoa'}
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
