<template>
  <div class="test-page d-flex justify-center pa-4" style="min-height: 100vh; background: #f4f6f9">
    <div style="max-width: 720px; width: 100%; padding-top: 32px">
      <div v-if="loading" class="text-center pa-10">
        <v-progress-circular indeterminate color="primary" />
      </div>

      <v-alert v-else-if="loadError" type="error" variant="tonal" rounded="xl">
        {{ loadError }}
      </v-alert>

      <!-- Intro screen -->
      <v-card v-else-if="session.status === 'pending'" rounded="xl" elevation="0" style="border: 1px solid #e8eaf0" class="pa-8">
        <div class="text-h5 font-weight-bold mb-4" style="color: #0D1B2A">{{ session.exam_type_name }}</div>
        <div class="mb-2"><span style="color: #888">Xodim: </span><strong>{{ session.employee_name }}</strong></div>
        <div class="mb-2"><span style="color: #888">Imtihon: </span><strong>{{ session.exam_title }}</strong></div>
        <div class="mb-2"><span style="color: #888">Savollar soni: </span><strong>{{ session.total_questions }}</strong></div>
        <div class="mb-6"><span style="color: #888">Vaqt: </span><strong>{{ session.duration_minutes }} daqiqa</strong></div>
        <v-alert type="info" variant="tonal" density="compact" class="mb-6">
          Test boshlangach taymer ishga tushadi. Vaqt tugaganda test avtomatik topshiriladi. Testni qaytadan boshlash imkoni yo'q.
        </v-alert>
        <v-btn color="primary" size="large" block rounded="lg" :loading="starting" @click="startTest">Testni boshlash</v-btn>
      </v-card>

      <!-- Already completed -->
      <v-card v-else-if="session.status === 'completed'" rounded="xl" elevation="0" style="border: 1px solid #e8eaf0" class="pa-8 text-center">
        <v-icon icon="mdi-check-circle" color="success" size="64" class="mb-4" />
        <div class="text-h5 font-weight-bold mb-2" style="color: #0D1B2A">Test allaqachon topshirilgan</div>
        <div class="text-h4 font-weight-bold mb-2" style="color: #2e7d32">{{ session.score_percent }}%</div>
      </v-card>

      <!-- Expired without submission -->
      <v-card v-else-if="session.status === 'expired'" rounded="xl" elevation="0" style="border: 1px solid #e8eaf0" class="pa-8 text-center">
        <v-icon icon="mdi-clock-alert" color="error" size="64" class="mb-4" />
        <div class="text-h5 font-weight-bold" style="color: #0D1B2A">Test vaqti tugagan</div>
      </v-card>

      <!-- In-progress: question form -->
      <template v-else-if="session.status === 'in_progress' && !result">
        <v-card rounded="xl" elevation="0" style="border: 1px solid #e8eaf0" class="pa-4 mb-4 d-flex align-center justify-space-between" :style="timeLeft <= 60 ? 'border-color:#f44336' : ''">
          <div class="font-weight-bold" style="color: #0D1B2A">{{ session.exam_title }}</div>
          <v-chip :color="timeLeft <= 60 ? 'error' : 'primary'" label size="large">
            <v-icon start icon="mdi-clock-outline" />
            {{ formattedTime }}
          </v-chip>
        </v-card>

        <v-card
          v-for="(q, idx) in questions"
          :key="q.exam_session_question_id"
          rounded="xl"
          elevation="0"
          style="border: 1px solid #e8eaf0"
          class="pa-6 mb-4"
        >
          <div class="font-weight-bold mb-3" style="color: #0D1B2A">{{ idx + 1 }}. {{ q.question_text }}</div>
          <v-radio-group v-model="answers[q.exam_session_question_id]" hide-details>
            <v-radio v-for="opt in q.options" :key="opt.key" :value="opt.key" :label="`${opt.key}. ${opt.text}`" />
          </v-radio-group>
        </v-card>

        <v-btn color="primary" size="large" block rounded="lg" :loading="submitting" @click="submitTest(false)">
          Testni topshirish
        </v-btn>
      </template>

      <!-- Result screen -->
      <v-card v-else-if="result" rounded="xl" elevation="0" style="border: 1px solid #e8eaf0" class="pa-8 text-center">
        <v-icon :icon="result.grade === 'unsatisfactory' ? 'mdi-close-circle' : 'mdi-check-circle'" :color="result.grade === 'unsatisfactory' ? 'error' : 'success'" size="64" class="mb-4" />
        <div class="text-h4 font-weight-bold mb-2">{{ result.score_percent }}%</div>
        <div class="text-body-1 mb-4" style="color: #888">{{ result.earned_points }} / {{ result.total_points }} ball</div>
        <v-chip :color="gradeColor(result.grade)" size="large" label>{{ gradeText(result.grade) }}</v-chip>
      </v-card>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'

const route = useRoute()
const token = route.params.token

const publicApi = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api',
  headers: { Accept: 'application/json' },
})

const loading = ref(true)
const loadError = ref('')
const session = ref(null)
const questions = ref([])
const answers = ref({})
const starting = ref(false)
const submitting = ref(false)
const result = ref(null)
const deadline = ref(null)
const timeLeft = ref(0)
let timer = null

const formattedTime = computed(() => {
  const m = Math.floor(timeLeft.value / 60)
  const s = timeLeft.value % 60
  return `${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`
})

function gradeColor(grade) {
  return { excellent: 'success', good: 'primary', satisfactory: 'warning', unsatisfactory: 'error' }[grade] || 'grey'
}

function gradeText(grade) {
  return { excellent: "A'lo", good: 'Yaxshi', satisfactory: 'Qoniqarli', unsatisfactory: 'Qoniqarsiz' }[grade] || grade
}

async function fetchSession() {
  loading.value = true
  try {
    const res = await publicApi.get(`/public/exam-sessions/${token}`)
    session.value = res.data
    if (session.value.status === 'in_progress' && session.value.started_at) {
      deadline.value = new Date(session.value.started_at).getTime() + session.value.duration_minutes * 60000
      startTimer()
    }
  } catch (e) {
    loadError.value = "Test havolasi topilmadi yoki noto'g'ri."
  }
  loading.value = false
}

async function startTest() {
  starting.value = true
  try {
    const res = await publicApi.post(`/public/exam-sessions/${token}/start`)
    questions.value = res.data.questions
    session.value.status = 'in_progress'
    deadline.value = new Date(res.data.started_at).getTime() + res.data.duration_minutes * 60000
    startTimer()
  } catch (e) {
    loadError.value = e.response?.data?.message || 'Xatolik yuz berdi'
  }
  starting.value = false
}

function startTimer() {
  updateTimeLeft()
  timer = setInterval(() => {
    updateTimeLeft()
    if (timeLeft.value <= 0) {
      clearInterval(timer)
      submitTest(true)
    }
  }, 1000)
}

function updateTimeLeft() {
  timeLeft.value = Math.max(0, Math.round((deadline.value - Date.now()) / 1000))
}

async function submitTest(auto) {
  if (submitting.value) return
  submitting.value = true
  if (timer) clearInterval(timer)
  try {
    const payload = {
      answers: Object.entries(answers.value).map(([exam_session_question_id, selected_option]) => ({
        exam_session_question_id: Number(exam_session_question_id),
        selected_option,
      })),
    }
    const res = await publicApi.post(`/public/exam-sessions/${token}/submit`, payload)
    result.value = res.data
  } catch (e) {
    if (!auto) loadError.value = e.response?.data?.message || 'Xatolik yuz berdi'
  }
  submitting.value = false
}

onMounted(fetchSession)
onUnmounted(() => { if (timer) clearInterval(timer) })
</script>
