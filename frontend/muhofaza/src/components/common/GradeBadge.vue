<template>
  <v-chip :color="chipColor" size="small" label>{{ gradeText }}</v-chip>
</template>

<script setup>
import { computed } from 'vue'
const props = defineProps(['grade'])
const gradeMap = {
  excellent: { text: "A'lo", color: 'success' },
  good: { text: 'Yaxshi', color: 'primary' },
  satisfactory: { text: 'Qoniqarli', color: 'warning' },
  unsatisfactory: { text: 'Qoniqarsiz', color: 'error' },
}
const chipColor = computed(() => gradeMap[props.grade]?.color || 'grey')
const gradeText = computed(() => gradeMap[props.grade]?.text || props.grade)
</script>
