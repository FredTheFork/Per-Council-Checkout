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
  fetchRecentApps,
  fetchSavedApps,
  checkSaved,
  saveApp,
  unsaveApp,
  trackView,
  addToWorkspace,
} from '../api'
import type {
  ApiError,
  Authority,
  PlanningApp,
  SearchFilters,
  SortOption,
  ViewMode,
} from '../types'

export interface SearchState {
  filters: SearchFilters
  sort: SortOption
  view: ViewMode
  apps: PlanningApp[]
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
  mapApps: PlanningApp[]
}

export interface SearchContextValue extends SearchState {
  runSearch: () => Promise<void>
  loadMore: () => Promise<void>
  setFilters: (partial: Partial<SearchFilters>) => void
  clearFilters: () => void
  setSort: (sort: SortOption) => void
  switchView: (view: ViewMode) => void
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

export function SearchProvider({ children }: { children: ReactNode }) {
  const [filters, setFiltersState] = useState<SearchFilters>(DEFAULT_FILTERS)
  const [sort, setSortState] = useState<SortOption>(DEFAULT_SORT)
  const [view, setView] = useState<ViewMode>(DEFAULT_VIEW)
  const [apps, setApps] = useState<PlanningApp[]>([])
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
  const [mapApps, setMapApps] = useState<PlanningApp[]>([])

  const abortRef = useRef<AbortController | null>(null)
  const mapAbortRef = useRef<AbortController | null>(null)
  const filtersRef = useRef<SearchFilters>(filters)
  const sortRef = useRef<SortOption>(sort)
  const pageRef = useRef<number>(page)
  const viewRef = useRef<ViewMode>(view)
  const mapLoadedForRef = useRef<string | null>(null)

  filtersRef.current = filters
  sortRef.current = sort
  pageRef.current = page
  viewRef.current = view

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
      setApps(result.apps)
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
      setApps((prev) => [...prev, ...result.apps])
      setPage(nextPage)
      pageRef.current = nextPage
    } catch (err) {
      setError(err as ApiError)
    } finally {
      setLoading(false)
    }
  }

  const setFilters = (partial: Partial<SearchFilters>): void => {
    setFiltersState((prev) => ({ ...prev, ...partial }))
  }

  const clearFilters = (): void => {
    setFiltersState(DEFAULT_FILTERS)
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
      setSavedIds(new Set(saved.map((a) => a.id)))
      setRecentIds(new Set(recent.map((a) => a.id)))
      const allIds = Array.from(new Set([...saved.map((a) => a.id), ...recent.map((a) => a.id)]))
      if (allIds.length) {
        const checked = await checkSaved(allIds)
        const confirmed = new Set<number>()
        for (const [id, isSaved] of Object.entries(checked.saved)) {
          if (isSaved) confirmed.add(Number(id))
        }
        setSavedIds(confirmed)
      }
    } catch {
      // best-effort sync
    }
  }

  // Hydrate on mount
  useMemo(() => {
    void (async () => {
      try {
        const authorities = await fetchAllowedAuthorities()
        setAllowedAuthorities(authorities)
      } catch {
        // non-critical
      }
      await refreshSavedState()
    })()
  }, [])

  const value = useMemo<SearchContextValue>(
    () => ({
      filters,
      sort,
      view,
      apps,
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
      mapApps,
      runSearch,
      loadMore,
      setFilters,
      clearFilters,
      setSort,
      switchView,
      saveApp: saveAppAction,
      unsaveApp: unsaveAppAction,
      trackView: trackViewAction,
      addToWorkspace: addToWorkspaceAction,
      refreshSavedState,
    }),
    // eslint-disable-next-line react-hooks/exhaustive-deps
    [filters, sort, view, apps, total, totalPages, page, perPage, loading, loadingMap, error, savedIds, recentIds, workspaceIds, allowedAuthorities, mapApps],
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
