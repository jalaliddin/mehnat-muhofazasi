<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-6">
      <div class="text-h5 font-weight-bold" style="color: #0D1B2A">Savollar</div>
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
        :headers="headers"
        :items="filteredTypes"
        :loading="loading"
        hover
      >
        <template #item.questions_count="{ item }">
          <v-chip size="small" :color="item.questions_count ? 'primary' : 'grey'" variant="tonal">
            {{ item.questions_count }} ta savol
          </v-chip>
        </template>
        <template #item.duration_minutes="{ item }">{{ item.duration_minutes }} daqiqa</template>
        <template #item.actions="{ item }">
          <v-btn
            color="primary"
            variant="tonal"
            size="small"
            rounded="lg"
            prepend-icon="mdi-help-circle-outline"
            @click="$router.push(`/exams/types/${item.id}/questions`)"
          >
            Savollarni boshqarish
          </v-btn>
        </template>
      </v-data-table>
    </v-card>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import api from '../../services/api'

const types = ref([])
const loading = ref(false)
const search = ref('')

const headers = [
  { title: 'Imtihon turi', key: 'name' },
  { title: 'Savollar soni', key: 'questions_count' },
  { title: 'Test vaqti', key: 'duration_minutes' },
  { title: 'Amallar', key: 'actions', sortable: false, align: 'end' },
]

const filteredTypes = computed(() => {
  if (!search.value) return types.value
  const q = search.value.toLowerCase()
  return types.value.filter(t => t.name?.toLowerCase().includes(q))
})

async function fetchTypes() {
  loading.value = true
  try {
    const res = await api.get('/exam-types')
    types.value = res.data.data || res.data
  } catch (e) {}
  loading.value = false
}

onMounted(fetchTypes)
</script>
