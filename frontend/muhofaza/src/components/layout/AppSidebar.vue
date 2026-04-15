<template>
  <v-navigation-drawer
    v-model="drawer"
    :rail="rail"
    permanent
    :style="{ background: '#0D1B2A' }"
    width="260"
  >
    <!-- Logo -->
    <div class="d-flex align-center px-4 py-4" style="border-bottom: 1px solid rgba(255,255,255,0.1); min-height: 64px">
      <v-icon icon="mdi-shield-check" color="white" size="28" class="mr-3 flex-shrink-0" />
      <div v-if="!rail" class="flex-grow-1 overflow-hidden">
        <div class="text-body-1 font-weight-bold text-white text-truncate">Urganchtransgaz</div>
        <div class="text-caption" style="color: rgba(255,255,255,0.5)">Mehnat Muhofazasi</div>
      </div>
      <v-btn
        variant="text"
        :icon="rail ? 'mdi-chevron-right' : 'mdi-chevron-left'"
        color="white"
        size="small"
        @click="rail = !rail"
        class="flex-shrink-0"
      />
    </div>

    <!-- Menu -->
    <div class="pa-2 mt-1">

      <!-- Dashboard -->
      <NavItem icon="mdi-view-dashboard" label="Dashboard" path="/dashboard" :rail="rail" />

      <div class="my-1" style="border-bottom: 1px solid rgba(255,255,255,0.08)" />

      <!-- Tashkilotlar (admin only) -->
      <NavItem v-if="authStore.isAdmin" icon="mdi-domain" label="Tashkilotlar" path="/organizations" :rail="rail" />

      <!-- XODIMLAR guruh -->
      <NavGroup label="XODIMLAR" icon="mdi-account-multiple" :rail="rail" :open="groupOpen.employees" @toggle="groupOpen.employees = !groupOpen.employees">
        <NavItem icon="mdi-account-multiple" label="Xodimlar Ro'yxati" path="/employees" :rail="rail" :indent="!rail" />
        <NavItem icon="mdi-office-building" label="Bo'limlar" path="/departments" :rail="rail" :indent="!rail" />
        <NavItem icon="mdi-briefcase" label="Lavozimlar" path="/positions" :rail="rail" :indent="!rail" />
      </NavGroup>

      <!-- IMTIHONLAR guruh -->
      <NavGroup label="IMTIHONLAR" icon="mdi-clipboard-check" :rail="rail" :open="groupOpen.exams" @toggle="groupOpen.exams = !groupOpen.exams">
        <NavItem icon="mdi-calendar-check" label="Davriy Imtihonlar" path="/exams/periodic" :rail="rail" :indent="!rail" />
        <NavItem icon="mdi-shape" label="Imtihon Turlari" path="/exams/types" :rail="rail" :indent="!rail" />
        <NavItem icon="mdi-refresh" label="Qayta Topshirish" path="/exams/retakes" :rail="rail" :indent="!rail" :badge="retakesCount" />
      </NavGroup>

      <div class="my-1" style="border-bottom: 1px solid rgba(255,255,255,0.08)" />

      <!-- Buyruqlar -->
      <NavItem icon="mdi-file-document" label="Buyruqlar" path="/orders" :rail="rail" />

      <!-- Bildirishnomalar -->
      <NavItem icon="mdi-bell" label="Bildirishnomalar" path="/notifications" :rail="rail" :badge="notifStore.unreadCount" />

      <!-- Hisobotlar -->
      <NavItem icon="mdi-chart-bar" label="Hisobotlar" path="/reports" :rail="rail" />

      <!-- Foydalanuvchilar (admin only) -->
      <template v-if="authStore.isAdmin">
        <div class="my-1" style="border-bottom: 1px solid rgba(255,255,255,0.08)" />
        <NavItem icon="mdi-account-cog" label="Foydalanuvchilar" path="/settings/users" :rail="rail" />
      </template>
    </div>

    <!-- User info -->
    <template #append>
      <div style="border-top: 1px solid rgba(255,255,255,0.1)" class="pa-3">
        <div class="d-flex align-center">
          <v-avatar color="#1565C0" size="36" class="flex-shrink-0">
            <span class="text-body-2 font-weight-bold text-white">
              {{ authStore.user?.name?.charAt(0)?.toUpperCase() }}
            </span>
          </v-avatar>
          <div v-if="!rail" class="ml-3 flex-grow-1 overflow-hidden">
            <div class="text-body-2 font-weight-medium text-white text-truncate">{{ authStore.user?.name }}</div>
            <div class="text-caption" style="color: rgba(255,255,255,0.5)">
              {{ authStore.user?.role === 'admin' ? 'Administrator' : 'Operator' }}
            </div>
          </div>
          <v-btn
            icon="mdi-logout"
            variant="text"
            color="rgba(255,255,255,0.5)"
            size="small"
            @click="handleLogout"
            class="flex-shrink-0"
            :class="rail ? 'ml-0' : 'ml-1'"
            title="Chiqish"
          />
        </div>
      </div>
    </template>
  </v-navigation-drawer>
</template>

<script setup>
import { ref, reactive, watch, onMounted, defineComponent, h, computed } from 'vue'
import { useAuthStore } from '../../stores/auth'
import { useNotificationStore } from '../../stores/notification'
import { useRouter, useRoute } from 'vue-router'
import api from '../../services/api'

const authStore = useAuthStore()
const notifStore = useNotificationStore()
const router = useRouter()
const route = useRoute()
const drawer = ref(true)
const rail = ref(false)
const retakesCount = ref(0)

const groupOpen = reactive({
  employees: true,
  exams: true,
})

// Auto-expand group when navigating directly to a child route
watch(() => route.path, (path) => {
  if (path.startsWith('/employees') || path.startsWith('/departments') || path.startsWith('/positions')) {
    groupOpen.employees = true
  }
  if (path.startsWith('/exams')) {
    groupOpen.exams = true
  }
}, { immediate: true })

async function fetchRetakesCount() {
  try {
    const res = await api.get('/retakes')
    retakesCount.value = (res.data.data || res.data).length
  } catch (e) {}
}

async function handleLogout() {
  await authStore.logout()
  router.push('/login')
}

onMounted(() => {
  notifStore.startPolling()
  fetchRetakesCount()
})

// ── NavItem ──────────────────────────────────────────────
const NavItem = defineComponent({
  name: 'NavItem',
  props: {
    icon: String,
    label: String,
    path: String,
    rail: Boolean,
    indent: { type: Boolean, default: false },
    badge: { type: Number, default: 0 },
  },
  setup(props) {
    const route = useRoute()
    const router = useRouter()
    const isActive = computed(() => {
      if (props.path === '/dashboard') return route.path === '/dashboard'
      return route.path.startsWith(props.path)
    })
    function navigate() {
      if (route.path !== props.path) router.push(props.path)
    }
    return () => h('div', {
      style: {
        borderRadius: '8px',
        background: isActive.value ? '#1565C0' : 'transparent',
        cursor: 'pointer',
        transition: 'background 0.15s',
        minHeight: '38px',
        display: 'flex',
        alignItems: 'center',
        padding: props.indent ? '0 12px 0 28px' : '0 12px',
        marginBottom: '2px',
      },
      onClick: navigate,
      onMouseenter: (e) => { if (!isActive.value) e.currentTarget.style.background = 'rgba(255,255,255,0.08)' },
      onMouseleave: (e) => { if (!isActive.value) e.currentTarget.style.background = 'transparent' },
    }, [
      h('v-icon', {
        icon: props.icon,
        size: 18,
        color: isActive.value ? 'white' : 'rgba(255,255,255,0.6)',
        style: { flexShrink: 0, marginRight: props.rail ? '0' : '10px' },
      }),
      !props.rail && h('span', {
        style: {
          color: isActive.value ? 'white' : 'rgba(255,255,255,0.7)',
          fontSize: '13.5px',
          fontWeight: isActive.value ? '600' : '400',
          flexGrow: 1,
          whiteSpace: 'nowrap',
          overflow: 'hidden',
          textOverflow: 'ellipsis',
        }
      }, props.label),
      !props.rail && props.badge > 0 && h('span', {
        style: {
          background: '#D32F2F',
          color: 'white',
          borderRadius: '10px',
          padding: '1px 7px',
          fontSize: '11px',
          fontWeight: '700',
          minWidth: '20px',
          textAlign: 'center',
          marginLeft: '4px',
        }
      }, String(props.badge)),
    ])
  }
})

// ── NavGroup ─────────────────────────────────────────────
const NavGroup = defineComponent({
  name: 'NavGroup',
  props: {
    label: String,
    icon: String,
    rail: Boolean,
    open: Boolean,
  },
  emits: ['toggle'],
  setup(props, { slots, emit }) {
    return () => {
      const header = h('div', {
        style: {
          display: 'flex',
          alignItems: 'center',
          padding: props.rail ? '0 12px' : '0 12px',
          minHeight: '32px',
          marginBottom: '2px',
          cursor: 'pointer',
          borderRadius: '6px',
        },
        onClick: () => emit('toggle'),
        onMouseenter: (e) => { e.currentTarget.style.background = 'rgba(255,255,255,0.05)' },
        onMouseleave: (e) => { e.currentTarget.style.background = 'transparent' },
      }, props.rail
        ? [h('v-icon', { icon: props.icon, size: 18, color: 'rgba(255,255,255,0.4)' })]
        : [
            h('span', {
              style: {
                color: 'rgba(255,255,255,0.35)',
                fontSize: '11px',
                fontWeight: '700',
                letterSpacing: '0.08em',
                flexGrow: 1,
              }
            }, props.label),
            h('v-icon', {
              icon: props.open ? 'mdi-chevron-down' : 'mdi-chevron-right',
              size: 16,
              color: 'rgba(255,255,255,0.3)',
            }),
          ]
      )

      const children = (!props.rail && props.open)
        ? h('div', { style: { overflow: 'hidden' } }, slots.default?.())
        : props.rail
          ? h('div', {}, slots.default?.())
          : null

      return h('div', [header, children])
    }
  }
})
</script>
