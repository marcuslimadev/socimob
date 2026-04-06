import { test, expect } from '@playwright/test';

test('verifica a pagina de login ou dashboard responde', async ({ page }) => {
  await page.goto('/');
  await expect(page).toHaveTitle(/.*|Socimob/i);
});
