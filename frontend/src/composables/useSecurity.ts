// Composable para validação de segurança e RBAC
import { useAuth } from './useAuth'
import { useTenantIsolation } from './useTenantIsolation'

export const ROLE_LEVELS = {
  super_admin: 4,
  admin: 3,
  user: 2,
  client: 1
}

export const ROLE_PERMISSIONS = {
  super_admin: [
    'manage_tenants',
    'manage_all_users',
    'manage_subscriptions',
    'view_all_data',
    'manage_settings',
    'view_analytics'
  ],
  admin: [
    'manage_tenant_users',
    'manage_properties',
    'manage_leads',
    'view_tenant_data',
    'import_properties',
    'manage_themes'
  ],
  user: [
    'manage_own_leads',
    'view_properties',
    'manage_own_data',
    'view_conversas'
  ],
  client: [
    'view_properties',
    'view_own_data',
    'contact_admin'
  ]
}

export function useSecurity() {
  const auth = useAuth()
  const tenant = useTenantIsolation()

  // Verificar se usuário tem permissão específica
  const hasPermission = (permission, role = null) => {
    const userRole = role || auth.user?.role
    if (!userRole) return false

    const permissions = ROLE_PERMISSIONS[userRole] || []
    return permissions.includes(permission)
  }

  // Verificar se usuário tem nível mínimo (hierarquia)
  const hasMinimumLevel = (requiredLevel) => {
    const userLevel = ROLE_LEVELS[auth.user?.role] || 0
    return userLevel >= requiredLevel
  }

  // Pode editar usuário?
  const canEditUser = (targetUser) => {
    if (auth.isSuperAdmin) return true
    if (!auth.isAdmin) return false
    
    // Admin só pode editar usuários do seu tenant
    return tenant.canAccessTenant(targetUser.tenant_id)
  }

  // Pode deletar usuário?
  const canDeleteUser = (targetUser) => {
    if (auth.isSuperAdmin) return true
    if (!auth.isAdmin) return false
    
    // Admin não pode deletar super_admin ou outro admin
    if (targetUser.role === 'super_admin' || targetUser.role === 'admin') return false
    
    return tenant.canAccessTenant(targetUser.tenant_id)
  }

  // Pode editar propriedade?
  const canEditProperty = (property) => {
    if (auth.isSuperAdmin) return true
    if (!auth.isAdmin && !auth.isUser) return false
    
    return tenant.canAccessTenant(property.tenant_id)
  }

  // Pode importar propriedades?
  const canImportProperties = () => {
    return hasPermission('import_properties')
  }

  // Pode gerenciar tenants?
  const canManageTenants = () => {
    return hasPermission('manage_tenants')
  }

  // Pode visualizar relatórios?
  const canViewAnalytics = () => {
    return hasPermission('view_analytics')
  }

  // Validar acesso a recurso
  const validateResourceAccess = (resource, requiredPermission) => {
    if (!auth.isAuthenticated || !auth.isActive) {
      return { allowed: false, reason: 'Usuário não autenticado' }
    }

    if (!hasPermission(requiredPermission)) {
      return { allowed: false, reason: 'Sem permissão para este recurso' }
    }

    if (resource.tenant_id && !auth.isSuperAdmin) {
      if (!tenant.canAccessTenant(resource.tenant_id)) {
        return { allowed: false, reason: 'Acesso negado ao tenant' }
      }
    }

    return { allowed: true }
  }

  return {
    hasPermission,
    hasMinimumLevel,
    canEditUser,
    canDeleteUser,
    canEditProperty,
    canImportProperties,
    canManageTenants,
    canViewAnalytics,
    validateResourceAccess,
    ROLE_LEVELS,
    ROLE_PERMISSIONS
  }
}
