<script>
  import { filters } from '../stores/properties';
  
  let search = '';
  let tipo = '';
  let finalidade = '';
  
  function handleFilter() {
    filters.set({ search, tipo, finalidade });
  }
  
  // Auto-filter ao digitar
  $: {
    filters.set({ search, tipo, finalidade });
  }
</script>

<div class="bg-slate-800/50 backdrop-blur-sm border border-slate-700/50 rounded-2xl p-6 mb-8 
  shadow-xl shadow-slate-900/20">
  <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
    <input 
      type="text" 
      bind:value={search}
      placeholder="Buscar por localização..."
      class="px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg 
        text-white placeholder-slate-400 focus:outline-none focus:border-blue-500 
        focus:ring-2 focus:ring-blue-500/20 transition-all"
    />
    
    <select 
      bind:value={tipo}
      class="px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg 
        text-white focus:outline-none focus:border-blue-500 
        focus:ring-2 focus:ring-blue-500/20 transition-all">
      <option value="">Todos os tipos</option>
      <option value="casa">Casa</option>
      <option value="apartamento">Apartamento</option>
      <option value="terreno">Terreno</option>
      <option value="comercial">Comercial</option>
    </select>
    
    <select 
      bind:value={finalidade}
      class="px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg 
        text-white focus:outline-none focus:border-blue-500 
        focus:ring-2 focus:ring-blue-500/20 transition-all">
      <option value="">Todas finalidades</option>
      <option value="venda">Venda</option>
      <option value="aluguel">Aluguel</option>
    </select>
    
    <button 
      on:click={handleFilter}
      class="px-6 py-3 bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg 
        text-white font-bold hover:from-blue-600 hover:to-purple-700 
        transition-all shadow-lg shadow-blue-500/20 flex items-center justify-center gap-2">
      <span>🔍</span>
      <span>Buscar</span>
    </button>
  </div>
</div>
