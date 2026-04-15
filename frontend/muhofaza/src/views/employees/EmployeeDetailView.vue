<template>
  <div>
    <div class="d-flex align-center mb-6">
      <v-btn icon="mdi-arrow-left" variant="text" @click="$router.back()" class="mr-3" />
      <div class="text-h5 font-weight-bold" style="color: #0D1B2A">Xodim ma'lumotlari</div>
    </div>

    <div v-if="loading" class="pa-10 text-center">
      <v-progress-circular indeterminate color="primary" />
    </div>

    <v-row v-else-if="employee">
      <!-- Info card -->
      <v-col cols="12" md="4">
        <v-card rounded="xl" elevation="0" style="border: 1px solid #e8eaf0" class="pa-6 mb-4">
          <div class="text-center mb-4">
            <v-avatar color="primary" size="80" class="mb-3">
              <span class="text-h4 text-white font-weight-bold">{{ employee.full_name?.charAt(0)?.toUpperCase() }}</span>
            </v-avatar>
            <div class="text-h6 font-weight-bold" style="color: #0D1B2A">{{ employee.full_name }}</div>
            <div class="text-body-2" style="color: #666">{{ employee.position?.name || '-' }}</div>
          </div>
          <v-divider class="mb-4" />
          <div class="d-flex flex-column">
            <InfoRow label="Tashkilot" :value="employee.organization?.name" />
            <InfoRow label="Bo'lim" :value="employee.department?.name" />
            <InfoRow label="Shaxsiy raqam" :value="employee.personnel_number" />
            <InfoRow label="Ishga kirgan" :value="formatDate(employee.hire_date)" />
            <InfoRow label="Telefon" :value="employee.phone" />
            <div class="d-flex justify-space-between align-center mt-3">
              <span class="text-body-2" style="color: #888">Holat:</span>
              <v-chip size="x-small" :color="employee.is_active ? 'success' : 'error'" label>
                {{ employee.is_active ? 'Faol' : 'Nofaol' }}
              </v-chip>
            </div>
          </div>
        </v-card>

        <!-- Upcoming exams timeline -->
        <UpcomingExamTimeline :employee-id="route.params.id" />
      </v-col>

      <!-- Exam history -->
      <v-col cols="12" md="8">
        <v-card rounded="xl" elevation="0" style="border: 1px solid #e8eaf0">
          <v-card-title class="pa-5 d-flex align-center">
            <v-icon icon="mdi-clipboard-text-clock" color="primary" class="mr-2" />
            <span style="color: #0D1B2A">Imtihon tarixi</span>
            <v-chip size="small" color="primary" class="ml-2" v-if="examResults.length">{{ examResults.length }}</v-chip>
          </v-card-title>
          <v-divider />
          <v-data-table
            :headers="examHeaders"
            :items="examResults"
            :loading="loadingExams"
            hover
            density="comfortable"
          >
            <template #item.exam_name="{ item }">
              <div class="font-weight-medium" style="color: #0D1B2A">{{ item.periodic_exam?.title || '-' }}</div>
              <div class="text-caption" style="color: #888">{{ item.periodic_exam?.exam_type?.name || '' }}</div>
            </template>
            <template #item.exam_date="{ item }">{{ formatDate(item.periodic_exam?.exam_date) }}</template>
            <template #item.grade="{ item }">
              <GradeBadge :grade="item.grade" />
            </template>
            <template #item.is_passed="{ item }">
              <v-icon
                :icon="item.is_passed ? 'mdi-check-circle' : 'mdi-close-circle'"
                :color="item.is_passed ? 'success' : 'error'"
                size="20"
              />
            </template>
            <template #item.score="{ item }">{{ item.score ?? '-' }}</template>
          </v-data-table>
        </v-card>
      </v-col>
    </v-row>
  </div>
</template>

<script setup>
import { ref, onMounted, defineComponent, h } from 'vue'
import { useRoute } from 'vue-router'
import api from '../../services/api'
import GradeBadge from '../../components/common/GradeBadge.vue'
import UpcomingExamTimeline from '../../components/employees/UpcomingExamTimeline.vue'

const route = useRoute()
const employee = ref(null)
const examResults = ref([])
const loading = ref(false)
const loadingExams = ref(false)

const examHeaders = [
  { title: 'Imtihon', key: 'exam_name', sortable: false },
  { title: 'Sana', key: 'exam_date', sortable: false },
  { title: 'Ball', key: 'score', sortable: false },
  { title: 'Baho', key: 'grade', sortable: false },
  { title: "O'tdi", key: 'is_passed', sortable: false },
  { title: 'Izoh', key: 'notes', sortable: false },
]

function formatDate(d) {
  if (!d) return '-'
  const date = new Date(d)
  return `${String(date.getDate()).padStart(2,'0')}.${String(date.getMonth()+1).padStart(2,'0')}.${date.getFullYear()}`
}

// Simple info row
const InfoRow = defineComponent({
  props: { label: String, value: String },
  setup(p) {
    return () => h('div', { class: 'd-flex justify-space-between align-center mt-3' }, [
      h('span', { style: { color: '#888', fontSize: '14px' } }, p.label + ':'),
      h('span', { style: { color: '#0D1B2A', fontSize: '14px', fontWeight: '500', textAlign: 'right', maxWidth: '60%' } }, p.value || '-'),
    ])
  }
})

onMounted(async () => {
  loading.value = true
  loadingExams.value = true
  try {
    const res = await api.get(`/employees/${route.params.id}/exam-history`)
    if (res.data.employee) {
      employee.value = res.data.employee
      examResults.value = res.data.results || []
    } else {
      employee.value = res.data
    }
  } catch (e) {
    try {
      const emp = await api.get(`/employees/${route.params.id}`)
      employee.value = emp.data
    } catch {}
  }
  loading.value = false
  loadingExams.value = false
})
</script>
