<template>
  <div>
    <div class="d-flex align-center justify-space-between mb-6">
      <div class="text-h5 font-weight-bold" style="color: #0D1B2A">Imtihon Turlari</div>
      <v-btn color="primary" prepend-icon="mdi-plus" rounded="lg" @click="openCreate">Yangi Tur</v-btn>
    </div>

    <!-- Tabs -->
    <v-card rounded="xl" elevation="0" style="border: 1px solid #e8eaf0" class="mb-4">
      <v-tabs v-model="tab" color="primary">
        <v-tab value="table"><v-icon start icon="mdi-table" />Jadval Ko'rinishi</v-tab>
        <v-tab value="calendar"><v-icon start icon="mdi-calendar-month" />Oy Bo'yicha Ko'rinish</v-tab>
      </v-tabs>
    </v-card>

    <!-- TABLE TAB -->
    <template v-if="tab === 'table'">
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
          rounded="xl"
        >
          <template #item.frequency_years="{ item }">{{ item.frequency_months }} oy</template>
          <template #item.exam_month_name="{ item }">
            <v-chip v-if="item.exam_month_name" size="small" color="primary" variant="tonal" prepend-icon="mdi-calendar">
              {{ item.exam_month_name }}
            </v-chip>
            <span v-else class="text-caption" style="color: #bbb">—</span>
          </template>
          <template #item.actions="{ item }">
            <v-btn icon="mdi-pencil" variant="text" size="small" color="primary" @click="openEdit(item)" />
            <v-btn icon="mdi-delete" variant="text" size="small" color="error" @click="openDelete(item)" />
          </template>
        </v-data-table>
      </v-card>
    </template>

    <!-- CALENDAR TAB -->
    <template v-if="tab === 'calendar'">
      <div v-if="loadingCalendar" class="pa-10 text-center">
        <v-progress-circular indeterminate color="primary" />
      </div>
      <v-row v-else>
        <v-col
          v-for="(entry, key) in calendar"
          :key="key"
          cols="12" sm="6" md="4"
          v-show="entry.exam_types.length > 0 || key === 'null'"
        >
          <v-card rounded="xl" elevation="0" style="border: 1px solid #e8eaf0" class="h-100">
            <v-card-title class="pa-4 d-flex align-center" style="border-bottom: 1px solid #f0f0f0">
              <v-avatar
                :color="key === 'null' ? 'grey' : 'primary'"
                size="32"
                class="mr-2 text-white text-caption font-weight-bold"
              >{{ key === 'null' ? '?' : key }}</v-avatar>
              <span style="color: #0D1B2A; font-size: 15px; font-weight: 600">{{ entry.month_name }}</span>
              <v-chip size="x-small" class="ml-auto" :color="entry.exam_types.length ? 'primary' : 'grey'">
                {{ entry.exam_types.length }}
              </v-chip>
            </v-card-title>
            <v-card-text class="pa-3">
              <div v-if="!entry.exam_types.length" class="text-caption text-center py-3" style="color: #ccc">
                Belgilanmagan
              </div>
              <div
                v-for="t in entry.exam_types"
                :key="t.id"
                class="d-flex align-center justify-space-between py-2 px-1"
                style="border-bottom: 1px solid #f5f5f5"
              >
                <div>
                  <div style="font-size: 13px; font-weight: 500; color: #0D1B2A">{{ t.name }}</div>
                  <div v-if="t.exam_month_note" style="font-size: 11px; color: #888">{{ t.exam_month_note }}</div>
                </div>
                <v-chip size="x-small" color="secondary" label>{{ t.frequency_months }} oy</v-chip>
              </div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
    </template>

    <!-- Create/Edit Dialog -->
    <v-dialog v-model="dialog" max-width="520">
      <v-card rounded="xl">
        <v-card-title class="pa-6 d-flex align-center">
          <v-icon :icon="editItem ? 'mdi-pencil' : 'mdi-plus'" class="mr-2" color="primary" />
          {{ editItem ? "Turni tahrirlash" : "Yangi Imtihon Turi" }}
        </v-card-title>
        <v-divider />
        <v-card-text class="pa-6">
          <v-form ref="formRef" @submit.prevent="save">
            <v-text-field
              v-model="form.name"
              label="Tur nomi *"
              variant="outlined"
              rounded="lg"
              :rules="[v => !!v || 'Majburiy maydon']"
              class="mb-3"
            />
            <v-text-field
              v-model.number="form.frequency_months"
              label="Chastota (oy) *"
              placeholder="Masalan: 6, 12, 36"
              type="number"
              min="1"
              variant="outlined"
              rounded="lg"
              :rules="[v => (!!v && v >= 1) || 'Kamida 1 oy kiritilsin']"
              suffix="oy"
              class="mb-3"
            />
            <v-select
              v-model="form.exam_month"
              :items="months"
              item-title="title"
              item-value="value"
              label="Belgilangan Oy (ixtiyoriy)"
              variant="outlined"
              rounded="lg"
              clearable
              prepend-inner-icon="mdi-calendar-month"
              class="mb-3"
            />
            <v-text-field
              v-if="form.exam_month"
              v-model="form.exam_month_note"
              label="Oy haqida izoh"
              placeholder="Masalan: Har yil mart oyida o'tkaziladi"
              variant="outlined"
              rounded="lg"
              class="mb-3"
            />
            <v-textarea
              v-model="form.description"
              label="Tavsif"
              variant="outlined"
              rounded="lg"
              rows="3"
            />
          </v-form>
        </v-card-text>
        <v-card-actions class="pa-4">
          <v-spacer />
          <v-btn variant="text" @click="dialog = false">Bekor qilish</v-btn>
          <v-btn color="primary" variant="flat" rounded="lg" :loading="saving" @click="save">Saqlash</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <ConfirmDialog
      ref="confirmRef"
      title="Turni o'chirish"
      :message="`'${deleteItem?.name}' turini o'chirmoqchimisiz?`"
      @confirm="deleteType"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import api from '../../services/api'
import ConfirmDialog from '../../components/common/ConfirmDialog.vue'

const types = ref([])
const calendar = ref({})
const loading = ref(false)
const loadingCalendar = ref(false)
const dialog = ref(false)
const saving = ref(false)
const editItem = ref(null)
const deleteItem = ref(null)
const search = ref('')
const tab = ref('table')
const formRef = ref(null)
const confirmRef = ref(null)

const months = [
  { title: 'Yanvar', value: 1 }, { title: 'Fevral', value: 2 },
  { title: 'Mart', value: 3 }, { title: 'Aprel', value: 4 },
  { title: 'May', value: 5 }, { title: 'Iyun', value: 6 },
  { title: 'Iyul', value: 7 }, { title: 'Avgust', value: 8 },
  { title: 'Sentabr', value: 9 }, { title: 'Oktabr', value: 10 },
  { title: 'Noyabr', value: 11 }, { title: 'Dekabr', value: 12 },
]

const defaultForm = () => ({ name: '', description: '', frequency_months: 12, exam_month: null, exam_month_note: '' })
const form = ref(defaultForm())

const headers = [
  { title: 'Nomi', key: 'name' },
  { title: 'Chastota (oy)', key: 'frequency_years' },
  { title: 'Belgilangan Oy', key: 'exam_month_name', sortable: false },
  { title: 'Tavsif', key: 'description', sortable: false },
  { title: 'Amallar', key: 'actions', sortable: false, align: 'end' },
]

const filteredTypes = computed(() => {
  if (!search.value) return types.value
  const q = search.value.toLowerCase()
  return types.value.filter(t => t.name?.toLowerCase().includes(q))
})

watch(tab, (val) => {
  if (val === 'calendar' && !Object.keys(calendar.value).length) fetchCalendar()
})

function openCreate() {
  editItem.value = null
  form.value = defaultForm()
  dialog.value = true
}

function openEdit(item) {
  editItem.value = item
  form.value = {
    name: item.name,
    description: item.description || '',
    frequency_months: Number(item.frequency_months),
    exam_month: item.exam_month || null,
    exam_month_note: item.exam_month_note || '',
  }
  dialog.value = true
}

function openDelete(item) {
  deleteItem.value = item
  confirmRef.value.open()
}

async function fetchTypes() {
  loading.value = true
  try {
    const res = await api.get('/exam-types')
    types.value = res.data.data || res.data
  } catch (e) {}
  loading.value = false
}

async function fetchCalendar() {
  loadingCalendar.value = true
  try {
    const res = await api.get('/exam-types/calendar')
    calendar.value = res.data.calendar
  } catch (e) {}
  loadingCalendar.value = false
}

async function save() {
  const { valid } = await formRef.value.validate()
  if (!valid) return
  saving.value = true
  try {
    const payload = { ...form.value }
    if (!payload.exam_month) { payload.exam_month = null; payload.exam_month_note = null }
    if (editItem.value) {
      await api.put(`/exam-types/${editItem.value.id}`, payload)
    } else {
      await api.post('/exam-types', payload)
    }
    dialog.value = false
    await fetchTypes()
    if (tab.value === 'calendar') await fetchCalendar()
  } catch (e) {}
  saving.value = false
}

async function deleteType() {
  try {
    await api.delete(`/exam-types/${deleteItem.value.id}`)
    await fetchTypes()
    if (tab.value === 'calendar') await fetchCalendar()
  } catch (e) {}
}

onMounted(fetchTypes)
</script>
