import { useEffect, useState } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { 
  Home, 
  Save, 
  X, 
  MapPin, 
  Loader2, 
  ChevronRight, 
  ChevronLeft, 
  Check,
  Upload,
  Image as ImageIcon,
  Video,
  Trash2
} from 'lucide-react';
import { toast } from 'sonner';
import { useLocation, useRoute } from 'wouter';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';
import { useViaCep } from '@/hooks/useViaCep';

const defaultFormData = {
  tipo_imovel: 'apartamento',
  finalidade_imovel: 'venda',
  valor_venda: '',
  valor_condominio: '',
  valor_iptu: '',
  dormitorios: '',
  suites: '',
  banheiros: '',
  garagem: '',
  area_total: '',
  area_privativa: '',
  area_terreno: '',
  cep: '',
  estado: '',
  cidade: '',
  bairro: '',
  logradouro: '',
  numero: '',
  complemento: '',
  em_condominio: false,
  nome_condominio: '',
  descricao: '',
  active: true,
  exibir_imovel: true,
  exclusividade: false,
};

const STEPS = [
  { id: 1, title: 'Informações Básicas', icon: Home },
  { id: 2, title: 'Localização', icon: MapPin },
  { id: 3, title: 'Características', icon: Home },
  { id: 4, title: 'Fotos e Vídeos', icon: ImageIcon },
  { id: 5, title: 'Revisão', icon: Check },
];

interface MediaFile {
  id: string;
  file?: File;
  url: string;
  type: 'image' | 'video';
  preview: string;
  destaque?: boolean;
}

export default function ImovelFormWizard() {
  const [, setLocation] = useLocation();
  const [match, params] = useRoute('/properties/:id/editar');
  const isEditMode = Boolean(match && params?.id);
  const propertyId = params?.id;
  
  const [currentStep, setCurrentStep] = useState(1);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isLoadingProperty, setIsLoadingProperty] = useState(false);
  const [mediaFiles, setMediaFiles] = useState<MediaFile[]>([]);
  const [formData, setFormData] = useState(defaultFormData);
  
  const { buscarCep, isLoading: isLoadingCep } = useViaCep();

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
        logradouro: resultado.logradouro,
        complemento: resultado.complemento || formData.complemento,
      });
      toast.success('Endereço preenchido automaticamente');
    }
  };

  useEffect(() => {
    const fetchProperty = async () => {
      if (!isEditMode || !propertyId) return;

      try {
        setIsLoadingProperty(true);
        const response = await api.get(`/imoveis/${propertyId}`);
        const item = response.data?.data;
        if (!item) {
          toast.error('Imóvel não encontrado');
          setLocation('/properties');
          return;
        }

        setFormData({
          tipo_imovel: item.tipo_imovel || 'apartamento',
          finalidade_imovel: item.finalidade_imovel || 'venda',
          valor_venda: item.valor_venda != null ? String(item.valor_venda) : '',
          valor_condominio: item.valor_condominio != null ? String(item.valor_condominio) : '',
          valor_iptu: item.valor_iptu != null ? String(item.valor_iptu) : '',
          dormitorios: item.dormitorios != null ? String(item.dormitorios) : '',
          suites: item.suites != null ? String(item.suites) : '',
          banheiros: item.banheiros != null ? String(item.banheiros) : '',
          garagem: item.garagem != null ? String(item.garagem) : '',
          area_total: item.area_total != null ? String(item.area_total) : '',
          area_privativa: item.area_privativa != null ? String(item.area_privativa) : '',
          area_terreno: item.area_terreno != null ? String(item.area_terreno) : '',
          cep: item.cep || '',
          estado: item.estado || '',
          cidade: item.cidade || '',
          bairro: item.bairro || '',
          logradouro: item.logradouro || '',
          numero: item.numero || '',
          complemento: item.complemento || '',
          em_condominio: Boolean(item.em_condominio),
          nome_condominio: item.nome_condominio || '',
          descricao: item.descricao || '',
          active: Boolean(item.active),
          exibir_imovel: Boolean(item.exibir_imovel),
          exclusividade: Boolean(item.exclusividade),
        });

        // Carregar imagens existentes
        if (item.imagens && Array.isArray(item.imagens)) {
          const existingMedia: MediaFile[] = item.imagens.map((url: string, index: number) => ({
            id: `existing-${index}`,
            url,
            type: 'image' as const,
            preview: url,
            destaque: index === 0,
          }));
          setMediaFiles(existingMedia);
        }
      } catch (error) {
        console.error('Erro ao carregar imóvel:', error);
        toast.error('Erro ao carregar imóvel para edição');
        setLocation('/properties');
      } finally {
        setIsLoadingProperty(false);
      }
    };

    fetchProperty();
  }, [isEditMode, propertyId, setLocation]);

  const handleMediaUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    const files = Array.from(e.target.files || []);
    
    const newMedia: MediaFile[] = files.map((file) => {
      const isVideo = file.type.startsWith('video/');
      return {
        id: `new-${Date.now()}-${Math.random()}`,
        file,
        url: '',
        type: isVideo ? 'video' : 'image',
        preview: URL.createObjectURL(file),
        destaque: mediaFiles.length === 0,
      };
    });

    setMediaFiles([...mediaFiles, ...newMedia]);
    toast.success(`${files.length} arquivo(s) adicionado(s)`);
  };

  const handleRemoveMedia = (id: string) => {
    setMediaFiles(mediaFiles.filter(m => m.id !== id));
  };

  const handleSetDestaque = (id: string) => {
    setMediaFiles(mediaFiles.map(m => ({
      ...m,
      destaque: m.id === id,
    })));
  };

  const validateStep = (step: number): boolean => {
    switch (step) {
      case 1: // Informações Básicas
        if (!formData.tipo_imovel) {
          toast.error('Selecione o tipo de imóvel');
          return false;
        }
        if (!formData.finalidade_imovel) {
          toast.error('Selecione a finalidade');
          return false;
        }
        if (!formData.valor_venda) {
          toast.error('Informe o valor do imóvel');
          return false;
        }
        return true;

      case 2: // Localização
        if (!formData.cep) {
          toast.error('Informe o CEP');
          return false;
        }
        if (!formData.cidade || !formData.estado) {
          toast.error('Informe cidade e estado');
          return false;
        }
        if (!formData.bairro) {
          toast.error('Informe o bairro');
          return false;
        }
        return true;

      case 3: // Características
        return true; // Características são opcionais

      case 4: // Fotos e Vídeos
        if (mediaFiles.length === 0) {
          toast.error('Adicione ao menos uma foto do imóvel');
          return false;
        }
        return true;

      default:
        return true;
    }
  };

  const handleNext = () => {
    if (validateStep(currentStep)) {
      setCurrentStep(Math.min(currentStep + 1, STEPS.length));
    }
  };

  const handlePrev = () => {
    setCurrentStep(Math.max(currentStep - 1, 1));
  };

  const handleSubmit = async () => {
    if (!validateStep(4)) return;

    try {
      setIsSubmitting(true);

      const formDataToSend = new FormData();

      // Dados básicos do imóvel
      formDataToSend.append('tipo_imovel', formData.tipo_imovel);
      formDataToSend.append('finalidade_imovel', formData.finalidade_imovel);
      formDataToSend.append('valor_venda', formData.valor_venda);
      
      if (formData.valor_condominio) formDataToSend.append('valor_condominio', formData.valor_condominio);
      if (formData.valor_iptu) formDataToSend.append('valor_iptu', formData.valor_iptu);
      
      // Localização
      formDataToSend.append('cep', formData.cep);
      formDataToSend.append('estado', formData.estado);
      formDataToSend.append('cidade', formData.cidade);
      formDataToSend.append('bairro', formData.bairro);
      formDataToSend.append('logradouro', formData.logradouro);
      if (formData.numero) formDataToSend.append('numero', formData.numero);
      if (formData.complemento) formDataToSend.append('complemento', formData.complemento);
      
      // Características
      if (formData.dormitorios) formDataToSend.append('dormitorios', formData.dormitorios);
      if (formData.suites) formDataToSend.append('suites', formData.suites);
      if (formData.banheiros) formDataToSend.append('banheiros', formData.banheiros);
      if (formData.garagem) formDataToSend.append('garagem', formData.garagem);
      if (formData.area_total) formDataToSend.append('area_total', formData.area_total);
      if (formData.area_privativa) formDataToSend.append('area_privativa', formData.area_privativa);
      if (formData.area_terreno) formDataToSend.append('area_terreno', formData.area_terreno);
      
      formDataToSend.append('em_condominio', formData.em_condominio ? '1' : '0');
      if (formData.em_condominio && formData.nome_condominio) {
        formDataToSend.append('nome_condominio', formData.nome_condominio);
      }
      
      if (formData.descricao) formDataToSend.append('descricao', formData.descricao);
      
      formDataToSend.append('active', formData.active ? '1' : '0');
      formDataToSend.append('exibir_imovel', formData.exibir_imovel ? '1' : '0');
      formDataToSend.append('exclusividade', formData.exclusividade ? '1' : '0');

      // Arquivos de mídia
      const destaqueFile = mediaFiles.find(m => m.destaque);
      mediaFiles.forEach((media, index) => {
        if (media.file) {
          formDataToSend.append(`media[]`, media.file);
          if (media.id === destaqueFile?.id) {
            formDataToSend.append('destaque_index', String(index));
          }
        }
      });

      // URLs existentes (para modo edição)
      const existingUrls = mediaFiles.filter(m => !m.file).map(m => m.url);
      if (existingUrls.length > 0) {
        formDataToSend.append('existing_images', JSON.stringify(existingUrls));
      }

      if (isEditMode && propertyId) {
        await api.post(`/imoveis/${propertyId}?_method=PUT`, formDataToSend, {
          headers: { 'Content-Type': 'multipart/form-data' }
        });
        toast.success('Imóvel atualizado com sucesso!');
      } else {
        await api.post('/imoveis', formDataToSend, {
          headers: { 'Content-Type': 'multipart/form-data' }
        });
        toast.success('Imóvel cadastrado com sucesso!');
      }
      
      setLocation('/properties');
    } catch (error: any) {
      console.error('Erro ao salvar imóvel:', error);
      const validationMessages = error?.response?.data?.messages;
      const message = validationMessages
        ? Object.entries(validationMessages)
            .flatMap(([, value]) => Array.isArray(value) ? value : [String(value)])
            .join(' | ')
        : error?.response?.data?.error || error?.message || 'Erro ao salvar imóvel';
      toast.error(message);
    } finally {
      setIsSubmitting(false);
    }
  };

  const renderStep = () => {
    switch (currentStep) {
      case 1: // Informações Básicas
        return (
          <motion.div
            initial={{ opacity: 0, x: 20 }}
            animate={{ opacity: 1, x: 0 }}
            exit={{ opacity: 0, x: -20 }}
            className="space-y-6"
          >
            <div>
              <label className="block text-sm font-semibold text-foreground mb-2">Tipo de Imóvel *</label>
              <select
                value={formData.tipo_imovel}
                onChange={(e) => setFormData({ ...formData, tipo_imovel: e.target.value })}
                className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                required
              >
                <option value="apartamento">Apartamento</option>
                <option value="casa">Casa</option>
                <option value="terreno">Terreno</option>
                <option value="sala_comercial">Sala Comercial</option>
                <option value="loja">Loja</option>
                <option value="galpao">Galpão</option>
                <option value="chacara">Chácara</option>
                <option value="sitio">Sítio</option>
                <option value="fazenda">Fazenda</option>
                <option value="outro">Outro</option>
              </select>
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-semibold text-foreground mb-2">Finalidade *</label>
                <select
                  value={formData.finalidade_imovel}
                  onChange={(e) => setFormData({ ...formData, finalidade_imovel: e.target.value })}
                  className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                >
                  <option value="venda">Venda</option>
                  <option value="aluguel">Aluguel</option>
                  <option value="temporada">Temporada</option>
                </select>
              </div>

              <div>
                <label className="block text-sm font-semibold text-foreground mb-2">
                  Valor {formData.finalidade_imovel === 'venda' ? 'de Venda' : 'do Aluguel'} *
                </label>
                <div className="relative">
                  <span className="absolute left-4 top-1/2 -translate-y-1/2 text-muted-foreground">R$</span>
                  <input
                    type="number"
                    step="0.01"
                    value={formData.valor_venda}
                    onChange={(e) => setFormData({ ...formData, valor_venda: e.target.value })}
                    className="w-full pl-12 pr-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                    placeholder="0,00"
                    required
                  />
                </div>
              </div>
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-semibold text-foreground mb-2">Valor Condomínio</label>
                <div className="relative">
                  <span className="absolute left-4 top-1/2 -translate-y-1/2 text-muted-foreground">R$</span>
                  <input
                    type="number"
                    step="0.01"
                    value={formData.valor_condominio}
                    onChange={(e) => setFormData({ ...formData, valor_condominio: e.target.value })}
                    className="w-full pl-12 pr-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                    placeholder="0,00"
                  />
                </div>
              </div>

              <div>
                <label className="block text-sm font-semibold text-foreground mb-2">Valor IPTU</label>
                <div className="relative">
                  <span className="absolute left-4 top-1/2 -translate-y-1/2 text-muted-foreground">R$</span>
                  <input
                    type="number"
                    step="0.01"
                    value={formData.valor_iptu}
                    onChange={(e) => setFormData({ ...formData, valor_iptu: e.target.value })}
                    className="w-full pl-12 pr-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                    placeholder="0,00"
                  />
                </div>
              </div>
            </div>

            <div className="flex flex-wrap gap-6 pt-2">
              <label className="flex items-center gap-3 cursor-pointer group">
                <input
                  type="checkbox"
                  checked={formData.active}
                  onChange={(e) => setFormData({ ...formData, active: e.target.checked })}
                  className="w-5 h-5 rounded border-white/20 bg-white/10"
                />
                <span className="text-sm font-medium text-foreground group-hover:text-blue-400 transition">Ativo</span>
              </label>

              <label className="flex items-center gap-3 cursor-pointer group">
                <input
                  type="checkbox"
                  checked={formData.exibir_imovel}
                  onChange={(e) => setFormData({ ...formData, exibir_imovel: e.target.checked })}
                  className="w-5 h-5 rounded border-white/20 bg-white/10"
                />
                <span className="text-sm font-medium text-foreground group-hover:text-blue-400 transition">Exibir no Portal</span>
              </label>

              <label className="flex items-center gap-3 cursor-pointer group">
                <input
                  type="checkbox"
                  checked={formData.exclusividade}
                  onChange={(e) => setFormData({ ...formData, exclusividade: e.target.checked })}
                  className="w-5 h-5 rounded border-white/20 bg-white/10"
                />
                <span className="text-sm font-medium text-foreground group-hover:text-blue-400 transition">Exclusividade</span>
              </label>
            </div>
          </motion.div>
        );

      case 2: // Localização
        return (
          <motion.div
            initial={{ opacity: 0, x: 20 }}
            animate={{ opacity: 1, x: 0 }}
            exit={{ opacity: 0, x: -20 }}
            className="space-y-6"
          >
            <div className="grid grid-cols-3 gap-4">
              <div className="col-span-2">
                <label className="block text-sm font-semibold text-foreground mb-2">CEP *</label>
                <input
                  type="text"
                  value={formData.cep}
                  onChange={(e) => setFormData({ ...formData, cep: e.target.value })}
                  onBlur={handleBuscarCep}
                  placeholder="00000-000"
                  className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                  required
                />
              </div>

              <div>
                <label className="block text-sm font-semibold text-foreground mb-2">&nbsp;</label>
                <motion.button
                  type="button"
                  whileHover={{ scale: 1.02 }}
                  whileTap={{ scale: 0.98 }}
                  onClick={handleBuscarCep}
                  disabled={isLoadingCep}
                  className="w-full px-4 py-3 bg-blue-500 hover:bg-blue-600 rounded-lg text-white font-semibold flex items-center justify-center gap-2 disabled:opacity-50 transition"
                >
                  {isLoadingCep ? (
                    <Loader2 className="animate-spin" size={18} />
                  ) : (
                    <><MapPin size={18} /> Buscar</>
                  )}
                </motion.button>
              </div>
            </div>

            <div className="grid grid-cols-3 gap-4">
              <div>
                <label className="block text-sm font-semibold text-foreground mb-2">Estado *</label>
                <input
                  type="text"
                  value={formData.estado}
                  onChange={(e) => setFormData({ ...formData, estado: e.target.value })}
                  maxLength={2}
                  className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition uppercase"
                  placeholder="UF"
                  required
                />
              </div>

              <div className="col-span-2">
                <label className="block text-sm font-semibold text-foreground mb-2">Cidade *</label>
                <input
                  type="text"
                  value={formData.cidade}
                  onChange={(e) => setFormData({ ...formData, cidade: e.target.value })}
                  className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                  placeholder="Nome da cidade"
                  required
                />
              </div>
            </div>

            <div>
              <label className="block text-sm font-semibold text-foreground mb-2">Bairro *</label>
              <input
                type="text"
                value={formData.bairro}
                onChange={(e) => setFormData({ ...formData, bairro: e.target.value })}
                className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                placeholder="Nome do bairro"
                required
              />
            </div>

            <div className="grid grid-cols-4 gap-4">
              <div className="col-span-3">
                <label className="block text-sm font-semibold text-foreground mb-2">Logradouro</label>
                <input
                  type="text"
                  value={formData.logradouro}
                  onChange={(e) => setFormData({ ...formData, logradouro: e.target.value })}
                  className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                  placeholder="Rua, Avenida, etc."
                />
              </div>

              <div>
                <label className="block text-sm font-semibold text-foreground mb-2">Número</label>
                <input
                  type="text"
                  value={formData.numero}
                  onChange={(e) => setFormData({ ...formData, numero: e.target.value })}
                  className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                  placeholder="Nº"
                />
              </div>
            </div>

            <div>
              <label className="block text-sm font-semibold text-foreground mb-2">Complemento</label>
              <input
                type="text"
                value={formData.complemento}
                onChange={(e) => setFormData({ ...formData, complemento: e.target.value })}
                className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                placeholder="Apartamento, Bloco, etc."
              />
            </div>

            <div className="pt-2">
              <label className="flex items-center gap-3 cursor-pointer group">
                <input
                  type="checkbox"
                  checked={formData.em_condominio}
                  onChange={(e) => setFormData({ ...formData, em_condominio: e.target.checked })}
                  className="w-5 h-5 rounded border-white/20 bg-white/10"
                />
                <span className="text-sm font-medium text-foreground group-hover:text-blue-400 transition">Em Condomínio</span>
              </label>
            </div>

            {formData.em_condominio && (
              <motion.div
                initial={{ opacity: 0, height: 0 }}
                animate={{ opacity: 1, height: 'auto' }}
                exit={{ opacity: 0, height: 0 }}
              >
                <label className="block text-sm font-semibold text-foreground mb-2">Nome do Condomínio</label>
                <input
                  type="text"
                  value={formData.nome_condominio}
                  onChange={(e) => setFormData({ ...formData, nome_condominio: e.target.value })}
                  className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                  placeholder="Nome do condomínio"
                />
              </motion.div>
            )}
          </motion.div>
        );

      case 3: // Características
        return (
          <motion.div
            initial={{ opacity: 0, x: 20 }}
            animate={{ opacity: 1, x: 0 }}
            exit={{ opacity: 0, x: -20 }}
            className="space-y-6"
          >
            <div className="grid grid-cols-4 gap-4">
              <div>
                <label className="block text-sm font-semibold text-foreground mb-2">Dormitórios</label>
                <input
                  type="number"
                  min="0"
                  value={formData.dormitorios}
                  onChange={(e) => setFormData({ ...formData, dormitorios: e.target.value })}
                  className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition text-center"
                  placeholder="0"
                />
              </div>

              <div>
                <label className="block text-sm font-semibold text-foreground mb-2">Suítes</label>
                <input
                  type="number"
                  min="0"
                  value={formData.suites}
                  onChange={(e) => setFormData({ ...formData, suites: e.target.value })}
                  className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition text-center"
                  placeholder="0"
                />
              </div>

              <div>
                <label className="block text-sm font-semibold text-foreground mb-2">Banheiros</label>
                <input
                  type="number"
                  min="0"
                  value={formData.banheiros}
                  onChange={(e) => setFormData({ ...formData, banheiros: e.target.value })}
                  className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition text-center"
                  placeholder="0"
                />
              </div>

              <div>
                <label className="block text-sm font-semibold text-foreground mb-2">Garagem</label>
                <input
                  type="number"
                  min="0"
                  value={formData.garagem}
                  onChange={(e) => setFormData({ ...formData, garagem: e.target.value })}
                  className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition text-center"
                  placeholder="0"
                />
              </div>
            </div>

            <div className="grid grid-cols-3 gap-4">
              <div>
                <label className="block text-sm font-semibold text-foreground mb-2">Área Total (m²)</label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  value={formData.area_total}
                  onChange={(e) => setFormData({ ...formData, area_total: e.target.value })}
                  className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                  placeholder="0,00"
                />
              </div>

              <div>
                <label className="block text-sm font-semibold text-foreground mb-2">Área Privativa (m²)</label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  value={formData.area_privativa}
                  onChange={(e) => setFormData({ ...formData, area_privativa: e.target.value })}
                  className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                  placeholder="0,00"
                />
              </div>

              <div>
                <label className="block text-sm font-semibold text-foreground mb-2">Área Terreno (m²)</label>
                <input
                  type="number"
                  step="0.01"
                  min="0"
                  value={formData.area_terreno}
                  onChange={(e) => setFormData({ ...formData, area_terreno: e.target.value })}
                  className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                  placeholder="0,00"
                />
              </div>
            </div>

            <div>
              <label className="block text-sm font-semibold text-foreground mb-2">Descrição</label>
              <textarea
                value={formData.descricao}
                onChange={(e) => setFormData({ ...formData, descricao: e.target.value })}
                rows={6}
                className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition resize-none"
                placeholder="Descreva as características e diferenciais do imóvel..."
              />
            </div>
          </motion.div>
        );

      case 4: // Fotos e Vídeos
        return (
          <motion.div
            initial={{ opacity: 0, x: 20 }}
            animate={{ opacity: 1, x: 0 }}
            exit={{ opacity: 0, x: -20 }}
            className="space-y-6"
          >
            <div>
              <label className="block text-sm font-semibold text-foreground mb-3">
                Fotos e Vídeos do Imóvel *
              </label>
              
              <div className="border-2 border-dashed border-white/20 rounded-lg p-8 text-center hover:border-blue-400 transition">
                <input
                  type="file"
                  multiple
                  accept="image/*,video/*"
                  onChange={handleMediaUpload}
                  className="hidden"
                  id="media-upload"
                />
                <label htmlFor="media-upload" className="cursor-pointer">
                  <Upload className="mx-auto mb-4 text-muted-foreground" size={48} />
                  <p className="text-foreground font-medium mb-2">
                    Clique para adicionar fotos ou vídeos
                  </p>
                  <p className="text-sm text-muted-foreground">
                    Formatos aceitos: JPG, PNG, MP4, MOV
                  </p>
                </label>
              </div>
            </div>

            {mediaFiles.length > 0 && (
              <div>
                <p className="text-sm font-semibold text-foreground mb-3">
                  {mediaFiles.length} arquivo(s) adicionado(s)
                </p>
                <div className="grid grid-cols-3 gap-4">
                  {mediaFiles.map((media) => (
                    <div
                      key={media.id}
                      className="relative group rounded-lg overflow-hidden bg-white/5 border-2 border-white/10 hover:border-blue-400 transition"
                    >
                      {media.type === 'image' ? (
                        <img
                          src={media.preview}
                          alt="Preview"
                          className="w-full h-40 object-cover"
                        />
                      ) : (
                        <div className="w-full h-40 flex items-center justify-center bg-black/50">
                          <Video size={48} className="text-white/70" />
                        </div>
                      )}
                      
                      {media.destaque && (
                        <div className="absolute top-2 left-2 bg-blue-500 text-white text-xs font-bold px-2 py-1 rounded">
                          DESTAQUE
                        </div>
                      )}

                      <div className="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2">
                        {!media.destaque && (
                          <motion.button
                            whileHover={{ scale: 1.1 }}
                            whileTap={{ scale: 0.9 }}
                            onClick={() => handleSetDestaque(media.id)}
                            className="px-3 py-2 bg-blue-500 hover:bg-blue-600 rounded text-white text-xs font-bold"
                          >
                            <Check size={16} />
                          </motion.button>
                        )}
                        <motion.button
                          whileHover={{ scale: 1.1 }}
                          whileTap={{ scale: 0.9 }}
                          onClick={() => handleRemoveMedia(media.id)}
                          className="px-3 py-2 bg-red-500 hover:bg-red-600 rounded text-white text-xs font-bold"
                        >
                          <Trash2 size={16} />
                        </motion.button>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}
          </motion.div>
        );

      case 5: // Revisão
        return (
          <motion.div
            initial={{ opacity: 0, x: 20 }}
            animate={{ opacity: 1, x: 0 }}
            exit={{ opacity: 0, x: -20 }}
            className="space-y-6"
          >
            <div className="bg-white/5 rounded-lg p-6 border border-white/10">
              <h3 className="text-lg font-bold text-foreground mb-4">Informações Básicas</h3>
              <div className="grid grid-cols-2 gap-4 text-sm">
                <div>
                  <span className="text-muted-foreground">Tipo:</span>
                  <span className="ml-2 text-foreground font-medium capitalize">{formData.tipo_imovel.replace('_', ' ')}</span>
                </div>
                <div>
                  <span className="text-muted-foreground">Finalidade:</span>
                  <span className="ml-2 text-foreground font-medium capitalize">{formData.finalidade_imovel}</span>
                </div>
                <div>
                  <span className="text-muted-foreground">Valor:</span>
                  <span className="ml-2 text-foreground font-medium">
                    R$ {parseFloat(formData.valor_venda || '0').toLocaleString('pt-BR', { minimumFractionDigits: 2 })}
                  </span>
                </div>
              </div>
            </div>

            <div className="bg-white/5 rounded-lg p-6 border border-white/10">
              <h3 className="text-lg font-bold text-foreground mb-4">Localização</h3>
              <div className="text-sm space-y-2">
                <p className="text-foreground">
                  {formData.logradouro && `${formData.logradouro}, `}
                  {formData.numero && `${formData.numero} - `}
                  {formData.bairro}
                </p>
                <p className="text-foreground">
                  {formData.cidade}/{formData.estado} - CEP: {formData.cep}
                </p>
                {formData.em_condominio && formData.nome_condominio && (
                  <p className="text-muted-foreground">Condomínio: {formData.nome_condominio}</p>
                )}
              </div>
            </div>

            <div className="bg-white/5 rounded-lg p-6 border border-white/10">
              <h3 className="text-lg font-bold text-foreground mb-4">Características</h3>
              <div className="grid grid-cols-4 gap-4 text-sm">
                {formData.dormitorios && (
                  <div>
                    <span className="text-muted-foreground">Dormitórios:</span>
                    <span className="ml-2 text-foreground font-medium">{formData.dormitorios}</span>
                  </div>
                )}
                {formData.suites && (
                  <div>
                    <span className="text-muted-foreground">Suítes:</span>
                    <span className="ml-2 text-foreground font-medium">{formData.suites}</span>
                  </div>
                )}
                {formData.banheiros && (
                  <div>
                    <span className="text-muted-foreground">Banheiros:</span>
                    <span className="ml-2 text-foreground font-medium">{formData.banheiros}</span>
                  </div>
                )}
                {formData.garagem && (
                  <div>
                    <span className="text-muted-foreground">Garagem:</span>
                    <span className="ml-2 text-foreground font-medium">{formData.garagem}</span>
                  </div>
                )}
                {formData.area_total && (
                  <div>
                    <span className="text-muted-foreground">Área Total:</span>
                    <span className="ml-2 text-foreground font-medium">{formData.area_total} m²</span>
                  </div>
                )}
              </div>
            </div>

            <div className="bg-white/5 rounded-lg p-6 border border-white/10">
              <h3 className="text-lg font-bold text-foreground mb-4">Fotos e Vídeos</h3>
              <p className="text-sm text-foreground">
                {mediaFiles.length} arquivo(s) adicionado(s)
              </p>
            </div>
          </motion.div>
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
          className="max-w-5xl mx-auto"
        >
          {/* Header */}
          <div className="page-header mb-8">
            <div>
              <h1 className="page-title mb-2 flex items-center gap-3">
                <Home size={36} />
                {isEditMode ? 'Editar Imóvel' : 'Cadastrar Novo Imóvel'}
              </h1>
              <p className="page-subtitle">
                {isEditMode ? 'Atualize os dados do imóvel' : 'Preencha as informações do imóvel passo a passo'}
              </p>
            </div>
            <motion.button
              whileHover={{ scale: 1.05 }}
              whileTap={{ scale: 0.95 }}
              onClick={() => setLocation('/properties')}
              className="flex items-center justify-center gap-2 rounded-lg border border-white/20 bg-white/10 px-5 py-3 font-semibold text-foreground"
            >
              <X size={18} />
              Cancelar
            </motion.button>
          </div>

          {/* Progress Steps */}
          <div className="mb-8">
            <div className="flex items-center justify-between">
              {STEPS.map((step, index) => {
                const StepIcon = step.icon;
                const isCompleted = currentStep > step.id;
                const isCurrent = currentStep === step.id;
                
                return (
                  <div key={step.id} className="flex items-center flex-1">
                    <div className="flex flex-col items-center flex-1">
                      <motion.div
                        whileHover={isCurrent ? { scale: 1.05 } : {}}
                        className={`w-12 h-12 rounded-full flex items-center justify-center border-2 transition-all ${
                          isCompleted
                            ? 'bg-blue-500 border-blue-500'
                            : isCurrent
                            ? 'bg-blue-500/20 border-blue-500'
                            : 'bg-white/5 border-white/20'
                        }`}
                      >
                        {isCompleted ? (
                          <Check className="text-white" size={24} />
                        ) : (
                          <StepIcon
                            className={isCurrent ? 'text-blue-400' : 'text-muted-foreground'}
                            size={24}
                          />
                        )}
                      </motion.div>
                      <p className={`mt-2 text-xs font-medium text-center ${
                        isCurrent ? 'text-foreground' : 'text-muted-foreground'
                      }`}>
                        {step.title}
                      </p>
                    </div>
                    
                    {index < STEPS.length - 1 && (
                      <div className={`h-0.5 flex-1 -mt-6 transition-all ${
                        isCompleted ? 'bg-blue-500' : 'bg-white/10'
                      }`} />
                    )}
                  </div>
                );
              })}
            </div>
          </div>

          {/* Form Content */}
          <div className="glass-panel rounded-2xl p-8 mb-6">
            {isLoadingProperty ? (
              <div className="mb-6 flex items-center gap-2 text-sm text-muted-foreground">
                <Loader2 className="animate-spin" size={16} />
                Carregando dados do imóvel...
              </div>
            ) : null}

            <AnimatePresence mode="wait">
              {renderStep()}
            </AnimatePresence>
          </div>

          {/* Navigation Buttons */}
          <div className="flex gap-4">
            <motion.button
              type="button"
              whileHover={{ scale: 1.02 }}
              whileTap={{ scale: 0.98 }}
              onClick={handlePrev}
              disabled={currentStep === 1 || isSubmitting}
              className="flex-1 px-6 py-4 bg-white/10 border border-white/20 rounded-lg text-foreground font-semibold flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed transition"
            >
              <ChevronLeft size={20} />
              Voltar
            </motion.button >

            {currentStep < STEPS.length ? (
              <motion.button
                type="button"
                whileHover={{ scale: 1.02 }}
                whileTap={{ scale: 0.98 }}
                onClick={handleNext}
                disabled={isSubmitting}
                className="flex-1 px-6 py-4 bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg text-white font-semibold flex items-center justify-center gap-2 transition"
              >
                Próximo
                <ChevronRight size={20} />
              </motion.button>
            ) : (
              <motion.button
                type="button"
                whileHover={{ scale: 1.02 }}
                whileTap={{ scale: 0.98 }}
                onClick={handleSubmit}
                disabled={isSubmitting || isLoadingProperty}
                className="flex-1 px-6 py-4 bg-gradient-to-r from-green-500 to-green-600 rounded-lg text-white font-semibold flex items-center justify-center gap-2 disabled:opacity-50 transition"
              >
                {isSubmitting ? (
                  <>
                    <Loader2 className="animate-spin" size={20} />
                    Salvando...
                  </>
                ) : (
                  <>
                    <Save size={20} />
                    {isEditMode ? 'Atualizar Imóvel' : 'Cadastrar Imóvel'}
                  </>
                )}
              </motion.button>
            )}
          </div>
        </motion.div>
      </div>
    </div>
  );
}
