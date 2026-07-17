import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { useLocation, Link } from 'wouter';
import {
  BarChart3,
  Users,
  Home,
  ChevronDown,
  Settings,
  LogOut,
  Menu,
  X,
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
  Bell,
  Megaphone,
  Palette,
} from 'lucide-react';
import { api } from '@/lib/api';
import { normalizeHiddenSidebarKeys } from '@/lib/sidebarVisibility';
import { INTERNAL_THEMES, type Theme, useTheme } from '@/contexts/ThemeContext';
import TenantSelector from './TenantSelector';

interface SidebarProps {
  isOpen?: boolean;
  onClose?: () => void;
}

interface SidebarItem {
  key: string;
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
  const [openDesktopSectionId, setOpenDesktopSectionId] = useState<string | null>(null);
  const [tenant, setTenant] = useState<TenantConfig | null>(null);
  const [user, setUser] = useState<UserData | null>(null);
  const [unreadMessagesCount, setUnreadMessagesCount] = useState(0);
  const [unreadNotificationsCount, setUnreadNotificationsCount] = useState(0);
  const [hiddenSidebarKeys, setHiddenSidebarKeys] = useState<string[]>([]);
  const { theme, setTheme } = useTheme();
  const isDarkTheme = theme === 'dark' || theme === 'navy';
  const badgePollingTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const badgePollingInFlightRef = useRef(false);
  const badgePollingFailureCountRef = useRef(0);
  const notificationPollingTimeoutRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const notificationPollingInFlightRef = useRef(false);
  const notificationPollingFailureCountRef = useRef(0);
  const desktopNavRef = useRef<HTMLDivElement | null>(null);

  const ThemeIcon = theme === 'light' ? Sun : theme === 'dark' ? Moon : Palette;

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
    const loadHiddenSidebarItems = async () => {
      try {
        const response = await api.get('/admin/settings');
        setHiddenSidebarKeys(normalizeHiddenSidebarKeys(response.data?.config?.hidden_sidebar_keys));
      } catch {
        setHiddenSidebarKeys([]);
      }
    };

    void loadHiddenSidebarItems();
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
        scheduleNextBadgePoll(60000);
        return;
      }

      if (typeof document !== 'undefined' && document.visibilityState !== 'visible') {
        scheduleNextBadgePoll(60000);
        return;
      }

      badgePollingInFlightRef.current = true;

      try {
        const messagesResult = await api.get('/admin/conversas/fila/estatisticas');
        if (messagesResult.data?.success && messagesResult.data?.data?.mensagens_nao_lidas !== undefined) {
          setUnreadMessagesCount(messagesResult.data.data.mensagens_nao_lidas);
        } else if (messagesResult.data?.success && messagesResult.data?.data?.aguardando !== undefined) {
          // Compatibilidade com payload antigo.
          setUnreadMessagesCount(messagesResult.data.data.aguardando);
        }

        badgePollingFailureCountRef.current = 0;
        scheduleNextBadgePoll(7000);
      } catch (error) {
        const failureCount = badgePollingFailureCountRef.current + 1;
        badgePollingFailureCountRef.current = failureCount;

        if (isTransientNetworkError(error)) {
          const delayMs = Math.min(60000 * Math.max(failureCount, 1), 5 * 60 * 1000);
          scheduleNextBadgePoll(delayMs);
        } else {
          scheduleNextBadgePoll(60000);
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

    const handleChatUnreadChanged = () => {
      void loadBadgeCounts();
    };

    window.addEventListener('online', handleOnline);
    document.addEventListener('visibilitychange', handleVisibilityChange);
    window.addEventListener('socimob:chat-unread-changed', handleChatUnreadChanged);

    return () => {
      clearBadgePollingTimer();
      window.removeEventListener('online', handleOnline);
      document.removeEventListener('visibilitychange', handleVisibilityChange);
      window.removeEventListener('socimob:chat-unread-changed', handleChatUnreadChanged);
    };
  }, []);

  useEffect(() => {
    const clearNotificationPollingTimer = () => {
      if (notificationPollingTimeoutRef.current) {
        clearTimeout(notificationPollingTimeoutRef.current);
        notificationPollingTimeoutRef.current = null;
      }
    };

    const scheduleNextNotificationPoll = (delayMs: number) => {
      clearNotificationPollingTimer();
      notificationPollingTimeoutRef.current = setTimeout(() => {
        void loadNotificationCount();
      }, delayMs);
    };

    const loadNotificationCount = async () => {
      if (notificationPollingInFlightRef.current) {
        return;
      }

      if (typeof navigator !== 'undefined' && !navigator.onLine) {
        scheduleNextNotificationPoll(60000);
        return;
      }

      if (typeof document !== 'undefined' && document.visibilityState !== 'visible') {
        scheduleNextNotificationPoll(60000);
        return;
      }

      notificationPollingInFlightRef.current = true;

      try {
        const response = await api.get('/notifications/summary');
        const unread = Number(response.data?.unread ?? 0);
        setUnreadNotificationsCount(Number.isFinite(unread) ? unread : 0);
        notificationPollingFailureCountRef.current = 0;
        scheduleNextNotificationPoll(7000);
      } catch (error) {
        const failureCount = notificationPollingFailureCountRef.current + 1;
        notificationPollingFailureCountRef.current = failureCount;

        if (isTransientNetworkError(error)) {
          const delayMs = Math.min(60000 * Math.max(failureCount, 1), 5 * 60 * 1000);
          scheduleNextNotificationPoll(delayMs);
        } else {
          scheduleNextNotificationPoll(60000);
        }
      } finally {
        notificationPollingInFlightRef.current = false;
      }
    };

    void loadNotificationCount();

    const handleOnline = () => {
      notificationPollingFailureCountRef.current = 0;
      void loadNotificationCount();
    };

    const handleVisibilityChange = () => {
      if (document.visibilityState === 'visible') {
        void loadNotificationCount();
      }
    };

    const handleNotificationsChanged = () => {
      void loadNotificationCount();
    };

    window.addEventListener('online', handleOnline);
    document.addEventListener('visibilitychange', handleVisibilityChange);
    window.addEventListener('socimob:notifications-changed', handleNotificationsChanged);

    return () => {
      clearNotificationPollingTimer();
      window.removeEventListener('online', handleOnline);
      document.removeEventListener('visibilitychange', handleVisibilityChange);
      window.removeEventListener('socimob:notifications-changed', handleNotificationsChanged);
    };
  }, []);

  const sections: SidebarSection[] = [
    {
      id: 'principal',
      icon: <BarChart3 size={18} />,
      label: 'Principal',
      href: '/dashboard',
      items: [
        { key: 'dashboard', icon: <BarChart3 size={16} />, label: 'Dashboard', href: '/dashboard' },
        { key: 'agenda', icon: <CalendarClock size={16} />, label: 'Agenda', href: '/agenda' },
      ],
    },
    {
      id: 'crm',
      icon: <Users size={18} />,
      label: 'Chat & Clientes',
      href: '/crm',
      items: [
        {
          key: 'crm',
          icon: <Users size={16} />,
          label: 'Chat',
          href: '/crm',
          badge: unreadMessagesCount || undefined,
        },
        { key: 'pessoas', icon: <UserRound size={16} />, label: 'Pessoas', href: '/pessoas' },
        { key: 'links-importantes', icon: <Link2 size={16} />, label: 'Links importantes', href: '/links-importantes' },
        { key: 'anuncios-integrados', icon: <Megaphone size={16} />, label: 'Anúncios Integrados', href: '/anuncios-integrados' },
        { key: 'ads', icon: <Zap size={16} />, label: 'Marketing / Anúncios', href: '/ads' },
        { key: 'contratos-locacao', icon: <FileSpreadsheet size={16} />, label: 'Contratos · Locação', href: '/financeiro/locacao' },
        { key: 'contratos-venda', icon: <FileSignature size={16} />, label: 'Contratos · Venda', href: '/financeiro/compra-venda' },
      ],
    },
    {
      id: 'imoveis',
      icon: <Home size={18} />,
      label: 'Imóveis',
      href: '/properties',
      items: [
        { key: 'properties', icon: <Home size={16} />, label: 'Imóveis', href: '/properties' },
        { key: 'properties-sync-runs', icon: <FileText size={16} />, label: 'Sincronizações', href: '/properties/sincronizacoes' },
        { key: 'properties-propaganda', icon: <Image size={16} />, label: 'Propaganda', href: '/properties/propaganda' },
        { key: 'controle-chaves', icon: <KeyRound size={16} />, label: 'Controle de Chaves', href: '/controle-chaves' },
        { key: 'imobi-brasil', icon: <Building2 size={16} />, label: 'ImobiBrasil', href: '/imobi-brasil' },
        { key: 'chaves-na-mao', icon: <KeyRound size={16} />, label: 'Chaves na Mão', href: '/chaves-na-mao' },
      ],
    },
    {
      id: 'operacional',
      icon: <Briefcase size={18} />,
      label: 'Operacional',
      href: '/vistorias',
      items: [
        { key: 'vistorias', icon: <ClipboardCheck size={16} />, label: 'Vistorias', href: '/vistorias' },
        { key: 'assinaturas', icon: <FileSignature size={16} />, label: 'Assinaturas', href: '/assinaturas' },
        { key: 'financeiro-locacao', icon: <FileSpreadsheet size={16} />, label: 'Locação / Operação', href: '/financeiro/locacao' },
        { key: 'financeiro-compra-venda', icon: <FileSignature size={16} />, label: 'Compra e Venda', href: '/financeiro/compra-venda' },
        { key: 'contrato-templates', icon: <FileText size={16} />, label: 'Templates de Contrato', href: '/contrato-templates' },
      ],
    },
    {
      id: 'financeiro',
      icon: <DollarSign size={18} />,
      label: 'Financeiro',
      href: '/financeiro',
      items: [
        { key: 'financeiro', icon: <Wallet size={16} />, label: 'Financeiro', href: '/financeiro' },
        { key: 'financeiro-contas', icon: <BookOpen size={16} />, label: 'Contas a Pagar/Receber', href: '/financeiro/contas' },
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
              { key: 'analytics', icon: <LineChart size={16} />, label: 'Estatísticas', href: '/analytics' },
              { key: 'admin-users', icon: <Shield size={16} />, label: 'Usuários', href: '/admin/users' },
              { key: 'system-logs', icon: <FileText size={16} />, label: 'Logs do Sistema', href: '/system-logs' },
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
              { key: 'tenants', icon: <Building2 size={16} />, label: 'Tenants', href: '/tenants' },
              { key: 'tenants-associacoes', icon: <Link2 size={16} />, label: 'Assoc. Tenants', href: '/tenants/associacoes' },
            ],
          } as SidebarSection,
        ]
      : []),
  ];

  const visibleSections = sections
    .map((section) => ({
      ...section,
      items: section.items.filter((item) => !hiddenSidebarKeys.includes(item.key)),
    }))
    .filter((section) => section.items.length > 0);

  const currentSection =
    visibleSections.find(
      (section) => isRouteMatch(location, section.href) || section.items.some((item) => isRouteMatch(location, item.href)),
    ) || null;

  useEffect(() => {
    delete document.body.dataset.sectionTabs;

    return () => {
      delete document.body.dataset.sectionTabs;
    };
  }, []);

  useEffect(() => {
    setOpenDesktopSectionId(null);
  }, [location]);

  useEffect(() => {
    const handleOutsideClick = (event: MouseEvent) => {
      if (!desktopNavRef.current) {
        return;
      }

      if (desktopNavRef.current.contains(event.target as Node)) {
        return;
      }

      setOpenDesktopSectionId(null);
    };

    document.addEventListener('mousedown', handleOutsideClick);

    return () => {
      document.removeEventListener('mousedown', handleOutsideClick);
    };
  }, []);

  const settingsItem: SidebarItem = { key: 'settings', icon: <Settings size={17} />, label: 'Configurações', href: '/settings' };
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

  useEffect(() => {
    const openMobileMenu = () => setInternalIsOpen(true);

    window.addEventListener('socimob:open-mobile-menu', openMobileMenu);

    return () => {
      window.removeEventListener('socimob:open-mobile-menu', openMobileMenu);
    };
  }, []);

  const primaryTabs: SidebarItem[] = [
    ...visibleSections.map((section) => ({
      key: section.id,
      icon: section.icon,
      label: section.label,
      href: section.href,
      badge: getSectionBadge(section),
    })),
    settingsItem,
  ];

  const secondaryTabs = currentSection?.items || [];
  const activePrimaryTab = primaryTabs.find((tab) => isPrimaryTabActive(tab, visibleSections, currentSection, settingsActive)) || null;
  const showFixedSubmenu = secondaryTabs.length > 1;
  const desktopMenuLabel = settingsActive
    ? settingsItem.label
    : currentSection?.label || activePrimaryTab?.label || 'Menu';
  const notificationBadgeLabel = unreadNotificationsCount > 99 ? '99+' : String(unreadNotificationsCount);
  const notificationsActive = isRouteMatch(location, '/notifications');
  const logoContainerClass = isDarkTheme
    ? 'border-cyan-300/10 bg-white/[0.04] text-white shadow-[0_14px_30px_rgba(2,6,23,0.28)]'
    : 'border-slate-300 bg-slate-100 text-slate-700 shadow-[0_8px_20px_rgba(15,23,42,0.12)]';
  const tenantNameClass = isDarkTheme ? 'text-white' : 'text-slate-800';
  const desktopMenuButtonClass = isDarkTheme
    ? 'border-transparent bg-white/[0.03] text-slate-200 hover:border-white/10 hover:bg-white/[0.07] hover:text-white'
    : 'border-slate-300 bg-white text-slate-700 hover:border-slate-400 hover:bg-slate-50 hover:text-slate-900';
  const desktopMenuButtonActiveClass = isDarkTheme
    ? 'border-cyan-300/16 bg-[linear-gradient(180deg,rgba(32,54,73,0.95),rgba(16,31,49,0.95))] text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.05),0_12px_24px_rgba(2,6,23,0.18)]'
    : 'border-slate-300 bg-slate-100 text-slate-900 shadow-[0_8px_20px_rgba(15,23,42,0.10)]';
  const dropdownPanelClass = isDarkTheme
    ? 'border-white/10 bg-[#0b1627] shadow-[0_18px_40px_rgba(2,6,23,0.45)]'
    : 'border-slate-300 bg-white shadow-[0_14px_32px_rgba(15,23,42,0.14)]';
  const sectionCardClass = isDarkTheme ? 'border-white/8 bg-white/[0.03]' : 'border-slate-200 bg-slate-50';
  const sectionHeaderTextClass = isDarkTheme ? 'text-slate-400' : 'text-slate-500';
  const sectionHeaderIconClass = isDarkTheme ? 'text-slate-300' : 'text-slate-600';
  const badgeClass = isDarkTheme ? 'bg-white/10 text-slate-200' : 'bg-slate-200 text-slate-700';
  const itemClass = isDarkTheme
    ? 'border border-transparent bg-white/5 text-slate-200 hover:border-white/10 hover:bg-white/10'
    : 'border border-slate-200 bg-white text-slate-700 hover:border-slate-300 hover:bg-slate-50';
  const itemActiveClass = isDarkTheme
    ? 'border border-cyan-400/20 bg-cyan-400/10 text-white'
    : 'border border-blue-200 bg-blue-50 text-blue-900';
  const mobilePanelClass = isDarkTheme
    ? 'border-white/10 bg-[#0b1627] shadow-[0_18px_40px_rgba(2,6,23,0.45)]'
    : 'border-slate-300 bg-white shadow-[0_14px_32px_rgba(15,23,42,0.14)]';
  const mobileSubtleTextClass = isDarkTheme ? 'text-slate-400' : 'text-slate-500';
  const mobileTitleClass = isDarkTheme ? 'text-white' : 'text-slate-900';
  const mobileChipClass = isDarkTheme
    ? 'border-white/10 bg-white/5 text-slate-300'
    : 'border-slate-300 bg-slate-100 text-slate-700';
  const mobileItemClass = isDarkTheme ? 'border border-white/10 bg-white/5 text-slate-200' : 'border border-slate-200 bg-white text-slate-700';
  const mobileItemActiveClass = isDarkTheme ? 'border border-white/15 bg-white/10 text-white' : 'border border-blue-200 bg-blue-50 text-blue-900';
  const mobileSecondaryItemClass = isDarkTheme ? 'border-white/10 bg-white/5 text-slate-200' : 'border-slate-200 bg-white text-slate-700';
  const mobileSecondaryItemActiveClass = isDarkTheme ? 'border-cyan-400/20 bg-cyan-400/10 text-white' : 'border-blue-200 bg-blue-50 text-blue-900';

  return (
    <>
      <div
        className={`fixed inset-x-0 top-0 z-40 border-b backdrop-blur-xl ${
          isDarkTheme
            ? 'border-white/8 bg-[linear-gradient(180deg,rgba(8,14,27,0.97),rgba(6,12,22,0.92))] shadow-[0_20px_48px_rgba(2,6,23,0.42)]'
            : 'border-slate-200/90 bg-[linear-gradient(180deg,rgba(255,255,255,0.97),rgba(248,250,252,0.94))] shadow-[0_20px_48px_rgba(15,23,42,0.08)]'
        }`}
      >
        <div className="mx-auto max-w-[1600px] px-4 md:px-6 lg:px-8">
          <div className="flex min-h-[60px] items-center justify-between gap-2 py-2 md:min-h-[72px] md:gap-3 md:py-3">
            <div className="flex min-w-0 items-center gap-3 md:flex-1 md:gap-4">
              {tenant?.logo_url || tenant?.logo ? (
                <img
                  src={tenant.logo_url || tenant.logo}
                  alt={tenant.name}
                  className={`h-9 w-9 rounded-xl border object-contain p-1.5 md:h-10 md:w-10 ${logoContainerClass}`}
                />
              ) : (
                <div className={`flex h-9 w-9 items-center justify-center rounded-xl border md:h-10 md:w-10 ${logoContainerClass}`}>
                  <Building2 size={17} />
                </div>
              )}

              <div className="min-w-0 shrink-0">
                <p className={`max-w-[11rem] truncate font-sans text-[0.95rem] font-semibold leading-tight tracking-[0.01em] min-[390px]:max-w-[13rem] md:max-w-none md:text-[1.12rem] ${tenantNameClass}`}>
                  {tenant?.name || 'SOCIMOB'}
                </p>
              </div>

              <div ref={desktopNavRef} className="hidden min-w-0 flex-1 items-center md:flex">
                <div className="relative">
                  <button
                    type="button"
                    onClick={() => setOpenDesktopSectionId((current) => (current === 'main-menu' ? null : 'main-menu'))}
                    className={`flex items-center gap-2 rounded-full border px-4 py-2.5 text-[13px] transition-all ${
                      openDesktopSectionId === 'main-menu'
                        ? desktopMenuButtonActiveClass
                        : desktopMenuButtonClass
                    }`}
                  >
                    <span className="font-medium">{desktopMenuLabel}</span>
                    {activePrimaryTab?.badge ? (
                      <span className={`rounded-full px-1.5 py-0.5 text-[10px] font-bold ${badgeClass}`}>
                        {activePrimaryTab.badge}
                      </span>
                    ) : null}
                    <ChevronDown size={14} className={`transition-transform ${openDesktopSectionId === 'main-menu' ? 'rotate-180' : ''}`} />
                  </button>

                  {openDesktopSectionId === 'main-menu' ? (
                    <div className={`absolute left-0 top-full z-50 mt-2 w-[360px] rounded-3xl border p-3 backdrop-blur-xl ${dropdownPanelClass}`}>
                      <div className="max-h-[70vh] space-y-3 overflow-y-auto pr-1">
                        {visibleSections.map((section) => (
                          <div key={section.id} className={`rounded-2xl border p-2 ${sectionCardClass}`}>
                            <div className="mb-1 flex items-center gap-2 px-2 py-2">
                              <div className={`shrink-0 ${sectionHeaderIconClass}`}>{section.icon}</div>
                              <p className={`text-[11px] font-semibold uppercase tracking-[0.16em] ${sectionHeaderTextClass}`}>{section.label}</p>
                              {getSectionBadge(section) ? (
                                <span className={`ml-auto rounded-full px-1.5 py-0.5 text-[10px] font-bold ${badgeClass}`}>
                                  {getSectionBadge(section)}
                                </span>
                              ) : null}
                            </div>
                            <div className="grid gap-1">
                              {section.items.map((item) => {
                                const itemIsActive = isRouteMatch(location, item.href);

                                return (
                                  <Link key={item.href} to={item.href}>
                                    <div
                                      onClick={() => setOpenDesktopSectionId(null)}
                                      className={`flex items-center gap-2 rounded-2xl px-3 py-2.5 text-sm transition-all ${
                                        itemIsActive
                                          ? itemActiveClass
                                          : itemClass
                                      }`}
                                    >
                                      <div className="shrink-0">{item.icon}</div>
                                      <span className="font-medium">{item.label}</span>
                                      {item.badge ? (
                                        <span className={`ml-auto rounded-full px-1.5 py-0.5 text-[10px] font-bold ${itemIsActive ? (isDarkTheme ? 'bg-white/15 text-white' : 'bg-blue-100 text-blue-800') : badgeClass}`}>
                                          {item.badge}
                                        </span>
                                      ) : null}
                                    </div>
                                  </Link>
                                );
                              })}
                            </div>
                          </div>
                        ))}

                        <div className={`rounded-2xl border p-2 ${sectionCardClass}`}>
                          <Link to={settingsItem.href}>
                            <div
                              onClick={() => setOpenDesktopSectionId(null)}
                              className={`flex items-center gap-2 rounded-2xl px-3 py-2.5 text-sm transition-all ${
                                settingsActive
                                  ? itemActiveClass
                                  : itemClass
                              }`}
                            >
                              <div className="shrink-0">{settingsItem.icon}</div>
                              <span className="font-medium">{settingsItem.label}</span>
                            </div>
                          </Link>
                        </div>
                      </div>
                    </div>
                  ) : null}
                </div>
              </div>
            </div>

            <div className="hidden items-center gap-2 md:flex lg:gap-2.5">
              {user?.role === 'super_admin' && <div className="w-[240px]"><TenantSelector isSuperAdmin={true} /></div>}
              <Link to="/notifications">
                <div
                  className={`relative flex h-10 w-10 items-center justify-center rounded-full border transition-colors ${
                    notificationsActive
                      ? 'border-cyan-300/20 bg-cyan-300/10 text-white'
                      : isDarkTheme
                        ? 'border-white/10 bg-white/[0.035] text-slate-200 hover:border-white/16 hover:bg-white/[0.08]'
                        : 'border-slate-300 bg-white text-slate-700 hover:border-slate-400 hover:bg-slate-50'
                  }`}
                  aria-label="Notificações"
                  title="Notificações"
                >
                  <Bell size={16} />
                  {unreadNotificationsCount > 0 ? (
                    <span className="absolute -right-1 -top-1 flex min-w-[18px] items-center justify-center rounded-full bg-red-500 px-1.5 py-0.5 text-[10px] font-bold leading-none text-white shadow-[0_0_0_2px_rgba(8,14,27,0.96)]">
                      {notificationBadgeLabel}
                    </span>
                  ) : null}
                </div>
              </Link>
              <label
                className={`flex h-10 items-center gap-2 rounded-full border px-3 text-[12px] transition-colors ${
                  isDarkTheme
                    ? 'border-white/10 bg-white/[0.035] text-slate-200 hover:border-white/16 hover:bg-white/[0.08]'
                    : 'border-slate-300 bg-white text-slate-700 hover:border-slate-400 hover:bg-slate-50'
                }`}
              >
                <ThemeIcon size={16} />
                <span className="sr-only">Tema interno</span>
                <select
                  aria-label="Tema interno"
                  value={theme}
                  onChange={(event) => setTheme(event.target.value as Theme)}
                  className="h-auto min-h-0 border-0 bg-transparent p-0 text-[12px] font-semibold text-inherit shadow-none outline-none"
                >
                  {INTERNAL_THEMES.map(option => <option key={option.value} value={option.value}>{option.label}</option>)}
                </select>
              </label>
              <button
                onClick={handleLogout}
                className={`flex h-10 items-center gap-2 rounded-full border px-3 text-[12px] transition-colors ${
                  isDarkTheme
                    ? 'border-white/10 bg-white/[0.035] text-slate-200 hover:border-white/16 hover:bg-white/[0.08]'
                    : 'border-slate-300 bg-white text-slate-700 hover:border-slate-400 hover:bg-slate-50'
                }`}
              >
                <LogOut size={16} />
                <span>Sair</span>
              </button>
            </div>

            <div className="flex items-center gap-2 md:hidden">
              <Link to="/notifications">
                <div
                  className={`relative flex h-10 w-10 items-center justify-center rounded-xl border text-white transition-colors ${
                    notificationsActive
                      ? isDarkTheme
                        ? 'border-cyan-300/20 bg-cyan-300/10 text-white'
                        : 'border-blue-200 bg-blue-50 text-blue-900'
                      : isDarkTheme
                        ? 'border-white/10 bg-white/[0.035] text-white hover:border-white/16 hover:bg-white/[0.08]'
                        : 'border-slate-300 bg-white text-slate-700 hover:border-slate-400 hover:bg-slate-50'
                  }`}
                  aria-label="Notificações"
                  title="Notificações"
                >
                  <Bell size={19} />
                  {unreadNotificationsCount > 0 ? (
                    <span className="absolute -right-1 -top-1 flex min-w-[18px] items-center justify-center rounded-full bg-red-500 px-1.5 py-0.5 text-[10px] font-bold leading-none text-white shadow-[0_0_0_2px_rgba(8,14,27,0.96)]">
                      {notificationBadgeLabel}
                    </span>
                  ) : null}
                </div>
              </Link>
              <button
                type="button"
                onClick={toggleMobileMenu}
                className={`flex h-10 w-10 items-center justify-center rounded-xl border transition-colors ${
                  isDarkTheme
                    ? 'border-white/10 bg-white/[0.035] text-white hover:border-white/16 hover:bg-white/[0.08]'
                    : 'border-slate-300 bg-white text-slate-700 hover:border-slate-400 hover:bg-slate-50'
                }`}
                aria-label={actualIsOpen ? 'Fechar navegação' : 'Abrir navegação'}
              >
                {actualIsOpen ? <X size={20} /> : <Menu size={20} />}
              </button>
            </div>
          </div>

          <div className="md:hidden">
            {actualIsOpen && typeof document !== 'undefined' && createPortal(
              <div className="fixed inset-x-0 bottom-[calc(var(--mobile-bottom-nav-height)+env(safe-area-inset-bottom))] top-[60px] z-[90] overflow-y-auto bg-slate-950/70 p-2.5 backdrop-blur-sm">
                <div className={`relative z-[95] mx-auto max-w-md rounded-2xl border p-3 ${mobilePanelClass}`}>
                  <div className="mb-4 flex items-center justify-between gap-3">
                    <div>
                      <p className={`text-xs font-semibold uppercase tracking-[0.16em] ${mobileSubtleTextClass}`}>Menu principal</p>
                      <p className={`text-sm font-medium ${mobileTitleClass}`}>Escolha uma área do sistema</p>
                    </div>
                    <button
                      type="button"
                      onClick={closeMobileMenu}
                      className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl border ${
                        isDarkTheme ? 'border-white/10 bg-white/5 text-white' : 'border-slate-300 bg-white text-slate-700'
                      }`}
                      aria-label="Fechar menu"
                    >
                      <X size={18} />
                    </button>
                  </div>

                  <div className="mb-3 flex items-center justify-between gap-3">
                    <div className={`rounded-full border px-2.5 py-1 text-[10px] font-semibold uppercase tracking-[0.16em] ${mobileChipClass}`}>
                      {currentSection?.label || 'Geral'}
                    </div>
                  </div>

                  <div className="grid gap-2">
                    {primaryTabs.map((tab) => {
                      const isActive = isPrimaryTabActive(tab, visibleSections, currentSection, settingsActive);

                      return (
                        <Link key={tab.href} to={tab.href}>
                          <div
                            onClick={closeMobileMenu}
                            className={`flex items-center gap-2 rounded-2xl px-3 py-3 text-sm ${
                              isActive
                                ? mobileItemActiveClass
                                : mobileItemClass
                            }`}
                          >
                            <div className="shrink-0">{tab.icon}</div>
                            <span className="font-medium">{tab.label}</span>
                            {tab.badge ? (
                              <span className={`ml-auto rounded-full px-1.5 py-0.5 text-[10px] font-bold ${isActive ? (isDarkTheme ? 'bg-white/15 text-white' : 'bg-blue-100 text-blue-800') : badgeClass}`}>
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
                      <p className={`mb-2 text-xs font-semibold uppercase tracking-[0.16em] ${mobileSubtleTextClass}`}>{currentSection?.label}</p>
                      <div className="grid gap-2">
                        {secondaryTabs.map((item) => {
                          const isActive = isRouteMatch(location, item.href);

                          return (
                            <Link key={item.href} to={item.href}>
                              <div
                                onClick={closeMobileMenu}
                                className={`flex items-center gap-2 rounded-2xl border px-3 py-2.5 text-sm ${
                                  isActive
                                    ? mobileSecondaryItemActiveClass
                                    : mobileSecondaryItemClass
                                }`}
                              >
                                <div className="shrink-0">{item.icon}</div>
                                <span className="font-medium">{item.label}</span>
                                {item.badge ? <span className={`ml-auto rounded-full px-1.5 py-0.5 text-[10px] font-bold ${isActive ? (isDarkTheme ? 'bg-white/15 text-white' : 'bg-blue-100 text-blue-800') : badgeClass}`}>{item.badge}</span> : null}
                              </div>
                            </Link>
                          );
                        })}
                      </div>
                    </div>
                  )}

                  <div className="mt-4 grid grid-cols-2 gap-2">
                    <label
                      className={`flex min-h-[44px] items-center justify-center gap-2 rounded-2xl border px-3 text-sm ${
                        isDarkTheme
                          ? 'border-white/10 bg-white/5 text-slate-200'
                          : 'border-slate-300 bg-white text-slate-700'
                      }`}
                    >
                      <ThemeIcon size={15} />
                      <select
                        aria-label="Tema interno"
                        value={theme}
                        onChange={(event) => setTheme(event.target.value as Theme)}
                        className="h-auto min-h-0 border-0 bg-transparent p-0 text-sm font-semibold text-inherit shadow-none outline-none"
                      >
                        {INTERNAL_THEMES.map(option => <option key={option.value} value={option.value}>{option.label}</option>)}
                      </select>
                    </label>
                    <button
                      onClick={handleLogout}
                      className={`flex min-h-[44px] items-center justify-center gap-2 rounded-2xl border px-3 text-sm ${
                        isDarkTheme
                          ? 'border-white/10 bg-white/5 text-slate-200'
                          : 'border-slate-300 bg-white text-slate-700'
                      }`}
                    >
                      <LogOut size={15} />
                      <span>Sair</span>
                    </button>
                  </div>

                  {user?.role === 'super_admin' && (
                    <div
                      className={`mt-4 rounded-2xl border p-2 ${
                        isDarkTheme ? 'border-white/10 bg-white/5' : 'border-slate-300 bg-slate-50'
                      }`}
                    >
                      <TenantSelector isSuperAdmin={true} />
                    </div>
                  )}
                </div>
              </div>,
              document.body,
            )}
          </div>
        </div>
      </div>
    </>
  );
};

export default Sidebar;
