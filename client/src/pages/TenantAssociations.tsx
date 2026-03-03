import { useEffect, useMemo, useState } from 'react';
import { Loader2, Save, Link2 } from 'lucide-react';
import { toast } from 'sonner';
import Sidebar from '@/components/Sidebar';
import { api } from '@/lib/api';

interface TenantItem {
  id: number;
  name: string;
  domain?: string;
  slug?: string;
  is_active?: boolean;
}

type AssociationsMap = Record<string, number[]>;

export default function TenantAssociations() {
  const [tenants, setTenants] = useState<TenantItem[]>([]);
  const [associations, setAssociations] = useState<AssociationsMap>({});
  const [selectedTenantId, setSelectedTenantId] = useState<number | null>(null);
  const [selectedRelatedIds, setSelectedRelatedIds] = useState<number[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);

  const selectedTenant = useMemo(
    () => tenants.find((tenant) => tenant.id === selectedTenantId) || null,
    [tenants, selectedTenantId],
  );

  useEffect(() => {
    const load = async () => {
      try {
        setIsLoading(true);
        const response = await api.get('/super-admin/tenant-associations');
        const nextTenants: TenantItem[] = response.data?.tenants || [];
        const nextAssociations: AssociationsMap = response.data?.associations || {};
        setTenants(nextTenants);
        setAssociations(nextAssociations);

        if (nextTenants.length > 0) {
          const firstTenantId = nextTenants[0].id;
          setSelectedTenantId(firstTenantId);
          setSelectedRelatedIds(nextAssociations[String(firstTenantId)] || []);
        }
      } catch (error) {
        console.error('Erro ao carregar associacoes de tenants:', error);
        toast.error('Erro ao carregar associações de tenants');
      } finally {
        setIsLoading(false);
      }
    };

    load();
  }, []);

  const handleSelectTenant = (tenantId: number) => {
    setSelectedTenantId(tenantId);
    setSelectedRelatedIds(associations[String(tenantId)] || []);
  };

  const handleToggleRelated = (tenantId: number, checked: boolean) => {
    setSelectedRelatedIds((prev) => {
      if (checked) return Array.from(new Set([...prev, tenantId]));
      return prev.filter((id) => id !== tenantId);
    });
  };

  const handleSave = async () => {
    if (!selectedTenantId) return;

    try {
      setIsSaving(true);
      await api.put(`/super-admin/tenant-associations/${selectedTenantId}`, {
        associated_tenant_ids: selectedRelatedIds,
      });

      setAssociations((prev) => ({
        ...prev,
        [String(selectedTenantId)]: selectedRelatedIds,
      }));

      toast.success('Associações salvas com sucesso');
    } catch (error: any) {
      console.error('Erro ao salvar associacoes:', error);
      
      let errorMessage = 'Erro ao salvar associações';
      
      if (error?.response?.data?.messages) {
        const messages = error.response.data.messages;
        Object.entries(messages).forEach(([field, errors]: [string, any]) => {
          const errorList = Array.isArray(errors) ? errors : [errors];
          errorMessage += `\n${field}: ${errorList.join(', ')}`;
        });
      } else if (error?.response?.data?.message) {
        errorMessage = error.response.data.message;
      } else if (error?.response?.data?.error) {
        errorMessage = error.response.data.error;
      }
      
      toast.error(errorMessage);
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <div className="flex">
      <Sidebar />

      <div className="page-shell">
        <div className="max-w-5xl mx-auto space-y-6">
          <div>
            <h1 className="page-title mb-2 flex items-center gap-3">
              <Link2 size={36} />
              Associação de Tenants
            </h1>
            <p className="page-subtitle">
              Defina quais tenants podem compartilhar imóveis entre seus portais públicos.
            </p>
          </div>

          {isLoading ? (
            <div className="glass-panel rounded-2xl p-8 flex items-center justify-center">
              <Loader2 className="w-6 h-6 animate-spin" />
            </div>
          ) : (
            <div className="glass-panel rounded-2xl p-6 space-y-6">
              <div>
                <label className="block text-sm font-semibold mb-2">Tenant principal</label>
                <select
                  value={selectedTenantId ?? ''}
                  onChange={(event) => handleSelectTenant(Number(event.target.value))}
                  className="w-full px-4 py-3 bg-white/10 border border-white/20 rounded-lg"
                >
                  {tenants.map((tenant) => (
                    <option key={tenant.id} value={tenant.id}>
                      {tenant.name} ({tenant.domain || tenant.slug || tenant.id})
                    </option>
                  ))}
                </select>
              </div>

              <div>
                <p className="text-sm font-semibold mb-2">Pode compartilhar com</p>
                <div className="grid gap-2">
                  {tenants
                    .filter((tenant) => tenant.id !== selectedTenantId)
                    .map((tenant) => {
                      const checked = selectedRelatedIds.includes(tenant.id);
                      return (
                        <label key={tenant.id} className="flex items-center gap-3 rounded-lg border border-white/10 px-3 py-2 cursor-pointer hover:bg-white/5">
                          <input
                            type="checkbox"
                            checked={checked}
                            onChange={(event) => handleToggleRelated(tenant.id, event.target.checked)}
                            className="w-4 h-4"
                          />
                          <div>
                            <p className="text-sm">{tenant.name}</p>
                            <p className="text-xs text-muted-foreground">{tenant.domain || tenant.slug || '-'}</p>
                          </div>
                        </label>
                      );
                    })}
                </div>

                {selectedTenant ? (
                  <p className="mt-3 text-xs text-muted-foreground">
                    Tenant atual: {selectedTenant.name}. Se marcado, imóveis poderão aparecer em ambos os portais.
                  </p>
                ) : null}
              </div>

              <button
                type="button"
                onClick={handleSave}
                disabled={!selectedTenantId || isSaving}
                className="inline-flex items-center gap-2 rounded-lg bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 disabled:opacity-60"
              >
                {isSaving ? <Loader2 size={16} className="animate-spin" /> : <Save size={16} />}
                Salvar associações
              </button>
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
