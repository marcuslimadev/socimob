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
} from 'lucide-react';
import { api } from '@/lib/api';
import { toast } from 'sonner';
import PageLayout from '@/components/PageLayout';

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

type TabType = 'informacoes' | 'documentos' | 'endereco' | 'atividades';

const PessoaPerfil: React.FC = () => {
  const [match, params] = useRoute('/pessoas/:id');
  const [, navigate] = useLocation();
  
  const id = params?.id;
  
  const [activeTab, setActiveTab] = useState<TabType>('informacoes');
  const [pessoa, setPessoa] = useState<Pessoa | null>(null);
  const [documents, setDocuments] = useState<PessoaDocumento[]>([]);
  const [loading, setLoading] = useState(true);
  const [uploading, setUploading] = useState(false);

  useEffect(() => {
    if (id) {
      loadPessoa();
      loadDocuments();
    }
  }, [id]);

  const loadPessoa = async () => {
    try {
      const response = await api.get(`/pessoas/${id}`);
      setPessoa(response.data.data || response.data);
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
    { id: 'documentos', label: 'Documentos', icon: FileText },
    { id: 'endereco', label: 'Endereço', icon: MapPin },
    { id: 'atividades', label: 'Atividades', icon: Activity },
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

              <button
                onClick={() => navigate(`/pessoas?edit=${id}`)}
                className="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors"
              >
                <Edit className="w-4 h-4" />
                Editar
              </button>
            </div>

            {/* Tabs */}
            <div className="flex gap-2 mt-6 border-b border-gray-200 dark:border-gray-700">
              {tabs.map((tab) => {
                const Icon = tab.icon;
                return (
                  <button
                    key={tab.id}
                    onClick={() => setActiveTab(tab.id as TabType)}
                    className={`
                      flex items-center gap-2 px-4 py-3 font-medium text-sm transition-colors relative
                      ${
                        activeTab === tab.id
                          ? 'text-blue-600 dark:text-blue-400'
                          : 'text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-200'
                      }
                    `}
                  >
                    <Icon className="w-4 h-4" />
                    {tab.label}
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
            <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
              <h2 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">
                Histórico de Atividades
              </h2>
              <p className="text-gray-500 dark:text-gray-400">
                Em desenvolvimento - aqui será exibido o histórico de interações com a pessoa.
              </p>
            </div>
          )}
        </div>
      </div>
    </PageLayout>
  );
};

export default PessoaPerfil;
