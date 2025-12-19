<template>
  <nav class="bg-white shadow-sm">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="flex justify-between h-16">
        <div class="flex items-center space-x-3">
          <router-link to="/" class="flex items-center space-x-2">
            <div class="w-8 h-8 bg-gradient-to-br from-indigo-600 to-purple-600 rounded-lg flex items-center justify-center">
              <span class="text-white font-bold text-lg">S</span>
            </div>
            <h1 class="text-xl font-bold bg-gradient-to-r from-indigo-600 to-purple-600 bg-clip-text text-transparent">SOCIMOB</h1>
          </router-link>

          <button
            class="md:hidden inline-flex items-center justify-center p-2 rounded-md text-gray-600 hover:text-gray-900 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-indigo-500"
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

          <div class="hidden md:flex space-x-1 ml-8">
            <router-link
              to="/"
              class="px-3 py-2 rounded-lg text-sm font-medium transition"
              :class="isActive('/') || isActive('/dashboard') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-100'"
            >
              Dashboard
            </router-link>
            <router-link
              to="/leads"
              class="px-3 py-2 rounded-lg text-sm font-medium transition"
              :class="isActive('/leads') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-100'"
            >
              Leads
            </router-link>
            <router-link
              to="/imoveis"
              class="px-3 py-2 rounded-lg text-sm font-medium transition"
              :class="isActive('/imoveis') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-100'"
            >
              Imóveis
            </router-link>
            <router-link
              to="/conversas"
              class="px-3 py-2 rounded-lg text-sm font-medium transition"
              :class="isActive('/conversas') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-100'"
            >
              Conversas
            </router-link>
            <router-link
              to="/importacao"
              class="px-3 py-2 rounded-lg text-sm font-medium transition"
              :class="isActive('/importacao') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-100'"
            >
              Importação
            </router-link>
            <router-link
              v-if="isSuperAdmin"
              to="/super-admin"
              class="px-3 py-2 rounded-lg text-sm font-medium transition border-l border-gray-200 ml-2"
              :class="isActive('/super-admin') ? 'bg-purple-50 text-purple-700' : 'text-purple-600 hover:bg-purple-50'"
            >
              ⚙️ Super Admin
            </router-link>
          </div>
        </div>

        <div class="flex items-center space-x-3">
          <div class="text-right hidden sm:block">
            <p class="text-sm font-medium text-gray-900">{{ user?.name || user?.nome || user?.email }}</p>
            <p class="text-xs text-gray-500">{{ getRoleLabel(user?.role) }}</p>
          </div>
          <button
            @click="handleLogout"
            class="px-4 py-2 text-sm font-medium text-white bg-red-500 hover:bg-red-600 rounded-lg transition"
          >
            Sair
          </button>
        </div>
      </div>
    </div>

    <div v-if="showMobileMenu" class="md:hidden border-t border-gray-100">
      <div class="px-4 pt-2 pb-3 space-y-1">
        <router-link
          to="/"
          class="block px-3 py-2 rounded-lg text-base font-medium"
          :class="isActive('/') || isActive('/dashboard') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50'"
        >
          Dashboard
        </router-link>
        <router-link
          to="/leads"
          class="block px-3 py-2 rounded-lg text-base font-medium"
          :class="isActive('/leads') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50'"
        >
          Leads
        </router-link>
        <router-link
          to="/imoveis"
          class="block px-3 py-2 rounded-lg text-base font-medium"
          :class="isActive('/imoveis') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50'"
        >
          Imóveis
        </router-link>
        <router-link
          to="/conversas"
          class="block px-3 py-2 rounded-lg text-base font-medium"
          :class="isActive('/conversas') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50'"
        >
          Conversas
        </router-link>
        <router-link
          to="/importacao"
          class="block px-3 py-2 rounded-lg text-base font-medium"
          :class="isActive('/importacao') ? 'bg-indigo-50 text-indigo-700' : 'text-gray-700 hover:bg-gray-50'"
        >
          Importação
        </router-link>
        <router-link
          v-if="isSuperAdmin"
          to="/super-admin"
          class="block px-3 py-2 rounded-lg text-base font-medium border-t border-gray-200 mt-2 pt-3"
          :class="isActive('/super-admin') ? 'bg-purple-50 text-purple-700' : 'text-purple-600 hover:bg-purple-50'"
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

const isSuperAdmin = computed(() => {
  return user.value?.role === 'super_admin'
})

const isActive = (path) => {
  return route.path === path
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
