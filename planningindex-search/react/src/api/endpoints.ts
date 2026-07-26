import { apiGet, apiPost } from './client'
import { config } from '../config'
import type {
  ApiError,
  Authority,
  Category,
  CheckSavedResult,
  PaginatedAppsResult,
  PlanningApp,
  RecentAppsResponse,
  SaveAppResponse,
  SavedAppsResponse,
  SearchFilters,
  TrackViewResponse,
  UnsaveAppResponse,
  UserApp,
  WorkspaceAddResult,
} from '../types'

const MAX_PER_PAGE = 100

function mapUserApp(raw: RawUserApp, saved: boolean): UserApp {
  return {
    id: raw.id,
    title: { rendered: raw.title ?? '' },
    content: { rendered: raw.content ?? '' },
    _authority_name: raw._authority_name ?? '',
    authority_id: 0,
    meta: raw.meta ?? {
      address: '',
      council_reference: '',
      date_received: '',
      info_url: '',
      status: '',
      decision: '',
      est_value: '',
      est_value_numeric: '',
      ai_badge: '',
      is_construction_job: '',
    },
    timestamp: raw.timestamp,
    saved,
  }
}

interface RawUserApp {
  id: number
  title?: string
  content?: string
  _authority_name?: string
  timestamp?: string
  meta?: UserApp['meta']
}

export function fetchApps(
  filters: SearchFilters,
  page: number,
  perPage: number,
  signal?: AbortSignal,
): Promise<PaginatedAppsResult> {
  const cappedPerPage = Math.min(MAX_PER_PAGE, Math.max(1, perPage))
  const params: Record<string, unknown> = {
    page: Math.max(1, page),
    per_page: cappedPerPage,
  }
  if (filters.search) params.search = filters.search
  if (filters.authority && filters.authority.length > 0) params.authority = filters.authority
  if (filters.app_category) params.app_category = filters.app_category
  if (filters.date_from) params.date_from = filters.date_from
  if (filters.date_to) params.date_to = filters.date_to

  return apiGet<PlanningApp[]>('/apps', params, signal).then(({ data, headers }) => ({
    apps: data,
    total: parseInt(headers.get('X-WP-Total') ?? '0', 10) || 0,
    totalPages: parseInt(headers.get('X-WP-TotalPages') ?? '0', 10) || 0,
    page: Math.max(1, page),
    perPage: cappedPerPage,
  }))
}

export async function fetchAllAppsForMap(
  filters: SearchFilters,
  signal?: AbortSignal,
): Promise<PlanningApp[]> {
  const all: PlanningApp[] = []
  let page = 1
  let totalPages = 1

  do {
    const result = await fetchApps(filters, page, MAX_PER_PAGE, signal)
    all.push(...result.apps)
    totalPages = result.totalPages
    page++
  } while (page <= totalPages)

  return all
}

export async function fetchAllowedAuthorities(signal?: AbortSignal): Promise<Authority[]> {
  try {
    const { data } = await apiGet<Authority[]>('/allowed-authorities', undefined, signal)
    if (Array.isArray(data)) return data
  } catch {
    // Endpoint not yet available — fall back to deriving from config term IDs.
  }
  return config.getAllowedAuthorities().map((id) => ({ id, name: '' }))
}

export async function fetchCategories(signal?: AbortSignal): Promise<Category[]> {
  try {
    const { data } = await apiGet<Category[]>('/app-categories', undefined, signal)
    if (Array.isArray(data)) {
      return data.filter((c): c is Category => typeof c?.id === 'number' && typeof c?.name === 'string')
    }
  } catch {
    // Endpoint not yet available — panel hides the control gracefully.
  }
  return []
}

export async function fetchSavedApps(signal?: AbortSignal): Promise<UserApp[]> {
  const { data } = await apiGet<SavedAppsResponse>('/user-apps/saved', undefined, signal)
  return (data.apps ?? []).map((raw) => mapUserApp(raw as unknown as RawUserApp, true))
}

export async function fetchRecentApps(signal?: AbortSignal): Promise<UserApp[]> {
  const { data } = await apiGet<RecentAppsResponse>('/user-apps/recent', undefined, signal)
  return (data.apps ?? []).map((raw) => mapUserApp(raw as unknown as RawUserApp, false))
}

export async function saveApp(id: number, signal?: AbortSignal): Promise<SaveAppResponse> {
  const { data } = await apiPost<SaveAppResponse>('/user-apps/save', { post_id: id }, signal)
  return data
}

export async function unsaveApp(id: number, signal?: AbortSignal): Promise<UnsaveAppResponse> {
  const { data } = await apiPost<UnsaveAppResponse>('/user-apps/unsave', { post_id: id }, signal)
  return data
}

export async function trackView(id: number, signal?: AbortSignal): Promise<TrackViewResponse> {
  const { data } = await apiPost<TrackViewResponse>('/user-apps/view', { post_id: id }, signal)
  return data
}

export async function checkSaved(
  ids: number[],
  signal?: AbortSignal,
): Promise<CheckSavedResult> {
  if (!ids.length) return { saved: {} }
  const { data } = await apiPost<CheckSavedResult>('/user-apps/check-saved', { post_ids: ids }, signal)
  return data
}

export async function addToWorkspace(
  id: number,
  signal?: AbortSignal,
): Promise<WorkspaceAddResult> {
  try {
    const { data } = await apiPost<WorkspaceAddResult>('/workspace/add', { post_id: id }, signal)
    return data
  } catch {
    return { success: false, added: false }
  }
}

export async function fetchAppById(
  id: number,
  signal?: AbortSignal,
): Promise<PlanningApp> {
  const { data } = await apiGet<PlanningApp[]>(`/apps`, { include: id, per_page: 1 }, signal)
  if (Array.isArray(data) && data.length > 0) return data[0]
  throw { status: 404, message: 'Application not found', endpoint: '/apps' } as ApiError
}

export async function fetchSearchCount(
  filters: SearchFilters,
  signal?: AbortSignal,
): Promise<number> {
  const params: Record<string, unknown> = {
    page: 1,
    per_page: 1,
  }
  if (filters.search) params.search = filters.search
  if (filters.authority && filters.authority.length > 0) params.authority = filters.authority
  if (filters.app_category) params.app_category = filters.app_category
  if (filters.date_from) params.date_from = filters.date_from
  if (filters.date_to) params.date_to = filters.date_to

  const { headers } = await apiGet<PlanningApp[]>('/apps', params, signal)
  return parseInt(headers.get('X-WP-Total') ?? '0', 10) || 0
}
