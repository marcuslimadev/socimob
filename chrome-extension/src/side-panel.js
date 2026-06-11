// Side Panel — Socimob Atendimento 360

let selectedLeadId = null;
let selectedLeadData = null;
let linkedConversationId = null;
let currentConversationInfo = null;
let currentSessionToken = null;
let socimobUrl = null;
let tenantDomain = null;

// ── Inicialização ──────────────────────────────────────────────────────────────

document.addEventListener('DOMContentLoaded', () => {
  setupEventListeners();
  initializePanel();
});

async function initializePanel() {
  const data = await storageGet(['sessionToken', 'socimobUrl', 'userEmail', 'selectedSocimobLead']);
  currentSessionToken = data.sessionToken;
  socimobUrl = data.socimobUrl;

  if (!currentSessionToken || !socimobUrl) {
    showNoSession();
    return;
  }

  try {
    tenantDomain = new URL(socimobUrl).hostname;
  } catch { tenantDomain = ''; }

  const emailEl = document.getElementById('sessionEmail');
  if (emailEl) emailEl.textContent = data.userEmail || '';

  document.getElementById('panelContent').style.display = 'block';
  document.getElementById('noSession').style.display = 'none';

  // Lead já selecionado anteriormente
  if (data.selectedSocimobLead) {
    applySelectedLead(data.selectedSocimobLead, null);
  }

  // Carrega conversa atual e dispara auto-match
  await refreshConversationInfo();

  // Carrega templates de mensagem
  loadTemplates();
}

function showNoSession() {
  document.getElementById('noSession').style.display = 'flex';
  document.getElementById('panelContent').style.display = 'none';
}

// ── Event Listeners ────────────────────────────────────────────────────────────

function setupEventListeners() {
  on('leadSearch', 'input', debounce((e) => searchLeads(e.target.value), 300));
  on('pullCurrentBtn', 'click', pullCurrentContext);
  on('linkConversationBtn', 'click', linkCurrentConversation);
  on('createLeadBtn', 'click', showCreateLeadForm);
  on('cancelCreateLeadBtn', 'click', hideCreateLeadForm);
  on('confirmCreateLeadBtn', 'click', createLeadFromForm);
  on('saveSummaryBtn', 'click', saveSummary);
  on('clearSummaryBtn', 'click', () => { setVal('summaryText', ''); });
  on('saveActionBtn', 'click', saveNextAction);
  on('createTaskBtn', 'click', createTask);
  on('scheduleVisitBtn', 'click', scheduleVisit);
  on('openCrmBtn', 'click', () => { if (selectedLeadId) openCrmLead(selectedLeadId); });
  on('refreshConvBtn', 'click', async () => {
    showStatus('Atualizando...', 'info');
    await refreshConversationInfo();
  });

  // Drag & drop de lead do CRM
  const dropZone = document.getElementById('leadDropZone');
  if (dropZone) {
    dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('drag-over'); });
    dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
    dropZone.addEventListener('drop', handleDrop);
  }
}

// ── Listeners do chrome ────────────────────────────────────────────────────────

chrome.storage.onChanged.addListener((changes, area) => {
  if (area !== 'local') return;
  if (changes.selectedSocimobLead?.newValue) {
    applySelectedLead(changes.selectedSocimobLead.newValue, 'Lead recebido do CRM');
  }
  if (changes.sessionToken?.newValue && !currentSessionToken) {
    currentSessionToken = changes.sessionToken.newValue;
    chrome.storage.local.get(['socimobUrl', 'userEmail'], (d) => {
      socimobUrl = d.socimobUrl;
      try { tenantDomain = new URL(socimobUrl).hostname; } catch { tenantDomain = ''; }
      document.getElementById('noSession').style.display = 'none';
      document.getElementById('panelContent').style.display = 'block';
      const emailEl = document.getElementById('sessionEmail');
      if (emailEl) emailEl.textContent = d.userEmail || '';
      refreshConversationInfo();
      loadTemplates();
    });
  }
});

chrome.runtime.onMessage.addListener((request) => {
  if (request.action === 'conversationUpdated' && request.data) {
    const newInfo = request.data;
    const phoneChanged = newInfo.contactPhone && newInfo.contactPhone !== currentConversationInfo?.contactPhone;
    const nameChanged = newInfo.contactName && newInfo.contactName !== currentConversationInfo?.contactName;

    setConversationInfo(newInfo);

    if (phoneChanged || (nameChanged && !currentConversationInfo)) {
      // Troca de conversa: reinicia estado
      linkedConversationId = null;
      selectedLeadId = null;
      selectedLeadData = null;
      document.getElementById('selectedLeadCard').style.display = 'none';
      document.getElementById('linkedConversationInfo').style.display = 'none';
      document.getElementById('leadSearch').value = '';
      document.getElementById('leadList').innerHTML = '';
      chrome.storage.local.remove(['selectedSocimobLead']);
      autoFindLead(newInfo);
    }
  }
  if (request.action === 'sessionExpired') {
    currentSessionToken = null;
    showNoSession();
    showStatus('Sessão expirada. Faça login no CRM.', 'error');
  }
  if (request.action === 'sessionRestored') {
    chrome.storage.local.get(['sessionToken', 'socimobUrl', 'userEmail'], (d) => {
      currentSessionToken = d.sessionToken;
      socimobUrl = d.socimobUrl;
      try { tenantDomain = new URL(socimobUrl).hostname; } catch { tenantDomain = ''; }
      initializePanel();
    });
  }
  if (request.action === 'leadSelectedFromCrm') {
    applySelectedLead(request.lead, 'Lead recebido do CRM');
  }
});

// ── Conversa atual ─────────────────────────────────────────────────────────────

async function refreshConversationInfo() {
  const info = await getConversationFromTab();
  if (info) {
    setConversationInfo(info);
    await autoFindLead(info);
  }
}

function getConversationFromTab() {
  return new Promise((resolve) => {
    chrome.tabs.query({ active: true, currentWindow: true }, (tabs) => {
      const tab = tabs[0];
      if (!tab?.id || !tab.url?.includes('web.whatsapp.com')) { resolve(null); return; }
      chrome.tabs.sendMessage(tab.id, { action: 'getConversationInfo' }, (resp) => {
        if (chrome.runtime.lastError) { resolve(null); return; }
        resolve(resp || null);
      });
    });
  });
}

function setConversationInfo(info) {
  currentConversationInfo = info;
  setText('contactName', info.contactName || '-');
  setText('contactPhone', info.contactPhone || '-');
  setText('messageCount', info.messageCount || 0);

  const recentEl = document.getElementById('recentMessages');
  if (recentEl && info.recentMessages?.length > 0) {
    recentEl.innerHTML = info.recentMessages.map((m) => `
      <div class="msg-bubble msg-${m.direction === 'out' ? 'out' : 'in'}">
        ${escapeHtml(m.text)}
      </div>
    `).join('');
    recentEl.parentElement.style.display = 'block';
  } else if (recentEl) {
    recentEl.parentElement.style.display = 'none';
  }
}

// ── Auto-match de lead ─────────────────────────────────────────────────────────

async function autoFindLead(info) {
  if (!currentSessionToken || !socimobUrl) return;
  if (!info?.contactPhone && !info?.contactName) return;

  hideBanner();

  // 1. Verifica se já existe uma conversa vinculada para este URL
  if (info.url) {
    const existing = await apiFetch(`/api/extension/conversations/find?url=${enc(info.url)}`);
    if (existing?.data) {
      linkedConversationId = existing.data.id;
      if (existing.data.lead) {
        applySelectedLead(normalizeLead(existing.data.lead), null);
        renderLinkedConversation(existing.data);
        showBanner(
          `✅ Conversa já vinculada: <strong>${escapeHtml(existing.data.lead.nome || 'Lead')}</strong>`,
          'success'
        );
        return;
      }
    }
  }

  // 2. Busca por telefone ou nome
  const term = info.contactPhone || info.contactName;
  const result = await apiFetch(`/api/extension/leads/search?q=${enc(term)}`);
  const leads = result?.data || [];

  if (leads.length === 0) {
    showBanner(
      `➕ Nenhum lead para <strong>${escapeHtml(term)}</strong> — <a href="#" class="banner-link" id="quickCreateLink">Criar lead</a>`,
      'warning'
    );
    document.getElementById('quickCreateLink')?.addEventListener('click', (e) => {
      e.preventDefault();
      showCreateLeadForm();
      prefillCreateLeadForm(info);
    });
    prefillCreateLeadForm(info);
  } else if (leads.length === 1) {
    const matched = normalizeLead(leads[0]);
    window._autoMatchedLead = matched;
    showBanner(
      `🎯 Lead encontrado: <strong>${escapeHtml(matched.name)}</strong> — <a href="#" class="banner-link" id="autoLinkBtn">Vincular agora</a>`,
      'match'
    );
    document.getElementById('autoLinkBtn')?.addEventListener('click', async (e) => {
      e.preventDefault();
      applySelectedLead(matched, 'Lead selecionado');
      await linkCurrentConversation();
    });
    renderLeadList(leads);
  } else {
    showBanner(`🔍 ${leads.length} leads encontrados para <strong>${escapeHtml(term)}</strong>`, 'info');
    renderLeadList(leads);
    document.getElementById('leadSearch').value = term;
  }
}

// ── Busca manual de leads ──────────────────────────────────────────────────────

async function searchLeads(query) {
  const leadList = document.getElementById('leadList');
  if (!query.trim()) { leadList.innerHTML = ''; return; }
  try {
    const data = await apiFetch(`/api/extension/leads/search?q=${enc(query)}`);
    renderLeadList(data?.data || []);
  } catch (err) {
    showStatus(`Erro na busca: ${err.message}`, 'error');
  }
}

function renderLeadList(leads) {
  const leadList = document.getElementById('leadList');
  leadList.innerHTML = '';
  if (leads.length === 0) {
    leadList.innerHTML = '<div class="empty-list">Nenhum lead encontrado</div>';
    return;
  }
  leads.forEach((lead) => {
    const n = normalizeLead(lead);
    const item = document.createElement('div');
    item.className = `lead-item${n.id === selectedLeadId ? ' selected' : ''}`;
    item.innerHTML = `
      <div class="lead-item-name">${escapeHtml(n.name)}</div>
      <div class="lead-item-phone">${escapeHtml(n.phone || 'sem telefone')}</div>
    `;
    item.addEventListener('click', () => {
      selectLead(n, item);
    });
    leadList.appendChild(item);
  });
}

function selectLead(lead, element) {
  applySelectedLead(lead, 'Lead selecionado');
  document.querySelectorAll('.lead-item').forEach((i) => i.classList.remove('selected'));
  if (element) element.classList.add('selected');
}

function normalizeLead(lead) {
  return {
    id: Number(lead.id || lead.leadId),
    leadId: Number(lead.leadId || lead.id),
    name: lead.name || lead.nome || 'Lead sem nome',
    phone: lead.phone || lead.telefone || lead.whatsapp || '',
    email: lead.email || null,
    propertyId: lead.propertyId || lead.property_id || null,
    classificacao: lead.classificacao || lead.classification || null,
    source: lead.source || 'extension_search',
  };
}

function applySelectedLead(lead, successMessage) {
  const n = normalizeLead(lead);
  if (!n.id) { showStatus('Lead inválido.', 'error'); return; }

  selectedLeadId = n.id;
  selectedLeadData = n;
  chrome.storage.local.set({ selectedSocimobLead: n });

  document.getElementById('leadSearch').value = n.name || n.phone || '';

  const card = document.getElementById('selectedLeadCard');
  card.style.display = 'block';
  card.innerHTML = `
    <div class="lead-card-header">
      <div class="lead-card-info">
        <strong>${escapeHtml(n.name)}</strong>
        <span>${escapeHtml(n.phone || 'Sem telefone')}</span>
        ${n.email ? `<span>${escapeHtml(n.email)}</span>` : ''}
      </div>
      <button class="icon-btn" id="openCrmBtn" title="Abrir no CRM">↗</button>
    </div>
    <div class="qualif-row">
      <span class="qualif-label">Qualificação:</span>
      <button class="qualif-btn ${n.classificacao === 'frio' ? 'active' : ''}" data-status="frio">🧊 Frio</button>
      <button class="qualif-btn ${n.classificacao === 'morno' ? 'active' : ''}" data-status="morno">🌡 Morno</button>
      <button class="qualif-btn ${n.classificacao === 'quente' ? 'active' : ''}" data-status="quente">🔥 Quente</button>
    </div>
  `;

  card.querySelectorAll('.qualif-btn').forEach((btn) => {
    btn.addEventListener('click', () => updateLeadStatus(btn.dataset.status));
  });

  document.getElementById('openCrmBtn')?.addEventListener('click', () => openCrmLead(n.id));

  if (successMessage) showStatus(successMessage, 'success');
  hideBanner();
}

// ── Ações sobre o lead ─────────────────────────────────────────────────────────

async function updateLeadStatus(status) {
  if (!selectedLeadId) { showStatus('Selecione um lead primeiro.', 'error'); return; }
  try {
    await apiFetch(`/api/extension/leads/${selectedLeadId}/status`, 'PATCH', { classificacao: status });
    showStatus(`Qualificação: ${status}`, 'success');
    if (selectedLeadData) {
      selectedLeadData.classificacao = status;
      applySelectedLead(selectedLeadData, null);
    }
  } catch (err) {
    showStatus(`Erro: ${err.message}`, 'error');
  }
}

function openCrmLead(leadId) {
  if (socimobUrl) chrome.tabs.create({ url: `${socimobUrl}/leads/${leadId}` });
}

// ── Vincular conversa ─────────────────────────────────────────────────────────

async function pullCurrentContext() {
  showStatus('Buscando conversa...', 'info');
  const info = await getConversationFromTab();
  if (!info) { showStatus('Abra o WhatsApp Web e entre numa conversa.', 'error'); return; }
  setConversationInfo(info);
  await autoFindLead(info);
}

async function linkCurrentConversation() {
  if (!selectedLeadId) { showStatus('Selecione um lead primeiro.', 'error'); return; }
  if (!currentConversationInfo?.url?.includes('web.whatsapp.com')) {
    showStatus('Abra uma conversa no WhatsApp Web.', 'error');
    return;
  }

  const btn = document.getElementById('linkConversationBtn');
  if (btn) { btn.disabled = true; btn.textContent = 'Vinculando...'; }

  try {
    const data = await apiFetch('/api/extension/conversations/link', 'POST', {
      lead_id: selectedLeadId,
      property_id: selectedLeadData?.propertyId || null,
      contact_name: currentConversationInfo.contactName || selectedLeadData?.name || '',
      contact_phone: currentConversationInfo.contactPhone || selectedLeadData?.phone || '',
      whatsapp_chat_identifier: currentConversationInfo.url,
      source: 'chrome_extension',
    });

    linkedConversationId = data.data?.id;
    renderLinkedConversation(data.data);

    const msg = data.already_linked ? 'Conversa já estava vinculada.' : 'Conversa vinculada ao lead!';
    showStatus(msg, 'success');
  } catch (err) {
    showStatus(err.message, 'error');
  } finally {
    if (btn) { btn.disabled = false; btn.textContent = 'Vincular conversa'; }
  }
}

function renderLinkedConversation(conv) {
  if (!conv) return;
  const el = document.getElementById('linkedConversationInfo');
  if (!el) return;
  el.style.display = 'block';
  el.innerHTML = `
    <div class="linked-conv-tag">
      🔗 Conversa #${conv.id} · <em>${conv.status || 'aberta'}</em>
      ${conv.stage ? ` · ${conv.stage}` : ''}
    </div>
  `;
}

// ── Resumo e próxima ação ──────────────────────────────────────────────────────

async function saveSummary() {
  if (!selectedLeadId) { showStatus('Selecione um lead primeiro.', 'error'); return; }
  const summary = getVal('summaryText').trim();
  if (!summary) { showStatus('Escreva o resumo antes de salvar.', 'error'); return; }

  const nextAction = getVal('nextAction').trim();
  const status = getVal('conversationStatus') || 'open';
  const interestLevel = getVal('interestLevel');

  const btn = document.getElementById('saveSummaryBtn');
  if (btn) { btn.disabled = true; btn.textContent = 'Salvando...'; }

  try {
    if (!linkedConversationId) await linkCurrentConversation();

    if (linkedConversationId) {
      await apiFetch(`/api/atendimento/conversations/${linkedConversationId}/summary`, 'POST', {
        summary,
        next_action: nextAction || null,
        interest_level: interestLevel ? Number(interestLevel) : null,
        status,
        event_source: 'chrome_extension',
      });
    } else {
      // Sem conversa vinculada: salva como nota no lead
      await apiFetch(`/api/extension/leads/${selectedLeadId}/note`, 'POST', {
        note: summary + (nextAction ? `\n\nPróxima ação: ${nextAction}` : ''),
        source: 'chrome_extension',
      });
    }

    setVal('summaryText', '');
    setVal('nextAction', '');
    showStatus('Resumo salvo com sucesso!', 'success');
  } catch (err) {
    showStatus(err.message, 'error');
  } finally {
    if (btn) { btn.disabled = false; btn.textContent = 'Salvar Resumo'; }
  }
}

async function saveNextAction() {
  if (!selectedLeadId) { showStatus('Selecione um lead primeiro.', 'error'); return; }
  const nextAction = getVal('nextAction').trim();
  if (!nextAction) { showStatus('Descreva a próxima ação.', 'error'); return; }

  try {
    if (linkedConversationId) {
      await apiFetch(`/api/atendimento/conversations/${linkedConversationId}/events`, 'POST', {
        event_type: 'next_action',
        title: nextAction,
        source: 'chrome_extension',
      });
    } else {
      await apiFetch(`/api/extension/leads/${selectedLeadId}/note`, 'POST', {
        note: `Próxima ação: ${nextAction}`,
        source: 'chrome_extension',
      });
    }
    setVal('nextAction', '');
    showStatus('Próxima ação registrada!', 'success');
  } catch (err) {
    showStatus(err.message, 'error');
  }
}

// ── Criar lead inline ─────────────────────────────────────────────────────────

function showCreateLeadForm() {
  document.getElementById('createLeadForm').style.display = 'block';
  document.getElementById('createLeadBtn').style.display = 'none';
  prefillCreateLeadForm(currentConversationInfo);
}

function hideCreateLeadForm() {
  document.getElementById('createLeadForm').style.display = 'none';
  document.getElementById('createLeadBtn').style.display = 'block';
}

function prefillCreateLeadForm(info) {
  if (!info) return;
  if (info.contactName && !getVal('newLeadName')) setVal('newLeadName', info.contactName);
  if (info.contactPhone && !getVal('newLeadPhone')) setVal('newLeadPhone', info.contactPhone);
}

async function createLeadFromForm() {
  const name = getVal('newLeadName').trim();
  const phone = getVal('newLeadPhone').trim();
  const email = getVal('newLeadEmail').trim();

  if (!name || !phone) { showStatus('Nome e telefone são obrigatórios.', 'error'); return; }

  const btn = document.getElementById('confirmCreateLeadBtn');
  if (btn) { btn.disabled = true; btn.textContent = 'Criando...'; }

  try {
    const data = await apiFetch('/api/extension/leads', 'POST', {
      name,
      phone,
      email: email || null,
      origin: 'whatsapp_web_extension',
      observations: currentConversationInfo?.contactName
        ? `Lead captado via WhatsApp Web (${currentConversationInfo.contactName})`
        : 'Lead captado via extensão Chrome',
    });

    const lead = data.data;
    applySelectedLead({ id: lead.id, nome: lead.nome, telefone: lead.telefone, email: lead.email }, `Lead "${lead.nome}" criado!`);
    hideCreateLeadForm();
    setVal('newLeadName', '');
    setVal('newLeadPhone', '');
    setVal('newLeadEmail', '');

    // Vincula automaticamente após criar
    await linkCurrentConversation();
  } catch (err) {
    showStatus(err.message, 'error');
  } finally {
    if (btn) { btn.disabled = false; btn.textContent = 'Criar Lead'; }
  }
}

// ── Ações rápidas ─────────────────────────────────────────────────────────────

async function createTask() {
  if (!selectedLeadId) { showStatus('Selecione um lead primeiro.', 'error'); return; }
  const title = prompt('Título da tarefa:');
  if (!title?.trim()) return;
  const dueDate = prompt('Data (YYYY-MM-DD) ou deixe vazio:') || null;

  try {
    if (linkedConversationId) {
      await apiFetch(`/api/atendimento/conversations/${linkedConversationId}/tasks`, 'POST', {
        title: title.trim(),
        due_date: dueDate || null,
        source: 'chrome_extension',
      });
    } else {
      await apiFetch(`/api/extension/leads/${selectedLeadId}/note`, 'POST', {
        note: `📋 Tarefa: ${title.trim()}${dueDate ? ' — prazo: ' + dueDate : ''}`,
        source: 'chrome_extension',
      });
    }
    showStatus('Tarefa criada!', 'success');
  } catch (err) {
    showStatus(err.message, 'error');
  }
}

async function scheduleVisit() {
  if (!selectedLeadId) { showStatus('Selecione um lead primeiro.', 'error'); return; }
  const date = prompt('Data da visita (YYYY-MM-DD):');
  if (!date?.trim()) return;
  const time = (prompt('Horário (HH:MM):') || '09:00').trim();
  const address = (prompt('Endereço ou imóvel:') || '').trim();

  try {
    if (linkedConversationId) {
      await apiFetch(`/api/atendimento/conversations/${linkedConversationId}/visits`, 'POST', {
        scheduled_at: `${date} ${time}:00`,
        address: address || null,
        notes: 'Agendado via extensão Chrome',
        source: 'chrome_extension',
      });
    } else {
      await apiFetch(`/api/extension/leads/${selectedLeadId}/note`, 'POST', {
        note: `📅 Visita agendada: ${date} às ${time}${address ? ' — ' + address : ''}`,
        source: 'chrome_extension',
      });
    }
    showStatus('Visita agendada!', 'success');
  } catch (err) {
    showStatus(err.message, 'error');
  }
}

// ── Templates de mensagem ─────────────────────────────────────────────────────

function loadTemplates() {
  if (!currentSessionToken || !socimobUrl) return;

  apiFetch('/api/extension/message-templates').then((data) => {
    const list = document.getElementById('templatesList');
    if (!list) return;
    list.innerHTML = '';
    const templates = data?.data || [];

    if (templates.length === 0) {
      list.innerHTML = '<div class="empty-list">Nenhum template disponível</div>';
      return;
    }

    templates.forEach((t) => {
      const item = document.createElement('div');
      item.className = 'template-item';
      item.innerHTML = `
        <span class="template-name" title="${escapeHtml(t.content)}">${escapeHtml(t.name)}</span>
        <div class="template-actions">
          <button class="icon-btn copy-btn" title="Copiar">📋</button>
          <button class="icon-btn insert-btn" title="Inserir no WhatsApp">↪</button>
        </div>
      `;
      item.querySelector('.copy-btn').addEventListener('click', () => copyToClipboard(t.content));
      item.querySelector('.insert-btn').addEventListener('click', () => insertInWhatsApp(t.content));
      list.appendChild(item);
    });
  }).catch(() => {});
}

function copyToClipboard(text) {
  chrome.tabs.query({ active: true, currentWindow: true }, (tabs) => {
    const tab = tabs[0];
    if (!tab?.id) { navigator.clipboard.writeText(text).then(() => showStatus('Copiado!', 'success')); return; }
    chrome.tabs.sendMessage(tab.id, { action: 'copyToClipboard', text }, (resp) => {
      if (resp?.success) showStatus('Template copiado!', 'success');
      else navigator.clipboard.writeText(text).then(() => showStatus('Copiado!', 'success'));
    });
  });
}

function insertInWhatsApp(text) {
  chrome.tabs.query({ active: true, currentWindow: true }, (tabs) => {
    const tab = tabs[0];
    if (!tab?.url?.includes('web.whatsapp.com')) {
      showStatus('Abra o WhatsApp Web para inserir o template.', 'error');
      return;
    }
    chrome.tabs.sendMessage(tab.id, { action: 'insertTextIntoInput', text }, (resp) => {
      if (resp?.success) showStatus('Template inserido!', 'success');
      else {
        copyToClipboard(text);
        showStatus('Copiado! (Cole com Ctrl+V no WhatsApp)', 'info');
      }
    });
  });
}

// ── API helper ────────────────────────────────────────────────────────────────

async function apiFetch(path, method = 'GET', body = null) {
  if (!currentSessionToken || !socimobUrl) throw new Error('Não autenticado.');

  const opts = {
    method,
    headers: {
      Authorization: `Bearer ${currentSessionToken}`,
      'Content-Type': 'application/json',
      Accept: 'application/json',
      'X-Tenant-Domain': tenantDomain || '',
    },
  };
  if (body) opts.body = JSON.stringify(body);

  const resp = await fetch(`${socimobUrl}${path}`, opts);
  const data = await resp.json().catch(() => ({}));

  if (!resp.ok) {
    if (resp.status === 401) {
      currentSessionToken = null;
      chrome.storage.local.remove(['sessionToken', 'socimobUrl', 'userEmail', 'expiresAt']);
      showNoSession();
      showStatus('Sessão expirada. Faça login no CRM.', 'error');
    }
    throw new Error(data.message || `Erro ${resp.status}`);
  }
  return data;
}

// ── Banner de auto-match ──────────────────────────────────────────────────────

function showBanner(html, type) {
  const banner = document.getElementById('autoMatchBanner');
  if (!banner) return;
  banner.innerHTML = html;
  banner.className = `match-banner match-${type}`;
  banner.style.display = 'block';
}

function hideBanner() {
  const banner = document.getElementById('autoMatchBanner');
  if (banner) banner.style.display = 'none';
}

// ── Drag & drop ───────────────────────────────────────────────────────────────

function handleDrop(event) {
  event.preventDefault();
  event.currentTarget.classList.remove('drag-over');
  const raw = event.dataTransfer.getData('application/x-socimob-lead') ||
    event.dataTransfer.getData('application/json');
  if (!raw) { showStatus('Dados de lead não encontrados.', 'error'); return; }
  try {
    applySelectedLead(JSON.parse(raw), 'Lead recebido por arrastar e soltar');
  } catch {
    showStatus('Não foi possível ler o lead arrastado.', 'error');
  }
}

// ── Utilitários ───────────────────────────────────────────────────────────────

function showStatus(message, type) {
  const el = document.getElementById('statusMessage');
  if (!el) return;
  el.textContent = message;
  el.className = `status ${type}`;
  el.style.display = 'block';
  if (type === 'success' || type === 'info') {
    setTimeout(() => { el.style.display = 'none'; }, 4000);
  }
}

function storageGet(keys) {
  return new Promise((resolve) => chrome.storage.local.get(keys, resolve));
}

function on(id, event, fn) {
  document.getElementById(id)?.addEventListener(event, fn);
}

function setText(id, value) {
  const el = document.getElementById(id);
  if (el) el.textContent = value;
}

function getVal(id) {
  return document.getElementById(id)?.value || '';
}

function setVal(id, value) {
  const el = document.getElementById(id);
  if (el) el.value = value;
}

function enc(str) {
  return encodeURIComponent(str);
}

function debounce(func, delay) {
  let timer;
  return function (...args) {
    clearTimeout(timer);
    timer = setTimeout(() => func(...args), delay);
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
