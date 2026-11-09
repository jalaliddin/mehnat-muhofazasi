<template>
  <v-dialog v-model="dialog" max-width="720" scrollable>
    <v-card rounded="xl">
      <v-card-title class="pa-6">
        Imzolash uchun chop etish<span v-if="rows.length"> — {{ rows.length }} ta sertifikat</span>
      </v-card-title>
      <v-divider />

      <v-card-text class="pa-6" style="max-height: 70vh">
        <div v-if="loading" class="text-center pa-6"><v-progress-circular indeterminate color="primary" /></div>

        <v-alert v-else-if="loadError" type="error" variant="tonal" density="compact">{{ loadError }}</v-alert>

        <div v-else-if="!rows.length" class="text-body-1 text-center py-6" style="color: #888">Imzolanmagan sertifikat yo'q</div>

        <template v-else>
          <div class="d-flex flex-wrap align-start mb-5" style="gap: 8px">
            <v-text-field
              v-model="first"
              label="Birinchi raqam"
              placeholder="0451"
              variant="outlined"
              density="compact"
              rounded="lg"
              style="flex: 0 1 220px"
              :error-messages="fillError"
              hint="Bo'sh kataklarga oxirgi son oshirib yoziladi: 0451, 0452, …"
              persistent-hint
              @update:model-value="fillError = ''"
            />
            <v-btn variant="tonal" color="primary" rounded="lg" prepend-icon="mdi-format-list-numbered" @click="fill">
              Ketma-ket to'ldirish
            </v-btn>
          </div>

          <div v-for="(row, i) in rows" :key="row.id" class="d-flex align-start py-1" style="gap: 12px; border-bottom: 1px solid #f0f2f5">
            <div class="text-body-2 pt-2" style="flex: 1 1 auto; color: #0D1B2A">{{ i + 1 }}. {{ row.name }}</div>
            <v-text-field
              v-model="row.number"
              variant="outlined"
              density="compact"
              rounded="lg"
              hide-details="auto"
              placeholder="Raqam"
              style="flex: 0 0 200px"
              :error-messages="rowErrors[i]"
              @update:model-value="rowErrors[i] = ''"
            />
          </div>

          <v-alert v-if="error" type="error" variant="tonal" density="compact" class="mt-4">{{ error }}</v-alert>
        </template>
      </v-card-text>

      <v-divider />
      <v-card-actions class="pa-4">
        <span v-if="rows.length && emptyCount" class="text-caption ml-2" style="color: #b26a00">{{ emptyCount }} ta katak bo'sh</span>
        <v-spacer />
        <v-btn variant="text" @click="dialog = false">Bekor qilish</v-btn>
        <v-btn
          v-if="rows.length"
          color="primary"
          variant="flat"
          rounded="lg"
          :loading="saving"
          :disabled="emptyCount > 0"
          @click="save"
        >
          Saqlash va PDF yuklab olish
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, computed } from 'vue'
import api from '../../services/api'
import { downloadFile } from '../../services/download'
import { fillSequential } from '../../utils/certificateNumbers'

const emit = defineEmits(['saved'])

const dialog = ref(false)
const loading = ref(false)
const saving = ref(false)
const examId = ref(null)
const rows = ref([])
const rowErrors = ref([])
const first = ref('')
const fillError = ref('')
const error = ref('')
const loadError = ref('')

const emptyCount = computed(() => rows.value.filter(r => !String(r.number ?? '').trim()).length)

async function open(periodicExamId) {
  examId.value = periodicExamId
  rows.value = []
  rowErrors.value = []
  first.value = ''
  error.value = ''
  fillError.value = ''
  loadError.value = ''
  dialog.value = true
  loading.value = true
  try {
    const res = await api.get('/certificates', {
      params: { periodic_exam_id: periodicExamId, status: 'pending_signature', per_page: 500 },
    })
    rows.value = res.data.data
      .slice()
      .sort((a, b) => a.id - b.id)
      .map(c => ({ id: c.id, name: c.employee?.full_name || `#${c.id}`, number: c.number || '' }))
    rowErrors.value = rows.value.map(() => '')
  } catch (e) {
    loadError.value = "Ro'yxatni yuklab bo'lmadi"
  }
  loading.value = false
}

function fill() {
  try {
    const filled = fillSequential(first.value, rows.value.map(r => r.number))
    rows.value.forEach((r, i) => { r.number = filled[i] })
  } catch (e) {
    fillError.value = e.message
  }
}

async function save() {
  saving.value = true
  error.value = ''
  rowErrors.value = rows.value.map(() => '')
  try {
    await api.put('/certificates/numbers', {
      items: rows.value.map(r => ({ id: r.id, number: r.number })),
    })
    await downloadFile('/certificates/print', { periodic_exam_id: examId.value }, `sertifikatlar-${examId.value}.pdf`)
    dialog.value = false
    emit('saved')
  } catch (e) {
    const errors = e.response?.data?.errors
    if (errors) {
      for (const [key, messages] of Object.entries(errors)) {
        const index = Number(key.match(/^items\.(\d+)\./)?.[1])
        if (!Number.isNaN(index)) rowErrors.value[index] = messages[0]
      }
      error.value = "Ba'zi raqamlarda xato bor — qizil qatorlarni tuzating"
    } else {
      error.value = e.response?.data?.message || e.message || "Saqlab bo'lmadi"
    }
  }
  saving.value = false
}

defineExpose({ open })
</script>
