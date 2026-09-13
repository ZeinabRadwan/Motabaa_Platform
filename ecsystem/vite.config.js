import VueI18nPlugin from '@intlify/unplugin-vue-i18n/vite'
import vue from '@vitejs/plugin-vue'
import vueJsx from '@vitejs/plugin-vue-jsx'
import { fileURLToPath } from 'node:url'
import AutoImport from 'unplugin-auto-import/vite'
import Components from 'unplugin-vue-components/vite'
import { defineConfig } from 'vite'
import Pages from 'vite-plugin-pages'
import Layouts from 'vite-plugin-vue-layouts'
import vuetify from 'vite-plugin-vuetify'

// @ts-expect-error Known error: https://github.com/sxzz/unplugin-vue-macros/issues/257#issuecomment-1410752890
import DefineOptions from 'unplugin-vue-define-options/vite'

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    vueJsx(),

    // https://github.com/vuetifyjs/vuetify-loader/tree/next/packages/vite-plugin
    vuetify({
      styles: {
        configFile: 'src/styles/variables/_vuetify.scss',
      },
    }),
    Pages({
      dirs: ['./src/pages'],
      importMode: 'async',
      // Globs are relative to src/pages. Do not use **/pages/** — that also
      // matches the pages directory itself and drops every production route.
      exclude: [
        'apps/**',
        'charts/**',
        'components/**',
        'extensions/**',
        'forms/**',
        'tables/**',
        'wizard-examples/**',
        'pages/**',
        'page/**',
        'access-control.vue',
        'dashboards/analyticsCharts.vue',
      ],
    }),
    Layouts({
      layoutsDirs: './src/layouts/',
    }),
    Components({
      dirs: ['src/@core/components', 'src/components'],
      dts: true,
    }),
    AutoImport({
      eslintrc: {
        enabled: true,
        filepath: './.eslintrc-auto-import.json',
      },
      imports: ['vue', 'vue-router', '@vueuse/core', '@vueuse/math', 'vue-i18n', 'pinia'],
      vueTemplate: true,
    }),
    VueI18nPlugin({
      runtimeOnly: true,
      compositionOnly: true,
      include: [
        fileURLToPath(new URL('./src/plugins/i18n/locales/ar.json', import.meta.url)),
        fileURLToPath(new URL('./src/plugins/i18n/locales/en.json', import.meta.url)),
      ],
    }),
    DefineOptions(),
  ],
  define: { 'process.env': {} },
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
      '@themeConfig': fileURLToPath(new URL('./themeConfig.js', import.meta.url)),
      '@core': fileURLToPath(new URL('./src/@core', import.meta.url)),
      '@layouts': fileURLToPath(new URL('./src/@layouts', import.meta.url)),
      '@images': fileURLToPath(new URL('./src/assets/images/', import.meta.url)),
      '@styles': fileURLToPath(new URL('./src/styles/', import.meta.url)),
      '@configured-variables': fileURLToPath(new URL('./src/styles/variables/_template.scss', import.meta.url)),
      '@axios': fileURLToPath(new URL('./src/plugins/axios', import.meta.url)),
      '@validators': fileURLToPath(new URL('./src/@core/utils/validators', import.meta.url)),
      'apexcharts': fileURLToPath(new URL('node_modules/apexcharts-clevision', import.meta.url)),
    },
  },
  build: {
    chunkSizeWarningLimit: 1000,
    rollupOptions: {
      output: {
        // Append a hash to the filename for cache busting
        entryFileNames: 'assets/[name]-[hash].js',
        chunkFileNames: 'assets/[name]-[hash].js',
        assetFileNames: 'assets/[name]-[hash][extname]',
        manualChunks(id) {
          if (!id.includes('node_modules'))
            return

          const normalizedId = id.replace(/\\/g, '/')

          // Named vendor chunks that are only used by async pages. Do not add
          // apexcharts or moment here — Vite would modulepreload them from the entry.
          if (normalizedId.includes('chart.js') || normalizedId.includes('vue-chartjs'))
            return 'chartjs'
          if (id.includes('vuetify'))
            return 'vuetify'
          if (id.includes('vue-i18n') || id.includes('@intlify'))
            return 'i18n'
          if (id.includes('vue-router') || id.includes('pinia') || id.includes('/vue/'))
            return 'vue-vendor'
        },
      },
    },
  },
  optimizeDeps: {
    exclude: ['vuetify'],
    entries: [
      './src/**/*.vue',
    ],
  },
})

