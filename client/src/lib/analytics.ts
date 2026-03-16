type ConsentState = 'granted' | 'denied' | null;

const CONSENT_KEY = 'analytics_consent';
const CONSENT_DATE_KEY = 'analytics_consent_at';
const SESSION_KEY = 'analytics_session_id';
const GA_MEASUREMENT_ID = import.meta.env.VITE_GA_MEASUREMENT_ID?.trim();

type GoogleAnalyticsEventValue = string | number | boolean;

declare global {
  interface Window {
    dataLayer?: unknown[];
    gtag?: (...args: unknown[]) => void;
  }
}

let googleAnalyticsLoadPromise: Promise<void> | null = null;

const getCookie = (name: string) => {
  const match = document.cookie.match(new RegExp(`(^| )${name}=([^;]+)`));
  return match ? decodeURIComponent(match[2]) : null;
};

const setCookie = (name: string, value: string, days: number) => {
  const maxAge = days * 24 * 60 * 60;
  document.cookie = `${name}=${encodeURIComponent(value)}; Max-Age=${maxAge}; Path=/; SameSite=Lax`;
};

const generateId = () => {
  if (crypto?.randomUUID) return crypto.randomUUID();
  return Math.random().toString(36).slice(2) + Date.now().toString(36);
};

const isGoogleAnalyticsEnabled = () => Boolean(GA_MEASUREMENT_ID);

const getGoogleConsentPayload = (state: Exclude<ConsentState, null>) => ({
  analytics_storage: state,
  ad_storage: 'denied',
  ad_user_data: 'denied',
  ad_personalization: 'denied',
});

const installGoogleAnalyticsStub = () => {
  window.dataLayer = window.dataLayer || [];

  if (!window.gtag) {
    window.gtag = (...args: unknown[]) => {
      window.dataLayer?.push(args);
    };
  }
};

const loadGoogleAnalytics = async () => {
  if (!isGoogleAnalyticsEnabled() || typeof window === 'undefined' || typeof document === 'undefined') {
    return;
  }

  if (googleAnalyticsLoadPromise) {
    return googleAnalyticsLoadPromise;
  }

  installGoogleAnalyticsStub();
  window.gtag?.('consent', 'default', getGoogleConsentPayload('denied'));
  window.gtag?.('js', new Date());
  window.gtag?.('config', GA_MEASUREMENT_ID, {
    send_page_view: false,
    anonymize_ip: true,
  });

  const existingScript = document.querySelector(`script[data-google-analytics="${GA_MEASUREMENT_ID}"]`);
  if (existingScript) {
    googleAnalyticsLoadPromise = Promise.resolve();
    return googleAnalyticsLoadPromise;
  }

  googleAnalyticsLoadPromise = new Promise((resolve, reject) => {
    const script = document.createElement('script');
    script.async = true;
    script.src = `https://www.googletagmanager.com/gtag/js?id=${encodeURIComponent(GA_MEASUREMENT_ID || '')}`;
    script.setAttribute('data-google-analytics', GA_MEASUREMENT_ID || '');
    script.onload = () => resolve();
    script.onerror = () => {
      googleAnalyticsLoadPromise = null;
      reject(new Error('Failed to load Google Analytics'));
    };
    document.head.appendChild(script);
  });

  return googleAnalyticsLoadPromise;
};

const updateGoogleAnalyticsConsent = (state: ConsentState) => {
  if (!isGoogleAnalyticsEnabled() || !state || typeof window === 'undefined') {
    return;
  }

  if (state === 'granted') {
    void loadGoogleAnalytics()
      .then(() => {
        window.gtag?.('consent', 'update', getGoogleConsentPayload(state));
      })
      .catch(() => undefined);
    return;
  }

  window.gtag?.('consent', 'update', getGoogleConsentPayload(state));
};

const normalizeGoogleAnalyticsValue = (value: unknown): GoogleAnalyticsEventValue | undefined => {
  if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean') {
    return value;
  }

  if (value instanceof Date) {
    return value.toISOString();
  }

  if (value === null || value === undefined) {
    return undefined;
  }

  try {
    return JSON.stringify(value);
  } catch {
    return String(value);
  }
};

const sendGoogleAnalyticsEvent = (event: string, properties?: Record<string, unknown>) => {
  if (!isGoogleAnalyticsEnabled() || getConsent() !== 'granted') {
    return;
  }

  const normalizedProperties = Object.entries(properties || {}).reduce<Record<string, GoogleAnalyticsEventValue>>(
    (accumulator, [key, value]) => {
      const normalizedValue = normalizeGoogleAnalyticsValue(value);
      if (normalizedValue !== undefined) {
        accumulator[key] = normalizedValue;
      }
      return accumulator;
    },
    {},
  );

  void loadGoogleAnalytics()
    .then(() => {
      window.gtag?.('event', event, normalizedProperties);
    })
    .catch(() => undefined);
};

export const getConsent = (): ConsentState => {
  const stored = localStorage.getItem(CONSENT_KEY) as ConsentState | null;
  if (stored === 'granted' || stored === 'denied') return stored;
  const cookie = getCookie(CONSENT_KEY) as ConsentState | null;
  if (cookie === 'granted' || cookie === 'denied') return cookie;
  return null;
};

export const setConsent = (value: ConsentState) => {
  if (!value) return;
  localStorage.setItem(CONSENT_KEY, value);
  localStorage.setItem(CONSENT_DATE_KEY, new Date().toISOString());
  setCookie(CONSENT_KEY, value, 180);
  updateGoogleAnalyticsConsent(value);
};

export const getConsentAt = () => {
  return localStorage.getItem(CONSENT_DATE_KEY) || new Date().toISOString();
};

export const getSessionId = () => {
  const existing = sessionStorage.getItem(SESSION_KEY);
  if (existing) return existing;
  const id = generateId();
  sessionStorage.setItem(SESSION_KEY, id);
  return id;
};

const detectDevice = () => {
  const ua = navigator.userAgent.toLowerCase();
  const isMobile = /iphone|android|mobile/.test(ua);
  const isTablet = /ipad|tablet/.test(ua);
  return isTablet ? 'tablet' : isMobile ? 'mobile' : 'desktop';
};

const detectOS = () => {
  const ua = navigator.userAgent.toLowerCase();
  if (ua.includes('windows')) return 'Windows';
  if (ua.includes('mac os')) return 'macOS';
  if (ua.includes('android')) return 'Android';
  if (ua.includes('iphone') || ua.includes('ipad')) return 'iOS';
  if (ua.includes('linux')) return 'Linux';
  return 'Other';
};

const detectBrowser = () => {
  const ua = navigator.userAgent.toLowerCase();
  if (ua.includes('edg/')) return 'Edge';
  if (ua.includes('chrome') && !ua.includes('chromium')) return 'Chrome';
  if (ua.includes('safari') && !ua.includes('chrome')) return 'Safari';
  if (ua.includes('firefox')) return 'Firefox';
  return 'Other';
};

export const initializeAnalytics = () => {
  updateGoogleAnalyticsConsent(getConsent());
};

export const trackEvent = (
  event: string,
  properties?: Record<string, any>,
  options?: { skipGoogleAnalytics?: boolean },
) => {
  const consent = getConsent();
  if (consent !== 'granted') return;

  const payload = {
    event,
    path: window.location.pathname,
    referrer: document.referrer || null,
    session_id: getSessionId(),
    consent: true,
    consent_at: getConsentAt(),
    device_type: detectDevice(),
    os: detectOS(),
    browser: detectBrowser(),
    properties: properties || null,
    user_id: (() => {
      try {
        const user = localStorage.getItem('user');
        return user ? JSON.parse(user)?.id : null;
      } catch {
        return null;
      }
    })(),
  };

  const body = JSON.stringify(payload);

  if (navigator.sendBeacon) {
    navigator.sendBeacon('/api/analytics/collect', new Blob([body], { type: 'application/json' }));
  } else {
    fetch('/api/analytics/collect', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body,
      keepalive: true,
    }).catch(() => undefined);
  }

  if (!options?.skipGoogleAnalytics) {
    sendGoogleAnalyticsEvent(event, properties);
  }
};

export const trackPageView = () => {
  trackEvent('pageview', undefined, { skipGoogleAnalytics: true });
  sendGoogleAnalyticsEvent('page_view', {
    page_title: document.title,
    page_location: window.location.href,
    page_path: `${window.location.pathname}${window.location.search}`,
  });
};
