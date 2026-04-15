<template>
  <v-card rounded="xl" elevation="0" style="border: 1px solid #e8eaf0" class="pa-4">
    <div class="text-subtitle-1 font-weight-bold mb-4" style="color: #0D1B2A">Baholar Taqsimoti</div>
    <apexchart
      v-if="hasData"
      type="donut"
      height="260"
      :options="chartOptions"
      :series="series"
    />
    <div v-else class="text-center py-10" style="color: #ccc">
      <v-icon icon="mdi-chart-donut" size="48" />
      <div class="mt-2">Ma'lumot yo'q</div>
    </div>
  </v-card>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  gradeStats: { type: Object, default: () => ({}) }
})

const series = computed(() => [
  props.gradeStats.excellent || 0,
  props.gradeStats.good || 0,
  props.gradeStats.satisfactory || 0,
  props.gradeStats.unsatisfactory || 0,
])

const hasData = computed(() => series.value.some(v => v > 0))

const chartOptions = {
  labels: ["A'lo", 'Yaxshi', 'Qoniqarli', 'Qoniqarsiz'],
  colors: ['#388E3C', '#1565C0', '#F57C00', '#D32F2F'],
  legend: { position: 'bottom', fontSize: '13px' },
  dataLabels: { enabled: true, formatter: (val) => Math.round(val) + '%' },
  plotOptions: {
    pie: { donut: { size: '60%', labels: { show: true, total: { show: true, label: 'Jami', fontSize: '14px' } } } }
  },
  tooltip: { y: { formatter: (v) => v + ' ta' } },
}
</script>
