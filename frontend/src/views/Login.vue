<template>
  <div class="min-h-screen flex items-center justify-center p-6 app-container">
    <div class="grid gap-8 w-full max-w-5xl items-center md:grid-cols-2">
      <div class="hidden md:flex flex-col gap-4 text-left p-8 bauhaus-card">
        <p class="text-sm uppercase tracking-[0.3em] text-primary-500 font-semibold">Acesso seguro</p>
        <h1 class="text-4xl font-black leading-tight text-ink">Bem-vindo ao SociMob</h1>
        <p class="text-lg text-ink/80 font-medium">
          Gestão imobiliária com estética Bauhaus: superfícies suaves, hierarquia clara e experiência fluida para equipes e clientes.
        </p>
        <div class="flex flex-wrap gap-3 mt-2">
          <span class="px-3 py-1 bg-primary-50 text-primary-700 font-semibold rounded-md">Leads organizados</span>
          <span class="px-3 py-1 bg-sunshine-50 text-ink font-semibold rounded-md">Templates de vendas</span>
          <span class="px-3 py-1 bg-accent-50 text-accent-700 font-semibold rounded-md">Isolamento por tenant</span>
        </div>
      </div>

      <div class="bauhaus-card w-full max-w-lg p-10 shadow-2xl">
        <div class="text-center mb-8">
          <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-primary-500 text-white font-black text-2xl shadow-lg">
            S
          </div>
          <h2 class="text-3xl font-black text-ink mt-4">Entrar</h2>
          <p class="text-ink/70 font-medium">Acesse o painel administrativo e continue seus atendimentos.</p>
        </div>

        <form @submit.prevent="handleLogin" class="space-y-6">
          <div>
            <label class="block text-sm font-bold text-ink mb-2 uppercase tracking-wide">
              E-mail
            </label>
            <input
              v-model="email"
              type="email"
              required
              class="w-full px-4 py-3 bauhaus-input"
              placeholder="seu@email.com"
            />
          </div>

          <div>
            <label class="block text-sm font-bold text-ink mb-2 uppercase tracking-wide">
              Senha
            </label>
            <input
              v-model="senha"
              type="password"
              required
              class="w-full px-4 py-3 bauhaus-input"
              placeholder="••••••••"
            />
          </div>

          <div v-if="error" class="bg-accent-50 border-l-4 border-accent-500 text-accent-700 px-4 py-3 text-sm font-semibold rounded-lg">
            {{ error }}
          </div>

          <button
            type="submit"
            :disabled="loading"
            class="w-full bauhaus-button py-4 text-base"
          >
            <span v-if="!loading">Entrar</span>
            <span v-else class="flex items-center justify-center gap-2">
              <svg class="animate-spin h-5 w-5" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
              </svg>
              Entrando...
            </span>
          </button>
        </form>
      </div>
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
