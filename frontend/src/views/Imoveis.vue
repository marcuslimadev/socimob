<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Hero Section -->
    <section class="gradient-bg text-white py-20 relative overflow-hidden">
      <div class="absolute inset-0 opacity-10">
        <div class="absolute top-0 left-0 w-96 h-96 bg-white rounded-full filter blur-3xl"></div>
        <div class="absolute bottom-0 right-0 w-96 h-96 bg-white rounded-full filter blur-3xl"></div>
      </div>
      
      <div class="container mx-auto px-4 relative z-10">
        <div class="text-center animate__animated animate__fadeInDown">
          <h1 class="text-5xl md:text-7xl font-bold mb-6">
            🏡 Exclusiva Lar Imóveis
          </h1>
          <p class="text-xl md:text-2xl mb-8 opacity-90">
            Encontre o imóvel dos seus sonhos em Belo Horizonte
          </p>
          <div class="flex flex-wrap justify-center gap-4 mb-8">
            <div class="bg-white/20 backdrop-blur-lg rounded-full px-6 py-3">
              <i class="fas fa-home mr-2"></i>
              <span class="font-semibold">{{ totalImoveis }}</span> Imóveis Disponíveis
            </div>
            <div class="bg-white/20 backdrop-blur-lg rounded-full px-6 py-3">
              <i class="fas fa-map-marker-alt mr-2"></i>
              Belo Horizonte e Região
            </div>
            <div class="bg-white/20 backdrop-blur-lg rounded-full px-6 py-3">
              <i class="fas fa-star mr-2"></i>
              Atendimento 24/7
            </div>
          </div>
        </div>
        
        <!-- Filters -->
        <div class="max-w-5xl mx-auto mt-12 animate__animated animate__fadeInUp">
          <div class="bg-white rounded-2xl shadow-2xl p-6">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-gray-800 text-lg font-semibold">
                <i class="fas fa-filter mr-2"></i>Filtrar Imóveis
              </h3>
              
              <!-- View Toggle -->
              <div class="flex gap-2 bg-gray-100 rounded-xl p-1">
                <button 
                  @click="modoVisualizacao = 'grid'"
                  :class="[
                    'px-4 py-2 rounded-lg font-semibold text-sm transition-all',
                    modoVisualizacao === 'grid' 
                      ? 'bg-white text-purple-600 shadow-md' 
                      : 'text-gray-600 hover:text-purple-600'
                  ]"
                >
                  <i class="fas fa-th-large mr-2"></i>Grade
                </button>
                <button 
                  @click="modoVisualizacao = 'split'"
                  :class="[
                    'px-4 py-2 rounded-lg font-semibold text-sm transition-all',
                    modoVisualizacao === 'split' 
                      ? 'bg-white text-purple-600 shadow-md' 
                      : 'text-gray-600 hover:text-purple-600'
                  ]"
                  title="Ver lista e mapa em tela cheia"
                >
                  <i class="fas fa-map-marked-alt mr-2"></i>Ver Mapa
                </button>
              </div>
            </div>
            
            <div class="grid md:grid-cols-4 gap-4 mb-4">
              <input 
                v-model="filters.search" 
                type="text" 
                placeholder="🔍 Buscar por bairro, cidade..."
                class="px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-purple-500 focus:outline-none text-gray-700 transition"
              >
              
              <select v-model="filters.tipo" class="px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-purple-500 focus:outline-none text-gray-700 transition">
                <option value="">Todos os Tipos</option>
                <option value="apartamento">Apartamento</option>
                <option value="casa">Casa</option>
                <option value="cobertura">Cobertura</option>
                <option value="lote">Lote/Terreno</option>
              </select>
              
              <select v-model="filters.quartos" class="px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-purple-500 focus:outline-none text-gray-700 transition">
                <option value="">Quartos</option>
                <option value="1">1+ Quarto</option>
                <option value="2">2+ Quartos</option>
                <option value="3">3+ Quartos</option>
                <option value="4">4+ Quartos</option>
              </select>
              
              <select v-model="filters.faixaPreco" class="px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-purple-500 focus:outline-none text-gray-700 transition">
                <option value="">Faixa de Preço</option>
                <option value="0-300000">Até R$ 300 mil</option>
                <option value="300000-500000">R$ 300-500 mil</option>
                <option value="500000-800000">R$ 500-800 mil</option>
                <option value="800000-999999999">Acima de R$ 800 mil</option>
              </select>
            </div>
            
            <div class="grid md:grid-cols-4 gap-4 mb-4">
              <select v-model="filters.ordenarPor" class="px-4 py-3 border-2 border-gray-200 rounded-xl focus:border-purple-500 focus:outline-none text-gray-700 transition">
                <option value="">Ordenar por</option>
                <option value="preco-asc">💰 Menor Preço</option>
                <option value="preco-desc">💰 Maior Preço</option>
                <option value="quartos-desc">🛏️ Mais Quartos</option>
                <option value="area-desc">📐 Maior Área</option>
              </select>
            </div>
            
            <div class="flex flex-wrap gap-2">
              <button 
                @click="limparFiltros"
                class="filter-pill px-4 py-2 bg-gray-200 rounded-full text-sm text-gray-700 hover:bg-gray-300"
              >
                <i class="fas fa-times mr-1"></i>Limpar Filtros
              </button>
              <div class="filter-pill px-4 py-2 bg-purple-100 rounded-full text-sm text-purple-700">
                <i class="fas fa-check-circle mr-1"></i>{{ imoveisFiltrados.length }} resultados
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
    
    <!-- Loading State -->
    <div v-if="loading" class="container mx-auto px-4 py-16">
      <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
        <div v-for="n in 6" :key="n" class="bg-white rounded-2xl overflow-hidden shadow-lg">
          <div class="shimmer h-64"></div>
          <div class="p-6">
            <div class="shimmer h-6 w-3/4 mb-4 rounded"></div>
            <div class="shimmer h-4 w-1/2 mb-2 rounded"></div>
            <div class="shimmer h-4 w-2/3 rounded"></div>
          </div>
        </div>
      </div>
    </div>
    
    <!-- Split View (List + Map) - Zillow Style -->
    <section v-if="!loading && modoVisualizacao === 'split'" class="split-view-fullscreen">
      <div class="split-toolbar">
        <div class="split-toolbar-info">
          <i class="fas fa-map-marked-alt text-purple-600"></i>
          <span>Mapa interativo</span>
          <span class="text-gray-400">•</span>
          <span>{{ imoveisFiltrados.length }} resultados</span>
        </div>
        <button 
          class="split-toolbar-close"
          @click="modoVisualizacao = 'grid'"
        >
          <i class="fas fa-times mr-2"></i>Fechar mapa
        </button>
      </div>
      <div class="split-view-container">
        <!-- Properties List (Left Side) -->
        <div class="split-view-list">
          <div class="split-view-scroll">
            <div v-if="imoveisFiltrados.length === 0" class="text-center py-20 px-4">
              <i class="fas fa-search text-gray-300 text-6xl mb-4"></i>
              <h3 class="text-2xl font-semibold text-gray-700 mb-2">Nenhum imóvel encontrado</h3>
              <p class="text-gray-500">Tente ajustar os filtros ou entre em contato conosco!</p>
              <button @click="abrirWhatsApp()" class="mt-6 whatsapp-button text-white px-8 py-4 rounded-full font-semibold inline-flex items-center gap-2">
                <i class="fab fa-whatsapp text-2xl"></i>
                Falar com um Corretor
              </button>
            </div>
            
            <div v-else class="grid gap-6 p-6">
              <div 
                v-for="imovel in imoveisFiltrados" 
                :key="imovel.codigo_imovel"
                class="card-hover bg-white rounded-2xl overflow-hidden shadow-lg cursor-pointer split-property-card"
                @click="abrirModal(imovel)"
                @mouseenter="highlightMarker(imovel)"
                @mouseleave="unhighlightMarker()"
              >
                <!-- Compact Card for Split View -->
                <div class="flex gap-4">
                  <!-- Image -->
                  <div class="relative w-48 h-40 flex-shrink-0 overflow-hidden bg-gray-200">
                    <img
                      :src="imovel.imagem_destaque || placeholderImagemCard"
                      :alt="imovel.tipo_imovel"
                      class="image-hover w-full h-full object-cover"
                      loading="lazy"
                      decoding="async"
                      @error="event => definirImagemFallback(event, placeholderImagemCard)"
                    >
                    
                    <!-- Price Tag -->
                    <div class="absolute bottom-2 right-2">
                      <div class="price-tag text-white px-3 py-1 rounded-full font-bold text-sm shadow-lg">
                        {{ formatarPreco(imovel.valor_venda) }}
                      </div>
                    </div>
                  </div>
                  
                  <!-- Content -->
                  <div class="flex-1 p-4">
                    <h3 class="text-lg font-bold text-gray-800 mb-1 line-clamp-1">
                      {{ imovel.tipo_imovel }} - {{ imovel.bairro }}
                    </h3>
                    
                    <p class="text-gray-600 text-sm mb-3 flex items-center gap-1">
                      <i class="fas fa-map-marker-alt text-purple-500"></i>
                      {{ imovel.bairro }}, {{ imovel.cidade }} - {{ imovel.estado }}
                    </p>
                    
                    <!-- Features -->
                    <div class="flex flex-wrap gap-3 text-sm text-gray-700">
                      <div v-if="imovel.dormitorios" class="flex items-center gap-1">
                        <i class="fas fa-bed text-purple-500"></i>
                        <span>{{ imovel.dormitorios }} quartos</span>
                      </div>
                      <div v-if="imovel.suites" class="flex items-center gap-1">
                        <i class="fas fa-bath text-purple-500"></i>
                        <span>{{ imovel.suites }} suítes</span>
                      </div>
                      <div v-if="imovel.garagem" class="flex items-center gap-1">
                        <i class="fas fa-car text-purple-500"></i>
                        <span>{{ imovel.garagem }} vagas</span>
                      </div>
                      <div v-if="imovel.area_total" class="flex items-center gap-1">
                        <i class="fas fa-ruler-combined text-purple-500"></i>
                        <span>{{ imovel.area_total }}m²</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        
        <!-- Map (Right Side) -->
        <div class="split-view-map">
          <PropertyMap 
            :imoveis="imoveisFiltrados"
            @property-click="abrirModal"
            ref="splitViewMap"
          />
        </div>
      </div>
    </section>
    
    <!-- Properties Grid -->
    <section v-else-if="!loading && modoVisualizacao === 'grid'" class="container mx-auto px-4 py-16">
      <div v-if="imoveisFiltrados.length === 0" class="text-center py-20">
        <i class="fas fa-search text-gray-300 text-6xl mb-4"></i>
        <h3 class="text-2xl font-semibold text-gray-700 mb-2">Nenhum imóvel encontrado</h3>
        <p class="text-gray-500">Tente ajustar os filtros ou entre em contato conosco!</p>
        <button @click="abrirWhatsApp()" class="mt-6 whatsapp-button text-white px-8 py-4 rounded-full font-semibold inline-flex items-center gap-2">
          <i class="fab fa-whatsapp text-2xl"></i>
          Falar com um Corretor
        </button>
      </div>
      
      <div v-else class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
        <div 
          v-for="imovel in imoveisFiltrados" 
          :key="imovel.codigo_imovel"
          class="card-hover bg-white rounded-2xl overflow-hidden shadow-lg cursor-pointer"
          @click="abrirModal(imovel)"
        >
          <!-- Image -->
          <div class="relative h-64 overflow-hidden bg-gray-200">
            <img
              :src="imovel.imagem_destaque || placeholderImagemCard"
              :alt="imovel.tipo_imovel"
              class="image-hover w-full h-full object-cover"
              loading="lazy"
              decoding="async"
              @error="event => definirImagemFallback(event, placeholderImagemCard)"
            >
            
            <!-- Badges -->
            <div class="absolute top-4 left-4 flex flex-col gap-2">
              <span v-if="imovel.exclusividade" class="badge-pulse bg-yellow-400 text-yellow-900 px-3 py-1 rounded-full text-xs font-bold">
                ⭐ EXCLUSIVO
              </span>
              <span class="bg-white/90 backdrop-blur-sm text-gray-800 px-3 py-1 rounded-full text-xs font-semibold">
                {{ imovel.tipo_imovel }}
              </span>
            </div>
            
            <!-- Price Tag -->
            <div class="absolute bottom-4 right-4">
              <div class="price-tag text-white px-4 py-2 rounded-full font-bold text-lg shadow-lg">
                {{ formatarPreco(imovel.valor_venda) }}
              </div>
            </div>
          </div>
          
          <!-- Content -->
          <div class="p-6">
            <h3 class="text-xl font-bold text-gray-800 mb-2 line-clamp-1">
              {{ imovel.tipo_imovel }} - {{ imovel.bairro }}
            </h3>
            
            <p class="text-gray-600 text-sm mb-4 flex items-center gap-1">
              <i class="fas fa-map-marker-alt text-purple-500"></i>
              {{ imovel.bairro }}, {{ imovel.cidade }} - {{ imovel.estado }}
            </p>
            
            <!-- Features -->
            <div class="flex flex-wrap gap-3 mb-4 text-sm text-gray-700">
              <div v-if="imovel.dormitorios" class="flex items-center gap-1">
                <i class="fas fa-bed text-purple-500"></i>
                <span>{{ imovel.dormitorios }} quartos</span>
              </div>
              <div v-if="imovel.suites" class="flex items-center gap-1">
                <i class="fas fa-bath text-purple-500"></i>
                <span>{{ imovel.suites }} suítes</span>
              </div>
              <div v-if="imovel.garagem" class="flex items-center gap-1">
                <i class="fas fa-car text-purple-500"></i>
                <span>{{ imovel.garagem }} vagas</span>
              </div>
              <div v-if="imovel.area_total" class="flex items-center gap-1">
                <i class="fas fa-ruler-combined text-purple-500"></i>
                <span>{{ imovel.area_total }}m²</span>
              </div>
            </div>
            
            <!-- CTA -->
            <button 
              @click.stop="abrirWhatsApp(imovel)"
              class="whatsapp-button w-full text-white py-3 rounded-xl font-semibold flex items-center justify-center gap-2"
            >
              <i class="fab fa-whatsapp text-xl"></i>
              Tenho Interesse
            </button>
          </div>
        </div>
      </div>
    </section>
    
    <!-- Modal de Detalhes -->
    <transition name="fade">
      <div 
        v-if="modalAberto" 
        class="modal-backdrop fixed inset-0 z-[9999] overflow-y-auto p-4"
        @click.self="fecharModal"
      >
        <div class="max-w-6xl mx-auto">
          <div class="zoom-in bg-white rounded-3xl w-full shadow-2xl flex flex-col md:flex-row overflow-hidden">
            <div class="relative md:w-1/2 w-full bg-gray-200">
              <button 
                @click="fecharModal"
                class="absolute top-4 right-4 z-10 bg-white/90 backdrop-blur-sm w-10 h-10 rounded-full flex items-center justify-center hover:bg-white transition shadow-lg"
              >
                <i class="fas fa-times text-xl text-gray-700"></i>
              </button>
              
              <div class="modal-gallery h-72 sm:h-80 md:h-[80vh]">
                <div 
                  v-if="imovelSelecionado.imagens && imovelSelecionado.imagens.length > 0"
                  class="relative h-full overflow-hidden"
                >
                  <div
                    v-for="(imagem, index) in imovelSelecionado.imagens"
                    :key="index"
                    :class="{'opacity-100': slideshowAtual === index, 'opacity-0': slideshowAtual !== index}"
                    class="absolute inset-0 transition-opacity duration-500"
                  >
                    <img
                      :src="imagem.url || placeholderImagemGaleria"
                      :alt="`${imovelSelecionado.tipo_imovel} - Imagem ${index + 1}`"
                      class="w-full h-full object-cover"
                      loading="lazy"
                      decoding="async"
                      @error="event => definirImagemFallback(event, placeholderImagemGaleria)"
                    >
                  </div>
                  
                  <button 
                    v-if="imovelSelecionado.imagens.length > 1"
                    @click="navegarSlideshow('prev')"
                    class="absolute left-4 top-1/2 -translate-y-1/2 bg-black/50 hover:bg-black/70 text-white w-10 h-10 rounded-full flex items-center justify-center transition z-10"
                  >
                    <i class="fas fa-chevron-left"></i>
                  </button>
                  
                  <button 
                    v-if="imovelSelecionado.imagens.length > 1"
                    @click="navegarSlideshow('next')"
                    class="absolute right-4 top-1/2 -translate-y-1/2 bg-black/50 hover:bg-black/70 text-white w-10 h-10 rounded-full flex items-center justify-center transition z-10"
                  >
                    <i class="fas fa-chevron-right"></i>
                  </button>
                  
                  <div 
                    v-if="imovelSelecionado.imagens.length > 1"
                    class="absolute bottom-4 left-1/2 -translate-x-1/2 flex gap-2"
                  >
                    <button
                      v-for="(imagem, index) in imovelSelecionado.imagens"
                      :key="index"
                      @click="slideshowAtual = index"
                      :class="{
                        'bg-white': slideshowAtual === index,
                        'bg-white/50': slideshowAtual !== index
                      }"
                      class="w-2.5 h-2.5 rounded-full transition"
                    ></button>
                  </div>
                  
                  <div 
                    v-if="imovelSelecionado.imagens.length > 1"
                    class="absolute top-4 right-4 bg-black/70 text-white px-3 py-1 rounded-full text-xs font-medium"
                  >
                    {{ slideshowAtual + 1 }} / {{ imovelSelecionado.imagens.length }}
                  </div>
                </div>
                
                <img
                  v-else
                  :src="imovelSelecionado.imagem_destaque || placeholderImagemGaleria"
                  :alt="imovelSelecionado.tipo_imovel"
                  class="w-full h-full object-cover"
                  loading="lazy"
                  decoding="async"
                  @error="event => definirImagemFallback(event, placeholderImagemGaleria)"
                >
              </div>
              
              <div class="absolute top-4 left-4 flex flex-wrap gap-2">
                <span v-if="imovelSelecionado.exclusividade" class="bg-yellow-400 text-yellow-900 px-4 py-2 rounded-full text-xs font-bold">
                  EXCLUSIVO
                </span>
                <span class="bg-white/90 backdrop-blur-sm text-gray-800 px-4 py-2 rounded-full text-xs font-semibold">
                  {{ imovelSelecionado.tipo_imovel }}
                </span>
              </div>
            </div>
            
            <div class="md:w-1/2 w-full flex flex-col bg-white">
              <div class="p-6 sm:p-8 flex-1 overflow-y-auto max-h-[80vh]">
                <header class="flex flex-col md:flex-row md:items-center md:justify-between mb-6">
                  <hgroup>
                    <h2 class="text-3xl font-bold text-gray-800 mb-2">
                      {{ imovelSelecionado.tipo_imovel }}
                    </h2>
                    <p class="text-gray-500 flex items-center gap-2">
                      <i class="fas fa-map-marker-alt text-purple-500"></i>
                      {{ imovelSelecionado.bairro }}, {{ imovelSelecionado.cidade }} - {{ imovelSelecionado.estado }}
                    </p>
                  </hgroup>
                  <div class="text-right mt-4 md:mt-0">
                    <p class="text-3xl font-bold text-purple-600">
                      {{ formatarPreco(imovelSelecionado.valor_venda) }}
                    </p>
                  </div>
                </header>
                
                <section class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                  <div v-if="imovelSelecionado.dormitorios" class="feature-card bg-gray-100 rounded-2xl p-4 text-center">
                    <i class="fas fa-bed text-purple-500 text-xl mb-2"></i>
                    <p class="font-bold text-gray-800">{{ imovelSelecionado.dormitorios }}</p>
                    <p class="text-xs text-gray-500">Quartos</p>
                  </div>
                  <div v-if="imovelSelecionado.suites" class="feature-card bg-gray-100 rounded-2xl p-4 text-center">
                    <i class="fas fa-bath text-purple-500 text-xl mb-2"></i>
                    <p class="font-bold text-gray-800">{{ imovelSelecionado.suites }}</p>
                    <p class="text-xs text-gray-500">Suítes</p>
                  </div>
                  <div v-if="imovelSelecionado.garagem" class="feature-card bg-gray-100 rounded-2xl p-4 text-center">
                    <i class="fas fa-car text-purple-500 text-xl mb-2"></i>
                    <p class="font-bold text-gray-800">{{ imovelSelecionado.garagem }}</p>
                    <p class="text-xs text-gray-500">Vagas</p>
                  </div>
                  <div v-if="imovelSelecionado.area_total" class="feature-card bg-gray-100 rounded-2xl p-4 text-center">
                    <i class="fas fa-ruler-combined text-purple-500 text-xl mb-2"></i>
                    <p class="font-bold text-gray-800">{{ imovelSelecionado.area_total }} m²</p>
                    <p class="text-xs text-gray-500">Área total</p>
                  </div>
                </section>
                
                <section class="mb-6">
                  <h3 class="text-lg font-semibold text-gray-800 mb-3 flex items-center gap-2">
                    <i class="fas fa-info-circle text-purple-500"></i>
                    Detalhes do imóvel
                  </h3>
                  <div class="prose prose-sm text-gray-600 description-content" v-html="sanitizeAndFormatHTML(imovelSelecionado.descricao)"></div>
                </section>
                
                <section v-if="imovelSelecionado.caracteristicas && imovelSelecionado.caracteristicas.length > 0" class="mb-6">
                  <h3 class="text-lg font-semibold text-gray-800 mb-3 flex items-center gap-2">
                    <i class="fas fa-star text-purple-500"></i>
                    Características
                  </h3>
                  <div class="flex flex-wrap gap-2">
                    <span 
                      v-for="(carac, index) in imovelSelecionado.caracteristicas"
                      :key="index"
                      class="px-3 py-1 bg-purple-50 text-purple-700 rounded-full text-sm"
                    >
                      {{ carac }}
                    </span>
                  </div>
                </section>
                
                <footer class="space-y-4">
                  <div class="bg-gray-100 rounded-2xl p-4 flex items-center gap-3">
                    <i class="fas fa-map-pin text-purple-500 text-xl"></i>
                    <div>
                      <p class="font-semibold text-gray-800">Localização</p>
                      <p class="text-sm text-gray-500">
                        {{ imovelSelecionado.logradouro || 'Endereço sob consulta' }}
                      </p>
                    </div>
                  </div>
                  <div class="bg-purple-50 rounded-2xl p-4 flex items-center gap-3">
                    <i class="fas fa-id-card text-purple-500 text-xl"></i>
                    <div>
                      <p class="font-semibold text-gray-800">Referência</p>
                      <p class="text-sm text-gray-500">
                        {{ imovelSelecionado.referencia_imovel || imovelSelecionado.codigo_imovel }}
                      </p>
                    </div>
                  </div>
                </footer>
              </div>
              
              <div class="p-6 sm:p-8 border-t border-gray-100 bg-white">
                <button 
                  @click.stop="abrirWhatsApp(imovelSelecionado)"
                  class="whatsapp-button w-full text-white py-3 rounded-2xl font-semibold flex items-center justify-center gap-2 text-lg"
                >
                  <i class="fab fa-whatsapp text-xl"></i>
                  Tenho Interesse
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </transition>
    
    <!-- Floating WhatsApp Button -->
    <a 
      href="https://wa.me/553173341150?text=Olá!%20Vim%20do%20site%20e%20gostaria%20de%20mais%20informações" 
      target="_blank"
      class="fixed bottom-8 right-8 whatsapp-button w-16 h-16 rounded-full flex items-center justify-center shadow-2xl z-[9998]"
    >
      <i class="fab fa-whatsapp text-3xl text-white"></i>
    </a>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch, onUnmounted } from 'vue'
import api from '../services/api'
import PropertyMap from '../components/PropertyMap.vue'

const placeholderImagemCard = 'https://via.placeholder.com/400x300?text=Imóvel'
const placeholderImagemGaleria = 'https://via.placeholder.com/800x600?text=Imóvel'

const definirImagemFallback = (event, fallback = placeholderImagemCard) => {
  if (!event?.target) return
  event.target.onerror = null
  event.target.src = fallback
}

const removerAcentos = (texto) => {
  if (!texto) return ''
  return typeof texto.normalize === 'function'
    ? texto.normalize('NFD').replace(/[\u0300-\u036f]/g, '')
    : texto
}

const prepararTextoBusca = (valor) => {
  if (valor === undefined || valor === null) return ''
  const texto = String(valor)
    .replace(/<[^>]*>/g, ' ')
    .replace(/&[#A-Za-z0-9]+;/gi, ' ')
  return removerAcentos(texto).toLowerCase()
}

const formatarTipoEtiqueta = (texto) => {
  if (!texto) return ''
  return texto
    .split('/')
    .map(part => part.trim())
    .filter(Boolean)
    .map(part => part.charAt(0).toUpperCase() + part.slice(1).toLowerCase())
    .join('/')
}

const inferirTipoImovel = (imovel) => {
  const tipoAtual = (imovel?.tipo_imovel || '').toString().trim()
  const tipoLower = tipoAtual.toLowerCase()

  if (tipoLower && tipoLower !== 'residencial') {
    return formatarTipoEtiqueta(tipoAtual)
  }

  const referenciaOriginal = (imovel?.referencia_imovel || '').toString().toLowerCase()
  const textoReferencia = prepararTextoBusca(imovel?.referencia_imovel)
  const textoDescricao = prepararTextoBusca(imovel?.descricao)
  const textoBusca = `${tipoLower} ${textoReferencia} ${textoDescricao}`

  if (textoBusca.includes('cobertura')) {
    return 'Cobertura'
  }

  if (
    referenciaOriginal.endsWith('ca') ||
    textoBusca.includes('casa') ||
    textoBusca.includes('sobrado')
  ) {
    return 'Casa'
  }

  if (textoBusca.includes('lote') || textoBusca.includes('terreno')) {
    return 'Lote/Terreno'
  }

  if (
    referenciaOriginal.endsWith('ap') ||
    textoBusca.includes('apartamento') ||
    textoBusca.includes('apto')
  ) {
    return 'Apartamento'
  }

  return tipoAtual || 'Residencial'
}

const ehImovelDeVenda = (imovel) => {
  const finalidade = (imovel?.finalidade_imovel || '').toString().toLowerCase()

  if (!finalidade) {
    return true
  }

  return finalidade.includes('venda')
}

const imoveis = ref([])
const loading = ref(true)
const modalAberto = ref(false)
const imovelSelecionado = ref({})
const slideshowAtual = ref(0)
const modoVisualizacao = ref('grid') // 'grid', 'mapa', ou 'split'
const splitViewMap = ref(null)
const filters = ref({
  search: '',
  tipo: '',
  quartos: '',
  faixaPreco: '',
  ordenarPor: ''
})

const totalImoveis = computed(() => imoveis.value.length)

const imoveisFiltrados = computed(() => {
  let result = imoveis.value
  
  // Filtro de busca - expandido para incluir mais campos
  if (filters.value.search) {
    const search = filters.value.search.toLowerCase()
    result = result.filter(i => {
      // Busca em campos básicos
      const bairroMatch = i.bairro?.toLowerCase().includes(search)
      const cidadeMatch = i.cidade?.toLowerCase().includes(search)
      const tipoMatch = i.tipo_imovel?.toLowerCase().includes(search)
      
      // Busca em referência e código
      const referenciaMatch = i.referencia_imovel?.toLowerCase().includes(search)
      const codigoMatch = i.codigo_imovel?.toString().includes(search)
      
      // Busca na descrição (removendo HTML tags)
      const descricaoText = i.descricao?.replace(/<[^>]*>/g, '').toLowerCase() || ''
      const descricaoMatch = descricaoText.includes(search)
      
      return bairroMatch || cidadeMatch || tipoMatch || referenciaMatch || codigoMatch || descricaoMatch
    })
  }
  
  // Filtro de tipo
  if (filters.value.tipo) {
    result = result.filter(i => 
      i.tipo_imovel?.toLowerCase().includes(filters.value.tipo.toLowerCase())
    )
  }
  
  // Filtro de quartos
  if (filters.value.quartos) {
    const minQuartos = parseInt(filters.value.quartos)
    result = result.filter(i => i.dormitorios >= minQuartos)
  }
  
  // Filtro de preço
  if (filters.value.faixaPreco) {
    const [min, max] = filters.value.faixaPreco.split('-').map(Number)
    result = result.filter(i => {
      const preco = parseFloat(i.valor_venda)
      return preco >= min && preco <= max
    })
  }
  
  // Ordenação
  if (filters.value.ordenarPor) {
    result = [...result] // Create a copy to avoid mutating the original array
    
    switch (filters.value.ordenarPor) {
      case 'preco-asc':
        result.sort((a, b) => {
          const precoA = parseFloat(a.valor_venda) || 0
          const precoB = parseFloat(b.valor_venda) || 0
          return precoA - precoB
        })
        break
      case 'preco-desc':
        result.sort((a, b) => {
          const precoA = parseFloat(a.valor_venda) || 0
          const precoB = parseFloat(b.valor_venda) || 0
          return precoB - precoA
        })
        break
      case 'quartos-desc':
        result.sort((a, b) => {
          const quartosA = parseInt(a.dormitorios) || 0
          const quartosB = parseInt(b.dormitorios) || 0
          return quartosB - quartosA
        })
        break
      case 'area-desc':
        result.sort((a, b) => {
          const areaA = parseFloat(a.area_total) || 0
          const areaB = parseFloat(b.area_total) || 0
          return areaB - areaA
        })
        break
    }
  }
  
  return result
})

const carregarImoveis = async () => {
  try {
    loading.value = true
    const response = await api.get('/api/properties')
    const dados = (response.data.data || []).filter(ehImovelDeVenda)
    
    // Processar imagens para cada imóvel
    imoveis.value = dados.map(imovel => {
      // Parse das imagens se vier como string
      if (typeof imovel.imagens === 'string') {
        try {
          imovel.imagens = JSON.parse(imovel.imagens)
        } catch (e) {
          imovel.imagens = []
        }
      }
      
      // Garantir que imagens seja um array
      if (!Array.isArray(imovel.imagens)) {
        imovel.imagens = []
      }
      
      // Se não tem imagem_destaque mas tem imagens, usar a primeira
      if (!imovel.imagem_destaque && imovel.imagens.length > 0) {
        imovel.imagem_destaque = imovel.imagens[0].url
      }

      imovel.tipo_original = imovel.tipo_imovel
      imovel.tipo_imovel = inferirTipoImovel(imovel)

      return imovel
    })
  } catch (error) {
    console.error('Erro ao carregar imóveis:', error)
  } finally {
    loading.value = false
  }
}

const formatarPreco = (valor) => {
  if (!valor) return 'Consulte'
  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(valor)
}

// Sanitize and format HTML descriptions
const sanitizeAndFormatHTML = (html) => {
  if (!html) return ''
  
  // Create a temporary div to parse HTML
  const tempDiv = document.createElement('div')
  tempDiv.innerHTML = html
  
  // Remove potentially dangerous tags (XSS protection)
  const dangerousTags = tempDiv.querySelectorAll('script, iframe, object, embed, form, input, button, link, meta, style')
  dangerousTags.forEach(el => el.remove())
  
  // Remove dangerous attributes from all elements
  const allElements = tempDiv.querySelectorAll('*')
  allElements.forEach(el => {
    // Remove event handlers
    Array.from(el.attributes).forEach(attr => {
      if (attr.name.startsWith('on') || attr.name === 'href' && attr.value.startsWith('javascript:')) {
        el.removeAttribute(attr.name)
      }
    })
    
    // Only allow safe href protocols
    if (el.hasAttribute('href')) {
      const href = el.getAttribute('href')
      if (href && !href.match(/^(https?:\/\/|mailto:|tel:|#)/i)) {
        el.removeAttribute('href')
      }
    }
  })
  
  // Get the sanitized HTML
  let sanitized = tempDiv.innerHTML
  
  // If there's no HTML content, treat as plain text and preserve line breaks
  if (sanitized === html && !html.includes('<')) {
    sanitized = html.replace(/\n/g, '<br>')
  }
  
  return sanitized
}

const abrirModal = (imovel) => {
  imovelSelecionado.value = imovel
  slideshowAtual.value = 0  // Reset slideshow
  modalAberto.value = true
  document.body.style.overflow = 'hidden'
}

const fecharModal = () => {
  modalAberto.value = false
  slideshowAtual.value = 0  // Reset slideshow
  document.body.style.overflow = 'auto'
}

const navegarSlideshow = (direcao) => {
  const totalImagens = imovelSelecionado.value.imagens?.length || 0
  if (totalImagens <= 1) return
  
  if (direcao === 'next') {
    slideshowAtual.value = (slideshowAtual.value + 1) % totalImagens
  } else {
    slideshowAtual.value = slideshowAtual.value === 0 ? totalImagens - 1 : slideshowAtual.value - 1
  }
}

const abrirWhatsApp = (imovel = null) => {
  const telefone = '553173341150'
  let mensagem = 'Olá! Vim do site e gostaria de mais informações'
  
  if (imovel) {
    mensagem = `Olá! Vi no site o imóvel *${imovel.tipo_imovel}* no bairro *${imovel.bairro}* (Ref: ${imovel.referencia_imovel || imovel.codigo_imovel}) e gostaria de mais informações. 🏡`
  }
  
  const url = `https://wa.me/${telefone}?text=${encodeURIComponent(mensagem)}`
  window.open(url, '_blank')
}

const limparFiltros = () => {
  filters.value = {
    search: '',
    tipo: '',
    quartos: '',
    faixaPreco: '',
    ordenarPor: ''
  }
}

const highlightMarker = (imovel) => {
  // TODO: Implement marker highlighting on map when hovering over list item
  console.log('Highlight marker for:', imovel.codigo_imovel)
}

const unhighlightMarker = () => {
  // TODO: Remove marker highlight
  console.log('Unhighlight marker')
}

onMounted(() => {
  carregarImoveis()
})

watch(modoVisualizacao, (novo) => {
  document.body.style.overflow = novo === 'split' ? 'hidden' : 'auto'
})

onUnmounted(() => {
  document.body.style.overflow = 'auto'
})
</script>

<style scoped>
.gradient-bg {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.card-hover {
  transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
}

.card-hover:hover {
  transform: translateY(-12px);
  box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
}

.image-hover {
  transition: transform 0.6s ease;
}

.card-hover:hover .image-hover {
  transform: scale(1.1);
}

.badge-pulse {
  animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: .7; }
}

.shimmer {
  background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
  background-size: 200% 100%;
  animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

.modal-backdrop {
  backdrop-filter: blur(8px);
  background-color: rgba(0, 0, 0, 0.7);
  isolation: isolate;
}

.zoom-in {
  animation: zoomIn 0.3s ease-out;
}

@keyframes zoomIn {
  from {
    transform: scale(0.9);
    opacity: 0;
  }
  to {
    transform: scale(1);
    opacity: 1;
  }
}

.price-tag {
  background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.whatsapp-button {
  background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
  transition: all 0.3s ease;
}

.whatsapp-button:hover {
  transform: scale(1.05);
  box-shadow: 0 10px 25px rgba(37, 211, 102, 0.4);
}

.filter-pill {
  transition: all 0.3s ease;
}

.filter-pill:hover {
  transform: translateY(-2px);
}

.feature-card {
  transition: all 0.3s ease;
}

.feature-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 16px rgba(99, 102, 241, 0.15);
}

.prose {
  line-height: 1.75;
}

/* Description content styling */
.description-content {
  word-wrap: break-word;
  overflow-wrap: break-word;
}

.description-content p {
  margin-bottom: 1rem;
}

.description-content p:last-child {
  margin-bottom: 0;
}

.description-content ul,
.description-content ol {
  margin-left: 1.5rem;
  margin-bottom: 1rem;
}

.description-content li {
  margin-bottom: 0.5rem;
}

.description-content strong,
.description-content b {
  font-weight: 600;
  color: #374151;
}

.description-content em,
.description-content i {
  font-style: italic;
}

.description-content h1,
.description-content h2,
.description-content h3,
.description-content h4 {
  font-weight: 700;
  color: #1f2937;
  margin-top: 1.5rem;
  margin-bottom: 0.75rem;
}

.description-content h1 {
  font-size: 1.5rem;
}

.description-content h2 {
  font-size: 1.25rem;
}

.description-content h3 {
  font-size: 1.125rem;
}

.description-content a {
  color: #6366f1;
  text-decoration: underline;
}

.description-content a:hover {
  color: #4f46e5;
}

.fade-enter-active, .fade-leave-active {
  transition: opacity 0.3s;
}

.fade-enter-from, .fade-leave-to {
  opacity: 0;
}

/* Split View Styles */
.split-view-container {
  display: flex;
  height: 100%;
  position: relative;
  z-index: 1;
}

.split-view-list {
  flex: 0 0 45%;
  max-width: 600px;
  overflow: hidden;
  border-right: 1px solid #e5e7eb;
  background: #f9fafb;
  position: relative;
  z-index: 2;
}

.split-view-scroll {
  height: 100%;
  overflow-y: auto;
  scrollbar-width: thin;
  scrollbar-color: #cbd5e1 #f1f5f9;
}

.split-view-scroll::-webkit-scrollbar {
  width: 8px;
}

.split-view-scroll::-webkit-scrollbar-track {
  background: #f1f5f9;
}

.split-view-scroll::-webkit-scrollbar-thumb {
  background: #cbd5e1;
  border-radius: 4px;
}

.split-view-scroll::-webkit-scrollbar-thumb:hover {
  background: #94a3b8;
}

.split-view-map {
  flex: 1;
  position: relative;
  z-index: 1;
}

.split-property-card {
  transition: all 0.3s ease;
}

.split-property-card:hover {
  transform: translateX(4px);
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.15);
}

@media (max-width: 768px) {
  .split-view-container {
    flex-direction: column;
    height: 100%;
  }
  
  .split-view-list {
    flex: none;
    max-width: 100%;
    max-height: 45vh;
  }
  
  .split-view-map {
    height: 55vh;
  }
}

.split-view-fullscreen {
  position: fixed;
  inset: 0;
  background: #f9fafb;
  z-index: 60;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

.split-view-fullscreen .split-view-container {
  flex: 1;
  padding: 0;
  height: 100%;
}

.split-toolbar {
  background: #ffffff;
  padding: 1rem 1.5rem;
  display: flex;
  align-items: center;
  justify-content: space-between;
  box-shadow: 0 10px 20px rgba(0, 0, 0, 0.08);
  z-index: 70;
}

.split-toolbar-info {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: 600;
  color: #4b5563;
}

.split-toolbar-close {
  background: #f3f4f6;
  border: none;
  color: #111827;
  padding: 0.5rem 1.25rem;
  border-radius: 999px;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 0.25rem;
  transition: background 0.2s ease, color 0.2s ease;
}

.split-toolbar-close:hover {
  background: #e5e7eb;
  color: #4c1d95;
}

.split-view-fullscreen .split-view-list {
  flex: 0 0 40%;
  max-width: 520px;
}

.split-view-fullscreen .split-view-map {
  flex: 1;
}

.split-view-fullscreen #properties-map,
.split-view-fullscreen .map-view {
  border-radius: 0;
}

@media (max-width: 768px) {
  .split-toolbar {
    flex-direction: column;
    align-items: flex-start;
    gap: 0.75rem;
  }
  
  .split-toolbar-close {
    width: 100%;
  }
  
  .split-view-fullscreen .split-view-list {
    max-width: 100%;
  }
}
</style>
