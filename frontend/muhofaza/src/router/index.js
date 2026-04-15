import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const routes = [
  { path: '/login', component: () => import('../views/LoginView.vue'), meta: { guest: true } },
  {
    path: '/',
    component: () => import('../components/layout/AppLayout.vue'),
    meta: { requiresAuth: true },
    children: [
      { path: '', redirect: '/dashboard' },
      { path: 'dashboard', component: () => import('../views/DashboardView.vue') },
      { path: 'organizations', component: () => import('../views/organizations/OrganizationsView.vue') },
      { path: 'employees', component: () => import('../views/employees/EmployeesView.vue') },
      { path: 'employees/:id', component: () => import('../views/employees/EmployeeDetailView.vue') },
      { path: 'departments', component: () => import('../views/employees/DepartmentsView.vue') },
      { path: 'positions', component: () => import('../views/employees/PositionsView.vue') },
      { path: 'exams/periodic', component: () => import('../views/exams/PeriodicExamsView.vue') },
      { path: 'exams/periodic/:id', component: () => import('../views/exams/ExamDetailView.vue') },
      { path: 'exams/types', component: () => import('../views/exams/ExamTypesView.vue') },
      { path: 'exams/retakes', component: () => import('../views/exams/RetakesView.vue') },
      { path: 'orders', component: () => import('../views/orders/OrdersView.vue') },
      { path: 'notifications', component: () => import('../views/notifications/NotificationsView.vue') },
      { path: 'reports', component: () => import('../views/reports/ReportsView.vue') },
      { path: 'settings/users', component: () => import('../views/settings/UsersView.vue') },
    ]
  },
  { path: '/:pathMatch(.*)*', redirect: '/' }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

router.beforeEach((to) => {
  const authStore = useAuthStore()
  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    return '/login'
  }
  if (to.meta.guest && authStore.isAuthenticated) {
    return '/'
  }
})

export default router
