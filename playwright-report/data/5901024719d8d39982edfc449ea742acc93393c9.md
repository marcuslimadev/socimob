# Instructions

- Following Playwright test failed.
- Explain why, be concise, respect Playwright best practices.
- Provide a snippet of code with the fix, if possible.

# Test info

- Name: basic.spec.ts >> verifica a pagina de login ou dashboard responde
- Location: e2e\basic.spec.ts:3:1

# Error details

```
Error: page.goto: net::ERR_CONNECTION_REFUSED at http://localhost:8000/
Call log:
  - navigating to "http://localhost:8000/", waiting until "load"

```

# Test source

```ts
  1 | import { test, expect } from '@playwright/test';
  2 | 
  3 | test('verifica a pagina de login ou dashboard responde', async ({ page }) => {
> 4 |   await page.goto('/');
    |              ^ Error: page.goto: net::ERR_CONNECTION_REFUSED at http://localhost:8000/
  5 |   await expect(page).toHaveTitle(/.*|Socimob/i);
  6 | });
  7 | 
```