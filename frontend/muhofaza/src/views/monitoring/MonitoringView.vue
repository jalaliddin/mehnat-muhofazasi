<template>
  <div>
    <div class="d-flex align-center flex-wrap mb-6" style="gap: 8px">
      <div class="text-h5 font-weight-bold" style="color: #0D1B2A">Monitoring</div>
      <v-spacer />
      <v-chip v-if="tab === 'active'" size="small" color="success" variant="tonal" label>
        <v-icon start icon="mdi-circle" size="10" class="live-dot" />Jonli · har 5 soniyada yangilanadi
      </v-chip>
    </div>

    <v-card rounded="xl" elevation="0" style="border: 1px solid #e8eaf0" class="mb-4">
      <v-tabs v-model="tab" color="primary">
        <v-tab value="active"><v-icon start icon="mdi-monitor-eye" />Hozir yechilmoqda ({{ active.length }})</v-tab>
        <v-tab value="finished"><v-icon start icon="mdi-check-all" />Yakunlangan</v-tab>
      </v-tabs>
    </v-card>

    <!-- Hozir yechilmoqda -->
    <v-card v-if="tab === 'active'" rounded="xl" elevation="0" style="border: 1px solid #e8eaf0">
      <v-alert v-if="activeError" type="warning" variant="tonal" density="compact" class="ma-4">Yangilab bo'lmadi, qayta urinilmoqda</v-alert>
      <v-data-table
        :headers="activeHeaders"
        :items="active"
        :loading="loadingActive && !active.length"
        hover
        density="comfortable"
        no-data-text="Hozir test yechayotgan xodim yo'q"
        @click:row="(_, { item }) => drawer.open(item.id)"
        style="cursor: pointer"
      >
        <template #item.employee="{ item }">
          <div class="font-weight-medium">{{ item.employee.full_name }}</div>
          <div class="text-caption" style="color: #888">{{ item.organization?.name }}</div>
        </template>
        <template #item.periodic_exam="{ item }">{{ item.periodic_exam.title }}</template>
        <template #item.progress="{ item }">
          <div style="min-width: 140px">
            <div class="text-caption mb-1">{{ item.answered }}/{{ item.total }}</div>
            <v-progress-linear :model-value="item.total ? (item.answered / item.total) * 100 : 0" color="primary" rounded height="6" />
          </div>
        </template>
        <template #item.remaining="{ item }">
          <span :style="{ color: remaining(item) < 60 ? '#c62828' : '#0D1B2A', fontWeight: 600 }">{{ clock(remaining(item)) }}</span>
        </template>
        <template #item.last_activity_at="{ item }">
          <span style="color: #666">{{ ago(item.last_activity_at) }}</span>
        </template>
      </v-data-table>
    </v-card>

    <!-- Yakunlangan -->
    <template v-else>
      <v-card rounded="xl" elevation="0" style="border: 1px solid #e8eaf0" class="mb-4 pa-4">
        <v-row dense>
          <v-col cols="12" md="4">
            <v-select v-model="filters.periodic_exam_id" :items="exams" item-title="title" item-value="id" label="Imtihon"
              variant="outlined" density="compact" rounded="lg" clearable hide-details />
          </v-col>
          <v-col cols="6" md="2">
            <v-text-field v-model="filters.date_from" type="date" label="Sanadan" variant="outlined" density="compact" rounded="lg" hide-details />
          </v-col>
          <v-col cols="6" md="2">
            <v-text-field v-model="filters.date_to" type="date" label="Sanagacha" variant="outlined" density="compact" rounded="lg" hide-details />
          </v-col>
          <v-col cols="12" md="4">
            <v-text-field v-model="search" placeholder="F.I.O. bo'yicha" prepend-inner-icon="mdi-magnify"
              variant="outlined" density="compact" rounded="lg" clearable hide-details />
          </v-col>
        </v-row>
      </v-card>

      <v-card rounded="xl" elevation="0" style="border: 1px solid #e8eaf0">
        <v-data-table-server
          v-model:page="page"
          v-model:items-per-page="perPage"
          :headers="finishedHeaders"
          :items="finished"
          :items-length="finishedTotal"
          :loading="loadingFinished"
          hover
          density="comfortable"
          no-data-text="Yakunlangan test topilmadi"
          @update:options="fetchFinished"
          @click:row="(_, { item }) => drawer.open(item.id)"
          style="cursor: pointer"
        >
          <template #item.employee="{ item }">
            <div class="font-weight-medium">{{ item.employee.full_name }}</div>
            <div class="text-caption" style="color: #888">{{ item.organization?.name }}</div>
          </template>
          <template #item.periodic_exam="{ item }">{{ item.periodic_exam.title }}</template>
          <template #item.finished_at="{ item }">{{ dateTime(item.finished_at) }}</template>
          <template #item.score_percent="{ item }">{{ item.score_percent ?? '—' }}%</template>
          <template #item.grade="{ item }"><GradeBadge v-if="item.grade" :grade="item.grade" /></template>
        </v-data-table-server>
      </v-card>
    </template>

    <SessionDetailDrawer ref="drawer" />
  </div>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue'
import api from '../../services/api'
import GradeBadge from '../../components/common/GradeBadge.vue'
import SessionDetailDrawer from '../../components/monitoring/SessionDetailDrawer.vue'

const POLL_MS = 5000

const tab = ref('active')
const drawer = ref(null)

// --- Hozir yechilmoqda ---
const active = ref([])
const loadingActive = ref(false)
const activeError = ref(false)
const now = ref(Date.now())
let fetchedAt = Date.now()
let pollTimer = null
let clockTimer = null

const activeHeaders = [
  { title: 'Xodim', key: 'employee', sortable: false },
  { title: 'Imtihon', key: 'periodic_exam', sortable: false },
  { title: 'Jarayon', key: 'progress', sortable: false },
  { title: 'Qolgan vaqt', key: 'remaining', sortable: false },
  { title: 'Oxirgi faollik', key: 'last_activity_at', sortable: false },
]

async function fetchActive() {
  loadingActive.value = true
  try {
    active.value = (await api.get('/monitoring/active')).data
    fetchedAt = Date.now()
    activeError.value = false
  } catch (e) {
    activeError.value = true
  }
  loadingActive.value = false
}

// Qolgan vaqt server qiymatidan har soniya mahalliy kamayadi (har pollingda qayta sinxronlanadi).
function remaining(item) {
  return Math.max(0, item.remaining_seconds - Math.floor((now.value - fetchedAt) / 1000))
}

function clock(seconds) {
  return `${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`
}

function ago(iso) {
  if (!iso) return '—'
  const seconds = Math.max(0, Math.round((now.value - new Date(iso).getTime()) / 1000))
  if (seconds < 60) return `${seconds} soniya oldin`
  return `${Math.floor(seconds / 60)} daqiqa oldin`
}

function startActivePolling() {
  stopActivePolling()
  fetchActive()
  pollTimer = setInterval(fetchActive, POLL_MS)
}

function stopActivePolling() {
  clearInterval(pollTimer)
  pollTimer = null
}

// --- Yakunlangan ---
const finished = ref([])
const finishedTotal = ref(0)
const loadingFinished = ref(false)
const page = ref(1)
const perPage = ref(25)
const exams = ref([])
const search = ref('')
const filters = ref({ periodic_exam_id: null, date_from: '', date_to: '' })

const finishedHeaders = [
  { title: 'Xodim', key: 'employee', sortable: false },
  { title: 'Imtihon', key: 'periodic_exam', sortable: false },
  { title: 'Tugagan', key: 'finished_at', sortable: false },
  { title: 'Ball', key: 'score_percent', sortable: false },
  { title: 'Baho', key: 'grade', sortable: false },
]

function dateTime(iso) {
  return iso ? new Date(iso).toLocaleString('uz-UZ', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '—'
}

async function fetchFinished() {
  loadingFinished.value = true
  try {
    const params = { page: page.value, per_page: perPage.value }
    for (const [key, value] of Object.entries(filters.value)) if (value) params[key] = value
    if (search.value) params.search = search.value
    const res = await api.get('/monitoring/finished', { params })
    finished.value = res.data.data
    finishedTotal.value = res.data.total
  } catch (e) {
    finished.value = []
  }
  loadingFinished.value = false
}

function refetchFinished() {
  if (page.value !== 1) page.value = 1
  else fetchFinished()
}

watch(filters, refetchFinished, { deep: true })
let searchTimer = null
watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(refetchFinished, 300)
})

watch(tab, value => {
  if (value === 'active') startActivePolling()
  else stopActivePolling()
})

onMounted(async () => {
  startActivePolling()
  clockTimer = setInterval(() => { now.value = Date.now() }, 1000)
  try {
    exams.value = (await api.get('/monitoring/exams')).data
  } catch (e) {
    exams.value = []
  }
})

onUnmounted(() => {
  stopActivePolling()
  clearInterval(clockTimer)
  clearTimeout(searchTimer)
})
</script>

<style scoped>
.live-dot {
  animation: pulse 1.4s infinite;
}
@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.3; }
}
</style>
