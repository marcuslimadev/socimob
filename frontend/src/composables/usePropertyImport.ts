// Composable para gerenciar importação de imóveis
import { ref } from 'vue'
import api from '@/services/api.ts'

export function usePropertyImport() {
  const file = ref(null)
  const loading = ref(false)
  const error = ref(null)
  const success = ref(null)
  const importProgress = ref(0)
  const importedCount = ref(0)
  const failedCount = ref(0)
  const errors = ref([])

  // Validar formato do arquivo
  const validateFile = (selectedFile) => {
    if (!selectedFile) {
      error.value = 'Selecione um arquivo'
      return false
    }

    const allowedTypes = ['text/csv', 'application/vnd.ms-excel', 'text/plain']
    const maxSize = 10 * 1024 * 1024 // 10MB

    if (!allowedTypes.includes(selectedFile.type) && !selectedFile.name.endsWith('.csv')) {
      error.value = 'Tipo de arquivo inválido. Use CSV'
      return false
    }

    if (selectedFile.size > maxSize) {
      error.value = 'Arquivo muito grande (máximo 10MB)'
      return false
    }

    return true
  }

  // Parse CSV
  const parseCSV = (csvContent) => {
    const lines = csvContent.trim().split('\n')
    if (lines.length < 2) {
      throw new Error('CSV deve conter header e pelo menos 1 linha de dados')
    }

    const headers = lines[0].split(',').map(h => h.trim().toLowerCase())
    const requiredFields = ['titulo', 'endereco', 'cidade', 'tipo']
    
    const missingFields = requiredFields.filter(field => !headers.includes(field))
    if (missingFields.length > 0) {
      throw new Error(`Campos obrigatórios ausentes: ${missingFields.join(', ')}`)
    }

    const properties = []
    for (let i = 1; i < lines.length; i++) {
      if (lines[i].trim() === '') continue

      const values = lines[i].split(',').map(v => v.trim())
      const property = {}

      headers.forEach((header, index) => {
        property[header] = values[index] || ''
      })

      // Validações básicas
      if (!property.titulo || !property.endereco) {
        errors.value.push(`Linha ${i + 1}: Titulo e Endereco são obrigatórios`)
        continue
      }

      properties.push(property)
    }

    return properties
  }

  // Upload e importação
  const importProperties = async (selectedFile) => {
    error.value = null
    success.value = null
    errors.value = []
    importedCount.value = 0
    failedCount.value = 0
    importProgress.value = 0

    if (!validateFile(selectedFile)) {
      return false
    }

    loading.value = true

    try {
      const formData = new FormData()
      formData.append('file', selectedFile)

      const response = await api.post('/api/properties/import', formData, {
        headers: {
          'Content-Type': 'multipart/form-data'
        },
        onUploadProgress: (progressEvent) => {
          importProgress.value = Math.round(
            (progressEvent.loaded * 100) / progressEvent.total
          )
        }
      })

      const result = response.data
      importedCount.value = result.imported || 0
      failedCount.value = result.failed || 0
      errors.value = result.errors || []

      success.value = `${importedCount.value} imóveis importados com sucesso`

      if (failedCount.value > 0) {
        error.value = `${failedCount.value} imóveis falharam na importação`
      }

      return true
    } catch (err) {
      error.value = err.response?.data?.message || 'Erro na importação de imóveis'
      failedCount.value = err.response?.data?.failed || 0
      errors.value = err.response?.data?.errors || []
      return false
    } finally {
      loading.value = false
      file.value = null
    }
  }

  // Download template
  const downloadTemplate = () => {
    const csvContent = 'titulo,endereco,cidade,estado,tipo,dormitorios,banheiros,valor,descricao\n' +
      'Casa Exemplo,Rua A 123,São Paulo,SP,casa,3,2,500000,Casa de 3 quartos\n' +
      'Apartamento,Av B 456,Rio de Janeiro,RJ,apartamento,2,1,400000,Apto no Centro'

    const blob = new Blob([csvContent], { type: 'text/csv' })
    const url = URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = 'template_imoveis.csv'
    link.click()
    URL.revokeObjectURL(url)
  }

  return {
    file,
    loading,
    error,
    success,
    importProgress,
    importedCount,
    failedCount,
    errors,
    validateFile,
    parseCSV,
    importProperties,
    downloadTemplate
  }
}
