export interface PlanningIndexSearchConfig {
  restBase: string
  restRoot: string
  nonce: string
  mapboxToken: string
  isLoggedIn: boolean
  userId: number
  isAdmin: boolean
  allowedAuthorities: number[]
  pluginUrl: string
  version: string
}

export interface PlanningAppMeta {
  address: string
  council_reference: string
  date_received: string
  info_url: string
  status: string
  decision: string
  est_value: string
  est_value_numeric: string
  ai_badge: string
  is_construction_job: string
}

export interface PlanningApp {
  id: number
  title: { rendered: string }
  content: { rendered: string }
  excerpt?: { rendered: string }
  link?: string
  _authority_name: string
  authority_id: number
  meta: PlanningAppMeta
  score?: number
}

export interface Authority {
  id: number
  name: string
  count?: number
}

export interface UserApp extends PlanningApp {
  timestamp?: string
  saved: boolean
}

export interface SearchFilters {
  search?: string
  authority?: number[]
  app_category?: number
  date_from?: string
  date_to?: string
}

export type SortOption = 'date_desc' | 'date_asc' | 'alpha_asc' | 'alpha_desc'

export type ViewMode = 'grid' | 'map'

export interface PaginatedAppsResult {
  apps: PlanningApp[]
  total: number
  totalPages: number
  page: number
  perPage: number
}

export interface WorkspaceAddResult {
  success: boolean
  added: boolean
  alreadyAdded?: boolean
}

export interface CheckSavedResult {
  saved: Record<number, boolean>
}

export interface SavedAppsResponse {
  apps: UserApp[]
}

export interface RecentAppsResponse {
  apps: UserApp[]
}

export interface SaveAppResponse {
  success: boolean
  saved?: boolean
  already_saved?: boolean
}

export interface UnsaveAppResponse {
  success: boolean
  removed?: boolean
}

export interface TrackViewResponse {
  success: boolean
}

export interface SavedSearch {
  id: number
  name: string
  filters: SearchFilters
  sort: SortOption
  created_at: string
}

export interface ApiError {
  status: number
  message: string
  endpoint?: string
}

declare global {
  interface Window {
    PlanningIndexSearch?: PlanningIndexSearchConfig
  }
}
