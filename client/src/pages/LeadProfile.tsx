import React, { useState, useEffect } from 'react';
import { useRoute, useLocation } from 'wouter';
import { motion } from 'framer-motion';
import {
  User,
  FileText,
  Target,
  Activity,
  Home,
  ArrowLeft,
  Upload,
  Download,
  Trash2,
  File,
  Mail,
  Phone,
} from 'lucide-react';
import { api } from '@/lib/api';
import { toast } from 'sonner';

interface Lead {
  id: number;
  nome: string;
  email?: string;
  telefone?: string;
  origem?: string;
  status?: string;
  created_at: string;
}

interface LeadDocument {
  id: number;
  nome: string;
  tipo?: string;
  mime_type: string;
  arquivo_url: string;
  status: string;
  created_at: string;
}

type TabType = 'informacoes' | 'documentos' | 'intencoes' | 'atividades';

const LeadProfile: React.FC = () => {
  const [match, params] = useRoute('/leads/:id');
  const [, navigate] = useLocation();
  
  const id = params?.id;
  
  const [activeTab, setActiveTab] = useState<TabType>('informacoes');
  const [lead, setLead] = useState<Lead | null>(null);
  const [documents, setDocuments] = useState<LeadDocument[]>([]);
  const [loading, setLoading] = useState(true);
  const [uploading, setUploading] = useState(false);

  useEffect(() => {
    if (id) {
      loadLead();
      loadDocuments();
    }
  }, [id]);

  const loadLead = async () => {
    try {
      const response = await api.get(`/leads/${id}`);
      setLead(response.data.data || response.data);
    } catch (error) {
      console.error('Erro ao carregar lead:', error);
      toast.error('Erro ao carregar dados do lead');
    } finally {
      setLoading(false);
    }
  };

  const loadDocuments = async () => {
    try {
      const response = await api.get(`/leads/${id}/documents`);
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
      await api.post(`/leads/${id}/documents`, formData, {
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
      await api.delete(`/leads/${id}/documents/${documentId}`);
      toast.success('Documento excluído com sucesso');
      loadDocuments();
    } catch (error) {
      console.error('Erro ao excluir documento:', error);
      toast.error('Erro ao excluir documento');
    }
  };

  const handleDownloadAll = async () => {
    try {
      const response = await api.get(`/leads/${id}/documents/export`, {
        responseType: 'blob',
      });
      
      const url = window.URL.createObjectURL(new Blob([response.data]));
      const link = document.createElement('a');
      link.href = url;
      link.setAttribute('download', `lead-${id}-documentos.zip`);
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
    { id: 'intencoes', label: 'Intenções', icon: Target },
    { id: 'atividades', label: 'Atividades', icon: Activity },
  ];

  if (loading) {
    return (
      <div className="flex items-center justify-center h-screen">
        <div className="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600"></div>
      </div>
    );
  }

  if (!lead) {
    return (
      <div className="p-8">
        <p>Lead não encontrado</p>
      </div>
    );
  }

  return (
    <div className="min-h-screen bg-gray-50 dark:bg-gray-900">
      {/* Header */}
      <div className="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-4">
              <button
                onClick={() => navigate(-1)}
                className="p-2 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors"
              >
                <ArrowLeft className="w-5 h-5 text-gray-600 dark:text-gray-400" />
              </button>
              <div>
                <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                  {lead.nome}
                </h1>
                <div className="flex items-center gap-4 mt-1 text-sm text-gray-500 dark:text-gray-400">
                  {lead.email && (
                    <div className="flex items-center gap-1">
                      <Mail className="w-4 h-4" />
                      {lead.email}
                    </div>
                  )}
                  {lead.telefone && (
                    <div className="flex items-center gap-1">
                      <Phone className="w-4 h-4" />
                      {lead.telefone}
                    </div>
                  )}
                </div>
              </div>
            </div>
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
              Informações do Lead
            </h2>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div>
                <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                  Nome
                </label>
                <p className="mt-1 text-gray-900 dark:text-white">{lead.nome}</p>
              </div>
              {lead.email && (
                <div>
                  <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                    Email
                  </label>
                  <p className="mt-1 text-gray-900 dark:text-white">{lead.email}</p>
                </div>
              )}
              {lead.telefone && (
                <div>
                  <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                    Telefone
                  </label>
                  <p className="mt-1 text-gray-900 dark:text-white">{lead.telefone}</p>
                </div>
              )}
              {lead.origem && (
                <div>
                  <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                    Origem
                  </label>
                  <p className="mt-1 text-gray-900 dark:text-white">{lead.origem}</p>
                </div>
              )}
              {lead.status && (
                <div>
                  <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                    Status
                  </label>
                  <p className="mt-1 text-gray-900 dark:text-white">{lead.status}</p>
                </div>
              )}
              <div>
                <label className="text-sm font-medium text-gray-500 dark:text-gray-400">
                  Data de Cadastro
                </label>
                <p className="mt-1 text-gray-900 dark:text-white">
                  {new Date(lead.created_at).toLocaleDateString('pt-BR')}
                </p>
              </div>
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
                            <span>•</span>
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
                      <button
                        onClick={() => handleDeleteDocument(doc.id)}
                        className="p-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/30 rounded-lg transition-colors"
                      >
                        <Trash2 className="w-4 h-4" />
                      </button>
                    </div>
                  </div>
                ))
              )}
            </div>
          </div>
        )}

        {activeTab === 'intencoes' && (
          <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h2 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">
              Intenções de Compra/Locação
            </h2>
            <p className="text-gray-500 dark:text-gray-400">
              Em desenvolvimento - aqui serão exibidas as intenções e preferências do lead.
            </p>
          </div>
        )}

        {activeTab === 'atividades' && (
          <div className="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            <h2 className="text-lg font-semibold text-gray-900 dark:text-white mb-4">
              Histórico de Atividades
            </h2>
            <p className="text-gray-500 dark:text-gray-400">
              Em desenvolvimento - aqui será exibido o histórico de interações com o lead.
            </p>
          </div>
        )}
      </div>
    </div>
  );
};

export default LeadProfile;
