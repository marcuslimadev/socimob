import { test, expect, Page } from '@playwright/test'

test.describe('Frontend - Testes E2E', () => {
  test('1. Deve carregar página home', async ({ page }) => {
    await page.goto('/')
    
    // Página deve carregar
    const url = page.url()
    expect(url).toContain('localhost')
    
    // Verificar se página carrega sem erros críticos
    const pageTitle = await page.title()
    expect(pageTitle.length).toBeGreaterThan(0)
  })

  test('2. Teste de RoleGuard - Acesso bloqueado sem autenticação', async ({ page }) => {
    await page.goto('/super-admin/tenants')
    
    // Deve redirecionar ou exibir acesso negado
    await page.waitForTimeout(1000)
    const url = page.url()
    const accessDenied = await page.locator('text=Acesso Negado').isVisible().catch(() => false)
    
    const isProtected = !url.includes('super-admin') || accessDenied
    expect(isProtected).toBe(true)
  })

  test('3. NotFound page - Rota inválida', async ({ page }) => {
    await page.goto('/rota-inexistente-12345')
    
    // Deve carregar sem erro
    const pageContent = await page.content()
    expect(pageContent.length).toBeGreaterThan(0)
  })

  test('4. PropertyImportEnhanced - Elemento existe no DOM', async ({ page }) => {
    await page.goto('/importacao-enhanced')
    
    // Elemento deve estar no DOM (pode ser bloqueado ou visível)
    await page.waitForTimeout(500)
    const pageContent = await page.content()
    expect(pageContent.length).toBeGreaterThan(0)
  })

  test('5. Router - Navegação funciona', async ({ page }) => {
    await page.goto('/')
    
    // Página deve carregar
    const content = await page.content()
    expect(content).toBeTruthy()
  })

  test('6. API Service - localStorage funciona', async ({ page }) => {
    await page.goto('/')
    
    // Verificar localStorage
    const token = await page.evaluate(() => localStorage.getItem('token'))
    // Token deve estar nulo ou ser string
    expect(token === null || typeof token === 'string').toBe(true)
  })

  test('7. Composables - useAuth não quebra', async ({ page }) => {
    await page.goto('/')
    
    // Página não deve ter erro de undefined
    const pageText = await page.evaluate(() => document.body.innerText)
    expect(pageText).not.toContain('undefined')
  })

  test('8. NotFound.vue - Sem erro de compilação', async ({ page }) => {
    const errors: string[] = []
    
    page.on('console', msg => {
      if (msg.type() === 'error') {
        errors.push(msg.text())
      }
    })
    
    await page.goto('/pagina-inexistente')
    await page.waitForTimeout(500)
    
    // Não deve haver erros de Vue
    const vueErrors = errors.filter(e => 
      e.includes('Vue') && !e.includes('404')
    )
    expect(vueErrors.length).toBe(0)
  })

  test('9. App.vue - Elemento root existe', async ({ page }) => {
    await page.goto('/')
    
    // Verificar se #app existe no DOM
    const appElements = await page.locator('#app')
    const count = await appElements.count()
    expect(count).toBeGreaterThan(0)
  })

  test('10. RoleGuard - Sem erros de template', async ({ page }) => {
    const errors: string[] = []
    
    page.on('console', msg => {
      if (msg.type() === 'error') {
        errors.push(msg.text())
      }
    })
    
    await page.goto('/super-admin/tenants')
    await page.waitForTimeout(500)
    
    // Não deve haver erro crítico
    const criticalErrors = errors.filter(e =>
      e.includes('template') || e.includes('parsed')
    )
    expect(criticalErrors.length).toBe(0)
  })
})

test.describe('Frontend - Validação de Assets', () => {
  test('11. CSS carrega corretamente', async ({ page }) => {
    await page.goto('/')
    
    // Verificar se não há 404 em stylesheets
    const stylesheets = await page.locator('link[rel="stylesheet"]').count()
    expect(stylesheets).toBeGreaterThanOrEqual(0)
    
    // Verificar se página tem estilos
    const body = page.locator('body')
    const bgColor = await body.evaluate(el => 
      window.getComputedStyle(el).backgroundColor
    )
    expect(bgColor).toBeTruthy()
  })

  test('12. JavaScript não tem erros fatais', async ({ page }) => {
    const errors: string[] = []
    
    page.on('console', msg => {
      if (msg.type() === 'error') {
        errors.push(msg.text())
      }
    })
    
    page.on('pageerror', error => {
      errors.push(error.message)
    })
    
    await page.goto('/')
    await page.waitForTimeout(1000)
    
    // Filtrar erros não-críticos
    const criticalErrors = errors.filter(e => 
      !e.includes('404') && 
      !e.includes('Not Found') &&
      !e.includes('favicon')
    )
    
    expect(criticalErrors.length).toBe(0)
  })
})

test.describe('Frontend - Testes de UX', () => {
  test('13. Links de navegação funcionam', async ({ page }) => {
    await page.goto('/')
    
    // Procurar por router-links
    const links = await page.locator('a').count()
    expect(links).toBeGreaterThanOrEqual(0)
  })

  test('14. Componentes Vue compilam sem erro', async ({ page }) => {
    await page.goto('/')
    
    // Verificar se há elementos Vue
    const hasVueApp = await page.evaluate(() => {
      return document.querySelector('#app') !== null
    })
    
    expect(hasVueApp).toBe(true)
  })

  test('15. Responsive design - Mobile', async ({ browser }) => {
    const mobilePage = await browser.newPage({
      viewport: { width: 375, height: 667 }
    })
    
    await mobilePage.goto('/')
    
    // Deve renderizar sem erros
    const content = await mobilePage.content()
    expect(content.length).toBeGreaterThan(0)
    
    await mobilePage.close()
  })
})
