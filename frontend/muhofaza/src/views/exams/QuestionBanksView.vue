<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-6">
      <div class="text-h5 font-weight-bold" style="color: #0D1B2A">Savollar bazalari</div>
      <v-btn v-if="authStore.isAdmin" color="primary" prepend-icon="mdi-plus" rounded="lg" @click="openCreate">
        Yangi baza
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
        :headers="visibleHeaders"
        :items="filteredBanks"
        :loading="loading"
        hover
        @click:row="(_, { item }) => $router.push(`/exams/question-banks/${item.id}`)"
        style="cursor: pointer"
      >
        <template #item.name="{ item }">
          <div class="font-weight-medium">{{ item.name }}</div>
          <div v-if="item.description" class="text-caption" style="color: #888">{{ item.description }}</div>
        </template>
        <template #item.questions_count="{ item }">
          <v-chip size="small" :color="item.questions_count ? 'primary' : 'grey'" variant="tonal">
            {{ item.questions_count }} ta savol
          </v-chip>
        </template>
        <template #item.exam_types="{ item }">
          <span v-if="!item.exam_types.length" style="color: #999">—</span>
          <div v-for="t in item.exam_types" :key="t.id" class="text-body-2">{{ t.name }} ({{ t.question_count }} ta)</div>
        </template>
        <template #item.actions="{ item }">
          <div class="d-flex justify-end" @click.stop>
            <v-btn icon="mdi-pencil" variant="text" size="small" color="primary" @click="openEdit(item)" />
            <v-btn icon="mdi-delete" variant="text" size="small" color="error" @click="askDelete(item)" />
          </div>
        </template>
      </v-data-table>
    </v-card>

    <v-dialog v-model="dialog" max-width="520">
      <v-card rounded="xl">
        <v-card-title class="pa-6 d-flex align-center">
          <v-icon :icon="editItem ? 'mdi-pencil' : 'mdi-plus'" class="mr-2" color="primary" />
          {{ editItem ? 'Bazani tahrirlash' : 'Yangi baza' }}
        </v-card-title>
        <v-divider />
        <v-card-text class="pa-6">
          <v-form ref="formRef" @submit.prevent="save">
            <v-text-field
              v-model="form.name"
              label="Baza nomi *"
              placeholder="Masalan: Elektr xavfsizligi"
              variant="outlined"
              rounded="lg"
              :rules="[v => !!v?.trim() || 'Majburiy maydon']"
              :error-messages="nameError"
              class="mb-3"
            />
            <v-textarea v-model="form.description" label="Tavsif" variant="outlined" rounded="lg" rows="3" />
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
      title="Bazani o'chirish"
      :message="deleteItem ? `«${deleteItem.name}» bazasi va undagi ${deleteItem.questions_count} ta savol o'chiriladi.` : ''"
      @confirm="destroy"
    />

    <v-snackbar v-model="snackbar" :color="snackbarColor" timeout="5000">{{ snackbarText }}</v-snackbar>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../../services/api'
import ConfirmDialog from '../../components/common/ConfirmDialog.vue'
import { useAuthStore } from '../../stores/auth'

const authStore = useAuthStore()

const banks = ref([])
const loading = ref(false)
const search = ref('')
const dialog = ref(false)
const saving = ref(false)
const editItem = ref(null)
const deleteItem = ref(null)
const formRef = ref(null)
const confirmRef = ref(null)
const nameError = ref('')
const form = ref({ name: '', description: '' })
const snackbar = ref(false)
const snackbarText = ref('')
const snackbarColor = ref('success')

const headers = [
  { title: 'Nomi', key: 'name' },
  { title: 'Savollar soni', key: 'questions_count' },
  { title: 'Testlarda', key: 'exam_types', sortable: false },
  { title: 'Amallar', key: 'actions', sortable: false, align: 'end' },
]
const visibleHeaders = computed(() => authStore.isAdmin ? headers : headers.filter(h => h.key !== 'actions'))

const filteredBanks = computed(() => {
  const q = search.value.trim().toLowerCase()
  return q ? banks.value.filter(b => b.name.toLowerCase().includes(q)) : banks.value
})

function notify(text, color = 'success') {
  snackbarText.value = text
  snackbarColor.value = color
  snackbar.value = true
}

async function fetchBanks() {
  loading.value = true
  try {
    banks.value = (await api.get('/question-banks')).data
  } catch (e) {
    notify("Bazalarni yuklab bo'lmadi", 'error')
  }
  loading.value = false
}

function openCreate() {
  editItem.value = null
  form.value = { name: '', description: '' }
  nameError.value = ''
  dialog.value = true
}

function openEdit(item) {
  editItem.value = item
  form.value = { name: item.name, description: item.description || '' }
  nameError.value = ''
  dialog.value = true
}

async function save() {
  const { valid } = await formRef.value.validate()
  if (!valid) return
  saving.value = true
  nameError.value = ''
  try {
    if (editItem.value) {
      await api.put(`/question-banks/${editItem.value.id}`, form.value)
    } else {
      await api.post('/question-banks', form.value)
    }
    dialog.value = false
    notify('Saqlandi')
    await fetchBanks()
  } catch (e) {
    const errors = e.response?.data?.errors
    if (errors?.name) nameError.value = errors.name[0]
    else notify(e.response?.data?.message || "Saqlab bo'lmadi", 'error')
  }
  saving.value = false
}

function askDelete(item) {
  deleteItem.value = item
  confirmRef.value.open()
}

async function destroy() {
  try {
    await api.delete(`/question-banks/${deleteItem.value.id}`)
    notify("O'chirildi")
    await fetchBanks()
  } catch (e) {
    notify(e.response?.data?.message || "O'chirib bo'lmadi", 'error')
  }
}

onMounted(fetchBanks)
</script>
