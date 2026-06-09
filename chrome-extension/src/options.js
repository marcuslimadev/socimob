// Options script para a extensão Socimob

document.addEventListener('DOMContentLoaded', () => {
  loadSettings();
  setupEventListeners();
});

function loadSettings() {
  chrome.storage.local.get(
    ['consentAccepted', 'notificationsEnabled', 'errorNotificationsEnabled', 'sessionTimeout'],
    (data) => {
      document.getElementById('consentCheckbox').checked = data.consentAccepted || false;
      document.getElementById('notificationsCheckbox').checked = data.notificationsEnabled !== false;
      document.getElementById('errorNotificationsCheckbox').checked = data.errorNotificationsEnabled !== false;
      document.getElementById('sessionTimeout').value = data.sessionTimeout || 1440;
    }
  );
}

function setupEventListeners() {
  const saveConsentBtn = document.getElementById('saveConsentBtn');
  const saveSettingsBtn = document.getElementById('saveSettingsBtn');
  const resetSettingsBtn = document.getElementById('resetSettingsBtn');
  const clearDataBtn = document.getElementById('clearDataBtn');

  saveConsentBtn.addEventListener('click', saveConsent);
  saveSettingsBtn.addEventListener('click', saveSettings);
  resetSettingsBtn.addEventListener('click', resetSettings);
  clearDataBtn.addEventListener('click', clearData);
}

function saveConsent() {
  const consentAccepted = document.getElementById('consentCheckbox').checked;

  chrome.storage.local.set({ consentAccepted }, () => {
    showStatus('Consentimento salvo com sucesso!', 'success');
  });
}

function saveSettings() {
  const notificationsEnabled = document.getElementById('notificationsCheckbox').checked;
  const errorNotificationsEnabled = document.getElementById('errorNotificationsCheckbox').checked;
  const sessionTimeout = parseInt(document.getElementById('sessionTimeout').value);

  chrome.storage.local.set(
    {
      notificationsEnabled,
      errorNotificationsEnabled,
      sessionTimeout,
    },
    () => {
      showStatus('Configurações salvas com sucesso!', 'success');
    }
  );
}

function resetSettings() {
  if (confirm('Tem certeza que deseja restaurar as configurações padrão?')) {
    chrome.storage.local.clear(() => {
      loadSettings();
      showStatus('Configurações restauradas para os padrões.', 'success');
    });
  }
}

function clearData() {
  if (confirm('Tem certeza que deseja limpar todos os dados locais? Esta ação não pode ser desfeita.')) {
    chrome.storage.local.clear(() => {
      showStatus('Dados locais limpos com sucesso!', 'success');
      loadSettings();
    });
  }
}

function showStatus(message, type) {
  const statusMessage = document.getElementById('statusMessage');
  statusMessage.textContent = message;
  statusMessage.className = `status ${type}`;
  statusMessage.style.display = 'block';

  if (type === 'success') {
    setTimeout(() => {
      statusMessage.style.display = 'none';
    }, 3000);
  }
}
