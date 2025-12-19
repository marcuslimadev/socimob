<template>
  <div class="min-h-screen bg-slate-50">
    <!-- Header -->
    <header class="bg-slate-900 border-b border-slate-800">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
        <div class="flex justify-between items-center">
          <div>
            <h1 class="text-3xl font-black text-white tracking-tighter uppercase">SOCIMOB <span class="font-light text-slate-400">| Super Admin</span></h1>
            <p class="text-slate-400 mt-1 font-medium">Gerenciamento completo do SaaS Imobiliário</p>
          </div>
          <div class="flex gap-3">
            <router-link to="/" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-none border border-slate-700 transition font-bold uppercase text-sm tracking-wide">
              ← Dashboard
            </router-link>
            <button @click="handleLogout" class="px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-none transition font-bold uppercase text-sm tracking-wide">
              Sair
            </button>
          </div>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- Stats Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div class="bg-white border-2 border-slate-200 p-6 hover:border-slate-900 transition-colors duration-300">
          <div class="flex items-center">
            <div class="p-3 bg-slate-100 border border-slate-200">
              <svg class="w-8 h-8 text-slate-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
              </svg>
            </div>
            <div class="ml-4">
              <p class="text-sm font-bold text-slate-500 uppercase tracking-wide">Total Empresas</p>
              <p class="text-3xl font-black text-slate-900">{{ stats.totalTenants }}</p>
            </div>
          </div>
        </div>

        <div class="bg-white border-2 border-slate-200 p-6 hover:border-slate-900 transition-colors duration-300">
          <div class="flex items-center">
            <div class="p-3 bg-slate-100 border border-slate-200">
              <svg class="w-8 h-8 text-slate-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
              </svg>
            </div>
            <div class="ml-4">
              <p class="text-sm font-bold text-slate-500 uppercase tracking-wide">Total Usuários</p>
              <p class="text-3xl font-black text-slate-900">{{ stats.totalUsers }}</p>
            </div>
          </div>
        </div>

        <div class="bg-white border-2 border-slate-200 p-6 hover:border-slate-900 transition-colors duration-300">
          <div class="flex items-center">
            <div class="p-3 bg-slate-100 border border-slate-200">
              <svg class="w-8 h-8 text-slate-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
              </svg>
            </div>
            <div class="ml-4">
              <p class="text-sm font-bold text-slate-500 uppercase tracking-wide">Assinaturas</p>
              <p class="text-3xl font-black text-slate-900">{{ stats.activeSubscriptions }}</p>
            </div>
          </div>
        </div>

        <div class="bg-white border-2 border-slate-200 p-6 hover:border-slate-900 transition-colors duration-300">
          <div class="flex items-center">
            <div class="p-3 bg-slate-100 border border-slate-200">
              <svg class="w-8 h-8 text-slate-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
              </svg>
            </div>
            <div class="ml-4">
              <p class="text-sm font-bold text-slate-500 uppercase tracking-wide">Receita Mensal</p>
              <p class="text-3xl font-black text-slate-900">R$ {{ stats.monthlyRevenue.toLocaleString('pt-BR') }}</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Action Cards -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <router-link to="/super-admin/tenants" class="bg-white border-2 border-slate-200 p-6 hover:border-slate-900 transition-all group">
          <div class="flex items-center justify-between mb-4">
            <div class="p-3 bg-slate-100 border border-slate-200 group-hover:bg-slate-900 group-hover:text-white transition-colors">
              <svg class="w-8 h-8 text-slate-900 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
              </svg>
            </div>
            <span class="text-slate-900 font-bold text-2xl group-hover:translate-x-2 transition-transform">→</span>
          </div>
          <h3 class="text-xl font-black text-slate-900 mb-2 uppercase tracking-tight">Gerenciar Empresas</h3>
          <p class="text-slate-600 font-medium">Cadastrar e gerenciar imobiliárias do sistema</p>
        </router-link>

        <router-link to="/super-admin/users" class="bg-white border-2 border-slate-200 p-6 hover:border-slate-900 transition-all group">
          <div class="flex items-center justify-between mb-4">
            <div class="p-3 bg-slate-100 border border-slate-200 group-hover:bg-slate-900 group-hover:text-white transition-colors">
              <svg class="w-8 h-8 text-slate-900 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
              </svg>
            </div>
            <span class="text-slate-900 font-bold text-2xl group-hover:translate-x-2 transition-transform">→</span>
          </div>
          <h3 class="text-xl font-black text-slate-900 mb-2 uppercase tracking-tight">Gerenciar Usuários</h3>
          <p class="text-slate-600 font-medium">Cadastrar usuários e definir permissões</p>
        </router-link>

        <router-link to="/super-admin/subscriptions" class="bg-white border-2 border-slate-200 p-6 hover:border-slate-900 transition-all group">
          <div class="flex items-center justify-between mb-4">
            <div class="p-3 bg-slate-100 border border-slate-200 group-hover:bg-slate-900 group-hover:text-white transition-colors">
              <svg class="w-8 h-8 text-slate-900 group-hover:text-white transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
              </svg>
            </div>
            <span class="text-slate-900 font-bold text-2xl group-hover:translate-x-2 transition-transform">→</span>
          </div>
          <h3 class="text-xl font-black text-slate-900 mb-2 uppercase tracking-tight">Assinaturas</h3>
          <p class="text-slate-600 font-medium">Controlar pagamentos e planos</p>
        </router-link>
      </div>
    </main>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const authStore = useAuthStore()

const stats = ref({
  totalTenants: 0,
  totalUsers: 0,
  activeSubscriptions: 0,
  monthlyRevenue: 0
})

function handleLogout() {
  authStore.logout()
  router.push('/login')
}

onMounted(() => {
  // TODO: Carregar estatísticas da API
  stats.value = {
    totalTenants: 12,
    totalUsers: 48,
    activeSubscriptions: 10,
    monthlyRevenue: 5400
  }
})
</script>
