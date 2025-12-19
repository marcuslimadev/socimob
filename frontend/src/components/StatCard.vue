<template>
  <div class="bg-white rounded-lg shadow p-6">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm text-gray-600 mb-1">{{ title }}</p>
        <p class="text-3xl font-bold" :class="colorClass">{{ value }}</p>
      </div>
      <div class="p-3 rounded-full" :class="bgColorClass">
        <component :is="iconComponent" class="h-8 w-8" :class="colorClass" />
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { 
  UsersIcon, 
  ChatBubbleBottomCenterTextIcon, 
  CalendarIcon, 
  ChartBarIcon 
} from '@heroicons/vue/24/outline'

const props = defineProps({
  title: String,
  value: [String, Number],
  icon: String,
  color: {
    type: String,
    default: 'blue'
  }
})

const iconComponent = computed(() => {
  const icons = {
    'users': UsersIcon,
    'chat': ChatBubbleBottomCenterTextIcon,
    'calendar': CalendarIcon,
    'chart': ChartBarIcon
  }
  return icons[props.icon] || UsersIcon
})

const colorClass = computed(() => {
  const colors = {
    'blue': 'text-blue-600',
    'green': 'text-green-600',
    'purple': 'text-purple-600',
    'orange': 'text-orange-600'
  }
  return colors[props.color] || 'text-blue-600'
})

const bgColorClass = computed(() => {
  const colors = {
    'blue': 'bg-blue-100',
    'green': 'bg-green-100',
    'purple': 'bg-purple-100',
    'orange': 'bg-orange-100'
  }
  return colors[props.color] || 'bg-blue-100'
})
</script>
