<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-6">
      <div class="text-h5 font-weight-bold" style="color: #0D1B2A">Tashkilotlar</div>
      <v-btn color="primary" prepend-icon="mdi-plus" rounded="lg" @click="openCreate">
        Yangi Tashkilot
      </v-btn>
    </div>

    <!-- Search -->
    <v-card rounded="xl" elevation="0" style="border: 1px solid #e8eaf0" class="mb-4 pa-4">
      <v-text-field
        v-model="search"
        placeholder="Qidirish..."
        prepend-inner-icon="mdi-magnify"
        variant="outlined"
        density="compact"
        hide-details
        rounded="lg"
        style="max-width: 400px"
      />
    </v-card>

    <v-card rounded="xl" elevation="0" style="border: 1px solid #e8eaf0">
      <v-data-table
        :headers="headers"
        :items="filteredOrgs"
        :loading="loading"
        rounded="xl"
        hover
      >
        <template #item.actions="{ item }">
          <v-btn icon="mdi-pencil" variant="text" size="small" color="primary" @click="openEdit(item)" />
          <v-btn icon="mdi-delete" variant="text" size="small" color="error" @click="openDelete(item)" />
        </template>
        <template #item.created_at="{ item }">{{ formatDate(item.created_at) }}</template>
      </v-data-table>
    </v-card>

    <!-- Create/Edit Dialog -->
    <v-dialog v-model="dialog" max-width="500">
      <v-card rounded="xl">
        <v-card-title class="pa-6 d-flex align-center">
          <v-icon :icon="editItem ? 'mdi-pencil' : 'mdi-plus'" class="mr-2" color="primary" />
          {{ editItem ? "Tashkilotni tahrirlash" : "Yangi Tashkilot" }}
        </v-card-title>
        <v-divider />
        <v-card-text class="pa-6">
          <v-form ref="formRef" @submit.prevent="save">
            <v-text-field
              v-model="form.name"
              label="Nomi *"
              variant="outlined"
              rounded="lg"
              :rules="[v => !!v || 'Majburiy maydon']"
              class="mb-3"
            />
            <v-text-field
              v-model="form.short_name"
              label="Qisqa nomi"
              variant="outlined"
              rounded="lg"
              class="mb-3"
            />
            <v-text-field
              v-model="form.address"
              label="Manzil"
              variant="outlined"
              rounded="lg"
              class="mb-3"
            />
            <v-text-field
              v-model="form.phone"
              label="Telefon"
              variant="outlined"
              rounded="lg"
              class="mb-3"
            />
            <v-text-field
              v-model="form.email"
              label="Email"
              variant="outlined"
              rounded="lg"
            />
          </v-form>
        </v-card-text>
        <v-card-actions class="pa-4">
          <v-spacer />
          <v-btn variant="text" @click="dialog = false">Bekor qilish</v-btn>
          <v-btn color="primary" variant="flat" rounded="lg" :loading="saving" @click="save">
            Saqlash
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Confirm Delete -->
    <ConfirmDialog
      ref="confirmRef"
      title="Tashkilotni o'chirish"
      :message="`'${deleteItem?.name}' tashkilotini o'chirmoqchimisiz?`"
      @confirm="deleteOrg"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../../services/api'
import ConfirmDialog from '../../components/common/ConfirmDialog.vue'

const orgs = ref([])
const loading = ref(false)
const dialog = ref(false)
const saving = ref(false)
const editItem = ref(null)
const deleteItem = ref(null)
const search = ref('')
const formRef = ref(null)
const confirmRef = ref(null)

const form = ref({
  name: '',
  short_name: '',
  address: '',
  phone: '',
  email: ''
})

const headers = [
  { title: 'Nomi', key: 'name' },
  { title: 'Qisqa nomi', key: 'short_name' },
  { title: 'Manzil', key: 'address' },
  { title: 'Telefon', key: 'phone' },
  { title: 'Email', key: 'email' },
  { title: 'Amallar', key: 'actions', sortable: false, align: 'end' },
]

const filteredOrgs = computed(() => {
  if (!search.value) return orgs.value
  const q = search.value.toLowerCase()
  return orgs.value.filter(o => o.name?.toLowerCase().includes(q) || o.short_name?.toLowerCase().includes(q))
})

function formatDate(d) {
  if (!d) return '-'
  const date = new Date(d)
  return `${String(date.getDate()).padStart(2,'0')}.${String(date.getMonth()+1).padStart(2,'0')}.${date.getFullYear()}`
}

function openCreate() {
  editItem.value = null
  form.value = { name: '', short_name: '', address: '', phone: '', email: '' }
  dialog.value = true
}

function openEdit(item) {
  editItem.value = item
  form.value = { name: item.name, short_name: item.short_name || '', address: item.address || '', phone: item.phone || '', email: item.email || '' }
  dialog.value = true
}

function openDelete(item) {
  deleteItem.value = item
  confirmRef.value.open()
}

async function fetchOrgs() {
  loading.value = true
  try {
    const res = await api.get('/organizations')
    orgs.value = res.data.data || res.data
  } catch (e) {}
  loading.value = false
}

async function save() {
  const { valid } = await formRef.value.validate()
  if (!valid) return
  saving.value = true
  try {
    if (editItem.value) {
      await api.put(`/organizations/${editItem.value.id}`, form.value)
    } else {
      await api.post('/organizations', form.value)
    }
    dialog.value = false
    await fetchOrgs()
  } catch (e) {}
  saving.value = false
}

async function deleteOrg() {
  try {
    await api.delete(`/organizations/${deleteItem.value.id}`)
    await fetchOrgs()
  } catch (e) {}
}

onMounted(fetchOrgs)
</script>
