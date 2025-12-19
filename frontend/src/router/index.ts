import { createRouter, createWebHistory } from 'vue-router'
import { useAuth } from '@/composables/useAuth'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'Login',
      component: () => import('@/views/Login.vue'),
      meta: { requiresAuth: false, public: true }
    },
    {
      path: '/',
      name: 'Dashboard',
      component: () => import('@/views/Dashboard.vue'),
      meta: { requiresAuth: true, roles: ['super_admin', 'admin', 'user', 'client'] }
    },
    {
      path: '/leads',
      name: 'Leads',
      component: () => import('@/views/Leads.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'user'] }
    },
    {
      path: '/imoveis',
      name: 'Imoveis',
      component: () => import('@/views/Imoveis.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'user', 'client'] }
    },
    {
      path: '/conversas',
      name: 'Conversas',
      component: () => import('@/views/Conversas.vue'),
      meta: { requiresAuth: true, roles: ['admin', 'user'] }
    },
    {
      path: '/importacao',
      name: 'ImportacaoImoveis',
      component: () => import('@/views/ImportacaoImoveis.vue'),
      meta: { requiresAuth: true, roles: ['admin'] }
    },
    {
      path: '/importacao-enhanced',
      name: 'PropertyImportEnhanced',
      component: () => import('@/views/PropertyImportEnhanced.vue'),
      meta: { requiresAuth: true, roles: ['admin'] }
    },
    {
      path: '/super-admin',
      name: 'SuperAdmin',
      component: () => import('@/views/SuperAdmin.vue'),
      meta: { requiresAuth: true, roles: ['super_admin'] }
    },
    {
      path: '/super-admin/tenants',
      name: 'Tenants',
      component: () => import('@/views/Tenants.vue'),
      meta: { requiresAuth: true, roles: ['super_admin'] }
    },
    {
      path: '/super-admin/tenants-enhanced',
      name: 'TenantsEnhanced',
      component: () => import('@/views/TenantsEnhanced.vue'),
      meta: { requiresAuth: true, roles: ['super_admin'] }
    },
    {
      path: '/super-admin/users',
      name: 'Users',
      component: () => import('@/views/Users.vue'),
      meta: { requiresAuth: true, roles: ['super_admin'] }
    },
    {
      path: '/super-admin/subscriptions',
      name: 'Subscriptions',
      component: () => import('@/views/Subscriptions.vue'),
      meta: { requiresAuth: true, roles: ['super_admin'] }
    },
    {
      path: '/:pathMatch(.*)*',
      name: 'NotFound',
      component: () => import('@/views/NotFound.vue'),
      meta: { requiresAuth: false }
    }
  ]
})

// Guard global para proteção de rotas
router.beforeEach(async (to, from, next) => {
  const auth = useAuth()
  const { isAuthenticated, hasPermission, isActive, checkAuth } = auth

  // Se rota requer autenticação
  if (to.meta.requiresAuth) {
    // Verificar se token ainda é válido
    if (!isAuthenticated) {
      next('/login')
      return
    }

    // Verificar se usuário está ativo
    if (!isActive) {
      await auth.logout()
      next('/login')
      return
    }

    // Verificar roles se especificado
    if (to.meta.roles && !hasPermission(to.meta.roles)) {
      console.warn(`Acesso negado: usuário ${auth.user?.email} não tem permissão para acessar ${to.path}`)
      next('/')
      return
    }
  }

  // Se está no login e autenticado, redireciona para dashboard
  if (to.path === '/login' && isAuthenticated) {
    next('/')
    return
  }

  next()
})

// Hook para verificar autenticação ao carregar app
router.afterEach((to, from) => {
  // Atualizar título da página
  document.title = `${to.name || 'Página'} - EXCLUSIVA`
})

export default router
