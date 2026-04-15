<template>
  <v-card rounded="xl" elevation="0" style="border: 1px solid #e8eaf0" class="pa-4">
    <div class="text-subtitle-1 font-weight-bold mb-4" style="color: #0D1B2A">Oylar Bo'yicha Imtihonlar</div>
    <apexchart
      type="bar"
      height="260"
      :options="chartOptions"
      :series="series"
    />
  </v-card>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  monthly: { type: Object, default: () => ({}) }
})

const monthNames = ['Yan', 'Fev', 'Mar', 'Apr', 'May', 'Iyu', 'Iyl', 'Avg', 'Sen', 'Okt', 'Noy', 'Dek']

const series = computed(() => [{
  name: 'Imtihon soni',
  data: monthNames.map((_, i) => props.monthly[i + 1]?.count || 0),
}])

const chartOptions = {
  chart: { toolbar: { show: false } },
  colors: ['#1565C0'],
  xaxis: { categories: monthNames },
  yaxis: { min: 0, tickAmount: 5, labels: { formatter: (v) => Math.round(v) } },
  plotOptions: { bar: { borderRadius: 4, columnWidth: '55%' } },
  dataLabels: { enabled: false },
  tooltip: { y: { formatter: (v) => v + ' ta' } },
  grid: { borderColor: '#f0f0f0' },
}
</script>
