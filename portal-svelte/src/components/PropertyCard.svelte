<script>
  import { createEventDispatcher } from 'svelte';
  
  export let property;
  
  const dispatch = createEventDispatcher();
  
  function formatPrice(price) {
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL',
      minimumFractionDigits: 0
    }).format(price);
  }
  
  function getPropertyTypeLabel(type) {
    const types = {
      casa: 'Casa',
      apartamento: 'Apartamento',
      terreno: 'Terreno',
      comercial: 'Comercial'
    };
    return types[type] || type;
  }
  
  function getListingTypeBadge(type) {
    if (type === 'venda') {
      return { label: 'Venda', class: 'bg-green-500/20 text-green-300 border-green-500/30' };
    }
    return { label: 'Aluguel', class: 'bg-blue-500/20 text-blue-300 border-blue-500/30' };
  }
  
  $: badge = getListingTypeBadge(property.listing_type);
  $: mainPhoto = property.photos?.[0] || '/images/placeholder-property.jpg';
</script>

<div 
  on:click={() => dispatch('click')}
  on:keydown={(e) => e.key === 'Enter' && dispatch('click')}
  role="button"
  tabindex="0"
  class="property-card group cursor-pointer bg-slate-800/50 backdrop-blur-sm 
    border border-slate-700/50 rounded-2xl overflow-hidden 
    hover:border-blue-500/50 hover:shadow-xl hover:shadow-blue-500/10 
    hover:-translate-y-1 transition-all duration-300">
  
  <!-- Image -->
  <div class="relative h-48 overflow-hidden">
    <img 
      src={mainPhoto} 
      alt={property.title}
      class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
    />
    
    <!-- Badge -->
    <div class="absolute top-3 right-3">
      <span class="px-3 py-1 {badge.class} border rounded-full text-xs font-bold backdrop-blur-sm">
        {badge.label}
      </span>
    </div>
    
    <!-- Code -->
    {#if property.code}
      <div class="absolute top-3 left-3">
        <span class="px-3 py-1 bg-slate-900/80 border border-slate-600 rounded-full 
          text-xs font-bold text-white backdrop-blur-sm">
          #{property.code}
        </span>
      </div>
    {/if}
  </div>
  
  <!-- Content -->
  <div class="p-5">
    <h3 class="text-xl font-bold text-white mb-2 line-clamp-2 group-hover:text-blue-400 transition-colors">
      {property.title}
    </h3>
    
    <p class="text-slate-400 text-sm mb-3 line-clamp-2">
      {property.description || 'Descrição não disponível'}
    </p>
    
    <!-- Location -->
    <div class="flex items-center gap-2 text-sm text-slate-400 mb-4">
      <span>📍</span>
      <span class="truncate">{property.neighborhood}, {property.city} - {property.state}</span>
    </div>
    
    <!-- Features -->
    <div class="flex items-center gap-4 text-sm text-slate-300 mb-4">
      {#if property.bedrooms}
        <div class="flex items-center gap-1">
          <span>🛏️</span>
          <span>{property.bedrooms}</span>
        </div>
      {/if}
      
      {#if property.bathrooms}
        <div class="flex items-center gap-1">
          <span>🚿</span>
          <span>{property.bathrooms}</span>
        </div>
      {/if}
      
      {#if property.area}
        <div class="flex items-center gap-1">
          <span>📐</span>
          <span>{property.area}m²</span>
        </div>
      {/if}
    </div>
    
    <!-- Price -->
    <div class="pt-4 border-t border-slate-700">
      <div class="flex items-end justify-between">
        <div>
          <div class="text-xs text-slate-400 mb-1">
            {getPropertyTypeLabel(property.property_type)}
          </div>
          <div class="text-2xl font-black text-white">
            {formatPrice(property.price)}
          </div>
        </div>
        
        <button class="px-4 py-2 bg-blue-500/20 border border-blue-500/30 rounded-lg 
          text-blue-300 font-bold text-sm hover:bg-blue-500/30 transition-all">
          Ver detalhes
        </button>
      </div>
    </div>
  </div>
</div>

<style>
  .line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }
</style>
