import { useState } from 'react';
import { motion } from 'framer-motion';
import { Settings as SettingsIcon, User, Bell, Lock, CreditCard, LogOut, ChevronRight } from 'lucide-react';
import Sidebar from '@/components/Sidebar';

interface SettingSection {
  id: string;
  title: string;
  description: string;
  icon: React.ReactNode;
}

export default function Settings() {
  const [activeSection, setActiveSection] = useState('profile');

  const sections: SettingSection[] = [
    {
      id: 'profile',
      title: 'Perfil',
      description: 'Gerencie suas informações pessoais',
      icon: <User size={24} />,
    },
    {
      id: 'notifications',
      title: 'Notificações',
      description: 'Configure suas preferências de notificações',
      icon: <Bell size={24} />,
    },
    {
      id: 'security',
      title: 'Segurança',
      description: 'Altere sua senha e configure autenticação',
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
              Configurações
            </h1>
            <p className="text-muted-foreground">Personalize sua experiência no SOCIMOB</p>
          </motion.div>

          <div className="grid grid-cols-1 lg:grid-cols-4 gap-8">
            {/* Settings Menu */}
            <motion.div variants={itemVariants} className="lg:col-span-1">
              <div className="glass-panel p-4 rounded-2xl sticky top-24 space-y-2">
                {sections.map((section) => (
                  <motion.button
                    key={section.id}
                    whileHover={{ x: 4 }}
                    whileTap={{ scale: 0.98 }}
                    onClick={() => setActiveSection(section.id)}
                    className={`w-full flex items-center gap-3 px-4 py-3 rounded-lg transition-all ${
                      activeSection === section.id
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

            {/* Settings Content */}
            <motion.div variants={itemVariants} className="lg:col-span-3">
              {/* Profile Section */}
              {activeSection === 'profile' && (
                <motion.div
                  initial={{ opacity: 0, y: 20 }}
                  animate={{ opacity: 1, y: 0 }}
                  className="space-y-6"
                >
                  <div className="glass-panel p-6 rounded-2xl">
                    <h2 className="text-2xl font-bold text-foreground mb-6">Informações Pessoais</h2>

                    <div className="space-y-6">
                      {/* Avatar */}
                      <div className="flex items-center gap-6">
                        <div className="w-20 h-20 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-3xl font-bold text-white">
                          JD
                        </div>
                        <motion.button
                          whileHover={{ scale: 1.05 }}
                          whileTap={{ scale: 0.95 }}
                          className="px-4 py-2 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 rounded-lg font-semibold text-white transition-all"
                        >
                          Alterar Foto
                        </motion.button>
                      </div>

                      {/* Form Fields */}
                      {[
                        { label: 'Nome Completo', value: 'João da Silva' },
                        { label: 'Email', value: 'joao@example.com' },
                        { label: 'Telefone', value: '(11) 98765-4321' },
                        { label: 'Empresa', value: 'Silva Imóveis' },
                      ].map((field) => (
                        <div key={field.label}>
                          <label className="block text-sm font-semibold text-foreground mb-2">
                            {field.label}
                          </label>
                          <input
                            type="text"
                            defaultValue={field.value}
                            className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                          />
                        </div>
                      ))}

                      <motion.button
                        whileHover={{ scale: 1.05 }}
                        whileTap={{ scale: 0.95 }}
                        className="w-full px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 rounded-lg font-semibold text-white transition-all glow-md hover:glow-lg"
                      >
                        Salvar Alterações
                      </motion.button>
                    </div>
                  </div>
                </motion.div>
              )}

              {/* Notifications Section */}
              {activeSection === 'notifications' && (
                <motion.div
                  initial={{ opacity: 0, y: 20 }}
                  animate={{ opacity: 1, y: 0 }}
                  className="glass-panel p-6 rounded-2xl"
                >
                  <h2 className="text-2xl font-bold text-foreground mb-6">Preferências de Notificações</h2>

                  <div className="space-y-4">
                    {[
                      { title: 'Novos Leads', description: 'Receba notificações quando novos leads chegarem' },
                      { title: 'Mensagens', description: 'Notifique-se sobre novas mensagens' },
                      { title: 'Lembretes de Visitas', description: 'Receba lembretes antes das visitas agendadas' },
                      { title: 'Atualizações de Preço', description: 'Notifique-se sobre mudanças de preço' },
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

              {/* Security Section */}
              {activeSection === 'security' && (
                <motion.div
                  initial={{ opacity: 0, y: 20 }}
                  animate={{ opacity: 1, y: 0 }}
                  className="space-y-6"
                >
                  <div className="glass-panel p-6 rounded-2xl">
                    <h2 className="text-2xl font-bold text-foreground mb-6">Segurança</h2>

                    <div className="space-y-6">
                      {[
                        { label: 'Senha Atual', type: 'password' },
                        { label: 'Nova Senha', type: 'password' },
                        { label: 'Confirmar Nova Senha', type: 'password' },
                      ].map((field) => (
                        <div key={field.label}>
                          <label className="block text-sm font-semibold text-foreground mb-2">
                            {field.label}
                          </label>
                          <input
                            type={field.type}
                            placeholder="••••••••"
                            className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg text-foreground focus:outline-none focus:ring-2 focus:ring-blue-500 transition-all"
                          />
                        </div>
                      ))}

                      <motion.button
                        whileHover={{ scale: 1.05 }}
                        whileTap={{ scale: 0.95 }}
                        className="w-full px-6 py-3 bg-gradient-to-r from-blue-500 to-blue-600 hover:from-blue-600 hover:to-blue-700 rounded-lg font-semibold text-white transition-all glow-md hover:glow-lg"
                      >
                        Alterar Senha
                      </motion.button>
                    </div>
                  </div>
                </motion.div>
              )}

              {/* Billing Section */}
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
                    <p className="text-sm text-muted-foreground mt-2">R$ 99,90/mês</p>
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
