<script>
  import { createEventDispatcher } from 'svelte';
  import { filteredProperties } from '../stores/properties';
  import PropertyCard from './PropertyCard.svelte';
  
  export let loading = false;
  
  const dispatch = createEventDispatcher();
  
  function handlePropertyClick(property) {
    dispatch('propertyClick', property);
  }
</script>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
  {#if loading}
    <!-- Loading State -->
    {#each [1, 2, 3] as i}
      <div class="animate-pulse">
        <div class="bg-slate-800/50 rounded-2xl h-96"></div>
      </div>
    {/each}
  {:else if $filteredProperties.length === 0}
    <!-- Empty State -->
    <div class="col-span-full text-center py-20">
      <div class="text-6xl mb-4">🏠</div>
      <h3 class="text-2xl font-bold text-white mb-2">Nenhum imóvel encontrado</h3>
      <p class="text-slate-400">Tente ajustar os filtros de busca</p>
    </div>
  {:else}
    <!-- Property Cards -->
    {#each $filteredProperties as property (property.id)}
      <PropertyCard 
        {property} 
        on:click={() => handlePropertyClick(property)}
      />
    {/each}
  {/if}
</div>
