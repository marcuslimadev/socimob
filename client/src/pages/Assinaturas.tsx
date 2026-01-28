import { useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import { FileSignature, Loader2, Search, Plus, Filter, Upload, Trash2, UserPlus } from 'lucide-react';
import { toast } from 'sonner';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';

interface Documento {
  id: number;
  titulo: string;
  tipo: string | null;
  status: string;
  descricao: string | null;
  data_envio: string | null;
  data_conclusao: string | null;
  assinantes: any[] | null;
}

const getStatusColor = (status: string) => {
  const colors: Record<string, string> = {
    pendente: 'bg-yellow-500/20 text-yellow-300',
    assinado: 'bg-green-500/20 text-green-300',
    cancelado: 'bg-red-500/20 text-red-300',
    expirado: 'bg-gray-500/20 text-gray-300',
  };
  return colors[status] || 'bg-gray-500/20 text-gray-300';
};

const getStatusLabel = (status: string) => {
  const labels: Record<string, string> = {
    pendente: 'Pendente',
    assinado: 'Assinado',
    cancelado: 'Cancelado',
    expirado: 'Expirado',
  };
  return labels[status] || status;
};

const getTipoLabel = (tipo: string | null) => {
  if (!tipo) return '-';
  const labels: Record<string, string> = {
    contrato: 'Contrato',
    procuracao: 'Procuração',
    termo: 'Termo',
    outro: 'Outro',
  };
  return labels[tipo] || tipo;
};

export default function Assinaturas() {
  const [documentos, setDocumentos] = useState<Documento[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState('');
  const [statusFilter, setStatusFilter] = useState('todos');
  const [tipoFilter, setTipoFilter] = useState('todos');
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  const [showModal, setShowModal] = useState(false);
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [formData, setFormData] = useState({
    titulo: '',
    tipo: 'contrato',
    descricao: '',
  });
  const [assinantes, setAssinantes] = useState<Array<{ nome: string; email: string; cpf: string }>>([]);

  useEffect(() => {
    fetchDocumentos();
  }, [page, statusFilter, tipoFilter]);

  const fetchDocumentos = async () => {
    try {
      setIsLoading(true);
      const params: Record<string, string | number> = { page, per_page: 10 };

      if (statusFilter !== 'todos') {
        params.status = statusFilter;
      }

      if (tipoFilter !== 'todos') {
        params.tipo = tipoFilter;
      }

      if (searchTerm) {
        params.search = searchTerm;
      }

      const response = await api.get('/assinaturas/documentos', { params });
      setDocumentos(response.data.data || []);
      setPage(response.data.current_page || 1);
      setLastPage(response.data.last_page || 1);
    } catch (error) {
      console.error('Erro ao carregar documentos:', error);
      toast.error('Erro ao carregar documentos');
    } finally {
      setIsLoading(false);
    }
  };

  const handleSearch = () => {
    setPage(1);
    fetchDocumentos();
  };

  const formatDate = (dateString: string | null) => {
    if (!dateString) return '-';
    const date = new Date(dateString);
    return date.toLocaleDateString('pt-BR', { dateStyle: 'short' });
  };

  return (
    <div className="flex">
      <Sidebar />

      <div className="flex-1 md:ml-80 min-h-screen p-4 md:p-8">
        <motion.div
          initial={{ opacity: 0, y: 16 }}
          animate={{ opacity: 1, y: 0 }}
          className="max-w-7xl mx-auto"
        >
          <div className="mb-8 flex items-center justify-between">
            <div>
              <h1 className="text-4xl font-bold gradient-text mb-2">Assinaturas Eletrônicas</h1>
              <p className="text-muted-foreground">Gestão de documentos e assinantes.</p>
            </div>
            <motion.button
              whileHover={{ scale: 1.05 }}
              whileTap={{ scale: 0.95 }}
              onClick={() => setShowModal(true)}
              className="flex items-center gap-2 px-5 py-3 bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg text-white font-semibold"
            >
              <Plus size={18} />
              Novo Documento
            </motion.button>
          </div>

          <div className="glass-panel p-6 rounded-2xl mb-6">
            <div className="flex items-center gap-3 flex-wrap">
              <div className="flex-1 min-w-[300px]">
                <div className="relative">
                  <Search className="absolute left-3 top-1/2 transform -translate-y-1/2 text-muted-foreground" size={18} />
                  <input
                    type="text"
                    placeholder="Buscar documentos..."
                    value={searchTerm}
                    onChange={(e) => setSearchTerm(e.target.value)}
                    onKeyDown={(e) => e.key === 'Enter' && handleSearch()}
                    className="w-full pl-10 pr-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                  />
                </div>
              </div>

              <Filter size={18} className="text-muted-foreground" />

              <select
                value={statusFilter}
                onChange={(e) => setStatusFilter(e.target.value)}
                className="px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="todos">Todos os status</option>
                <option value="pendente">Pendente</option>
                <option value="assinado">Assinado</option>
                <option value="cancelado">Cancelado</option>
                <option value="expirado">Expirado</option>
              </select>

              <select
                value={tipoFilter}
                onChange={(e) => setTipoFilter(e.target.value)}
                className="px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
              >
                <option value="todos">Todos os tipos</option>
                <option value="contrato">Contrato</option>
                <option value="procuracao">Procuração</option>
                <option value="termo">Termo</option>
                <option value="outro">Outro</option>
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
                <FileSignature size={22} />
                Documentos para assinatura
              </h2>
              <span className="text-sm text-muted-foreground">
                Página {page} de {lastPage}
              </span>
            </div>

            {isLoading ? (
              <div className="flex items-center justify-center py-12">
                <Loader2 className="w-8 h-8 animate-spin text-primary" />
              </div>
            ) : documentos.length === 0 ? (
              <div className="text-center py-12 text-muted-foreground">
                <FileSignature size={48} className="mx-auto mb-4 opacity-50" />
                <p className="text-lg mb-2">Nenhum documento encontrado</p>
                <p className="text-sm">Comece criando um novo documento para assinatura</p>
              </div>
            ) : (
              <div className="overflow-x-auto">
                <table className="w-full text-sm">
                  <thead>
                    <tr className="text-left text-muted-foreground">
                      <th className="pb-3">Título</th>
                      <th className="pb-3">Tipo</th>
                      <th className="pb-3">Status</th>
                      <th className="pb-3">Assinantes</th>
                      <th className="pb-3">Data Envio</th>
                      <th className="pb-3">Data Conclusão</th>
                    </tr>
                  </thead>
                  <tbody>
                    {documentos.map((doc) => (
                      <tr key={doc.id} className="border-t border-white/10">
                        <td className="py-3 text-foreground font-medium">{doc.titulo}</td>
                        <td className="py-3 text-foreground">{getTipoLabel(doc.tipo)}</td>
                        <td className="py-3">
                          <span className={`px-2 py-1 rounded text-xs ${getStatusColor(doc.status)}`}>
                            {getStatusLabel(doc.status)}
                          </span>
                        </td>
                        <td className="py-3 text-foreground">
                          {doc.assinantes ? doc.assinantes.length : 0}
                        </td>
                        <td className="py-3 text-foreground">{formatDate(doc.data_envio)}</td>
                        <td className="py-3 text-foreground">{formatDate(doc.data_conclusao)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            )}

            {!isLoading && documentos.length > 0 && (
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

      {/* Modal de Novo Documento */}
      {showModal && (
        <div className="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
          <motion.div
            initial={{ opacity: 0, scale: 0.9 }}
            animate={{ opacity: 1, scale: 1 }}
            className="bg-slate-900 rounded-2xl p-6 max-w-2xl w-full max-h-[90vh] overflow-y-auto"
          >
            <h2 className="text-2xl font-bold text-foreground mb-6">Novo Documento para Assinatura</h2>

            <form onSubmit={async (e) => {
              e.preventDefault();
              if (!formData.titulo) {
                toast.error('Título é obrigatório');
                return;
              }

              try {
                const dataToSend = {
                  ...formData,
                  assinantes: assinantes.length > 0 ? assinantes : null,
                  arquivo_url: selectedFile ? selectedFile.name : null, // Simulado
                };

                await api.post('/assinaturas/documentos', dataToSend);
                toast.success('Documento criado com sucesso');
                setShowModal(false);
                setFormData({ titulo: '', tipo: 'contrato', descricao: '' });
                setAssinantes([]);
                setSelectedFile(null);
                fetchDocumentos();
              } catch (error) {
                console.error('Erro ao criar documento:', error);
                toast.error('Erro ao criar documento');
              }
            }}>
              <div className="space-y-4">
                <div>
                  <label className="block text-sm font-medium text-foreground mb-1">Título *</label>
                  <input
                    type="text"
                    value={formData.titulo}
                    onChange={(e) => setFormData({ ...formData, titulo: e.target.value })}
                    className="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                    required
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-foreground mb-1">Tipo</label>
                  <select
                    value={formData.tipo}
                    onChange={(e) => setFormData({ ...formData, tipo: e.target.value })}
                    className="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                  >
                    <option value="contrato">Contrato</option>
                    <option value="procuracao">Procuração</option>
                    <option value="termo">Termo</option>
                    <option value="outro">Outro</option>
                  </select>
                </div>

                <div>
                  <label className="block text-sm font-medium text-foreground mb-1">Descrição</label>
                  <textarea
                    value={formData.descricao}
                    onChange={(e) => setFormData({ ...formData, descricao: e.target.value })}
                    rows={3}
                    className="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                  />
                </div>

                <div>
                  <label className="block text-sm font-medium text-foreground mb-2">Upload de Arquivo (PDF)</label>
                  <div className="border-2 border-dashed border-white/20 rounded-lg p-6 text-center">
                    {selectedFile ? (
                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-3">
                          <FileSignature size={24} className="text-blue-400" />
                          <span className="text-foreground">{selectedFile.name}</span>
                        </div>
                        <motion.button
                          type="button"
                          whileHover={{ scale: 1.1 }}
                          whileTap={{ scale: 0.9 }}
                          onClick={() => setSelectedFile(null)}
                          className="p-2 rounded-lg bg-red-500/20 text-red-300 hover:bg-red-500/30"
                        >
                          <Trash2 size={16} />
                        </motion.button>
                      </div>
                    ) : (
                      <label className="cursor-pointer">
                        <Upload size={32} className="mx-auto mb-2 text-muted-foreground" />
                        <p className="text-sm text-foreground mb-1">Clique para fazer upload ou arraste o arquivo</p>
                        <p className="text-xs text-muted-foreground">Apenas PDF, máximo 10MB</p>
                        <input
                          type="file"
                          accept=".pdf"
                          onChange={(e) => setSelectedFile(e.target.files?.[0] || null)}
                          className="hidden"
                        />
                      </label>
                    )}
                  </div>
                </div>

                <div>
                  <div className="flex items-center justify-between mb-3">
                    <label className="block text-sm font-medium text-foreground">Assinantes</label>
                    <motion.button
                      type="button"
                      whileHover={{ scale: 1.05 }}
                      whileTap={{ scale: 0.95 }}
                      onClick={() => setAssinantes([...assinantes, { nome: '', email: '', cpf: '' }])}
                      className="px-3 py-1 bg-blue-500 hover:bg-blue-600 rounded-lg text-white text-sm flex items-center gap-2"
                    >
                      <UserPlus size={14} />
                      Adicionar
                    </motion.button>
                  </div>

                  {assinantes.length === 0 ? (
                    <div className="text-center py-4 text-muted-foreground text-sm bg-white/5 rounded-lg">
                      Nenhum assinante adicionado
                    </div>
                  ) : (
                    <div className="space-y-3">
                      {assinantes.map((assinante, index) => (
                        <div key={index} className="p-3 bg-white/5 rounded-lg border border-white/10">
                          <div className="flex gap-3 items-start">
                            <div className="flex-1 grid grid-cols-3 gap-2">
                              <input
                                type="text"
                                placeholder="Nome"
                                value={assinante.nome}
                                onChange={(e) => {
                                  const newAssinantes = [...assinantes];
                                  newAssinantes[index].nome = e.target.value;
                                  setAssinantes(newAssinantes);
                                }}
                                className="px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                              />
                              <input
                                type="email"
                                placeholder="E-mail"
                                value={assinante.email}
                                onChange={(e) => {
                                  const newAssinantes = [...assinantes];
                                  newAssinantes[index].email = e.target.value;
                                  setAssinantes(newAssinantes);
                                }}
                                className="px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                              />
                              <input
                                type="text"
                                placeholder="CPF"
                                value={assinante.cpf}
                                onChange={(e) => {
                                  const newAssinantes = [...assinantes];
                                  newAssinantes[index].cpf = e.target.value;
                                  setAssinantes(newAssinantes);
                                }}
                                className="px-3 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                              />
                            </div>
                            <motion.button
                              type="button"
                              whileHover={{ scale: 1.1 }}
                              whileTap={{ scale: 0.9 }}
                              onClick={() => setAssinantes(assinantes.filter((_, i) => i !== index))}
                              className="p-2 rounded-lg bg-red-500/20 text-red-300 hover:bg-red-500/30"
                            >
                              <Trash2 size={16} />
                            </motion.button>
                          </div>
                        </div>
                      ))}
                    </div>
                  )}
                </div>
              </div>

              <div className="flex gap-3 mt-6">
                <motion.button
                  type="button"
                  whileHover={{ scale: 1.05 }}
                  whileTap={{ scale: 0.95 }}
                  onClick={() => {
                    setShowModal(false);
                    setFormData({ titulo: '', tipo: 'contrato', descricao: '' });
                    setAssinantes([]);
                    setSelectedFile(null);
                  }}
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
                  Criar Documento
                </motion.button>
              </div>
            </form>
          </motion.div>
        </div>
      )}
    </div>
  );
}
