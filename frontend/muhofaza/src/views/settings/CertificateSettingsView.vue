<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-6">
      <div class="text-h5 font-weight-bold" style="color: #0D1B2A">Sertifikat sozlamalari</div>
    </div>

    <v-card rounded="xl" elevation="0" style="border: 1px solid #e8eaf0; max-width: 720px" class="pa-6">
      <div class="text-body-2 mb-6" style="color: #666">
        Bu ma'lumotlar har bir yangi sertifikatga yoziladi. O'zgartirishdan keyin imzolanmagan sertifikatlarni
        imtihon sahifasidagi "Sertifikat yaratish" tugmasi bilan yangilash mumkin.
      </div>

      <div v-if="loading" class="text-center pa-6">
        <v-progress-circular indeterminate color="primary" />
      </div>

      <v-form v-else @submit.prevent="save">
        <v-text-field
          v-model="form.institution_name"
          label="Ta'lim muassasasi nomi"
          placeholder='"JAHON MEHNAT MUHOFAZASI" MCHJ'
          variant="outlined"
          rounded="lg"
          class="mb-3"
        />
        <v-text-field
          v-model="form.commission_chair_name"
          label="Imtihon komissiyasi raisi (F.I.O)"
          placeholder="J. Xasanov"
          variant="outlined"
          rounded="lg"
          class="mb-3"
        />
        <v-text-field
          v-model="form.director_name"
          label="Ta'lim muassasasi rahbari (F.I.O)"
          placeholder="A. Bazarova"
          variant="outlined"
          rounded="lg"
          class="mb-3"
        />
        <v-text-field
          v-model="form.inspector_name"
          label="Davlat texnik inspektori (F.I.O)"
          placeholder="M. Yurev"
          variant="outlined"
          rounded="lg"
          class="mb-3"
        />
        <v-text-field
          v-model="form.city"
          label="Shahar (tuman)"
          placeholder="Urganch sh."
          variant="outlined"
          rounded="lg"
          class="mb-3"
        />
        <div class="d-flex justify-end">
          <v-btn color="primary" variant="flat" rounded="lg" type="submit" :loading="saving">Saqlash</v-btn>
        </div>
      </v-form>
    </v-card>

    <v-card rounded="xl" elevation="0" style="border: 1px solid #e8eaf0; max-width: 980px" class="pa-6 mt-6">
      <div class="d-flex align-center mb-4">
        <div>
          <div class="text-h6 font-weight-bold" style="color: #0D1B2A">Sertifikat turlari</div>
          <div class="text-body-2" style="color: #666">Davriy imtihonda tanlanadi: soat, sarlavha va kurs matni sertifikatga shu turdan yoziladi.</div>
        </div>
        <v-spacer />
        <v-btn color="primary" prepend-icon="mdi-plus" rounded="lg" @click="openType()">Yangi tur</v-btn>
      </div>

      <v-data-table :headers="typeHeaders" :items="types" :loading="loadingTypes" density="comfortable" hide-default-footer :items-per-page="-1">
        <template #item.hours="{ item }">{{ item.hours }} soat</template>
        <template #item.actions="{ item }">
          <div class="d-flex justify-end">
            <v-btn icon="mdi-pencil" variant="text" size="small" color="primary" @click="openType(item)" />
            <v-btn icon="mdi-delete" variant="text" size="small" color="error" @click="askDeleteType(item)" />
          </div>
        </template>
      </v-data-table>
    </v-card>

    <v-dialog v-model="typeDialog" max-width="640">
      <v-card rounded="xl">
        <v-card-title class="pa-6">{{ typeForm.id ? 'Turni tahrirlash' : 'Yangi sertifikat turi' }}</v-card-title>
        <v-divider />
        <v-card-text class="pa-6">
          <v-row dense>
            <v-col cols="12" sm="8">
              <v-text-field v-model="typeForm.name" label="Nomi *" placeholder="72 soatlik malaka oshirish" variant="outlined" rounded="lg" :error-messages="typeErrors.name" @update:model-value="typeErrors.name = null" />
            </v-col>
            <v-col cols="12" sm="4">
              <v-text-field v-model.number="typeForm.hours" label="Soat *" type="number" min="1" variant="outlined" rounded="lg" :error-messages="typeErrors.hours" @update:model-value="typeErrors.hours = null" />
            </v-col>
            <v-col cols="12">
              <v-text-field v-model="typeForm.title_text" label="Sertifikat sarlavhasi *" variant="outlined" rounded="lg" :error-messages="typeErrors.title_text" @update:model-value="typeErrors.title_text = null" />
            </v-col>
            <v-col cols="12">
              <v-text-field v-model="typeForm.course_text" label="Kurs matni *" placeholder="malaka oshirish kursida" variant="outlined" rounded="lg"
                :error-messages="typeErrors.course_text" @update:model-value="typeErrors.course_text = null"
                :hint="`Sertifikatda: dastur bo‘yicha ${typeForm.hours || '__'} soat hajmida ${typeForm.course_text || '...'} o‘qidi:`" persistent-hint />
            </v-col>
          </v-row>
        </v-card-text>
        <v-card-actions class="pa-4">
          <v-spacer />
          <v-btn variant="text" @click="typeDialog = false">Bekor qilish</v-btn>
          <v-btn color="primary" variant="flat" rounded="lg" :loading="savingType" @click="saveType">Saqlash</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <ConfirmDialog
      ref="confirmRef"
      title="Turni o'chirish"
      :message="deleteTypeItem ? `«${deleteTypeItem.name}» turi o'chiriladi.` : ''"
      @confirm="deleteType"
    />

    <v-snackbar v-model="snackbar" :color="snackbarColor" timeout="4000">{{ snackbarText }}</v-snackbar>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../../services/api'
import ConfirmDialog from '../../components/common/ConfirmDialog.vue'

const FIELDS = ['institution_name', 'commission_chair_name', 'director_name', 'inspector_name', 'city']

const form = ref(Object.fromEntries(FIELDS.map(f => [f, ''])))
const loading = ref(true)
const saving = ref(false)
const snackbar = ref(false)
const snackbarText = ref('')
const snackbarColor = ref('success')

function notify(text, color = 'success') {
  snackbarText.value = text
  snackbarColor.value = color
  snackbar.value = true
}

async function load() {
  loading.value = true
  try {
    const res = await api.get('/certificate-settings')
    form.value = Object.fromEntries(FIELDS.map(f => [f, res.data[f] || '']))
  } catch (e) {
    notify("Sozlamalarni yuklab bo'lmadi", 'error')
  }
  loading.value = false
}

async function save() {
  saving.value = true
  try {
    await api.put('/certificate-settings', form.value)
    notify('Saqlandi')
  } catch (e) {
    notify(e.response?.data?.message || "Saqlab bo'lmadi", 'error')
  }
  saving.value = false
}

// --- Sertifikat turlari ---
const types = ref([])
const loadingTypes = ref(false)
const typeDialog = ref(false)
const savingType = ref(false)
const typeForm = ref({})
const typeErrors = ref({})
const deleteTypeItem = ref(null)
const confirmRef = ref(null)

const typeHeaders = [
  { title: 'Nomi', key: 'name' },
  { title: 'Soat', key: 'hours' },
  { title: 'Sarlavha', key: 'title_text', sortable: false },
  { title: 'Kurs matni', key: 'course_text', sortable: false },
  { title: '', key: 'actions', sortable: false, align: 'end' },
]

async function loadTypes() {
  loadingTypes.value = true
  try {
    types.value = (await api.get('/certificate-types')).data
  } catch (e) {
    notify("Turlarni yuklab bo'lmadi", 'error')
  }
  loadingTypes.value = false
}

function openType(item = null) {
  typeForm.value = item
    ? { ...item }
    : { name: '', hours: 36, title_text: 'Mehnatni muhofaza qilish sohasida malaka oshirganlik haqida', course_text: 'malaka oshirish kursida' }
  typeErrors.value = {}
  typeDialog.value = true
}

async function saveType() {
  savingType.value = true
  typeErrors.value = {}
  try {
    const payload = { name: typeForm.value.name, hours: typeForm.value.hours, title_text: typeForm.value.title_text, course_text: typeForm.value.course_text }
    if (typeForm.value.id) await api.put(`/certificate-types/${typeForm.value.id}`, payload)
    else await api.post('/certificate-types', payload)
    typeDialog.value = false
    notify('Saqlandi')
    await loadTypes()
  } catch (e) {
    if (e.response?.data?.errors) typeErrors.value = e.response.data.errors
    else notify(e.response?.data?.message || "Saqlab bo'lmadi", 'error')
  }
  savingType.value = false
}

function askDeleteType(item) {
  deleteTypeItem.value = item
  confirmRef.value.open()
}

async function deleteType() {
  try {
    await api.delete(`/certificate-types/${deleteTypeItem.value.id}`)
    notify("O'chirildi")
    await loadTypes()
  } catch (e) {
    notify(e.response?.data?.message || "O'chirib bo'lmadi", 'error')
  }
}

onMounted(() => {
  load()
  loadTypes()
})
</script>
