type GoogleCredentialResponse = {
  credential?: string;
};

type GoogleButtonOptions = {
  theme?: 'outline' | 'filled_blue' | 'filled_black';
  size?: 'large' | 'medium' | 'small';
  width?: number;
  text?: 'signin_with' | 'signup_with' | 'continue_with' | 'signin';
  locale?: string;
};

type GoogleIdentityState = {
  clientId?: string;
  callback?: (response: GoogleCredentialResponse) => void;
};

const LOCAL_GOOGLE_IDENTITY_ORIGINS = new Set([
  'http://localhost:3000',
  'http://localhost:5173',
  'http://127.0.0.1:3000',
  'http://127.0.0.1:5173',
]);

const getConfiguredGoogleOrigins = (): string[] => {
  const rawOrigins = String(import.meta.env.VITE_GOOGLE_ALLOWED_ORIGINS || '').trim();
  if (!rawOrigins) {
    return [];
  }

  return rawOrigins
    .split(',')
    .map((origin) => origin.trim())
    .filter(Boolean);
};

export const isGoogleIdentitySupportedOrigin = (): boolean => {
  if (typeof window === 'undefined') {
    return false;
  }

  const currentOrigin = window.location.origin;
  const configuredOrigins = getConfiguredGoogleOrigins();

  if (configuredOrigins.length > 0) {
    return configuredOrigins.includes(currentOrigin);
  }

  return LOCAL_GOOGLE_IDENTITY_ORIGINS.has(currentOrigin);
};

export const getGoogleIdentityUnavailableMessage = (): string => {
  return 'Login com Google indisponível neste domínio no momento.';
};

declare global {
  interface Window {
    google?: any;
    __socimobGoogleIdentity?: GoogleIdentityState;
  }
}

const getGoogleIdentityState = (): GoogleIdentityState => {
  if (typeof window === 'undefined') {
    return {};
  }

  window.__socimobGoogleIdentity ??= {};
  return window.__socimobGoogleIdentity;
};

export const initializeGoogleIdentity = (
  clientId: string,
  callback: (response: GoogleCredentialResponse) => void
) => {
  if (
    typeof window === 'undefined' ||
    !window.google?.accounts?.id ||
    !clientId ||
    !isGoogleIdentitySupportedOrigin()
  ) {
    return false;
  }

  const state = getGoogleIdentityState();
  state.callback = callback;

  if (state.clientId === clientId) {
    return true;
  }

  window.google.accounts.id.initialize({
    client_id: clientId,
    callback: (response: GoogleCredentialResponse) => {
      getGoogleIdentityState().callback?.(response);
    },
  });

  state.clientId = clientId;
  return true;
};

export const renderGoogleIdentityButton = (
  element: HTMLDivElement,
  options: GoogleButtonOptions
) => {
  if (
    typeof window === 'undefined' ||
    !window.google?.accounts?.id ||
    !isGoogleIdentitySupportedOrigin()
  ) {
    return false;
  }

  element.innerHTML = '';
  window.google.accounts.id.renderButton(element, options);
  return true;
};