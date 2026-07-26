import type { PaginationMode, SortOption, ViewMode } from '../types'

export interface UserPreferences {
  sort: SortOption
  view: ViewMode
  paginationMode: PaginationMode
}

const DEFAULTS: UserPreferences = {
  sort: 'date_desc',
  view: 'grid',
  paginationMode: 'button',
}

function storageKey(userId: number): string {
  return `pis_prefs_${userId}`
}

function safeParse(raw: string | null): Partial<UserPreferences> | null {
  if (!raw) return null
  try {
    const parsed = JSON.parse(raw)
    if (typeof parsed !== 'object' || parsed === null) return null
    return parsed
  } catch {
    console.warn('[PlanningIndexSearch] Corrupt preferences JSON, using defaults')
    return null
  }
}

export function loadPreferences(userId: number): UserPreferences {
  if (!userId || userId <= 0) return { ...DEFAULTS }
  const parsed = safeParse(localStorage.getItem(storageKey(userId)))
  if (!parsed) return { ...DEFAULTS }
  return {
    sort: typeof parsed.sort === 'string' ? (parsed.sort as SortOption) : DEFAULTS.sort,
    view: typeof parsed.view === 'string' ? (parsed.view as ViewMode) : DEFAULTS.view,
    paginationMode:
      typeof parsed.paginationMode === 'string'
        ? (parsed.paginationMode as PaginationMode)
        : DEFAULTS.paginationMode,
  }
}

export function persistPreferences(userId: number, prefs: UserPreferences): void {
  if (!userId || userId <= 0) return
  try {
    localStorage.setItem(storageKey(userId), JSON.stringify(prefs))
  } catch {
    console.warn('[PlanningIndexSearch] Could not persist preferences')
  }
}

export function persistPreference<K extends keyof UserPreferences>(
  userId: number,
  key: K,
  value: UserPreferences[K],
): void {
  if (!userId || userId <= 0) return
  const current = loadPreferences(userId)
  persistPreferences(userId, { ...current, [key]: value })
}
