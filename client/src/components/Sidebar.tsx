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

interface SidebarSection {
  id: string;
  icon: React.ReactNode;
  label: string;
  href: string;
  items: SidebarItem[];
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

const isRouteMatch = (currentPath: string, targetPath: string) => {
  if (targetPath === '/') {
    return currentPath === targetPath;
  }

  return currentPath === targetPath || currentPath.startsWith(`${targetPath}/`);
};

const getSectionBadge = (section: SidebarSection) => {
  const total = section.items.reduce((sum, item) => sum + (item.badge || 0), 0);
  return total || undefined;
};

const isTransientNetworkError = (error: unknown) => {
  if (!error || typeof error !== 'object') {
    return false;
  }

  const maybeError = error as { code?: string; message?: string };
  const message = maybeError.message?.toLowerCase() || '';

  return maybeError.code === 'ERR_NETWORK' || message.includes('network changed');
};

const SidebarLink = ({
  item,
  active,
  collapsed,
  onClick,
}: {
  item: SidebarItem;
  active: boolean;
  collapsed: boolean;
  onClick?: () => void;
}) => {
  const content = (
    <div
      onClick={onClick}
      className={`relative flex items-center ${collapsed ? 'justify-center' : 'gap-2.5'} px-3 py-2.5 rounded-xl transition-all duration-200 cursor-pointer ${
        active
          ? 'bg-gradient-to-r from-blue-500/30 to-purple-500/30 text-white shadow-[0_12px_32px_rgba(59,130,246,0.18)]'
          : 'text-muted-foreground hover:bg-white/10'
      }`}
    >
      <div className="shrink-0">{item.icon}</div>
      {!collapsed && <span className="flex-1 font-medium text-sm">{item.label}</span>}
      {!collapsed && item.badge ? (
        <span className="bg-gradient-to-r from-red-500 to-pink-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center">
          {item.badge}
        </span>
      ) : null}
      {collapsed && item.badge ? (
        <span className="absolute -top-1 -right-1 bg-gradient-to-r from-red-500 to-pink-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full min-w-[18px] text-center leading-none">
          {item.badge}
        </span>
      ) : null}
    </div>
  );

  if (!collapsed) {
    return <Link to={item.href}>{content}</Link>;
  }

  return (
    <div className="relative group/tooltip">
      <Link to={item.href}>{content}</Link>
      <div className="absolute left-full top-1/2 ml-3 hidden -translate-y-1/2 group-hover/tooltip:flex items-center bg-black/80 backdrop-blur-sm border border-white/10 rounded-xl px-3 py-2 z-50 shadow-xl whitespace-nowrap">
        <span className="text-sm text-white font-medium">{item.label}</span>
      </div>
    </div>
  );
};

const SectionTabsDock = ({
  section,
  location,
  isCollapsed,
}: {
  section: SidebarSection | null;
  location: string;
  isCollapsed: boolean;
}) => {
  if (!section || section.items.length <= 1) {
    return null;
  }

  const desktopLeft = isCollapsed ? 112 : 312;

  return (
    <>
      <div
        className="hidden md:block fixed top-0 right-0 z-30 border-b border-white/8 bg-[#050814]/96 backdrop-blur-xl shadow-[0_18px_40px_rgba(2,6,23,0.42)]"
        style={{ left: `${desktopLeft}px` }}
      >
        <div className="px-6 pt-4 pb-0">
          <div className="mb-2 flex items-center gap-3 px-1">
            <span className="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-400 whitespace-nowrap">
              {section.label}
            </span>
            <div className="h-px flex-1 bg-white/8" />
          </div>
          <div className="flex items-end gap-2 overflow-x-auto scrollbar-thin scrollbar-thumb-white/10 scrollbar-track-transparent">
            {section.items.map((item) => {
              const isActive = isRouteMatch(location, item.href);

              return (
                <Link key={item.href} to={item.href}>
                  <div
                    className={`relative flex items-center gap-2 whitespace-nowrap rounded-t-[18px] rounded-b-[10px] border border-b-0 px-4 py-3 text-sm transition-all duration-200 ${
                      isActive
                        ? 'border-white/20 bg-white text-slate-950 shadow-[0_12px_32px_rgba(255,255,255,0.18)]'
                        : 'border-white/8 bg-white/[0.04] text-slate-300 hover:bg-white/[0.08] hover:text-white'
                    }`}
                  >
                    <div className="shrink-0">{item.icon}</div>
                    <span className="font-medium">{item.label}</span>
                    {item.badge ? (
                      <span className={`rounded-full px-1.5 py-0.5 text-[10px] font-bold ${isActive ? 'bg-slate-950/10 text-slate-950' : 'bg-white/10 text-white'}`}>
                        {item.badge}
                      </span>
                    ) : null}
                  </div>
                </Link>
              );
            })}
          </div>
        </div>
      </div>

      <div className="md:hidden fixed top-[4.25rem] left-0 right-0 z-30 border-b border-white/8 bg-[#050814]/96 px-4 pb-0 pt-3 backdrop-blur-xl shadow-[0_16px_36px_rgba(2,6,23,0.34)]">
        <div className="mb-2 flex items-center gap-2 px-1">
          <span className="text-[10px] font-semibold uppercase tracking-[0.22em] text-slate-400 whitespace-nowrap">
            {section.label}
          </span>
          <div className="h-px flex-1 bg-white/8" />
        </div>
        <div className="flex items-end gap-2 overflow-x-auto scrollbar-thin scrollbar-thumb-white/10 scrollbar-track-transparent">
            {section.items.map((item) => {
              const isActive = isRouteMatch(location, item.href);

              return (
                <Link key={item.href} to={item.href}>
                  <div
                    className={`flex items-center gap-2 whitespace-nowrap rounded-t-[16px] rounded-b-[10px] border border-b-0 px-3 py-2.5 text-sm transition-all duration-200 ${
                      isActive
                        ? 'border-white/20 bg-white text-slate-950'
                        : 'border-white/8 bg-white/[0.04] text-slate-300 hover:bg-white/[0.08] hover:text-white'
                    }`}
                  >
                    <div className="shrink-0">{item.icon}</div>
                    <span className="font-medium">{item.label}</span>
                  </div>
                </Link>
              );
            })}
        </div>
      </div>
    </>
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
            Authorization: `Bearer ${localStorage.getItem('token')}`,
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
      if (typeof navigator !== 'undefined' && !navigator.onLine) {
        return;
      }

      if (typeof document !== 'undefined' && document.visibilityState !== 'visible') {
        return;
      }

      try {
        const [notifResult, leadsResult, messagesResult] = await Promise.allSettled([
          api.get('/notifications/unread-count'),
          api.get('/leads/stats'),
          api.get('/admin/conversas/fila/estatisticas'),
        ]);

        if (notifResult.status === 'fulfilled' && notifResult.value.data?.unread_count !== undefined) {
          const notifResponse = notifResult.value;
          setNotificationCount(notifResponse.data.unread_count);
        }

        if (leadsResult.status === 'fulfilled' && leadsResult.value.data?.success && leadsResult.value.data?.data?.novos !== undefined) {
          const leadsResponse = leadsResult.value;
          setLeadsCount(leadsResponse.data.data.novos);
        }

        if (messagesResult.status === 'fulfilled' && messagesResult.value.data?.success && messagesResult.value.data?.data?.aguardando !== undefined) {
          const messagesResponse = messagesResult.value;
          setUnreadMessagesCount(messagesResponse.data.data.aguardando);
        }
      } catch (error) {
        if (!isTransientNetworkError(error)) {
          // Silently handle non-transient errors to avoid noisy polling failures.
        }
      }
    };

    loadBadgeCounts();
    const interval = setInterval(loadBadgeCounts, 30000);

    const handleOnline = () => {
      void loadBadgeCounts();
    };

    const handleVisibilityChange = () => {
      if (document.visibilityState === 'visible') {
        void loadBadgeCounts();
      }
    };

    window.addEventListener('online', handleOnline);
    document.addEventListener('visibilitychange', handleVisibilityChange);

    return () => {
      clearInterval(interval);
      window.removeEventListener('online', handleOnline);
      document.removeEventListener('visibilitychange', handleVisibilityChange);
    };
  }, []);

  const sections: SidebarSection[] = [
    {
      id: 'principal',
      icon: <BarChart3 size={18} />,
      label: 'Principal',
      href: '/dashboard',
      items: [
        { icon: <BarChart3 size={16} />, label: 'Dashboard', href: '/dashboard' },
        { icon: <Bell size={16} />, label: 'Notificações', href: '/notifications', badge: notificationCount || undefined },
        { icon: <CalendarClock size={16} />, label: 'Agenda', href: '/agenda' },
      ],
    },
    {
      id: 'crm',
      icon: <Users size={18} />,
      label: 'CRM & Clientes',
      href: '/crm',
      items: [
        {
          icon: <Users size={16} />,
          label: 'CRM',
          href: '/crm',
          badge: (leadsCount || 0) + (unreadMessagesCount || 0) || undefined,
        },
        { icon: <UserRound size={16} />, label: 'Pessoas', href: '/pessoas' },
        { icon: <Zap size={16} />, label: 'Marketing / Anúncios', href: '/ads' },
      ],
    },
    {
      id: 'imoveis',
      icon: <Home size={18} />,
      label: 'Imóveis',
      href: '/properties',
      items: [
        { icon: <Home size={16} />, label: 'Imóveis', href: '/properties' },
        { icon: <KeyRound size={16} />, label: 'Controle de Chaves', href: '/controle-chaves' },
        { icon: <Building2 size={16} />, label: 'ImobiBrasil', href: '/imobi-brasil' },
      ],
    },
    {
      id: 'operacional',
      icon: <Briefcase size={18} />,
      label: 'Operacional',
      href: '/vistorias',
      items: [
        { icon: <ClipboardCheck size={16} />, label: 'Vistorias', href: '/vistorias' },
        { icon: <FileSignature size={16} />, label: 'Assinaturas', href: '/assinaturas' },
        { icon: <FileSpreadsheet size={16} />, label: 'Locação / Operação', href: '/financeiro/locacao' },
        { icon: <FileText size={16} />, label: 'Templates de Contrato', href: '/contrato-templates' },
      ],
    },
    {
      id: 'financeiro',
      icon: <DollarSign size={18} />,
      label: 'Financeiro',
      href: '/financeiro',
      items: [
        { icon: <Wallet size={16} />, label: 'Financeiro', href: '/financeiro' },
        { icon: <BookOpen size={16} />, label: 'Contas a Pagar/Receber', href: '/financeiro/contas' },
      ],
    },
    ...((user?.role === 'admin' || user?.role === 'super_admin')
      ? [
          {
            id: 'admin',
            icon: <Shield size={18} />,
            label: 'Administração',
            href: '/analytics',
            items: [
              { icon: <LineChart size={16} />, label: 'Estatísticas', href: '/analytics' },
              { icon: <Shield size={16} />, label: 'Usuários', href: '/admin/users' },
              { icon: <Image size={16} />, label: 'Propaganda', href: '/admin/property-ads' },
              { icon: <FileText size={16} />, label: 'Logs do Sistema', href: '/system-logs' },
            ],
          } as SidebarSection,
        ]
      : []),
    ...(user?.role === 'super_admin'
      ? [
          {
            id: 'superadmin',
            icon: <Star size={18} />,
            label: 'Super Admin',
            href: '/tenants',
            items: [
              { icon: <Building2 size={16} />, label: 'Tenants', href: '/tenants' },
              { icon: <Link2 size={16} />, label: 'Assoc. Tenants', href: '/tenants/associacoes' },
            ],
          } as SidebarSection,
        ]
      : []),
  ];

  const currentSection =
    sections.find(
      (section) => isRouteMatch(location, section.href) || section.items.some((item) => isRouteMatch(location, item.href))
    ) || null;

  useEffect(() => {
    if (currentSection && currentSection.items.length > 1) {
      document.body.dataset.sectionTabs = 'active';
      return () => {
        delete document.body.dataset.sectionTabs;
      };
    }

    delete document.body.dataset.sectionTabs;
    return undefined;
  }, [currentSection]);

  const settingsItem: SidebarItem = { icon: <Settings size={17} />, label: 'Configurações', href: '/settings' };
  const settingsActive = isRouteMatch(location, settingsItem.href);

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

  return (
    <>
      <SectionTabsDock section={currentSection} location={location} isCollapsed={isCollapsed} />

      {!onClose && (
        <button
          onClick={() => setInternalIsOpen(!internalIsOpen)}
          className="md:hidden fixed top-4 left-4 z-50 glass-panel p-3 rounded-xl hover:bg-white/20 transition-all"
          aria-label="Toggle menu"
        >
          {internalIsOpen ? <X size={24} /> : <Menu size={24} />}
        </button>
      )}

      <motion.div
        animate={isCollapsed ? 'collapsed' : 'expanded'}
        variants={sidebarVariants}
        className="hidden md:flex fixed left-0 top-0 h-screen glass-panel m-4 rounded-3xl flex-col justify-between py-5 z-40 overflow-hidden"
      >
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

        <nav className="flex-1 flex flex-col gap-1 px-2 overflow-y-auto scrollbar-thin scrollbar-thumb-white/10 scrollbar-track-transparent py-1">
          {sections.map((section) => {
            const badge = getSectionBadge(section);
            const active = currentSection?.id === section.id;

            return (
              <SidebarLink
                key={section.id}
                item={{ icon: section.icon, label: section.label, href: section.href, badge }}
                active={active}
                collapsed={isCollapsed}
              />
            );
          })}

          <div className="mt-2 pt-2 border-t border-white/10">
            <SidebarLink item={settingsItem} active={settingsActive} collapsed={isCollapsed} />
          </div>
        </nav>

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

              <nav className="flex-1 flex flex-col gap-1 overflow-y-auto scrollbar-thin scrollbar-thumb-white/10 scrollbar-track-transparent">
                {sections.map((section) => {
                  const badge = getSectionBadge(section);
                  const active = currentSection?.id === section.id;

                  return (
                    <SidebarLink
                      key={section.id}
                      item={{ icon: section.icon, label: section.label, href: section.href, badge }}
                      active={active}
                      collapsed={false}
                      onClick={handleClose}
                    />
                  );
                })}

                <div className="mt-2 pt-2 border-t border-white/10">
                  <SidebarLink
                    item={settingsItem}
                    active={settingsActive}
                    collapsed={false}
                    onClick={handleClose}
                  />
                </div>
              </nav>

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
