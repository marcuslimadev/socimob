const API_BASE_URL = 'https://exclusivalarimoveis.com/api';

export async function apiCall(endpoint: string, options: RequestInit = {}) {
  const token = localStorage.getItem('socimob_auth_token');
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
  };

  if (options.headers && typeof options.headers === 'object') {
    Object.assign(headers, options.headers);
  }

  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
  }

  const response = await fetch(`${API_BASE_URL}${endpoint}`, {
    ...options,
    headers,
  });

  if (response.status === 401) {
    localStorage.removeItem('socimob_auth_token');
    localStorage.removeItem('socimob_auth_user');
    window.location.href = '/login';
  }

  if (!response.ok) {
    throw new Error(`API Error: ${response.statusText}`);
  }

  return response.json();
}

export const dashboardAPI = {
  getStats: () => apiCall('/dashboard/stats'),
  getActivities: () => apiCall('/dashboard/atividades'),
};

export const leadsAPI = {
  list: (filters?: any) => {
    const params = new URLSearchParams(filters || {});
    return apiCall(`/leads?${params}`);
  },
  get: (id: string) => apiCall(`/leads/${id}`),
  create: (data: any) => apiCall('/leads', { method: 'POST', body: JSON.stringify(data) }),
  update: (id: string, data: any) => apiCall(`/leads/${id}`, { method: 'PUT', body: JSON.stringify(data) }),
  delete: (id: string) => apiCall(`/leads/${id}`, { method: 'DELETE' }),
};

export const propertiesAPI = {
  list: (filters?: any) => {
    const params = new URLSearchParams(filters || {});
    return apiCall(`/properties?${params}`);
  },
  get: (id: string) => apiCall(`/properties/${id}`),
};

export function handleApiError(error: any) {
  if (error instanceof Error) {
    return { message: error.message, status: -1 };
  }
  return { message: 'Erro desconhecido', status: -1 };
}
