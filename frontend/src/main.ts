import { createApp } from 'vue'
import { createPinia } from 'pinia'
import router from './router'
import './style.css'
import App from './App.vue'

// Importar componentes globais
import RoleGuard from './components/RoleGuard.vue'
import PropertyImportEnhanced from './components/PropertyImportEnhanced.vue'

const app = createApp(App)
const pinia = createPinia()

app.use(pinia)
app.use(router)

// Registrar componentes globais
app.component('RoleGuard', RoleGuard)
app.component('PropertyImportEnhanced', PropertyImportEnhanced)

app.mount('#app')
