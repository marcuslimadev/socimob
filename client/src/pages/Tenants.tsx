import { useState, useEffect } from 'react';
import { motion } from 'framer-motion';
import {
  Building2,
  Plus,
  Search,
  Edit2,
  Trash2,
  Save,
  X,
  Loader2,
  Key,
  Mail,
  Phone,
  Globe,
} from 'lucide-react';
import Sidebar from '../components/Sidebar';
import { api } from '../lib/api';
import { toast } from 'sonner';

interface Tenant {
  id: number;
  name: string;
  domain: string;
  slug: string;
  contact_email: string | null;
  contact_phone: string | null;
  logo_url: string | null;
  primary_color: string;
  secondary_color: string;
  subscription_status: string;
  is_active: boolean;
  created_at: string;
  // Integration fields - Twilio
  twilio_account_sid?: string;
  twilio_auth_token?: string;
  twilio_whatsapp_from?: string;
  twilio_template_welcome_sid?: string;
  // Integration fields - OpenAI
  openai_api_key?: string;
  openai_model?: string;
  ai_assistant_name?: string;
  // Integration fields - Email
  mail_driver?: string;
  mail_host?: string;
  mail_port?: number;
  mail_username?: string;
  mail_password?: string;
  mail_encryption?: string;
  mail_from_address?: string;
  mail_from_name?: string;
  // Company fields
  razao_social?: string;
  cnpj?: string;
  endereco?: string;
  slogan?: string;
  favicon_url?: string;
}

export default function Tenants() {
  const [tenants, setTenants] = useState<Tenant[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [searchTerm, setSearchTerm] = useState('');
  const [editingTenant, setEditingTenant] = useState<Tenant | null>(null);
  const [showModal, setShowModal] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const [logoFile, setLogoFile] = useState<File | null>(null);
  const [logoPreview, setLogoPreview] = useState<string | null>(null);

  const containerVariants = {
    hidden: { opacity: 0 },
    visible: {
      opacity: 1,
      transition: { staggerChildren: 0.05, delayChildren: 0.1 },
    },
  };

  const itemVariants = {
    hidden: { opacity: 0, y: 20 },
    visible: { opacity: 1, y: 0 },
  };

  useEffect(() => {
    fetchTenants();
  }, []);

  const fetchTenants = async () => {
    try {
      setIsLoading(true);
      const response = await api.get('/super-admin/tenants');
      if (response.data?.tenants) {
        setTenants(response.data.tenants);
      }
    } catch (error: any) {
      console.error('Erro ao carregar tenants:', error);
      toast.error('Erro ao carregar lista de tenants');
    } finally {
      setIsLoading(false);
    }
  };

  const handleEdit = (tenant: Tenant) => {
    setEditingTenant(tenant);
    setLogoFile(null);
    setLogoPreview(tenant.logo_url || null);
    setShowModal(true);
  };

  const handleLogoChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file) {
      setLogoFile(file);
      const reader = new FileReader();
      reader.onloadend = () => {
        setLogoPreview(reader.result as string);
      };
      reader.readAsDataURL(file);
    }
  };

  const handleSave = async () => {
    if (!editingTenant) return;

    try {
      setIsSaving(true);
      
      const formData = new FormData();
      formData.append('name', editingTenant.name);
      formData.append('domain', editingTenant.domain);
      formData.append('slug', editingTenant.slug);
      if (editingTenant.contact_email) formData.append('contact_email', editingTenant.contact_email);
      if (editingTenant.contact_phone) formData.append('contact_phone', editingTenant.contact_phone);
      
      // Company fields
      if (editingTenant.razao_social) formData.append('razao_social', editingTenant.razao_social);
      if (editingTenant.cnpj) formData.append('cnpj', editingTenant.cnpj);
      if (editingTenant.endereco) formData.append('endereco', editingTenant.endereco);
      if (editingTenant.slogan) formData.append('slogan', editingTenant.slogan);
      if (editingTenant.favicon_url) formData.append('favicon_url', editingTenant.favicon_url);
      
      // Twilio fields
      if (editingTenant.twilio_account_sid) formData.append('twilio_account_sid', editingTenant.twilio_account_sid);
      if (editingTenant.twilio_auth_token) formData.append('twilio_auth_token', editingTenant.twilio_auth_token);
      if (editingTenant.twilio_whatsapp_from) formData.append('twilio_whatsapp_from', editingTenant.twilio_whatsapp_from);
      if (editingTenant.twilio_template_welcome_sid) formData.append('twilio_template_welcome_sid', editingTenant.twilio_template_welcome_sid);
      
      // OpenAI fields
      if (editingTenant.openai_api_key) formData.append('openai_api_key', editingTenant.openai_api_key);
      if (editingTenant.openai_model) formData.append('openai_model', editingTenant.openai_model);
      if (editingTenant.ai_assistant_name) formData.append('ai_assistant_name', editingTenant.ai_assistant_name);
      
      // Email fields
      if (editingTenant.mail_driver) formData.append('mail_driver', editingTenant.mail_driver);
      if (editingTenant.mail_host) formData.append('mail_host', editingTenant.mail_host);
      if (editingTenant.mail_port) formData.append('mail_port', editingTenant.mail_port.toString());
      if (editingTenant.mail_username) formData.append('mail_username', editingTenant.mail_username);
      if (editingTenant.mail_password) formData.append('mail_password', editingTenant.mail_password);
      if (editingTenant.mail_encryption) formData.append('mail_encryption', editingTenant.mail_encryption);
      if (editingTenant.mail_from_address) formData.append('mail_from_address', editingTenant.mail_from_address);
      if (editingTenant.mail_from_name) formData.append('mail_from_name', editingTenant.mail_from_name);
      
      // Logo file
      if (logoFile) {
        formData.append('logo', logoFile);
      }
      
      const response = await api.post(
        `/super-admin/tenants/${editingTenant.id}?_method=PUT`,
        formData,
        {
          headers: {
            'Content-Type': 'multipart/form-data',
          },
        }
      );
      
      if (response.data?.success) {
        toast.success('Tenant atualizado com sucesso');
        setShowModal(false);
        setEditingTenant(null);
        setLogoFile(null);
        setLogoPreview(null);
        fetchTenants();
      }
    } catch (error: any) {
      console.error('Erro ao salvar tenant:', error);
      toast.error(error?.response?.data?.message || 'Erro ao salvar tenant');
    } finally {
      setIsSaving(false);
    }
  };

  const filteredTenants = tenants.filter(
    (t) =>
      t.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
      t.domain.toLowerCase().includes(searchTerm.toLowerCase()) ||
      t.slug.toLowerCase().includes(searchTerm.toLowerCase())
  );

  const getStatusBadge = (status: string) => {
    const colors = {
      active: 'bg-green-500/20 text-green-400 border-green-500/30',
      inactive: 'bg-gray-500/20 text-gray-400 border-gray-500/30',
      suspended: 'bg-red-500/20 text-red-400 border-red-500/30',
      expired: 'bg-orange-500/20 text-orange-400 border-orange-500/30',
    };
    return colors[status as keyof typeof colors] || colors.inactive;
  };

  return (
    <div className="flex">
      <Sidebar />

      <div className="page-shell">
        <motion.div
          variants={containerVariants}
          initial="hidden"
          animate="visible"
          className="max-w-7xl mx-auto"
        >
          <motion.div variants={itemVariants} className="mb-8">
            <h1 className="page-title mb-2 flex items-center gap-3">
              <Building2 size={40} />
              Gestão de Tenants
            </h1>
            <p className="page-subtitle">
              Gerencie todas as imobiliárias da plataforma
            </p>
          </motion.div>

          {/* Search Bar */}
          <motion.div variants={itemVariants} className="mb-6">
            <div className="glass-panel p-4 rounded-2xl">
              <div className="relative">
                <Search
                  className="absolute left-4 top-1/2 transform -translate-y-1/2 text-muted-foreground"
                  size={20}
                />
                <input
                  type="text"
                  value={searchTerm}
                  onChange={(e) => setSearchTerm(e.target.value)}
                  placeholder="Buscar por nome, domínio ou slug..."
                  className="w-full pl-12 pr-4 py-3 bg-white/5 border border-white/10 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                />
              </div>
            </div>
          </motion.div>

          {/* Tenants List */}
          {isLoading ? (
            <div className="flex items-center justify-center py-20">
              <Loader2 className="w-8 h-8 animate-spin text-blue-500" />
            </div>
          ) : (
            <motion.div variants={itemVariants} className="space-y-4">
              {filteredTenants.map((tenant) => (
                <div
                  key={tenant.id}
                  className="glass-panel p-6 rounded-2xl hover:bg-white/10 transition-all"
                >
                  <div className="flex items-start justify-between">
                    <div className="flex items-start gap-4 flex-1">
                      {tenant.logo_url ? (
                        <img
                          src={tenant.logo_url}
                          alt={tenant.name}
                          className="w-16 h-16 rounded-lg object-contain bg-white/10"
                        />
                      ) : (
                        <div className="w-16 h-16 rounded-lg bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-2xl font-bold text-white">
                          {tenant.name.substring(0, 2).toUpperCase()}
                        </div>
                      )}

                      <div className="flex-1">
                        <h3 className="text-xl font-bold text-foreground mb-1">
                          {tenant.name}
                        </h3>
                        <div className="flex flex-wrap gap-2 mb-3">
                          <span
                            className={`px-3 py-1 rounded-full text-xs font-semibold border ${getStatusBadge(
                              tenant.subscription_status
                            )}`}
                          >
                            {tenant.subscription_status}
                          </span>
                          {tenant.is_active && (
                            <span className="px-3 py-1 rounded-full text-xs font-semibold border bg-blue-500/20 text-blue-400 border-blue-500/30">
                              Ativo
                            </span>
                          )}
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm text-muted-foreground">
                          <div className="flex items-center gap-2">
                            <Globe size={14} />
                            <span>{tenant.domain}</span>
                          </div>
                          {tenant.contact_email && (
                            <div className="flex items-center gap-2">
                              <Mail size={14} />
                              <span>{tenant.contact_email}</span>
                            </div>
                          )}
                          {tenant.contact_phone && (
                            <div className="flex items-center gap-2">
                              <Phone size={14} />
                              <span>{tenant.contact_phone}</span>
                            </div>
                          )}
                          {tenant.twilio_account_sid && (
                            <div className="flex items-center gap-2">
                              <Key size={14} className="text-green-400" />
                              <span className="text-green-400">Twilio ✓</span>
                            </div>
                          )}
                          {tenant.openai_api_key && (
                            <div className="flex items-center gap-2">
                              <Key size={14} className="text-purple-400" />
                              <span className="text-purple-400">OpenAI ✓</span>
                            </div>
                          )}
                        </div>
                      </div>
                    </div>

                    <motion.button
                      whileHover={{ scale: 1.05 }}
                      whileTap={{ scale: 0.95 }}
                      onClick={() => handleEdit(tenant)}
                      className="px-4 py-2 bg-blue-500/20 hover:bg-blue-500/30 rounded-lg text-blue-400 border border-blue-500/30 transition-all flex items-center gap-2"
                    >
                      <Edit2 size={16} />
                      Editar
                    </motion.button>
                  </div>
                </div>
              ))}

              {filteredTenants.length === 0 && (
                <div className="text-center py-20 text-muted-foreground">
                  Nenhum tenant encontrado
                </div>
              )}
            </motion.div>
          )}
        </motion.div>
      </div>

      {/* Edit Modal */}
      {showModal && editingTenant && (
        <div className="fixed inset-0 bg-black/80 backdrop-blur-sm flex items-center justify-center z-50 p-4">
          <motion.div
            initial={{ opacity: 0, scale: 0.95 }}
            animate={{ opacity: 1, scale: 1 }}
            className="glass-panel p-8 rounded-2xl w-full max-w-7xl max-h-[95vh] overflow-y-auto"
          >
            <div className="flex items-center justify-between mb-6 sticky top-0 bg-background/95 backdrop-blur-sm py-4 z-10 -mt-8 -mx-8 px-8 border-b border-white/10">
              <h2 className="text-3xl font-bold text-foreground flex items-center gap-3">
                <Edit2 size={28} />
                Editar Tenant
              </h2>
              <button
                onClick={() => {
                  setShowModal(false);
                  setEditingTenant(null);
                }}
                className="text-muted-foreground hover:text-foreground transition-colors"
              >
                <X size={28} />
              </button>
            </div>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-6 mt-6">
              {/* Coluna Esquerda */}
              <div className="space-y-6">
                {/* Informações Básicas */}
                <div className="bg-white/5 rounded-xl p-6 border border-white/10">
                  <h3 className="text-lg font-bold text-foreground mb-4 flex items-center gap-2">
                    <Building2 size={20} />
                    Informações Básicas
                  </h3>
                  
                  <div className="space-y-4">
                    <div>
                      <label className="block text-sm font-semibold text-foreground mb-2">
                        Nome *
                      </label>
                      <input
                        type="text"
                        value={editingTenant.name}
                        onChange={(e) =>
                          setEditingTenant({ ...editingTenant, name: e.target.value })
                        }
                        className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                      />
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <label className="block text-sm font-semibold text-foreground mb-2">
                          Domínio *
                        </label>
                        <input
                          type="text"
                          value={editingTenant.domain}
                          onChange={(e) =>
                            setEditingTenant({ ...editingTenant, domain: e.target.value })
                          }
                          className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                      </div>

                      <div>
                        <label className="block text-sm font-semibold text-foreground mb-2">
                          Slug *
                        </label>
                        <input
                          type="text"
                          value={editingTenant.slug}
                          onChange={(e) =>
                            setEditingTenant({ ...editingTenant, slug: e.target.value })
                          }
                          className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                      </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <label className="block text-sm font-semibold text-foreground mb-2">
                          Email
                        </label>
                        <input
                          type="email"
                          value={editingTenant.contact_email || ''}
                          onChange={(e) =>
                            setEditingTenant({
                              ...editingTenant,
                              contact_email: e.target.value,
                            })
                          }
                          className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                      </div>

                      <div>
                        <label className="block text-sm font-semibold text-foreground mb-2">
                          Telefone
                        </label>
                        <input
                          type="tel"
                          value={editingTenant.contact_phone || ''}
                          onChange={(e) =>
                            setEditingTenant({
                              ...editingTenant,
                              contact_phone: e.target.value,
                            })
                          }
                          className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                      </div>
                    </div>
                  </div>
                </div>

                {/* Informações da Empresa */}
                <div className="bg-white/5 rounded-xl p-6 border border-white/10">
                  <h3 className="text-lg font-bold text-foreground mb-4">🏢 Informações da Empresa</h3>
                  
                  <div className="space-y-4">
                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <label className="block text-sm font-semibold text-foreground mb-2">
                          Razão Social
                        </label>
                        <input
                          type="text"
                          value={editingTenant.razao_social || ''}
                          onChange={(e) =>
                            setEditingTenant({
                              ...editingTenant,
                              razao_social: e.target.value,
                            })
                          }
                          className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                      </div>

                      <div>
                        <label className="block text-sm font-semibold text-foreground mb-2">
                          CNPJ
                        </label>
                        <input
                          type="text"
                          value={editingTenant.cnpj || ''}
                          onChange={(e) =>
                            setEditingTenant({
                              ...editingTenant,
                              cnpj: e.target.value,
                            })
                          }
                          className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                          placeholder="00.000.000/0000-00"
                        />
                      </div>
                    </div>

                    <div>
                      <label className="block text-sm font-semibold text-foreground mb-2">
                        Endereço
                      </label>
                      <input
                        type="text"
                        value={editingTenant.endereco || ''}
                        onChange={(e) =>
                          setEditingTenant({
                            ...editingTenant,
                            endereco: e.target.value,
                          })
                        }
                        className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                      />
                    </div>

                    <div>
                      <label className="block text-sm font-semibold text-foreground mb-2">
                        Slogan
                      </label>
                      <input
                        type="text"
                        value={editingTenant.slogan || ''}
                        onChange={(e) =>
                          setEditingTenant({
                            ...editingTenant,
                            slogan: e.target.value,
                          })
                        }
                        className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                      />
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <label className="block text-sm font-semibold text-foreground mb-2">
                          Logo
                        </label>
                        <input
                          type="file"
                          accept="image/*"
                          onChange={handleLogoChange}
                          className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                        />
                        {logoPreview && (
                          <div className="mt-3">
                            <img
                              src={logoPreview}
                              alt="Preview"
                              className="h-20 object-contain bg-white/10 rounded-lg p-2"
                            />
                          </div>
                        )}
                      </div>

                      <div>
                        <label className="block text-sm font-semibold text-foreground mb-2">
                          Favicon URL
                        </label>
                        <input
                          type="url"
                          value={editingTenant.favicon_url || ''}
                          onChange={(e) =>
                            setEditingTenant({
                              ...editingTenant,
                              favicon_url: e.target.value,
                            })
                          }
                          className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                          placeholder="https://exemplo.com/favicon.ico"
                        />
                      </div>
                    </div>
                  </div>
                </div>

                {/* Configurações de Email */}
                <div className="bg-white/5 rounded-xl p-6 border border-white/10">
                  <h3 className="text-lg font-bold text-foreground mb-4">📧 Configurações de Email (SMTP)</h3>
                  
                  <div className="space-y-4">
                    <div className="grid grid-cols-3 gap-4">
                      <div>
                        <label className="block text-sm font-semibold text-foreground mb-2">
                          Driver
                        </label>
                        <input
                          type="text"
                          value={editingTenant.mail_driver || ''}
                          onChange={(e) =>
                            setEditingTenant({
                              ...editingTenant,
                              mail_driver: e.target.value,
                            })
                          }
                          className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                          placeholder="smtp"
                        />
                      </div>

                      <div>
                        <label className="block text-sm font-semibold text-foreground mb-2">
                          Host
                        </label>
                        <input
                          type="text"
                          value={editingTenant.mail_host || ''}
                          onChange={(e) =>
                            setEditingTenant({
                              ...editingTenant,
                              mail_host: e.target.value,
                            })
                          }
                          className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                          placeholder="smtp.titan.email"
                        />
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              {/* Coluna Direita - Integrações */}
              <div className="space-y-6">
                {/* Twilio */}
                <div className="bg-white/5 rounded-xl p-6 border border-white/10">
                  <h3 className="text-lg font-bold text-foreground mb-4">📱 Integração Twilio (WhatsApp)</h3>
                  
                  <div className="space-y-4">
                    <div>
                      <label className="block text-sm font-semibold text-foreground mb-2">
                        Account SID
                      </label>
                      <input
                        type="text"
                        value={editingTenant.twilio_account_sid || ''}
                        onChange={(e) =>
                          setEditingTenant({
                            ...editingTenant,
                            twilio_account_sid: e.target.value,
                          })
                        }
                        className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono text-sm"
                        placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                      />
                    </div>

                    <div>
                      <label className="block text-sm font-semibold text-foreground mb-2">
                        Auth Token
                      </label>
                      <input
                        type="password"
                        value={editingTenant.twilio_auth_token || ''}
                        onChange={(e) =>
                          setEditingTenant({
                            ...editingTenant,
                            twilio_auth_token: e.target.value,
                          })
                        }
                        className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono text-sm"
                      />
                    </div>

                    <div>
                      <label className="block text-sm font-semibold text-foreground mb-2">
                        WhatsApp From (Número)
                      </label>
                      <input
                        type="text"
                        value={editingTenant.twilio_whatsapp_from || ''}
                        onChange={(e) =>
                          setEditingTenant({
                            ...editingTenant,
                            twilio_whatsapp_from: e.target.value,
                          })
                        }
                        className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                        placeholder="whatsapp:+5511999999999"
                      />
                    </div>

                    <div>
                      <label className="block text-sm font-semibold text-foreground mb-2">
                        Template Welcome SID
                      </label>
                      <input
                        type="text"
                        value={editingTenant.twilio_template_welcome_sid || ''}
                        onChange={(e) =>
                          setEditingTenant({
                            ...editingTenant,
                            twilio_template_welcome_sid: e.target.value,
                          })
                        }
                        className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono text-sm"
                        placeholder="HXxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
                      />
                    </div>
                  </div>
                </div>

                {/* OpenAI */}
                <div className="bg-white/5 rounded-xl p-6 border border-white/10">
                  <h3 className="text-lg font-bold text-foreground mb-4">🤖 Integração OpenAI</h3>
                  
                  <div className="space-y-4">
                    <div>
                      <label className="block text-sm font-semibold text-foreground mb-2">
                        API Key
                      </label>
                      <input
                        type="password"
                        value={editingTenant.openai_api_key || ''}
                        onChange={(e) =>
                          setEditingTenant({
                            ...editingTenant,
                            openai_api_key: e.target.value,
                          })
                        }
                        className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 font-mono text-sm"
                        placeholder="sk-proj-..."
                      />
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                      <div>
                        <label className="block text-sm font-semibold text-foreground mb-2">
                          Modelo
                        </label>
                        <input
                          type="text"
                          value={editingTenant.openai_model || ''}
                          onChange={(e) =>
                            setEditingTenant({
                              ...editingTenant,
                              openai_model: e.target.value,
                            })
                          }
                          className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                          placeholder="gpt-4o-mini"
                        />
                      </div>

                      <div>
                        <label className="block text-sm font-semibold text-foreground mb-2">
                          Nome do Assistente IA
                        </label>
                        <input
                          type="text"
                          value={editingTenant.ai_assistant_name || ''}
                          onChange={(e) =>
                            setEditingTenant({
                              ...editingTenant,
                              ai_assistant_name: e.target.value,
                            })
                          }
                          className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500"
                          placeholder="Teresa"
                        />
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            {/* Botões de Ação */}
            <div className="flex justify-end gap-3 mt-8 pt-6 border-t border-white/10 sticky bottom-0 bg-background/95 backdrop-blur-sm pb-4">
              <motion.button
                  whileHover={{ scale: 1.02 }}
                  whileTap={{ scale: 0.98 }}
                  onClick={handleSave}
                  disabled={isSaving}
                  className="flex-1 px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 rounded-lg font-semibold text-white transition-all flex items-center justify-center gap-2 disabled:opacity-60"
                >
                  {isSaving ? (
                    <Loader2 className="w-5 h-5 animate-spin" />
                  ) : (
                    <Save size={18} />
                  )}
                  Salvar
                </motion.button>

                <motion.button
                  whileHover={{ scale: 1.02 }}
                  whileTap={{ scale: 0.98 }}
                  onClick={() => {
                    setShowModal(false);
                    setEditingTenant(null);
                  }}
                  className="px-6 py-3 bg-white/10 hover:bg-white/20 rounded-lg font-semibold text-foreground transition-all"
                >
                  Cancelar
                </motion.button>
              </div>
          </motion.div>
        </div>
      )}
    </div>
  );
}
