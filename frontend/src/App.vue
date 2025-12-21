<template>
  <div id="app" class="app-container bauhaus-ambient">
    <router-view />
  </div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue'
import { useAuth } from '@/composables/useAuth'
import { useRouter } from 'vue-router'

const auth = useAuth()
const router = useRouter()

// Verificar autenticação ao carregar app
onMounted(async () => {
  const token = localStorage.getItem('token')

  if (token && router.currentRoute.value.path !== '/login') {
    const isValid = await auth.checkAuth()
    if (!isValid) {
      await router.push('/login')
    }
  }
})
</script>

<style scoped>
#app {
  min-height: 100vh;
}
</style>
