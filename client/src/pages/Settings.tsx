import { useEffect, useState } from 'react';

interface TenantConfig {
  id: number;
  name: string;
  domain: string;
  theme?: string;
  logo_url?: string;
  favicon_url?: string;
  slogan?: string;
  primary_color?: string;
  secondary_color?: string;
  contact_email?: string;
  contact_phone?: string;
  metadata?: Record<string, any>;
  razao_social?: string;
  cnpj?: string;
  endereco?: string;
}
import { motion } from 'framer-motion';
import {
  Settings as SettingsIcon,
  User,
  Bell,
  Lock,
  CreditCard,
  LogOut,
  ChevronRight,
  Save,
  Loader2,
  Building2,
} from 'lucide-react';
import { toast } from 'sonner';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';

interface SettingSection {
  id: string;
  title: string;
  description: string;
  icon: React.ReactNode;
}

interface ProfileUser {
  id: number;
  name: string;
  email: string;
  role: string;
  avatar?: string;
}

interface LeadProfile {
  telefone?: string;
  whatsapp?: string;
  budget_min?: number | string | null;
  budget_max?: number | string | null;
  localizacao?: string | null;
  quartos?: number | string | null;
  suites?: number | string | null;
  garagem?: number | string | null;
  caracteristicas_desejadas?: string | null;
  observacoes_cliente?: string | null;
}

export default function Settings() {
  const [activeSection, setActiveSection] = useState('profile');
  const [profileUser, setProfileUser] = useState<ProfileUser | null>(null);
  const [leadProfile, setLeadProfile] = useState<LeadProfile | null>(null);
  const [tenantConfig, setTenantConfig] = useState<TenantConfig | null>(null);
  const [isLoadingTenant, setIsLoadingTenant] = useState(true);
  const [tenantError, setTenantError] = useState<string | null>(null);
  const [form, setForm] = useState({
    name: '',
    email: '',
    telefone: '',
    whatsapp: '',
    budget_min: '',
    budget_max: '',
    localizacao: '',
    quartos: '',
    suites: '',
    garagem: '',
    caracteristicas_desejadas: '',
    observacoes_cliente: '',
  });
  const [isLoadingProfile, setIsLoadingProfile] = useState(true);
  const [isSavingProfile, setIsSavingProfile] = useState(false);
  const [password, setPassword] = useState('');
  const [passwordConfirm, setPasswordConfirm] = useState('');
  const [isSavingPassword, setIsSavingPassword] = useState(false);
  const [isSavingTenant, setIsSavingTenant] = useState(false);
  const [tenantForm, setTenantForm] = useState({
    name: '',
    contact_email: '',
    contact_phone: '',
    primary_color: '',
    secondary_color: '',
    logo_url: '',
    // Integration fields from tenants table
    openai_api_key: '',
    openai_model: 'gpt-4o-mini',
    ai_assistant_name: 'Assistente Virtual',
    twilio_account_sid: '',
    twilio_auth_token: '',
    twilio_whatsapp_from: '',
    twilio_template_welcome_sid: '',
    mail_driver: 'smtp',
    mail_host: '',
    mail_port: 587,
    mail_username: '',
    mail_password: '',
    mail_encryption: 'tls',
    mail_from_address: '',
    mail_from_name: '',
  });
  
  const [tenantConfigForm, setTenantConfigForm] = useState({
    api_key_pagar_me: '',
    api_key_apm_imoveis: '',
    api_key_neca: '',
    accent_color: '#FF6B6B',
    font_primary: '',
    font_secondary: '',
    font_url: '',
    favicon_url: '',
    smtp_host: '',
    smtp_port: 587,
    smtp_username: '',
    smtp_password: '',
    smtp_from_email: '',
    smtp_from_name: '',
    notify_new_leads: true,
    notify_new_properties: true,
    notify_new_messages: true,
    notification_email: '',
    max_images_per_property: 20,
    max_properties: 1000,
    require_approval_for_properties: false,
    max_leads: 5000,
    auto_assign_leads: false,
    portal_finalidades: [] as string[],
    twilio_account_sid: '',
    twilio_auth_token: '',
    twilio_whatsapp_from: '',
  });

  const storedUser = localStorage.getItem('user');
  const storedRole = storedUser ? (() => {
    try {
      return JSON.parse(storedUser)?.role as string | undefined;
    } catch {
      return undefined;
    }
  })() : undefined;
  const isAdminRole = ['admin', 'super_admin'].includes(profileUser?.role || storedRole || '');

  const sections: SettingSection[] = [
    {
      id: 'profile',
      title: 'Perfil',
      description: 'Gerencie suas informacoes pessoais',
      icon: <User size={24} />,
    },
    ...(isAdminRole ? [{
      id: 'company',
      title: 'Empresa',
      description: 'Configuracoes da imobiliaria e integrações',
      icon: <Building2 size={24} />,
    }] : []),
    {
      id: 'notifications',
      title: 'Notificacoes',
      description: 'Configure suas preferencias de notificacoes',
      icon: <Bell size={24} />,
    },
    {
      id: 'security',
      title: 'Seguranca',
      description: 'Altere sua senha e configure autenticacao',
      icon: <Lock size={24} />,
    },
    {
      id: 'billing',
      title: 'Faturamento',
      description: 'Gerencie seu plano e pagamentos',
      icon: <CreditCard size={24} />,
    },
  ];

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
    fetchProfile();
    fetchTenantConfig();
  }, []);

  useEffect(() => {
    if (tenantConfig) {
      const config = tenantConfig as any; // Type assertion for integration fields
      setTenantForm({
        name: config.name || '',
        contact_email: config.contact_email || '',
        contact_phone: config.contact_phone || '',
        primary_color: config.primary_color || '#1e293b',
        secondary_color: config.secondary_color || '#3b82f6',
        logo_url: config.logo_url || '',
        // Integration fields from tenants table
        openai_api_key: config.openai_api_key || '',
        openai_model: config.openai_model || 'gpt-4o-mini',
        ai_assistant_name: config.ai_assistant_name || 'Assistente Virtual',
        twilio_account_sid: config.twilio_account_sid || '',
        twilio_auth_token: '',
        twilio_whatsapp_from: config.twilio_whatsapp_from || '',
        twilio_template_welcome_sid: config.twilio_template_welcome_sid || '',
        mail_driver: config.mail_driver || 'smtp',
        mail_host: config.mail_host || '',
        mail_port: config.mail_port || 587,
        mail_username: config.mail_username || '',
        mail_password: '',
        mail_encryption: config.mail_encryption || 'tls',
        mail_from_address: config.mail_from_address || '',
        mail_from_name: config.mail_from_name || '',
      });
    }
  }, [tenantConfig]);

  const fetchTenantConfig = async () => {
    try {
      setIsLoadingTenant(true);
      setTenantError(null);
      
      // Se super_admin, pegar tenant_id do localStorage
      const viewAsTenantId = localStorage.getItem('superadmin_view_as_tenant');
      const params = viewAsTenantId ? { tenant_id: viewAsTenantId } : {};
      
      const response = await api.get('/admin/settings', { params });
      if (response.data?.tenant) {
        setTenantConfig(response.data.tenant);
        
        // Populate tenant config form
        if (response.data?.config) {
          const config = response.data.config;
          setTenantConfigForm({
            api_key_pagar_me: config.api_key_pagar_me || '',
            api_key_apm_imoveis: config.api_key_apm_imoveis || '',
            api_key_neca: config.api_key_neca || '',
            accent_color: config.accent_color || '#FF6B6B',
            font_primary: config.font_primary || '',
            font_secondary: config.font_secondary || '',
            font_url: config.font_url || '',
            favicon_url: config.favicon_url || '',
            smtp_host: config.smtp_host || '',
            smtp_port: config.smtp_port || 587,
            smtp_username: config.smtp_username || '',
            smtp_password: '',
            smtp_from_email: config.smtp_from_email || '',
            smtp_from_name: config.smtp_from_name || '',
            notify_new_leads: config.notify_new_leads ?? true,
            notify_new_properties: config.notify_new_properties ?? true,
            notify_new_messages: config.notify_new_messages ?? true,
            notification_email: config.notification_email || '',
            max_images_per_property: config.max_images_per_property || 20,
            max_properties: config.max_properties || 1000,
            require_approval_for_properties: config.require_approval_for_properties ?? false,
            max_leads: config.max_leads || 5000,
            auto_assign_leads: config.auto_assign_leads ?? false,
            portal_finalidades: config.portal_finalidades || [],
            twilio_account_sid: config.twilio_account_sid || '',
            twilio_auth_token: '',
            twilio_whatsapp_from: config.twilio_whatsapp_from || '',
          });
        }
      } else {
        setTenantError('Configuração do tenant não encontrada.');
      }
    } catch (error: any) {
      setTenantError(error?.response?.data?.message || 'Erro ao carregar informações da empresa.');
    } finally {
      setIsLoadingTenant(false);
    }
  };

  const fetchProfile = async () => {
    try {
      setIsLoadingProfile(true);
      
      // Get user from localStorage to check role
      const storedUser = localStorage.getItem('user');
      const user = storedUser ? JSON.parse(storedUser) : null;
      
      // Admin and super_admin use /auth/me, clients use /portal/auth/me
      if (user?.role === 'admin' || user?.role === 'super_admin' || user?.role === 'corretor') {
        const response = await api.get('/auth/me');
        if (response.data?.success && response.data?.data) {
          const userData = response.data.data;
          setProfileUser(userData);
          setForm((prev) => ({
            ...prev,
            name: userData.name || '',
            email: userData.email || '',
          }));
          localStorage.setItem('user', JSON.stringify(userData));
          return;
        }
      } else {
        // Client users use portal endpoint
        const { default: axios } = await import('axios');
        const response = await axios.get('/portal/auth/me', {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'X-Tenant-Domain': window.location.hostname,
          },
        });
        
        // Portal returns { success: true, user: {...}, lead: {...} }
        if (response.data?.success && response.data?.user) {
          setProfileUser(response.data.user);
          setLeadProfile(response.data.lead || null);
          setForm((prev) => ({
            ...prev,
            name: response.data.user.name || '',
            email: response.data.user.email || '',
            telefone: response.data.lead?.telefone || '',
            whatsapp: response.data.lead?.whatsapp || '',
            budget_min: response.data.lead?.budget_min ?? '',
            budget_max: response.data.lead?.budget_max ?? '',
            localizacao: response.data.lead?.localizacao ?? '',
            quartos: response.data.lead?.quartos ?? '',
            suites: response.data.lead?.suites ?? '',
            garagem: response.data.lead?.garagem ?? '',
            caracteristicas_desejadas: response.data.lead?.caracteristicas_desejadas ?? '',
            observacoes_cliente: response.data.lead?.observacoes_cliente ?? '',
          }));
          localStorage.setItem('user', JSON.stringify(response.data.user));
          return;
        }
      }
      
      throw new Error('Perfil nao retornado');
    } catch (error) {
      console.error('Erro ao carregar perfil:', error);
      // Try to load from localStorage as fallback
      const storedUser = localStorage.getItem('user');
      if (storedUser) {
        try {
          const parsed = JSON.parse(storedUser);
          setProfileUser(parsed);
          setForm((prev) => ({
            ...prev,
            name: parsed.name || '',
            email: parsed.email || '',
          }));
        } catch (parseError) {
          console.error('Error parsing stored user:', parseError);
        }
      }
    } finally {
      setIsLoadingProfile(false);
    }
  };

  const handleProfileChange = (field: string, value: string) => {
    setForm((prev) => ({ ...prev, [field]: value }));
  };

  const handleProfileSave = async () => {
    try {
      setIsSavingProfile(true);
      const payload = {
        name: form.name,
        email: form.email,
        telefone: form.telefone || null,
        whatsapp: form.whatsapp || null,
        budget_min: form.budget_min || null,
        budget_max: form.budget_max || null,
        localizacao: form.localizacao || null,
        quartos: form.quartos || null,
        suites: form.suites || null,
        garagem: form.garagem || null,
        caracteristicas_desejadas: form.caracteristicas_desejadas || null,
        observacoes_cliente: form.observacoes_cliente || null,
      };
      // Use admin auth endpoint for profile update
      const response = await api.put('/auth/me', payload);
      if (response.data?.success) {
        setProfileUser(response.data.user || profileUser);
        setLeadProfile(response.data.lead || leadProfile);
        if (response.data.user) {
          localStorage.setItem('user', JSON.stringify(response.data.user));
        }
        toast.success('Perfil atualizado com sucesso');
      } else {
        toast.error(response.data?.message || 'Erro ao salvar perfil');
      }
    } catch (error: any) {
      console.error('Erro ao salvar perfil:', error);
      toast.error(error?.response?.data?.message || 'Erro ao salvar perfil');
    } finally {
      setIsSavingProfile(false);
    }
  };

  const handlePasswordSave = async () => {
    if (!password || password.length < 6) {
      toast.error('A senha deve ter no minimo 6 caracteres');
      return;
    }
    if (password !== passwordConfirm) {
      toast.error('As senhas nao conferem');
      return;
    }
    try {
      setIsSavingPassword(true);
      // Use admin auth endpoint for password update
      const response = await api.put('/auth/me', { password });
      if (response.data?.success) {
        setPassword('');
        setPasswordConfirm('');
        toast.success('Senha atualizada com sucesso');
      } else {
        toast.error(response.data?.message || 'Erro ao atualizar senha');
      }
    } catch (error: any) {
      console.error('Erro ao atualizar senha:', error);
      toast.error(error?.response?.data?.message || 'Erro ao atualizar senha');
    } finally {
      setIsSavingPassword(false);
    }
  };

  const handleTenantSave = async () => {
    try {
      setIsSavingTenant(true);
      
      // Se super_admin, incluir tenant_id do localStorage
      const viewAsTenantId = localStorage.getItem('superadmin_view_as_tenant');
      
      const payload = {
        ...tenantForm,
        config: tenantConfigForm,
        ...(viewAsTenantId ? { tenant_id: parseInt(viewAsTenantId) } : {}),
      };
      
      const response = await api.put('/admin/settings', payload);
      if (response.data?.success) {
        setTenantConfig(response.data.tenant);
        toast.success('Configurações da empresa atualizadas com sucesso');
        fetchTenantConfig(); // Recarregar para pegar dados atualizados
      } else {
        toast.error(response.data?.message || 'Erro ao salvar configurações');
      }
    } catch (error: any) {
      console.error('Erro ao salvar configurações:', error);
      toast.error(error?.response?.data?.message || 'Erro ao salvar configurações da empresa');
    } finally {
      setIsSavingTenant(false);
    }
  };

  useEffect(() => {
    fetchProfile();
    fetchTenantConfig();
  }, []);

  const getRoleLabel = (role?: string) => {
    const roles: Record<string, string> = {
      admin: 'Administrador',
      corretor: 'Corretor',
      user: 'Usuario',
      manager: 'Gerente',
      client: 'Cliente',
      super_admin: 'Super Admin',
    };
    if (!role) return 'Usuário';
    return roles[role] || role;
  };

  const isClient = profileUser?.role === 'client';

  return (
    <div className="flex">
      <Sidebar />

      <div className="page-shell">
        <motion.div
          variants={containerVariants}
          initial="hidden"
          animate="visible"
          className="max-w-6xl mx-auto"
        >
          <motion.div variants={itemVariants} className="mb-8">
            <h1 className="page-title mb-2 flex items-center gap-3">
              <SettingsIcon size={40} />
              Configurações
            </h1>
            <p className="page-subtitle">Personalize sua experiência no SOCIMOB</p>
          </motion.div>

          <div className="grid grid-cols-1 lg:grid-cols-4 gap-8">
            <motion.div variants={itemVariants} className="lg:col-span-1">
              <div className="glass-panel p-4 rounded-2xl sticky top-24 space-y-2">
                {sections.map((section) => (
                  <motion.button
                    key={section.id}
                    whileHover={{ x: 4 }}
                    whileTap={{ scale: 0.98 }}
                    onClick={() => setActiveSection(section.id)}
                    className={`w-full flex items-center gap-3 px-4 py-3 rounded-lg transition-all ${activeSection === section.id
                        ? 'bg-gradient-to-r from-blue-500/30 to-purple-500/30 text-white border border-blue-500/30'
                        : 'text-muted-foreground hover:bg-white/10'
                      }`}
                  >
                    {section.icon}
                    <div className="flex-1 text-left">
                      <p className="font-semibold text-sm">{section.title}</p>
                    </div>
                    {activeSection === section.id && <ChevronRight size={18} />}
                  </motion.button>
                ))}

                <div className="border-t border-white/10 pt-4 mt-4">
                  <motion.button
                    onClick={() => {
                      localStorage.removeItem('token');
                      localStorage.removeItem('user');
                      window.location.href = '/login';
                    }}
                    whileHover={{ x: 4 }}
                    whileTap={{ scale: 0.98 }}
                    className="w-full flex items-center gap-3 px-4 py-3 rounded-lg text-destructive hover:bg-destructive/10 transition-all"
                  >
                    <LogOut size={20} />
                    <span className="font-semibold text-sm">Sair</span>
                  </motion.button>
                </div>
              </div>
            </motion.div>

            <motion.div variants={itemVariants} className="lg:col-span-3">
              {activeSection === 'profile' && (
                <motion.div
                  initial={{ opacity: 0, y: 20 }}
                  animate={{ opacity: 1, y: 0 }}
                  className="space-y-6"
                >
                  {/* Tenant/Company Info Section */}
                  <div className="glass-panel p-6 rounded-2xl mb-6">
                    <div className="flex items-center gap-6 mb-4">
                      {isLoadingTenant ? (
                        <div className="w-20 h-20 flex items-center justify-center">
                          <Loader2 className="w-8 h-8 animate-spin text-muted-foreground" />
                        </div>
                      ) : tenantError ? (
                        <div className="text-destructive font-semibold">{tenantError}</div>
                      ) : tenantConfig ? (
                        <>
                          {tenantConfig.logo_url ? (
                            <img
                              src={tenantConfig.logo_url}
                              alt="Logo"
                              className="w-20 h-20 rounded-full object-contain bg-white/10 border border-white/20"
                            />
                          ) : (
                            <div className="w-20 h-20 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-3xl font-bold text-white uppercase">
                              {tenantConfig.name?.substring(0, 2) || 'EM'}
                            </div>
                          )}
                          <div>
                            <p className="text-xl font-bold text-foreground">{tenantConfig.name}</p>
                            {tenantConfig.slogan && (
                              <p className="text-sm text-muted-foreground italic">{tenantConfig.slogan}</p>
                            )}
                            {tenantConfig.razao_social && (
                              <p className="text-sm text-muted-foreground mt-1">Razão Social: {tenantConfig.razao_social}</p>
                            )}
                            {tenantConfig.cnpj && (
                              <p className="text-sm text-muted-foreground">CNPJ: {tenantConfig.cnpj}</p>
                            )}
                            {tenantConfig.endereco && (
                              <p className="text-sm text-muted-foreground">Endereço: {tenantConfig.endereco}</p>
                            )}
                            {tenantConfig.contact_email && (
                              <p className="text-sm text-muted-foreground">Email: {tenantConfig.contact_email}</p>
                            )}
                            {tenantConfig.contact_phone && (
                              <p className="text-sm text-muted-foreground">Telefone: {tenantConfig.contact_phone}</p>
                            )}
                          </div>
                        </>
                      ) : null}
                    </div>
                  </div>

                  <div className="glass-panel p-6 rounded-2xl">
                    <div className="flex items-center justify-between mb-6">
                      <h2 className="text-2xl font-bold text-foreground">Informacoes Pessoais</h2>
                      {isLoadingProfile && <Loader2 className="w-5 h-5 animate-spin text-muted-foreground" />}
                    </div>

                    <div className="space-y-6">
                      <div className="flex items-center gap-6">
                        <div className="w-20 h-20 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-3xl font-bold text-white uppercase">
                          {profileUser?.name?.substring(0, 2) || 'US'}
                        </div>
                        <div>
                          <p className="text-lg font-semibold text-foreground">{profileUser?.name || 'Usuario'}</p>
                          <p className="text-sm text-muted-foreground">{getRoleLabel(profileUser?.role)}</p>
                        </div>
                      </div>

                      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                          <label className="block text-sm font-semibold text-foreground mb-2">Nome Completo</label>
                          <input
                            type="text"
                            value={form.name}
                            onChange={(e) => handleProfileChange('name', e.target.value)}
                            className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                          />
                        </div>
                        <div>
                          <label className="block text-sm font-semibold text-foreground mb-2">Email</label>
                          <input
                            type="email"
                            value={form.email}
                            onChange={(e) => handleProfileChange('email', e.target.value)}
                            className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                          />
                        </div>
                        <div>
                          <label className="block text-sm font-semibold text-foreground mb-2">Telefone</label>
                          <input
                            type="text"
                            value={form.telefone}
                            onChange={(e) => handleProfileChange('telefone', e.target.value)}
                            className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                          />
                        </div>
                        <div>
                          <label className="block text-sm font-semibold text-foreground mb-2">WhatsApp</label>
                          <input
                            type="text"
                            value={form.whatsapp}
                            onChange={(e) => handleProfileChange('whatsapp', e.target.value)}
                            className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                          />
                        </div>
                      </div>

                      {isClient && (
                        <div className="space-y-4">
                          <h3 className="text-lg font-semibold text-foreground">Preferencias do Cliente</h3>
                          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                              <label className="block text-sm font-semibold text-foreground mb-2">Budget Minimo</label>
                              <input
                                type="number"
                                value={form.budget_min}
                                onChange={(e) => handleProfileChange('budget_min', e.target.value)}
                                className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                              />
                            </div>
                            <div>
                              <label className="block text-sm font-semibold text-foreground mb-2">Budget Maximo</label>
                              <input
                                type="number"
                                value={form.budget_max}
                                onChange={(e) => handleProfileChange('budget_max', e.target.value)}
                                className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                              />
                            </div>
                            <div>
                              <label className="block text-sm font-semibold text-foreground mb-2">Localizacao</label>
                              <input
                                type="text"
                                value={form.localizacao}
                                onChange={(e) => handleProfileChange('localizacao', e.target.value)}
                                className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                              />
                            </div>
                            <div>
                              <label className="block text-sm font-semibold text-foreground mb-2">Quartos</label>
                              <input
                                type="number"
                                value={form.quartos}
                                onChange={(e) => handleProfileChange('quartos', e.target.value)}
                                className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                              />
                            </div>
                            <div>
                              <label className="block text-sm font-semibold text-foreground mb-2">Suites</label>
                              <input
                                type="number"
                                value={form.suites}
                                onChange={(e) => handleProfileChange('suites', e.target.value)}
                                className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                              />
                            </div>
                            <div>
                              <label className="block text-sm font-semibold text-foreground mb-2">Garagem</label>
                              <input
                                type="number"
                                value={form.garagem}
                                onChange={(e) => handleProfileChange('garagem', e.target.value)}
                                className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                              />
                            </div>
                          </div>
                          <div>
                            <label className="block text-sm font-semibold text-foreground mb-2">Caracteristicas desejadas</label>
                            <textarea
                              value={form.caracteristicas_desejadas}
                              onChange={(e) => handleProfileChange('caracteristicas_desejadas', e.target.value)}
                              rows={3}
                              className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                            />
                          </div>
                          <div>
                            <label className="block text-sm font-semibold text-foreground mb-2">Observacoes</label>
                            <textarea
                              value={form.observacoes_cliente}
                              onChange={(e) => handleProfileChange('observacoes_cliente', e.target.value)}
                              rows={3}
                              className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                            />
                          </div>
                        </div>
                      )}

                      <motion.button
                        whileHover={{ scale: 1.02 }}
                        whileTap={{ scale: 0.98 }}
                        onClick={handleProfileSave}
                        disabled={isSavingProfile}
                        className="w-full px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 rounded-lg font-semibold text-white transition-all glow-md hover:glow-lg flex items-center justify-center gap-2 disabled:opacity-60"
                      >
                        {isSavingProfile ? <Loader2 className="w-5 h-5 animate-spin" /> : <Save size={18} />}
                        Salvar Alteracoes
                      </motion.button>
                    </div>
                  </div>
                </motion.div>
              )}

              {activeSection === 'company' && (
                <motion.div
                  initial={{ opacity: 0, y: 20 }}
                  animate={{ opacity: 1, y: 0 }}
                  className="space-y-6"
                >
                  <div className="glass-panel p-6 rounded-2xl">
                    <h2 className="text-xl font-bold text-foreground mb-6 flex items-center gap-2">
                      <Building2 size={24} />
                      Configurações da Empresa
                    </h2>

                    <div className="space-y-4">
                      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                          <label className="block text-sm font-semibold text-foreground mb-2">
                            Nome da Empresa *
                          </label>
                          <input
                            type="text"
                            value={tenantForm.name}
                            onChange={(e) => setTenantForm({ ...tenantForm, name: e.target.value })}
                            className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                            placeholder="Exclusiva Imóveis"
                          />
                        </div>

                        <div>
                          <label className="block text-sm font-semibold text-foreground mb-2">
                            Email de Contato *
                          </label>
                          <input
                            type="email"
                            value={tenantForm.contact_email}
                            onChange={(e) => setTenantForm({ ...tenantForm, contact_email: e.target.value })}
                            className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                            placeholder="contato@empresa.com"
                          />
                        </div>
                      </div>

                      <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                          <label className="block text-sm font-semibold text-foreground mb-2">
                            WhatsApp (com DDD) *
                          </label>
                          <input
                            type="tel"
                            value={tenantForm.contact_phone}
                            onChange={(e) => setTenantForm({ ...tenantForm, contact_phone: e.target.value })}
                            className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                            placeholder="11999999999"
                          />
                          <p className="text-xs text-muted-foreground mt-1">
                            Usado para enviar mensagens e no botão WhatsApp dos emails
                          </p>
                        </div>

                        <div>
                          <label className="block text-sm font-semibold text-foreground mb-2">
                            URL do Logo
                          </label>
                          <input
                            type="text"
                            value={tenantForm.logo_url}
                            onChange={(e) => setTenantForm({ ...tenantForm, logo_url: e.target.value })}
                            className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                            placeholder="https://exemplo.com/logo.png"
                          />
                        </div>
                      </div>

                      <div className="border-t border-white/10 pt-6 mt-6">
                        <h3 className="text-lg font-bold text-foreground mb-4">Integração com IA (OpenAI)</h3>
                        
                        <div className="space-y-4">
                          <div>
                            <label className="block text-sm font-semibold text-foreground mb-2">
                              API Key OpenAI
                            </label>
                            <input
                              type="password"
                              value={tenantForm.openai_api_key}
                              onChange={(e) => setTenantForm({ ...tenantForm, openai_api_key: e.target.value })}
                              className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all font-mono"
                              placeholder="sk-proj-..."
                            />
                            <p className="text-xs text-muted-foreground mt-1">
                              Chave da API OpenAI para gerar emails automáticos com IA. Deixe em branco para usar email padrão.
                              {' '}
                              <a 
                                href="https://platform.openai.com/api-keys" 
                                target="_blank" 
                                rel="noopener noreferrer"
                                className="text-blue-400 hover:text-blue-300 underline"
                              >
                                Obter API Key aqui
                              </a>
                            </p>
                          </div>

                          <div className="grid grid-cols-2 gap-4">
                            <div>
                              <label className="block text-sm font-semibold text-foreground mb-2">
                                Modelo
                              </label>
                              <input
                                type="text"
                                value={tenantForm.openai_model}
                                onChange={(e) => setTenantForm({ ...tenantForm, openai_model: e.target.value })}
                                className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                                placeholder="gpt-4o-mini"
                              />
                            </div>

                            <div>
                              <label className="block text-sm font-semibold text-foreground mb-2">
                                Nome do Assistente
                              </label>
                              <input
                                type="text"
                                value={tenantForm.ai_assistant_name}
                                onChange={(e) => setTenantForm({ ...tenantForm, ai_assistant_name: e.target.value })}
                                className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                                placeholder="Assistente Virtual"
                              />
                            </div>
                          </div>
                        </div>
                      </div>

                      <div className="border-t border-white/10 pt-6 mt-6">
                        <h3 className="text-lg font-bold text-foreground mb-4">Personalização Visual</h3>
                        
                          <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                              <label className="block text-sm font-semibold text-foreground mb-2">
                                Cor Primária
                              </label>
                            <div className="flex gap-2">
                              <input
                                type="color"
                                value={tenantForm.primary_color}
                                onChange={(e) => setTenantForm({ ...tenantForm, primary_color: e.target.value })}
                                className="w-16 h-12 rounded-lg cursor-pointer"
                              />
                              <input
                                type="text"
                                value={tenantForm.primary_color}
                                onChange={(e) => setTenantForm({ ...tenantForm, primary_color: e.target.value })}
                                className="flex-1 px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all font-mono"
                                placeholder="#1e293b"
                              />
                            </div>
                          </div>

                            <div>
                              <label className="block text-sm font-semibold text-foreground mb-2">
                                Cor Secundária
                              </label>
                            <div className="flex gap-2">
                              <input
                                type="color"
                                value={tenantForm.secondary_color}
                                onChange={(e) => setTenantForm({ ...tenantForm, secondary_color: e.target.value })}
                                className="w-16 h-12 rounded-lg cursor-pointer"
                              />
                              <input
                                type="text"
                                value={tenantForm.secondary_color}
                                onChange={(e) => setTenantForm({ ...tenantForm, secondary_color: e.target.value })}
                                className="flex-1 px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all font-mono"
                                placeholder="#3b82f6"
                              />
                              </div>
                            </div>
                          </div>

                          <div className="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                              <label className="block text-sm font-semibold text-foreground mb-2">
                                Fonte Primária
                              </label>
                              <input
                                type="text"
                                value={tenantConfigForm.font_primary}
                                onChange={(e) => setTenantConfigForm({ ...tenantConfigForm, font_primary: e.target.value })}
                                className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                                placeholder="Ex: 'Poppins', 'Montserrat', 'Roboto'"
                              />
                            </div>

                            <div>
                              <label className="block text-sm font-semibold text-foreground mb-2">
                                Fonte Secundária
                              </label>
                              <input
                                type="text"
                                value={tenantConfigForm.font_secondary}
                                onChange={(e) => setTenantConfigForm({ ...tenantConfigForm, font_secondary: e.target.value })}
                                className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                                placeholder="Ex: 'Poppins', 'Montserrat', 'Roboto'"
                              />
                            </div>

                            <div className="md:col-span-2">
                              <label className="block text-sm font-semibold text-foreground mb-2">
                                URL da Fonte (Google Fonts)
                              </label>
                              <input
                                type="text"
                                value={tenantConfigForm.font_url}
                                onChange={(e) => setTenantConfigForm({ ...tenantConfigForm, font_url: e.target.value })}
                                className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                                placeholder="Ex: https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap"
                              />
                              <p className="text-xs text-muted-foreground mt-2">
                                Dica: cole a URL do Google Fonts para carregar a fonte no site.
                              </p>
                            </div>
                          </div>
                        </div>

                      <div className="border-t border-white/10 pt-6 mt-6">
                        <h3 className="text-lg font-bold text-foreground mb-4">Integrações de APIs Externas</h3>
                        
                        <div className="grid grid-cols-1 gap-4">
                          <div>
                            <label className="block text-sm font-semibold text-foreground mb-2">
                              API Key Pagar.me
                            </label>
                            <input
                              type="password"
                              value={tenantConfigForm.api_key_pagar_me}
                              onChange={(e) => setTenantConfigForm({ ...tenantConfigForm, api_key_pagar_me: e.target.value })}
                              className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all font-mono"
                              placeholder="ak_..."
                            />
                          </div>

                          <div>
                            <label className="block text-sm font-semibold text-foreground mb-2">
                              API Key APM Imóveis
                            </label>
                            <input
                              type="password"
                              value={tenantConfigForm.api_key_apm_imoveis}
                              onChange={(e) => setTenantConfigForm({ ...tenantConfigForm, api_key_apm_imoveis: e.target.value })}
                              className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all font-mono"
                              placeholder="Chave de API"
                            />
                          </div>

                          <div>
                            <label className="block text-sm font-semibold text-foreground mb-2">
                              API Key NECA
                            </label>
                            <input
                              type="password"
                              value={tenantConfigForm.api_key_neca}
                              onChange={(e) => setTenantConfigForm({ ...tenantConfigForm, api_key_neca: e.target.value })}
                              className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all font-mono"
                              placeholder="Chave de API"
                            />
                          </div>
                        </div>
                      </div>

                      <div className="border-t border-white/10 pt-6 mt-6">
                        <h3 className="text-lg font-bold text-foreground mb-4">Integração Twilio (WhatsApp)</h3>
                        
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                          <div>
                            <label className="block text-sm font-semibold text-foreground mb-2">
                              Account SID
                            </label>
                            <input
                              type="text"
                              value={tenantForm.twilio_account_sid}
                              onChange={(e) => setTenantForm({ ...tenantForm, twilio_account_sid: e.target.value })}
                              className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all font-mono"
                              placeholder="AC..."
                            />
                          </div>

                          <div>
                            <label className="block text-sm font-semibold text-foreground mb-2">
                              Auth Token
                            </label>
                            <input
                              type="password"
                              value={tenantForm.twilio_auth_token}
                              onChange={(e) => setTenantForm({ ...tenantForm, twilio_auth_token: e.target.value })}
                              className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all font-mono"
                              placeholder="Token"
                            />
                          </div>

                          <div>
                            <label className="block text-sm font-semibold text-foreground mb-2">
                              WhatsApp From (número)
                            </label>
                            <input
                              type="text"
                              value={tenantForm.twilio_whatsapp_from}
                              onChange={(e) => setTenantForm({ ...tenantForm, twilio_whatsapp_from: e.target.value })}
                              className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                              placeholder="whatsapp:+5511999999999"
                            />
                          </div>

                          <div>
                            <label className="block text-sm font-semibold text-foreground mb-2">
                              Template Welcome SID
                            </label>
                            <input
                              type="text"
                              value={tenantForm.twilio_template_welcome_sid}
                              onChange={(e) => setTenantForm({ ...tenantForm, twilio_template_welcome_sid: e.target.value })}
                              className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all font-mono"
                              placeholder="HX..."
                            />
                          </div>
                        </div>
                      </div>

                      <div className="border-t border-white/10 pt-6 mt-6">
                        <h3 className="text-lg font-bold text-foreground mb-4">Configurações de Email SMTP</h3>
                        
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                          <div>
                            <label className="block text-sm font-semibold text-foreground mb-2">
                              Driver
                            </label>
                            <input
                              type="text"
                              value={tenantForm.mail_driver}
                              onChange={(e) => setTenantForm({ ...tenantForm, mail_driver: e.target.value })}
                              className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                              placeholder="smtp"
                            />
                          </div>

                          <div>
                            <label className="block text-sm font-semibold text-foreground mb-2">
                              Host SMTP
                            </label>
                            <input
                              type="text"
                              value={tenantForm.mail_host}
                              onChange={(e) => setTenantForm({ ...tenantForm, mail_host: e.target.value })}
                              className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                              placeholder="smtp.titan.email"
                            />
                          </div>

                          <div>
                            <label className="block text-sm font-semibold text-foreground mb-2">
                              Porta
                            </label>
                            <input
                              type="number"
                              value={tenantForm.mail_port}
                              onChange={(e) => setTenantForm({ ...tenantForm, mail_port: parseInt(e.target.value) || 587 })}
                              className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                              placeholder="587"
                            />
                          </div>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                          <div>
                            <label className="block text-sm font-semibold text-foreground mb-2">
                              Username
                            </label>
                            <input
                              type="text"
                              value={tenantForm.mail_username}
                              onChange={(e) => setTenantForm({ ...tenantForm, mail_username: e.target.value })}
                              className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                              placeholder="usuario@email.com"
                            />
                          </div>

                          <div>
                            <label className="block text-sm font-semibold text-foreground mb-2">
                              Password
                            </label>
                            <input
                              type="password"
                              value={tenantForm.mail_password}
                              onChange={(e) => setTenantForm({ ...tenantForm, mail_password: e.target.value })}
                              className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                              placeholder="********"
                            />
                          </div>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                          <div>
                            <label className="block text-sm font-semibold text-foreground mb-2">
                              Encryption
                            </label>
                            <select
                              value={tenantForm.mail_encryption}
                              onChange={(e) => setTenantForm({ ...tenantForm, mail_encryption: e.target.value })}
                              className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                            >
                              <option value="">Nenhum</option>
                              <option value="tls">TLS</option>
                              <option value="ssl">SSL</option>
                            </select>
                          </div>

                          <div>
                            <label className="block text-sm font-semibold text-foreground mb-2">
                              From Address
                            </label>
                            <input
                              type="email"
                              value={tenantForm.mail_from_address}
                              onChange={(e) => setTenantForm({ ...tenantForm, mail_from_address: e.target.value })}
                              className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                              placeholder="noreply@empresa.com"
                            />
                          </div>

                          <div>
                            <label className="block text-sm font-semibold text-foreground mb-2">
                              From Name
                            </label>
                            <input
                              type="text"
                              value={tenantForm.mail_from_name}
                              onChange={(e) => setTenantForm({ ...tenantForm, mail_from_name: e.target.value })}
                              className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                              placeholder="Empresa"
                            />
                          </div>
                        </div>
                      </div>

                      <div className="border-t border-white/10 pt-6 mt-6">
                        <h3 className="text-lg font-bold text-foreground mb-4">Notificações</h3>
                        
                        <div className="space-y-4">
                          <div className="flex items-center justify-between">
                            <div>
                              <p className="font-semibold text-foreground">Notificar Novos Leads</p>
                              <p className="text-sm text-muted-foreground">Receba notificações quando novos leads chegarem</p>
                            </div>
                            <label className="relative inline-flex items-center cursor-pointer">
                              <input
                                type="checkbox"
                                checked={tenantConfigForm.notify_new_leads}
                                onChange={(e) => setTenantConfigForm({ ...tenantConfigForm, notify_new_leads: e.target.checked })}
                                className="sr-only peer"
                              />
                              <div className="w-11 h-6 bg-gray-700 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-800 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                          </div>

                          <div className="flex items-center justify-between">
                            <div>
                              <p className="font-semibold text-foreground">Notificar Novos Imóveis</p>
                              <p className="text-sm text-muted-foreground">Receba notificações quando novos imóveis forem cadastrados</p>
                            </div>
                            <label className="relative inline-flex items-center cursor-pointer">
                              <input
                                type="checkbox"
                                checked={tenantConfigForm.notify_new_properties}
                                onChange={(e) => setTenantConfigForm({ ...tenantConfigForm, notify_new_properties: e.target.checked })}
                                className="sr-only peer"
                              />
                              <div className="w-11 h-6 bg-gray-700 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-800 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                          </div>

                          <div className="flex items-center justify-between">
                            <div>
                              <p className="font-semibold text-foreground">Notificar Novas Mensagens</p>
                              <p className="text-sm text-muted-foreground">Receba notificações sobre novas mensagens</p>
                            </div>
                            <label className="relative inline-flex items-center cursor-pointer">
                              <input
                                type="checkbox"
                                checked={tenantConfigForm.notify_new_messages}
                                onChange={(e) => setTenantConfigForm({ ...tenantConfigForm, notify_new_messages: e.target.checked })}
                                className="sr-only peer"
                              />
                              <div className="w-11 h-6 bg-gray-700 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-800 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                          </div>

                          <div>
                            <label className="block text-sm font-semibold text-foreground mb-2">
                              Email para Notificações
                            </label>
                            <input
                              type="email"
                              value={tenantConfigForm.notification_email}
                              onChange={(e) => setTenantConfigForm({ ...tenantConfigForm, notification_email: e.target.value })}
                              className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                              placeholder="notificacoes@empresa.com"
                            />
                          </div>
                        </div>
                      </div>

                      <div className="border-t border-white/10 pt-6 mt-6">
                        <h3 className="text-lg font-bold text-foreground mb-4">Limites e Restrições</h3>
                        
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                          <div>
                            <label className="block text-sm font-semibold text-foreground mb-2">
                              Máximo de Imagens por Imóvel
                            </label>
                            <input
                              type="number"
                              value={tenantConfigForm.max_images_per_property}
                              onChange={(e) => setTenantConfigForm({ ...tenantConfigForm, max_images_per_property: parseInt(e.target.value) })}
                              className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                              min="1"
                              max="100"
                            />
                          </div>

                          <div>
                            <label className="block text-sm font-semibold text-foreground mb-2">
                              Máximo de Imóveis
                            </label>
                            <input
                              type="number"
                              value={tenantConfigForm.max_properties}
                              onChange={(e) => setTenantConfigForm({ ...tenantConfigForm, max_properties: parseInt(e.target.value) })}
                              className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                              min="1"
                            />
                          </div>

                          <div>
                            <label className="block text-sm font-semibold text-foreground mb-2">
                              Máximo de Leads
                            </label>
                            <input
                              type="number"
                              value={tenantConfigForm.max_leads}
                              onChange={(e) => setTenantConfigForm({ ...tenantConfigForm, max_leads: parseInt(e.target.value) })}
                              className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                              min="1"
                            />
                          </div>

                          <div className="flex items-center justify-between md:col-span-2">
                            <div>
                              <p className="font-semibold text-foreground">Aprovação Necessária para Imóveis</p>
                              <p className="text-sm text-muted-foreground">Novos imóveis precisam de aprovação antes de serem publicados</p>
                            </div>
                            <label className="relative inline-flex items-center cursor-pointer">
                              <input
                                type="checkbox"
                                checked={tenantConfigForm.require_approval_for_properties}
                                onChange={(e) => setTenantConfigForm({ ...tenantConfigForm, require_approval_for_properties: e.target.checked })}
                                className="sr-only peer"
                              />
                              <div className="w-11 h-6 bg-gray-700 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-800 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                          </div>

                          <div className="flex items-center justify-between md:col-span-2">
                            <div>
                              <p className="font-semibold text-foreground">Atribuição Automática de Leads</p>
                              <p className="text-sm text-muted-foreground">Distribuir leads automaticamente entre corretores</p>
                            </div>
                            <label className="relative inline-flex items-center cursor-pointer">
                              <input
                                type="checkbox"
                                checked={tenantConfigForm.auto_assign_leads}
                                onChange={(e) => setTenantConfigForm({ ...tenantConfigForm, auto_assign_leads: e.target.checked })}
                                className="sr-only peer"
                              />
                              <div className="w-11 h-6 bg-gray-700 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-800 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                            </label>
                          </div>
                        </div>
                      </div>

                      <div className="border-t border-white/10 pt-6 mt-6">
                        <h3 className="text-lg font-bold text-foreground mb-4">Finalidades do Portal</h3>
                        
                        <div className="flex gap-4">
                          <label className="flex items-center gap-2 cursor-pointer">
                            <input
                              type="checkbox"
                              checked={tenantConfigForm.portal_finalidades.includes('venda')}
                              onChange={(e) => {
                                const newFinalidades = e.target.checked
                                  ? [...tenantConfigForm.portal_finalidades, 'venda']
                                  : tenantConfigForm.portal_finalidades.filter(f => f !== 'venda');
                                setTenantConfigForm({ ...tenantConfigForm, portal_finalidades: newFinalidades });
                              }}
                              className="w-5 h-5 rounded border-white/20 bg-white/10 text-blue-600 focus:ring-2 focus:ring-blue-500"
                            />
                            <span className="text-foreground font-semibold">Venda</span>
                          </label>

                          <label className="flex items-center gap-2 cursor-pointer">
                            <input
                              type="checkbox"
                              checked={tenantConfigForm.portal_finalidades.includes('aluguel')}
                              onChange={(e) => {
                                const newFinalidades = e.target.checked
                                  ? [...tenantConfigForm.portal_finalidades, 'aluguel']
                                  : tenantConfigForm.portal_finalidades.filter(f => f !== 'aluguel');
                                setTenantConfigForm({ ...tenantConfigForm, portal_finalidades: newFinalidades });
                              }}
                              className="w-5 h-5 rounded border-white/20 bg-white/10 text-blue-600 focus:ring-2 focus:ring-blue-500"
                            />
                            <span className="text-foreground font-semibold">Aluguel</span>
                          </label>
                        </div>
                      </div>

                      <motion.button
                        whileHover={{ scale: 1.02 }}
                        whileTap={{ scale: 0.98 }}
                        onClick={handleTenantSave}
                        disabled={isSavingTenant}
                        className="w-full px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 rounded-lg font-semibold text-white transition-all glow-md hover:glow-lg flex items-center justify-center gap-2 disabled:opacity-60"
                      >
                        {isSavingTenant ? <Loader2 className="w-5 h-5 animate-spin" /> : <Save size={18} />}
                        Salvar Configurações da Empresa
                      </motion.button>
                    </div>
                  </div>
                </motion.div>
              )}

              {activeSection === 'notifications' && (
                <motion.div
                  initial={{ opacity: 0, y: 20 }}
                  animate={{ opacity: 1, y: 0 }}
                  className="glass-panel p-6 rounded-2xl"
                >
                  <h2 className="text-2xl font-bold text-foreground mb-6">Preferencias de Notificacoes</h2>

                  <div className="space-y-4">
                    {[
                      { title: 'Novos Leads', description: 'Receba notificacoes quando novos leads chegarem' },
                      { title: 'Mensagens', description: 'Notifique-se sobre novas mensagens' },
                      { title: 'Lembretes de Visitas', description: 'Receba lembretes antes das visitas agendadas' },
                      { title: 'Atualizacoes de Preco', description: 'Notifique-se sobre mudancas de preco' },
                    ].map((notif) => (
                      <div key={notif.title} className="flex items-center justify-between p-4 bg-white/5 rounded-lg border border-white/10">
                        <div>
                          <p className="font-semibold text-foreground">{notif.title}</p>
                          <p className="text-sm text-muted-foreground">{notif.description}</p>
                        </div>
                        <label className="flex items-center cursor-pointer">
                          <input type="checkbox" defaultChecked className="w-5 h-5" />
                        </label>
                      </div>
                    ))}
                  </div>
                </motion.div>
              )}

              {activeSection === 'security' && (
                <motion.div
                  initial={{ opacity: 0, y: 20 }}
                  animate={{ opacity: 1, y: 0 }}
                  className="space-y-6"
                >
                  <div className="glass-panel p-6 rounded-2xl">
                    <h2 className="text-2xl font-bold text-foreground mb-6">Seguranca</h2>

                    <div className="space-y-6">
                      <div>
                        <label className="block text-sm font-semibold text-foreground mb-2">Nova Senha</label>
                        <input
                          type="password"
                          value={password}
                          onChange={(e) => setPassword(e.target.value)}
                          placeholder="••••••••"
                          className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                        />
                      </div>
                      <div>
                        <label className="block text-sm font-semibold text-foreground mb-2">Confirmar Nova Senha</label>
                        <input
                          type="password"
                          value={passwordConfirm}
                          onChange={(e) => setPasswordConfirm(e.target.value)}
                          placeholder="••••••••"
                          className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                        />
                      </div>

                      <motion.button
                        whileHover={{ scale: 1.05 }}
                        whileTap={{ scale: 0.95 }}
                        onClick={handlePasswordSave}
                        disabled={isSavingPassword}
                        className="w-full px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 rounded-lg font-semibold text-white transition-all glow-md hover:glow-lg flex items-center justify-center gap-2 disabled:opacity-60"
                      >
                        {isSavingPassword ? <Loader2 className="w-5 h-5 animate-spin" /> : <Save size={18} />}
                        Alterar Senha
                      </motion.button>
                    </div>
                  </div>
                </motion.div>
              )}

              {activeSection === 'billing' && (
                <motion.div
                  initial={{ opacity: 0, y: 20 }}
                  animate={{ opacity: 1, y: 0 }}
                  className="glass-panel p-6 rounded-2xl"
                >
                  <h2 className="text-2xl font-bold text-foreground mb-6">Plano e Faturamento</h2>

                  <div className="mb-6 p-4 bg-gradient-to-r from-blue-500/20 to-purple-500/20 rounded-lg border border-blue-500/30">
                    <p className="text-sm text-muted-foreground mb-1">Plano Atual</p>
                    <p className="text-2xl font-bold text-foreground">Profissional</p>
                    <p className="text-sm text-muted-foreground mt-2">R$ 99,90/mes</p>
                  </div>

                  <motion.button
                    whileHover={{ scale: 1.05 }}
                    whileTap={{ scale: 0.95 }}
                    className="w-full px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 rounded-lg font-semibold text-white transition-all glow-md hover:glow-lg"
                  >
                    Gerenciar Plano
                  </motion.button>
                </motion.div>
              )}
            </motion.div>
          </div>
        </motion.div>
      </div>
    </div>
  );
}
