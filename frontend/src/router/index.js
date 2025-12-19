import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = createRouter({
  history: createWebHistory(),
  routes: [
    {
      path: '/login',
      name: 'Login',
      component: () => import('../views/Login.vue'),
      meta: { requiresAuth: false }
    },
    {
      path: '/',
      name: 'Dashboard',
      component: () => import('../views/Dashboard.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/leads',
      name: 'Leads',
      component: () => import('../views/Leads.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/imoveis',
      name: 'Imoveis',
      component: () => import('../views/Imoveis.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/conversas',
      name: 'Conversas',
      component: () => import('../views/Conversas.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/importacao',
      name: 'ImportacaoImoveis',
      component: () => import('../views/ImportacaoImoveis.vue'),
      meta: { requiresAuth: true }
    },
    {
      path: '/super-admin',
      name: 'SuperAdmin',
      component: () => import('../views/SuperAdmin.vue'),
      meta: { requiresAuth: true, role: 'super_admin' }
    },
    {
      path: '/super-admin/tenants',
      name: 'Tenants',
      component: () => import('../views/Tenants.vue'),
      meta: { requiresAuth: true, role: 'super_admin' }
    },
    {
      path: '/super-admin/users',
      name: 'Users',
      component: () => import('../views/Users.vue'),
      meta: { requiresAuth: true, role: 'super_admin' }
    },
    {
      path: '/super-admin/subscriptions',
      name: 'Subscriptions',
      component: () => import('../views/Subscriptions.vue'),
      meta: { requiresAuth: true, role: 'super_admin' }
    }
  ]
})

router.beforeEach((to, from, next) => {
  const authStore = useAuthStore()
  
  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    next('/login')
  } else if (to.path === '/login' && authStore.isAuthenticated) {
    next('/')
  } else if (to.meta.role && authStore.user?.role !== to.meta.role) {
    next('/')
  } else {
    next()
  }
})

export default router
