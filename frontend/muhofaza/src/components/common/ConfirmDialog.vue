<template>
  <v-dialog v-model="dialog" max-width="400">
    <v-card rounded="xl">
      <v-card-title class="pt-6 px-6">{{ title }}</v-card-title>
      <v-card-text class="px-6">{{ message }}</v-card-text>
      <v-card-actions class="pa-4">
        <v-spacer />
        <v-btn variant="text" @click="dialog = false">Bekor qilish</v-btn>
        <v-btn :color="confirmColor || 'error'" variant="flat" @click="confirm">
          {{ confirmText || "O'chirish" }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref } from 'vue'
const props = defineProps(['title', 'message', 'confirmText', 'confirmColor'])
const emit = defineEmits(['confirm'])
const dialog = ref(false)
function open() { dialog.value = true }
function confirm() { dialog.value = false; emit('confirm') }
defineExpose({ open })
</script>
