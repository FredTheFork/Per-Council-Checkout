import { useState } from 'react'
import { Loader as Loader2, X, Download, Bookmark, Settings2 } from 'lucide-react'
import { useSearchContext } from '../context/SearchContext'
import { advancedFilterCount } from '../utils/advancedFilters'
import { exportAppsToCsv } from '../utils/csvExport'
import { usePrefersReducedMotion } from '../hooks/usePrefersReducedMotion'
import { useToast } from './ToastProvider'
import { QUICK_FILTERS } from '../quickFilters'
import type { PlanningApp } from '../types'
import SortDropdown from './SortDropdown'
import ViewToggle from './ViewToggle'
import SavedSearchesDropdown from './SavedSearchesDropdown'
import SelectAllCheckbox from './SelectAllCheckbox'

interface ResultsToolbarProps {
  apps: PlanningApp[]
  loading: boolean
}

export default function ResultsToolbar({ apps, loading }: ResultsToolbarProps) {
  const {
    total,
    view,
    sort,
    filters,
    activeQuickFilter,
    selectedIds,
    setFilters,
    setQuickFilter,
    runSearch,
    toggleHighValue,
    toggleConstruction,
    openSaveSearchModal,
    paginationMode,
    setPaginationMode,
  } = useSearchContext()

  const { showToast } = useToast()
  const [exporting, setExporting] = useState(false)
  const [prefsOpen, setPrefsOpen] = useState(false)
  const prefersReducedMotion = usePrefersReducedMotion()

  const showingAll = apps.length > 0 && apps.length >= total
  const canSaveSearch =
    advancedFilterCount(filters) > 0 || !!filters.search || !!activeQuickFilter
  const effectivePaginationMode =
    prefersReducedMotion ? 'button' : paginationMode

  const handleExport = () => {
    const selectedCount = selectedIds.size
    const toExport = selectedCount > 0
      ? apps.filter((a) => selectedIds.has(a.id))
      : apps
    if (toExport.length === 0) {
      showToast('No applications to export', { type: 'info' })
      return
    }
    setExporting(true)
    try {
      exportAppsToCsv(toExport)
      showToast(
        `Exported ${toExport.length} application${toExport.length !== 1 ? 's' : ''} to CSV`,
      )
    } catch {
      showToast('Could not export to CSV', { type: 'error' })
    } finally {
      setExporting(false)
    }
  }

  const isMap = view === 'map'
  const showCount = !isMap
  const showSelectionControls = !isMap && apps.length > 0
  const showExport = !isMap
  const showSort = !isMap

  return (
    <div
      role="toolbar"
      aria-label="Results controls"
      className="sticky z-20 border-b border-slate-200 bg-white/95 backdrop-blur-sm transition-shadow"
      style={{ top: 'var(--pi-header-height, 0px)' }}
    >
      <div className="mx-auto max-w-7xl px-4 py-2 sm:px-6 lg:px-8">
        <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
          {/* Left cluster: count + filter chips */}
          <div className="flex flex-wrap items-center gap-2">
            {showCount && (
              <p className="text-sm text-slate-600">
                {loading && apps.length === 0 ? (
                  <span className="inline-flex items-center gap-1.5">
                    <Loader2 className="h-3.5 w-3.5 animate-spin text-slate-400" />
                    Loading…
                  </span>
                ) : apps.length === 0 ? (
                  'No applications found'
                ) : showingAll ? (
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
            )}

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
                    const def = QUICK_FILTERS.find((d) => d.id === activeQuickFilter)
                    setQuickFilter(null)
                    if (def) {
                      if (def.type === 'keyword') {
                        setFilters({ search: undefined })
                      } else if (def.type === 'date') {
                        setFilters({ date_from: undefined, date_to: undefined })
                      }
                    }
                    void runSearch()
                  }}
                />
              )}
              {filters.highValueOnly && (
                <FilterChip
                  label="High Value"
                  onRemove={() => {
                    toggleHighValue()
                    runSearch()
                  }}
                />
              )}
              {filters.constructionOnly && (
                <FilterChip
                  label="Construction"
                  onRemove={() => {
                    toggleConstruction()
                    runSearch()
                  }}
                />
              )}
            </div>
          </div>

          {/* Right cluster: controls */}
          <div className="flex flex-wrap items-center gap-2">
            {showSelectionControls && (
              <SelectAllCheckbox apps={apps} />
            )}

            {showExport && (
              <button
                type="button"
                onClick={handleExport}
                disabled={exporting || apps.length === 0}
                aria-label={`Export ${selectedIds.size > 0 ? selectedIds.size : apps.length} applications to CSV`}
                className="inline-flex items-center gap-1.5 rounded-md bg-white px-3 py-1.5 text-sm font-medium text-slate-600 ring-1 ring-inset ring-slate-200 transition-colors hover:bg-slate-50 hover:text-slate-900 disabled:cursor-not-allowed disabled:opacity-50"
              >
                <Download className="h-4 w-4" />
                <span className="hidden whitespace-nowrap lg:inline">
                  {selectedIds.size > 0 ? `Export (${selectedIds.size})` : 'Export CSV'}
                </span>
              </button>
            )}

            {showSort && <SortDropdown />}

            <ViewToggle />

            {/* Save search button */}
            <button
              type="button"
              onClick={openSaveSearchModal}
              disabled={!canSaveSearch}
              aria-disabled={!canSaveSearch}
              aria-pressed={false}
              title={canSaveSearch ? 'Save this search' : 'Set a filter first'}
              className="inline-flex items-center gap-1.5 rounded-md bg-white px-3 py-1.5 text-sm font-medium text-slate-600 ring-1 ring-inset ring-slate-200 transition-colors hover:bg-slate-50 hover:text-slate-900 disabled:cursor-not-allowed disabled:opacity-50"
            >
              <Bookmark className="h-4 w-4" />
              <span className="hidden whitespace-nowrap lg:inline">Save Search</span>
            </button>

            <SavedSearchesDropdown />

            {/* Preferences popover */}
            <div className="relative">
              <button
                type="button"
                onClick={() => setPrefsOpen((v) => !v)}
                aria-label="Pagination preferences"
                aria-expanded={prefsOpen}
                className="inline-flex h-8 w-8 items-center justify-center rounded-md bg-white text-slate-500 ring-1 ring-inset ring-slate-200 transition-colors hover:bg-slate-50 hover:text-slate-900"
              >
                <Settings2 className="h-4 w-4" />
              </button>
              {prefsOpen && (
                <>
                  <div
                    className="fixed inset-0 z-30"
                    onClick={() => setPrefsOpen(false)}
                    aria-hidden="true"
                  />
                  <div className="absolute right-0 z-40 mt-2 w-64 rounded-lg border border-slate-200 bg-white p-4 shadow-elevated">
                    <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                      Pagination
                    </p>
                    <div className="mt-3 space-y-2">
                      <label className="flex cursor-pointer items-center justify-between gap-3 rounded-md px-2 py-1.5 text-sm text-slate-700 hover:bg-slate-50">
                        <span>Infinite scroll</span>
                        <input
                          type="checkbox"
                          checked={effectivePaginationMode === 'infinite'}
                          disabled={prefersReducedMotion}
                          onChange={(e) =>
                            setPaginationMode(e.target.checked ? 'infinite' : 'button')
                          }
                          className="h-4 w-4 rounded border-slate-300 text-accent-600 focus:ring-accent-500"
                        />
                      </label>
                    </div>
                    {prefersReducedMotion && (
                      <p className="mt-2 text-xs text-slate-400">
                        Infinite scroll is disabled while reduced motion is on.
                      </p>
                    )}
                    <p className="mt-3 text-xs text-slate-400">
                      {effectivePaginationMode === 'infinite'
                        ? 'Next pages load automatically as you scroll.'
                        : 'Click "Load more" to fetch additional pages.'}
                    </p>
                  </div>
                </>
              )}
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

function FilterChip({
  label,
  onRemove,
}: {
  label: string
  onRemove: () => void
}) {
  return (
    <span className="inline-flex items-center gap-1 rounded-md bg-slate-100 px-2 py-0.5 text-xs font-medium capitalize text-slate-700 transition-colors hover:bg-slate-200">
      {label}
      <button
        type="button"
        onClick={onRemove}
        className="ml-0.5 inline-flex items-center rounded p-0.5 hover:bg-black/10"
        aria-label={`Remove ${label} filter`}
      >
        <X className="h-3 w-3" />
      </button>
    </span>
  )
}
