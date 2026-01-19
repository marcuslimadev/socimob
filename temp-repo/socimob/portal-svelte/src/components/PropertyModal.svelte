<script>
  import { createEventDispatcher } from 'svelte';
  import { fly, fade } from 'svelte/transition';
  
  export let property;
  
  const dispatch = createEventDispatcher();
  
  let currentPhotoIndex = 0;
  
  function formatPrice(price) {
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL',
      minimumFractionDigits: 0
    }).format(price);
  }
  
  function close() {
    dispatch('close');
  }
  
  function nextPhoto() {
    currentPhotoIndex = (currentPhotoIndex + 1) % (property.photos?.length || 1);
  }
  
  function prevPhoto() {
    currentPhotoIndex = currentPhotoIndex === 0 
      ? (property.photos?.length || 1) - 1 
      : currentPhotoIndex - 1;
  }
  
  function handleInterest() {
    alert(`Interesse registrado no imóvel ${property.code}!`);
    close();
  }
  
  $: photos = property.photos || ['/images/placeholder-property.jpg'];
</script>

<svelte:window on:keydown={(e) => e.key === 'Escape' && close()} />

<!-- Overlay -->
<div 
  on:click={close}
  on:keydown={(e) => e.key === 'Enter' && close()}
  role="button"
  tabindex="0"
  transition:fade={{ duration: 200 }}
  class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 flex items-center justify-center p-4">
  
  <!-- Modal -->
  <div 
    on:click|stopPropagation
    on:keydown|stopPropagation
    role="dialog"
    tabindex="-1"
    transition:fly={{ y: 50, duration: 300 }}
    class="bg-slate-900 rounded-2xl shadow-2xl max-w-4xl w-full max-h-[90vh] overflow-y-auto 
      border border-slate-700">
    
    <!-- Header -->
    <div class="sticky top-0 bg-slate-900/95 backdrop-blur-lg border-b border-slate-700 p-6 z-10">
      <div class="flex items-start justify-between">
        <div>
          <div class="text-sm text-slate-400 mb-1">#{property.code}</div>
          <h2 class="text-3xl font-black text-white">{property.title}</h2>
          <div class="flex items-center gap-2 text-slate-400 mt-2">
            <span>📍</span>
            <span>{property.address}, {property.neighborhood}</span>
          </div>
        </div>
        
        <button 
          on:click={close}
          class="p-2 hover:bg-slate-800 rounded-lg transition-colors">
          <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    </div>
    
    <!-- Photos Carousel -->
    <div class="relative h-96 bg-slate-800">
      <img 
        src={photos[currentPhotoIndex]} 
        alt={property.title}
        class="w-full h-full object-cover"
      />
      
      {#if photos.length > 1}
        <button 
          on:click={prevPhoto}
          class="absolute left-4 top-1/2 -translate-y-1/2 p-3 bg-slate-900/80 hover:bg-slate-900 
            rounded-full text-white backdrop-blur-sm transition-all">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </button>
        
        <button 
          on:click={nextPhoto}
          class="absolute right-4 top-1/2 -translate-y-1/2 p-3 bg-slate-900/80 hover:bg-slate-900 
            rounded-full text-white backdrop-blur-sm transition-all">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
          </svg>
        </button>
        
        <div class="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-2">
          {#each photos as _, i}
            <button 
              on:click={() => currentPhotoIndex = i}
              class="w-2 h-2 rounded-full transition-all {i === currentPhotoIndex ? 'bg-white w-6' : 'bg-white/50'}">
            </button>
          {/each}
        </div>
      {/if}
    </div>
    
    <!-- Content -->
    <div class="p-6">
      <!-- Price & Features -->
      <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div class="bg-slate-800/50 rounded-xl p-6 border border-slate-700">
          <div class="text-sm text-slate-400 mb-2">Valor</div>
          <div class="text-4xl font-black text-white">{formatPrice(property.price)}</div>
          <div class="mt-2 inline-flex px-3 py-1 bg-green-500/20 border border-green-500/30 
            rounded-full text-sm font-bold text-green-300">
            {property.listing_type === 'venda' ? 'Venda' : 'Aluguel'}
          </div>
        </div>
        
        <div class="bg-slate-800/50 rounded-xl p-6 border border-slate-700">
          <div class="text-sm text-slate-400 mb-4">Características</div>
          <div class="grid grid-cols-3 gap-4">
            {#if property.bedrooms}
              <div class="text-center">
                <div class="text-2xl mb-1">🛏️</div>
                <div class="text-lg font-bold text-white">{property.bedrooms}</div>
                <div class="text-xs text-slate-400">Quartos</div>
              </div>
            {/if}
            
            {#if property.bathrooms}
              <div class="text-center">
                <div class="text-2xl mb-1">🚿</div>
                <div class="text-lg font-bold text-white">{property.bathrooms}</div>
                <div class="text-xs text-slate-400">Banheiros</div>
              </div>
            {/if}
            
            {#if property.area}
              <div class="text-center">
                <div class="text-2xl mb-1">📐</div>
                <div class="text-lg font-bold text-white">{property.area}</div>
                <div class="text-xs text-slate-400">m²</div>
              </div>
            {/if}
          </div>
        </div>
      </div>
      
      <!-- Description -->
      <div class="mb-6">
        <h3 class="text-xl font-bold text-white mb-3">Descrição</h3>
        <p class="text-slate-300 leading-relaxed">
          {property.description || 'Descrição não disponível'}
        </p>
      </div>
      
      <!-- Location -->
      <div class="mb-6">
        <h3 class="text-xl font-bold text-white mb-3">Localização</h3>
        <div class="bg-slate-800/50 rounded-xl p-4 border border-slate-700">
          <div class="space-y-2 text-slate-300">
            <div><strong>Endereço:</strong> {property.address}</div>
            <div><strong>Bairro:</strong> {property.neighborhood}</div>
            <div><strong>Cidade:</strong> {property.city} - {property.state}</div>
          </div>
        </div>
      </div>
      
      <!-- Actions -->
      <div class="flex gap-4">
        <button 
          on:click={handleInterest}
          class="flex-1 px-6 py-4 bg-gradient-to-r from-blue-500 to-purple-600 rounded-xl 
            text-white font-bold text-lg hover:from-blue-600 hover:to-purple-700 
            transition-all shadow-lg shadow-blue-500/20">
          💬 Tenho Interesse
        </button>
        
        <button 
          on:click={close}
          class="px-6 py-4 bg-slate-800 border border-slate-700 rounded-xl 
            text-slate-300 font-bold hover:bg-slate-700 transition-all">
          Fechar
        </button>
      </div>
    </div>
  </div>
</div>
