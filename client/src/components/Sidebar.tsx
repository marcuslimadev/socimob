import { useState, useEffect } from 'react';
import { motion, AnimatePresence } from 'framer-motion';
import { useLocation, Link } from 'wouter';
import {
  BarChart3,
  Users,
  Home,
  Settings,
  LogOut,
  Menu,
  X,
  Bell,
  CalendarClock,
  Wallet,
  Building2,
  ClipboardCheck,
  FileSignature,
  UserRound,
  FileText,
  FileSpreadsheet,
  Shield,
  Image,
  Sun,
  Moon,
  LineChart,
  KeyRound,
  Link2,
  Zap,
  ChevronDown,
  ChevronRight,
  Layers,
  Briefcase,
  DollarSign,
  Star,
  BookOpen,
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
}

interface SidebarGroup {
  id: string;
  icon: React.ReactNode;
  label: string;
  items: SidebarItem[];
  defaultOpen?: boolean;
}

interface TenantConfig {
  name: string;
  logo?: string;
  logo_url?: string;
  favicon_url?: string;
  slogan?: string;
  primary_color?: string;
}

interface UserData {
  id: number;
  name: string;
  email: string;
  role: string;
  avatar?: string;
}

// Componente de grupo expansível
const NavGroup = ({
  group,
  location,
  isCollapsed,
  onItemClick,
}: {
  group: SidebarGroup;
  location: string;
  isCollapsed: boolean;
  onItemClick?: () => void;
}) => {
  const hasActiveItem = group.items.some(
    (item) => location === item.href || (item.href !== '/dashboard' && location.startsWith(item.href))
  );
  const [isOpen, setIsOpen] = useState(group.defaultOpen || hasActiveItem);
  const totalBadge = group.items.reduce((sum, item) => sum + (item.badge || 0), 0);

  // Quando sidebar colapsa, fecha os grupos
  useEffect(() => {
    if (isCollapsed) setIsOpen(false);
  }, [isCollapsed]);

  if (isCollapsed) {
    // No modo colapsado: mostra apenas o ícone do grupo sem expandir
    return (
      <div className="relative group/tooltip">
        <div
          className={`relative flex items-center justify-center p-2.5 rounded-xl transition-all duration-200 cursor-pointer ${
            hasActiveItem
              ? 'bg-gradient-to-r from-blue-500/30 to-purple-500/30 text-white'
              : 'text-muted-foreground hover:bg-white/10'
          }`}
        >
          {group.icon}
          {totalBadge > 0 && (
            <div className="absolute -top-1 -right-1 bg-gradient-to-r from-red-500 to-pink-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center leading-none">
              {totalBadge}
            </div>
          )}
        </div>

        {/* Tooltip com subitens */}
        <div className="absolute left-full top-0 ml-3 hidden group-hover/tooltip:flex flex-col gap-1 bg-black/80 backdrop-blur-sm border border-white/10 rounded-xl p-2 z-50 min-w-[180px] shadow-xl">
          <p className="text-xs font-semibold text-muted-foreground px-2 pb-1 border-b border-white/10 mb-1">
            {group.label}
          </p>
          {group.items.map((item) => {
            const isActive = location === item.href || (item.href !== '/dashboard' && location.startsWith(item.href));
            return (
              <Link key={item.label} to={item.href}>
                <div
                  onClick={onItemClick}
                  className={`flex items-center gap-2 px-2 py-1.5 rounded-lg transition-all cursor-pointer text-sm ${
                    isActive ? 'bg-blue-500/30 text-white' : 'text-muted-foreground hover:bg-white/10'
                  }`}
                >
                  <div className="shrink-0">{item.icon}</div>
                  <span className="font-medium">{item.label}</span>
                  {item.badge ? (
                    <span className="ml-auto bg-gradient-to-r from-red-500 to-pink-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center">
                      {item.badge}
                    </span>
                  ) : null}
                </div>
              </Link>
            );
          })}
        </div>
      </div>
    );
  }

  return (
    <div>
      {/* Cabeçalho do grupo */}
      <button
        onClick={() => setIsOpen(!isOpen)}
        className={`w-full flex items-center gap-2 px-3 py-2 rounded-xl transition-all duration-200 cursor-pointer ${
          hasActiveItem && !isOpen
            ? 'bg-gradient-to-r from-blue-500/20 to-purple-500/20 text-white'
            : 'text-muted-foreground hover:bg-white/8'
        }`}
      >
        <div className="shrink-0">{group.icon}</div>
        <span className="flex-1 text-left text-xs font-semibold uppercase tracking-wider">
          {group.label}
        </span>
        {totalBadge > 0 && !isOpen && (
          <span className="bg-gradient-to-r from-red-500 to-pink-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center">
            {totalBadge}
          </span>
        )}
        <motion.div
          animate={{ rotate: isOpen ? 90 : 0 }}
          transition={{ duration: 0.2 }}
        >
          <ChevronRight size={14} />
        </motion.div>
      </button>

      {/* Itens do grupo */}
      <AnimatePresence initial={false}>
        {isOpen && (
          <motion.div
            initial={{ height: 0, opacity: 0 }}
            animate={{ height: 'auto', opacity: 1 }}
            exit={{ height: 0, opacity: 0 }}
            transition={{ duration: 0.2, ease: 'easeInOut' }}
            className="overflow-hidden"
          >
            <div className="ml-3 mt-0.5 mb-1 pl-3 border-l border-white/10 flex flex-col gap-0.5">
              {group.items.map((item) => {
                const isActive = location === item.href || (item.href !== '/dashboard' && location.startsWith(item.href));
                return (
                  <Link key={item.label} to={item.href}>
                    <div
                      onClick={onItemClick}
                      className={`relative flex items-center gap-2.5 px-2.5 py-2 rounded-lg transition-all duration-200 cursor-pointer ${
                        isActive
                          ? 'bg-gradient-to-r from-blue-500/30 to-purple-500/30 text-white'
                          : 'text-muted-foreground hover:bg-white/10'
                      }`}
                    >
                      {isActive && (
                        <motion.div
                          layoutId="activeIndicator"
                          className="absolute left-0 top-0 bottom-0 w-0.5 bg-blue-500 rounded-r"
                          transition={{ type: 'spring', stiffness: 200 }}
                        />
                      )}
                      <div className="shrink-0">{item.icon}</div>
                      <span className="flex-1 font-medium text-sm">{item.label}</span>
                      {item.badge ? (
                        <motion.span
                          initial={{ scale: 0 }}
                          animate={{ scale: 1 }}
                          className="bg-gradient-to-r from-red-500 to-pink-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center"
                        >
                          {item.badge}
                        </motion.span>
                      ) : null}
                    </div>
                  </Link>
                );
              })}
            </div>
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
};

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

  useEffect(() => {
    document.body.dataset.sidebar = isCollapsed ? 'collapsed' : 'expanded';
  }, [isCollapsed]);

  const actualIsOpen = onClose ? isOpen : internalIsOpen;
  const handleClose = onClose || (() => setInternalIsOpen(false));

  useEffect(() => {
    const loadTenantAndUser = async () => {
      try {
        const storedUser = localStorage.getItem('user');
        if (storedUser) {
          setUser(JSON.parse(storedUser));
        }

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

  useEffect(() => {
    const loadBadgeCounts = async () => {
      try {
        const notifResponse = await api.get('/notifications/unread-count');
        if (notifResponse.data?.unread_count !== undefined) {
          setNotificationCount(notifResponse.data.unread_count);
        }

        const leadsResponse = await api.get('/leads/stats');
        if (leadsResponse.data?.success && leadsResponse.data?.data?.novos !== undefined) {
          setLeadsCount(leadsResponse.data.data.novos);
        }

        const messagesResponse = await api.get('/admin/conversas/fila/estatisticas');
        if (messagesResponse.data?.success && messagesResponse.data?.data?.aguardando !== undefined) {
          setUnreadMessagesCount(messagesResponse.data.data.aguardando);
        }
      } catch (error) {
        // Silently handle error
      }
    };

    loadBadgeCounts();
    const interval = setInterval(loadBadgeCounts, 30000);
    return () => clearInterval(interval);
  }, []);

  // Grupos de navegação
  const navGroups: SidebarGroup[] = [
    {
      id: 'principal',
      icon: <BarChart3 size={16} />,
      label: 'Principal',
      defaultOpen: true,
      items: [
        { icon: <BarChart3 size={17} />, label: 'Dashboard', href: '/dashboard' },
        { icon: <Bell size={17} />, label: 'Notificações', href: '/notifications', badge: notificationCount || undefined },
        { icon: <CalendarClock size={17} />, label: 'Agenda', href: '/agenda' },
      ],
    },
    {
      id: 'crm',
      icon: <Users size={16} />,
      label: 'CRM & Clientes',
      items: [
        { icon: <Users size={17} />, label: 'CRM', href: '/crm', badge: (leadsCount || 0) + (unreadMessagesCount || 0) || undefined },
        { icon: <UserRound size={17} />, label: 'Pessoas', href: '/pessoas' },
        { icon: <Zap size={17} />, label: 'Marketing / Anúncios', href: '/ads' },
      ],
    },
    {
      id: 'imoveis',
      icon: <Home size={16} />,
      label: 'Imóveis',
      items: [
        { icon: <Home size={17} />, label: 'Imóveis', href: '/properties' },
        { icon: <KeyRound size={17} />, label: 'Controle de Chaves', href: '/controle-chaves' },
        { icon: <Building2 size={17} />, label: 'ImobiBrasil', href: '/imobi-brasil' },
      ],
    },
    {
      id: 'operacional',
      icon: <Briefcase size={16} />,
      label: 'Operacional',
      items: [
        { icon: <ClipboardCheck size={17} />, label: 'Vistorias', href: '/financeiro/locacao' },
        { icon: <FileSignature size={17} />, label: 'Assinaturas', href: '/assinaturas' },
        { icon: <FileSpreadsheet size={17} />, label: 'Locação/Operação', href: '/financeiro/locacao' },
      ],
    },
    {
      id: 'financeiro',
      icon: <DollarSign size={16} />,
      label: 'Financeiro',
      items: [
        { icon: <Wallet size={17} />, label: 'Financeiro', href: '/financeiro' },
        { icon: <BookOpen size={17} />, label: 'Contas a Pagar/Receber', href: '/financeiro/contas' },
      ],
    },
  ];

  // Grupos condicionais para admin
  const adminGroup: SidebarGroup | null =
    user?.role === 'admin' || user?.role === 'super_admin'
      ? {
          id: 'admin',
          icon: <Shield size={16} />,
          label: 'Administração',
          items: [
            { icon: <LineChart size={17} />, label: 'Estatísticas', href: '/analytics' },
            { icon: <Shield size={17} />, label: 'Usuários', href: '/admin/users' },
            { icon: <Image size={17} />, label: 'Propaganda', href: '/admin/property-ads' },
            { icon: <FileText size={17} />, label: 'Logs do Sistema', href: '/system-logs' },
          ],
        }
      : null;

  const superAdminGroup: SidebarGroup | null =
    user?.role === 'super_admin'
      ? {
          id: 'superadmin',
          icon: <Star size={16} />,
          label: 'Super Admin',
          items: [
            { icon: <Building2 size={17} />, label: 'Tenants', href: '/tenants' },
            { icon: <Link2 size={17} />, label: 'Assoc. Tenants', href: '/tenants/associacoes' },
          ],
        }
      : null;

  const allGroups = [
    ...navGroups,
    ...(adminGroup ? [adminGroup] : []),
    ...(superAdminGroup ? [superAdminGroup] : []),
  ];

  const settingsItem: SidebarItem = { icon: <Settings size={17} />, label: 'Configurações', href: '/settings' };

  const sidebarVariants = {
    expanded: { width: 260 },
    collapsed: { width: 68 },
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
      super_admin: 'Super Admin',
    };
    return roles[role] || role;
  };

  const handleLogout = () => {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
    window.location.href = '/login';
  };

  const settingsActive = location === settingsItem.href || location.startsWith(settingsItem.href);

  return (
    <>
      {/* Mobile Menu Button */}
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
        className="hidden md:flex fixed left-0 top-0 h-screen glass-panel m-4 rounded-3xl flex-col justify-between py-5 z-40 overflow-hidden"
      >
        {/* Header */}
        <div className="flex flex-col items-center gap-3 px-3">
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
                className="w-14 h-14 rounded-xl object-contain bg-white/5 p-2"
              />
            ) : (
              <div className="w-14 h-14 rounded-xl bg-blue-600 flex items-center justify-center text-white font-bold text-2xl">
                <Building2 size={28} />
              </div>
            )}
          </motion.div>

          {!isCollapsed && (
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              transition={{ delay: 0.15 }}
              className="text-center w-full"
            >
              <h1 className="font-bold text-sm text-foreground line-clamp-1">
                {tenant?.name || 'SOCIMOB'}
              </h1>
              {tenant?.slogan && (
                <p className="text-[11px] text-muted-foreground line-clamp-1 mt-0.5">
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
              transition={{ delay: 0.2 }}
              className="w-full p-2.5 rounded-xl bg-white/5 border border-white/10"
            >
              <div className="flex items-center gap-2.5">
                {user.avatar ? (
                  <img
                    src={user.avatar}
                    alt={user.name}
                    className="w-8 h-8 rounded-full object-cover shrink-0"
                  />
                ) : (
                  <div className="w-8 h-8 rounded-full bg-gradient-to-br from-blue-400 to-purple-500 flex items-center justify-center text-white text-xs font-bold shrink-0">
                    {getInitials(user.name)}
                  </div>
                )}
                <div className="flex-1 min-w-0">
                  <p className="text-xs font-semibold text-foreground truncate">{user.name}</p>
                  <p className="text-[11px] text-muted-foreground truncate">{getRoleLabel(user.role)}</p>
                </div>
              </div>
            </motion.div>
          )}

          {user?.role === 'super_admin' && !isCollapsed && (
            <motion.div
              initial={{ opacity: 0, y: -10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.25 }}
              className="w-full"
            >
              <TenantSelector isSuperAdmin={true} />
            </motion.div>
          )}

          <div className="blur-divider w-full" />
        </div>

        {/* Navigation Groups */}
        <nav className="flex-1 flex flex-col gap-0.5 px-2 overflow-y-auto scrollbar-thin scrollbar-thumb-white/10 scrollbar-track-transparent py-1">
          {allGroups.map((group) => (
            <NavGroup
              key={group.id}
              group={group}
              location={location}
              isCollapsed={isCollapsed}
            />
          ))}

          {/* Separador + Configurações */}
          <div className="mt-1 pt-1 border-t border-white/10">
            {isCollapsed ? (
              <div className="relative group/tooltip">
                <Link to={settingsItem.href}>
                  <div
                    className={`flex items-center justify-center p-2.5 rounded-xl transition-all duration-200 cursor-pointer ${
                      settingsActive
                        ? 'bg-gradient-to-r from-blue-500/30 to-purple-500/30 text-white'
                        : 'text-muted-foreground hover:bg-white/10'
                    }`}
                  >
                    {settingsItem.icon}
                  </div>
                </Link>
                <div className="absolute left-full top-0 ml-3 hidden group-hover/tooltip:flex items-center bg-black/80 backdrop-blur-sm border border-white/10 rounded-xl px-3 py-2 z-50 shadow-xl whitespace-nowrap">
                  <span className="text-sm text-white font-medium">{settingsItem.label}</span>
                </div>
              </div>
            ) : (
              <Link to={settingsItem.href}>
                <div
                  className={`flex items-center gap-2.5 px-3 py-2 rounded-xl transition-all duration-200 cursor-pointer ${
                    settingsActive
                      ? 'bg-gradient-to-r from-blue-500/30 to-purple-500/30 text-white'
                      : 'text-muted-foreground hover:bg-white/10'
                  }`}
                >
                  {settingsItem.icon}
                  <span className="font-medium text-sm">{settingsItem.label}</span>
                </div>
              </Link>
            )}
          </div>
        </nav>

        {/* Footer */}
        <div className="flex flex-col gap-1.5 px-2">
          <div className="blur-divider w-full" />

          {!isCollapsed ? (
            <motion.div
              initial={{ opacity: 0, y: 10 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ delay: 0.3 }}
              className="px-3 py-2 rounded-xl bg-white/5 border border-white/10"
            >
              <div className="flex items-center gap-2">
                <div className="w-2 h-2 rounded-full bg-green-500 animate-pulse shrink-0" />
                <div className="flex-1 min-w-0">
                  <p className="text-xs font-semibold text-foreground">SOCIMOB v1.0.0</p>
                  <p className="text-[10px] text-muted-foreground">
                    Build {new Date().toISOString().slice(0, 16).replace(/[-:T]/g, '')}
                  </p>
                </div>
              </div>
            </motion.div>
          ) : (
            <div className="flex justify-center py-1">
              <div className="w-2 h-2 rounded-full bg-green-500 animate-pulse" />
            </div>
          )}

          <motion.button
            onClick={() => setIsCollapsed(!isCollapsed)}
            className="flex items-center gap-3 px-3 py-2 rounded-xl text-muted-foreground hover:bg-white/10 transition-all duration-300"
            whileHover={{ scale: 1.02 }}
            whileTap={{ scale: 0.98 }}
          >
            <Menu size={18} />
            {!isCollapsed && <span className="text-sm font-medium">Recolher</span>}
          </motion.button>

          <motion.button
            onClick={toggleTheme}
            className="flex items-center gap-3 px-3 py-2 rounded-xl text-muted-foreground hover:bg-white/10 transition-all duration-300"
            whileHover={{ scale: 1.02 }}
            whileTap={{ scale: 0.98 }}
          >
            {theme === 'dark' ? <Moon size={18} /> : <Sun size={18} />}
            {!isCollapsed && (
              <span className="text-sm font-medium">Tema {theme === 'dark' ? 'Claro' : 'Escuro'}</span>
            )}
          </motion.button>

          <motion.button
            onClick={handleLogout}
            className="flex items-center gap-3 px-3 py-2 rounded-xl text-destructive hover:bg-destructive/10 transition-all duration-300"
            whileHover={{ scale: 1.02 }}
            whileTap={{ scale: 0.98 }}
          >
            <LogOut size={18} />
            {!isCollapsed && <span className="text-sm font-medium">Sair</span>}
          </motion.button>
        </div>
      </motion.div>

      {/* Mobile Sidebar */}
      <AnimatePresence>
        {actualIsOpen && (
          <>
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              onClick={handleClose}
              className="md:hidden fixed inset-0 bg-black/60 backdrop-blur-sm z-30"
            />

            <motion.div
              initial={{ x: -300 }}
              animate={{ x: 0 }}
              exit={{ x: -300 }}
              transition={{ type: 'spring', damping: 25 }}
              className={`md:hidden fixed left-0 ${onClose ? 'top-16' : 'top-0'} bottom-0 w-72 glass-panel z-40 flex flex-col py-6 px-3`}
            >
              {!onClose && tenant && (
                <div className="flex flex-col gap-4 mb-5 pb-4 border-b border-white/10">
                  <div className="flex items-center gap-3">
                    {tenant?.logo_url || tenant?.logo ? (
                      <img
                        src={tenant.logo_url || tenant.logo}
                        alt={tenant.name}
                        className="w-11 h-11 rounded-lg object-contain bg-white/5 p-1"
                      />
                    ) : (
                      <div className="w-11 h-11 rounded-lg bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-bold">
                        <Building2 size={22} />
                      </div>
                    )}
                    <div className="flex-1 min-w-0">
                      <h1 className="font-bold text-foreground text-sm truncate">
                        {tenant?.name || 'SOCIMOB'}
                      </h1>
                      {tenant?.slogan && (
                        <p className="text-xs text-muted-foreground truncate">{tenant.slogan}</p>
                      )}
                    </div>
                  </div>
                </div>
              )}

              {/* User Info Mobile */}
              <div className="flex items-center gap-3 mb-5 px-1">
                <div className="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-bold text-sm shrink-0">
                  {user ? getInitials(user.name) : '?'}
                </div>
                <div className="flex-1 min-w-0">
                  <h2 className="font-bold text-foreground text-sm truncate">{user?.name || 'Usuário'}</h2>
                  <p className="text-xs text-muted-foreground truncate">
                    {user ? getRoleLabel(user.role) : 'Carregando...'}
                  </p>
                </div>
              </div>

              {/* Navigation Mobile - grupos */}
              <nav className="flex-1 flex flex-col gap-0.5 overflow-y-auto scrollbar-thin scrollbar-thumb-white/10 scrollbar-track-transparent">
                {allGroups.map((group) => (
                  <NavGroup
                    key={group.id}
                    group={group}
                    location={location}
                    isCollapsed={false}
                    onItemClick={handleClose}
                  />
                ))}

                <div className="mt-1 pt-1 border-t border-white/10">
                  <Link to={settingsItem.href}>
                    <div
                      onClick={handleClose}
                      className={`flex items-center gap-2.5 px-3 py-2 rounded-xl transition-all duration-200 cursor-pointer ${
                        settingsActive
                          ? 'bg-gradient-to-r from-blue-500/30 to-purple-500/30 text-white'
                          : 'text-muted-foreground hover:bg-white/10'
                      }`}
                    >
                      {settingsItem.icon}
                      <span className="font-medium text-sm">{settingsItem.label}</span>
                    </div>
                  </Link>
                </div>
              </nav>

              {/* Version + Logout Mobile */}
              <div className="px-3 py-2 rounded-xl bg-white/5 border border-white/10 mt-3">
                <div className="flex items-center gap-2">
                  <div className="w-2 h-2 rounded-full bg-green-500 animate-pulse" />
                  <div className="flex-1">
                    <p className="text-xs font-semibold text-foreground">SOCIMOB v1.0.0</p>
                    <p className="text-[10px] text-muted-foreground">
                      Build {new Date().toISOString().slice(0, 16).replace(/[-:T]/g, '')}
                    </p>
                  </div>
                </div>
              </div>

              <button
                onClick={handleLogout}
                className="flex items-center gap-3 px-3 py-2.5 rounded-xl text-destructive hover:bg-destructive/10 w-full mt-2"
              >
                <LogOut size={18} />
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
