import { useState, useEffect } from 'react';
import { Menu, X } from 'lucide-react';
import { motion, AnimatePresence } from 'framer-motion';

interface TenantConfig {
  name: string;
  logo?: string;
}

interface MobileHeaderProps {
  onMenuToggle: (isOpen: boolean) => void;
  isMenuOpen: boolean;
}

export default function MobileHeader({ onMenuToggle, isMenuOpen }: MobileHeaderProps) {
  const [tenant, setTenant] = useState<TenantConfig | null>(null);

  useEffect(() => {
    const loadTenant = async () => {
      try {
        const response = await fetch('/api/tenant/config', {
          credentials: 'include'
        });

        if (response.ok) {
          const data = await response.json();
          if (data.success && data.tenant) {
            setTenant({
              name: data.tenant.name,
              logo: data.tenant.logo_url
            });
          }
        }
      } catch (error) {
        console.error('Erro ao carregar tenant:', error);
      }
    };

    loadTenant();
  }, []);

  return (
    <div className="md:hidden fixed top-0 left-0 right-0 h-16 bg-gradient-to-r from-gray-900 to-black backdrop-blur-lg border-b border-purple-500/30 z-50 flex items-center justify-between px-4 shadow-lg">
      {/* Logo e Nome */}
      <div className="flex items-center gap-3">
        {tenant?.logo ? (
          <img
            src={tenant.logo}
            alt={tenant.name}
            className="h-8 w-auto object-contain"
          />
        ) : (
          <div className="h-8 px-3 bg-gradient-to-r from-blue-500 to-purple-500 rounded-lg flex items-center justify-center">
            <span className="text-white font-bold text-sm">
              {tenant?.name?.charAt(0) || 'S'}
            </span>
          </div>
        )}
        <span className="font-bold text-foreground text-lg truncate max-w-[150px]">
          {tenant?.name || 'SOCIMOB'}
        </span>
      </div>

      {/* Menu Button */}
      <motion.button
        whileTap={{ scale: 0.95 }}
        onClick={() => onMenuToggle(!isMenuOpen)}
        className="p-2 rounded-xl bg-white/10 hover:bg-white/20 transition-colors"
        aria-label="Toggle menu"
      >
        <AnimatePresence mode="wait">
          {isMenuOpen ? (
            <motion.div
              key="close"
              initial={{ rotate: -90, opacity: 0 }}
              animate={{ rotate: 0, opacity: 1 }}
              exit={{ rotate: 90, opacity: 0 }}
              transition={{ duration: 0.2 }}
            >
              <X size={24} className="text-foreground" />
            </motion.div>
          ) : (
            <motion.div
              key="menu"
              initial={{ rotate: 90, opacity: 0 }}
              animate={{ rotate: 0, opacity: 1 }}
              exit={{ rotate: -90, opacity: 0 }}
              transition={{ duration: 0.2 }}
            >
              <Menu size={24} className="text-foreground" />
            </motion.div>
          )}
        </AnimatePresence>
      </motion.button>
    </div>
  );
}
