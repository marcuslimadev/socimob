<script>
  import { onMount } from 'svelte';
  import Navbar from './components/Navbar.svelte';
  import PropertyGrid from './components/PropertyGrid.svelte';
  import PropertyFilters from './components/PropertyFilters.svelte';
  import PropertyModal from './components/PropertyModal.svelte';
  import { auth } from './stores/auth';
  import { properties, loadProperties, filters } from './stores/properties';

  let selectedProperty = null;
  let showModal = false;

  onMount(() => {
    // Verificar autenticação
    const token = localStorage.getItem('token');
    const userStr = localStorage.getItem('user');
    
    if (!token || !userStr) {
      window.location.href = '/';
      return;
    }
    
    const user = JSON.parse(userStr);
    auth.login(user, token);
    
    // Carregar imóveis
    loadProperties();
    
    // Atualizar a cada 30 segundos
    const interval = setInterval(loadProperties, 30000);
    return () => clearInterval(interval);
  });

  function handlePropertyClick(event) {
    selectedProperty = event.detail;
    showModal = true;
  }

  function handleCloseModal() {
    showModal = false;
    selectedProperty = null;
  }
</script>

<div class="min-h-screen">
  <Navbar />
  
  <main class="container mx-auto px-4 py-8">
    <!-- Header -->
    <div class="mb-8">
      <div class="inline-block px-4 py-1 bg-gradient-to-r from-blue-500/20 to-purple-500/20 
        border border-blue-500/30 rounded-full text-sm font-bold text-blue-300 mb-3">
        Catálogo exclusivo
      </div>
      <h2 class="text-4xl font-black text-white mb-2">Imóveis Disponíveis</h2>
      <p class="text-slate-300">Encontre o imóvel perfeito para você</p>
    </div>

    <!-- Filtros -->
    <PropertyFilters />

    <!-- Grid de Imóveis -->
    <PropertyGrid 
      properties={$properties} 
      loading={false}
      on:propertyClick={handlePropertyClick}
    />
  </main>

  <!-- Modal -->
  {#if showModal && selectedProperty}
    <PropertyModal 
      property={selectedProperty} 
      on:close={handleCloseModal}
    />
  {/if}
</div>

<style>
  .container {
    max-width: 1280px;
  }
</style>
