// Side Panel script para a extensão Socimob

let selectedLeadId = null;
let selectedLeadData = null;
let currentConversationInfo = null;
let currentSessionToken = null;
let socimobUrl = null;

document.addEventListener('DOMContentLoaded', () => {
  initializePanel(() => {
    loadSelectedLeadFromStorage();
    loadTemplates();
  });
  setupEventListeners();
  loadConversationInfo();
});

function initializePanel(onReady) {
  // Recuperar dados de sessão
  chrome.storage.local.get(['sessionToken', 'socimobUrl'], (data) => {
    currentSessionToken = data.sessionToken;
    socimobUrl = data.socimobUrl;

    if (!currentSessionToken || !socimobUrl) {
      showStatus('Sessão expirada. Por favor, reconecte no popup.', 'error');
    }

    if (typeof onReady === 'function') onReady();
  });
}

function setupEventListeners() {
  const leadSearch = document.getElementById('leadSearch');
  const leadDropZone = document.getElementById('leadDropZone');
  const createLeadBtn = document.getElementById('createLeadBtn');
  const pullCurrentBtn = document.getElementById('pullCurrentBtn');
  const linkConversationBtn = document.getElementById('linkConversationBtn');
  const saveSummaryBtn = document.getElementById('saveSummaryBtn');
  const clearSummaryBtn = document.getElementById('clearSummaryBtn');
  const saveActionBtn = document.getElementById('saveActionBtn');
  const createTaskBtn = document.getElementById('createTaskBtn');
  const scheduleVisitBtn = document.getElementById('scheduleVisitBtn');
  const createProposalBtn = document.getElementById('createProposalBtn');

  leadSearch.addEventListener('input', debounce(() => searchLeads(leadSearch.value), 300));
  pullCurrentBtn.addEventListener('click', pullCurrentContext);
  linkConversationBtn.addEventListener('click', linkCurrentConversation);
  createLeadBtn.addEventListener('click', openCreateLeadModal);
  saveSummaryBtn.addEventListener('click', saveSummary);
  clearSummaryBtn.addEventListener('click', () => {
    document.getElementById('summaryText').value = '';
  });
  saveActionBtn.addEventListener('click', saveNextAction);
  createTaskBtn.addEventListener('click', openCreateTaskModal);
  scheduleVisitBtn.addEventListener('click', openScheduleVisitModal);
  createProposalBtn.addEventListener('click', openCreateProposalModal);

  leadDropZone.addEventListener('dragover', (event) => {
    event.preventDefault();
    leadDropZone.classList.add('drag-over');
  });

  leadDropZone.addEventListener('dragleave', () => {
    leadDropZone.classList.remove('drag-over');
  });

  leadDropZone.addEventListener('drop', (event) => {
    event.preventDefault();
    leadDropZone.classList.remove('drag-over');
    const raw = event.dataTransfer.getData('application/x-socimob-lead') ||
      event.dataTransfer.getData('application/json');

    if (!raw) {
      showStatus('Não encontrei dados de lead no item solto.', 'error');
      return;
    }

    try {
      applySelectedLead(JSON.parse(raw), 'Lead recebido por arrastar e soltar');
    } catch (error) {
      showStatus('Não foi possível ler o lead arrastado.', 'error');
    }
  });
}

chrome.storage.onChanged.addListener((changes, areaName) => {
  if (areaName === 'local' && changes.selectedSocimobLead?.newValue) {
    applySelectedLead(changes.selectedSocimobLead.newValue, 'Lead recebido do CRM');
  }
});

chrome.runtime.onMessage.addListener((request) => {
  if (request.action === 'conversationUpdated' && request.data) {
    setConversationInfo(request.data);
  }
});

function loadConversationInfo() {
  // Obter informações da conversa atual da content script
  chrome.tabs.query({ active: true, currentWindow: true }, (tabs) => {
    chrome.tabs.sendMessage(tabs[0].id, { action: 'getConversationInfo' }, (response) => {
      if (response) {
        setConversationInfo(response);
      }
    });
  });
}

function setConversationInfo(response) {
  currentConversationInfo = response;
  document.getElementById('contactName').textContent = response.contactName || '-';
  document.getElementById('contactPhone').textContent = response.contactPhone || '-';
  document.getElementById('messageCount').textContent = response.messageCount || 0;
}

function loadSelectedLeadFromStorage() {
  chrome.storage.local.get(['selectedSocimobLead'], (data) => {
    if (data.selectedSocimobLead) {
      applySelectedLead(data.selectedSocimobLead, null);
    }
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
          const normalizedLead = normalizeLead(lead);
          const leadItem = document.createElement('div');
          leadItem.className = 'lead-item';
          if (normalizedLead.id === selectedLeadId) {
            leadItem.classList.add('selected');
          }
          leadItem.textContent = `${normalizedLead.name} (${normalizedLead.phone || 'sem telefone'})`;
          leadItem.addEventListener('click', () => selectLead(normalizedLead, leadItem));
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

function normalizeLead(lead) {
  return {
    id: Number(lead.id || lead.leadId),
    leadId: Number(lead.leadId || lead.id),
    name: lead.name || lead.nome || 'Lead sem nome',
    phone: lead.phone || lead.telefone || lead.whatsapp || '',
    email: lead.email || null,
    propertyId: lead.propertyId || lead.property_id || null,
    source: lead.source || 'extension_search',
  };
}

function selectLead(lead, element) {
  applySelectedLead(lead, 'Lead selecionado com sucesso');
  document.querySelectorAll('.lead-item').forEach((item) => {
    item.classList.remove('selected');
  });
  if (element) element.classList.add('selected');
}

function applySelectedLead(lead, successMessage) {
  const normalizedLead = normalizeLead(lead);
  if (!normalizedLead.id) {
    showStatus('Lead inválido: id não encontrado.', 'error');
    return;
  }

  selectedLeadId = normalizedLead.id;
  selectedLeadData = normalizedLead;
  chrome.storage.local.set({ selectedSocimobLead: normalizedLead });

  const leadSearch = document.getElementById('leadSearch');
  const selectedLeadCard = document.getElementById('selectedLeadCard');
  leadSearch.value = normalizedLead.name || normalizedLead.phone || '';
  selectedLeadCard.style.display = 'block';
  selectedLeadCard.innerHTML = `
    <strong>${escapeHtml(normalizedLead.name)}</strong>
    <span>${escapeHtml(normalizedLead.phone || 'Telefone não informado')}</span>
    ${normalizedLead.email ? `<span>${escapeHtml(normalizedLead.email)}</span>` : ''}
  `;

  if (successMessage) showStatus(successMessage, 'success');
}

function pullCurrentContext() {
  loadConversationInfo();
  loadSelectedLeadFromStorage();

  if (currentConversationInfo?.contactPhone || currentConversationInfo?.contactName) {
    const term = currentConversationInfo.contactPhone || currentConversationInfo.contactName;
    document.getElementById('leadSearch').value = term;
    searchLeads(term);
    showStatus('Conversa aberta puxada para busca de lead.', 'info');
    return;
  }

  showStatus('Abra uma conversa no WhatsApp Web ou envie um lead pelo CRM.', 'info');
}

function linkCurrentConversation() {
  if (!selectedLeadId || !selectedLeadData) {
    showStatus('Selecione ou envie um lead primeiro.', 'error');
    return;
  }

  if (!currentConversationInfo?.url || !currentConversationInfo.url.includes('web.whatsapp.com')) {
    showStatus('Abra a conversa no WhatsApp Web para vincular.', 'error');
    return;
  }

  const payload = {
    lead_id: selectedLeadId,
    property_id: selectedLeadData.propertyId || null,
    contact_name: currentConversationInfo.contactName || selectedLeadData.name,
    contact_phone: currentConversationInfo.contactPhone || selectedLeadData.phone,
    whatsapp_chat_identifier: currentConversationInfo.url,
    source: 'chrome_extension',
  };

  fetch(`${socimobUrl}/api/extension/conversations/link`, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${currentSessionToken}`,
      'Content-Type': 'application/json',
      'Accept': 'application/json',
    },
    body: JSON.stringify(payload),
  })
    .then(async (response) => {
      const data = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(data.message || 'Erro ao vincular conversa');
      showStatus(data.message || 'Conversa vinculada ao lead.', 'success');
    })
    .catch((error) => {
      showStatus(error.message, 'error');
    });
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

function escapeHtml(value) {
  return String(value || '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#039;');
}
