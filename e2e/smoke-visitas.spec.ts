import { expect, test } from '@playwright/test';

const baseUrl = (process.env.SMOKE_BASE_URL || 'https://exclusivalarimoveis.com').replace(/\/$/, '');
const adminEmail = process.env.SMOKE_ADMIN_EMAIL;
const adminPassword = process.env.SMOKE_ADMIN_PASSWORD;
const runId = process.env.SMOKE_RUN_ID || `${Date.now()}`;

if (!adminEmail || !adminPassword) {
  throw new Error('SMOKE_ADMIN_EMAIL e SMOKE_ADMIN_PASSWORD são obrigatórios para o smoke test.');
}

const prefix = `SMOKE ${runId}`;
const manualVisitName = `${prefix} Agenda`;
const portalVisitName = `${prefix} Portal`;
const chatVisitName = `${prefix} Chat`;

const toDateTimeLocalValue = (value: Date) => {
  const timezoneOffset = value.getTimezoneOffset() * 60_000;
  return new Date(value.getTime() - timezoneOffset).toISOString().slice(0, 16);
};

const toChatDateText = (value: Date) => {
  const day = String(value.getDate()).padStart(2, '0');
  const month = String(value.getMonth() + 1).padStart(2, '0');
  const year = value.getFullYear();
  const hour = String(value.getHours()).padStart(2, '0');
  const minute = String(value.getMinutes()).padStart(2, '0');
  return `${day}/${month}/${year} ${hour}:${minute}`;
};

test.describe.configure({ mode: 'serial' });

test('smoke end-to-end de agenda, portal inicial e chat', async ({ page, context }) => {
  test.setTimeout(180_000);

  const manualDate = new Date(Date.now() + 3 * 60 * 60 * 1000);
  const portalDate = new Date(Date.now() + 4 * 60 * 60 * 1000);
  const chatDate = new Date(Date.now() + 5 * 60 * 60 * 1000);

  await context.route('https://wa.me/**', async (route) => {
    await route.fulfill({
      status: 200,
      contentType: 'text/html',
      body: '<html><body>WhatsApp smoke stub</body></html>',
    });
  });

  await page.goto(`${baseUrl}/login`, { waitUntil: 'domcontentloaded' });
  await page.getByPlaceholder('seu@email.com').fill(adminEmail);
  await page.getByPlaceholder('••••••••').fill(adminPassword);
  await page.getByRole('button', { name: 'Entrar' }).click();
  await expect(page).toHaveURL(/\/dashboard/, { timeout: 20_000 });

  await page.goto(`${baseUrl}/agenda`, { waitUntil: 'domcontentloaded' });
  await expect(page.getByText('Agenda de Visitas')).toBeVisible();
  await page.getByPlaceholder('Ex: Apartamento 3 quartos no Centro').fill(`Imóvel ${manualVisitName}`);
  await page.getByPlaceholder('Nome do cliente').fill(manualVisitName);
  await page.getByPlaceholder('(31) 99999-9999').first().fill('(31) 99999-1001');
  await page.getByPlaceholder('cliente@email.com').fill(`agenda.${runId}@example.com`);
  await page.locator('input[type="datetime-local"]').first().fill(toDateTimeLocalValue(manualDate));
  await page.getByPlaceholder('Detalhes do encontro, ponto de referência, imóvel desejado ou contexto do lead').fill(`Smoke test agenda ${runId} - pode ignorar`);

  const assigneeSelect = page.locator('label').filter({ hasText: 'Responsável' }).locator('select');
  const assigneeOptions = await assigneeSelect.locator('option').allTextContents();
  const preferredAssignee = assigneeOptions.find((option) => /Nelson|Roberto|Joice|Jocineide/i.test(option)) || assigneeOptions[0];
  await assigneeSelect.selectOption({ label: preferredAssignee });
  await page.getByRole('button', { name: 'Criar visita' }).click();
  await expect(page.getByText(manualVisitName)).toBeVisible({ timeout: 20_000 });

  await page.goto(`${baseUrl}/`, { waitUntil: 'domcontentloaded' });
  const portalOpenButton = page.locator('[aria-label="Abrir atendimento no WhatsApp"], [aria-label="Abrir WhatsApp"]');
  await portalOpenButton.first().click();
  await expect(page.getByText('Falar com nossa equipe')).toBeVisible({ timeout: 15_000 });
  await page.getByPlaceholder('Como você se chama?').fill(portalVisitName);
  await page.getByPlaceholder('(31) 99999-9999').fill('(31) 99999-1002');
  await page.locator('input[type="datetime-local"]').fill(toDateTimeLocalValue(portalDate));
  await page.getByPlaceholder('Ex: melhor horário no fim da tarde, visita com família, imóvel semelhante ao anúncio').fill(`Smoke test portal ${runId} - pode ignorar`);
  const portalResponsePromise = page.waitForResponse((response) => response.url().includes('/api/portal/chat-lead') && response.request().method() === 'POST');
  await page.getByRole('button', { name: 'Registrar e abrir WhatsApp' }).click();
  const portalResponse = await portalResponsePromise;
  const portalPayload = await portalResponse.json();
  expect(portalPayload.success).toBeTruthy();
  expect(portalPayload.visita_id).toBeTruthy();

  await page.goto(`${baseUrl}/agenda`, { waitUntil: 'domcontentloaded' });
  await expect(page.getByText(portalVisitName)).toBeVisible({ timeout: 20_000 });

  await page.goto(`${baseUrl}/portal/classic`, { waitUntil: 'domcontentloaded' });
  await page.getByAltText('Mascote').first().click();
  await expect(page.getByText('Assistente Virtual')).toBeVisible({ timeout: 15_000 });

  await page.getByPlaceholder('Seu nome...').fill(chatVisitName);
  await page.getByPlaceholder('Seu nome...').press('Enter');
  await page.getByPlaceholder('(31) 99999-8888').fill('(31) 99999-1003');
  await page.getByPlaceholder('(31) 99999-8888').press('Enter');
  await page.getByPlaceholder('seu@email.com').fill('pular');
  await page.getByPlaceholder('seu@email.com').press('Enter');
  await page.getByPlaceholder('Ex: apartamento 2 quartos...').fill(`Procuro imóvel para smoke test ${runId}`);
  await page.getByPlaceholder('Ex: apartamento 2 quartos...').press('Enter');
  await page.getByPlaceholder('Ex: 25/04/2026 15:30').fill(toChatDateText(chatDate));
  const chatResponsePromise = page.waitForResponse((response) => response.url().includes('/api/portal/chat-lead') && response.request().method() === 'POST');
  await page.getByPlaceholder('Ex: 25/04/2026 15:30').press('Enter');
  const chatResponse = await chatResponsePromise;
  const chatPayload = await chatResponse.json();
  expect(chatPayload.success).toBeTruthy();
  expect(chatPayload.visita_id).toBeTruthy();
  await expect(page.getByText('Continuar no WhatsApp')).toBeVisible({ timeout: 20_000 });

  await page.goto(`${baseUrl}/agenda`, { waitUntil: 'domcontentloaded' });
  await expect(page.getByText(chatVisitName)).toBeVisible({ timeout: 20_000 });
});