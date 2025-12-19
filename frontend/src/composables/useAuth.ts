// Composable para gerenciar autenticação com Bearer token
import { ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api.ts'

export function useAuth() {
  const router = useRouter()
  const user = ref(null)
  const token = ref(localStorage.getItem('token') || null)
  const loading = ref(false)
  const error = ref(null)
  const tenantId = ref(localStorage.getItem('tenant_id') || null)

  // Getters para verificar roles
  const isAuthenticated = computed(() => !!token.value)
  const isSuperAdmin = computed(() => user.value?.role === 'super_admin')
  const isAdmin = computed(() => user.value?.role === 'admin')
  const isUser = computed(() => user.value?.role === 'user')
  const isClient = computed(() => user.value?.role === 'client')
  const isActive = computed(() => user.value?.is_active === 1 || user.value?.is_active === true)

  // Gerar Bearer token no formato esperado pelo backend
  const generateBearerToken = (userId, timestamp, secret) => {
    return btoa(`${userId}|${timestamp}|${secret}`)
  }

  // Login com email e senha
  const login = async (email, senha) => {
    loading.value = true
    error.value = null

    console.log('🔑 useAuth.login chamado', { email, senha: '***' })

    try {
      console.log('📡 Enviando requisição para /auth/login...')
      const response = await api.post('/auth/login', { email, senha })
      
      console.log('📥 Resposta recebida:', {
        status: response.status,
        data: response.data,
        success: response.data.success,
        hasToken: !!response.data.token
      })

      if (response.data.success && response.data.token) {
        token.value = response.data.token
        user.value = response.data.user
        tenantId.value = response.data.user?.tenant_id || null

        // Salvar no localStorage
        localStorage.setItem('token', token.value)
        localStorage.setItem('user', JSON.stringify(user.value))
        if (tenantId.value) {
          localStorage.setItem('tenant_id', tenantId.value)
        }

        // Atualizar header de autenticação
        api.defaults.headers.common['Authorization'] = `Bearer ${token.value}`

        console.log('✅ Login bem-sucedido!', { user: user.value.name, role: user.value.role })
        return true
      } else {
        const msg = response.data.message || 'Credenciais inválidas'
        error.value = msg
        console.error('❌ Login falhou:', msg)
        return false
      }
    } catch (err) {
      const msg = err.response?.data?.message || err.message || 'Erro ao fazer login'
      error.value = msg
      console.error('💥 Exceção no login:', {
        message: msg,
        error: err,
        response: err.response?.data
      })
      return false
    } finally {
      loading.value = false
      console.log('🏁 Login finalizado', { loading: loading.value })
    }
  }

  // Logout
  const logout = async () => {
    try {
      await api.post('/auth/logout')
    } catch (err) {
      console.error('Erro ao fazer logout:', err)
    } finally {
      token.value = null
      user.value = null
      tenantId.value = null
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      localStorage.removeItem('tenant_id')
      delete api.defaults.headers.common['Authorization']
      await router.push('/login')
    }
  }

  // Verificar autenticação
  const checkAuth = async () => {
    if (!token.value) return false

    try {
      const response = await api.get('/auth/me')
      user.value = response.data.user
      tenantId.value = response.data.user?.tenant_id

      if (!isActive.value) {
        await logout()
        return false
      }

      return true
    } catch (err) {
      await logout()
      return false
    }
  }

  // Redirecionar baseado no role
  const redirectBasedOnRole = async () => {
    if (!user.value) return

    switch (user.value.role) {
      case 'super_admin':
        await router.push('/super-admin')
        break
      case 'admin':
        await router.push('/')
        break
      default:
        await router.push('/')
    }
  }

  // Verificar permissão para rota
  const hasPermission = (requiredRoles) => {
    if (!Array.isArray(requiredRoles)) {
      requiredRoles = [requiredRoles]
    }
    return requiredRoles.includes(user.value?.role)
  }

  // Pode acessar tenant específico
  const canAccessTenant = (targetTenantId) => {
    if (isSuperAdmin.value) return true
    return tenantId.value === targetTenantId
  }

  return {
    user,
    token,
    loading,
    error,
    tenantId,
    isAuthenticated,
    isSuperAdmin,
    isAdmin,
    isUser,
    isClient,
    isActive,
    login,
    logout,
    checkAuth,
    hasPermission,
    canAccessTenant,
    generateBearerToken
  }
}
