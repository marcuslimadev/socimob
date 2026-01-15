import { writable, derived } from 'svelte/store';
import axios from 'axios';

export const properties = writable([]);
export const loading = writable(false);
export const filters = writable({
  search: '',
  tipo: '',
  finalidade: ''
});

// Computed: filtered properties
export const filteredProperties = derived(
  [properties, filters],
  ([$properties, $filters]) => {
    return $properties.filter(property => {
      const matchSearch = !$filters.search || 
        property.title?.toLowerCase().includes($filters.search.toLowerCase()) ||
        property.address?.toLowerCase().includes($filters.search.toLowerCase()) ||
        property.neighborhood?.toLowerCase().includes($filters.search.toLowerCase());
      
      const matchTipo = !$filters.tipo || property.property_type === $filters.tipo;
      const matchFinalidade = !$filters.finalidade || property.listing_type === $filters.finalidade;
      
      return matchSearch && matchTipo && matchFinalidade;
    });
  }
);

export async function loadProperties() {
  loading.set(true);
  try {
    const token = localStorage.getItem('token');
    const response = await axios.get('/api/portal/imoveis', {
      headers: {
        Authorization: `Bearer ${token}`,
        'Cache-Control': 'no-cache',
        Pragma: 'no-cache'
      },
      params: { _ts: Date.now() }
    });
    
    if (response.data.success) {
      properties.set(response.data.data || []);
    }
  } catch (error) {
    console.error('Erro ao carregar imóveis:', error);
    properties.set([]);
  } finally {
    loading.set(false);
  }
}
