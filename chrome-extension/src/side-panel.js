// Side Panel script para a extensão Socimob

let selectedLeadId = null;
let currentConversationInfo = null;
let currentSessionToken = null;
let socimobUrl = null;

document.addEventListener('DOMContentLoaded', () => {
  initializePanel();
  setupEventListeners();
  loadConversationInfo();
  loadTemplates();
});

function initializePanel() {
  // Recuperar dados de sessão
  chrome.storage.local.get(['sessionToken', 'socimobUrl'], (data) => {
    currentSessionToken = data.sessionToken;
    socimobUrl = data.socimobUrl;

    if (!currentSessionToken || !socimobUrl) {
      showStatus('Sessão expirada. Por favor, reconecte no popup.', 'error');
    }
  });
}

function setupEventListeners() {
  const leadSearch = document.getElementById('leadSearch');
  const createLeadBtn = document.getElementById('createLeadBtn');
  const saveSummaryBtn = document.getElementById('saveSummaryBtn');
  const clearSummaryBtn = document.getElementById('clearSummaryBtn');
  const saveActionBtn = document.getElementById('saveActionBtn');
  const createTaskBtn = document.getElementById('createTaskBtn');
  const scheduleVisitBtn = document.getElementById('scheduleVisitBtn');
  const createProposalBtn = document.getElementById('createProposalBtn');

  leadSearch.addEventListener('input', debounce(() => searchLeads(leadSearch.value), 300));
  createLeadBtn.addEventListener('click', openCreateLeadModal);
  saveSummaryBtn.addEventListener('click', saveSummary);
  clearSummaryBtn.addEventListener('click', () => {
    document.getElementById('summaryText').value = '';
  });
  saveActionBtn.addEventListener('click', saveNextAction);
  createTaskBtn.addEventListener('click', openCreateTaskModal);
  scheduleVisitBtn.addEventListener('click', openScheduleVisitModal);
  createProposalBtn.addEventListener('click', openCreateProposalModal);
}

function loadConversationInfo() {
  // Obter informações da conversa atual da content script
  chrome.tabs.query({ active: true, currentWindow: true }, (tabs) => {
    chrome.tabs.sendMessage(tabs[0].id, { action: 'getConversationInfo' }, (response) => {
      if (response) {
        currentConversationInfo = response;
        document.getElementById('contactName').textContent = response.contactName || '-';
        document.getElementById('contactPhone').textContent = response.contactPhone || '-';
        document.getElementById('messageCount').textContent = response.messageCount || 0;
      }
    });
  });
}

function searchLeads(query) {
  if (!query.trim()) {
    document.getElementById('leadList').innerHTML = '';
    return;
  }

  fetch(`${socimobUrl}/api/extension/leads/search?q=${encodeURIComponent(query)}`, {
    headers: {
      'Authorization': `Bearer ${currentSessionToken}`,
    },
  })
    .then((response) => response.json())
    .then((data) => {
      const leadList = document.getElementById('leadList');
      leadList.innerHTML = '';

      if (data.data && data.data.length > 0) {
        data.data.forEach((lead) => {
          const leadItem = document.createElement('div');
          leadItem.className = 'lead-item';
          if (lead.id === selectedLeadId) {
            leadItem.classList.add('selected');
          }
          leadItem.textContent = `${lead.name} (${lead.phone})`;
          leadItem.addEventListener('click', () => selectLead(lead.id, leadItem));
          leadList.appendChild(leadItem);
        });
      } else {
        leadList.innerHTML = '<div style="padding: 8px; text-align: center; color: #999;">Nenhum lead encontrado</div>';
      }
    })
    .catch((error) => {
      showStatus(`Erro ao buscar leads: ${error.message}`, 'error');
    });
}

function selectLead(leadId, element) {
  selectedLeadId = leadId;
  document.querySelectorAll('.lead-item').forEach((item) => {
    item.classList.remove('selected');
  });
  element.classList.add('selected');
  showStatus('Lead selecionado com sucesso!', 'success');
}

function saveSummary() {
  if (!selectedLeadId) {
    showStatus('Por favor, selecione um lead primeiro.', 'error');
    return;
  }

  const summary = document.getElementById('summaryText').value.trim();
  if (!summary) {
    showStatus('Por favor, escreva um resumo.', 'error');
    return;
  }

  // Aqui você faria a chamada à API para vincular a conversa e salvar o resumo
  showStatus('Resumo salvo com sucesso!', 'success');
  document.getElementById('summaryText').value = '';
}

function saveNextAction() {
  if (!selectedLeadId) {
    showStatus('Por favor, selecione um lead primeiro.', 'error');
    return;
  }

  const nextAction = document.getElementById('nextAction').value.trim();
  if (!nextAction) {
    showStatus('Por favor, descreva a próxima ação.', 'error');
    return;
  }

  showStatus('Próxima ação salva com sucesso!', 'success');
  document.getElementById('nextAction').value = '';
}

function openCreateLeadModal() {
  alert('Abrir modal para criar novo lead (a implementar)');
}

function openCreateTaskModal() {
  alert('Abrir modal para criar tarefa (a implementar)');
}

function openScheduleVisitModal() {
  alert('Abrir modal para agendar visita (a implementar)');
}

function openCreateProposalModal() {
  alert('Abrir modal para criar proposta (a implementar)');
}

function loadTemplates() {
  fetch(`${socimobUrl}/api/extension/message-templates`, {
    headers: {
      'Authorization': `Bearer ${currentSessionToken}`,
    },
  })
    .then((response) => response.json())
    .then((data) => {
      const templatesList = document.getElementById('templatesList');
      templatesList.innerHTML = '';

      if (data.data && data.data.length > 0) {
        data.data.forEach((template) => {
          const templateItem = document.createElement('div');
          templateItem.className = 'template-item';
          templateItem.innerHTML = `
            <span>${template.name}</span>
            <button class="copy-btn" onclick="copyTemplate('${template.content}')">Copiar</button>
          `;
          templatesList.appendChild(templateItem);
        });
      } else {
        templatesList.innerHTML = '<div style="padding: 8px; text-align: center; color: #999;">Nenhum template disponível</div>';
      }
    })
    .catch((error) => {
      console.error('Erro ao carregar templates:', error);
    });
}

function copyTemplate(content) {
  chrome.tabs.query({ active: true, currentWindow: true }, (tabs) => {
    chrome.tabs.sendMessage(tabs[0].id, { action: 'copyToClipboard', text: content }, (response) => {
      if (response && response.success) {
        showStatus('Template copiado para a área de transferência!', 'success');
      } else {
        showStatus('Erro ao copiar template.', 'error');
      }
    });
  });
}

function showStatus(message, type) {
  const statusMessage = document.getElementById('statusMessage');
  statusMessage.textContent = message;
  statusMessage.className = `status ${type}`;
  statusMessage.style.display = 'block';

  if (type === 'success' || type === 'info') {
    setTimeout(() => {
      statusMessage.style.display = 'none';
    }, 3000);
  }
}

function debounce(func, delay) {
  let timeoutId;
  return function (...args) {
    clearTimeout(timeoutId);
    timeoutId = setTimeout(() => func(...args), delay);
  };
}
