// Popup script para a extensão Socimob

document.addEventListener('DOMContentLoaded', () => {
  const loginSection = document.getElementById('loginSection');
  const authenticatedSection = document.getElementById('authenticatedSection');
  const loginBtn = document.getElementById('loginBtn');
  const logoutBtn = document.getElementById('logoutBtn');
  const openPanelBtn = document.getElementById('openPanelBtn');
  const statusMessage = document.getElementById('statusMessage');
  const userEmailDisplay = document.getElementById('userEmail');

  // Verificar se o usuário já está autenticado
  chrome.storage.local.get(['sessionToken', 'userEmail', 'socimobUrl'], (data) => {
    if (data.sessionToken && data.userEmail) {
      showAuthenticatedUI(data.userEmail);
    } else {
      showLoginUI();
    }
  });

  // Evento de login
  loginBtn.addEventListener('click', async () => {
    const socimobUrl = document.getElementById('socimobUrl').value.trim();
    const email = document.getElementById('email').value.trim();
    const password = document.getElementById('password').value;

    if (!socimobUrl || !email || !password) {
      showStatus('Por favor, preencha todos os campos.', 'error');
      return;
    }

    try {
      showStatus('Conectando...', 'info');
      loginBtn.disabled = true;

      // Fazer login na API do Socimob
      const response = await fetch(`${socimobUrl}/api/auth/login`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({ email, password }),
      });

      if (!response.ok) {
        throw new Error('Falha na autenticação. Verifique suas credenciais.');
      }

      const data = await response.json();
      const sessionToken = data.token || data.access_token;

      if (!sessionToken) {
        throw new Error('Token não recebido do servidor.');
      }

      // Salvar dados de sessão
      chrome.storage.local.set({
        sessionToken,
        userEmail: email,
        socimobUrl,
        expiresAt: Date.now() + (24 * 60 * 60 * 1000), // 24 horas
      }, () => {
        showStatus('Conectado com sucesso!', 'success');
        showAuthenticatedUI(email);
      });
    } catch (error) {
      showStatus(`Erro: ${error.message}`, 'error');
      loginBtn.disabled = false;
    }
  });

  // Evento de logout
  logoutBtn.addEventListener('click', () => {
    chrome.storage.local.remove(['sessionToken', 'userEmail', 'socimobUrl', 'expiresAt'], () => {
      showLoginUI();
      showStatus('Desconectado com sucesso.', 'success');
    });
  });

  // Evento para abrir o painel lateral
  openPanelBtn.addEventListener('click', () => {
    chrome.tabs.query({ active: true, currentWindow: true }, (tabs) => {
      if (tabs[0].url.includes('web.whatsapp.com')) {
        chrome.sidePanel.open({ tabId: tabs[0].id });
      } else {
        showStatus('Por favor, abra o WhatsApp Web primeiro.', 'error');
      }
    });
  });

  function showLoginUI() {
    loginSection.style.display = 'flex';
    authenticatedSection.style.display = 'none';
    logoutBtn.style.display = 'none';
  }

  function showAuthenticatedUI(email) {
    loginSection.style.display = 'none';
    authenticatedSection.style.display = 'block';
    logoutBtn.style.display = 'block';
    userEmailDisplay.textContent = `Conectado como: ${email}`;
  }

  function showStatus(message, type) {
    statusMessage.textContent = message;
    statusMessage.className = `status ${type}`;
    statusMessage.style.display = 'block';

    if (type === 'success' || type === 'info') {
      setTimeout(() => {
        statusMessage.style.display = 'none';
      }, 3000);
    }
  }
});
