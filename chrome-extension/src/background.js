// Background Service Worker — Socimob Atendimento 360

chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
  // ── Sessão ──────────────────────────────────────────────────────────────────

  if (request.action === 'getSessionData') {
    chrome.storage.local.get(['sessionToken', 'socimobUrl', 'userEmail'], (data) => {
      sendResponse(data);
    });
    return true;
  }

  if (request.action === 'saveSessionData') {
    chrome.storage.local.set({
      sessionToken: request.sessionToken,
      socimobUrl: request.socimobUrl,
      userEmail: request.userEmail,
      expiresAt: Date.now() + 24 * 60 * 60 * 1000,
    }, () => sendResponse({ success: true }));
    return true;
  }

  // Captura automática de sessão vinda do CRM (content script)
  if (request.action === 'autoSaveSession') {
    chrome.storage.local.get(['sessionToken'], (existing) => {
      if (existing.sessionToken === request.sessionToken) {
        sendResponse({ success: true, unchanged: true });
        return;
      }
      chrome.storage.local.set({
        sessionToken: request.sessionToken,
        socimobUrl: request.socimobUrl,
        userEmail: request.userEmail,
        expiresAt: Date.now() + 24 * 60 * 60 * 1000,
        autoCapture: true,
      }, () => {
        // Notifica o side panel para reinicializar com a nova sessão
        chrome.runtime.sendMessage({ action: 'sessionRestored', userEmail: request.userEmail }).catch(() => {});
        sendResponse({ success: true });
      });
    });
    return true;
  }

  if (request.action === 'clearSessionData') {
    chrome.storage.local.remove(
      ['sessionToken', 'socimobUrl', 'userEmail', 'expiresAt', 'autoCapture'],
      () => sendResponse({ success: true })
    );
    return true;
  }

  // ── Lead selecionado no CRM ──────────────────────────────────────────────────

  if (request.action === 'socimobLeadSelected') {
    chrome.storage.local.set({ selectedSocimobLead: request.lead }, () => {
      // Abre o side panel na aba ativa do WhatsApp Web se estiver aberta
      if (sender.tab?.id) {
        chrome.sidePanel?.open({ tabId: sender.tab.id }).catch(() => {});
      }
      // Encaminha para o side panel caso esteja aberto
      chrome.runtime.sendMessage({ action: 'leadSelectedFromCrm', lead: request.lead }).catch(() => {});
      sendResponse({ success: true });
    });
    return true;
  }

  // ── Conversa atualizada (WhatsApp Web → content script → background) ─────────

  if (request.action === 'conversationUpdated') {
    const tabId = sender.tab?.id;
    if (tabId) {
      // Armazena conversa atual por aba para o side panel consultar
      chrome.storage.local.set({ [`conversation_tab_${tabId}`]: request.data });
    }
    // Encaminha ao side panel (pode falhar silenciosamente se fechado)
    chrome.runtime.sendMessage({ action: 'conversationUpdated', data: request.data }).catch(() => {});
    sendResponse({ success: true });
    return true;
  }
});

// Abre o side panel ao clicar no ícone (funciona em qualquer página)
chrome.action.onClicked.addListener((tab) => {
  if (chrome.sidePanel?.open) {
    chrome.sidePanel.open({ tabId: tab.id });
  }
});

// Verifica expiração de sessão a cada 5 minutos
chrome.alarms.create('checkSessionExpiry', { periodInMinutes: 5 });

chrome.alarms.onAlarm.addListener((alarm) => {
  if (alarm.name !== 'checkSessionExpiry') return;

  chrome.storage.local.get(['expiresAt'], (data) => {
    if (data.expiresAt && Date.now() > data.expiresAt) {
      chrome.storage.local.remove(['sessionToken', 'socimobUrl', 'userEmail', 'expiresAt', 'autoCapture']);
      chrome.runtime.sendMessage({ action: 'sessionExpired' }).catch(() => {});
    }
  });
});
