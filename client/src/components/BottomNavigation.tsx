import { motion } from 'framer-motion';
import { useLocation, Link } from 'wouter';
import {
  BarChart3,
  Users,
  Home,
  CalendarClock,
  MoreHorizontal,
} from 'lucide-react';
import { useState } from 'react';

interface NavItem {
  icon: React.ReactNode;
  label: string;
  href: string;
  badge?: number;
}

export default function BottomNavigation() {
  const [location] = useLocation();
  const [showMore, setShowMore] = useState(false);

  const isPublicRoute =
    location === '/' ||
    location === '/portal' ||
    location.startsWith('/portal/') ||
    location === '/login' ||
    location === '/forgot-password' ||
    location === '/reset-password';

  const isAuthenticated = typeof window !== 'undefined' && Boolean(localStorage.getItem('token'));

  if (isPublicRoute || !isAuthenticated) {
    return null;
  }

  const mainNavItems: NavItem[] = [
    { icon: <BarChart3 size={21} />, label: 'Início', href: '/dashboard' },
    { icon: <Users size={21} />, label: 'Chat', href: '/crm' },
    { icon: <Home size={21} />, label: 'Imóveis', href: '/properties' },
    { icon: <CalendarClock size={21} />, label: 'Agenda', href: '/agenda' },
    { icon: <MoreHorizontal size={21} />, label: 'Menu', href: '#menu' },
  ];

  const moreNavItems: NavItem[] = [];

  const isActive = (href: string) => location === href || (href !== '/dashboard' && location.startsWith(href));

  return (
    <>
      {/* Bottom Navigation - Mobile Only */}
      <motion.div
        initial={{ y: 100 }}
        animate={{ y: 0 }}
        className="md:hidden fixed bottom-0 left-0 right-0 border-t border-slate-200 bg-white/96 text-slate-700 shadow-[0_-8px_22px_rgba(15,23,42,0.12)] backdrop-blur-xl z-40 safe-area-inset-bottom dark:border-white/10 dark:bg-slate-950/96 dark:text-slate-300"
      >
        <div className="grid grid-cols-5 px-1 pb-1.5 pt-1.5">
          {mainNavItems.map((item) => {
            const active = isActive(item.href);
            const isMenu = item.href === '#menu';
            return (
              <Link key={item.label} to={isMenu ? location : item.href}>
                <motion.div
                  whileTap={{ scale: 0.9 }}
                  onClick={() => {
                    if (isMenu) window.dispatchEvent(new CustomEvent('socimob:open-mobile-menu'));
                  }}
                  className={`relative mx-auto flex min-h-[46px] w-full max-w-[68px] flex-col items-center justify-center rounded-xl px-1 py-1 transition-all ${
                    active
                      ? 'bg-blue-50 text-blue-700 dark:bg-blue-500/15 dark:text-blue-300'
                      : 'text-slate-500 active:bg-slate-100 dark:text-slate-400 dark:active:bg-white/10'
                  }`}
                >
                  <div className={active ? 'text-blue-700 dark:text-blue-300' : 'text-slate-500 dark:text-slate-400'}>
                    {item.icon}
                  </div>
                  {item.badge && (
                    <motion.span
                      initial={{ scale: 0 }}
                      animate={{ scale: 1 }}
                      className="absolute -top-1 -right-1 bg-gradient-to-r from-red-500 to-pink-500 text-white text-xs font-bold w-5 h-5 rounded-full flex items-center justify-center"
                    >
                      {item.badge}
                    </motion.span>
                  )}
                  <span className={`mt-0.5 max-w-full truncate text-[10px] font-semibold leading-none ${active ? 'text-blue-700 dark:text-blue-300' : 'text-slate-500 dark:text-slate-400'}`}>
                    {item.label}
                  </span>
                </motion.div>
              </Link>
            );
          })}

          {/* More Menu */}
          {moreNavItems.length > 0 && (
            <div className="relative">
              <motion.button
                whileTap={{ scale: 0.9 }}
                onClick={() => setShowMore(!showMore)}
                className="flex flex-col items-center justify-center px-3 py-2 rounded-xl hover:bg-white/5 transition-all"
              >
                <MoreHorizontal size={24} className="text-muted-foreground" />
                <span className="text-[10px] mt-1 font-medium text-muted-foreground">Mais</span>
              </motion.button>

              {showMore && (
                <motion.div
                  initial={{ opacity: 0, y: 10 }}
                  animate={{ opacity: 1, y: 0 }}
                  exit={{ opacity: 0, y: 10 }}
                  className="absolute bottom-full right-0 mb-2 w-48 bg-gray-900 border border-white/10 rounded-xl shadow-2xl overflow-hidden z-50"
                >
                  {moreNavItems.map((item) => {
                    const active = isActive(item.href);
                    return (
                      <Link key={item.label} to={item.href}>
                        <motion.div
                          onClick={() => setShowMore(false)}
                          className={`flex items-center gap-3 px-4 py-3 transition-all ${
                            active
                              ? 'bg-gradient-to-r from-blue-500/30 to-purple-500/30 text-blue-400'
                              : 'text-muted-foreground hover:bg-white/5'
                          }`}
                        >
                          {item.icon}
                          <span className="text-sm font-medium">{item.label}</span>
                          {item.badge && (
                            <span className="ml-auto bg-gradient-to-r from-red-500 to-pink-500 text-white text-xs font-bold px-2 py-0.5 rounded-full">
                              {item.badge}
                            </span>
                          )}
                        </motion.div>
                      </Link>
                    );
                  })}
                </motion.div>
              )}
            </div>
          )}
        </div>
      </motion.div>
    </>
  );
}
