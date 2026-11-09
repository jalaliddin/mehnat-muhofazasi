<template>
  <v-dialog v-model="dialog" max-width="480">
    <v-card rounded="xl">
      <v-card-title class="pa-6 pb-1">Sertifikat raqami</v-card-title>
      <v-card-subtitle class="px-6">{{ certificate?.employee?.full_name }}</v-card-subtitle>
      <v-card-text class="pa-6">
        <v-text-field
          v-model="number"
          label="Sertifikat raqami *"
          placeholder="Masalan: 0451"
          variant="outlined"
          rounded="lg"
          autofocus
          :error-messages="error"
          hint="PDF shu raqam bilan chiziladi va yuklab olinadi"
          persistent-hint
          @update:model-value="error = ''"
          @keyup.enter="save"
        />
      </v-card-text>
      <v-card-actions class="pa-4">
        <v-spacer />
        <v-btn variant="text" @click="dialog = false">Bekor qilish</v-btn>
        <v-btn color="primary" variant="flat" rounded="lg" :loading="saving" :disabled="!number.trim()" @click="save">
          Saqlash va yuklab olish
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref } from 'vue'
import api from '../../services/api'
import { downloadFile } from '../../services/download'

const emit = defineEmits(['saved'])

const dialog = ref(false)
const saving = ref(false)
const certificate = ref(null)
const number = ref('')
const error = ref('')

function open(item) {
  certificate.value = item
  number.value = item.number || ''
  error.value = ''
  dialog.value = true
}

async function save() {
  if (!number.value.trim() || saving.value) return
  saving.value = true
  error.value = ''
  try {
    const res = await api.put(`/certificates/${certificate.value.id}/number`, { number: number.value })
    await downloadFile(`/certificates/${certificate.value.id}/download`, {}, `sertifikat-${res.data.number}.pdf`)
    dialog.value = false
    emit('saved')
  } catch (e) {
    error.value = e.response?.data?.errors?.number?.[0] || e.response?.data?.message || e.message || "Saqlab bo'lmadi"
  }
  saving.value = false
}

defineExpose({ open })
</script>
