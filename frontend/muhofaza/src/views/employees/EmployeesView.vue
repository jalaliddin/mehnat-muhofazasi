<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-6">
      <div class="text-h5 font-weight-bold" style="color: #0D1B2A">Xodimlar</div>
      <v-btn color="primary" prepend-icon="mdi-plus" rounded="lg" @click="openCreate">
        Yangi Xodim
      </v-btn>
    </div>

    <!-- Filters -->
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
            @update:model-value="onOrgChange"
          />
        </v-col>
        <v-col cols="12" sm="4" md="3">
          <v-select
            v-model="filterDept"
            :items="departments"
            item-title="name"
            item-value="id"
            label="Bo'lim"
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
            placeholder="Qidirish (ism, FIO)..."
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
        :items="filteredEmployees"
        :loading="loading"
        hover
        rounded="xl"
      >
        <template #item.full_name="{ item }">
          <router-link :to="`/employees/${item.id}`" class="text-primary text-decoration-none font-weight-medium">
            {{ item.full_name }}
          </router-link>
        </template>
        <template #item.is_active="{ item }">
          <v-chip size="x-small" :color="item.is_active ? 'success' : 'error'">
            {{ item.is_active ? 'Faol' : 'Nofaol' }}
          </v-chip>
        </template>
        <template #item.actions="{ item }">
          <v-btn icon="mdi-eye" variant="text" size="small" color="primary" :to="`/employees/${item.id}`" />
          <v-btn icon="mdi-pencil" variant="text" size="small" color="warning" @click="openEdit(item)" />
          <v-btn icon="mdi-delete" variant="text" size="small" color="error" @click="openDelete(item)" />
        </template>
      </v-data-table>
    </v-card>

    <!-- Create/Edit Dialog -->
    <v-dialog v-model="dialog" max-width="600">
      <v-card rounded="xl">
        <v-card-title class="pa-6 d-flex align-center">
          <v-icon :icon="editItem ? 'mdi-pencil' : 'mdi-plus'" class="mr-2" color="primary" />
          {{ editItem ? "Xodimni tahrirlash" : "Yangi Xodim" }}
        </v-card-title>
        <v-divider />
        <v-card-text class="pa-6">
          <v-form ref="formRef" @submit.prevent="save">
            <v-row dense>
              <v-col cols="12">
                <v-text-field
                  v-model="form.full_name"
                  label="To'liq ism *"
                  variant="outlined"
                  rounded="lg"
                  :rules="[v => !!v || 'Majburiy maydon']"
                />
              </v-col>
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
                  @update:model-value="loadDepsForForm"
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-select
                  v-model="form.department_id"
                  :items="formDepartments"
                  item-title="name"
                  item-value="id"
                  label="Bo'lim"
                  variant="outlined"
                  rounded="lg"
                  clearable
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-select
                  v-model="form.position_id"
                  :items="positions"
                  item-title="name"
                  item-value="id"
                  label="Lavozim"
                  variant="outlined"
                  rounded="lg"
                  clearable
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="form.personnel_number"
                  label="Shaxsiy raqam"
                  variant="outlined"
                  rounded="lg"
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="form.hire_date"
                  label="Ishga kirgan sana"
                  type="date"
                  variant="outlined"
                  rounded="lg"
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="form.phone"
                  label="Telefon"
                  variant="outlined"
                  rounded="lg"
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-switch
                  v-model="form.is_active"
                  label="Faol xodim"
                  color="success"
                  inset
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
      title="Xodimni o'chirish"
      :message="`'${deleteItem?.full_name}' xodimini o'chirmoqchimisiz?`"
      @confirm="deleteEmployee"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../../services/api'
import ConfirmDialog from '../../components/common/ConfirmDialog.vue'
import { useAuthStore } from '../../stores/auth'

const authStore = useAuthStore()
const employees = ref([])
const organizations = ref([])
const departments = ref([])
const formDepartments = ref([])
const positions = ref([])
const loading = ref(false)
const dialog = ref(false)
const saving = ref(false)
const editItem = ref(null)
const deleteItem = ref(null)
const search = ref('')
const filterOrg = ref(null)
const filterDept = ref(null)
const formRef = ref(null)
const confirmRef = ref(null)

const defaultForm = () => ({
  full_name: '',
  organization_id: authStore.isAdmin ? null : authStore.organizationId,
  department_id: null,
  position_id: null,
  personnel_number: '',
  hire_date: '',
  phone: '',
  is_active: true,
})

const form = ref(defaultForm())

const headers = [
  { title: 'FIO', key: 'full_name' },
  { title: 'Tashkilot', key: 'organization.name' },
  { title: "Bo'lim", key: 'department.name' },
  { title: 'Lavozim', key: 'position.name' },
  { title: 'Shaxsiy raqam', key: 'personnel_number' },
  { title: 'Telefon', key: 'phone' },
  { title: 'Holat', key: 'is_active', sortable: false },
  { title: 'Amallar', key: 'actions', sortable: false, align: 'end' },
]

const filteredEmployees = computed(() => {
  let list = employees.value
  if (filterOrg.value) list = list.filter(e => e.organization_id === filterOrg.value)
  if (filterDept.value) list = list.filter(e => e.department_id === filterDept.value)
  if (search.value) {
    const q = search.value.toLowerCase()
    list = list.filter(e => e.full_name?.toLowerCase().includes(q))
  }
  return list
})

function formatDate(d) {
  if (!d) return '-'
  const date = new Date(d)
  return `${String(date.getDate()).padStart(2,'0')}.${String(date.getMonth()+1).padStart(2,'0')}.${date.getFullYear()}`
}

async function onOrgChange(val) {
  filterDept.value = null
  if (val) {
    const res = await api.get('/departments', { params: { organization_id: val } })
    departments.value = res.data.data || res.data
  } else {
    departments.value = []
  }
}

async function loadDepsForForm(orgId) {
  form.value.department_id = null
  if (orgId) {
    const res = await api.get('/departments', { params: { organization_id: orgId } })
    formDepartments.value = res.data.data || res.data
  }
}

function openCreate() {
  editItem.value = null
  form.value = defaultForm()
  if (!authStore.isAdmin) {
    loadDepsForForm(authStore.organizationId)
  }
  dialog.value = true
}

function openEdit(item) {
  editItem.value = item
  form.value = {
    full_name: item.full_name,
    organization_id: item.organization_id,
    department_id: item.department_id,
    position_id: item.position_id,
    personnel_number: item.personnel_number || '',
    hire_date: item.hire_date || '',
    phone: item.phone || '',
    is_active: item.is_active,
  }
  if (item.organization_id) loadDepsForForm(item.organization_id)
  dialog.value = true
}

function openDelete(item) {
  deleteItem.value = item
  confirmRef.value.open()
}

async function fetchAll() {
  loading.value = true
  try {
    const [empRes, orgRes, posRes] = await Promise.all([
      api.get('/employees'),
      api.get('/organizations'),
      api.get('/positions'),
    ])
    employees.value = empRes.data.data || empRes.data
    organizations.value = orgRes.data.data || orgRes.data
    positions.value = posRes.data.data || posRes.data
  } catch (e) {}
  loading.value = false
}

async function save() {
  const { valid } = await formRef.value.validate()
  if (!valid) return
  saving.value = true
  try {
    if (editItem.value) {
      await api.put(`/employees/${editItem.value.id}`, form.value)
    } else {
      await api.post('/employees', form.value)
    }
    dialog.value = false
    await fetchAll()
  } catch (e) {}
  saving.value = false
}

async function deleteEmployee() {
  try {
    await api.delete(`/employees/${deleteItem.value.id}`)
    await fetchAll()
  } catch (e) {}
}

onMounted(fetchAll)
</script>
