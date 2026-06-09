// Content Script para detectar conversas do WhatsApp Web

// Função para extrair informações da conversa atual
function extractConversationInfo() {
  try {
    // Selecionar o nome do contato (pode variar conforme a versão do WhatsApp Web)
    const contactNameElement = document.querySelector('[data-testid="conversation-header-contact-name"]') ||
                               document.querySelector('[data-testid="chat-header-title"]');
    const contactName = contactNameElement ? contactNameElement.textContent.trim() : null;

    // Selecionar o telefone/ID do contato (se visível)
    const contactPhoneElement = document.querySelector('[data-testid="conversation-header-contact-phone"]') ||
                                document.querySelector('[data-testid="chat-header-subtitle"]');
    const contactPhone = contactPhoneElement ? contactPhoneElement.textContent.trim() : null;

    // Selecionar o histórico de mensagens (primeiras e últimas mensagens)
    const messagesElements = document.querySelectorAll('[data-testid="msg-container"]');
    const messageCount = messagesElements.length;

    return {
      contactName,
      contactPhone,
      messageCount,
      url: window.location.href,
      timestamp: new Date().toISOString(),
    };
  } catch (error) {
    console.error('Erro ao extrair informações da conversa:', error);
    return null;
  }
}

// Listener para mensagens do background/popup
chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
  if (request.action === 'getConversationInfo') {
    const conversationInfo = extractConversationInfo();
    sendResponse(conversationInfo);
  }

  if (request.action === 'copyToClipboard') {
    navigator.clipboard.writeText(request.text).then(() => {
      sendResponse({ success: true });
    }).catch((error) => {
      console.error('Erro ao copiar para clipboard:', error);
      sendResponse({ success: false, error: error.message });
    });
    return true;
  }
});

// Monitorar mudanças na conversa atual
const observer = new MutationObserver(() => {
  const conversationInfo = extractConversationInfo();
  if (conversationInfo) {
    // Enviar informações para o side panel
    chrome.runtime.sendMessage({
      action: 'conversationUpdated',
      data: conversationInfo,
    }).catch(() => {
      // Silenciar erros se nenhum listener estiver pronto
    });
  }
});

// Observar mudanças no DOM
observer.observe(document.body, {
  childList: true,
  subtree: true,
  attributes: true,
  attributeFilter: ['data-testid'],
});
