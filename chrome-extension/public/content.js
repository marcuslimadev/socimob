// Content Script — Socimob Atendimento 360

const isWhatsAppWeb = window.location.hostname === 'web.whatsapp.com';
const isSocimobCrm = /(^|\.)exclusivalarimoveis\.com$/.test(window.location.hostname) ||
  /(^|\.)lojadaesquina\.store$/.test(window.location.hostname) ||
  ['localhost', '127.0.0.1'].includes(window.location.hostname);

// ===== CRM: CAPTURA AUTOMÁTICA DE SESSÃO =====
if (isSocimobCrm) {
  // Escuta leads enviados do CRM via evento customizado
  window.addEventListener('socimob:sendLeadToExtension', (event) => {
    const lead = event.detail;
    if (!lead || !lead.id) return;
    chrome.storage.local.set({ selectedSocimobLead: lead }, () => {
      chrome.runtime.sendMessage({ action: 'socimobLeadSelected', lead }).catch(() => {});
    });
  });

  // Captura o token do localStorage e envia para o background
  function tryCaptureSession() {
    const token = localStorage.getItem('token');
    if (!token) return;

    let userEmail = '';
    try {
      const user = JSON.parse(localStorage.getItem('user') || 'null');
      userEmail = user?.email || '';
    } catch { /* ignore */ }

    chrome.runtime.sendMessage({
      action: 'autoSaveSession',
      sessionToken: token,
      socimobUrl: `${window.location.protocol}//${window.location.host}`,
      userEmail,
    }).catch(() => {});
  }

  // Captura imediata ao carregar a página
  tryCaptureSession();

  // Monitora mudanças no localStorage (login/logout)
  const _origSetItem = localStorage.setItem.bind(localStorage);
  localStorage.setItem = function (key, value) {
    _origSetItem(key, value);
    if (key === 'token' || key === 'user') {
      setTimeout(tryCaptureSession, 200);
    }
  };

  const _origRemoveItem = localStorage.removeItem.bind(localStorage);
  localStorage.removeItem = function (key) {
    _origRemoveItem(key);
    if (key === 'token') {
      chrome.runtime.sendMessage({ action: 'clearSessionData' }).catch(() => {});
    }
  };
}

// ===== WHATSAPP WEB =====
if (!isWhatsAppWeb) return;

let lastKnownUrl = window.location.href;
let lastContactName = null;
let lastContactPhone = null;
let debounceTimer = null;

// Extrai telefone de um URL do WhatsApp Web
function extractPhoneFromUrl(url) {
  try {
    const u = new URL(url);
    const phone = u.searchParams.get('phone');
    if (phone) return phone;
  } catch { /* ignore */ }
  return null;
}

// Tenta extrair o número de telefone do texto de subtítulo
function parsePhoneFromText(text) {
  const cleaned = text.replace(/[\s\-\(\)\+]/g, '');
  return /^\d{8,15}$/.test(cleaned) ? text.trim() : null;
}

// Extrai dados completos da conversa aberta no WhatsApp Web
function extractConversationInfo() {
  try {
    // Seletores para o nome do contato (WhatsApp Web muda com frequência)
    const nameSelectors = [
      '#main header [data-testid="conversation-info-header-chat-title"]',
      '#main header [data-testid="chat-title-container"] span[title]',
      '#main header span[data-testid="chat-header-title-container"] span[title]',
      '#main header ._3ko75 span[title]',
      '#main header span[title]',
      '[data-testid="conversation-header-contact-name"]',
    ];

    let contactName = null;
    for (const sel of nameSelectors) {
      const el = document.querySelector(sel);
      if (el) {
        contactName = el.getAttribute('title') || el.textContent.trim();
        if (contactName) break;
      }
    }

    // Seletores para telefone/subtítulo
    const phoneSelectors = [
      '#main header [data-testid="conversation-info-header-chat-subtitle"]',
      '#main header [data-testid="chat-header-subtitle"]',
      '[data-testid="conversation-header-contact-phone"]',
    ];

    let contactPhone = extractPhoneFromUrl(window.location.href);

    if (!contactPhone) {
      for (const sel of phoneSelectors) {
        const el = document.querySelector(sel);
        if (el) {
          const parsed = parsePhoneFromText(el.textContent);
          if (parsed) { contactPhone = parsed; break; }
        }
      }
    }

    // Contagem de mensagens
    const msgElements = document.querySelectorAll('[data-testid="msg-container"]');
    const messageCount = msgElements.length;

    // Extrai as últimas 6 mensagens para o painel
    const recentMessages = [];
    const lastMsgs = Array.from(msgElements).slice(-6);
    for (const msgEl of lastMsgs) {
      const textEl = msgEl.querySelector('[data-testid="msg-text"]') ||
        msgEl.querySelector('span.selectable-text');
      if (!textEl) continue;

      const isOut = msgEl.querySelector('[data-testid="msg-dblcheck"]') !== null ||
        msgEl.querySelector('[data-testid="msg-check"]') !== null;

      recentMessages.push({
        direction: isOut ? 'out' : 'in',
        text: textEl.textContent.trim().slice(0, 300),
      });
    }

    return {
      contactName,
      contactPhone,
      messageCount,
      recentMessages,
      url: window.location.href,
      timestamp: new Date().toISOString(),
    };
  } catch (err) {
    console.error('[Socimob] Erro ao extrair info da conversa:', err);
    return null;
  }
}

// Notifica o background/side-panel sobre a conversa atual
function notifyConversationUpdated(force = false) {
  const info = extractConversationInfo();
  if (!info) return;

  const contactChanged = info.contactName !== lastContactName || info.contactPhone !== lastContactPhone;

  if (force || contactChanged) {
    lastContactName = info.contactName;
    lastContactPhone = info.contactPhone;
    chrome.runtime.sendMessage({ action: 'conversationUpdated', data: info }).catch(() => {});
  }
}

// Detecta mudança de conversa via URL (mais confiável que MutationObserver)
function pollUrlChange() {
  if (window.location.href !== lastKnownUrl) {
    lastKnownUrl = window.location.href;
    // Aguarda o DOM atualizar após a navegação
    setTimeout(() => notifyConversationUpdated(true), 900);
  }
}

// Listener para mensagens do background e side panel
chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
  if (request.action === 'getConversationInfo') {
    sendResponse(extractConversationInfo());
    return;
  }

  if (request.action === 'copyToClipboard') {
    navigator.clipboard.writeText(request.text).then(
      () => sendResponse({ success: true }),
      (err) => sendResponse({ success: false, error: err.message })
    );
    return true;
  }

  if (request.action === 'insertTextIntoInput') {
    // Insere texto no campo de digitação do WhatsApp Web
    const inputEl =
      document.querySelector('[data-testid="conversation-compose-box-input"]') ||
      document.querySelector('div[contenteditable="true"][data-tab]');

    if (inputEl) {
      inputEl.focus();
      document.execCommand('insertText', false, request.text);
      sendResponse({ success: true });
    } else {
      sendResponse({ success: false, error: 'Campo de mensagem não encontrado.' });
    }
    return;
  }
});

// Extração inicial (aguarda DOM carregar)
setTimeout(() => notifyConversationUpdated(true), 1500);

// Polling de URL para detectar troca de conversa
setInterval(pollUrlChange, 600);

// MutationObserver debounced para atualizações de contagem de mensagens
const observer = new MutationObserver(() => {
  clearTimeout(debounceTimer);
  debounceTimer = setTimeout(() => {
    const info = extractConversationInfo();
    if (!info) return;
    chrome.runtime.sendMessage({ action: 'conversationUpdated', data: info }).catch(() => {});
  }, 1500);
});

observer.observe(document.body, { childList: true, subtree: true });
