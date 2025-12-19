<template>
  <role-guard roles="admin">
    <div class="min-h-screen bg-gradient-to-br from-slate-50 to-slate-100">
      <navbar />

      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <!-- Header -->
        <div class="bg-gradient-to-r from-blue-600 to-blue-800 rounded-2xl p-8 text-white shadow-lg mb-8">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm uppercase tracking-widest text-blue-100 mb-2">📥 Importação</p>
              <h1 class="text-4xl font-bold mb-2">Importar Imóveis</h1>
              <p class="text-blue-100 max-w-2xl">
                Importe múltiplos imóveis através de um arquivo CSV. Suporta até 10MB.
              </p>
            </div>
            <div class="text-6xl">📋</div>
          </div>
        </div>

        <!-- Alerts -->
        <div v-if="success" class="mb-6 p-4 bg-green-100 border-l-4 border-green-500 text-green-700 rounded-lg">
          <p class="font-bold">✅ Sucesso!</p>
          <p>{{ success }}</p>
        </div>

        <div v-if="error" class="mb-6 p-4 bg-red-100 border-l-4 border-red-500 text-red-700 rounded-lg">
          <p class="font-bold">❌ Erro</p>
          <p>{{ error }}</p>
        </div>

        <!-- Main Content -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
          
          <!-- Upload Section -->
          <div class="lg:col-span-2">
            <div class="bg-white rounded-2xl shadow-lg p-8">
              <h2 class="text-2xl font-bold text-gray-900 mb-6">Enviar Arquivo</h2>

              <!-- Drag and Drop -->
              <div
                @dragover.prevent="isDragging = true"
                @dragleave.prevent="isDragging = false"
                @drop.prevent="handleFileDrop"
                @click="$refs.fileInput?.click()"
                :class="[
                  'border-2 border-dashed rounded-xl p-12 text-center cursor-pointer transition-all',
                  isDragging ? 'border-blue-500 bg-blue-50' : 'border-gray-300 hover:border-blue-400 hover:bg-gray-50'
                ]"
              >
                <template v-if="!selectedFile">
                  <div class="text-5xl mb-4">📁</div>
                  <p class="text-xl text-gray-700 font-semibold mb-2">Arraste seu CSV aqui</p>
                  <p class="text-gray-500">ou clique para selecionar um arquivo</p>
                  <p class="text-sm text-gray-400 mt-2">Máximo 10MB • Formato: CSV</p>
                </template>

                <template v-else>
                  <div class="text-5xl mb-4">✅</div>
                  <p class="text-xl text-green-700 font-semibold mb-2">{{ selectedFile?.name }}</p>
                  <p class="text-gray-500">{{ formatFileSize(selectedFile?.size) }}</p>
                </template>
              </div>

              <input
                ref="fileInput"
                type="file"
                accept=".csv"
                @change="handleFileSelect"
                class="hidden"
              />

              <!-- File Actions -->
              <div class="flex gap-4 mt-6">
                <button
                  v-if="selectedFile"
                  @click="selectedFile = null"
                  class="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 font-bold py-3 px-4 rounded-lg transition"
                >
                  🔄 Trocar Arquivo
                </button>

                <button
                  v-if="selectedFile"
                  @click="handleImport"
                  :disabled="loading"
                  class="flex-1 bg-green-600 hover:bg-green-700 disabled:bg-gray-400 text-white font-bold py-3 px-4 rounded-lg transition disabled:cursor-not-allowed"
                >
                  <span v-if="loading" class="flex items-center justify-center gap-2">
                    <span class="animate-spin">⏳</span> Importando...
                  </span>
                  <span v-else>✅ Importar Agora</span>
                </button>

                <button
                  v-else
                  @click="$refs.fileInput?.click()"
                  class="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg transition"
                >
                  📂 Selecionar Arquivo
                </button>
              </div>

              <!-- Progress Bar -->
              <div v-if="loading && importProgress > 0" class="mt-6">
                <div class="flex justify-between items-center mb-2">
                  <span class="text-sm font-semibold text-gray-700">Progresso do Upload</span>
                  <span class="text-sm font-bold text-blue-600">{{ importProgress }}%</span>
                </div>
                <div class="w-full bg-gray-200 rounded-full h-3">
                  <div
                    :style="{ width: importProgress + '%' }"
                    class="bg-gradient-to-r from-blue-500 to-blue-600 h-3 rounded-full transition-all duration-300"
                  ></div>
                </div>
              </div>

              <!-- Results Card -->
              <div v-if="importedCount > 0 || failedCount > 0" class="mt-8 p-6 bg-gradient-to-r from-blue-50 to-indigo-50 rounded-xl border border-blue-200">
                <h3 class="font-bold text-gray-900 mb-4 text-lg">📊 Resultado da Importação</h3>
                <div class="grid grid-cols-2 gap-4">
                  <div class="bg-white p-4 rounded-lg border-l-4 border-green-500">
                    <p class="text-sm text-gray-600">✅ Importados com Sucesso</p>
                    <p class="text-3xl font-bold text-green-600">{{ importedCount }}</p>
                  </div>
                  <div v-if="failedCount > 0" class="bg-white p-4 rounded-lg border-l-4 border-red-500">
                    <p class="text-sm text-gray-600">❌ Falhados</p>
                    <p class="text-3xl font-bold text-red-600">{{ failedCount }}</p>
                  </div>
                </div>
              </div>

              <!-- Error List -->
              <div v-if="errors.length > 0" class="mt-6">
                <h3 class="font-bold text-red-900 mb-3 text-lg">⚠️ Erros Encontrados</h3>
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 max-h-48 overflow-y-auto">
                  <ul class="text-sm text-red-700 space-y-2">
                    <li v-for="(err, idx) in errors.slice(0, 15)" :key="idx" class="flex gap-2">
                      <span class="font-bold">•</span>
                      <span>{{ err }}</span>
                    </li>
                    <li v-if="errors.length > 15" class="text-gray-600 italic pt-2 border-t border-red-200">
                      ... e {{ errors.length - 15 }} outros erros
                    </li>
                  </ul>
                </div>
              </div>
            </div>
          </div>

          <!-- Info Section -->
          <div class="space-y-6">
            <!-- Template Card -->
            <div class="bg-white rounded-2xl shadow-lg p-6 border-t-4 border-blue-600">
              <h3 class="text-xl font-bold text-gray-900 mb-3">📋 Template</h3>
              <p class="text-sm text-gray-600 mb-4">
                Faça download de um template para entender o formato correto.
              </p>
              <button
                @click="downloadTemplate"
                class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg transition"
              >
                ⬇️ Download Template
              </button>
            </div>

            <!-- Requirements Card -->
            <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-2xl border-2 border-blue-200 p-6">
              <h3 class="font-bold text-blue-900 mb-4 text-lg">✓ Campos Obrigatórios</h3>
              <ul class="text-sm text-blue-800 space-y-2 mb-4">
                <li class="flex gap-2"><span>📌</span> <strong>titulo</strong> - Título do imóvel</li>
                <li class="flex gap-2"><span>📌</span> <strong>endereco</strong> - Endereço completo</li>
                <li class="flex gap-2"><span>📌</span> <strong>cidade</strong> - Cidade</li>
                <li class="flex gap-2"><span>📌</span> <strong>tipo</strong> - casa, apt, etc</li>
              </ul>

              <h3 class="font-bold text-blue-900 mb-4 text-lg">📝 Campos Opcionais</h3>
              <ul class="text-sm text-blue-800 space-y-2">
                <li class="flex gap-2"><span>▫️</span> estado - UF (SP, RJ, MG)</li>
                <li class="flex gap-2"><span>▫️</span> dormitorios - Número</li>
                <li class="flex gap-2"><span>▫️</span> banheiros - Número</li>
                <li class="flex gap-2"><span>▫️</span> valor - Valor em reais</li>
                <li class="flex gap-2"><span>▫️</span> descricao - Descrição</li>
              </ul>
            </div>

            <!-- Tips Card -->
            <div class="bg-amber-50 rounded-2xl border-2 border-amber-300 p-6">
              <h3 class="font-bold text-amber-900 mb-3">💡 Dicas</h3>
              <ul class="text-sm text-amber-800 space-y-2">
                <li>• Máximo 10MB por arquivo</li>
                <li>• Campos separados por vírgula</li>
                <li>• Sem linhas em branco</li>
                <li>• Encoding UTF-8</li>
                <li>• Use values entre aspas</li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </role-guard>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import Navbar from '@/components/Navbar.vue'
import RoleGuard from '@/components/RoleGuard.vue'
import { usePropertyImport } from '@/composables/usePropertyImport'

const fileInput = ref()
const selectedFile = ref(null)
const isDragging = ref(false)

const {
  loading,
  error,
  success,
  importProgress,
  importedCount,
  failedCount,
  errors,
  importProperties,
  downloadTemplate
} = usePropertyImport()

const handleFileSelect = (event) => {
  const files = event.target.files
  if (files?.length > 0) {
    selectedFile.value = files[0]
  }
}

const handleFileDrop = (event) => {
  isDragging.value = false
  const files = event.dataTransfer.files
  if (files?.length > 0) {
    selectedFile.value = files[0]
  }
}

const handleImport = async () => {
  if (!selectedFile.value) return
  const success = await importProperties(selectedFile.value)
  if (success) {
    selectedFile.value = null
  }
}

const formatFileSize = (bytes) => {
  if (!bytes) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i]
}
</script>
