<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-6">
      <div class="text-h5 font-weight-bold" style="color: #0D1B2A">Lavozimlar</div>
      <v-btn color="primary" prepend-icon="mdi-plus" rounded="lg" @click="openCreate">
        Yangi Lavozim
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
        :items="filteredPositions"
        :loading="loading"
        hover
        rounded="xl"
      >
        <template #item.actions="{ item }">
          <v-btn icon="mdi-pencil" variant="text" size="small" color="primary" @click="openEdit(item)" />
          <v-btn icon="mdi-delete" variant="text" size="small" color="error" @click="openDelete(item)" />
        </template>
      </v-data-table>
    </v-card>

    <v-dialog v-model="dialog" max-width="500">
      <v-card rounded="xl">
        <v-card-title class="pa-6 d-flex align-center">
          <v-icon :icon="editItem ? 'mdi-pencil' : 'mdi-plus'" class="mr-2" color="primary" />
          {{ editItem ? "Lavozimni tahrirlash" : "Yangi Lavozim" }}
        </v-card-title>
        <v-divider />
        <v-card-text class="pa-6">
          <v-form ref="formRef" @submit.prevent="save">
            <v-select
              v-if="authStore.isAdmin"
              v-model="form.organization_id"
              :items="organizations"
              item-title="name"
              item-value="id"
              label="Tashkilot *"
              variant="outlined"
              rounded="lg"
              :rules="[v => !!v || 'Majburiy maydon']"
              class="mb-3"
            />
            <v-text-field
              v-model="form.name"
              label="Lavozim nomi *"
              variant="outlined"
              rounded="lg"
              :rules="[v => !!v || 'Majburiy maydon']"
              class="mb-3"
            />
            <v-text-field
              v-model="form.code"
              label="Kod"
              variant="outlined"
              rounded="lg"
              class="mb-3"
            />
            <v-textarea
              v-model="form.description"
              label="Tavsif"
              variant="outlined"
              rounded="lg"
              rows="3"
            />
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
      title="Lavozimni o'chirish"
      :message="`'${deleteItem?.name}' lavozimini o'chirmoqchimisiz?`"
      @confirm="deletePosition"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../../services/api'
import ConfirmDialog from '../../components/common/ConfirmDialog.vue'
import { useAuthStore } from '../../stores/auth'

const authStore = useAuthStore()
const positions = ref([])
const organizations = ref([])
const loading = ref(false)
const dialog = ref(false)
const saving = ref(false)
const editItem = ref(null)
const deleteItem = ref(null)
const search = ref('')
const filterOrg = ref(null)
const formRef = ref(null)
const confirmRef = ref(null)

const form = ref({ name: '', code: '', description: '', organization_id: authStore.isAdmin ? null : authStore.organizationId })

const headers = [
  { title: 'Nomi', key: 'name' },
  { title: 'Kod', key: 'code' },
  { title: 'Tashkilot', key: 'organization.name' },
  { title: 'Tavsif', key: 'description' },
  { title: 'Amallar', key: 'actions', sortable: false, align: 'end' },
]

const filteredPositions = computed(() => {
  let list = positions.value
  if (filterOrg.value) list = list.filter(p => p.organization_id === filterOrg.value)
  if (search.value) {
    const q = search.value.toLowerCase()
    list = list.filter(p => p.name?.toLowerCase().includes(q))
  }
  return list
})

function openCreate() {
  editItem.value = null
  form.value = { name: '', code: '', description: '', organization_id: authStore.isAdmin ? null : authStore.organizationId }
  dialog.value = true
}

function openEdit(item) {
  editItem.value = item
  form.value = { name: item.name, code: item.code || '', description: item.description || '', organization_id: item.organization_id }
  dialog.value = true
}

function openDelete(item) {
  deleteItem.value = item
  confirmRef.value.open()
}

async function fetchAll() {
  loading.value = true
  try {
    const [posRes, orgRes] = await Promise.all([
      api.get('/positions'),
      api.get('/organizations'),
    ])
    positions.value = posRes.data.data || posRes.data
    organizations.value = orgRes.data.data || orgRes.data
  } catch (e) {}
  loading.value = false
}

async function save() {
  const { valid } = await formRef.value.validate()
  if (!valid) return
  saving.value = true
  try {
    if (editItem.value) {
      await api.put(`/positions/${editItem.value.id}`, form.value)
    } else {
      await api.post('/positions', form.value)
    }
    dialog.value = false
    await fetchAll()
  } catch (e) {}
  saving.value = false
}

async function deletePosition() {
  try {
    await api.delete(`/positions/${deleteItem.value.id}`)
    await fetchAll()
  } catch (e) {}
}

onMounted(fetchAll)
</script>
