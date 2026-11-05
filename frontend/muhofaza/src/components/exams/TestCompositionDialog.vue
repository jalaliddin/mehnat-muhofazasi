<template>
  <v-dialog v-model="dialog" max-width="760">
    <v-card rounded="xl">
      <v-card-title class="pa-6 d-flex align-center">
        <v-icon icon="mdi-format-list-checks" color="primary" class="mr-2" />
        Test tarkibi: {{ type?.name }}
      </v-card-title>
      <v-divider />

      <v-card-text class="pa-6">
        <div v-if="loading" class="text-center pa-6">
          <v-progress-circular indeterminate color="primary" />
        </div>

        <template v-else>
          <v-row dense>
            <v-col cols="12" sm="6">
              <v-text-field
                v-model.number="settings.duration_minutes"
                label="Test vaqti (daqiqa)"
                type="number"
                min="1"
                variant="outlined"
                rounded="lg"
                density="compact"
                hide-details
                :disabled="!isAdmin"
              />
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field
                v-model.number="settings.passing_score"
                label="O'tish balli (%)"
                type="number"
                min="1"
                max="100"
                variant="outlined"
                rounded="lg"
                density="compact"
                hide-details
                :disabled="!isAdmin"
              />
            </v-col>
          </v-row>

          <div class="text-subtitle-2 font-weight-bold mt-6 mb-3" style="color: #0D1B2A">Savollar bazalari</div>

          <div v-if="!rows.length" class="text-body-2 py-2" style="color: #888">
            Tarkib bo'sh — test havolalarini yaratish uchun kamida bitta baza qo'shing.
          </div>

          <div v-for="(row, i) in rows" :key="row.key" class="d-flex flex-wrap align-center mb-2" style="gap: 8px">
            <v-select
              v-model="row.question_bank_id"
              :items="bankOptions(i)"
              item-title="name"
              item-value="id"
              label="Baza"
              variant="outlined"
              density="compact"
              rounded="lg"
              hide-details
              :disabled="!isAdmin"
              style="flex: 1 1 260px"
            />
            <v-text-field
              v-model.number="row.question_count"
              label="Nechta"
              type="number"
              min="1"
              max="500"
              variant="outlined"
              density="compact"
              rounded="lg"
              hide-details
              :disabled="!isAdmin"
              style="flex: 0 0 110px"
            />
            <div class="d-flex align-center text-body-2" style="flex: 0 0 120px; color: #666">
              <template v-if="row.question_bank_id">
                Bazada {{ available(row) }} ta
                <v-icon v-if="row.question_count > available(row)" icon="mdi-alert" color="warning" size="small" class="ml-1">
                  <v-tooltip activator="parent" location="top">Bazada {{ available(row) }} ta savol bor</v-tooltip>
                </v-icon>
              </template>
            </div>
            <v-btn v-if="isAdmin" icon="mdi-close" variant="text" size="small" @click="rows.splice(i, 1)" />
          </div>

          <div class="d-flex align-center mt-4">
            <v-btn
              v-if="isAdmin"
              variant="tonal"
              color="primary"
              prepend-icon="mdi-plus"
              rounded="lg"
              size="small"
              :disabled="rows.length >= banks.length"
              @click="addRow"
            >
              Baza qo'shish
            </v-btn>
            <v-spacer />
            <div class="font-weight-bold" style="color: #0D1B2A">Jami: {{ total }} ta savol</div>
          </div>

          <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mt-4">{{ error }}</v-alert>
        </template>
      </v-card-text>

      <v-card-actions class="pa-4">
        <v-spacer />
        <v-btn variant="text" @click="dialog = false">{{ isAdmin ? 'Bekor qilish' : 'Yopish' }}</v-btn>
        <v-btn v-if="isAdmin" color="primary" variant="flat" rounded="lg" :loading="saving" @click="save">Saqlash</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, computed } from 'vue'
import api from '../../services/api'
import { useAuthStore } from '../../stores/auth'

const emit = defineEmits(['saved'])
const authStore = useAuthStore()
const isAdmin = computed(() => authStore.isAdmin)

const dialog = ref(false)
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const type = ref(null)
const banks = ref([])
const rows = ref([])
const settings = ref({ duration_minutes: 30, passing_score: 60 })

let rowKey = 0
const DEFAULT_COUNT = 10

const total = computed(() => rows.value.reduce((sum, r) => sum + (Number(r.question_count) || 0), 0))

function available(row) {
  return banks.value.find(b => b.id === row.question_bank_id)?.questions_count ?? 0
}

// Bitta baza bitta testga bir marta qo'shiladi: boshqa qatorlarda tanlanganlar ro'yxatdan chiqariladi.
function bankOptions(index) {
  const taken = rows.value.filter((_, i) => i !== index).map(r => r.question_bank_id)
  return banks.value.filter(b => !taken.includes(b.id))
}

function addRow() {
  rows.value.push({ key: ++rowKey, question_bank_id: null, question_count: DEFAULT_COUNT })
}

function firstError(e) {
  const errors = e.response?.data?.errors
  if (errors) return Object.values(errors)[0]?.[0]
  return e.response?.data?.message || "Saqlab bo'lmadi"
}

async function open(examType) {
  type.value = examType
  settings.value = {
    duration_minutes: examType.duration_minutes ?? 30,
    passing_score: examType.passing_score ?? 60,
  }
  error.value = ''
  rows.value = []
  dialog.value = true
  loading.value = true
  try {
    const [banksRes, compositionRes] = await Promise.all([
      api.get('/question-banks'),
      api.get(`/exam-types/${examType.id}/question-banks`),
    ])
    banks.value = banksRes.data
    rows.value = compositionRes.data.map(r => ({
      key: ++rowKey,
      question_bank_id: r.question_bank_id,
      question_count: r.question_count,
    }))
  } catch (e) {
    error.value = "Tarkibni yuklab bo'lmadi"
  }
  loading.value = false
}

async function save() {
  error.value = ''
  const invalid = rows.value.some(r => !r.question_bank_id || !(r.question_count >= 1 && r.question_count <= 500))
  if (invalid) {
    error.value = "Har bir qatorda baza tanlangan va son 1–500 oralig'ida bo'lishi kerak"
    return
  }
  saving.value = true
  try {
    await api.put(`/exam-types/${type.value.id}`, settings.value)
    await api.put(`/exam-types/${type.value.id}/question-banks`, {
      banks: rows.value.map(r => ({ question_bank_id: r.question_bank_id, question_count: r.question_count })),
    })
    dialog.value = false
    emit('saved')
  } catch (e) {
    error.value = firstError(e)
  }
  saving.value = false
}

defineExpose({ open })
</script>
