# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: smoke-visitas.spec.ts >> smoke end-to-end de agenda, portal inicial e chat
- Location: e2e\smoke-visitas.spec.ts:33:1

# Error details

```
Test timeout of 180000ms exceeded.
```

```
Error: locator.fill: Test timeout of 180000ms exceeded.
Call log:
  - waiting for getByPlaceholder('Ex: Apartamento 3 quartos no Centro')

```

# Page snapshot

```yaml
- generic [ref=e2]:
  - region "Notifications alt+T"
  - generic [ref=e4]:
    - generic [ref=e5]: Usamos cookies para estatísticas e melhoria do produto. Você pode aceitar ou recusar.
    - generic [ref=e6]:
      - button "Recusar" [ref=e7] [cursor=pointer]
      - button "Aceitar" [ref=e8] [cursor=pointer]
  - generic [ref=e9]:
    - generic [ref=e11]:
      - generic [ref=e12]:
        - generic [ref=e13]:
          - img "Exclusiva Lar Imoveis" [ref=e14]
          - generic [ref=e15]:
            - paragraph [ref=e16]: Exclusiva Lar Imoveis
            - paragraph [ref=e18]: Principal
        - generic [ref=e19]:
          - button "Tema claro" [ref=e20] [cursor=pointer]:
            - img [ref=e21]
            - generic [ref=e23]: Tema claro
          - button "Sair" [ref=e24] [cursor=pointer]:
            - img [ref=e25]
            - generic [ref=e28]: Sair
      - navigation [ref=e29]:
        - generic [ref=e30]:
          - link "Principal" [ref=e31] [cursor=pointer]:
            - /url: /dashboard
            - generic [ref=e32]:
              - img [ref=e34]
              - generic [ref=e36]: Principal
          - link "CRM & Clientes 267" [ref=e37] [cursor=pointer]:
            - /url: /crm
            - generic [ref=e38]:
              - img [ref=e40]
              - generic [ref=e45]: CRM & Clientes
              - generic [ref=e46]: "267"
          - link "Imóveis" [ref=e47] [cursor=pointer]:
            - /url: /properties
            - generic [ref=e48]:
              - img [ref=e50]
              - generic [ref=e53]: Imóveis
          - link "Operacional" [ref=e54] [cursor=pointer]:
            - /url: /vistorias
            - generic [ref=e55]:
              - img [ref=e57]
              - generic [ref=e60]: Operacional
          - link "Financeiro" [ref=e61] [cursor=pointer]:
            - /url: /financeiro
            - generic [ref=e62]:
              - img [ref=e64]
              - generic [ref=e66]: Financeiro
          - link "Administração" [ref=e67] [cursor=pointer]:
            - /url: /analytics
            - generic [ref=e68]:
              - img [ref=e70]
              - generic [ref=e72]: Administração
          - link "Configurações" [ref=e74] [cursor=pointer]:
            - /url: /settings
            - generic [ref=e75]:
              - img [ref=e77]
              - generic [ref=e80]: Configurações
      - generic [ref=e82]:
        - generic [ref=e83]: Principal
        - link "Dashboard" [ref=e84] [cursor=pointer]:
          - /url: /dashboard
          - generic [ref=e85]:
            - img [ref=e87]
            - generic [ref=e89]: Dashboard
        - link "Notificações" [ref=e90] [cursor=pointer]:
          - /url: /notifications
          - generic [ref=e91]:
            - img [ref=e93]
            - generic [ref=e96]: Notificações
        - link "Agenda" [ref=e97] [cursor=pointer]:
          - /url: /agenda
          - generic [ref=e98]:
            - img [ref=e100]
            - generic [ref=e104]: Agenda
    - generic [ref=e106]:
      - generic [ref=e107]:
        - generic [ref=e108]:
          - heading "Agenda de Visitas" [level=1] [ref=e109]:
            - img [ref=e110]
            - text: Agenda de Visitas
          - paragraph [ref=e114]: Acompanhe as visitas no calendário, filtre por dia e envie cada compromisso para o Google Agenda.
        - generic [ref=e115]:
          - button "Abrir Google Agenda" [ref=e116] [cursor=pointer]:
            - img [ref=e117]
            - text: Abrir Google Agenda
          - button "Abrir Microsoft Agenda" [ref=e121] [cursor=pointer]:
            - img [ref=e122]
            - text: Abrir Microsoft Agenda
          - button "Atualizar" [ref=e126] [cursor=pointer]:
            - img [ref=e127]
            - text: Atualizar
      - generic [ref=e132]:
        - generic [ref=e133]:
          - paragraph [ref=e134]: Visitas cadastradas
          - paragraph [ref=e135]: "0"
        - generic [ref=e136]:
          - paragraph [ref=e137]: Visitas hoje
          - paragraph [ref=e138]: "0"
        - generic [ref=e139]:
          - paragraph [ref=e140]: Confirmadas
          - paragraph [ref=e141]: "0"
      - generic [ref=e143]:
        - generic [ref=e144]:
          - img [ref=e145]
          - textbox "Buscar por lead ou imóvel" [ref=e148]
        - combobox [ref=e149] [cursor=pointer]:
          - option "Todos os status" [selected]
          - option "Pendentes"
          - option "Confirmadas"
          - option "Concluídas"
          - option "Canceladas"
      - generic [ref=e150]:
        - generic [ref=e151]:
          - generic [ref=e152]:
            - generic [ref=e153]:
              - paragraph [ref=e154]: Calendário
              - paragraph [ref=e155]: Selecione um dia para filtrar as visitas.
            - button "Limpar filtro" [ref=e156] [cursor=pointer]
          - generic [ref=e159]:
            - navigation "Navigation bar" [ref=e160]:
              - button "Go to the Previous Month" [ref=e161] [cursor=pointer]:
                - img
              - button "Go to the Next Month" [ref=e162] [cursor=pointer]:
                - img
            - generic [ref=e163]:
              - status [ref=e165]: Abril de 2026
              - grid "April 2026" [ref=e166]:
                - rowgroup [ref=e167]:
                  - row [ref=e168]:
                    - columnheader [ref=e169]: dom
                    - columnheader [ref=e170]: seg
                    - columnheader [ref=e171]: ter
                    - columnheader [ref=e172]: qua
                    - columnheader [ref=e173]: qui
                    - columnheader [ref=e174]: sex
                    - columnheader [ref=e175]: sáb
                - rowgroup [ref=e176]:
                  - row "Sunday, March 29th, 2026 Monday, March 30th, 2026 Tuesday, March 31st, 2026 Wednesday, April 1st, 2026 Thursday, April 2nd, 2026 Friday, April 3rd, 2026 Saturday, April 4th, 2026" [ref=e177]:
                    - gridcell "Sunday, March 29th, 2026" [ref=e178]:
                      - button "Sunday, March 29th, 2026" [ref=e179] [cursor=pointer]: "29"
                    - gridcell "Monday, March 30th, 2026" [ref=e180]:
                      - button "Monday, March 30th, 2026" [ref=e181] [cursor=pointer]: "30"
                    - gridcell "Tuesday, March 31st, 2026" [ref=e182]:
                      - button "Tuesday, March 31st, 2026" [ref=e183] [cursor=pointer]: "31"
                    - gridcell "Wednesday, April 1st, 2026" [ref=e184]:
                      - button "Wednesday, April 1st, 2026" [ref=e185] [cursor=pointer]: "1"
                    - gridcell "Thursday, April 2nd, 2026" [ref=e186]:
                      - button "Thursday, April 2nd, 2026" [ref=e187] [cursor=pointer]: "2"
                    - gridcell "Friday, April 3rd, 2026" [ref=e188]:
                      - button "Friday, April 3rd, 2026" [ref=e189] [cursor=pointer]: "3"
                    - gridcell "Saturday, April 4th, 2026" [ref=e190]:
                      - button "Saturday, April 4th, 2026" [ref=e191] [cursor=pointer]: "4"
                  - row "Sunday, April 5th, 2026 Monday, April 6th, 2026 Today, Tuesday, April 7th, 2026 Wednesday, April 8th, 2026 Thursday, April 9th, 2026 Friday, April 10th, 2026 Saturday, April 11th, 2026" [ref=e192]:
                    - gridcell "Sunday, April 5th, 2026" [ref=e193]:
                      - button "Sunday, April 5th, 2026" [ref=e194] [cursor=pointer]: "5"
                    - gridcell "Monday, April 6th, 2026" [ref=e195]:
                      - button "Monday, April 6th, 2026" [ref=e196] [cursor=pointer]: "6"
                    - gridcell "Today, Tuesday, April 7th, 2026" [ref=e197]:
                      - button "Today, Tuesday, April 7th, 2026" [ref=e198] [cursor=pointer]: "7"
                    - gridcell "Wednesday, April 8th, 2026" [ref=e199]:
                      - button "Wednesday, April 8th, 2026" [ref=e200] [cursor=pointer]: "8"
                    - gridcell "Thursday, April 9th, 2026" [ref=e201]:
                      - button "Thursday, April 9th, 2026" [ref=e202] [cursor=pointer]: "9"
                    - gridcell "Friday, April 10th, 2026" [ref=e203]:
                      - button "Friday, April 10th, 2026" [ref=e204] [cursor=pointer]: "10"
                    - gridcell "Saturday, April 11th, 2026" [ref=e205]:
                      - button "Saturday, April 11th, 2026" [ref=e206] [cursor=pointer]: "11"
                  - row "Sunday, April 12th, 2026 Monday, April 13th, 2026 Tuesday, April 14th, 2026 Wednesday, April 15th, 2026 Thursday, April 16th, 2026 Friday, April 17th, 2026 Saturday, April 18th, 2026" [ref=e207]:
                    - gridcell "Sunday, April 12th, 2026" [ref=e208]:
                      - button "Sunday, April 12th, 2026" [ref=e209] [cursor=pointer]: "12"
                    - gridcell "Monday, April 13th, 2026" [ref=e210]:
                      - button "Monday, April 13th, 2026" [ref=e211] [cursor=pointer]: "13"
                    - gridcell "Tuesday, April 14th, 2026" [ref=e212]:
                      - button "Tuesday, April 14th, 2026" [ref=e213] [cursor=pointer]: "14"
                    - gridcell "Wednesday, April 15th, 2026" [ref=e214]:
                      - button "Wednesday, April 15th, 2026" [ref=e215] [cursor=pointer]: "15"
                    - gridcell "Thursday, April 16th, 2026" [ref=e216]:
                      - button "Thursday, April 16th, 2026" [ref=e217] [cursor=pointer]: "16"
                    - gridcell "Friday, April 17th, 2026" [ref=e218]:
                      - button "Friday, April 17th, 2026" [ref=e219] [cursor=pointer]: "17"
                    - gridcell "Saturday, April 18th, 2026" [ref=e220]:
                      - button "Saturday, April 18th, 2026" [ref=e221] [cursor=pointer]: "18"
                  - row "Sunday, April 19th, 2026 Monday, April 20th, 2026 Tuesday, April 21st, 2026 Wednesday, April 22nd, 2026 Thursday, April 23rd, 2026 Friday, April 24th, 2026 Saturday, April 25th, 2026" [ref=e222]:
                    - gridcell "Sunday, April 19th, 2026" [ref=e223]:
                      - button "Sunday, April 19th, 2026" [ref=e224] [cursor=pointer]: "19"
                    - gridcell "Monday, April 20th, 2026" [ref=e225]:
                      - button "Monday, April 20th, 2026" [ref=e226] [cursor=pointer]: "20"
                    - gridcell "Tuesday, April 21st, 2026" [ref=e227]:
                      - button "Tuesday, April 21st, 2026" [ref=e228] [cursor=pointer]: "21"
                    - gridcell "Wednesday, April 22nd, 2026" [ref=e229]:
                      - button "Wednesday, April 22nd, 2026" [ref=e230] [cursor=pointer]: "22"
                    - gridcell "Thursday, April 23rd, 2026" [ref=e231]:
                      - button "Thursday, April 23rd, 2026" [ref=e232] [cursor=pointer]: "23"
                    - gridcell "Friday, April 24th, 2026" [ref=e233]:
                      - button "Friday, April 24th, 2026" [ref=e234] [cursor=pointer]: "24"
                    - gridcell "Saturday, April 25th, 2026" [ref=e235]:
                      - button "Saturday, April 25th, 2026" [ref=e236] [cursor=pointer]: "25"
                  - row "Sunday, April 26th, 2026 Monday, April 27th, 2026 Tuesday, April 28th, 2026 Wednesday, April 29th, 2026 Thursday, April 30th, 2026 Friday, May 1st, 2026 Saturday, May 2nd, 2026" [ref=e237]:
                    - gridcell "Sunday, April 26th, 2026" [ref=e238]:
                      - button "Sunday, April 26th, 2026" [ref=e239] [cursor=pointer]: "26"
                    - gridcell "Monday, April 27th, 2026" [ref=e240]:
                      - button "Monday, April 27th, 2026" [ref=e241] [cursor=pointer]: "27"
                    - gridcell "Tuesday, April 28th, 2026" [ref=e242]:
                      - button "Tuesday, April 28th, 2026" [ref=e243] [cursor=pointer]: "28"
                    - gridcell "Wednesday, April 29th, 2026" [ref=e244]:
                      - button "Wednesday, April 29th, 2026" [ref=e245] [cursor=pointer]: "29"
                    - gridcell "Thursday, April 30th, 2026" [ref=e246]:
                      - button "Thursday, April 30th, 2026" [ref=e247] [cursor=pointer]: "30"
                    - gridcell "Friday, May 1st, 2026" [ref=e248]:
                      - button "Friday, May 1st, 2026" [ref=e249] [cursor=pointer]: "1"
                    - gridcell "Saturday, May 2nd, 2026" [ref=e250]:
                      - button "Saturday, May 2nd, 2026" [ref=e251] [cursor=pointer]: "2"
          - generic [ref=e252]:
            - paragraph [ref=e253]: Todos os dias
            - paragraph [ref=e254]: Dias com visitas aparecem destacados no calendário.
        - generic [ref=e255]:
          - generic [ref=e257]:
            - generic [ref=e258]:
              - img [ref=e259]
              - paragraph [ref=e261]: Google e Microsoft Agenda
            - paragraph [ref=e262]: Cole as URLs de incorporação do Google Calendar e do Outlook Calendar para ver as agendas oficiais lado a lado com a agenda interna.
          - generic [ref=e263]:
            - generic [ref=e264]:
              - paragraph [ref=e265]: Onde copiar no Google
              - paragraph [ref=e266]: No Google Calendar, abra as configurações do calendário desejado, entre em "Integrar agenda" e copie apenas a URL do atributo src do código de incorporação. Não cole o iframe inteiro.
            - generic [ref=e267]:
              - paragraph [ref=e268]: Onde copiar no Outlook
              - paragraph [ref=e269]: No Outlook ou Microsoft 365 Calendar, publique ou incorpore o calendário e copie somente a URL do src do iframe gerado. A tela aceita a URL direta de embed.
          - generic [ref=e270]:
            - 'textbox "Google: https://calendar.google.com/calendar/embed?..." [ref=e271]': https://calendar.google.com/calendar/embed?src=marcusabagnale%40gmail.com&ctz=America%2FSao_Paulo
            - 'textbox "Microsoft: https://outlook.office.com/calendar/embed?..." [ref=e272]'
            - button "Salvar" [ref=e273] [cursor=pointer]:
              - img [ref=e274]
              - text: Salvar
          - paragraph [ref=e278]: "Dica: se você copiar um código completo de iframe, extraia apenas o valor de src=\"...\" e cole aqui."
          - generic [ref=e279]:
            - iframe [ref=e281]:
              - generic [ref=f1e2]:
                - img "Logotipo do Google" [ref=f1e4]
                - generic [ref=f1e5]: Sign in to your Google Account
                - generic [ref=f1e6]: You must sign in to access this content
                - button "Fazer login" [ref=f1e8] [cursor=pointer]
            - generic [ref=e283]:
              - img [ref=e284]
              - paragraph [ref=e286]: Microsoft Agenda não configurado
              - paragraph [ref=e287]: Use a URL de incorporação do Outlook Calendar ou Microsoft 365 para visualizar sua agenda oficial nesta tela.
      - generic [ref=e289]: Nenhuma visita encontrada com os filtros atuais.
```

# Test source

```ts
  1   | import { expect, test } from '@playwright/test';
  2   | 
  3   | const baseUrl = (process.env.SMOKE_BASE_URL || 'https://exclusivalarimoveis.com').replace(/\/$/, '');
  4   | const adminEmail = process.env.SMOKE_ADMIN_EMAIL;
  5   | const adminPassword = process.env.SMOKE_ADMIN_PASSWORD;
  6   | const runId = process.env.SMOKE_RUN_ID || `${Date.now()}`;
  7   | 
  8   | if (!adminEmail || !adminPassword) {
  9   |   throw new Error('SMOKE_ADMIN_EMAIL e SMOKE_ADMIN_PASSWORD são obrigatórios para o smoke test.');
  10  | }
  11  | 
  12  | const prefix = `SMOKE ${runId}`;
  13  | const manualVisitName = `${prefix} Agenda`;
  14  | const portalVisitName = `${prefix} Portal`;
  15  | const chatVisitName = `${prefix} Chat`;
  16  | 
  17  | const toDateTimeLocalValue = (value: Date) => {
  18  |   const timezoneOffset = value.getTimezoneOffset() * 60_000;
  19  |   return new Date(value.getTime() - timezoneOffset).toISOString().slice(0, 16);
  20  | };
  21  | 
  22  | const toChatDateText = (value: Date) => {
  23  |   const day = String(value.getDate()).padStart(2, '0');
  24  |   const month = String(value.getMonth() + 1).padStart(2, '0');
  25  |   const year = value.getFullYear();
  26  |   const hour = String(value.getHours()).padStart(2, '0');
  27  |   const minute = String(value.getMinutes()).padStart(2, '0');
  28  |   return `${day}/${month}/${year} ${hour}:${minute}`;
  29  | };
  30  | 
  31  | test.describe.configure({ mode: 'serial' });
  32  | 
  33  | test('smoke end-to-end de agenda, portal inicial e chat', async ({ page, context }) => {
  34  |   test.setTimeout(180_000);
  35  | 
  36  |   const manualDate = new Date(Date.now() + 3 * 60 * 60 * 1000);
  37  |   const portalDate = new Date(Date.now() + 4 * 60 * 60 * 1000);
  38  |   const chatDate = new Date(Date.now() + 5 * 60 * 60 * 1000);
  39  | 
  40  |   await context.route('https://wa.me/**', async (route) => {
  41  |     await route.fulfill({
  42  |       status: 200,
  43  |       contentType: 'text/html',
  44  |       body: '<html><body>WhatsApp smoke stub</body></html>',
  45  |     });
  46  |   });
  47  | 
  48  |   await page.goto(`${baseUrl}/login`, { waitUntil: 'domcontentloaded' });
  49  |   await page.getByPlaceholder('seu@email.com').fill(adminEmail);
  50  |   await page.getByPlaceholder('••••••••').fill(adminPassword);
  51  |   await page.getByRole('button', { name: 'Entrar' }).click();
  52  |   await expect(page).toHaveURL(/\/dashboard/, { timeout: 20_000 });
  53  | 
  54  |   await page.goto(`${baseUrl}/agenda`, { waitUntil: 'domcontentloaded' });
  55  |   await expect(page.getByText('Agenda de Visitas')).toBeVisible();
> 56  |   await page.getByPlaceholder('Ex: Apartamento 3 quartos no Centro').fill(`Imóvel ${manualVisitName}`);
      |                                                                      ^ Error: locator.fill: Test timeout of 180000ms exceeded.
  57  |   await page.getByPlaceholder('Nome do cliente').fill(manualVisitName);
  58  |   await page.getByPlaceholder('(31) 99999-9999').first().fill('(31) 99999-1001');
  59  |   await page.getByPlaceholder('cliente@email.com').fill(`agenda.${runId}@example.com`);
  60  |   await page.locator('input[type="datetime-local"]').first().fill(toDateTimeLocalValue(manualDate));
  61  |   await page.getByPlaceholder('Detalhes do encontro, ponto de referência, imóvel desejado ou contexto do lead').fill(`Smoke test agenda ${runId} - pode ignorar`);
  62  | 
  63  |   const assigneeSelect = page.locator('label').filter({ hasText: 'Responsável' }).locator('select');
  64  |   const assigneeOptions = await assigneeSelect.locator('option').allTextContents();
  65  |   const preferredAssignee = assigneeOptions.find((option) => /Nelson|Roberto|Joice|Jocineide/i.test(option)) || assigneeOptions[0];
  66  |   await assigneeSelect.selectOption({ label: preferredAssignee });
  67  |   await page.getByRole('button', { name: 'Criar visita' }).click();
  68  |   await expect(page.getByText(manualVisitName)).toBeVisible({ timeout: 20_000 });
  69  | 
  70  |   await page.goto(`${baseUrl}/`, { waitUntil: 'domcontentloaded' });
  71  |   const portalOpenButton = page.locator('[aria-label="Abrir atendimento no WhatsApp"], [aria-label="Abrir WhatsApp"]');
  72  |   await portalOpenButton.first().click();
  73  |   await expect(page.getByText('Falar com nossa equipe')).toBeVisible({ timeout: 15_000 });
  74  |   await page.getByPlaceholder('Como você se chama?').fill(portalVisitName);
  75  |   await page.getByPlaceholder('(31) 99999-9999').fill('(31) 99999-1002');
  76  |   await page.locator('input[type="datetime-local"]').fill(toDateTimeLocalValue(portalDate));
  77  |   await page.getByPlaceholder('Ex: melhor horário no fim da tarde, visita com família, imóvel semelhante ao anúncio').fill(`Smoke test portal ${runId} - pode ignorar`);
  78  |   const portalResponsePromise = page.waitForResponse((response) => response.url().includes('/api/portal/chat-lead') && response.request().method() === 'POST');
  79  |   await page.getByRole('button', { name: 'Registrar e abrir WhatsApp' }).click();
  80  |   const portalResponse = await portalResponsePromise;
  81  |   const portalPayload = await portalResponse.json();
  82  |   expect(portalPayload.success).toBeTruthy();
  83  |   expect(portalPayload.visita_id).toBeTruthy();
  84  | 
  85  |   await page.goto(`${baseUrl}/agenda`, { waitUntil: 'domcontentloaded' });
  86  |   await expect(page.getByText(portalVisitName)).toBeVisible({ timeout: 20_000 });
  87  | 
  88  |   await page.goto(`${baseUrl}/portal/classic`, { waitUntil: 'domcontentloaded' });
  89  |   await page.getByAltText('Mascote').first().click();
  90  |   await expect(page.getByText('Assistente Virtual')).toBeVisible({ timeout: 15_000 });
  91  | 
  92  |   await page.getByPlaceholder('Seu nome...').fill(chatVisitName);
  93  |   await page.getByPlaceholder('Seu nome...').press('Enter');
  94  |   await page.getByPlaceholder('(31) 99999-8888').fill('(31) 99999-1003');
  95  |   await page.getByPlaceholder('(31) 99999-8888').press('Enter');
  96  |   await page.getByPlaceholder('seu@email.com').fill('pular');
  97  |   await page.getByPlaceholder('seu@email.com').press('Enter');
  98  |   await page.getByPlaceholder('Ex: apartamento 2 quartos...').fill(`Procuro imóvel para smoke test ${runId}`);
  99  |   await page.getByPlaceholder('Ex: apartamento 2 quartos...').press('Enter');
  100 |   await page.getByPlaceholder('Ex: 25/04/2026 15:30').fill(toChatDateText(chatDate));
  101 |   const chatResponsePromise = page.waitForResponse((response) => response.url().includes('/api/portal/chat-lead') && response.request().method() === 'POST');
  102 |   await page.getByPlaceholder('Ex: 25/04/2026 15:30').press('Enter');
  103 |   const chatResponse = await chatResponsePromise;
  104 |   const chatPayload = await chatResponse.json();
  105 |   expect(chatPayload.success).toBeTruthy();
  106 |   expect(chatPayload.visita_id).toBeTruthy();
  107 |   await expect(page.getByText('Continuar no WhatsApp')).toBeVisible({ timeout: 20_000 });
  108 | 
  109 |   await page.goto(`${baseUrl}/agenda`, { waitUntil: 'domcontentloaded' });
  110 |   await expect(page.getByText(chatVisitName)).toBeVisible({ timeout: 20_000 });
  111 | });
```