<template>
  <Teleport to="body">
    <Transition name="modal">
      <div
        v-if="isOpen"
        class="fixed inset-0 z-50 overflow-y-auto"
        @click.self="close"
      >
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-black bg-opacity-50 transition-opacity"></div>
        
        <!-- Modal -->
        <div class="flex min-h-screen items-center justify-center p-4">
          <div class="relative bg-white rounded-lg shadow-xl max-w-2xl w-full">
            <!-- Header -->
            <div class="bg-gradient-to-r from-green-600 to-green-700 px-6 py-4 flex items-center justify-between rounded-t-lg">
              <div class="flex items-center space-x-3">
                <div class="bg-white/20 p-2 rounded-full">
                  <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                  </svg>
                </div>
                <div>
                  <h2 class="text-xl font-bold text-white">Editar Lead</h2>
                  <p class="text-green-100 text-sm">Atualize as informações do lead</p>
                </div>
              </div>
              <button
                @click="close"
                class="text-white hover:bg-white/20 rounded-full p-2 transition"
              >
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>

            <!-- Form -->
            <form @submit.prevent="save" class="p-6">
              <div class="space-y-6">
                <!-- Nome e Email -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                      Nome <span class="text-red-500">*</span>
                    </label>
                    <input
                      v-model="form.nome"
                      type="text"
                      required
                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                      placeholder="Nome completo"
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                    <input
                      v-model="form.email"
                      type="email"
                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                      placeholder="email@exemplo.com"
                    />
                  </div>
                </div>

                <!-- Status -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">
                    Status <span class="text-red-500">*</span>
                  </label>
                  <select
                    v-model="form.status"
                    required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                  >
                    <option value="novo">🆕 Novo</option>
                    <option value="em_atendimento">⏳ Em Atendimento</option>
                    <option value="qualificado">✅ Qualificado</option>
                    <option value="convertido">🎉 Convertido</option>
                    <option value="perdido">❌ Perdido</option>
                    <option value="fechado">📦 Fechado</option>
                  </select>
                </div>

                <!-- Dados pessoais -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">CPF</label>
                    <input
                      v-model="form.cpf"
                      type="text"
                      maxlength="14"
                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                      placeholder="000.000.000-00"
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Renda mensal</label>
                    <div class="relative">
                      <span class="absolute left-3 top-2.5 text-gray-500">R$</span>
                      <input
                        v-model="form.renda_mensal"
                        type="text"
                        inputmode="numeric"
                        class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                        placeholder="0"
                        @blur="handleCurrencyBlur('renda_mensal')"
                      />
                    </div>
                  </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Estado civil</label>
                    <input
                      v-model="form.estado_civil"
                      type="text"
                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                      placeholder="Casado(a), solteiro(a)..."
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Composição familiar</label>
                    <input
                      v-model="form.composicao_familiar"
                      type="text"
                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                      placeholder="2 adultos, 1 criança..."
                    />
                  </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Profissão</label>
                    <input
                      v-model="form.profissao"
                      type="text"
                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                      placeholder="Cargo/área"
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Fonte de renda</label>
                    <input
                      v-model="form.fonte_renda"
                      type="text"
                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                      placeholder="CLT, PJ, autônomo..."
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Prazo de compra</label>
                    <input
                      v-model="form.prazo_compra"
                      type="text"
                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                      placeholder="30 dias, 6 meses..."
                    />
                  </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Objetivo da compra</label>
                    <input
                      v-model="form.objetivo_compra"
                      type="text"
                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                      placeholder="Morar, investir..."
                    />
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Situação de financiamento</label>
                    <input
                      v-model="form.financiamento_status"
                      type="text"
                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                      placeholder="Aprovado, em análise..."
                    />
                  </div>
                </div>

                <!-- Orçamento -->
                <div class="grid grid-cols-2 gap-4">
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Orçamento Mínimo</label>
                    <div class="relative">
                      <span class="absolute left-3 top-2.5 text-gray-500">R$</span>
                      <input
                        v-model="form.budget_min"
                        type="text"
                        inputmode="numeric"
                        class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                        placeholder="0"
                        @blur="handleCurrencyBlur('budget_min')"
                      />
                    </div>
                  </div>
                  <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Orçamento Máximo</label>
                    <div class="relative">
                      <span class="absolute left-3 top-2.5 text-gray-500">R$</span>
                      <input
                        v-model="form.budget_max"
                        type="text"
                        inputmode="numeric"
                        class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                        placeholder="0"
                        @blur="handleCurrencyBlur('budget_max')"
                      />
                    </div>
                  </div>
                </div>

                <!-- Localização -->
                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Localização</label>
                  <input
                    v-model="form.localizacao"
                    type="text"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                    placeholder="Cidade, Estado"
                  />
                </div>

                <!-- Preferências do Imóvel -->
                <div class="bg-gray-50 p-4 rounded-lg">
                  <h3 class="font-semibold text-gray-900 mb-4">Preferências do Imóvel</h3>
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de imóvel</label>
                      <input
                        v-model="form.preferencia_tipo_imovel"
                        type="text"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                        placeholder="Casa, apartamento..."
                      />
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-2">Bairro preferido</label>
                      <input
                        v-model="form.preferencia_bairro"
                        type="text"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                        placeholder="Centro, Savassi..."
                      />
                    </div>
                  </div>
                  <div class="grid grid-cols-3 gap-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-2">🛏️ Quartos</label>
                      <input
                        v-model.number="form.quartos"
                        type="number"
                        min="0"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                        placeholder="0"
                      />
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-2">🚿 Suítes</label>
                      <input
                        v-model.number="form.suites"
                        type="number"
                        min="0"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                        placeholder="0"
                      />
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-2">🚗 Garagem</label>
                      <input
                        v-model.number="form.garagem"
                        type="number"
                        min="0"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                        placeholder="0"
                      />
                    </div>
                  </div>
                  <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Características desejadas</label>
                    <textarea
                      v-model="form.caracteristicas_desejadas"
                      rows="2"
                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                      placeholder="Piscina, área gourmet, pet friendly..."
                    ></textarea>
                  </div>
                  <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-2">Preferências de lazer</label>
                      <textarea
                        v-model="form.preferencia_lazer"
                        rows="2"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                        placeholder="Piscina, quadra, academia..."
                      ></textarea>
                    </div>
                    <div>
                      <label class="block text-sm font-medium text-gray-700 mb-2">Preferências de segurança</label>
                      <textarea
                        v-model="form.preferencia_seguranca"
                        rows="2"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                        placeholder="Portaria 24h, monitoramento..."
                      ></textarea>
                    </div>
                  </div>
                </div>

                <div>
                  <label class="block text-sm font-medium text-gray-700 mb-2">Observações gerais</label>
                  <textarea
                    v-model="form.observacoes_cliente"
                    rows="3"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                    placeholder="Notas livres para o corretor"
                  ></textarea>
                </div>
              </div>

              <!-- Footer -->
              <div class="mt-6 flex justify-end space-x-3">
                <button
                  type="button"
                  @click="close"
                  :disabled="saving"
                  class="px-6 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition font-medium disabled:opacity-50"
                >
                  Cancelar
                </button>
                <button
                  type="submit"
                  :disabled="saving"
                  class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition font-medium disabled:opacity-50 flex items-center"
                >
                  <svg v-if="saving" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                  </svg>
                  {{ saving ? 'Salvando...' : 'Salvar Alterações' }}
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useLeadsStore } from '../stores/leads'

const props = defineProps({
  isOpen: Boolean,
  lead: Object
})

const emit = defineEmits(['close', 'saved'])

const leadsStore = useLeadsStore()
const saving = ref(false)

const form = ref({
  nome: '',
  email: '',
  status: 'novo',
  cpf: '',
  renda_mensal: '',
  estado_civil: '',
  composicao_familiar: '',
  profissao: '',
  fonte_renda: '',
  prazo_compra: '',
  objetivo_compra: '',
  financiamento_status: '',
  budget_min: '',
  budget_max: '',
  localizacao: '',
  preferencia_tipo_imovel: '',
  preferencia_bairro: '',
  quartos: null,
  suites: null,
  garagem: null,
  caracteristicas_desejadas: '',
  preferencia_lazer: '',
  preferencia_seguranca: '',
  observacoes_cliente: ''
})

const parseCurrencyToNumber = (value) => {
  if (value === null || value === undefined) return null

  const stringValue = String(value).trim()
  if (!stringValue) return null

  const normalized = stringValue
    .replace(/R\$/gi, '')
    .replace(/\s/g, '')
    .replace(/\./g, '')
    .replace(',', '.')

  const numberValue = Number(normalized)
  return Number.isNaN(numberValue) ? null : numberValue
}

const currencyFormatter = new Intl.NumberFormat('pt-BR', {
  minimumFractionDigits: 0,
  maximumFractionDigits: 0
})

const formatCurrencyInput = (value) => {
  const numericValue = parseCurrencyToNumber(value)
  if (numericValue === null) return ''
  return currencyFormatter.format(numericValue)
}

const handleCurrencyBlur = (field) => {
  form.value[field] = formatCurrencyInput(form.value[field])
}

watch(() => props.lead, (newLead) => {
  if (newLead) {
    form.value = {
      nome: newLead.nome || '',
      email: newLead.email || '',
      status: newLead.status || 'novo',
      cpf: newLead.cpf || '',
      renda_mensal: formatCurrencyInput(newLead.renda_mensal),
      estado_civil: newLead.estado_civil || '',
      composicao_familiar: newLead.composicao_familiar || '',
      profissao: newLead.profissao || '',
      fonte_renda: newLead.fonte_renda || '',
      prazo_compra: newLead.prazo_compra || '',
      objetivo_compra: newLead.objetivo_compra || '',
      financiamento_status: newLead.financiamento_status || '',
      budget_min: formatCurrencyInput(newLead.budget_min),
      budget_max: formatCurrencyInput(newLead.budget_max),
      localizacao: newLead.localizacao || '',
      preferencia_tipo_imovel: newLead.preferencia_tipo_imovel || '',
      preferencia_bairro: newLead.preferencia_bairro || '',
      quartos: newLead.quartos || null,
      suites: newLead.suites || null,
      garagem: newLead.garagem || null,
      caracteristicas_desejadas: newLead.caracteristicas_desejadas || '',
      preferencia_lazer: newLead.preferencia_lazer || '',
      preferencia_seguranca: newLead.preferencia_seguranca || '',
      observacoes_cliente: newLead.observacoes_cliente || ''
    }
  }
}, { immediate: true })

const save = async () => {
  if (!props.lead?.id) return

  saving.value = true
  try {
    const payload = {
      ...form.value,
      renda_mensal: parseCurrencyToNumber(form.value.renda_mensal),
      budget_min: parseCurrencyToNumber(form.value.budget_min),
      budget_max: parseCurrencyToNumber(form.value.budget_max)
    }

    const success = await leadsStore.atualizarLead(props.lead.id, payload)
    
    if (success) {
      emit('saved')
      emit('close')
    } else {
      alert('Erro ao salvar as alterações')
    }
  } catch (error) {
    console.error('Erro ao salvar lead:', error)
    alert('Erro ao salvar as alterações')
  } finally {
    saving.value = false
  }
}

const close = () => {
  if (!saving.value) {
    emit('close')
  }
}
</script>

<style scoped>
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.3s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}
</style>
