<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-6">
      <div class="text-h5 font-weight-bold" style="color: #0D1B2A">Sertifikatlar</div>
    </div>

    <v-card rounded="xl" elevation="0" style="border: 1px solid #e8eaf0" class="mb-4 pa-4">
      <v-row dense>
        <v-col v-if="authStore.isAdmin" cols="12" sm="6" md="3">
          <v-select
            v-model="filters.organization_id"
            :items="organizations"
            item-title="name"
            item-value="id"
            label="Tashkilot"
            variant="outlined"
            density="compact"
            rounded="lg"
            clearable
            hide-details
          />
        </v-col>
        <v-col cols="12" sm="6" md="3">
          <v-select
            v-model="filters.periodic_exam_id"
            :items="exams"
            item-title="title"
            item-value="id"
            label="Imtihon"
            variant="outlined"
            density="compact"
            rounded="lg"
            clearable
            hide-details
          />
        </v-col>
        <v-col cols="12" sm="6" md="3">
          <v-select
            v-model="filters.status"
            :items="statusOptions"
            item-title="label"
            item-value="value"
            label="Holat"
            variant="outlined"
            density="compact"
            rounded="lg"
            clearable
            hide-details
          />
        </v-col>
        <v-col cols="12" sm="6" md="3">
          <v-text-field
            v-model="search"
            placeholder="F.I.O. yoki № bo'yicha"
            prepend-inner-icon="mdi-magnify"
            variant="outlined"
            density="compact"
            rounded="lg"
            clearable
            hide-details
          />
        </v-col>
      </v-row>
    </v-card>

    <v-card rounded="xl" elevation="0" style="border: 1px solid #e8eaf0">
      <v-data-table-server
        v-model:items-per-page="perPage"
        v-model:page="page"
        :headers="headers"
        :items="items"
        :items-length="total"
        :loading="loading"
        hover
        density="comfortable"
        @update:options="fetchItems"
      >
        <template #item.number="{ item }">
          <span class="font-weight-medium">{{ item.number }}</span>
        </template>
        <template #item.employee.organization.name="{ item }">{{ item.employee?.organization?.name || '—' }}</template>
        <template #item.issue_date="{ item }">{{ formatDate(item.data?.issue_date) }}</template>
        <template #item.status="{ item }">
          <v-chip :color="item.status === 'signed' ? 'success' : 'warning'" size="small" label>
            {{ item.status === 'signed' ? 'Imzolangan' : 'Imzo kutilmoqda' }}
          </v-chip>
        </template>
        <template #item.actions="{ item }">
          <div class="d-flex justify-end">
            <v-btn icon="mdi-file-pdf-box" variant="text" size="small" color="primary" title="PDF yuklab olish (imzolanmagan)"
              :loading="busy === `pdf-${item.id}`" @click="downloadGenerated(item)" />
            <v-btn icon="mdi-upload" variant="text" size="small" color="success" title="Imzoli nusxani yuklash"
              :loading="busy === `upload-${item.id}`" @click="pickFile(item)" />
            <v-btn v-if="item.status === 'signed'" icon="mdi-eye" variant="text" size="small" color="info" title="Imzoli nusxani ko'rish"
              @click="viewSigned(item)" />
            <v-btn v-if="item.status !== 'signed'" icon="mdi-refresh" variant="text" size="small" color="warning" title="Qayta yaratish"
              :loading="busy === `regen-${item.id}`" @click="regenerate(item)" />
            <v-btn icon="mdi-delete" variant="text" size="small" color="error" title="O'chirish" @click="askDelete(item)" />
          </div>
        </template>
      </v-data-table-server>
    </v-card>

    <input ref="fileInput" type="file" accept=".pdf,.jpg,.jpeg,.png" style="display: none" @change="uploadSigned" />

    <ConfirmDialog
      ref="confirmRef"
      title="Sertifikatni o'chirish"
      :message="deleteItem ? `№ ${deleteItem.number} sertifikati va uning fayllari o'chiriladi.` : ''"
      @confirm="destroy"
    />

    <v-snackbar v-model="snackbar" :color="snackbarColor" timeout="4000">{{ snackbarText }}</v-snackbar>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import api from '../../services/api'
import { downloadFile, openFile } from '../../services/download'
import ConfirmDialog from '../../components/common/ConfirmDialog.vue'
import { useAuthStore } from '../../stores/auth'

const route = useRoute()
const authStore = useAuthStore()

const items = ref([])
const total = ref(0)
const page = ref(1)
const perPage = ref(25)
const loading = ref(false)
const busy = ref('')
const organizations = ref([])
const exams = ref([])
const search = ref('')
const filters = ref({
  organization_id: null,
  periodic_exam_id: route.query.periodic_exam_id ? Number(route.query.periodic_exam_id) : null,
  status: null,
})
const fileInput = ref(null)
const uploadTarget = ref(null)
const confirmRef = ref(null)
const deleteItem = ref(null)
const snackbar = ref(false)
const snackbarText = ref('')
const snackbarColor = ref('success')

const statusOptions = [
  { label: 'Imzo kutilmoqda', value: 'pending_signature' },
  { label: 'Imzolangan', value: 'signed' },
]

const headers = [
  { title: '№', key: 'number', sortable: false },
  { title: 'F.I.O.', key: 'employee.full_name', sortable: false },
  { title: 'Tashkilot', key: 'employee.organization.name', sortable: false },
  { title: 'Imtihon', key: 'periodic_exam.title', sortable: false },
  { title: 'Berilgan sana', key: 'issue_date', sortable: false },
  { title: 'Holat', key: 'status', sortable: false },
  { title: 'Amallar', key: 'actions', sortable: false, align: 'end' },
]

function notify(text, color = 'success') {
  snackbarText.value = text
  snackbarColor.value = color
  snackbar.value = true
}

function formatDate(d) {
  if (!d) return '—'
  const [y, m, day] = d.slice(0, 10).split('-')
  return `${day}.${m}.${y}`
}

async function fetchItems() {
  loading.value = true
  try {
    const params = { page: page.value, per_page: perPage.value }
    for (const [key, value] of Object.entries(filters.value)) {
      if (value) params[key] = value
    }
    if (search.value) params.search = search.value
    const res = await api.get('/certificates', { params })
    items.value = res.data.data
    total.value = res.data.total
  } catch (e) {
    notify(e.response?.data?.message || "Ro'yxatni yuklab bo'lmadi", 'error')
  }
  loading.value = false
}

function refetchFromFirstPage() {
  if (page.value !== 1) page.value = 1 // v-data-table-server fetchItems ni o'zi chaqiradi
  else fetchItems()
}

watch(filters, refetchFromFirstPage, { deep: true })

let searchTimer = null
watch(search, () => {
  clearTimeout(searchTimer)
  searchTimer = setTimeout(refetchFromFirstPage, 300)
})

async function downloadGenerated(item) {
  busy.value = `pdf-${item.id}`
  try {
    await downloadFile(`/certificates/${item.id}/download`, {}, `sertifikat-${item.number}.pdf`)
  } catch (e) {
    notify(e.message, 'error')
  }
  busy.value = ''
}

async function viewSigned(item) {
  try {
    await openFile(`/certificates/${item.id}/download`, { type: 'signed' })
  } catch (e) {
    notify(e.message, 'error')
  }
}

function pickFile(item) {
  uploadTarget.value = item
  fileInput.value.value = ''
  fileInput.value.click()
}

async function uploadSigned(event) {
  const file = event.target.files?.[0]
  const item = uploadTarget.value
  if (!file || !item) return
  busy.value = `upload-${item.id}`
  try {
    const formData = new FormData()
    formData.append('file', file)
    await api.post(`/certificates/${item.id}/signed`, formData, { headers: { 'Content-Type': 'multipart/form-data' } })
    notify(`№ ${item.number} — imzoli nusxa yuklandi`)
    await fetchItems()
  } catch (e) {
    const errors = e.response?.data?.errors?.file
    notify(errors?.[0] || e.response?.data?.message || "Faylni yuklab bo'lmadi", 'error')
  }
  busy.value = ''
}

async function regenerate(item) {
  busy.value = `regen-${item.id}`
  try {
    await api.post(`/certificates/${item.id}/regenerate`)
    notify(`№ ${item.number} qayta yaratildi`)
    await fetchItems()
  } catch (e) {
    notify(e.response?.data?.message || "Qayta yaratib bo'lmadi", 'error')
  }
  busy.value = ''
}

function askDelete(item) {
  deleteItem.value = item
  confirmRef.value.open()
}

async function destroy() {
  try {
    await api.delete(`/certificates/${deleteItem.value.id}`)
    notify("O'chirildi")
    await fetchItems()
  } catch (e) {
    notify(e.response?.data?.message || "O'chirib bo'lmadi", 'error')
  }
}

onMounted(async () => {
  try {
    const [examsRes, orgsRes] = await Promise.all([
      api.get('/periodic-exams'),
      authStore.isAdmin ? api.get('/organizations') : Promise.resolve({ data: [] }),
    ])
    exams.value = examsRes.data.data || examsRes.data
    organizations.value = orgsRes.data.data || orgsRes.data
  } catch (e) {
    // filtrlar bo'sh qoladi, ro'yxat baribir ishlaydi
  }
})
</script>
