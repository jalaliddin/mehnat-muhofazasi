<template>
  <div>
    <div class="d-flex align-center mb-6">
      <v-btn icon="mdi-arrow-left" variant="text" @click="$router.back()" class="mr-3" />
      <div class="text-h5 font-weight-bold" style="color: #0D1B2A">Imtihon tafsilotlari</div>
    </div>

    <!-- Exam info card -->
    <v-card v-if="exam" rounded="xl" elevation="0" style="border: 1px solid #e8eaf0" class="mb-6 pa-6">
      <v-row>
        <v-col cols="12" md="8">
          <div class="text-h6 font-weight-bold mb-2" style="color: #0D1B2A">{{ exam.title }}</div>
          <div class="d-flex flex-wrap gap-4">
            <div>
              <span class="text-body-2" style="color: #888">Tashkilot: </span>
              <span class="text-body-2 font-weight-medium">{{ exam.organization?.name }}</span>
            </div>
            <div>
              <span class="text-body-2" style="color: #888">Tur: </span>
              <span class="text-body-2 font-weight-medium">{{ exam.exam_type?.name }}</span>
            </div>
            <div>
              <span class="text-body-2" style="color: #888">Sana: </span>
              <span class="text-body-2 font-weight-medium">{{ formatDate(exam.exam_date) }}</span>
            </div>
            <div>
              <span class="text-body-2" style="color: #888">Keyingi: </span>
              <span class="text-body-2 font-weight-medium">{{ formatDate(exam.next_exam_date) }}</span>
            </div>
            <div>
              <span class="text-body-2" style="color: #888">Chastota: </span>
              <span class="text-body-2 font-weight-medium">{{ exam.frequency_months }} oy</span>
            </div>
          </div>
        </v-col>
        <v-col cols="12" md="4" class="d-flex align-center justify-end">
          <v-chip :color="statusColor(exam.status)" size="large" label class="mr-2">
            {{ statusText(exam.status) }}
          </v-chip>
        </v-col>
      </v-row>
    </v-card>

    <!-- Results table -->
    <v-card rounded="xl" elevation="0" style="border: 1px solid #e8eaf0">
      <v-card-title class="pa-5 d-flex align-center">
        <v-icon icon="mdi-clipboard-list" color="primary" class="mr-2" />
        <span style="color: #0D1B2A">Natijalar</span>
        <v-spacer />
        <v-btn color="primary" prepend-icon="mdi-plus" rounded="lg" size="small" @click="openAddResult">
          Natija qo'shish
        </v-btn>
      </v-card-title>
      <v-divider />
      <v-data-table
        :headers="resultHeaders"
        :items="results"
        :loading="loadingResults"
        hover
        density="comfortable"
      >
        <template #item.grade="{ item }">
          <GradeBadge :grade="item.grade" />
        </template>
        <template #item.score="{ item }">{{ item.score ?? '-' }}</template>
        <template #item.is_passed="{ item }">
          <v-icon :icon="item.is_passed ? 'mdi-check-circle' : 'mdi-close-circle'" :color="item.is_passed ? 'success' : 'error'" size="small" />
        </template>
        <template #item.retake_required="{ item }">
          <v-icon :icon="item.retake_required ? 'mdi-check-circle' : 'mdi-minus-circle'" :color="item.retake_required ? 'error' : 'grey'" size="small" />
        </template>
        <template #item.actions="{ item }">
          <v-btn icon="mdi-pencil" variant="text" size="small" color="warning" @click="openEditResult(item)" />
          <v-btn icon="mdi-delete" variant="text" size="small" color="error" @click="openDeleteResult(item)" />
        </template>
      </v-data-table>
    </v-card>

    <!-- Add/Edit Result Dialog -->
    <v-dialog v-model="resultDialog" max-width="500">
      <v-card rounded="xl">
        <v-card-title class="pa-6 d-flex align-center">
          <v-icon :icon="editResult ? 'mdi-pencil' : 'mdi-plus'" class="mr-2" color="primary" />
          {{ editResult ? 'Natijani tahrirlash' : 'Natija qo\'shish' }}
        </v-card-title>
        <v-divider />
        <v-card-text class="pa-6">
          <v-form ref="resultFormRef" @submit.prevent="saveResult">
            <v-autocomplete
              v-if="!editResult"
              v-model="resultForm.employee_id"
              :items="availableEmployees"
              item-title="full_name"
              item-value="id"
              label="Xodim *"
              variant="outlined"
              rounded="lg"
              :rules="[v => !!v || 'Majburiy maydon']"
              class="mb-3"
            />
            <v-select
              v-model="resultForm.grade"
              :items="gradeOptions"
              item-title="label"
              item-value="value"
              label="Baho *"
              variant="outlined"
              rounded="lg"
              :rules="[v => !!v || 'Majburiy maydon']"
              class="mb-3"
            />
            <v-text-field
              v-model.number="resultForm.score"
              label="Ball"
              type="number"
              variant="outlined"
              rounded="lg"
              class="mb-3"
            />
            <v-text-field
              v-model="resultForm.exam_date"
              label="Topshirgan sana"
              type="date"
              variant="outlined"
              rounded="lg"
              class="mb-3"
            />
            <v-switch
              v-model="resultForm.retake_required"
              label="Qayta topshirish kerak"
              color="error"
              class="mb-2"
            />
            <v-textarea
              v-model="resultForm.notes"
              label="Izoh"
              variant="outlined"
              rounded="lg"
              rows="2"
            />
          </v-form>
        </v-card-text>
        <v-card-actions class="pa-4">
          <v-spacer />
          <v-btn variant="text" @click="resultDialog = false">Bekor qilish</v-btn>
          <v-btn color="primary" variant="flat" rounded="lg" :loading="savingResult" @click="saveResult">Saqlash</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <ConfirmDialog
      ref="confirmRef"
      title="Natijani o'chirish"
      message="Ushbu natijani o'chirmoqchimisiz?"
      @confirm="deleteResult"
    />
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import api from '../../services/api'
import GradeBadge from '../../components/common/GradeBadge.vue'
import ConfirmDialog from '../../components/common/ConfirmDialog.vue'

const route = useRoute()
const exam = ref(null)
const results = ref([])
const availableEmployees = ref([])
const loadingResults = ref(false)
const resultDialog = ref(false)
const savingResult = ref(false)
const editResult = ref(null)
const deleteResultItem = ref(null)
const resultFormRef = ref(null)
const confirmRef = ref(null)

const gradeOptions = [
  { label: "A'lo", value: 'excellent' },
  { label: 'Yaxshi', value: 'good' },
  { label: 'Qoniqarli', value: 'satisfactory' },
  { label: 'Qoniqarsiz', value: 'unsatisfactory' },
]

const resultHeaders = [
  { title: 'Xodim', key: 'employee.full_name' },
  { title: 'Lavozim', key: 'employee.position.name', sortable: false },
  { title: 'Ball', key: 'score' },
  { title: 'Baho', key: 'grade' },
  { title: 'O\'tdi', key: 'is_passed', sortable: false },
  { title: 'Qayta topshirish', key: 'retake_required', sortable: false },
  { title: 'Izoh', key: 'notes', sortable: false },
  { title: 'Amallar', key: 'actions', sortable: false, align: 'end' },
]

const defaultResultForm = () => ({
  employee_id: null,
  grade: null,
  score: null,
  retake_required: false,
  notes: '',
})

const resultForm = ref(defaultResultForm())

function formatDate(d) {
  if (!d) return '-'
  const date = new Date(d)
  return `${String(date.getDate()).padStart(2,'0')}.${String(date.getMonth()+1).padStart(2,'0')}.${date.getFullYear()}`
}

function statusColor(s) {
  return { planned: 'primary', in_progress: 'warning', completed: 'success' }[s] || 'grey'
}

function statusText(s) {
  return { planned: 'Rejalashtirilgan', in_progress: 'Jarayonda', completed: 'Tugallangan' }[s] || s
}

function openAddResult() {
  editResult.value = null
  resultForm.value = defaultResultForm()
  resultDialog.value = true
}

function openEditResult(item) {
  editResult.value = item
  resultForm.value = {
    employee_id: item.employee_id,
    grade: item.grade,
    score: item.score,
    retake_required: item.retake_required,
    notes: item.notes || '',
  }
  resultDialog.value = true
}

function openDeleteResult(item) {
  deleteResultItem.value = item
  confirmRef.value.open()
}

async function fetchExam() {
  try {
    const res = await api.get(`/periodic-exams/${route.params.id}`)
    exam.value = res.data
    // Always load all org employees for the result entry dialog
    if (exam.value?.organization_id) {
      const empRes = await api.get('/employees', { params: { organization_id: exam.value.organization_id, is_active: 1 } })
      availableEmployees.value = empRes.data.data || empRes.data
    }
  } catch (e) {}
}

async function fetchResults() {
  loadingResults.value = true
  try {
    const res = await api.get(`/exam-results`, { params: { periodic_exam_id: route.params.id } })
    results.value = res.data.data || res.data
  } catch (e) {}
  loadingResults.value = false
}

async function saveResult() {
  const { valid } = await resultFormRef.value.validate()
  if (!valid) return
  savingResult.value = true
  try {
    if (editResult.value) {
      await api.put(`/exam-results/${editResult.value.id}`, resultForm.value)
    } else {
      await api.post(`/exam-results`, {
        ...resultForm.value,
        periodic_exam_id: Number(route.params.id),
      })
    }
    resultDialog.value = false
    await fetchResults()
  } catch (e) {}
  savingResult.value = false
}

async function deleteResult() {
  try {
    await api.delete(`/exam-results/${deleteResultItem.value.id}`)
    await fetchResults()
  } catch (e) {}
}

onMounted(async () => {
  await fetchExam()
  await fetchResults()
})
</script>
