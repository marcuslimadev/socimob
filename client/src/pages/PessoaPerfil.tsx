import React, { useState, useEffect } from 'react';
import { useRoute, useLocation } from 'wouter';
import { motion } from 'framer-motion';
import {
  User,
  FileText,
  Target,
  Activity,
  ArrowLeft,
  Upload,
  Download,
  Trash2,
  File,
  Mail,
  Phone,
  MapPin,
  Edit,
  MessageCircle,
  ExternalLink,
  Users,
  TrendingUp,
  Calendar,
  MessageSquare,
  Building2,
  DollarSign,
  Clock,
  Send,
} from 'lucide-react';
import { api } from '@/lib/api';
import { toast } from 'sonner';
import PageLayout from '@/components/PageLayout';

interface Lead {
  id: number;
  nome?: string | null;
  email?: string | null;
  telefone?: string | null;
  whatsapp?: string | null;
  cpf?: string | null;
  estado_civil?: string | null;
  composicao_familiar?: string | null;
  profissao?: string | null;
  renda_mensal?: number | null;
  fonte_renda?: string | null;
  preferencia_tipo_imovel?: string | null;
  preferencia_bairro?: string | null;
  quartos?: number | null;
  suites?: number | null;
  garagem?: number | null;
  budget_min?: number | null;
  budget_max?: number | null;
  financiamento_status?: string | null;
  prazo_compra?: string | null;
  objetivo_compra?: string | null;
  preferencia_lazer?: string | null;
  preferencia_seguranca?: string | null;
  caracteristicas_desejadas?: string | null;
  observacoes_cliente?: string | null;
  diagnostico_ia?: string | null;
  diagnostico_status?: string | null;
  status?: string | null;
  fonte?: string | null;
  localizacao?: string | null;
  cidade?: string | null;
  state?: string | null;
}

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
  whatsapp?: string | null;
  cep?: string | null;
  cidade: string | null;
  estado: string | null;
  bairro?: string | null;
  endereco?: string | null;
  numero?: string | null;
  complemento?: string | null;
  observacoes?: string | null;
  ativo: boolean;
  created_at?: string;
  // Campos CRM
  papeis?: string[];
  status?: string;
  origem?: string;
  corretor_responsavel_id?: number | null;
  renda_mensal?: number | null;
  profissao?: string | null;
  lead?: Lead | null;
}

interface PessoaDocumento {
  id: number;
  nome: string;
  tipo?: string;
  mime_type: string;
  arquivo_url: string;
  status: string;
  created_at: string;
}

interface Interacao {
  id: number;
  tipo: string;
  descricao: string;
  data_interacao: string;
  usuario?: { id: number; name: string };
}

interface Relacionamento {
  id: number;
  tipo: string;
  pessoa_destino: { id: number; nome: string; tipo: string };
}

interface Estatisticas {
  total_interacoes: number;
  total_documentos: number;
  total_relacionamentos: number;
  total_indicacoes: number;
}

type TabType = 'informacoes' | 'documentos' | 'endereco' | 'atividades' | 'relacionamentos' | 'notas';

const PessoaPerfil: React.FC = () => {
  const [match, params] = useRoute('/pessoas/:id');
  const [, navigate] = useLocation();
  
  const id = params?.id;
  
  const [activeTab, setActiveTab] = useState<TabType>('informacoes');
  const [pessoa, setPessoa] = useState<Pessoa | null>(null);
  const [documents, setDocuments] = useState<PessoaDocumento[]>([]);
  const [loading, setLoading] = useState(true);
  const [uploading, setUploading] = useState(false);
  const [interacoes, setInteracoes] = useState<Interacao[]>([]);
  const [relacionamentos, setRelacionamentos] = useState<Relacionamento[]>([]);
  const [estatisticas, setEstatisticas] = useState<Estatisticas | null>(null);
  const [newNote, setNewNote] = useState('');
  const [loadingInteracoes, setLoadingInteracoes] = useState(false);
  const [recommendedProperties, setRecommendedProperties] = useState<any[]>([]);
  const [loadingProperties, setLoadingProperties] = useState(false);

  useEffect(() => {
    if (id) {
      loadPessoa();
      loadDocuments();
      loadInteracoes();
      loadRelacionamentos();
    }
  }, [id]);

  const loadPessoa = async () => {
    try {
      const response = await api.get(`/pessoas/${id}`);
      const pessoaData = response.data.data || response.data;
      setPessoa(pessoaData);
      if (pessoaData.estatisticas) {
        setEstatisticas(pessoaData.estatisticas);
      }
    } catch (error) {
      console.error('Erro ao carregar pessoa:', error);
      toast.error('Erro ao carregar dados da pessoa');
    } finally {
      setLoading(false);
    }
  };

  const loadDocuments = async () => {
    try {
      const response = await api.get(`/pessoas/${id}/documentos`);
      setDocuments(response.data.data || []);
    } catch (error) {
      console.error('Erro ao carregar documentos:', error);
    }
  };

  const loadInteracoes = async () => {
    try {
      setLoadingInteracoes(true);
      const response = await api.get(`/pessoas/${id}/interacoes`);
      setInteracoes(response.data.data || []);
    } catch (error) {
      console.error('Erro ao carregar interações:', error);
    } finally {
      setLoadingInteracoes(false);
    }
  };

  const loadRelacionamentos = async () => {
    try {
      const response = await api.get(`/pessoas/${id}/relacionamentos`);
      setRelacionamentos(response.data.data || []);
    } catch (error) {
      console.error('Erro ao carregar relacionamentos:', error);
    }
  };

  const handleAddNote = async () => {
    if (!newNote.trim()) return;

    try {
      await api.post(`/pessoas/${id}/interacoes`, {
        tipo: 'nota',
        descricao: newNote,
        data_interacao: new Date().toISOString(),
      });
      toast.success('Nota adicionada com sucesso');
      setNewNote('');
      loadInteracoes();
    } catch (error) {
      console.error('Erro ao adicionar nota:', error);
      toast.error('Erro ao adicionar nota');
    }
  };

  const handleWhatsApp = () => {
    const phone = (pessoa?.whatsapp || pessoa?.celular || pessoa?.telefone)?.replace(/\D/g, '');
    if (phone) {
      window.open(`https://wa.me/55${phone}`, '_blank');
    } else {
      toast.error('Número de telefone não encontrado');
    }
  };

  const handleEmail = () => {
    if (pessoa?.email) {
      window.location.href = `mailto:${pessoa.email}`;
    } else {
      toast.error('Email não encontrado');
    }
  };

  const handleCall = () => {
    const phone = pessoa?.celular || pessoa?.telefone;
    if (phone) {
      window.location.href = `tel:${phone}`;
    } else {
      toast.error('Número de telefone não encontrado');
    }
  };

  const handleFileUpload = async (event: React.ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0];
    if (!file) return;

    const formData = new FormData();
    formData.append('arquivo', file);
    formData.append('nome', file.name);
    formData.append('tipo', 'upload_manual');

    setUploading(true);
    try {
      await api.post(`/pessoas/${id}/documentos`, formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      toast.success('Documento enviado com sucesso');
      loadDocuments();
    } catch (error) {
      console.error('Erro ao enviar documento:', error);
      toast.error('Erro ao enviar documento');
    } finally {
      setUploading(false);
    }
  };

  const handleDeleteDocument = async (documentId: number) => {
    if (!confirm('Deseja realmente excluir este documento?')) return;

    try {
      await api.delete(`/pessoas/documentos/${documentId}`);
      toast.success('Documento excluído com sucesso');
      loadDocuments();
    } catch (error) {
      console.error('Erro ao excluir documento:', error);
      toast.error('Erro ao excluir documento');
    }
  };

  const handleDownloadAll = async () => {
    try {
      const response = await api.get(`/pessoas/${id}/documentos/export`, {
        responseType: 'blob',
      });
      
      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', `pessoa-${id}-documentos.zip`);
      document.body.appendChild(link);
      link.click();
      link.remove();
      window.URL.revokeObjectURL(url);
      
      toast.success('Download iniciado');
    } catch (error) {
      console.error('Erro ao baixar documentos:', error);
      toast.error('Erro ao baixar documentos');
    }
  };

  const tabs = [
    { id: 'informacoes', label: 'Informações', icon: User },
    { id: 'documentos', label: 'Documentos', icon: FileText, badge: documents.length },
    { id: 'endereco', label: 'Endereço', icon: MapPin },
    { id: 'atividades', label: 'Atividades', icon: Activity, badge: estatisticas?.total_interacoes },
    { id: 'relacionamentos', label: 'Relacionamentos', icon: Users, badge: estatisticas?.total_relacionamentos },
    { id: 'notas', label: 'Notas', icon: MessageSquare },
  ];

  if (loading) {
    return (
      <PageLayout>
        <div className="flex items-center justify-center h-screen">
          <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
        </div>
      </PageLayout>
    );
  }

  if (!pessoa) {
    return (
      <PageLayout>
        <div className="p-8">
          <p>Pessoa não encontrada</p>
        </div>
      </PageLayout>
    );
  }

  return (
    <PageLayout>
      <div className="-m-3 sm:-m-4 md:-m-6 lg:-m-8">
        {/* Header */}
        <div className="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
          <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-4">
                <button
                  onClick={() => navigate('/pessoas')}
                  className="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
                >
                  <ArrowLeft className="w-5 h-5 text-gray-600 dark:text-gray-400" />
                </button>
                <div>
                  <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                    {pessoa.nome}
                  </h1>
                  <div className="flex items-center gap-4 mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {pessoa.email && (
                      <div className="flex items-center gap-1">
                        <Mail className="w-4 h-4" />
                        {pessoa.email}
                      </div>
                    )}
                    {(pessoa.celular || pessoa.telefone) && (
                      <div className="flex items-center gap-1">
                        <Phone className="w-4 h-4" />
                        {pessoa.celular || pessoa.telefone}
                      </div>
                    )}
                    {pessoa.tipo && (
                      <span className="px-2 py-1 bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-400 rounded text-xs font-medium">
                        {pessoa.tipo === 'fisica' ? 'Pessoa Física' : 'Pessoa Jurídica'}
                      </span>
                    )}
                  </div>
                </div>
              </div>

              <div className="flex items-center gap-2">
                {/* Quick Action Buttons */}
                {(pessoa.celular || pessoa.telefone || pessoa.whatsapp) && (
                  <motion.button
                    whileHover={{ scale: 1.05 }}
                    whileTap={{ scale: 0.95 }}
                    onClick={handleWhatsApp}
                    className="flex items-center gap-2 px-3 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors"
                    title="WhatsApp"
                  >
                    <MessageCircle className="w-4 h-4" />
                  </motion.button>
                )}
                {pessoa.email && (
                  <motion.button
                    whileHover={{ scale: 1.05 }}
                    whileTap={{ scale: 0.95 }}
                    onClick={handleEmail}
                    className="flex items-center gap-2 px-3 py-2 bg-purple-600 hover:bg-purple-700 text-white rounded-lg transition-colors"
                    title="Email"
                  >
                    <Mail className="w-4 h-4" />
                  </motion.button>
                )}
                {(pessoa.celular || pessoa.telefone) && (
                  <motion.button
                    whileHover={{ scale: 1.05 }}
                    whileTap={{ scale: 0.95 }}
                    onClick={handleCall}
                    className="flex items-center gap-2 px-3 py-2 bg-orange-600 hover:bg-orange-700 text-white rounded-lg transition-colors"
                    title="Ligar"
                  >
                    <Phone className="w-4 h-4" />
                  </motion.button>
                )}
                <button
                  onClick={() => navigate(`/pessoas?edit=${id}`)}
                  className="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors"
                >
                  <Edit className="w-4 h-4" />
                  Editar
                </button>
              </div>
            </div>

            {/* Tabs */}
            <div className="flex gap-2 mt-6 border-b border-gray-200 dark:border-gray-700 overflow-x-auto">
              {tabs.map((tab) => {
                const Icon = tab.icon;
                return (
                  <button
                    key={tab.id}
                    onClick={() => setActiveTab(tab.id as TabType)}
                    className={`
                      flex items-center gap-2 px-4 py-3 font-medium text-sm transition-colors relative whitespace-nowrap
                      ${
                        activeTab === tab.id
                          ? 'text-blue-600 dark:text-blue-400'
                          : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200'
                      }
                    `}
                  >
                    <Icon className="w-4 h-4" />
                    {tab.label}
                    {tab.badge !== undefined && tab.badge > 0 && (
                      <span className="ml-1 px-2 py-0.5 bg-blue-100 dark:bg-blue-900/40 text-blue-800 dark:text-blue-300 text-xs rounded-full">
                        {tab.badge}
                      </span>
                    )}
                    {activeTab === tab.id && (
                      <motion.div
                        layoutId="activeTab"
                        className="absolute bottom-0 left-0 right-0 h-0.5 bg-blue-600 dark:bg-blue-400"
                      />
                    )}
                  </button>
                );
              })}
            </div>
          </div>
        </div>

        {/* Content */}
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          {/* Statistics Cards */}
          {estatisticas && (
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
              <motion.div
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: 0.1 }}
                className="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl p-6 text-white shadow-lg"
              >
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-blue-100 text-sm font-medium">Interações</p>
                    <p className="text-3xl font-bold mt-1">{estatisticas.total_interacoes}</p>
                  </div>
                  <Activity className="w-10 h-10 text-blue-200" />
                </div>
              </motion.div>

              <motion.div
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: 0.2 }}
                className="bg-gradient-to-br from-green-500 to-green-600 rounded-xl p-6 text-white shadow-lg"
              >
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-green-100 text-sm font-medium">Documentos</p>
                    <p className="text-3xl font-bold mt-1">{estatisticas.total_documentos}</p>
                  </div>
                  <FileText className="w-10 h-10 text-green-200" />
                </div>
              </motion.div>

              <motion.div
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: 0.3 }}
                className="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl p-6 text-white shadow-lg"
              >
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-purple-100 text-sm font-medium">Relacionamentos</p>
                    <p className="text-3xl font-bold mt-1">{estatisticas.total_relacionamentos}</p>
                  </div>
                  <Users className="w-10 h-10 text-purple-200" />
                </div>
              </motion.div>

              <motion.div
                initial={{ opacity: 0, y: 20 }}
                animate={{ opacity: 1, y: 0 }}
                transition={{ delay: 0.4 }}
                className="bg-gradient-to-br from-orange-500 to-orange-600 rounded-xl p-6 text-white shadow-lg"
              >
                <div className="flex items-center justify-between">
                  <div>
                    <p className="text-orange-100 text-sm font-medium">Indicações</p>
                    <p className="text-3xl font-bold mt-1">{estatisticas.total_indicacoes}</p>
                  </div>
                  <TrendingUp className="w-10 h-10 text-orange-200" />
                </div>
              </motion.div>
            </div>
          )}

          {activeTab === 'informacoes' && (
            <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
              <h2 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                Informações {pessoa.tipo === 'fisica' ? 'Pessoais' : 'da Empresa'}
              </h2>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                  <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                    Nome {pessoa.tipo === 'juridica' ? 'Fantasia' : ''}
                  </label>
                  <p className="mt-1 text-gray-900 dark:text-white">{pessoa.nome}</p>
                </div>

                {pessoa.tipo === 'fisica' && pessoa.cpf && (
                  <div>
                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                      CPF
                    </label>
                    <p className="mt-1 text-gray-900 dark:text-white">{pessoa.cpf}</p>
                  </div>
                )}

                {pessoa.tipo === 'juridica' && (
                  <>
                    {pessoa.cnpj && (
                      <div>
                        <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                          CNPJ
                        </label>
                        <p className="mt-1 text-gray-900 dark:text-white">{pessoa.cnpj}</p>
                      </div>
                    )}
                    {pessoa.razao_social && (
                      <div>
                        <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                          Razão Social
                        </label>
                        <p className="mt-1 text-gray-900 dark:text-white">{pessoa.razao_social}</p>
                      </div>
                    )}
                  </>
                )}

                {pessoa.email && (
                  <div>
                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                      Email
                    </label>
                    <p className="mt-1 text-gray-900 dark:text-white">{pessoa.email}</p>
                  </div>
                )}

                {pessoa.celular && (
                  <div>
                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                      Celular
                    </label>
                    <p className="mt-1 text-gray-900 dark:text-white">{pessoa.celular}</p>
                  </div>
                )}

                {pessoa.telefone && (
                  <div>
                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                      Telefone
                    </label>
                    <p className="mt-1 text-gray-900 dark:text-white">{pessoa.telefone}</p>
                  </div>
                )}

                {pessoa.profissao && (
                  <div>
                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                      Profissão
                    </label>
                    <p className="mt-1 text-gray-900 dark:text-white">{pessoa.profissao}</p>
                  </div>
                )}

                {pessoa.renda_mensal && (
                  <div>
                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                      Renda Mensal
                    </label>
                    <p className="mt-1 text-gray-900 dark:text-white">
                      R$ {pessoa.renda_mensal.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
                    </p>
                  </div>
                )}

                {pessoa.status && (
                  <div>
                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                      Status
                    </label>
                    <p className="mt-1 text-gray-900 dark:text-white">{pessoa.status}</p>
                  </div>
                )}

                {pessoa.origem && (
                  <div>
                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                      Origem
                    </label>
                    <p className="mt-1 text-gray-900 dark:text-white">{pessoa.origem}</p>
                  </div>
                )}

                {pessoa.created_at && (
                  <div>
                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                      Data de Cadastro
                    </label>
                    <p className="mt-1 text-gray-900 dark:text-white">
                      {new Date(pessoa.created_at).toLocaleDateString('pt-BR')}
                    </p>
                  </div>
                )}

                {pessoa.observacoes && (
                  <div className="md:col-span-2">
                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                      Observações
                    </label>
                    <p className="mt-1 text-gray-900 dark:text-white whitespace-pre-wrap">
                      {pessoa.observacoes}
                    </p>
                  </div>
                )}
              </div>

              {/* Dados do Lead - Se existir */}
              {pessoa.lead && (
                <>
                  {/* Perfil Pessoal do Lead */}
                  <div className="mt-8 bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-lg p-6">
                    <h3 className="text-md font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                      <User className="w-5 h-5 text-blue-600" />
                      Perfil Pessoal (Lead)
                    </h3>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                      {pessoa.lead.estado_civil && (
                        <div>
                          <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                            Estado Civil
                          </label>
                          <p className="mt-1 text-gray-900 dark:text-white capitalize">
                            {pessoa.lead.estado_civil.replace('_', ' ')}
                          </p>
                        </div>
                      )}
                      
                      {pessoa.lead.composicao_familiar && (
                        <div>
                          <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                            Composição Familiar
                          </label>
                          <p className="mt-1 text-gray-900 dark:text-white">
                            {pessoa.lead.composicao_familiar}
                          </p>
                        </div>
                      )}
                    </div>
                  </div>

                  {/* Dados Financeiros */}
                  <div className="mt-8 bg-gradient-to-r from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 rounded-lg p-6">
                    <h3 className="text-md font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                      <span className="text-xl">💰</span>
                      Dados Financeiros
                    </h3>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                      {pessoa.lead.renda_mensal && (
                        <div>
                          <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                            Renda Mensal
                          </label>
                          <p className="mt-1 text-gray-900 dark:text-white font-semibold">
                            R$ {Number(pessoa.lead.renda_mensal).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
                          </p>
                        </div>
                      )}
                      
                      {pessoa.lead.fonte_renda && (
                        <div>
                          <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                            Fonte de Renda
                          </label>
                          <p className="mt-1 text-gray-900 dark:text-white capitalize">
                            {pessoa.lead.fonte_renda.replace('_', ' ')}
                          </p>
                        </div>
                      )}
                      
                      {pessoa.lead.financiamento_status && (
                        <div>
                          <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                            Status do Financiamento
                          </label>
                          <p className="mt-1 text-gray-900 dark:text-white capitalize">
                            {pessoa.lead.financiamento_status.replace('_', ' ')}
                          </p>
                        </div>
                      )}
                      
                      {(pessoa.lead.budget_min || pessoa.lead.budget_max) && (
                        <div>
                          <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                            Orçamento
                          </label>
                          <p className="mt-1 text-gray-900 dark:text-white font-semibold">
                            {pessoa.lead.budget_min && `R$ ${Number(pessoa.lead.budget_min).toLocaleString('pt-BR', { minimumFractionDigits: 0 })}`}
                            {pessoa.lead.budget_min && pessoa.lead.budget_max && ' - '}
                            {pessoa.lead.budget_max && `R$ ${Number(pessoa.lead.budget_max).toLocaleString('pt-BR', { minimumFractionDigits: 0 })}`}
                          </p>
                        </div>
                      )}
                    </div>
                  </div>

                  {/* Preferências de Imóvel */}
                  <div className="mt-8 bg-gradient-to-r from-purple-50 to-pink-50 dark:from-purple-900/20 dark:to-pink-900/20 rounded-lg p-6">
                    <h3 className="text-md font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                      <span className="text-xl">🏠</span>
                      Preferências de Imóvel
                    </h3>
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                      {pessoa.lead.preferencia_tipo_imovel && (
                        <div>
                          <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                            Tipo de Imóvel
                          </label>
                          <p className="mt-1 text-gray-900 dark:text-white capitalize font-semibold">
                            {pessoa.lead.preferencia_tipo_imovel}
                          </p>
                        </div>
                      )}
                      
                      {pessoa.lead.preferencia_bairro && (
                        <div>
                          <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                            Bairro Preferido
                          </label>
                          <p className="mt-1 text-gray-900 dark:text-white font-semibold">
                            {pessoa.lead.preferencia_bairro}
                          </p>
                        </div>
                      )}
                      
                      {(pessoa.lead.quartos || pessoa.lead.suites || pessoa.lead.garagem) && (
                        <div>
                          <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                            Cômodos
                          </label>
                          <p className="mt-1 text-gray-900 dark:text-white">
                            {pessoa.lead.quartos && `${pessoa.lead.quartos} quartos`}
                            {pessoa.lead.quartos && pessoa.lead.suites && ', '}
                            {pessoa.lead.suites && `${pessoa.lead.suites} suítes`}
                            {(pessoa.lead.quartos || pessoa.lead.suites) && pessoa.lead.garagem && ', '}
                            {pessoa.lead.garagem && `${pessoa.lead.garagem} vagas`}
                          </p>
                        </div>
                      )}
                      
                      {pessoa.lead.preferencia_lazer && (
                        <div className="md:col-span-2">
                          <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                            Preferências de Lazer
                          </label>
                          <p className="mt-1 text-gray-900 dark:text-white">
                            {pessoa.lead.preferencia_lazer}
                          </p>
                        </div>
                      )}
                      
                      {pessoa.lead.preferencia_seguranca && (
                        <div>
                          <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                            Segurança
                          </label>
                          <p className="mt-1 text-gray-900 dark:text-white">
                            {pessoa.lead.preferencia_seguranca}
                          </p>
                        </div>
                      )}
                      
                      {pessoa.lead.caracteristicas_desejadas && (
                        <div className="md:col-span-3">
                          <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                            Características Desejadas
                          </label>
                          <p className="mt-1 text-gray-900 dark:text-white">
                            {pessoa.lead.caracteristicas_desejadas}
                          </p>
                        </div>
                      )}
                    </div>
                  </div>

                  {/* Timeline de Compra */}
                  <div className="mt-8 bg-gradient-to-r from-orange-50 to-amber-50 dark:from-orange-900/20 dark:to-amber-900/20 rounded-lg p-6">
                    <h3 className="text-md font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                      <span className="text-xl">⏰</span>
                      Timeline de Compra
                    </h3>
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                      {pessoa.lead.prazo_compra && (
                        <div>
                          <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                            Prazo para Compra
                          </label>
                          <p className="mt-1 text-gray-900 dark:text-white font-semibold capitalize">
                            {pessoa.lead.prazo_compra.replace('_', ' ')}
                          </p>
                        </div>
                      )}
                      
                      {pessoa.lead.objetivo_compra && (
                        <div>
                          <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                            Objetivo da Compra
                          </label>
                          <p className="mt-1 text-gray-900 dark:text-white capitalize">
                            {pessoa.lead.objetivo_compra}
                          </p>
                        </div>
                      )}
                    </div>
                  </div>

                  {/* Diagnóstico e Observações */}
                  {(pessoa.lead.observacoes_cliente || pessoa.lead.diagnostico_ia) && (
                    <div className="mt-8 bg-gradient-to-r from-gray-50 to-slate-50 dark:from-gray-900/20 dark:to-slate-900/20 rounded-lg p-6">
                      <h3 className="text-md font-semibold text-gray-900 dark:text-white mb-4 flex items-center gap-2">
                        <Activity className="w-5 h-5 text-gray-600" />
                        Diagnóstico e Observações
                      </h3>
                      <div className="grid grid-cols-1 gap-6">
                        {pessoa.lead.observacoes_cliente && (
                          <div>
                            <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                              Observações do Cliente
                            </label>
                            <p className="mt-1 text-gray-900 dark:text-white whitespace-pre-wrap">
                              {pessoa.lead.observacoes_cliente}
                            </p>
                          </div>
                        )}
                        
                        {pessoa.lead.diagnostico_ia && (
                          <div>
                            <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                              Diagnóstico IA
                            </label>
                            <p className="mt-1 text-gray-900 dark:text-white whitespace-pre-wrap">
                              {pessoa.lead.diagnostico_ia}
                            </p>
                          </div>
                        )}
                      </div>
                    </div>
                  )}
                </>
              )}
            </div>
          )}

          {activeTab === 'documentos' && (
            <div className="bg-white dark:bg-gray-800 rounded-lg shadow">
              <div className="p-6 border-b border-gray-200 dark:border-gray-700">
                <div className="flex items-center justify-between">
                  <h2 className="text-lg font-semibold text-gray-900 dark:text-white">
                    Documentos ({documents.length})
                  </h2>
                  <div className="flex gap-2">
                    {documents.length > 0 && (
                      <button
                        onClick={handleDownloadAll}
                        className="flex items-center gap-2 px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg transition-colors"
                      >
                        <Download className="w-4 h-4" />
                        Baixar Todos (ZIP)
                      </button>
                    )}
                    <label className="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg cursor-pointer transition-colors">
                      <Upload className="w-4 h-4" />
                      {uploading ? 'Enviando...' : 'Enviar Arquivo'}
                      <input
                        type="file"
                        className="hidden"
                        onChange={handleFileUpload}
                        disabled={uploading}
                        accept=".pdf,.jpg,.jpeg,.png,.doc,.docx"
                      />
                    </label>
                  </div>
                </div>
              </div>

              <div className="divide-y divide-gray-200 dark:divide-gray-700">
                {documents.length === 0 ? (
                  <div className="p-12 text-center">
                    <FileText className="w-12 h-12 text-gray-400 mx-auto mb-3" />
                    <p className="text-gray-500 dark:text-gray-400">
                      Nenhum documento enviado ainda
                    </p>
                  </div>
                ) : (
                  documents.map((doc) => (
                    <div
                      key={doc.id}
                      className="p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors"
                    >
                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3 flex-1 min-w-0">
                          <div className="p-2 bg-blue-100 dark:bg-blue-900/30 rounded-lg">
                            <File className="w-5 h-5 text-blue-600 dark:text-blue-400" />
                          </div>
                          <div className="flex-1 min-w-0">
                            <p className="font-medium text-gray-900 dark:text-white truncate">
                              {doc.nome}
                            </p>
                            <div className="flex items-center gap-2 text-xs text-gray-500 dark:text-gray-400">
                              {doc.tipo && <span>{doc.tipo}</span>}
                              {doc.tipo && <span>•</span>}
                              <span>{new Date(doc.created_at).toLocaleDateString('pt-BR')}</span>
                              <span>•</span>
                              <span className={`
                                px-2 py-0.5 rounded-full
                                ${doc.status === 'aprovado' ? 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400' : ''}
                                ${doc.status === 'pendente' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-400' : ''}
                                ${doc.status === 'rejeitado' ? 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400' : ''}
                              `}>
                                {doc.status}
                              </span>
                            </div>
                          </div>
                        </div>
                        <div className="flex items-center gap-2">
                          <a
                            href={doc.arquivo_url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="p-2 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded-lg transition-colors"
                          >
                            <Download className="w-4 h-4" />
                          </a>
                          <button
                            onClick={() => handleDeleteDocument(doc.id)}
                            className="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg transition-colors"
                          >
                            <Trash2 className="w-4 h-4" />
                          </button>
                        </div>
                      </div>
                    </div>
                  ))
                )}
              </div>
            </div>
          )}

          {activeTab === 'endereco' && (
            <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
              <h2 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                Endereço
              </h2>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                {pessoa.cep && (
                  <div>
                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                      CEP
                    </label>
                    <p className="mt-1 text-gray-900 dark:text-white">{pessoa.cep}</p>
                  </div>
                )}

                {pessoa.endereco && (
                  <div>
                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                      Endereço
                    </label>
                    <p className="mt-1 text-gray-900 dark:text-white">{pessoa.endereco}</p>
                  </div>
                )}

                {pessoa.numero && (
                  <div>
                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                      Número
                    </label>
                    <p className="mt-1 text-gray-900 dark:text-white">{pessoa.numero}</p>
                  </div>
                )}

                {pessoa.complemento && (
                  <div>
                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                      Complemento
                    </label>
                    <p className="mt-1 text-gray-900 dark:text-white">{pessoa.complemento}</p>
                  </div>
                )}

                {pessoa.bairro && (
                  <div>
                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                      Bairro
                    </label>
                    <p className="mt-1 text-gray-900 dark:text-white">{pessoa.bairro}</p>
                  </div>
                )}

                {pessoa.cidade && (
                  <div>
                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                      Cidade
                    </label>
                    <p className="mt-1 text-gray-900 dark:text-white">{pessoa.cidade}</p>
                  </div>
                )}

                {pessoa.estado && (
                  <div>
                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                      Estado
                    </label>
                    <p className="mt-1 text-gray-900 dark:text-white">{pessoa.estado}</p>
                  </div>
                )}

                {pessoa.pais && (
                  <div>
                    <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                      País
                    </label>
                    <p className="mt-1 text-gray-900 dark:text-white">{pessoa.pais}</p>
                  </div>
                )}
              </div>
            </div>
          )}

          {activeTab === 'atividades' && (
            <div className="bg-white dark:bg-gray-800 rounded-lg shadow">
              <div className="p-6 border-b border-gray-200 dark:border-gray-700">
                <h2 className="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                  <Activity className="w-5 h-5" />
                  Histórico de Atividades ({interacoes.length})
                </h2>
              </div>
              
              {loadingInteracoes ? (
                <div className="flex items-center justify-center p-12">
                  <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                </div>
              ) : interacoes.length === 0 ? (
                <div className="p-12 text-center">
                  <Activity className="w-12 h-12 text-gray-400 mx-auto mb-3" />
                  <p className="text-gray-500 dark:text-gray-400">
                    Nenhuma atividade registrada ainda
                  </p>
                </div>
              ) : (
                <div className="p-6 space-y-4">
                  {interacoes.map((interacao, index) => (
                    <motion.div
                      key={interacao.id}
                      initial={{ opacity: 0, x: -20 }}
                      animate={{ opacity: 1, x: 0 }}
                      transition={{ delay: index * 0.05 }}
                      className="flex gap-4 p-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg border border-gray-200 dark:border-gray-700"
                    >
                      <div className="flex-shrink-0">
                        <div className={`w-10 h-10 rounded-full flex items-center justify-center ${
                          interacao.tipo === 'ligacao' ? 'bg-blue-100 dark:bg-blue-900/30' :
                          interacao.tipo === 'email' ? 'bg-purple-100 dark:bg-purple-900/30' :
                          interacao.tipo === 'nota' ? 'bg-yellow-100 dark:bg-yellow-900/30' :
                          interacao.tipo === 'reuniao' ? 'bg-green-100 dark:bg-green-900/30' :
                          'bg-gray-100 dark:bg-gray-700'
                        }`}>
                          {interacao.tipo === 'ligacao' && <Phone className="w-5 h-5 text-blue-600 dark:text-blue-400" />}
                          {interacao.tipo === 'email' && <Mail className="w-5 h-5 text-purple-600 dark:text-purple-400" />}
                          {interacao.tipo === 'nota' && <MessageSquare className="w-5 h-5 text-yellow-600 dark:text-yellow-400" />}
                          {interacao.tipo === 'reuniao' && <Users className="w-5 h-5 text-green-600 dark:text-green-400" />}
                          {!['ligacao', 'email', 'nota', 'reuniao'].includes(interacao.tipo) && <Activity className="w-5 h-5 text-gray-600 dark:text-gray-400" />}
                        </div>
                      </div>
                      <div className="flex-1 min-w-0">
                        <div className="flex items-start justify-between gap-2">
                          <div>
                            <p className="font-medium text-gray-900 dark:text-white capitalize">
                              {interacao.tipo.replace('_', ' ')}
                            </p>
                            <p className="text-sm text-gray-600 dark:text-gray-400 mt-1">
                              {interacao.descricao}
                            </p>
                          </div>
                          <span className="text-xs text-gray-500 dark:text-gray-400 whitespace-nowrap">
                            {new Date(interacao.data_interacao).toLocaleString('pt-BR')}
                          </span>
                        </div>
                        {interacao.usuario && (
                          <p className="text-xs text-gray-500 dark:text-gray-400 mt-2">
                            Por: {interacao.usuario.name}
                          </p>
                        )}
                      </div>
                    </motion.div>
                  ))}
                </div>
              )}
            </div>
          )}

          {activeTab === 'relacionamentos' && (
            <div className="bg-white dark:bg-gray-800 rounded-lg shadow">
              <div className="p-6 border-b border-gray-200 dark:border-gray-700">
                <h2 className="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                  <Users className="w-5 h-5" />
                  Relacionamentos ({relacionamentos.length})
                </h2>
              </div>

              {relacionamentos.length === 0 ? (
                <div className="p-12 text-center">
                  <Users className="w-12 h-12 text-gray-400 mx-auto mb-3" />
                  <p className="text-gray-500 dark:text-gray-400">
                    Nenhum relacionamento cadastrado
                  </p>
                </div>
              ) : (
                <div className="divide-y divide-gray-200 dark:divide-gray-700">
                  {relacionamentos.map((rel) => (
                    <div key={rel.id} className="p-4 hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                          <div className="w-10 h-10 bg-gradient-to-br from-blue-500 to-purple-500 rounded-full flex items-center justify-center text-white font-semibold">
                            {rel.pessoa_destino.nome.charAt(0).toUpperCase()}
                          </div>
                          <div>
                            <p className="font-medium text-gray-900 dark:text-white">
                              {rel.pessoa_destino.nome}
                            </p>
                            <div className="flex items-center gap-2 mt-1">
                              <span className="text-xs px-2 py-0.5 bg-blue-100 dark:bg-blue-900/30 text-blue-800 dark:text-blue-400 rounded">
                                {rel.tipo}
                              </span>
                              <span className="text-xs text-gray-500 dark:text-gray-400">
                                {rel.pessoa_destino.tipo === 'fisica' ? 'Pessoa Física' : 'Pessoa Jurídica'}
                              </span>
                            </div>
                          </div>
                        </div>
                        <button
                          onClick={() => navigate(`/pessoas/${rel.pessoa_destino.id}`)}
                          className="p-2 text-blue-600 hover:bg-blue-50 dark:hover:bg-blue-900/30 rounded-lg transition-colors"
                        >
                          <ExternalLink className="w-4 h-4" />
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
          )}

          {activeTab === 'notas' && (
            <div className="bg-white dark:bg-gray-800 rounded-lg shadow">
              <div className="p-6 border-b border-gray-200 dark:border-gray-700">
                <h2 className="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                  <MessageSquare className="w-5 h-5" />
                  Notas e Comentários
                </h2>
              </div>

              <div className="p-6">
                <div className="flex gap-3 mb-6">
                  <textarea
                    value={newNote}
                    onChange={(e) => setNewNote(e.target.value)}
                    placeholder="Adicionar uma nota..."
                    className="flex-1 px-4 py-3 bg-gray-50 dark:bg-gray-900 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"
                    rows={3}
                  />
                  <motion.button
                    whileHover={{ scale: 1.05 }}
                    whileTap={{ scale: 0.95 }}
                    onClick={handleAddNote}
                    disabled={!newNote.trim()}
                    className="px-6 py-3 bg-blue-600 hover:bg-blue-700 disabled:bg-gray-400 text-white rounded-lg transition-colors flex items-center gap-2 h-fit"
                  >
                    <Send className="w-4 h-4" />
                    Adicionar
                  </motion.button>
                </div>

                <div className="space-y-4">
                  {interacoes.filter(i => i.tipo === 'nota').map((nota, index) => (
                    <motion.div
                      key={nota.id}
                      initial={{ opacity: 0, y: 10 }}
                      animate={{ opacity: 1, y: 0 }}
                      transition={{ delay: index * 0.05 }}
                      className="p-4 bg-yellow-50 dark:bg-yellow-900/10 border border-yellow-200 dark:border-yellow-800 rounded-lg"
                    >
                      <p className="text-gray-900 dark:text-white whitespace-pre-wrap">
                        {nota.descricao}
                      </p>
                      <div className="flex items-center justify-between mt-3 text-xs text-gray-500 dark:text-gray-400">
                        <span>{nota.usuario?.name || 'Sistema'}</span>
                        <span>{new Date(nota.data_interacao).toLocaleString('pt-BR')}</span>
                      </div>
                    </motion.div>
                  ))}
                  {interacoes.filter(i => i.tipo === 'nota').length === 0 && (
                    <p className="text-center text-gray-500 dark:text-gray-400 py-8">
                      Nenhuma nota adicionada ainda
                    </p>
                  )}
                </div>
              </div>
            </div>
          )}
        </div>
      </div>
    </PageLayout>
  );
};

export default PessoaPerfil;
