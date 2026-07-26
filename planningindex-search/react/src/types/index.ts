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
  _authority_name: string
  authority_id: number
  meta: PlanningAppMeta
}

declare global {
  interface Window {
    PlanningIndexSearch?: PlanningIndexSearchConfig
  }
}
