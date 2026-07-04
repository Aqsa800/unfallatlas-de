import { createRouter, createWebHistory } from 'vue-router'

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: '/',
      name: 'dashboard',
      component: () => import('../views/DashboardView.vue')
    },
    {
      path: '/accidents',
      name: 'accidents',
      component: () => import('../views/AccidentsView.vue')
    },
    {
      path: '/regions',
      name: 'regions',
      component: () => import('../views/RegionsView.vue')
    },
    {
      path: '/stats',
      name: 'stats',
      component: () => import('../views/StatsView.vue')
    },
    {
      path: '/api-explorer',
      name: 'api-explorer',
      component: () => import('../views/ApiExplorerView.vue')
    }
  ]
})

export default router
