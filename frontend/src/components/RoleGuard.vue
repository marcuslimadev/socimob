<template>
  <div v-if="hasAccess">
    <slot></slot>
  </div>
  <div v-else class="access-denied">
    <div class="alert">
      <div class="icon">🚫</div>
      <h2>Acesso Negado</h2>
      <p>Você não tem permissão para acessar este recurso.</p>
      <p v-if="requiredRoles" class="roles-info">
        <strong>Roles necessários:</strong> {{ requiredRoles.join(', ') }}
      </p>
      <router-link to="/" class="btn-back">Voltar ao Dashboard</router-link>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useAuth } from '@/composables/useAuth'
import { useSecurity } from '@/composables/useSecurity'

interface Props {
  roles?: string | string[]
  permission?: string
  requireTenant?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  requireTenant: false
})

const auth = useAuth()
const security = useSecurity()

const requiredRoles = computed(() => {
  if (!props.roles) return null
  return Array.isArray(props.roles) ? props.roles : [props.roles]
})

const hasAccess = computed(() => {
  if (!auth.isAuthenticated || !auth.isActive) {
    return false
  }

  if (props.roles) {
    const roleArray = Array.isArray(props.roles) ? props.roles : [props.roles]
    return auth.hasPermission(roleArray)
  }

  if (props.permission) {
    return security.hasPermission(props.permission)
  }

  if (props.requireTenant && !auth.tenantId) {
    return false
  }

  return true
})
</script>

<style scoped>
.access-denied {
  display: flex;
  align-items: center;
  justify-content: center;
  min-height: 50vh;
  padding: 2rem;
  background: #f5f5f5;
}

.alert {
  background: white;
  border: 2px solid #f44336;
  border-radius: 8px;
  padding: 2rem;
  text-align: center;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  max-width: 400px;
}

.icon {
  font-size: 3rem;
  margin-bottom: 1rem;
}

.alert h2 {
  color: #d32f2f;
  margin-bottom: 0.5rem;
}

.alert p {
  color: #666;
  margin: 0.5rem 0;
}

.roles-info {
  background: #ffebee;
  padding: 1rem;
  border-radius: 4px;
  font-size: 0.9rem;
  color: #c62828;
  margin: 1rem 0 !important;
}

.btn-back {
  display: inline-block;
  margin-top: 1rem;
  padding: 0.75rem 1.5rem;
  background: #f44336;
  color: white;
  text-decoration: none;
  border-radius: 4px;
  transition: background 0.3s;
}

.btn-back:hover {
  background: #d32f2f;
}
</style>
