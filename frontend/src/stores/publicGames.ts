import { defineStore } from 'pinia'
import axiosClient from '@/axios'

// Calendario y resultados del portal (sin sesión): solo torneos públicos y activos

export interface PublicTeam {
  id: number
  name: string
  short_name: string | null
  abbreviation: string | null
}

export interface PublicTournament {
  id: number
  name: string
  games_count: number
  first_date: string | null
  last_date: string | null
  teams: PublicTeam[]
}

export type GameStatus = 'scheduled' | 'in_progress' | 'final' | 'postponed' | 'suspended' | 'cancelled'

export interface PublicGameSide extends Partial<PublicTeam> {
  record: string
  runs: number | null
  hits: number | null
  errors: number | null
  line: Array<number | null> | null
}

export interface PublicGame {
  id: number
  date: string
  time: string | null
  status: GameStatus
  round: number | null
  matchday: number | null
  series: number | null
  series_game: number | null
  game_number: number | null
  away: PublicGameSide
  home: PublicGameSide
}

export interface PublicGamesPage {
  tournament: { id: number, name: string }
  from: string | null
  to: string | null
  prev_date: string | null
  next_date: string | null
  games: PublicGame[]
}

export interface GamesQuery {
  date?: string
  around?: string
  before?: number
  after?: number
  team_id?: number | null
}

export interface StandingRow {
  team: PublicTeam
  w: number
  l: number
  pct: number | null
  gb: number
  rs: number
  ra: number
  diff: number
  last10: string
  streak: string | null
  home: string
  away: string
  expected: string | null
  vs_500: string
}

export interface PublicStandings {
  tournament: { id: number, name: string }
  date: string
  games_played: number
  games_total: number
  standings: StandingRow[]
}

export interface LeaderEntry {
  name: string
  position?: string | null
  team: { id: number, name: string, abbreviation: string | null }
  value: number
  display: string
}

export interface LeaderCategory {
  key: string
  label: string
  abbr: string
  type: 'player' | 'team'
  leaders: LeaderEntry[]
}

export interface PublicLeaders {
  tournament: { id: number, name: string } | null
  date?: string
  games?: number
  categories: LeaderCategory[]
}

const STORAGE_KEY = 'ejpmx.portal.tournament'

let tournamentsRequest: Promise<PublicTournament[]> | null = null

export const usePublicGamesStore = defineStore('publicGames', {
  state: () => ({
    tournaments: [] as PublicTournament[],
    loaded: false,
    loading: false,
    error: null as string | null,
  }),

  actions: {
    async fetchTournaments (force = false) {
      if (this.loaded && !force) return this.tournaments
      // Varios componentes lo piden al montar: comparten la misma petición
      if (tournamentsRequest && !force) return tournamentsRequest

      this.loading = true
      this.error = null
      tournamentsRequest = (async () => {
        try {
          const response = await axiosClient.get('/public/tournaments')
          this.tournaments = Array.isArray(response.data) ? response.data : []
          this.loaded = true
        } catch (error) {
          console.error('Error al obtener los torneos públicos:', error)
          this.error = 'No se pudieron cargar los torneos.'
        } finally {
          this.loading = false
          tournamentsRequest = null
        }
        return this.tournaments
      })()
      return tournamentsRequest
    },

    async fetchGames (tournamentId: number, params: GamesQuery = {}): Promise<PublicGamesPage> {
      const response = await axiosClient.get(`/public/tournaments/${tournamentId}/games`, { params })
      return response.data
    },

    async fetchDates (tournamentId: number, teamId?: number | null): Promise<string[]> {
      const response = await axiosClient.get(`/public/tournaments/${tournamentId}/dates`, { params: { team_id: teamId || undefined } })
      return Array.isArray(response.data) ? response.data : []
    },

    // Posiciones de algunos torneos al azar (portada y página de posiciones)
    async fetchFeaturedStandings (count = 3): Promise<PublicStandings[]> {
      const response = await axiosClient.get('/public/standings/featured', { params: { count } })
      return Array.isArray(response.data) ? response.data : []
    },

    async fetchStandings (tournamentId: number): Promise<PublicStandings> {
      const response = await axiosClient.get(`/public/tournaments/${tournamentId}/standings`)
      return response.data
    },

    // Líderes de un torneo; sin torneo, el servidor elige uno al azar
    async fetchLeaders (tournamentId?: number | null): Promise<PublicLeaders> {
      const response = await axiosClient.get('/public/leaders', { params: { tournament_id: tournamentId || undefined } })
      return response.data
    },

    // Torneo a mostrar: el último que eligió el visitante; si no, el que juega hoy o el más cercano a hoy
    defaultTournamentId (): number | null {
      if (!this.tournaments.length) return null

      let stored: number | null = null
      try {
        stored = Number(localStorage.getItem(STORAGE_KEY)) || null
      } catch { /* sin almacenamiento */ }
      if (stored && this.tournaments.some(t => t.id === stored)) return stored

      const today = todayString()
      const distance = (t: PublicTournament) => {
        if (!t.first_date || !t.last_date) return Number.POSITIVE_INFINITY
        if (t.first_date <= today && today <= t.last_date) return 0
        const edge = today < t.first_date ? t.first_date : t.last_date
        return Math.abs(new Date(edge).getTime() - new Date(today).getTime())
      }

      return [...this.tournaments].sort((a, b) => distance(a) - distance(b))[0].id
    },

    rememberTournament (id: number) {
      try {
        localStorage.setItem(STORAGE_KEY, String(id))
      } catch { /* sin almacenamiento */ }
    },
  },
})

// ---- Utilidades de presentación compartidas por el ticker y la página de Resultados ----

export function todayString (): string {
  const now = new Date()
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`
}

// "2026-12-01" como fecha local (sin desfase por zona horaria)
export function parseDay (day: string): Date {
  const [y, m, d] = day.split('-').map(Number)
  return new Date(y, m - 1, d)
}

export function addDays (day: string, days: number): string {
  const date = parseDay(day)
  date.setDate(date.getDate() + days)
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

// "19:00" -> "7:00 PM"
export function formatTime (time: string | null): string {
  if (!time) return 'Por definir'
  const [h, m] = time.split(':').map(Number)
  const suffix = h >= 12 ? 'PM' : 'AM'
  return `${((h + 11) % 12) + 1}:${String(m).padStart(2, '0')} ${suffix}`
}

export const STATUS_LABELS: Record<GameStatus, string> = {
  scheduled: 'Programado',
  in_progress: 'En juego',
  final: 'Final',
  postponed: 'Pospuesto',
  suspended: 'Suspendido',
  cancelled: 'Cancelado',
}

export function teamLabel (team: Partial<PublicTeam>): string {
  return team.abbreviation || team.short_name || team.name || '—'
}

// Color estable por equipo para su avatar (los equipos aún no tienen logo)
const PALETTE = ['#1E56B8', '#C62828', '#2E7D32', '#6A1B9A', '#EF6C00', '#00838F', '#4E342E', '#AD1457', '#283593', '#558B2F']
export function teamColor (team: Partial<PublicTeam>): string {
  const key = String(team.id ?? team.name ?? '')
  let hash = 0
  for (const char of key) hash = (hash * 31 + char.charCodeAt(0)) >>> 0
  return PALETTE[hash % PALETTE.length]
}

// .596 (o "-" sin partidos) y juegos detrás ("-" para el líder)
export function formatPct (pct: number | null): string {
  return pct === null ? '-' : pct.toFixed(3).replace(/^0/, '')
}

export function formatGb (gb: number): string {
  return gb === 0 ? '-' : gb.toFixed(1)
}

export function teamInitials (team: Partial<PublicTeam>): string {
  return (team.abbreviation || team.short_name || team.name || '?').slice(0, 3).toUpperCase()
}
