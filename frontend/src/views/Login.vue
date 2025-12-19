<template>
  <div class="min-h-screen bg-slate-50 flex items-center justify-center p-4">
    <div class="bg-white border border-slate-200 rounded-none shadow-xl w-full max-w-md p-10">
      <div class="text-center mb-10">
        <h1 class="text-4xl font-black text-slate-900 mb-2 tracking-tighter uppercase">SOCIMOB</h1>
        <p class="text-slate-600 font-medium">Sistema de Gestão Imobiliária</p>
      </div>

      <form @submit.prevent="handleLogin" class="space-y-6">
        <div>
          <label class="block text-sm font-bold text-slate-900 mb-2 uppercase tracking-wide">
            E-mail
          </label>
          <input
            v-model="email"
            type="email"
            required
            class="w-full px-4 py-3 border-2 border-slate-200 rounded-none focus:ring-0 focus:border-slate-900 transition outline-none font-medium text-slate-900 placeholder-slate-400"
            placeholder="seu@email.com"
          />
        </div>

        <div>
          <label class="block text-sm font-bold text-slate-900 mb-2 uppercase tracking-wide">
            Senha
          </label>
          <input
            v-model="senha"
            type="password"
            required
            class="w-full px-4 py-3 border-2 border-slate-200 rounded-none focus:ring-0 focus:border-slate-900 transition outline-none font-medium text-slate-900 placeholder-slate-400"
            placeholder="••••••••"
          />
        </div>

        <div v-if="error" class="bg-red-50 border-l-4 border-red-500 text-red-700 px-4 py-3 text-sm font-medium">
          {{ error }}
        </div>

        <button
          type="submit"
          :disabled="loading"
          class="w-full bg-slate-900 hover:bg-slate-800 text-white font-bold py-4 rounded-none transition disabled:opacity-50 disabled:cursor-not-allowed uppercase tracking-widest"
        >
          <span v-if="!loading">Entrar</span>
          <span v-else class="flex items-center justify-center">
            <svg class="animate-spin h-5 w-5 mr-2" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
            Entrando...
          </span>
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const router = useRouter()
const authStore = useAuthStore()

const email = ref('')
const senha = ref('')
const loading = ref(false)
const error = ref('')

const handleLogin = async () => {
  loading.value = true
  error.value = ''
  
  const success = await authStore.login(email.value, senha.value)
  
  if (success) {
    router.push('/')
  } else {
    error.value = authStore.error
  }
  
  loading.value = false
}
</script>
