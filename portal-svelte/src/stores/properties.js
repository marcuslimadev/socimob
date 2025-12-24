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
      headers: { Authorization: `Bearer ${token}` }
    });
    
    if (response.data.success) {
      properties.set(response.data.data || []);
    }
  } catch (error) {
    console.error('Erro ao carregar imóveis:', error);
    // Dados de exemplo para desenvolvimento
    properties.set([
      {
        id: 1,
        code: 'AP001',
        title: 'Apartamento Moderno no Centro',
        description: 'Lindo apartamento com 3 quartos, 2 banheiros e varanda gourmet.',
        property_type: 'apartamento',
        listing_type: 'venda',
        price: 450000,
        bedrooms: 3,
        bathrooms: 2,
        area: 85,
        address: 'Rua das Flores, 123',
        neighborhood: 'Centro',
        city: 'São Paulo',
        state: 'SP',
        photos: ['/images/placeholder-property.jpg']
      },
      {
        id: 2,
        code: 'CS002',
        title: 'Casa Confortável com Quintal',
        description: 'Casa ampla com 4 quartos, piscina e churrasqueira.',
        property_type: 'casa',
        listing_type: 'venda',
        price: 680000,
        bedrooms: 4,
        bathrooms: 3,
        area: 200,
        address: 'Av. Paulista, 456',
        neighborhood: 'Jardins',
        city: 'São Paulo',
        state: 'SP',
        photos: ['/images/placeholder-property.jpg']
      }
    ]);
  } finally {
    loading.set(false);
  }
}
