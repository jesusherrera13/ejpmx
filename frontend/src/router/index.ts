import { createRouter, createWebHistory } from 'vue-router'
// import Home from './views/Home.vue'
import Home from '@/views/Home.vue'
import Login from '@/views/Login.vue'
import AdminDashboardView from '@/views/AdminDashboardView.vue'
import TournamentConfigurationView from '@/views/TournamentConfigurationView.vue'
import GameBoxscoreView from '@/views/GameBoxscoreView.vue'
import BlogPostView from '@/views/BlogPostView.vue'
import ResultsView from '@/views/ResultsView.vue'

const routes = [
  {
    path: '/',
    name: 'Home',
    component: Home
  },
  {
    path: '/noticias/:id/:slug?',
    name: 'BlogPost',
    component: BlogPostView,
    props: true
  },
  {
    // Calendario y resultados públicos (?torneo=&fecha=&equipo=)
    path: '/resultados',
    name: 'Results',
    component: ResultsView
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
  },
  {
    // Box score de un partido del torneo (misma protección que la configuración: la da la API)
    path: '/tournament/:id/games/:gameId/boxscore',
    name: 'GameBoxscore',
    component: GameBoxscoreView,
    props: true
  }
]

export const router = createRouter({
  history: createWebHistory(),
  routes
})
