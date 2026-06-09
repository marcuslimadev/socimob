// Background Service Worker para a extensão Socimob Atendimento 360

// Listener para mensagens da content script e popup
chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
  if (request.action === 'getSessionData') {
    chrome.storage.local.get(['sessionToken', 'socimobUrl', 'userEmail'], (data) => {
      sendResponse(data);
    });
    return true; // Indica que a resposta será enviada de forma assíncrona
  }

  if (request.action === 'saveSessionData') {
    chrome.storage.local.set({
      sessionToken: request.sessionToken,
      socimobUrl: request.socimobUrl,
      userEmail: request.userEmail,
      expiresAt: Date.now() + (24 * 60 * 60 * 1000), // 24 horas
    }, () => {
      sendResponse({ success: true });
    });
    return true;
  }

  if (request.action === 'clearSessionData') {
    chrome.storage.local.remove(['sessionToken', 'socimobUrl', 'userEmail', 'expiresAt'], () => {
      sendResponse({ success: true });
    });
    return true;
  }
});

// Listener para abrir o side panel quando clicado no ícone da extensão
chrome.action.onClicked.addListener((tab) => {
  if (tab.url.includes('web.whatsapp.com')) {
    chrome.sidePanel.open({ tabId: tab.id });
  }
});

// Verificar expiração de sessão periodicamente
chrome.alarms.create('checkSessionExpiry', { periodInMinutes: 5 });

chrome.alarms.onAlarm.addListener((alarm) => {
  if (alarm.name === 'checkSessionExpiry') {
    chrome.storage.local.get(['expiresAt'], (data) => {
      if (data.expiresAt && Date.now() > data.expiresAt) {
        chrome.storage.local.remove(['sessionToken', 'socimobUrl', 'userEmail', 'expiresAt']);
        // Notificar popup/side-panel que a sessão expirou
        chrome.runtime.sendMessage({ action: 'sessionExpired' }).catch(() => {
          // Silenciar erros se nenhum listener estiver pronto
        });
      }
    });
  }
});
