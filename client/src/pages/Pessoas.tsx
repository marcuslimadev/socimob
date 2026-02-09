import { useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import { UserRound, Loader2, Search, Plus, Edit, Trash2, MapPin } from 'lucide-react';
import { toast } from 'sonner';
import { useLocation, useSearch, useRoute } from 'wouter';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';
import { useViaCep } from '@/hooks/useViaCep';
import { AlertDialog, AlertDialogAction, AlertDialogCancel, AlertDialogContent, AlertDialogDescription, AlertDialogFooter, AlertDialogHeader, AlertDialogTitle } from '@/components/ui/alert-dialog';

interface Pessoa {
  id: number;
  nome: string;
  tipo: string;
  pais?: string | null;
  cpf: string | null;
  cnpj: string | null;
  rg?: string | null;
  orgao_expedidor?: string | null;
  data_expedicao?: string | null;
  cnh?: string | null;
  data_nascimento?: string | null;
  razao_social?: string | null;
  inscricao_estadual?: string | null;
  inscricao_municipal?: string | null;
  email: string | null;
  telefone: string | null;
  celular: string | null;
  cep?: string | null;
  cidade: string | null;
  estado: string | null;
  bairro?: string | null;
  endereco?: string | null;
  numero?: string | null;
  complemento?: string | null;
  observacoes?: string | null;
  contatos?: Array<{ tipo: string; contato: string; descricao: string }> | null;
  ativo: boolean;
  created_at?: string;
}

export default function Pessoas() {
  const searchParams = useSearch();
  const [match, params] = useRoute('/pessoas/:id');
  const pessoaId = match ? params?.id : null;
  
  const [pessoas, setPessoas] = useState<Pessoa[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState('');
  const [tipoFilter, setTipoFilter] = useState('todos');
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [showModal, setShowModal] = useState(false);
  const [currentTab, setCurrentTab] = useState('principal');
  const [editingPessoa, setEditingPessoa] = useState<Pessoa | null>(null);
  const [deleteDialog, setDeleteDialog] = useState<{ open: boolean; id: number | null; name: string }>({
    open: false,
    id: null,
    name: '',
  });

  const [formData, setFormData] = useState({
    nome: '',
    pais: 'Brasil',
    telefone: '',
    celular: '',
    email: '',
    tipo: 'fisica',
    cpf: '',
    rg: '',
    orgao_expedidor: '',
    data_expedicao: '',
    cnh: '',
    data_nascimento: '',
    cnpj: '',
    razao_social: '',
    inscricao_estadual: '',
    inscricao_municipal: '',
    cep: '',
    estado: '',
    cidade: '',
    bairro: '',
    endereco: '',
    numero: '',
    complemento: '',
    observacoes: '',
  });

  const [contatos, setContatos] = useState<Array<{ tipo: string; contato: string; descricao: string }>>([]);
  const { buscarCep, isLoading: isLoadingCep } = useViaCep();

  // Abrir pessoa específica se vier da URL (/pessoas/:id)
  useEffect(() => {
    if (pessoaId) {
      const loadPessoa = async () => {
        try {
          const response = await api.get(`/pessoas/${pessoaId}`);
          const pessoa = response.data.data || response.data;
          await openEditModal(pessoa);
        } catch (error) {
          console.error('Erro ao carregar pessoa:', error);
          toast.error('Pessoa não encontrada');
        }
      };
      loadPessoa();
    }
  }, [pessoaId]);

  // Aplicar busca automática se vier da URL
  useEffect(() => {
    const urlParams = new URLSearchParams(searchParams);
    const searchParam = urlParams.get('search');
    if (searchParam) {
      setSearchTerm(searchParam);
    }
  }, [searchParams]);

  // Abrir automaticamente a pessoa se houver apenas 1 resultado da busca vinda da URL
  useEffect(() => {
    const urlParams = new URLSearchParams(searchParams);
    const searchParam = urlParams.get('search');
    
    if (searchParam && pessoas.length === 1 && !isLoading && !pessoaId) {
      // Aguardar um momento para garantir que a lista foi carregada
      const timer = setTimeout(() => {
        openEditModal(pessoas[0]);
      }, 300);
      return () => clearTimeout(timer);
    }
  }, [pessoas, searchParams, isLoading, pessoaId]);

  const handleBuscarCep = async () => {
    if (!formData.cep) {
      toast.error('Digite um CEP');
      return;
    }

    const resultado = await buscarCep(formData.cep);
    if (resultado) {
      setFormData({
        ...formData,
        estado: resultado.uf,
        cidade: resultado.localidade,
        bairro: resultado.bairro,
        endereco: resultado.logradouro,
        complemento: resultado.complemento || formData.complemento,
      });
      toast.success('Endereço preenchido automaticamente');
    }
  };

  useEffect(() => {
    fetchPessoas();
  }, [page, tipoFilter, searchTerm]);

  const fetchPessoas = async () => {
    try {
      setIsLoading(true);
      const params: Record<string, string | number> = { page, per_page: 10 };

      if (tipoFilter !== 'todos') {
        params.tipo = tipoFilter;
      }

      if (searchTerm) {
        params.search = searchTerm;
      }

      const response = await api.get('/pessoas', { params });
      setPessoas(response.data.data || []);
      setPage(response.data.current_page || 1);
      setLastPage(response.data.last_page || 1);
    } catch (error) {
      console.error('Erro ao carregar pessoas:', error);
      toast.error('Erro ao carregar pessoas');
    } finally {
      setIsLoading(false);
    }
  };

  const handleSearch = () => {
    setPage(1);
    fetchPessoas();
  };

  const openCreateModal = () => {
    setEditingPessoa(null);
    setFormData({
      nome: '',
      pais: 'Brasil',
      telefone: '',
      celular: '',
      email: '',
      tipo: 'fisica',
      cpf: '',
      rg: '',
      orgao_expedidor: '',
      data_expedicao: '',
      cnh: '',
      data_nascimento: '',
      cnpj: '',
      razao_social: '',
      inscricao_estadual: '',
      inscricao_municipal: '',
      cep: '',
      estado: '',
      cidade: '',
      bairro: '',
      endereco: '',
      numero: '',
      complemento: '',
      observacoes: '',
    });
    setContatos([]);
    setCurrentTab('principal');
    setShowModal(true);
  };

  const openEditModal = async (pessoa: Pessoa) => {
    try {
      // Buscar dados completos da pessoa
      const response = await api.get(`/pessoas/${pessoa.id}`);
      const pessoaCompleta = response.data.data || response.data;

      setEditingPessoa(pessoaCompleta);
      setFormData({
        nome: pessoaCompleta.nome || '',
        pais: pessoaCompleta.pais || 'Brasil',
        telefone: pessoaCompleta.telefone || '',
        celular: pessoaCompleta.celular || '',
        email: pessoaCompleta.email || '',
        tipo: pessoaCompleta.tipo || 'fisica',
        cpf: pessoaCompleta.cpf || '',
        rg: pessoaCompleta.rg || '',
        orgao_expedidor: pessoaCompleta.orgao_expedidor || '',
        data_expedicao: pessoaCompleta.data_expedicao?.split('T')[0] || '',
        cnh: pessoaCompleta.cnh || '',
        data_nascimento: pessoaCompleta.data_nascimento?.split('T')[0] || '',
        cnpj: pessoaCompleta.cnpj || '',
        razao_social: pessoaCompleta.razao_social || '',
        inscricao_estadual: pessoaCompleta.inscricao_estadual || '',
        inscricao_municipal: pessoaCompleta.inscricao_municipal || '',
        cep: pessoaCompleta.cep || '',
        estado: pessoaCompleta.estado || '',
        cidade: pessoaCompleta.cidade || '',
        bairro: pessoaCompleta.bairro || '',
        endereco: pessoaCompleta.endereco || '',
        numero: pessoaCompleta.numero || '',
        complemento: pessoaCompleta.complemento || '',
        observacoes: pessoaCompleta.observacoes || '',
      });
      setContatos(pessoaCompleta.contatos || []);
      setCurrentTab('principal');
      setShowModal(true);
    } catch (error) {
      console.error('Erro ao carregar dados da pessoa:', error);
      toast.error('Erro ao carregar dados da pessoa');
    }
  };

  const addContato = () => {
    setContatos([...contatos, { tipo: 'celular', contato: '', descricao: '' }]);
  };

  const removeContato = (index: number) => {
    setContatos(contatos.filter((_, i) => i !== index));
  };

  const updateContato = (index: number, field: string, value: string) => {
    const newContatos = [...contatos];
    newContatos[index] = { ...newContatos[index], [field]: value };
    setContatos(newContatos);
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!formData.nome || !formData.tipo) {
      toast.error('Nome e Tipo são obrigatórios');
      return;
    }

    try {
      const dataToSend = {
        ...formData,
        contatos: contatos.length > 0 ? contatos : null,
      };

      if (editingPessoa) {
        await api.put(`/pessoas/${editingPessoa.id}`, dataToSend);
        toast.success('Pessoa atualizada com sucesso');
      } else {
        await api.post('/pessoas', dataToSend);
        toast.success('Pessoa criada com sucesso');
      }

      setShowModal(false);
      fetchPessoas();
    } catch (error) {
      console.error('Erro ao salvar pessoa:', error);
      toast.error('Erro ao salvar pessoa');
    }
  };

  const handleDeleteClick = (id: number, name: string) => {
    setDeleteDialog({ open: true, id, name });
  };

  const handleDeleteConfirm = async () => {
    if (!deleteDialog.id) return;

    try {
      await api.delete(`/pessoas/${deleteDialog.id}`);
      toast.success('Pessoa excluída com sucesso');
      fetchPessoas();
    } catch (error) {
      toast.error('Erro ao excluir pessoa');
    } finally {
      setDeleteDialog({ open: false, id: null, name: '' });
    }
  };

  const renderFormTab = () => {
    switch (currentTab) {
      case 'principal':
        return (
          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-foreground mb-1">Nome *</label>
              <input
                type="text"
                value={formData.nome}
                onChange={(e) => setFormData({ ...formData, nome: e.target.value })}
                className="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                required
              />
            </div>

            <div>
              <label className="block text-sm font-medium text-foreground mb-1">Tipo *</label>
              <select
                value={formData.tipo}
                onChange={(e) => setFormData({ ...formData, tipo: e.target.value })}
                className="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="fisica">Pessoa Física</option>
                <option value="juridica">Pessoa Jurídica</option>
              </select>
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-foreground mb-1">Telefone</label>
                <input
                  type="text"
                  value={formData.telefone}
                  onChange={(e) => setFormData({ ...formData, telefone: e.target.value })}
                  className="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-foreground mb-1">Celular</label>
                <input
                  type="text"
                  value={formData.celular}
                  onChange={(e) => setFormData({ ...formData, celular: e.target.value })}
                  className="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-foreground mb-1">E-mail</label>
              <input
                type="email"
                value={formData.email}
                onChange={(e) => setFormData({ ...formData, email: e.target.value })}
                className="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
          </div>
        );

      case 'documentos':
        return (
          <div className="space-y-4">
            {formData.tipo === 'fisica' ? (
              <>
                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-foreground mb-1">CPF</label>
                    <input
                      type="text"
                      value={formData.cpf}
                      onChange={(e) => setFormData({ ...formData, cpf: e.target.value })}
                      className="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-foreground mb-1">RG</label>
                    <input
                      type="text"
                      value={formData.rg}
                      onChange={(e) => setFormData({ ...formData, rg: e.target.value })}
                      className="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                  </div>
                </div>

                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-foreground mb-1">Órgão Expedidor</label>
                    <input
                      type="text"
                      value={formData.orgao_expedidor}
                      onChange={(e) => setFormData({ ...formData, orgao_expedidor: e.target.value })}
                      className="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-foreground mb-1">Data de Expedição</label>
                    <input
                      type="date"
                      value={formData.data_expedicao}
                      onChange={(e) => setFormData({ ...formData, data_expedicao: e.target.value })}
                      className="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                  </div>
                </div>

                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-foreground mb-1">CNH</label>
                    <input
                      type="text"
                      value={formData.cnh}
                      onChange={(e) => setFormData({ ...formData, cnh: e.target.value })}
                      className="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-foreground mb-1">Data de Nascimento</label>
                    <input
                      type="date"
                      value={formData.data_nascimento}
                      onChange={(e) => setFormData({ ...formData, data_nascimento: e.target.value })}
                      className="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                  </div>
                </div>
              </>
            ) : (
              <>
                <div>
                  <label className="block text-sm font-medium text-foreground mb-1">CNPJ</label>
                  <input
                    type="text"
                    value={formData.cnpj}
                    onChange={(e) => setFormData({ ...formData, cnpj: e.target.value })}
                    className="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-foreground mb-1">Razão Social</label>
                  <input
                    type="text"
                    value={formData.razao_social}
                    onChange={(e) => setFormData({ ...formData, razao_social: e.target.value })}
                    className="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                  />
                </div>

                <div className="grid grid-cols-2 gap-4">
                  <div>
                    <label className="block text-sm font-medium text-foreground mb-1">Inscrição Estadual</label>
                    <input
                      type="text"
                      value={formData.inscricao_estadual}
                      onChange={(e) => setFormData({ ...formData, inscricao_estadual: e.target.value })}
                      className="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                  </div>

                  <div>
                    <label className="block text-sm font-medium text-foreground mb-1">Inscrição Municipal</label>
                    <input
                      type="text"
                      value={formData.inscricao_municipal}
                      onChange={(e) => setFormData({ ...formData, inscricao_municipal: e.target.value })}
                      className="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                    />
                  </div>
                </div>
              </>
            )}
          </div>
        );

      case 'endereco':
        return (
          <div className="space-y-4">
            <div className="grid grid-cols-3 gap-4">
              <div className="col-span-2">
                <label className="block text-sm font-medium text-foreground mb-1">CEP</label>
                <input
                  type="text"
                  value={formData.cep}
                  onChange={(e) => setFormData({ ...formData, cep: e.target.value })}
                  onBlur={handleBuscarCep}
                  className="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="00000-000"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-foreground mb-1">&nbsp;</label>
                <motion.button
                  type="button"
                  whileHover={{ scale: 1.05 }}
                  whileTap={{ scale: 0.95 }}
                  onClick={handleBuscarCep}
                  disabled={isLoadingCep}
                  className="w-full px-4 py-2 bg-blue-500 hover:bg-blue-600 rounded-lg text-white font-semibold flex items-center justify-center gap-2 disabled:opacity-50"
                >
                  {isLoadingCep ? (
                    <Loader2 className="animate-spin" size={18} />
                  ) : (
                    <><MapPin size={18} /> Buscar CEP</>
                  )}
                </motion.button>
              </div>
            </div>

            <div className="grid grid-cols-3 gap-4">

              <div>
                <label className="block text-sm font-medium text-foreground mb-1">Estado</label>
                <input
                  type="text"
                  value={formData.estado}
                  onChange={(e) => setFormData({ ...formData, estado: e.target.value })}
                  maxLength={2}
                  className="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-foreground mb-1">Cidade</label>
                <input
                  type="text"
                  value={formData.cidade}
                  onChange={(e) => setFormData({ ...formData, cidade: e.target.value })}
                  className="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-foreground mb-1">Bairro</label>
              <input
                type="text"
                value={formData.bairro}
                onChange={(e) => setFormData({ ...formData, bairro: e.target.value })}
                className="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>

            <div className="grid grid-cols-3 gap-4">
              <div className="col-span-2">
                <label className="block text-sm font-medium text-foreground mb-1">Endereço</label>
                <input
                  type="text"
                  value={formData.endereco}
                  onChange={(e) => setFormData({ ...formData, endereco: e.target.value })}
                  className="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-foreground mb-1">Número</label>
                <input
                  type="text"
                  value={formData.numero}
                  onChange={(e) => setFormData({ ...formData, numero: e.target.value })}
                  className="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-foreground mb-1">Complemento</label>
              <input
                type="text"
                value={formData.complemento}
                onChange={(e) => setFormData({ ...formData, complemento: e.target.value })}
                className="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
              />
            </div>
          </div>
        );

      case 'contatos':
        return (
          <div className="space-y-4">
            <div className="flex items-center justify-between mb-4">
              <h3 className="text-sm font-medium text-foreground">Contatos Adicionais</h3>
              <motion.button
                type="button"
                whileHover={{ scale: 1.05 }}
                whileTap={{ scale: 0.95 }}
                onClick={addContato}
                className="px-3 py-2 bg-blue-500 hover:bg-blue-600 rounded-lg text-white text-sm font-semibold flex items-center gap-2"
              >
                <Plus size={16} />
                Adicionar Contato
              </motion.button>
            </div>

            {contatos.length === 0 ? (
              <div className="text-center py-8 text-muted-foreground text-sm">
                Nenhum contato adicional. Clique em "Adicionar Contato" para incluir.
              </div>
            ) : (
              <div className="space-y-3">
                {contatos.map((contato, index) => (
                  <div key={index} className="p-4 bg-white/5 rounded-lg border border-white/10">
                    <div className="flex items-start gap-3">
                      <div className="flex-1 grid grid-cols-3 gap-3">
                        <div>
                          <label className="block text-xs font-medium text-foreground mb-1">Tipo</label>
                          <select
                            value={contato.tipo}
                            onChange={(e) => updateContato(index, 'tipo', e.target.value)}
                            className="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                          >
                            <option value="celular">Celular</option>
                            <option value="telefone">Telefone</option>
                            <option value="email">E-mail</option>
                            <option value="whatsapp">WhatsApp</option>
                            <option value="outro">Outro</option>
                          </select>
                        </div>

                        <div>
                          <label className="block text-xs font-medium text-foreground mb-1">Contato</label>
                          <input
                            type="text"
                            value={contato.contato}
                            onChange={(e) => updateContato(index, 'contato', e.target.value)}
                            className="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                          />
                        </div>

                        <div>
                          <label className="block text-xs font-medium text-foreground mb-1">Descrição</label>
                          <input
                            type="text"
                            value={contato.descricao}
                            onChange={(e) => updateContato(index, 'descricao', e.target.value)}
                            placeholder="Ex: Comercial"
                            className="w-full px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                          />
                        </div>
                      </div>

                      <motion.button
                        type="button"
                        whileHover={{ scale: 1.1 }}
                        whileTap={{ scale: 0.9 }}
                        onClick={() => removeContato(index)}
                        className="mt-6 p-2 rounded-lg bg-red-500/20 text-red-300 hover:bg-red-500/30"
                      >
                        <Trash2 size={16} />
                      </motion.button>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>
        );

      default:
        return null;
    }
  };

  return (
    <div className="flex">
      <Sidebar />

      <div className="page-shell">
        <motion.div
          initial={{ opacity: 0, y: 16 }}
          animate={{ opacity: 1, y: 0 }}
          className="max-w-7xl mx-auto"
        >
          <div className="page-header mb-8">
            <div>
              <h1 className="page-title mb-2">Pessoas</h1>
              <p className="page-subtitle">Cadastro completo de clientes, proprietários e contatos.</p>
            </div>
            <motion.button
              whileHover={{ scale: 1.05 }}
              whileTap={{ scale: 0.95 }}
              onClick={openCreateModal}
              className="flex w-full items-center justify-center gap-2 rounded-lg bg-gradient-to-r from-blue-500 to-blue-600 px-5 py-3 font-semibold text-white sm:w-auto"
            >
              <Plus size={18} />
              Nova Pessoa
            </motion.button>
          </div>

          <div className="glass-panel p-6 rounded-2xl mb-6">
            <div className="flex items-center gap-3 flex-wrap">
              <div className="flex-1 min-w-[300px]">
                <div className="relative">
                  <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground" size={18} />
                  <input
                    type="text"
                    placeholder="Buscar por nome, CPF, CNPJ, email ou telefone..."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
                    className="w-full pl-10 pr-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                  />
                </div>
              </div>

              <select
                value={tipoFilter}
                onChange={(e) => setTipoFilter(e.target.value)}
                className="px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="todos">Todos</option>
                <option value="fisica">Pessoa Física</option>
                <option value="juridica">Pessoa Jurídica</option>
              </select>

              <motion.button
                whileHover={{ scale: 1.05 }}
                whileTap={{ scale: 0.95 }}
                onClick={handleSearch}
                className="px-5 py-3 bg-blue-500 hover:bg-blue-600 rounded-lg text-white font-semibold"
              >
                Buscar
              </motion.button>
            </div>
          </div>

          <div className="glass-panel p-6 rounded-2xl">
            <div className="flex items-center justify-between mb-6">
              <h2 className="text-xl font-bold text-foreground flex items-center gap-2">
                <UserRound size={22} />
                Lista de pessoas
              </h2>
              <span className="text-sm text-muted-foreground">
                Página {page} de {lastPage}
              </span>
            </div>

            {isLoading ? (
              <div className="flex items-center justify-center py-12">
                <Loader2 className="w-8 h-8 animate-spin text-primary" />
              </div>
            ) : pessoas.length === 0 ? (
              <div className="text-center py-12 text-muted-foreground">
                Nenhuma pessoa encontrada.
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="text-left text-muted-foreground">
                      <th className="pb-3">Nome</th>
                      <th className="pb-3">Tipo</th>
                      <th className="pb-3">CPF/CNPJ</th>
                      <th className="pb-3">Email</th>
                      <th className="pb-3">Telefone</th>
                      <th className="pb-3">Cidade/UF</th>
                      <th className="pb-3 text-right">Ações</th>
                    </tr>
                  </thead>
                  <tbody>
                    {pessoas.map((pessoa) => (
                      <tr key={pessoa.id} className="border-t border-white/10">
                        <td className="py-3 text-foreground">{pessoa.nome}</td>
                        <td className="py-3 text-foreground">
                          {pessoa.tipo === 'fisica' ? 'Física' : 'Jurídica'}
                        </td>
                        <td className="py-3 text-foreground">{pessoa.cpf || pessoa.cnpj || '-'}</td>
                        <td className="py-3 text-foreground">{pessoa.email || '-'}</td>
                        <td className="py-3 text-foreground">{pessoa.celular || pessoa.telefone || '-'}</td>
                        <td className="py-3 text-foreground">
                          {pessoa.cidade && pessoa.estado ? `${pessoa.cidade}/${pessoa.estado}` : '-'}
                        </td>
                        <td className="py-3 text-right">
                          <div className="flex items-center justify-end gap-2">
                            <motion.button
                              whileHover={{ scale: 1.1 }}
                              whileTap={{ scale: 0.9 }}
                              onClick={() => openEditModal(pessoa)}
                              className="p-2 rounded-lg bg-blue-500/20 text-blue-300 hover:bg-blue-500/30"
                            >
                              <Edit size={16} />
                            </motion.button>
                            <motion.button
                              whileHover={{ scale: 1.1 }}
                              whileTap={{ scale: 0.9 }}
                              onClick={() => handleDeleteClick(pessoa.id, pessoa.nome)}
                              className="p-2 rounded-lg bg-red-500/20 text-red-300 hover:bg-red-500/30"
                            >
                              <Trash2 size={16} />
                            </motion.button>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}

            {!isLoading && pessoas.length > 0 && (
              <div className="flex items-center justify-center gap-3 mt-8">
                <motion.button
                  whileHover={{ scale: 1.05 }}
                  whileTap={{ scale: 0.95 }}
                  onClick={() => setPage((prev) => Math.max(1, prev - 1))}
                  disabled={page === 1}
                  className="px-4 py-2 rounded-lg bg-white/10 border border-white/20 text-foreground disabled:opacity-40"
                >
                  Anterior
                </motion.button>
                <motion.button
                  whileHover={{ scale: 1.05 }}
                  whileTap={{ scale: 0.95 }}
                  onClick={() => setPage((prev) => Math.min(lastPage, prev + 1))}
                  disabled={page === lastPage}
                  className="px-4 py-2 rounded-lg bg-white/10 border border-white/20 text-foreground disabled:opacity-40"
                >
                  Próxima
                </motion.button>
              </div>
            )}
          </div>
        </motion.div>
      </div>

      {/* Modal de Formulário */}
      {showModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
          <motion.div
            initial={{ opacity: 0, scale: 0.9 }}
            animate={{ opacity: 1, scale: 1 }}
            className="bg-slate-900 rounded-2xl p-6 max-w-2xl w-full max-h-[90vh] overflow-y-auto"
          >
            <h2 className="text-2xl font-bold text-foreground mb-6">
              {editingPessoa ? 'Editar Pessoa' : 'Nova Pessoa'}
            </h2>

            <div className="flex gap-2 mb-6 border-b border-white/10">
              <button
                onClick={() => setCurrentTab('principal')}
                className={`px-4 py-2 font-medium transition-all ${
                  currentTab === 'principal'
                    ? 'text-blue-400 border-b-2 border-blue-400'
                    : 'text-muted-foreground hover:text-foreground'
                }`}
              >
                Principal
              </button>
              <button
                onClick={() => setCurrentTab('documentos')}
                className={`px-4 py-2 font-medium transition-all ${
                  currentTab === 'documentos'
                    ? 'text-blue-400 border-b-2 border-blue-400'
                    : 'text-muted-foreground hover:text-foreground'
                }`}
              >
                {formData.tipo === 'fisica' ? 'Pessoa Física' : 'Pessoa Jurídica'}
              </button>
              <button
                onClick={() => setCurrentTab('endereco')}
                className={`px-4 py-2 font-medium transition-all ${
                  currentTab === 'endereco'
                    ? 'text-blue-400 border-b-2 border-blue-400'
                    : 'text-muted-foreground hover:text-foreground'
                }`}
              >
                Endereço
              </button>
              <button
                onClick={() => setCurrentTab('contatos')}
                className={`px-4 py-2 font-medium transition-all ${
                  currentTab === 'contatos'
                    ? 'text-blue-400 border-b-2 border-blue-400'
                    : 'text-muted-foreground hover:text-foreground'
                }`}
              >
                Contatos
              </button>
            </div>

            <form onSubmit={handleSubmit}>
              {renderFormTab()}

              <div className="flex gap-3 mt-6">
                <motion.button
                  type="button"
                  whileHover={{ scale: 1.05 }}
                  whileTap={{ scale: 0.95 }}
                  onClick={() => setShowModal(false)}
                  className="flex-1 px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground font-semibold"
                >
                  Cancelar
                </motion.button>
                <motion.button
                  type="submit"
                  whileHover={{ scale: 1.05 }}
                  whileTap={{ scale: 0.95 }}
                  className="flex-1 px-4 py-3 bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg text-white font-semibold"
                >
                  Salvar
                </motion.button>
              </div>
            </form>
          </motion.div>
        </div>
      )}

      <AlertDialog open={deleteDialog.open} onOpenChange={(open) => setDeleteDialog(prev => ({ ...prev, open }))}>
        <AlertDialogContent className="bg-[#0f0f0f] border border-white/10">
          <AlertDialogHeader>
            <AlertDialogTitle>Excluir Pessoa</AlertDialogTitle>
            <AlertDialogDescription>
              Tem certeza que deseja excluir <strong>{deleteDialog.name}</strong>? Esta ação não pode ser desfeita.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel className="border-white/20 hover:bg-white/10">Cancelar</AlertDialogCancel>
            <AlertDialogAction onClick={handleDeleteConfirm} className="bg-red-600 hover:bg-red-700">
              Excluir
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  );
}
