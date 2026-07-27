import { useMemo, useCallback } from 'react'
import { Loader as Loader2 } from 'lucide-react'
import { useSearchContext } from '../context/SearchContext'
import { sortApps } from '../utils/sortApps'
import { usePrefersReducedMotion } from '../hooks/usePrefersReducedMotion'
import { useInfiniteScroll } from '../hooks/useInfiniteScroll'
import { useRovingFocus } from '../hooks/useRovingFocus'
import type { PlanningApp } from '../types'
import AppCard from './AppCard'
import AppListRow from './AppListRow'
import SkeletonCard from './SkeletonCard'
import SkeletonRow from './SkeletonRow'
import EmptyState from './EmptyState'
import ResultsToolbar from './ResultsToolbar'
import MapView from './MapView'

const SKELETON_GRID_COUNT = 6
const SKELETON_LIST_COUNT = 10

export default function ResultsArea() {
  const {
    apps,
    total,
    page,
    totalPages,
    loading,
    loadingMore,
    error,
    view,
    sort,
    filters,
    activeQuickFilter,
    savedIds,
    workspaceIds,
    selectedIds,
    runSearch,
    loadMore,
    saveApp,
    unsaveApp,
    addToWorkspace,
    openDetailPanel,
    toggleSelected,
    paginationMode,
  } = useSearchContext()

  const prefersReducedMotion = usePrefersReducedMotion()
  const effectivePaginationMode = prefersReducedMotion ? 'button' : paginationMode

  const sortedApps = useMemo(
    () => sortApps(apps, sort, filters),
    [apps, sort, filters],
  )

  const rovingFocus = useRovingFocus({
    itemCount: sortedApps.length,
    enabled: view !== 'map' && !loading,
  })

  const handleToggleSave = useCallback(
    (id: number) => {
      if (savedIds.has(id)) {
        return unsaveApp(id)
      }
      return saveApp(id)
    },
    [savedIds, saveApp, unsaveApp],
  )

  const handleViewDetails = useCallback(
    (app: PlanningApp) => {
      openDetailPanel(app.id)
    },
    [openDetailPanel],
  )

  const hasMore = page < totalPages
  const showingAll = !hasMore && apps.length > 0

  // Infinite scroll sentinel
  const sentinelRef = useInfiniteScroll({
    enabled: effectivePaginationMode === 'infinite' && view !== 'map',
    hasMore,
    loading: loadingMore,
    onLoadMore: loadMore,
  })

  // Error state
  if (error && apps.length === 0 && !loading) {
    return (
      <div className="mx-auto max-w-md rounded-xl border border-error-200 bg-error-50 p-6 text-center">
        <p className="text-sm font-medium text-error-700">
          Something went wrong loading results.
        </p>
        <p className="mt-1 text-xs text-error-600">{error.message}</p>
        <button type="button" onClick={() => runSearch()} className="btn-secondary mt-4">
          Retry
        </button>
      </div>
    )
  }

  // Empty state
  if (!loading && apps.length === 0 && !error) {
    return (
      <div className="space-y-4">
        <ResultsToolbar apps={sortedApps} loading={loading} />
        <EmptyState />
      </div>
    )
  }

  // Map view
  if (view === 'map') {
    return (
      <div className="space-y-4 pi-fade-in" key={`map-${view}`}>
        <ResultsToolbar apps={[]} loading={loading} />
        <MapView />
      </div>
    )
  }

  const viewKey = `${view}-${loading}`

  return (
    <div className="space-y-4">
      <ResultsToolbar apps={sortedApps} loading={loading} />

      {/* Skip to results link for screen readers */}
      <a
        href="#pi-results"
        className="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-brand-600 focus:px-4 focus:py-2 focus:text-white"
      >
        Skip to results
      </a>

      {/* Grid or List */}
      <div
        id="pi-results"
        ref={rovingFocus.containerRef}
        key={viewKey}
        className={loading && apps.length === 0 ? '' : 'pi-fade-in'}
      >
        {loading && apps.length === 0 ? (
          view === 'list' ? (
            <div className="card overflow-hidden">
              {Array.from({ length: SKELETON_LIST_COUNT }).map((_, i) => (
                <SkeletonRow key={i} />
              ))}
            </div>
          ) : (
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
              {Array.from({ length: SKELETON_GRID_COUNT }).map((_, i) => (
                <SkeletonCard key={i} />
              ))}
            </div>
          )
        ) : view === 'list' ? (
          <div className="card overflow-hidden">
            {/* Column header */}
            <div className="hidden items-center gap-3 border-b border-slate-200 bg-slate-50 px-4 py-2 text-xs font-semibold uppercase tracking-wide text-slate-400 md:flex">
              <span className="w-4" />
              <span className="w-2.5" />
              <span className="w-28">Council</span>
              <span className="flex-1">Address</span>
              <span className="w-24 text-right sm:block">Value</span>
              <span className="hidden w-24 lg:block">Date</span>
              <span className="hidden w-10 text-right lg:block">Score</span>
              <span className="hidden w-28 xl:block">Reference</span>
              <span className="w-20" />
            </div>
            {sortedApps.map((app, idx) => (
              <AppListRow
                key={app.id}
                app={app}
                saved={savedIds.has(app.id)}
                inWorkspace={workspaceIds.has(app.id)}
                selected={selectedIds.has(app.id)}
                onToggleSave={handleToggleSave}
                onAddToWorkspace={addToWorkspace}
                onToggleSelect={toggleSelected}
                onViewDetails={handleViewDetails}
                index={idx}
                getTabIndex={rovingFocus.getTabIndex}
                setItemRef={rovingFocus.setItemRef}
                onKeyDown={rovingFocus.handleKeyDown}
              />
            ))}
          </div>
        ) : (
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            {sortedApps.map((app, idx) => (
              <div
                key={app.id}
                className={prefersReducedMotion ? '' : 'pi-card-enter'}
                style={prefersReducedMotion ? undefined : { animationDelay: `${Math.min(idx * 30, 300)}ms` }}
              >
                <AppCard
                  app={app}
                  saved={savedIds.has(app.id)}
                  inWorkspace={workspaceIds.has(app.id)}
                  selected={selectedIds.has(app.id)}
                  onToggleSave={handleToggleSave}
                  onAddToWorkspace={addToWorkspace}
                  onToggleSelect={toggleSelected}
                  onViewDetails={handleViewDetails}
                  index={idx}
                  getTabIndex={rovingFocus.getTabIndex}
                  setItemRef={rovingFocus.setItemRef}
                  onKeyDown={rovingFocus.handleKeyDown}
                />
              </div>
            ))}
          </div>
        )}
      </div>

      {/* Loading more indicator / Load more button / End of results */}
      {!loading && apps.length > 0 && (
        <>
          {loadingMore && effectivePaginationMode === 'infinite' && (
            <div
              className="flex items-center justify-center gap-2 py-4 text-sm text-slate-500 pi-fade-in"
              role="status"
              aria-live="polite"
            >
              <Loader2 className="h-4 w-4 animate-spin" />
              Loading more applications…
            </div>
          )}

          {hasMore && effectivePaginationMode === 'button' ? (
            <div className="flex flex-col items-center gap-2 py-4">
              <button
                type="button"
                onClick={loadMore}
                disabled={loadingMore}
                className="btn-primary"
              >
                {loadingMore ? (
                  <>
                    <Loader2 className="h-4 w-4 animate-spin" />
                    Loading more…
                  </>
                ) : (
                  'Load more'
                )}
              </button>
            </div>
          ) : showingAll ? (
            <p className="py-4 text-center text-sm text-slate-400 pi-fade-in">
              You've reached the end — all {total.toLocaleString('en-GB')} applications loaded
            </p>
          ) : null}

          {/* Infinite scroll sentinel */}
          {effectivePaginationMode === 'infinite' && hasMore && !loadingMore && (
            <div ref={sentinelRef} aria-hidden="true" className="h-1 w-full" />
          )}
        </>
      )}
    </div>
  )
}
