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

    <v-snackbar v-model="snackbar" :color="snackbarColor" timeout="3000">{{ snackbarText }}</v-snackbar>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '../../services/api'

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

onMounted(load)
</script>
