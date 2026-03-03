type ConsentState = 'granted' | 'denied' | null;

const CONSENT_KEY = 'analytics_consent';
const CONSENT_DATE_KEY = 'analytics_consent_at';
const SESSION_KEY = 'analytics_session_id';

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

export const trackEvent = (event: string, properties?: Record<string, any>) => {
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
    return;
  }

  fetch('/api/analytics/collect', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body,
    keepalive: true,
  }).catch(() => undefined);
};

export const trackPageView = () => trackEvent('pageview');
