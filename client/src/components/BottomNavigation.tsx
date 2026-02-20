import { motion } from 'framer-motion';
import { useLocation, Link } from 'wouter';
import {
  BarChart3,
  Users,
  Home,
  MessageSquare,
  Bell,
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
    { icon: <BarChart3 size={24} />, label: 'Dashboard', href: '/dashboard' },
    { icon: <Users size={24} />, label: 'CRM', href: '/crm' },
    { icon: <Home size={24} />, label: 'Imóveis', href: '/properties' },
    { icon: <Bell size={24} />, label: 'Alertas', href: '/notifications' },
  ];

  const moreNavItems: NavItem[] = [
    { icon: <Bell size={24} />, label: 'Notificações', href: '/notifications', badge: 5 },
  ];

  const isActive = (href: string) => location === href || (href !== '/dashboard' && location.startsWith(href));

  return (
    <>
      {/* Bottom Navigation - Mobile Only */}
      <motion.div
        initial={{ y: 100 }}
        animate={{ y: 0 }}
        className="md:hidden fixed bottom-0 left-0 right-0 bg-gradient-to-t from-gray-900 via-gray-900 to-gray-900/95 border-t border-white/10 backdrop-blur-xl z-40 safe-area-inset-bottom"
      >
        <div className="flex items-center justify-between px-2 py-3">
          {mainNavItems.map((item) => {
            const active = isActive(item.href);
            return (
              <Link key={item.label} to={item.href}>
                <motion.div
                  whileTap={{ scale: 0.9 }}
                  className={`flex flex-col items-center justify-center px-3 py-2 rounded-xl transition-all relative ${
                    active
                      ? 'bg-gradient-to-br from-blue-500/30 to-purple-500/30'
                      : 'hover:bg-white/5'
                  }`}
                >
                  <div className={`${active ? 'text-blue-400' : 'text-muted-foreground'}`}>
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
                  <span className={`text-[10px] mt-1 font-medium ${active ? 'text-blue-400' : 'text-muted-foreground'}`}>
                    {item.label}
                  </span>
                </motion.div>
              </Link>
            );
          })}

          {/* More Menu */}
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
        </div>
      </motion.div>

      {/* Safe area padding for mobile */}
      <div className="md:hidden h-20" />
    </>
  );
}
