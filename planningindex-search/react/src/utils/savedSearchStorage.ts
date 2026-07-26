import type { SavedSearch } from '../types'

const LEGACY_KEY = 'pis_saved_searches'
const MAX_SAVED_SEARCHES = 20

function storageKey(userId: number): string {
  return `pis_saved_searches_${userId}`
}

function safeParse(raw: string | null): SavedSearch[] | null {
  if (!raw) return null
  try {
    const parsed = JSON.parse(raw)
    if (!Array.isArray(parsed)) return null
    return parsed.filter(
      (s): s is SavedSearch =>
        typeof s === 'object' &&
        s !== null &&
        typeof s.id === 'number' &&
        typeof s.name === 'string' &&
        typeof s.filters === 'object' &&
        typeof s.sort === 'string' &&
        typeof s.created_at === 'string',
    )
  } catch {
    console.warn('[PlanningIndexSearch] Corrupt saved searches JSON, starting fresh')
    return null
  }
}

export function loadSavedSearches(userId: number): SavedSearch[] {
  const key = storageKey(userId)

  // Try the namespaced key first
  const raw = localStorage.getItem(key)
  const parsed = safeParse(raw)
  if (parsed) return parsed

  // Migration: check legacy key
  const legacyRaw = localStorage.getItem(LEGACY_KEY)
  const legacyParsed = safeParse(legacyRaw)
  if (legacyParsed && legacyParsed.length > 0) {
    try {
      localStorage.setItem(key, JSON.stringify(legacyParsed))
      localStorage.removeItem(LEGACY_KEY)
    } catch {
      // ignore write errors
    }
    return legacyParsed
  }

  return []
}

export function persistSavedSearches(userId: number, searches: SavedSearch[]): void {
  try {
    localStorage.setItem(storageKey(userId), JSON.stringify(searches))
  } catch {
    console.warn('[PlanningIndexSearch] Could not persist saved searches')
  }
}

export function addSavedSearch(
  userId: number,
  searches: SavedSearch[],
  search: SavedSearch,
): SavedSearch[] {
  const next = [...searches, search]
  persistSavedSearches(userId, next)
  return next
}

export function removeSavedSearch(
  userId: number,
  searches: SavedSearch[],
  id: number,
): SavedSearch[] {
  const next = searches.filter((s) => s.id !== id)
  persistSavedSearches(userId, next)
  return next
}

export function updateSavedSearchTimestamp(
  userId: number,
  searches: SavedSearch[],
  id: number,
  timestamp: string,
): SavedSearch[] {
  const next = searches.map((s) =>
    s.id === id ? { ...s, lastAppliedAt: timestamp, newCount: 0 } : s,
  )
  persistSavedSearches(userId, next)
  return next
}

export function updateSavedSearchCount(
  userId: number,
  searches: SavedSearch[],
  id: number,
  count: number | undefined,
): SavedSearch[] {
  const next = searches.map((s) => (s.id === id ? { ...s, newCount: count } : s))
  persistSavedSearches(userId, next)
  return next
}

export function updateAllSavedSearchCounts(
  userId: number,
  searches: SavedSearch[],
  counts: Record<number, number | undefined>,
): SavedSearch[] {
  const next = searches.map((s) =>
    s.id in counts ? { ...s, newCount: counts[s.id] } : s,
  )
  persistSavedSearches(userId, next)
  return next
}

export { MAX_SAVED_SEARCHES }
