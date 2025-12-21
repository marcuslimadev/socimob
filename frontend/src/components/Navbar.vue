<template>
  <nav class="bauhaus-nav-shell mx-3 mt-3 mb-6">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2">
      <div class="flex justify-between h-16 items-center gap-4">
        <div class="flex items-center space-x-4">
          <router-link to="/" class="flex items-center space-x-3">
            <div class="logo-mark">
              <span class="logo-letter">S</span>
            </div>
            <div>
              <h1 class="text-2xl font-black leading-tight">SOCIMOB</h1>
              <p class="text-xs uppercase tracking-[0.32em] font-semibold opacity-90">Plataforma imobiliária</p>
            </div>
          </router-link>

          <button
            class="md:hidden inline-flex items-center justify-center p-2 text-white/90 hover:text-white nav-toggle"
            @click="showMobileMenu = !showMobileMenu"
            aria-label="Abrir menu"
          >
            <svg v-if="!showMobileMenu" class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
            <svg v-else class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
          </button>

          <div class="hidden md:flex space-x-1 ml-4">
            <router-link
              to="/"
              class="nav-pill"
              :class="isActive('/') || isActive('/dashboard') ? 'nav-pill--active' : ''"
            >
              Dashboard
            </router-link>
            <router-link
              to="/leads"
              class="nav-pill"
              :class="isActive('/leads') ? 'nav-pill--active' : ''"
            >
              Leads
            </router-link>
            <router-link
              to="/imoveis"
              class="nav-pill"
              :class="isActive('/imoveis') ? 'nav-pill--active' : ''"
            >
              Imóveis
            </router-link>
            <router-link
              to="/conversas"
              class="nav-pill"
              :class="isActive('/conversas') ? 'nav-pill--active' : ''"
            >
              Conversas
            </router-link>
            <router-link
              to="/importacao"
              class="nav-pill"
              :class="isActive('/importacao') ? 'nav-pill--active' : ''"
            >
              Importação
            </router-link>
            <router-link
              v-if="isSuperAdmin"
              to="/super-admin"
              class="nav-pill nav-pill--super"
              :class="isActive('/super-admin') ? 'nav-pill--active' : ''"
            >
              ⚙️ Super Admin
            </router-link>
          </div>
        </div>

        <div class="flex items-center space-x-4 text-white">
          <div class="text-right hidden sm:block">
            <p class="text-sm font-semibold drop-shadow">{{ user?.name || user?.nome || user?.email }}</p>
            <p class="text-xs uppercase tracking-[0.2em] text-white/80">{{ getRoleLabel(user?.role) }}</p>
          </div>
          <button @click="handleLogout" class="bauhaus-button px-4 py-2 text-sm">
            Sair
          </button>
        </div>
      </div>
    </div>

    <div v-if="showMobileMenu" class="md:hidden mobile-panel">
      <div class="px-4 pt-2 pb-3 space-y-1">
        <router-link to="/" class="mobile-link" :class="isActive('/') || isActive('/dashboard') ? 'mobile-link--active' : ''">
          Dashboard
        </router-link>
        <router-link to="/leads" class="mobile-link" :class="isActive('/leads') ? 'mobile-link--active' : ''">
          Leads
        </router-link>
        <router-link to="/imoveis" class="mobile-link" :class="isActive('/imoveis') ? 'mobile-link--active' : ''">
          Imóveis
        </router-link>
        <router-link to="/conversas" class="mobile-link" :class="isActive('/conversas') ? 'mobile-link--active' : ''">
          Conversas
        </router-link>
        <router-link to="/importacao" class="mobile-link" :class="isActive('/importacao') ? 'mobile-link--active' : ''">
          Importação
        </router-link>
        <router-link
          v-if="isSuperAdmin"
          to="/super-admin"
          class="mobile-link"
          :class="isActive('/super-admin') ? 'mobile-link--active' : ''"
        >
          ⚙️ Super Admin
        </router-link>
      </div>
    </div>
  </nav>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()

const user = computed(() => authStore.user)
const showMobileMenu = ref(false)

const isSuperAdmin = computed(() => user.value?.role === 'super_admin')

const isActive = (path) => {
  if (!path) return false

  // mantém realce para rotas aninhadas (ex.: /leads/123)
  if (path === '/') {
    return route.path === '/' || route.path.startsWith('/dashboard')
  }

  return route.path === path || route.path.startsWith(`${path}/`)
}

const getRoleLabel = (role) => {
  const labels = {
    super_admin: 'Super Admin',
    admin: 'Administrador',
    user: 'Usuário',
    corretor: 'Corretor',
    cliente: 'Cliente'
  }
  return labels[role] || role
}

watch(
  () => route.path,
  () => {
    showMobileMenu.value = false
  }
)

const handleLogout = async () => {
  await authStore.logout()
  router.push('/login')
}
</script>

<style scoped>
nav {
  backdrop-filter: blur(12px);
}

.logo-mark {
  width: 48px;
  height: 48px;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.9), rgba(255, 255, 255, 0.7));
  border: 2px solid rgba(255, 255, 255, 0.5);
  border-radius: 14px;
  display: grid;
  place-items: center;
  box-shadow: 0 10px 20px rgba(0, 0, 0, 0.15);
}

.logo-letter {
  color: var(--bauhaus-blue);
  font-weight: 900;
  font-size: 1.1rem;
  letter-spacing: 0.2em;
}

.nav-pill {
  color: rgba(255, 255, 255, 0.9);
  padding: 0.6rem 0.9rem;
  border-radius: 14px;
  border: 1px solid transparent;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  transition: all 0.2s ease;
  box-shadow: inset 0 0 0 1px rgba(255, 255, 255, 0.08);
}

.nav-pill:hover {
  background: rgba(255, 255, 255, 0.16);
  border-color: rgba(255, 255, 255, 0.22);
}

.nav-pill--active {
  background: rgba(255, 255, 255, 0.24);
  border-color: rgba(255, 255, 255, 0.32);
  box-shadow: 0 10px 24px rgba(0, 0, 0, 0.14);
  color: #0b3c6f;
}

.nav-pill--super {
  background: rgba(255, 255, 255, 0.1);
  border-color: rgba(255, 255, 255, 0.24);
}

.mobile-panel {
  border-top: 1px solid rgba(255, 255, 255, 0.28);
  background: rgba(255, 255, 255, 0.08);
  backdrop-filter: blur(16px);
  padding-bottom: 1rem;
}

.mobile-link {
  display: block;
  padding: 0.9rem 1rem;
  border-radius: 16px;
  color: rgba(255, 255, 255, 0.95);
  font-weight: 700;
  letter-spacing: 0.04em;
  border: 1px solid transparent;
  transition: all 0.2s ease;
}

.mobile-link--active {
  background: rgba(255, 255, 255, 0.22);
  border-color: rgba(255, 255, 255, 0.24);
  color: #0b3c6f;
  box-shadow: 0 10px 24px rgba(0, 0, 0, 0.15);
}

.nav-toggle {
  border-radius: 14px;
  border: 1px solid rgba(255, 255, 255, 0.4);
}
</style>
