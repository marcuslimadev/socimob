/**
 * AdsPublishToggle — Componente de publicação de anúncio.
 *
 * Uso:
 *   <AdsPublishToggle listingId={property.id} />
 *
 * Exibe o status atual do anúncio por provider (Meta, Google) e
 * permite publicar/despublicar com um toggle.
 */
import { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import axios from 'axios';
import { api } from '@/lib/api';
import { toast } from 'sonner';
import { Zap, CheckCircle2, XCircle, AlertTriangle, Loader2, ExternalLink } from 'lucide-react';
import { Switch } from '@/components/ui/switch';
import { Label } from '@/components/ui/label';

interface AdsListingStatus {
  provider: string;
  publish_status: string;
  external_item_id: string | null;
  last_sync_at: string | null;
  last_error: string | null;
}

interface Props {
  listingId: number | string;
  compact?: boolean; // modo compacto para o form
}

const STATUS_LABELS: Record<string, { label: string; color: string }> = {
  ACTIVE:     { label: 'Ativo',       color: 'text-green-600' },
  PUBLISHING: { label: 'Publicando…', color: 'text-blue-500'  },
  PAUSED:     { label: 'Pausado',     color: 'text-amber-500' },
  DRAFT:      { label: 'Rascunho',    color: 'text-gray-400'  },
  ERROR:      { label: 'Erro',        color: 'text-red-500'   },
};

function providerLabel(provider: string) {
  return provider === 'meta' ? 'Meta (FB/IG)' : provider === 'google' ? 'Google Ads' : provider;
}

function StatusIcon({ status }: { status: string }) {
  if (status === 'ACTIVE')     return <CheckCircle2 className="h-3.5 w-3.5 text-green-500" />;
  if (status === 'PUBLISHING') return <Loader2 className="h-3.5 w-3.5 text-blue-500 animate-spin" />;
  if (status === 'ERROR')      return <AlertTriangle className="h-3.5 w-3.5 text-red-500" />;
  if (status === 'PAUSED')     return <XCircle className="h-3.5 w-3.5 text-amber-500" />;
  return null;
}

export function AdsPublishToggle({ listingId, compact = false }: Props) {
  const queryClient = useQueryClient();

  const { data: adsData, isLoading } = useQuery<AdsListingStatus[] | null>({
    queryKey: ['ads-listing-status', listingId],
    queryFn: async () => {
      try {
        const r = await api.get(`/listings/${listingId}/ads/status`);
        return r.data.data ?? [];
      } catch (error) {
        if (axios.isAxiosError(error) && error.response?.status === 404) {
          return null;
        }

        throw error;
      }
    },
    enabled: !!listingId,
    retry: (failureCount, error) => {
      if (axios.isAxiosError(error) && error.response?.status === 404) {
        return false;
      }

      return failureCount < 3;
    },
    refetchInterval: (query) => (query.state.data === null ? false : 10000),
  });

  const adsModuleUnavailable = adsData === null;

  const publishMutation = useMutation({
    mutationFn: async (provider: string) => {
      const r = await api.post(`/listings/${listingId}/ads/publish`, { provider });
      return r.data;
    },
    onSuccess: (data) => {
      toast.success(data.message ?? 'Publicação iniciada!');
      queryClient.invalidateQueries({ queryKey: ['ads-listing-status', listingId] });
    },
    onError: (err: any) => {
      toast.error(err?.response?.data?.error ?? 'Erro ao publicar anúncio.');
    },
  });

  const unpublishMutation = useMutation({
    mutationFn: async (provider: string) => {
      const r = await api.post(`/listings/${listingId}/ads/unpublish`, { provider });
      return r.data;
    },
    onSuccess: () => {
      toast.success('Anúncio pausado.');
      queryClient.invalidateQueries({ queryKey: ['ads-listing-status', listingId] });
    },
    onError: (err: any) => {
      toast.error(err?.response?.data?.error ?? 'Erro ao pausar anúncio.');
    },
  });

  const getProviderStatus = (provider: string): AdsListingStatus | undefined =>
    adsData?.find(s => s.provider === provider);

  const isPublished = (provider: string) => {
    const s = getProviderStatus(provider);
    return s?.publish_status === 'ACTIVE' || s?.publish_status === 'PUBLISHING';
  };

  const handleToggle = (provider: string, newValue: boolean) => {
    if (adsModuleUnavailable) {
      return;
    }

    if (newValue) {
      publishMutation.mutate(provider);
    } else {
      unpublishMutation.mutate(provider);
    }
  };

  if (isLoading && !adsData) {
    return (
      <div className="flex items-center gap-2 text-xs text-gray-400">
        <Loader2 className="h-3.5 w-3.5 animate-spin" />
        Carregando status…
      </div>
    );
  }

  if (adsModuleUnavailable) {
    return null;
  }

  if (compact) {
    const metaStatus = getProviderStatus('meta');
    const isMetaActive = isPublished('meta');

    return (
      <div className="flex items-center gap-3 py-1">
        <Zap className="h-4 w-4 text-yellow-500 flex-shrink-0" />
        <div className="flex-1">
          <Label className="text-sm font-medium text-gray-700 cursor-pointer">
            Publicar anúncio
          </Label>
          {metaStatus?.publish_status && metaStatus.publish_status !== 'DRAFT' && (
            <p className={`text-xs ${STATUS_LABELS[metaStatus.publish_status]?.color ?? 'text-gray-400'}`}>
              Meta: {STATUS_LABELS[metaStatus.publish_status]?.label}
              {metaStatus.last_error && ` — ${metaStatus.last_error}`}
            </p>
          )}
        </div>
        <Switch
          checked={isMetaActive}
          onCheckedChange={(v) => handleToggle('meta', v)}
          disabled={publishMutation.isPending || unpublishMutation.isPending || metaStatus?.publish_status === 'PUBLISHING'}
        />
      </div>
    );
  }

  return (
    <div className="space-y-3">
      <div className="flex items-center gap-2">
        <Zap className="h-4 w-4 text-yellow-500" />
        <span className="text-sm font-medium text-gray-800">Anúncios automáticos</span>
      </div>

      {(['meta', 'google'] as const).map(provider => {
        const status = getProviderStatus(provider);
        const active = isPublished(provider);
        const isBusy = publishMutation.isPending || unpublishMutation.isPending || status?.publish_status === 'PUBLISHING';

        return (
          <div key={provider} className="flex items-center justify-between p-3 border border-gray-100 rounded-lg bg-gray-50">
            <div className="flex items-center gap-2 min-w-0">
              <StatusIcon status={status?.publish_status ?? 'DRAFT'} />
              <div className="min-w-0">
                <p className="text-sm font-medium text-gray-700">{providerLabel(provider)}</p>
                {status && status.publish_status !== 'DRAFT' ? (
                  <p className={`text-xs ${STATUS_LABELS[status.publish_status]?.color ?? 'text-gray-400'}`}>
                    {STATUS_LABELS[status.publish_status]?.label}
                    {status.last_sync_at && ` · sync ${new Date(status.last_sync_at).toLocaleString('pt-BR', { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })}`}
                  </p>
                ) : (
                  <p className="text-xs text-gray-400">Não publicado</p>
                )}
                {status?.last_error && (
                  <p className="text-xs text-red-500 truncate max-w-xs">{status.last_error}</p>
                )}
              </div>
            </div>
            <Switch
              checked={active}
              onCheckedChange={(v) => handleToggle(provider, v)}
              disabled={isBusy}
            />
          </div>
        );
      })}

      <p className="text-xs text-gray-400">
        Ao ativar, o imóvel é sincronizado com o catálogo e o anúncio entra em exibição automaticamente.
        Leads captados aparecem no seu CRM.
      </p>
    </div>
  );
}
