import { useState } from 'react';
import { motion } from 'framer-motion';
import { ClipboardCheck, Plus, Trash2, Save, ArrowLeft } from 'lucide-react';
import { toast } from 'sonner';
import { Link, useLocation } from 'wouter';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';

type TabKey = 'solicitacao' | 'observacoes' | 'pessoas' | 'historico';

export default function VistoriaSolicitacaoNova() {
  const [, setLocation] = useLocation();
  const [activeTab, setActiveTab] = useState<TabKey>('solicitacao');
  const [isSaving, setIsSaving] = useState(false);
  const [form, setForm] = useState({
    cliente_nome: '',
    tipo: '',
    imovel_id: '',
    observacoes: '',
  });
  const [pessoas, setPessoas] = useState<string[]>([]);
  const [novaPessoa, setNovaPessoa] = useState('');
  const [historico] = useState([
    { evento: 'iniciado', descricao: 'Formulario iniciado', data: new Date().toLocaleString('pt-BR') },
  ]);

  const handleChange = (field: string, value: string) => {
    setForm((prev) => ({ ...prev, [field]: value }));
  };

  const addPessoa = () => {
    const value = novaPessoa.trim();
    if (!value) return;
    setPessoas((prev) => [...prev, value]);
    setNovaPessoa('');
  };

  const removePessoa = (index: number) => {
    setPessoas((prev) => prev.filter((_, i) => i !== index));
  };

  const handleSubmit = async () => {
    if (!form.cliente_nome || !form.tipo) {
      toast.error('Cliente e tipo sao obrigatorios');
      return;
    }
    try {
      setIsSaving(true);
      const payload = {
        cliente_nome: form.cliente_nome,
        tipo: form.tipo,
        imovel_id: form.imovel_id || null,
        observacoes: form.observacoes || null,
        pessoas: pessoas.length ? pessoas : null,
      };
      const response = await api.post('/vistorias/solicitacoes', payload);
      if (response.data?.success) {
        toast.success('Solicitacao criada com sucesso');
        setLocation('/vistorias');
      } else {
        toast.error('Nao foi possivel criar a solicitacao');
      }
    } catch (error: any) {
      console.error('Erro ao criar solicitacao:', error);
      toast.error(error?.response?.data?.message || 'Erro ao criar solicitacao');
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <div className="flex">
      <Sidebar />

      <div className="page-shell">
        <motion.div
          initial={{ opacity: 0, y: 16 }}
          animate={{ opacity: 1, y: 0 }}
          className="max-w-5xl mx-auto"
        >
          <div className="page-header mb-6">
            <div>
              <h1 className="page-title mb-2">Nova Solicitação</h1>
              <p className="page-subtitle">Cadastro de solicitação de vistoria.</p>
            </div>
            <Link to="/vistorias">
              <motion.button
                whileHover={{ scale: 1.05 }}
                whileTap={{ scale: 0.95 }}
                className="flex w-full items-center justify-center gap-2 rounded-lg border border-white/20 bg-white/10 px-4 py-2 text-foreground sm:w-auto"
              >
                <ArrowLeft size={16} />
                Voltar
              </motion.button>
            </Link>
          </div>

          <div className="glass-panel p-6 rounded-2xl">
            <div className="flex flex-wrap gap-2 mb-6">
              {[
                { key: 'solicitacao', label: 'Solicitação' },
                { key: 'observacoes', label: 'Observações' },
                { key: 'pessoas', label: 'Pessoas' },
                { key: 'historico', label: 'Histórico' },
              ].map((tab) => (
                <motion.button
                  key={tab.key}
                  whileHover={{ scale: 1.03 }}
                  whileTap={{ scale: 0.97 }}
                  onClick={() => setActiveTab(tab.key as TabKey)}
                  className={`px-4 py-2 rounded-lg text-sm font-semibold transition-all ${
                    activeTab === tab.key
                      ? 'bg-gradient-to-r from-blue-500 to-blue-600 text-white'
                      : 'bg-white/10 text-foreground hover:bg-white/20'
                  }`}
                >
                  {tab.label}
                </motion.button>
              ))}
            </div>

            {activeTab === 'solicitacao' && (
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-semibold text-foreground mb-2">Cliente *</label>
                  <input
                    type="text"
                    value={form.cliente_nome}
                    onChange={(e) => handleChange('cliente_nome', e.target.value)}
                    className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                  />
                </div>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-semibold text-foreground mb-2">Tipo *</label>
                    <input
                      type="text"
                      value={form.tipo}
                      onChange={(e) => handleChange('tipo', e.target.value)}
                      placeholder="Entrada, saida, periodica..."
                      className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                    />
                  </div>
                  <div>
                    <label className="block text-sm font-semibold text-foreground mb-2">Imovel ID</label>
                    <input
                      type="text"
                      value={form.imovel_id}
                      onChange={(e) => handleChange('imovel_id', e.target.value)}
                      className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                    />
                  </div>
                </div>
              </div>
            )}

            {activeTab === 'observacoes' && (
              <div>
                <label className="block text-sm font-semibold text-foreground mb-2">Observacoes</label>
                <textarea
                  rows={5}
                  value={form.observacoes}
                  onChange={(e) => handleChange('observacoes', e.target.value)}
                  className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                />
              </div>
            )}

            {activeTab === 'pessoas' && (
              <div className="space-y-4">
                <div className="flex gap-2">
                  <input
                    type="text"
                    value={novaPessoa}
                    onChange={(e) => setNovaPessoa(e.target.value)}
                    placeholder="Adicionar pessoa"
                    className="flex-1 px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                  />
                  <motion.button
                    whileHover={{ scale: 1.05 }}
                    whileTap={{ scale: 0.95 }}
                    onClick={addPessoa}
                    className="px-4 py-3 bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg text-white"
                  >
                    <Plus size={18} />
                  </motion.button>
                </div>
                {pessoas.length === 0 ? (
                  <p className="text-sm text-muted-foreground">Nenhuma pessoa adicionada.</p>
                ) : (
                  <div className="space-y-2">
                    {pessoas.map((pessoa, index) => (
                      <div
                        key={`${pessoa}-${index}`}
                        className="flex items-center justify-between px-4 py-2 bg-white/5 border border-white/10 rounded-lg"
                      >
                        <span className="text-foreground">{pessoa}</span>
                        <motion.button
                          whileHover={{ scale: 1.05 }}
                          whileTap={{ scale: 0.95 }}
                          onClick={() => removePessoa(index)}
                          className="text-destructive"
                        >
                          <Trash2 size={16} />
                        </motion.button>
                      </div>
                    ))}
                  </div>
                )}
              </div>
            )}

            {activeTab === 'historico' && (
              <div className="space-y-3">
                {historico.map((item, index) => (
                  <div key={`${item.evento}-${index}`} className="p-4 bg-white/5 border border-white/10 rounded-lg">
                    <p className="text-sm text-muted-foreground">{item.data}</p>
                    <p className="text-foreground font-semibold">{item.descricao}</p>
                  </div>
                ))}
              </div>
            )}

            <div className="mt-8 flex items-center justify-between">
              <div className="flex items-center gap-2 text-sm text-muted-foreground">
                <ClipboardCheck size={18} />
                Campos obrigatorios: cliente, tipo.
              </div>
              <motion.button
                whileHover={{ scale: 1.05 }}
                whileTap={{ scale: 0.95 }}
                onClick={handleSubmit}
                disabled={isSaving}
                className="px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg text-white font-semibold flex items-center gap-2 disabled:opacity-60"
              >
                <Save size={18} />
                {isSaving ? 'Salvando...' : 'Salvar solicitacao'}
              </motion.button>
            </div>
          </div>
        </motion.div>
      </div>
    </div>
  );
}
