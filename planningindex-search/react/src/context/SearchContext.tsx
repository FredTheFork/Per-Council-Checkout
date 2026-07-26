import {
  createContext,
  useContext,
  useMemo,
  useRef,
  useState,
  type ReactNode,
} from 'react'
import {
  fetchAllowedAuthorities,
  fetchAllAppsForMap,
  fetchAppById,
  fetchApps,
  fetchCategories,
  fetchRecentApps,
  fetchSavedApps,
  fetchSearchCount,
  checkSaved,
  saveApp,
  unsaveApp,
  trackView,
  addToWorkspace,
} from '../api'
import {
  loadSavedSearches,
  persistSavedSearches,
  MAX_SAVED_SEARCHES,
} from '../utils/savedSearchStorage'
import { isHighValue, isConstructionJob } from '../utils'
import type {
  ApiError,
  Authority,
  Category,
  PlanningApp,
  QuickFilterId,
  SavedSearch,
  SearchFilters,
  SortOption,
  UserApp,
  ViewMode,
} from '../types'
import { config } from '../config'

export interface SearchState {
  filters: SearchFilters
  activeQuickFilter: QuickFilterId | null
  sort: SortOption
  view: ViewMode
  apps: PlanningApp[]
  rawApps: PlanningApp[]
  total: number
  totalPages: number
  page: number
  perPage: number
  loading: boolean
  loadingMore: boolean
  loadingMap: boolean
  error: ApiError | null
  savedIds: Set<number>
  recentIds: Set<number>
  workspaceIds: Set<number>
  selectedIds: Set<number>
  allowedAuthorities: Authority[]
  categories: Category[]
  mapApps: PlanningApp[]
}

export interface SearchContextValue extends SearchState {
  selectedAppId: number | null
  isMyAppsOpen: boolean
  savedApps: UserApp[]
  recentApps: UserApp[]
  loadingMyApps: boolean
  savedSearches: SavedSearch[]
  saveSearchModalOpen: boolean
  loadingSavedSearchCounts: boolean
  openSaveSearchModal: () => void
  closeSaveSearchModal: () => void
  saveCurrentSearch: (name: string) => boolean
  deleteSavedSearch: (id: number) => void
  applySavedSearch: (search: SavedSearch) => void
  refreshSavedSearchCounts: () => Promise<void>
  openMyApps: () => void
  closeMyApps: () => void
  refreshMyApps: () => Promise<void>
  runSearch: () => Promise<void>
  loadMore: () => Promise<void>
  setFilters: (partial: Partial<SearchFilters>) => void
  clearFilters: () => void
  setQuickFilter: (id: QuickFilterId | null) => void
  toggleHighValue: () => void
  toggleConstruction: () => void
  toggleHideSaved: () => void
  toggleHideViewed: () => void
  toggleHideWorkspace: () => void
  setValueRange: (min: number | undefined, max: number | undefined) => void
  setSort: (sort: SortOption) => void
  switchView: (view: ViewMode) => Promise<void>
  saveApp: (id: number) => Promise<void>
  unsaveApp: (id: number) => Promise<void>
  trackView: (id: number) => void
  addToWorkspace: (id: number) => Promise<void>
  refreshSavedState: () => Promise<void>
  toggleSelected: (id: number) => void
  selectAll: (ids: number[]) => void
  clearSelection: () => void
  openDetailPanel: (id: number) => void
  closeDetailPanel: () => void
  fetchAppById: (id: number, signal?: AbortSignal) => Promise<PlanningApp>
  fetchSavedApps: () => Promise<UserApp[]>
  fetchRecentApps: () => Promise<UserApp[]>
}

const SearchContext = createContext<SearchContextValue | null>(null)

const DEFAULT_FILTERS: SearchFilters = {}
const DEFAULT_SORT: SortOption = 'date_desc'
const DEFAULT_VIEW: ViewMode = 'grid'
const DEFAULT_PER_PAGE = 40

function applyClientFilters(
  apps: PlanningApp[],
  filters: SearchFilters,
  savedIds?: Set<number>,
  recentIds?: Set<number>,
  workspaceIds?: Set<number>,
): PlanningApp[] {
  let out = apps
  if (filters.highValueOnly) {
    out = out.filter((a) => isHighValue(a.meta))
  }
  if (filters.constructionOnly) {
    out = out.filter((a) => isConstructionJob(a.meta))
  }
  if (filters.estValueMin != null) {
    out = out.filter((a) => {
      const n = parseFloat(a.meta.est_value_numeric)
      return !isNaN(n) && n >= (filters.estValueMin as number)
    })
  }
  if (filters.estValueMax != null) {
    out = out.filter((a) => {
      const n = parseFloat(a.meta.est_value_numeric)
      return !isNaN(n) && n <= (filters.estValueMax as number)
    })
  }
  if (filters.hideSaved && savedIds && savedIds.size) {
    out = out.filter((a) => !savedIds.has(a.id))
  }
  if (filters.hideViewed && recentIds && recentIds.size) {
    out = out.filter((a) => !recentIds.has(a.id))
  }
  if (filters.hideWorkspace && workspaceIds && workspaceIds.size) {
    out = out.filter((a) => !workspaceIds.has(a.id))
  }
  return out
}

export function SearchProvider({ children }: { children: ReactNode }) {
  const [filters, setFiltersState] = useState<SearchFilters>(DEFAULT_FILTERS)
  const [activeQuickFilter, setActiveQuickFilter] = useState<QuickFilterId | null>(null)
  const [sort, setSortState] = useState<SortOption>(DEFAULT_SORT)
  const [view, setView] = useState<ViewMode>(DEFAULT_VIEW)
  const [apps, setApps] = useState<PlanningApp[]>([])
  const [rawApps, setRawApps] = useState<PlanningApp[]>([])
  const [total, setTotal] = useState(0)
  const [totalPages, setTotalPages] = useState(0)
  const [page, setPage] = useState(1)
  const [perPage] = useState(DEFAULT_PER_PAGE)
  const [loading, setLoading] = useState(false)
  const [loadingMore, setLoadingMore] = useState(false)
  const [loadingMap, setLoadingMap] = useState(false)
  const [error, setError] = useState<ApiError | null>(null)
  const [savedIds, setSavedIds] = useState<Set<number>>(new Set())
  const [recentIds, setRecentIds] = useState<Set<number>>(new Set())
  const [workspaceIds, setWorkspaceIds] = useState<Set<number>>(new Set())
  const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set())
  const [allowedAuthorities, setAllowedAuthorities] = useState<Authority[]>([])
  const [categories, setCategories] = useState<Category[]>([])
  const [mapApps, setMapApps] = useState<PlanningApp[]>([])
  const [selectedAppId, setSelectedAppId] = useState<number | null>(null)
  const [isMyAppsOpen, setIsMyAppsOpen] = useState(false)
  const [savedApps, setSavedApps] = useState<UserApp[]>([])
  const [recentApps, setRecentApps] = useState<UserApp[]>([])
  const [loadingMyApps, setLoadingMyApps] = useState(false)
  const [savedSearches, setSavedSearches] = useState<SavedSearch[]>([])
  const [saveSearchModalOpen, setSaveSearchModalOpen] = useState(false)
  const [loadingSavedSearchCounts, setLoadingSavedSearchCounts] = useState(false)

  const savedSearchesRef = useRef<SavedSearch[]>(savedSearches)
  savedSearchesRef.current = savedSearches

  const abortRef = useRef<AbortController | null>(null)
  const mapAbortRef = useRef<AbortController | null>(null)
  const filtersRef = useRef<SearchFilters>(filters)
  const sortRef = useRef<SortOption>(sort)
  const pageRef = useRef<number>(page)
  const viewRef = useRef<ViewMode>(view)
  const mapLoadedForRef = useRef<string | null>(null)
  const savedIdsRef = useRef<Set<number>>(savedIds)
  const recentIdsRef = useRef<Set<number>>(recentIds)
  const workspaceIdsRef = useRef<Set<number>>(workspaceIds)

  filtersRef.current = filters
  sortRef.current = sort
  pageRef.current = page
  viewRef.current = view
  savedIdsRef.current = savedIds
  recentIdsRef.current = recentIds
  workspaceIdsRef.current = workspaceIds

  const runSearch = async (): Promise<void> => {
    abortRef.current?.abort()
    const controller = new AbortController()
    abortRef.current = controller

    setLoading(true)
    setError(null)
    setPage(1)
    pageRef.current = 1

    try {
      const result = await fetchApps(filtersRef.current, 1, DEFAULT_PER_PAGE, controller.signal)
      if (controller.signal.aborted) return
      setRawApps(result.apps)
      setApps(applyClientFilters(result.apps, filtersRef.current, savedIdsRef.current, recentIdsRef.current, workspaceIdsRef.current))
      setTotal(result.total)
      setTotalPages(result.totalPages)
    } catch (err) {
      if (controller.signal.aborted) return
      setError(err as ApiError)
    } finally {
      if (!controller.signal.aborted) setLoading(false)
    }
  }

  const loadMore = async (): Promise<void> => {
    if (loading || loadingMore || pageRef.current >= totalPages) return
    const nextPage = pageRef.current + 1
    setLoadingMore(true)
    try {
      const result = await fetchApps(filtersRef.current, nextPage, DEFAULT_PER_PAGE)
      setRawApps((prev) => {
        const next = [...prev, ...result.apps]
        setApps(applyClientFilters(next, filtersRef.current, savedIdsRef.current, recentIdsRef.current, workspaceIdsRef.current))
        return next
      })
      setPage(nextPage)
      pageRef.current = nextPage
    } catch (err) {
      setError(err as ApiError)
    } finally {
      setLoadingMore(false)
    }
  }

  const setFilters = (partial: Partial<SearchFilters>): void => {
    if ('date_from' in partial || 'date_to' in partial) {
      setActiveQuickFilter(null)
    }
    setFiltersState((prev) => ({ ...prev, ...partial }))
  }

  const clearFilters = (): void => {
    setActiveQuickFilter(null)
    setFiltersState(DEFAULT_FILTERS)
    void runSearch()
  }

  const setQuickFilter = (id: QuickFilterId | null): void => {
    setActiveQuickFilter(id)
  }

  const toggleHighValue = (): void => {
    const next = !filters.highValueOnly
    setFiltersState((prev) => ({ ...prev, highValueOnly: next }))
    setApps(applyClientFilters(rawApps, { ...filtersRef.current, highValueOnly: next }, savedIdsRef.current, recentIdsRef.current, workspaceIdsRef.current))
    if (rawApps.length === 0) void runSearch()
  }

  const toggleConstruction = (): void => {
    const next = !filters.constructionOnly
    setFiltersState((prev) => ({ ...prev, constructionOnly: next }))
    setApps(applyClientFilters(rawApps, { ...filtersRef.current, constructionOnly: next }, savedIdsRef.current, recentIdsRef.current, workspaceIdsRef.current))
    if (rawApps.length === 0) void runSearch()
  }

  const toggleHideSaved = (): void => {
    const next = !filters.hideSaved
    setFiltersState((prev) => ({ ...prev, hideSaved: next }))
    setApps(applyClientFilters(rawApps, { ...filtersRef.current, hideSaved: next }, savedIdsRef.current, recentIdsRef.current, workspaceIdsRef.current))
    if (rawApps.length === 0) void runSearch()
  }

  const toggleHideViewed = (): void => {
    const next = !filters.hideViewed
    setFiltersState((prev) => ({ ...prev, hideViewed: next }))
    setApps(applyClientFilters(rawApps, { ...filtersRef.current, hideViewed: next }, savedIdsRef.current, recentIdsRef.current, workspaceIdsRef.current))
    if (rawApps.length === 0) void runSearch()
  }

  const toggleHideWorkspace = (): void => {
    const next = !filters.hideWorkspace
    setFiltersState((prev) => ({ ...prev, hideWorkspace: next }))
    setApps(applyClientFilters(rawApps, { ...filtersRef.current, hideWorkspace: next }, savedIdsRef.current, recentIdsRef.current, workspaceIdsRef.current))
    if (rawApps.length === 0) void runSearch()
  }

  const setValueRange = (min: number | undefined, max: number | undefined): void => {
    setFiltersState((prev) => ({ ...prev, estValueMin: min, estValueMax: max }))
    setApps(applyClientFilters(rawApps, { ...filtersRef.current, estValueMin: min, estValueMax: max }, savedIdsRef.current, recentIdsRef.current, workspaceIdsRef.current))
    if (rawApps.length === 0) void runSearch()
  }

  const setSort = (newSort: SortOption): void => {
    setSortState(newSort)
  }

  const switchView = async (newView: ViewMode): Promise<void> => {
    if (newView === viewRef.current) return
    setView(newView)
    viewRef.current = newView

    if (newView === 'map') {
      const filterKey = JSON.stringify(filtersRef.current)
      if (mapLoadedForRef.current === filterKey && mapApps.length > 0) return

      mapAbortRef.current?.abort()
      const controller = new AbortController()
      mapAbortRef.current = controller
      setLoadingMap(true)
      try {
        const all = await fetchAllAppsForMap(filtersRef.current, controller.signal)
        if (controller.signal.aborted) return
        setMapApps(all)
        mapLoadedForRef.current = filterKey
      } catch (err) {
        if (controller.signal.aborted) return
        setError(err as ApiError)
      } finally {
        if (!controller.signal.aborted) setLoadingMap(false)
      }
    }
  }

  const saveAppAction = async (id: number): Promise<void> => {
    const prev = new Set(savedIds)
    setSavedIds((s) => new Set(s).add(id))
    try {
      await saveApp(id)
    } catch {
      setSavedIds(prev)
    }
  }

  const unsaveAppAction = async (id: number): Promise<void> => {
    const prev = new Set(savedIds)
    setSavedIds((s) => {
      const next = new Set(s)
      next.delete(id)
      return next
    })
    try {
      await unsaveApp(id)
    } catch {
      setSavedIds(prev)
    }
  }

  const trackViewAction = (id: number): void => {
    setRecentIds((s) => new Set(s).add(id))
    void trackView(id)
  }

  const addToWorkspaceAction = async (id: number): Promise<void> => {
    const prev = new Set(workspaceIds)
    setWorkspaceIds((s) => new Set(s).add(id))
    try {
      const result = await addToWorkspace(id)
      if (!result.success && !result.added) {
        setWorkspaceIds(prev)
      }
    } catch {
      setWorkspaceIds(prev)
    }
  }

  const toggleSelected = (id: number): void => {
    setSelectedIds((s) => {
      const next = new Set(s)
      if (next.has(id)) next.delete(id)
      else next.add(id)
      return next
    })
  }

  const selectAll = (ids: number[]): void => {
    setSelectedIds(new Set(ids))
  }

  const clearSelection = (): void => {
    setSelectedIds(new Set())
  }

  const openDetailPanel = (id: number): void => {
    setSelectedAppId(id)
    void trackViewAction(id)
  }

  const closeDetailPanel = (): void => {
    setSelectedAppId(null)
  }

  const refreshMyApps = async (): Promise<void> => {
    setLoadingMyApps(true)
    try {
      const [saved, recent] = await Promise.all([fetchSavedApps(), fetchRecentApps()])
      setSavedApps(saved)
      setRecentApps(recent)
      setSavedIds(new Set(saved.map((a) => a.id)))
      setRecentIds(new Set(recent.map((a) => a.id)))
    } catch {
      // best-effort sync
    } finally {
      setLoadingMyApps(false)
    }
  }

  const openMyApps = (): void => {
    setIsMyAppsOpen(true)
    void refreshMyApps()
  }

  const closeMyApps = (): void => {
    setIsMyAppsOpen(false)
  }

  const openSaveSearchModal = (): void => {
    setSaveSearchModalOpen(true)
  }

  const closeSaveSearchModal = (): void => {
    setSaveSearchModalOpen(false)
  }

  const saveCurrentSearch = (name: string): boolean => {
    const trimmed = name.trim()
    if (!trimmed) return false
    if (savedSearchesRef.current.length >= MAX_SAVED_SEARCHES) return false

    const now = new Date().toISOString()
    const search: SavedSearch = {
      id: Date.now(),
      name: trimmed,
      filters: { ...filtersRef.current },
      sort: sortRef.current,
      created_at: now,
      lastAppliedAt: now,
      newCount: 0,
    }

    const userId = config.getUserId()
    const next = [...savedSearchesRef.current, search]
    persistSavedSearches(userId, next)
    setSavedSearches(next)
    setSaveSearchModalOpen(false)
    return true
  }

  const deleteSavedSearch = (id: number): void => {
    const userId = config.getUserId()
    const next = savedSearchesRef.current.filter((s) => s.id !== id)
    persistSavedSearches(userId, next)
    setSavedSearches(next)
  }

  const applySavedSearch = (search: SavedSearch): void => {
    const userId = config.getUserId()
    const now = new Date().toISOString()

    setActiveQuickFilter(null)
    setSortState(search.sort)
    sortRef.current = search.sort
    setFiltersState(search.filters)
    filtersRef.current = search.filters

    const next = savedSearchesRef.current.map((s) =>
      s.id === search.id ? { ...s, lastAppliedAt: now, newCount: 0 } : s,
    )
    persistSavedSearches(userId, next)
    setSavedSearches(next)

    void runSearch()
  }

  const refreshSavedSearchCounts = async (): Promise<void> => {
    if (savedSearchesRef.current.length === 0) return
    setLoadingSavedSearchCounts(true)

    const results = await Promise.allSettled(
      savedSearchesRef.current.map(async (s) => {
        const now = new Date()
        const lastApplied = s.lastAppliedAt ? new Date(s.lastAppliedAt) : null
        const filterDate = s.filters.date_from ? new Date(s.filters.date_from) : null

        let effectiveDate: Date | null = lastApplied
        if (filterDate && lastApplied) {
          effectiveDate = filterDate > lastApplied ? filterDate : lastApplied
        } else if (filterDate) {
          effectiveDate = filterDate
        }

        const dateFrom = effectiveDate
          ? effectiveDate.toISOString().slice(0, 10)
          : undefined

        const countFilters: SearchFilters = { ...s.filters }
        if (dateFrom) {
          countFilters.date_from = dateFrom
        }

        const count = await fetchSearchCount(countFilters)
        return { id: s.id, count }
      }),
    )

    const counts: Record<number, number | undefined> = {}
    for (const result of results) {
      if (result.status === 'fulfilled') {
        counts[result.value.id] = result.value.count
      }
    }

    const userId = config.getUserId()
    const next = savedSearchesRef.current.map((s) =>
      s.id in counts ? { ...s, newCount: counts[s.id] } : s,
    )
    persistSavedSearches(userId, next)
    setSavedSearches(next)
    setLoadingSavedSearchCounts(false)
  }

  const refreshSavedState = async (): Promise<void> => {
    try {
      const [saved, recent] = await Promise.all([fetchSavedApps(), fetchRecentApps()])
      const savedSet = new Set(saved.map((a) => a.id))
      const recentSet = new Set(recent.map((a) => a.id))
      setSavedIds(savedSet)
      setRecentIds(recentSet)
      const allIds = Array.from(new Set([...saved.map((a) => a.id), ...recent.map((a) => a.id)]))
      let confirmed: Set<number> | undefined
      if (allIds.length) {
        const checked = await checkSaved(allIds)
        confirmed = new Set<number>()
        for (const [id, isSaved] of Object.entries(checked.saved)) {
          if (isSaved) confirmed.add(Number(id))
        }
        setSavedIds(confirmed)
      }
      if (rawApps.length > 0) {
        setApps(applyClientFilters(rawApps, filtersRef.current, confirmed ?? savedSet, recentSet, workspaceIdsRef.current))
      }
    } catch {
      // best-effort sync
    }
  }

  // Hydrate on mount
  useMemo(() => {
    const userId = config.getUserId()
    if (userId > 0) {
      const stored = loadSavedSearches(userId)
      setSavedSearches(stored)
      savedSearchesRef.current = stored
    }

    void (async () => {
      try {
        const [authorities, cats] = await Promise.all([
          fetchAllowedAuthorities(),
          fetchCategories(),
        ])
        setAllowedAuthorities(authorities)
        setCategories(cats)
      } catch {
        // non-critical
      }
      await refreshSavedState()

      // Refresh saved search counts after a short delay
      setTimeout(() => {
        void refreshSavedSearchCounts()
      }, 500)
    })()
  }, [])

  const value = useMemo<SearchContextValue>(
    () => ({
      filters,
      activeQuickFilter,
      sort,
      view,
      apps,
      rawApps,
      total,
      totalPages,
      page,
      perPage,
      loading,
      loadingMore,
      loadingMap,
      error,
      savedIds,
      recentIds,
      workspaceIds,
      selectedIds,
      allowedAuthorities,
      categories,
      mapApps,
      selectedAppId,
      isMyAppsOpen,
      savedApps,
      recentApps,
      loadingMyApps,
      savedSearches,
      saveSearchModalOpen,
      loadingSavedSearchCounts,
      openSaveSearchModal,
      closeSaveSearchModal,
      saveCurrentSearch,
      deleteSavedSearch,
      applySavedSearch,
      refreshSavedSearchCounts,
      openMyApps,
      closeMyApps,
      refreshMyApps,
      runSearch,
      loadMore,
      setFilters,
      clearFilters,
      setQuickFilter,
      toggleHighValue,
      toggleConstruction,
      toggleHideSaved,
      toggleHideViewed,
      toggleHideWorkspace,
      setValueRange,
      setSort,
      switchView,
      saveApp: saveAppAction,
      unsaveApp: unsaveAppAction,
      trackView: trackViewAction,
      addToWorkspace: addToWorkspaceAction,
      refreshSavedState,
      toggleSelected,
      selectAll,
      clearSelection,
      openDetailPanel,
      closeDetailPanel,
      fetchAppById,
      fetchSavedApps,
      fetchRecentApps,
    }),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [filters, activeQuickFilter, sort, view, apps, rawApps, total, totalPages, page, perPage, loading, loadingMore, loadingMap, error, savedIds, recentIds, workspaceIds, selectedIds, allowedAuthorities, categories, mapApps, selectedAppId, isMyAppsOpen, savedApps, recentApps, loadingMyApps, savedSearches, saveSearchModalOpen, loadingSavedSearchCounts],
  )

  return <SearchContext.Provider value={value}>{children}</SearchContext.Provider>
}

export function useSearchContext(): SearchContextValue {
  const ctx = useContext(SearchContext)
  if (!ctx) {
    throw new Error('useSearchContext must be used within a SearchProvider')
  }
  return ctx
}
