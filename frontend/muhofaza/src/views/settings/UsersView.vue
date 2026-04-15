<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-6">
      <div class="text-h5 font-weight-bold" style="color: #0D1B2A">Foydalanuvchilar</div>
      <v-btn color="primary" prepend-icon="mdi-plus" rounded="lg" @click="openCreate">
        Yangi Foydalanuvchi
      </v-btn>
    </div>

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
        :items="filteredUsers"
        :loading="loading"
        hover
        rounded="xl"
      >
        <template #item.role="{ item }">
          <v-chip :color="item.role === 'admin' ? 'primary' : 'grey'" size="small" label>
            {{ item.role === 'admin' ? 'Administrator' : 'Operator' }}
          </v-chip>
        </template>
        <template #item.organization="{ item }">{{ item.organization?.name || '-' }}</template>
        <template #item.created_at="{ item }">{{ formatDate(item.created_at) }}</template>
        <template #item.actions="{ item }">
          <v-btn icon="mdi-pencil" variant="text" size="small" color="primary" @click="openEdit(item)" />
          <v-btn
            icon="mdi-delete"
            variant="text"
            size="small"
            color="error"
            @click="openDelete(item)"
            :disabled="item.id === currentUser?.id"
          />
        </template>
      </v-data-table>
    </v-card>

    <!-- Create/Edit Dialog -->
    <v-dialog v-model="dialog" max-width="500">
      <v-card rounded="xl">
        <v-card-title class="pa-6 d-flex align-center">
          <v-icon :icon="editItem ? 'mdi-pencil' : 'mdi-plus'" class="mr-2" color="primary" />
          {{ editItem ? "Foydalanuvchini tahrirlash" : "Yangi Foydalanuvchi" }}
        </v-card-title>
        <v-divider />
        <v-card-text class="pa-6">
          <v-form ref="formRef" @submit.prevent="save">
            <v-text-field
              v-model="form.name"
              label="Ism *"
              variant="outlined"
              rounded="lg"
              :rules="[v => !!v || 'Majburiy maydon']"
              class="mb-3"
            />
            <v-text-field
              v-model="form.username"
              label="Login *"
              variant="outlined"
              rounded="lg"
              :rules="[v => !!v || 'Majburiy maydon']"
              class="mb-3"
            />
            <v-text-field
              v-model="form.email"
              label="Email"
              variant="outlined"
              rounded="lg"
              class="mb-3"
            />
            <v-select
              v-model="form.role"
              :items="roles"
              item-title="label"
              item-value="value"
              label="Rol *"
              variant="outlined"
              rounded="lg"
              :rules="[v => !!v || 'Majburiy maydon']"
              class="mb-3"
              @update:model-value="onRoleChange"
            />
            <v-select
              v-if="form.role === 'operator'"
              v-model="form.organization_id"
              :items="organizations"
              item-title="name"
              item-value="id"
              label="Tashkilot *"
              variant="outlined"
              rounded="lg"
              :rules="[v => form.role !== 'operator' || !!v || 'Majburiy maydon']"
              class="mb-3"
            />
            <v-text-field
              v-model="form.password"
              :label="editItem ? 'Yangi parol (o\'zgartirish uchun)' : 'Parol *'"
              :type="showPass ? 'text' : 'password'"
              :append-inner-icon="showPass ? 'mdi-eye-off' : 'mdi-eye'"
              @click:append-inner="showPass = !showPass"
              variant="outlined"
              rounded="lg"
              :rules="editItem ? [] : [v => !!v || 'Majburiy maydon', v => v.length >= 6 || 'Kamida 6 ta belgi']"
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
      title="Foydalanuvchini o'chirish"
      :message="`'${deleteItem?.name}' foydalanuvchisini o'chirmoqchimisiz?`"
      @confirm="deleteUser"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../../services/api'
import ConfirmDialog from '../../components/common/ConfirmDialog.vue'
import { useAuthStore } from '../../stores/auth'

const authStore = useAuthStore()
const users = ref([])
const organizations = ref([])
const loading = ref(false)
const dialog = ref(false)
const saving = ref(false)
const showPass = ref(false)
const editItem = ref(null)
const deleteItem = ref(null)
const search = ref('')
const formRef = ref(null)
const confirmRef = ref(null)

const currentUser = computed(() => authStore.user)

const roles = [
  { label: 'Administrator', value: 'admin' },
  { label: 'Operator', value: 'operator' },
]

const defaultForm = () => ({
  name: '',
  username: '',
  email: '',
  role: 'operator',
  organization_id: null,
  password: '',
})

const form = ref(defaultForm())

const headers = [
  { title: 'Ism', key: 'name' },
  { title: 'Login', key: 'username' },
  { title: 'Email', key: 'email' },
  { title: 'Rol', key: 'role' },
  { title: 'Tashkilot', key: 'organization' },
  { title: 'Yaratilgan', key: 'created_at' },
  { title: 'Amallar', key: 'actions', sortable: false, align: 'end' },
]

const filteredUsers = computed(() => {
  if (!search.value) return users.value
  const q = search.value.toLowerCase()
  return users.value.filter(u => u.name?.toLowerCase().includes(q) || u.username?.toLowerCase().includes(q))
})

function formatDate(d) {
  if (!d) return '-'
  const date = new Date(d)
  return `${String(date.getDate()).padStart(2,'0')}.${String(date.getMonth()+1).padStart(2,'0')}.${date.getFullYear()}`
}

function onRoleChange(role) {
  if (role === 'admin') form.value.organization_id = null
}

function openCreate() {
  editItem.value = null
  form.value = defaultForm()
  showPass.value = false
  dialog.value = true
}

function openEdit(item) {
  editItem.value = item
  form.value = {
    name: item.name,
    username: item.username,
    email: item.email || '',
    role: item.role,
    organization_id: item.organization_id || null,
    password: '',
  }
  showPass.value = false
  dialog.value = true
}

function openDelete(item) {
  deleteItem.value = item
  confirmRef.value.open()
}

async function fetchAll() {
  loading.value = true
  try {
    const [userRes, orgRes] = await Promise.all([
      api.get('/users'),
      api.get('/organizations'),
    ])
    users.value = userRes.data.data || userRes.data
    organizations.value = orgRes.data.data || orgRes.data
  } catch (e) {}
  loading.value = false
}

async function save() {
  const { valid } = await formRef.value.validate()
  if (!valid) return
  saving.value = true
  try {
    const payload = { ...form.value }
    if (!payload.password) delete payload.password
    if (editItem.value) {
      await api.put(`/users/${editItem.value.id}`, payload)
    } else {
      await api.post('/users', payload)
    }
    dialog.value = false
    await fetchAll()
  } catch (e) {}
  saving.value = false
}

async function deleteUser() {
  try {
    await api.delete(`/users/${deleteItem.value.id}`)
    await fetchAll()
  } catch (e) {}
}

onMounted(fetchAll)
</script>
