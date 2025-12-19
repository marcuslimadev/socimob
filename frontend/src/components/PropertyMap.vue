<template>
  <div class="property-map-container">
    <!-- Map Container -->
    <div id="properties-map" class="map-view" ref="mapContainer"></div>
    
    <!-- Map Controls Overlay -->
    <div class="map-info-overlay" v-if="filteredCount >= 0">
      <div class="info-badge">
        <i class="fas fa-map-marked-alt mr-2"></i>
        <span>{{ filteredCount }} imóveis na visualização</span>
      </div>
      
      <!-- Redo Search Button -->
      <button 
        v-if="showRedoSearch" 
        @click="redoSearch"
        class="redo-search-button"
      >
        <i class="fas fa-redo-alt mr-2"></i>
        Refazer busca nesta área
      </button>
    </div>
    
    <!-- Zoom Filter Info -->
    <div v-if="showZoomFilterInfo" class="zoom-filter-info">
      <i class="fas fa-info-circle mr-2"></i>
      Filtrando imóveis na área visível do mapa
    </div>
    
    <!-- Hover Preview Card -->
    <transition name="slide-up">
      <div 
        v-if="hoveredProperty" 
        class="hover-preview-card"
        :style="{ left: previewCardPosition.x + 'px', top: previewCardPosition.y + 'px' }"
      >
        <div class="preview-image-container">
          <img
            :src="hoveredProperty.imagem_destaque || previewPlaceholderImage"
            :alt="hoveredProperty.tipo_imovel"
            class="preview-image"
            loading="lazy"
            decoding="async"
            @error="event => handlePreviewImageError(event)"
          >
          <span class="preview-badge badge-venda">
            {{ obterRotuloFinalidade(hoveredProperty.finalidade_imovel) }}
          </span>
        </div>
        <div class="preview-content">
          <div class="preview-price">{{ formatarMoeda(hoveredProperty.valor_venda) }}</div>
          <div class="preview-title">{{ hoveredProperty.tipo_imovel }}</div>
          <div class="preview-address">
            <i class="fas fa-map-marker-alt"></i>
            {{ hoveredProperty.bairro }}, {{ hoveredProperty.cidade }}
          </div>
          <div class="preview-features">
            <span v-if="hoveredProperty.dormitorios">
              <i class="fas fa-bed"></i> {{ hoveredProperty.dormitorios }}
            </span>
            <span v-if="hoveredProperty.suites">
              <i class="fas fa-bath"></i> {{ hoveredProperty.suites }}
            </span>
            <span v-if="hoveredProperty.garagem">
              <i class="fas fa-car"></i> {{ hoveredProperty.garagem }}
            </span>
            <span v-if="hoveredProperty.area_total">
              <i class="fas fa-ruler-combined"></i> {{ hoveredProperty.area_total }}m²
            </span>
          </div>
        </div>
      </div>
    </transition>
    
    <!-- Keyboard Shortcuts Help -->
    <transition name="fade">
      <div v-if="showKeyboardHelp" class="keyboard-help-overlay">
        <div class="keyboard-help-content">
          <div class="keyboard-help-header">
            <h3><i class="fas fa-keyboard mr-2"></i>Atalhos do Teclado</h3>
            <button @click="showKeyboardHelp = false" class="close-help-btn">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="keyboard-shortcuts">
            <div class="shortcut-item">
              <kbd>Ctrl</kbd> + <kbd>↑↓←→</kbd>
              <span>Mover mapa</span>
            </div>
            <div class="shortcut-item">
              <kbd>Ctrl</kbd> + <kbd>+</kbd>
              <span>Zoom in</span>
            </div>
            <div class="shortcut-item">
              <kbd>Ctrl</kbd> + <kbd>-</kbd>
              <span>Zoom out</span>
            </div>
            <div class="shortcut-item">
              <kbd>Esc</kbd>
              <span>Fechar popup</span>
            </div>
            <div class="shortcut-item">
              <kbd>?</kbd>
              <span>Mostrar/ocultar ajuda</span>
            </div>
          </div>
        </div>
      </div>
    </transition>
    
    <!-- Help Button -->
    <button 
      @click="showKeyboardHelp = !showKeyboardHelp" 
      class="keyboard-help-btn"
      title="Atalhos do teclado (pressione ?)"
    >
      <i class="fas fa-question-circle"></i>
    </button>
  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, watch, nextTick } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import 'leaflet.markercluster/dist/MarkerCluster.css'
import 'leaflet.markercluster/dist/MarkerCluster.Default.css'
import 'leaflet.markercluster'
import 'leaflet-draw/dist/leaflet.draw.css'
import 'leaflet-draw'

const props = defineProps({
  imoveis: {
    type: Array,
    required: true,
    default: () => []
  },
  onPropertyClick: {
    type: Function,
    default: () => {}
  }
})

const emit = defineEmits(['update:filteredProperties', 'property-click'])

// Map State
const previewPlaceholderImage = 'https://via.placeholder.com/280x160?text=Imóvel'

const mapContainer = ref(null)
const map = ref(null)
const markersLayer = ref(null)
const markerClusterGroup = ref(null)
const userMarker = ref(null)
const currentLayer = ref('SATELLITE')
const zoomFilterEnabled = ref(true)
const lastBounds = ref(null)
const filteredCount = ref(0)
const showZoomFilterInfo = ref(false)
const showRedoSearch = ref(false)
const hoveredProperty = ref(null)
const previewCardPosition = ref({ x: 0, y: 0 })
const drawControl = ref(null)
const drawnItems = ref(null)
const showKeyboardHelp = ref(false)

const handlePreviewImageError = (event) => {
  if (!event?.target) return
  event.target.onerror = null
  event.target.src = previewPlaceholderImage
}

const ehImovelDeVenda = (imovel) => {
  const finalidade = (imovel?.finalidade_imovel || '').toString().toLowerCase()

  if (!finalidade) {
    return true
  }

  return finalidade.includes('venda')
}

const obterRotuloFinalidade = (finalidade) => {
  const texto = (finalidade || '').toString().toLowerCase()

  if (!texto || texto.includes('venda')) {
    return 'Imóvel à venda'
  }

  return texto.charAt(0).toUpperCase() + texto.slice(1)
}

// Map Configuration
const mapConfig = {
  DEFAULT_CENTER: [-19.9191, -43.9386], // Belo Horizonte
  DEFAULT_ZOOM: 12,
  MIN_ZOOM: 10,
  MAX_ZOOM: 19,
  MIN_ZOOM_FOR_FILTER: 14,
  MIN_ZOOM_FOR_INDIVIDUAL_MARKERS: 15, // Below this, show price clusters
  TILE_LAYERS: {
    SATELLITE: {
      name: 'Satélite',
      url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
      attribution: '© Esri'
    },
    STREET: {
      name: 'Ruas',
      url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
      attribution: '© OpenStreetMap contributors'
    },
    TERRAIN: {
      name: 'Relevo',
      url: 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
      attribution: '© OpenTopoMap'
    }
  }
}

// Initialize Map
const inicializarMapa = async () => {
  try {
    console.log('Inicializando mapa de propriedades...')
    
    // Create map instance
    map.value = L.map('properties-map', {
      center: mapConfig.DEFAULT_CENTER,
      zoom: mapConfig.DEFAULT_ZOOM,
      minZoom: mapConfig.MIN_ZOOM,
      maxZoom: mapConfig.MAX_ZOOM,
      zoomControl: true,
      attributionControl: true,
      preferCanvas: true
    })

    // Add default tile layer (satellite)
    L.tileLayer(mapConfig.TILE_LAYERS.SATELLITE.url, {
      attribution: mapConfig.TILE_LAYERS.SATELLITE.attribution,
      maxZoom: mapConfig.MAX_ZOOM
    }).addTo(map.value)

    // Create marker cluster group with custom styling
    markerClusterGroup.value = L.markerClusterGroup({
      showCoverageOnHover: false,
      zoomToBoundsOnClick: true,
      spiderfyOnMaxZoom: true,
      removeOutsideVisibleBounds: true,
      maxClusterRadius: 80,
      iconCreateFunction: createClusterIcon,
      disableClusteringAtZoom: mapConfig.MIN_ZOOM_FOR_INDIVIDUAL_MARKERS
    })
    
    map.value.addLayer(markerClusterGroup.value)

    // Create drawn items layer for custom area selection
    drawnItems.value = new L.FeatureGroup()
    map.value.addLayer(drawnItems.value)

    // Add custom controls
    adicionarControles()

    // Map events
    map.value.on('moveend', handleMapMoveEnd)
    map.value.on('zoomend', verificarFiltroZoom)
    map.value.on('dragend', () => {
      showRedoSearch.value = true
    })

    console.log('Mapa inicializado com sucesso')
    
    // Update markers with initial properties
    if (props.imoveis.length > 0) {
      atualizarMarkers(props.imoveis)
    }

  } catch (error) {
    console.error('Erro ao inicializar mapa:', error)
  }
}

// Create Custom Cluster Icon with Price Range
const createClusterIcon = (cluster) => {
  const markers = cluster.getAllChildMarkers()
  const prices = markers.map(m => {
    const price = m.options.propertyData?.valor_venda || 0
    return parseFloat(price)
  }).filter(p => p > 0)
  
  const count = markers.length
  const avgPrice = prices.length > 0 ? prices.reduce((a, b) => a + b, 0) / prices.length : 0
  const minPrice = prices.length > 0 ? Math.min(...prices) : 0
  const maxPrice = prices.length > 0 ? Math.max(...prices) : 0
  
  const priceRange = formatPriceRange(minPrice, maxPrice)
  
  const html = `
    <div class="cluster-marker">
      <div class="cluster-count">${count}</div>
      <div class="cluster-price">${priceRange}</div>
    </div>
  `
  
  return L.divIcon({
    html: html,
    className: 'custom-cluster-icon',
    iconSize: L.point(80, 80)
  })
}

// Format Price Range for Clusters
const formatPriceRange = (min, max) => {
  if (min === 0 && max === 0) return 'Consulte'
  
  const formatShort = (value) => {
    if (value >= 1000000) {
      return `${(value / 1000000).toFixed(1)}M`
    } else if (value >= 1000) {
      return `${(value / 1000).toFixed(0)}K`
    }
    return value.toString()
  }
  
  if (min === max) {
    return `R$ ${formatShort(min)}`
  }
  
  return `R$ ${formatShort(min)}-${formatShort(max)}`
}

// Handle Map Move End - Show "Redo Search" button
const handleMapMoveEnd = () => {
  const currentBounds = map.value.getBounds()
  
  if (lastBounds.value && !boundsEqual(currentBounds, lastBounds.value)) {
    showRedoSearch.value = true
  }
}

// Redo Search in Current Map Area
const redoSearch = () => {
  verificarFiltroZoom()
  showRedoSearch.value = false
  lastBounds.value = map.value.getBounds()
}

// Add Custom Controls
const adicionarControles = () => {
  // Layer Control
  const LayerControl = L.Control.extend({
    options: { position: 'topright' },
    onAdd: () => {
      const container = L.DomUtil.create('div', 'leaflet-bar map-layer-control')
      
      Object.keys(mapConfig.TILE_LAYERS).forEach(key => {
        const layerConfig = mapConfig.TILE_LAYERS[key]
        const button = L.DomUtil.create('button', 'layer-button', container)
        button.innerHTML = layerConfig.name
        button.title = `Trocar para ${layerConfig.name}`
        
        if (key === currentLayer.value) {
          button.classList.add('active')
        }
        
        L.DomEvent.on(button, 'click', (e) => {
          L.DomEvent.stopPropagation(e)
          trocarCamada(key)
          
          container.querySelectorAll('.layer-button').forEach(btn => {
            btn.classList.remove('active')
          })
          button.classList.add('active')
        })
      })
      
      return container
    }
  })

  // Location Control
  const LocationControl = L.Control.extend({
    options: { position: 'topright' },
    onAdd: () => {
      const container = L.DomUtil.create('div', 'leaflet-bar leaflet-control-custom')
      container.innerHTML = '<i class="fas fa-location-arrow"></i>'
      container.title = 'Minha localização'
      container.onclick = obterLocalizacaoUsuario
      return container
    }
  })

  // Filter Control
  const FilterControl = L.Control.extend({
    options: { position: 'topleft' },
    onAdd: () => {
      const container = L.DomUtil.create('div', 'leaflet-bar leaflet-control-custom filter-control')
      
      const updateIcon = () => {
        container.innerHTML = `<i class="fas fa-filter" style="color: ${zoomFilterEnabled.value ? '#10b981' : '#6b7280'}"></i>`
        container.title = zoomFilterEnabled.value ? 'Desativar filtro por área' : 'Ativar filtro por área'
      }
      
      updateIcon()
      
      container.onclick = () => {
        zoomFilterEnabled.value = !zoomFilterEnabled.value
        updateIcon()
        verificarFiltroZoom()
      }
      
      return container
    }
  })

  // Draw Control for custom area selection
  drawControl.value = new L.Control.Draw({
    position: 'topleft',
    draw: {
      polygon: {
        allowIntersection: false,
        drawError: {
          color: '#e74c3c',
          message: '<strong>Erro!</strong> A área não pode se intersectar.'
        },
        shapeOptions: {
          color: '#6366f1',
          weight: 3
        }
      },
      rectangle: {
        shapeOptions: {
          color: '#6366f1',
          weight: 3
        }
      },
      circle: {
        shapeOptions: {
          color: '#6366f1',
          weight: 3
        }
      },
      polyline: false,
      marker: false,
      circlemarker: false
    },
    edit: {
      featureGroup: drawnItems.value,
      remove: true
    }
  })

  map.value.addControl(new LayerControl())
  map.value.addControl(new LocationControl())
  map.value.addControl(new FilterControl())
  map.value.addControl(drawControl.value)

  // Handle drawn shapes
  map.value.on(L.Draw.Event.CREATED, (event) => {
    const layer = event.layer
    drawnItems.value.clearLayers()
    drawnItems.value.addLayer(layer)
    
    // Filter properties within drawn area
    filterPropertiesByDrawnArea(layer)
  })

  map.value.on(L.Draw.Event.DELETED, () => {
    // Reset filter when area is deleted
    emit('update:filteredProperties', props.imoveis.filter(ehImovelDeVenda))
  })
}

// Filter Properties by Drawn Area
const filterPropertiesByDrawnArea = (layer) => {
  const filteredProperties = props.imoveis.filter(imovel => {
    if (!ehImovelDeVenda(imovel)) return false
    if (!validarCoordenadas(imovel.latitude, imovel.longitude)) return false
    
    const point = L.latLng(parseFloat(imovel.latitude), parseFloat(imovel.longitude))
    
    if (layer instanceof L.Circle) {
      return layer.getLatLng().distanceTo(point) <= layer.getRadius()
    } else if (layer instanceof L.Polygon || layer instanceof L.Rectangle) {
      return layer.getBounds().contains(point)
    }
    
    return false
  })
  
  emit('update:filteredProperties', filteredProperties)
  atualizarMarkers(filteredProperties)
}

// Update Markers
const atualizarMarkers = (imoveis) => {
  if (!map.value || !markerClusterGroup.value) return

  try {
    const apenasVenda = imoveis.filter(ehImovelDeVenda)
    console.log(`Atualizando ${apenasVenda.length} markers no mapa...`)

    // Clear existing markers
    markerClusterGroup.value.clearLayers()

    const bounds = []
    let markersValidos = 0

    apenasVenda.forEach(imovel => {
      if (!validarCoordenadas(imovel.latitude, imovel.longitude)) {
        return
      }

      const lat = parseFloat(imovel.latitude)
      const lng = parseFloat(imovel.longitude)

      const marker = criarMarker(lat, lng, imovel)
      markerClusterGroup.value.addLayer(marker)

      bounds.push([lat, lng])
      markersValidos++
    })

    filteredCount.value = markersValidos

    // Fit bounds if not using zoom filter
    if (bounds.length > 0 && !zoomFilterEnabled.value) {
      if (bounds.length === 1) {
        map.value.setView(bounds[0], 15)
      } else {
        map.value.fitBounds(bounds, { 
          padding: [20, 20],
          maxZoom: 16
        })
      }
    }

    console.log(`${markersValidos} markers adicionados com sucesso`)

  } catch (error) {
    console.error('Erro ao atualizar markers:', error)
  }
}

// Create Custom Marker
const criarMarker = (lat, lng, imovel) => {
  const isVenda = ehImovelDeVenda(imovel)
  const gradient = isVenda
    ? 'linear-gradient(135deg, #10b981 0%, #059669 100%)'
    : 'linear-gradient(135deg, #6b7280 0%, #4b5563 100%)'

  const iconHtml = `
    <div class="custom-marker-icon" style="background: ${gradient}">
      <i class="fas fa-${isVenda ? 'tag' : 'map-marker-alt'}"></i>
      <div class="marker-badge" style="background: ${isVenda ? '#f59e0b' : '#9ca3af'}">
        ${isVenda ? 'V' : 'I'}
      </div>
    </div>
  `

  const customIcon = L.divIcon({
    html: iconHtml,
    className: 'custom-leaflet-marker',
    iconSize: [40, 40],
    iconAnchor: [20, 20],
    popupAnchor: [0, -20]
  })

  const marker = L.marker([lat, lng], { 
    icon: customIcon,
    propertyData: imovel // Store property data for clustering
  })

  // Create popup
  const popupContent = criarPopupContent(imovel)
  marker.bindPopup(popupContent, {
    maxWidth: 320,
    className: 'custom-popup'
  })

  // Marker events with hover preview
  marker.on('mouseover', (e) => {
    hoveredProperty.value = imovel
    
    // Position the preview card near the marker
    const markerPoint = map.value.latLngToContainerPoint(e.latlng)
    previewCardPosition.value = {
      x: markerPoint.x + 50,
      y: markerPoint.y - 100
    }
    
    marker.openPopup()
  })
  
  marker.on('mouseout', () => {
    setTimeout(() => {
      hoveredProperty.value = null
    }, 300)
  })
  
  marker.on('click', () => {
    emit('property-click', imovel)
    if (props.onPropertyClick) {
      props.onPropertyClick(imovel)
    }
  })

  return marker
}

// Create Popup Content
const criarPopupContent = (imovel) => {
  const preco = formatarMoeda(imovel.valor_venda)
  const imagemUrl = imovel.imagem_destaque || 'https://via.placeholder.com/280x160?text=Imóvel'

  return `
    <article class="popup-content">
      <figure class="popup-image">
        <img src="${imagemUrl}" 
             alt="${imovel.tipo_imovel}"
             loading="lazy"
             onerror="this.src='https://via.placeholder.com/280x160?text=Imóvel'">
        <span class="popup-badge badge-venda">
          ${obterRotuloFinalidade(imovel.finalidade_imovel)}
        </span>
      </figure>
      
      <div class="popup-body">
        <h3 class="popup-price">${preco}</h3>
        
        <h4 class="popup-title">${imovel.tipo_imovel}</h4>
        
        <address class="popup-address">
          <i class="fas fa-map-marker-alt"></i>
          ${imovel.bairro}, ${imovel.cidade}
        </address>
        
        <dl class="popup-features">
          ${imovel.dormitorios ? `
            <div class="feature-item">
              <dt><i class="fas fa-bed"></i></dt>
              <dd>${imovel.dormitorios}</dd>
            </div>
          ` : ''}
          ${imovel.suites ? `
            <div class="feature-item">
              <dt><i class="fas fa-bath"></i></dt>
              <dd>${imovel.suites}</dd>
            </div>
          ` : ''}
          ${imovel.garagem ? `
            <div class="feature-item">
              <dt><i class="fas fa-car"></i></dt>
              <dd>${imovel.garagem}</dd>
            </div>
          ` : ''}
        </dl>
        
        <button
          onclick="window.dispatchEvent(new CustomEvent('open-property-details', {detail: ${JSON.stringify(imovel.codigo_imovel)}}))"
          class="popup-button">
          <i class="fas fa-eye"></i> Ver Detalhes
        </button>
      </div>
    </article>
  `
}

// Switch Map Layer
const trocarCamada = (novaLayer) => {
  if (!map.value || novaLayer === currentLayer.value) return

  // Remove all layers
  map.value.eachLayer(layer => {
    if (layer instanceof L.TileLayer) {
      map.value.removeLayer(layer)
    }
  })

  // Add new layer
  L.tileLayer(mapConfig.TILE_LAYERS[novaLayer].url, {
    attribution: mapConfig.TILE_LAYERS[novaLayer].attribution,
    maxZoom: mapConfig.MAX_ZOOM
  }).addTo(map.value)

  currentLayer.value = novaLayer
  console.log(`Camada alterada para: ${novaLayer}`)
}

// Get User Location
const obterLocalizacaoUsuario = async () => {
  if (!navigator.geolocation) {
    console.warn('Geolocalização não suportada')
    return
  }

  try {
    const position = await new Promise((resolve, reject) => {
      navigator.geolocation.getCurrentPosition(resolve, reject, {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 300000
      })
    })

    const { latitude, longitude } = position.coords
    console.log('Localização obtida:', { latitude, longitude })

    // Remove previous user marker
    if (userMarker.value) {
      map.value.removeLayer(userMarker.value)
    }

    // Add user marker
    const userIcon = L.divIcon({
      html: `
        <div class="user-marker-icon">
          <i class="fas fa-user"></i>
        </div>
      `,
      className: 'user-leaflet-marker',
      iconSize: [28, 28],
      iconAnchor: [14, 14]
    })

    userMarker.value = L.marker([latitude, longitude], { icon: userIcon })
      .addTo(map.value)
      .bindPopup('📍 Sua localização')
      .openPopup()

    map.value.setView([latitude, longitude], 15)

  } catch (error) {
    console.error('Erro ao obter localização:', error)
  }
}

// Verify Zoom Filter
const verificarFiltroZoom = () => {
  if (!zoomFilterEnabled.value || !map.value) {
    showZoomFilterInfo.value = false
    return
  }

  const zoom = map.value.getZoom()
  const minZoom = mapConfig.MIN_ZOOM_FOR_FILTER

  if (zoom >= minZoom) {
    const bounds = map.value.getBounds()
    
    if (!lastBounds.value || !boundsEqual(bounds, lastBounds.value)) {
      lastBounds.value = bounds
      
      // Filter properties by visible area
      const filteredProperties = props.imoveis.filter(imovel => {
        if (!ehImovelDeVenda(imovel)) return false
        if (!validarCoordenadas(imovel.latitude, imovel.longitude)) return false
        return bounds.contains([parseFloat(imovel.latitude), parseFloat(imovel.longitude)])
      })

      emit('update:filteredProperties', filteredProperties)
      showZoomFilterInfo.value = true
    }
  } else {
    showZoomFilterInfo.value = false
    emit('update:filteredProperties', props.imoveis.filter(ehImovelDeVenda))
  }
}

// Compare Bounds
const boundsEqual = (bounds1, bounds2) => {
  const tolerance = 0.001
  return Math.abs(bounds1.getNorth() - bounds2.getNorth()) < tolerance &&
         Math.abs(bounds1.getSouth() - bounds2.getSouth()) < tolerance &&
         Math.abs(bounds1.getEast() - bounds2.getEast()) < tolerance &&
         Math.abs(bounds1.getWest() - bounds2.getWest()) < tolerance
}

// Validate Coordinates
const validarCoordenadas = (lat, lng) => {
  if (!lat || !lng) return false
  const latitude = parseFloat(lat)
  const longitude = parseFloat(lng)
  return !isNaN(latitude) && !isNaN(longitude) && 
         latitude >= -90 && latitude <= 90 && 
         longitude >= -180 && longitude <= 180
}

// Format Currency
const formatarMoeda = (valor) => {
  if (!valor) return 'Consulte'
  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(valor)
}

// Watchers
watch(() => props.imoveis, (novosImoveis) => {
  if (map.value && novosImoveis.length > 0) {
    atualizarMarkers(novosImoveis)
  }
}, { deep: true })

// Lifecycle
const handlePropertyDetailsEvent = (e) => {
  const targetCode = e?.detail != null ? String(e.detail) : null
  if (!targetCode) return

  const imovel = props.imoveis.find(i => String(i.codigo_imovel) === targetCode)
  if (imovel) {
    emit('property-click', imovel)
  }
}

onMounted(async () => {
  await nextTick()
  inicializarMapa()

  // Listen for property details event
  window.addEventListener('open-property-details', handlePropertyDetailsEvent)

  // Keyboard navigation
  window.addEventListener('keydown', handleKeyboardNavigation)
})

onBeforeUnmount(() => {
  if (map.value) {
    map.value.remove()
    map.value = null
  }

  window.removeEventListener('open-property-details', handlePropertyDetailsEvent)
  window.removeEventListener('keydown', handleKeyboardNavigation)
})

// Keyboard Navigation Handler
const handleKeyboardNavigation = (e) => {
  if (!map.value) return
  
  const panDistance = 100
  const zoomStep = 1
  
  // Toggle help with '?'
  if (e.key === '?' && !e.ctrlKey && !e.metaKey) {
    e.preventDefault()
    showKeyboardHelp.value = !showKeyboardHelp.value
    return
  }
  
  switch(e.key) {
    case 'ArrowUp':
      if (e.ctrlKey || e.metaKey) {
        e.preventDefault()
        map.value.panBy([0, -panDistance])
      }
      break
    case 'ArrowDown':
      if (e.ctrlKey || e.metaKey) {
        e.preventDefault()
        map.value.panBy([0, panDistance])
      }
      break
    case 'ArrowLeft':
      if (e.ctrlKey || e.metaKey) {
        e.preventDefault()
        map.value.panBy([-panDistance, 0])
      }
      break
    case 'ArrowRight':
      if (e.ctrlKey || e.metaKey) {
        e.preventDefault()
        map.value.panBy([panDistance, 0])
      }
      break
    case '+':
    case '=':
      if (e.ctrlKey || e.metaKey) {
        e.preventDefault()
        map.value.zoomIn(zoomStep)
      }
      break
    case '-':
    case '_':
      if (e.ctrlKey || e.metaKey) {
        e.preventDefault()
        map.value.zoomOut(zoomStep)
      }
      break
    case 'Escape':
      // Close any open popups and help
      map.value.closePopup()
      hoveredProperty.value = null
      showKeyboardHelp.value = false
      break
  }
}
</script>

<style scoped>
.property-map-container {
  position: relative;
  width: 100%;
  height: 100%;
  min-height: 600px;
}

.map-view {
  width: 100%;
  height: 100%;
  border-radius: 1rem;
  overflow: hidden;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
}

.map-info-overlay {
  position: absolute;
  top: 1rem;
  left: 50%;
  transform: translateX(-50%);
  z-index: 400;
  pointer-events: none;
}

.info-badge {
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(10px);
  color: #374151;
  padding: 0.75rem 1.5rem;
  border-radius: 2rem;
  font-size: 0.875rem;
  font-weight: 600;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.zoom-filter-info {
  position: absolute;
  bottom: 2rem;
  left: 50%;
  transform: translateX(-50%);
  z-index: 400;
  background: rgba(99, 102, 241, 0.95);
  backdrop-filter: blur(10px);
  color: white;
  padding: 0.75rem 1.5rem;
  border-radius: 2rem;
  font-size: 0.875rem;
  font-weight: 600;
  box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
  display: flex;
  align-items: center;
  gap: 0.5rem;
  animation: slideUp 0.3s ease-out;
}

@keyframes slideUp {
  from {
    opacity: 0;
    transform: translateX(-50%) translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateX(-50%) translateY(0);
  }
}

/* Redo Search Button */
.redo-search-button {
  background: linear-gradient(135deg, #6366f1 0%, #60a5fa 100%);
  color: white;
  border: none;
  padding: 0.75rem 1.5rem;
  border-radius: 2rem;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
  transition: all 0.3s ease;
  margin-top: 0.5rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.redo-search-button:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(99, 102, 241, 0.4);
}

/* Hover Preview Card */
.hover-preview-card {
  position: absolute;
  z-index: 500;
  background: white;
  border-radius: 12px;
  overflow: hidden;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
  width: 280px;
  pointer-events: none;
  transform: translateX(-50%);
}

.preview-image-container {
  position: relative;
  height: 140px;
  overflow: hidden;
}

.preview-image {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.preview-badge {
  position: absolute;
  top: 8px;
  left: 8px;
  color: white;
  padding: 4px 10px;
  border-radius: 15px;
  font-size: 11px;
  font-weight: bold;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
}

.preview-content {
  padding: 12px;
}

.preview-price {
  font-size: 18px;
  font-weight: bold;
  background: linear-gradient(135deg, #60a5fa 0%, #2563eb 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  margin-bottom: 6px;
}

.preview-title {
  font-weight: bold;
  color: #374151;
  font-size: 14px;
  margin-bottom: 4px;
}

.preview-address {
  color: #6b7280;
  font-size: 12px;
  margin-bottom: 8px;
  display: flex;
  align-items: center;
  gap: 4px;
}

.preview-features {
  display: flex;
  gap: 12px;
  font-size: 12px;
  color: #6b7280;
}

.preview-features span {
  display: flex;
  align-items: center;
  gap: 3px;
}

.preview-features i {
  color: #6366f1;
}

.slide-up-enter-active {
  animation: slideUpPreview 0.2s ease-out;
}

.slide-up-leave-active {
  animation: slideUpPreview 0.15s ease-in reverse;
}

@keyframes slideUpPreview {
  from {
    opacity: 0;
    transform: translateX(-50%) translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateX(-50%) translateY(0);
  }
}
</style>

<style>
/* Global Leaflet Customizations */
.leaflet-control-custom {
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(10px);
  width: 40px;
  height: 40px;
  cursor: pointer;
  border-radius: 8px;
  border: 1px solid rgba(255, 255, 255, 0.2);
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
}

.leaflet-control-custom:hover {
  background: white;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.leaflet-control-custom i {
  color: #6366f1;
  font-size: 16px;
}

.filter-control.leaflet-control-custom:hover i {
  transform: scale(1.1);
}

.map-layer-control {
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(10px);
  border-radius: 8px;
  padding: 0.5rem;
  display: flex;
  gap: 0.5rem;
}

.layer-button {
  background: transparent;
  border: none;
  padding: 0.5rem 1rem;
  border-radius: 6px;
  font-size: 0.875rem;
  font-weight: 600;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.3s ease;
}

.layer-button:hover {
  background: rgba(99, 102, 241, 0.1);
  color: #6366f1;
}

.layer-button.active {
  background: linear-gradient(135deg, #6366f1 0%, #60a5fa 100%);
  color: white;
}

/* Custom Marker Styles */
.custom-marker-icon {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  border: 3px solid white;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 16px;
  position: relative;
  animation: markerFloat 3s ease-in-out infinite;
}

@keyframes markerFloat {
  0%, 100% { transform: translateY(0px); }
  50% { transform: translateY(-5px); }
}

.marker-badge {
  position: absolute;
  top: -6px;
  right: -6px;
  width: 18px;
  height: 18px;
  border-radius: 50%;
  border: 2px solid white;
  font-size: 10px;
  font-weight: bold;
  display: flex;
  align-items: center;
  justify-content: center;
}

.user-marker-icon {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
  width: 28px;
  height: 28px;
  border-radius: 50%;
  border: 3px solid white;
  box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  font-size: 12px;
  animation: userPulse 2s infinite;
}

@keyframes userPulse {
  0%, 100% {
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
  }
  50% {
    box-shadow: 0 4px 20px rgba(239, 68, 68, 0.7);
  }
}

/* Custom Popup Styles */
.custom-popup .leaflet-popup-content-wrapper {
  background: white;
  border-radius: 16px;
  padding: 0;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
  overflow: hidden;
}

.custom-popup .leaflet-popup-content {
  margin: 0;
  width: 300px !important;
}

.custom-popup .leaflet-popup-tip {
  background: white;
}

.popup-content {
  font-family: inherit;
  margin: 0;
}

.popup-image {
  position: relative;
  margin: 0;
  height: 160px;
  overflow: hidden;
}

.popup-image img {
  width: 100%;
  height: 100%;
  object-fit: cover;
  transition: transform 0.3s ease;
}

.popup-content:hover .popup-image img {
  transform: scale(1.05);
}

.popup-badge {
  position: absolute;
  top: 12px;
  left: 12px;
  color: white;
  padding: 6px 12px;
  border-radius: 20px;
  font-size: 12px;
  font-weight: bold;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
}

.badge-venda {
  background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.popup-body {
  padding: 1rem;
  text-align: center;
}

.popup-price {
  font-size: 20px;
  font-weight: bold;
  background: linear-gradient(135deg, #60a5fa 0%, #2563eb 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
  margin: 0 0 8px 0;
}

.popup-title {
  font-weight: bold;
  margin: 0 0 4px 0;
  color: #374151;
  font-size: 16px;
}

.popup-address {
  color: #6b7280;
  font-size: 14px;
  font-style: normal;
  margin: 0 0 12px 0;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 4px;
}

.popup-features {
  display: flex;
  justify-content: center;
  gap: 16px;
  margin: 0 0 16px 0;
  font-size: 13px;
  color: #6b7280;
}

.feature-item {
  display: flex;
  align-items: center;
  gap: 4px;
}

.feature-item dt {
  color: #6366f1;
  font-weight: normal;
  margin: 0;
}

.feature-item dd {
  margin: 0;
}

.popup-button {
  background: linear-gradient(135deg, #6366f1 0%, #60a5fa 100%);
  color: white;
  border: none;
  padding: 10px 20px;
  border-radius: 12px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
  box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3);
  width: 100%;
}

.popup-button:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(99, 102, 241, 0.4);
}

.popup-button i {
  margin-right: 8px;
}

/* Custom Cluster Marker Styles */
.custom-cluster-icon {
  background: transparent !important;
  border: none !important;
}

.cluster-marker {
  background: linear-gradient(135deg, #6366f1 0%, #60a5fa 100%);
  border: 4px solid white;
  border-radius: 50%;
  width: 80px;
  height: 80px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
  animation: clusterPulse 2s ease-in-out infinite;
}

@keyframes clusterPulse {
  0%, 100% {
    transform: scale(1);
    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
  }
  50% {
    transform: scale(1.05);
    box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4);
  }
}

.cluster-count {
  color: white;
  font-size: 22px;
  font-weight: bold;
  line-height: 1;
}

.cluster-price {
  color: white;
  font-size: 10px;
  font-weight: 600;
  margin-top: 2px;
  white-space: nowrap;
}

/* Leaflet Draw Styles Override */
.leaflet-draw-toolbar {
  margin-top: 10px !important;
}

.leaflet-draw-draw-polygon,
.leaflet-draw-draw-rectangle,
.leaflet-draw-draw-circle {
  background-color: rgba(255, 255, 255, 0.95) !important;
  backdrop-filter: blur(10px);
  border-radius: 8px !important;
}

.leaflet-draw-actions {
  background: rgba(255, 255, 255, 0.95) !important;
  backdrop-filter: blur(10px);
  border-radius: 8px !important;
}

/* Keyboard Help Overlay */
.keyboard-help-btn {
  position: absolute;
  bottom: 2rem;
  right: 2rem;
  z-index: 400;
  background: rgba(255, 255, 255, 0.95);
  backdrop-filter: blur(10px);
  width: 48px;
  height: 48px;
  border-radius: 50%;
  border: none;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  justify-content: center;
}

.keyboard-help-btn:hover {
  background: white;
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.2);
  transform: scale(1.05);
}

.keyboard-help-btn i {
  color: #6366f1;
  font-size: 24px;
}

.keyboard-help-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(8px);
  z-index: 600;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 2rem;
}

.keyboard-help-content {
  background: white;
  border-radius: 16px;
  padding: 2rem;
  max-width: 500px;
  width: 100%;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.keyboard-help-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 1.5rem;
  padding-bottom: 1rem;
  border-bottom: 2px solid #f3f4f6;
}

.keyboard-help-header h3 {
  font-size: 1.5rem;
  font-weight: bold;
  color: #374151;
  margin: 0;
  display: flex;
  align-items: center;
}

.keyboard-help-header i {
  color: #6366f1;
}

.close-help-btn {
  background: transparent;
  border: none;
  font-size: 24px;
  color: #6b7280;
  cursor: pointer;
  transition: all 0.3s ease;
  padding: 0.5rem;
  border-radius: 8px;
}

.close-help-btn:hover {
  background: #f3f4f6;
  color: #374151;
}

.keyboard-shortcuts {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.shortcut-item {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 0.75rem;
  background: #f9fafb;
  border-radius: 8px;
  transition: all 0.3s ease;
}

.shortcut-item:hover {
  background: #f3f4f6;
  transform: translateX(4px);
}

.shortcut-item kbd {
  background: white;
  border: 2px solid #e5e7eb;
  border-radius: 6px;
  padding: 0.25rem 0.75rem;
  font-family: monospace;
  font-weight: bold;
  color: #374151;
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  font-size: 0.875rem;
}

.shortcut-item span {
  flex: 1;
  color: #6b7280;
  font-size: 0.875rem;
}

.fade-enter-active, .fade-leave-active {
  transition: opacity 0.3s ease;
}

.fade-enter-from, .fade-leave-to {
  opacity: 0;
}


</style>
