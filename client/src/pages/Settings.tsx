import { useEffect, useState } from 'react';
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

  const sections: SettingSection[] = [
    {
      id: 'profile',
      title: 'Perfil',
      description: 'Gerencie suas informacoes pessoais',
      icon: <User size={24} />,
    },
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
  }, []);

  const fetchProfile = async () => {
    try {
      setIsLoadingProfile(true);
      const response = await api.get('/portal/profile');
      if (response.data?.user) {
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
      throw new Error('Perfil nao retornado');
    } catch (error) {
      console.error('Erro ao carregar perfil:', error);
      try {
        const fallback = await api.get('/auth/me');
        if (fallback.data?.user) {
          setProfileUser(fallback.data.user);
          setForm((prev) => ({
            ...prev,
            name: fallback.data.user.name || '',
            email: fallback.data.user.email || '',
          }));
        }
      } catch (fallbackError) {
        console.error('Erro ao carregar perfil (fallback):', fallbackError);
        const storedUser = localStorage.getItem('user');
        if (storedUser) {
          const parsed = JSON.parse(storedUser);
          setProfileUser(parsed);
          setForm((prev) => ({
            ...prev,
            name: parsed.name || '',
            email: parsed.email || '',
          }));
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
      const response = await api.put('/portal/profile', payload);
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
      const response = await api.put('/portal/profile', { password });
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

  const getRoleLabel = (role?: string) => {
    const roles: Record<string, string> = {
      admin: 'Administrador',
      corretor: 'Corretor',
      user: 'Usuario',
      manager: 'Gerente',
      client: 'Cliente',
      super_admin: 'Super Admin',
    };
    if (!role) return 'Usuario';
    return roles[role] || role;
  };

  const isClient = profileUser?.role === 'client';

  return (
    <div className="flex">
      <Sidebar />

      <div className="flex-1 md:ml-80 min-h-screen p-4 md:p-8">
        <motion.div
          variants={containerVariants}
          initial="hidden"
          animate="visible"
          className="max-w-6xl mx-auto"
        >
          <motion.div variants={itemVariants} className="mb-8">
            <h1 className="text-4xl font-bold gradient-text mb-2 flex items-center gap-3">
              <SettingsIcon size={40} />
              Configuracoes
            </h1>
            <p className="text-muted-foreground">Personalize sua experiencia no SOCIMOB</p>
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
