import { useMemo, useCallback } from 'react'
import { Loader as Loader2, X } from 'lucide-react'
import { useSearchContext } from '../context/SearchContext'
import { sortApps } from '../utils/sortApps'
import { advancedFilterCount } from '../utils/advancedFilters'
import type { PlanningApp } from '../types'
import AppCard from './AppCard'
import AppListRow from './AppListRow'
import SkeletonCard from './SkeletonCard'
import SkeletonRow from './SkeletonRow'
import EmptyState from './EmptyState'
import SortDropdown from './SortDropdown'
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
    setFilters,
    setQuickFilter,
    toggleHighValue,
    toggleConstruction,
  } = useSearchContext()

  const sortedApps = useMemo(
    () => sortApps(apps, sort, filters),
    [apps, sort, filters],
  )

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
  const hasActiveFilters =
    advancedFilterCount(filters) > 0 || !!filters.search || !!activeQuickFilter

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
    return <EmptyState />
  }

  // Map view
  if (view === 'map') {
    return <MapView />
  }

  return (
    <div className="space-y-4">
      {/* Results toolbar */}
      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div className="flex flex-wrap items-center gap-2">
          <p className="text-sm text-slate-600">
            {showingAll ? (
              <>
                Showing all{' '}
                <span className="font-semibold text-slate-900">
                  {total.toLocaleString('en-GB')}
                </span>{' '}
                application{total !== 1 ? 's' : ''}
              </>
            ) : (
              <>
                Showing{' '}
                <span className="font-semibold text-slate-900">
                  {apps.length.toLocaleString('en-GB')}
                </span>{' '}
                of{' '}
                <span className="font-semibold text-slate-900">
                  {total.toLocaleString('en-GB')}
                </span>{' '}
                application{total !== 1 ? 's' : ''}
              </>
            )}
          </p>

          {/* Active filter chips */}
          <div className="flex flex-wrap items-center gap-1.5">
            {filters.search && (
              <FilterChip
                label={`"${filters.search}"`}
                onRemove={() => {
                  setFilters({ search: undefined })
                  runSearch()
                }}
              />
            )}
            {activeQuickFilter && (
              <FilterChip
                label={activeQuickFilter.replace('_', ' ')}
                onRemove={() => {
                  setQuickFilter(null)
                  runSearch()
                }}
              />
            )}
            {filters.highValueOnly && (
              <FilterChip
                label="High Value"
                color="success"
                onRemove={() => {
                  toggleHighValue()
                  runSearch()
                }}
              />
            )}
            {filters.constructionOnly && (
              <FilterChip
                label="Construction"
                color="brand"
                onRemove={() => {
                  toggleConstruction()
                  runSearch()
                }}
              />
            )}
          </div>
        </div>

        <SortDropdown />
      </div>

      {/* Grid or List */}
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
          {sortedApps.map((app) => (
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
            />
          ))}
        </div>
      ) : (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          {sortedApps.map((app) => (
            <AppCard
              key={app.id}
              app={app}
              saved={savedIds.has(app.id)}
              inWorkspace={workspaceIds.has(app.id)}
              selected={selectedIds.has(app.id)}
              onToggleSave={handleToggleSave}
              onAddToWorkspace={addToWorkspace}
              onToggleSelect={toggleSelected}
              onViewDetails={handleViewDetails}
            />
          ))}
        </div>
      )}

      {/* Load more / end of results */}
      {!loading && apps.length > 0 && (
        <div className="flex flex-col items-center gap-2 py-4">
          {hasMore ? (
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
          ) : (
            <p className="text-sm text-slate-400">
              You've reached the end — all {total.toLocaleString('en-GB')} applications loaded
            </p>
          )}
        </div>
      )}
    </div>
  )
}

function FilterChip({
  label,
  color = 'slate',
  onRemove,
}: {
  label: string
  color?: 'slate' | 'success' | 'brand'
  onRemove: () => void
}) {
  const colorClasses = {
    slate: 'bg-slate-100 text-slate-700 hover:bg-slate-200',
    success: 'bg-success-100 text-success-700 hover:bg-success-200',
    brand: 'bg-brand-100 text-brand-700 hover:bg-brand-200',
  }

  return (
    <span
      className={`inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium capitalize transition-colors ${colorClasses[color]}`}
    >
      {label}
      <button
        type="button"
        onClick={onRemove}
        className="ml-0.5 inline-flex items-center rounded-full p-0.5 hover:bg-black/10"
        aria-label={`Remove ${label} filter`}
      >
        <X className="h-3 w-3" />
      </button>
    </span>
  )
}
