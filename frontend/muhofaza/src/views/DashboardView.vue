<template>
  <div>
    <div class="text-h5 font-weight-bold mb-6" style="color: #0D1B2A">Dashboard</div>

    <!-- Stat Cards -->
    <v-row class="mb-6">
      <v-col cols="12" sm="6" md="3">
        <StatCard title="Jami Xodimlar" :value="stats.total_employees || 0" icon="mdi-account-group" iconBg="primary" />
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <StatCard title="Bu oy imtihonlar" :value="stats.this_month_exams || 0" icon="mdi-clipboard-text" iconBg="success" />
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <StatCard title="30 kunda yaqinlashadi" :value="stats.upcoming_30_days || 0" icon="mdi-clock-alert" iconBg="warning" color="#F57C00" />
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <StatCard title="Qoniqarsiz natijalar" :value="stats.unsatisfactory_results || 0" icon="mdi-close-circle" iconBg="error" color="#D32F2F" />
      </v-col>
    </v-row>

    <!-- Imtihonlar qatori -->
    <v-row class="mb-6">
      <v-col cols="12" md="6">
        <v-card rounded="xl" elevation="0" style="border: 1px solid #e8eaf0">
          <v-card-title class="pa-5 d-flex align-center">
            <v-icon icon="mdi-clock-alert" color="warning" class="mr-2" />
            <span style="color: #0D1B2A">Yaqinlashayotgan Imtihonlar</span>
            <v-chip size="small" color="warning" class="ml-2">30 kun</v-chip>
          </v-card-title>
          <v-divider />
          <v-data-table
            :headers="upcomingHeaders"
            :items="upcomingExams"
            :loading="loadingUpcoming"
            density="compact"
            hide-default-footer
            :items-per-page="10"
          >
            <template #item.exam_date="{ item }">{{ formatDate(item.exam_date) }}</template>
            <template #item.next_exam_date="{ item }">{{ formatDate(item.next_exam_date) }}</template>
            <template #item.status="{ item }">
              <v-chip size="x-small" :color="statusColor(item.status)">{{ statusText(item.status) }}</v-chip>
            </template>
          </v-data-table>
        </v-card>
      </v-col>

      <v-col cols="12" md="6">
        <v-card rounded="xl" elevation="0" style="border: 1px solid #e8eaf0">
          <v-card-title class="pa-5 d-flex align-center">
            <v-icon icon="mdi-alert-circle" color="error" class="mr-2" />
            <span style="color: #0D1B2A">Muddati O'tgan Imtihonlar</span>
          </v-card-title>
          <v-divider />
          <v-data-table
            :headers="overdueHeaders"
            :items="overdueExams"
            :loading="loadingOverdue"
            density="compact"
            hide-default-footer
            :items-per-page="10"
          >
            <template #item.exam_date="{ item }">{{ formatDate(item.exam_date) }}</template>
            <template #item.next_exam_date="{ item }">{{ formatDate(item.next_exam_date) }}</template>
          </v-data-table>
        </v-card>
      </v-col>
    </v-row>

    <!-- Xodimlar kesimida yaqinlashayotgan imtihonlar -->
    <v-card rounded="xl" elevation="0" style="border: 1px solid #e8eaf0">
      <v-card-title class="pa-5 d-flex align-center">
        <v-icon icon="mdi-account-clock" color="primary" class="mr-2" />
        <span style="color: #0D1B2A">Xodimlar Bo'yicha Yaqinlashayotgan Imtihonlar</span>
        <v-spacer />
        <v-chip size="small" color="primary" variant="tonal">60 kun ichida</v-chip>
      </v-card-title>
      <v-divider />
      <v-data-table
        :headers="empHeaders"
        :items="upcomingEmployees"
        :loading="loadingEmp"
        density="comfortable"
        hide-default-footer
        :items-per-page="20"
      >
        <template #item.full_name="{ item }">
          <router-link :to="`/employees/${item.employee_id}`" style="color: #1565C0; text-decoration: none; font-weight: 500">
            {{ item.full_name }}
          </router-link>
        </template>
        <template #item.status="{ item }">
          <v-chip
            size="x-small"
            :color="empStatusColor(item.status)"
            label
          >{{ empStatusText(item.status) }}</v-chip>
        </template>
        <template #item.days_remaining="{ item }">
          <span :style="{ fontWeight: '600', color: empStatusColor(item.status) }">
            {{ item.days_remaining < 0 ? Math.abs(item.days_remaining) + ' kun o\'tdi' : item.days_remaining + ' kun qoldi' }}
          </span>
        </template>
      </v-data-table>
    </v-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import StatCard from '../components/common/StatCard.vue'
import api from '../services/api'

const stats = ref({})
const upcomingExams = ref([])
const overdueExams = ref([])
const upcomingEmployees = ref([])
const loadingUpcoming = ref(false)
const loadingOverdue = ref(false)
const loadingEmp = ref(false)

const upcomingHeaders = [
  { title: 'Imtihon', key: 'title', sortable: false },
  { title: 'Tashkilot', key: 'organization.name', sortable: false },
  { title: 'Keyingi sana', key: 'next_exam_date', sortable: false },
  { title: 'Holat', key: 'status', sortable: false },
]

const overdueHeaders = [
  { title: 'Imtihon', key: 'title', sortable: false },
  { title: 'Tashkilot', key: 'organization.name', sortable: false },
  { title: 'Keyingi sana', key: 'next_exam_date', sortable: false },
]

const empHeaders = [
  { title: 'Xodim', key: 'full_name', sortable: false },
  { title: 'Tashkilot', key: 'organization', sortable: false },
  { title: 'Lavozim', key: 'position', sortable: false },
  { title: 'Imtihon turi', key: 'exam_type', sortable: false },
  { title: 'Oxirgi imtihon', key: 'last_exam_date', sortable: false },
  { title: 'Keyingi sana', key: 'next_exam_date', sortable: false },
  { title: 'Holat', key: 'status', sortable: false },
  { title: 'Qolgan muddat', key: 'days_remaining', sortable: false },
]

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

function empStatusColor(s) {
  return { overdue: '#D32F2F', urgent: '#D32F2F', warning: '#F57C00', normal: '#388E3C' }[s] || 'grey'
}

function empStatusText(s) {
  return { overdue: 'Muddati o\'tdi', urgent: 'Shoshilinch', warning: 'Ogohlantirish', normal: 'Oddiy' }[s] || s
}

onMounted(async () => {
  try {
    const [statsRes] = await Promise.all([api.get('/dashboard/stats')])
    stats.value = statsRes.data
  } catch (e) {}

  loadingUpcoming.value = true
  try {
    const res = await api.get('/dashboard/upcoming-exams')
    upcomingExams.value = res.data.data || res.data
  } catch (e) {}
  loadingUpcoming.value = false

  loadingOverdue.value = true
  try {
    const res = await api.get('/dashboard/overdue-exams')
    overdueExams.value = res.data.data || res.data
  } catch (e) {}
  loadingOverdue.value = false

  loadingEmp.value = true
  try {
    const res = await api.get('/dashboard/upcoming-employees')
    upcomingEmployees.value = res.data
  } catch (e) {}
  loadingEmp.value = false
})
</script>
