<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-6">
      <div class="text-h5 font-weight-bold" style="color: #0D1B2A">Davriy Imtihonlar</div>
      <v-btn color="primary" prepend-icon="mdi-plus" rounded="lg" @click="openCreate">
        Yangi Imtihon
      </v-btn>
    </div>

    <!-- Filters -->
    <v-card rounded="xl" elevation="0" style="border: 1px solid #e8eaf0" class="mb-4 pa-4">
      <v-row dense>
        <v-col cols="12" sm="6" md="3">
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
        <v-col cols="12" sm="6" md="2">
          <v-select
            v-model="filterStatus"
            :items="statusOptions"
            item-title="label"
            item-value="value"
            label="Holat"
            variant="outlined"
            density="compact"
            hide-details
            rounded="lg"
            clearable
          />
        </v-col>
        <v-col cols="12" sm="6" md="2">
          <v-select
            v-model="filterType"
            :items="examTypes"
            item-title="name"
            item-value="id"
            label="Tur"
            variant="outlined"
            density="compact"
            hide-details
            rounded="lg"
            clearable
          />
        </v-col>
        <v-col cols="12" sm="6" md="2">
          <v-text-field
            v-model="filterYear"
            label="Yil"
            type="number"
            variant="outlined"
            density="compact"
            hide-details
            rounded="lg"
          />
        </v-col>
        <v-col cols="12" md="3">
          <v-text-field
            v-model="search"
            placeholder="Qidirish..."
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
        :items="filteredExams"
        :loading="loading"
        hover
        rounded="xl"
      >
        <template #item.exam_date="{ item }">{{ formatDate(item.exam_date) }}</template>
        <template #item.next_exam_date="{ item }">{{ formatDate(item.next_exam_date) }}</template>
        <template #item.status="{ item }">
          <v-chip size="small" :color="statusColor(item.status)">{{ statusText(item.status) }}</v-chip>
        </template>
        <template #item.frequency_years="{ item }">{{ item.frequency_months }} oy</template>
        <template #item.actions="{ item }">
          <v-btn icon="mdi-eye" variant="text" size="small" color="primary" :to="`/exams/periodic/${item.id}`" />
          <v-btn icon="mdi-pencil" variant="text" size="small" color="warning" @click="openEdit(item)" />
          <v-btn icon="mdi-delete" variant="text" size="small" color="error" @click="openDelete(item)" />
        </template>
      </v-data-table>
    </v-card>

    <!-- Create/Edit Dialog -->
    <v-dialog v-model="dialog" max-width="700" scrollable>
      <v-card rounded="xl">
        <v-card-title class="pa-6 d-flex align-center">
          <v-icon :icon="editItem ? 'mdi-pencil' : 'mdi-plus'" class="mr-2" color="primary" />
          {{ editItem ? "Imtihonni tahrirlash" : "Yangi Davriy Imtihon" }}
        </v-card-title>
        <v-divider />
        <v-card-text class="pa-6" style="max-height: 70vh; overflow-y: auto">
          <v-form ref="formRef" @submit.prevent="save">
            <v-row dense>
              <v-col cols="12" sm="6" v-if="authStore.isAdmin">
                <v-select
                  v-model="form.organization_id"
                  :items="organizations"
                  item-title="name"
                  item-value="id"
                  label="Tashkilot *"
                  variant="outlined"
                  rounded="lg"
                  :rules="[v => !!v || 'Majburiy maydon']"
                  @update:model-value="onDialogOrgChange"
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-select
                  v-model="form.exam_type_id"
                  :items="examTypes"
                  item-title="name"
                  item-value="id"
                  label="Imtihon turi *"
                  variant="outlined"
                  rounded="lg"
                  :rules="[v => !!v || 'Majburiy maydon']"
                  @update:model-value="onTypeChange"
                />
              </v-col>
              <v-col cols="12">
                <v-text-field
                  v-model="form.title"
                  label="Sarlavha *"
                  variant="outlined"
                  rounded="lg"
                  :rules="[v => !!v || 'Majburiy maydon']"
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="form.exam_date"
                  label="Imtihon sanasi *"
                  type="date"
                  variant="outlined"
                  rounded="lg"
                  :rules="[v => !!v || 'Majburiy maydon']"
                  @update:model-value="calcNextDate"
                />
              </v-col>
              <v-col cols="12" sm="3">
                <v-text-field
                  v-model.number="form.frequency_months"
                  label="Chastota (oy) *"
                  placeholder="6, 12, 36..."
                  type="number"
                  min="1"
                  variant="outlined"
                  rounded="lg"
                  suffix="oy"
                  :rules="[v => (!!v && v >= 1) || 'Kamida 1 oy kiritilsin']"
                  @update:model-value="calcNextDate"
                />
              </v-col>
              <v-col cols="12" sm="3">
                <v-text-field
                  v-model="nextDateDisplay"
                  label="Keyingi sana"
                  variant="outlined"
                  rounded="lg"
                  readonly
                  bg-color="#f5f5f5"
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="form.course_start_date"
                  label="Kurs boshlanishi"
                  type="date"
                  variant="outlined"
                  rounded="lg"
                  hint="Sertifikatda ko'rsatiladi"
                  persistent-hint
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="form.course_end_date"
                  label="Kurs tugashi"
                  type="date"
                  variant="outlined"
                  rounded="lg"
                  :rules="[v => !v || !form.course_start_date || v >= form.course_start_date || 'Boshlanish sanasidan oldin bo\'lmasin']"
                />
              </v-col>
              <v-col cols="12">
                <v-select
                  v-model="form.certificate_type_id"
                  :items="certificateTypes"
                  item-title="name"
                  item-value="id"
                  label="Sertifikat turi"
                  variant="outlined"
                  rounded="lg"
                  clearable
                  prepend-inner-icon="mdi-certificate-outline"
                  hint="Sertifikatdagi soat va matn shu turdan olinadi"
                  persistent-hint
                />
              </v-col>
              <v-col cols="12">
                <v-select
                  v-model="form.status"
                  :items="statusOptions"
                  item-title="label"
                  item-value="value"
                  label="Holat"
                  variant="outlined"
                  rounded="lg"
                />
              </v-col>
              <v-col cols="12">
                <v-textarea
                  v-model="form.notes"
                  label="Izoh"
                  variant="outlined"
                  rounded="lg"
                  rows="2"
                />
              </v-col>

              <!-- Employee multi-select -->
              <v-col cols="12">
                <v-divider class="mb-4" />
                <div class="text-subtitle-2 mb-2" style="color: #0D1B2A">Xodimlarni tanlash</div>
                <v-autocomplete
                  v-model="selectedDepartments"
                  :items="dialogDepartments"
                  item-title="name"
                  item-value="id"
                  label="Bo'limlar"
                  hint="Bo'lim tanlansa, uning barcha faol xodimlari qo'shiladi; olib tashlansa — chiqariladi"
                  persistent-hint
                  variant="outlined"
                  rounded="lg"
                  multiple
                  chips
                  closable-chips
                  class="mb-4"
                  @update:model-value="onDepartmentsChange"
                />
                <div class="text-body-2 mb-2" style="color: #555">
                  Tanlangan: <strong>{{ form.employee_ids.length }} ta xodim</strong>
                  <span style="color: #999"> — ro'yxatdan qo'lda olib tashlash yoki qo'shish mumkin</span>
                </div>
                <v-autocomplete
                  v-model="form.employee_ids"
                  :items="dialogEmployees"
                  item-title="full_name"
                  item-value="id"
                  label="Xodimlar"
                  variant="outlined"
                  rounded="lg"
                  multiple
                  chips
                  closable-chips
                  :loading="loadingEmployees"
                />
              </v-col>
            </v-row>
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
      title="Imtihonni o'chirish"
      :message="`'${deleteItem?.title}' imtihonini o'chirmoqchimisiz?`"
      @confirm="deleteExam"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../../services/api'
import ConfirmDialog from '../../components/common/ConfirmDialog.vue'
import { useAuthStore } from '../../stores/auth'

const authStore = useAuthStore()
const exams = ref([])
const organizations = ref([])
const examTypes = ref([])
const certificateTypes = ref([])
const dialogEmployees = ref([])
const dialogDepartments = ref([])
const loading = ref(false)
const dialog = ref(false)
const saving = ref(false)
const loadingEmployees = ref(false)
const editItem = ref(null)
const deleteItem = ref(null)
const search = ref('')
const filterOrg = ref(null)
const filterStatus = ref(null)
const filterType = ref(null)
const filterYear = ref(null)
const selectedDepartments = ref([])
let previousDepartments = []
const nextDateDisplay = ref('')
const formRef = ref(null)
const confirmRef = ref(null)

const statusOptions = [
  { label: 'Rejalashtirilgan', value: 'planned' },
  { label: 'Jarayonda', value: 'in_progress' },
  { label: 'Tugallangan', value: 'completed' },
]

const defaultForm = () => ({
  title: '',
  organization_id: authStore.isAdmin ? null : authStore.organizationId,
  exam_type_id: null,
  exam_date: '',
  frequency_months: 12,
  status: 'planned',
  notes: '',
  employee_ids: [],
  course_start_date: '',
  course_end_date: '',
  certificate_type_id: null,
})

const form = ref(defaultForm())

const headers = [
  { title: 'Sarlavha', key: 'title' },
  { title: 'Tashkilot', key: 'organization.name' },
  { title: 'Tur', key: 'exam_type.name' },
  { title: 'Sana', key: 'exam_date' },
  { title: 'Keyingi', key: 'next_exam_date' },
  { title: 'Chastota (oy)', key: 'frequency_years' },
  { title: 'Holat', key: 'status' },
  { title: 'Amallar', key: 'actions', sortable: false, align: 'end' },
]

function activeEmployeeIdsOf(departmentIds) {
  return dialogEmployees.value
    .filter(e => departmentIds.includes(e.department_id) && e.is_active !== false)
    .map(e => e.id)
}

// Bo'lim qo'shilsa — uning faol xodimlari ro'yxatga qo'shiladi, olib tashlansa — chiqariladi.
function onDepartmentsChange(departmentIds) {
  const added = departmentIds.filter(id => !previousDepartments.includes(id))
  const removed = previousDepartments.filter(id => !departmentIds.includes(id))
  const toRemove = new Set(activeEmployeeIdsOf(removed))
  const next = form.value.employee_ids.filter(id => !toRemove.has(id))
  for (const id of activeEmployeeIdsOf(added)) {
    if (!next.includes(id)) next.push(id)
  }
  form.value.employee_ids = next
  previousDepartments = [...departmentIds]
}

const filteredExams = computed(() => {
  let list = exams.value
  if (filterOrg.value) list = list.filter(e => e.organization_id === filterOrg.value)
  if (filterStatus.value) list = list.filter(e => e.status === filterStatus.value)
  if (filterType.value) list = list.filter(e => e.exam_type_id === filterType.value)
  if (filterYear.value) list = list.filter(e => e.exam_date?.startsWith(String(filterYear.value)))
  if (search.value) {
    const q = search.value.toLowerCase()
    list = list.filter(e => e.title?.toLowerCase().includes(q))
  }
  return list
})

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

function calcNextDate() {
  if (!form.value.exam_date || !form.value.frequency_months) {
    nextDateDisplay.value = ''
    return
  }
  const d = new Date(form.value.exam_date)
  d.setMonth(d.getMonth() + Number(form.value.frequency_months))
  nextDateDisplay.value = formatDate(d.toISOString().split('T')[0])
}

function onTypeChange(typeId) {
  const t = examTypes.value.find(x => x.id === typeId)
  if (t) {
    form.value.frequency_months = t.frequency_months || 12
    calcNextDate()
  }
}

async function onDialogOrgChange(orgId, keepSelection = false) {
  // Tahrirlashda saqlangan ishtirokchilar o'chib ketmasin; tashkilot almashtirilganda esa tanlov tozalanadi.
  if (!keepSelection) form.value.employee_ids = []
  selectedDepartments.value = []
  previousDepartments = []
  if (orgId) {
    loadingEmployees.value = true
    const [empRes, deptRes] = await Promise.all([
      api.get('/employees', { params: { organization_id: orgId } }),
      api.get('/departments', { params: { organization_id: orgId } }),
    ])
    dialogEmployees.value = empRes.data.data || empRes.data
    dialogDepartments.value = deptRes.data.data || deptRes.data
    loadingEmployees.value = false
  } else {
    dialogEmployees.value = []
    dialogDepartments.value = []
  }
}

function openCreate() {
  editItem.value = null
  form.value = defaultForm()
  nextDateDisplay.value = ''
  selectedDepartments.value = []
  previousDepartments = []
  if (!authStore.isAdmin) {
    onDialogOrgChange(authStore.organizationId)
  }
  dialog.value = true
}

function openEdit(item) {
  editItem.value = item
  form.value = {
    title: item.title,
    organization_id: item.organization_id,
    exam_type_id: item.exam_type_id,
    exam_date: item.exam_date,
    frequency_months: item.frequency_months,
    status: item.status,
    notes: item.notes || '',
    employee_ids: item.employees?.map(e => e.id) || [],
    course_start_date: item.course_start_date?.slice(0, 10) || '',
    course_end_date: item.course_end_date?.slice(0, 10) || '',
    certificate_type_id: item.certificate_type_id || null,
  }
  calcNextDate()
  onDialogOrgChange(item.organization_id, true)
  dialog.value = true
}

function openDelete(item) {
  deleteItem.value = item
  confirmRef.value.open()
}

async function fetchAll() {
  loading.value = true
  try {
    const [examRes, orgRes, typeRes, certTypeRes] = await Promise.all([
      api.get('/periodic-exams'),
      api.get('/organizations'),
      api.get('/exam-types'),
      api.get('/certificate-types'),
    ])
    exams.value = examRes.data.data || examRes.data
    organizations.value = orgRes.data.data || orgRes.data
    examTypes.value = typeRes.data.data || typeRes.data
    certificateTypes.value = certTypeRes.data
  } catch (e) {}
  loading.value = false
}

async function save() {
  const { valid } = await formRef.value.validate()
  if (!valid) return
  saving.value = true
  try {
    if (editItem.value) {
      await api.put(`/periodic-exams/${editItem.value.id}`, form.value)
    } else {
      await api.post('/periodic-exams', form.value)
    }
    dialog.value = false
    await fetchAll()
  } catch (e) {}
  saving.value = false
}

async function deleteExam() {
  try {
    await api.delete(`/periodic-exams/${deleteItem.value.id}`)
    await fetchAll()
  } catch (e) {}
}

onMounted(fetchAll)
</script>
