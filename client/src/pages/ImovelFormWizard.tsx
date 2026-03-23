import { useEffect, useRef, useState } from 'react';
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
  Trash2,
  FileText,
  Download
} from 'lucide-react';
import { toast } from 'sonner';
import { useLocation, useRoute } from 'wouter';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';
import { useViaCep } from '@/hooks/useViaCep';
import { AdsPublishToggle } from '@/components/AdsPublishToggle';

// Formata CEP em tempo real: "01310100" → "01310-100"
function formatCep(value: string): string {
  const digits = value.replace(/\D/g, '').slice(0, 8);
  if (digits.length <= 5) return digits;
  return `${digits.slice(0, 5)}-${digits.slice(5)}`;
}

// Formata dígitos em tempo real para pt-BR: "1500000" → "1.500.000,00"
function formatCurrencyInput(raw: string): string {
  const digits = raw.replace(/\D/g, '');
  if (!digits) return '';
  const number = parseInt(digits, 10) / 100;
  return number.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Converte "1.500.000,00" → "1500000.00" para envio ao backend
function parseCurrencyInput(value: string): string {
  if (!value) return '';
  return value.replace(/\./g, '').replace(',', '.');
}

// Formata número vindo do banco → string pt-BR para exibição no input
function numberToCurrencyInput(value: number | string | null | undefined): string {
  if (value == null || value === '') return '';
  const n = typeof value === 'string' ? parseFloat(value) : value;
  if (isNaN(n)) return '';
  return n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

// Converte "1.234,50" → "1234.50" para envio ao backend (áreas)
function parseAreaInput(value: string): string {
  if (!value) return '';
  return value.replace(/\./g, '').replace(',', '.');
}

// Formata área ao sair do campo (onBlur): "1500.5" ou "1.500,5" → "1.500,50"
function formatAreaOnBlur(value: string): string {
  if (!value) return '';
  const n = parseFloat(parseAreaInput(value));
  if (isNaN(n)) return value;
  return n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

const PROPERTY_TYPE_OPTIONS = [
  { value: 'apartamento', label: 'Apartamento' },
  { value: 'casa', label: 'Casa' },
  { value: 'cobertura', label: 'Cobertura' },
  { value: 'flat', label: 'Flat' },
  { value: 'barracao', label: 'Barracão' },
  { value: 'terreno', label: 'Terreno' },
  { value: 'lote', label: 'Lote' },
  { value: 'sala_comercial', label: 'Sala Comercial' },
  { value: 'loja', label: 'Loja' },
  { value: 'galpao', label: 'Galpão' },
  { value: 'chacara', label: 'Chácara' },
  { value: 'sitio', label: 'Sítio' },
  { value: 'fazenda', label: 'Fazenda' },
  { value: 'outro', label: 'Outro' },
];

const PURPOSE_OPTIONS = [
  { value: 'venda', label: 'Venda' },
  { value: 'aluguel', label: 'Aluguel' },
  { value: 'venda_aluguel', label: 'Venda + Aluguel' },
  { value: 'temporada', label: 'Temporada' },
];

const ADDRESS_VISIBILITY_OPTIONS = [
  { value: 'bairro_cidade', label: 'Somente bairro/cidade' },
  { value: 'cidade_estado', label: 'Somente cidade/estado' },
  { value: 'completo', label: 'Endereço completo' },
  { value: 'oculto', label: 'Ocultar localização' },
];

const PROPERTY_CHARACTERISTIC_OPTIONS = [
  { value: 'condominio', label: 'Condomínio' },
  { value: 'condominio_alto_padrao', label: 'Condomínio alto padrão' },
  { value: 'casa_geminada', label: 'Casa geminada' },
  { value: 'casa_entrada_coletiva', label: 'Casa entrada coletiva' },
  { value: 'lancamento', label: 'Lançamento' },
  { value: 'em_construcao', label: 'Em construção' },
];

const PROPERTY_CLASSIFICATION_OPTIONS = [
  { value: 'oportunidade', label: 'Oportunidade' },
  { value: 'baixo_preco', label: 'Baixo preço' },
  { value: 'aceita_permuta', label: 'Aceita permuta' },
  { value: 'aceita_pets', label: 'Aceita pets' },
];

function parseStoredSelections(value: unknown): string[] {
  if (Array.isArray(value)) {
    return value
      .filter((item): item is string => typeof item === 'string' && item.trim() !== '')
      .map((item) => item.trim());
  }

  if (typeof value !== 'string') return [];

  const raw = value.trim();
  if (!raw) return [];

  try {
    const decoded = JSON.parse(raw);
    if (Array.isArray(decoded)) {
      return decoded
        .filter((item): item is string => typeof item === 'string' && item.trim() !== '')
        .map((item) => item.trim());
    }
  } catch {
    // fallback below
  }

  return raw.split(',').map((item) => item.trim()).filter(Boolean);
}

function stringifySelections(values: string[]): string {
  return JSON.stringify(Array.from(new Set(values)));
}

function requiresSalePrice(finalidade: string): boolean {
  return ['venda', 'venda_aluguel'].includes(finalidade);
}

function requiresRentPrice(finalidade: string): boolean {
  return ['aluguel', 'temporada', 'venda_aluguel', 'aluguel_temporada'].includes(finalidade);
}

function formatPurposeLabel(finalidade: string): string {
  return PURPOSE_OPTIONS.find((option) => option.value === finalidade)?.label || finalidade.replace(/_/g, ' ');
}

function formatSelectionLabel(value: string): string {
  return value.replace(/_/g, ' ');
}

const defaultFormData = {
  tipo_imovel: 'apartamento',
  finalidade_imovel: 'venda',
  valor_venda: '',
  valor_aluguel: '',
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
  descricao_resumida: '',
  local_chaves: '',
  status_chaves: 'disponivel',
  captador_user_id: '',
  construtora_pessoa_id: '',
  proprietario_nome: '',
  proprietario_telefone: '',
  proprietario_email: '',
  proprietario_observacoes: '',
  caracteristicas: [] as string[],
  classificacoes: [] as string[],
  visibilidade_endereco: 'bairro_cidade',
  portal_tenant_ids: [] as number[],
  active: true,
  exibir_imovel: true,
  exclusividade: false,
  publicar_anuncio: false,
};

const DRAFT_KEY = 'imovel-wizard-draft';

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

interface PortalTenantOption {
  id: number;
  name: string;
  domain?: string;
  is_owner?: boolean;
}

interface CaptadorOption {
  id: number;
  name: string;
  email?: string;
}

interface ConstrutoraOption {
  id: number;
  nome: string;
  razao_social?: string | null;
  cnpj?: string | null;
  papeis?: string[] | null;
}

interface PropertyDocument {
  id: number;
  nome: string;
  tipo?: string | null;
  mime_type?: string | null;
  tamanho_bytes?: number | null;
  url_documento?: string | null;
  created_at: string;
}

const isVideoUrl = (url: string) => /\.(mp4|mov|m4v|avi|webm|mkv)(\?.*)?$/i.test(url);

function formatFileSize(bytes?: number | null): string {
  if (!bytes || bytes <= 0) return '-';

  const units = ['B', 'KB', 'MB', 'GB'];
  let value = bytes;
  let index = 0;

  while (value >= 1024 && index < units.length - 1) {
    value /= 1024;
    index += 1;
  }

  return `${value.toFixed(index === 0 ? 0 : 1)} ${units[index]}`;
}

export default function ImovelFormWizard() {
  const [, setLocation] = useLocation();
  const [match, params] = useRoute('/properties/:id/editar');
  const isEditMode = Boolean(match && params?.id);
  const propertyId = params?.id;
  
  const [currentStep, setCurrentStep] = useState(1);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isGeneratingAi, setIsGeneratingAi] = useState(false);
  const [isLoadingProperty, setIsLoadingProperty] = useState(false);
  const [mediaFiles, setMediaFiles] = useState<MediaFile[]>([]);
  const [formData, setFormData] = useState(defaultFormData);
  const [portalOptions, setPortalOptions] = useState<PortalTenantOption[]>([]);
  const [isLoadingPortalOptions, setIsLoadingPortalOptions] = useState(false);
  const [captadores, setCaptadores] = useState<CaptadorOption[]>([]);
  const [isLoadingCaptadores, setIsLoadingCaptadores] = useState(false);
  const [construtoras, setConstrutoras] = useState<ConstrutoraOption[]>([]);
  const [isLoadingConstrutoras, setIsLoadingConstrutoras] = useState(false);
  const [autoSaveStatus, setAutoSaveStatus] = useState<'idle' | 'saving' | 'saved' | 'error'>('idle');
  const [isSendingImobiBrasil, setIsSendingImobiBrasil] = useState(false);
  const [imobibrasilStatus, setImobiBrasilStatus] = useState<{ enviado: boolean; data_envio?: string; erro?: string }>({ enviado: false });
  const [isSendingImagesImobiBrasil, setIsSendingImagesImobiBrasil] = useState(false);
  const [imobibrasilImagesStatus, setImobiBrasilImagesStatus] = useState<{ enviadas: boolean; data_envio?: string; error?: string }>({ enviadas: false });
  const [propertyDocuments, setPropertyDocuments] = useState<PropertyDocument[]>([]);
  const [isLoadingDocuments, setIsLoadingDocuments] = useState(false);
  const [isUploadingDocuments, setIsUploadingDocuments] = useState(false);
  const autoSaveTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  // Prevents auto-save from firing before initial data is loaded
  const autoSaveEnabled = useRef(!Boolean(match && params?.id));
  
  const { buscarCep, isLoading: isLoadingCep } = useViaCep();

  useEffect(() => {
    const loadPortalOptions = async () => {
      try {
        setIsLoadingPortalOptions(true);
        const response = await api.get('/imoveis/portal-opcoes');
        const options: PortalTenantOption[] = response.data?.data || [];
        setPortalOptions(options);

        setFormData((prev) => {
          if (prev.portal_tenant_ids.length > 0) {
            return prev;
          }
          const owner = options.find((option) => option.is_owner);
          return {
            ...prev,
            portal_tenant_ids: owner ? [owner.id] : prev.portal_tenant_ids,
          };
        });
      } catch (error) {
        console.error('Erro ao carregar opções de portais:', error);
      } finally {
        setIsLoadingPortalOptions(false);
      }
    };

    loadPortalOptions();
  }, []);

  useEffect(() => {
    const loadCaptadores = async () => {
      try {
        setIsLoadingCaptadores(true);
        const response = await api.get('/imoveis/captadores');
        setCaptadores(Array.isArray(response.data?.captadores) ? response.data.captadores : []);
      } catch (error) {
        console.error('Erro ao carregar captadores:', error);
        setCaptadores([]);
      } finally {
        setIsLoadingCaptadores(false);
      }
    };

    loadCaptadores();
  }, []);

  useEffect(() => {
    const loadConstrutoras = async () => {
      try {
        setIsLoadingConstrutoras(true);
        const response = await api.get('/pessoas', {
          params: {
            per_page: 200,
            ativo: 1,
            tipo: 'juridica',
          },
        });

        const allPeople = Array.isArray(response.data?.data) ? response.data.data : [];
        setConstrutoras(
          allPeople.filter((pessoa: ConstrutoraOption) => {
            const papeis = pessoa.papeis || [];
            return papeis.includes('construtora') || papeis.includes('construtor');
          })
        );
      } catch (error) {
        console.error('Erro ao carregar construtoras:', error);
        setConstrutoras([]);
      } finally {
        setIsLoadingConstrutoras(false);
      }
    };

    loadConstrutoras();
  }, []);

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
          valor_venda: numberToCurrencyInput(item.valor_venda),
          valor_aluguel: numberToCurrencyInput(item.valor_aluguel),
          valor_condominio: numberToCurrencyInput(item.valor_condominio),
          valor_iptu: numberToCurrencyInput(item.valor_iptu),
          dormitorios: item.dormitorios != null ? String(item.dormitorios) : '',
          suites: item.suites != null ? String(item.suites) : '',
          banheiros: item.banheiros != null ? String(item.banheiros) : '',
          garagem: item.garagem != null ? String(item.garagem) : '',
          area_total: numberToCurrencyInput(item.area_total),
          area_privativa: numberToCurrencyInput(item.area_privativa),
          area_terreno: numberToCurrencyInput(item.area_terreno),
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
          descricao_resumida: item.descricao_resumida || '',
          local_chaves: item.local_chaves || '',
          status_chaves: item.status_chaves || 'disponivel',
          captador_user_id: item.captador_user_id != null ? String(item.captador_user_id) : '',
          construtora_pessoa_id: item.construtora_pessoa_id != null ? String(item.construtora_pessoa_id) : '',
          proprietario_nome: item.proprietario_nome || '',
          proprietario_telefone: item.proprietario_telefone || '',
          proprietario_email: item.proprietario_email || '',
          proprietario_observacoes: item.proprietario_observacoes || '',
          caracteristicas: Array.from(new Set([
            ...parseStoredSelections(item.caracteristicas),
            ...(item.em_condominio ? ['condominio'] : []),
          ])),
          classificacoes: parseStoredSelections(item.classificacoes),
          visibilidade_endereco: item.visibilidade_endereco || 'bairro_cidade',
          portal_tenant_ids: Array.isArray(item.portal_tenant_ids)
            ? item.portal_tenant_ids.map((id: unknown) => Number(id)).filter((id: number) => Number.isFinite(id))
            : [],
          active: Boolean(item.active),
          exibir_imovel: Boolean(item.exibir_imovel),
          exclusividade: Boolean(item.exclusividade),
          publicar_anuncio: false, // UI-only: reseta a cada abertura do form
        });

        // Carregar imagens existentes
        if (item.imagens && Array.isArray(item.imagens)) {
          const destaqueUrl = item.imagem_destaque || null;
          const validUrls: string[] = item.imagens.filter((url: unknown) => typeof url === 'string' && url.trim() !== '');
          const existingMedia: MediaFile[] = validUrls.map((url: string, index: number) => ({
            id: `existing-${index}`,
            url,
            type: isVideoUrl(url) ? 'video' : 'image',
            preview: url,
            destaque: destaqueUrl ? url === destaqueUrl : index === 0,
          }));
          // Garantir que sempre exista exatamente 1 destaque visível no form.
          if (!existingMedia.some((m) => m.destaque) && existingMedia.length > 0) {
            existingMedia[0].destaque = true;
          }
          setMediaFiles(existingMedia);
        }

        // Carregar status de Imobi Brasil
        try {
          const statusResponse = await api.get(`/imoveis/${propertyId}/status-imobi-brasil`);
          const statusData = statusResponse.data?.data || { enviado: false };
          setImobiBrasilStatus(statusData);
          if (statusData.images_sent_at) {
            setImobiBrasilImagesStatus({ enviadas: true, data_envio: statusData.images_sent_at });
          }
        } catch {
          // Silenciosamente ignorar se não estiver disponível
          setImobiBrasilStatus({ enviado: false });
        }
      } catch (error) {
        console.error('Erro ao carregar imóvel:', error);
        toast.error('Erro ao carregar imóvel para edição');
        setLocation('/properties');
      } finally {
        setIsLoadingProperty(false);
        // Enable auto-save only after the initial data render settles
        setTimeout(() => { autoSaveEnabled.current = true; }, 200);
      }
    };

    fetchProperty();
  }, [isEditMode, propertyId, setLocation]);

  const loadDocuments = async () => {
    if (!isEditMode || !propertyId) {
      setPropertyDocuments([]);
      return;
    }

    try {
      setIsLoadingDocuments(true);
      const response = await api.get(`/imoveis/${propertyId}/documentos`);
      setPropertyDocuments(response.data?.data || []);
    } catch (error) {
      console.error('Erro ao carregar documentos do imóvel:', error);
      toast.error('Erro ao carregar anexos do imóvel');
    } finally {
      setIsLoadingDocuments(false);
    }
  };

  useEffect(() => {
    loadDocuments();
  }, [isEditMode, propertyId]); // eslint-disable-line react-hooks/exhaustive-deps

  // Restore draft for new-mode forms
  useEffect(() => {
    if (isEditMode) return;
    const draft = localStorage.getItem(DRAFT_KEY);
    if (draft) {
      try {
        const parsed = JSON.parse(draft);
        setFormData({
          ...defaultFormData,
          ...parsed,
          portal_tenant_ids: Array.isArray(parsed?.portal_tenant_ids)
            ? parsed.portal_tenant_ids.map((id: unknown) => Number(id)).filter((id: number) => Number.isFinite(id))
            : [],
        });
        toast.info('Rascunho restaurado automaticamente');
      } catch {
        localStorage.removeItem(DRAFT_KEY);
      }
    }
  }, []); // eslint-disable-line react-hooks/exhaustive-deps

  // Auto-save: debounced 1.5 s after any formData change
  useEffect(() => {
    if (!autoSaveEnabled.current) return;
    if (autoSaveTimer.current) clearTimeout(autoSaveTimer.current);

    autoSaveTimer.current = setTimeout(async () => {
      if (isEditMode && propertyId) {
        try {
          setAutoSaveStatus('saving');
          const payload = {
            tipo_imovel: formData.tipo_imovel,
            finalidade_imovel: formData.finalidade_imovel,
            valor_venda: parseCurrencyInput(formData.valor_venda) || '0',
            valor_aluguel: parseCurrencyInput(formData.valor_aluguel) || '0',
            valor_condominio: parseCurrencyInput(formData.valor_condominio) || null,
            valor_iptu: parseCurrencyInput(formData.valor_iptu) || null,
            cep: formData.cep,
            estado: formData.estado,
            cidade: formData.cidade,
            bairro: formData.bairro,
            logradouro: formData.logradouro,
            numero: formData.numero,
            complemento: formData.complemento,
            dormitorios: formData.dormitorios || null,
            suites: formData.suites || null,
            banheiros: formData.banheiros || null,
            garagem: formData.garagem || null,
            area_total: parseAreaInput(formData.area_total) || null,
            area_privativa: parseAreaInput(formData.area_privativa) || null,
            area_terreno: parseAreaInput(formData.area_terreno) || null,
            em_condominio: formData.em_condominio ? 1 : 0,
            nome_condominio: formData.nome_condominio,
            descricao: formData.descricao,
            descricao_resumida: formData.descricao_resumida,
            captador_user_id: formData.captador_user_id ? Number(formData.captador_user_id) : null,
            construtora_pessoa_id: formData.construtora_pessoa_id ? Number(formData.construtora_pessoa_id) : null,
            proprietario_nome: formData.proprietario_nome,
            proprietario_telefone: formData.proprietario_telefone,
            proprietario_email: formData.proprietario_email,
            proprietario_observacoes: formData.proprietario_observacoes,
            caracteristicas: stringifySelections(formData.caracteristicas),
            classificacoes: stringifySelections(formData.classificacoes),
            visibilidade_endereco: formData.visibilidade_endereco,
            portal_tenant_ids: Array.isArray(formData.portal_tenant_ids) ? formData.portal_tenant_ids.filter((id) => Number.isFinite(id)) : [],
            active: formData.active ? 1 : 0,
            exibir_imovel: formData.exibir_imovel ? 1 : 0,
            exclusividade: formData.exclusividade ? 1 : 0,
          };

          await api.post(`/imoveis/${propertyId}?_method=PUT`, payload);
          setAutoSaveStatus('saved');
          setTimeout(() => setAutoSaveStatus('idle'), 2000);
        } catch (error: any) {
          console.error('Auto-save error:', error?.response?.data || error?.message);
          setAutoSaveStatus('error');
          // Manter o status de erro por mais tempo para visibilidade
          setTimeout(() => setAutoSaveStatus('idle'), 5000);
        }
      } else {
        localStorage.setItem(DRAFT_KEY, JSON.stringify(formData));
        setAutoSaveStatus('saved');
        setTimeout(() => setAutoSaveStatus('idle'), 1500);
      }
    }, 1500);

    return () => {
      if (autoSaveTimer.current) clearTimeout(autoSaveTimer.current);
    };
  }, [formData]); // eslint-disable-line react-hooks/exhaustive-deps

  const MAX_FILES_PER_UPLOAD = 180; // server max_file_uploads = 200 (.user.ini), leave buffer

  // Pre-upload new files in batches of ≤20 before the main save (edit mode only).
  // Returns the final ordered list of all image URLs stored on the server,
  // or null if an upload batch fails.
  const preUploadNewFiles = async (
    pid: string | number,
    currentMedia: MediaFile[]
  ): Promise<string[] | null> => {
    const newFiles = currentMedia.filter(m => m.file);
    if (newFiles.length === 0) {
      // Nothing to pre-upload; return current existing URLs
      return currentMedia.filter(m => !m.file).map(m => m.url);
    }

    const BATCH = 20;
    // Start from the URLs already on the server
    let serverUrls: string[] = currentMedia.filter(m => !m.file).map(m => m.url);

    for (let i = 0; i < newFiles.length; i += BATCH) {
      const batch = newFiles.slice(i, i + BATCH);
      const batchForm = new FormData();
      // Send current server URLs as existing, so each batch accumulates
      if (serverUrls.length > 0) {
        batchForm.append('existing_images', JSON.stringify(serverUrls));
      }
      batch.forEach(m => batchForm.append('media[]', m.file!));

      try {
        const res = await api.post(`/imoveis/${pid}?_method=PUT`, batchForm, {
          headers: { 'Content-Type': 'multipart/form-data' },
        });
        const saved = res.data?.data?.imagens;
        if (Array.isArray(saved)) {
          serverUrls = saved;
        }
      } catch {
        return null; // caller will report the error
      }
    }

    return serverUrls;
  };

  const handleMediaUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
    let files = Array.from(e.target.files || []);

    if (files.length > MAX_FILES_PER_UPLOAD) {
      toast.warning(`Limite de ${MAX_FILES_PER_UPLOAD} arquivos por envio. Apenas os primeiros ${MAX_FILES_PER_UPLOAD} foram adicionados. Salve e adicione o restante em seguida.`);
      files = files.slice(0, MAX_FILES_PER_UPLOAD);
    }

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

  const handleDocumentUpload = async (event: React.ChangeEvent<HTMLInputElement>) => {
    const files = Array.from(event.target.files || []);
    if (!propertyId || files.length === 0) return;

    try {
      setIsUploadingDocuments(true);

      for (const file of files) {
        const uploadData = new FormData();
        uploadData.append('arquivo', file);
        uploadData.append('nome', file.name);

        await api.post(`/imoveis/${propertyId}/documentos`, uploadData, {
          headers: { 'Content-Type': 'multipart/form-data' },
        });
      }

      toast.success(`${files.length} anexo(s) enviado(s)`);
      await loadDocuments();
    } catch (error: any) {
      console.error('Erro ao enviar anexos do imóvel:', error);
      toast.error(error?.response?.data?.message || 'Erro ao enviar anexos');
    } finally {
      event.target.value = '';
      setIsUploadingDocuments(false);
    }
  };

  const handleDeleteDocument = async (documentId: number) => {
    if (!propertyId || !window.confirm('Deseja realmente remover este anexo?')) {
      return;
    }

    try {
      await api.delete(`/imoveis/${propertyId}/documentos/${documentId}`);
      toast.success('Anexo removido com sucesso');
      await loadDocuments();
    } catch (error) {
      console.error('Erro ao remover anexo do imóvel:', error);
      toast.error('Erro ao remover anexo');
    }
  };

  const handleMoveMedia = (id: string, dir: 'left' | 'right') => {
    const idx = mediaFiles.findIndex(m => m.id === id);
    if (idx === -1) return;
    const next = dir === 'left' ? idx - 1 : idx + 1;
    if (next < 0 || next >= mediaFiles.length) return;
    const arr = [...mediaFiles];
    [arr[idx], arr[next]] = [arr[next], arr[idx]];
    setMediaFiles(arr);
  };

  const dragSrcId = useRef<string | null>(null);
  const [dragOverId, setDragOverId] = useState<string | null>(null);

  const handleDragReorder = (targetId: string) => {
    if (!dragSrcId.current || dragSrcId.current === targetId) return;
    const srcIdx = mediaFiles.findIndex(m => m.id === dragSrcId.current);
    const tgtIdx = mediaFiles.findIndex(m => m.id === targetId);
    if (srcIdx === -1 || tgtIdx === -1) return;
    const arr = [...mediaFiles];
    const [moved] = arr.splice(srcIdx, 1);
    arr.splice(tgtIdx, 0, moved);
    setMediaFiles(arr);
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
        if (requiresSalePrice(formData.finalidade_imovel) && !formData.valor_venda) {
          toast.error('Informe o valor de venda');
          return false;
        }
        if (requiresRentPrice(formData.finalidade_imovel) && !formData.valor_aluguel) {
          toast.error('Informe o valor de aluguel ou temporada');
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

  const handleGoToStep = (targetStep: number) => {
    if (targetStep === currentStep) return;
    if (targetStep < currentStep) {
      setCurrentStep(targetStep);
      return;
    }

    for (let step = currentStep; step < targetStep; step++) {
      if (!validateStep(step)) return;
    }

    setCurrentStep(targetStep);
  };

  const handleToggleSelection = (field: 'caracteristicas' | 'classificacoes', value: string) => {
    setFormData((prev) => {
      const currentValues = prev[field];
      const exists = currentValues.includes(value);
      const nextValues = exists
        ? currentValues.filter((item) => item !== value)
        : [...currentValues, value];

      if (field === 'caracteristicas' && value === 'condominio') {
        return {
          ...prev,
          em_condominio: !exists,
          [field]: nextValues,
        };
      }

      return {
        ...prev,
        [field]: nextValues,
      };
    });
  };

  const handleToggleCondominium = (checked: boolean) => {
    setFormData((prev) => ({
      ...prev,
      em_condominio: checked,
      caracteristicas: checked
        ? Array.from(new Set([...prev.caracteristicas, 'condominio']))
        : prev.caracteristicas.filter((item) => item !== 'condominio'),
    }));
  };

  const handleSubmit = async () => {
    if (!validateStep(4)) return;

    try {
      setIsSubmitting(true);

      let effectiveMediaFiles = mediaFiles;
      let effectivePropertyId = isEditMode ? propertyId : null;

      const buildFormData = (includeMedia: boolean) => {
        const fd = new FormData();
        // Dados básicos do imóvel
        fd.append('tipo_imovel', formData.tipo_imovel);
        fd.append('finalidade_imovel', formData.finalidade_imovel);
        fd.append('valor_venda', parseCurrencyInput(formData.valor_venda));
        fd.append('valor_aluguel', parseCurrencyInput(formData.valor_aluguel) || '0');

        if (formData.valor_condominio) fd.append('valor_condominio', parseCurrencyInput(formData.valor_condominio));
        if (formData.valor_iptu) fd.append('valor_iptu', parseCurrencyInput(formData.valor_iptu));
        
        // Localização
        fd.append('cep', formData.cep);
        fd.append('estado', formData.estado);
        fd.append('cidade', formData.cidade);
        fd.append('bairro', formData.bairro);
        fd.append('logradouro', formData.logradouro);
        if (formData.numero) fd.append('numero', formData.numero);
        if (formData.complemento) fd.append('complemento', formData.complemento);
        
        // Características
        if (formData.dormitorios) fd.append('dormitorios', formData.dormitorios);
        if (formData.suites) fd.append('suites', formData.suites);
        if (formData.banheiros) fd.append('banheiros', formData.banheiros);
        if (formData.garagem) fd.append('garagem', formData.garagem);
        if (formData.area_total) fd.append('area_total', parseAreaInput(formData.area_total));
        if (formData.area_privativa) fd.append('area_privativa', parseAreaInput(formData.area_privativa));
        if (formData.area_terreno) fd.append('area_terreno', parseAreaInput(formData.area_terreno));
        
        fd.append('em_condominio', formData.em_condominio ? '1' : '0');
        fd.append('caracteristicas', stringifySelections(formData.caracteristicas));
        fd.append('classificacoes', stringifySelections(formData.classificacoes));
        if (formData.em_condominio && formData.nome_condominio) {
          fd.append('nome_condominio', formData.nome_condominio);
        }
        
        if (formData.descricao) fd.append('descricao', formData.descricao);
        if (formData.descricao_resumida) fd.append('descricao_resumida', formData.descricao_resumida);
        if (formData.local_chaves) fd.append('local_chaves', formData.local_chaves);
        if (formData.status_chaves) fd.append('status_chaves', formData.status_chaves);
        if (formData.captador_user_id) fd.append('captador_user_id', formData.captador_user_id);
        if (formData.construtora_pessoa_id) fd.append('construtora_pessoa_id', formData.construtora_pessoa_id);
        if (formData.proprietario_nome) fd.append('proprietario_nome', formData.proprietario_nome);
        if (formData.proprietario_telefone) fd.append('proprietario_telefone', formData.proprietario_telefone);
        if (formData.proprietario_email) fd.append('proprietario_email', formData.proprietario_email);
        if (formData.proprietario_observacoes) fd.append('proprietario_observacoes', formData.proprietario_observacoes);
        if (formData.visibilidade_endereco) fd.append('visibilidade_endereco', formData.visibilidade_endereco);
        formData.portal_tenant_ids.forEach((tenantId) => {
          fd.append('portal_tenant_ids[]', String(tenantId));
        });
        
        fd.append('active', formData.active ? '1' : '0');
        fd.append('exibir_imovel', formData.exibir_imovel ? '1' : '0');
        fd.append('exclusividade', formData.exclusividade ? '1' : '0');

        if (includeMedia) {
          const destaqueFile = effectiveMediaFiles.find(m => m.destaque);
          effectiveMediaFiles.forEach((media, index) => {
            if (media.file) {
              fd.append(`media[]`, media.file);
              if (media.id === destaqueFile?.id) {
                fd.append('destaque_index', String(index));
              }
            }
          });

          const existingUrls = effectiveMediaFiles.filter(m => !m.file).map(m => m.url);
          if (existingUrls.length > 0) {
            fd.append('existing_images', JSON.stringify(existingUrls));
          }
        }
        
        return fd;
      };

      // Se for um novo imóvel e tiver arquivos, precisamos criar o registro de texto antes
      // para obtermos um ID e fazermos o loteamento seguro (batch pre-upload)
      if (!isEditMode && mediaFiles.some(m => m.file)) {
        toast.info('Cadastrando dados básicos...');
        const initialForm = buildFormData(false);
        const res = await api.post('/imoveis', initialForm, {
          headers: { 'Content-Type': 'multipart/form-data' }
        });
        effectivePropertyId = res.data?.data?.id ?? res.data?.id;
        
        if (!effectivePropertyId) {
          throw new Error('Falha ao obter o ID do novo imóvel.');
        }
      }

      // ── BATCH PRE-UPLOAD: Fazemos em ambos os modos, desde que tenhamos um propertyId ──
      // Isso evita que o limite de max_file_uploads e post_max_size do PHP falhe silenciosamente.
      if (effectivePropertyId && mediaFiles.some(m => m.file)) {
        toast.info('Enviando fotos e vídeos...');
        const allUrls = await preUploadNewFiles(effectivePropertyId, mediaFiles);
        if (allUrls === null) {
          toast.error('Grave: Falha ao enviar fotos. O imóvel foi salvo mas as imagens podem estar incompletas.');
          setIsSubmitting(false);
          setLocation('/properties');
          return;
        }
        
        // Reconstrói a lista com as novas imagens convertidas em 'existing' URLs
        const destaqueUrl = mediaFiles.find(m => m.destaque)?.url ||
          mediaFiles.find(m => m.destaque)?.preview || '';
        
        effectiveMediaFiles = allUrls.map((url, i) => ({
          id: `existing-${i}`,
          url,
          type: isVideoUrl(url) ? 'video' : 'image',
          preview: url,
          destaque: destaqueUrl ? url === destaqueUrl : i === 0,
        }));
        
        // Atualiza a interface (opcional, pois já vamos redirecionar)
        setMediaFiles(effectiveMediaFiles);
      }
      
      // Prepara os dados finais atualizados (com 'existing_images' resolvido)
      const finalFormData = buildFormData(true);

      // Finaliza o envio
      if (effectivePropertyId) {
        await api.post(`/imoveis/${effectivePropertyId}?_method=PUT`, finalFormData, {
          headers: { 'Content-Type': 'multipart/form-data' }
        });
        
        if (formData.publicar_anuncio) {
          try {
            await api.post(`/listings/${effectivePropertyId}/ads/publish`, { provider: 'meta' });
            toast.success(isEditMode ? 'Imóvel atualizado e anúncio publicado no Meta Ads!' : 'Imóvel cadastrado, fotos enviadas e anúncio publicado!');
          } catch {
            toast.success(isEditMode ? 'Imóvel atualizado com sucesso!' : 'Imóvel cadastrado com sucesso!');
            toast.warning('Meta Ads: não foi possível publicar automaticamente.');
          }
        } else {
          toast.success(isEditMode ? 'Imóvel atualizado com sucesso!' : 'Imóvel cadastrado com sucesso!');
        }
      } else {
        // Fallback: Modo criação SEM NENHUM arquivo (effectivePropertyId == null)
        const res = await api.post('/imoveis', finalFormData, {
          headers: { 'Content-Type': 'multipart/form-data' }
        });
        const savedId = res.data?.data?.id ?? res.data?.id;
        
        if (formData.publicar_anuncio && savedId) {
          try {
            await api.post(`/listings/${savedId}/ads/publish`, { provider: 'meta' });
            toast.success('Imóvel cadastrado e anúncio publicado no Meta Ads!');
          } catch {
            toast.success('Imóvel cadastrado com sucesso!');
            toast.warning('Meta Ads: não foi possível publicar automaticamente.');
          }
        } else {
          toast.success('Imóvel cadastrado com sucesso!');
        }
      }

      localStorage.removeItem(DRAFT_KEY);
      setLocation('/properties');
    } catch (error: any) {
      console.error('Erro ao salvar imóvel:', error);
      const responseData = error?.response?.data;
      const validationMessages = responseData?.messages || responseData?.errors;
      const message = validationMessages
        ? Object.entries(validationMessages)
            .flatMap(([field, value]) => {
              const list = Array.isArray(value) ? value : [String(value)];
              return list.map((item) => `${field}: ${item}`);
            })
            .join(' | ')
        : responseData?.message || responseData?.error || error?.message || 'Erro ao salvar imóvel';
      console.error('Detalhes da validação:', responseData);
      toast.error(message);
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleGenerateDescriptionWithAi = async () => {
    try {
      setIsGeneratingAi(true);
      const response = await api.post('/imoveis/ai/gerar-descricao', {
        tipo_imovel: formData.tipo_imovel,
        finalidade_imovel: formData.finalidade_imovel,
        valor_venda: formData.valor_venda ? Number(parseCurrencyInput(formData.valor_venda)) : 0,
        valor_aluguel: formData.valor_aluguel ? Number(parseCurrencyInput(formData.valor_aluguel)) : 0,
        cidade: formData.cidade,
        estado: formData.estado,
        bairro: formData.bairro,
        dormitorios: formData.dormitorios ? Number(formData.dormitorios) : 0,
        banheiros: formData.banheiros ? Number(formData.banheiros) : 0,
        garagem: formData.garagem ? Number(formData.garagem) : 0,
        area_total: formData.area_total ? Number(parseAreaInput(formData.area_total)) : 0,
        descricao_base: formData.descricao || '',
      });

      const descricao = response.data?.data?.descricao || '';
      const descricaoResumida = response.data?.data?.descricao_resumida || '';

      if (!descricao && !descricaoResumida) {
        toast.error('Nao foi possivel gerar a descricao com IA.');
        return;
      }

      setFormData((prev) => ({
        ...prev,
        descricao: descricao || prev.descricao,
        descricao_resumida: descricaoResumida || prev.descricao_resumida,
      }));

      if (response.data?.data?.fallback) {
        toast.warning('Descricao gerada em modo fallback (sem OpenAI configurada).');
      } else {
        toast.success('Descricao gerada com IA.');
      }
    } catch (error: any) {
      toast.error(error?.response?.data?.error || 'Erro ao gerar descricao com IA.');
    } finally {
      setIsGeneratingAi(false);
    }
  };

  const handleEnviarImobiBrasil = async () => {
    if (!propertyId) {
      toast.error('Imóvel não encontrado');
      return;
    }

    try {
      setIsSendingImobiBrasil(true);
      
      const endpoint = imobibrasilStatus.enviado
        ? `/imoveis/${propertyId}/atualizar-imobi-brasil`
        : `/imoveis/${propertyId}/enviar-imobi-brasil`;

      const method = imobibrasilStatus.enviado ? 'put' : 'post';
      
      const response = await api[method](endpoint);

      if (response.data?.success) {
        const imagesSent = response.data?.images_sent ?? 0;
        const baseMsg = response.data?.message || 'Imóvel enviado com sucesso para Imobi Brasil';
        const fullMsg = imagesSent > 0
          ? `${baseMsg} + ${imagesSent} imagem(ns) enviada(s)`
          : baseMsg;
        toast.success(fullMsg);
        
        if (imagesSent > 0) {
          setImobiBrasilImagesStatus({ enviadas: true, data_envio: new Date().toISOString() });
        }
        
        // Atualizar status
        const statusResponse = await api.get(`/imoveis/${propertyId}/status-imobi-brasil`);
        setImobiBrasilStatus(statusResponse.data?.data || { enviado: true });
      } else {
        toast.error(response.data?.error || 'Erro ao enviar imóvel');
      }
    } catch (error: any) {
      console.error('Erro ao enviar para Imobi Brasil:', error);
      toast.error(error?.response?.data?.error || 'Erro ao enviar imóvel para Imobi Brasil');
    } finally {
      setIsSendingImobiBrasil(false);
    }
  };

  const saveImovelSilently = async (): Promise<boolean> => {
    if (!propertyId) return false;
    try {
      const formDataToSend = new FormData();
      formDataToSend.append('tipo_imovel', formData.tipo_imovel);
      formDataToSend.append('finalidade_imovel', formData.finalidade_imovel);
      formDataToSend.append('valor_venda', parseCurrencyInput(formData.valor_venda));
      formDataToSend.append('valor_aluguel', parseCurrencyInput(formData.valor_aluguel) || '0');
      if (formData.valor_condominio) formDataToSend.append('valor_condominio', parseCurrencyInput(formData.valor_condominio));
      if (formData.valor_iptu) formDataToSend.append('valor_iptu', parseCurrencyInput(formData.valor_iptu));
      formDataToSend.append('cep', formData.cep);
      formDataToSend.append('estado', formData.estado);
      formDataToSend.append('cidade', formData.cidade);
      formDataToSend.append('bairro', formData.bairro);
      formDataToSend.append('logradouro', formData.logradouro);
      if (formData.numero) formDataToSend.append('numero', formData.numero);
      if (formData.complemento) formDataToSend.append('complemento', formData.complemento);
      if (formData.dormitorios) formDataToSend.append('dormitorios', formData.dormitorios);
      if (formData.suites) formDataToSend.append('suites', formData.suites);
      if (formData.banheiros) formDataToSend.append('banheiros', formData.banheiros);
      if (formData.garagem) formDataToSend.append('garagem', formData.garagem);
      if (formData.area_total) formDataToSend.append('area_total', parseAreaInput(formData.area_total));
      if (formData.area_privativa) formDataToSend.append('area_privativa', parseAreaInput(formData.area_privativa));
      if (formData.area_terreno) formDataToSend.append('area_terreno', parseAreaInput(formData.area_terreno));
      formDataToSend.append('em_condominio', formData.em_condominio ? '1' : '0');
      formDataToSend.append('caracteristicas', stringifySelections(formData.caracteristicas));
      formDataToSend.append('classificacoes', stringifySelections(formData.classificacoes));
      if (formData.em_condominio && formData.nome_condominio) formDataToSend.append('nome_condominio', formData.nome_condominio);
      if (formData.descricao) formDataToSend.append('descricao', formData.descricao);
      if (formData.descricao_resumida) formDataToSend.append('descricao_resumida', formData.descricao_resumida);
      if (formData.local_chaves) formDataToSend.append('local_chaves', formData.local_chaves);
      if (formData.status_chaves) formDataToSend.append('status_chaves', formData.status_chaves);
      if (formData.captador_user_id) formDataToSend.append('captador_user_id', formData.captador_user_id);
      if (formData.construtora_pessoa_id) formDataToSend.append('construtora_pessoa_id', formData.construtora_pessoa_id);
      if (formData.proprietario_nome) formDataToSend.append('proprietario_nome', formData.proprietario_nome);
      if (formData.proprietario_telefone) formDataToSend.append('proprietario_telefone', formData.proprietario_telefone);
      if (formData.proprietario_email) formDataToSend.append('proprietario_email', formData.proprietario_email);
      if (formData.proprietario_observacoes) formDataToSend.append('proprietario_observacoes', formData.proprietario_observacoes);
      if (formData.visibilidade_endereco) formDataToSend.append('visibilidade_endereco', formData.visibilidade_endereco);
      formData.portal_tenant_ids.forEach((tenantId) => formDataToSend.append('portal_tenant_ids[]', String(tenantId)));
      formDataToSend.append('active', formData.active ? '1' : '0');
      formDataToSend.append('exibir_imovel', formData.exibir_imovel ? '1' : '0');
      formDataToSend.append('exclusividade', formData.exclusividade ? '1' : '0');

      const destaqueFile = mediaFiles.find(m => m.destaque);
      mediaFiles.forEach((media, index) => {
        if (media.file) {
          formDataToSend.append('media[]', media.file);
          if (media.id === destaqueFile?.id) formDataToSend.append('destaque_index', String(index));
        }
      });
      const existingUrls = mediaFiles.filter(m => !m.file).map(m => m.url);
      if (existingUrls.length > 0) formDataToSend.append('existing_images', JSON.stringify(existingUrls));

      await api.post(`/imoveis/${propertyId}?_method=PUT`, formDataToSend, {
        headers: { 'Content-Type': 'multipart/form-data' },
      });
      return true;
    } catch (error: any) {
      const msg = error?.response?.data?.message || 'Erro ao salvar imóvel antes de enviar imagens';
      toast.error(msg);
      return false;
    }
  };

  const handleEnviarImagensImobiBrasil = async () => {
    if (!propertyId) {
      toast.error('Imóvel não encontrado');
      return;
    }

    if (mediaFiles.length === 0) {
      toast.error('Adicione imagens antes de enviar');
      return;
    }

    try {
      setIsSendingImagesImobiBrasil(true);

      // Salva o estado atual (imagens adicionadas/removidas) antes de enviar
      toast.info('Salvando imóvel...');
      const saved = await saveImovelSilently();
      if (!saved) {
        setIsSendingImagesImobiBrasil(false);
        return;
      }

      const response = await api.post(`/imoveis/${propertyId}/enviar-imagens-imobi-brasil`);

      if (response.data?.success) {
        const isAsync = response.data?.async === true;
        const message = response.data?.message || `${response.data?.images_sent || 0} imagens enviadas com sucesso`;
        if (isAsync) {
          toast.success(message, { duration: 8000 });
        } else {
          toast.success(message);
        }

        // Atualizar status
        setImobiBrasilImagesStatus({
          enviadas: true,
          data_envio: new Date().toISOString()
        });
      } else {
        toast.error(response.data?.message || 'Erro ao enviar imagens');
        setImobiBrasilImagesStatus({ 
          enviadas: false,
          error: response.data?.message
        });
      }
    } catch (error: any) {
      console.error('Erro ao enviar imagens para Imobi Brasil:', error);
      const errorMsg = error?.response?.data?.message || 'Erro ao enviar imagens para Imobi Brasil';
      toast.error(errorMsg);
      setImobiBrasilImagesStatus({ 
        enviadas: false,
        error: errorMsg
      });
    } finally {
      setIsSendingImagesImobiBrasil(false);
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
              <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                {PROPERTY_TYPE_OPTIONS.map((option) => {
                  const isSelected = formData.tipo_imovel === option.value;
                  return (
                    <button
                      key={option.value}
                      type="button"
                      onClick={() => setFormData({ ...formData, tipo_imovel: option.value })}
                      className={`rounded-xl border px-4 py-3 text-sm font-semibold transition ${
                        isSelected
                          ? 'border-blue-400 bg-blue-500/15 text-blue-100'
                          : 'border-white/10 bg-white/5 text-muted-foreground hover:border-white/20 hover:text-foreground'
                      }`}
                    >
                      {option.label}
                    </button>
                  );
                })}
              </div>
            </div>

            <div>
              <label className="block text-sm font-semibold text-foreground mb-2">Finalidade *</label>
              <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                {PURPOSE_OPTIONS.map((option) => {
                  const isSelected = formData.finalidade_imovel === option.value;
                  return (
                    <button
                      key={option.value}
                      type="button"
                      onClick={() => setFormData({ ...formData, finalidade_imovel: option.value })}
                      className={`rounded-xl border px-4 py-3 text-sm font-semibold transition ${
                        isSelected
                          ? 'border-blue-400 bg-blue-500/15 text-blue-100'
                          : 'border-white/10 bg-white/5 text-muted-foreground hover:border-white/20 hover:text-foreground'
                      }`}
                    >
                      {option.label}
                    </button>
                  );
                })}
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              {requiresSalePrice(formData.finalidade_imovel) && (
                <div>
                  <label className="block text-sm font-semibold text-foreground mb-2">Valor de Venda *</label>
                  <div className="relative">
                    <span className="absolute left-4 top-1/2 -translate-y-1/2 text-muted-foreground">R$</span>
                    <input
                      type="text"
                      inputMode="decimal"
                      value={formData.valor_venda}
                      onChange={(e) => setFormData({ ...formData, valor_venda: formatCurrencyInput(e.target.value) })}
                      className="w-full pl-12 pr-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                      placeholder="0,00"
                      required
                    />
                  </div>
                </div>
              )}

              {requiresRentPrice(formData.finalidade_imovel) && (
                <div>
                  <label className="block text-sm font-semibold text-foreground mb-2">
                    {formData.finalidade_imovel === 'temporada' ? 'Valor da Temporada *' : 'Valor de Aluguel *'}
                  </label>
                  <div className="relative">
                    <span className="absolute left-4 top-1/2 -translate-y-1/2 text-muted-foreground">R$</span>
                    <input
                      type="text"
                      inputMode="decimal"
                      value={formData.valor_aluguel}
                      onChange={(e) => setFormData({ ...formData, valor_aluguel: formatCurrencyInput(e.target.value) })}
                      className="w-full pl-12 pr-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                      placeholder="0,00"
                      required
                    />
                  </div>
                </div>
              )}
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-semibold text-foreground mb-2">Valor Condomínio</label>
                <div className="relative">
                  <span className="absolute left-4 top-1/2 -translate-y-1/2 text-muted-foreground">R$</span>
                  <input
                    type="text"
                    inputMode="decimal"
                    value={formData.valor_condominio}
                    onChange={(e) => setFormData({ ...formData, valor_condominio: formatCurrencyInput(e.target.value) })}
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
                    type="text"
                    inputMode="decimal"
                    value={formData.valor_iptu}
                    onChange={(e) => setFormData({ ...formData, valor_iptu: formatCurrencyInput(e.target.value) })}
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

              <label className="flex items-center gap-3 cursor-pointer group">
                <input
                  type="checkbox"
                  checked={formData.publicar_anuncio}
                  onChange={(e) => setFormData({ ...formData, publicar_anuncio: e.target.checked })}
                  className="w-5 h-5 rounded border-yellow-400/60 bg-white/10 accent-yellow-400"
                />
                <span className="text-sm font-medium text-yellow-400 group-hover:text-yellow-300 transition flex items-center gap-1.5">
                  ⚡ Publicar no Meta Ads (automático ao salvar)
                </span>
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
                  inputMode="numeric"
                  value={formData.cep}
                  onChange={(e) => setFormData({ ...formData, cep: formatCep(e.target.value) })}
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

            <div>
              <label className="block text-sm font-semibold text-foreground mb-2">Privacidade do endereço no portal</label>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-3">
                {ADDRESS_VISIBILITY_OPTIONS.map((option) => {
                  const isSelected = formData.visibilidade_endereco === option.value;
                  return (
                    <button
                      key={option.value}
                      type="button"
                      onClick={() => setFormData({ ...formData, visibilidade_endereco: option.value })}
                      className={`rounded-xl border px-4 py-3 text-left text-sm transition ${
                        isSelected
                          ? 'border-blue-400 bg-blue-500/15 text-blue-100'
                          : 'border-white/10 bg-white/5 text-muted-foreground hover:border-white/20 hover:text-foreground'
                      }`}
                    >
                      <span className="block font-semibold">{option.label}</span>
                    </button>
                  );
                })}
              </div>
            </div>

            <div className="rounded-lg border border-white/10 bg-white/5 p-4">
              <p className="text-sm font-semibold text-foreground mb-1">Portais onde este imóvel será exibido</p>
              <p className="text-xs text-muted-foreground mb-3">Selecione em quais portais vinculados o imóvel ficará visível.</p>

              {isLoadingPortalOptions ? (
                <p className="text-xs text-muted-foreground">Carregando opções...</p>
              ) : (
                <div className="space-y-2">
                  {portalOptions.map((option) => {
                    const checked = formData.portal_tenant_ids.includes(option.id);
                    return (
                      <label key={option.id} className="flex items-center gap-3 rounded-lg border border-white/10 px-3 py-2 cursor-pointer hover:bg-white/5">
                        <input
                          type="checkbox"
                          checked={checked}
                          onChange={(event) => {
                            const candidate = event.target.checked
                              ? [...formData.portal_tenant_ids, option.id]
                              : formData.portal_tenant_ids.filter((id) => id !== option.id);
                            const unique = Array.from(new Set(candidate));
                            const owner = portalOptions.find((item) => item.is_owner);
                            const fallback = owner?.id ?? portalOptions[0]?.id;
                            setFormData({
                              ...formData,
                              portal_tenant_ids: unique.length > 0
                                ? unique
                                : (fallback ? [fallback] : []),
                            });
                          }}
                          className="w-4 h-4 rounded border-white/20 bg-white/10"
                        />
                        <div>
                          <p className="text-sm text-foreground">
                            {option.name} {option.is_owner ? '(principal)' : ''}
                          </p>
                          {option.domain ? <p className="text-xs text-muted-foreground">{option.domain}</p> : null}
                        </div>
                      </label>
                    );
                  })}
                  {!portalOptions.length && (
                    <p className="text-xs text-muted-foreground">Nenhum portal vinculado encontrado para este tenant.</p>
                  )}
                </div>
              )}
            </div>

            <div className="pt-2">
              <label className="flex items-center gap-3 cursor-pointer group">
                <input
                  type="checkbox"
                  checked={formData.em_condominio}
                  onChange={(e) => handleToggleCondominium(e.target.checked)}
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
                  type="text"
                  inputMode="numeric"
                  value={formData.dormitorios}
                  onChange={(e) => setFormData({ ...formData, dormitorios: e.target.value.replace(/\D/g, '').slice(0, 2) })}
                  className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition text-center"
                  placeholder="0"
                />
              </div>

              <div>
                <label className="block text-sm font-semibold text-foreground mb-2">Suítes</label>
                <input
                  type="text"
                  inputMode="numeric"
                  value={formData.suites}
                  onChange={(e) => setFormData({ ...formData, suites: e.target.value.replace(/\D/g, '').slice(0, 2) })}
                  className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition text-center"
                  placeholder="0"
                />
              </div>

              <div>
                <label className="block text-sm font-semibold text-foreground mb-2">Banheiros</label>
                <input
                  type="text"
                  inputMode="numeric"
                  value={formData.banheiros}
                  onChange={(e) => setFormData({ ...formData, banheiros: e.target.value.replace(/\D/g, '').slice(0, 2) })}
                  className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition text-center"
                  placeholder="0"
                />
              </div>

              <div>
                <label className="block text-sm font-semibold text-foreground mb-2">Garagem</label>
                <input
                  type="text"
                  inputMode="numeric"
                  value={formData.garagem}
                  onChange={(e) => setFormData({ ...formData, garagem: e.target.value.replace(/\D/g, '').slice(0, 2) })}
                  className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition text-center"
                  placeholder="0"
                />
              </div>
            </div>

            <div className="grid grid-cols-3 gap-4">
              <div>
                <label className="block text-sm font-semibold text-foreground mb-2">Área Total (m²)</label>
                <input
                  type="text"
                  inputMode="decimal"
                  value={formData.area_total}
                  onChange={(e) => setFormData({ ...formData, area_total: e.target.value })}
                  onBlur={(e) => setFormData({ ...formData, area_total: formatAreaOnBlur(e.target.value) })}
                  className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                  placeholder="0,00"
                />
              </div>

              <div>
                <label className="block text-sm font-semibold text-foreground mb-2">Área Privativa (m²)</label>
                <input
                  type="text"
                  inputMode="decimal"
                  value={formData.area_privativa}
                  onChange={(e) => setFormData({ ...formData, area_privativa: e.target.value })}
                  onBlur={(e) => setFormData({ ...formData, area_privativa: formatAreaOnBlur(e.target.value) })}
                  className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                  placeholder="0,00"
                />
              </div>

              <div>
                <label className="block text-sm font-semibold text-foreground mb-2">Área Terreno (m²)</label>
                <input
                  type="text"
                  inputMode="decimal"
                  value={formData.area_terreno}
                  onChange={(e) => setFormData({ ...formData, area_terreno: e.target.value })}
                  onBlur={(e) => setFormData({ ...formData, area_terreno: formatAreaOnBlur(e.target.value) })}
                  className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                  placeholder="0,00"
                />
              </div>
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div className="rounded-lg border border-white/10 bg-white/5 p-4">
                <p className="text-sm font-semibold text-foreground mb-3">Características do imóvel</p>
                <div className="space-y-2">
                  {PROPERTY_CHARACTERISTIC_OPTIONS.map((option) => {
                    const checked = formData.caracteristicas.includes(option.value);
                    return (
                      <label key={option.value} className="flex items-center gap-3 rounded-lg border border-white/10 px-3 py-2 cursor-pointer hover:bg-white/5">
                        <input
                          type="checkbox"
                          checked={checked}
                          onChange={() => handleToggleSelection('caracteristicas', option.value)}
                          className="w-4 h-4 rounded border-white/20 bg-white/10"
                        />
                        <span className="text-sm text-foreground">{option.label}</span>
                      </label>
                    );
                  })}
                </div>
              </div>

              <div className="rounded-lg border border-white/10 bg-white/5 p-4">
                <p className="text-sm font-semibold text-foreground mb-3">Classificações comerciais</p>
                <div className="space-y-2">
                  {PROPERTY_CLASSIFICATION_OPTIONS.map((option) => {
                    const checked = formData.classificacoes.includes(option.value);
                    return (
                      <label key={option.value} className="flex items-center gap-3 rounded-lg border border-white/10 px-3 py-2 cursor-pointer hover:bg-white/5">
                        <input
                          type="checkbox"
                          checked={checked}
                          onChange={() => handleToggleSelection('classificacoes', option.value)}
                          className="w-4 h-4 rounded border-white/20 bg-white/10"
                        />
                        <span className="text-sm text-foreground">{option.label}</span>
                      </label>
                    );
                  })}
                </div>
              </div>
            </div>

            <div>
              <div className="mb-2 flex items-center justify-between gap-2">
                <label className="block text-sm font-semibold text-foreground">Descrição</label>
                <button
                  type="button"
                  onClick={handleGenerateDescriptionWithAi}
                  disabled={isGeneratingAi}
                  className="inline-flex items-center gap-2 rounded-lg border border-blue-400/30 bg-blue-500/10 px-3 py-1.5 text-xs font-semibold text-blue-200 disabled:opacity-60"
                >
                  {isGeneratingAi ? <Loader2 size={14} className="animate-spin" /> : <Save size={14} />}
                  {isGeneratingAi ? 'Gerando...' : 'Gerar com IA'}
                </button>
              </div>
              <textarea
                value={formData.descricao}
                onChange={(e) => setFormData({ ...formData, descricao: e.target.value })}
                rows={6}
                className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition resize-none"
                placeholder="Descreva as características e diferenciais do imóvel..."
              />
            </div>

            <div>
              <label className="block text-sm font-semibold text-foreground mb-2">Descrição resumida</label>
              <textarea
                value={formData.descricao_resumida}
                onChange={(e) => setFormData({ ...formData, descricao_resumida: e.target.value })}
                rows={3}
                maxLength={220}
                className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition resize-none"
                placeholder="Resumo curto para vitrine/anuncio (ate 220 caracteres)"
              />
              <p className="mt-1 text-xs text-muted-foreground text-right">
                {formData.descricao_resumida.length}/220
              </p>
            </div>

            <div className="rounded-lg border border-white/10 bg-white/5 p-4">
              <p className="text-sm font-semibold text-foreground mb-3">Controle interno de chaves (não exibido no portal)</p>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-semibold text-foreground mb-2">Local das chaves</label>
                  <input
                    type="text"
                    value={formData.local_chaves}
                    onChange={(e) => setFormData({ ...formData, local_chaves: e.target.value })}
                    className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                    placeholder="Ex: Gaveta 2 / Recepção"
                  />
                </div>
                <div>
                  <label className="block text-sm font-semibold text-foreground mb-2">Status das chaves</label>
                  <select
                    value={formData.status_chaves}
                    onChange={(e) => setFormData({ ...formData, status_chaves: e.target.value })}
                    className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                  >
                    <option value="disponivel">Disponível</option>
                    <option value="retirada">Retirada</option>
                    <option value="reserva">Reservada</option>
                  </select>
                </div>
              </div>
            </div>

            <div className="rounded-lg border border-amber-400/20 bg-amber-500/10 p-4">
              <p className="text-sm font-semibold text-foreground mb-1">Dados do proprietário</p>
              <p className="text-xs text-amber-100/80 mb-4">Uso interno. Esses dados não aparecem no portal.</p>
              <div className="mb-4">
                <label className="block text-sm font-semibold text-foreground mb-2">Captador</label>
                <select
                  value={formData.captador_user_id}
                  onChange={(e) => setFormData({ ...formData, captador_user_id: e.target.value })}
                  className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                >
                  <option value="">Selecione um corretor</option>
                  {captadores.map((captador) => (
                    <option key={captador.id} value={captador.id}>{captador.name}</option>
                  ))}
                </select>
                {isLoadingCaptadores && (
                  <p className="mt-2 text-xs text-muted-foreground">Carregando corretores...</p>
                )}
              </div>
              {requiresSalePrice(formData.finalidade_imovel) && (
                <div className="mb-4">
                  <label className="block text-sm font-semibold text-foreground mb-2">Construtora vinculada</label>
                  <select
                    value={formData.construtora_pessoa_id}
                    onChange={(e) => setFormData({ ...formData, construtora_pessoa_id: e.target.value })}
                    className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                  >
                    <option value="">Nenhuma construtora vinculada</option>
                    {construtoras.map((construtora) => (
                      <option key={construtora.id} value={construtora.id}>
                        {(construtora.razao_social || construtora.nome)}{construtora.cnpj ? ` • ${construtora.cnpj}` : ''}
                      </option>
                    ))}
                  </select>
                  {isLoadingConstrutoras && (
                    <p className="mt-2 text-xs text-muted-foreground">Carregando construtoras...</p>
                  )}
                  {!isLoadingConstrutoras && construtoras.length === 0 && (
                    <p className="mt-2 text-xs text-muted-foreground">
                      Nenhuma construtora cadastrada com esse papel. Cadastre em Pessoas para vincular ao imóvel.
                    </p>
                  )}
                </div>
              )}
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                  <label className="block text-sm font-semibold text-foreground mb-2">Nome do proprietário</label>
                  <input
                    type="text"
                    value={formData.proprietario_nome}
                    onChange={(e) => setFormData({ ...formData, proprietario_nome: e.target.value })}
                    className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                    placeholder="Nome completo"
                  />
                </div>
                <div>
                  <label className="block text-sm font-semibold text-foreground mb-2">Telefone do proprietário</label>
                  <input
                    type="text"
                    value={formData.proprietario_telefone}
                    onChange={(e) => setFormData({ ...formData, proprietario_telefone: e.target.value })}
                    className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                    placeholder="(00) 00000-0000"
                  />
                </div>
              </div>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                <div>
                  <label className="block text-sm font-semibold text-foreground mb-2">E-mail do proprietário</label>
                  <input
                    type="email"
                    value={formData.proprietario_email}
                    onChange={(e) => setFormData({ ...formData, proprietario_email: e.target.value })}
                    className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                    placeholder="email@exemplo.com"
                  />
                </div>
                <div>
                  <label className="block text-sm font-semibold text-foreground mb-2">Observações</label>
                  <input
                    type="text"
                    value={formData.proprietario_observacoes}
                    onChange={(e) => setFormData({ ...formData, proprietario_observacoes: e.target.value })}
                    className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition"
                    placeholder="Observações internas"
                  />
                </div>
              </div>
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
                  {mediaFiles.map((media, idx) => (
                    <div
                      key={media.id}
                      draggable
                      onDragStart={() => { dragSrcId.current = media.id; }}
                      onDragOver={(e) => { e.preventDefault(); setDragOverId(media.id); }}
                      onDragLeave={() => setDragOverId(null)}
                      onDrop={(e) => { e.preventDefault(); handleDragReorder(media.id); setDragOverId(null); }}
                      onDragEnd={() => { dragSrcId.current = null; setDragOverId(null); }}
                      className={`relative group rounded-lg overflow-hidden bg-white/5 border-2 transition cursor-grab active:cursor-grabbing ${
                        dragOverId === media.id
                          ? 'border-blue-400 scale-105 shadow-lg shadow-blue-500/30'
                          : 'border-white/10 hover:border-blue-400'
                      }`}
                    >
                      {media.type === 'image' ? (
                        <img
                          src={media.preview}
                          alt="Preview"
                          className="w-full h-40 object-cover"
                        />
                      ) : (
                        <div className="relative w-full h-40 bg-black/60">
                          <video
                            src={media.preview}
                            className="w-full h-full object-cover"
                            muted
                            playsInline
                            preload="metadata"
                          />
                          <div className="absolute inset-0 flex items-center justify-center pointer-events-none">
                            <div className="rounded-full bg-black/45 p-2">
                              <Video size={22} className="text-white/90" />
                            </div>
                          </div>
                        </div>
                      )}

                      {/* Position badge */}
                      <div className="absolute top-2 right-2 bg-black/60 text-white text-[10px] font-bold px-1.5 py-0.5 rounded">
                        {idx + 1}/{mediaFiles.length}
                      </div>

                      {media.destaque && (
                        <div className="absolute top-2 left-2 bg-blue-500 text-white text-xs font-bold px-2 py-1 rounded">
                          DESTAQUE
                        </div>
                      )}

                      <div className="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 transition flex items-center justify-center gap-2">
                        {/* Mover esquerda */}
                        <motion.button
                          whileHover={{ scale: 1.1 }}
                          whileTap={{ scale: 0.9 }}
                          onClick={() => handleMoveMedia(media.id, 'left')}
                          disabled={idx === 0}
                          className="px-2 py-2 bg-white/20 hover:bg-white/40 disabled:opacity-30 disabled:cursor-not-allowed rounded text-white"
                          title="Mover para esquerda"
                        >
                          <ChevronLeft size={16} />
                        </motion.button>

                        {!media.destaque && (
                          <motion.button
                            whileHover={{ scale: 1.1 }}
                            whileTap={{ scale: 0.9 }}
                            onClick={() => handleSetDestaque(media.id)}
                            className="px-3 py-2 bg-blue-500 hover:bg-blue-600 rounded text-white text-xs font-bold"
                            title="Definir como destaque"
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

                        {/* Mover direita */}
                        <motion.button
                          whileHover={{ scale: 1.1 }}
                          whileTap={{ scale: 0.9 }}
                          onClick={() => handleMoveMedia(media.id, 'right')}
                          disabled={idx === mediaFiles.length - 1}
                          className="px-2 py-2 bg-white/20 hover:bg-white/40 disabled:opacity-30 disabled:cursor-not-allowed rounded text-white"
                          title="Mover para direita"
                        >
                          <ChevronRight size={16} />
                        </motion.button>
                      </div>
                    </div>
                  ))}
                </div>
              </div>
            )}

            <div className="rounded-lg border border-white/10 bg-white/5 p-5">
              <div className="flex items-start justify-between gap-4 mb-4">
                <div>
                  <h3 className="text-sm font-semibold text-foreground">Anexos do imóvel</h3>
                  <p className="text-xs text-muted-foreground mt-1">
                    Matrícula, autorização, IPTU, contratos e outros arquivos internos.
                  </p>
                </div>

                {isEditMode && propertyId ? (
                  <label className="inline-flex cursor-pointer items-center gap-2 rounded-lg border border-white/15 bg-white/10 px-3 py-2 text-sm font-medium text-foreground hover:bg-white/15 transition">
                    <Upload size={16} />
                    {isUploadingDocuments ? 'Enviando...' : 'Adicionar anexos'}
                    <input
                      type="file"
                      multiple
                      className="hidden"
                      onChange={handleDocumentUpload}
                      disabled={isUploadingDocuments}
                      accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.txt"
                    />
                  </label>
                ) : null}
              </div>

              {!isEditMode ? (
                <div className="rounded-lg border border-amber-400/20 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
                  Salve o imóvel primeiro para liberar o envio de anexos.
                </div>
              ) : isLoadingDocuments ? (
                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                  <Loader2 size={16} className="animate-spin" /> Carregando anexos...
                </div>
              ) : propertyDocuments.length === 0 ? (
                <div className="rounded-lg border border-white/10 bg-black/10 px-4 py-6 text-center text-sm text-muted-foreground">
                  Nenhum anexo enviado ainda.
                </div>
              ) : (
                <div className="space-y-3">
                  {propertyDocuments.map((document) => (
                    <div key={document.id} className="flex items-center justify-between gap-3 rounded-lg border border-white/10 bg-black/10 px-4 py-3">
                      <div className="min-w-0 flex items-center gap-3">
                        <div className="rounded-lg bg-blue-500/15 p-2 text-blue-200">
                          <FileText size={18} />
                        </div>
                        <div className="min-w-0">
                          <p className="truncate text-sm font-medium text-foreground">{document.nome}</p>
                          <p className="text-xs text-muted-foreground">
                            {[document.tipo, formatFileSize(document.tamanho_bytes), document.created_at ? new Date(document.created_at).toLocaleDateString('pt-BR') : null]
                              .filter(Boolean)
                              .join(' • ')}
                          </p>
                        </div>
                      </div>

                      <div className="flex items-center gap-2">
                        {document.url_documento ? (
                          <a
                            href={document.url_documento}
                            target="_blank"
                            rel="noreferrer"
                            className="inline-flex items-center gap-1 rounded-lg border border-white/15 px-3 py-2 text-xs font-medium text-foreground hover:bg-white/10 transition"
                          >
                            <Download size={14} /> Abrir
                          </a>
                        ) : null}
                        <button
                          type="button"
                          onClick={() => handleDeleteDocument(document.id)}
                          className="inline-flex items-center gap-1 rounded-lg border border-red-400/25 px-3 py-2 text-xs font-medium text-red-200 hover:bg-red-500/10 transition"
                        >
                          <Trash2 size={14} /> Remover
                        </button>
                      </div>
                    </div>
                  ))}
                </div>
              )}
            </div>
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
            {isEditMode && (
              <div className="bg-gradient-to-r from-blue-500/10 to-cyan-500/10 rounded-lg p-6 border border-blue-400/30">
                <div className="flex items-start justify-between gap-4">
                  <div className="flex-1">
                    <h3 className="text-lg font-bold text-foreground mb-2">Integração Imobi Brasil</h3>
                    <p className="text-sm text-muted-foreground mb-4">
                      {imobibrasilStatus.enviado
                        ? `✓ Imóvel enviado em ${imobibrasilStatus.data_envio ? new Date(imobibrasilStatus.data_envio).toLocaleDateString('pt-BR') : 'desconhecido'}`
                        : 'Envie este imóvel para o Imobi Brasil para ampliar sua visibilidade (dados + imagens)'}
                    </p>
                    {imobibrasilStatus.erro && (
                      <p className="text-xs text-red-400 mt-2">
                        ⚠️ Último erro: {imobibrasilStatus.erro}
                      </p>
                    )}
                    {imobibrasilImagesStatus.enviadas && (
                      <p className="text-xs text-green-400 mt-2">
                        ✓ Imagens enviadas em {imobibrasilImagesStatus.data_envio ? new Date(imobibrasilImagesStatus.data_envio).toLocaleDateString('pt-BR', { hour: '2-digit', minute: '2-digit' }) : 'desconhecido'}
                      </p>
                    )}
                    {imobibrasilImagesStatus.error && (
                      <p className="text-xs text-red-400 mt-2">
                        ⚠️ Erro ao enviar imagens: {imobibrasilImagesStatus.error}
                      </p>
                    )}
                  </div>
                  <div className="flex flex-col gap-3 items-stretch">
                    <motion.button
                      type="button"
                      whileHover={{ scale: 1.02 }}
                      whileTap={{ scale: 0.98 }}
                      onClick={handleEnviarImobiBrasil}
                      disabled={isSendingImobiBrasil || isSendingImagesImobiBrasil}
                      className="px-6 py-3 bg-gradient-to-r from-blue-500 to-cyan-500 hover:from-blue-600 hover:to-cyan-600 rounded-lg text-white font-semibold flex items-center gap-2 disabled:opacity-50 whitespace-nowrap transition justify-center"
                    >
                      {isSendingImobiBrasil ? (
                        <>
                          <Loader2 size={16} className="animate-spin" />
                          Enviando...
                        </>
                      ) : (
                        <>
                          <Save size={16} />
                          {imobibrasilStatus.enviado ? 'Atualizar' : 'Enviar'} para Imobi Brasil
                        </>
                      )}
                    </motion.button>
                    {imobibrasilStatus.enviado && (
                      <motion.button
                        type="button"
                        whileHover={{ scale: 1.02 }}
                        whileTap={{ scale: 0.98 }}
                        onClick={handleEnviarImagensImobiBrasil}
                        disabled={isSendingImagesImobiBrasil || isSendingImobiBrasil}
                        className="px-6 py-3 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-600 hover:to-teal-600 rounded-lg text-white font-semibold flex items-center gap-2 disabled:opacity-50 whitespace-nowrap transition justify-center"
                      >
                        {isSendingImagesImobiBrasil ? (
                          <>
                            <Loader2 size={16} className="animate-spin" />
                            Enviando imagens...
                          </>
                        ) : (
                          <>
                            <ImageIcon size={16} />
                            Reenviar Imagens
                          </>
                        )}
                      </motion.button>
                    )}
                  </div>
                </div>
              </div>
            )}

            <div className="bg-white/5 rounded-lg p-6 border border-white/10">
              <h3 className="text-lg font-bold text-foreground mb-4">Informações Básicas</h3>
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                <div>
                  <span className="text-muted-foreground">Tipo:</span>
                  <span className="ml-2 text-foreground font-medium">
                    {PROPERTY_TYPE_OPTIONS.find((option) => option.value === formData.tipo_imovel)?.label || formatSelectionLabel(formData.tipo_imovel)}
                  </span>
                </div>
                <div>
                  <span className="text-muted-foreground">Finalidade:</span>
                  <span className="ml-2 text-foreground font-medium">{formatPurposeLabel(formData.finalidade_imovel)}</span>
                </div>
                {requiresSalePrice(formData.finalidade_imovel) && (
                  <div>
                    <span className="text-muted-foreground">Venda:</span>
                    <span className="ml-2 text-foreground font-medium">R$ {formData.valor_venda || '0,00'}</span>
                  </div>
                )}
                {requiresRentPrice(formData.finalidade_imovel) && (
                  <div>
                    <span className="text-muted-foreground">
                      {formData.finalidade_imovel === 'temporada' ? 'Temporada:' : 'Aluguel:'}
                    </span>
                    <span className="ml-2 text-foreground font-medium">R$ {formData.valor_aluguel || '0,00'}</span>
                  </div>
                )}
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
                <p className="text-muted-foreground">
                  Visibilidade no portal: {ADDRESS_VISIBILITY_OPTIONS.find((option) => option.value === formData.visibilidade_endereco)?.label || formData.visibilidade_endereco}
                </p>
                <p className="text-muted-foreground">
                  Portais: {formData.portal_tenant_ids.length > 0
                    ? formData.portal_tenant_ids
                        .map((tenantId) => portalOptions.find((option) => option.id === tenantId)?.name || `#${tenantId}`)
                        .join(', ')
                    : 'Nenhum selecionado'}
                </p>
                {formData.em_condominio && formData.nome_condominio && (
                  <p className="text-muted-foreground">Condomínio: {formData.nome_condominio}</p>
                )}
              </div>
            </div>

            <div className="bg-white/5 rounded-lg p-6 border border-white/10">
              <h3 className="text-lg font-bold text-foreground mb-4">Características</h3>
              <div className="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
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
                {formData.area_privativa && (
                  <div>
                    <span className="text-muted-foreground">Área privativa:</span>
                    <span className="ml-2 text-foreground font-medium">{formData.area_privativa} m²</span>
                  </div>
                )}
                {formData.area_terreno && (
                  <div>
                    <span className="text-muted-foreground">Área terreno:</span>
                    <span className="ml-2 text-foreground font-medium">{formData.area_terreno} m²</span>
                  </div>
                )}
              </div>

              {formData.caracteristicas.length > 0 && (
                <div className="mt-4">
                  <span className="text-muted-foreground text-sm">Características:</span>
                  <p className="mt-1 text-sm text-foreground">
                    {formData.caracteristicas.map(formatSelectionLabel).join(', ')}
                  </p>
                </div>
              )}

              {formData.classificacoes.length > 0 && (
                <div className="mt-4">
                  <span className="text-muted-foreground text-sm">Classificações:</span>
                  <p className="mt-1 text-sm text-foreground">
                    {formData.classificacoes.map(formatSelectionLabel).join(', ')}
                  </p>
                </div>
              )}

              {(formData.valor_condominio || formData.valor_iptu) && (
                <div className="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                  {formData.valor_condominio && (
                    <div>
                      <span className="text-muted-foreground">Condomínio:</span>
                      <span className="ml-2 text-foreground font-medium">R$ {formData.valor_condominio}</span>
                    </div>
                  )}
                  {formData.valor_iptu && (
                    <div>
                      <span className="text-muted-foreground">IPTU:</span>
                      <span className="ml-2 text-foreground font-medium">R$ {formData.valor_iptu}</span>
                    </div>
                  )}
                </div>
              )}

              {(formData.local_chaves || formData.status_chaves) && (
                <div className="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                  {formData.local_chaves && (
                    <div>
                      <span className="text-muted-foreground">Local das chaves:</span>
                      <span className="ml-2 text-foreground font-medium">{formData.local_chaves}</span>
                    </div>
                  )}
                  {formData.status_chaves && (
                    <div>
                      <span className="text-muted-foreground">Status das chaves:</span>
                      <span className="ml-2 text-foreground font-medium capitalize">{formData.status_chaves}</span>
                    </div>
                  )}
                </div>
              )}
            </div>

            {(formData.proprietario_nome || formData.proprietario_telefone || formData.proprietario_email || formData.proprietario_observacoes) && (
              <div className="bg-white/5 rounded-lg p-6 border border-white/10">
                <h3 className="text-lg font-bold text-foreground mb-4">Proprietário</h3>
                <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                  {formData.proprietario_nome && (
                    <div>
                      <span className="text-muted-foreground">Nome:</span>
                      <span className="ml-2 text-foreground font-medium">{formData.proprietario_nome}</span>
                    </div>
                  )}
                  {formData.proprietario_telefone && (
                    <div>
                      <span className="text-muted-foreground">Telefone:</span>
                      <span className="ml-2 text-foreground font-medium">{formData.proprietario_telefone}</span>
                    </div>
                  )}
                  {formData.proprietario_email && (
                    <div>
                      <span className="text-muted-foreground">E-mail:</span>
                      <span className="ml-2 text-foreground font-medium">{formData.proprietario_email}</span>
                    </div>
                  )}
                  {formData.proprietario_observacoes && (
                    <div className="md:col-span-2">
                      <span className="text-muted-foreground">Observações:</span>
                      <span className="ml-2 text-foreground font-medium">{formData.proprietario_observacoes}</span>
                    </div>
                  )}
                </div>
              </div>
            )}

            {formData.captador_user_id && (
              <div className="bg-white/5 rounded-lg p-6 border border-white/10">
                <h3 className="text-lg font-bold text-foreground mb-4">Captação</h3>
                <div className="text-sm">
                  <span className="text-muted-foreground">Captador:</span>
                  <span className="ml-2 text-foreground font-medium">
                    {captadores.find((captador) => String(captador.id) === String(formData.captador_user_id))?.name || 'Corretor selecionado'}
                  </span>
                </div>
              </div>
            )}

            <div className="bg-white/5 rounded-lg p-6 border border-white/10">
              <h3 className="text-lg font-bold text-foreground mb-4">Fotos e Vídeos</h3>
              <p className="text-sm text-foreground">
                {mediaFiles.length} arquivo(s) adicionado(s)
              </p>
              {isEditMode && (
                <p className="mt-2 text-sm text-muted-foreground">
                  {propertyDocuments.length} anexo(s) interno(s) vinculado(s) ao imóvel.
                </p>
              )}
            </div>

            {/* Anúncios — toggle real em edição; confirmação do checkbox em novo cadastro */}
            {isEditMode && propertyId ? (
              <div className="bg-white/5 rounded-lg p-6 border border-white/10">
                <h3 className="text-lg font-bold text-foreground mb-4">Anúncios automáticos</h3>
                <AdsPublishToggle listingId={Number(propertyId)} />
              </div>
            ) : (
              <div className={`rounded-lg p-4 border flex items-center gap-3 ${
                formData.publicar_anuncio
                  ? 'bg-yellow-500/10 border-yellow-400/30'
                  : 'bg-white/5 border-white/10'
              }`}>
                <span className="text-xl">{formData.publicar_anuncio ? '⚡' : '💤'}</span>
                <div>
                  <p className="text-sm font-semibold text-foreground">
                    {formData.publicar_anuncio ? 'Publicar no Meta Ads ao salvar' : 'Meta Ads desativado'}
                  </p>
                  <p className="text-xs text-muted-foreground">
                    {formData.publicar_anuncio
                      ? 'O imóvel será enviado automaticamente ao catálogo do Meta (Facebook/Instagram) após o cadastro.'
                      : 'Para ativar, marque "⚡ Publicar no Meta Ads" na etapa 1.'}
                  </p>
                </div>
              </div>
            )}

            {(formData.descricao || formData.descricao_resumida) && (
              <div className="bg-white/5 rounded-lg p-6 border border-white/10">
                <h3 className="text-lg font-bold text-foreground mb-4">Textos do Imóvel</h3>
                {formData.descricao_resumida && (
                  <p className="text-sm text-foreground mb-3">
                    <span className="text-muted-foreground">Resumo:</span> {formData.descricao_resumida}
                  </p>
                )}
                {formData.descricao && (
                  <p className="text-sm text-foreground line-clamp-5">{formData.descricao}</p>
                )}
              </div>
            )}
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
              {autoSaveStatus === 'saving' && (
                <p className="mt-1 text-xs text-blue-400 flex items-center gap-1.5">
                  <Loader2 size={12} className="animate-spin" /> Salvando...
                </p>
              )}
              {autoSaveStatus === 'saved' && (
                <p className="mt-1 text-xs text-green-400">
                  {isEditMode ? 'Salvo automaticamente' : 'Rascunho salvo'}
                </p>
              )}
              {autoSaveStatus === 'error' && (
                <p className="mt-1 text-xs text-red-400">Falha ao salvar automaticamente</p>
              )}
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
                    <button
                      type="button"
                      onClick={() => handleGoToStep(step.id)}
                      className="flex flex-col items-center flex-1"
                      title={`Ir para ${step.title}`}
                    >
                      <motion.div
                        whileHover={{ scale: 1.05 }}
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
                    </button>
                    
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
