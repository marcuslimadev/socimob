import { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { useLocation, Link } from 'wouter';
import {
  BarChart3,
  Users,
  Home,
  MessageSquare,
  Settings,
  LogOut,
  Menu,
  X,
  Bell,
  ChevronRight,
  CalendarClock,
  Wallet,
  Building2,
  ClipboardCheck,
  FileSignature,
  UserRound,
  FileText,
  Shield,
  Image,
  Sun,
  Moon,
  LineChart,
} from 'lucide-react';
import { api } from '@/lib/api';
import { useTheme } from '@/contexts/ThemeContext';
import TenantSelector from './TenantSelector';

interface SidebarProps {
  isOpen?: boolean;
  onClose?: () => void;
}

interface SidebarItem {
  icon: React.ReactNode;
  label: string;
  href: string;
  badge?: number;
  isActive?: boolean;
}

interface TenantConfig {
  name: string;
  logo?: string;
  logo_url?: string;
  favicon_url?: string;
  slogan?: string;
  primary_color?: string;
}

interface SidebarProps {
  isOpen?: boolean;
  onClose?: () => void;
}

interface UserData {
  id: number;
  name: string;
  email: string;
  role: string;
  avatar?: string;
}

const Sidebar = ({ isOpen = false, onClose }: SidebarProps) => {
  const [location] = useLocation();
  const [isCollapsed, setIsCollapsed] = useState(false);
  const [internalIsOpen, setInternalIsOpen] = useState(false);
  const [tenant, setTenant] = useState<TenantConfig | null>(null);
  const [user, setUser] = useState<UserData | null>(null);
  const [notificationCount, setNotificationCount] = useState(0);
  const [leadsCount, setLeadsCount] = useState(0);
  const [unreadMessagesCount, setUnreadMessagesCount] = useState(0);
  const { theme, toggleTheme } = useTheme();

  // Use internal state if not controlled externally
  const actualIsOpen = onClose ? isOpen : internalIsOpen;
  const handleClose = onClose || (() => setInternalIsOpen(false));

  // Carregar dados do tenant e usuário
  useEffect(() => {
    const loadTenantAndUser = async () => {
      try {
        // Carregar dados do usuário do localStorage
        const storedUser = localStorage.getItem('user');
        if (storedUser) {
          setUser(JSON.parse(storedUser));
        }

        // Carregar configuração do tenant da API
        const { default: axios } = await import('axios');
        const response = await axios.get('/api/portal/config', {
          headers: {
            'Authorization': `Bearer ${localStorage.getItem('token')}`,
            'X-Tenant-Domain': window.location.hostname,
          },
        });
        if (response.data.success && response.data.data) {
          const tenantData = response.data.data as TenantConfig;
          setTenant({
            ...tenantData,
            logo_url: tenantData.logo_url || tenantData.logo,
          });

          if (typeof document !== 'undefined') {
            if (tenantData.name) {
              document.title = tenantData.name;
            }

            const faviconUrl = tenantData.favicon_url || tenantData.logo_url || tenantData.logo;
            if (faviconUrl) {
              let faviconLink = document.querySelector("link[rel~='icon']") as HTMLLinkElement | null;
              if (!faviconLink) {
                faviconLink = document.createElement('link');
                faviconLink.rel = 'icon';
                document.head.appendChild(faviconLink);
              }
              faviconLink.href = faviconUrl;
            }
          }
        }
      } catch (error) {
        // Silently handle error
      }
    };

    loadTenantAndUser();
  }, []);

  // Carregar contadores de badges dinamicamente
  useEffect(() => {
    const loadBadgeCounts = async () => {
      try {
        // Carregar contagem de notificações não lidas
        const notifResponse = await api.get('/notifications/unread-count');
        if (notifResponse.data?.unread_count !== undefined) {
          setNotificationCount(notifResponse.data.unread_count);
        }

        // Carregar contagem de leads novos
        const leadsResponse = await api.get('/leads/stats');
        if (leadsResponse.data?.success && leadsResponse.data?.data?.novos !== undefined) {
          setLeadsCount(leadsResponse.data.data.novos);
        }

        // Carregar contagem de mensagens não lidas
        const messagesResponse = await api.get('/admin/conversas/fila/estatisticas');
        if (messagesResponse.data?.success && messagesResponse.data?.data?.aguardando !== undefined) {
          setUnreadMessagesCount(messagesResponse.data.data.aguardando);
        }
      } catch (error) {
        // Silently handle error - badges will show 0
      }
    };

    loadBadgeCounts();

    // Atualizar contadores a cada 30 segundos
    const interval = setInterval(loadBadgeCounts, 30000);
    return () => clearInterval(interval);
  }, []);

  const menuItems: SidebarItem[] = [
    { icon: <BarChart3 size={20} />, label: 'Dashboard', href: '/dashboard' },
    { icon: <Users size={20} />, label: 'CRM', href: '/crm', badge: (leadsCount || 0) + (unreadMessagesCount || 0) || undefined },
    { icon: <Home size={20} />, label: 'Imóveis', href: '/properties' },
    { icon: <ClipboardCheck size={20} />, label: 'Vistorias', href: '/vistorias' },
    { icon: <FileSignature size={20} />, label: 'Assinaturas', href: '/assinaturas' },
    { icon: <Bell size={20} />, label: 'Notificações', href: '/notifications', badge: notificationCount || undefined },
    { icon: <CalendarClock size={20} />, label: 'Agenda', href: '/agenda' },
    { icon: <Wallet size={20} />, label: 'Financeiro', href: '/financeiro' },
  ];

  // Menu adicional para admin
  const adminMenuItems: SidebarItem[] = user?.role === 'admin' || user?.role === 'super_admin' ? [
    { icon: <LineChart size={20} />, label: 'Estatísticas', href: '/analytics' },
    { icon: <Shield size={20} />, label: 'Usuários', href: '/admin/users' },
    { icon: <Image size={20} />, label: 'Propaganda', href: '/admin/property-ads' },
    { icon: <FileText size={20} />, label: 'Logs do Sistema', href: '/system-logs' },
  ] : [];

  // Menu exclusivo para super_admin (fica ANTES de Configurações)
  const superAdminMenuItems: SidebarItem[] = user?.role === 'super_admin' ? [
    { icon: <Building2 size={20} />, label: 'Tenants', href: '/tenants' },
  ] : [];
  
  // Configurações fica por último
  const settingsItem: SidebarItem = { icon: <Settings size={20} />, label: 'Configurações', href: '/settings' };

  const sidebarVariants = {
    expanded: { width: 280 },
    collapsed: { width: 80 },
  };

  const itemVariants = {
    hidden: { opacity: 0, x: -20 },
    visible: { opacity: 1, x: 0 },
  };

  const getInitials = (name: string) => {
    if (!name) return '?';
    const parts = name.trim().split(' ');
    if (parts.length >= 2) {
      return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
    }
    return name.slice(0, 2).toUpperCase();
  };

  const getRoleLabel = (role: string) => {
    const roles: Record<string, string> = {
      admin: 'Administrador',
      corretor: 'Corretor',
      user: 'Usuário',
      manager: 'Gerente',
    };
    return roles[role] || role;
  };

  const handleLogout = () => {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    window.location.href = '/login';
  };

  return (
    <>
      {/* Mobile Menu Button - Only show if not controlled externally */}
      {!onClose && (
        <button
          onClick={() => setInternalIsOpen(!internalIsOpen)}
          className="md:hidden fixed top-4 left-4 z-50 glass-panel p-3 rounded-xl hover:bg-white/20 transition-all"
          aria-label="Toggle menu"
        >
          {internalIsOpen ? <X size={24} /> : <Menu size={24} />}
        </button>
      )}

      {/* Desktop Sidebar */}
      <motion.div
        animate={isCollapsed ? 'collapsed' : 'expanded'}
        variants={sidebarVariants}
        className="hidden md:flex fixed left-0 top-0 h-screen glass-panel m-4 rounded-3xl flex-col justify-between py-6 z-40 overflow-hidden"
      >
        {/* Header com Logo e Tenant Info */}
        <div className="flex flex-col items-center gap-4 px-4">
          <motion.div
            initial={{ scale: 0, rotate: -180 }}
            animate={{ scale: 1, rotate: 0 }}
            transition={{ type: 'spring', stiffness: 200 }}
            className="relative"
          >
            {tenant?.logo_url || tenant?.logo ? (
              <img
                src={tenant.logo_url || tenant.logo}
                alt={tenant.name}
                className="w-16 h-16 rounded-xl object-contain bg-white/5 p-2"
              />
            ) : (
              <div className="w-16 h-16 rounded-xl bg-blue-600 flex items-center justify-center text-white font-bold text-2xl">
                <Building2 size={32} />
              </div>
            )}
          </motion.div>

          {!isCollapsed && (
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              transition={{ delay: 0.2 }}
              className="text-center"
            >
              <h1 className="font-bold text-base text-foreground line-clamp-1">
                {tenant?.name || 'SOCIMOB'}
              </h1>
              {tenant?.slogan && (
                <p className="text-xs text-muted-foreground line-clamp-1 mt-1">
                  {tenant.slogan}
                </p>
              )}
            </motion.div>
          )}

          {/* User Info */}
          {user && !isCollapsed && (
            <motion.div
              initial={{ opacity: 0, y: -10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.3 }}
              className="w-full p-3 rounded-xl bg-white/5 border border-white/10"
            >
              <div className="flex items-center gap-3">
                {user.avatar ? (
                  <img
                    src={user.avatar}
                    alt={user.name}
                    className="w-10 h-10 rounded-full object-cover"
                  />
                ) : (
                  <div className="w-10 h-10 rounded-full bg-gradient-to-br from-blue-400 to-purple-500 flex items-center justify-center text-white text-sm font-bold">
                    {getInitials(user.name)}
                  </div>
                )}
                <div className="flex-1 min-w-0">
                  <p className="text-sm font-semibold text-foreground truncate">
                    {user.name}
                  </p>
                  <p className="text-xs text-muted-foreground truncate">
                    {getRoleLabel(user.role)}
                  </p>
                </div>
              </div>
            </motion.div>
          )}

          {/* Tenant Selector for Super Admin */}
          {user?.role === 'super_admin' && !isCollapsed && (
            <motion.div
              initial={{ opacity: 0, y: -10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.4 }}
              className="w-full"
            >
              <TenantSelector isSuperAdmin={true} />
            </motion.div>
          )}

          <div className="blur-divider w-full" />
        </div>

        {/* Navigation */}
        <nav className="flex-1 flex flex-col gap-1 px-2 overflow-y-auto scrollbar-thin scrollbar-thumb-white/10 scrollbar-track-transparent">
          {[...menuItems, ...adminMenuItems, ...superAdminMenuItems, settingsItem].map((item, index) => {
            const isActive = location === item.href || (item.href !== '/dashboard' && location.startsWith(item.href));

            return (
              <Link key={item.label} to={item.href}>
                <motion.div
                  initial="hidden"
                  animate="visible"
                  transition={{ delay: index * 0.05 }}
                  variants={itemVariants}
                  whileHover={{ x: 4 }}
                  className={`relative group flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all duration-300 cursor-pointer ${
                    isActive
                      ? 'bg-gradient-to-r from-blue-500/30 to-purple-500/30 text-white'
                      : 'text-muted-foreground hover:bg-white/10'
                  }`}
                >
                  {isActive && (
                    <motion.div
                      layoutId="activeIndicator"
                      className="absolute left-0 top-0 bottom-0 w-1 bg-blue-600 rounded-r-lg"
                      transition={{ type: 'spring', stiffness: 200 }}
                    />
                  )}

                  <div>
                    {item.icon}
                  </div>

                  {!isCollapsed && (
                    <div className="flex-1 flex items-center justify-between">
                      <span className="font-medium text-sm">{item.label}</span>
                      {item.badge && (
                        <motion.span
                          initial={{ scale: 0 }}
                          animate={{ scale: 1 }}
                          className="bg-gradient-to-r from-red-500 to-pink-500 text-white text-xs font-bold px-2 py-0.5 rounded-full min-w-[20px] text-center"
                        >
                          {item.badge}
                        </motion.span>
                      )}
                    </div>
                  )}

                  {isCollapsed && item.badge && (
                    <div className="absolute -top-1 -right-1 bg-gradient-to-r from-red-500 to-pink-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center">
                      {item.badge}
                    </div>
                  )}
                </motion.div>
              </Link>
            );
          })}
        </nav>

        {/* Footer Actions */}
        <div className="flex flex-col gap-2 px-2">
          <div className="blur-divider w-full" />

          {/* Version Badge */}
          {!isCollapsed && (
            <motion.div
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.5 }}
              className="px-3 py-2 rounded-xl bg-white/5 border border-white/10"
            >
              <div className="flex items-center gap-2">
                <div className="w-2 h-2 rounded-full bg-green-500 animate-pulse" />
                <div className="flex-1">
                  <p className="text-xs font-semibold text-foreground">SOCIMOB v1.0.0</p>
                  <p className="text-[10px] text-muted-foreground">Build {new Date().toISOString().slice(0, 16).replace(/[-:T]/g, '')}</p>
                </div>
              </div>
            </motion.div>
          )}

          {isCollapsed && (
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              className="flex justify-center py-2"
            >
              <div className="w-2 h-2 rounded-full bg-green-500 animate-pulse" />
            </motion.div>
          )}

          <motion.button
            onClick={() => setIsCollapsed(!isCollapsed)}
            className="flex items-center gap-3 px-3 py-2.5 rounded-xl text-muted-foreground hover:bg-white/10 transition-all duration-300"
            whileHover={{ scale: 1.02 }}
            whileTap={{ scale: 0.98 }}
          >
            <Menu size={20} />
            {!isCollapsed && <span className="text-sm font-medium">Recolher</span>}
          </motion.button>

          <motion.button
            onClick={toggleTheme}
            className="flex items-center gap-3 px-3 py-2.5 rounded-xl text-muted-foreground hover:bg-white/10 transition-all duration-300"
            whileHover={{ scale: 1.02 }}
            whileTap={{ scale: 0.98 }}
          >
            {theme === 'dark' ? <Moon size={20} /> : <Sun size={20} />}
            {!isCollapsed && <span className="text-sm font-medium">Tema {theme === 'dark' ? 'Claro' : 'Escuro'}</span>}
          </motion.button>

          <motion.button
            onClick={handleLogout}
            className="flex items-center gap-3 px-3 py-2.5 rounded-xl text-destructive hover:bg-destructive/10 transition-all duration-300"
            whileHover={{ scale: 1.02 }}
            whileTap={{ scale: 0.98 }}
          >
            <LogOut size={20} />
            {!isCollapsed && <span className="text-sm font-medium">Sair</span>}
          </motion.button>
        </div>
      </motion.div>

      {/* Mobile Sidebar */}
      <AnimatePresence>
        {actualIsOpen && (
          <>
            {/* Backdrop */}
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              onClick={handleClose}
              className="md:hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-30"
            />

            {/* Sidebar Panel */}
            <motion.div
              initial={{ x: -300 }}
              animate={{ x: 0 }}
              exit={{ x: -300 }}
              transition={{ type: 'spring', damping: 25 }}
              className={`md:hidden fixed left-0 ${onClose ? 'top-16' : 'top-0'} bottom-0 w-72 glass-panel z-40 flex flex-col py-6 px-4`}
            >
              {/* Tenant Header - Only show if not controlled externally */}
              {!onClose && tenant && (
                <div className="flex flex-col gap-4 mb-6 pb-4 border-b border-white/10">
                  <div className="flex items-center gap-3">
                    {tenant?.logo_url || tenant?.logo ? (
                      <img
                        src={tenant.logo_url || tenant.logo}
                        alt={tenant.name}
                        className="w-12 h-12 rounded-lg object-contain bg-white/5 p-1"
                      />
                    ) : (
                      <div className="w-12 h-12 rounded-lg bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-bold">
                        <Building2 size={24} />
                      </div>
                    )}
                    <div className="flex-1 min-w-0">
                      <h1 className="font-bold text-foreground text-base truncate">
                        {tenant?.name || 'SOCIMOB'}
                      </h1>
                      {tenant?.slogan && (
                        <p className="text-xs text-muted-foreground truncate">
                          {tenant.slogan}
                        </p>
                      )}
                    </div>
                  </div>
                </div>
              )}

              {/* User Info */}
              <div className="flex flex-col gap-4 mb-6">
                <div className="flex items-center gap-3">
                  <div className="w-12 h-12 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-bold text-lg">
                    {user ? getInitials(user.name) : '?'}
                  </div>
                  <div className="flex-1 min-w-0">
                    <h2 className="font-bold text-foreground text-sm truncate">
                      {user?.name || 'Usuário'}
                    </h2>
                    <p className="text-xs text-muted-foreground truncate">
                      {user ? getRoleLabel(user.role) : 'Carregando...'}
                    </p>
                  </div>
                </div>
              </div>

              {/* Navigation */}
              <nav className="flex-1 flex flex-col gap-1 overflow-y-auto scrollbar-thin scrollbar-thumb-white/10 scrollbar-track-transparent">
                {[...menuItems, ...adminMenuItems, ...superAdminMenuItems, settingsItem].map((item) => {
                  const isActive = location === item.href || (item.href !== '/dashboard' && location.startsWith(item.href));

                  return (
                    <Link key={item.label} to={item.href}>
                      <div
                        onClick={handleClose}
                        className={`flex items-center gap-3 px-4 py-3 rounded-xl transition-all cursor-pointer ${
                          isActive
                            ? 'bg-gradient-to-r from-blue-500/30 to-purple-500/30 text-white'
                            : 'text-muted-foreground hover:bg-white/10'
                        }`}
                      >
                        {item.icon}
                        <span className="font-medium text-sm flex-1">{item.label}</span>
                        {item.badge && (
                          <span className="bg-gradient-to-r from-red-500 to-pink-500 text-white text-xs font-bold px-2 py-0.5 rounded-full min-w-[20px] text-center">
                            {item.badge}
                          </span>
                        )}
                      </div>
                    </Link>
                  );
                })}
              </nav>

              {/* Version Badge Mobile */}
              <div className="px-4 py-2 rounded-xl bg-white/5 border border-white/10 mt-4">
                <div className="flex items-center gap-2">
                  <div className="w-2 h-2 rounded-full bg-green-500 animate-pulse" />
                  <div className="flex-1">
                    <p className="text-xs font-semibold text-foreground">SOCIMOB v1.0.0</p>
                    <p className="text-[10px] text-muted-foreground">Build {new Date().toISOString().slice(0, 16).replace(/[-:T]/g, '')}</p>
                  </div>
                </div>
              </div>

              {/* Logout Button */}
              <button
                onClick={handleLogout}
                className="flex items-center gap-3 px-4 py-3 rounded-xl text-destructive hover:bg-destructive/10 w-full mt-2"
              >
                <LogOut size={20} />
                <span className="font-medium text-sm">Sair</span>
              </button>
            </motion.div>
          </>
        )}
      </AnimatePresence>
    </>
  );
};

export default Sidebar;
