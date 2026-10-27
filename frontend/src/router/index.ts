import { createRouter, createWebHistory } from 'vue-router'
// import Home from './views/Home.vue'
import Home from '@/views/Home.vue'
import Login from '@/views/Login.vue'
import AdminDashboardView from '@/views/AdminDashboardView.vue'
import TournamentConfigurationView from '@/views/TournamentConfigurationView.vue'

const routes = [
  {
    path: '/',
    name: 'Home',
    component: Home
  },
  {
    path: '/login',
    name: 'Login',
    component: Login
  },
  {
    path: '/admin/dashboard',
    name: 'AdminDashboard',
    component: AdminDashboardView
  },
  {
    path: '/tournament/:id/configuration',
    name: 'TournamentConfiguration',
    component: TournamentConfigurationView,
    props: true
  }
]

export const router = createRouter({
  history: createWebHistory(),
  routes
})
