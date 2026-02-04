import { useEffect, useState } from 'react';
import { getConsent, setConsent, trackEvent } from '@/lib/analytics';

export default function AnalyticsConsentBanner() {
  const [visible, setVisible] = useState(false);

  useEffect(() => {
    const consent = getConsent();
    setVisible(consent === null);
  }, []);

  if (!visible) return null;

  return (
    <div className="fixed inset-x-0 bottom-0 z-50 bg-card border-t border-border px-4 py-3">
      <div className="max-w-6xl mx-auto flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div className="text-sm text-foreground">
          Usamos cookies para estatísticas e melhoria do produto. Você pode aceitar ou recusar.
        </div>
        <div className="flex items-center gap-2">
          <button
            type="button"
            className="px-4 py-2 rounded-md bg-muted text-foreground hover:bg-muted/80 transition-colors text-sm"
            onClick={() => {
              setConsent('denied');
              setVisible(false);
            }}
          >
            Recusar
          </button>
          <button
            type="button"
            className="px-4 py-2 rounded-md bg-primary text-primary-foreground hover:bg-primary/90 transition-colors text-sm"
            onClick={() => {
              setConsent('granted');
              setVisible(false);
              trackEvent('consent_granted');
            }}
          >
            Aceitar
          </button>
        </div>
      </div>
    </div>
  );
}
