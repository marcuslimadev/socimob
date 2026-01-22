import { useState } from 'react';
import { motion } from 'framer-motion';
import { useLocation } from 'wouter';
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
} from 'lucide-react';

interface SidebarItem {
  icon: React.ReactNode;
  label: string;
  href: string;
  badge?: number;
  isActive?: boolean;
}

const Sidebar = () => {
  const [isOpen, setIsOpen] = useState(true);
  const [isCollapsed, setIsCollapsed] = useState(false);

  const menuItems: SidebarItem[] = [
    { icon: <BarChart3 size={24} />, label: 'Dashboard', href: '#', isActive: true },
    { icon: <Users size={24} />, label: 'Leads', href: '#', badge: 12 },
    { icon: <Home size={24} />, label: 'Imóveis', href: '#' },
    { icon: <MessageSquare size={24} />, label: 'Chat', href: '#', badge: 3 },
    { icon: <Bell size={24} />, label: 'Notificações', href: '#', badge: 5 },
    { icon: <Settings size={24} />, label: 'Configurações', href: '#' },
  ];

  const sidebarVariants = {
    expanded: { width: 280 },
    collapsed: { width: 80 },
  };

  const itemVariants = {
    hidden: { opacity: 0, x: -20 },
    visible: { opacity: 1, x: 0 },
  };

  return (
    <>
      <button
        onClick={() => setIsOpen(!isOpen)}
        className="md:hidden fixed top-4 left-4 z-50 glass-panel p-2 hover:bg-white/20"
      >
        {isOpen ? <X size={24} /> : <Menu size={24} />}
      </button>

      <motion.div
        animate={isCollapsed ? 'collapsed' : 'expanded'}
        variants={sidebarVariants}
        className="hidden md:flex fixed left-0 top-0 h-screen glass-panel m-4 rounded-3xl flex-col justify-between py-6 z-40"
      >
        <div className="flex flex-col items-center gap-4">
          <motion.div
            initial={{ scale: 0, rotate: -180 }}
            animate={{ scale: 1, rotate: 0 }}
            transition={{ type: 'spring', stiffness: 200 }}
            className="w-12 h-12 rounded-xl bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-bold text-xl glow-md"
          >
            S
          </motion.div>

          {!isCollapsed && (
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              transition={{ delay: 0.2 }}
              className="text-center"
            >
              <h1 className="font-bold text-lg gradient-text">SOCIMOB</h1>
              <p className="text-xs text-muted-foreground">v2.0</p>
            </motion.div>
          )}

          <div className="blur-divider w-full" />
        </div>

        <nav className="flex-1 flex flex-col gap-2 px-2">
          {menuItems.map((item, index) => (
            <motion.a
              key={item.label}
              href={item.href}
              initial="hidden"
              animate="visible"
              transition={{ delay: index * 0.05 }}
              variants={itemVariants}
              className={`relative group flex items-center gap-3 px-4 py-3 rounded-xl transition-all duration-300 ${
                item.isActive
                  ? 'bg-gradient-to-r from-blue-500/30 to-purple-500/30 text-white'
                  : 'text-muted-foreground hover:bg-white/10'
              }`}
            >
              {item.isActive && (
                <motion.div
                  layoutId="activeIndicator"
                  className="absolute left-0 top-0 bottom-0 w-1 bg-gradient-to-b from-blue-500 to-purple-600 rounded-r-lg"
                  transition={{ type: 'spring', stiffness: 200 }}
                />
              )}

              <div className={`relative ${item.isActive ? 'glow-sm' : ''}`}>
                {item.icon}
              </div>

              {!isCollapsed && (
                <motion.div
                  initial={{ opacity: 0, width: 0 }}
                  animate={{ opacity: 1, width: 'auto' }}
                  exit={{ opacity: 0, width: 0 }}
                  className="flex-1 flex items-center justify-between"
                >
                  <span className="font-medium">{item.label}</span>
                  {item.badge && (
                    <motion.span
                      initial={{ scale: 0 }}
                      animate={{ scale: 1 }}
                      className="bg-gradient-to-r from-red-500 to-pink-500 text-white text-xs font-bold px-2 py-1 rounded-full"
                    >
                      {item.badge}
                    </motion.span>
                  )}
                </motion.div>
              )}

              <motion.div
                initial={{ opacity: 0, x: -10 }}
                whileHover={{ opacity: 1, x: 0 }}
                className="absolute right-4 opacity-0 group-hover:opacity-100"
              >
                <ChevronRight size={16} />
              </motion.div>
            </motion.a>
          ))}
        </nav>

        <div className="flex flex-col gap-2 px-2">
          <div className="blur-divider w-full" />

          <motion.button
            onClick={() => setIsCollapsed(!isCollapsed)}
            className="flex items-center gap-3 px-4 py-3 rounded-xl text-muted-foreground hover:bg-white/10 transition-all duration-300"
            whileHover={{ scale: 1.05 }}
            whileTap={{ scale: 0.95 }}
          >
            <Menu size={20} />
            {!isCollapsed && <span className="text-sm font-medium">Recolher</span>}
          </motion.button>

          <motion.button
            onClick={() => {
              localStorage.removeItem('token');
              localStorage.removeItem('user');
              window.location.href = '/login';
            }}
            className="flex items-center gap-3 px-4 py-3 rounded-xl text-destructive hover:bg-destructive/10 transition-all duration-300"
            whileHover={{ scale: 1.05 }}
            whileTap={{ scale: 0.95 }}
          >
            <LogOut size={20} />
            {!isCollapsed && <span className="text-sm font-medium">Sair</span>}
          </motion.button>
        </div>
      </motion.div>

      {isOpen && (
        <motion.div
          initial={{ x: -300 }}
          animate={{ x: 0 }}
          exit={{ x: -300 }}
          className="md:hidden fixed left-0 top-0 h-screen w-64 glass-panel z-40 flex flex-col py-6 px-4"
        >
          <div className="flex items-center gap-3 mb-6">
            <div className="w-10 h-10 rounded-lg bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-white font-bold">
              S
            </div>
            <h1 className="font-bold gradient-text">SOCIMOB</h1>
          </div>

          <nav className="flex-1 flex flex-col gap-2">
            {menuItems.map((item) => (
              <a
                key={item.label}
                href={item.href}
                className={`flex items-center gap-3 px-4 py-3 rounded-xl transition-all ${
                  item.isActive
                    ? 'bg-gradient-to-r from-blue-500/30 to-purple-500/30 text-white'
                    : 'text-muted-foreground hover:bg-white/10'
                }`}
              >
                {item.icon}
                <span className="font-medium">{item.label}</span>
                {item.badge && (
                  <span className="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full">
                    {item.badge}
                  </span>
                )}
              </a>
            ))}
          </nav>

          <button 
            onClick={() => {
              localStorage.removeItem('token');
              localStorage.removeItem('user');
              window.location.href = '/login';
            }}
            className="flex items-center gap-3 px-4 py-3 rounded-xl text-destructive hover:bg-destructive/10 w-full"
          >
            <LogOut size={20} />
            <span className="font-medium">Sair</span>
          </button>
        </motion.div>
      )}
    </>
  );
};

export default Sidebar;
