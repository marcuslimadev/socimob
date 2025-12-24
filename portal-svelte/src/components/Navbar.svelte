<script>
  import { auth } from '../stores/auth';
</script>

<nav class="sticky top-0 z-50 backdrop-blur-lg bg-slate-900/80 border-b border-slate-700/50">
  <div class="container mx-auto px-4 py-4">
    <div class="flex justify-between items-center">
      <div class="flex items-center gap-3">
        <div class="px-4 py-2 bg-gradient-to-r from-blue-500 to-purple-600 rounded-lg 
          font-black text-white text-lg shadow-lg shadow-blue-500/20">
          🏠 SOCIMOB
        </div>
        <span class="hidden md:inline-flex px-3 py-1 bg-blue-500/20 border border-blue-500/30 
          rounded-full text-sm font-bold text-blue-300">
          Portal do Cliente
        </span>
      </div>
      
      <div class="flex items-center gap-4">
        {#if $auth.user}
          <span class="text-white font-semibold hidden sm:block">{$auth.user.name}</span>
        {/if}
        <button 
          on:click={() => auth.logout()}
          class="px-4 py-2 bg-red-500/20 border border-red-500/30 rounded-lg 
            text-red-300 font-bold hover:bg-red-500/30 transition-all">
          Sair
        </button>
      </div>
    </div>
  </div>
</nav>

<style>
  .container {
    max-width: 1280px;
  }
</style>
