import { useEffect, useState } from 'react';
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
  ChevronDown,
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

const isPrimaryTabActive = (
  tab: SidebarItem,
  sections: SidebarSection[],
  currentSection: SidebarSection | null,
  settingsActive: boolean,
) => {
  const matchedSection = sections.find((section) => section.href === tab.href);

  if (matchedSection) {
    return currentSection?.href === tab.href || currentSection?.id === matchedSection.id;
  }

  return settingsActive;
};

const isTransientNetworkError = (error: unknown) => {
  if (!error || typeof error !== 'object') {
    return false;
  }

  const maybeError = error as { code?: string; message?: string };
  const message = maybeError.message?.toLowerCase() || '';

  return maybeError.code === 'ERR_NETWORK' || message.includes('network changed');
};

const Sidebar = ({ isOpen = false, onClose }: SidebarProps) => {
  const [location] = useLocation();
  const [internalIsOpen, setInternalIsOpen] = useState(false);
  const [openDesktopMenu, setOpenDesktopMenu] = useState<string | null>(null);
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
    setOpenDesktopMenu(null);
  }, [location]);

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
  const activePrimaryTab = primaryTabs.find((tab) => isPrimaryTabActive(tab, sections, currentSection, settingsActive)) || null;
  const showFixedSubmenu = secondaryTabs.length > 1;

  return (
    <>
      {!onClose && (
        <button
          onClick={() => setInternalIsOpen(!internalIsOpen)}
          className="md:hidden fixed top-3 left-4 z-50 rounded-xl border border-white/10 bg-[#08111f]/96 p-3 text-white shadow-[0_12px_28px_rgba(2,6,23,0.35)] backdrop-blur-xl"
          aria-label="Abrir navegação"
        >
          {actualIsOpen ? <LogOut size={0} className="hidden" /> : <Menu size={22} />}
          {actualIsOpen && <X size={22} />}
        </button>
      )}

      <div className="fixed inset-x-0 top-0 z-40 border-b border-white/8 bg-[rgba(6,12,22,0.92)] shadow-[0_18px_40px_rgba(2,6,23,0.38)] backdrop-blur-xl">
        <div className="mx-auto flex max-w-[1600px] items-center justify-between gap-6 px-4 py-4 md:px-8 md:py-4">
          <div className="flex min-w-0 items-center gap-3 md:gap-4">
            {tenant?.logo_url || tenant?.logo ? (
              <img
                src={tenant.logo_url || tenant.logo}
                alt={tenant.name}
                className="h-10 w-10 rounded-xl border border-white/10 bg-white/5 object-contain p-1.5"
              />
            ) : (
              <div className="flex h-10 w-10 items-center justify-center rounded-xl border border-white/10 bg-white/5 text-white">
                <Building2 size={19} />
              </div>
            )}
            <div className="min-w-0">
              <p className="truncate font-serif text-[1.05rem] font-semibold leading-tight tracking-[0.01em] text-white">{tenant?.name || 'SOCIMOB'}</p>
              <p className="truncate text-[10px] uppercase tracking-[0.22em] text-slate-500">
                {currentSection?.label || 'Navegação'}
              </p>
            </div>
          </div>

          <nav className="hidden min-w-0 flex-1 items-center justify-center md:flex">
            <div className="flex min-w-0 items-center gap-2 rounded-[28px] border border-white/8 bg-white/[0.03] px-3 py-2 shadow-[0_12px_28px_rgba(2,6,23,0.2)]">
              {sections.map((section) => {
                const isActive = currentSection?.id === section.id;
                const isOpen = openDesktopMenu === section.id;

                return (
                  <div
                    key={section.id}
                    className="relative"
                    onMouseEnter={() => setOpenDesktopMenu(section.id)}
                    onMouseLeave={() => setOpenDesktopMenu((current) => (current === section.id ? null : current))}
                  >
                    <button
                      type="button"
                      onClick={() => setOpenDesktopMenu((current) => (current === section.id ? null : section.id))}
                      className={`flex items-center gap-2 rounded-full px-4 py-2 text-[13px] transition-colors ${
                        isActive || isOpen
                          ? 'bg-white/11 text-white shadow-[inset_0_0_0_1px_rgba(255,255,255,0.06)]'
                          : 'text-slate-300 hover:bg-white/[0.05] hover:text-white'
                      }`}
                    >
                      <div className="shrink-0">{section.icon}</div>
                      <span className="font-medium tracking-[0.01em]">{section.label}</span>
                      {getSectionBadge(section) ? (
                        <span className={`rounded-full px-1.5 py-0.5 text-[10px] font-bold ${isActive || isOpen ? 'bg-white/15 text-white' : 'bg-white/10 text-slate-200'}`}>
                          {getSectionBadge(section)}
                        </span>
                      ) : null}
                      <ChevronDown size={15} className={`transition-transform ${isOpen ? 'rotate-180' : ''}`} />
                    </button>

                    <div
                      className={`absolute left-0 top-full z-50 mt-3 w-72 rounded-2xl border border-white/10 bg-[#0b1627] p-2 shadow-[0_18px_40px_rgba(2,6,23,0.45)] transition-all ${
                        isOpen ? 'pointer-events-auto translate-y-0 opacity-100' : 'pointer-events-none -translate-y-1 opacity-0'
                      }`}
                    >
                      <div className="mb-2 px-2 pt-1">
                        <p className="text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-400">{section.label}</p>
                      </div>
                      <div className="grid gap-1">
                        {section.items.map((item) => {
                          const itemActive = isRouteMatch(location, item.href);

                          return (
                            <Link key={item.href} to={item.href}>
                              <div
                                className={`flex items-center gap-2 rounded-xl px-3 py-2.5 text-sm transition-colors ${
                                  itemActive
                                    ? 'bg-white/10 text-white'
                                    : 'text-slate-300 hover:bg-white/6 hover:text-white'
                                }`}
                              >
                                <div className="shrink-0">{item.icon}</div>
                                <span className="font-medium">{item.label}</span>
                                {item.badge ? (
                                  <span className={`ml-auto rounded-full px-1.5 py-0.5 text-[10px] font-bold ${itemActive ? 'bg-white/15 text-white' : 'bg-white/10 text-slate-200'}`}>
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
                );
              })}

              <Link to={settingsItem.href}>
                <div
                  className={`flex items-center gap-2 rounded-full px-4 py-2 text-[13px] transition-colors ${
                    settingsActive
                      ? 'bg-white/11 text-white shadow-[inset_0_0_0_1px_rgba(255,255,255,0.06)]'
                      : 'text-slate-300 hover:bg-white/[0.05] hover:text-white'
                  }`}
                >
                  <div className="shrink-0">{settingsItem.icon}</div>
                  <span className="font-medium">{settingsItem.label}</span>
                </div>
              </Link>

              {showFixedSubmenu && (
                <>
                  <div className="mx-1 h-7 w-px bg-white/10" />
                  <div className="flex min-w-0 items-center gap-1 overflow-x-auto pr-1 scrollbar-thin scrollbar-thumb-white/10 scrollbar-track-transparent">
                    {secondaryTabs.map((item) => {
                      const isActive = isRouteMatch(location, item.href);

                      return (
                        <Link key={item.href} to={item.href}>
                          <div
                            className={`flex items-center gap-2 whitespace-nowrap rounded-full px-3 py-2 text-[13px] transition-colors ${
                              isActive
                                ? 'bg-white/11 text-white shadow-[inset_0_0_0_1px_rgba(255,255,255,0.06)]'
                                : 'text-slate-400 hover:bg-white/[0.05] hover:text-white'
                            }`}
                          >
                            <div className="shrink-0">{item.icon}</div>
                            <span className="font-medium tracking-[0.01em]">{item.label}</span>
                            {item.badge ? <span className={`rounded-full px-1.5 py-0.5 text-[10px] font-bold ${isActive ? 'bg-white/15 text-white' : 'bg-white/10 text-slate-200'}`}>{item.badge}</span> : null}
                          </div>
                        </Link>
                      );
                    })}
                  </div>
                </>
              )}
            </div>
          </nav>

          <div className="hidden items-center gap-2 md:flex">
            {user?.role === 'super_admin' && <div className="w-[220px]"><TenantSelector isSuperAdmin={true} /></div>}
            <button
              onClick={toggleTheme}
              className="flex h-10 items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3.5 text-[13px] text-slate-200 hover:bg-white/10"
            >
              {theme === 'dark' ? <Moon size={16} /> : <Sun size={16} />}
              <span>{theme === 'dark' ? 'Tema claro' : 'Tema escuro'}</span>
            </button>
            <button
              onClick={handleLogout}
              className="flex h-10 items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3.5 text-[13px] text-slate-200 hover:bg-white/10"
            >
              <LogOut size={16} />
              <span>Sair</span>
            </button>
          </div>
        </div>

        <div className="md:hidden px-4 pb-3">
          {actualIsOpen && (
            <div className="space-y-3 pt-3">
              <div className="rounded-2xl border border-white/10 bg-[#0b1627] p-3 shadow-[0_18px_40px_rgba(2,6,23,0.45)]">
                <div className="mb-3 flex items-center justify-between gap-2">
                  <div>
                    <p className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Menu principal</p>
                    <p className="text-sm font-medium text-white">Escolha a seção</p>
                  </div>
                  <button
                    onClick={handleClose}
                    className="flex h-9 w-9 items-center justify-center rounded-xl border border-white/10 bg-white/5 text-slate-200"
                    aria-label="Fechar navegação"
                  >
                    <X size={18} />
                  </button>
                </div>

                <div className="grid gap-2">
                  {primaryTabs.map((tab) => {
                    const isActive = isPrimaryTabActive(tab, sections, currentSection, settingsActive);

                    return (
                      <Link key={tab.href} to={tab.href}>
                        <div
                          onClick={handleClose}
                          className={`flex items-center gap-2 rounded-xl px-3 py-3 text-sm ${
                            isActive
                              ? 'bg-white/10 text-white'
                              : 'border border-white/10 bg-white/5 text-slate-200'
                          }`}
                        >
                          <div className="shrink-0">{tab.icon}</div>
                          <span className="font-medium">{tab.label}</span>
                          {tab.badge ? (
                            <span className={`ml-auto rounded-full px-1.5 py-0.5 text-[10px] font-bold ${isActive ? 'bg-white/15 text-white' : 'bg-white/10 text-slate-200'}`}>
                              {tab.badge}
                            </span>
                          ) : null}
                        </div>
                      </Link>
                    );
                  })}
                </div>

                {showFixedSubmenu && (
                  <div className="mt-4 border-t border-white/10 pt-4">
                    <p className="mb-2 text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Submenu</p>
                    <div className="grid gap-2">
                      {secondaryTabs.map((item) => {
                        const isActive = isRouteMatch(location, item.href);

                        return (
                          <Link key={item.href} to={item.href}>
                            <div
                              onClick={handleClose}
                              className={`flex items-center gap-2 rounded-xl border px-3 py-2.5 text-sm ${
                                isActive
                                  ? 'border-white/15 bg-white/10 text-white'
                                  : 'border-white/10 bg-white/5 text-slate-200'
                              }`}
                            >
                              <div className="shrink-0">{item.icon}</div>
                              <span className="font-medium">{item.label}</span>
                              {item.badge ? <span className={`ml-auto rounded-full px-1.5 py-0.5 text-[10px] font-bold ${isActive ? 'bg-white/15 text-white' : 'bg-white/10 text-slate-200'}`}>{item.badge}</span> : null}
                            </div>
                          </Link>
                        );
                      })}
                    </div>
                  </div>
                )}

                <div className="mt-4 grid grid-cols-2 gap-2">
                  <button
                    onClick={() => {
                      toggleTheme();
                      handleClose();
                    }}
                    className="flex min-h-[44px] items-center justify-center gap-2 rounded-xl border border-white/10 bg-white/5 px-3 text-sm text-slate-200"
                  >
                    {theme === 'dark' ? <Moon size={15} /> : <Sun size={15} />}
                    <span>{theme === 'dark' ? 'Tema claro' : 'Tema escuro'}</span>
                  </button>
                  <button
                    onClick={handleLogout}
                    className="flex min-h-[44px] items-center justify-center gap-2 rounded-xl border border-white/10 bg-white/5 px-3 text-sm text-slate-200"
                  >
                    <LogOut size={15} />
                    <span>Sair</span>
                  </button>
                </div>

                {user?.role === 'super_admin' && <div className="mt-4 rounded-xl border border-white/10 bg-white/5 p-2"><TenantSelector isSuperAdmin={true} /></div>}
              </div>
            </div>
          )}
        </div>
      </div>
    </>
  );
};

export default Sidebar;
