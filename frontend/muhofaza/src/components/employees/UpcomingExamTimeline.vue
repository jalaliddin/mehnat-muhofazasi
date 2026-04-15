<template>
  <v-card rounded="xl" elevation="0" style="border: 1px solid #e8eaf0">
    <v-card-title class="pa-5 d-flex align-center">
      <v-icon icon="mdi-calendar-clock" color="primary" class="mr-2" />
      <span style="color: #0D1B2A">Yaqinlashayotgan Imtihonlar</span>
      <v-spacer />
      <v-btn icon="mdi-refresh" variant="text" size="small" @click="fetch" :loading="loading" />
    </v-card-title>
    <v-divider />

    <div v-if="loading" class="pa-8 text-center">
      <v-progress-circular indeterminate color="primary" />
    </div>

    <div v-else-if="!upcomingExams.length" class="pa-10 text-center">
      <v-icon icon="mdi-calendar-check" size="56" color="grey-lighten-2" />
      <div class="text-body-1 mt-3" style="color: #bbb">Yaqin 6 oy ichida imtihon yo'q</div>
    </div>

    <div v-else class="pa-4">
      <!-- Overdue -->
      <template v-if="byStatus.overdue.length">
        <div class="d-flex align-center mb-2 mt-1">
          <v-icon icon="mdi-alert-octagon" color="#8B0000" size="18" class="mr-2" />
          <span class="text-caption font-weight-bold" style="color: #8B0000; letter-spacing: 0.05em">MUDDATI O'TGAN</span>
        </div>
        <ExamRow
          v-for="ex in byStatus.overdue"
          :key="ex.periodic_exam_id"
          :exam="ex"
          class="mb-2"
        />
      </template>

      <!-- Urgent -->
      <template v-if="byStatus.urgent.length">
        <div class="d-flex align-center mb-2 mt-3">
          <v-icon icon="mdi-alert-circle" color="error" size="18" class="mr-2" />
          <span class="text-caption font-weight-bold" style="color: #D32F2F; letter-spacing: 0.05em">SHOSHILINCH (7 kun va kam)</span>
        </div>
        <ExamRow
          v-for="ex in byStatus.urgent"
          :key="ex.periodic_exam_id"
          :exam="ex"
          class="mb-2"
        />
      </template>

      <!-- Warning -->
      <template v-if="byStatus.warning.length">
        <div class="d-flex align-center mb-2 mt-3">
          <v-icon icon="mdi-clock-alert" color="warning" size="18" class="mr-2" />
          <span class="text-caption font-weight-bold" style="color: #F57C00; letter-spacing: 0.05em">YAQIN (30 kun ichida)</span>
        </div>
        <ExamRow
          v-for="ex in byStatus.warning"
          :key="ex.periodic_exam_id"
          :exam="ex"
          class="mb-2"
        />
      </template>

      <!-- Normal -->
      <template v-if="byStatus.normal.length">
        <div class="d-flex align-center mb-2 mt-3">
          <v-icon icon="mdi-calendar-check" color="primary" size="18" class="mr-2" />
          <span class="text-caption font-weight-bold" style="color: #1565C0; letter-spacing: 0.05em">BELGILANGAN</span>
        </div>
        <ExamRow
          v-for="ex in byStatus.normal"
          :key="ex.periodic_exam_id"
          :exam="ex"
          class="mb-2"
        />
      </template>
    </div>
  </v-card>
</template>

<script setup>
import { ref, computed, onMounted, defineComponent, h } from 'vue'
import { useRouter } from 'vue-router'
import api from '../../services/api'

const props = defineProps({ employeeId: { type: [Number, String], required: true } })
const router = useRouter()
const upcomingExams = ref([])
const loading = ref(false)

const byStatus = computed(() => ({
  overdue: upcomingExams.value.filter(e => e.status === 'overdue'),
  urgent: upcomingExams.value.filter(e => e.status === 'urgent'),
  warning: upcomingExams.value.filter(e => e.status === 'warning'),
  normal: upcomingExams.value.filter(e => e.status === 'normal'),
}))

async function fetch() {
  loading.value = true
  try {
    const res = await api.get(`/employees/${props.employeeId}/upcoming-exams`)
    upcomingExams.value = res.data.upcoming_exams || []
  } catch (e) {}
  loading.value = false
}

onMounted(fetch)

// Inner row component
const ExamRow = defineComponent({
  name: 'ExamRow',
  props: { exam: Object },
  setup(props) {
    const colorMap = {
      overdue: { bg: '#fff5f5', border: '#ffcdd2', badge: '#8B0000', text: 'white' },
      urgent:  { bg: '#fff5f5', border: '#ffcdd2', badge: '#D32F2F', text: 'white' },
      warning: { bg: '#fffde7', border: '#ffe082', badge: '#F57C00', text: 'white' },
      normal:  { bg: '#e3f2fd', border: '#bbdefb', badge: '#1565C0', text: 'white' },
    }
    const gradeMap = {
      excellent: "A'lo", good: 'Yaxshi', satisfactory: 'Qoniqarli', unsatisfactory: 'Qoniqarsiz',
    }
    const gradeColor = {
      excellent: '#388E3C', good: '#1565C0', satisfactory: '#F57C00', unsatisfactory: '#D32F2F',
    }

    return () => {
      const { exam } = props
      const c = colorMap[exam.status] || colorMap.normal
      const days = exam.days_remaining
      const daysText = days < 0 ? `${Math.abs(days)} kun o'tgan` : `${days} kun qoldi`
      const isOverdue = exam.status === 'overdue'

      return h('div', {
        style: {
          background: c.bg,
          border: `1px solid ${c.border}`,
          borderRadius: '10px',
          padding: '10px 14px',
          cursor: 'pointer',
          animation: isOverdue ? 'pulse-border 2s infinite' : 'none',
        },
      }, [
        h('div', { class: 'd-flex align-center justify-space-between' }, [
          // Left: exam type name + last grade
          h('div', { class: 'd-flex align-center' }, [
            h('div', [
              h('div', {
                style: { fontWeight: '600', fontSize: '14px', color: '#0D1B2A' }
              }, exam.exam_type),
              h('div', {
                style: { fontSize: '12px', color: '#888', marginTop: '2px' }
              }, [
                `Oxirgi: ${exam.last_exam_date} · `,
                h('span', {
                  style: { color: gradeColor[exam.last_grade] || '#888', fontWeight: '500' }
                }, gradeMap[exam.last_grade] || exam.last_grade),
              ]),
            ]),
          ]),
          // Right: date + days badge
          h('div', { class: 'd-flex align-center', style: { gap: '8px' } }, [
            h('div', { style: { textAlign: 'right' } }, [
              h('div', { style: { fontSize: '13px', fontWeight: '600', color: '#0D1B2A' } }, exam.next_exam_date),
              h('div', {
                style: {
                  background: c.badge,
                  color: c.text,
                  borderRadius: '12px',
                  padding: '2px 10px',
                  fontSize: '11px',
                  fontWeight: '700',
                  marginTop: '3px',
                  display: 'inline-block',
                }
              }, daysText),
            ]),
          ]),
        ]),
        // Progress bar
        h('div', {
          style: {
            marginTop: '8px',
            height: '4px',
            background: '#e0e0e0',
            borderRadius: '2px',
            overflow: 'hidden',
          }
        }, [
          h('div', {
            style: {
              height: '100%',
              width: progressWidth(exam) + '%',
              background: c.badge,
              borderRadius: '2px',
              transition: 'width 0.5s',
            }
          }),
        ]),
      ])
    }
  }
})

function progressWidth(exam) {
  const totalDays = (exam.frequency_months || 12) * 30
  const daysUsed = totalDays - Math.max(0, exam.days_remaining)
  return Math.min(100, Math.max(0, Math.round((daysUsed / totalDays) * 100)))
}
</script>

<style>
@keyframes pulse-border {
  0%, 100% { box-shadow: 0 0 0 0 rgba(139, 0, 0, 0.3); }
  50% { box-shadow: 0 0 0 4px rgba(139, 0, 0, 0.1); }
}
</style>
