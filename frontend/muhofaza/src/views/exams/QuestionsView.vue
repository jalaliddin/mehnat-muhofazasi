<template>
  <div>
    <div class="d-flex align-center mb-6">
      <v-btn icon="mdi-arrow-left" variant="text" @click="$router.push('/exams/question-banks')" class="mr-3" />
      <div>
        <div class="text-h5 font-weight-bold" style="color: #0D1B2A">Savollar — {{ bank?.name }}</div>
        <div v-if="bank?.description" class="text-body-2" style="color: #777">{{ bank.description }}</div>
      </div>
    </div>

    <v-card v-if="authStore.isAdmin" rounded="xl" elevation="0" style="border: 1px solid #e8eaf0" class="mb-4 pa-4">
      <div class="d-flex flex-wrap align-center gap-2">
        <v-btn variant="outlined" rounded="lg" prepend-icon="mdi-download" @click="downloadTemplate">
          Excel shablonni yuklab olish
        </v-btn>
        <v-btn variant="outlined" rounded="lg" prepend-icon="mdi-upload" :loading="importing" @click="triggerImport">
          Excel orqali import qilish
        </v-btn>
        <input ref="fileInput" type="file" accept=".xlsx,.xls" hidden @change="handleImport" />
        <v-spacer />
        <v-btn color="primary" prepend-icon="mdi-plus" rounded="lg" @click="openCreate">
          Yangi savol
        </v-btn>
      </div>
      <v-alert
        v-if="importResult"
        :type="importResult.errors.length ? 'warning' : 'success'"
        class="mt-3"
        density="compact"
        variant="tonal"
      >
        {{ importResult.imported }} ta savol import qilindi.
        <div v-if="importResult.errors.length">
          <div v-for="(err, i) in importResult.errors" :key="i" class="text-caption">{{ err }}</div>
        </div>
      </v-alert>
    </v-card>

    <v-card rounded="xl" elevation="0" style="border: 1px solid #e8eaf0">
      <v-data-table
        :headers="visibleHeaders"
        :items="questions"
        :loading="loading"
        hover
      >
        <template #item.question_text="{ item }">
          <div style="max-width: 420px">{{ item.question_text }}</div>
        </template>
        <template #item.options="{ item }">
          <div v-for="opt in item.options" :key="opt.key" class="text-caption">
            <strong :style="opt.key === item.correct_option ? 'color:#2e7d32' : ''">{{ opt.key }}.</strong>
            {{ opt.text }}
            <v-icon v-if="opt.key === item.correct_option" icon="mdi-check-circle" color="success" size="x-small" />
          </div>
        </template>
        <template #item.points="{ item }">{{ item.points }}</template>
        <template #item.actions="{ item }">
          <v-btn icon="mdi-pencil" variant="text" size="small" color="primary" @click="openEdit(item)" />
          <v-btn icon="mdi-delete" variant="text" size="small" color="error" @click="openDelete(item)" />
        </template>
      </v-data-table>
    </v-card>

    <!-- Create/Edit Question Dialog -->
    <v-dialog v-model="dialog" max-width="600">
      <v-card rounded="xl">
        <v-card-title class="pa-6 d-flex align-center">
          <v-icon :icon="editItem ? 'mdi-pencil' : 'mdi-plus'" class="mr-2" color="primary" />
          {{ editItem ? "Savolni tahrirlash" : "Yangi savol" }}
        </v-card-title>
        <v-divider />
        <v-card-text class="pa-6">
          <v-form ref="formRef" @submit.prevent="save">
            <v-textarea
              v-model="form.question_text"
              label="Savol matni *"
              variant="outlined"
              rounded="lg"
              rows="2"
              :rules="[v => !!v || 'Majburiy maydon']"
              class="mb-3"
            />
            <div v-for="(opt, idx) in form.options" :key="idx" class="d-flex align-center mb-2 gap-2">
              <v-radio-group v-model="form.correct_option" hide-details class="mr-1">
                <v-radio :value="opt.key" />
              </v-radio-group>
              <v-text-field
                v-model="opt.text"
                :label="`Variant ${opt.key}`"
                variant="outlined"
                rounded="lg"
                density="compact"
                hide-details
              />
            </div>
            <div class="text-caption mb-3" style="color: #888">To'g'ri javobni radio tugma bilan belgilang</div>
            <v-text-field
              v-model.number="form.points"
              label="Ball"
              type="number"
              min="1"
              variant="outlined"
              rounded="lg"
              class="mb-3"
            />
          </v-form>
        </v-card-text>
        <v-card-actions class="pa-4">
          <v-spacer />
          <v-btn variant="text" @click="dialog = false">Bekor qilish</v-btn>
          <v-btn color="primary" variant="flat" rounded="lg" :loading="saving" @click="save">Saqlash</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <ConfirmDialog
      ref="confirmRef"
      title="Savolni o'chirish"
      message="Ushbu savolni o'chirmoqchimisiz?"
      @confirm="deleteQuestion"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import api from '../../services/api'
import ConfirmDialog from '../../components/common/ConfirmDialog.vue'
import { useAuthStore } from '../../stores/auth'

const route = useRoute()
const authStore = useAuthStore()
const bankId = Number(route.params.id)

const bank = ref(null)
const questions = ref([])
const loading = ref(false)
const dialog = ref(false)
const saving = ref(false)
const importing = ref(false)
const importResult = ref(null)
const editItem = ref(null)
const deleteItem = ref(null)
const formRef = ref(null)
const confirmRef = ref(null)
const fileInput = ref(null)

const headers = [
  { title: 'Savol', key: 'question_text' },
  { title: 'Variantlar', key: 'options', sortable: false },
  { title: 'Ball', key: 'points' },
  { title: 'Amallar', key: 'actions', sortable: false, align: 'end' },
]
// Operator savollarni faqat ko'radi — amallar ustuni yashiriladi.
const visibleHeaders = computed(() => authStore.isAdmin ? headers : headers.filter(h => h.key !== 'actions'))

const defaultForm = () => ({
  question_text: '',
  options: [
    { key: 'A', text: '' },
    { key: 'B', text: '' },
    { key: 'C', text: '' },
    { key: 'D', text: '' },
  ],
  correct_option: 'A',
  points: 1,
})

const form = ref(defaultForm())

function openCreate() {
  editItem.value = null
  form.value = defaultForm()
  dialog.value = true
}

function openEdit(item) {
  editItem.value = item
  const options = ['A', 'B', 'C', 'D'].map(key => {
    const found = item.options.find(o => o.key === key)
    return { key, text: found?.text || '' }
  })
  form.value = {
    question_text: item.question_text,
    options,
    correct_option: item.correct_option,
    points: item.points,
  }
  dialog.value = true
}

function openDelete(item) {
  deleteItem.value = item
  confirmRef.value.open()
}

async function fetchBank() {
  try {
    const res = await api.get(`/question-banks/${bankId}`)
    bank.value = res.data
  } catch (e) {}
}

async function fetchQuestions() {
  loading.value = true
  try {
    const res = await api.get('/questions', { params: { question_bank_id: bankId } })
    questions.value = res.data
  } catch (e) {}
  loading.value = false
}

async function save() {
  const { valid } = await formRef.value.validate()
  if (!valid) return
  saving.value = true
  try {
    const payload = {
      question_bank_id: bankId,
      question_text: form.value.question_text,
      options: form.value.options.filter(o => o.text.trim() !== ''),
      correct_option: form.value.correct_option,
      points: form.value.points || 1,
    }
    if (editItem.value) {
      await api.put(`/questions/${editItem.value.id}`, payload)
    } else {
      await api.post('/questions', payload)
    }
    dialog.value = false
    await fetchQuestions()
  } catch (e) {}
  saving.value = false
}

async function deleteQuestion() {
  try {
    await api.delete(`/questions/${deleteItem.value.id}`)
    await fetchQuestions()
  } catch (e) {}
}

async function downloadTemplate() {
  const res = await api.get('/questions/template', { responseType: 'blob' })
  const url = window.URL.createObjectURL(new Blob([res.data]))
  const link = document.createElement('a')
  link.href = url
  link.download = 'savollar-shablon.xlsx'
  link.click()
  window.URL.revokeObjectURL(url)
}

function triggerImport() {
  fileInput.value.click()
}

async function handleImport(e) {
  const file = e.target.files[0]
  if (!file) return
  importing.value = true
  importResult.value = null
  try {
    const formData = new FormData()
    formData.append('question_bank_id', bankId)
    formData.append('file', file)
    const res = await api.post('/questions/import', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    })
    importResult.value = res.data
    await fetchQuestions()
  } catch (e) {}
  importing.value = false
  e.target.value = ''
}

onMounted(async () => {
  await fetchBank()
  await fetchQuestions()
})
</script>
