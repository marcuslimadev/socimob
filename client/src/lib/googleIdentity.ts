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
  if (typeof window === 'undefined' || !window.google?.accounts?.id || !clientId) {
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
  if (typeof window === 'undefined' || !window.google?.accounts?.id) {
    return false;
  }

  element.innerHTML = '';
  window.google.accounts.id.renderButton(element, options);
  return true;
};