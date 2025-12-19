<template>
  <div class="property-import">
    <div class="header">
      <h1>Importar Imóveis</h1>
      <p class="subtitle">Importe múltiplos imóveis via arquivo CSV</p>
    </div>

    <role-guard roles="admin" require-tenant>
      <div class="upload-area" 
        @dragover.prevent="isDragging = true"
        @dragleave="isDragging = false"
        @drop.prevent="handleDrop"
        :class="{ dragging: isDragging }">
        <div class="upload-icon">📤</div>
        <h3>Arraste o arquivo aqui ou clique para selecionar</h3>
        <p>Arquivo CSV com dados dos imóveis (máximo 10MB)</p>
        
        <input 
          type="file" 
          ref="fileInput"
          @change="handleFileSelect"
          accept=".csv"
          class="file-input"
        />
        <button @click="() => fileInput?.click()" class="btn-select">
          Selecionar Arquivo
        </button>
        <button @click="downloadTemplate" class="btn-template">
          📥 Baixar Template
        </button>
      </div>

      <div v-if="selectedFile" class="file-info">
        <p><strong>Arquivo:</strong> {{ selectedFile.name }}</p>
        <p><strong>Tamanho:</strong> {{ (selectedFile.size / 1024).toFixed(2) }} KB</p>
      </div>

      <div v-if="loading" class="progress">
        <div class="progress-bar" :style="{ width: importProgress + '%' }"></div>
        <p>{{ importProgress }}% - Enviando arquivo...</p>
      </div>

      <div v-if="success" class="alert success">
        ✅ {{ success }}
        <p v-if="importedCount > 0">{{ importedCount }} imóvéis importados com sucesso</p>
      </div>

      <div v-if="error" class="alert error">
        ❌ {{ error }}
        <p v-if="failedCount > 0">{{ failedCount }} imóvéis falharam</p>
      </div>

      <div v-if="errors.length > 0" class="errors-list">
        <h3>Erros na Importação:</h3>
        <ul>
          <li v-for="(err, idx) in errors.slice(0, 10)" :key="idx">{{ err }}</li>
          <li v-if="errors.length > 10">... e mais {{ errors.length - 10 }} erros</li>
        </ul>
      </div>

      <button 
        v-if="selectedFile && !loading"
        @click="importFile" 
        class="btn-import"
        :disabled="loading">
        🚀 Importar Imóveis
      </button>
    </role-guard>

    <!-- Template de Campos -->
    <div class="template-info">
      <h2>Formato do Arquivo CSV</h2>
      <div class="table-container">
        <table>
          <thead>
            <tr>
              <th>Campo</th>
              <th>Tipo</th>
              <th>Obrigatório</th>
              <th>Exemplo</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>titulo</td>
              <td>texto</td>
              <td>✓</td>
              <td>Casa Moderna</td>
            </tr>
            <tr>
              <td>endereco</td>
              <td>texto</td>
              <td>✓</td>
              <td>Rua A 123</td>
            </tr>
            <tr>
              <td>cidade</td>
              <td>texto</td>
              <td>✓</td>
              <td>São Paulo</td>
            </tr>
            <tr>
              <td>tipo</td>
              <td>texto</td>
              <td>✓</td>
              <td>casa, apartamento</td>
            </tr>
            <tr>
              <td>valor</td>
              <td>número</td>
              <td></td>
              <td>500000</td>
            </tr>
            <tr>
              <td>dormitorios</td>
              <td>número</td>
              <td></td>
              <td>3</td>
            </tr>
            <tr>
              <td>descricao</td>
              <td>texto</td>
              <td></td>
              <td>Descrição do imóvel</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { usePropertyImport } from '@/composables/usePropertyImport'
import RoleGuard from '@/components/RoleGuard.vue'

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

const fileInput = ref<HTMLInputElement | null>(null)
const selectedFile = ref<File | null>(null)
const isDragging = ref(false)

const handleFileSelect = (event: Event) => {
  const target = event.target as HTMLInputElement
  if (target.files) {
    selectedFile.value = target.files[0]
  }
}

const handleDrop = (event: DragEvent) => {
  isDragging.value = false
  if (event.dataTransfer?.files) {
    selectedFile.value = event.dataTransfer.files[0]
  }
}

const importFile = async () => {
  if (!selectedFile.value) return
  await importProperties(selectedFile.value)
}
</script>

<style scoped>
.property-import {
  max-width: 800px;
  margin: 0 auto;
  padding: 2rem;
}

.header {
  margin-bottom: 2rem;
  text-align: center;
}

.header h1 {
  font-size: 2rem;
  margin: 0 0 0.5rem 0;
  color: #333;
}

.subtitle {
  color: #666;
  margin: 0;
}

.upload-area {
  border: 2px dashed #2196f3;
  border-radius: 8px;
  padding: 2rem;
  text-align: center;
  background: #f5f9ff;
  transition: all 0.3s;
  margin-bottom: 2rem;
  cursor: pointer;
}

.upload-area.dragging {
  border-color: #1976d2;
  background: #e3f2fd;
}

.upload-icon {
  font-size: 3rem;
  margin-bottom: 1rem;
}

.upload-area h3 {
  margin: 0 0 0.5rem 0;
  color: #333;
}

.upload-area p {
  margin: 0 0 1.5rem 0;
  color: #666;
  font-size: 0.9rem;
}

.file-input {
  display: none;
}

.btn-select,
.btn-template,
.btn-import {
  padding: 0.75rem 1.5rem;
  margin: 0.5rem;
  border: none;
  border-radius: 4px;
  cursor: pointer;
  font-size: 0.9rem;
  font-weight: 500;
  transition: all 0.3s;
}

.btn-select {
  background: #2196f3;
  color: white;
}

.btn-select:hover {
  background: #1976d2;
}

.btn-template {
  background: #f0f0f0;
  color: #333;
}

.btn-template:hover {
  background: #e0e0e0;
}

.btn-import {
  background: #4caf50;
  color: white;
  margin-top: 1rem;
  width: 100%;
}

.btn-import:hover:not(:disabled) {
  background: #45a049;
}

.btn-import:disabled {
  background: #ccc;
  cursor: not-allowed;
}

.file-info {
  background: #f5f5f5;
  padding: 1rem;
  border-radius: 4px;
  margin-bottom: 1rem;
  font-size: 0.9rem;
}

.file-info p {
  margin: 0.25rem 0;
}

.progress {
  margin-bottom: 1rem;
}

.progress-bar {
  height: 8px;
  background: #2196f3;
  border-radius: 4px;
  margin-bottom: 0.5rem;
  transition: width 0.3s;
}

.progress p {
  margin: 0;
  font-size: 0.9rem;
  color: #666;
}

.alert {
  padding: 1rem;
  border-radius: 4px;
  margin-bottom: 1rem;
}

.alert.success {
  background: #c8e6c9;
  color: #2e7d32;
  border: 1px solid #4caf50;
}

.alert.error {
  background: #ffcdd2;
  color: #c62828;
  border: 1px solid #f44336;
}

.alert p {
  margin: 0.5rem 0 0 0;
  font-size: 0.9rem;
}

.errors-list {
  background: #fff3cd;
  border: 1px solid #ffc107;
  border-radius: 4px;
  padding: 1rem;
  margin-bottom: 1rem;
}

.errors-list h3 {
  margin-top: 0;
  color: #856404;
}

.errors-list ul {
  margin: 0;
  padding-left: 1.5rem;
  color: #856404;
  font-size: 0.9rem;
}

.errors-list li {
  margin: 0.25rem 0;
}

.template-info {
  background: white;
  border-radius: 8px;
  padding: 2rem;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  margin-top: 2rem;
}

.template-info h2 {
  margin-top: 0;
  color: #333;
}

.table-container {
  overflow-x: auto;
}

table {
  width: 100%;
  border-collapse: collapse;
}

table th,
table td {
  padding: 0.75rem;
  text-align: left;
  border-bottom: 1px solid #e0e0e0;
}

table th {
  background: #f5f5f5;
  font-weight: 600;
  color: #333;
}

table tr:hover {
  background: #f9f9f9;
}
</style>
