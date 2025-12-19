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
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuth } from '@/composables/useAuth'

console.log('🚀 Login.vue carregado!')

const router = useRouter()
const route = useRoute()
const auth = useAuth()

const email = ref('')
const senha = ref('')
const loading = ref(false)
const error = ref('')

onMounted(() => {
  console.log('✅ Login.vue montado! Componente ativo.')
  console.log('Auth disponível:', !!auth)
})

const handleLogin = async () => {
  console.log('🔐 handleLogin CHAMADO!')
  console.log('Email:', email.value, 'Senha:', senha.value ? '***' : 'vazio')
  
  loading.value = true
  error.value = ''
  
  console.log('🔐 Iniciando login...', { email: email.value })
  
  try {
    const success = await auth.login(email.value, senha.value)
    
    console.log('📥 Resposta do login:', { success, hasError: !!auth.error.value })
    
    if (success) {
      console.log('✅ Login bem-sucedido! Redirecionando...')
      const redirectPath = String(route.query.redirect || '/')
      if (redirectPath.startsWith('/configuracoes')) {
        router.push({ name: 'Settings' })
      } else {
        router.push(redirectPath)
      }
    } else {
      const errorMsg = auth.error.value || 'Erro ao fazer login'
      console.error('❌ Login falhou:', errorMsg)
      error.value = errorMsg
    }
  } catch (err) {
    console.error('💥 Exceção no login:', err)
    error.value = 'Erro ao fazer login. Verifique suas credenciais.'
  }
  
  loading.value = false
}
</script>
