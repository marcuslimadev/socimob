import { useState, useEffect } from 'react';
import { Building2, ChevronDown, Check } from 'lucide-react';
import { motion, AnimatePresence } from 'framer-motion';
import { api } from '@/lib/api';

interface Tenant {
  id: number;
  name: string;
  domain: string;
  logo_url: string | null;
}

interface TenantSelectorProps {
  isSuperAdmin: boolean;
}

export default function TenantSelector({ isSuperAdmin }: TenantSelectorProps) {
  const [tenants, setTenants] = useState<Tenant[]>([]);
  const [selectedTenantId, setSelectedTenantId] = useState<number | null>(null);
  const [currentTenant, setCurrentTenant] = useState<Tenant | null>(null);
  const [isOpen, setIsOpen] = useState(false);
  const [isLoading, setIsLoading] = useState(false);

  useEffect(() => {
    if (isSuperAdmin) {
      loadTenants();
      loadCurrentSelection();
    }
  }, [isSuperAdmin]);

  const loadTenants = async () => {
    try {
      const response = await api.get('/super-admin/tenants');
      if (response.data?.tenants) {
        setTenants(response.data.tenants);
      }
    } catch (error) {
      console.error('Erro ao carregar tenants:', error);
    }
  };

  const loadCurrentSelection = () => {
    const savedTenantId = localStorage.getItem('superadmin_view_as_tenant');
    if (savedTenantId) {
      const tenantId = parseInt(savedTenantId);
      setSelectedTenantId(tenantId);
    }
  };

  useEffect(() => {
    if (selectedTenantId && tenants.length > 0) {
      const tenant = tenants.find(t => t.id === selectedTenantId);
      setCurrentTenant(tenant || null);
    } else {
      setCurrentTenant(null);
    }
  }, [selectedTenantId, tenants]);

  const handleSelectTenant = (tenantId: number | null) => {
    if (tenantId === null) {
      localStorage.removeItem('superadmin_view_as_tenant');
      setSelectedTenantId(null);
      setIsOpen(false);
      window.location.reload(); // Reload to reset views
    } else {
      localStorage.setItem('superadmin_view_as_tenant', tenantId.toString());
      setSelectedTenantId(tenantId);
      setIsOpen(false);
      window.location.reload(); // Reload to apply new tenant context
    }
  };

  if (!isSuperAdmin) {
    return null;
  }

  return (
    <div className="relative">
      <motion.button
        whileHover={{ scale: 1.02 }}
        whileTap={{ scale: 0.98 }}
        onClick={() => setIsOpen(!isOpen)}
        className="w-full px-4 py-2 bg-gradient-to-r from-purple-500/20 to-blue-500/20 hover:from-purple-500/30 hover:to-blue-500/30 rounded-lg border border-purple-500/30 flex items-center gap-3 transition-all"
      >
        <Building2 size={18} className="text-purple-400" />
        <div className="flex-1 text-left">
          <div className="text-xs text-purple-300 font-semibold">Visualizando como:</div>
          <div className="text-sm text-foreground font-medium truncate">
            {currentTenant ? currentTenant.name : 'Visão Super Admin'}
          </div>
        </div>
        <ChevronDown
          size={16}
          className={`text-muted-foreground transition-transform ${isOpen ? 'rotate-180' : ''}`}
        />
      </motion.button>

      <AnimatePresence>
        {isOpen && (
          <motion.div
            initial={{ opacity: 0, y: -10 }}
            animate={{ opacity: 1, y: 0 }}
            exit={{ opacity: 0, y: -10 }}
            className="absolute top-full left-0 right-0 mt-2 glass-panel rounded-lg border border-white/10 shadow-xl max-h-[400px] overflow-y-auto z-50"
          >
            {/* Reset option */}
            <button
              onClick={() => handleSelectTenant(null)}
              className={`w-full px-4 py-3 text-left hover:bg-white/10 transition-colors flex items-center gap-3 ${
                !selectedTenantId ? 'bg-purple-500/20' : ''
              }`}
            >
              <div className="w-8 h-8 rounded-lg bg-gradient-to-br from-purple-500 to-blue-600 flex items-center justify-center">
                <Building2 size={16} className="text-white" />
              </div>
              <div className="flex-1">
                <div className="text-sm font-semibold text-foreground">Visão Super Admin</div>
                <div className="text-xs text-muted-foreground">Ver todos os tenants</div>
              </div>
              {!selectedTenantId && <Check size={16} className="text-green-400" />}
            </button>

            <div className="border-t border-white/10 my-1"></div>

            {/* Tenant list */}
            {tenants.map((tenant) => (
              <button
                key={tenant.id}
                onClick={() => handleSelectTenant(tenant.id)}
                className={`w-full px-4 py-3 text-left hover:bg-white/10 transition-colors flex items-center gap-3 ${
                  selectedTenantId === tenant.id ? 'bg-blue-500/20' : ''
                }`}
              >
                {tenant.logo_url ? (
                  <img
                    src={tenant.logo_url}
                    alt={tenant.name}
                    className="w-8 h-8 rounded-lg object-contain bg-white/10"
                  />
                ) : (
                  <div className="w-8 h-8 rounded-lg bg-gradient-to-br from-blue-500 to-purple-600 flex items-center justify-center text-xs font-bold text-white">
                    {tenant.name.substring(0, 2).toUpperCase()}
                  </div>
                )}
                <div className="flex-1">
                  <div className="text-sm font-semibold text-foreground">{tenant.name}</div>
                  <div className="text-xs text-muted-foreground">{tenant.domain}</div>
                </div>
                {selectedTenantId === tenant.id && (
                  <Check size={16} className="text-green-400" />
                )}
              </button>
            ))}
          </motion.div>
        )}
      </AnimatePresence>
    </div>
  );
}
