<template>
  <div>
    <div class="text-h5 font-weight-bold mb-6" style="color: #0D1B2A">Hisobotlar</div>

    <v-card rounded="xl" elevation="0" style="border: 1px solid #e8eaf0" class="mb-5">
      <v-tabs v-model="tab" color="primary" grow>
        <v-tab value="employees"><v-icon start icon="mdi-account-multiple" />Xodimlar</v-tab>
        <v-tab value="exams"><v-icon start icon="mdi-clipboard-text" />Imtihonlar</v-tab>
        <v-tab value="organizations"><v-icon start icon="mdi-domain" />Tashkilotlar</v-tab>
        <v-tab value="annual"><v-icon start icon="mdi-calendar-month" />Yillik Reja</v-tab>
      </v-tabs>
    </v-card>

    <!-- ─── TAB 1: XODIMLAR ─────────────────────────────── -->
    <div v-if="tab === 'employees'">
      <!-- Filters -->
      <v-card rounded="xl" elevation="0" style="border: 1px solid #e8eaf0" class="mb-4 pa-4">
        <v-row dense>
          <v-col cols="12" sm="6" md="3" v-if="authStore.isAdmin">
            <v-select v-model="ef.organization_id" :items="organizations" item-title="name" item-value="id"
              label="Tashkilot" variant="outlined" density="compact" hide-details rounded="lg" clearable />
          </v-col>
          <v-col cols="12" sm="6" md="2">
            <v-select v-model="ef.exam_type_id" :items="examTypes" item-title="name" item-value="id"
              label="Imtihon turi" variant="outlined" density="compact" hide-details rounded="lg" clearable />
          </v-col>
          <v-col cols="12" sm="6" md="2">
            <v-select v-model="ef.year" :items="years" label="Yil" variant="outlined"
              density="compact" hide-details rounded="lg" clearable />
          </v-col>
          <v-col cols="12" sm="6" md="2">
            <v-select v-model="ef.grade"
              :items="[{t:'A\'lo',v:'excellent'},{t:'Yaxshi',v:'good'},{t:'Qoniqarli',v:'satisfactory'},{t:'Qoniqarsiz',v:'unsatisfactory'}]"
              item-title="t" item-value="v"
              label="Baho" variant="outlined" density="compact" hide-details rounded="lg" clearable />
          </v-col>
          <v-col cols="12" sm="6" md="2">
            <v-btn color="primary" variant="flat" rounded="lg" @click="loadEmployees" :loading="eLoading" block>
              <v-icon start icon="mdi-magnify" />Ko'rish
            </v-btn>
          </v-col>
        </v-row>
      </v-card>

      <!-- Summary -->
      <v-row class="mb-4" v-if="eSummary">
        <v-col cols="6" sm="3">
          <SummaryCard title="Jami" :value="eSummary.total" icon="mdi-clipboard-list" color="primary" />
        </v-col>
        <v-col cols="6" sm="3">
          <SummaryCard title="O'tdi" :value="eSummary.passed" icon="mdi-check-circle" color="success" />
        </v-col>
        <v-col cols="6" sm="3">
          <SummaryCard title="O'tmadi" :value="eSummary.failed" icon="mdi-close-circle" color="error" />
        </v-col>
        <v-col cols="6" sm="3">
          <SummaryCard title="O'tish foizi" :value="eSummary.pass_rate + '%'" icon="mdi-percent" color="warning" />
        </v-col>
      </v-row>

      <!-- Table -->
      <v-card rounded="xl" elevation="0" style="border: 1px solid #e8eaf0">
        <v-data-table
          :headers="eHeaders"
          :items="eResults"
          :loading="eLoading"
          hover
          density="comfortable"
        >
          <template #item.index="{ index }">{{ index + 1 }}</template>
          <template #item.employee.full_name="{ item }">
            <router-link :to="`/employees/${item.employee_id}`" class="text-primary text-decoration-none font-weight-medium">
              {{ item.employee?.full_name }}
            </router-link>
          </template>
          <template #item.exam_type="{ item }">{{ item.periodic_exam?.exam_type?.name || '-' }}</template>
          <template #item.exam_year="{ item }">{{ item.periodic_exam?.exam_date?.slice(0,4) || '-' }}</template>
          <template #item.grade="{ item }"><GradeBadge :grade="item.grade" /></template>
          <template #item.is_passed="{ item }">
            <v-icon :icon="item.is_passed ? 'mdi-check-circle' : 'mdi-close-circle'"
              :color="item.is_passed ? 'success' : 'error'" size="20" />
          </template>
        </v-data-table>
      </v-card>
    </div>

    <!-- ─── TAB 2: IMTIHONLAR ───────────────────────────── -->
    <div v-if="tab === 'exams'">
      <v-card rounded="xl" elevation="0" style="border: 1px solid #e8eaf0" class="mb-4 pa-4">
        <v-row dense>
          <v-col cols="12" sm="6" md="3" v-if="authStore.isAdmin">
            <v-select v-model="xf.organization_id" :items="organizations" item-title="name" item-value="id"
              label="Tashkilot" variant="outlined" density="compact" hide-details rounded="lg" clearable />
          </v-col>
          <v-col cols="12" sm="6" md="2">
            <v-select v-model="xf.year" :items="years" label="Yil" variant="outlined"
              density="compact" hide-details rounded="lg" clearable />
          </v-col>
          <v-col cols="12" sm="6" md="2">
            <v-select v-model="xf.month" :items="months" item-title="title" item-value="value"
              label="Oy" variant="outlined" density="compact" hide-details rounded="lg" clearable />
          </v-col>
          <v-col cols="12" sm="6" md="2">
            <v-btn color="primary" variant="flat" rounded="lg" @click="loadExams" :loading="xLoading" block>
              <v-icon start icon="mdi-magnify" />Ko'rish
            </v-btn>
          </v-col>
        </v-row>
      </v-card>

      <!-- Stat cards -->
      <v-row class="mb-5" v-if="xSummary">
        <v-col cols="6" sm="4" md="2">
          <SummaryCard title="Imtihonlar" :value="xSummary.total_exams" icon="mdi-clipboard-text" color="primary" />
        </v-col>
        <v-col cols="6" sm="4" md="2">
          <SummaryCard title="Natijalar" :value="xSummary.total_results" icon="mdi-account-check" color="secondary" />
        </v-col>
        <v-col cols="6" sm="4" md="2">
          <SummaryCard title="O'tdi" :value="xSummary.passed" icon="mdi-check-circle" color="success" />
        </v-col>
        <v-col cols="6" sm="4" md="2">
          <SummaryCard title="O'tmadi" :value="xSummary.failed" icon="mdi-close-circle" color="error" />
        </v-col>
        <v-col cols="6" sm="4" md="2">
          <SummaryCard title="Qayta top." :value="xSummary.retakes" icon="mdi-refresh" color="warning" />
        </v-col>
        <v-col cols="6" sm="4" md="2">
          <SummaryCard title="O'tish %" :value="xSummary.pass_rate + '%'" icon="mdi-percent" color="info" />
        </v-col>
      </v-row>

      <v-row v-if="xSummary">
        <v-col cols="12" md="5">
          <GradeDonutChart :grade-stats="xSummary.grade_stats" />
        </v-col>
        <v-col cols="12" md="7">
          <MonthlyBarChart :monthly="xMonthly" />
        </v-col>
      </v-row>
    </div>

    <!-- ─── TAB 3: TASHKILOTLAR ─────────────────────────── -->
    <div v-if="tab === 'organizations'">
      <v-card rounded="xl" elevation="0" style="border: 1px solid #e8eaf0" class="mb-4 pa-4">
        <v-row dense align="center">
          <v-col cols="12" sm="4" md="2">
            <v-select v-model="of.year" :items="years" label="Yil" variant="outlined"
              density="compact" hide-details rounded="lg" />
          </v-col>
          <v-col cols="auto">
            <v-btn color="primary" variant="flat" rounded="lg" @click="loadOrgs" :loading="oLoading">
              <v-icon start icon="mdi-magnify" />Ko'rish
            </v-btn>
          </v-col>
        </v-row>
      </v-card>

      <v-card rounded="xl" elevation="0" style="border: 1px solid #e8eaf0" v-if="orgData">
        <v-table density="comfortable">
          <thead>
            <tr style="background: #f8f9fa">
              <th style="font-weight:600;color:#0D1B2A">Tashkilot</th>
              <th class="text-center" style="font-weight:600;color:#0D1B2A">Xodimlar</th>
              <th class="text-center" style="font-weight:600;color:#0D1B2A">Imtihonlar</th>
              <th class="text-center" style="font-weight:600;color:#0D1B2A">O'tdi</th>
              <th class="text-center" style="font-weight:600;color:#0D1B2A">O'tmadi</th>
              <th class="text-center" style="font-weight:600;color:#0D1B2A">O'tish %</th>
              <th class="text-center" style="font-weight:600;color:#0D1B2A">Yaqin muddatli</th>
              <th class="text-center" style="font-weight:600;color:#0D1B2A">O'rt. baho</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="org in orgData.organizations" :key="org.id">
              <td>
                <div style="font-weight:600;color:#0D1B2A">{{ org.name }}</div>
                <v-chip size="x-small" :color="org.type === 'central' ? 'primary' : 'secondary'" label>
                  {{ org.type === 'central' ? 'Markaziy' : 'Filial' }}
                </v-chip>
              </td>
              <td class="text-center font-weight-bold">{{ org.employees }}</td>
              <td class="text-center">{{ org.total_results }}</td>
              <td class="text-center" style="color:#388E3C;font-weight:600">{{ org.passed }}</td>
              <td class="text-center" style="color:#D32F2F;font-weight:600">{{ org.failed }}</td>
              <td class="text-center">
                <v-chip size="small" :color="org.pass_rate >= 90 ? 'success' : org.pass_rate >= 70 ? 'warning' : 'error'">
                  {{ org.pass_rate }}%
                </v-chip>
              </td>
              <td class="text-center">
                <v-chip size="small" :color="org.upcoming_count > 0 ? 'warning' : 'success'">
                  {{ org.upcoming_count }}
                </v-chip>
              </td>
              <td class="text-center font-weight-bold" :style="{ color: org.avg_grade >= 4 ? '#388E3C' : '#F57C00' }">
                {{ org.avg_grade }}/5
              </td>
            </tr>
          </tbody>
          <tfoot v-if="orgData.totals" style="background:#f0f4ff">
            <tr>
              <td style="font-weight:700;color:#0D1B2A;padding:12px 16px">JAMI</td>
              <td class="text-center font-weight-bold">{{ orgData.totals.employees }}</td>
              <td class="text-center font-weight-bold">{{ orgData.totals.total_results }}</td>
              <td class="text-center font-weight-bold" style="color:#388E3C">{{ orgData.totals.passed }}</td>
              <td class="text-center font-weight-bold" style="color:#D32F2F">{{ orgData.totals.failed }}</td>
              <td class="text-center">—</td>
              <td class="text-center font-weight-bold">{{ orgData.totals.upcoming_count }}</td>
              <td class="text-center">—</td>
            </tr>
          </tfoot>
        </v-table>
      </v-card>
    </div>

    <!-- ─── TAB 4: YILLIK REJA ──────────────────────────── -->
    <div v-if="tab === 'annual'">
      <v-card rounded="xl" elevation="0" style="border: 1px solid #e8eaf0" class="mb-4 pa-4">
        <v-row dense align="center">
          <v-col cols="12" sm="4" md="2">
            <v-select v-model="af.year" :items="years" label="Yil" variant="outlined"
              density="compact" hide-details rounded="lg" />
          </v-col>
          <v-col cols="12" sm="4" md="3" v-if="authStore.isAdmin">
            <v-select v-model="af.organization_id" :items="organizations" item-title="name" item-value="id"
              label="Tashkilot" variant="outlined" density="compact" hide-details rounded="lg" clearable />
          </v-col>
          <v-col cols="auto">
            <v-btn color="primary" variant="flat" rounded="lg" @click="loadAnnual" :loading="aLoading">
              <v-icon start icon="mdi-magnify" />Ko'rish
            </v-btn>
          </v-col>
        </v-row>
      </v-card>

      <AnnualPlanTable v-if="annualData" :months="annualData.months" :totals="annualData.totals" :year="annualData.year" />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, defineComponent, h } from 'vue'
import { useAuthStore } from '../../stores/auth'
import api from '../../services/api'
import GradeBadge from '../../components/common/GradeBadge.vue'
import GradeDonutChart from '../../components/reports/GradeDonutChart.vue'
import MonthlyBarChart from '../../components/reports/MonthlyBarChart.vue'
import AnnualPlanTable from '../../components/reports/AnnualPlanTable.vue'

const authStore = useAuthStore()
const tab = ref('employees')
const organizations = ref([])
const examTypes = ref([])

const currentYear = new Date().getFullYear()
const years = Array.from({ length: 5 }, (_, i) => currentYear - i)
const months = [
  {title:'Yanvar',value:1},{title:'Fevral',value:2},{title:'Mart',value:3},
  {title:'Aprel',value:4},{title:'May',value:5},{title:'Iyun',value:6},
  {title:'Iyul',value:7},{title:'Avgust',value:8},{title:'Sentabr',value:9},
  {title:'Oktabr',value:10},{title:'Noyabr',value:11},{title:'Dekabr',value:12},
]

// ── Employees tab ──────────────────────────────────────
const ef = ref({ organization_id: null, exam_type_id: null, year: currentYear, grade: null })
const eResults = ref([])
const eSummary = ref(null)
const eLoading = ref(false)
const eHeaders = [
  { title: '№', key: 'index', sortable: false, width: 50 },
  { title: 'Xodim', key: 'employee.full_name' },
  { title: "Bo'lim", key: 'employee.department.name', sortable: false },
  { title: 'Lavozim', key: 'employee.position.name', sortable: false },
  { title: 'Imtihon turi', key: 'exam_type', sortable: false },
  { title: 'Yil', key: 'exam_year', sortable: false },
  { title: 'Baho', key: 'grade', sortable: false },
  { title: "O'tdi", key: 'is_passed', sortable: false },
]

async function loadEmployees() {
  eLoading.value = true
  try {
    const params = {}
    if (ef.value.organization_id) params.organization_id = ef.value.organization_id
    if (ef.value.exam_type_id) params.exam_type_id = ef.value.exam_type_id
    if (ef.value.year) params.year = ef.value.year
    if (ef.value.grade) params.grade = ef.value.grade
    const res = await api.get('/reports/employees', { params })
    eResults.value = res.data.data || []
    eSummary.value = res.data.summary
  } catch (e) {}
  eLoading.value = false
}

// ── Exams tab ──────────────────────────────────────────
const xf = ref({ organization_id: null, year: currentYear, month: null })
const xSummary = ref(null)
const xMonthly = ref({})
const xLoading = ref(false)

async function loadExams() {
  xLoading.value = true
  try {
    const params = {}
    if (xf.value.organization_id) params.organization_id = xf.value.organization_id
    if (xf.value.year) params.year = xf.value.year
    if (xf.value.month) params.month = xf.value.month
    const res = await api.get('/reports/exams', { params })
    xSummary.value = res.data.summary
    xMonthly.value = res.data.monthly || {}
  } catch (e) {}
  xLoading.value = false
}

// ── Organizations tab ──────────────────────────────────
const of = ref({ year: currentYear })
const orgData = ref(null)
const oLoading = ref(false)

async function loadOrgs() {
  oLoading.value = true
  try {
    const res = await api.get('/reports/organizations', { params: { year: of.value.year } })
    orgData.value = res.data
  } catch (e) {}
  oLoading.value = false
}

// ── Annual Plan tab ────────────────────────────────────
const af = ref({ year: currentYear, organization_id: null })
const annualData = ref(null)
const aLoading = ref(false)

async function loadAnnual() {
  aLoading.value = true
  try {
    const params = { year: af.value.year }
    if (af.value.organization_id) params.organization_id = af.value.organization_id
    const res = await api.get('/reports/annual-plan', { params })
    annualData.value = res.data
  } catch (e) {}
  aLoading.value = false
}

// Summary card mini-component
const SummaryCard = defineComponent({
  props: { title: String, value: [String, Number], icon: String, color: String },
  setup(p) {
    return () => h('div', {
      style: {
        background: 'white',
        border: '1px solid #e8eaf0',
        borderRadius: '12px',
        padding: '16px',
        display: 'flex',
        alignItems: 'center',
        gap: '12px',
      }
    }, [
      h('v-avatar', { color: p.color, size: 42, rounded: 'lg' }, {
        default: () => h('v-icon', { icon: p.icon, color: 'white', size: 22 })
      }),
      h('div', [
        h('div', { style: { fontSize: '12px', color: '#888' } }, p.title),
        h('div', { style: { fontSize: '20px', fontWeight: '700', color: '#0D1B2A' } }, String(p.value ?? 0)),
      ])
    ])
  }
})

onMounted(async () => {
  try {
    const [orgRes, typeRes] = await Promise.all([api.get('/organizations'), api.get('/exam-types')])
    organizations.value = orgRes.data.data || orgRes.data
    examTypes.value = typeRes.data.data || typeRes.data
  } catch (e) {}
  // Load initial data for first tab
  await loadEmployees()
})
</script>
