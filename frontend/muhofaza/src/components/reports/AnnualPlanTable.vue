<template>
  <v-card rounded="xl" elevation="0" style="border: 1px solid #e8eaf0">
    <v-card-title class="pa-5 d-flex align-center justify-space-between">
      <span style="color: #0D1B2A">{{ year }}-yil Yillik Reja</span>
      <v-chip color="primary" size="small" v-if="totals">
        Bajarilish: {{ totals.percentage }}%
      </v-chip>
    </v-card-title>
    <v-divider />

    <v-table density="comfortable">
      <thead>
        <tr style="background: #f8f9fa">
          <th style="color: #0D1B2A; font-weight: 600">Oy</th>
          <th style="color: #0D1B2A; font-weight: 600">Rejalashtirilgan imtihon turlari</th>
          <th class="text-center" style="color: #0D1B2A; font-weight: 600">Reja</th>
          <th class="text-center" style="color: #0D1B2A; font-weight: 600">Bajarildi</th>
          <th style="color: #0D1B2A; font-weight: 600; min-width: 160px">Bajarilish</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="m in months" :key="m.month">
          <td style="font-weight: 600; color: #0D1B2A; white-space: nowrap">{{ m.month_name }}</td>
          <td>
            <div v-if="!m.planned_types?.length" class="text-caption" style="color: #ccc">—</div>
            <div v-for="t in m.planned_types" :key="t.id" class="d-flex align-center my-1">
              <v-icon icon="mdi-circle-small" size="16" color="primary" />
              <span style="font-size: 13px">{{ t.name }}</span>
              <v-chip size="x-small" class="ml-1" color="secondary" label>{{ t.frequency_months }} oy</v-chip>
            </div>
          </td>
          <td class="text-center">
            <span style="font-weight: 600">{{ m.planned }}</span>
          </td>
          <td class="text-center">
            <span :style="{ fontWeight: '600', color: completedColor(m) }">{{ m.completed }}</span>
          </td>
          <td>
            <div v-if="m.planned === 0" class="text-caption" style="color: #ccc">—</div>
            <div v-else>
              <div class="d-flex align-center justify-space-between mb-1">
                <span class="text-caption" :style="{ color: progressColor(m.percentage), fontWeight: '600' }">
                  {{ m.percentage }}%
                </span>
              </div>
              <v-progress-linear
                :model-value="m.percentage"
                :color="progressColor(m.percentage)"
                bg-color="#e8eaf0"
                height="8"
                rounded
              />
            </div>
          </td>
        </tr>
      </tbody>
      <tfoot v-if="totals">
        <tr style="background: #f0f4ff; font-weight: 700">
          <td colspan="2" style="font-weight: 700; color: #0D1B2A; padding: 12px 16px">JAMI</td>
          <td class="text-center" style="font-weight: 700">{{ totals.planned }}</td>
          <td class="text-center" style="font-weight: 700; color: #1565C0">{{ totals.completed }}</td>
          <td>
            <div class="d-flex align-center" style="gap: 8px">
              <v-progress-linear
                :model-value="totals.percentage"
                :color="progressColor(totals.percentage)"
                bg-color="#e8eaf0"
                height="10"
                rounded
                style="flex: 1"
              />
              <span style="font-weight: 700; min-width: 40px">{{ totals.percentage }}%</span>
            </div>
          </td>
        </tr>
      </tfoot>
    </v-table>
  </v-card>
</template>

<script setup>
defineProps({
  months: { type: Array, default: () => [] },
  totals: { type: Object, default: null },
  year: { type: [Number, String], default: '' },
})

function progressColor(pct) {
  if (pct === null || pct === undefined) return 'grey'
  if (pct >= 100) return '#1565C0'
  if (pct >= 50) return '#F57C00'
  if (pct > 0) return '#FF8F00'
  return '#D32F2F'
}

function completedColor(m) {
  if (!m.planned) return '#0D1B2A'
  if (m.completed >= m.planned) return '#388E3C'
  if (m.completed > 0) return '#F57C00'
  return '#D32F2F'
}
</script>
