import { useEffect, useState } from 'react';
import { motion } from 'framer-motion';
import { Home, Save, X, MapPin, Loader2 } from 'lucide-react';
import { toast } from 'sonner';
import { useLocation, useRoute } from 'wouter';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';
import { useViaCep } from '@/hooks/useViaCep';

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

// Formata área ao sair do campo (onBlur)
function formatAreaOnBlur(value: string): string {
  if (!value) return '';
  const n = parseFloat(parseAreaInput(value));
  if (isNaN(n)) return value;
  return n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

const defaultFormData = {
  codigo_imovel: '',
  referencia_imovel: '',
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
  // Dados do proprietário (interno, não exibido no portal)
  proprietario_nome: '',
  proprietario_telefone: '',
  proprietario_email: '',
  proprietario_observacoes: '',
};

export default function ImovelForm() {
  const [, setLocation] = useLocation();
  const [match, params] = useRoute('/properties/:id/editar');
  const isEditMode = Boolean(match && params?.id);
  const propertyId = params?.id;
  const [currentTab, setCurrentTab] = useState('identificacao');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isLoadingProperty, setIsLoadingProperty] = useState(false);
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

  const [formData, setFormData] = useState(defaultFormData);

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
          codigo_imovel: item.codigo_imovel || '',
          referencia_imovel: item.referencia_imovel || '',
          tipo_imovel: item.tipo_imovel || 'apartamento',
          finalidade_imovel: item.finalidade_imovel || 'venda',
          valor_venda: numberToCurrencyInput(item.valor_venda),
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
          active: Boolean(item.active),
          exibir_imovel: Boolean(item.exibir_imovel),
          exclusividade: Boolean(item.exclusividade),
          // Dados do proprietário
          proprietario_nome: item.proprietario_nome || '',
          proprietario_telefone: item.proprietario_telefone || '',
          proprietario_email: item.proprietario_email || '',
          proprietario_observacoes: item.proprietario_observacoes || '',
        });
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

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();

    if (!formData.codigo_imovel || !formData.tipo_imovel) {
      toast.error('Código e Tipo são obrigatórios');
      return;
    }

    try {
      setIsSubmitting(true);

      // Converter strings vazias em null para números
      const dataToSend = {
        ...formData,
        valor_venda: formData.valor_venda ? parseFloat(parseCurrencyInput(formData.valor_venda)) : null,
        valor_condominio: formData.valor_condominio ? parseFloat(parseCurrencyInput(formData.valor_condominio)) : null,
        valor_iptu: formData.valor_iptu ? parseFloat(parseCurrencyInput(formData.valor_iptu)) : null,
        dormitorios: formData.dormitorios ? parseInt(formData.dormitorios) : null,
        suites: formData.suites ? parseInt(formData.suites) : null,
        banheiros: formData.banheiros ? parseInt(formData.banheiros) : null,
        garagem: formData.garagem ? parseInt(formData.garagem) : null,
        area_total: formData.area_total ? parseFloat(parseAreaInput(formData.area_total)) : null,
        area_privativa: formData.area_privativa ? parseFloat(parseAreaInput(formData.area_privativa)) : null,
        area_terreno: formData.area_terreno ? parseFloat(parseAreaInput(formData.area_terreno)) : null,
      };

      if (isEditMode && propertyId) {
        await api.put(`/imoveis/${propertyId}`, dataToSend);
        toast.success('Imóvel atualizado com sucesso');
      } else {
        await api.post('/imoveis', dataToSend);
        toast.success('Imóvel criado com sucesso');
      }
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
        : responseData?.message || responseData?.error || 'Erro ao salvar imóvel';
      console.error('Detalhes da validação:', responseData);
      toast.error(message);
    } finally {
      setIsSubmitting(false);
    }
  };

  const renderFormTab = () => {
    switch (currentTab) {
      case 'identificacao':
        return (
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-foreground mb-1">Código do Imóvel *</label>
                <input
                  type="text"
                  value={formData.codigo_imovel}
                  onChange={(e) => setFormData({ ...formData, codigo_imovel: e.target.value })}
                  className="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                  required
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-foreground mb-1">Referência</label>
                <input
                  type="text"
                  value={formData.referencia_imovel}
                  onChange={(e) => setFormData({ ...formData, referencia_imovel: e.target.value })}
                  className="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-foreground mb-1">Finalidade *</label>
                <select
                  value={formData.finalidade_imovel}
                  onChange={(e) => setFormData({ ...formData, finalidade_imovel: e.target.value })}
                  className="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                >
                  <option value="venda">Venda</option>
                  <option value="aluguel">Aluguel</option>
                  <option value="temporada">Temporada</option>
                </select>
              </div>

              <div>
                <label className="block text-sm font-medium text-foreground mb-1">Valor de Venda/Aluguel</label>
                <div className="relative">
                  <span className="absolute left-4 top-1/2 -translate-y-1/2 text-muted-foreground text-sm">R$</span>
                  <input
                    type="text"
                    inputMode="decimal"
                    value={formData.valor_venda}
                    onChange={(e) => setFormData({ ...formData, valor_venda: formatCurrencyInput(e.target.value) })}
                    className="w-full pl-10 pr-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="0,00"
                  />
                </div>
              </div>
            </div>

            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-foreground mb-1">Valor Condomínio</label>
                <div className="relative">
                  <span className="absolute left-4 top-1/2 -translate-y-1/2 text-muted-foreground text-sm">R$</span>
                  <input
                    type="text"
                    inputMode="decimal"
                    value={formData.valor_condominio}
                    onChange={(e) => setFormData({ ...formData, valor_condominio: formatCurrencyInput(e.target.value) })}
                    className="w-full pl-10 pr-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="0,00"
                  />
                </div>
              </div>

              <div>
                <label className="block text-sm font-medium text-foreground mb-1">Valor IPTU</label>
                <div className="relative">
                  <span className="absolute left-4 top-1/2 -translate-y-1/2 text-muted-foreground text-sm">R$</span>
                  <input
                    type="text"
                    inputMode="decimal"
                    value={formData.valor_iptu}
                    onChange={(e) => setFormData({ ...formData, valor_iptu: formatCurrencyInput(e.target.value) })}
                    className="w-full pl-10 pr-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                    placeholder="0,00"
                  />
                </div>
              </div>
            </div>

            <div className="flex gap-4">
              <label className="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  checked={formData.active}
                  onChange={(e) => setFormData({ ...formData, active: e.target.checked })}
                  className="w-4 h-4 rounded"
                />
                <span className="text-sm text-foreground">Ativo</span>
              </label>

              <label className="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  checked={formData.exibir_imovel}
                  onChange={(e) => setFormData({ ...formData, exibir_imovel: e.target.checked })}
                  className="w-4 h-4 rounded"
                />
                <span className="text-sm text-foreground">Exibir no Portal</span>
              </label>

              <label className="flex items-center gap-2 cursor-pointer">
                <input
                  type="checkbox"
                  checked={formData.exclusividade}
                  onChange={(e) => setFormData({ ...formData, exclusividade: e.target.checked })}
                  className="w-4 h-4 rounded"
                />
                <span className="text-sm text-foreground">Exclusividade</span>
              </label>
            </div>
          </div>
        );

      case 'tipo':
        return (
          <div className="space-y-4">
            <div>
              <label className="block text-sm font-medium text-foreground mb-1">Tipo de Imóvel *</label>
              <select
                value={formData.tipo_imovel}
                onChange={(e) => setFormData({ ...formData, tipo_imovel: e.target.value })}
                className="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                required
              >
                <option value="apartamento">Apartamento</option>
                <option value="casa">Casa</option>
                <option value="terreno">Terreno</option>
                <option value="lote">Lote</option>
                <option value="sala_comercial">Sala Comercial</option>
                <option value="loja">Loja</option>
                <option value="galpao">Galpão</option>
                <option value="chacara">Chácara</option>
                <option value="sitio">Sítio</option>
                <option value="fazenda">Fazenda</option>
                <option value="outro">Outro</option>
              </select>
            </div>

            <div className="grid grid-cols-4 gap-4">
              <div>
                <label className="block text-sm font-medium text-foreground mb-1">Dormitórios</label>
                <input
                  type="text"
                  inputMode="numeric"
                  value={formData.dormitorios}
                  onChange={(e) => setFormData({ ...formData, dormitorios: e.target.value.replace(/\D/g, '').slice(0, 2) })}
                  className="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 text-center"
                  placeholder="0"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-foreground mb-1">Suítes</label>
                <input
                  type="text"
                  inputMode="numeric"
                  value={formData.suites}
                  onChange={(e) => setFormData({ ...formData, suites: e.target.value.replace(/\D/g, '').slice(0, 2) })}
                  className="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 text-center"
                  placeholder="0"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-foreground mb-1">Banheiros</label>
                <input
                  type="text"
                  inputMode="numeric"
                  value={formData.banheiros}
                  onChange={(e) => setFormData({ ...formData, banheiros: e.target.value.replace(/\D/g, '').slice(0, 2) })}
                  className="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 text-center"
                  placeholder="0"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-foreground mb-1">Garagem</label>
                <input
                  type="text"
                  inputMode="numeric"
                  value={formData.garagem}
                  onChange={(e) => setFormData({ ...formData, garagem: e.target.value.replace(/\D/g, '') })}
                  className="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 text-center"
                  placeholder="0"
                />
              </div>
            </div>
          </div>
        );

      case 'edificio':
        return (
          <div className="space-y-4">
            <div>
              <label className="flex items-center gap-2 cursor-pointer mb-4">
                <input
                  type="checkbox"
                  checked={formData.em_condominio}
                  onChange={(e) => setFormData({ ...formData, em_condominio: e.target.checked })}
                  className="w-4 h-4 rounded"
                />
                <span className="text-sm text-foreground">Em Condomínio</span>
              </label>
            </div>

            {formData.em_condominio && (
              <div>
                <label className="block text-sm font-medium text-foreground mb-1">Nome do Condomínio</label>
                <input
                  type="text"
                  value={formData.nome_condominio}
                  onChange={(e) => setFormData({ ...formData, nome_condominio: e.target.value })}
                  className="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                />
              </div>
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
                  placeholder="00000-000"
                  className="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
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
                    <><MapPin size={18} /> Buscar</>
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
                <label className="block text-sm font-medium text-foreground mb-1">Logradouro</label>
                <input
                  type="text"
                  value={formData.logradouro}
                  onChange={(e) => setFormData({ ...formData, logradouro: e.target.value })}
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

      case 'detalhes':
        return (
          <div className="space-y-4">
            <div className="grid grid-cols-3 gap-4">
              <div>
                <label className="block text-sm font-medium text-foreground mb-1">Área Total (m²)</label>
                <input
                  type="text"
                  inputMode="decimal"
                  value={formData.area_total}
                  onChange={(e) => setFormData({ ...formData, area_total: e.target.value })}
                  onBlur={(e) => setFormData({ ...formData, area_total: formatAreaOnBlur(e.target.value) })}
                  className="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="0,00"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-foreground mb-1">Área Privativa (m²)</label>
                <input
                  type="text"
                  inputMode="decimal"
                  value={formData.area_privativa}
                  onChange={(e) => setFormData({ ...formData, area_privativa: e.target.value })}
                  onBlur={(e) => setFormData({ ...formData, area_privativa: formatAreaOnBlur(e.target.value) })}
                  className="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="0,00"
                />
              </div>

              <div>
                <label className="block text-sm font-medium text-foreground mb-1">Área Terreno (m²)</label>
                <input
                  type="text"
                  inputMode="decimal"
                  value={formData.area_terreno}
                  onChange={(e) => setFormData({ ...formData, area_terreno: e.target.value })}
                  onBlur={(e) => setFormData({ ...formData, area_terreno: formatAreaOnBlur(e.target.value) })}
                  className="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="0,00"
                />
              </div>
            </div>

            <div>
              <label className="block text-sm font-medium text-foreground mb-1">Descrição</label>
              <textarea
                value={formData.descricao}
                onChange={(e) => setFormData({ ...formData, descricao: e.target.value })}
                rows={8}
                className="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Descrição detalhada do imóvel..."
              />
            </div>
          </div>
        );

      case 'proprietario':
        return (
          <div className="space-y-4">
            <p className="text-xs text-amber-400 bg-amber-400/10 border border-amber-400/20 rounded-lg px-3 py-2">
              Uso interno — estas informações não são exibidas no portal ou para clientes.
            </p>
            <div className="grid grid-cols-2 gap-4">
              <div>
                <label className="block text-sm font-medium text-foreground mb-1">Nome do Proprietário</label>
                <input
                  type="text"
                  value={formData.proprietario_nome}
                  onChange={(e) => setFormData({ ...formData, proprietario_nome: e.target.value })}
                  className="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="Nome completo"
                />
              </div>
              <div>
                <label className="block text-sm font-medium text-foreground mb-1">Telefone do Proprietário</label>
                <input
                  type="text"
                  value={formData.proprietario_telefone}
                  onChange={(e) => setFormData({ ...formData, proprietario_telefone: e.target.value })}
                  className="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                  placeholder="(00) 00000-0000"
                />
              </div>
            </div>
            <div>
              <label className="block text-sm font-medium text-foreground mb-1">E-mail do Proprietário</label>
              <input
                type="email"
                value={formData.proprietario_email}
                onChange={(e) => setFormData({ ...formData, proprietario_email: e.target.value })}
                className="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="email@exemplo.com"
              />
            </div>
            <div>
              <label className="block text-sm font-medium text-foreground mb-1">Observações</label>
              <textarea
                value={formData.proprietario_observacoes}
                onChange={(e) => setFormData({ ...formData, proprietario_observacoes: e.target.value })}
                rows={4}
                className="w-full px-4 py-2 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                placeholder="Observações sobre o proprietário..."
              />
            </div>
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
          className="max-w-4xl mx-auto"
        >
          <div className="page-header mb-8">
            <div>
              <h1 className="page-title mb-2 flex items-center gap-3">
                <Home size={36} />
                {isEditMode ? 'Editar Imóvel' : 'Novo Imóvel'}
              </h1>
              <p className="page-subtitle">
                {isEditMode ? 'Atualize os dados do imóvel' : 'Cadastre um novo imóvel no sistema'}
              </p>
            </div>
            <motion.button
              whileHover={{ scale: 1.05 }}
              whileTap={{ scale: 0.95 }}
              onClick={() => setLocation('/properties')}
              className="flex w-full items-center justify-center gap-2 rounded-lg border border-white/20 bg-white/10 px-5 py-3 font-semibold text-foreground sm:w-auto"
            >
              <X size={18} />
              Cancelar
            </motion.button>
          </div>

          <form onSubmit={handleSubmit}>
            <div className="glass-panel rounded-2xl p-6">
              {isLoadingProperty ? (
                <div className="mb-6 flex items-center gap-2 text-sm text-muted-foreground">
                  <Loader2 className="animate-spin" size={16} />
                  Carregando dados do imóvel...
                </div>
              ) : null}
              <div className="flex gap-2 mb-6 border-b border-white/10">
                <button
                  type="button"
                  onClick={() => setCurrentTab('identificacao')}
                  className={`px-4 py-2 font-medium transition-all ${
                    currentTab === 'identificacao'
                      ? 'text-blue-400 border-b-2 border-blue-400'
                      : 'text-muted-foreground hover:text-foreground'
                  }`}
                >
                  Identificação
                </button>
                <button
                  type="button"
                  onClick={() => setCurrentTab('tipo')}
                  className={`px-4 py-2 font-medium transition-all ${
                    currentTab === 'tipo'
                      ? 'text-blue-400 border-b-2 border-blue-400'
                      : 'text-muted-foreground hover:text-foreground'
                  }`}
                >
                  Tipo
                </button>
                <button
                  type="button"
                  onClick={() => setCurrentTab('edificio')}
                  className={`px-4 py-2 font-medium transition-all ${
                    currentTab === 'edificio'
                      ? 'text-blue-400 border-b-2 border-blue-400'
                      : 'text-muted-foreground hover:text-foreground'
                  }`}
                >
                  Edifício/Condomínio
                </button>
                <button
                  type="button"
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
                  type="button"
                  onClick={() => setCurrentTab('detalhes')}
                  className={`px-4 py-2 font-medium transition-all ${
                    currentTab === 'detalhes'
                      ? 'text-blue-400 border-b-2 border-blue-400'
                      : 'text-muted-foreground hover:text-foreground'
                  }`}
                >
                  Detalhes
                </button>
                <button
                  type="button"
                  onClick={() => setCurrentTab('proprietario')}
                  className={`px-4 py-2 font-medium transition-all ${
                    currentTab === 'proprietario'
                      ? 'text-amber-400 border-b-2 border-amber-400'
                      : 'text-muted-foreground hover:text-foreground'
                  }`}
                >
                  Proprietário
                </button>
              </div>

              {renderFormTab()}

              <div className="flex gap-3 mt-6">
                <motion.button
                  type="button"
                  whileHover={{ scale: 1.05 }}
                  whileTap={{ scale: 0.95 }}
                  onClick={() => setLocation('/properties')}
                  className="flex-1 px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground font-semibold"
                  disabled={isSubmitting || isLoadingProperty}
                >
                  Cancelar
                </motion.button>
                <motion.button
                  type="submit"
                  whileHover={{ scale: 1.05 }}
                  whileTap={{ scale: 0.95 }}
                  className="flex-1 px-4 py-3 bg-gradient-to-r from-blue-500 to-blue-600 rounded-lg text-white font-semibold flex items-center justify-center gap-2"
                  disabled={isSubmitting || isLoadingProperty}
                >
                  {isSubmitting ? (
                    <>
                      <motion.div animate={{ rotate: 360 }} transition={{ duration: 1, repeat: Infinity, ease: 'linear' }}>
                        <Save size={18} />
                      </motion.div>
                      Salvando...
                    </>
                  ) : (
                    <>
                      <Save size={18} />
                      {isEditMode ? 'Atualizar Imóvel' : 'Salvar Imóvel'}
                    </>
                  )}
                </motion.button>
              </div>
            </div>
          </form>
        </motion.div>
      </div>
    </div>
  );
}
