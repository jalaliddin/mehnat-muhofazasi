<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-6">
      <div class="text-h5 font-weight-bold" style="color: #0D1B2A">Buyruqlar</div>
      <v-btn color="primary" prepend-icon="mdi-plus" rounded="lg" @click="openCreate">
        Yangi Buyruq
      </v-btn>
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
        :items="filteredOrders"
        :loading="loading"
        hover
        rounded="xl"
      >
        <template #item.order_date="{ item }">{{ formatDate(item.order_date) }}</template>
        <template #item.file="{ item }">
          <v-btn
            v-if="item.file_path"
            icon="mdi-download"
            variant="text"
            size="small"
            color="primary"
            :href="item.file_url"
            target="_blank"
          />
          <span v-else class="text-caption" style="color: #aaa">-</span>
        </template>
        <template #item.actions="{ item }">
          <v-btn icon="mdi-pencil" variant="text" size="small" color="warning" @click="openEdit(item)" />
          <v-btn icon="mdi-delete" variant="text" size="small" color="error" @click="openDelete(item)" />
        </template>
      </v-data-table>
    </v-card>

    <!-- Create/Edit Dialog -->
    <v-dialog v-model="dialog" max-width="600" scrollable>
      <v-card rounded="xl">
        <v-card-title class="pa-6 d-flex align-center">
          <v-icon :icon="editItem ? 'mdi-pencil' : 'mdi-plus'" class="mr-2" color="primary" />
          {{ editItem ? "Buyruqni tahrirlash" : "Yangi Buyruq" }}
        </v-card-title>
        <v-divider />
        <v-card-text class="pa-6">
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
                />
              </v-col>
              <v-col cols="12" sm="6">
                <v-text-field
                  v-model="form.order_number"
                  label="Buyruq raqami *"
                  variant="outlined"
                  rounded="lg"
                  :rules="[v => !!v || 'Majburiy maydon']"
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
                  v-model="form.order_date"
                  label="Buyruq sanasi *"
                  type="date"
                  variant="outlined"
                  rounded="lg"
                  :rules="[v => !!v || 'Majburiy maydon']"
                />
              </v-col>
              <v-col cols="12">
                <v-textarea
                  v-model="form.description"
                  label="Mazmuni"
                  variant="outlined"
                  rounded="lg"
                  rows="3"
                />
              </v-col>
              <v-col cols="12">
                <v-file-input
                  v-model="fileInput"
                  label="Fayl biriktirish (PDF, Word)"
                  variant="outlined"
                  rounded="lg"
                  prepend-icon="mdi-paperclip"
                  accept=".pdf,.doc,.docx"
                  show-size
                  :clearable="true"
                />
                <div v-if="editItem?.file_path" class="text-caption mt-1" style="color: #1565C0">
                  <v-icon size="small" icon="mdi-file" /> Mavjud fayl: {{ editItem.file_path?.split('/').pop() }}
                </div>
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
      title="Buyruqni o'chirish"
      :message="`'${deleteItem?.title}' buyrug'ini o'chirmoqchimisiz?`"
      @confirm="deleteOrder"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../../services/api'
import ConfirmDialog from '../../components/common/ConfirmDialog.vue'
import { useAuthStore } from '../../stores/auth'

const authStore = useAuthStore()
const orders = ref([])
const organizations = ref([])
const loading = ref(false)
const dialog = ref(false)
const saving = ref(false)
const editItem = ref(null)
const deleteItem = ref(null)
const search = ref('')
const filterOrg = ref(null)
const fileInput = ref(null)
const formRef = ref(null)
const confirmRef = ref(null)

const orderTypes = [
  { label: 'Buyruq', value: 'order' },
  { label: "Farmoyish", value: 'directive' },
  { label: 'Nizom', value: 'regulation' },
  { label: 'Boshqa', value: 'other' },
]

const defaultForm = () => ({
  title: '',
  order_number: '',
  order_date: '',
  description: '',
  organization_id: authStore.isAdmin ? null : authStore.organizationId,
})

const form = ref(defaultForm())

const headers = [
  { title: 'Raqam', key: 'order_number' },
  { title: 'Sarlavha', key: 'title' },
  { title: 'Sana', key: 'order_date' },
  { title: 'Tashkilot', key: 'organization.name' },
  { title: 'Fayl', key: 'file', sortable: false },
  { title: 'Amallar', key: 'actions', sortable: false, align: 'end' },
]

const filteredOrders = computed(() => {
  let list = orders.value
  if (filterOrg.value) list = list.filter(o => o.organization_id === filterOrg.value)
  if (search.value) {
    const q = search.value.toLowerCase()
    list = list.filter(o => o.title?.toLowerCase().includes(q) || o.order_number?.toLowerCase().includes(q))
  }
  return list
})

function formatDate(d) {
  if (!d) return '-'
  const date = new Date(d)
  return `${String(date.getDate()).padStart(2,'0')}.${String(date.getMonth()+1).padStart(2,'0')}.${date.getFullYear()}`
}

function openCreate() {
  editItem.value = null
  form.value = defaultForm()
  fileInput.value = null
  dialog.value = true
}

function openEdit(item) {
  editItem.value = item
  form.value = {
    title: item.title,
    order_number: item.order_number,
    order_date: item.order_date,
    description: item.description || '',
    organization_id: item.organization_id,
  }
  fileInput.value = null
  dialog.value = true
}

function openDelete(item) {
  deleteItem.value = item
  confirmRef.value.open()
}

async function fetchAll() {
  loading.value = true
  try {
    const [orderRes, orgRes] = await Promise.all([
      api.get('/orders'),
      api.get('/organizations'),
    ])
    orders.value = orderRes.data.data || orderRes.data
    organizations.value = orgRes.data.data || orgRes.data
  } catch (e) {}
  loading.value = false
}

async function save() {
  const { valid } = await formRef.value.validate()
  if (!valid) return
  saving.value = true
  try {
    const fd = new FormData()
    Object.entries(form.value).forEach(([k, v]) => { if (v !== null && v !== undefined) fd.append(k, v) })
    if (fileInput.value) {
      const file = Array.isArray(fileInput.value) ? fileInput.value[0] : fileInput.value
      if (file) fd.append('file', file)
    }
    const config = { headers: { 'Content-Type': 'multipart/form-data' } }
    if (editItem.value) {
      fd.append('_method', 'PUT')
      await api.post(`/orders/${editItem.value.id}`, fd, config)
    } else {
      await api.post('/orders', fd, config)
    }
    dialog.value = false
    await fetchAll()
  } catch (e) {}
  saving.value = false
}

async function deleteOrder() {
  try {
    await api.delete(`/orders/${deleteItem.value.id}`)
    await fetchAll()
  } catch (e) {}
}

onMounted(fetchAll)
</script>
