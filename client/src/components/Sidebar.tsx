import { useEffect, useRef, useState } from 'react';
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

  return (
    maybeError.code === 'ERR_NETWORK' ||
    message.includes('network changed') ||
    message.includes('connection closed') ||
    message.includes('socket hang up')
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
  const badgePollingTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const badgePollingInFlightRef = useRef(false);
  const badgePollingFailureCountRef = useRef(0);

  const actualIsOpen = onClose ? isOpen : internalIsOpen;

  useEffect(() => {
    document.body.dataset.sidebar = 'topnav';

    return () => {
      delete document.body.dataset.sidebar;
    };
  }, []);

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
        // Silently handle config loading failures.
      }
    };

    loadTenantAndUser();
  }, []);

  useEffect(() => {
    const clearBadgePollingTimer = () => {
      if (badgePollingTimeoutRef.current) {
        clearTimeout(badgePollingTimeoutRef.current);
        badgePollingTimeoutRef.current = null;
      }
    };

    const scheduleNextBadgePoll = (delayMs: number) => {
      clearBadgePollingTimer();
      badgePollingTimeoutRef.current = setTimeout(() => {
        void loadBadgeCounts();
      }, delayMs);
    };

    const loadBadgeCounts = async () => {
      if (badgePollingInFlightRef.current) {
        return;
      }

      if (typeof navigator !== 'undefined' && !navigator.onLine) {
        scheduleNextBadgePoll(120000);
        return;
      }

      if (typeof document !== 'undefined' && document.visibilityState !== 'visible') {
        scheduleNextBadgePoll(120000);
        return;
      }

      badgePollingInFlightRef.current = true;

      try {
        const notifResult = await api.get('/notifications/unread-count');
        if (notifResult.data?.unread_count !== undefined) {
          setNotificationCount(notifResult.data.unread_count);
        }

        const leadsResult = await api.get('/leads/stats');
        if (leadsResult.data?.success && leadsResult.data?.data?.novos !== undefined) {
          setLeadsCount(leadsResult.data.data.novos);
        }

        const messagesResult = await api.get('/admin/conversas/fila/estatisticas');
        if (messagesResult.data?.success && messagesResult.data?.data?.aguardando !== undefined) {
          setUnreadMessagesCount(messagesResult.data.data.aguardando);
        }

        badgePollingFailureCountRef.current = 0;
        scheduleNextBadgePoll(60000);
      } catch (error) {
        const failureCount = badgePollingFailureCountRef.current + 1;
        badgePollingFailureCountRef.current = failureCount;

        if (isTransientNetworkError(error)) {
          const delayMs = Math.min(60000 * Math.max(failureCount, 1), 5 * 60 * 1000);
          scheduleNextBadgePoll(delayMs);
        } else {
          scheduleNextBadgePoll(120000);
        }
      } finally {
        badgePollingInFlightRef.current = false;
      }
    };

    void loadBadgeCounts();

    const handleOnline = () => {
      badgePollingFailureCountRef.current = 0;
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
      clearBadgePollingTimer();
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
      (section) => isRouteMatch(location, section.href) || section.items.some((item) => isRouteMatch(location, item.href)),
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

  const toggleMobileMenu = () => {
    if (!onClose) {
      setInternalIsOpen((current) => !current);
      return;
    }

    if (actualIsOpen) {
      onClose();
    }
  };

  const closeMobileMenu = () => {
    if (onClose) {
      onClose();
      return;
    }

    setInternalIsOpen(false);
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
      <div className="fixed inset-x-0 top-0 z-40 border-b border-white/8 bg-[linear-gradient(180deg,rgba(8,14,27,0.97),rgba(6,12,22,0.92))] shadow-[0_20px_48px_rgba(2,6,23,0.42)] backdrop-blur-xl">
        <div className="mx-auto max-w-[1600px] px-4 md:px-6 lg:px-8">
          <div className="flex min-h-[68px] items-center justify-between gap-3 py-2.5 md:min-h-[72px] md:py-3">
            <div className="flex min-w-0 items-center gap-2.5 md:gap-3">
              {tenant?.logo_url || tenant?.logo ? (
                <img
                  src={tenant.logo_url || tenant.logo}
                  alt={tenant.name}
                  className="h-10 w-10 rounded-xl border border-cyan-300/10 bg-white/[0.04] object-contain p-1.5 shadow-[0_14px_30px_rgba(2,6,23,0.28)]"
                />
              ) : (
                <div className="flex h-10 w-10 items-center justify-center rounded-xl border border-cyan-300/10 bg-white/[0.04] text-white shadow-[0_14px_30px_rgba(2,6,23,0.28)]">
                  <Building2 size={17} />
                </div>
              )}

              <div className="min-w-0">
                <p className="truncate font-serif text-[1rem] font-semibold leading-tight tracking-[0.01em] text-white md:text-[1.12rem]">
                  {tenant?.name || 'SOCIMOB'}
                </p>
                <div className="flex min-w-0 items-center gap-2">
                  <p className="truncate text-[9px] uppercase tracking-[0.2em] text-slate-400 md:text-[10px]">
                    {currentSection?.label || 'Navegação'}
                  </p>
                  {activePrimaryTab?.badge ? (
                    <span className="hidden rounded-full border border-cyan-400/18 bg-cyan-400/12 px-2 py-0.5 text-[10px] font-semibold text-cyan-100 shadow-[inset_0_1px_0_rgba(255,255,255,0.05)] md:inline-flex">
                      {activePrimaryTab.badge}
                    </span>
                  ) : null}
                </div>
              </div>
            </div>

            <div className="hidden items-center gap-2 md:flex lg:gap-2.5">
              {user?.role === 'super_admin' && <div className="w-[240px]"><TenantSelector isSuperAdmin={true} /></div>}
              <button
                onClick={toggleTheme}
                className="flex h-10 items-center gap-2 rounded-full border border-white/10 bg-white/[0.035] px-3 text-[12px] text-slate-200 transition-colors hover:border-white/16 hover:bg-white/[0.08]"
              >
                {theme === 'dark' ? <Moon size={16} /> : <Sun size={16} />}
                <span>{theme === 'dark' ? 'Tema claro' : 'Tema escuro'}</span>
              </button>
              <button
                onClick={handleLogout}
                className="flex h-10 items-center gap-2 rounded-full border border-white/10 bg-white/[0.035] px-3 text-[12px] text-slate-200 transition-colors hover:border-white/16 hover:bg-white/[0.08]"
              >
                <LogOut size={16} />
                <span>Sair</span>
              </button>
            </div>

            <button
              type="button"
              onClick={toggleMobileMenu}
              className="flex h-11 w-11 items-center justify-center rounded-2xl border border-white/10 bg-white/[0.035] text-white transition-colors hover:border-white/16 hover:bg-white/[0.08] md:hidden"
              aria-label={actualIsOpen ? 'Fechar navegação' : 'Abrir navegação'}
            >
              {actualIsOpen ? <X size={20} /> : <Menu size={20} />}
            </button>
          </div>

          <nav className="hidden border-t border-white/8 py-2 md:block">
            <div className="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-thin scrollbar-thumb-white/10 scrollbar-track-transparent">
              {sections.map((section) => {
                const isActive = currentSection?.id === section.id;
                const sectionBadge = getSectionBadge(section);

                return (
                  <Link key={section.id} to={section.href}>
                    <div
                      className={`flex shrink-0 items-center gap-2 rounded-full border px-3.5 py-2 text-[12px] transition-all ${
                        isActive
                          ? 'border-cyan-300/16 bg-[linear-gradient(180deg,rgba(32,54,73,0.95),rgba(16,31,49,0.95))] text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.05),0_12px_24px_rgba(2,6,23,0.18)]'
                          : 'border-transparent bg-white/[0.03] text-slate-300 hover:border-white/10 hover:bg-white/[0.07] hover:text-white'
                      }`}
                    >
                      <div className="shrink-0">{section.icon}</div>
                      <span className="font-medium tracking-[0.01em]">{section.label}</span>
                      {sectionBadge ? (
                        <span className={`rounded-full px-1.5 py-0.5 text-[10px] font-bold ${isActive ? 'bg-cyan-300/14 text-cyan-100' : 'bg-white/10 text-slate-200'}`}>
                          {sectionBadge}
                        </span>
                      ) : null}
                    </div>
                  </Link>
                );
              })}

              <div className="mx-1 h-6 w-px shrink-0 bg-white/10" />

              <Link to={settingsItem.href}>
                <div
                  className={`flex shrink-0 items-center gap-2 rounded-full border px-3.5 py-2 text-[12px] transition-all ${
                    settingsActive
                      ? 'border-cyan-300/16 bg-[linear-gradient(180deg,rgba(32,54,73,0.95),rgba(16,31,49,0.95))] text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.05),0_12px_24px_rgba(2,6,23,0.18)]'
                      : 'border-transparent bg-white/[0.03] text-slate-300 hover:border-white/10 hover:bg-white/[0.07] hover:text-white'
                  }`}
                >
                  <div className="shrink-0">{settingsItem.icon}</div>
                  <span className="font-medium">{settingsItem.label}</span>
                </div>
              </Link>
            </div>
          </nav>

          {showFixedSubmenu && (
            <div className="hidden border-t border-white/8 py-2 md:block">
              <div className="flex items-center gap-3 overflow-x-auto pb-1 scrollbar-thin scrollbar-thumb-white/10 scrollbar-track-transparent">
                <div className="shrink-0 rounded-full border border-white/10 bg-white/[0.03] px-3 py-1 text-[9px] font-semibold uppercase tracking-[0.18em] text-slate-400">
                  {currentSection?.label}
                </div>

                {secondaryTabs.map((item) => {
                  const isActive = isRouteMatch(location, item.href);

                  return (
                    <Link key={item.href} to={item.href}>
                      <div
                        className={`flex shrink-0 items-center gap-2 rounded-full border px-3 py-1.5 text-[12px] transition-all ${
                          isActive
                            ? 'border-cyan-400/18 bg-cyan-400/10 text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.05)]'
                            : 'border-transparent bg-transparent text-slate-400 hover:border-white/10 hover:bg-white/[0.05] hover:text-white'
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
            </div>
          )}

          <div className="pb-3 md:hidden">
            {actualIsOpen && (
              <div className="space-y-3 border-t border-white/8 pt-3">
                <div className="rounded-[28px] border border-white/10 bg-[#0b1627] p-4 shadow-[0_18px_40px_rgba(2,6,23,0.45)]">
                  <div className="mb-4 flex items-center justify-between gap-3">
                    <div>
                      <p className="text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">Menu principal</p>
                      <p className="text-sm font-medium text-white">Escolha uma área do sistema</p>
                    </div>
                    <div className="rounded-full border border-white/10 bg-white/5 px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] text-slate-300">
                      {currentSection?.label || 'Geral'}
                    </div>
                  </div>

                  <div className="grid gap-2">
                    {primaryTabs.map((tab) => {
                      const isActive = isPrimaryTabActive(tab, sections, currentSection, settingsActive);

                      return (
                        <Link key={tab.href} to={tab.href}>
                          <div
                            onClick={closeMobileMenu}
                            className={`flex items-center gap-2 rounded-2xl px-3 py-3 text-sm ${
                              isActive
                                ? 'border border-white/15 bg-white/10 text-white'
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
                      <p className="mb-2 text-xs font-semibold uppercase tracking-[0.16em] text-slate-400">{currentSection?.label}</p>
                      <div className="grid gap-2">
                        {secondaryTabs.map((item) => {
                          const isActive = isRouteMatch(location, item.href);

                          return (
                            <Link key={item.href} to={item.href}>
                              <div
                                onClick={closeMobileMenu}
                                className={`flex items-center gap-2 rounded-2xl border px-3 py-2.5 text-sm ${
                                  isActive
                                    ? 'border-cyan-400/20 bg-cyan-400/10 text-white'
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
                        closeMobileMenu();
                      }}
                      className="flex min-h-[44px] items-center justify-center gap-2 rounded-2xl border border-white/10 bg-white/5 px-3 text-sm text-slate-200"
                    >
                      {theme === 'dark' ? <Moon size={15} /> : <Sun size={15} />}
                      <span>{theme === 'dark' ? 'Tema claro' : 'Tema escuro'}</span>
                    </button>
                    <button
                      onClick={handleLogout}
                      className="flex min-h-[44px] items-center justify-center gap-2 rounded-2xl border border-white/10 bg-white/5 px-3 text-sm text-slate-200"
                    >
                      <LogOut size={15} />
                      <span>Sair</span>
                    </button>
                  </div>

                  {user?.role === 'super_admin' && <div className="mt-4 rounded-2xl border border-white/10 bg-white/5 p-2"><TenantSelector isSuperAdmin={true} /></div>}
                </div>
              </div>
            )}
          </div>
        </div>
      </div>
    </>
  );
};

export default Sidebar;
