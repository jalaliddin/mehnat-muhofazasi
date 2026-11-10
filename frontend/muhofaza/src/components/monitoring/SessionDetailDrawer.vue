<template>
  <v-navigation-drawer
    v-model="open"
    location="right"
    temporary
    :width="mobile ? 360 : 540"
    class="session-drawer"
  >
    <div class="pa-4 d-flex align-center" style="border-bottom: 1px solid #eef0f4">
      <div class="flex-grow-1 overflow-hidden">
        <div class="text-subtitle-1 font-weight-bold text-truncate" style="color: #0D1B2A">{{ detail?.employee?.full_name || '…' }}</div>
        <div class="text-caption text-truncate" style="color: #777">{{ detail?.periodic_exam?.title }} · {{ detail?.organization?.name }}</div>
      </div>
      <v-btn icon="mdi-close" variant="text" size="small" @click="open = false" />
    </div>

    <div v-if="loading && !detail" class="text-center pa-8"><v-progress-circular indeterminate color="primary" /></div>

    <div v-else-if="detail" class="pa-4">
      <div class="d-flex flex-wrap align-center mb-4" style="gap: 8px">
        <v-chip size="small" label :color="detail.status === 'in_progress' ? 'primary' : 'grey'">
          <v-icon v-if="detail.status === 'in_progress'" start icon="mdi-circle" size="10" class="live-dot" />
          {{ statusText(detail.status) }}
        </v-chip>
        <v-chip size="small" label variant="tonal">{{ detail.summary.answered }}/{{ detail.summary.total }} javob</v-chip>
        <v-chip size="small" label variant="tonal" color="success">{{ detail.summary.correct }} to'g'ri</v-chip>
        <v-chip size="small" label variant="tonal">{{ detail.summary.score_percent ?? 0 }}%</v-chip>
        <GradeBadge v-if="detail.grade" :grade="detail.grade" />
        <v-chip v-if="detail.status === 'in_progress'" size="small" label :color="remaining < 60 ? 'error' : 'default'" variant="tonal">
          <v-icon start icon="mdi-clock-outline" size="14" />{{ clock(remaining) }}
        </v-chip>
      </div>

      <v-alert v-if="refreshError" type="warning" variant="tonal" density="compact" class="mb-3">Yangilab bo'lmadi, qayta urinilmoqda</v-alert>

      <div v-for="q in detail.questions" :key="q.order" class="question-card mb-3">
        <div class="d-flex align-start mb-2">
          <div class="text-body-2 font-weight-bold flex-grow-1" style="color: #0D1B2A">{{ q.order }}. {{ q.question_text }}</div>
          <v-icon v-if="q.is_correct === true" icon="mdi-check-circle" color="success" size="20" />
          <v-icon v-else-if="q.is_correct === false" icon="mdi-close-circle" color="error" size="20" />
        </div>
        <div v-for="opt in q.options" :key="opt.key" class="option" :class="optionClass(q, opt)">
          <span class="flex-grow-1">{{ opt.text }}</span>
          <v-icon v-if="opt.key === q.correct_option" icon="mdi-check" size="16" color="success" />
        </div>
        <div class="text-caption mt-1" style="color: #999">
          {{ q.selected_option ? `Javob vaqti: ${time(q.answered_at)}` : 'Javob berilmagan' }}
        </div>
      </div>
    </div>
  </v-navigation-drawer>
</template>

<script setup>
import { ref, computed, watch, onUnmounted } from 'vue'
import { useDisplay } from 'vuetify'
import api from '../../services/api'
import GradeBadge from '../common/GradeBadge.vue'

const POLL_MS = 3000

const { mobile } = useDisplay()
const open = ref(false)
const loading = ref(false)
const refreshError = ref(false)
const detail = ref(null)
const sessionId = ref(null)
const deadlineMs = ref(0)
const now = ref(Date.now())
let pollTimer = null
let clockTimer = null
// Har ochilish o'z "avlodi"ga ega: eski so'rov javobi yoki yopilgandan keyin kelgan javob taymer ishga tushirmaydi.
let generation = 0

const remaining = computed(() => Math.max(0, Math.round((deadlineMs.value - now.value) / 1000)))

function statusText(status) {
  return { in_progress: 'Jonli', completed: 'Yakunlangan', expired: 'Vaqti tugagan', pending: 'Boshlanmagan' }[status] || status
}

function clock(seconds) {
  return `${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`
}

function time(iso) {
  return iso ? new Date(iso).toLocaleTimeString('uz-UZ', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) : ''
}

function optionClass(q, opt) {
  if (opt.key !== q.selected_option) return ''
  return q.is_correct ? 'chosen correct' : 'chosen wrong'
}

async function load(gen) {
  if (!sessionId.value) return
  try {
    const res = await api.get(`/monitoring/sessions/${sessionId.value}`)
    if (gen !== generation) return
    detail.value = res.data
    refreshError.value = false
    if (res.data.remaining_seconds !== null) deadlineMs.value = Date.now() + res.data.remaining_seconds * 1000
    if (res.data.status !== 'in_progress') stopPolling()
  } catch (e) {
    if (gen === generation) refreshError.value = true
  }
}

function stopPolling() {
  clearInterval(pollTimer)
  pollTimer = null
}

async function show(id) {
  stopPolling()
  const gen = ++generation
  sessionId.value = id
  detail.value = null
  open.value = true
  loading.value = true
  await load(gen)
  if (gen !== generation || !open.value) return
  loading.value = false
  stopPolling()
  if (detail.value?.status === 'in_progress') pollTimer = setInterval(() => load(gen), POLL_MS)
  clearInterval(clockTimer)
  clockTimer = setInterval(() => { now.value = Date.now() }, 1000)
}

watch(open, value => {
  if (!value) {
    generation++
    stopPolling()
    clearInterval(clockTimer)
  }
})

onUnmounted(() => {
  stopPolling()
  clearInterval(clockTimer)
})

defineExpose({ open: show })
</script>

<style scoped>
.question-card {
  border: 1px solid #e8eaf0;
  border-radius: 12px;
  padding: 12px;
}
.option {
  display: flex;
  align-items: center;
  border: 1px solid #eef0f4;
  border-radius: 8px;
  padding: 6px 10px;
  margin-bottom: 6px;
  font-size: 14px;
}
.option.chosen.correct {
  border-color: #2e7d32;
  background: #e8f5e9;
}
.option.chosen.wrong {
  border-color: #c62828;
  background: #ffebee;
}
.live-dot {
  animation: pulse 1.4s infinite;
}
@keyframes pulse {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.3; }
}
</style>
