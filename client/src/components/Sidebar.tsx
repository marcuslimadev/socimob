import { useEffect, useState } from 'react';
import { motion } from 'framer-motion';
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
        className="hidden md:block fixed top-0 right-0 z-30 h-[106px] border-b border-white/8 bg-[#050814] backdrop-blur-xl shadow-[0_18px_40px_rgba(2,6,23,0.42)]"
        style={{ left: `${desktopLeft}px` }}
      >
        <div className="flex h-full flex-col justify-end px-6 pt-3 pb-0">
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

      <div className="md:hidden fixed top-[4.25rem] left-0 right-0 z-30 h-[92px] border-b border-white/8 bg-[#050814] px-4 pb-0 pt-3 backdrop-blur-xl shadow-[0_16px_36px_rgba(2,6,23,0.34)]">
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
  const [internalIsOpen, setInternalIsOpen] = useState(false);
  const [tenant, setTenant] = useState<TenantConfig | null>(null);
  const [user, setUser] = useState<UserData | null>(null);
  const [notificationCount, setNotificationCount] = useState(0);
  const [leadsCount, setLeadsCount] = useState(0);
  const [unreadMessagesCount, setUnreadMessagesCount] = useState(0);
  const { theme, toggleTheme } = useTheme();

  useEffect(() => {
    document.body.dataset.sidebar = 'topnav';

    return () => {
      delete document.body.dataset.sidebar;
    };
  }, []);

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

  const primaryTabs: SidebarItem[] = [
    ...sections.map((section) => ({
      icon: section.icon,
      label: section.label,
      href: section.href,
      badge: getSectionBadge(section),
    })),
    settingsItem,
  ];

  const secondaryTabs = currentSection?.items || [];

  return (
    <>
      {!onClose && (
        <button
          onClick={() => setInternalIsOpen(!internalIsOpen)}
          className="md:hidden fixed top-4 left-4 z-50 rounded-xl border border-white/10 bg-[#050814]/96 p-3 text-white shadow-[0_12px_28px_rgba(2,6,23,0.35)] backdrop-blur-xl"
          aria-label="Abrir navegação"
        >
          {actualIsOpen ? <LogOut size={0} className="hidden" /> : <Menu size={22} />}
          {actualIsOpen && <X size={22} />}
        </button>
      )}

      <div className="fixed inset-x-0 top-0 z-40 border-b border-white/8 bg-[#050814] shadow-[0_18px_40px_rgba(2,6,23,0.42)] backdrop-blur-xl">
        <div className="mx-auto flex max-w-[1600px] items-center justify-between gap-4 px-4 py-3 md:px-8">
          <div className="flex min-w-0 items-center gap-3">
            {tenant?.logo_url || tenant?.logo ? (
              <img
                src={tenant.logo_url || tenant.logo}
                alt={tenant.name}
                className="h-10 w-10 rounded-xl bg-white/5 object-contain p-1.5"
              />
            ) : (
              <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-white/8 text-white">
                <Building2 size={20} />
              </div>
            )}
            <div className="min-w-0">
              <p className="truncate text-sm font-semibold text-white">{tenant?.name || 'SOCIMOB'}</p>
              <p className="truncate text-[11px] uppercase tracking-[0.18em] text-slate-400">
                {currentSection?.label || 'Navegação'}
              </p>
            </div>
          </div>

          <div className="hidden items-center gap-2 md:flex">
            {user?.role === 'super_admin' && <div className="w-[220px]"><TenantSelector isSuperAdmin={true} /></div>}
            <button
              onClick={toggleTheme}
              className="flex h-10 items-center gap-2 rounded-xl border border-white/10 bg-white/[0.04] px-3 text-sm text-slate-200 hover:bg-white/[0.08]"
            >
              {theme === 'dark' ? <Moon size={16} /> : <Sun size={16} />}
              <span>{theme === 'dark' ? 'Tema claro' : 'Tema escuro'}</span>
            </button>
            <button
              onClick={handleLogout}
              className="flex h-10 items-center gap-2 rounded-xl border border-white/10 bg-white/[0.04] px-3 text-sm text-slate-200 hover:bg-white/[0.08]"
            >
              <LogOut size={16} />
              <span>Sair</span>
            </button>
          </div>
        </div>

        <div className="mx-auto max-w-[1600px] px-4 md:px-8">
          <div className="hidden gap-2 overflow-x-auto pb-0 scrollbar-thin scrollbar-thumb-white/10 scrollbar-track-transparent md:flex">
            {primaryTabs.map((tab) => {
              const isActive = sections.some((section) => section.href === tab.href)
                ? currentSection?.href === tab.href || currentSection?.id === sections.find((section) => section.href === tab.href)?.id
                : settingsActive;

              return (
                <Link key={tab.href} to={tab.href}>
                  <div
                    className={`flex items-center gap-2 whitespace-nowrap rounded-t-[18px] rounded-b-[10px] border border-b-0 px-4 py-3 text-sm transition-colors ${
                      isActive
                        ? 'border-white/18 bg-white text-slate-950 shadow-[0_12px_32px_rgba(255,255,255,0.16)]'
                        : 'border-white/8 bg-white/[0.04] text-slate-300 hover:bg-white/[0.08] hover:text-white'
                    }`}
                  >
                    <div className="shrink-0">{tab.icon}</div>
                    <span className="font-medium">{tab.label}</span>
                    {tab.badge ? (
                      <span className={`rounded-full px-1.5 py-0.5 text-[10px] font-bold ${isActive ? 'bg-slate-950/10 text-slate-950' : 'bg-white/10 text-white'}`}>
                        {tab.badge}
                      </span>
                    ) : null}
                  </div>
                </Link>
              );
            })}
          </div>

          {secondaryTabs.length > 0 && (
            <div className="hidden items-center gap-2 border-t border-white/8 py-3 md:flex">
              {secondaryTabs.map((item) => {
                const isActive = isRouteMatch(location, item.href);

                return (
                  <Link key={item.href} to={item.href}>
                    <div
                      className={`flex items-center gap-2 whitespace-nowrap rounded-xl border px-3 py-2 text-sm transition-colors ${
                        isActive
                          ? 'border-sky-400/30 bg-sky-400/14 text-white'
                          : 'border-white/8 bg-white/[0.03] text-slate-300 hover:bg-white/[0.08] hover:text-white'
                      }`}
                    >
                      <div className="shrink-0">{item.icon}</div>
                      <span className="font-medium">{item.label}</span>
                      {item.badge ? <span className="rounded-full bg-white/10 px-1.5 py-0.5 text-[10px] font-bold text-white">{item.badge}</span> : null}
                    </div>
                  </Link>
                );
              })}
            </div>
          )}

          <div className="md:hidden pb-3">
            {actualIsOpen && (
              <div className="space-y-3 rounded-2xl border border-white/10 bg-white/[0.04] p-3">
                <div className="grid gap-2">
                  {primaryTabs.map((tab) => {
                    const isActive = sections.some((section) => section.href === tab.href)
                      ? currentSection?.href === tab.href || currentSection?.id === sections.find((section) => section.href === tab.href)?.id
                      : settingsActive;

                    return (
                      <Link key={tab.href} to={tab.href}>
                        <div
                          onClick={handleClose}
                          className={`flex items-center gap-2 rounded-xl border px-3 py-2.5 text-sm ${
                            isActive
                              ? 'border-white/18 bg-white text-slate-950'
                              : 'border-white/8 bg-white/[0.04] text-slate-200'
                          }`}
                        >
                          <div className="shrink-0">{tab.icon}</div>
                          <span className="font-medium">{tab.label}</span>
                        </div>
                      </Link>
                    );
                  })}
                </div>

                {secondaryTabs.length > 0 && (
                  <div className="grid gap-2 border-t border-white/8 pt-3">
                    {secondaryTabs.map((item) => {
                      const isActive = isRouteMatch(location, item.href);

                      return (
                        <Link key={item.href} to={item.href}>
                          <div
                            onClick={handleClose}
                            className={`flex items-center gap-2 rounded-xl border px-3 py-2.5 text-sm ${
                              isActive
                                ? 'border-sky-400/30 bg-sky-400/14 text-white'
                                : 'border-white/8 bg-white/[0.03] text-slate-200'
                            }`}
                          >
                            <div className="shrink-0">{item.icon}</div>
                            <span className="font-medium">{item.label}</span>
                          </div>
                        </Link>
                      );
                    })}
                  </div>
                )}
              </div>
            )}
          </div>
        </div>
      </div>
    </>
  );
};

export default Sidebar;
