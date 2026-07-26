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
  fetchApps,
  fetchCategories,
  fetchRecentApps,
  fetchSavedApps,
  checkSaved,
  saveApp,
  unsaveApp,
  trackView,
  addToWorkspace,
} from '../api'
import { isHighValue, isConstructionJob } from '../utils'
import type {
  ApiError,
  Authority,
  Category,
  PlanningApp,
  QuickFilterId,
  SearchFilters,
  SortOption,
  ViewMode,
} from '../types'

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
  loadingMap: boolean
  error: ApiError | null
  savedIds: Set<number>
  recentIds: Set<number>
  workspaceIds: Set<number>
  allowedAuthorities: Authority[]
  categories: Category[]
  mapApps: PlanningApp[]
}

export interface SearchContextValue extends SearchState {
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
  const [loadingMap, setLoadingMap] = useState(false)
  const [error, setError] = useState<ApiError | null>(null)
  const [savedIds, setSavedIds] = useState<Set<number>>(new Set())
  const [recentIds, setRecentIds] = useState<Set<number>>(new Set())
  const [workspaceIds, setWorkspaceIds] = useState<Set<number>>(new Set())
  const [allowedAuthorities, setAllowedAuthorities] = useState<Authority[]>([])
  const [categories, setCategories] = useState<Category[]>([])
  const [mapApps, setMapApps] = useState<PlanningApp[]>([])

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
    if (loading || pageRef.current >= totalPages) return
    const nextPage = pageRef.current + 1
    setLoading(true)
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
      setLoading(false)
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
      loadingMap,
      error,
      savedIds,
      recentIds,
      workspaceIds,
      allowedAuthorities,
      categories,
      mapApps,
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
    }),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [filters, activeQuickFilter, sort, view, apps, rawApps, total, totalPages, page, perPage, loading, loadingMap, error, savedIds, recentIds, workspaceIds, allowedAuthorities, categories, mapApps],
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
