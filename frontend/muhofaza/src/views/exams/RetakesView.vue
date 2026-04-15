<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-6">
      <div>
        <div class="text-h5 font-weight-bold" style="color: #0D1B2A">Qayta Topshirish</div>
        <div class="text-body-2" style="color: #888">Qoniqarsiz natija olingan va qayta topshirish zarur bo'lgan xodimlar</div>
      </div>
    </div>

    <v-card rounded="xl" elevation="0" style="border: 1px solid #e8eaf0" class="mb-4 pa-4">
      <v-row dense>
        <v-col cols="12" sm="4" md="3">
          <v-select
            v-if="authStore.isAdmin"
            v-model="filterOrg"
            :items="organizations"
            item-title="name"
            item-value="id"
            label="Tashkilot"
            variant="outlined"
            density="compact"
            hide-details
            rounded="lg"
            clearable
          />
        </v-col>
        <v-col cols="12" sm="4" md="4">
          <v-text-field
            v-model="search"
            placeholder="Xodim qidirish..."
            prepend-inner-icon="mdi-magnify"
            variant="outlined"
            density="compact"
            hide-details
            rounded="lg"
          />
        </v-col>
      </v-row>
    </v-card>

    <v-card rounded="xl" elevation="0" style="border: 1px solid #e8eaf0">
      <v-data-table
        :headers="headers"
        :items="filteredRetakes"
        :loading="loading"
        hover
        rounded="xl"
      >
        <template #item.grade="{ item }">
          <GradeBadge :grade="item.grade" />
        </template>
        <template #item.periodic_exam.exam_date="{ item }">
          {{ formatDate(item.periodic_exam?.exam_date) }}
        </template>
        <template #item.periodic_exam.organization.name="{ item }">
          {{ item.periodic_exam?.organization?.name || '-' }}
        </template>
        <template #item.actions="{ item }">
          <v-btn
            size="small"
            color="primary"
            variant="flat"
            rounded="lg"
            prepend-icon="mdi-clipboard-edit"
            @click="openRetakeGrade(item)"
          >
            Baho kirish
          </v-btn>
        </template>
      </v-data-table>
    </v-card>

    <!-- Grade entry dialog -->
    <v-dialog v-model="gradeDialog" max-width="520">
      <v-card rounded="xl">
        <v-card-title class="pa-6 d-flex align-center">
          <v-icon icon="mdi-clipboard-edit" class="mr-2" color="primary" />
          Qayta topshirish bahosini kiriting
        </v-card-title>
        <v-divider />
        <v-card-text class="pa-6" v-if="selectedRetake">
          <v-alert type="info" variant="tonal" rounded="lg" class="mb-4">
            <div><strong>Xodim:</strong> {{ selectedRetake.employee?.full_name }}</div>
            <div><strong>Imtihon:</strong> {{ selectedRetake.periodic_exam?.title }}</div>
            <div><strong>Dastlabki baho:</strong> Qoniqarsiz</div>
          </v-alert>
          <v-form ref="gradeFormRef">
            <v-select
              v-model="gradeForm.periodic_exam_id"
              :items="periodicExams"
              item-title="title"
              item-value="id"
              label="Qayta topshirish imtihoni *"
              variant="outlined"
              rounded="lg"
              :rules="[v => !!v || 'Majburiy maydon']"
              class="mb-3"
            />
            <v-select
              v-model="gradeForm.grade"
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
              v-model.number="gradeForm.score"
              label="Ball (0-100)"
              type="number"
              min="0"
              max="100"
              variant="outlined"
              rounded="lg"
              class="mb-3"
            />
            <v-textarea
              v-model="gradeForm.notes"
              label="Izoh"
              variant="outlined"
              rounded="lg"
              rows="2"
            />
          </v-form>
        </v-card-text>
        <v-card-actions class="pa-4">
          <v-spacer />
          <v-btn variant="text" @click="gradeDialog = false">Bekor qilish</v-btn>
          <v-btn color="primary" variant="flat" rounded="lg" :loading="savingGrade" @click="saveRetakeGrade">Saqlash</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../../services/api'
import GradeBadge from '../../components/common/GradeBadge.vue'
import { useAuthStore } from '../../stores/auth'

const authStore = useAuthStore()
const retakes = ref([])
const organizations = ref([])
const periodicExams = ref([])
const loading = ref(false)
const gradeDialog = ref(false)
const savingGrade = ref(false)
const selectedRetake = ref(null)
const search = ref('')
const filterOrg = ref(null)
const gradeFormRef = ref(null)

const gradeOptions = [
  { label: "A'lo", value: 'excellent' },
  { label: 'Yaxshi', value: 'good' },
  { label: 'Qoniqarli', value: 'satisfactory' },
  { label: 'Qoniqarsiz', value: 'unsatisfactory' },
]

const gradeForm = ref({
  periodic_exam_id: null,
  grade: null,
  score: null,
  notes: '',
})

const headers = [
  { title: 'Xodim', key: 'employee.full_name' },
  { title: 'Tashkilot', key: 'periodic_exam.organization.name', sortable: false },
  { title: 'Imtihon', key: 'periodic_exam.title', sortable: false },
  { title: 'Imtihon sanasi', key: 'periodic_exam.exam_date', sortable: false },
  { title: 'Dastlabki baho', key: 'grade', sortable: false },
  { title: 'Amallar', key: 'actions', sortable: false, align: 'end' },
]

const filteredRetakes = computed(() => {
  let list = retakes.value
  if (filterOrg.value) {
    list = list.filter(r => r.periodic_exam?.organization_id === filterOrg.value)
  }
  if (search.value) {
    const q = search.value.toLowerCase()
    list = list.filter(r => r.employee?.full_name?.toLowerCase().includes(q))
  }
  return list
})

function formatDate(d) {
  if (!d) return '-'
  const date = new Date(d)
  return `${String(date.getDate()).padStart(2,'0')}.${String(date.getMonth()+1).padStart(2,'0')}.${date.getFullYear()}`
}

function openRetakeGrade(item) {
  selectedRetake.value = item
  gradeForm.value = {
    periodic_exam_id: item.periodic_exam_id,
    grade: null,
    score: null,
    notes: '',
  }
  gradeDialog.value = true
}

async function fetchAll() {
  loading.value = true
  try {
    const [retakeRes, orgRes, examRes] = await Promise.all([
      api.get('/retakes'),
      api.get('/organizations'),
      api.get('/periodic-exams'),
    ])
    retakes.value = retakeRes.data.data || retakeRes.data
    organizations.value = orgRes.data.data || orgRes.data
    periodicExams.value = examRes.data.data || examRes.data
  } catch (e) {}
  loading.value = false
}

async function saveRetakeGrade() {
  const { valid } = await gradeFormRef.value.validate()
  if (!valid) return
  savingGrade.value = true
  try {
    await api.post(`/retakes/${selectedRetake.value.id}`, gradeForm.value)
    gradeDialog.value = false
    await fetchAll()
  } catch (e) {
    console.error(e?.response?.data)
  }
  savingGrade.value = false
}

onMounted(fetchAll)
</script>
