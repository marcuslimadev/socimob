export interface SidebarVisibilityItemOption {
  key: string;
  label: string;
  href: string;
  roles?: string[];
}

export interface SidebarVisibilitySectionOption {
  id: string;
  label: string;
  items: SidebarVisibilityItemOption[];
  roles?: string[];
}

const hasRoleAccess = (roles: string[] | undefined, role?: string) => {
  if (!roles || roles.length === 0) {
    return true;
  }

  if (!role) {
    return false;
  }

  return roles.includes(role);
};

export const SIDEBAR_VISIBILITY_SECTIONS: SidebarVisibilitySectionOption[] = [
  {
    id: 'principal',
    label: 'Principal',
    items: [
      { key: 'dashboard', label: 'Dashboard', href: '/dashboard' },
      { key: 'agenda', label: 'Agenda', href: '/agenda' },
    ],
  },
  {
    id: 'crm',
    label: 'Chat & Clientes',
    items: [
      { key: 'crm', label: 'Chat', href: '/crm' },
      { key: 'pessoas', label: 'Pessoas', href: '/pessoas' },
      { key: 'links-importantes', label: 'Links importantes', href: '/links-importantes' },
      { key: 'ads', label: 'Marketing / Anúncios', href: '/ads' },
      { key: 'contratos-locacao', label: 'Contratos · Locação', href: '/financeiro/locacao' },
      { key: 'contratos-venda', label: 'Contratos · Venda', href: '/financeiro/compra-venda' },
    ],
  },
  {
    id: 'imoveis',
    label: 'Imóveis',
    items: [
      { key: 'properties', label: 'Imóveis', href: '/properties' },
      { key: 'properties-propaganda', label: 'Propaganda', href: '/properties/propaganda' },
      { key: 'controle-chaves', label: 'Controle de Chaves', href: '/controle-chaves' },
      { key: 'imobi-brasil', label: 'ImobiBrasil', href: '/imobi-brasil' },
    ],
  },
  {
    id: 'operacional',
    label: 'Operacional',
    items: [
      { key: 'vistorias', label: 'Vistorias', href: '/vistorias' },
      { key: 'assinaturas', label: 'Assinaturas', href: '/assinaturas' },
      { key: 'financeiro-locacao', label: 'Locação / Operação', href: '/financeiro/locacao' },
      { key: 'financeiro-compra-venda', label: 'Compra e Venda', href: '/financeiro/compra-venda' },
      { key: 'contrato-templates', label: 'Templates de Contrato', href: '/contrato-templates' },
    ],
  },
  {
    id: 'financeiro',
    label: 'Financeiro',
    items: [
      { key: 'financeiro', label: 'Financeiro', href: '/financeiro' },
      { key: 'financeiro-contas', label: 'Contas a Pagar/Receber', href: '/financeiro/contas' },
    ],
  },
  {
    id: 'admin',
    label: 'Administração',
    roles: ['admin', 'super_admin'],
    items: [
      { key: 'analytics', label: 'Estatísticas', href: '/analytics', roles: ['admin', 'super_admin'] },
      { key: 'admin-users', label: 'Usuários', href: '/admin/users', roles: ['admin', 'super_admin'] },
      { key: 'system-logs', label: 'Logs do Sistema', href: '/system-logs', roles: ['admin', 'super_admin'] },
    ],
  },
  {
    id: 'superadmin',
    label: 'Super Admin',
    roles: ['super_admin'],
    items: [
      { key: 'tenants', label: 'Tenants', href: '/tenants', roles: ['super_admin'] },
      { key: 'tenants-associacoes', label: 'Assoc. Tenants', href: '/tenants/associacoes', roles: ['super_admin'] },
    ],
  },
];

export const getSidebarVisibilitySections = (role?: string) =>
  SIDEBAR_VISIBILITY_SECTIONS.filter((section) => hasRoleAccess(section.roles, role))
    .map((section) => ({
      ...section,
      items: section.items.filter((item) => hasRoleAccess(item.roles, role)),
    }))
    .filter((section) => section.items.length > 0);

const allSidebarVisibilityKeys = new Set(
  SIDEBAR_VISIBILITY_SECTIONS.flatMap((section) => section.items.map((item) => item.key)),
);

export const normalizeHiddenSidebarKeys = (value: unknown) => {
  if (!Array.isArray(value)) {
    return [] as string[];
  }

  return Array.from(
    new Set(
      value
        .filter((item): item is string => typeof item === 'string')
        .map((item) => item.trim())
        .filter((item) => item !== '' && allSidebarVisibilityKeys.has(item)),
    ),
  );
};
