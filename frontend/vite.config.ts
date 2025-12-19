import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import path from 'node:path'

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const enableDevtools = env.VITE_ENABLE_DEVTOOLS === 'true'
  const alias = enableDevtools
    ? [
        {
          find: '@',
          replacement: path.resolve(__dirname, './src')
        }
      ]
    : [
        {
          find: '@',
          replacement: path.resolve(__dirname, './src')
        },
        {
          find: '@vue/devtools-api',
          replacement: path.resolve(
            __dirname,
            'src/stubs/vue-devtools-api.ts',
          ),
        },
      ]

  return {
    plugins: [vue()],
    resolve: {
      alias,
    },
    server: {
      host: '0.0.0.0',
      port: 3000,
      proxy: {
        '/api': {
          target: 'http://localhost:8000',
          changeOrigin: true,
          secure: false
        }
      }
    }
  }
})
