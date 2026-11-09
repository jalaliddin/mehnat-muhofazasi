<template>
  <div class="d-flex justify-center pa-4" style="min-height: 100vh; background: #f4f6f9">
    <div style="max-width: 560px; width: 100%; padding-top: 24px">
      <div class="d-flex align-center justify-center mb-6">
        <v-icon icon="mdi-shield-check" color="primary" size="28" class="mr-2" />
        <span class="text-h6 font-weight-bold" style="color: #0D1B2A">Mehnat Muhofazasi</span>
      </div>

      <div v-if="loading" class="text-center pa-10">
        <v-progress-circular indeterminate color="primary" />
      </div>

      <v-card v-else-if="notFound" rounded="xl" elevation="0" style="border: 1px solid #e8eaf0" class="pa-8 text-center">
        <v-icon icon="mdi-alert-circle" color="error" size="64" class="mb-4" />
        <div class="text-h6 font-weight-bold" style="color: #0D1B2A">Sertifikat topilmadi</div>
        <div class="text-body-2 mt-2" style="color: #777">QR kod yoki havola noto'g'ri bo'lishi mumkin.</div>
      </v-card>

      <v-alert v-else-if="loadError" type="error" variant="tonal" rounded="xl">{{ loadError }}</v-alert>

      <v-card v-else rounded="xl" elevation="0" style="border: 1px solid #e8eaf0" class="pa-6">
        <div class="text-center mb-5">
          <v-icon icon="mdi-check-decagram" color="success" size="56" />
          <div class="text-h6 font-weight-bold mt-2" style="color: #2e7d32">Sertifikat haqiqiy</div>
        </div>

        <div v-for="row in rows" :key="row.label" class="d-flex flex-wrap py-2" style="border-bottom: 1px solid #f0f2f5">
          <div style="color: #888; min-width: 170px" class="text-body-2">{{ row.label }}</div>
          <div class="text-body-1 font-weight-medium" style="color: #0D1B2A; word-break: break-word; flex: 1 1 200px">{{ row.value }}</div>
        </div>

        <v-btn
          v-if="certificate.status === 'signed'"
          :href="downloadUrl"
          target="_blank"
          color="primary"
          size="large"
          block
          rounded="lg"
          prepend-icon="mdi-download"
          class="mt-6 text-none wrap-btn"
        >
          Imzolangan sertifikatni yuklab olish
        </v-btn>
        <v-alert v-else type="info" variant="tonal" rounded="lg" class="mt-6">
          Mas'ul shaxslar imzosi kutilmoqda
        </v-alert>
      </v-card>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'

const route = useRoute()
const code = route.params.code

const publicApi = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api',
  headers: { Accept: 'application/json' },
})

const loading = ref(true)
const notFound = ref(false)
const loadError = ref('')
const certificate = ref(null)

const downloadUrl = computed(() => `${publicApi.defaults.baseURL}/public/certificates/${encodeURIComponent(code)}/download`)

function formatDate(d) {
  if (!d) return '—'
  const [y, m, day] = d.slice(0, 10).split('-')
  return `${day}.${m}.${y}`
}

const rows = computed(() => {
  const c = certificate.value
  if (!c) return []
  const period = c.course_start_date || c.course_end_date
    ? `${formatDate(c.course_start_date)} – ${formatDate(c.course_end_date)}`
    : '—'
  return [
    { label: 'F.I.O.', value: c.full_name || '—' },
    { label: 'Sertifikat №', value: c.number || '—' },
    { label: "Ro'yxatdan o'tish raqami", value: c.registration_number },
    { label: 'Dastur', value: c.program_name || '—' },
    { label: "Ta'lim muassasasi", value: c.institution_name || '—' },
    { label: 'Kurs muddati', value: period },
    { label: 'Soat hajmi', value: c.training_hours ? `${c.training_hours} soat` : '—' },
    { label: 'Berilgan sana', value: formatDate(c.issue_date) },
  ]
})

onMounted(async () => {
  try {
    const res = await publicApi.get(`/public/certificates/${encodeURIComponent(code)}`)
    certificate.value = res.data
  } catch (e) {
    if (e.response?.status === 404) notFound.value = true
    else loadError.value = "Ma'lumotni yuklab bo'lmadi. Keyinroq qayta urinib ko'ring."
  }
  loading.value = false
})
</script>

<style scoped>
/* Telefonda uzun matn kesilib qolmasligi uchun tugma matni qatorga o'raladi. */
.wrap-btn {
  height: auto !important;
  min-height: 52px;
  padding-top: 8px;
  padding-bottom: 8px;
  letter-spacing: normal;
}
.wrap-btn :deep(.v-btn__content) {
  white-space: normal;
  text-align: center;
}
</style>
